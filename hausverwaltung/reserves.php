<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Rücklagen je Gebäude: Kontostand, Zuführungen, Entnahmen */
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

require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvcalculator.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'banks', 'bills'));
if (!$user->hasRight('hausverwaltung', 'read') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie – sie enthält Daten aller Einheiten
	accessforbidden();
}
$year = GETPOSTINT('year') ?: (int) dol_print_date(dol_now(), '%Y');
$repo = new HvRepository($db);
$buildings = $repo->getBuildings(false);
$calc = new HvCalculator($db);
$rows = $calc->compute();
$planned = array();
foreach ($rows as $r) {
	$b = (int) $r['unit']->fk_building;
	$planned[$b] = ($planned[$b] ?? 0) + $r['reserve'];
}

$sums = array();
$sql = "SELECT fk_building, direction, SUM(amount) as total FROM ".$db->prefix()."hv_reserve_move WHERE entity = ".((int) $conf->entity);
$sql .= " AND date_move BETWEEN '".$db->idate(dol_get_first_day($year, 1))."' AND '".$db->idate(dol_get_last_day($year, 12))."' GROUP BY fk_building, direction";
$res = $db->query($sql);
while ($res && ($o = $db->fetch_object($res))) {
	$sums[(int) $o->fk_building][$o->direction] = (float) $o->total;
}

llxHeader('', $langs->trans('HvReserves'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvReserves').' '.$year, '<form method="GET"><input type="number" name="year" class="width75" value="'.$year.'"> <input type="submit" class="button small" value="OK"></form>', 'bank_account');

print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvBuilding').'</th><th>'.$langs->trans('HvReserveAccount').'</th><th class="right">'.$langs->trans('HvMonthly').' ('.$langs->trans('HvBudget').')</th><th class="right">'.$langs->trans('HvMovesIn').' '.$year.'</th><th class="right">'.$langs->trans('HvMovesOut').' '.$year.'</th><th class="right">'.$langs->trans('HvBalance').'</th></tr>';
foreach ($buildings as $b) {
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($b->ref.' – '.$b->label).'</td><td>';
	$bal = null;
	if ($b->fk_bank_reserve) {
		$acc = new Account($db);
		if ($acc->fetch($b->fk_bank_reserve) > 0) {
			print $acc->getNomUrl(1);
			$bal = $acc->solde(1);
		}
	} else {
		print '<span class="warning">'.$langs->trans('HvNoReserveAccount').'</span>';
	}
	print '</td><td class="right">'.hvMoney($planned[$b->rowid] ?? 0).'</td><td class="right">'.hvMoney($sums[$b->rowid]['in'] ?? 0).'</td><td class="right">'.hvMoney($sums[$b->rowid]['out'] ?? 0).'</td><td class="right"><strong>'.($bal !== null ? hvMoney($bal) : '-').'</strong></td></tr>';
}
print '</table></div><br>';

print load_fiche_titre($langs->trans('HvLastMoves'), '', '');
$sql = "SELECT m.*, b.ref as bref FROM ".$db->prefix()."hv_reserve_move m LEFT JOIN ".$db->prefix()."hv_building b ON b.rowid = m.fk_building WHERE m.entity = ".((int) $conf->entity)." ORDER BY m.date_move DESC, m.rowid DESC";
$res = $db->query($sql.$db->plimit(50));
print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('HvBuilding').'</th><th>'.$langs->trans('Type').'</th><th>'.$langs->trans('Invoice').'</th><th class="right">'.$langs->trans('Amount').'</th><th>'.$langs->trans('BankLine').'</th></tr>';
while ($res && ($o = $db->fetch_object($res))) {
	$invurl = $o->element === 'payment_supplier' ? DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$o->fk_invoice : DOL_URL_ROOT.'/compta/facture/card.php?facid='.$o->fk_invoice;
	print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($o->date_move), 'day').'</td><td>'.dol_escape_htmltag($o->bref).'</td><td>'.($o->direction === 'in' ? $langs->trans('HvMovesIn') : $langs->trans('HvMovesOut')).'</td>';
	print '<td><a href="'.$invurl.'">#'.((int) $o->fk_invoice).'</a></td><td class="right">'.hvMoney($o->amount).'</td><td><a href="'.DOL_URL_ROOT.'/compta/bank/line.php?rowid='.((int) $o->fk_bank_line_to).'">'.((int) $o->fk_bank_line_to).'</a></td></tr>';
}
print '</table>';

llxFooter();
$db->close();
