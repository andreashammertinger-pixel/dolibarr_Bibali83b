<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */
/** Idee / ToDo bearbeiten, zuordnen (Firma, Geschäftspartner, Projekt) und übernehmen */
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/ideenbox/lib/ideenbox.lib.php');

$langs->loadLangs(array('ideenbox@ideenbox', 'projects', 'companies', 'agenda'));
if (!$user->hasRight('ideenbox', 'read') || !empty($user->socid)) {
	accessforbidden();
}
$canwrite = $user->hasRight('ideenbox', 'write');
$seeall = $user->hasRight('ideenbox', 'all');
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$backurl = dol_buildpath('/ideenbox/index.php', 1);

$n = new IdeenboxNote($db);
if ($id > 0) {
	if ($n->fetch($id) <= 0) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
	if (!$seeall && !in_array($user->id, array($n->fk_user_author, $n->fk_user_assign))) {
		accessforbidden();
	}
} elseif ($action !== 'create' && $action !== 'add') {
	header('Location: '.$backurl);
	exit;
}

if ($canwrite && in_array($action, array('add', 'update', 'update_totask', 'update_toagenda'), true) && GETPOST('token', 'alpha')) {
	if (GETPOST('cancel', 'alpha')) {
		header('Location: '.($id > 0 ? $_SERVER['PHP_SELF'].'?id='.$id : $backurl));
		exit;
	}
	$n->type = GETPOST('type', 'aZ09') === 'todo' ? 'todo' : 'idea';
	$n->text = trim(GETPOST('text', 'restricthtml'));
	$n->date_reminder = dol_mktime(GETPOSTINT('remhour'), GETPOSTINT('remmin'), 0, GETPOSTINT('remmonth'), GETPOSTINT('remday'), GETPOSTINT('remyear'), 'tzuserrel') ?: null;
	$n->priority = GETPOSTINT('priority') ? 1 : 0;
	$n->fk_target_entity = GETPOSTINT('fk_target_entity') ?: null;
	$n->fk_soc = GETPOSTINT('socid') > 0 ? GETPOSTINT('socid') : null;
	$n->fk_project = GETPOSTINT('fk_project') > 0 ? GETPOSTINT('fk_project') : null;
	$n->fk_user_assign = GETPOSTINT('fk_user_assign') > 0 ? GETPOSTINT('fk_user_assign') : $user->id;
	if ($n->fk_project && !$n->fk_soc) {
		$res = $db->query("SELECT fk_soc, entity FROM ".$db->prefix()."projet WHERE rowid = ".((int) $n->fk_project));
		if ($res && ($o = $db->fetch_object($res))) {
			$n->fk_soc = $o->fk_soc ?: null;
			if (!$n->fk_target_entity && isModEnabled('multicompany')) {
				$n->fk_target_entity = (int) $o->entity;
			}
		}
	}
	if (GETPOSTISSET('status')) {
		$n->status = (int) GETPOST('status', 'int');
	}
	if ((int) $n->status === IdeenboxNote::STATUS_NEW && ($n->fk_soc || $n->fk_project || $n->fk_target_entity)) {
		$n->status = IdeenboxNote::STATUS_ASSIGNED;
	}
	if ($n->text === '') {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('IbText')), null, 'errors');
		$action = $id > 0 ? 'edit' : 'create';
	} else {
		if ($id > 0) {
			$r = $n->update($user);
		} else {
			$n->source = 'web';
			$r = $n->create($user);
			$id = $n->id;
		}
		if ($r > 0) {
			setEventMessages($langs->trans('IbSaved'), null);
			if ($action === 'update_totask') {
				$t = $n->convertToTask($user);
				setEventMessages($t > 0 ? $langs->trans('IbTaskCreated', $n->task_ref) : $n->error, null, $t > 0 ? 'mesgs' : 'errors');
			} elseif ($action === 'update_toagenda') {
				if (empty($n->date_reminder)) {
					setEventMessages($langs->trans('IbErrorNoReminder'), null, 'warnings');
				} else {
					$t = $n->syncAgenda($user, true);
					setEventMessages($t > 0 ? $langs->trans('IbAgendaSynced') : $n->error, null, $t > 0 ? 'mesgs' : 'errors');
				}
			}
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
			exit;
		}
		setEventMessages($n->error, null, 'errors');
		$action = $id > 0 ? 'edit' : 'create';
	}
}
if ($canwrite && $action === 'confirm_delete' && GETPOST('confirm', 'alpha') === 'yes' && $id > 0) {
	$n->delete($user);
	header('Location: '.$backurl);
	exit;
}

$form = new Form($db);
llxHeader('', $langs->trans('IbMenuTop'), '', '', 0, 0, '', '', '', 'mod-ideenbox page-card');

$edit = $canwrite && in_array($action, array('create', 'edit'), true);
if ($action === 'delete') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('IbConfirmDelete'), 'confirm_delete', '', 0, 1);
}

$titletxt = $id > 0 ? ideenboxTypeBadge($n->type).' #'.$n->id : $langs->trans('IbMenuNew');
print load_fiche_titre($titletxt, '<a href="'.$backurl.'">'.$langs->trans('BackToList').'</a>', 'fa-lightbulb');

if ($edit) {
	if ($action === 'create') {
		$n->type = GETPOST('type', 'aZ09') === 'todo' ? 'todo' : ($n->type ?: 'idea');
	}
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].($id > 0 ? '?id='.$id : '').'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" id="ibaction" value="'.($id > 0 ? 'update' : 'add').'">';
}

print '<table class="border centpercent tableforfield">';
// Art
print '<tr><td class="titlefieldcreate">'.$langs->trans('IbType').'</td><td>';
if ($edit) {
	print '<label><input type="radio" name="type" value="idea"'.($n->type !== 'todo' ? ' checked' : '').'> '.ideenboxTypeBadge('idea').'</label> &nbsp; ';
	print '<label><input type="radio" name="type" value="todo"'.($n->type === 'todo' ? ' checked' : '').'> '.ideenboxTypeBadge('todo').'</label>';
	print ' &nbsp; <label><input type="checkbox" name="priority" value="1"'.($n->priority ? ' checked' : '').'> '.$langs->trans('IbPriority').'</label>';
} else {
	print ideenboxTypeBadge($n->type).($n->priority ? ' '.img_picto('', 'fa-exclamation-circle', 'class="error"').' '.$langs->trans('IbPriority') : '');
}
print '</td></tr>';
// Text
print '<tr><td class="fieldrequired">'.$langs->trans('IbText').'</td><td>';
print $edit ? '<textarea name="text" rows="6" class="centpercent" required>'.dol_escape_htmltag($n->text).'</textarea>' : nl2br(dol_escape_htmltag($n->text));
print '</td></tr>';
// Termin
print '<tr><td>'.$langs->trans('IbReminder').'</td><td>';
print $edit ? $form->selectDate($n->date_reminder ?: -1, 'rem', 1, 1, 1, '', 1, 1) : ideenboxReminderHtml($n);
print '</td></tr>';
// Status
if ($id > 0) {
	print '<tr><td>'.$langs->trans('Status').'</td><td>';
	if ($edit) {
		print $form->selectarray('status', array(0 => $langs->trans('IbStatusNew'), 1 => $langs->trans('IbStatusAssigned'), 2 => $langs->trans('IbStatusDone'), 9 => $langs->trans('IbStatusDiscarded')), $n->status);
	} else {
		print IdeenboxNote::statusLabel($n->status, 5);
	}
	print '</td></tr>';
}
// Firma (Multicompany)
$entities = ideenboxEntities($db, $user);
if (!empty($entities)) {
	print '<tr><td>'.$form->textwithpicto($langs->trans('IbTargetEntity'), $langs->trans('IbEntityHelp')).'</td><td>';
	print $edit ? $form->selectarray('fk_target_entity', $entities, $n->fk_target_entity, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300') : dol_escape_htmltag(ideenboxEntityLabel($db, $n->fk_target_entity));
	print '</td></tr>';
}
// Geschäftspartner
print '<tr><td>'.$langs->trans('IbThirdparty').'</td><td>';
if ($edit) {
	print img_picto('', 'company', 'class="pictofixedwidth"').$form->select_company($n->fk_soc, 'socid', '', 1, 0, 0, array(), 0, 'minwidth300 maxwidth500');
} elseif ($n->fk_soc > 0) {
	require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
	$soc = new Societe($db);
	print $soc->fetch($n->fk_soc) > 0 ? $soc->getNomUrl(1) : '';
}
print '</td></tr>';
// Projekt
if (isModEnabled('project')) {
	print '<tr><td>'.$form->textwithpicto($langs->trans('IbProject'), $langs->trans('IbProjectHelp')).'</td><td>';
	if ($edit) {
		print img_picto('', 'project', 'class="pictofixedwidth"').ideenboxSelectProject($db, $user, $n->fk_project);
	} elseif ($n->fk_project > 0) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$p = new Project($db);
		if ($p->fetch($n->fk_project) > 0) {
			print $p->getNomUrl(1, '', 1);
			if (isModEnabled('multicompany')) {
				print ' <span class="opacitymedium">('.dol_escape_htmltag(ideenboxEntityLabel($db, $p->entity)).')</span>';
			}
		}
	}
	print '</td></tr>';
}
// Zuständig
print '<tr><td>'.$langs->trans('IbAssignedTo').'</td><td>';
if ($edit) {
	print img_picto('', 'user', 'class="pictofixedwidth"').$form->select_dolusers($n->fk_user_assign ?: $user->id, 'fk_user_assign', 0, null, 0, '', '', '0', 0, 0, '', 0, '', 'minwidth200');
} elseif ($n->fk_user_assign > 0) {
	$u = new User($db);
	print $u->fetch($n->fk_user_assign) > 0 ? $u->getNomUrl(-1) : '';
}
print '</td></tr>';
// Verknüpfungen + Herkunft
if ($id > 0 && !$edit) {
	if ($n->fk_actioncomm > 0) {
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$ac = new ActionComm($db);
		if ($ac->fetch($n->fk_actioncomm) > 0) {
			print '<tr><td>'.$langs->trans('IbOpenAgenda').'</td><td>'.$ac->getNomUrl(1).' '.dol_print_date($ac->datep, 'dayhour', 'tzuserrel').'</td></tr>';
		}
	}
	if ($n->fk_task > 0) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
		$t = new Task($db);
		if ($t->fetch($n->fk_task) > 0) {
			print '<tr><td>'.$langs->trans('IbOpenTask').'</td><td>'.$t->getNomUrl(1, 'withproject').' '.dol_escape_htmltag($t->label).'</td></tr>';
		}
	}
	$au = new User($db);
	print '<tr><td>'.$langs->trans('IbCreatedBy').'</td><td>'.($n->fk_user_author > 0 && $au->fetch($n->fk_user_author) > 0 ? $au->getNomUrl(-1).' · ' : '').dol_print_date($n->date_creation, 'dayhour', 'tzuserrel');
	if ($n->source) {
		print ' · '.$langs->trans('IbSource'.$n->source);
	}
	print '</td></tr>';
}
print '</table>';

if ($edit) {
	print '<div class="center margintoponly">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	if (isModEnabled('project') && !($n->fk_task > 0)) {
		print ' <input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Save').' + '.$langs->trans('IbToTask')).'" onclick="document.getElementById(\'ibaction\').value=\'update_totask\'">';
	}
	if (isModEnabled('agenda') && !($n->fk_actioncomm > 0)) {
		print ' <input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Save').' + '.$langs->trans('IbToAgenda')).'" onclick="document.getElementById(\'ibaction\').value=\'update_toagenda\'">';
	}
	print ' <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'" formnovalidate>';
	print '</div></form>';
	// Projekt gewählt → Geschäftspartner und Firma vorschlagen
	print '<script>$(function(){ $("#fk_project").on("change", function(){ var o=$(this).find("option:selected"); var e=o.data("entity"); if(e && $("#fk_target_entity").length && !$("#fk_target_entity").val()){ $("#fk_target_entity").val(e).trigger("change"); } }); });</script>';
} elseif ($id > 0 && $canwrite) {
	print '<div class="tabsAction">';
	if (in_array((int) $n->status, array(0, 1), true)) {
		print dolGetButtonAction('', $langs->trans('IbMarkDone'), 'default', $backurl.'?id='.$n->id.'&action=done&token='.newToken().'&fstatus=all', '');
	}
	print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$n->id.'&action=edit', '');
	if (isModEnabled('project') && $n->fk_project > 0 && !($n->fk_task > 0)) {
		print dolGetButtonAction('', $langs->trans('IbToTask'), 'default', $backurl.'?id='.$n->id.'&action=totask&token='.newToken().'&fstatus=all', '');
	}
	if (isModEnabled('agenda') && $n->date_reminder && !($n->fk_actioncomm > 0)) {
		print dolGetButtonAction('', $langs->trans('IbToAgenda'), 'default', $backurl.'?id='.$n->id.'&action=toagenda&token='.newToken().'&fstatus=all', '');
	}
	print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$n->id.'&action=delete&token='.newToken(), '');
	print '</div>';
}

llxFooter();
$db->close();
