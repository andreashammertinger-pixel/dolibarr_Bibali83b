<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');

/**
 * Jahresabrechnung Betriebskosten:
 *  Ist-Kosten (freigegebene Eingangsrechnungen mit Kostenart, ohne "aus Rücklage") nach m² verteilt
 *  gegen vorgeschriebene BK-Akonti (Position HV-BK der Vorschreibungen des Jahres).
 *  Nachzahlung -> eigene Rechnung; Guthaben -> wird mit den nächsten Vorschreibungen verrechnet (§10.6 Miteigentumsvertrag analog).
 */
class HvSettlement
{
	const REF_SETTLEMENT = 'HV-BKA';   // Nachzahlung aus Abrechnung
	const REF_CREDIT = 'HV-GUT';       // Guthaben aus Abrechnung (negativ auf Vorschreibung)

	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @return array{costitems:array, actual:array<int,float>, units:array<int,array{unit:object,actual:float,prepaid:float,paid:float,saldo:float,booked:?object}>}
	 */
	public function compute($year)
	{
		global $conf;
		$calc = new HvCalculator($this->db);
		$repo = new HvRepository($this->db);
		$costitems = $repo->getCostItems(false);
		$d1 = $this->db->idate(dol_get_first_day($year, 1));
		$d2 = $this->db->idate(dol_get_last_day($year, 12));

		$actual = array();
		$sql = "SELECT fe.hv_costitem, SUM(f.total_ttc) as total FROM ".$this->db->prefix()."facture_fourn f";
		$sql .= " INNER JOIN ".$this->db->prefix()."facture_fourn_extrafields fe ON fe.fk_object = f.rowid";
		$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut IN (1,2) AND f.datef BETWEEN '".$d1."' AND '".$d2."'";
		$sql .= " AND (fe.hv_reserve IS NULL OR fe.hv_reserve = 0) AND fe.hv_costitem > 0 GROUP BY fe.hv_costitem";
		$res = $this->db->query($sql);
		while ($res && ($o = $this->db->fetch_object($res))) {
			$actual[(int) $o->hv_costitem] = (float) $o->total;
		}
		$unitActual = array();
		foreach ($actual as $cid => $amount) {
			$fk_b = isset($costitems[$cid]) ? (int) $costitems[$cid]->fk_building : 0;
			$key = isset($costitems[$cid]) && !empty($costitems[$cid]->alloc_key) ? $costitems[$cid]->alloc_key : 'area';
			foreach ($calc->allocate($amount, $fk_b, $key) as $uid => $v) {
				$unitActual[$uid] = ($unitActual[$uid] ?? 0) + $v;
			}
		}

		$prepaid = $paid = array();
		$p = new Product($this->db);
		if ($p->fetch(0, HvInvoicer::REF_BK) > 0) {
			$sql = "SELECT fe.hv_unit, SUM(d.total_ttc) as total, SUM(CASE WHEN f.paye = 1 THEN d.total_ttc ELSE 0 END) as paid FROM ".$this->db->prefix()."facturedet d";
			$sql .= " INNER JOIN ".$this->db->prefix()."facture f ON f.rowid = d.fk_facture";
			$sql .= " INNER JOIN ".$this->db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid";
			$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut IN (1,2) AND d.fk_product = ".((int) $p->id);
			$sql .= " AND fe.hv_period LIKE '".((int) $year)."-__' GROUP BY fe.hv_unit";
			$res = $this->db->query($sql);
			while ($res && ($o = $this->db->fetch_object($res))) {
				$prepaid[(int) $o->hv_unit] = (float) $o->total;
				$paid[(int) $o->hv_unit] = (float) $o->paid;
			}
		}

		$booked = array();
		$res = $this->db->query("SELECT * FROM ".$this->db->prefix()."hv_settlement WHERE entity = ".((int) $conf->entity)." AND year = ".((int) $year));
		while ($res && ($o = $this->db->fetch_object($res))) {
			$booked[(int) $o->fk_unit] = $o;
		}

		$units = array();
		foreach ($calc->units as $uid => $u) {
			$a = round($unitActual[$uid] ?? 0, 2);
			$pp = round($prepaid[$uid] ?? 0, 2);
			$units[$uid] = array('unit' => $u, 'actual' => $a, 'prepaid' => $pp, 'paid' => round($paid[$uid] ?? 0, 2), 'saldo' => round($pp - $a, 2), 'booked' => $booked[$uid] ?? null);
		}
		return array('costitems' => $costitems, 'actual' => $actual, 'units' => $units);
	}

	/**
	 * Abrechnung buchen: Nachzahlungsrechnungen erzeugen (optional freigeben), Guthaben vormerken.
	 *
	 * @return array<int,array{unit:string,status:string,message:string,invoice_id:int,saldo:float}>
	 */
	public function book(User $user, $year, $validate = false)
	{
		global $conf, $langs;
		$log = array();
		$data = $this->compute($year);
		$inv = new HvInvoicer($this->db);
		$pid = $inv->ensureProduct($user, self::REF_SETTLEMENT, 'Nachzahlung Betriebskostenabrechnung', 'Nachzahlung aus der Jahresabrechnung der Betriebskosten');
		$inv->ensureProduct($user, self::REF_CREDIT, 'Guthaben aus Betriebskostenabrechnung', 'Verrechnung eines Guthabens aus der Jahresabrechnung');
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$ol = new Translate('', $conf);
		$ol->setDefaultLang($orig);
		$ol->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
		$dueDays = getDolGlobalInt('HAUSVERWALTUNG_SETTLEMENT_DUE_DAYS', 30);

		foreach ($data['units'] as $uid => $r) {
			$u = $r['unit'];
			$e = array('unit' => $u->ref, 'status' => 'skip', 'message' => '', 'invoice_id' => 0, 'saldo' => $r['saldo']);
			if ($r['booked']) {
				$e['message'] = $langs->trans('HvSettlementAlreadyBooked');
				$e['invoice_id'] = (int) $r['booked']->fk_invoice;
				$log[] = $e;
				continue;
			}
			if (empty($u->fk_soc)) {
				$e['message'] = $langs->trans('HvNoThirdparty');
				$log[] = $e;
				continue;
			}
			$this->db->begin();
			$fkInvoice = 0;
			$err = false;
			if ($r['saldo'] < 0) {
				$f = new Facture($this->db);
				$f->socid = (int) $u->fk_soc;
				$f->type = Facture::TYPE_STANDARD;
				$f->date = dol_now();
				$f->ref_client = $ol->transnoentities('HvSettlementRefClient', $u->ref, $year);
				$f->model_pdf = getDolGlobalString('HAUSVERWALTUNG_PDF_MODEL', 'hausverwaltung');
				if (getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT') > 0) {
					$f->fk_account = getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT');
				}
				$f->cond_reglement_id = 1;
				$f->array_options = array('options_hv_period' => $year.'-A', 'options_hv_unit' => (int) $uid);
				$fkInvoice = $f->create($user);
				$err = $fkInvoice <= 0;
				if (!$err) {
					foreach ($inv->ownerContacts((int) $u->fk_soc) as $cid) {
						$f->add_contact($cid, 'BILLING', 'external');
					}
					$err = $f->addline($ol->transnoentities('HvLineSettlement', $year), -$r['saldo'], 1, 0, 0, 0, $pid, 0, '', '', 0, 0, 0, 'HT', 0, 1) <= 0;
				}
				if (!$err) {
					$this->db->query("UPDATE ".$this->db->prefix()."facture SET date_lim_reglement = '".$this->db->idate(dol_time_plus_duree(dol_now(), $dueDays, 'd'))."' WHERE rowid = ".((int) $fkInvoice));
					if ($validate) {
						$f->fetch($fkInvoice);
						$err = $f->validate($user) <= 0;
					}
				}
				if ($err) {
					$e['message'] = $f->error.' '.implode(', ', $f->errors);
				}
			}
			if (!$err) {
				$sql = "INSERT INTO ".$this->db->prefix()."hv_settlement (entity, year, fk_unit, fk_soc, actual, prepaid, saldo, fk_invoice, credit_remaining, datec, fk_user) VALUES (";
				$sql .= ((int) $conf->entity).", ".((int) $year).", ".((int) $uid).", ".((int) $u->fk_soc).", ".price2num($r['actual']).", ".price2num($r['prepaid']).", ".price2num($r['saldo']).", ".($fkInvoice > 0 ? (int) $fkInvoice : 'NULL').", ".price2num(max(0, $r['saldo'])).", '".$this->db->idate(dol_now())."', ".((int) $user->id).")";
				$err = !$this->db->query($sql);
				if ($err) {
					$e['message'] = $this->db->lasterror();
				}
			}
			if ($err) {
				$this->db->rollback();
				$e['status'] = 'error';
			} else {
				$this->db->commit();
				$e['status'] = 'ok';
				$e['invoice_id'] = (int) $fkInvoice;
				$e['message'] = $r['saldo'] > 0 ? $langs->trans('HvCreditNoted') : ($r['saldo'] < 0 ? $langs->trans('HvSettlementInvoiceCreated') : '');
				if ($fkInvoice > 0 && $validate) {
					$f->fetch($fkInvoice);
					$f->generateDocument($f->model_pdf, $ol);
				}
			}
			$log[] = $e;
		}
		return $log;
	}

	/** Offenes Guthaben einer Einheit */
	public function openCredit($unitId)
	{
		global $conf;
		$res = $this->db->query("SELECT SUM(credit_remaining) as c FROM ".$this->db->prefix()."hv_settlement WHERE entity = ".((int) $conf->entity)." AND fk_unit = ".((int) $unitId)." AND credit_remaining > 0");
		$o = $res ? $this->db->fetch_object($res) : null;
		return $o ? round((float) $o->c, 2) : 0.0;
	}

	/**
	 * Guthaben auf einer Vorschreibung verrechnen (max. bis Rechnungsbetrag), älteste Abrechnung zuerst.
	 *
	 * @return float verrechneter Betrag
	 */
	public function applyCredit(User $user, Facture $f, $unitId, Translate $ol)
	{
		global $conf;
		$f->fetch($f->id);
		$max = round((float) $f->total_ttc, 2);
		if ($max <= 0) {
			return 0;
		}
		$p = new Product($this->db);
		if ($p->fetch(0, self::REF_CREDIT) <= 0) {
			return 0;
		}
		$used = 0;
		$res = $this->db->query("SELECT rowid, year, credit_remaining FROM ".$this->db->prefix()."hv_settlement WHERE entity = ".((int) $conf->entity)." AND fk_unit = ".((int) $unitId)." AND credit_remaining > 0 ORDER BY year, rowid");
		$rows = array();
		while ($res && ($o = $this->db->fetch_object($res))) {
			$rows[] = $o;
		}
		foreach ($rows as $o) {
			$take = round(min((float) $o->credit_remaining, $max - $used), 2);
			if ($take <= 0) {
				break;
			}
			if ($f->addline($ol->transnoentities('HvLineCredit', $o->year), -$take, 1, 0, 0, 0, (int) $p->id, 0, '', '', 0, 0, 0, 'HT', 0, 1) <= 0) {
				$this->error = $f->error;
				return -1;
			}
			$this->db->query("UPDATE ".$this->db->prefix()."hv_settlement SET credit_remaining = credit_remaining - ".price2num($take)." WHERE rowid = ".((int) $o->rowid));
			$used += $take;
		}
		return $used;
	}
}
