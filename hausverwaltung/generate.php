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
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
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
$periodTo = GETPOST('period_to', 'alphanohtml');
if (!preg_match('/^\d{4}-\d{2}$/', (string) $periodTo) || HvInvoicer::months($period, $periodTo) <= 0) {
	$periodTo = $period;
}
$nMonths = HvInvoicer::months($period, $periodTo);
$validate = GETPOSTINT('validate') ? true : false;
$withfee = ($action === '' || $action === 'preview') ? (getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0) : (GETPOSTINT('withfee') ? true : false);

$inv = new HvInvoicer($db);
$calc = new HvCalculator($db);
$rows = $calc->compute();
$log = array();
if ($action === 'generate') {
	$log = $inv->generate($user, $period, $validate, array(), false, $periodTo);
	if ($withfee) {
		$fee = $inv->generateManagerFee($user, $period, $periodTo);
		$fee['unit'] = $langs->transnoentities('HvManagerFee');
		$log[] = $fee;
	}
}
$preview = $inv->generate($user, $period, false, array(), true, $periodTo);
$status = array();
foreach ($preview as $p) {
	$status[$p['unit']] = $p;
}

llxHeader('', $langs->trans('HvGenerate'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvGenerate'), '', 'bill');

// Prüfung: KPD-Code bei allen Vorschreibungs-Leistungen hinterlegt?
$missing = array();
$sqlk = "SELECT p.rowid, p.ref FROM ".$db->prefix()."product p LEFT JOIN ".$db->prefix()."product_extrafields pe ON pe.fk_object = p.rowid";
$sqlk .= " WHERE (p.ref IN ('".HvInvoicer::REF_BK."', '".HvInvoicer::REF_ADMIN."') OR p.rowid IN (SELECT fk_product_reserve FROM ".$db->prefix()."hv_building WHERE entity = ".((int) $conf->entity)."))";
$sqlk .= " AND p.entity IN (".getEntity('product').") AND (pe.kpd IS NULL OR pe.kpd = '')";
$resk = $db->query($sqlk);
while ($resk && ($o = $db->fetch_object($resk))) {
	$missing[] = '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.$o->rowid.'">'.dol_escape_htmltag($o->ref).'</a>';
}
if ($missing) {
	print info_admin($langs->trans('HvKpdMissing').': '.implode(', ', $missing), 0, 0, 'warning');
}

// Prüfung OIB (Pflichtangabe): Gemeinschaft, Verwalter, Eigentümer je Apartment
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
$oibIssues = array();
$chk = function ($label, $oib) use (&$oibIssues, $langs) {
	if ($oib === '') {
		$oibIssues[] = $label.': '.$langs->trans('HvOibMissing');
	} elseif (!hvOibValid($oib)) {
		$oibIssues[] = $label.': '.$langs->trans('HvOibInvalid', $oib);
	}
};
$chk($mysoc->name, hvOib($mysoc));
if (getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0) {
	$mg = new Societe($db);
	if ($mg->fetch(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID')) > 0) {
		$chk($mg->name, hvOib($mg));
	}
}
foreach ($calc->units as $uid => $u) {
	if (empty($u->fk_soc)) {
		continue;
	}
	$owners = $inv->ownerContacts((int) $u->fk_soc);
	$soc = new Societe($db);
	$soc->fetch((int) $u->fk_soc);
	if (!$owners) {
		$chk($langs->trans('HvUnit').' '.$u->ref.' ('.$langs->trans('HvNoOwnerContact').')', hvOib($soc));
	}
	foreach ($owners as $cid) {
		$c = new Contact($db);
		$c->fetch($cid);
		$chk($langs->trans('HvUnit').' '.$u->ref.' – '.$c->getFullName($langs), hvOib($c) ?: (count($owners) == 1 ? hvOib($soc) : ''));
	}
}
if ($oibIssues) {
	print info_admin($langs->trans('HvOibCheck').':<br>'.implode('<br>', array_map('dol_escape_htmltag', $oibIssues)), 0, 0, 'warning');
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'">';
$y = (int) substr($period, 0, 4);
$now = dol_print_date(dol_now(), '%Y-%m');
$presets = array(
	$langs->trans('HvPresetMonth') => array($now, $now),
	$langs->trans('HvPresetRestOfYear') => array($now, substr($now, 0, 4).'-12'),
	$langs->trans('HvPresetYear', $y + 1) => array(($y + 1).'-01', ($y + 1).'-12'),
);
print '<div class="inline-block marginrightonly">'.$langs->trans('HvPeriodFrom').': <input type="month" name="period" value="'.dol_escape_htmltag($period).'"> '.$langs->trans('HvPeriodTo').': <input type="month" name="period_to" value="'.dol_escape_htmltag($periodTo).'"></div>';
foreach ($presets as $lbl => $p) {
	print '<a class="butAction small" href="'.$_SERVER['PHP_SELF'].'?period='.$p[0].'&period_to='.$p[1].'">'.dol_escape_htmltag($lbl).'</a> ';
}
print '<br><br>';
print '<button class="button" name="action" value="preview">'.$langs->trans('HvPreviewRefresh').'</button> ';
print '<label class="marginleftonly"><input type="checkbox" name="validate" value="1"'.($validate ? ' checked' : '').'> '.$langs->trans('HvValidateDirectly').'</label> ';
print '<label class="marginleftonly"><input type="checkbox" name="withfee" value="1"'.($withfee ? ' checked' : '').(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0 ? '' : ' disabled').'> '.$langs->trans('HvCreateManagerFee').'</label> ';
print '<button class="button button-save" name="action" value="generate">'.$langs->trans('HvCreateInvoices').'</button>';
print '</form><br>';

if ($log) {
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Invoice').'</th><th class="right">'.$langs->trans('Amount').'</th></tr>';
	foreach ($log as $l) {
		$link = '';
		if ($l['invoice_id'] && $l['unit'] === $langs->transnoentities('HvManagerFee')) {
			$f = new FactureFournisseur($db);
			$f->fetch($l['invoice_id']);
			$link = $f->getNomUrl(1);
		} elseif ($l['invoice_id']) {
			$f = new Facture($db);
			$f->fetch($l['invoice_id']);
			$link = $f->getNomUrl(1);
		}
		$cls = $l['status'] === 'ok' ? 'ok' : ($l['status'] === 'error' ? 'error' : 'opacitymedium');
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($l['unit']).'</td><td class="'.$cls.'">'.dol_escape_htmltag($l['status'].' '.$l['message']).'</td><td>'.$link.'</td><td class="right">'.hvMoney($l['total']).'</td></tr>';
	}
	print '</table><br>';
}

print load_fiche_titre($langs->trans('HvPreview').' '.HvInvoicer::periodLabel($period, $periodTo).($nMonths > 1 ? ' ('.$nMonths.' '.$langs->trans('HvMonths').')' : ''), '', '');
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvOwner').'</th><th class="right">'.$langs->trans('HvBk').'</th><th class="right">'.$langs->trans('HvReserve').'</th><th class="right">'.$langs->trans('HvAdmin').'</th><th class="right">'.$langs->trans('HvTotalMonth').'</th>'.($nMonths > 1 ? '<th class="right">'.$langs->trans('Total').' '.HvInvoicer::periodLabel($period, $periodTo).'</th>' : '').'<th>'.$langs->trans('HvPdfPreview').'</th><th>'.$langs->trans('Status').'</th></tr>';
$sum = 0;
foreach ($rows as $r) {
	$u = $r['unit'];
	$st = $status[$u->ref] ?? null;
	$sum += $r['total'];
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($u->ref).'</td><td>'.dol_escape_htmltag($u->socname).'</td><td class="right">'.hvMoney($r['bk']).'</td><td class="right">'.hvMoney($r['reserve']).'</td><td class="right">'.hvMoney($r['admin']).'</td><td class="right"><strong>'.hvMoney($r['total']).'</strong></td>'.($nMonths > 1 ? '<td class="right"><strong>'.hvMoney($r['total'] * $nMonths).'</strong></td>' : '');
	$pl = '';
	if (!empty($u->fk_soc)) {
		$purl = dol_buildpath('/hausverwaltung/preview_pdf.php', 1).'?unit='.((int) $u->rowid).'&period='.urlencode($period).'&period_to='.urlencode($periodTo);
		$pl = '<a href="'.$purl.'" target="_blank">'.img_picto('', 'pdf').' '.$langs->trans('HvPdfOriginal').'</a>';
		$lrow = $db->getRow("SELECT default_lang FROM ".$db->prefix()."societe WHERE rowid = ".((int) $u->fk_soc));
		$ul = $lrow ? (string) $lrow->default_lang : '';
		if ($ul !== '' && substr($ul, 0, 2) !== substr(getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR'), 0, 2)) {
			$pl .= ' · <a href="'.$purl.'&copy=1" target="_blank">'.$langs->trans('HvPdfCopyShort').' ('.substr($ul, 0, 2).')</a>';
		}
	}
	print '<td class="nowraponall">'.$pl.'</td>';
	print '<td>'.($st && $st['status'] !== 'dryrun' ? '<span class="opacitymedium">'.dol_escape_htmltag($st['message']).'</span>' : '').'</td></tr>';
}
print '<tr class="liste_total"><td colspan="5">'.$langs->trans('Total').'</td><td class="right">'.hvMoney($sum).'</td>'.($nMonths > 1 ? '<td class="right">'.hvMoney($sum * $nMonths).'</td>' : '').'<td></td><td></td></tr></table></div>';

llxFooter();
$db->close();
