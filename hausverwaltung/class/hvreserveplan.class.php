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
				$details[$bid][$yr][] = array('label' => $it->label, 'amount' => $amt * $sh);
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
