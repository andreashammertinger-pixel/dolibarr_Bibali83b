<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvrepository.class.php');

/**
 * Berechnung der monatlichen Vorschreibung je Einheit – entspricht der Excel-Logik
 * (Blätter "Verteilung" und "Monatsabrechnung"):
 *
 *  Kosten mit Gebäude 0  -> Verteilung nach m² über ALLE Einheiten
 *  Kosten mit Gebäude n  -> Verteilung nach m² nur über Einheiten in Gebäude n
 *  Monatsanteil einer Kostenposition = Brutto / Periode (12, 6, 3, 1 Monate)
 *  Rücklage   = m² × Rücklage-Satz €/m² (Satz je Einheit, typ. je Gebäude unterschiedlich)
 *  Verwaltung = m² × Verwaltungs-Satz €/m²
 */
class HvCalculator
{
	public $db;
	public $buildings = array();
	public $units = array();
	public $costitems = array();

	public function __construct($db, $buildings = null, $units = null, $costitems = null)
	{
		$this->db = $db;
		if ($buildings === null || $units === null || $costitems === null) {
			$repo = new HvRepository($db);
			$buildings = $repo->getBuildings();
			$units = $repo->getUnits();
			$costitems = $repo->getCostItems();
		}
		$this->buildings = $buildings;
		$this->units = $units;
		$this->costitems = $costitems;
	}

	public static function gross($c)
	{
		return (float) $c->amount_net * (1 + (float) $c->vat_rate / 100);
	}

	public static function monthly($c)
	{
		$p = max(1, (int) $c->period_months);
		return self::gross($c) / $p;
	}

	/** Summe m² je Gebäude, Schlüssel 0 = alle */
	public function areas()
	{
		$a = array(0 => 0.0);
		foreach ($this->units as $u) {
			$b = (int) $u->fk_building;
			if (!isset($a[$b])) {
				$a[$b] = 0.0;
			}
			$a[$b] += (float) $u->area;
			$a[0] += (float) $u->area;
		}
		return $a;
	}

	/** Monatliche Kosten je Gebäude-Schlüssel (0 = alle) */
	public function monthlyCostsByBuilding()
	{
		$m = array(0 => 0.0);
		foreach ($this->buildings as $b) {
			$m[(int) $b->rowid] = 0.0;
		}
		foreach ($this->costitems as $c) {
			$k = (int) $c->fk_building;
			if (!isset($m[$k])) {
				$m[$k] = 0.0;
			}
			$m[$k] += self::monthly($c);
		}
		return $m;
	}

	/**
	 * Ergebnis je Einheit.
	 *
	 * @return array<int,array{unit:object,share_all:float,share_building:float,bk_all:float,bk_building:float,bk:float,reserve:float,admin:float,total:float}>
	 */
	public function compute()
	{
		$areas = $this->areas();
		$mc = $this->monthlyCostsByBuilding();
		$out = array();
		foreach ($this->units as $id => $u) {
			$b = (int) $u->fk_building;
			$area = (float) $u->area;
			$shareAll = $areas[0] > 0 ? $area / $areas[0] : 0;
			$shareB = !empty($areas[$b]) ? $area / $areas[$b] : 0;
			$bkAll = ($mc[0] ?? 0) * $shareAll;
			$bkB = ($mc[$b] ?? 0) * $shareB;
			$bk = round($bkAll + $bkB, 2);
			$reserve = round($area * (float) $u->reserve_rate, 2);
			$admin = round($area * (float) $u->admin_rate, 2);
			$out[$id] = array(
				'unit' => $u,
				'share_all' => $shareAll,
				'share_building' => $shareB,
				'bk_all' => $bkAll,
				'bk_building' => $bkB,
				'bk' => $bk,
				'reserve' => $reserve,
				'admin' => $admin,
				'total' => round($bk + $reserve + $admin, 2),
			);
		}
		return $out;
	}

	/**
	 * Verteilt einen Betrag einer Kostenposition (Gebäude 0 = alle) auf die Einheiten.
	 *
	 * @return array<int,float> unit_id => anteil
	 */
	public function allocate($amount, $fk_building)
	{
		$areas = $this->areas();
		$fk_building = (int) $fk_building;
		$base = $fk_building === 0 ? $areas[0] : ($areas[$fk_building] ?? 0);
		$res = array();
		foreach ($this->units as $id => $u) {
			if ($fk_building !== 0 && (int) $u->fk_building !== $fk_building) {
				continue;
			}
			$res[$id] = $base > 0 ? (float) $amount * (float) $u->area / $base : 0;
		}
		return $res;
	}
}
