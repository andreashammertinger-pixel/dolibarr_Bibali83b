<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

/**
 * Erzeugt die monatlichen Vorschreibungen (Kundenrechnungen) je Einheit
 * und verwaltet die dafür nötigen Dienstleistungen.
 */
class HvInvoicer
{
	const REF_BK = 'HV-BK';
	const REF_ADMIN = 'HV-VW';
	const REF_RESERVE_PREFIX = 'HV-RL-';

	public $db;
	public $error = '';
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Anzahl Monate von 'YYYY-MM' bis 'YYYY-MM' (inklusive), 0 bei ungültig */
	public static function months($from, $to)
	{
		if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $from, $a) || !preg_match('/^(\d{4})-(\d{2})$/', (string) $to, $b)) {
			return 0;
		}
		$n = ((int) $b[1] - (int) $a[1]) * 12 + ((int) $b[2] - (int) $a[2]) + 1;
		return $n > 0 ? $n : 0;
	}

	/** Anzeige des Zeitraums: 09/2027 bzw. 01–12/2027 bzw. 11/2026–02/2027 */
	public static function periodLabel($from, $to = '')
	{
		$to = $to ?: $from;
		if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $from, $a) || !preg_match('/^(\d{4})-(\d{2})$/', (string) $to, $b)) {
			return (string) $from;
		}
		if ($from === $to) {
			return $a[2].'/'.$a[1];
		}
		return $a[1] === $b[1] ? $a[2].'–'.$b[2].'/'.$a[1] : $a[2].'/'.$a[1].'–'.$b[2].'/'.$b[1];
	}

	/**
	 * Zahlungsplan: monatliche Raten (letzte Rate gleicht Rundung aus).
	 *
	 * @return array<int,array{due:int,amount:float,label:string}>
	 */
	/** Frist für bereits überfällige Raten (Tage ab Freigabe) */
	public static function lateDays()
	{
		return max(1, getDolGlobalInt('HAUSVERWALTUNG_LATE_DAYS', 7));
	}

	/** Ausstellungszeitpunkt: Freigabe, bei Entwurf/Vorschau heute */
	public static function issued($f)
	{
		return (!empty($f->statut) || !empty($f->status)) && !empty($f->date_validation) ? (int) $f->date_validation : dol_now();
	}

	/** Fälligkeit verschieben, wenn sie zum Ausstellungszeitpunkt schon vorbei ist */
	public static function shiftDue($due, $issued)
	{
		if ($issued > 0 && $due > 0 && dol_mktime(23, 59, 59, (int) dol_print_date($due, '%m'), (int) dol_print_date($due, '%d'), (int) dol_print_date($due, '%Y')) < $issued) {
			$d = $issued + self::lateDays() * 86400;
			return array(dol_mktime(12, 0, 0, (int) dol_print_date($d, '%m'), (int) dol_print_date($d, '%d'), (int) dol_print_date($d, '%Y')), true);
		}
		return array($due, false);
	}

	/** Fälligkeit einer Einzel-Vorschreibung unter Berücksichtigung verspäteter Ausstellung */
	public static function effectiveDue($f)
	{
		return self::shiftDue((int) $f->date_lim_reglement, self::issued($f));
	}

	public static function installments($total, $from, $to, $issued = 0)
	{
		$n = self::months($from, $to);
		if ($n <= 0) {
			return array();
		}
		$dueDay = max(1, min(28, getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5)));
		preg_match('/^(\d{4})-(\d{2})$/', $from, $a);
		$rate = round((float) $total / $n, 2);
		$out = array();
		$sum = 0;
		for ($i = 0; $i < $n; $i++) {
			$y = (int) $a[1] + intdiv((int) $a[2] - 1 + $i, 12);
			$mo = ((int) $a[2] - 1 + $i) % 12 + 1;
			$amt = ($i === $n - 1) ? round((float) $total - $sum, 2) : $rate;
			$sum += $amt;
			list($due, $late) = self::shiftDue(dol_mktime(12, 0, 0, $mo, $dueDay, $y), (int) $issued);
			$out[] = array('due' => $due, 'late' => $late, 'amount' => $amt, 'label' => sprintf('%02d/%04d', $mo, $y));
		}
		return $out;
	}

	public static function vatRate()
	{
		return (float) getDolGlobalString('HAUSVERWALTUNG_VAT_RATE', '0');
	}

	/**
	 * Dienstleistung anhand Ref holen oder anlegen.
	 *
	 * @return int product id
	 */
	public function ensureProduct(User $user, $ref, $label, $description = '', $kpd = '')
	{
		$p = new Product($this->db);
		if ($p->fetch(0, $ref) > 0) {
			return (int) $p->id;
		}
		$p = new Product($this->db);
		$p->ref = $ref;
		$p->label = $label;
		$p->description = $description;
		$p->type = Product::TYPE_SERVICE;
		$p->status = 1;
		$p->status_buy = 0;
		$p->price = 0;
		$p->price_base_type = 'HT';
		$p->tva_tx = self::vatRate();
		if ($kpd !== '') {
			$p->array_options = array('options_kpd' => $kpd);
		}
		$id = $p->create($user);
		if ($id <= 0) {
			$this->error = $p->error;
			$this->errors = array_merge($this->errors, $p->errors);
			return -1;
		}
		return (int) $id;
	}

	public function ensureBaseProducts(User $user)
	{
		$a = $this->ensureProduct($user, self::REF_BK, 'Betriebskosten lt. Aufstellung (Akonto)', 'Monatliche Akontozahlung Betriebskosten, Verteilung nach Wohnfläche');
		// KPD 68.32.01 = Verwaltung von Wohnimmobilien gegen Entgelt (Vorschlag, vom Steuerberater bestätigen lassen)
		$b = $this->ensureProduct($user, self::REF_ADMIN, 'Verwaltungskosten', 'Verwaltungshonorar je m² Wohnfläche', '68.32.01');
		return ($a > 0 && $b > 0) ? 1 : -1;
	}

	/** Rücklage-Dienstleistung für ein Gebäude sicherstellen und am Gebäude speichern */
	public function ensureReserveProduct(User $user, $building)
	{
		if (!empty($building->fk_product_reserve)) {
			$p = new Product($this->db);
			if ($p->fetch((int) $building->fk_product_reserve) > 0) {
				return (int) $p->id;
			}
		}
		$id = $this->ensureProduct($user, self::REF_RESERVE_PREFIX.dol_string_nospecial($building->ref), 'Rücklage '.$building->label, 'Zuführung zur Rücklage (pričuva) '.$building->label);
		if ($id > 0) {
			$this->db->query("UPDATE ".$this->db->prefix()."hv_building SET fk_product_reserve = ".((int) $id)." WHERE rowid = ".((int) $building->rowid));
			$building->fk_product_reserve = $id;
		}
		return $id;
	}

	/**
	 * Eingangsrechnung (Entwurf) des Hausverwalters über das Monatshonorar anlegen.
	 * Lieferant = HAUSVERWALTUNG_MANAGER_SOCID, Betrag = Summe m² × Satz (netto) + USt.
	 * Die Rechnungsnummer des Verwalters wird beim Erhalt der echten Rechnung nachgetragen.
	 *
	 * @return array{status:string,message:string,invoice_id:int,total:float}
	 */
	public function generateManagerFee(User $user, $period, $periodTo = '')
	{
		global $conf, $langs;
		$res = array('status' => 'skip', 'message' => '', 'invoice_id' => 0, 'total' => 0);
		$socid = getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID');
		$periodTo = $periodTo ?: $period;
		$nMonths = self::months($period, $periodTo);
		if ($socid <= 0 || !preg_match('/^(\d{4})-(\d{2})$/', $period, $m) || $nMonths <= 0) {
			$res['message'] = $langs->trans('HvNoManager');
			return $res;
		}
		$refsupplier = 'HON-'.$period.($periodTo !== $period ? '..'.$periodTo : '');
		$sql = "SELECT rowid FROM ".$this->db->prefix()."facture_fourn WHERE entity = ".((int) $conf->entity)." AND fk_soc = ".$socid." AND ref_supplier = '".$this->db->escape($refsupplier)."'";
		$q = $this->db->query($sql);
		if ($q && ($o = $this->db->fetch_object($q))) {
			$res['message'] = $langs->trans('HvAlreadyExists');
			$res['invoice_id'] = (int) $o->rowid;
			return $res;
		}
		$calc = new HvCalculator($this->db);
		$net = 0;
		foreach ($calc->compute() as $r) {
			$net += $r['admin_net'];
		}
		$vat = HvCalculator::adminVatRate();
		$net = $net * $nMonths;
		$mlabel = self::periodLabel($period, $periodTo);
		$this->db->begin();
		$ff = new FactureFournisseur($this->db);
		$ff->socid = $socid;
		$ff->ref_supplier = $refsupplier;
		$ff->date = dol_mktime(12, 0, 0, (int) $m[2], 1, (int) $m[1]);
		$ff->type = FactureFournisseur::TYPE_STANDARD;
		$ff->label = $langs->transnoentities('HvManagerFeeLabel', $mlabel);
		$ff->note_private = $langs->transnoentities('HvManagerFeeNote');
		$ff->array_options = array('options_hv_costitem' => null, 'options_hv_reserve' => 0);
		$id = $ff->create($user);
		if ($id > 0 && $ff->addline($ff->label, price2num($net, 'MU'), $vat, 0, 0, 1, 0, 0, 0, 0, 0, 0, 'HT', 1) <= 0) {
			$id = -1;
		}
		if ($id <= 0) {
			$this->db->rollback();
			$res['status'] = 'error';
			$res['message'] = $ff->error.' '.implode(', ', $ff->errors);
			return $res;
		}
		$this->db->commit();
		$ff->fetch($id);
		$res['status'] = 'ok';
		$res['invoice_id'] = (int) $id;
		$res['total'] = (float) $ff->total_ttc;
		return $res;
	}

	/**
	 * Aktive Kontakte der Einheit mit Kennzeichen "Eigentümer" (Zusatzfeld hv_owner), in Reihenfolge der Anlage.
	 * Andere Kontakte (Mieter, Notfallkontakt …) erhalten keine Dokumente.
	 * Ohne markierten Eigentümer: keine (dann wird die Adresse des Geschäftspartners verwendet).
	 *
	 * @return int[]
	 */
	public function ownerContacts($socid)
	{
		$ids = array();
		$sql = "SELECT sp.rowid FROM ".$this->db->prefix()."socpeople sp INNER JOIN ".$this->db->prefix()."socpeople_extrafields e ON e.fk_object = sp.rowid";
		$sql .= " WHERE sp.fk_soc = ".((int) $socid)." AND sp.statut = 1 AND e.hv_owner = 1 ORDER BY sp.rowid";
		$res = $this->db->query($sql);
		while ($res && ($o = $this->db->fetch_object($res))) {
			$ids[] = (int) $o->rowid;
		}
		return $ids;
	}

	/** Namen der markierten Eigentümer (für Übersichten) */
	public function ownerNames($socid)
	{
		$names = array();
		foreach ($this->ownerContacts($socid) as $cid) {
			$c = new Contact($this->db);
			if ($c->fetch($cid) > 0) {
				$names[] = $c->getFullName($GLOBALS['langs']);
			}
		}
		return $names;
	}

	/**
	 * Stimmt eine Vorschreibung noch mit den aktuellen Sätzen überein?
	 * Vergleicht je Position (Betriebskosten, Rücklage, Verwaltung) den Monatsbetrag der Rechnung mit dem heute berechneten.
	 * Guthaben-Zeilen u. ä. ohne diese Produkte werden ignoriert.
	 *
	 * @return array<int,array{label:string,inv:float,cur:float}> Abweichungen (leer = passt)
	 */
	public function compareWithCurrent(Facture $f)
	{
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
		$f->fetch_optionals();
		$unitId = (int) ($f->array_options['options_hv_unit'] ?? 0);
		$period = (string) ($f->array_options['options_hv_period'] ?? '');
		$periodTo = (string) ($f->array_options['options_hv_period_to'] ?? '') ?: $period;
		if (self::$calcCache === null) {
			self::$calcCache = new HvCalculator($this->db);
			self::$rowsCache = self::$calcCache->compute();
		}
		$calc = self::$calcCache;
		$rows = self::$rowsCache;
		if ($unitId <= 0 || empty($rows[$unitId]) || !preg_match('/^\d{4}-\d{2}$/', $period)) {
			return array();
		}
		$r = $rows[$unitId];
		$area = (float) $r['area'];
		$multi = self::months($period, $periodTo) > 1;
		$building = $calc->buildings[(int) $r['unit']->fk_building] ?? null;
		$pBk = new Product($this->db);
		$pBk->fetch(0, self::REF_BK);
		$pAd = new Product($this->db);
		$pAd->fetch(0, self::REF_ADMIN);
		$map = array();
		if ($pBk->id > 0) {
			$map[(int) $pBk->id] = array('HvBk', (float) $r['bk_rate']);
		}
		if ($building && (int) $building->fk_product_reserve > 0) {
			$map[(int) $building->fk_product_reserve] = array('HvReserve', (float) $r['reserve_rate']);
		}
		if ($pAd->id > 0) {
			$map[(int) $pAd->id] = array('HvAdmin', (float) $r['admin_rate']);
		}
		$seen = array();
		$out = array();
		foreach ($f->lines as $l) {
			$pid = (int) $l->fk_product;
			if ($pid <= 0 || (float) $l->subprice < 0) {
				continue; // freie Zeilen und Guthaben-Verrechnung
			}
			if (!isset($map[$pid])) {
				// Position mit einem anderen Produkt (z. B. früheres Rücklage-Produkt) -> gehört nicht zur heutigen Berechnung
				$mi = $multi ? (float) $l->subprice : (float) $l->subprice * (float) $l->qty;
				$out[] = array('label' => 'HvOtherLine', 'inv' => $area > 0 ? $mi / $area : 0, 'cur' => 0.0);
				continue;
			}
			if (isset($seen[$pid])) {
				$out[] = array('label' => $map[$pid][0], 'inv' => 0.0, 'cur' => $map[$pid][1]); // doppelte Position
				continue;
			}
			$seen[$pid] = true;
			$monthInv = $multi ? (float) $l->subprice : (float) $l->subprice * (float) $l->qty;
			$monthCur = round($map[$pid][1] * $area, 2);
			if (abs($monthInv - $monthCur) > 0.011) {
				$out[] = array('label' => $map[$pid][0], 'inv' => $area > 0 ? $monthInv / $area : 0, 'cur' => $map[$pid][1]);
			}
		}
		foreach ($map as $pid => $m) {
			if (!isset($seen[$pid]) && round($m[1] * $area, 2) != 0.0) {
				$out[] = array('label' => $m[0], 'inv' => 0.0, 'cur' => $m[1]);
			}
		}
		return $out;
	}

	/** Berechnung je Seitenaufruf nur einmal (viele Vorschreibungen prüfen) */
	protected static $calcCache = null;
	protected static $rowsCache = null;

	public static function resetCache()
	{
		self::$calcCache = null;
		self::$rowsCache = null;
	}

	/**
	 * Offene Vorschreibungen (Entwurf oder freigegeben, nicht bezahlt), die nicht mehr zu den aktuellen Sätzen passen.
	 *
	 * @return array<int,array{f:Facture,diffs:array,paid:float,new_total:float}>
	 */
	public function outdatedInvoices()
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$out = array();
		$sql = "SELECT f.rowid FROM ".$this->db->prefix()."facture f INNER JOIN ".$this->db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid";
		$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.type = 0 AND f.fk_statut IN (0, 1) AND f.paye = 0 AND fe.hv_unit > 0 AND fe.hv_period NOT LIKE '%-A'";
		$sql .= " ORDER BY fe.hv_period, f.ref";
		$res = $this->db->query($sql);
		$ids = array();
		while ($res && ($o = $this->db->fetch_object($res))) {
			$ids[] = (int) $o->rowid;
		}
		foreach ($ids as $id) {
			$f = new Facture($this->db);
			if ($f->fetch($id) <= 0) {
				continue;
			}
			$diffs = $this->compareWithCurrent($f);
			if ($diffs && !$this->isKept($id, self::diffText($diffs, $GLOBALS['langs']))) {
				$out[$id] = array('f' => $f, 'diffs' => $diffs, 'paid' => (float) $f->getSommePaiement(), 'new_total' => $this->newTotal($f));
			}
		}
		return $out;
	}

	/** Betrag der Vorschreibung nach Neuberechnung (Positionen neu, übrige Zeilen wie Guthaben unverändert) */
	public function newTotal(Facture $f)
	{
		$plan = $this->recalcLines($f);
		if (!$plan) {
			return (float) $f->total_ttc;
		}
		$t = 0.0;
		foreach ($f->lines as $l) {
			if ((int) $l->fk_product <= 0 || (float) $l->subprice < 0) {
				$t += (float) $l->total_ttc;
			}
		}
		foreach ($plan['lines'] as $l) {
			$t += round($l['price'] * $l['qty'], 2) * (1 + $plan['vat'] / 100);
		}
		return round($t, 2);
	}

	/** Neue Positionen einer Vorschreibung nach den aktuellen Sätzen (wie beim Erzeugen) */
	protected function recalcLines(Facture $f)
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$f->fetch_optionals();
		$unitId = (int) ($f->array_options['options_hv_unit'] ?? 0);
		$period = (string) ($f->array_options['options_hv_period'] ?? '');
		$periodTo = (string) ($f->array_options['options_hv_period_to'] ?? '') ?: $period;
		if (self::$calcCache === null) {
			self::$calcCache = new HvCalculator($this->db);
			self::$rowsCache = self::$calcCache->compute();
		}
		$r = self::$rowsCache[$unitId] ?? null;
		if (!$r || !preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
			return null;
		}
		$n = self::months($period, $periodTo);
		$building = self::$calcCache->buildings[(int) $r['unit']->fk_building] ?? null;
		$pBk = new Product($this->db);
		$pBk->fetch(0, self::REF_BK);
		$pAd = new Product($this->db);
		$pAd->fetch(0, self::REF_ADMIN);
		$ol = new Translate('', $conf);
		$ol->setDefaultLang(getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR'));
		$ol->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
		$fkRes = $building ? (int) $building->fk_product_reserve : 0;
		$lines = array();
		foreach ($this->lineDefs($r, $building, $fkRes, (int) $pBk->id, (int) $pAd->id, $ol, self::periodLabel($period, $periodTo)) as $l) {
			if ((float) $l[2] == 0) {
				continue;
			}
			$lines[] = $n > 1 ? array('fk_product' => (int) $l[0], 'desc' => $l[1], 'price' => round($l[2] * (float) $r['area'], 2), 'qty' => $n)
				: array('fk_product' => (int) $l[0], 'desc' => $l[1], 'price' => (float) $l[2], 'qty' => (float) $r['area']);
		}
		preg_match('/^(\d{4})-(\d{2})$/', $periodTo, $mt);
		return array('lines' => $lines, 'products' => array_values(array_filter(array((int) $pBk->id, $fkRes, (int) $pAd->id))), 'vat' => self::vatRate(),
			'date' => dol_mktime(12, 0, 0, (int) $m[2], 1, (int) $m[1]), 'dateEnd' => dol_get_last_day((int) $mt[1], (int) $mt[2]), 'building' => $building);
	}

	/**
	 * Vorschreibung mit den aktuellen Sätzen neu berechnen – gleiche Nummer, ohne Gutschrift.
	 * Nur wenn noch nichts bezahlt ist. Wird protokolliert (llx_hv_invoice_recalc) und als Ereignis beim Apartment vermerkt.
	 *
	 * @return int >0 ok, <0 Fehler ($this->error)
	 */
	public function recalculate(User $user, Facture $f)
	{
		global $conf, $langs;
		if ((float) $f->getSommePaiement() > 0 || (int) $f->paye === 1) {
			$this->error = $f->ref.': '.$langs->transnoentities('HvRecalcPaid');
			return -1;
		}
		$diffs = $this->compareWithCurrent($f);
		if (!$diffs) {
			return 0;
		}
		if ($this->ensureBaseProducts($user) < 0) {
			return -2;
		}
		$plan = $this->recalcLines($f);
		if (!$plan) {
			$this->error = $f->ref.': unit/period';
			return -3;
		}
		if ($plan['building'] && empty($plan['building']->fk_product_reserve)) {
			$this->ensureReserveProduct($user, $plan['building']);
			$plan = $this->recalcLines($f);
		}
		$oldTotal = (float) $f->total_ttc;
		$oldDue = $f->date_lim_reglement;
		$wasValid = (int) $f->status === Facture::STATUS_VALIDATED;
		$this->db->begin();
		$err = false;
		if ($wasValid && $f->setDraft($user) <= 0) {
			$err = true;
		}
		if (!$err) {
			$f->fetch($f->id);
			foreach ($f->lines as $l) {
				// alle Positionen der Vorschreibung (mit Produkt) ersetzen; Guthaben-Zeilen und freie Zeilen bleiben
				if ((int) $l->fk_product > 0 && (float) $l->subprice >= 0) {
					if ($f->deleteLine($l->id) <= 0) {
						$err = true;
						break;
					}
				}
			}
		}
		if (!$err) {
			foreach ($plan['lines'] as $l) {
				if ($f->addline($l['desc'], $l['price'], $l['qty'], $plan['vat'], 0, 0, $l['fk_product'], 0, $plan['date'], $plan['dateEnd'], 0, 0, 0, 'HT', 0, 1) <= 0) {
					$err = true;
					break;
				}
			}
		}
		if (!$err && $wasValid) {
			$f->fetch($f->id);
			if ($f->validate($user) <= 0) {
				$err = true;
			}
		}
		if ($err) {
			$this->db->rollback();
			$this->error = $f->ref.': '.$f->error.' '.implode(', ', (array) $f->errors);
			return -4;
		}
		// Fälligkeit wie bisher
		$this->db->query("UPDATE ".$this->db->prefix()."facture SET date_lim_reglement = '".$this->db->idate($oldDue)."' WHERE rowid = ".((int) $f->id));
		$f->fetch($f->id);
		$this->db->query("INSERT INTO ".$this->db->prefix()."hv_invoice_recalc (entity, fk_invoice, old_total, new_total, diffs, datec, fk_user) VALUES (".((int) $conf->entity).", ".((int) $f->id).", ".((float) $oldTotal).", ".((float) $f->total_ttc).", '".$this->db->escape(self::diffText($diffs, $langs))."', '".$this->db->idate(dol_now())."', ".((int) $user->id).")");
		$this->db->commit();
		// PDFs neu (Original + Kopie), Erläuterung passend dazu
		$ol = new Translate('', $conf);
		$ol->setDefaultLang(getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR'));
		$ol->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
		$f->fetch_thirdparty();
		$f->generateDocument($f->model_pdf ?: getDolGlobalString('HAUSVERWALTUNG_PDF_MODEL', 'hausverwaltung'), $ol);
		dol_include_once('/hausverwaltung/class/hvmailer.class.php');
		if (HvMailer::infoEnabled()) {
			dol_include_once('/hausverwaltung/class/hvinfopdf.class.php');
			(new HvInfoPdf($this->db))->generateForInvoice($f);
		}
		HvMailer::addEvent($this->db, $user, (int) $f->socid, array(), $langs->transnoentities('HvRecalcEvent', $f->ref, price($oldTotal, 0, $langs, 1, 2, 2), price($f->total_ttc, 0, $langs, 1, 2, 2)), '', '', '', self::diffText($diffs, $langs), array(), 'hvrecalc-'.((int) $f->id).'-'.dol_now());
		return 1;
	}

	/** Vorschreibung trotz Abweichung bewusst unverändert lassen (bis sich die Sätze erneut ändern) */
	public function keep(User $user, Facture $f)
	{
		global $conf, $langs;
		$diffs = $this->compareWithCurrent($f);
		if (!$diffs) {
			return 0;
		}
		return $this->db->query("INSERT INTO ".$this->db->prefix()."hv_invoice_recalc (entity, fk_invoice, old_total, new_total, action, diffs, datec, fk_user) VALUES (".((int) $conf->entity).", ".((int) $f->id).", ".((float) $f->total_ttc).", ".((float) $f->total_ttc).", 'keep', '".$this->db->escape(self::diffText($diffs, $langs))."', '".$this->db->idate(dol_now())."', ".((int) $user->id).")") ? 1 : -1;
	}

	protected function isKept($invoiceId, $diffText)
	{
		$res = $this->db->query("SELECT action, diffs FROM ".$this->db->prefix()."hv_invoice_recalc WHERE fk_invoice = ".((int) $invoiceId)." ORDER BY rowid DESC");
		$o = $res ? $this->db->fetch_object($res) : null;
		return $o && $o->action === 'keep' && (string) $o->diffs === $diffText;
	}

	/** Erste Neuberechnung nach einem Zeitpunkt (z. B. letzter Versand): Betrag der zuletzt verschickten Fassung in 'old' */
	public function recalcSince($invoiceId, $since)
	{
		$res = $this->db->query("SELECT old_total, datec FROM ".$this->db->prefix()."hv_invoice_recalc WHERE fk_invoice = ".((int) $invoiceId)." AND action = 'recalc' AND datec > '".$this->db->idate($since)."' ORDER BY rowid ASC");
		$o = $res ? $this->db->fetch_object($res) : null;
		return $o ? array('old' => (float) $o->old_total, 'date' => $this->db->jdate($o->datec)) : null;
	}

	/** Letzte Neuberechnung einer Vorschreibung (oder null) */
	public function lastRecalc($invoiceId)
	{
		$res = $this->db->query("SELECT old_total, new_total, datec FROM ".$this->db->prefix()."hv_invoice_recalc WHERE fk_invoice = ".((int) $invoiceId)." AND action = 'recalc' ORDER BY rowid DESC");
		$o = $res ? $this->db->fetch_object($res) : null;
		return $o ? array('old' => (float) $o->old_total, 'new' => (float) $o->new_total, 'date' => $this->db->jdate($o->datec)) : null;
	}

	/** Abweichungen als kurzer Text, z. B. „Rücklage 0,80 statt 0,49 €/m²“ */
	public static function diffText(array $diffs, Translate $T)
	{
		$p = array();
		foreach ($diffs as $d) {
			$p[] = $T->transnoentities('HvDiffLine', $T->transnoentities($d['label']), price($d['inv'], 0, $T, 1, 2, 2), price($d['cur'], 0, $T, 1, 2, 2));
		}
		return implode('; ', $p);
	}

	/**
	 * Positionen einer Vorschreibung: [fk_product, Text, Satz €/m² und Monat]
	 */
	public function lineDefs($r, $building, $fkReserveProduct, $fkBk, $fkAdmin, Translate $outlangs, $mlabel)
	{
		$u = $r['unit'];
		$bname = $building ? (preg_match('/\d/', $building->ref) ? $outlangs->transnoentities('HvBuildingNo', preg_replace('/\D/', '', $building->ref)) : $building->label) : '';
		return array(
			array($fkBk, $outlangs->transnoentities('HvLineBk', $mlabel, $u->ref), $r['bk_rate']),
			array($fkReserveProduct, $outlangs->transnoentities('HvLineReserve', $bname, $mlabel), $r['reserve_rate']),
			array($fkAdmin, $outlangs->transnoentities('HvLineAdmin', $mlabel), $r['admin_rate']),
		);
	}

	/**
	 * Vorschau: Rechnung nur im Speicher aufbauen (wird nicht gespeichert), für die PDF-Vorschau.
	 *
	 * @return Facture|null
	 */
	public function previewInvoice($unitId, $period, $periodTo = '')
	{
		global $conf;
		$periodTo = $periodTo ?: $period;
		$nMonths = self::months($period, $periodTo);
		if ($nMonths <= 0) {
			return null;
		}
		$calc = new HvCalculator($this->db);
		$rows = $calc->compute();
		if (!isset($rows[$unitId]) || empty($rows[$unitId]['unit']->fk_soc)) {
			return null;
		}
		$r = $rows[$unitId];
		$u = $r['unit'];
		$building = $calc->buildings[(int) $u->fk_building] ?? null;
		preg_match('/^(\d{4})-(\d{2})$/', $period, $m);
		$dueDay = max(1, min(28, getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5)));
		$ol = new Translate('', $conf);
		$ol->setDefaultLang(getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR'));
		$ol->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
		$pBk = new Product($this->db);
		$pBk->fetch(0, self::REF_BK);
		$pAdmin = new Product($this->db);
		$pAdmin->fetch(0, self::REF_ADMIN);

		$f = new Facture($this->db);
		$f->id = 0;
		$f->entity = $conf->entity;
		$f->ref = 'PREVIEW';
		$f->statut = Facture::STATUS_DRAFT;
		$f->status = Facture::STATUS_DRAFT;
		$f->socid = (int) $u->fk_soc;
		$f->fetch_thirdparty();
		$f->date = dol_mktime(12, 0, 0, (int) $m[2], 1, (int) $m[1]);
		$f->date_lim_reglement = dol_mktime(12, 0, 0, (int) $m[2], $dueDay, (int) $m[1]);
		$f->fk_account = getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT');
		$f->array_options = array('options_hv_period' => $period, 'options_hv_period_to' => ($periodTo !== $period ? $periodTo : ''), 'options_hv_unit' => (int) $unitId);
		$f->hv_preview_contacts = $this->ownerContacts((int) $u->fk_soc);
		$f->lines = array();
		$tot = 0;
		foreach ($this->lineDefs($r, $building, $building ? (int) $building->fk_product_reserve : 0, (int) $pBk->id, (int) $pAdmin->id, $ol, self::periodLabel($period, $periodTo)) as $l) {
			if ((float) $l[2] == 0) {
				continue;
			}
			$ln = new FactureLigne($this->db);
			$ln->fk_product = (int) $l[0];
			$ln->desc = $l[1];
			$ln->tva_tx = self::vatRate();
			if ($nMonths > 1) {
				$ln->subprice = round($l[2] * (float) $r['area'], 2);
				$ln->qty = $nMonths;
			} else {
				$ln->subprice = $l[2];
				$ln->qty = (float) $r['area'];
			}
			$ln->total_ht = round($ln->subprice * $ln->qty, 2);
			$ln->total_tva = round($ln->total_ht * $ln->tva_tx / 100, 2);
			$ln->total_ttc = $ln->total_ht + $ln->total_tva;
			$tot += $ln->total_ttc;
			$f->lines[] = $ln;
		}
		$f->total_ht = $f->total_ttc = round($tot, 2);
		$f->total_tva = 0;
		return $f;
	}

	/** Existiert für Einheit + Periode bereits eine (nicht stornierte) Vorschreibung? */
	public function findExisting($unitId, $period, $periodTo = '')
	{
		global $conf;
		$periodTo = $periodTo ?: $period;
		$sql = "SELECT f.rowid FROM ".$this->db->prefix()."facture f";
		$sql .= " INNER JOIN ".$this->db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid";
		$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.type = ".Facture::TYPE_STANDARD;
		$sql .= " AND f.fk_statut <> ".Facture::STATUS_ABANDONED;
		// Überschneidung mit bestehender Vorschreibung (einzelner Monat oder Zeitraum); Abrechnungen (JJJJ-A) ausgenommen
		$sql .= " AND fe.hv_unit = ".((int) $unitId)." AND fe.hv_period NOT LIKE '%-A'";
		$sql .= " AND fe.hv_period <= '".$this->db->escape($periodTo)."' AND COALESCE(NULLIF(fe.hv_period_to, ''), fe.hv_period) >= '".$this->db->escape($period)."'";
		$res = $this->db->query($sql);
		if ($res && ($obj = $this->db->fetch_object($res))) {
			return (int) $obj->rowid;
		}
		return 0;
	}

	/**
	 * Vorschreibungen erzeugen.
	 *
	 * @param User   $user
	 * @param string $period   'YYYY-MM'
	 * @param bool   $validate Rechnungen gleich freigeben (Nummer vergeben, PDF erzeugen)
	 * @param int[]  $onlyUnits leer = alle Einheiten
	 * @param bool   $dryrun   nur berechnen
	 * @return array<int,array{unit:string,status:string,message:string,invoice_id:int,total:float}>
	 */
	public function generate(User $user, $period, $validate = false, $onlyUnits = array(), $dryrun = false, $periodTo = '')
	{
		global $conf, $langs;

		$log = array();
		$periodTo = $periodTo ?: $period;
		$nMonths = self::months($period, $periodTo);
		if (!preg_match('/^(\d{4})-(\d{2})$/', $period, $m) || $nMonths <= 0 || $nMonths > 24) {
			$this->error = 'Bad period';
			return $log;
		}
		$year = (int) $m[1];
		$month = (int) $m[2];
		preg_match('/^(\d{4})-(\d{2})$/', $periodTo, $mt);
		$date = dol_mktime(12, 0, 0, $month, 1, $year);
		$dateEnd = dol_get_last_day((int) $mt[1], (int) $mt[2]);
		$dueDay = max(1, min(28, (int) getDolGlobalString('HAUSVERWALTUNG_DUE_DAY', '5')));
		$due = dol_mktime(12, 0, 0, $month, $dueDay, $year);
		$vat = self::vatRate();

		$calc = new HvCalculator($this->db);
		$rows = $calc->compute();

		if (!$dryrun && $this->ensureBaseProducts($user) < 0) {
			$log[] = array('unit' => '-', 'status' => 'error', 'message' => 'Produkte: '.$this->error, 'invoice_id' => 0, 'total' => 0);
			return $log;
		}
		$pBk = new Product($this->db);
		$pBk->fetch(0, self::REF_BK);
		$pAdmin = new Product($this->db);
		$pAdmin->fetch(0, self::REF_ADMIN);

		foreach ($rows as $unitId => $r) {
			$u = $r['unit'];
			if (!empty($onlyUnits) && !in_array($unitId, $onlyUnits)) {
				continue;
			}
			$entry = array('unit' => $u->ref, 'status' => '', 'message' => '', 'invoice_id' => 0, 'total' => round($r['total'] * $nMonths, 2));
			if (empty($u->fk_soc)) {
				$entry['status'] = 'skip';
				$entry['message'] = $langs->trans('HvNoThirdparty');
				$log[] = $entry;
				continue;
			}
			$existing = $this->findExisting($unitId, $period, $periodTo);
			if ($existing) {
				$entry['status'] = 'skip';
				$entry['message'] = $langs->trans('HvAlreadyExists');
				$entry['invoice_id'] = $existing;
				$log[] = $entry;
				continue;
			}
			if ($dryrun) {
				$entry['status'] = 'dryrun';
				$log[] = $entry;
				continue;
			}

			$building = $calc->buildings[(int) $u->fk_building] ?? null;
			$fkReserveProduct = $building ? $this->ensureReserveProduct($user, $building) : 0;

			$soc = new Societe($this->db);
			$soc->fetch((int) $u->fk_soc);
			$outlangs = new Translate('', $conf);
			// Rechnungstexte immer in der Originalsprache (Kroatien: hr_HR); Kopie in Eigentümersprache erzeugt die PDF-Vorlage
			$outlangs->setDefaultLang(getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR'));
			$outlangs->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
			$mlabel = self::periodLabel($period, $periodTo);

			$this->db->begin();
			$f = new Facture($this->db);
			$f->socid = (int) $u->fk_soc;
			$f->type = Facture::TYPE_STANDARD;
			$f->date = $date;
			$f->ref_client = $outlangs->transnoentities('HvInvoiceRefClient', $u->ref, $mlabel);
			$f->note_public = $nMonths > 1 ? $outlangs->transnoentities('HvInvoiceNoteRange', $mlabel, $nMonths, $dueDay) : $outlangs->transnoentities('HvInvoiceNote', $mlabel, dol_print_date($due, 'day', false, $outlangs));
			if (getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT') > 0) {
				$f->fk_account = getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT');
			}
			if (getDolGlobalInt('HAUSVERWALTUNG_PAYMENT_MODE') > 0) {
				$f->mode_reglement_id = getDolGlobalInt('HAUSVERWALTUNG_PAYMENT_MODE');
			}
			$f->cond_reglement_id = $soc->cond_reglement_id ?: 1;
			$f->array_options = array('options_hv_period' => $period, 'options_hv_period_to' => ($periodTo !== $period ? $periodTo : ''), 'options_hv_unit' => (int) $unitId);
			$f->model_pdf = getDolGlobalString('HAUSVERWALTUNG_PDF_MODEL', 'hausverwaltung');
			$id = $f->create($user);
			$err = $id <= 0;
			if (!$err) {
				foreach ($this->ownerContacts((int) $u->fk_soc) as $cid) {
					$f->add_contact($cid, 'BILLING', 'external');
				}
			}

			$lines = $this->lineDefs($r, $building, $fkReserveProduct, (int) $pBk->id, (int) $pAdmin->id, $outlangs, $mlabel);
			foreach ($lines as $l) {
				if ($err) {
					break;
				}
				if ((float) $l[2] == 0) {
					continue;
				}
				if ($nMonths > 1) {
					// Zeitraum: Einzelpreis = Monatsbetrag (Satz €/m² × m², gerundet), Menge = Anzahl Monate → jede Monatsrate = Monatsbetrag
					$res = $f->addline($l[1], round($l[2] * (float) $r['area'], 2), $nMonths, $vat, 0, 0, (int) $l[0], 0, $date, $dateEnd, 0, 0, 0, 'HT', 0, 1);
				} else {
					// Einzelmonat: Einzelpreis = Satz €/m², Menge = m²
					$res = $f->addline($l[1], $l[2], (float) $r['area'], $vat, 0, 0, (int) $l[0], 0, $date, $dateEnd, 0, 0, 0, 'HT', 0, 1);
				}
				if ($res <= 0) {
					$err = true;
				}
			}
			// Guthaben aus Jahresabrechnung verrechnen (reduziert die Vorschreibung)
			if (!$err) {
				dol_include_once('/hausverwaltung/class/hvsettlement.class.php');
				$st = new HvSettlement($this->db);
				if ($st->applyCredit($user, $f, (int) $unitId, $outlangs) < 0) {
					$err = true;
				}
			}
			if (!$err) {
				$this->db->query("UPDATE ".$this->db->prefix()."facture SET date_lim_reglement = '".$this->db->idate($due)."' WHERE rowid = ".((int) $f->id));
			}
			if (!$err && $validate) {
				$f->fetch($f->id);
				if ($f->validate($user) <= 0) {
					$err = true;
				}
			}
			if ($err) {
				$this->db->rollback();
				$entry['status'] = 'error';
				$entry['message'] = $f->error.' '.implode(', ', $f->errors);
			} else {
				$this->db->commit();
				if ($validate) {
					$f->fetch($f->id);
					$f->generateDocument(getDolGlobalString('HAUSVERWALTUNG_PDF_MODEL', 'hausverwaltung'), $outlangs);
				}
				$entry['status'] = 'ok';
				$entry['invoice_id'] = (int) $f->id;
			}
			$log[] = $entry;
		}
		return $log;
	}
}
