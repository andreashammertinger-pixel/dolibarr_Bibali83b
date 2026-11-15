<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Apartments: Fläche, Gebäude, Sätze, Zuordnung Geschäftspartner */
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

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'companies'));
if (!$user->hasRight('hausverwaltung', 'read')) {
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
		'ref' => GETPOST('ref', 'alphanohtml'),
		'fk_building' => GETPOSTINT('fk_building'),
		'fk_soc' => GETPOSTINT('fk_soc') > 0 ? GETPOSTINT('fk_soc') : null,
		'area' => (float) price2num(GETPOST('area', 'alphanohtml')),
		'reserve_rate' => (float) price2num(GETPOST('reserve_rate', 'alphanohtml')),
		'admin_rate' => (float) price2num(GETPOST('admin_rate', 'alphanohtml')),
		'position' => GETPOSTINT('position'),
		'active' => GETPOSTINT('active') ? 1 : 0,
		'note' => GETPOST('note', 'restricthtml'),
	);
	if (empty($fields['ref']) || empty($fields['fk_building']) || $fields['area'] <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Ref').' / '.$langs->transnoentities('HvBuilding').' / '.$langs->transnoentities('HvArea')), null, 'errors');
		$action = $id ? 'edit' : 'create';
	} elseif ($repo->save('hv_unit', $fields, $id) > 0) {
		setEventMessages($langs->trans('HvSaved'), null);
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($repo->error, null, 'errors');
	}
}
if ($canwrite && $action === 'confirm_delete' && GETPOST('confirm') === 'yes') {
	$repo->delete('hv_unit', $id);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$units = $repo->getUnits(false);

llxHeader('', $langs->trans('HvUnits'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
$newbtn = $canwrite ? dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create') : '';
print load_fiche_titre($langs->trans('HvUnits'), $newbtn, 'building');

if ($action === 'delete') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDelete'), 'confirm_delete', '', 0, 1);
}

if ($canwrite && in_array($action, array('create', 'edit'))) {
	$u = ($id && isset($units[$id])) ? $units[$id] : (object) array('ref' => '', 'fk_building' => 0, 'fk_soc' => 0, 'area' => '', 'reserve_rate' => '', 'admin_rate' => getDolGlobalString('HAUSVERWALTUNG_DEFAULT_ADMIN_RATE', '0.3'), 'position' => 0, 'active' => 1, 'note' => '');
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.$id.'">';
	print '<table class="border centpercent tableforfieldedit">';
	print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Ref').'</td><td><input name="ref" value="'.dol_escape_htmltag($u->ref).'" maxlength="32"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('HvBuilding').'</td><td>'.hvSelectBuilding('fk_building', $u->fk_building, $buildings).'</td></tr>';
	print '<tr><td>'.$langs->trans('HvOwner').'</td><td>'.$form->select_company($u->fk_soc, 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth300').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('HvArea').'</td><td><input name="area" class="width75" value="'.dol_escape_htmltag(price2num($u->area)).'"></td></tr>';
	print '<tr><td>'.$langs->trans('HvReserveRate').'</td><td><input name="reserve_rate" class="width75" value="'.dol_escape_htmltag(price2num($u->reserve_rate)).'"> € / m² / Monat</td></tr>';
	print '<tr><td>'.$langs->trans('HvAdminRate').'</td><td><input name="admin_rate" class="width75" value="'.dol_escape_htmltag(price2num($u->admin_rate)).'"> € / m² / Monat</td></tr>';
	print '<tr><td>'.$langs->trans('Position').'</td><td><input name="position" class="width50" value="'.((int) $u->position).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Active').'</td><td><input type="checkbox" name="active" value="1"'.($u->active ? ' checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans('Note').'</td><td><textarea name="note" class="quatrevingtpercent" rows="2">'.dol_escape_htmltag($u->note).'</textarea></td></tr>';
	print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a></div></form><br>';
}

print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('HvBuilding').'</th><th>'.$langs->trans('HvOwner').'</th><th class="right">'.$langs->trans('HvArea').'</th><th class="right">'.$langs->trans('HvReserveRate').'</th><th class="right">'.$langs->trans('HvAdminRate').'</th><th class="center">'.$langs->trans('Active').'</th><th></th></tr>';
foreach ($units as $u) {
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($u->ref).'</td><td>'.dol_escape_htmltag(hvBuildingLabel($u->fk_building, $buildings)).'</td>';
	print '<td>'.($u->fk_soc ? '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$u->fk_soc.'">'.dol_escape_htmltag($u->socname).'</a>' : '<span class="warning">'.$langs->trans('HvNoThirdparty').'</span>').'</td>';
	print '<td class="right">'.price($u->area, 0, $langs, 1, 2, 2).'</td><td class="right">'.price($u->reserve_rate, 0, $langs, 1, 2, 4).'</td><td class="right">'.price($u->admin_rate, 0, $langs, 1, 2, 4).'</td>';
	print '<td class="center">'.yn($u->active).'</td><td class="right nowraponall">';
	if ($canwrite) {
		print '<a class="editfielda paddingright" href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$u->rowid.'">'.img_edit().'</a>';
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delete&token='.newToken().'&id='.$u->rowid.'">'.img_delete().'</a>';
	}
	print '</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
