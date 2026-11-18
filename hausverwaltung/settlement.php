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
dol_include_once('/hausverwaltung/class/hvstatementpdf.class.php');
dol_include_once('/hausverwaltung/class/hvmailer.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills'));
if (!$user->hasRight('hausverwaltung', 'read') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie – sie enthält Daten aller Einheiten
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
// Abrechnungsblätter erzeugen / versenden
$canInvoice = $user->hasRight('hausverwaltung', 'invoice');
$stmtLog = array();
$testto = trim(GETPOST('testto', 'alphanohtml')) ?: (string) $user->email;
if ($canInvoice && $action === 'genstatements') {
	$gen = new HvStatementPdf($db);
	$n = 0;
	foreach ((new HvCalculator($db))->units as $uid => $u) {
		if ($gen->generate($year, $uid)) {
			$n++;
		}
	}
	setEventMessages($langs->trans('HvStmtGenerated', $n), null);
}
if ($canInvoice && in_array($action, array('stmttest', 'confirm_stmtsend'), true) && ($action === 'stmttest' || GETPOST('confirm') === 'yes')) {
	$mailer = new HvMailer($db);
	$ok = 0;
	$all = 0;
	foreach ((new HvCalculator($db))->units as $uid => $u) {
		if (empty($u->fk_soc)) {
			continue;
		}
		$all++;
		$r = $mailer->sendStatement($user, $year, $uid, $action === 'stmttest' ? $testto : '');
		$stmtLog[$uid] = $r > 0 ? 'ok' : $mailer->error;
		if ($r > 0) {
			$ok++;
		}
	}
	setEventMessages($langs->trans($action === 'stmttest' ? 'HvMailTestSent' : 'HvMailSent', $ok, $all), null, $ok == $all ? 'mesgs' : 'warnings');
}
$data = $st->compute($year);
$calc = new HvCalculator($db);

llxHeader('', $langs->trans('HvSettlement'), hvHelpUrl('jahresabrechnung/'), '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvSettlement').' '.$year, '<form method="GET"><input type="number" name="year" class="width75" value="'.$year.'"> <input type="submit" class="button small" value="OK"></form>', 'bill');

if ($action === 'stmtsend') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?year='.$year, $langs->trans('HvStmtSend'), $langs->trans('HvStmtSendConfirm', $year), 'confirm_stmtsend', '', 0, 1);
}
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

// Abrechnungsblätter je Eigentümer
print '<br>'.load_fiche_titre($langs->trans('HvStmtSection'), '', 'pdf');
print '<div class="opacitymedium">'.$langs->trans('HvStmtHelp').'</div><br>';
$mailer = new HvMailer($db);
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvOwnersMarked').'</th><th>'.$langs->trans('Language').'</th><th>'.$langs->trans('HvMailFiles').'</th><th>'.$langs->trans('HvMailLastSent').'</th><th>'.$langs->trans('Status').'</th></tr>';
$inv2 = new HvInvoicer($db);
foreach ($data['units'] as $uid => $r) {
	$u = $r['unit'];
	$links = array();
	foreach (HvStatementPdf::files($year, $u->ref) as $f) {
		$rel = 'statements/'.$year.'/'.basename($f);
		$links[] = '<a href="'.dol_buildpath('/hausverwaltung/file.php', 1).'?file='.urlencode($rel).'" target="_blank">'.img_picto('', 'pdf').' '.dol_escape_htmltag(basename($f)).'</a>';
	}
	$lrow = $u->fk_soc ? $db->getRow("SELECT default_lang FROM ".$db->prefix()."societe WHERE rowid = ".((int) $u->fk_soc)) : null;
	$last = $mailer->lastSentStatement($year, $u->ref);
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($u->ref).'</td><td>'.dol_escape_htmltag(implode(', ', $u->fk_soc ? $inv2->ownerNames((int) $u->fk_soc) : array())).'</td><td>'.dol_escape_htmltag($lrow ? (string) $lrow->default_lang : '').'</td>';
	print '<td>'.($links ? implode('<br>', $links) : '<span class="opacitymedium">–</span>').'</td><td>'.($last ? dol_print_date($last, 'dayhour') : '').'</td>';
	print '<td>'.(isset($stmtLog[$uid]) ? ($stmtLog[$uid] === 'ok' ? '<span class="ok">OK</span>' : '<span class="error">'.dol_escape_htmltag($stmtLog[$uid]).'</span>') : '').'</td></tr>';
}
print '</table></div><br>';
if ($canInvoice) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="year" value="'.$year.'">';
	print '<button class="button" name="action" value="genstatements">'.$langs->trans('HvStmtGenerate').'</button> &nbsp; ';
	print $langs->trans('HvMailTestTo').': <input name="testto" class="minwidth200" value="'.dol_escape_htmltag($testto).'"> <button class="button" name="action" value="stmttest">'.$langs->trans('HvMailSendTest').'</button> ';
	print '<button class="button button-save" name="action" value="stmtsend">'.$langs->trans('HvStmtSend').'</button></form>';
}

llxFooter();
$db->close();
