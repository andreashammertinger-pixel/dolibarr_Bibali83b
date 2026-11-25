<?php
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
dol_include_once('/hausverwaltung/class/hvsettlement.class.php');

/**
 * E-Mail-Versand von Vorschreibungen / Abrechnungen:
 * an alle Rechnungskontakte (Eigentümer) mit E-Mail, Anhänge = kroatisches Original + Übersetzungskopie,
 * Betreff/Text in der Sprache des Eigentümers (Geschäftspartner-Sprache).
 */
class HvMailer
{
	/** Erläuterung zur Vorschreibung anhängen (Einstellung, Standard an) */
	public static function infoEnabled()
	{
		return getDolGlobalString('HAUSVERWALTUNG_INFO_ATTACH', '1') === '1';
	}

	/** Versendete Dokumente beim Apartment ablegen (Einstellung, Standard an) */
	public static function archiveEnabled()
	{
		return getDolGlobalString('HAUSVERWALTUNG_SENT_TO_THIRDPARTY', '1') === '1';
	}

	/**
	 * Kopie der versendeten Dateien in die Dokumente des Geschäftspartners (Reiter „Verknüpfte Dokumente“),
	 * Dateiname mit Versanddatum vorne, z. B. 2026-11-18_IN2611-0001.pdf
	 *
	 * @return int Anzahl abgelegter Dateien
	 */
	public static function archiveSent($socid, $files, $when = 0)
	{
		if ((int) $socid <= 0 || !$files) {
			return 0;
		}
		dol_include_once('/hausverwaltung/class/hvstatementpdf.class.php');
		$dir = HvStatementPdf::thirdpartyDir((int) $socid);
		dol_mkdir($dir);
		$prefix = dol_print_date($when ?: dol_now(), '%Y-%m-%d').'_';
		$n = 0;
		foreach ($files as $fl) {
			if (is_readable($fl) && dol_copy($fl, $dir.'/'.$prefix.basename($fl), '0', 1) > 0) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Ereignis (Agenda) beim Apartment: versendete E-Mail mit Empfängern, Betreff, Text und Anhängen,
	 * damit unter „Ereignisse“ die gesamte Kommunikation mit dem Eigentümer chronologisch sichtbar ist.
	 *
	 * @param array  $contactIds Kontakte (Eigentümer), an die gesendet wurde
	 * @param string[] $files    angehängte Dateien (Namen; Kopien liegen unter „Verknüpfte Dokumente“ mit Datum vorne)
	 * @param string $refExt     eindeutiger Schlüssel (verhindert doppelte Einträge)
	 * @return int id des Ereignisses, 0 wenn schon vorhanden/Agenda aus, <0 Fehler
	 */
	public static function addEvent($db, User $user, $socid, array $contactIds, $label, $to, $from, $subject, $body, array $files, $refExt, $when = 0, $msgid = '')
	{
		if (!isModEnabled('agenda') || (int) $socid <= 0) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		if ($refExt !== '') {
			$q = $db->query("SELECT id FROM ".$db->prefix()."actioncomm WHERE ref_ext = '".$db->escape($refExt)."'");
			if ($q && $db->num_rows($q) > 0) {
				return 0;
			}
		}
		$when = $when ?: dol_now();
		$names = array();
		$prefix = self::archiveEnabled() ? dol_print_date($when, '%Y-%m-%d').'_' : '';
		foreach ($files as $f) {
			$names[] = $prefix.basename($f);
		}
		$note = $to !== '' ? 'Sent to: '.$to."\nSubject: ".$subject."\n\n".$body : $body;
		if ($names) {
			$note .= "\n\n".$GLOBALS['langs']->transnoentities('HvEventFiles').":\n- ".implode("\n- ", $names);
		}
		$ev = new ActionComm($db);
		$ev->type_code = 'AC_OTH_AUTO';
		$ev->code = 'AC_HV_MAIL';
		$ev->label = dol_trunc($label, 250, 'right', 'UTF-8', 1);
		$ev->note_private = dol_nl2br(dol_escape_htmltag($note, 0, 1));
		$ev->datep = $when;
		$ev->datef = $when;
		$ev->percentage = -1;
		$ev->fulldayevent = 0;
		$ev->socid = (int) $socid;
		$ev->userownerid = (int) $user->id;
		$ev->authorid = (int) $user->id;
		$ev->ref_ext = $refExt;
		$ev->email_subject = $subject;
		$ev->email_to = $to;
		$ev->email_from = $from;
		$ev->email_msgid = $msgid;
		$contactIds = array_values(array_filter(array_map('intval', $contactIds)));
		if ($contactIds) {
			$ev->contact_id = $contactIds[0];
			foreach ($contactIds as $cid) {
				$ev->socpeopleassigned[$cid] = array('id' => $cid, 'mandatory' => 0, 'answer_status' => 0, 'transparency' => 0);
			}
		}
		$r = $ev->create($user, 1);
		if ($r <= 0) {
			dol_syslog('HvMailer::addEvent '.$ev->error, LOG_WARNING);
		}
		return $r;
	}

	/** Einmalig: bereits versendete Vorschreibungen nachträglich beim Apartment ablegen */
	public static function backfillArchive($db)
	{
		global $conf;
		if (!self::archiveEnabled() || getDolGlobalString('HAUSVERWALTUNG_SENT_ARCHIVE_BACKFILL') === '1') {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$n = 0;
		$res = $db->query("SELECT fk_invoice, MIN(datec) as d FROM ".$db->prefix()."hv_mail_log WHERE entity = ".((int) $conf->entity)." AND status = 'ok' AND fk_invoice > 0 GROUP BY fk_invoice");
		$m = new self($db);
		while ($res && ($o = $db->fetch_object($res))) {
			$f = new Facture($db);
			if ($f->fetch((int) $o->fk_invoice) > 0) {
				$n += self::archiveSent((int) $f->socid, $m->files($f), $db->jdate($o->d));
			}
		}
		dolibarr_set_const($db, 'HAUSVERWALTUNG_SENT_ARCHIVE_BACKFILL', '1', 'chaine', 0, '', $conf->entity);
		return $n;
	}

	/** IBAN lesbar in 4er-Gruppen */
	public static function formatIban($iban)
	{
		return trim(chunk_split(strtoupper(preg_replace('/\s+/', '', (string) $iban)), 4, ' '));
	}

	/** Zahlungsangaben untereinander */
	public static function bankBlock($ol, $holder, $iban, $bic, $ref)
	{
		$t = $ol->transnoentities('HvMailBankTitle')."\n";
		$t .= $ol->transnoentities('HvMailLblHolder').': '.$holder."\n";
		$t .= 'IBAN: '.self::formatIban($iban)."\n";
		if (trim((string) $bic) !== '') {
			$t .= 'BIC: '.trim($bic)."\n";
		}
		$t .= $ol->transnoentities('HvMailLblRef').': '.$ref."\n\n";
		return $t;
	}

	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** @return array<int,array{id:int,name:string,email:string}> */
	public function recipients(Facture $f)
	{
		$out = array();
		foreach ($f->getIdContact('external', 'BILLING') as $cid) {
			$c = new Contact($this->db);
			if ($c->fetch($cid) > 0 && isValidEmail($c->email)) {
				$out[] = array('id' => (int) $c->id, 'name' => $c->getFullName($GLOBALS['langs']), 'email' => $c->email, 'company' => (trim((string) $c->firstname) === ''));
			}
		}
		if (!$out) {
			$f->fetch_thirdparty();
			if (isValidEmail($f->thirdparty->email)) {
				$out[] = array('id' => 0, 'name' => $f->thirdparty->name, 'email' => $f->thirdparty->email, 'company' => true);
			}
		}
		return $out;
	}

	/** Original + Übersetzungskopien (REF.pdf, REF_xx_XX.pdf) */
	public function files(Facture $f)
	{
		global $conf;
		$ref = dol_sanitizeFileName($f->ref);
		$dir = $conf->facture->multidir_output[$f->entity ?? $conf->entity].'/'.$ref;
		$files = array();
		if (is_readable($dir.'/'.$ref.'.pdf')) {
			$files[] = $dir.'/'.$ref.'.pdf';
		}
		foreach (glob($dir.'/'.$ref.'_??_??.pdf') ?: array() as $p) {
			$files[] = $p;
		}
		if ($files && self::infoEnabled()) {
			dol_include_once('/hausverwaltung/class/hvinfopdf.class.php');
			$files = array_merge($files, HvInfoPdf::infoFiles($f));
		}
		return $files;
	}

	/** Letzter erfolgreicher Versand */
	public function lastSent($invoiceId)
	{
		$res = $this->db->query("SELECT datec FROM ".$this->db->prefix()."hv_mail_log WHERE fk_invoice = ".((int) $invoiceId)." AND status = 'ok' ORDER BY datec DESC");
		$o = $res ? $this->db->fetch_object($res) : null;
		return $o ? $this->db->jdate($o->datec) : 0;
	}

	/**
	 * Versenden.
	 *
	 * @param string $testto Testadresse: alles geht nur dorthin, Betreff mit [TEST], kein Protokoll
	 * @return int >0 ok, <0 Fehler ($this->error)
	 */
	public function send(User $user, Facture $f, $testto = '')
	{
		global $conf, $mysoc;
		$f->fetch($f->id);
		$f->fetch_thirdparty();
		$f->fetch_optionals();
		$rcpt = $this->recipients($f);
		if (!$rcpt && $testto === '') {
			$this->error = 'Keine E-Mail-Adresse beim Eigentümer / Apartment';
			return -1;
		}
		$files = $this->files($f);
		if (!$files) {
			$this->error = 'Kein PDF vorhanden – bitte Rechnung freigeben/PDF erzeugen';
			return -2;
		}
		// Erläuterung zur Vorschreibung (nicht bei Abrechnungs-Rechnungen) frisch erzeugen und anhängen
		$withInfo = false;
		if (self::infoEnabled() && !preg_match('/-A$/', (string) ($f->array_options['options_hv_period'] ?? ''))) {
			dol_include_once('/hausverwaltung/class/hvinfopdf.class.php');
			$ip = new HvInfoPdf($this->db);
			$withInfo = (bool) $ip->generateForInvoice($f);
			if (!$withInfo && $ip->error !== '' && strpos($ip->error, $f->ref) !== false) {
				// Vorschreibung enthält andere Sätze als die Erläuterung zeigen würde -> nicht widersprüchlich versenden
				$this->error = $ip->error;
				return -6;
			}
			$files = $this->files($f);
		}
		$lang = !empty($f->thirdparty->default_lang) ? $f->thirdparty->default_lang : getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$ol = new Translate('', $conf);
		$ol->setDefaultLang($lang);
		$ol->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');

		$period = (string) ($f->array_options['options_hv_period'] ?? '');
		$isSettlement = (bool) preg_match('/^(\d{4})-A$/', $period, $m);
		$periodTo = (string) ($f->array_options['options_hv_period_to'] ?? '');
		$nMonths = $isSettlement ? 1 : max(1, HvInvoicer::months($period, $periodTo ?: $period));
		$plabel = $isSettlement ? $m[1] : (preg_match('/^\d{4}-\d{2}$/', $period) ? HvInvoicer::periodLabel($period, $periodTo) : dol_print_date($f->date, '%m/%Y'));
		$unitRef = '';
		if (!empty($f->array_options['options_hv_unit'])) {
			$r = $this->db->query("SELECT ref FROM ".$this->db->prefix()."hv_unit WHERE rowid = ".((int) $f->array_options['options_hv_unit']));
			if ($r && ($o = $this->db->fetch_object($r))) {
				$unitRef = $o->ref;
			}
		}
		$acc = new Account($this->db);
		$iban = $holder = $bic = '';
		if (!empty($f->fk_account) && $acc->fetch($f->fk_account) > 0) {
			$iban = $acc->iban;
			$bic = (string) $acc->bic;
			$holder = !empty($acc->owner_name) ? $acc->owner_name : (!empty($acc->proprio) ? $acc->proprio : $mysoc->name);
		}
		$names = implode(', ', array_column($rcpt, 'name'));
		$amount = price($f->total_ttc, 0, $ol, 1, 2, 2, $conf->currency);
		$due = dol_print_date($f->date_lim_reglement, 'day', false, $ol);

		$subject = $ol->transnoentities($isSettlement ? 'HvMailSubjectSettlement' : 'HvMailSubject', $mysoc->name, $unitRef, $plabel);
		// Anrede: Personen namentlich, Firmen (Kontakt ohne Vorname) mit "Sehr geehrte Damen und Herren"
		$persons = array();
		foreach ($rcpt as $rr) {
			if (empty($rr['company'])) {
				$persons[] = $rr['name'];
			}
		}
		$body = ($persons ? $ol->transnoentities('HvMailHello', implode(', ', $persons)) : $ol->transnoentities('HvMailHelloCompany'))."\n\n";
		$body .= $ol->transnoentities($isSettlement ? 'HvMailIntroSettlement' : 'HvMailIntro', $unitRef, $plabel)."\n\n";
		// neu berechnete Vorschreibung (gleiche Nummer) -> Hinweis, dass sie die bisherige Fassung ersetzt
		$sentAt = $this->lastSent((int) $f->id);
		$rc = $sentAt > 0 ? (new HvInvoicer($this->db))->recalcSince((int) $f->id, $sentAt) : null;
		if ($rc && abs($rc['old'] - (float) $f->total_ttc) > 0.005) {
			$body .= $ol->transnoentities('HvMailRecalcNote', price($rc['old'], 0, $ol, 1, 2, 2, $conf->currency), price($f->total_ttc, 0, $ol, 1, 2, 2, $conf->currency))."\n\n";
		}
		if ((float) $f->total_ttc > 0) {
			$body .= $ol->transnoentities('HvMailLblTotal').': '.$amount."\n";
			if ($nMonths > 1) {
				$plan = HvInvoicer::installments($f->total_ttc, $period, $periodTo, HvInvoicer::issued($f));
				$body .= $ol->transnoentities('HvMailLblRate').': '.price($plan[0]['amount'], 0, $ol, 1, 2, 2, $conf->currency).' ('.$ol->transnoentities('HvMailRateInfo', $nMonths, getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5)).")\n";
				$body .= $ol->transnoentities('HvMailLblFirstDue').': '.dol_print_date($plan[0]['due'], 'day', false, $ol).(!empty($plan[0]['late']) ? ' ('.$ol->transnoentities('HvMailWithinDays', HvInvoicer::lateDays()).')' : '')."\n\n";
			} else {
				list($ed, $late) = HvInvoicer::effectiveDue($f);
				$body .= $ol->transnoentities('HvMailLblDue').': '.dol_print_date($ed, 'day', false, $ol).($late ? ' ('.$ol->transnoentities('HvMailWithinDays', HvInvoicer::lateDays()).')' : '')."\n\n";
			}
			$body .= self::bankBlock($ol, $holder, $iban, $bic, $f->ref);
			if ($nMonths > 1) {
				$body .= $ol->transnoentities('HvMailStandingOrder')."\n\n";
			}
		} else {
			$body .= $ol->transnoentities('HvMailNothingToPay')."\n\n";
		}
		if ($withInfo) {
			$body .= $ol->transnoentities('HvMailInfoAttached')."\n\n";
		}
		$mgr = null;
		if (getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0) {
			$mgr = new Societe($this->db);
			if ($mgr->fetch(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID')) <= 0) {
				$mgr = null;
			}
		}
		$body .= $ol->transnoentities('HvMailRegards')."\n".($mgr ? $mgr->name."\n".$ol->transnoentities('HvMailOnBehalf', $mysoc->name) : $mysoc->name)."\n";
		$body .= "\n--\n".hvDocStamp($ol)."\n";

		$fromMail = getDolGlobalString('HAUSVERWALTUNG_MAIL_FROM', $mysoc->email);
		if (!isValidEmail($fromMail)) {
			$this->error = 'Absenderadresse fehlt (Einstellungen Hausverwaltung bzw. E-Mail der Firma)';
			return -3;
		}
		$from = dol_string_nospecial($mysoc->name, ' ', array(",")).' <'.$fromMail.'>';
		$to = array();
		if ($testto !== '') {
			$to[] = $testto;
			$subject = '[TEST] '.$subject;
			$body = '[TEST – '.implode(', ', array_column($rcpt, 'email')).']'."\n\n".$body;
		} else {
			foreach ($rcpt as $r) {
				$to[] = dol_string_nospecial($r['name'], ' ', array(",")).' <'.$r['email'].'>';
			}
		}
		$mimes = array_fill(0, count($files), 'application/pdf');
		$names = array_map('basename', $files);
		$bcc = $testto === '' ? getDolGlobalString('HAUSVERWALTUNG_MAIL_BCC') : '';
		$replyto = ($mgr && isValidEmail($mgr->email)) ? $mgr->email : '';
		$trackid = 'inv'.$f->id;
		$mail = new CMailFile($subject, implode(', ', $to), $from, $body, $files, $mimes, $names, '', $bcc, 0, 0, '', '', $trackid, '', 'standard', $replyto);
		if (!empty($mail->error) || !$mail->sendfile()) {
			$this->error = $mail->error ?: 'Versand fehlgeschlagen';
			if ($testto === '') {
				$this->log($user, $f, $to, $names, 'error', $this->error);
			}
			return -4;
		}
		if ($testto === '') {
			$this->log($user, $f, $to, $names, 'ok', '');
			if (self::archiveEnabled()) {
				self::archiveSent((int) $f->socid, $files);
			}
			// Agenda-Eintrag an der Rechnung (wie beim Dolibarr-Versand)
			$f->sendtoid = array_values(array_filter(array_column($rcpt, 'id')));
			$f->actiontypecode = 'AC_OTH_AUTO';
			$f->actionmsg = dol_concatdesc('Sent to: '.implode(', ', $to)."\nSubject: ".$subject, $body);
			$f->actionmsg2 = $GLOBALS['langs']->transnoentities('InvoiceSentByEMail', $f->ref);
			$f->trackid = $trackid;
			$f->email_subject = $subject;
			$f->email_to = implode(', ', $to);
			$f->email_from = $from;
			$f->email_msgid = $mail->msgid;
			$f->call_trigger('BILL_SENTBYMAIL', $user);
			if (!getDolGlobalString('MAIN_AGENDA_ACTIONAUTO_BILL_SENTBYMAIL')) {
				// Agenda-Automatik für Rechnungsversand ist aus -> Ereignis selbst anlegen, damit es beim Apartment sichtbar ist
				self::addEvent($this->db, $user, (int) $f->socid, array_column($rcpt, 'id'), $GLOBALS['langs']->transnoentities('InvoiceSentByEMail', $f->ref), implode(', ', $to), $from, $subject, $body, $files, 'hvinv-'.((int) $f->id).'-'.dol_now(), 0, $mail->msgid);
			}
		}
		return 1;
	}

	/**
	 * Abrechnungsblatt einer Einheit versenden: an alle Eigentümer, Anhänge = Abrechnungsblatt (Original + Kopie)
	 * und ggf. Nachzahlungsrechnung (Original + Kopie). Text in der Sprache des Eigentümers.
	 */
	public function sendStatement(User $user, $year, $unitId, $testto = '')
	{
		global $conf, $mysoc;
		dol_include_once('/hausverwaltung/class/hvstatementpdf.class.php');
		$st = new HvSettlement($this->db);
		$d = $st->detail($year, $unitId);
		if (!$d || empty($d['unit']->fk_soc)) {
			$this->error = 'no data';
			return -1;
		}
		$files = HvStatementPdf::files($year, $d['unit']->ref);
		if (!$files) {
			$this->error = 'Abrechnungsblatt fehlt – bitte zuerst erzeugen';
			return -2;
		}
		$fi = null;
		if (!empty($d['booked']->fk_invoice)) {
			$fi = new Facture($this->db);
			if ($fi->fetch((int) $d['booked']->fk_invoice) > 0) {
				$files = array_merge($files, $this->files($fi));
			} else {
				$fi = null;
			}
		}
		$soc = new Societe($this->db);
		$soc->fetch((int) $d['unit']->fk_soc);
		$inv = new HvInvoicer($this->db);
		$rcpt = array();
		foreach ($inv->ownerContacts((int) $soc->id) as $cid) {
			$c = new Contact($this->db);
			if ($c->fetch($cid) > 0 && isValidEmail($c->email)) {
				$rcpt[] = array('id' => (int) $c->id, 'name' => $c->getFullName($GLOBALS['langs']), 'email' => $c->email, 'company' => (trim((string) $c->firstname) === ''));
			}
		}
		if (!$rcpt && $testto === '') {
			$this->error = 'Keine E-Mail-Adresse beim Eigentümer';
			return -3;
		}
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$lang = !empty($soc->default_lang) ? $soc->default_lang : $orig;
		$ol = new Translate('', $conf);
		$ol->setDefaultLang($lang);
		$ol->loadLangs(array('main', 'bills', 'hausverwaltung@hausverwaltung'));
		$persons = array();
		foreach ($rcpt as $r) {
			if (empty($r['company'])) {
				$persons[] = $r['name'];
			}
		}
		$saldo = (float) $d['saldo'];
		$subject = $ol->transnoentities('HvMailSubjectStatement', $mysoc->name, $d['unit']->ref, $year);
		$body = ($persons ? $ol->transnoentities('HvMailHello', implode(', ', $persons)) : $ol->transnoentities('HvMailHelloCompany'))."\n\n";
		$body .= $ol->transnoentities('HvMailStatementIntro', $d['unit']->ref, $year)."\n\n";
		$amt = price(abs($saldo), 0, $ol, 1, 2, 2, $conf->currency);
		if (abs($saldo) < 0.005) {
			$body .= $ol->transnoentities('HvStmtBalanced')."\n\n";
		} elseif ($saldo > 0) {
			$body .= $ol->transnoentities('HvMailStatementCredit', $amt)."\n\n";
		} else {
			$body .= $ol->transnoentities('HvMailStatementAdditional', $amt, $fi ? $fi->ref : '-', $fi ? dol_print_date($fi->date_lim_reglement, 'day', false, $ol) : '-')."\n\n";
			$sacc = new Account($this->db);
			if ($fi && !empty($fi->fk_account) && $sacc->fetch($fi->fk_account) > 0) {
				$body .= self::bankBlock($ol, !empty($sacc->owner_name) ? $sacc->owner_name : (!empty($sacc->proprio) ? $sacc->proprio : $mysoc->name), $sacc->iban, $sacc->bic, $fi->ref);
			}
		}
		$mgr = null;
		if (getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0) {
			$mgr = new Societe($this->db);
			if ($mgr->fetch(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID')) <= 0) {
				$mgr = null;
			}
		}
		$body .= $ol->transnoentities('HvMailRegards')."\n".($mgr ? $mgr->name."\n".$ol->transnoentities('HvMailOnBehalf', $mysoc->name) : $mysoc->name)."\n";
		$body .= "\n--\n".hvDocStamp($ol)."\n";
		$fromMail = getDolGlobalString('HAUSVERWALTUNG_MAIL_FROM', $mysoc->email);
		if (!isValidEmail($fromMail)) {
			$this->error = 'Absenderadresse fehlt';
			return -4;
		}
		$from = dol_string_nospecial($mysoc->name, ' ', array(",")).' <'.$fromMail.'>';
		$to = array();
		if ($testto !== '') {
			$to[] = $testto;
			$subject = '[TEST] '.$subject;
			$body = '[TEST – '.implode(', ', array_column($rcpt, 'email')).']'."\n\n".$body;
		} else {
			foreach ($rcpt as $r) {
				$to[] = dol_string_nospecial($r['name'], ' ', array(",")).' <'.$r['email'].'>';
			}
		}
		$names = array_map('basename', $files);
		$mail = new CMailFile($subject, implode(', ', $to), $from, $body, $files, array_fill(0, count($files), 'application/pdf'), $names, '', $testto === '' ? getDolGlobalString('HAUSVERWALTUNG_MAIL_BCC') : '', 0, 0, '', '', 'hvstmt'.$unitId, '', 'standard', ($mgr && isValidEmail($mgr->email)) ? $mgr->email : '');
		if (!empty($mail->error) || !$mail->sendfile()) {
			$this->error = $mail->error ?: 'Versand fehlgeschlagen';
			return -5;
		}
		if ($testto === '' && self::archiveEnabled()) {
			self::archiveSent((int) $d['unit']->fk_soc, $files);
		}
		if ($testto === '') {
			$this->db->query("INSERT INTO ".$this->db->prefix()."hv_mail_log (entity, fk_invoice, sendto, files, status, error, datec, fk_user) VALUES (".((int) $conf->entity).", ".($fi ? (int) $fi->id : 0).", '".$this->db->escape(implode(', ', $to))."', '".$this->db->escape(implode(', ', $names))."', 'ok', '', '".$this->db->idate(dol_now())."', ".((int) $user->id).")");
			self::addEvent($this->db, $user, (int) $d['unit']->fk_soc, array_column($rcpt, 'id'), $GLOBALS['langs']->transnoentities('HvEventStatement', $year, $d['unit']->ref), implode(', ', $to), $from, $subject, $body, $files, 'hvstmt-'.((int) $year).'-'.((int) $unitId).'-'.dol_now(), 0, $mail->msgid);
		}
		return 1;
	}

	/** Zuletzt versendetes Abrechnungsblatt */
	public function lastSentStatement($year, $unitRef)
	{
		$res = $this->db->query("SELECT MAX(datec) as d FROM ".$this->db->prefix()."hv_mail_log WHERE status = 'ok' AND files LIKE '".$this->db->escape('ABR-'.((int) $year).'-'.dol_sanitizeFileName($unitRef)).".pdf%'");
		$o = $res ? $this->db->fetch_object($res) : null;
		return ($o && $o->d) ? $this->db->jdate($o->d) : 0;
	}

	protected function log(User $user, Facture $f, $to, $files, $status, $error)
	{
		global $conf;
		$this->db->query("INSERT INTO ".$this->db->prefix()."hv_mail_log (entity, fk_invoice, sendto, files, status, error, datec, fk_user) VALUES (".((int) $conf->entity).", ".((int) $f->id).", '".$this->db->escape(implode(', ', $to))."', '".$this->db->escape(implode(', ', $files))."', '".$this->db->escape($status)."', '".$this->db->escape($error)."', '".$this->db->idate(dol_now())."', ".((int) $user->id).")");
	}
}
