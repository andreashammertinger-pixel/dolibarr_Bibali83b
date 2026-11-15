<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

use Luracast\Restler\RestException;

dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
dol_include_once('/hausverwaltung/class/hvsettlement.class.php');
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * Lese-Schnittstelle Hausverwaltung (nur GET). Erfordert Recht "Hausverwaltung ansehen".
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Hausverwaltung extends DolibarrApi
{
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	private function check()
	{
		if (!DolibarrApiAccess::$user->hasRight('hausverwaltung', 'read')) {
			throw new RestException(403, 'Missing right hausverwaltung read');
		}
	}

	/**
	 * Übersicht: Einstellungen, Gebäude, Apartments, Verteilung und Monatsbeträge
	 *
	 * @return array
	 *
	 * @url GET overview
	 */
	public function overview()
	{
		global $conf;
		$this->check();
		$calc = new HvCalculator($this->db);
		$rows = $calc->compute();
		$settings = array();
		foreach (array('HAUSVERWALTUNG_BANK_ACCOUNT', 'HAUSVERWALTUNG_PAYMENT_MODE', 'HAUSVERWALTUNG_DUE_DAY', 'HAUSVERWALTUNG_VAT_RATE', 'HAUSVERWALTUNG_MANAGER_SOCID', 'HAUSVERWALTUNG_ADMIN_VAT_RATE', 'HAUSVERWALTUNG_ORIGINAL_LANG', 'HAUSVERWALTUNG_RATE_DECIMALS', 'HAUSVERWALTUNG_AREA_FIELDS') as $k) {
			$settings[$k] = getDolGlobalString($k);
		}
		$buildings = array();
		foreach ($calc->buildings as $b) {
			$acc = null;
			if ($b->fk_bank_reserve) {
				$a = new Account($this->db);
				if ($a->fetch($b->fk_bank_reserve) > 0) {
					$acc = array('id' => (int) $a->id, 'ref' => $a->ref, 'label' => $a->label, 'balance' => (float) $a->solde(1));
				}
			}
			$prod = null;
			if ($b->fk_product_reserve) {
				$p = new Product($this->db);
				if ($p->fetch($b->fk_product_reserve) > 0) {
					$prod = array('id' => (int) $p->id, 'ref' => $p->ref, 'vat' => (float) $p->tva_tx);
				}
			}
			$buildings[] = array('id' => (int) $b->rowid, 'ref' => $b->ref, 'label' => $b->label, 'reserve_account' => $acc, 'reserve_product' => $prod);
		}
		$units = array();
		foreach ($rows as $r) {
			$u = $r['unit'];
			$units[] = array(
				'id' => (int) $u->rowid, 'ref' => $u->ref, 'building' => $u->building_ref, 'socid' => (int) $u->fk_soc, 'socname' => $u->socname,
				'area' => (float) $r['area'], 'area_from_thirdparty' => !empty($u->area_from_soc), 'reserve_rate' => (float) $u->reserve_rate, 'admin_rate_net' => (float) $u->admin_rate,
				'rate_bk' => $r['bk_rate'], 'rate_reserve' => $r['reserve_rate'], 'rate_admin' => $r['admin_rate'],
				'bk' => $r['bk'], 'reserve' => $r['reserve'], 'admin' => $r['admin'], 'total' => $r['total'],
				'household_insurance' => HvRepository::hhStatus($u, $this->db),
			);
		}
		$areas = $calc->areas();
		return array('entity' => (int) $conf->entity, 'settings' => $settings, 'buildings' => $buildings, 'units' => $units,
			'monthly_costs_by_building' => $calc->monthlyCostsByBuilding(), 'area_total' => $areas[0],
			'total_month' => round(array_sum(array_column($units, 'total')), 2));
	}

	/**
	 * Kostenplan
	 *
	 * @return array
	 *
	 * @url GET costitems
	 */
	public function costitems()
	{
		$this->check();
		$repo = new HvRepository($this->db);
		$out = array();
		foreach ($repo->getCostItems(false) as $c) {
			$out[] = array('id' => (int) $c->rowid, 'label' => $c->label, 'building_id' => (int) $c->fk_building, 'period_months' => (int) $c->period_months,
				'amount_net' => (float) $c->amount_net, 'vat_rate' => (float) $c->vat_rate, 'gross' => HvCalculator::gross($c), 'monthly' => round(HvCalculator::monthly($c), 4),
				'supplier' => $c->suppliername, 'reference' => $c->reference, 'note' => $c->note, 'active' => (int) $c->active);
		}
		return $out;
	}

	/**
	 * Jahresabrechnung (Berechnung + gebuchter Stand)
	 *
	 * @param int $year Jahr
	 * @return array
	 *
	 * @url GET settlement/{year}
	 */
	public function settlement($year)
	{
		$this->check();
		$st = new HvSettlement($this->db);
		$d = $st->compute((int) $year);
		$units = array();
		foreach ($d['units'] as $r) {
			$units[] = array('ref' => $r['unit']->ref, 'socname' => $r['unit']->socname, 'actual' => $r['actual'], 'prepaid' => $r['prepaid'], 'paid' => $r['paid'], 'saldo' => $r['saldo'],
				'booked' => $r['booked'] ? array('saldo' => (float) $r['booked']->saldo, 'fk_invoice' => (int) $r['booked']->fk_invoice, 'credit_remaining' => (float) $r['booked']->credit_remaining) : null);
		}
		return array('year' => (int) $year, 'actual_by_costitem' => $d['actual'], 'units' => $units);
	}
}
