<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Weiterleitung zur Online-Hilfe: Eigentümer (externe Benutzer) → Hilfe für Eigentümer, Mitarbeiter → Handbuch; jeweils in der Sprache des Benutzers */
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

dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
$for = GETPOST('for', 'aZ09');
$section = (!empty($user->socid) || $for === 'owner') ? 'eigentuemer' : 'verwaltung';
$url = hvHelpUrl('', $section);
if ($url === '') {
	$langs->load('hausverwaltung@hausverwaltung');
	llxHeader('', $langs->trans('HvHelp'));
	print info_admin($langs->trans('HvHelpNotConfigured'));
	llxFooter();
	exit;
}
header('Location: '.$url);
exit;
