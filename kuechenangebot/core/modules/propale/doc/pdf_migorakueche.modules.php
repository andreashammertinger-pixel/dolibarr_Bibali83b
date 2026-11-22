<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/core/modules/propale/doc/pdf_migorakueche.modules.php
 * \brief   Dokumentvorlage „migorakueche“ für Angebote: deutsches Original + optionale Übersetzungskopie
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';
dol_include_once('/kuechenangebot/class/kakalk.class.php');
dol_include_once('/kuechenangebot/class/kapdf.class.php');

class pdf_migorakueche extends ModelePDFPropales
{
	public $db;
	public $name;
	public $description;
	public $type;
	public $version = '1.0.0';
	public $page_largeur = 210;
	public $page_hauteur = 297;
	public $format = array(210, 297);
	public $marge_gauche = 15;
	public $marge_droite = 15;
	public $marge_haute = 10;
	public $marge_basse = 10;
	public $emetteur;

	public function __construct($db)
	{
		global $mysoc;
		$this->db = $db;
		$this->name = 'migorakueche';
		$this->description = 'migora Küchenangebot (Deckblatt mit 3D-Bild, Ausstattung, Bestimmungen, Annahme) – nur für Angebote aus dem Modul Küchenangebot';
		$this->type = 'pdf';
		$this->update_main_doc_field = 1;
		$this->option_logo = 1;
		$this->option_multilang = 1;
		$this->option_draft_watermark = 0;
		$this->emetteur = $mysoc;
	}

	/**
	 * @param Propal    $object
	 * @param Translate $outputlangs wird ignoriert: Original immer Deutsch
	 * @return int 1 = OK, 0 = Fehler
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $conf, $langs, $hookmanager;
		$langs->load('kuechenangebot@kuechenangebot');

		$kalk = new KaKalk($this->db);
		if ($kalk->fetch(0, $object->id) <= 0) {
			$this->error = $langs->transnoentities('KaNoKalkForProposal');
			return 0;
		}
		if (empty($conf->propal->multidir_output[$object->entity])) {
			$this->error = $langs->transnoentities('ErrorConstantNotDefined', 'PROP_OUTPUTDIR');
			return 0;
		}
		$object->fetch_thirdparty();
		$ref = dol_sanitizeFileName($object->ref);
		$dir = $conf->propal->multidir_output[$object->entity].'/'.$ref;
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		$file = $dir.'/'.$ref.'.pdf';
		$builder = new KaPdf($this->db);
		if ($builder->build($object, $kalk, 'de_DE', $file) <= 0) {
			$this->error = $builder->error;
			return 0;
		}

		// Übersetzungskopie
		foreach (glob($dir.'/'.$ref.'_??.pdf') ?: array() as $old) {
			@unlink($old);
		}
		if ($kalk->lang2 && $kalk->lang2 !== 'de_DE') {
			$file2 = $dir.'/'.$ref.'_'.strtolower(substr($kalk->lang2, 0, 2)).'.pdf';
			$b2 = new KaPdf($this->db);
			$b2->build($object, $kalk, $kalk->lang2, $file2);
		}

		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		global $action;
		$hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);

		$this->result = array('fullpath' => $file);
		return 1;
	}
}
