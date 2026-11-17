<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Vorschreibungen / Abrechnungen per E-Mail versenden (Original + Übersetzungskopie, Text in Eigentümersprache) */
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
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvmailer.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills', 'mails'));
if (!$user->hasRight('hausverwaltung', 'invoice')) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$period = GETPOST('period', 'alphanohtml');
if (!preg_match('/^\d{4}-(\d{2}|A)$/', (string) $period)) {
	$period = dol_print_date(dol_now(), '%Y-%m');
}
$testto = trim(GETPOST('testto', 'alphanohtml'));
if ($testto === '' && !GETPOSTISSET('testto')) {
	$testto = (string) $user->email;
}
$sel = GETPOST('toselect', 'array:int');
$form = new Form($db);
$mailer = new HvMailer($db);

// Vorschreibungen der Periode (freigegeben)
$invoices = array();
$sql = "SELECT f.rowid FROM ".$db->prefix()."facture f INNER JOIN ".$db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid";
$sql .= " LEFT JOIN ".$db->prefix()."hv_unit u ON u.rowid = fe.hv_unit";
$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut IN (1,2) AND fe.hv_period = '".$db->escape($period)."' ORDER BY u.position, u.ref";
$res = $db->query($sql);
while ($res && ($o = $db->fetch_object($res))) {
	$f = new Facture($db);
	if ($f->fetch($o->rowid) > 0) {
		$invoices[(int) $o->rowid] = $f;
	}
}

$log = array();
if (in_array($action, array('sendtest', 'confirm_send'), true) && ($action === 'sendtest' || GETPOST('confirm') === 'yes')) {
	if ($action === 'confirm_send') {
		$sel = array_filter(array_map('intval', explode(',', GETPOST('ids', 'alphanohtml'))));
	}
	if ($action === 'sendtest' && !isValidEmail($testto)) {
		setEventMessages($langs->trans('HvMailTestAddressInvalid'), null, 'errors');
	} else {
		foreach ($sel as $id) {
			if (!isset($invoices[$id])) {
				continue;
			}
			$r = $mailer->send($user, $invoices[$id], $action === 'sendtest' ? $testto : '');
			$log[$id] = $r > 0 ? 'ok' : $mailer->error;
		}
		$nok = count(array_filter($log, function ($v) {
			return $v === 'ok';
		}));
		setEventMessages($langs->trans($action === 'sendtest' ? 'HvMailTestSent' : 'HvMailSent', $nok, count($log)), null, $nok == count($log) ? 'mesgs' : 'warnings');
	}
}

llxHeader('', $langs->trans('HvSend'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvSend'), '', 'email');

if ($action === 'send' && $sel) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?period='.urlencode($period).'&ids='.implode(',', array_map('intval', $sel)), $langs->trans('HvSend'), $langs->trans('HvMailConfirm', count($sel)), 'confirm_send', '', 0, 1);
}

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('HvPeriod').': <input name="period" class="width100" value="'.dol_escape_htmltag($period).'"> <span class="opacitymedium">(JJJJ-MM, '.$langs->trans('HvSettlement').': JJJJ-A)</span> <input type="submit" class="button small" value="OK"></form><br>';

$fromMail = getDolGlobalString('HAUSVERWALTUNG_MAIL_FROM', $mysoc->email);
print '<div class="opacitymedium">'.$langs->trans('MailFrom').': '.dol_escape_htmltag($mysoc->name.' <'.$fromMail.'>').(getDolGlobalString('HAUSVERWALTUNG_MAIL_BCC') ? ' · BCC: '.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_MAIL_BCC')) : '').'</div><br>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="period" value="'.dol_escape_htmltag($period).'">';
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th class="center"><input type="checkbox" onclick="jQuery(\'.hvsel\').prop(\'checked\', this.checked)"></th><th>'.$langs->trans('Invoice').'</th><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvMailTo').'</th><th>'.$langs->trans('Language').'</th><th>'.$langs->trans('HvMailFiles').'</th><th class="right">'.$langs->trans('Amount').'</th><th>'.$langs->trans('HvMailLastSent').'</th><th>'.$langs->trans('Status').'</th></tr>';
if (!$invoices) {
	print '<tr><td colspan="9" class="opacitymedium">'.$langs->trans('HvMailNoInvoices').'</td></tr>';
}
foreach ($invoices as $id => $f) {
	$f->fetch_thirdparty();
	$rc = $mailer->recipients($f);
	$files = $mailer->files($f);
	$last = $mailer->lastSent($id);
	$ok = $rc && $files;
	$checked = $ok && !$last && !GETPOSTISSET('token') ? ' checked' : (in_array($id, (array) $sel) ? ' checked' : '');
	print '<tr class="oddeven"><td class="center"><input type="checkbox" class="hvsel" name="toselect[]" value="'.$id.'"'.($ok ? $checked : ' disabled').'></td>';
	print '<td>'.$f->getNomUrl(1).'</td><td>'.dol_escape_htmltag($f->thirdparty->name).'</td>';
	print '<td>'.($rc ? dol_escape_htmltag(implode(', ', array_column($rc, 'email'))) : '<span class="error">'.$langs->trans('HvMailNoAddress').'</span>').'</td>';
	print '<td>'.dol_escape_htmltag($f->thirdparty->default_lang ?: '-').'</td>';
	print '<td>'.($files ? dol_escape_htmltag(implode(', ', array_map('basename', $files))) : '<span class="error">'.$langs->trans('HvMailNoPdf').'</span>').'</td>';
	print '<td class="right">'.hvMoney($f->total_ttc).'</td><td>'.($last ? dol_print_date($last, 'dayhour') : '').'</td>';
	print '<td>'.(isset($log[$id]) ? ($log[$id] === 'ok' ? '<span class="ok">OK</span>' : '<span class="error">'.dol_escape_htmltag($log[$id]).'</span>') : '').'</td></tr>';
}
print '</table></div><br>';
if ($invoices) {
	print $langs->trans('HvMailTestTo').': <input name="testto" class="minwidth250" value="'.dol_escape_htmltag($testto).'"> <button class="button" name="action" value="sendtest">'.$langs->trans('HvMailSendTest').'</button> ';
	print '<button class="button button-save" name="action" value="send">'.$langs->trans('HvMailSendReal').'</button>';
	print '<div class="opacitymedium margintoponly">'.$langs->trans('HvMailHelp').'</div>';
}
print '</form>';

llxFooter();
$db->close();
