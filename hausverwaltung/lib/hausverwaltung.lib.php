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

/**
 * OIB (kroatische Identifikationsnummer) aus einem Objekt lesen:
 * Kontakt-Extrafeld hv_oib, sonst Prof-ID 1, sonst USt-ID (ohne "HR"/"OIB:").
 *
 * @return string 11 Ziffern oder ''
 */
function hvOib($o)
{
	if (!is_object($o)) {
		return '';
	}
	if (empty($o->array_options) && method_exists($o, 'fetch_optionals')) {
		$o->fetch_optionals();
	}
	foreach (array($o->array_options['options_hv_oib'] ?? '', $o->idprof1 ?? '', $o->tva_intra ?? '') as $v) {
		$d = preg_replace('/\D/', '', (string) $v);
		if (strlen($d) === 11) {
			return $d;
		}
	}
	return '';
}

/** Prüfziffer OIB nach ISO 7064, MOD 11,10 */
function hvOibValid($oib)
{
	if (!preg_match('/^\d{11}$/', (string) $oib)) {
		return false;
	}
	$a = 10;
	for ($i = 0; $i < 10; $i++) {
		$a = ($a + (int) $oib[$i]) % 10;
		if ($a == 0) {
			$a = 10;
		}
		$a = ($a * 2) % 11;
	}
	$check = 11 - $a;
	if ($check == 10) {
		$check = 0;
	}
	return $check == (int) $oib[10];
}

/** Sprachen, in denen Dokumente für Eigentümer erstellt werden (erste = Original) */
function hvDocLangs()
{
	$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
	$list = array_filter(array_map('trim', explode(',', getDolGlobalString('HAUSVERWALTUNG_DOC_LANGS', 'hr_HR,de_DE,en_US,sl_SI,uk_UA'))));
	return array_values(array_unique(array_merge(array($orig), $list)));
}

/** Übersetzter Text eines Datensatzes (Feld i18n als JSON), Rückfall auf das Originalfeld */
function hvI18n($obj, $field, $lang)
{
	$tr = !empty($obj->i18n) ? json_decode($obj->i18n, true) : array();
	if (!empty($tr[$lang][$field])) {
		return $tr[$lang][$field];
	}
	$short = substr($lang, 0, 2);
	foreach ((array) $tr as $l => $v) {
		if (substr($l, 0, 2) === $short && !empty($v[$field])) {
			return $v[$field];
		}
	}
	return (string) ($obj->$field ?? '');
}

/** Gebäudebezeichnung sprachneutral: "Haus 1" in der Ausgabesprache, sonst Bezeichnung */
function hvBuildingName($b, $outlangs)
{
	if (!$b) {
		return $outlangs->transnoentities('HvAllBuildings');
	}
	return preg_match('/\d/', $b->ref) ? $outlangs->transnoentities('HvBuildingNo', preg_replace('/\D/', '', $b->ref)) : $b->label;
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
