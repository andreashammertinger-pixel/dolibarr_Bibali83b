<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/list.php
 * \brief   Liste der Küchenkalkulationen
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
llxHeader('', $langs->trans('KaMenuList'), '', '', 0, 0, '', '', '', 'mod-kuechenangebot page-list');
$new = $user->hasRight('kuechenangebot', 'write') ? dolGetButtonTitle($langs->trans('KaMenuNew'), '', 'fa fa-plus-circle', dol_buildpath('/kuechenangebot/card.php', 1).'?action=create') : '';
print load_fiche_titre($langs->trans('KaMenuList'), $new, 'propal');
$p = new Propal($db);
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>#</td><td>'.$langs->trans('KaLabel').'</td><td>'.$langs->trans('KaCustomer').'</td><td>'.$langs->trans('KaArt').'</td><td class="right">'.$langs->trans('KaQty').'</td><td class="right">'.$langs->trans('KaVk').'</td><td>'.$langs->trans('KaProposal').'</td><td class="center">'.$langs->trans('Date').'</td></tr>';
$rows = KaKalk::fetchList($db);
foreach ($rows as $o) {
	$url = dol_buildpath('/kuechenangebot/card.php', 1).'?id='.$o->rowid;
	print '<tr class="oddeven"><td><a href="'.$url.'">'.img_picto('', 'propal', 'class="pictofixedwidth"').$o->rowid.'</a></td>';
	$pp = (array) json_decode((string) $o->params, true);
	$ver = max(1, (int) ($pp['version'] ?? 1));
	print '<td><a href="'.$url.'">'.dol_escape_htmltag($o->label ?: '-').'</a>'.($ver > 1 || !empty($pp['gesperrt']) ? ' <span class="badge badge-'.(!empty($pp['gesperrt']) ? 'secondary' : 'info').'">V'.$ver.'</span>' : '').'</td>';
	print '<td>'.($o->fk_soc ? '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$o->fk_soc.'">'.dol_escape_htmltag($o->socname).'</a>' : '').'</td>';
	print '<td>'.$langs->trans($o->art === 'mfh' ? 'KaArtMfh' : 'KaArtPrivat').'</td>';
	print '<td class="right">'.kaFmt($o->total_stueck, 0).'</td><td class="right nowraponall">'.kaMoney($o->total_ht).'</td>';
	print '<td>'.($o->fk_propal ? '<a href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$o->fk_propal.'">'.dol_escape_htmltag($o->propref).'</a> '.$p->LibStatut((int) $o->propstatus, 5) : '').'</td>';
	print '<td class="center">'.dol_print_date($db->jdate($o->datec), 'day').'</td></tr>';
}
if (!$rows) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
llxFooter();
$db->close();
