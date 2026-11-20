<?php
/* Copyright (C) 2026 SmartImmo Holding
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ideenbox/core/modules/modIdeenbox.class.php
 * \brief   Modul-Deskriptor Ideenbox (Ideen & ToDos aus der Android-App)
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modIdeenbox extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;

		$this->numero = 591831;
		$this->rights_class = 'ideenbox';
		$this->family = 'projects';
		$this->module_position = '91';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Ideenbox: Ideen und ToDos per Sprache aus der Android-App sammeln, zuordnen und übernehmen';
		$this->descriptionlong = 'Gedanken und ToDos werden in der App „Ideenbox“ diktiert oder getippt und hier gesammelt (z. B. in der Holding). Im Büro werden sie Firmen, Geschäftspartnern und Projekten zugeordnet und als Agenda-Eintrag oder Projektaufgabe übernommen.';
		$this->editor_name = 'SmartImmo Holding';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-lightbulb';

		$this->module_parts = array('triggers' => 0, 'models' => 0, 'css' => array(), 'js' => array(), 'hooks' => array());
		$this->dirs = array();
		$this->config_page_url = array('setup.php@ideenbox');

		$this->hidden = false;
		$this->depends = array('modApi');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('ideenbox@ideenbox');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);

		$this->const = array(
			1 => array('IDEENBOX_AUTO_AGENDA', 'chaine', '1', 'Bei Erinnerung automatisch Agenda-Eintrag anlegen', 0, 'current', 0),
		);

		if (!isModEnabled('ideenbox')) {
			$conf->ideenbox = new stdClass();
			$conf->ideenbox->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array(
			0 => array('file' => 'box_ideenbox.php@ideenbox', 'note' => 'Offene ToDos und Ideen', 'enabledbydefaulton' => 'Home'),
		);
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;
		$this->rights[$r++] = array($this->numero.'01', 'Ideen und ToDos ansehen', 'r', 1, 'read', '');
		$this->rights[$r++] = array($this->numero.'02', 'Ideen und ToDos erfassen und bearbeiten (auch per App)', 'w', 1, 'write', '');
		$this->rights[$r++] = array($this->numero.'03', 'Ideen und ToDos aller Benutzer sehen und löschen', 'd', 0, 'all', '');

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '', 'type' => 'top', 'titre' => 'IbMenuTop', 'prefix' => img_picto('', 'fa-lightbulb', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'ideenbox', 'leftmenu' => '', 'url' => '/ideenbox/index.php',
			'langs' => 'ideenbox@ideenbox', 'position' => 1050, 'enabled' => 'isModEnabled("ideenbox")',
			'perms' => '$user->hasRight("ideenbox", "read") && empty($user->socid)', 'target' => '', 'user' => 0,
		);
		$left = array(
			array('IbMenuOverview', '/ideenbox/index.php', 'read', 'ib_overview'),
			array('IbMenuTodos', '/ideenbox/index.php?type=todo', 'read', 'ib_todos'),
			array('IbMenuIdeas', '/ideenbox/index.php?type=idea', 'read', 'ib_ideas'),
			array('IbMenuInbox', '/ideenbox/index.php?unassigned=1', 'read', 'ib_inbox'),
			array('IbMenuNew', '/ideenbox/card.php?action=create', 'write', 'ib_new'),
			array('IbMenuApp', '/ideenbox/app.php', 'read', 'ib_app'),
		);
		$pos = 1051;
		foreach ($left as $l) {
			$this->menu[$r++] = array(
				'fk_menu' => 'fk_mainmenu=ideenbox', 'type' => 'left', 'titre' => $l[0],
				'mainmenu' => 'ideenbox', 'leftmenu' => $l[3], 'url' => $l[1],
				'langs' => 'ideenbox@ideenbox', 'position' => $pos++, 'enabled' => 'isModEnabled("ideenbox")',
				'perms' => '$user->hasRight("ideenbox", "'.$l[2].'") && empty($user->socid)', 'target' => '', 'user' => 0,
			);
		}
	}

	public function init($options = '')
	{
		$result = $this->_load_tables('/ideenbox/sql/');
		if ($result < 0) {
			return -1;
		}
		$this->remove($options);
		return $this->_init(array(), $options);
	}

	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
