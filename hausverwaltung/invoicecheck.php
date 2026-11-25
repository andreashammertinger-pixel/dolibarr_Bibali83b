<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Vorschreibungen prüfen: nach Änderung von Kosten, Flächen oder Sätzen neu berechnen (gleiche Nummer) und versenden */
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
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
dol_include_once('/hausverwaltung/class/hvmailer.class.php');

/**
 * Vorschreibungen prüfen: offene Vorschreibungen, die nach Änderungen an Kosten, Flächen oder Sätzen nicht mehr stimmen.
 * Neu berechnen (gleiche Nummer, ohne Gutschrift) und anschließend versenden – oder bewusst beibehalten.
 */
$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills'));
if (!$user->hasRight('hausverwaltung', 'invoice') || !empty($user->socid)) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$form = new Form($db);
$inv = new HvInvoicer($db);
$self = $_SERVER['PHP_SELF'];

if (in_array($action, array('confirm_recalc', 'confirm_keep'), true) && GETPOST('confirm') === 'yes') {
	$ids = array_filter(array_map('intval', explode(',', GETPOST('ids', 'alphanohtml'))));
	$ok = array();
	$errs = array();
	foreach ($ids as $id) {
		$f = new Facture($db);
		if ($f->fetch($id) <= 0) {
			continue;
		}
		if ($action === 'confirm_keep') {
			$inv->keep($user, $f);
			continue;
		}
		$r = $inv->recalculate($user, $f);
		if ($r > 0) {
			$ok[] = $id;
		} elseif ($r < 0) {
			$errs[] = $inv->error;
		}
	}
	if ($action === 'confirm_keep') {
		setEventMessages($langs->trans('HvRecalcKept', count($ids)), null);
		header('Location: '.$self);
		exit;
	}
	setEventMessages($langs->trans('HvRecalcDone', count($ok)), $errs ?: null, $errs ? 'warnings' : 'mesgs');
	header('Location: '.$self.'?done='.implode(',', $ok));
	exit;
}

llxHeader('', $langs->trans('HvInvoiceCheck'), hvHelpUrl('vorschreibung/'), '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvInvoiceCheck'), '', 'bill');

$sel = GETPOST('toselect', 'array:int');
if (in_array($action, array('recalc', 'keep'), true) && $sel) {
	print $form->formconfirm($self.'?ids='.implode(',', array_map('intval', $sel)), $langs->trans($action === 'recalc' ? 'HvRecalcBtn' : 'HvKeepBtn'), $langs->trans($action === 'recalc' ? 'HvRecalcConfirm' : 'HvKeepConfirm', count($sel)), 'confirm_'.$action, '', 0, 1);
}

// Ergebnis: neu berechnete Vorschreibungen jetzt versenden (je Abrechnungszeitraum)
$done = array_filter(array_map('intval', explode(',', GETPOST('done', 'alphanohtml'))));
if ($done) {
	$byPeriod = array();
	foreach ($done as $id) {
		$f = new Facture($db);
		if ($f->fetch($id) > 0) {
			$byPeriod[(string) ($f->array_options['options_hv_period'] ?? '')][] = $id;
		}
	}
	print '<div class="info">'.$langs->trans('HvRecalcSendNow');
	foreach ($byPeriod as $p => $ids) {
		$q = 'period='.urlencode($p);
		foreach ($ids as $id) {
			$q .= '&toselect[]='.$id;
		}
		print ' <a class="butAction small" href="'.dol_buildpath('/hausverwaltung/send.php', 1).'?'.$q.'">'.$langs->trans('HvRecalcSendPeriod', $p, count($ids)).'</a>';
	}
	print '</div>';
}

print '<div class="opacitymedium">'.$langs->trans('HvInvoiceCheckHelp').'</div><br>';
$list = $inv->outdatedInvoices();
$mailer = new HvMailer($db);
print '<form method="POST" action="'.$self.'"><input type="hidden" name="token" value="'.newToken().'">';
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th class="center"><input type="checkbox" checked onclick="jQuery(\'.hvsel:not(:disabled)\').prop(\'checked\', this.checked)"></th><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('Invoice').'</th><th>'.$langs->trans('HvPeriod').'</th><th>'.$langs->trans('HvRecalcDiff').'</th><th class="right">'.$langs->trans('HvRecalcOld').'</th><th class="right">'.$langs->trans('HvRecalcNew').'</th><th class="right">'.$langs->trans('HvRecalcDelta').'</th><th>'.$langs->trans('HvMailLastSent').'</th></tr>';
if (!$list) {
	print '<tr><td colspan="9" class="opacitymedium">'.img_picto('', 'tick', 'class="pictofixedwidth"').$langs->trans('HvInvoiceCheckNone').'</td></tr>';
}
$so = $sn = 0;
foreach ($list as $id => $x) {
	$f = $x['f'];
	$f->fetch_thirdparty();
	$paid = $x['paid'] > 0;
	$so += (float) $f->total_ttc;
	$sn += (float) $x['new_total'];
	$p = (string) ($f->array_options['options_hv_period'] ?? '');
	$pt = (string) ($f->array_options['options_hv_period_to'] ?? '');
	$last = $mailer->lastSent($id);
	print '<tr class="oddeven"><td class="center"><input type="checkbox" class="hvsel" name="toselect[]" value="'.$id.'"'.($paid ? ' disabled' : ' checked').'></td>';
	print '<td>'.dol_escape_htmltag($f->thirdparty->name).'</td><td>'.$f->getNomUrl(1).($f->status == 0 ? ' <span class="badge badge-status0">'.$langs->trans('Draft').'</span>' : '').'</td><td>'.dol_escape_htmltag($p.($pt && $pt !== $p ? ' – '.$pt : '')).'</td>';
	print '<td class="small">'.dol_escape_htmltag(HvInvoicer::diffText($x['diffs'], $langs)).($paid ? '<br><span class="error">'.$langs->trans('HvRecalcPaid').'</span>' : '').'</td>';
	print '<td class="right">'.price($f->total_ttc, 0, $langs, 1, 2, 2, $conf->currency).'</td><td class="right"><strong>'.price($x['new_total'], 0, $langs, 1, 2, 2, $conf->currency).'</strong></td><td class="right">'.price($x['new_total'] - $f->total_ttc, 0, $langs, 1, 2, 2, $conf->currency).'</td>';
	print '<td>'.($last ? dol_print_date($last, 'day') : '').'</td></tr>';
}
if ($list) {
	print '<tr class="liste_total"><td colspan="5">'.$langs->trans('Total').'</td><td class="right">'.price($so, 0, $langs, 1, 2, 2, $conf->currency).'</td><td class="right">'.price($sn, 0, $langs, 1, 2, 2, $conf->currency).'</td><td class="right">'.price($sn - $so, 0, $langs, 1, 2, 2, $conf->currency).'</td><td></td></tr>';
}
print '</table></div>';
if ($list) {
	print '<div class="center paddingtop"><button class="button button-save" name="action" value="recalc">'.$langs->trans('HvRecalcBtn').'</button> <button class="button" name="action" value="keep">'.$langs->trans('HvKeepBtn').'</button></div>';
}
print '</form>';

llxFooter();
$db->close();
