<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvrepository.class.php');
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
dol_include_once('/hausverwaltung/class/hvmailer.class.php');
dol_include_once('/hausverwaltung/class/hvtranslator.class.php');

/**
 * Rundschreiben an Eigentümer: Text in einer Sprache schreiben, in die Sprachen der Eigentümer übersetzen (KI, bearbeitbar),
 * Anhänge je Sprache (hochgeladen, aus Dolibarr gewählt oder per KI übersetzt), Versand je Apartment in der Sprache des Eigentümers.
 */
class HvMailing
{
	public $db;
	public $error = '';

	public $id = 0;
	public $subject = '';
	public $body = '';
	public $srclang = '';
	public $i18n = array();
	public $docs = array();
	public $recipients = array();
	public $status = 'draft';
	public $datec;
	public $date_sent;
	public $date_test;
	/** Fehler beim Erzeugen von Modul-Dokumenten im letzten filesFor() */
	public $genError = '';

	/** @var array Modul-Dokumente, die während eines Versandlaufs bereits erzeugt wurden */
	protected $genCache = array();

	/** Arten von Modul-Dokumenten (werden beim Versand neu erzeugt) */
	const GEN_KINDS = array('plan', 'statement', 'invoice');

	public function __construct($db)
	{
		$this->db = $db;
	}

	public static function baseDir()
	{
		global $conf;
		return !empty($conf->hausverwaltung->multidir_output[$conf->entity]) ? $conf->hausverwaltung->multidir_output[$conf->entity] : $conf->hausverwaltung->dir_output;
	}

	public function dir()
	{
		return self::baseDir().'/mailing/'.((int) $this->id);
	}

	public function fetch($id)
	{
		global $conf;
		$res = $this->db->query("SELECT * FROM ".$this->db->prefix()."hv_mailing WHERE rowid = ".((int) $id)." AND entity = ".((int) $conf->entity));
		$o = $res ? $this->db->fetch_object($res) : null;
		if (!$o) {
			return 0;
		}
		$this->id = (int) $o->rowid;
		$this->subject = (string) $o->subject;
		$this->body = (string) $o->body;
		$this->srclang = (string) $o->srclang;
		$this->i18n = $o->i18n ? (array) json_decode($o->i18n, true) : array();
		$this->docs = $o->docs ? (array) json_decode($o->docs, true) : array();
		$this->recipients = $o->recipients ? array_map('intval', (array) json_decode($o->recipients, true)) : array();
		$this->status = (string) $o->status;
		$this->datec = $this->db->jdate($o->datec);
		$this->date_sent = $this->db->jdate($o->date_sent);
		$this->date_test = isset($o->date_test) ? $this->db->jdate($o->date_test) : null;
		return 1;
	}

	public function create(User $user, $srclang)
	{
		global $conf;
		$all = array_keys(self::units($this->db));
		$sql = "INSERT INTO ".$this->db->prefix()."hv_mailing (entity, subject, body, srclang, i18n, docs, recipients, status, datec, fk_user) VALUES (".((int) $conf->entity).", '', '', '".$this->db->escape($srclang)."', NULL, NULL, '".$this->db->escape(json_encode($all))."', 'draft', '".$this->db->idate(dol_now())."', ".((int) $user->id).")";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return $this->fetch($this->db->last_insert_id($this->db->prefix().'hv_mailing'));
	}

	/**
	 * Speichern. $changed = true: Inhalt/Empfänger geändert -> der letzte Testversand gilt nicht mehr.
	 */
	public function update($changed = false)
	{
		if ($changed) {
			$this->date_test = null;
		}
		$sql = "UPDATE ".$this->db->prefix()."hv_mailing SET subject = '".$this->db->escape($this->subject)."', body = '".$this->db->escape($this->body)."', srclang = '".$this->db->escape($this->srclang)."'";
		$sql .= ", i18n = ".($this->i18n ? "'".$this->db->escape(json_encode($this->i18n, JSON_UNESCAPED_UNICODE))."'" : 'NULL');
		$sql .= ", docs = ".($this->docs ? "'".$this->db->escape(json_encode(array_values($this->docs), JSON_UNESCAPED_UNICODE))."'" : 'NULL');
		$sql .= ", recipients = '".$this->db->escape(json_encode(array_values(array_unique(array_map('intval', $this->recipients)))))."'";
		$sql .= ", date_test = ".($this->date_test ? "'".$this->db->idate($this->date_test)."'" : 'NULL');
		$sql .= ", status = '".$this->db->escape($this->status)."'".($this->status === 'sent' ? ", date_sent = '".$this->db->idate($this->date_sent ?: dol_now())."'" : '');
		$sql .= " WHERE rowid = ".((int) $this->id);
		return $this->db->query($sql) ? 1 : -1;
	}

	public function delete()
	{
		dol_delete_dir_recursive($this->dir());
		$this->db->query("DELETE FROM ".$this->db->prefix()."hv_mailing_rcpt WHERE fk_mailing = ".((int) $this->id));
		return $this->db->query("DELETE FROM ".$this->db->prefix()."hv_mailing WHERE rowid = ".((int) $this->id)) ? 1 : -1;
	}

	public static function listAll($db)
	{
		global $conf;
		$out = array();
		$res = $db->query("SELECT m.rowid, m.subject, m.status, m.datec, m.date_sent, m.recipients, (SELECT COUNT(*) FROM ".$db->prefix()."hv_mailing_rcpt r WHERE r.fk_mailing = m.rowid AND r.status = 'ok') as nsent FROM ".$db->prefix()."hv_mailing m WHERE m.entity = ".((int) $conf->entity)." ORDER BY m.rowid DESC");
		while ($res && ($o = $db->fetch_object($res))) {
			$out[] = $o;
		}
		return $out;
	}

	/**
	 * Apartments mit Eigentümern: socid => [unit, building, lang, rcpt => [[name, email, company]]]
	 */
	public static function units($db)
	{
		$repo = new HvRepository($db);
		$inv = new HvInvoicer($db);
		$out = array();
		foreach ($repo->getUnits() as $u) {
			if (empty($u->fk_soc)) {
				continue;
			}
			$soc = new Societe($db);
			if ($soc->fetch((int) $u->fk_soc) <= 0) {
				continue;
			}
			$rc = array();
			foreach ($inv->ownerContacts((int) $u->fk_soc) as $cid) {
				$c = new Contact($db);
				if ($c->fetch($cid) > 0 && isValidEmail($c->email)) {
					$rc[] = array('name' => $c->getFullName($GLOBALS['langs']), 'email' => $c->email, 'company' => trim((string) $c->firstname) === '');
				}
			}
			$out[(int) $u->fk_soc] = array('unit' => $u, 'building' => (string) ($u->building_ref ?? ''), 'socname' => $soc->name, 'lang' => $soc->default_lang ?: getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR'), 'rcpt' => $rc);
		}
		return $out;
	}

	/** Sprachen der ausgewählten Empfänger */
	public function recipientLangs()
	{
		$units = self::units($this->db);
		$l = array();
		foreach ($this->recipients as $sid) {
			if (isset($units[$sid])) {
				$l[$units[$sid]['lang']] = true;
			}
		}
		return array_keys($l);
	}

	/** Text (Betreff, Inhalt) in eine Sprache – Ausgangstext oder Übersetzung */
	public function textFor($lang)
	{
		if ($lang === $this->srclang || empty($this->i18n[$lang]['body'])) {
			return array('subject' => $this->subject, 'body' => $this->body, 'translated' => false);
		}
		return array('subject' => (string) ($this->i18n[$lang]['subject'] ?? $this->subject), 'body' => (string) $this->i18n[$lang]['body'], 'translated' => true);
	}

	/** Betreff und Text per KI in die angegebenen Sprachen übersetzen (vorhandene werden ersetzt) */
	public function translateText(array $langsList)
	{
		$targets = array_values(array_diff($langsList, array($this->srclang)));
		if (!$targets || trim($this->body) === '') {
			return 0;
		}
		$t = new HvTranslator();
		$res = $t->translate(array('subject' => $this->subject, 'body' => $this->body), $this->srclang, $targets);
		if ($res === false) {
			$this->error = $t->error;
			return -1;
		}
		foreach ($res as $l => $v) {
			$this->i18n[$l] = array('subject' => (string) ($v['subject'] ?? $this->subject), 'body' => (string) ($v['body'] ?? ''), 'ai' => 1);
		}
		return count($res);
	}

	/* ---------------- Anhänge ---------------- */

	public function addDoc($title)
	{
		$id = 'd'.substr(md5(uniqid('', true)), 0, 8);
		$this->docs[$id] = array('id' => $id, 'title' => (string) $title, 'versions' => array(), 'ai' => array());
		return $id;
	}

	/** Modul-Dokument hinzufügen (Rücklagen-Plan, Abrechnungsblatt Jahr, Vorschreibung Periode) */
	public function addGenDoc($kind, $param)
	{
		$this->docIndex();
		if (!in_array($kind, self::GEN_KINDS, true)) {
			return -1;
		}
		if ($kind === 'statement') {
			$param = (string) (int) $param;
			if ((int) $param < 2000 || (int) $param > 2100) {
				$this->error = 'Jahr';
				return -1;
			}
		} elseif ($kind === 'invoice') {
			$param = (string) $param;
			if (!preg_match('/^\d{4}-\d{2}$/', $param)) {
				$this->error = 'Periode';
				return -1;
			}
		} else {
			$param = '';
		}
		foreach ($this->docs as $d) {
			if (($d['gen'] ?? '') === $kind && (string) ($d['param'] ?? '') === $param) {
				return 0; // schon vorhanden
			}
		}
		$id = 'g'.substr(md5(uniqid('', true)), 0, 8);
		$this->docs[$id] = array('id' => $id, 'title' => '', 'gen' => $kind, 'param' => $param, 'versions' => array(), 'ai' => array());
		return 1;
	}

	public function removeDoc($docId)
	{
		$this->docIndex();
		if (isset($this->docs[$docId]) && !empty($this->docs[$docId]['gen'])) {
			unset($this->docs[$docId]);
		}
	}

	/** Bezeichnung eines Modul-Dokuments */
	public static function genTitle(array $d, $T)
	{
		$k = (string) ($d['gen'] ?? '');
		if ($k === 'statement') {
			return $T->trans('HvMlGenStatement', $d['param']);
		}
		if ($k === 'invoice') {
			return $T->trans('HvMlGenInvoice', $d['param']);
		}
		return $T->trans('HvMlGenPlan');
	}

	/** Perioden, für die Vorschreibungen existieren (neueste zuerst) */
	public static function invoicePeriods($db)
	{
		global $conf;
		$out = array();
		$res = $db->query("SELECT DISTINCT ef.hv_period FROM ".$db->prefix()."facture f INNER JOIN ".$db->prefix()."facture_extrafields ef ON ef.fk_object = f.rowid WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut > 0 AND f.type = 0 AND ef.hv_period IS NOT NULL AND ef.hv_period <> '' ORDER BY ef.hv_period DESC");
		while ($res && ($o = $db->fetch_object($res))) {
			$out[] = (string) $o->hv_period;
		}
		return $out;
	}

	/**
	 * Modul-Dokument für ein Apartment und eine Sprache neu erzeugen (einmal je Versandlauf).
	 *
	 * @return string[]|false Dateien (Original + Fassung in der Sprache des Eigentümers) oder false ($this->error)
	 */
	protected function genFiles(array $d, $lang, $sid, array $u)
	{
		global $conf;
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$kind = (string) $d['gen'];
		if ($kind === 'plan') {
			dol_include_once('/hausverwaltung/class/hvplanpdf.class.php');
			$out = array();
			foreach (array_unique(array($orig, $lang)) as $l) {
				if ($l !== $orig && substr($l, 0, 2) === substr($orig, 0, 2)) {
					continue;
				}
				if (!isset($this->genCache['plan'][$l])) {
					dol_mkdir($this->dir().'/gen');
					$f = $this->dir().'/gen/'.'Plan_pricuve_'.$l.'.pdf';
					$this->genCache['plan'][$l] = (new HvPlanPdf($this->db))->write($l, $f) ? $f : '';
				}
				if ($this->genCache['plan'][$l] !== '') {
					$out[] = $this->genCache['plan'][$l];
				}
			}
			return $out;
		}
		$key = $kind.'|'.$d['param'].'|'.$sid;
		if (isset($this->genCache[$key])) {
			return $this->genCache[$key];
		}
		$unitId = (int) ($u['unit']->rowid ?? 0);
		$res = false;
		if ($kind === 'statement') {
			dol_include_once('/hausverwaltung/class/hvsettlement.class.php');
			dol_include_once('/hausverwaltung/class/hvstatementpdf.class.php');
			dol_include_once('/hausverwaltung/core/modules/facture/doc/pdf_hausverwaltung.modules.php');
			$g = new HvStatementPdf($this->db);
			$files = $g->generate((int) $d['param'], $unitId);
			$res = $files ?: false;
			if (!$files) {
				$this->error = $u['unit']->ref.': '.$GLOBALS['langs']->transnoentities('HvMlGenMissing', self::genTitle($d, $GLOBALS['langs']));
			}
		} elseif ($kind === 'invoice') {
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
			$q = $this->db->query("SELECT f.rowid FROM ".$this->db->prefix()."facture f INNER JOIN ".$this->db->prefix()."facture_extrafields ef ON ef.fk_object = f.rowid WHERE f.entity = ".((int) $conf->entity)." AND f.fk_soc = ".((int) $sid)." AND f.fk_statut > 0 AND f.type = 0 AND ef.hv_period = '".$this->db->escape($d['param'])."' ORDER BY f.rowid DESC");
			$o = $q ? $this->db->fetch_object($q) : null;
			$f = new Facture($this->db);
			if (!$o || $f->fetch((int) $o->rowid) <= 0) {
				$this->error = $u['unit']->ref.': '.$GLOBALS['langs']->transnoentities('HvMlGenMissing', self::genTitle($d, $GLOBALS['langs']));
			} else {
				$ol = new Translate('', $conf);
				$ol->setDefaultLang($orig);
				$ol->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
				$f->fetch_thirdparty();
				if ($f->generateDocument($f->model_pdf ?: 'hausverwaltung', $ol) <= 0) {
					dol_syslog('HvMailing: invoice pdf '.$f->ref.' '.$f->error, LOG_WARNING);
				}
				if (HvMailer::infoEnabled()) {
					dol_include_once('/hausverwaltung/class/hvinfopdf.class.php');
					(new HvInfoPdf($this->db))->generateForInvoice($f);
				}
				$files = (new HvMailer($this->db))->files($f);
				$res = $files ?: false;
				if (!$files) {
					$this->error = $f->ref.': PDF';
				}
			}
		}
		$this->genCache[$key] = $res;
		return $res;
	}

	public function docIndex()
	{
		$out = array();
		foreach ($this->docs as $d) {
			$out[$d['id']] = $d;
		}
		$this->docs = $out;
	}

	/** Hochgeladene Datei als Sprachfassung ablegen */
	public function addUpload($docId, $lang, array $file)
	{
		$this->docIndex();
		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			$this->error = 'upload';
			return -1;
		}
		$name = dol_sanitizeFileName($file['name']);
		if ($name === '' || preg_match('/\.(php\d?|phtml|phar|js|html?|exe|sh)$/i', $name)) {
			$this->error = 'Dateityp nicht erlaubt';
			return -1;
		}
		dol_mkdir($this->dir());
		$target = $this->uniqueName($name);
		$mv = dol_move_uploaded_file($file['tmp_name'], $this->dir().'/'.$target, 1, 0, (int) ($file['error'] ?? 0), 1);
		if ((is_numeric($mv) ? (int) $mv <= 0 : true) || !is_readable($this->dir().'/'.$target)) {
			$this->error = 'Datei konnte nicht gespeichert werden';
			return -1;
		}
		if (!$docId || !isset($this->docs[$docId])) {
			$docId = $this->addDoc(pathinfo($name, PATHINFO_FILENAME));
		}
		$this->docs[$docId]['versions'][$lang] = $target;
		return 1;
	}

	/** Datei aus den Dolibarr-Dokumenten (ECM) übernehmen */
	public function addFromDolibarr($docId, $lang, $rel)
	{
		$this->docIndex();
		$base = realpath(self::ecmDir());
		$src = $base ? realpath($base.'/'.$rel) : false;
		if (!$base || !$src || strpos($src, $base.DIRECTORY_SEPARATOR) !== 0 || !is_file($src)) {
			$this->error = 'Datei nicht gefunden';
			return -1;
		}
		dol_mkdir($this->dir());
		$target = $this->uniqueName(dol_sanitizeFileName(basename($src)));
		dol_copy($src, $this->dir().'/'.$target, '0', 1);
		if (!$docId || !isset($this->docs[$docId])) {
			$docId = $this->addDoc(pathinfo($src, PATHINFO_FILENAME));
		}
		$this->docs[$docId]['versions'][$lang] = $target;
		return 1;
	}

	protected function uniqueName($name)
	{
		$n = $name;
		$i = 1;
		while (file_exists($this->dir().'/'.$n)) {
			$n = pathinfo($name, PATHINFO_FILENAME).'-'.($i++).(pathinfo($name, PATHINFO_EXTENSION) ? '.'.pathinfo($name, PATHINFO_EXTENSION) : '');
		}
		return $n;
	}

	public function removeVersion($docId, $lang, $isAi)
	{
		$this->docIndex();
		if (!isset($this->docs[$docId])) {
			return;
		}
		$k = $isAi ? 'ai' : 'versions';
		if (isset($this->docs[$docId][$k][$lang])) {
			dol_delete_file($this->dir().'/'.$this->docs[$docId][$k][$lang], 0, 0, 0, null, false, 0);
			unset($this->docs[$docId][$k][$lang]);
		}
		if (!$this->docs[$docId]['versions'] && !$this->docs[$docId]['ai']) {
			unset($this->docs[$docId]);
		}
	}

	public static function ecmDir()
	{
		global $conf;
		if (!isModEnabled('ecm')) {
			return '';
		}
		return !empty($conf->ecm->multidir_output[$conf->entity]) ? $conf->ecm->multidir_output[$conf->entity] : (string) $conf->ecm->dir_output;
	}

	/** Dateien in den Dolibarr-Dokumenten (ECM), relativ */
	public static function ecmFiles($max = 400)
	{
		$dir = self::ecmDir();
		if ($dir === '' || !is_dir($dir)) {
			return array();
		}
		$out = array();
		foreach (dol_dir_list($dir, 'files', 1, '', array('(\.meta|_preview.*\.png|\.tmp)$', '^\.'), 'fullname', SORT_ASC, 0) as $f) {
			$out[] = ltrim(substr($f['fullname'], strlen(rtrim($dir, '/'))), '/');
			if (count($out) >= $max) {
				break;
			}
		}
		return $out;
	}

	/** Ausgangsfassung eines Dokuments: sprachneutral, Ausgangssprache oder erste vorhandene */
	public function docSource(array $d)
	{
		foreach (array('all', $this->srclang, getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR')) as $l) {
			if (!empty($d['versions'][$l])) {
				return array($l, $d['versions'][$l]);
			}
		}
		foreach ($d['versions'] as $l => $f) {
			return array($l, $f);
		}
		return array('', '');
	}

	/** Fehlende Sprachfassungen (nur PDF) per KI übersetzen */
	public function translateDocs(array $langsList)
	{
		global $conf, $mysoc;
		$this->docIndex();
		$n = 0;
		$errs = array();
		foreach ($this->docs as $id => $d) {
			if (!empty($d['versions']['all']) || !empty($d['gen'])) {
				continue; // sprachneutral bzw. Modul-Dokument (wird je Sprache erzeugt)
			}
			list($sl, $sf) = $this->docSource($d);
			if ($sf === '' || strtolower(pathinfo($sf, PATHINFO_EXTENSION)) !== 'pdf') {
				continue;
			}
			foreach ($langsList as $l) {
				if (!empty($d['versions'][$l]) || !empty($d['ai'][$l]) || $l === $sl) {
					continue;
				}
				$t = new HvTranslator();
				$r = $t->translatePdf($this->dir().'/'.$sf, $sl, $l);
				if ($r === false) {
					$errs[] = $d['title'].' ('.$l.'): '.$t->error;
					continue;
				}
				$fname = $this->uniqueName(dol_sanitizeFileName(pathinfo($sf, PATHINFO_FILENAME).'_'.$l.'_KI.pdf'));
				if ($this->writeTranslatedPdf($this->dir().'/'.$fname, $r, $sl, $l, $sf)) {
					$this->docs[$id]['ai'][$l] = $fname;
					$n++;
				}
			}
		}
		$this->error = implode('; ', $errs);
		return $n;
	}

	/** Übersetztes Dokument als PDF mit deutlichem Hinweis „KI-Übersetzung“ */
	protected function writeTranslatedPdf($file, array $r, $src, $lang, $origName)
	{
		global $conf, $mysoc;
		$T = new Translate('', $conf);
		$T->setDefaultLang($lang);
		$T->loadLangs(array('main', 'languages', 'hausverwaltung@hausverwaltung'));
		$fmt = pdf_getFormat();
		$pdf = pdf_getInstance(array($fmt['width'], $fmt['height']));
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetMargins(18, 15, 15);
		$pdf->SetAutoPageBreak(true, 20);
		$pdf->SetFont(getDolGlobalString('HAUSVERWALTUNG_PDF_FONT', 'dejavusans'), '', 10);
		$pdf->SetTitle($r['title']);
		$pdf->AddPage();
		$pdf->SetFont('', '', 10);
		$pdf->writeHTML('<style>h1{font-size:15pt}h2{font-size:13pt}h3{font-size:11pt}td,th{border:0.3px solid #999}</style>'.$r['html'], true, false, true, false, '');
		$pdf->Ln(4);
		$pdf->SetFont('', '', 7);
		$pdf->SetTextColor(110, 110, 110);
		$pdf->MultiCell(0, 4, hvDocStamp($T, true, $src), 0, 'C');
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);
		return is_readable($file);
	}

	/**
	 * Anhänge für eine Sprache: sprachneutrale Dateien; sonst kroatisches Original (bzw. Ausgangsfassung)
	 * und zusätzlich die Fassung in der Sprache des Eigentümers (hochgeladen oder KI-Übersetzung).
	 */
	public function filesFor($lang, $sid = 0, array $u = null)
	{
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$out = array();
		$gen = array();
		$this->genError = '';
		foreach ($this->docs as $d) {
			if (!empty($d['gen'])) {
				if ($sid > 0 && $u) {
					$g = $this->genFiles($d, $lang, $sid, $u);
					if ($g === false) {
						$this->genError .= ($this->genError ? '; ' : '').$this->error;
					} else {
						$gen = array_merge($gen, $g);
					}
				}
				continue;
			}
			if (!empty($d['versions']['all'])) {
				$out[] = $d['versions']['all'];
				continue;
			}
			$base = $d['versions'][$orig] ?? $this->docSource($d)[1];
			if ($base !== '') {
				$out[] = $base;
			}
			$mine = $d['versions'][$lang] ?? ($d['ai'][$lang] ?? '');
			if ($mine !== '' && $mine !== $base) {
				$out[] = $mine;
			}
		}
		$dir = $this->dir();
		return array_values(array_filter(array_merge(array_map(function ($f) use ($dir) {
			return $dir.'/'.$f;
		}, array_unique($out)), $gen), 'is_readable'));
	}

	/* ---------------- Versand ---------------- */

	protected function buildMail($lang, array $u = null)
	{
		global $conf, $mysoc;
		$ol = new Translate('', $conf);
		$ol->setDefaultLang($lang);
		$ol->loadLangs(array('main', 'languages', 'hausverwaltung@hausverwaltung'));
		$tx = $this->textFor($lang);
		$body = '';
		if (getDolGlobalString('HAUSVERWALTUNG_MAILING_GREETING', '1') === '1') {
			$persons = array();
			foreach ((array) ($u['rcpt'] ?? array()) as $r) {
				if (empty($r['company'])) {
					$persons[] = $r['name'];
				}
			}
			$body .= ($persons ? $ol->transnoentities('HvMailHello', implode(', ', $persons)) : $ol->transnoentities('HvMailHelloCompany'))."\n\n";
		}
		$body .= rtrim($tx['body'])."\n\n";
		$mgr = null;
		if (getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0) {
			$mgr = new Societe($this->db);
			if ($mgr->fetch(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID')) <= 0) {
				$mgr = null;
			}
		}
		if (getDolGlobalString('HAUSVERWALTUNG_MAILING_GREETING', '1') === '1') {
			$body .= $ol->transnoentities('HvMailRegards')."\n".($mgr ? $mgr->name."\n".$ol->transnoentities('HvMailOnBehalf', $mysoc->name) : $mysoc->name)."\n";
		}
		$body .= "\n--\n".hvDocStamp($ol, true, $this->srclang)."\n";
		return array('subject' => $mysoc->name.' – '.$tx['subject'], 'body' => $body, 'replyto' => ($mgr && isValidEmail($mgr->email)) ? $mgr->email : '');
	}

	/**
	 * Versenden. Testversand: je Sprache der Empfänger eine E-Mail an $testto.
	 *
	 * @return array{ok:int,err:int,errors:string[]}
	 */
	public function send(User $user, $testto = '')
	{
		global $conf, $mysoc;
		$units = self::units($this->db);
		$fromMail = getDolGlobalString('HAUSVERWALTUNG_MAIL_FROM', $mysoc->email);
		$from = dol_string_nospecial($mysoc->name, ' ', array(',')).' <'.$fromMail.'>';
		$out = array('ok' => 0, 'err' => 0, 'errors' => array());
		if (!isValidEmail($fromMail)) {
			$out['errors'][] = 'Absenderadresse fehlt';
			return $out;
		}
		if ($testto === '' && !$this->date_test) {
			$out['errors'][] = $GLOBALS['langs']->transnoentities('HvMlTestFirst');
			return $out;
		}
		// fehlende Übersetzungen (Text und PDF) vor dem Versand ergänzen
		$langsList = $this->recipientLangs();
		if (HvTranslator::enabled()) {
			$missing = array();
			foreach ($langsList as $l) {
				if ($l !== $this->srclang && empty($this->i18n[$l]['body'])) {
					$missing[] = $l;
				}
			}
			if ($missing && $this->translateText($missing) < 0) {
				$out['errors'][] = $this->error;
			}
			$this->translateDocs($langsList);
			$this->update();
		}
		$this->genCache = array();
		if ($testto !== '') {
			foreach ($langsList as $l) {
				$sample = null;
				$sampleSid = 0;
				foreach ($this->recipients as $sid) {
					if (isset($units[$sid]) && $units[$sid]['lang'] === $l) {
						$sample = $units[$sid];
						$sampleSid = (int) $sid;
						break;
					}
				}
				$m = $this->buildMail($l, $sample);
				$files = $this->filesFor($l, $sampleSid, $sample);
				if ($this->genError !== '') {
					$out['errors'][] = $l.': '.$this->genError;
				}
				$info = '[TEST '.$l.' – '.($sample ? $sample['unit']->ref.' '.$sample['socname'] : '').': '.implode(', ', array_column((array) ($sample['rcpt'] ?? array()), 'email')).']';
				$mail = new CMailFile('[TEST '.$l.'] '.$m['subject'], $testto, $from, $info."\n\n".$m['body'], $files, array_map('dol_mimetype', $files), array_map('basename', $files), '', '', 0, 0, '', '', 'hvml'.$this->id, '', 'standard', $m['replyto']);
				if (!empty($mail->error) || !$mail->sendfile()) {
					$out['err']++;
					$out['errors'][] = $l.': '.($mail->error ?: 'Versand fehlgeschlagen');
				} else {
					$out['ok']++;
				}
			}
			if ($out['ok'] > 0 && $out['err'] === 0 && !$out['errors']) {
				$this->date_test = dol_now();
				$this->update();
			}
			return $out;
		}
		foreach ($this->recipients as $sid) {
			if (!isset($units[$sid])) {
				continue;
			}
			$u = $units[$sid];
			if (!$u['rcpt']) {
				$this->logRcpt($sid, '', $u['lang'], array(), 'error', 'keine E-Mail');
				$out['err']++;
				continue;
			}
			$m = $this->buildMail($u['lang'], $u);
			$files = $this->filesFor($u['lang'], $sid, $u);
			if ($this->genError !== '') {
				// Dokument der Hausverwaltung fehlt -> dieses Apartment nicht unvollständig versenden
				$this->logRcpt($sid, '', $u['lang'], $files, 'error', $this->genError);
				$out['err']++;
				$out['errors'][] = $this->genError;
				continue;
			}
			$to = array();
			foreach ($u['rcpt'] as $r) {
				$to[] = dol_string_nospecial($r['name'], ' ', array(',')).' <'.$r['email'].'>';
			}
			$mail = new CMailFile($m['subject'], implode(', ', $to), $from, $m['body'], $files, array_map('dol_mimetype', $files), array_map('basename', $files), '', getDolGlobalString('HAUSVERWALTUNG_MAIL_BCC'), 0, 0, '', '', 'hvml'.$this->id.'-'.$sid, '', 'standard', $m['replyto']);
			if (!empty($mail->error) || !$mail->sendfile()) {
				$this->logRcpt($sid, implode(', ', $to), $u['lang'], $files, 'error', $mail->error ?: 'Versand fehlgeschlagen');
				$out['err']++;
				$out['errors'][] = $u['socname'].': '.($mail->error ?: 'Versand fehlgeschlagen');
				continue;
			}
			$this->logRcpt($sid, implode(', ', $to), $u['lang'], $files, 'ok', '');
			$out['ok']++;
			// beim Apartment ablegen: Text als PDF-freie Textdatei + Anhänge
			if (HvMailer::archiveEnabled()) {
				$txt = $this->dir().'/Rundschreiben-'.((int) $this->id).'_'.$u['lang'].'.txt';
				file_put_contents($txt, $m['subject']."\n\n".$m['body']);
				HvMailer::archiveSent($sid, array_merge(array($txt), $files));
			}
		}
		if ($out['ok'] > 0) {
			$this->status = 'sent';
			$this->date_sent = dol_now();
			$this->update();
		}
		return $out;
	}

	protected function logRcpt($sid, $email, $lang, array $files, $status, $error)
	{
		global $conf;
		$this->db->query("INSERT INTO ".$this->db->prefix()."hv_mailing_rcpt (entity, fk_mailing, fk_soc, email, lang, files, status, error, datec) VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".((int) $sid).", '".$this->db->escape($email)."', '".$this->db->escape($lang)."', '".$this->db->escape(implode(', ', array_map('basename', $files)))."', '".$this->db->escape($status)."', '".$this->db->escape($error)."', '".$this->db->idate(dol_now())."')");
	}

	public function log()
	{
		$out = array();
		$res = $this->db->query("SELECT r.*, s.nom FROM ".$this->db->prefix()."hv_mailing_rcpt r LEFT JOIN ".$this->db->prefix()."societe s ON s.rowid = r.fk_soc WHERE r.fk_mailing = ".((int) $this->id)." ORDER BY r.rowid");
		while ($res && ($o = $this->db->fetch_object($res))) {
			$out[] = $o;
		}
		return $out;
	}
}
