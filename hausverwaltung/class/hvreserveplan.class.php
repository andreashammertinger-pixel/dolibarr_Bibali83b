<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

/**
 * Rücklagen-Prognose je Gebäude:
 *  Startstand = aktueller Kontostand des Rücklagenkontos
 *  Zuführung  = Monatsrücklage aller Einheiten des Gebäudes (Satz × m²); im laufenden Jahr nur die Restmonate
 *  Entnahme   = Planpositionen, Betrag × (1 + Baukostensteigerung)^(Jahr − aktuelles Jahr);
 *               Positionen "alle Gebäude" werden nach ihrem Schlüssel auf die Gebäude aufgeteilt
 *  Mindestsatz = kleinster gleichbleibender Satz €/m²/Monat, mit dem der Stand nie negativ wird
 */
class HvReservePlan
{
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public static function inflation()
	{
		return (float) getDolGlobalString('HAUSVERWALTUNG_PLAN_INFLATION', '3');
	}

	public static function horizon()
	{
		return max(5, min(40, getDolGlobalInt('HAUSVERWALTUNG_PLAN_YEARS', 20)));
	}

	/** Anteile eines Gebäudes an "alle Gebäude" je Schlüssel */
	public static function buildingShares(HvCalculator $calc, $key)
	{
		$sums = $calc->sums($key);
		if ($sums[0] <= 0) {
			$sums = $calc->sums('area');
		}
		$out = array();
		foreach ($calc->buildings as $bid => $b) {
			$out[$bid] = $sums[0] > 0 ? ($sums[$bid] ?? 0) / $sums[0] : 0;
		}
		return $out;
	}

	/**
	 * @return array{years:int[], buildings:array<int,array>}
	 */
	public function projection()
	{
		$calc = new HvCalculator($this->db);
		$rows = $calc->compute();
		$repo = new HvRepository($this->db);
		$items = $repo->getReservePlan(true);
		$y0 = (int) dol_print_date(dol_now(), '%Y');
		$m0 = (int) dol_print_date(dol_now(), '%m');
		$years = range($y0, $y0 + self::horizon() - 1);
		$infl = self::inflation() / 100;

		$out = array();
		foreach ($calc->buildings as $bid => $b) {
			$monthly = 0;
			$area = 0;
			foreach ($rows as $r) {
				if ((int) $r['unit']->fk_building === (int) $bid) {
					$monthly += $r['reserve'];
					$area += (float) $r['area'];
				}
			}
			$balance = 0.0;
			$account = null;
			if (!empty($b->fk_bank_reserve)) {
				$acc = new Account($this->db);
				if ($acc->fetch($b->fk_bank_reserve) > 0) {
					$balance = (float) $acc->solde(1);
					$account = $acc->ref.' '.$acc->label;
				}
			}
			$out[$bid] = array('building' => $b, 'monthly' => $monthly, 'area' => $area, 'start' => $balance, 'account' => $account, 'rows' => array(), 'min_balance' => null, 'shortfall_year' => null, 'required_rate' => 0.0, 'current_rate' => $area > 0 ? $monthly / $area : 0);
		}
		// Entnahmen je Gebäude und Jahr
		$withdraw = array();
		$details = array();
		foreach ($items as $it) {
			$yr = (int) $it->year_planned;
			if ($yr < $y0 || $yr > end($years)) {
				continue;
			}
			$amt = (float) $it->amount * pow(1 + $infl, $yr - $y0);
			if ((int) $it->fk_building > 0) {
				$shares = array((int) $it->fk_building => 1.0);
			} else {
				$shares = self::buildingShares($calc, $it->alloc_key ?: 'area');
			}
			foreach ($shares as $bid => $sh) {
				if (!isset($out[$bid]) || $sh <= 0) {
					continue;
				}
				$withdraw[$bid][$yr] = ($withdraw[$bid][$yr] ?? 0) + $amt * $sh;
				$details[$bid][$yr][] = array('label' => $it->label, 'amount' => $amt * $sh, 'obj' => $it);
			}
		}
		foreach ($out as $bid => &$o) {
			$bal = $o['start'];
			$cumMonths = 0;
			$cumW = 0;
			$need = 0;
			foreach ($years as $yr) {
				$months = ($yr === $y0) ? (12 - $m0 + 1) : 12;
				$in = $o['monthly'] * $months;
				$w = $withdraw[$bid][$yr] ?? 0;
				$start = $bal;
				$bal = $bal + $in - $w;
				$cumMonths += $months;
				$cumW += $w;
				if ($cumMonths > 0) {
					$need = max($need, ($cumW - $o['start']) / $cumMonths);
				}
				$o['rows'][$yr] = array('start' => $start, 'in' => $in, 'out' => $w, 'end' => $bal, 'items' => $details[$bid][$yr] ?? array());
				if ($o['min_balance'] === null || $bal < $o['min_balance']) {
					$o['min_balance'] = $bal;
				}
				if ($bal < 0 && $o['shortfall_year'] === null) {
					$o['shortfall_year'] = $yr;
				}
			}
			$o['required_rate'] = $o['area'] > 0 ? max(0, $need) / $o['area'] : 0;
		}
		unset($o);
		return array('years' => $years, 'buildings' => $out, 'inflation' => self::inflation());
	}
}

/**
 * Festlegung des Rücklagen-Satzes je Haus: gesetzliches Minimum, Mindestsatz laut Plan, Faktor oder manuell.
 * Jede Festlegung wird mit allen Grundlagen protokolliert (llx_hv_reserve_rate_log).
 */
class HvReserveRate
{
	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public static function modes()
	{
		return array('plan', 'factor', 'manual', 'legal');
	}

	/** Gesetzlicher Mindestsatz €/m² und Monat: Prozentsatz × Etalonpreis ÷ 12 (ZUOZ) */
	public static function legalMin()
	{
		$pct = (float) price2num(getDolGlobalString('HAUSVERWALTUNG_RESERVE_LEGAL_PCT', '0.54'));
		$etalon = (float) price2num(getDolGlobalString('HAUSVERWALTUNG_ETALON_PRICE', '796.34'));
		return $pct / 100 * $etalon / 12;
	}

	/** auf 2 Nachkommastellen aufrunden (der Satz liegt nie unter dem Minimum) */
	public static function ceil2($x)
	{
		return ceil(round((float) $x * 100, 6)) / 100;
	}

	/** Satz nach Festlegungsart */
	public static function compute($mode, $factor, $manual, $planMin, $legal)
	{
		if ($mode === 'legal') {
			return self::ceil2($legal);
		}
		if ($mode === 'plan') {
			return self::ceil2(max($planMin, $legal));
		}
		if ($mode === 'factor') {
			return self::ceil2(max($planMin * max(0, (float) $factor), $legal));
		}
		return round(max(0, (float) $manual), 4);
	}

	/**
	 * Satz eines Hauses festlegen und protokollieren.
	 *
	 * @return float|false neuer Satz
	 */
	public function setRate(User $user, $bid, $mode, $factor, $manual, $note, array $proj)
	{
		global $conf;
		$bid = (int) $bid;
		if (!in_array($mode, self::modes(), true)) {
			$mode = 'manual';
		}
		$res = $this->db->query("SELECT * FROM ".$this->db->prefix()."hv_building WHERE rowid = ".$bid." AND entity = ".((int) $conf->entity));
		$b = $res ? $this->db->fetch_object($res) : null;
		if (!$b) {
			$this->error = 'building';
			return false;
		}
		$pb = $proj['buildings'][$bid] ?? null;
		$planMin = $pb ? (float) $pb['required_rate'] : 0;
		$legal = self::legalMin();
		$new = self::compute($mode, $factor, $manual, $planMin, $legal);
		// Planvolumen des Hauses (heutige Preise): eigene Positionen + Anteil an gemeinsamen Positionen
		$planTotal = 0;
		$calc = new HvCalculator($this->db);
		foreach ((new HvRepository($this->db))->getReservePlan(true) as $it) {
			if ((int) $it->fk_building === $bid) {
				$planTotal += (float) $it->amount;
			} elseif ((int) $it->fk_building === 0) {
				$sh = HvReservePlan::buildingShares($calc, $it->alloc_key ?: 'area');
				$planTotal += (float) $it->amount * ($sh[$bid] ?? 0);
			}
		}
		$this->db->begin();
		$ok = $this->db->query("UPDATE ".$this->db->prefix()."hv_building SET reserve_mode = '".$this->db->escape($mode)."', reserve_factor = ".((float) $factor).", reserve_rate = ".price2num($new).", reserve_note = '".$this->db->escape((string) $note)."' WHERE rowid = ".$bid);
		$ok = $ok && $this->db->query("INSERT INTO ".$this->db->prefix()."hv_reserve_rate_log (entity, fk_building, datec, fk_user, mode, factor, rate_old, rate_new, legal_min, plan_min, plan_total, inflation, horizon, balance, note) VALUES ("
			.((int) $conf->entity).", ".$bid.", '".$this->db->idate(dol_now())."', ".((int) $user->id).", '".$this->db->escape($mode)."', ".((float) $factor).", "
			.price2num((float) $b->reserve_rate).", ".price2num($new).", ".price2num($legal).", ".price2num($planMin).", ".price2num($planTotal).", "
			.price2num((float) ($proj['inflation'] ?? 0)).", ".((int) count($proj['years'] ?? array())).", ".price2num($pb ? (float) $pb['start'] : 0).", '".$this->db->escape((string) $note)."')");
		if (!$ok) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return $new;
	}

	/** Verlauf (neueste zuerst) */
	public function history($limit = 50)
	{
		global $conf;
		$out = array();
		$res = $this->db->query("SELECT l.*, u.login FROM ".$this->db->prefix()."hv_reserve_rate_log l LEFT JOIN ".$this->db->prefix()."user u ON u.rowid = l.fk_user WHERE l.entity = ".((int) $conf->entity)." ORDER BY l.datec DESC, l.rowid DESC LIMIT ".((int) $limit));
		while ($res && ($o = $this->db->fetch_object($res))) {
			$out[] = $o;
		}
		return $out;
	}

	/**
	 * Einmalig: bisherige Sätze je Apartment als Satz des Hauses übernehmen (nur wenn im Haus einheitlich).
	 */
	public static function migrate($db)
	{
		global $conf;
		if (getDolGlobalString('HAUSVERWALTUNG_RESERVE_RATE_MIGRATED') === '1') {
			return;
		}
		$p = $db->prefix();
		$res = $db->query("SELECT b.rowid, b.reserve_rate, MIN(u.reserve_rate) as rmin, MAX(u.reserve_rate) as rmax FROM ".$p."hv_building b INNER JOIN ".$p."hv_unit u ON u.fk_building = b.rowid AND u.active = 1 WHERE b.entity = ".((int) $conf->entity)." GROUP BY b.rowid, b.reserve_rate");
		while ($res && ($o = $db->fetch_object($res))) {
			if ((float) $o->reserve_rate == 0 && (float) $o->rmin > 0 && abs((float) $o->rmax - (float) $o->rmin) < 0.00001) {
				$db->query("UPDATE ".$p."hv_building SET reserve_mode = 'manual', reserve_factor = 1, reserve_rate = ".price2num((float) $o->rmin).", reserve_note = 'Übernommen aus den bisherigen Sätzen der Apartments' WHERE rowid = ".((int) $o->rowid));
				$db->query("INSERT INTO ".$p."hv_reserve_rate_log (entity, fk_building, datec, mode, factor, rate_old, rate_new, legal_min, note) VALUES (".((int) $conf->entity).", ".((int) $o->rowid).", '".$db->idate(dol_now())."', 'manual', 1, ".price2num((float) $o->rmin).", ".price2num((float) $o->rmin).", ".price2num(self::legalMin()).", 'Übernommen aus den bisherigen Sätzen der Apartments')");
			}
		}
		dolibarr_set_const($db, 'HAUSVERWALTUNG_RESERVE_RATE_MIGRATED', '1', 'chaine', 0, '', $conf->entity);
	}
}
