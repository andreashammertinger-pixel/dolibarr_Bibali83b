<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Gebäude: Liste + Bearbeiten (inkl. Rücklagenkonto) */
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
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'banks'));
if (!$user->hasRight('hausverwaltung', 'read')) {
	accessforbidden();
}
$canwrite = $user->hasRight('hausverwaltung', 'write');

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$repo = new HvRepository($db);
$form = new Form($db);

if ($canwrite && $action === 'save') {
	$fields = array(
		'ref' => GETPOST('ref', 'alphanohtml'),
		'label' => GETPOST('label', 'alphanohtml'),
		'fk_bank_reserve' => GETPOSTINT('fk_bank_reserve') > 0 ? GETPOSTINT('fk_bank_reserve') : null,
		'position' => GETPOSTINT('position'),
		'active' => GETPOSTINT('active') ? 1 : 0,
		'note' => GETPOST('note', 'restricthtml'),
	);
	if (empty($fields['ref']) || empty($fields['label'])) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Ref').'/'.$langs->transnoentities('Label')), null, 'errors');
		$action = $id ? 'edit' : 'create';
	} elseif ($repo->save('hv_building', $fields, $id) > 0) {
		setEventMessages($langs->trans('HvSaved'), null);
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($repo->error, null, 'errors');
	}
}
if ($canwrite && $action === 'confirm_delete' && GETPOST('confirm') === 'yes') {
	$res = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."hv_unit WHERE fk_building = ".((int) $id));
	$o = $db->fetch_object($res);
	if ($o->nb > 0) {
		setEventMessages('Gebäude hat noch Apartments.', null, 'errors');
	} else {
		$repo->delete('hv_building', $id);
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$buildings = $repo->getBuildings(false);

llxHeader('', $langs->trans('HvBuildings'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
$newbtn = $canwrite ? dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create') : '';
print load_fiche_titre($langs->trans('HvBuildings'), $newbtn, 'building');

if ($action === 'delete') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDelete'), 'confirm_delete', '', 0, 1);
}

if ($canwrite && in_array($action, array('create', 'edit'))) {
	$b = ($id && isset($buildings[$id])) ? $buildings[$id] : (object) array('ref' => '', 'label' => '', 'fk_bank_reserve' => 0, 'position' => 0, 'active' => 1, 'note' => '');
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.$id.'">';
	print '<table class="border centpercent tableforfieldedit">';
	print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Ref').'</td><td><input name="ref" value="'.dol_escape_htmltag($b->ref).'" maxlength="32"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Label').'</td><td><input name="label" class="minwidth300" value="'.dol_escape_htmltag($b->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans('HvReserveAccount').'</td><td>';
	$form->select_comptes($b->fk_bank_reserve, 'fk_bank_reserve', 0, '', 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('Position').'</td><td><input name="position" class="width50" value="'.((int) $b->position).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Active').'</td><td><input type="checkbox" name="active" value="1"'.($b->active ? ' checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans('Note').'</td><td><textarea name="note" class="quatrevingtpercent" rows="3">'.dol_escape_htmltag($b->note).'</textarea></td></tr>';
	print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a></div></form><br>';
}

print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('HvReserveAccount').'</th><th>'.$langs->trans('HvReserveProduct').'</th><th class="center">'.$langs->trans('Active').'</th><th></th></tr>';
foreach ($buildings as $b) {
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($b->ref).'</td><td>'.dol_escape_htmltag($b->label).'</td><td>';
	if ($b->fk_bank_reserve) {
		$acc = new Account($db);
		if ($acc->fetch($b->fk_bank_reserve) > 0) {
			print $acc->getNomUrl(1);
		}
	} else {
		print '<span class="warning">'.$langs->trans('HvNoReserveAccount').'</span>';
	}
	print '</td><td>';
	if ($b->fk_product_reserve) {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$p = new Product($db);
		if ($p->fetch($b->fk_product_reserve) > 0) {
			print $p->getNomUrl(1);
		}
	}
	print '</td><td class="center">'.yn($b->active).'</td><td class="right nowraponall">';
	if ($canwrite) {
		print '<a class="editfielda paddingright" href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$b->rowid.'">'.img_edit().'</a>';
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delete&token='.newToken().'&id='.$b->rowid.'">'.img_delete().'</a>';
	}
	print '</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
