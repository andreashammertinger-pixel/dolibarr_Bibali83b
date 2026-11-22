<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/bausteine.php
 * \brief   Textbausteine (Hinweise) Deutsch / Kroatisch / Englisch
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


dol_include_once('/kuechenangebot/lib/kuechenangebot.lib.php');
$langs->loadLangs(array('kuechenangebot@kuechenangebot', 'admin'));
if (!$user->hasRight('kuechenangebot', 'setup') || !empty($user->socid)) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
if ($action === 'save') {
	$rows = GETPOST('b', 'array');
	foreach ((is_array($rows) ? $rows : array()) as $rid => $r) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'ka_baustein SET label = \''.$db->escape(dol_string_nohtmltag($r['label'] ?? '')).'\', text_de = \''.$db->escape(dol_string_nohtmltag($r['de'] ?? '')).'\', text_hr = \''.$db->escape(dol_string_nohtmltag($r['hr'] ?? '')).'\', text_en = \''.$db->escape(dol_string_nohtmltag($r['en'] ?? '')).'\', vorauswahl = '.(!empty($r['vorauswahl']) ? 1 : 0).', aktiv = '.(!empty($r['aktiv']) ? 1 : 0).', rang = '.((int) ($r['rang'] ?? 0));
		$sql .= ' WHERE rowid = '.((int) $rid).' AND entity = '.((int) $conf->entity);
		$db->query($sql);
	}
	$code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', GETPOST('new_code', 'alphanohtml')));
	if ($code !== '' && GETPOST('new_de', 'restricthtml') !== '') {
		$db->query('INSERT INTO '.MAIN_DB_PREFIX.'ka_baustein (entity, code, label, text_de, text_hr, text_en, vorauswahl, aktiv, rang) VALUES ('.((int) $conf->entity).", '".$db->escape($code)."', '".$db->escape(GETPOST('new_label', 'alphanohtml'))."', '".$db->escape(dol_string_nohtmltag(GETPOST('new_de', 'restricthtml')))."', '".$db->escape(dol_string_nohtmltag(GETPOST('new_hr', 'restricthtml')))."', '".$db->escape(dol_string_nohtmltag(GETPOST('new_en', 'restricthtml')))."', ".(GETPOST('new_vorauswahl', 'int') ? 1 : 0).', 1, 999)');
	}
	setEventMessages($langs->trans('KaSaved'), null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
if ($action === 'del') {
	$db->query('DELETE FROM '.MAIN_DB_PREFIX.'ka_baustein WHERE rowid = '.GETPOSTINT('rowid').' AND entity = '.((int) $conf->entity));
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
if ($action === 'seed') {
	kaSeedBausteine($db);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
llxHeader('', $langs->trans('KaBausteine'), '', '', 0, 0, '', '', '', 'mod-kuechenangebot page-bausteine');
print load_fiche_titre($langs->trans('KaSetup'), '', 'title_setup');
print dol_get_fiche_head(kaAdminPrepareHead(), 'bausteine', '', -1, '');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('KaCode').'</td><td>'.$langs->trans('KaTextDe').'</td><td>'.$langs->trans('KaTextHr').'</td><td>'.$langs->trans('KaTextEn').'</td><td class="center">'.$langs->trans('KaPreselect').'</td><td class="center">'.$langs->trans('KaActive').'</td><td></td></tr>';
foreach (kaFetchBausteine($db, false) as $code => $b) {
	$n = 'b['.$b->rowid.']';
	print '<tr class="oddeven"><td class="tdtop"><b>'.dol_escape_htmltag($code).'</b><br><input name="'.$n.'[label]" value="'.dol_escape_htmltag($b->label).'" class="width150"><br><input name="'.$n.'[rang]" value="'.((int) $b->rang).'" class="width50" title="Reihenfolge"></td>';
	foreach (array('de', 'hr', 'en') as $l) {
		print '<td><textarea name="'.$n.'['.$l.']" rows="4" class="centpercent">'.dol_escape_htmltag($b->{'text_'.$l}).'</textarea></td>';
	}
	print '<td class="center"><input type="checkbox" name="'.$n.'[vorauswahl]" value="1"'.($b->vorauswahl ? ' checked' : '').'></td><td class="center"><input type="checkbox" name="'.$n.'[aktiv]" value="1"'.($b->aktiv ? ' checked' : '').'></td>';
	print '<td><a href="'.$_SERVER['PHP_SELF'].'?action=del&rowid='.$b->rowid.'&token='.newToken().'">'.img_delete().'</a></td></tr>';
}
print '<tr class="oddeven"><td class="tdtop"><input name="new_code" placeholder="'.$langs->trans('KaCode').'" class="width100"><br><input name="new_label" placeholder="'.$langs->trans('Label').'" class="width150"></td>';
foreach (array('de', 'hr', 'en') as $l) {
	print '<td><textarea name="new_'.$l.'" rows="3" class="centpercent"></textarea></td>';
}
print '<td class="center"><input type="checkbox" name="new_vorauswahl" value="1"></td><td></td><td></td></tr>';
print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> <a class="button" href="'.$_SERVER['PHP_SELF'].'?action=seed&token='.newToken().'">Standard-Bausteine ergänzen</a></div></form>';
print dol_get_fiche_end();
llxFooter();
$db->close();
