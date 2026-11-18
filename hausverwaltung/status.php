<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Zahlungsstand: fällige Raten bis heute vs. bezahlt, Rückstände je Einheit */
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
if (!$user->hasRight('hausverwaltung', 'read') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie – sie enthält Daten aller Einheiten
	accessforbidden();
}
$year = GETPOSTINT('year') ?: (int) dol_print_date(dol_now(), '%Y');
$today = dol_now();

$sql = "SELECT f.rowid, f.ref, f.total_ttc, f.paye, fe.hv_period, fe.hv_period_to, fe.hv_unit, u.ref as uref, s.nom,";
$sql .= " (SELECT COALESCE(SUM(pf.amount),0) FROM ".$db->prefix()."paiement_facture pf WHERE pf.fk_facture = f.rowid) as paid";
$sql .= " FROM ".$db->prefix()."facture f INNER JOIN ".$db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid";
$sql .= " LEFT JOIN ".$db->prefix()."hv_unit u ON u.rowid = fe.hv_unit LEFT JOIN ".$db->prefix()."societe s ON s.rowid = f.fk_soc";
$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut IN (1,2) AND fe.hv_period LIKE '".((int) $year)."-%'";
$sql .= " ORDER BY u.position, u.ref, fe.hv_period";
$res = $db->query($sql);

llxHeader('', $langs->trans('HvPaymentStatus'), hvHelpUrl('zahlungen/'), '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvPaymentStatus').' '.$year, '<form method="GET"><input type="number" name="year" class="width75" value="'.$year.'"> <input type="submit" class="button small" value="OK"></form>', 'payment');
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvOwner').'</th><th>'.$langs->trans('Invoice').'</th><th>'.$langs->trans('HvPeriod').'</th><th class="right">'.$langs->trans('Total').'</th><th class="right">'.$langs->trans('HvDueUntilToday').'</th><th class="right">'.$langs->trans('AlreadyPaid').'</th><th class="right">'.$langs->trans('HvArrears').'</th><th>'.$langs->trans('HvNextDue').'</th></tr>';
$tot = array('total' => 0, 'due' => 0, 'paid' => 0, 'arr' => 0);
while ($res && ($o = $db->fetch_object($res))) {
	$isSettlement = (bool) preg_match('/-A$/', $o->hv_period);
	$f = new Facture($db);
	$f->fetch($o->rowid);
	if ($isSettlement || empty($o->hv_period_to)) {
		$plan = array(array('due' => HvInvoicer::effectiveDue($f)[0], 'amount' => (float) $o->total_ttc));
	} else {
		$plan = HvInvoicer::installments($o->total_ttc, $o->hv_period, $o->hv_period_to, HvInvoicer::issued($f));
	}
	$due = 0;
	$next = null;
	foreach ($plan as $p) {
		if ($p['due'] <= $today) {
			$due += $p['amount'];
		} elseif ($next === null) {
			$next = $p;
		}
	}
	$arr = max(0, round($due - (float) $o->paid, 2));
	$tot['total'] += $o->total_ttc;
	$tot['due'] += $due;
	$tot['paid'] += $o->paid;
	$tot['arr'] += $arr;
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($o->uref).'</td><td>'.dol_escape_htmltag($o->nom).'</td><td>'.$f->getNomUrl(1).'</td>';
	print '<td>'.dol_escape_htmltag($isSettlement ? $langs->trans('HvSettlement').' '.substr($o->hv_period, 0, 4) : HvInvoicer::periodLabel($o->hv_period, $o->hv_period_to)).'</td>';
	print '<td class="right">'.hvMoney($o->total_ttc).'</td><td class="right">'.hvMoney($due).'</td><td class="right">'.hvMoney($o->paid).'</td>';
	print '<td class="right '.($arr > 0 ? 'error' : '').'"><strong>'.($arr > 0 ? hvMoney($arr) : '–').'</strong></td>';
	print '<td>'.($next ? dol_print_date($next['due'], 'day').' · '.hvMoney($next['amount']) : '').'</td></tr>';
}
print '<tr class="liste_total"><td colspan="4">'.$langs->trans('Total').'</td><td class="right">'.hvMoney($tot['total']).'</td><td class="right">'.hvMoney($tot['due']).'</td><td class="right">'.hvMoney($tot['paid']).'</td><td class="right">'.hvMoney($tot['arr']).'</td><td></td></tr>';
print '</table></div>';

llxFooter();
$db->close();
