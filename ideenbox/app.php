<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */
/** Anleitung: App „Ideenbox“ mit Dolibarr verbinden */
/** Mobile Oberfläche: Eingangsrechnung fotografieren und zuordnen, offene Posten, Rücklagen */
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

$langs->loadLangs(array('ideenbox@ideenbox', 'users'));
if (!$user->hasRight('ideenbox', 'read') || !empty($user->socid)) {
	accessforbidden();
}
llxHeader('', $langs->trans('IbAppTitle'), '', '', 0, 0, '', '', '', 'mod-ideenbox page-app');
print load_fiche_titre($langs->trans('IbAppTitle'), '', 'fa-mobile-alt');

$url = preg_replace('#/+$#', '', DOL_MAIN_URL_ROOT);
print '<p>'.$langs->trans('IbAppIntro').'</p>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('IbAppUrl').'</td><td><input type="text" readonly class="flat minwidth400" value="'.dol_escape_htmltag($url).'" onclick="this.select()"></td></tr>';
print '<tr><td>'.$langs->trans('IbAppKey').'</td><td>'.$langs->trans('IbAppKeyHelp').'<br><a href="'.DOL_URL_ROOT.'/user/card.php?id='.$user->id.'&action=edit">'.img_picto('', 'user', 'class="pictofixedwidth"').$user->getFullName($langs).'</a></td></tr>';
print '<tr><td>'.$langs->trans('Module').'</td><td>'.(isModEnabled('api') ? img_picto('', 'tick').' '.$langs->trans('IbAppApiOk') : img_warning().' <span class="error">'.$langs->trans('IbAppApiMissing').'</span>').'</td></tr>';
print '<tr><td>'.$langs->trans('IbTargetEntity').'</td><td>'.$langs->trans('IbAppEntityHelp').'</td></tr>';
print '</table>';
print '<p class="margintoponly">'.$langs->trans('IbAppInstall').'</p>';

llxFooter();
$db->close();
