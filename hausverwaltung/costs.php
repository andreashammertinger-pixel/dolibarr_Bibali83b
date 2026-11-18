<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Kostenplan (Excel-Blatt "Kosten"): Kostenarten je Gebäude oder alle Gebäude */
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
dol_include_once('/hausverwaltung/class/hvcalculator.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'companies', 'bills'));
if (!$user->hasRight('hausverwaltung', 'read') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie – sie enthält Daten aller Einheiten
	accessforbidden();
}
$canwrite = $user->hasRight('hausverwaltung', 'write');

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$repo = new HvRepository($db);
$form = new Form($db);
$buildings = $repo->getBuildings(false);

if ($canwrite && $action === 'save') {
	$fields = array(
		'label' => GETPOST('label', 'alphanohtml'),
		'fk_building' => GETPOSTINT('fk_building'),
		'period_months' => in_array(GETPOSTINT('period_months'), array(1, 3, 6, 12)) ? GETPOSTINT('period_months') : 12,
		'amount_net' => (float) price2num(GETPOST('amount_net', 'alphanohtml')),
		'vat_rate' => (float) price2num(GETPOST('vat_rate', 'alphanohtml')),
		'alloc_key' => in_array(GETPOST('alloc_key', 'aZ09'), HvCalculator::keys(), true) ? GETPOST('alloc_key', 'aZ09') : 'area',
		'fk_soc_supplier' => GETPOSTINT('fk_soc_supplier') > 0 ? GETPOSTINT('fk_soc_supplier') : null,
		'reference' => GETPOST('reference', 'alphanohtml'),
		'position' => GETPOSTINT('position'),
		'active' => GETPOSTINT('active') ? 1 : 0,
		'note' => GETPOST('note', 'restricthtml'),
	);
	$trl = array();
	foreach ((array) GETPOST('tr_label', 'array') as $l => $v) {
		$v = trim(strip_tags((string) $v));
		if ($v !== '' && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $l)) {
			$trl[$l]['label'] = $v;
		}
	}
	$fields['i18n'] = $trl ? json_encode($trl, JSON_UNESCAPED_UNICODE) : null;
	if (empty($fields['label'])) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label')), null, 'errors');
		$action = $id ? 'edit' : 'create';
	} elseif ($repo->save('hv_costitem', $fields, $id) > 0) {
		setEventMessages($langs->trans('HvSaved'), null);
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($repo->error, null, 'errors');
	}
}
if ($canwrite && $action === 'confirm_delete' && GETPOST('confirm') === 'yes') {
	$repo->delete('hv_costitem', $id);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$items = $repo->getCostItems(false);
$chk = new HvCalculator($db);
$chk->compute();

llxHeader('', $langs->trans('HvCostPlan'), hvHelpUrl('kosten/'), '', 0, 0, '', '', '', 'mod-hausverwaltung');
$newbtn = $canwrite ? dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create') : '';
print load_fiche_titre($langs->trans('HvCostPlan'), $newbtn, 'bill');
if (!empty($chk->warnings)) {
	print info_admin($langs->trans('HvKeyFallback', implode(', ', array_map(function ($k) use ($langs) {
		return $langs->trans('HvKey_'.$k);
	}, array_keys($chk->warnings)))), 0, 0, 'warning');
}

if ($action === 'delete') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDelete'), 'confirm_delete', '', 0, 1);
}

if ($canwrite && in_array($action, array('create', 'edit'))) {
	$c = ($id && isset($items[$id])) ? $items[$id] : (object) array('label' => '', 'fk_building' => 0, 'period_months' => 12, 'amount_net' => '', 'vat_rate' => 25, 'alloc_key' => 'area', 'fk_soc_supplier' => 0, 'reference' => '', 'position' => 0, 'active' => 1, 'note' => '');
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.$id.'">';
	print '<table class="border centpercent tableforfieldedit">';
	print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Label').'</td><td><input name="label" class="minwidth300" value="'.dol_escape_htmltag($c->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('HvBuilding').'</td><td>'.hvSelectBuilding('fk_building', (string) (int) $c->fk_building, $buildings, true).'</td></tr>';
	print '<tr><td>'.$langs->trans('HvRhythm').'</td><td>'.hvSelectPeriod('period_months', $c->period_months).'</td></tr>';
	print '<tr><td>'.$langs->trans('HvAllocKey').'</td><td><select name="alloc_key" class="flat">';
	foreach (HvCalculator::keys() as $k) {
		print '<option value="'.$k.'"'.(($c->alloc_key ?? 'area') === $k ? ' selected' : '').'>'.$langs->trans('HvKey_'.$k).'</option>';
	}
	print '</select></td></tr>';
	print '<tr><td>'.$langs->trans('HvAmountNet').'</td><td><input name="amount_net" class="width100" value="'.dol_escape_htmltag(price2num($c->amount_net)).'"> €</td></tr>';
	print '<tr><td>'.$langs->trans('VAT').'</td><td><input name="vat_rate" class="width50" value="'.dol_escape_htmltag(price2num($c->vat_rate)).'"> %</td></tr>';
	print '<tr><td>'.$langs->trans('HvSupplier').'</td><td>'.$form->select_company($c->fk_soc_supplier, 'fk_soc_supplier', '(s.fournisseur:=:1)', 1, 0, 0, array(), 0, 'minwidth300').'</td></tr>';
	print '<tr><td>'.$langs->trans('HvReference').'</td><td><input name="reference" value="'.dol_escape_htmltag($c->reference).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Position').'</td><td><input name="position" class="width50" value="'.((int) $c->position).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Active').'</td><td><input type="checkbox" name="active" value="1"'.($c->active ? ' checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans('Note').'</td><td><textarea name="note" class="quatrevingtpercent" rows="2">'.dol_escape_htmltag($c->note).'</textarea></td></tr>';
	$tri = !empty($c->i18n) ? json_decode($c->i18n, true) : array();
	print '<tr><td>'.$langs->trans('HvPlanTranslations').'<br><span class="opacitymedium small">'.$langs->trans('HvCostTranslationsHelp').'</span></td><td>';
	foreach (hvDocLangs() as $l) {
		print '<div class="paddingbottom"><span class="inline-block width75">'.$l.'</span><input name="tr_label['.$l.']" class="minwidth400" value="'.dol_escape_htmltag($tri[$l]['label'] ?? '').'"></div>';
	}
	print '</td></tr>';
	print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a></div></form><br>';
}

$tot = array('m' => 0, 'y' => 0);
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('HvBuilding').'</th><th>'.$langs->trans('HvRhythm').'</th><th>'.$langs->trans('HvAllocKey').'</th><th class="right">'.$langs->trans('AmountHT').'</th><th class="right">'.$langs->trans('VAT').'</th><th class="right">'.$langs->trans('HvAmountGross').'</th><th class="right">'.$langs->trans('HvMonthly').'</th><th class="right">'.$langs->trans('HvYearly').'</th><th>'.$langs->trans('HvSupplier').'</th><th>'.$langs->trans('HvReference').'</th><th class="center">'.$langs->trans('Active').'</th><th></th></tr>';
foreach ($items as $c) {
	$m = HvCalculator::monthly($c);
	if ($c->active) {
		$tot['m'] += $m;
		$tot['y'] += $m * 12;
	}
	print '<tr class="oddeven'.($c->active ? '' : ' opacitymedium').'"><td>'.dol_escape_htmltag($c->label).'</td><td>'.dol_escape_htmltag(hvBuildingLabel($c->fk_building, $buildings)).'</td>';
	print '<td>'.$langs->trans('HvPeriod'.((int) $c->period_months)).'</td><td>'.$langs->trans('HvKey_'.($c->alloc_key ?: 'area')).'</td>';
	print '<td class="right">'.hvMoney($c->amount_net).'</td><td class="right">'.vatrate($c->vat_rate, true).'</td><td class="right">'.hvMoney(HvCalculator::gross($c)).'</td>';
	print '<td class="right">'.hvMoney($m).'</td><td class="right">'.hvMoney($m * 12).'</td>';
	print '<td>'.dol_escape_htmltag($c->suppliername).'</td><td>'.dol_escape_htmltag($c->reference).'</td><td class="center">'.yn($c->active).'</td><td class="right nowraponall">';
	if ($canwrite) {
		print '<a class="editfielda paddingright" href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$c->rowid.'">'.img_edit().'</a>';
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delete&token='.newToken().'&id='.$c->rowid.'">'.img_delete().'</a>';
	}
	print '</td></tr>';
}
print '<tr class="liste_total"><td colspan="7">'.$langs->trans('Total').'</td><td class="right">'.hvMoney($tot['m']).'</td><td class="right">'.hvMoney($tot['y']).'</td><td colspan="4"></td></tr>';
print '</table></div>';

llxFooter();
$db->close();
