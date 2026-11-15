<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvrepository.class.php');

/** Auswahlfeld Gebäude; $withAll = Option "0 = alle Gebäude" */
function hvSelectBuilding($name, $selected, $buildings, $withAll = false, $morecss = 'minwidth150')
{
	global $langs;
	$out = '<select name="'.dol_escape_htmltag($name).'" id="'.dol_escape_htmltag($name).'" class="flat '.$morecss.'">';
	if ($withAll) {
		$out .= '<option value="0"'.((string) $selected === '0' ? ' selected' : '').'>'.$langs->trans('HvAllBuildings').'</option>';
	}
	foreach ($buildings as $b) {
		$out .= '<option value="'.((int) $b->rowid).'"'.((int) $selected === (int) $b->rowid ? ' selected' : '').'>'.dol_escape_htmltag($b->ref.' – '.$b->label).'</option>';
	}
	$out .= '</select>';
	return $out;
}

function hvSelectPeriod($name, $selected)
{
	global $langs;
	$opts = array(12 => 'HvPeriod12', 6 => 'HvPeriod6', 3 => 'HvPeriod3', 1 => 'HvPeriod1');
	$out = '<select name="'.$name.'" class="flat">';
	foreach ($opts as $k => $v) {
		$out .= '<option value="'.$k.'"'.((int) $selected === $k ? ' selected' : '').'>'.$langs->trans($v).'</option>';
	}
	return $out.'</select>';
}

function hvBuildingLabel($id, $buildings)
{
	global $langs;
	if ((int) $id === 0) {
		return $langs->trans('HvAllBuildings');
	}
	return isset($buildings[(int) $id]) ? $buildings[(int) $id]->ref.' – '.$buildings[(int) $id]->label : '?';
}

function hvMoney($v)
{
	global $langs, $conf;
	return price($v, 0, $langs, 1, -1, 2, $conf->currency);
}

function hvAdminPrepareHead()
{
	global $langs;
	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath('/hausverwaltung/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;
	return $head;
}
