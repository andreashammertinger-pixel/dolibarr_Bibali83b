<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */
/** Übersicht Ideen & ToDos: filtern, schnell erfassen, erledigen, übernehmen */
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
$type = GETPOST('type', 'aZ09');
$fstatus = GETPOSTISSET('fstatus') ? GETPOST('fstatus', 'aZ09') : 'open';
$due = GETPOST('due', 'aZ09');
$search = GETPOST('search', 'alphanohtml');
$unassigned = GETPOSTINT('unassigned');
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'n.date_creation';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'DESC';

$param = '&type='.urlencode($type).'&fstatus='.urlencode($fstatus).'&due='.urlencode($due).'&search='.urlencode($search).'&unassigned='.$unassigned;
$self = $_SERVER['PHP_SELF'].'?'.ltrim($param, '&');

// Aktionen
if ($canwrite && $action === 'quickadd' && GETPOST('token', 'alpha')) {
	$n = new IdeenboxNote($db);
	$n->type = GETPOST('qtype', 'aZ09') === 'todo' ? 'todo' : 'idea';
	$n->text = trim(GETPOST('qtext', 'restricthtml'));
	$n->date_reminder = dol_mktime(GETPOSTINT('qdatehour'), GETPOSTINT('qdatemin'), 0, GETPOSTINT('qdatemonth'), GETPOSTINT('qdateday'), GETPOSTINT('qdateyear'), 'tzuserrel') ?: null;
	$n->source = 'web';
	if ($n->create($user) > 0) {
		setEventMessages($langs->trans('IbSaved'), null);
	} else {
		setEventMessages($n->error, null, 'errors');
	}
	header('Location: '.$self);
	exit;
}
if ($canwrite && $id > 0 && in_array($action, array('done', 'reopen', 'discard', 'toagenda', 'totask', 'confirm_delete'), true)) {
	$n = new IdeenboxNote($db);
	if ($n->fetch($id) > 0 && ($seeall || in_array($user->id, array($n->fk_user_author, $n->fk_user_assign)))) {
		if ($action === 'done') {
			$n->status = IdeenboxNote::STATUS_DONE;
			$n->update($user);
		} elseif ($action === 'reopen') {
			$n->status = ($n->fk_soc || $n->fk_project || $n->fk_target_entity) ? IdeenboxNote::STATUS_ASSIGNED : IdeenboxNote::STATUS_NEW;
			$n->update($user);
		} elseif ($action === 'discard') {
			$n->status = IdeenboxNote::STATUS_DISCARDED;
			$n->update($user);
		} elseif ($action === 'toagenda') {
			if (empty($n->date_reminder)) {
				setEventMessages($langs->trans('IbErrorNoReminder'), null, 'warnings');
			} elseif ($n->syncAgenda($user, true) > 0) {
				setEventMessages($langs->trans('IbAgendaSynced'), null);
			} else {
				setEventMessages($n->error, null, 'errors');
			}
		} elseif ($action === 'totask') {
			$r = $n->convertToTask($user);
			if ($r > 0) {
				setEventMessages($langs->trans('IbTaskCreated', $n->task_ref), null);
			} else {
				setEventMessages($n->error, null, 'errors');
			}
		} elseif ($action === 'confirm_delete' && GETPOST('confirm', 'alpha') === 'yes') {
			$n->delete($user);
		}
	}
	header('Location: '.$self);
	exit;
}

// Daten
$filter = array('type' => $type, 'search' => $search, 'due' => $due, 'unassigned' => $unassigned);
if ($fstatus === 'open') {
	$filter['status'] = array(0, 1);
} elseif ($fstatus === 'done') {
	$filter['status'] = array(2);
} elseif ($fstatus === 'discarded') {
	$filter['status'] = array(9);
}
if (!$seeall) {
	$filter['user'] = $user->id;
}
$notes = IdeenboxNote::fetchAll($db, $filter, 1000, $sortfield, $sortorder);

$title = $langs->trans('IbMenuTop');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-ideenbox page-index');
$form = new Form($db);

if ($action === 'delete' && $id > 0) {
	print $form->formconfirm($self.'&id='.$id, $langs->trans('Delete'), $langs->trans('IbConfirmDelete'), 'confirm_delete', '', 0, 1);
}

print load_fiche_titre($title.($type === 'todo' ? ' – '.$langs->trans('IbMenuTodos') : ($type === 'idea' ? ' – '.$langs->trans('IbMenuIdeas') : '')), $canwrite ? dolGetButtonTitle($langs->trans('IbMenuNew'), '', 'fa fa-plus-circle', dol_buildpath('/ideenbox/card.php', 1).'?action=create') : '', 'fa-lightbulb');

// Schnell erfassen
if ($canwrite) {
	print '<form method="POST" action="'.$self.'" class="marginbottomonly">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="quickadd">';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><td colspan="4">'.img_picto('', 'fa-bolt', 'class="pictofixedwidth"').$langs->trans('IbQuickAdd').'</td></tr><tr class="oddeven">';
	print '<td class="nowraponall"><label><input type="radio" name="qtype" value="idea"'.($type !== 'todo' ? ' checked' : '').'> '.$langs->trans('IbTypeIdea').'</label> &nbsp; <label><input type="radio" name="qtype" value="todo"'.($type === 'todo' ? ' checked' : '').'> '.$langs->trans('IbTypeTodo').'</label></td>';
	print '<td class="minwidth300"><textarea name="qtext" rows="2" class="centpercent" required placeholder="'.dol_escape_htmltag($langs->trans('IbQuickAddPlaceholder')).'"></textarea></td>';
	print '<td class="nowraponall">'.$langs->trans('IbReminder').'<br>'.$form->selectDate(-1, 'qdate', 1, 1, 1, '', 1, 1).'</td>';
	print '<td class="right"><input type="submit" class="button" value="'.$langs->trans('IbAdd').'"></td>';
	print '</tr></table></div></form>';
}

// Filter
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<div class="inline-block marginbottomonly">';
print $form->selectarray('type', array('' => $langs->trans('IbAll'), 'idea' => $langs->trans('IbMenuIdeas'), 'todo' => $langs->trans('IbMenuTodos')), $type, 0, 0, 0, '', 0, 0, 0, '', 'minwidth100');
print ' '.$form->selectarray('fstatus', array('open' => $langs->trans('IbStatusOpen'), 'done' => $langs->trans('IbStatusDone'), 'discarded' => $langs->trans('IbStatusDiscarded'), 'all' => $langs->trans('IbAll')), $fstatus, 0, 0, 0, '', 0, 0, 0, '', 'minwidth100');
print ' '.$form->selectarray('due', array('' => $langs->trans('IbDue').': '.$langs->trans('IbAll'), 'overdue' => $langs->trans('IbOverdue'), 'today' => $langs->trans('IbDueToday'), 'week' => $langs->trans('IbDueWeek'), 'none' => $langs->trans('IbDueNone')), $due, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150');
print ' <label><input type="checkbox" name="unassigned" value="1"'.($unassigned ? ' checked' : '').'> '.$langs->trans('IbOnlyUnassigned').'</label>';
print ' <input type="text" name="search" class="flat maxwidth200" value="'.dol_escape_htmltag($search).'" placeholder="'.dol_escape_htmltag($langs->trans('Search')).'">';
print ' <input type="submit" class="button small" value="'.$langs->trans('IbFilter').'">';
print '</div></form>';

$sortparam = ltrim($param, '&');
print '<div class="div-table-responsive"><table class="tagtable liste noborder centpercent">';
print '<tr class="liste_titre">';
print_liste_field_titre('IbType', $_SERVER['PHP_SELF'], 'n.type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('IbText', $_SERVER['PHP_SELF'], 'n.text', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('IbReminder', $_SERVER['PHP_SELF'], 'n.date_reminder', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('IbAssignment', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('IbCreated', $_SERVER['PHP_SELF'], 'n.date_creation', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'n.status', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', '', '', '', '', 'right ');
print '</tr>';

if (empty($notes)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('IbNothing').'</span></td></tr>';
}
$card = dol_buildpath('/ideenbox/card.php', 1);
foreach ($notes as $n) {
	$tok = '&token='.newToken();
	$lnk = $self.'&id='.$n->id.$tok;
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.ideenboxTypeBadge($n->type).($n->priority ? ' '.img_picto($langs->trans('IbPriority'), 'fa-exclamation-circle', 'class="error"') : '').'</td>';
	print '<td class="tdoverflowmax500"><a href="'.$card.'?id='.$n->id.'" title="'.dol_escape_htmltag($n->text).'">'.dol_escape_htmltag(dol_trunc($n->text, 160)).'</a>';
	if ($n->source === 'voice') {
		print ' '.img_picto($langs->trans('IbSourcevoice'), 'fa-microphone', 'class="opacitymedium"');
	}
	print '</td>';
	print '<td class="nowraponall">'.ideenboxReminderHtml($n).'</td>';
	print '<td class="tdoverflowmax300">'.dol_escape_htmltag($n->assignmentLabel()).'</td>';
	print '<td class="nowraponall">'.dol_print_date($n->date_creation, 'dayhour', 'tzuserrel').'</td>';
	print '<td class="center">'.IdeenboxNote::statusLabel($n->status, 5).'</td>';
	print '<td class="right nowraponall">';
	if ($canwrite) {
		if (in_array((int) $n->status, array(0, 1), true)) {
			print '<a class="marginleftonly" href="'.$lnk.'&action=done" title="'.$langs->trans('IbMarkDone').'">'.img_picto($langs->trans('IbMarkDone'), 'fa-check', 'class="fa-lg"').'</a>';
		} else {
			print '<a class="marginleftonly" href="'.$lnk.'&action=reopen" title="'.$langs->trans('IbReopen').'">'.img_picto($langs->trans('IbReopen'), 'fa-undo').'</a>';
		}
		if ($n->fk_actioncomm > 0) {
			print '<a class="marginleftonly" href="'.DOL_URL_ROOT.'/comm/action/card.php?id='.$n->fk_actioncomm.'" title="'.$langs->trans('IbOpenAgenda').'">'.img_picto($langs->trans('IbOpenAgenda'), 'fa-calendar-check').'</a>';
		} elseif (isModEnabled('agenda') && $n->date_reminder) {
			print '<a class="marginleftonly" href="'.$lnk.'&action=toagenda" title="'.$langs->trans('IbToAgenda').'">'.img_picto($langs->trans('IbToAgenda'), 'fa-calendar-plus').'</a>';
		}
		if ($n->fk_task > 0) {
			print '<a class="marginleftonly" href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$n->fk_task.'" title="'.$langs->trans('IbOpenTask').'">'.img_picto($langs->trans('IbOpenTask'), 'fa-tasks').'</a>';
		} elseif ($n->fk_project > 0) {
			print '<a class="marginleftonly" href="'.$lnk.'&action=totask" title="'.$langs->trans('IbToTask').'">'.img_picto($langs->trans('IbToTask'), 'fa-share-square').'</a>';
		}
		print '<a class="marginleftonly editfielda" href="'.$card.'?id='.$n->id.'&action=edit" title="'.$langs->trans('Modify').'">'.img_edit().'</a>';
		print '<a class="marginleftonly" href="'.$self.'&id='.$n->id.'&action=delete" title="'.$langs->trans('Delete').'">'.img_delete().'</a>';
	}
	print '</td></tr>';
}
print '</table></div>';
print '<div class="opacitymedium margintoponly">'.$langs->trans('IbCountOpen', count(array_filter($notes, function ($x) {
	return in_array((int) $x->status, array(0, 1), true);
}))).'</div>';

llxFooter();
$db->close();
