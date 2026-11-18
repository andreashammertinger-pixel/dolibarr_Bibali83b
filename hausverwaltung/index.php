<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Übersicht: Verteilung und Monatsabrechnung (entspricht Excel-Blättern Verteilung/Monatsabrechnung/Jahresübersicht) */
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
dol_include_once('/hausverwaltung/class/hvcalculator.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills'));
if (!$user->hasRight('hausverwaltung', 'read') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie – sie enthält Daten aller Einheiten
	accessforbidden();
}

$calc = new HvCalculator($db);
$rows = $calc->compute();
$areas = $calc->areas();
$mc = $calc->monthlyCostsByBuilding();

llxHeader('', $langs->trans('Hausverwaltung'), hvHelpUrl(''), '', 0, 0, '', '', '', 'mod-hausverwaltung page-index');
print load_fiche_titre($langs->trans('HvOverview'), '', 'building');

if (empty($calc->units)) {
	print info_admin($langs->trans('HvImportSeedHelp').' → <a href="'.dol_buildpath('/hausverwaltung/admin/setup.php', 1).'">'.$langs->trans('HvSetupPage').'</a>');
}

$ext = hvExternalWithRights();
if ($ext) {
	print info_admin($langs->trans('HvExternalRightsWarning', implode(', ', array_map('dol_escape_htmltag', $ext))), 0, 0, 'error');
}
if (!empty($calc->warnings)) {
	print info_admin($langs->trans('HvKeyFallback', implode(', ', array_map(function ($k) use ($langs) {
		return $langs->trans('HvKey_'.$k);
	}, array_keys($calc->warnings)))), 0, 0, 'warning');
}

// Warnung Haushaltsversicherung (Miteigentumsvertrag §10.2)
$hhMissing = array();
foreach ($calc->units as $u) {
	if (in_array(HvRepository::hhStatus($u, $db), array('missing', 'expired'), true)) {
		$hhMissing[] = $u->ref;
	}
}
if ($hhMissing) {
	print info_admin($langs->trans('HvHhWarning').': '.implode(', ', $hhMissing).' → <a href="'.dol_buildpath('/hausverwaltung/units.php', 1).'">'.$langs->trans('HvUnits').'</a>', 0, 0, 'warning');
}

// Monatliche Kosten je Gebäude
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('HvBuilding').'</th><th class="right">'.$langs->trans('HvArea').'</th><th class="right">'.$langs->trans('HvMonthly').' '.$langs->trans('HvBk').'</th></tr>';
foreach ($mc as $bid => $sum) {
	print '<tr class="oddeven"><td>'.dol_escape_htmltag(hvBuildingLabel($bid, $calc->buildings)).'</td>';
	print '<td class="right">'.price($areas[$bid] ?? 0, 0, $langs, 1, 2, 2).'</td>';
	print '<td class="right">'.hvMoney($sum).'</td></tr>';
}
print '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="right">'.price($areas[0], 0, $langs, 1, 2, 2).'</td><td class="right">'.hvMoney(array_sum($mc)).'</td></tr>';
print '</table></div><br>';

// Verteilung je Apartment
$t = array('bk' => 0, 'reserve' => 0, 'admin_net' => 0, 'admin' => 0, 'total' => 0, 'bk_all' => 0, 'bk_building' => 0);
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre">';
foreach (array('HvUnit' => '', 'HvBuilding' => '', 'HvOwner' => '', 'HvArea' => 'right', 'HvShareAll' => 'right', 'HvShareBuilding' => 'right', 'HvBkAll' => 'right', 'HvBkBuilding' => 'right', 'HvBk' => 'right', 'HvReserve' => 'right', 'HvAdminNet' => 'right', 'HvAdmin' => 'right', 'HvTotalMonth' => 'right', 'HvTotalYear' => 'right') as $k => $c) {
	print '<th class="'.$c.'">'.$langs->trans($k).'</th>';
}
print '</tr>';
foreach ($rows as $r) {
	$u = $r['unit'];
	print '<tr class="oddeven">';
	print '<td><a href="'.dol_buildpath('/hausverwaltung/units.php', 1).'?action=edit&id='.$u->rowid.'">'.dol_escape_htmltag($u->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag($u->building_ref).'</td>';
	print '<td>'.($u->fk_soc ? '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$u->fk_soc.'">'.dol_escape_htmltag($u->socname).'</a>' : '<span class="opacitymedium">'.$langs->trans('HvNoThirdparty').'</span>').'</td>';
	print '<td class="right">'.price($u->area, 0, $langs, 1, 2, 2).'</td>';
	print '<td class="right">'.price($r['share_all'] * 100, 0, $langs, 1, 2, 2).' %</td>';
	print '<td class="right">'.price($r['share_building'] * 100, 0, $langs, 1, 2, 2).' %</td>';
	print '<td class="right">'.hvMoney($r['bk_all']).'</td>';
	print '<td class="right">'.hvMoney($r['bk_building']).'</td>';
	print '<td class="right">'.hvMoney($r['bk']).'</td>';
	print '<td class="right">'.hvMoney($r['reserve']).'</td>';
	print '<td class="right opacitymedium">'.hvMoney($r['admin_net']).'</td>';
	print '<td class="right">'.hvMoney($r['admin']).'</td>';
	print '<td class="right"><strong>'.hvMoney($r['total']).'</strong></td>';
	print '<td class="right">'.hvMoney($r['total'] * 12).'</td>';
	print '</tr>';
	foreach ($t as $k => $v) {
		$t[$k] += $r[$k];
	}
}
print '<tr class="liste_total"><td colspan="3">'.$langs->trans('Total').'</td><td class="right">'.price($areas[0], 0, $langs, 1, 2, 2).'</td><td class="right">100 %</td><td></td>';
print '<td class="right">'.hvMoney($t['bk_all']).'</td><td class="right">'.hvMoney($t['bk_building']).'</td><td class="right">'.hvMoney($t['bk']).'</td><td class="right">'.hvMoney($t['reserve']).'</td><td class="right opacitymedium">'.hvMoney($t['admin_net']).'</td><td class="right">'.hvMoney($t['admin']).'</td><td class="right">'.hvMoney($t['total']).'</td><td class="right">'.hvMoney($t['total'] * 12).'</td></tr>';
print '</table></div>';

$diff = round($t['bk'] - array_sum($mc), 2);
print '<br><div class="opacitymedium">'.$langs->trans('HvCheckSum').': '.hvMoney(array_sum($mc)).' · '.$langs->trans('HvDifference').': '.hvMoney($diff).'</div>';

llxFooter();
$db->close();
