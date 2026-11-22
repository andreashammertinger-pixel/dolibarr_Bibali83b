<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/kennzahlen.php
 * \brief   Kennzahlen bisheriger Küchen (automatisch aus angenommenen Angeboten + händisch)
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


require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
dol_include_once('/kuechenangebot/class/kakalk.class.php');
dol_include_once('/kuechenangebot/lib/kuechenangebot.lib.php');
$langs->loadLangs(array('kuechenangebot@kuechenangebot', 'propal'));
if (!$user->hasRight('kuechenangebot', 'read') || !empty($user->socid)) {
	accessforbidden();
}
$cansetup = $user->hasRight('kuechenangebot', 'setup');
$action = GETPOST('action', 'aZ09');
if ($action === 'add' && $cansetup) {
	$art = GETPOST('art', 'aZ09') === 'mfh' ? 'mfh' : 'privat';
	$d = dol_mktime(12, 0, 0, GETPOSTINT('datummonth'), GETPOSTINT('datumday'), GETPOSTINT('datumyear'));
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'ka_kennzahl (entity, datum, art, label, stueck, lfm, ek, kosten, vk, montage_std, note) VALUES ('
		.((int) $conf->entity).', '.($d ? "'".$db->idate($d)."'" : 'NULL').", '".$art."', '".$db->escape(GETPOST('label', 'alphanohtml'))."', "
		.max(1, kaNum('stueck', 1)).', '.kaNum('lfm').', '.kaNum('ek').', '.kaNum('kosten').', '.kaNum('vk').', '.kaNum('montage_std').", '".$db->escape(GETPOST('note', 'alphanohtml'))."')";
	if ($db->query($sql)) {
		setEventMessages($langs->trans('KaSaved'), null);
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
if ($action === 'del' && $cansetup) {
	$db->query('DELETE FROM '.MAIN_DB_PREFIX.'ka_kennzahl WHERE rowid = '.GETPOSTINT('rowid').' AND entity = '.((int) $conf->entity));
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
$form = new Form($db);
llxHeader('', $langs->trans('KaKennzahlen'), '', '', 0, 0, '', '', '', 'mod-kuechenangebot page-kennzahlen');
print load_fiche_titre($langs->trans('KaKennzahlen'), '', 'propal');
print '<div class="opacitymedium">'.$langs->trans('KaKennzahlenIntro').'</div><br>';

foreach (array('privat' => 'KaArtPrivat', 'mfh' => 'KaArtMfh') as $art => $lab) {
	$k = new KaKalk($db);
	$k->art = $art;
	$b = $k->benchmark();
	print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="6">'.$langs->trans($lab).'</td></tr>';
	print '<tr class="oddeven"><td>n = '.$b['n'].' · '.kaFmt($b['stueck'], 0).' '.$langs->trans('KaQty').'</td><td>'.$langs->trans('KaEk').': '.kaMoney($b['ek']).'</td><td>'.$langs->trans('KaVk').': '.kaMoney($b['vk']).'</td>';
	print '<td>'.$langs->trans('KaFactorShort').': <b>'.kaFmt($b['faktor'], 3).'</b></td><td>'.$langs->trans('KaVkLfm').': <b>'.kaMoney($b['vk_lfm']).'</b></td><td>'.$langs->trans('KaAvgPiece').': <b>'.kaMoney($b['vk_stueck']).'</b></td></tr></table><br>';
}

// automatisch
print load_fiche_titre($langs->trans('KaKennzahlAuto'), '', '');
$sql = 'SELECT k.rowid, k.label, k.art, k.total_ek, p.total_ht, k.total_lfm, k.total_stueck, k.total_kosten, p.ref, p.rowid as pid, p.fk_statut, p.datep';
$sql .= ' FROM '.MAIN_DB_PREFIX.'ka_kalk as k INNER JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = k.fk_propal';
$sql .= ' WHERE k.entity = '.((int) $conf->entity).' AND p.fk_statut IN (2, 4) ORDER BY p.datep DESC';
$res = $db->query($sql);
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('KaProposal').'</td><td>'.$langs->trans('KaLabel').'</td><td>'.$langs->trans('KaArt').'</td><td class="right">'.$langs->trans('KaQty').'</td><td class="right">'.$langs->trans('KaLfm').'</td><td class="right">'.$langs->trans('KaEk').'</td><td class="right">'.$langs->trans('KaKosten').'</td><td class="right">'.$langs->trans('KaVk').'</td><td class="right">'.$langs->trans('KaFactorShort').'</td></tr>';
$n = 0;
while ($res && ($o = $db->fetch_object($res))) {
	$n++;
	print '<tr class="oddeven"><td><a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$o->pid.'">'.dol_escape_htmltag($o->ref).'</a></td><td><a href="'.dol_buildpath('/kuechenangebot/card.php', 1).'?id='.$o->rowid.'">'.dol_escape_htmltag($o->label).'</a></td>';
	print '<td>'.$langs->trans($o->art === 'mfh' ? 'KaArtMfh' : 'KaArtPrivat').'</td><td class="right">'.kaFmt($o->total_stueck, 0).'</td><td class="right">'.kaFmt($o->total_lfm).'</td><td class="right">'.kaMoney($o->total_ek).'</td><td class="right">'.kaMoney($o->total_kosten).'</td><td class="right">'.kaMoney($o->total_ht).'</td><td class="right">'.($o->total_ek > 0 ? kaFmt($o->total_ht / $o->total_ek, 3) : '').'</td></tr>';
}
if (!$n) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table><br>';

// händisch
print load_fiche_titre($langs->trans('KaKennzahlManual'), '', '');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('KaDate').'</td><td>'.$langs->trans('KaLabel').'</td><td>'.$langs->trans('KaArt').'</td><td class="right">'.$langs->trans('KaQty').'</td><td class="right">'.$langs->trans('KaLfm').'</td><td class="right">'.$langs->trans('KaEk').'</td><td class="right">'.$langs->trans('KaKosten').'</td><td class="right">'.$langs->trans('KaVk').'</td><td class="right">'.$langs->trans('KaHours').'</td><td>'.$langs->trans('KaNote').'</td><td></td></tr>';
$res = $db->query('SELECT * FROM '.MAIN_DB_PREFIX.'ka_kennzahl WHERE entity = '.((int) $conf->entity).' ORDER BY datum DESC, rowid DESC');
while ($res && ($o = $db->fetch_object($res))) {
	print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($o->datum), 'day').'</td><td>'.dol_escape_htmltag($o->label).'</td><td>'.$langs->trans($o->art === 'mfh' ? 'KaArtMfh' : 'KaArtPrivat').'</td>';
	print '<td class="right">'.kaFmt($o->stueck, 0).'</td><td class="right">'.kaFmt($o->lfm).'</td><td class="right">'.kaMoney($o->ek).'</td><td class="right">'.kaMoney($o->kosten).'</td><td class="right">'.kaMoney($o->vk).'</td><td class="right">'.kaFmt($o->montage_std, 1).'</td><td>'.dol_escape_htmltag($o->note).'</td>';
	print '<td class="right">'.($cansetup ? '<a href="'.$_SERVER['PHP_SELF'].'?action=del&rowid='.$o->rowid.'&token='.newToken().'">'.img_delete().'</a>' : '').'</td></tr>';
}
if ($cansetup) {
	print '<tr class="oddeven"><td>'.$form->selectDate(-1, 'datum', 0, 0, 1, '', 1, 0).'</td><td><input name="label" class="minwidth150"></td>';
	print '<td><select name="art"><option value="privat">'.$langs->trans('KaArtPrivat').'</option><option value="mfh">'.$langs->trans('KaArtMfh').'</option></select></td>';
	print '<td class="right"><input name="stueck" value="1" class="width50 right"></td><td class="right"><input name="lfm" class="width50 right"></td><td class="right"><input name="ek" class="width75 right"></td><td class="right"><input name="kosten" class="width75 right"></td><td class="right"><input name="vk" class="width75 right"></td><td class="right"><input name="montage_std" class="width50 right"></td><td><input name="note" class="minwidth100"></td>';
	print '<td><input type="submit" class="button smallpaddingimp" value="'.$langs->trans('KaAdd').'"></td></tr>';
}
print '</table></form>';
llxFooter();
$db->close();
