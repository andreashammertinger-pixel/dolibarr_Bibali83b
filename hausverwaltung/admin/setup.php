<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Einstellungen + Import Startdaten */
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');

$langs->loadLangs(array('admin', 'banks', 'bills', 'hausverwaltung@hausverwaltung'));
if (!$user->admin) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$form = new Form($db);

if ($action === 'save') {
	dolibarr_set_const($db, 'HAUSVERWALTUNG_BANK_ACCOUNT', GETPOSTINT('HAUSVERWALTUNG_BANK_ACCOUNT'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_PAYMENT_MODE', GETPOSTINT('HAUSVERWALTUNG_PAYMENT_MODE'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_DUE_DAY', max(1, min(28, GETPOSTINT('HAUSVERWALTUNG_DUE_DAY'))), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_VAT_RATE', price2num(GETPOST('HAUSVERWALTUNG_VAT_RATE', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'importseed') {
	$repo = new HvRepository($db);
	$data = json_decode(file_get_contents(dol_buildpath('/hausverwaltung/data/bibali83b_seed.json', 0)), true);
	$bmap = array();
	foreach ($repo->getBuildings(false) as $b) {
		$bmap[$b->ref] = (int) $b->rowid;
	}
	$n = array(0, 0, 0);
	foreach ($data['buildings'] as $b) {
		if (!isset($bmap[$b['ref']])) {
			$bmap[$b['ref']] = $repo->save('hv_building', array('ref' => $b['ref'], 'label' => $b['label'], 'position' => (int) $b['position'], 'active' => 1));
			$n[0]++;
		}
	}
	$existingUnits = array();
	foreach ($repo->getUnits(false) as $u) {
		$existingUnits[$u->ref] = 1;
	}
	foreach ($data['units'] as $i => $u) {
		if (!isset($existingUnits[$u['ref']])) {
			$repo->save('hv_unit', array('ref' => $u['ref'], 'fk_building' => (int) $bmap[$u['building']], 'area' => (float) $u['area'], 'reserve_rate' => (float) $u['reserve_rate'], 'admin_rate' => (float) $u['admin_rate'], 'position' => $i + 1, 'active' => 1));
			$n[1]++;
		}
	}
	if (count($repo->getCostItems(false)) == 0) {
		foreach ($data['costitems'] as $i => $c) {
			$repo->save('hv_costitem', array('label' => $c['label'], 'fk_building' => $c['building'] ? (int) $bmap[$c['building']] : 0, 'period_months' => (int) $c['period'], 'amount_net' => (float) $c['net'], 'vat_rate' => (float) $c['vat'], 'reference' => $c['reference'] ?? '', 'note' => $c['note'] ?? '', 'position' => $i + 1, 'active' => 1));
			$n[2]++;
		}
	}
	// Leistungen anlegen
	$inv = new HvInvoicer($db);
	$inv->ensureBaseProducts($user);
	foreach ($repo->getBuildings(false) as $b) {
		$inv->ensureReserveProduct($user, $b);
	}
	setEventMessages($langs->trans('HvBuildings').': '.$n[0].', '.$langs->trans('HvUnits').': '.$n[1].', '.$langs->trans('HvCostPlan').': '.$n[2], null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('HvSetupPage'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('HvSetupPage'), $linkback, 'title_setup');
print dol_get_fiche_head(hvAdminPrepareHead(), 'settings', '', -1, 'building');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvOperatingAccount').'</td><td>';
$form->select_comptes(getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT'), 'HAUSVERWALTUNG_BANK_ACCOUNT', 0, '', 1);
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvPaymentMode').'</td><td>';
$form->select_types_paiements(getDolGlobalInt('HAUSVERWALTUNG_PAYMENT_MODE'), 'HAUSVERWALTUNG_PAYMENT_MODE', '', 0, 1);
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvDueDay').'</td><td><input name="HAUSVERWALTUNG_DUE_DAY" class="width50" value="'.getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('VAT').' (Vorschreibung)</td><td><input name="HAUSVERWALTUNG_VAT_RATE" class="width50" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_VAT_RATE', '0')).'"> %</td></tr>';
print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form><br>';

print load_fiche_titre($langs->trans('HvImportSeed'), '', '');
print '<p>'.$langs->trans('HvImportSeedHelp').'</p>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="importseed"><input type="submit" class="button" value="'.$langs->trans('HvImportSeed').'"></form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
