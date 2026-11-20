<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */

/**
 * \file    ideenbox/core/boxes/box_ideenbox.php
 * \brief   Startseiten-Widget: offene Ideen & ToDos (fällige zuerst)
 */
include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_ideenbox extends ModeleBoxes
{
	public $boxcode = 'ideenbox';
	public $boximg = 'fa-lightbulb';
	public $boxlabel = 'IbBoxTitle';
	public $depends = array('ideenbox');
	public $enabled = 1;

	public function __construct($db, $param = '')
	{
		global $user;
		$this->db = $db;
		$this->hidden = !$user->hasRight('ideenbox', 'read') || !empty($user->socid);
	}

	public function loadBox($max = 10)
	{
		global $user, $langs;
		$langs->load('ideenbox@ideenbox');
		dol_include_once('/ideenbox/lib/ideenbox.lib.php');
		$this->max = $max;
		$url = dol_buildpath('/ideenbox/index.php', 1);
		$this->info_box_head = array('text' => '<a href="'.$url.'">'.$langs->trans('IbBoxTitle').'</a>', 'limit' => $max);

		$filter = array('status' => array(0, 1));
		if (!$user->hasRight('ideenbox', 'all')) {
			$filter['user'] = $user->id;
		}
		// zuerst mit Termin (früheste zuerst), dann neueste ohne Termin
		$withdate = IdeenboxNote::fetchAll($this->db, $filter + array('due' => 'week'), $max, 'n.date_reminder', 'ASC');
		$rest = IdeenboxNote::fetchAll($this->db, $filter, $max * 2, 'n.date_creation', 'DESC');
		$seen = array();
		$list = array();
		foreach (array_merge($withdate, $rest) as $n) {
			if (!isset($seen[$n->id]) && count($list) < $max) {
				$seen[$n->id] = 1;
				$list[] = $n;
			}
		}
		$line = 0;
		$card = dol_buildpath('/ideenbox/card.php', 1);
		foreach ($list as $n) {
			$this->info_box_contents[$line][] = array('td' => 'class="nowraponall" width="18"', 'text' => ideenboxTypeBadge($n->type, true), 'asis' => 1);
			$this->info_box_contents[$line][] = array('td' => 'class="tdoverflowmax300"', 'text' => '<a href="'.$card.'?id='.$n->id.'">'.dol_escape_htmltag(dol_trunc($n->text, 90)).'</a>', 'asis' => 1);
			$this->info_box_contents[$line][] = array('td' => 'class="right nowraponall"', 'text' => $n->date_reminder ? ideenboxReminderHtml($n) : '', 'asis' => 1);
			$line++;
		}
		if (!$line) {
			$this->info_box_contents[0][0] = array('td' => 'class="center"', 'text' => '<span class="opacitymedium">'.$langs->trans('IbNothing').'</span>', 'asis' => 1);
		}
	}

	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
