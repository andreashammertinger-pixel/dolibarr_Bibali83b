<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

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
	public function generateManagerFee(User $user, $period)
	{
		global $conf, $langs;
		$res = array('status' => 'skip', 'message' => '', 'invoice_id' => 0, 'total' => 0);
		$socid = getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID');
		if ($socid <= 0 || !preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
			$res['message'] = $langs->trans('HvNoManager');
			return $res;
		}
		$refsupplier = 'HON-'.$period;
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
		$mlabel = sprintf('%02d/%04d', (int) $m[2], (int) $m[1]);
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
	 * Kontakte des Apartments mit Funktion Eigentümer / Vlasnik / Owner / Lastnik (in dieser Reihenfolge der Anlage).
	 * Ohne solche Kontakte: keine (dann wird die Adresse des Geschäftspartners verwendet).
	 *
	 * @return int[]
	 */
	public function ownerContacts($socid)
	{
		$ids = array();
		$res = $this->db->query("SELECT rowid, poste FROM ".$this->db->prefix()."socpeople WHERE fk_soc = ".((int) $socid)." AND statut = 1 ORDER BY rowid");
		while ($res && ($o = $this->db->fetch_object($res))) {
			if (preg_match('/^\s*(eigentümer|eigentuemer|vlasni|owner|lastnik|власник)/iu', (string) $o->poste)) {
				$ids[] = (int) $o->rowid;
			}
		}
		return $ids;
	}

	/** Existiert für Einheit + Periode bereits eine (nicht stornierte) Vorschreibung? */
	public function findExisting($unitId, $period)
	{
		global $conf;
		$sql = "SELECT f.rowid FROM ".$this->db->prefix()."facture f";
		$sql .= " INNER JOIN ".$this->db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid";
		$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.type = ".Facture::TYPE_STANDARD;
		$sql .= " AND f.fk_statut <> ".Facture::STATUS_ABANDONED;
		$sql .= " AND fe.hv_unit = ".((int) $unitId)." AND fe.hv_period = '".$this->db->escape($period)."'";
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
	public function generate(User $user, $period, $validate = false, $onlyUnits = array(), $dryrun = false)
	{
		global $conf, $langs;

		$log = array();
		if (!preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
			$this->error = 'Bad period';
			return $log;
		}
		$year = (int) $m[1];
		$month = (int) $m[2];
		$date = dol_mktime(12, 0, 0, $month, 1, $year);
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
			$entry = array('unit' => $u->ref, 'status' => '', 'message' => '', 'invoice_id' => 0, 'total' => $r['total']);
			if (empty($u->fk_soc)) {
				$entry['status'] = 'skip';
				$entry['message'] = $langs->trans('HvNoThirdparty');
				$log[] = $entry;
				continue;
			}
			$existing = $this->findExisting($unitId, $period);
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
			$mlabel = sprintf('%02d/%04d', $month, $year);

			$this->db->begin();
			$f = new Facture($this->db);
			$f->socid = (int) $u->fk_soc;
			$f->type = Facture::TYPE_STANDARD;
			$f->date = $date;
			$f->ref_client = $outlangs->transnoentities('HvInvoiceRefClient', $u->ref, $mlabel);
			$f->note_public = $outlangs->transnoentities('HvInvoiceNote', $mlabel, dol_print_date($due, 'day', false, $outlangs));
			if (getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT') > 0) {
				$f->fk_account = getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT');
			}
			if (getDolGlobalInt('HAUSVERWALTUNG_PAYMENT_MODE') > 0) {
				$f->mode_reglement_id = getDolGlobalInt('HAUSVERWALTUNG_PAYMENT_MODE');
			}
			$f->cond_reglement_id = $soc->cond_reglement_id ?: 1;
			$f->array_options = array('options_hv_period' => $period, 'options_hv_unit' => (int) $unitId);
			$f->model_pdf = getDolGlobalString('HAUSVERWALTUNG_PDF_MODEL', 'hausverwaltung');
			$id = $f->create($user);
			$err = $id <= 0;
			if (!$err) {
				foreach ($this->ownerContacts((int) $u->fk_soc) as $cid) {
					$f->add_contact($cid, 'BILLING', 'external');
				}
			}

			$lines = array(
				array($pBk->id, $outlangs->transnoentities('HvLineBk', $mlabel, $u->ref), $r['bk_rate']),
				array($fkReserveProduct, $outlangs->transnoentities('HvLineReserve', $building ? $outlangs->transnoentities('HvBuildingNo', preg_replace('/\D/', '', $building->ref) ?: $building->ref) : '', $mlabel), $r['reserve_rate']),
				array($pAdmin->id, $outlangs->transnoentities('HvLineAdmin', $mlabel), $r['admin_rate']),
			);
			foreach ($lines as $l) {
				if ($err) {
					break;
				}
				if ((float) $l[2] == 0) {
					continue;
				}
				$res = $f->addline($l[1], $l[2], (float) $r['area'], $vat, 0, 0, (int) $l[0], 0, $date, dol_get_last_day($year, $month), 0, 0, 0, 'HT', 0, 1);
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
