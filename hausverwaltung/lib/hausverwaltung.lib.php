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
	global $db, $conf;
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
	$list = array_filter(array_map('trim', explode(',', getDolGlobalString('HAUSVERWALTUNG_DOC_LANGS', 'hr_HR,de_DE,en_US,sl_SI,uk_UA'))));
	// Sprachen aller Eigentümer automatisch dazunehmen (neue Eigentümer mit neuer Sprache)
	$res = $db->query("SELECT DISTINCT s.default_lang FROM ".$db->prefix()."hv_unit u INNER JOIN ".$db->prefix()."societe s ON s.rowid = u.fk_soc WHERE u.entity = ".((int) $conf->entity)." AND s.default_lang IS NOT NULL AND s.default_lang <> ''");
	while ($res && ($o = $db->fetch_object($res))) {
		if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $o->default_lang)) {
			$list[] = $o->default_lang;
		}
	}
	$cache = array_values(array_unique(array_merge(array($orig), $list)));
	return $cache;
}

/**
 * Textschlüssel, die in Dokumenten/E-Mails an Eigentümer vorkommen (für die Seite "Sprachen").
 *
 * @return array<string,string> key => deutscher Text
 */
function hvDocKeys()
{
	$file = dol_buildpath('/hausverwaltung/langs/de_DE/hausverwaltung.lang', 0);
	$out = array();
	$extra = array('HvBuildingNo', 'HvUnit', 'HvAllBuildings', 'HvReservePlan', 'HvBuilding', 'HvReserveAccount', 'HvBalance', 'HvMovesIn', 'HvMovesOut',
		'HvPlanAssumptions', 'HvPlanMonthly', 'HvPlanCurrentRate', 'HvPlanRequiredRate', 'HvPlanMinBalance', 'HvPlanOk', 'HvPlanShortfall', 'HvPlanRequiredHelp', 'HvPlanProjection',
		'HvPlanEndBalance', 'HvPlanMeasures', 'HvPlanItems', 'HvPlanYear', 'HvPlanAmount', 'HvPlanBasis', 'HvPlanTodayPrices', 'HvPlanDisclaimer');
	foreach (file($file) ?: array() as $line) {
		if (!preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
			continue;
		}
		if (preg_match('/^(HvPdf|HvMail|HvStmt|HvLine|HvInvoice|HvKey_|HvInfo)/', $m[1]) || in_array($m[1], $extra, true)) {
			if (!in_array($m[1], array('HvMailFromSetting', 'HvMailBccSetting', 'HvMailTo', 'HvMailFiles', 'HvMailLastSent', 'HvMailNoAddress', 'HvMailNoPdf', 'HvMailNoInvoices', 'HvMailTestTo', 'HvMailSendTest', 'HvMailSendReal', 'HvMailConfirm', 'HvMailTestSent', 'HvMailSent', 'HvMailTestAddressInvalid', 'HvMailHelp', 'HvPdfPreview', 'HvPdfOriginal', 'HvPdfCopyShort', 'HvInvoiceNumber', 'HvStmtSection', 'HvStmtHelp', 'HvStmtGenerate', 'HvStmtGenerated', 'HvStmtSend', 'HvStmtSendConfirm', 'HvInfoAttachSetting', 'HvInfoAttachHelp'), true)) {
				$out[$m[1]] = trim($m[2]);
			}
		}
	}
	return $out;
}

/** Übersetzungen einer Sprache: Modul-Sprachdatei + eigene Übersetzungen (Datenbank, Vorrang) */
function hvLangValues($lang)
{
	global $db, $conf;
	$vals = array();
	$file = dol_buildpath('/hausverwaltung/langs/'.$lang.'/hausverwaltung.lang', 0);
	if (is_readable($file)) {
		foreach (file($file) as $line) {
			if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
				$vals[$m[1]] = array('value' => trim($m[2]), 'src' => 'file');
			}
		}
	}
	$res = $db->query("SELECT transkey, transvalue FROM ".$db->prefix()."overwrite_trans WHERE lang = '".$db->escape($lang)."' AND entity IN (0, ".((int) $conf->entity).") AND transkey LIKE 'Hv%'");
	while ($res && ($o = $db->fetch_object($res))) {
		$vals[$o->transkey] = array('value' => $o->transvalue, 'src' => 'db');
	}
	return $vals;
}

/** Eigene Übersetzung speichern (Dolibarr-Tabelle overwrite_trans, gilt nur für diese Firma) */
function hvSaveTrans($lang, $key, $value)
{
	global $db, $conf;
	$db->query("DELETE FROM ".$db->prefix()."overwrite_trans WHERE lang = '".$db->escape($lang)."' AND transkey = '".$db->escape($key)."' AND entity = ".((int) $conf->entity));
	if (trim((string) $value) !== '') {
		$db->query("INSERT INTO ".$db->prefix()."overwrite_trans (lang, transkey, transvalue, entity) VALUES ('".$db->escape($lang)."', '".$db->escape($key)."', '".$db->escape(trim((string) $value))."', ".((int) $conf->entity).")");
	}
	if (!getDolGlobalString('MAIN_ENABLE_OVERWRITE_TRANSLATION')) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		dolibarr_set_const($db, 'MAIN_ENABLE_OVERWRITE_TRANSLATION', '1', 'chaine', 0, '', $conf->entity);
	}
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

/** Externe Benutzer (Eigentümer-Zugang) mit Hausverwaltungs-Rechten – darf es nicht geben (Daten aller Einheiten) */
function hvExternalWithRights()
{
	global $db, $conf;
	$e = (int) $conf->entity;
	$p = $db->prefix();
	$sql = "SELECT DISTINCT u.login FROM ".$p."user u WHERE u.fk_soc > 0 AND u.statut = 1 AND (";
	$sql .= " EXISTS (SELECT 1 FROM ".$p."user_rights ur INNER JOIN ".$p."rights_def rd ON rd.id = ur.fk_id WHERE ur.fk_user = u.rowid AND rd.module = 'hausverwaltung' AND ur.entity = ".$e.")";
	$sql .= " OR EXISTS (SELECT 1 FROM ".$p."usergroup_user ugu INNER JOIN ".$p."usergroup_rights ugr ON ugr.fk_usergroup = ugu.fk_usergroup INNER JOIN ".$p."rights_def rd ON rd.id = ugr.fk_id WHERE ugu.fk_user = u.rowid AND rd.module = 'hausverwaltung' AND ugr.entity = ".$e."))";
	$out = array();
	$res = $db->query($sql);
	while ($res && ($o = $db->fetch_object($res))) {
		$out[] = $o->login;
	}
	return $out;
}

/**
 * Adresse der Online-Hilfe (Handbuch für die Verwaltung bzw. Hilfe für Eigentümer) in der Sprache des Benutzers.
 * Basisadresse: Einstellung HAUSVERWALTUNG_HELP_URL, z. B. https://helpdolibarr.greenresorts.hr/
 *
 * @param string $page    Seite relativ zum Bereich, z. B. 'vorschreibung/'
 * @param string $section 'verwaltung' oder 'eigentuemer'
 * @param string $lang    Sprache (leer = Sprache des Benutzers)
 */
function hvHelpUrl($page = '', $section = 'verwaltung', $lang = '')
{
	global $langs;
	$base = rtrim(getDolGlobalString('HAUSVERWALTUNG_HELP_URL'), '/');
	if ($base === '' || !preg_match('#^https?://#i', $base)) {
		return '';
	}
	$l = substr($lang !== '' ? $lang : (is_object($langs) ? $langs->defaultlang : 'de_DE'), 0, 2);
	if ($section === 'eigentuemer') {
		$prefix = $l === 'hr' ? '' : (in_array($l, array('de', 'en', 'sl', 'uk'), true) ? $l.'/' : 'en/');
	} else {
		$section = 'verwaltung';
		$prefix = $l === 'hr' ? 'hr/' : '';
	}
	return $base.'/'.$section.'/'.$prefix.$page;
}
