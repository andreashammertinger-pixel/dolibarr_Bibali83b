<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */

/**
 * \file    ideenbox/lib/ideenbox.lib.php
 * \brief   Hilfsfunktionen Ideenbox
 */
dol_include_once('/ideenbox/class/ideenboxnote.class.php');

/** Firmen (Entitäten), auf die der Benutzer Zugriff hat – leer ohne Multicompany */
function ideenboxEntities($db, $user)
{
	if (!isModEnabled('multicompany')) {
		return array();
	}
	$allowed = ideenboxUserEntities($db, $user);
	$out = array();
	$sql = "SELECT rowid, label FROM ".$db->prefix()."entity WHERE active = 1";
	if ($allowed !== null) {
		$sql .= " AND rowid IN (".implode(',', $allowed).")";
	}
	$sql .= " ORDER BY label";
	$res = $db->query($sql);
	if ($res) {
		while ($o = $db->fetch_object($res)) {
			$out[(int) $o->rowid] = $o->label;
		}
	}
	return $out;
}

/** @return int[]|null  null = alle Firmen (Superadmin) */
function ideenboxUserEntities($db, $user)
{
	global $conf;
	if (!isModEnabled('multicompany')) {
		return array((int) $conf->entity);
	}
	if ($user->admin && empty($user->entity)) {
		return null;
	}
	$ids = array((int) $conf->entity, (int) $user->entity);
	$res = $db->query("SELECT DISTINCT entity FROM ".$db->prefix()."usergroup_user WHERE fk_user = ".((int) $user->id));
	if ($res) {
		while ($o = $db->fetch_object($res)) {
			$ids[] = (int) $o->entity;
		}
	}
	return array_values(array_unique(array_filter($ids)));
}

/** Offene Projekte (alle zugänglichen Firmen) */
function ideenboxProjects($db, $user, $includeid = 0)
{
	if (!isModEnabled('project')) {
		return array();
	}
	$allowed = ideenboxUserEntities($db, $user);
	$sql = "SELECT p.rowid, p.ref, p.title, p.entity, p.fk_soc FROM ".$db->prefix()."projet p WHERE (p.fk_statut IN (0, 1)";
	if ($includeid > 0) {
		$sql .= " OR p.rowid = ".((int) $includeid);
	}
	$sql .= ")";
	if ($allowed !== null) {
		$sql .= " AND p.entity IN (".implode(',', $allowed).")";
	}
	$sql .= " ORDER BY p.entity, p.ref DESC";
	$out = array();
	$res = $db->query($sql);
	if ($res) {
		while ($o = $db->fetch_object($res)) {
			$out[(int) $o->rowid] = $o;
		}
	}
	return $out;
}

/** Symbol + Name der Art */
function ideenboxTypeBadge($type, $short = false)
{
	global $langs;
	if ($type === 'todo') {
		return '<span class="badge badge-status4" title="'.dol_escape_htmltag($langs->trans('IbTypeTodo')).'">'.img_picto('', 'fa-check-square', 'class="paddingright"').($short ? '' : $langs->trans('IbTypeTodo')).'</span>';
	}
	return '<span class="badge badge-status1" title="'.dol_escape_htmltag($langs->trans('IbTypeIdea')).'">'.img_picto('', 'fa-lightbulb', 'class="paddingright"').($short ? '' : $langs->trans('IbTypeIdea')).'</span>';
}

/** Termin mit Hervorhebung (überfällig rot) */
function ideenboxReminderHtml($note)
{
	global $langs;
	if (empty($note->date_reminder)) {
		return '<span class="opacitymedium">'.$langs->trans('IbNoReminder').'</span>';
	}
	$s = dol_print_date($note->date_reminder, 'dayhour', 'tzuserrel');
	$open = in_array((int) $note->status, array(0, 1), true);
	if ($open && $note->date_reminder < dol_now()) {
		return '<span class="error" title="'.dol_escape_htmltag($langs->trans('IbOverdue')).'">'.img_warning().' '.$s.'</span>';
	}
	return $s;
}

/** Projekt-Auswahl (gruppiert nach Firma) */
function ideenboxSelectProject($db, $user, $selected, $htmlname = 'fk_project')
{
	global $langs;
	$projects = ideenboxProjects($db, $user, $selected);
	$multi = isModEnabled('multicompany');
	$out = '<select class="flat minwidth300 maxwidth500" name="'.$htmlname.'" id="'.$htmlname.'">';
	$out .= '<option value="0">&nbsp;</option>';
	$cur = null;
	foreach ($projects as $p) {
		if ($multi && $cur !== (int) $p->entity) {
			if ($cur !== null) {
				$out .= '</optgroup>';
			}
			$cur = (int) $p->entity;
			$out .= '<optgroup label="'.dol_escape_htmltag(ideenboxEntityLabel($db, $cur)).'">';
		}
		$out .= '<option value="'.((int) $p->rowid).'" data-entity="'.((int) $p->entity).'" data-socid="'.((int) $p->fk_soc).'"'.((int) $selected === (int) $p->rowid ? ' selected' : '').'>'.dol_escape_htmltag($p->ref.' – '.$p->title).'</option>';
	}
	if ($multi && $cur !== null) {
		$out .= '</optgroup>';
	}
	$out .= '</select>';
	return $out.ajax_combobox($htmlname);
}
