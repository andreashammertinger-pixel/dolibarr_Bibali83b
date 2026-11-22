<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/calcpdf.php
 * \brief   Interne Kalkulationsaufstellung als PDF anzeigen (nicht beim Angebot abgelegt)
 */
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

dol_include_once('/kuechenangebot/class/kacalcpdf.class.php');
$langs->loadLangs(array('kuechenangebot@kuechenangebot', 'propal'));
if (!$user->hasRight('kuechenangebot', 'read') || !empty($user->socid)) {
	accessforbidden();
}
$kalk = new KaKalk($db);
if ($kalk->fetch(GETPOSTINT('id')) <= 0) {
	httponly_accessforbidden('not found', 404);
}
$out = (new KaCalcPdf($db))->build($kalk);
$name = 'Kalkulation_intern_'.$kalk->id.($kalk->version() > 1 ? '_V'.$kalk->version() : '').'.pdf';
top_httphead('application/pdf');
header('Content-Disposition: inline; filename="'.$name.'"');
header('Content-Length: '.strlen($out));
header('Cache-Control: private, no-store');
print $out;
exit;
