<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Jahresabrechnung: Ist-Kosten (Eingangsrechnungen) vs. Akonto je Apartment */
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
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills'));
if (!$user->hasRight('hausverwaltung', 'read')) {
	accessforbidden();
}
$year = GETPOSTINT('year') ?: (int) dol_print_date(dol_now(), '%Y');
$calc = new HvCalculator($db);
$repo = new HvRepository($db);
$costitems = $repo->getCostItems(false);
$d1 = $db->idate(dol_get_first_day($year, 1));
$d2 = $db->idate(dol_get_last_day($year, 12));

// Ist-Kosten je Kostenart aus freigegebenen Eingangsrechnungen (ohne "aus Rücklage bezahlt")
$actual = array();
$unassigned = 0;
$sql = "SELECT fe.hv_costitem, SUM(f.total_ttc) as total FROM ".$db->prefix()."facture_fourn f";
$sql .= " INNER JOIN ".$db->prefix()."facture_fourn_extrafields fe ON fe.fk_object = f.rowid";
$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut IN (1,2) AND f.datef BETWEEN '".$d1."' AND '".$d2."'";
$sql .= " AND (fe.hv_reserve IS NULL OR fe.hv_reserve = 0) AND fe.hv_costitem > 0 GROUP BY fe.hv_costitem";
$res = $db->query($sql);
while ($res && ($o = $db->fetch_object($res))) {
	$actual[(int) $o->hv_costitem] = (float) $o->total;
}

// Ist-Anteil je Apartment
$unitActual = array();
foreach ($actual as $cid => $amount) {
	$fk_b = isset($costitems[$cid]) ? (int) $costitems[$cid]->fk_building : 0;
	foreach ($calc->allocate($amount, $fk_b) as $uid => $v) {
		$unitActual[$uid] = ($unitActual[$uid] ?? 0) + $v;
	}
}

// Vorgeschriebene / bezahlte Akonto-BK je Apartment
$pBk = new Product($db);
$pBk->fetch(0, HvInvoicer::REF_BK);
$prepaid = array();
$paid = array();
if ($pBk->id > 0) {
	$sql = "SELECT fe.hv_unit, SUM(d.total_ttc) as total, SUM(CASE WHEN f.paye = 1 THEN d.total_ttc ELSE 0 END) as paid FROM ".$db->prefix()."facturedet d";
	$sql .= " INNER JOIN ".$db->prefix()."facture f ON f.rowid = d.fk_facture";
	$sql .= " INNER JOIN ".$db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid";
	$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut IN (1,2) AND d.fk_product = ".((int) $pBk->id);
	$sql .= " AND fe.hv_period LIKE '".((int) $year)."-%' GROUP BY fe.hv_unit";
	$res = $db->query($sql);
	while ($res && ($o = $db->fetch_object($res))) {
		$prepaid[(int) $o->hv_unit] = (float) $o->total;
		$paid[(int) $o->hv_unit] = (float) $o->paid;
	}
}

llxHeader('', $langs->trans('HvSettlement'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvSettlement').' '.$year, '<form method="GET"><input type="number" name="year" class="width75" value="'.$year.'"> <input type="submit" class="button small" value="OK"></form>', 'bill');

print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvCostItem').'</th><th>'.$langs->trans('HvBuilding').'</th><th class="right">'.$langs->trans('HvBudget').'</th><th class="right">'.$langs->trans('HvActual').'</th><th class="right">'.$langs->trans('HvDifference').'</th></tr>';
$tb = $ta = 0;
foreach ($costitems as $c) {
	$budget = HvCalculator::monthly($c) * 12 * ($c->active ? 1 : 0);
	$act = $actual[(int) $c->rowid] ?? 0;
	if ($budget == 0 && $act == 0) {
		continue;
	}
	$tb += $budget;
	$ta += $act;
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($c->label).'</td><td>'.dol_escape_htmltag(hvBuildingLabel($c->fk_building, $calc->buildings)).'</td><td class="right">'.hvMoney($budget).'</td><td class="right">'.hvMoney($act).'</td><td class="right">'.hvMoney($budget - $act).'</td></tr>';
}
print '<tr class="liste_total"><td colspan="2">'.$langs->trans('Total').'</td><td class="right">'.hvMoney($tb).'</td><td class="right">'.hvMoney($ta).'</td><td class="right">'.hvMoney($tb - $ta).'</td></tr></table></div><br>';

print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvOwner').'</th><th class="right">'.$langs->trans('HvActual').'</th><th class="right">'.$langs->trans('HvPrepaid').'</th><th class="right">'.$langs->trans('AlreadyPaid').'</th><th class="right">'.$langs->trans('HvSaldo').'</th></tr>';
foreach ($calc->units as $uid => $u) {
	$a = $unitActual[$uid] ?? 0;
	$p = $prepaid[$uid] ?? 0;
	$s = $p - $a;
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($u->ref).'</td><td>'.dol_escape_htmltag($u->socname).'</td><td class="right">'.hvMoney($a).'</td><td class="right">'.hvMoney($p).'</td><td class="right">'.hvMoney($paid[$uid] ?? 0).'</td><td class="right '.($s < 0 ? 'error' : 'ok').'"><strong>'.hvMoney($s).'</strong></td></tr>';
}
print '</table></div>';
print '<br><div class="opacitymedium">Ist-Kosten = freigegebene Eingangsrechnungen des Jahres mit Kostenart, ohne Rechnungen „aus Rücklage bezahlt“. Verteilung nach m² wie im Kostenplan (alle Gebäude bzw. nur das jeweilige Gebäude).</div>';

llxFooter();
$db->close();
