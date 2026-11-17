<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

/**
 * Datenzugriff für Gebäude, Einheiten und Kostenplan (mandantenfähig über entity).
 */
class HvRepository
{
	/** @var DoliDB */
	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	protected function entity()
	{
		global $conf;
		return (int) $conf->entity;
	}

	protected function fetchAll($sql)
	{
		$out = array();
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return $out;
		}
		while ($obj = $this->db->fetch_object($res)) {
			$out[(int) $obj->rowid] = $obj;
		}
		$this->db->free($res);
		return $out;
	}

	/** @return array<int,object> */
	public function getBuildings($onlyactive = true)
	{
		$sql = "SELECT * FROM ".$this->db->prefix()."hv_building WHERE entity = ".$this->entity();
		if ($onlyactive) {
			$sql .= " AND active = 1";
		}
		$sql .= " ORDER BY position, ref";
		return $this->fetchAll($sql);
	}

	/**
	 * Extrafelder des Geschäftspartners, aus denen die m² gelesen werden (erstes befülltes gewinnt).
	 * Standard: wohnflaeche_m2 (reine Wohnfläche, Beschluss: Abrechnung über Wohnfläche), dann bk_schluessel.
	 *
	 * @return string[] vorhandene Spalten
	 */
	public function areaFields()
	{
		return $this->socFields(getDolGlobalString('HAUSVERWALTUNG_AREA_FIELDS', 'wohnflaeche_m2,bk_schluessel'));
	}

	/** Extrafelder für den Miteigentumsanteil (Standard: eigentumsanteil) */
	public function shareFields()
	{
		return $this->socFields(getDolGlobalString('HAUSVERWALTUNG_SHARE_FIELDS', 'eigentumsanteil'));
	}

	/** @return string[] vorhandene Spalten aus einer kommagetrennten Liste */
	protected function socFields($list)
	{
		static $cacheAll = array();
		if (isset($cacheAll[$list])) {
			return $cacheAll[$list];
		}
		$cache = array();
		$wanted = array_filter(array_map('trim', explode(',', $list)));
		$res = $this->db->query("SHOW COLUMNS FROM ".$this->db->prefix()."societe_extrafields");
		$cols = array();
		while ($res && ($o = $this->db->fetch_object($res))) {
			$cols[] = $o->Field;
		}
		foreach ($wanted as $w) {
			if (preg_match('/^[a-z0-9_]+$/', $w) && in_array($w, $cols, true)) {
				$cache[] = $w;
			}
		}
		$cacheAll[$list] = $cache;
		return $cache;
	}

	public function hasSocField($name)
	{
		static $cols = null;
		if ($cols === null) {
			$cols = array();
			$res = $this->db->query("SHOW COLUMNS FROM ".$this->db->prefix()."societe_extrafields");
			while ($res && ($o = $this->db->fetch_object($res))) {
				$cols[] = $o->Field;
			}
		}
		return in_array($name, $cols, true);
	}

	/**
	 * Status Haushaltsversicherung: ok | expiring (< 30 Tage) | expired | missing
	 */
	public static function hhStatus($u, $db)
	{
		if (empty($u->hh_valid_until)) {
			return 'missing';
		}
		$t = $db->jdate($u->hh_valid_until);
		if ($t < dol_now()) {
			return 'expired';
		}
		return ($t < dol_now() + 30 * 86400) ? 'expiring' : 'ok';
	}

	/** @return array<int,object> */
	public function getUnits($onlyactive = true)
	{
		$fields = $this->areaFields();
		$areaExpr = 'u.area';
		if ($fields) {
			$parts = array();
			foreach ($fields as $f) {
				$parts[] = "NULLIF(se.".$f.", 0)";
			}
			$areaExpr = "COALESCE(".implode(', ', $parts).", u.area)";
		}
		$sf = $this->shareFields();
		$shareExpr = 'u.share';
		if ($sf) {
			$sp = array();
			foreach ($sf as $f) {
				$sp[] = "NULLIF(ses.".$f.", 0)";
			}
			$shareExpr = "COALESCE(".implode(', ', $sp).", u.share)";
		}
		$hh = $this->hasSocField('hv_hh_valid_until') ? "seh.hv_hh_valid_until as hh_valid_until, seh.hv_hh_insurer as hh_insurer," : "NULL as hh_valid_until, NULL as hh_insurer,";
		$sql = "SELECT u.*, u.area as area_module, ".$areaExpr." as area, u.share as share_module, ".$shareExpr." as share, ".$hh." ".($fields ? "CASE WHEN COALESCE(".implode(', ', $parts).") IS NULL THEN 0 ELSE 1 END" : "0")." as area_from_soc, s.nom as socname, b.ref as building_ref FROM ".$this->db->prefix()."hv_unit u";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe s ON s.rowid = u.fk_soc";
		if ($fields) {
			$sql .= " LEFT JOIN ".$this->db->prefix()."societe_extrafields se ON se.fk_object = u.fk_soc";
		}
		if ($sf) {
			$sql .= " LEFT JOIN ".$this->db->prefix()."societe_extrafields ses ON ses.fk_object = u.fk_soc";
		}
		if ($this->hasSocField('hv_hh_valid_until')) {
			$sql .= " LEFT JOIN ".$this->db->prefix()."societe_extrafields seh ON seh.fk_object = u.fk_soc";
		}
		$sql .= " LEFT JOIN ".$this->db->prefix()."hv_building b ON b.rowid = u.fk_building";
		$sql .= " WHERE u.entity = ".$this->entity();
		if ($onlyactive) {
			$sql .= " AND u.active = 1";
		}
		$sql .= " ORDER BY u.position, u.ref";
		return $this->fetchAll($sql);
	}

	/** @return array<int,object> */
	public function getCostItems($onlyactive = true)
	{
		$sql = "SELECT c.*, s.nom as suppliername FROM ".$this->db->prefix()."hv_costitem c";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe s ON s.rowid = c.fk_soc_supplier";
		$sql .= " WHERE c.entity = ".$this->entity();
		if ($onlyactive) {
			$sql .= " AND c.active = 1";
		}
		$sql .= " ORDER BY c.period_months DESC, c.position, c.label";
		return $this->fetchAll($sql);
	}

	/**
	 * Generisches Speichern (Insert wenn rowid leer, sonst Update)
	 *
	 * @param string $table  hv_building | hv_unit | hv_costitem
	 * @param array  $fields feld => wert (bereits validiert)
	 * @param int    $rowid
	 * @return int  rowid oder <0
	 */
	public function save($table, array $fields, $rowid = 0)
	{
		if (!in_array($table, array('hv_building', 'hv_unit', 'hv_costitem'), true)) {
			return -1;
		}
		$set = array();
		foreach ($fields as $k => $v) {
			if (!preg_match('/^[a-z_]+$/', $k)) {
				return -2;
			}
			if ($v === null || $v === '') {
				$set[$k] = 'NULL';
			} elseif (is_int($v) || is_float($v)) {
				$set[$k] = (string) price2num($v);
			} else {
				$set[$k] = "'".$this->db->escape($v)."'";
			}
		}
		if ($rowid > 0) {
			$parts = array();
			foreach ($set as $k => $v) {
				$parts[] = $k." = ".$v;
			}
			$sql = "UPDATE ".$this->db->prefix().$table." SET ".implode(', ', $parts)." WHERE rowid = ".((int) $rowid)." AND entity = ".$this->entity();
		} else {
			$set['entity'] = (string) $this->entity();
			$set['datec'] = "'".$this->db->idate(dol_now())."'";
			$sql = "INSERT INTO ".$this->db->prefix().$table." (".implode(', ', array_keys($set)).") VALUES (".implode(', ', $set).")";
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -3;
		}
		return $rowid > 0 ? (int) $rowid : (int) $this->db->last_insert_id($this->db->prefix().$table);
	}

	public function delete($table, $rowid)
	{
		if (!in_array($table, array('hv_building', 'hv_unit', 'hv_costitem'), true)) {
			return -1;
		}
		$sql = "DELETE FROM ".$this->db->prefix().$table." WHERE rowid = ".((int) $rowid)." AND entity = ".$this->entity();
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -2;
		}
		return 1;
	}

	/** Gebäude anhand Bankkonto der Rücklage */
	public function getBuildingByReserveAccount($fk_bank)
	{
		foreach ($this->getBuildings(false) as $b) {
			if ((int) $b->fk_bank_reserve === (int) $fk_bank) {
				return $b;
			}
		}
		return null;
	}
}
