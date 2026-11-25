<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    hausverwaltung/core/modules/modHausverwaltung.class.php
 * \brief   Modul-Deskriptor Hausverwaltung (Betriebskosten, Rücklagen, Vorschreibung)
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modHausverwaltung extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;

		$this->numero = 591830;
		$this->rights_class = 'hausverwaltung';
		$this->family = 'financial';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Hausverwaltung: Betriebskosten, Verteilschlüssel, Vorschreibungen, Rücklagen je Gebäude';
		$this->descriptionlong = 'Kostenplan je Gebäude/alle Gebäude, Verteilung nach m², monatliche Vorschreibung als Kundenrechnung, automatische Rücklagen-Umbuchung bei Zahlungseingang, Jahresabrechnung und mobile Belegerfassung.';
		$this->editor_name = 'Green Villa and Apartment Resorts d.o.o.';
		$this->editor_url = 'https://www.greenresorts.hr';
		$this->version = '1.16.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'building';

		$this->module_parts = array(
			'triggers' => 1,
			'models' => 1,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 1, // nur für das Menü „Hilfe“ der Eigentümer; alle Seiten der Hausverwaltung bleiben für externe Benutzer gesperrt
		);

		$this->dirs = array('/hausverwaltung/temp');
		$this->config_page_url = array('setup.php@hausverwaltung');

		$this->hidden = false;
		$this->depends = array('modSociete', 'modFacture', 'modFournisseur', 'modBanque', 'modService');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('hausverwaltung@hausverwaltung');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);

		$this->const = array(
			1 => array('HAUSVERWALTUNG_VAT_RATE', 'chaine', '0', 'MwSt-Satz auf Vorschreibungspositionen', 0, 'current', 0),
			2 => array('HAUSVERWALTUNG_DUE_DAY', 'chaine', '5', 'Fälligkeit: Tag des Monats', 0, 'current', 0),
		);

		if (!isModEnabled('hausverwaltung')) {
			$conf->hausverwaltung = new stdClass();
			$conf->hausverwaltung->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Rechte
		$this->rights = array();
		$r = 0;
		$this->rights[$r] = array($this->numero.'01', 'Hausverwaltung ansehen', 'r', 0, 'read', '');
		$r++;
		$this->rights[$r] = array($this->numero.'02', 'Stammdaten und Kostenplan bearbeiten', 'w', 0, 'write', '');
		$r++;
		$this->rights[$r] = array($this->numero.'03', 'Vorschreibungen erstellen', 'w', 0, 'invoice', '');
		$r++;
		$this->rights[$r] = array($this->numero.'04', 'Belege mobil erfassen', 'w', 0, 'capture', '');
		$r++;

		// Menüs
		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '', 'type' => 'top', 'titre' => 'Hausverwaltung', 'prefix' => img_picto('', 'building', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'hausverwaltung', 'leftmenu' => '', 'url' => '/hausverwaltung/index.php',
			'langs' => 'hausverwaltung@hausverwaltung', 'position' => 1000, 'enabled' => 'isModEnabled("hausverwaltung")',
			'perms' => '$user->hasRight("hausverwaltung", "read") && empty($user->socid)', 'target' => '', 'user' => 0,
		);
		$left = array(
			array('HvOverview', '/hausverwaltung/index.php', 'read', 'hv_overview'),
			array('HvBuildings', '/hausverwaltung/buildings.php', 'read', 'hv_buildings'),
			array('HvUnits', '/hausverwaltung/units.php', 'read', 'hv_units'),
			array('HvCostPlan', '/hausverwaltung/costs.php', 'read', 'hv_costs'),
			array('HvGenerate', '/hausverwaltung/generate.php', 'invoice', 'hv_generate'),
			array('HvSend', '/hausverwaltung/send.php', 'invoice', 'hv_send'),
			array('HvMailing', '/hausverwaltung/mailing.php', 'invoice', 'hv_mailing'),
			array('HvPaymentStatus', '/hausverwaltung/status.php', 'read', 'hv_status'),
			array('HvReserves', '/hausverwaltung/reserves.php', 'read', 'hv_reserves'),
			array('HvReservePlan', '/hausverwaltung/reserveplan.php', 'read', 'hv_reserveplan'),
			array('HvSettlement', '/hausverwaltung/settlement.php', 'read', 'hv_settlement'),
			array('HvMobile', '/hausverwaltung/mobile.php', 'capture', 'hv_mobile'),
			array('HvLanguages', '/hausverwaltung/languages.php', 'write', 'hv_languages'),
			array('HvHelpManual', '/hausverwaltung/help.php', 'read', 'hv_help'),
		);
		// Hilfe für Eigentümer (externe Benutzer): eigenes Hauptmenü, öffnet die Eigentümer-Hilfe in ihrer Sprache
		$this->menu[$r++] = array(
			'fk_menu' => '', 'type' => 'top', 'titre' => 'HvHelp', 'prefix' => img_picto('', 'question', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'hvhelp', 'leftmenu' => '', 'url' => '/hausverwaltung/help.php',
			'langs' => 'hausverwaltung@hausverwaltung', 'position' => 1100, 'enabled' => 'isModEnabled("hausverwaltung") && getDolGlobalString("HAUSVERWALTUNG_HELP_URL")',
			'perms' => '!empty($user->socid)', 'target' => '_blank', 'user' => 1,
		);
		$pos = 1001;
		foreach ($left as $l) {
			$this->menu[$r++] = array(
				'fk_menu' => 'fk_mainmenu=hausverwaltung', 'type' => 'left', 'titre' => $l[0],
				'mainmenu' => 'hausverwaltung', 'leftmenu' => $l[3], 'url' => $l[1],
				'langs' => 'hausverwaltung@hausverwaltung', 'position' => $pos++, 'enabled' => 'isModEnabled("hausverwaltung")',
				'perms' => '$user->hasRight("hausverwaltung", "'.$l[2].'") && empty($user->socid)', 'target' => ($l[3] === 'hv_help' ? '_blank' : ''), 'user' => 0,
			);
		}
	}

	/**
	 * Aktivierung: Tabellen, Extrafelder, Dienstleistungen anlegen
	 */
	public function init($options = '')
	{
		global $conf, $langs, $user;

		$result = $this->_load_tables('/hausverwaltung/sql/');
		if ($result < 0) {
			return -1;
		}

		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$ef = new ExtraFields($this->db);
		$en = 'isModEnabled("hausverwaltung")';
		$lf = 'hausverwaltung@hausverwaltung';

		// Filter der Auswahllisten in der Syntax von Dolibarr ≥ 19 (Universal Search Filter)
		$unitparam = array('options' => array('hv_unit:ref:rowid::(entity:=:$ENTITY$)' => null));
		$ciparam = array('options' => array('hv_costitem:label:rowid::(entity:=:$ENTITY$) AND (active:=:1)' => null));

		// Kundenrechnung (Vorschreibung)
		$ef->addExtraField('hv_period', 'HvPeriod', 'varchar', 100, 7, 'facture', 0, 0, '', '', 1, '', '1', 'HvPeriodHelp', '', '0', $lf, $en);
		$ef->addExtraField('hv_period_to', 'HvPeriodTo', 'varchar', 100, 7, 'facture', 0, 0, '', '', 1, '', '1', 'HvPeriodToHelp', '', '0', $lf, $en);
		$ef->addExtraField('hv_unit', 'HvUnit', 'sellist', 101, '', 'facture', 0, 0, '', $unitparam, 1, '', '1', '', '', '0', $lf, $en);
		// Lieferantenrechnung (Eingangsrechnung)
		$ef->addExtraField('hv_costitem', 'HvCostItem', 'sellist', 100, '', 'facture_fourn', 0, 0, '', $ciparam, 1, '', '1', 'HvCostItemHelp', '', '0', $lf, $en);
		// KPD 2025 (Klasifikacija proizvoda po djelatnostima) je Produkt/Leistung – Pflichtangabe je Position auf eRačun (Fiskalizacija 2.0)
		// Auswahl aus der KPD-Tabelle (nur gültige 6-stellige Codes), Anzeige Code + deutscher + kroatischer Name
		$kpdparam = array('options' => array('c_hv_kpd:code|label_de|label_hr:code::(active:=:1)' => null));
		$ef->addExtraField('kpd', 'HvKpd', 'sellist', 50, '', 'product', 0, 0, '', $kpdparam, 1, '', '1', 'HvKpdHelp', '', '0', $lf, '1');
		// Ältere Installation (Freitextfeld) auf Auswahlliste umstellen
		$resk = $this->db->query("SELECT type FROM ".$this->db->prefix()."extrafields WHERE name = 'kpd' AND elementtype = 'product'");
		if ($resk && ($ok = $this->db->fetch_object($resk)) && $ok->type !== 'sellist') {
			$ef->update('kpd', 'HvKpd', 'sellist', '', 'product', 0, 0, 50, $kpdparam, 1, '', '1', 'HvKpdHelp', '', '', '0', $lf, '1');
		}
		// Ältere Installation: Filter der Auswahllisten in die neue Syntax bringen (sonst SQL-Fehler in den Listen)
		foreach (array(array('hv_unit', 'facture', $unitparam), array('hv_costitem', 'facture_fourn', $ciparam), array('kpd', 'product', $kpdparam)) as $fx) {
			$this->db->query("UPDATE ".$this->db->prefix()."extrafields SET param = '".$this->db->escape(serialize($fx[2]))."' WHERE name = '".$this->db->escape($fx[0])."' AND elementtype = '".$this->db->escape($fx[1])."' AND type = 'sellist'");
		}
		// Kennzeichen "Eigentümer" am Kontakt: nur diese Kontakte erhalten Vorschreibungen/Abrechnungen
		$ownerFieldExisted = false;
		$resx = $this->db->query("SELECT rowid FROM ".$this->db->prefix()."extrafields WHERE name = 'hv_owner' AND elementtype = 'contact'");
		if ($resx && $this->db->num_rows($resx)) {
			$ownerFieldExisted = true;
		}
		$ef->addExtraField('hv_owner', 'HvIsOwner', 'boolean', 5, '', 'contact', 0, 0, '', '', 1, '', '1', 'HvIsOwnerHelp', '', '0', $lf, '1');
		if (!$ownerFieldExisted) {
			// Einmalige Übernahme: Kontakte mit Funktion "Eigentümer/Vlasnik/Owner" als Eigentümer markieren
			$this->db->query("INSERT INTO ".$this->db->prefix()."socpeople_extrafields (fk_object, hv_owner) SELECT sp.rowid, 1 FROM ".$this->db->prefix()."socpeople sp WHERE (sp.poste LIKE 'Eigent%' OR sp.poste LIKE 'Vlasni%' OR sp.poste LIKE 'Owner%' OR sp.poste LIKE 'Lastnik%') AND NOT EXISTS (SELECT 1 FROM ".$this->db->prefix()."socpeople_extrafields e WHERE e.fk_object = sp.rowid)");
			$this->db->query("UPDATE ".$this->db->prefix()."socpeople_extrafields e INNER JOIN ".$this->db->prefix()."socpeople sp ON sp.rowid = e.fk_object SET e.hv_owner = 1 WHERE (e.hv_owner IS NULL OR e.hv_owner = 0) AND (sp.poste LIKE 'Eigent%' OR sp.poste LIKE 'Vlasni%' OR sp.poste LIKE 'Owner%' OR sp.poste LIKE 'Lastnik%')");
		}
		// OIB je Eigentümer (Kontakt) – Pflichtangabe auf kroatischen Rechnungen
		$ef->addExtraField('hv_oib', 'HvOib', 'varchar', 10, 20, 'contact', 0, 0, '', '', 1, '', '1', 'HvOibHelp', '', '0', $lf, '1');
		// Nachweis Haushaltsversicherung je Apartment (Miteigentumsvertrag §10.2)
		$ef->addExtraField('hv_hh_insurer', 'HvHhInsurer', 'varchar', 900, 128, 'thirdparty', 0, 0, '', '', 1, '', '1', 'HvHhHelp', '', '0', $lf, $en);
		$ef->addExtraField('hv_hh_policy', 'HvHhPolicy', 'varchar', 901, 64, 'thirdparty', 0, 0, '', '', 1, '', '1', 'HvHhHelp', '', '0', $lf, $en);
		$ef->addExtraField('hv_hh_valid_until', 'HvHhValidUntil', 'date', 902, '', 'thirdparty', 0, 0, '', '', 1, '', '1', 'HvHhHelp', '', '0', $lf, $en);
		$ef->addExtraField('hv_reserve', 'HvPaidFromReserve', 'boolean', 101, '', 'facture_fourn', 0, 0, '', '', 1, '', '1', 'HvPaidFromReserveHelp', '', '0', $lf, $en);

		dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
		$inv = new HvInvoicer($this->db);
		$inv->ensureBaseProducts($user);

		$this->remove($options);
		// PDF-Vorlage "hausverwaltung" für Kundenrechnungen aktivieren
		$sql = array(
			"DELETE FROM ".$this->db->prefix()."document_model WHERE nom = 'hausverwaltung' AND type = 'invoice' AND entity = ".((int) $conf->entity),
			"INSERT INTO ".$this->db->prefix()."document_model (nom, type, entity) VALUES ('hausverwaltung', 'invoice', ".((int) $conf->entity).")",
		);
		$ret = $this->_init($sql, $options);
		if ($ret > 0) {
			// bereits versendete Vorschreibungen einmalig beim Apartment ablegen
			dol_include_once('/hausverwaltung/class/hvmailer.class.php');
			HvMailer::backfillArchive($this->db);
			dol_include_once('/hausverwaltung/class/hvreserveplan.class.php');
			HvReserveRate::migrate($this->db);
		}
		return $ret;
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
