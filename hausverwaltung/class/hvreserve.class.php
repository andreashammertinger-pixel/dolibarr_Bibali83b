<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

/**
 * Rücklagen-Logik:
 *  - Kundenzahlung auf Betriebskonto  -> Rücklagenanteil der Rechnung wird auf das Rücklagenkonto
 *    des jeweiligen Gebäudes umgebucht (Zuführung, direction 'in').
 *  - Lieferantenzahlung einer Eingangsrechnung mit "aus Rücklage bezahlt" -> Betrag wird vom
 *    Rücklagenkonto des Gebäudes auf das Zahlungskonto umgebucht (Entnahme, direction 'out').
 *    Kostenposition "alle Gebäude" wird nach m²-Anteil auf die Gebäude-Rücklagen aufgeteilt.
 */
class HvReserve
{
	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** product id => building */
	protected function reserveProducts()
	{
		$repo = new HvRepository($this->db);
		$map = array();
		foreach ($repo->getBuildings(false) as $b) {
			if (!empty($b->fk_product_reserve)) {
				$map[(int) $b->fk_product_reserve] = $b;
			}
		}
		return $map;
	}

	/**
	 * Rücklagenanteil einer Kundenrechnung je Gebäude (als Anteil 0..1 des Rechnungsbetrags TTC)
	 *
	 * @return array<int,float> building_id => ratio
	 */
	public function reserveRatiosOfInvoice(Facture $f)
	{
		$map = $this->reserveProducts();
		$ratios = array();
		if (empty($f->lines)) {
			$f->fetch_lines();
		}
		// Bezugsgröße = Summe der positiven Positionen (ein verrechnetes Guthaben wirkt wie eine Zahlung)
		$gross = 0;
		foreach ($f->lines as $l) {
			if ((float) $l->total_ttc > 0) {
				$gross += (float) $l->total_ttc;
			}
		}
		if ($gross == 0.0) {
			return $ratios;
		}
		foreach ($f->lines as $l) {
			if (!empty($l->fk_product) && isset($map[(int) $l->fk_product])) {
				$bid = (int) $map[(int) $l->fk_product]->rowid;
				$ratios[$bid] = ($ratios[$bid] ?? 0) + (float) $l->total_ttc / $gross;
			}
		}
		return $ratios;
	}

	/**
	 * Freigabe einer Vorschreibung mit verrechnetem Guthaben: der durch das Guthaben gedeckte
	 * Rücklagenanteil wird sofort vom Betriebskonto auf das Rücklagenkonto umgebucht.
	 */
	public function onInvoiceValidated(Facture $f, User $user)
	{
		$account = !empty($f->fk_account) ? (int) $f->fk_account : getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT');
		if ($account <= 0) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$p = new Product($this->db);
		if ($p->fetch(0, 'HV-GUT') <= 0) {
			return 0;
		}
		if (empty($f->lines)) {
			$f->fetch_lines();
		}
		$credit = 0;
		foreach ($f->lines as $l) {
			if ((int) $l->fk_product === (int) $p->id) {
				$credit += -(float) $l->total_ttc;
			}
		}
		if ($credit <= 0) {
			return 0;
		}
		$pseudo = new stdClass();
		$pseudo->element = 'invoice_credit';
		$pseudo->id = 0;
		$pseudo->fk_account = $account;
		$pseudo->datepaye = $f->date;
		$pseudo->amounts = array((int) $f->id => $credit);
		return $this->onPaymentAddedToBank($pseudo, $user);
	}

	/** Rücknahme der Guthaben-Umbuchung (Rechnung zurück auf Entwurf / gelöscht) */
	public function onInvoiceUnvalidated(Facture $f, User $user)
	{
		$pseudo = new stdClass();
		$pseudo->element = 'invoice_credit';
		$pseudo->id = 0;
		$pseudo->fk_invoice = (int) $f->id;
		return $this->onPaymentDeleted($pseudo, $user);
	}

	/**
	 * Aufteilung einer Eingangsrechnung auf Gebäude-Rücklagen.
	 *
	 * @return array<int,float> building_id => ratio
	 */
	public function reserveRatiosOfSupplierInvoice(FactureFournisseur $ff)
	{
		if (empty($ff->array_options)) {
			$ff->fetch_optionals();
		}
		if (empty($ff->array_options['options_hv_reserve'])) {
			return array();
		}
		$costitemId = (int) ($ff->array_options['options_hv_costitem'] ?? 0);
		$fk_building = 0;
		if ($costitemId > 0) {
			$res = $this->db->query("SELECT fk_building FROM ".$this->db->prefix()."hv_costitem WHERE rowid = ".$costitemId);
			if ($res && ($o = $this->db->fetch_object($res))) {
				$fk_building = (int) $o->fk_building;
			}
		}
		if ($fk_building > 0) {
			return array($fk_building => 1.0);
		}
		// alle Gebäude: nach Fläche je Gebäude
		$calc = new HvCalculator($this->db);
		$areas = $calc->areas();
		$ratios = array();
		foreach ($calc->buildings as $bid => $b) {
			if (!empty($areas[$bid]) && $areas[0] > 0) {
				$ratios[$bid] = $areas[$bid] / $areas[0];
			}
		}
		return $ratios;
	}

	/**
	 * Wird vom Trigger PAYMENT_ADD_TO_BANK aufgerufen.
	 *
	 * @param Paiement|PaiementFourn $payment
	 */
	public function onPaymentAddedToBank($payment, User $user)
	{
		global $langs;

		$isSupplier = ($payment->element === 'payment_supplier');
		// 'payment' (Kundenzahlung) und 'invoice_credit' (verrechnetes Guthaben) sind Zuführungen
		$payAccount = (int) $payment->fk_account;
		if ($payAccount <= 0 || empty($payment->amounts) || !is_array($payment->amounts)) {
			return 0;
		}
		$repo = new HvRepository($this->db);
		$buildings = $repo->getBuildings(false);

		// building_id => [amount, invoice_id]
		$moves = array();
		foreach ($payment->amounts as $invId => $amount) {
			$amount = (float) price2num($amount);
			if ($amount == 0.0) {
				continue;
			}
			if ($isSupplier) {
				$inv = new FactureFournisseur($this->db);
				if ($inv->fetch((int) $invId) <= 0) {
					continue;
				}
				$ratios = $this->reserveRatiosOfSupplierInvoice($inv);
			} else {
				$inv = new Facture($this->db);
				if ($inv->fetch((int) $invId) <= 0) {
					continue;
				}
				$ratios = $this->reserveRatiosOfInvoice($inv);
			}
			foreach ($ratios as $bid => $ratio) {
				$val = (float) price2num($amount * $ratio, 'MT');
				if ($val == 0.0) {
					continue;
				}
				$moves[] = array('building' => $bid, 'amount' => $val, 'invoice' => (int) $invId, 'invref' => $inv->ref);
			}
		}

		$done = 0;
		foreach ($moves as $mv) {
			$b = $buildings[$mv['building']] ?? null;
			if (!$b || empty($b->fk_bank_reserve) || (int) $b->fk_bank_reserve === $payAccount) {
				continue; // kein Rücklagenkonto konfiguriert oder direkt auf/vom Rücklagenkonto bezahlt
			}
			if ($isSupplier) {
				$from = (int) $b->fk_bank_reserve;
				$to = $payAccount;
				$label = 'Entnahme Rücklage '.$b->label.' – '.$mv['invref'];
				$dir = 'out';
			} else {
				$from = $payAccount;
				$to = (int) $b->fk_bank_reserve;
				$label = ($payment->element === 'invoice_credit' ? 'Zuführung Rücklage (Guthaben verrechnet) ' : 'Zuführung Rücklage ').$b->label.' – '.$mv['invref'];
				$dir = 'in';
			}
			$amount = $mv['amount'];
			if ($amount < 0) { // Gutschrift: Richtung umkehren
				list($from, $to) = array($to, $from);
				$amount = -$amount;
			}
			$lines = $this->transfer($user, $from, $to, $amount, $payment->datepaye, $label);
			if ($lines === false) {
				return -1;
			}
			$sql = "INSERT INTO ".$this->db->prefix()."hv_reserve_move (entity, fk_building, direction, element, fk_payment, fk_invoice, amount, fk_bank_line_from, fk_bank_line_to, date_move, datec, fk_user)";
			$sql .= " VALUES (".((int) $b->entity).", ".((int) $b->rowid).", '".$this->db->escape($dir)."', '".$this->db->escape($payment->element)."', ".((int) $payment->id).", ".((int) $mv['invoice']).", ".price2num($mv['amount']).", ".((int) $lines[0]).", ".((int) $lines[1]).", '".$this->db->idate($payment->datepaye)."', '".$this->db->idate(dol_now())."', ".((int) $user->id).")";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$done++;
		}
		return $done;
	}

	/**
	 * Bankübertrag zwischen zwei Dolibarr-Konten (wie compta/bank/transfer.php)
	 *
	 * @return array{0:int,1:int}|false bank line ids
	 */
	public function transfer(User $user, $fromId, $toId, $amount, $date, $label)
	{
		$from = new Account($this->db);
		$to = new Account($this->db);
		if ($from->fetch($fromId) <= 0 || $to->fetch($toId) <= 0) {
			$this->error = 'Bank account not found';
			return false;
		}
		$type = ($from->type == Account::TYPE_CASH || $to->type == Account::TYPE_CASH) ? 'LIQ' : 'VIR';
		$l1 = $from->addline($date, $type, $label, price2num(-1 * $amount), '', '', $user);
		$l2 = $l1 > 0 ? $to->addline($date, $type, $label, price2num($amount), '', '', $user) : -1;
		if ($l1 <= 0 || $l2 <= 0) {
			$this->error = $from->error.' '.$to->error;
			return false;
		}
		$from->add_url_line($l1, $l2, DOL_URL_ROOT.'/compta/bank/line.php?rowid=', '(banktransfert)', 'banktransfert');
		$to->add_url_line($l2, $l1, DOL_URL_ROOT.'/compta/bank/line.php?rowid=', '(banktransfert)', 'banktransfert');
		return array($l1, $l2);
	}

	/**
	 * Zahlung gelöscht -> zugehörige Umbuchungen entfernen (sofern nicht abgeglichen)
	 */
	public function onPaymentDeleted($payment, User $user)
	{
		$sql = "SELECT rowid, fk_bank_line_from, fk_bank_line_to FROM ".$this->db->prefix()."hv_reserve_move";
		$sql .= " WHERE element = '".$this->db->escape($payment->element)."' AND fk_payment = ".((int) $payment->id);
		if (!empty($payment->fk_invoice)) {
			$sql .= " AND fk_invoice = ".((int) $payment->fk_invoice);
		}
		$res = $this->db->query($sql);
		if (!$res) {
			return -1;
		}
		$rows = array();
		while ($o = $this->db->fetch_object($res)) {
			$rows[] = $o;
		}
		foreach ($rows as $o) {
			foreach (array($o->fk_bank_line_from, $o->fk_bank_line_to) as $lid) {
				$bl = new AccountLine($this->db);
				if ($lid > 0 && $bl->fetch((int) $lid) > 0) {
					if (!empty($bl->rappro)) {
						$this->error = 'Rücklagen-Umbuchung bereits abgeglichen (Bankzeile '.$lid.') – bitte manuell stornieren.';
						return -1;
					}
					$bl->delete($user);
				}
			}
			$this->db->query("DELETE FROM ".$this->db->prefix()."hv_reserve_move WHERE rowid = ".((int) $o->rowid));
		}
		return count($rows);
	}
}
