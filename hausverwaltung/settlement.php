<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Jahresabrechnung: Ist vs. Akonto, Buchung von Nachzahlungen / Guthaben */
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvsettlement.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills'));
if (!$user->hasRight('hausverwaltung', 'read')) {
	accessforbidden();
}
$year = GETPOSTINT('year') ?: ((int) dol_print_date(dol_now(), '%Y') - 1);
$action = GETPOST('action', 'aZ09');
$form = new Form($db);
$st = new HvSettlement($db);
$log = array();
if ($action === 'confirm_book' && GETPOST('confirm') === 'yes' && $user->hasRight('hausverwaltung', 'invoice')) {
	$log = $st->book($user, $year, GETPOSTINT('validate') ? true : false);
}
$data = $st->compute($year);
$calc = new HvCalculator($db);

llxHeader('', $langs->trans('HvSettlement'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvSettlement').' '.$year, '<form method="GET"><input type="number" name="year" class="width75" value="'.$year.'"> <input type="submit" class="button small" value="OK"></form>', 'bill');

if ($action === 'book') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?year='.$year.'&validate='.GETPOSTINT('validate'), $langs->trans('HvBookSettlement'), $langs->trans('HvBookSettlementConfirm', $year), 'confirm_book', '', 0, 1);
}

if ($log) {
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Invoice').'</th><th class="right">'.$langs->trans('HvSaldo').'</th></tr>';
	foreach ($log as $l) {
		$link = '';
		if ($l['invoice_id']) {
			$f = new Facture($db);
			$f->fetch($l['invoice_id']);
			$link = $f->getNomUrl(1);
		}
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($l['unit']).'</td><td class="'.($l['status'] === 'error' ? 'error' : '').'">'.dol_escape_htmltag($l['status'].' '.$l['message']).'</td><td>'.$link.'</td><td class="right">'.hvMoney($l['saldo']).'</td></tr>';
	}
	print '</table><br>';
}

// Kostenarten Plan / Ist
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvCostItem').'</th><th>'.$langs->trans('HvBuilding').'</th><th class="right">'.$langs->trans('HvBudget').'</th><th class="right">'.$langs->trans('HvActual').'</th><th class="right">'.$langs->trans('HvDifference').'</th></tr>';
$tb = $ta = 0;
foreach ($data['costitems'] as $c) {
	$budget = HvCalculator::monthly($c) * 12 * ($c->active ? 1 : 0);
	$act = $data['actual'][(int) $c->rowid] ?? 0;
	if ($budget == 0 && $act == 0) {
		continue;
	}
	$tb += $budget;
	$ta += $act;
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($c->label).'</td><td>'.dol_escape_htmltag(hvBuildingLabel($c->fk_building, $calc->buildings)).'</td><td class="right">'.hvMoney($budget).'</td><td class="right">'.hvMoney($act).'</td><td class="right">'.hvMoney($budget - $act).'</td></tr>';
}
print '<tr class="liste_total"><td colspan="2">'.$langs->trans('Total').'</td><td class="right">'.hvMoney($tb).'</td><td class="right">'.hvMoney($ta).'</td><td class="right">'.hvMoney($tb - $ta).'</td></tr></table></div><br>';

// Je Apartment
$anyOpen = false;
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvOwner').'</th><th class="right">'.$langs->trans('HvActual').'</th><th class="right">'.$langs->trans('HvPrepaid').'</th><th class="right">'.$langs->trans('AlreadyPaid').'</th><th class="right">'.$langs->trans('HvSaldo').'</th><th>'.$langs->trans('Status').'</th></tr>';
foreach ($data['units'] as $uid => $r) {
	$u = $r['unit'];
	$b = $r['booked'];
	$status = '';
	if ($b) {
		if ((float) $b->saldo < 0 && $b->fk_invoice) {
			$f = new Facture($db);
			$f->fetch($b->fk_invoice);
			$status = $langs->trans('HvSettlementInvoiceCreated').' '.$f->getNomUrl(1);
		} elseif ((float) $b->saldo > 0) {
			$status = $langs->trans('HvCreditRemaining').': '.hvMoney($b->credit_remaining);
		} else {
			$status = $langs->trans('HvSettlementBooked');
		}
	} else {
		$anyOpen = true;
		$status = '<span class="opacitymedium">'.$langs->trans('HvNotBooked').'</span>';
	}
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($u->ref).'</td><td>'.dol_escape_htmltag($u->socname).'</td><td class="right">'.hvMoney($r['actual']).'</td><td class="right">'.hvMoney($r['prepaid']).'</td><td class="right">'.hvMoney($r['paid']).'</td><td class="right '.($r['saldo'] < 0 ? 'error' : 'ok').'"><strong>'.hvMoney($r['saldo']).'</strong></td><td>'.$status.'</td></tr>';
}
print '</table></div>';
print '<br><div class="opacitymedium">'.$langs->trans('HvSettlementHelp').'</div>';

if ($anyOpen && $user->hasRight('hausverwaltung', 'invoice')) {
	print '<br><form method="GET" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="year" value="'.$year.'"><input type="hidden" name="action" value="book">';
	print '<label><input type="checkbox" name="validate" value="1" checked> '.$langs->trans('HvValidateDirectly').'</label> ';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('HvBookSettlement').'"></form>';
}

llxFooter();
$db->close();
