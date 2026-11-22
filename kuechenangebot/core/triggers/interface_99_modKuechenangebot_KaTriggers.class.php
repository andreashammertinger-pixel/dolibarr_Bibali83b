<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/core/triggers/interface_99_modKuechenangebot_KaTriggers.class.php
 * \brief   Pläne mit migora-Plankopf erst nach Annahme des Angebots zum Kunden geben:
 *          bei „Unterschrieben“/„Fakturiert“ zum Angebot (und zu daraus erstellten Aufträgen) legen,
 *          bei Rücksetzen auf Entwurf/Offen/Abgelehnt wieder entfernen.
 */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceKaTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'crm';
		$this->description = 'Küchenangebot: Pläne erst nach Annahme beim Angebot/Auftrag ablegen';
		$this->version = '1.3.0';
		$this->picto = 'propal';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('kuechenangebot')) {
			return 0;
		}
		$propalid = 0;
		switch ($action) {
			case 'PROPAL_CLOSE_SIGNED':
			case 'PROPAL_CLASSIFY_BILLED':
			case 'PROPAL_CLOSE_REFUSED':
			case 'PROPAL_REOPEN':
			case 'PROPAL_UNVALIDATE':
				$propalid = (int) $object->id;
				break;
			case 'ORDER_CREATE':
				// Auftrag aus Angebot: Herkunft steht am neuen Auftrag
				$origin = !empty($object->origin_type) ? $object->origin_type : (!empty($object->origin) ? $object->origin : '');
				if ($origin === 'propal' && !empty($object->origin_id)) {
					$propalid = (int) $object->origin_id;
				}
				break;
		}
		if (!$propalid) {
			return 0;
		}
		dol_include_once('/kuechenangebot/class/kaservice.class.php');
		$svc = new KaService($this->db);
		if ($action === 'ORDER_CREATE') {
			// Verknüpfung ist zu diesem Zeitpunkt evtl. noch nicht gespeichert: direkt in den Auftragsordner legen
			$kalk = new KaKalk($this->db);
			$p = new Propal($this->db);
			if ($kalk->fetch(0, $propalid) > 0 && $p->fetch($propalid) > 0 && KaService::plansReleased($p)) {
				$dir = $conf->commande->multidir_output[$object->entity ?: $conf->entity].'/'.dol_sanitizeFileName($object->ref);
				dol_mkdir($dir);
				foreach ($kalk->typen as $typ) {
					$src = $kalk->getDir().'/'.KaService::planFile($typ);
					if (is_readable($src)) {
						@copy($src, $dir.'/Plaene_'.dol_sanitizeFileName(str_replace(' ', '_', $typ->label ?: 'Kueche'.$typ->id)).'.pdf');
					}
				}
			}
			return 0;
		}
		$svc->syncPlansForProposal($propalid);
		dol_syslog('Küchenangebot: Pläne für Angebot '.$propalid.' abgeglichen ('.$action.')');
		return 0;
	}
}
