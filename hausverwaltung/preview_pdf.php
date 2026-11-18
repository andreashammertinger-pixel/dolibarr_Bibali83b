<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** PDF-Vorschau einer Vorschreibung (nichts wird gespeichert) */
// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
dol_include_once('/hausverwaltung/core/modules/facture/doc/pdf_hausverwaltung.modules.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills'));
if (!$user->hasRight('hausverwaltung', 'invoice')) {
	accessforbidden();
}
$unitId = GETPOSTINT('unit');
$period = GETPOST('period', 'alphanohtml');
$periodTo = GETPOST('period_to', 'alphanohtml');
$copy = GETPOSTINT('copy');
if (!preg_match('/^\d{4}-\d{2}$/', (string) $period) || ($periodTo !== '' && !preg_match('/^\d{4}-\d{2}$/', (string) $periodTo))) {
	accessforbidden('Bad period');
}

$inv = new HvInvoicer($db);
$f = $inv->previewInvoice($unitId, $period, $periodTo);
if (!$f) {
	print $langs->trans('HvNoThirdparty');
	exit;
}
$model = new pdf_hausverwaltung($db);
$tmpdir = $conf->hausverwaltung->dir_temp ?? (DOL_DATA_ROOT.'/hausverwaltung/temp');
dol_mkdir($tmpdir);
$file = $tmpdir.'/preview_'.$user->id.'_'.$unitId.'.pdf';
$model->previewfile = $file;
$ol = new Translate('', $conf);
if ($copy && !empty($f->thirdparty->default_lang)) {
	$model->copylang = $f->thirdparty->default_lang;
	$ol->setDefaultLang($f->thirdparty->default_lang);
}
if ($model->write_file($f, $ol) <= 0) {
	print dol_escape_htmltag($model->error);
	exit;
}
$name = 'Vorschau_'.dol_sanitizeFileName($f->thirdparty->name).'_'.HvInvoicer::periodLabel($period, $periodTo).($copy ? '_'.$model->copylang : '').'.pdf';
$name = str_replace(array('/', '–'), array('-', '-'), $name);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$name.'"');
header('Content-Length: '.filesize($file));
readfile($file);
dol_delete_file($file, 0, 0, 0, null, false, 0);
$db->close();
