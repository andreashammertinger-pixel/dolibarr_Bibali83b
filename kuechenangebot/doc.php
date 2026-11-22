<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/doc.php
 * \brief   Unterlagen einer Küchenkalkulation herunterladen (nur intern, mit Leserecht)
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/kuechenangebot/class/kakalk.class.php');
if (!$user->hasRight('kuechenangebot', 'read') || !empty($user->socid)) {
	accessforbidden();
}
$kalk = new KaKalk($db);
if ($kalk->fetch(GETPOSTINT('id')) <= 0) {
	httponly_accessforbidden('not found', 404);
}
$name = basename(GETPOST('file', 'alphanohtml'));
$f = $kalk->getDir().'/unterlagen/'.$name;
if ($name === '' || !is_file($f)) {
	httponly_accessforbidden('not found', 404);
}
$mime = dol_mimetype($name);
$inline = preg_match('#^(image/(jpeg|png|gif|webp)|application/pdf)$#', $mime);
top_httphead($mime);
header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.str_replace('"', '', $name).'"');
header('Content-Length: '.filesize($f));
header('X-Content-Type-Options: nosniff');
readfile($f);
exit;
