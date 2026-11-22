<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/admin/setup.php
 * \brief   Einstellungen Küchenangebot
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
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
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


require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/kuechenangebot/lib/kuechenangebot.lib.php');
$langs->loadLangs(array('admin', 'kuechenangebot@kuechenangebot'));
if (!$user->admin && !$user->hasRight('kuechenangebot', 'setup')) {
	accessforbidden();
}
$num = array(
	'KA_KOSTEN_STUNDE' => array('KaSetupCostHour', '45'), 'KA_SATZ_STUNDE' => array('KaSetupRateHour', '65'), 'KA_SATZ_LFM' => array('KaSetupRateLfm', '0'),
	'KA_STD_PRO_LFM' => array('KaSetupHoursLfm', '2.5'), 'KA_GK_PRIVAT' => array('KaSetupGkPrivat', '12'), 'KA_GK_MFH' => array('KaSetupGkMfh', '8'),
	'KA_AUFSCHLAG_PRIVAT' => array('KaSetupMarkupPrivat', '30'), 'KA_AUFSCHLAG_MFH' => array('KaSetupMarkupMfh', '20'),
	'KA_FAKTOR_EUR' => array('KaSetupFactorEur', '1'), 'KA_FAKTOR_PUNKTE' => array('KaSetupFactorPoints', '1'),
	'KA_TVA' => array('KaSetupVat', '20'), 'KA_GUELTIG_TAGE' => array('KaSetupValid', '30'),
);
$txt = array('KA_PDF_FARBE' => 'KaSetupColor', 'KA_AGB_URL' => 'KaSetupAgbUrl', 'KA_KONTAKT' => 'KaSetupContact');
$action = GETPOST('action', 'aZ09');
if ($action === 'save') {
	foreach ($num as $k => $d) {
		dolibarr_set_const($db, $k, (string) kaNum($k, (float) $d[1]), 'chaine', 0, '', $conf->entity);
	}
	foreach ($txt as $k => $lab) {
		dolibarr_set_const($db, $k, trim(GETPOST($k, 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	}
	$l2 = GETPOST('KA_LANG2_DEFAULT', 'aZ09');
	dolibarr_set_const($db, 'KA_LANG2_DEFAULT', array_key_exists($l2, kaLang2Options()) ? $l2 : '', 'chaine', 0, '', $conf->entity);
	foreach (array('DE', 'HR', 'EN') as $l) {
		$v = trim(GETPOST('KA_TERMS_'.$l, 'restricthtml'));
		$def = kaDefaultTerms();
		if ($v === trim($def[strtolower($l)])) {
			$v = ''; // unverändert = Standard behalten (bekommt Aktualisierungen)
		}
		dolibarr_set_const($db, 'KA_TERMS_'.$l, dol_string_nohtmltag($v), 'chaine', 0, '', $conf->entity);
		if ($l !== 'DE') {
			dolibarr_set_const($db, 'KA_DICT_'.$l, dol_string_nohtmltag(GETPOST('KA_DICT_'.$l, 'restricthtml')), 'chaine', 0, '', $conf->entity);
		}
	}
	setEventMessages($langs->trans('KaSetupSaved'), null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('KaSetup'), '', '', 0, 0, '', '', '', 'mod-kuechenangebot page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('KaSetup'), $linkback, 'title_setup');
print dol_get_fiche_head(kaAdminPrepareHead(), 'setup', '', -1, '');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="2">'.$langs->trans('KaSetupCosts').'</td></tr>';
foreach ($num as $k => $d) {
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans($d[0]).'</td><td><input name="'.$k.'" value="'.dol_escape_htmltag(str_replace('.', ',', getDolGlobalString($k, $d[1]))).'" class="width75 right"></td></tr>';
}
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('KaSetupPdf').'</td></tr>';
foreach ($txt as $k => $lab) {
	print '<tr class="oddeven"><td>'.$langs->trans($lab).'</td><td><input name="'.$k.'" value="'.dol_escape_htmltag(getDolGlobalString($k)).'" class="minwidth400">';
	if ($k === 'KA_AGB_URL') {
		print '<br><span class="opacitymedium small">'.$langs->trans('KaSetupAgbHint').'</span>';
	}
	print '</td></tr>';
}
print '<tr class="oddeven"><td>'.$langs->trans('KaSetupLang2Default').'</td><td><select name="KA_LANG2_DEFAULT"><option value="">'.$langs->trans('KaLang2None').'</option>';
foreach (kaLang2Options() as $k => $lab) {
	print '<option value="'.$k.'"'.(getDolGlobalString('KA_LANG2_DEFAULT') === $k ? ' selected' : '').'>'.dol_escape_htmltag($lab).'</option>';
}
print '</select></td></tr>';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('KaSetupTerms').' <span class="opacitymedium small">'.$langs->trans('KaSetupTermsHint').'</span></td></tr>';
$def = kaDefaultTerms();
foreach (array('DE' => 'Deutsch', 'HR' => 'Hrvatski', 'EN' => 'English') as $l => $lab) {
	$v = getDolGlobalString('KA_TERMS_'.$l) ?: $def[strtolower($l)];
	print '<tr class="oddeven"><td class="tdtop">'.$lab.'</td><td><textarea name="KA_TERMS_'.$l.'" rows="14" class="centpercent">'.dol_escape_htmltag($v).'</textarea></td></tr>';
}
print '<tr class="liste_titre"><td colspan="2">Wörterbuch Nobilia-Begriffe (Ergänzungen, eine Zeile je Begriff: <code>deutsch = übersetzung</code>)</td></tr>';
foreach (array('HR' => 'Hrvatski', 'EN' => 'English') as $l => $lab) {
	print '<tr class="oddeven"><td class="tdtop">'.$lab.'</td><td><textarea name="KA_DICT_'.$l.'" rows="4" class="centpercent" placeholder="Kaschmirgrau = kašmir siva">'.dol_escape_htmltag(getDolGlobalString('KA_DICT_'.$l)).'</textarea></td></tr>';
}
print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
print '<br><div class="opacitymedium small">Texte des Deckblatts (Hersteller, Qualität, Gewährleistung usw.) lassen sich über <i>Einstellungen → Übersetzung</i> anpassen, z. B. Schlüssel <code>KaPdfDurableText</code> oder <code>KaPdfWarrantyText</code>.</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
