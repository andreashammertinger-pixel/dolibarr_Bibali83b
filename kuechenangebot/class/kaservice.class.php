<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kaservice.class.php
 * \brief   Abläufe: Kunde suchen/anlegen, Nobilia-Dateien übernehmen, Dolibarr-Angebot erzeugen
 */
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/kuechenangebot/class/kakalk.class.php');
dol_include_once('/kuechenangebot/class/kanobiliaparser.class.php');
dol_include_once('/kuechenangebot/class/kadict.class.php');
dol_include_once('/kuechenangebot/lib/kuechenangebot.lib.php');

class KaService
{
	public $db;
	public $error = '';
	public $errors = array();
	public $messages = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Mögliche Dubletten zu einem neuen Kunden
	 * @return array<int,string> id → Name
	 */
	public function findDuplicates($name, $email = '', $town = '')
	{
		$out = array();
		$name = trim($name);
		if ($name === '' && $email === '') {
			return $out;
		}
		$w = array();
		if ($name !== '') {
			$w[] = "s.nom LIKE '%".$this->db->escape($this->db->escapeforlike($name))."%'";
		}
		if ($email !== '') {
			$w[] = "s.email = '".$this->db->escape($email)."'";
		}
		$sql = 'SELECT s.rowid, s.nom, s.town FROM '.MAIN_DB_PREFIX.'societe as s WHERE s.entity IN ('.getEntity('societe').') AND ('.implode(' OR ', $w).')'.$this->db->plimit(10);
		$res = $this->db->query($sql);
		while ($res && ($o = $this->db->fetch_object($res))) {
			$out[(int) $o->rowid] = $o->nom.($o->town ? ' ('.$o->town.')' : '');
		}
		return $out;
	}

	/**
	 * Neuen Kunden anlegen
	 * @param array $d keys: type (private|company), company, firstname, lastname, address, zip, town, country_id, phone, email, lang
	 * @return int id oder <0
	 */
	public function createCustomer($user, $d)
	{
		$s = new Societe($this->db);
		$private = ($d['type'] ?? 'private') === 'private';
		if ($private) {
			$s->name = trim(trim($d['firstname'] ?? '').' '.trim($d['lastname'] ?? ''));
			$s->firstname = trim($d['firstname'] ?? '');
			$s->lastname = trim($d['lastname'] ?? '');
			$s->particulier = 1;
			$s->typent_id = (int) dol_getIdFromCode($this->db, 'TE_PRIVATE', 'c_typent', 'code', 'id');
		} else {
			$s->name = trim($d['company'] ?? '');
		}
		if ($s->name === '') {
			$this->error = 'ErrorFieldRequired';
			return -1;
		}
		$s->client = 1;
		$s->code_client = -1;
		$s->address = trim($d['address'] ?? '');
		$s->zip = trim($d['zip'] ?? '');
		$s->town = trim($d['town'] ?? '');
		$s->country_id = (int) ($d['country_id'] ?? 0);
		$s->phone = trim($d['phone'] ?? '');
		$s->email = trim($d['email'] ?? '');
		if (!empty($d['lang'])) {
			$s->default_lang = $d['lang'];
		}
		$id = $s->create($user);
		if ($id <= 0) {
			$this->error = $s->error;
			$this->errors = $s->errors;
			return -1;
		}
		return $id;
	}

	/**
	 * Nobilia-Dateien zu einem Küchentyp übernehmen und auslesen
	 * @param KaKalk      $kalk
	 * @param KaTyp|null  $typ   null = neuer Typ
	 * @param string|null $anTmp Pfad hochgeladene AN-PDF
	 * @param string|null $bdTmp Pfad hochgeladene BD-PDF
	 * @return KaTyp|null
	 */
	public function importNobilia($kalk, $typ, $anTmp, $bdTmp, $anName = 'angebot.pdf', $bdName = 'plaene.pdf')
	{
		$dir = $kalk->getDir();
		dol_mkdir($dir);
		$new = !$typ;
		if ($new) {
			$typ = new KaTyp($this->db);
			$typ->fk_kalk = $kalk->id;
			$typ->rang = count($kalk->typen) + 1;
			$typ->qty = 1;
			$typ->save();
		}
		$parser = new KaNobiliaParser();
		if ($anTmp) {
			$dest = $dir.'/typ'.$typ->id.'_AN_'.dol_sanitizeFileName($anName);
			if (!@copy($anTmp, $dest)) {
				$this->error = 'Datei konnte nicht gespeichert werden';
				return null;
			}
			$res = $parser->parseOffer($dest);
			if (!$res || !$res['positionen']) {
				$this->error = $parser->error ?: 'Keine Positionen gefunden – ist das ein Nobilia/CARAT-Angebot?';
				if ($new) {
					$typ->delete();
				}
				return null;
			}
			$keep = isset($typ->data['plaene']) ? $typ->data['plaene'] : null;
			$typ->data = $res;
			if ($keep) {
				$typ->data['plaene'] = $keep;
			}
			$typ->file_an = basename($dest);
			$typ->katalog = (float) $res['katalog'];
			if ($new || !$typ->lfm) {
				$typ->lfm = (float) $res['lfm'];
			}
			if ($new) {
				$typ->faktor = (float) getDolGlobalString('KA_FAKTOR_EUR', '1');
				$typ->preis_modus = 'eur';
				if (!$typ->label) {
					$typ->label = self::labelFromMeta($res['meta']);
				}
			}
			if (!$kalk->label && !empty($res['meta']['planungsinformation'])) {
				$kalk->label = $res['meta']['planungsinformation'];
			}
		}
		if ($bdTmp) {
			$dest = $dir.'/typ'.$typ->id.'_BD_'.dol_sanitizeFileName($bdName);
			if (!@copy($bdTmp, $dest)) {
				$this->error = 'Datei konnte nicht gespeichert werden';
				return null;
			}
			$typ->file_bd = basename($dest);
			$img = 'typ'.$typ->id.'_3d.jpg';
			$pl = $parser->parsePlans($dest, $dir.'/'.$img);
			$typ->data['plaene'] = $pl;
			if ($pl['bild']) {
				$typ->image = $img;
			}
		}
		$typ->save();
		// neue Kunden-Hinweise aus den Nobilia-Unterlagen vorauswählen, Bezeichnung übernehmen
		foreach (array_merge((array) ($typ->data['hinweise'] ?? array()), (array) ($typ->data['plaene']['hinweise'] ?? array())) as $h) {
			if (!kaIsInternalHint($h) && !in_array($h, (array) $kalk->hinweise['nobilia'])) {
				$kalk->hinweise['nobilia'][] = $h;
			}
		}
		$this->db->query('UPDATE '.MAIN_DB_PREFIX."ka_kalk SET label = '".$this->db->escape($kalk->label)."', hinweise = '".$this->db->escape(json_encode($kalk->hinweise))."' WHERE rowid = ".((int) $kalk->id));
		return $typ;
	}

	/** Kurzbezeichnung aus "Wohnanlage Musterstraße 1 - Gemeinschaftsküche Typ 22" → "Typ 22" */
	public static function labelFromMeta($meta)
	{
		$p = (string) ($meta['planungsinformation'] ?? '');
		if (preg_match('/\b(Typ\s*[\w.\-]+)/u', $p, $m)) {
			return $m[1];
		}
		$parts = preg_split('/\s+-\s+/u', $p);
		return trim((string) end($parts));
	}

	/** Eigenes Bild für einen Typ übernehmen */
	public function setImage($kalk, $typ, $tmp)
	{
		$dir = $kalk->getDir();
		dol_mkdir($dir);
		$img = 'typ'.$typ->id.'_3d.jpg';
		$info = @getimagesize($tmp);
		if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG))) {
			$this->error = 'Bitte JPG oder PNG hochladen';
			return -1;
		}
		if ($info[2] == IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
			$im = imagecreatefrompng($tmp);
			$bg = imagecreatetruecolor(imagesx($im), imagesy($im));
			imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
			imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
			imagejpeg($bg, $tmp.'.jpg', 90);
			$tmp .= '.jpg';
		}
		KaNobiliaParser::resizeJpeg($tmp, $dir.'/'.$img, 2000);
		$typ->image = $img;
		return $typ->save();
	}

	/**
	 * Dolibarr-Angebot erstellen bzw. aktualisieren und PDFs erzeugen
	 * @param KaKalk $kalk
	 * @return int propal id oder <0
	 */
	public function generateProposal($user, $kalk)
	{
		global $conf, $langs;
		if (!$kalk->typen) {
			$this->error = $langs->trans('KaNeedKitchen');
			return -1;
		}
		if (!$kalk->fk_soc) {
			$this->error = $langs->trans('KaCustomerMissing');
			return -1;
		}
		$calc = $kalk->compute();
		$this->db->begin();
		$p = new Propal($this->db);
		$isnew = true;
		if ($kalk->fk_propal && $p->fetch($kalk->fk_propal) > 0) {
			$isnew = false;
		}
		if ($isnew) {
			$p->socid = $kalk->fk_soc;
			$p->date = dol_now();
			$p->duree_validite = (int) $kalk->params['gueltig_tage'];
			$p->model_pdf = 'migorakueche';
			$p->ref_client = dol_trunc($kalk->label, 250, 'right', 'UTF-8', 1);
			$p->note_private = 'Erstellt aus Küchenkalkulation #'.$kalk->id;
			if ($p->create($user) <= 0) {
				$this->db->rollback();
				$this->error = $p->error;
				$this->errors = $p->errors;
				return -1;
			}
			$p->fetch($p->id);
		}
		$langs->load('kuechenangebot@kuechenangebot');
		if ((int) $p->statut === Propal::STATUS_DRAFT) {
			foreach ($p->lines as $line) {
				$p->deleteLine($line->id);
			}
			if ((int) $p->socid !== (int) $kalk->fk_soc) {
				$p->socid = $kalk->fk_soc;
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.'propal SET fk_soc = '.((int) $kalk->fk_soc).' WHERE rowid = '.((int) $p->id));
			}
			$p->setValueFrom('model_pdf', 'migorakueche');
			$de = new Translate('', $conf);
			$de->setDefaultLang('de_DE');
			$de->loadLangs(array('kuechenangebot@kuechenangebot'));
			$multi = count($kalk->typen) > 1;
			foreach ($kalk->typen as $k => $typ) {
				$row = $calc['typen'][$k];
				$label = ($multi || $typ->label) ? $de->transnoentities('KaPdfKitchenLine', $typ->label ?: '#'.($k + 1)) : $de->transnoentities('KaPdfKitchenLineShort');
				$prog = trim($typ->merkmal('Programm').' '.$typ->merkmal('Front-Kombination'));
				$desc = $de->transnoentities('KaPdfKitchenSub', $prog ?: '-');
				if (!empty($kalk->params['verbraucher'])) {
					$desc .= "\n".$de->transnoentities('KaPdfIndividual');
				}
				$r = $p->addline($desc, $row['vk'], $row['qty'], $calc['tva'], 0, 0, 0, 0, 'HT', 0, 0, 0, -1, 0, 0, 0, round($row['kosten'], 2), $label);
				if ($r <= 0) {
					$this->db->rollback();
					$this->error = $p->error;
					return -1;
				}
			}
			$this->messages[] = $langs->trans($isnew ? 'KaProposalCreated' : 'KaProposalUpdated', $p->ref);
		} else {
			$this->messages[] = $langs->trans('KaProposalNotDraft', $p->ref);
		}
		$kalk->fk_propal = $p->id;
		$kalk->save($user);
		$this->db->commit();

		$p->fetch($p->id);
		$outputlangs = new Translate('', $conf);
		$outputlangs->setDefaultLang('de_DE');
		$res = $p->generateDocument('migorakueche', $outputlangs);
		if ($res <= 0) {
			$this->error = $p->error;
			$this->errors = $p->errors;
			return -2;
		}
		// Pläne als zusätzliche Dokumente
		$dir = $conf->propal->multidir_output[$p->entity].'/'.dol_sanitizeFileName($p->ref);
		foreach (glob($dir.'/Plaene_*.pdf') ?: array() as $old) {
			@unlink($old);
		}
		if (!empty($kalk->params['plaene_anhaengen'])) {
			foreach ($kalk->typen as $typ) {
				if ($typ->file_bd && is_readable($kalk->getDir().'/'.$typ->file_bd)) {
					@copy($kalk->getDir().'/'.$typ->file_bd, $dir.'/Plaene_'.dol_sanitizeFileName($typ->label ?: 'Kueche'.$typ->id).'.pdf');
				}
			}
		}
		return $p->id;
	}
}
