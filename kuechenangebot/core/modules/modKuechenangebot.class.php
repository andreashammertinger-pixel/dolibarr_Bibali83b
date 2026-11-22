<?php
/* Copyright (C) 2026 migora gmbh
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    kuechenangebot/core/modules/modKuechenangebot.class.php
 * \brief   Modul-Deskriptor Küchenangebot (Nobilia → Kundenangebot im migora-Layout)
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modKuechenangebot extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;

		$this->numero = 591850;
		$this->rights_class = 'kuechenangebot';
		$this->family = 'crm';
		$this->module_position = '91';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Küchenangebot: Kundenangebot aus Nobilia-Planung (CARAT) mit Kalkulation und Preis-Check';
		$this->descriptionlong = 'Liest Nobilia-Angebot und Pläne (PDF) ein, kalkuliert Aufschlag, Antransport, Aufbau und Müllentsorgung, vergleicht mit Kennzahlen bisheriger Küchen (privat / Mehrfamilienhaus) und erzeugt ein Dolibarr-Angebot mit eigenem PDF (Deckblatt mit 3D-Bild, Hinweise, Angebotsbestimmungen, AGB-QR-Code, Unterschriftsfeld) – immer Deutsch, optional Kopie in Kroatisch oder Englisch.';
		$this->editor_name = 'migora gmbh';
		$this->editor_url = 'https://www.migora.at';
		$this->version = '1.7.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'propal';

		$this->module_parts = array(
			'triggers' => 1,
			'models' => 1,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
		);

		$this->dirs = array('/kuechenangebot/temp', '/kuechenangebot/kalk');
		$this->config_page_url = array('setup.php@kuechenangebot');

		$this->hidden = false;
		$this->depends = array('modSociete', 'modPropale');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('kuechenangebot@kuechenangebot');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);

		$this->const = array(
			1 => array('KA_KOSTEN_STUNDE', 'chaine', '45', 'Interne Kosten je Monteurstunde', 0, 'current', 0),
			2 => array('KA_SATZ_STUNDE', 'chaine', '65', 'Verkaufs-Stundensatz Montage', 0, 'current', 0),
			3 => array('KA_SATZ_LFM', 'chaine', '0', 'Montage je Laufmeter', 0, 'current', 0),
			4 => array('KA_STD_PRO_LFM', 'chaine', '2.5', 'Richtwert Montagestunden je Laufmeter', 0, 'current', 0),
			5 => array('KA_GK_PRIVAT', 'chaine', '12', 'Gemeinkosten private Küche %', 0, 'current', 0),
			6 => array('KA_GK_MFH', 'chaine', '8', 'Gemeinkosten Mehrfamilienhaus %', 0, 'current', 0),
			7 => array('KA_AUFSCHLAG_PRIVAT', 'chaine', '30', 'Standard-Aufschlag privat %', 0, 'current', 0),
			8 => array('KA_AUFSCHLAG_MFH', 'chaine', '20', 'Standard-Aufschlag Mehrfamilienhaus %', 0, 'current', 0),
			9 => array('KA_FAKTOR_EUR', 'chaine', '1', 'Faktor bei Euro-Werten', 0, 'current', 0),
			10 => array('KA_FAKTOR_PUNKTE', 'chaine', '1', 'Faktor bei Katalogpunkten', 0, 'current', 0),
			11 => array('KA_TVA', 'chaine', '20', 'USt.-Satz', 0, 'current', 0),
			12 => array('KA_GUELTIG_TAGE', 'chaine', '30', 'Gültigkeit in Tagen', 0, 'current', 0),
			13 => array('KA_PDF_FARBE', 'chaine', 'AE9D95', 'Akzentfarbe PDF', 0, 'current', 0),
			14 => array('KA_LANG2_DEFAULT', 'chaine', 'hr_HR', 'Vorauswahl zusätzliche Sprache', 0, 'current', 0),
		);

		if (!isModEnabled('kuechenangebot')) {
			$conf->kuechenangebot = new stdClass();
			$conf->kuechenangebot->enabled = 0;
		}

		// Reiter im Dolibarr-Angebot
		$this->tabs = array(
			array('data' => 'propal:+kuechenkalk:KaTabKalk:kuechenangebot@kuechenangebot:$user->hasRight("kuechenangebot", "read"):/kuechenangebot/card.php?propalid=__ID__'),
		);
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;
		$this->rights[$r] = array($this->numero.'01', 'Küchenkalkulationen ansehen', 'r', 0, 'read', '');
		$r++;
		$this->rights[$r] = array($this->numero.'02', 'Küchenkalkulationen erstellen und bearbeiten', 'w', 0, 'write', '');
		$r++;
		$this->rights[$r] = array($this->numero.'03', 'Einstellungen, Textbausteine und Kennzahlen pflegen', 'w', 0, 'setup', '');
		$r++;

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '', 'type' => 'top', 'titre' => 'KaMenuTop', 'prefix' => img_picto('', 'propal', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'kuechenangebot', 'leftmenu' => '', 'url' => '/kuechenangebot/list.php',
			'langs' => 'kuechenangebot@kuechenangebot', 'position' => 1010, 'enabled' => 'isModEnabled("kuechenangebot")',
			'perms' => '$user->hasRight("kuechenangebot", "read") && empty($user->socid)', 'target' => '', 'user' => 0,
		);
		$left = array(
			array('KaMenuNew', '/kuechenangebot/card.php?action=create', 'write', 'ka_new'),
			array('KaMenuList', '/kuechenangebot/list.php', 'read', 'ka_list'),
			array('KaMenuKennzahlen', '/kuechenangebot/kennzahlen.php', 'read', 'ka_kennzahlen'),
			array('KaMenuBausteine', '/kuechenangebot/bausteine.php', 'setup', 'ka_bausteine'),
			array('KaMenuSetup', '/kuechenangebot/admin/setup.php', 'setup', 'ka_setup'),
		);
		$pos = 1011;
		foreach ($left as $l) {
			$this->menu[$r++] = array(
				'fk_menu' => 'fk_mainmenu=kuechenangebot', 'type' => 'left', 'titre' => $l[0],
				'mainmenu' => 'kuechenangebot', 'leftmenu' => $l[3], 'url' => $l[1],
				'langs' => 'kuechenangebot@kuechenangebot', 'position' => $pos++, 'enabled' => 'isModEnabled("kuechenangebot")',
				'perms' => '$user->hasRight("kuechenangebot", "'.$l[2].'") && empty($user->socid)', 'target' => '', 'user' => 0,
			);
		}
	}

	/**
	 * Aktivierung: Tabellen, Dokumentvorlage, Textbausteine
	 */
	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/kuechenangebot/sql/');
		if ($result < 0) {
			return -1;
		}

		$sql = array(
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'migorakueche' AND type = 'propal' AND entity = ".((int) $conf->entity),
			"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES ('migorakueche', 'propal', ".((int) $conf->entity).")",
		);

		$res = $this->_init($sql, $options);
		if ($res > 0) {
			dol_include_once('/kuechenangebot/lib/kuechenangebot.lib.php');
			kaSeedBausteine($this->db);
		}
		return $res;
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
