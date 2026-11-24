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
			$keepAb = isset($typ->data['ab']) ? $typ->data['ab'] : null;
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
			$typ->save();
			$this->convertPlan($kalk, $typ);
		}
		$typ->save();
		// Eine bereits übernommene AB bleibt maßgeblich: nach neuem AN erneut anwenden
		if ($anTmp && !empty($keepAb['angewendet']) && !empty($keepAb['datei']) && is_readable($dir.'/'.$keepAb['datei'])) {
			global $user;
			$tmpAb = $dir.'/.ab_reapply.pdf';
			@copy($dir.'/'.$keepAb['datei'], $tmpAb);
			$this->importConfirmation($user, $kalk, $typ, $tmpAb, preg_replace('/^typ\d+_AB_/', '', $keepAb['datei']), false, (int) $keepAb['kuechen']);
			@unlink($tmpAb);
		}
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
				$posl = array();
				foreach ($kalk->positionsFor($k) as $pos) {
					$posl[] = $pos['label'];
				}
				if ($posl) {
					$desc .= "\n".$de->transnoentities('KaPdfInclChanges', implode('; ', $posl));
				}
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

		// Neue Version: Vorgänger-Angebot verknüpfen und schließen
		if ($isnew && !empty($kalk->params['vorgaenger'])) {
			$this->closePredecessor($user, $kalk, $p);
		}

		$p->fetch($p->id);
		$outputlangs = new Translate('', $conf);
		$outputlangs->setDefaultLang('de_DE');
		$res = $p->generateDocument('migorakueche', $outputlangs);
		if ($res <= 0) {
			$this->error = $p->error;
			$this->errors = $p->errors;
			return -2;
		}
		// Pläne mit migora-Plankopf: erst ab „Unterschrieben“ beim Angebot, vorher entfernen
		$this->syncPlans($kalk, $p);
		return $p->id;
	}

	/**
	 * Neue Version einer Kalkulation anlegen: alles kopieren, alte Version sperren
	 * @return int id der neuen Kalkulation oder <0
	 */
	public function createVersion($user, $kalk)
	{
		global $langs;
		if ($kalk->isLocked()) {
			$this->error = $langs->trans('KaVersionLocked');
			return -1;
		}
		$new = new KaKalk($this->db);
		foreach (array('fk_soc', 'art', 'lang2', 'label', 'objekt', 'note_de', 'note_lang2') as $f) {
			$new->$f = $kalk->$f;
		}
		$new->params = $kalk->params;
		$new->params['version'] = $kalk->version() + 1;
		$new->params['vorgaenger'] = $kalk->id;
		$new->params['nachfolger'] = 0;
		$new->params['gesperrt'] = 0;
		$new->hinweise = $kalk->hinweise;
		$new->fk_propal = 0;
		if ($new->save($user) <= 0) {
			$this->error = $new->error;
			return -1;
		}
		$src = $kalk->getDir();
		$dst = $new->getDir();
		dol_mkdir($dst);
		if (is_dir($src.'/unterlagen')) {
			dolCopyDir($src.'/unterlagen', $dst.'/unterlagen', '0', 0);
		}
		foreach ($kalk->typen as $t) {
			$nt = new KaTyp($this->db);
			foreach (array('rang', 'label', 'qty', 'preis_modus', 'katalog', 'faktor', 'lfm', 'vk_override', 'data') as $f) {
				$nt->$f = $t->$f;
			}
			$nt->fk_kalk = $new->id;
			$nt->save();
			$old = array('file_an' => $t->file_an, 'file_bd' => $t->file_bd, 'image' => $t->image);
			foreach ($old as $f => $name) {
				if ($name && is_readable($src.'/'.$name)) {
					$nn = preg_replace('/^typ'.$t->id.'_/', 'typ'.$nt->id.'_', $name);
					@copy($src.'/'.$name, $dst.'/'.$nn);
					$nt->$f = $nn;
				}
			}
			if (is_readable($src.'/'.self::planFile($t))) {
				@copy($src.'/'.self::planFile($t), $dst.'/'.self::planFile($nt));
			}
			$nt->save();
		}
		$kalk->params['gesperrt'] = 1;
		$kalk->params['nachfolger'] = $new->id;
		$kalk->save($user);
		return $new->id;
	}

	/** Vorgänger-Angebot verknüpfen und auf „Nicht unterschrieben“ setzen */
	protected function closePredecessor($user, $kalk, $p)
	{
		global $langs;
		$prev = new KaKalk($this->db);
		if ($prev->fetch((int) $kalk->params['vorgaenger']) <= 0 || !$prev->fk_propal) {
			return;
		}
		$old = new Propal($this->db);
		if ($old->fetch($prev->fk_propal) <= 0) {
			return;
		}
		$p->add_object_linked('propal', $old->id);
		$note = $langs->transnoentities('KaVersionReplaced', $kalk->version());
		if ((int) $old->statut === Propal::STATUS_VALIDATED) {
			$old->closeProposal($user, Propal::STATUS_NOTSIGNED, $note);
			$this->messages[] = $langs->trans('KaVersionClosedOld', $old->ref);
		} else {
			$old->update_note(trim($old->note_private."\n".$note), '_private');
		}
	}

	/**
	 * nobilia-Auftragsbestätigung (AB) zu einem Küchentyp übernehmen.
	 * Eine gültige AB (kein Storno, Gesamtpreis > 0) ist maßgeblich: Ausführung, Geräte und Positionen im Angebot
	 * kommen dann aus der AB (statt aus dem AN), Einkauf je Küche = AB-Gesamtpreis / Küchen, Faktor = Einkauf / Listenwert der AB.
	 * Die AN-Daten bleiben intern unter data['an_original'] erhalten. Ist das Angebot schon unterschrieben,
	 * wird der Verkaufspreis vorher festgeschrieben.
	 * @return int 1 übernommen, 0 gespeichert aber nicht übernommen, <0 Fehler
	 */
	public function importConfirmation($user, $kalk, $typ, $tmp, $name, $force = false, $kuechen = 0)
	{
		$dir = $kalk->getDir();
		dol_mkdir($dir);
		$dest = $dir.'/typ'.$typ->id.'_AB_'.dol_sanitizeFileName($name ?: 'ab.pdf');
		if (!@copy($tmp, $dest)) {
			$this->error = 'Datei konnte nicht gespeichert werden';
			return -1;
		}
		$parser = new KaNobiliaParser();
		$ab = $parser->parseConfirmation($dest);
		if (!$ab) {
			@unlink($dest);
			$this->error = $parser->error;
			return -1;
		}
		if (!empty($typ->data['ab']['datei']) && $typ->data['ab']['datei'] !== basename($dest)) {
			@unlink($dir.'/'.$typ->data['ab']['datei']);
		}
		$an = isset($typ->data['an_original']) ? $typ->data['an_original'] : $typ->data;
		$cmp = KaNobiliaParser::compareConfirmation($an, $ab);
		$k = $kuechen > 0 ? (int) $kuechen : (int) $ab['kuechen'];
		$info = array(
			'datei' => basename($dest), 'nr' => $ab['nr'], 'datum' => $ab['datum'], 'kommission' => $ab['kommission'], 'kv' => $ab['kv'],
			'liefertermin' => $ab['liefertermin'], 'storno' => $ab['storno'], 'gesamt' => $ab['gesamt'], 'listenwert' => $ab['listenwert'],
			'kalkulation' => $ab['kalkulation'], 'kuechen' => max(1, $k), 'vergleich' => $cmp, 'ausfuehrung' => $ab['ausfuehrung'],
			'npos' => count($ab['positionen']), 'angewendet' => 0, 'grund' => '',
		);
		if ($ab['storno']) {
			$info['grund'] = 'storno';
		} elseif (!$ab['gesamt'] || $ab['gesamt'] <= 0) {
			$info['grund'] = 'ohnepreis';
		}
		if ($info['grund'] === '') {
			$this->freezePrices($kalk);
			$typ->fetchFresh();
			$ek = $ab['gesamt'] / $info['kuechen'];
			$info['faktor_vorher'] = (float) $typ->faktor;
			if (!isset($typ->data['an_original'])) {
				$typ->data['an_original'] = array('katalog' => (float) $typ->katalog, 'preis_modus' => $typ->preis_modus);
			}
			$info['katalog_an'] = (float) ($typ->data['an_original']['katalog'] ?? $typ->katalog);
			// Inhalte aus der AB übernehmen, AN-Daten sichern
			if (!isset($typ->data['an_original']['merkmale'])) {
				$typ->data['an_original'] = array_merge($typ->data['an_original'], array_intersect_key($typ->data, array_flip(array('meta', 'merkmale', 'positionen', 'summen', 'geraete', 'hinweise'))));
			}
			$this->applyConfirmationContent($typ, $ab, $typ->data['an_original']);
			// Einkauf und Faktor: Bezug ist der Listenwert der AB (gleiche Planung), sonst der AN-Katalogwert
			$liste = $ab['listenwert'] / $info['kuechen'];
			if ($liste > 0) {
				$typ->katalog = round($liste, 2);
				$typ->preis_modus = 'eur';
			} elseif ((float) $typ->katalog <= 0) {
				$typ->katalog = round($ek, 2);
			}
			$typ->faktor = round($ek / (float) $typ->katalog, 6);
			$info['faktor'] = $typ->faktor;
			$info['ek'] = round($ek, 2);
			$info['angewendet'] = 1;
		}
		$typ->data['ab'] = $info;
		$typ->save();
		$kalk->fetch($kalk->id);
		$kalk->save($user);
		return $info['angewendet'] ? 1 : 0;
	}

	/**
	 * Ausführung, Positionen und Geräte des Küchentyps aus der AB setzen (Format wie beim AN, damit das Angebots-PDF sie nutzt).
	 * Geräte, die auch im AN stehen (gleiche Artikelnummer), übernehmen die ausführlichere AN-Bezeichnung und Energieklasse.
	 */
	protected function applyConfirmationContent($typ, $ab, $an)
	{
		$nb = function ($t) {
			return trim(preg_replace('/\bNB$/u', 'Nachbildung', trim((string) $t)));
		};
		$a = $ab['ausfuehrung'];
		$val = function ($k) use ($a) {
			if (empty($a[$k])) {
				return '';
			}
			return trim($a[$k]['text'] === 'mm' || preg_match('/^\d+$/', (string) $a[$k]['code']) && $k === 'sockelhoehe' ? $a[$k]['code'].' '.$a[$k]['text'] : $a[$k]['text']);
		};
		$m = array();
		$add = function ($label, $code, $value) use (&$m) {
			if (trim((string) $value) !== '') {
				$m[] = array('label' => $label, 'code' => (string) $code, 'value' => trim((string) $value));
			}
		};
		$prog = $val('programm');
		if ($prog !== '' && !preg_match('/^\d+$/', $prog)) {
			$add('Programm', '', $prog);
		}
		$add('Front-Kombination', $a['front']['code'] ?? '', $val('front'));
		$add('Korpusfarbe außen', $a['korpus']['code'] ?? '', $val('korpus'));
		$add('Korpusfarbe innen', $a['korpus_innen']['code'] ?? '', $val('korpus_innen'));
		$add('Griff-Kombination', $a['griff']['code'] ?? '', $val('griff'));
		$add('Sockelfarbe', $a['sockel']['code'] ?? '', $val('sockel'));
		$add('Sockelhöhe', '', $val('sockelhoehe'));
		$apl = $nb($val('apl'));
		$add('Arbeitsplatten-Farbe', $a['apl']['code'] ?? '', $apl);
		$aplA = trim(implode(', ', array_filter(array($val('apl_gruppe'), $val('apl_kante')))));
		$add('Arbeitsplatten-Ausführung', '', $aplA);
		// Positionen im AN-Format
		$pos = array();
		$niche = '';
		foreach ($ab['positionen'] as $p) {
			if (strpos($p['kbz'], 'Block') === 0) {
				continue;
			}
			$text = trim(preg_replace('/\s+/u', ' ', $p['text']));
			if (preg_match('/^Nischenverkleidung/u', $text) && preg_match('/\*APL-DEKOR\*\s*\d+\s+(.+?)(?:\s+\*|$)/u', $text, $mm)) {
				$niche = $nb($mm[1]);
			}
			$pos[] = array('pos' => (string) $p['pos'], 'menge' => (float) $p['stueck'], 'artikel' => $p['kbz'], 'lr' => '', 'text' => $text, 'preis' => null, 'level' => 0, 'wg' => $p['wg']);
		}
		if ($niche !== '') {
			$add('Nischenfarbe', '', $niche);
		}
		// Geräte: AB-Artikel der Warengruppe E bzw. mit Gerätebegriff; Bezeichnung aus dem AN, wenn dort vorhanden
		$norm = function ($x) {
			return strtoupper(preg_replace('/\s+/', '', (string) $x));
		};
		$anDev = array();
		foreach ((array) ($an['geraete'] ?? array()) as $d) {
			$anDev[$norm($d['artikel'] ?? '')] = $d;
		}
		$dev = array();
		foreach ($pos as $p) {
			$key = $norm($p['artikel']);
			if (isset($anDev[$key])) {
				$d = $anDev[$key];
				$d['pos'] = $p['pos'];
				$dev[] = $d;
				continue;
			}
			$cat = '';
			foreach (self::abCategories() as $c => $words) {
				foreach ($words as $w) {
					if (mb_stripos($p['text'], $w) !== false) {
						$cat = $c;
						break 2;
					}
				}
			}
			if ($p['wg'] === 'E' || (in_array($p['wg'], array('ZN', 'ZV'), true) && in_array($cat, array('licht', 'dunst'), true))) {
				$name = trim(preg_replace('/\s*\*.*$/u', '', $p['text']));
				$name = preg_replace('/^GSP\s+/u', 'Geschirrspüler ', $name);
				$name = preg_replace('/^HD-/u', 'Hochdruck-', $name);
				$dev[] = array('pos' => $p['pos'], 'kategorie' => $cat ?: 'sonstiges', 'marke' => '', 'name' => $name, 'eek' => '', 'preis' => 0, 'artikel' => $p['artikel']);
			}
		}
		$typ->data['merkmale'] = $m;
		$typ->data['positionen'] = $pos;
		$typ->data['geraete'] = $dev;
		$typ->data['summen'] = array();
		foreach ($ab['kalkulation'] as $k) {
			if (strpos($k['text'], 'Auftragswert') === 0) {
				$typ->data['summen'][$k['text']] = $k['betrag'];
			}
		}
		$typ->data['meta'] = array_merge((array) ($an['meta'] ?? array()), array('angebot' => 'AB '.$ab['nr'], 'datum' => $ab['datum'], 'quelle' => 'AB', 'kommission' => $ab['kommission'], 'kv' => $ab['kv']));
	}

	/** Gerätekategorien für die Kurztexte der AB */
	protected static function abCategories()
	{
		return array(
			'geschirrspueler' => array('Geschirrsp', 'GSP '),
			'kuehlen' => array('Kühlautomat', 'Kühlschrank', 'Gefrier', 'Kühl-'),
			'kochfeld' => array('Kochfeld', 'Induktion'),
			'backofen' => array('Einbauherd', 'Backofen', 'Herd ', 'Dampfgarer'),
			'mikrowelle' => array('Mikrowelle'),
			'dunst' => array('Dunstabzug', 'Haube', 'Aktivkohlefilter', 'Muldenlüfter'),
			'spuele' => array('Spüle', 'Einbauspüle'),
			'armatur' => array('Mischer', 'Armatur', 'Mischbatterie'),
			'licht' => array('Leuchte', 'LED '),
		);
	}

	/** Bei unterschriebenem Angebot die Verkaufspreise festschreiben (Endpreis je Küche), bevor sich der Einkauf ändert */
	protected function freezePrices($kalk)
	{
		if (!$kalk->fk_propal) {
			return;
		}
		$p = new Propal($this->db);
		if ($p->fetch($kalk->fk_propal) <= 0 || !self::plansReleased($p)) {
			return;
		}
		$calc = $kalk->compute();
		foreach ($kalk->typen as $k => $t) {
			if ($t->vk_override === null || $t->vk_override <= 0) {
				$t->vk_override = round($calc['typen'][$k]['vk'] - $calc['typen'][$k]['pos_vk'], 2);
				$t->save();
			}
		}
	}

	/** Zeilen für den Plankopf links: Einstellung KA_PLAN_KONTAKT oder Ansprechpartner + Firmen-E-Mail */
	public static function planContact()
	{
		global $mysoc;
		$lines = array_values(array_filter(array_map('trim', explode("\n", kaLines(getDolGlobalString('KA_PLAN_KONTAKT'))))));
		if (!$lines) {
			$lines = array_values(array_filter(array_map('trim', explode(',', getDolGlobalString('KA_KONTAKT')))));
			if (!empty($mysoc->email)) {
				$lines[] = $mysoc->email;
			}
		}
		return array_slice($lines, 0, 3);
	}

	/** Bezeichnung rechts im Plankopf */
	public static function planLabel($kalk)
	{
		$l = trim((string) ($kalk->params['plan_label'] ?? ''));
		if ($l === '') {
			$l = trim(getDolGlobalString('KA_PLAN_PREFIX', 'BVH').' '.$kalk->label);
		}
		return $l;
	}

	/** Dateiname der umgewandelten Pläne eines Typs */
	public static function planFile($typ)
	{
		return 'typ'.$typ->id.'_Plaene_migora.pdf';
	}

	/**
	 * Pläne eines Typs mit migora-Plankopf erzeugen (intern in der Kalkulation)
	 * @return int 1 = ok, 0 = keine Pläne, <0 Fehler
	 */
	public function convertPlan($kalk, $typ)
	{
		dol_include_once('/kuechenangebot/class/kaplanconverter.class.php');
		if (!$typ->file_bd || !is_readable($kalk->getDir().'/'.$typ->file_bd)) {
			return 0;
		}
		$conv = KaPlanConverter::fromFile($kalk->getDir().'/'.$typ->file_bd);
		$logo = dol_buildpath('/kuechenangebot/img/plankopf_logo.jpg', 0);
		$ok = $conv->convert($kalk->getDir().'/'.self::planFile($typ), self::planContact(), self::planLabel($kalk), $logo);
		if (!$ok) {
			$this->error = 'Pläne: '.$conv->error;
			$typ->data['plan_status'] = 'error';
			$typ->save();
			return -1;
		}
		$miss = 0;
		foreach ($conv->pagesInfo as $pi) {
			if (!$pi['removed']) {
				$miss++;
			}
		}
		$typ->data['plan_status'] = $miss ? 'warn' : 'ok';
		$typ->data['plan_missing'] = $miss;
		$typ->save();
		return 1;
	}

	/** Alle Pläne der Kalkulation neu erzeugen (z. B. nach Änderung der Bezeichnung) */
	public function convertAllPlans($kalk)
	{
		foreach ($kalk->typen as $typ) {
			$this->convertPlan($kalk, $typ);
		}
	}

	/** Pläne dürfen zum Kunden: Angebot unterschrieben oder fakturiert */
	public static function plansReleased($propal)
	{
		return $propal && in_array((int) $propal->statut, array(2, 4), true);
	}

	/**
	 * Pläne beim Angebot (und bei daraus erstellten Aufträgen) ablegen bzw. entfernen
	 * @param KaKalk $kalk
	 * @param Propal $propal
	 */
	public function syncPlans($kalk, $propal)
	{
		global $conf;
		$dirs = array($conf->propal->multidir_output[$propal->entity].'/'.dol_sanitizeFileName($propal->ref));
		$released = self::plansReleased($propal);
		if ($released) {
			$propal->fetchObjectLinked($propal->id, 'propal', null, 'commande');
			foreach ((array) ($propal->linkedObjects['commande'] ?? array()) as $cmd) {
				$dirs[] = $conf->commande->multidir_output[$cmd->entity].'/'.dol_sanitizeFileName($cmd->ref);
			}
		}
		$n = 0;
		foreach ($dirs as $i => $dir) {
			foreach (glob($dir.'/Plaene_*.pdf') ?: array() as $old) {
				@unlink($old);
			}
			if (!$released) {
				continue;
			}
			dol_mkdir($dir);
			foreach ($kalk->typen as $typ) {
				$src = $kalk->getDir().'/'.self::planFile($typ);
				if (!is_readable($src)) {
					$this->convertPlan($kalk, $typ);
				}
				if (is_readable($src)) {
					$name = 'Plaene_'.dol_sanitizeFileName(str_replace(' ', '_', $typ->label ?: 'Kueche'.$typ->id)).'.pdf';
					if (@copy($src, $dir.'/'.$name) && $i === 0) {
						$n++;
					}
				}
			}
		}
		return $n;
	}

	/** Für den Trigger: Pläne zu einem Angebot freigeben */
	public function syncPlansForProposal($propalid)
	{
		$kalk = new KaKalk($this->db);
		if ($kalk->fetch(0, $propalid) <= 0) {
			return 0;
		}
		$p = new Propal($this->db);
		if ($p->fetch($propalid) <= 0) {
			return 0;
		}
		return $this->syncPlans($kalk, $p);
	}
}
