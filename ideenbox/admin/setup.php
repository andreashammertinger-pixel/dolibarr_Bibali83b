<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */
/** Einstellungen Ideenbox */
/** Mobile Oberfläche: Eingangsrechnung fotografieren und zuordnen, offene Posten, Rücklagen */
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

$langs->loadLangs(array('admin', 'agenda', 'ideenbox@ideenbox'));
if (!$user->admin) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
if ($action === 'save' && GETPOST('token', 'alpha')) {
	dolibarr_set_const($db, 'IDEENBOX_AUTO_AGENDA', GETPOSTINT('IDEENBOX_AUTO_AGENDA') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'IDEENBOX_AGENDA_TYPE', GETPOST('IDEENBOX_AGENDA_TYPE', 'aZ09') ?: 'AC_OTH', 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$form = new Form($db);
llxHeader('', $langs->trans('IbSetup'), '', '', 0, 0, '', '', '', 'mod-ideenbox page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('IbSetup'), $linkback, 'title_setup');

$types = array();
$res = $db->query("SELECT code, libelle FROM ".$db->prefix()."c_actioncomm WHERE active = 1 AND type IN ('system', 'user') ORDER BY position, libelle");
if ($res) {
	while ($o = $db->fetch_object($res)) {
		$key = 'Action'.$o->code;
		$types[$o->code] = ($langs->trans($key) !== $key) ? $langs->trans($key) : $o->libelle;
	}
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('IbSetupAutoAgenda'), $langs->trans('IbSetupAutoAgendaHelp')).'</td><td><input type="checkbox" name="IDEENBOX_AUTO_AGENDA" value="1"'.(getDolGlobalInt('IDEENBOX_AUTO_AGENDA', 1) ? ' checked' : '').'></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('IbSetupAgendaType').'</td><td>'.$form->selectarray('IDEENBOX_AGENDA_TYPE', $types, getDolGlobalString('IDEENBOX_AGENDA_TYPE', 'AC_OTH')).'</td></tr>';
print '</table>';
print '<div class="center margintoponly"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';
print '<br><a href="'.dol_buildpath('/ideenbox/app.php', 1).'">'.img_picto('', 'fa-mobile-alt', 'class="pictofixedwidth"').$langs->trans('IbAppTitle').'</a>';

llxFooter();
$db->close();
