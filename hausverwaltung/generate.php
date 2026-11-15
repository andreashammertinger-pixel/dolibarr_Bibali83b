<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Monatliche Vorschreibungen als Kundenrechnungen erzeugen */
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

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills'));
if (!$user->hasRight('hausverwaltung', 'invoice')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$period = GETPOST('period', 'alphanohtml');
if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
	$period = dol_print_date(dol_now(), '%Y-%m');
}
$validate = GETPOSTINT('validate') ? true : false;

$inv = new HvInvoicer($db);
$calc = new HvCalculator($db);
$rows = $calc->compute();
$log = array();
if ($action === 'generate') {
	$log = $inv->generate($user, $period, $validate);
}
$preview = $inv->generate($user, $period, false, array(), true);
$status = array();
foreach ($preview as $p) {
	$status[$p['unit']] = $p;
}

llxHeader('', $langs->trans('HvGenerate'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvGenerate'), '', 'bill');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'">';
print '<div class="inline-block marginrightonly">'.$langs->trans('HvPeriod').': <input type="month" name="period" value="'.dol_escape_htmltag($period).'"></div>';
print '<button class="button" name="action" value="preview">'.$langs->trans('HvPreview').'</button> ';
print '<label class="marginleftonly"><input type="checkbox" name="validate" value="1"'.($validate ? ' checked' : '').'> '.$langs->trans('HvValidateDirectly').'</label> ';
print '<button class="button button-save" name="action" value="generate">'.$langs->trans('HvCreateInvoices').'</button>';
print '</form><br>';

if ($log) {
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Invoice').'</th><th class="right">'.$langs->trans('Amount').'</th></tr>';
	foreach ($log as $l) {
		$link = '';
		if ($l['invoice_id']) {
			$f = new Facture($db);
			$f->fetch($l['invoice_id']);
			$link = $f->getNomUrl(1);
		}
		$cls = $l['status'] === 'ok' ? 'ok' : ($l['status'] === 'error' ? 'error' : 'opacitymedium');
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($l['unit']).'</td><td class="'.$cls.'">'.dol_escape_htmltag($l['status'].' '.$l['message']).'</td><td>'.$link.'</td><td class="right">'.hvMoney($l['total']).'</td></tr>';
	}
	print '</table><br>';
}

print load_fiche_titre($langs->trans('HvPreview').' '.$period, '', '');
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvOwner').'</th><th class="right">'.$langs->trans('HvBk').'</th><th class="right">'.$langs->trans('HvReserve').'</th><th class="right">'.$langs->trans('HvAdmin').'</th><th class="right">'.$langs->trans('Total').'</th><th>'.$langs->trans('Status').'</th></tr>';
$sum = 0;
foreach ($rows as $r) {
	$u = $r['unit'];
	$st = $status[$u->ref] ?? null;
	$sum += $r['total'];
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($u->ref).'</td><td>'.dol_escape_htmltag($u->socname).'</td><td class="right">'.hvMoney($r['bk']).'</td><td class="right">'.hvMoney($r['reserve']).'</td><td class="right">'.hvMoney($r['admin']).'</td><td class="right"><strong>'.hvMoney($r['total']).'</strong></td>';
	print '<td>'.($st && $st['status'] !== 'dryrun' ? '<span class="opacitymedium">'.dol_escape_htmltag($st['message']).'</span>' : '').'</td></tr>';
}
print '<tr class="liste_total"><td colspan="5">'.$langs->trans('Total').'</td><td class="right">'.hvMoney($sum).'</td><td></td></tr></table></div>';

llxFooter();
$db->close();
