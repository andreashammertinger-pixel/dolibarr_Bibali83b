<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvrepository.class.php');

/**
 * Berechnung der monatlichen Vorschreibung je Einheit – entspricht der Excel-Logik
 * (Blätter "Verteilung" und "Monatsabrechnung"):
 *
 *  Kosten mit Gebäude 0  -> Verteilung über ALLE Einheiten
 *  Kosten mit Gebäude n  -> Verteilung nur über Einheiten in Gebäude n
 *  Schlüssel je Kostenart: m² (Standard), Miteigentumsanteil oder gleich je Einheit
 *  Monatsanteil einer Kostenposition = Brutto / Periode (12, 6, 3, 1 Monate)
 *  Rücklage   = m² × Rücklage-Satz €/m² (Satz je Einheit, typ. je Gebäude unterschiedlich)
 *  Verwaltung = m² × Verwaltungs-Satz €/m² (netto) × (1 + USt Honorar)
 *    Der Verwalter stellt sein Honorar mit USt in Rechnung; die Gemeinschaft kann keine
 *    Vorsteuer abziehen, daher wird der Bruttobetrag vorgeschrieben.
 */
class HvCalculator
{
	public $db;
	public $buildings = array();
	public $units = array();
	public $costitems = array();
	/** @var array<string,bool> Schlüssel, die mangels Werten auf m² ausgewichen sind */
	public $warnings = array();

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

	/** USt-Satz auf das Verwaltungshonorar (%) */
	public static function adminVatRate()
	{
		return (float) getDolGlobalString('HAUSVERWALTUNG_ADMIN_VAT_RATE', '25');
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

	/** Verteilschlüssel: area = m², share = Miteigentumsanteil, units = gleich je Einheit */
	public static function keys()
	{
		return array('area', 'share', 'units');
	}

	/** Gewicht einer Einheit für einen Schlüssel */
	public static function weight($u, $key)
	{
		if ($key === 'share') {
			return (float) $u->share;
		}
		if ($key === 'units') {
			return 1.0;
		}
		return (float) $u->area;
	}

	/** Summe des Schlüssels je Gebäude, Index 0 = alle */
	public function sums($key = 'area')
	{
		$a = array(0 => 0.0);
		foreach ($this->units as $u) {
			$b = (int) $u->fk_building;
			$w = self::weight($u, $key);
			$a[$b] = ($a[$b] ?? 0) + $w;
			$a[0] += $w;
		}
		return $a;
	}

	/** Summe m² je Gebäude, Schlüssel 0 = alle */
	public function areas()
	{
		return $this->sums('area');
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
			$m[$k] = ($m[$k] ?? 0) + self::monthly($c);
		}
		return $m;
	}

	/**
	 * Ergebnis je Einheit (Monatswerte).
	 *
	 * @return array<int,array>
	 */
	public function compute()
	{
		// Betriebskosten je Einheit: jede Kostenart nach ihrem Schlüssel auf "alle" bzw. "ihr Gebäude" verteilen
		$bkAll = $bkB = array();
		foreach ($this->costitems as $c) {
			$key = !empty($c->alloc_key) && in_array($c->alloc_key, self::keys(), true) ? $c->alloc_key : 'area';
			foreach ($this->allocate(self::monthly($c), (int) $c->fk_building, $key) as $uid => $v) {
				if ((int) $c->fk_building === 0) {
					$bkAll[$uid] = ($bkAll[$uid] ?? 0) + $v;
				} else {
					$bkB[$uid] = ($bkB[$uid] ?? 0) + $v;
				}
			}
		}
		$areas = $this->sums('area');
		$out = array();
		$dec = getDolGlobalInt('HAUSVERWALTUNG_RATE_DECIMALS', 2);
		foreach ($this->units as $id => $u) {
			$b = (int) $u->fk_building;
			$area = (float) $u->area;
			// Je m²-Sätze (Vorschreibung: Einzelpreis = €/m² und Monat, Menge = m²)
			$bkRate = $area > 0 ? round((($bkAll[$id] ?? 0) + ($bkB[$id] ?? 0)) / $area, $dec) : 0;
			$reserveRate = round((float) $u->reserve_rate, $dec);
			$adminRate = round((float) $u->admin_rate * (1 + self::adminVatRate() / 100), $dec);
			$bk = round($bkRate * $area, 2);
			$reserve = round($reserveRate * $area, 2);
			$adminNet = round($area * (float) $u->admin_rate, 2);
			$admin = round($adminRate * $area, 2);
			$out[$id] = array(
				'unit' => $u,
				'share_all' => $areas[0] > 0 ? $area / $areas[0] : 0,
				'share_building' => !empty($areas[$b]) ? $area / $areas[$b] : 0,
				'bk_all' => $bkAll[$id] ?? 0,
				'bk_building' => $bkB[$id] ?? 0,
				'area' => $area,
				'bk_rate' => $bkRate,
				'reserve_rate' => $reserveRate,
				'admin_rate' => $adminRate,
				'bk' => $bk,
				'reserve' => $reserve,
				'admin_net' => $adminNet,
				'admin' => $admin,
				'total' => round($bk + $reserve + $admin, 2),
			);
		}
		return $out;
	}

	/**
	 * Verteilt einen Betrag (Gebäude 0 = alle) nach Schlüssel auf die Einheiten.
	 *
	 * @return array<int,float> unit_id => anteil
	 */
	public function allocate($amount, $fk_building, $key = 'area')
	{
		$sums = $this->sums($key);
		$fk_building = (int) $fk_building;
		$base = $fk_building === 0 ? $sums[0] : ($sums[$fk_building] ?? 0);
		if ($base <= 0 && $key !== 'area') {
			// Schlüssel nicht befüllt (z. B. keine Anteile hinterlegt): nach m² verteilen, damit keine Kosten verloren gehen
			$this->warnings[$key] = true;
			$key = 'area';
			$sums = $this->sums('area');
			$base = $fk_building === 0 ? $sums[0] : ($sums[$fk_building] ?? 0);
		}
		$res = array();
		foreach ($this->units as $id => $u) {
			if ($fk_building !== 0 && (int) $u->fk_building !== $fk_building) {
				continue;
			}
			$res[$id] = $base > 0 ? (float) $amount * self::weight($u, $key) / $base : 0;
		}
		return $res;
	}

}
