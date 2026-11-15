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

	/** @return array<int,object> */
	public function getUnits($onlyactive = true)
	{
		$sql = "SELECT u.*, s.nom as socname, b.ref as building_ref FROM ".$this->db->prefix()."hv_unit u";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe s ON s.rowid = u.fk_soc";
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
