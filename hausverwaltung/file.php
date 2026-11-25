<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Download der Abrechnungsblätter – nur interne Benutzer mit Leserecht */
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
if (!$user->hasRight('hausverwaltung', 'read') || !empty($user->socid)) {
	accessforbidden();
}
$rel = (string) GETPOST('file', 'alphanohtml');
if ((!preg_match('#^statements/\d{4}/ABR-[A-Za-z0-9._-]+\.pdf$#', $rel) && !preg_match('#^mailing/\d+/[A-Za-z0-9._ ()-]+$#', $rel)) || strpos($rel, '..') !== false) {
	httponly_accessforbidden('Bad file');
}
$path = $conf->hausverwaltung->dir_output.'/'.$rel;
if (!is_readable($path)) {
	httponly_accessforbidden('Not found', 404);
}
top_httphead(dol_mimetype($path));
header('Content-Disposition: inline; filename="'.basename($path).'"');
header('Content-Length: '.filesize($path));
readfile($path);
