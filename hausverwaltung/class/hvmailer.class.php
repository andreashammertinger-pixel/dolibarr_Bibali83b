<?php
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
		if ((float) $f->total_ttc > 0) {
			$body .= $ol->transnoentities('HvMailLblTotal').': '.$amount."\n";
			if ($nMonths > 1) {
				$plan = HvInvoicer::installments($f->total_ttc, $period, $periodTo);
				$body .= $ol->transnoentities('HvMailLblRate').': '.price($plan[0]['amount'], 0, $ol, 1, 2, 2, $conf->currency).' ('.$ol->transnoentities('HvMailRateInfo', $nMonths, getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5)).")\n";
				$body .= $ol->transnoentities('HvMailLblFirstDue').': '.dol_print_date($plan[0]['due'], 'day', false, $ol)."\n\n";
			} else {
				$body .= $ol->transnoentities('HvMailLblDue').': '.$due."\n\n";
			}
			$body .= self::bankBlock($ol, $holder, $iban, $bic, $f->ref);
			if ($nMonths > 1) {
				$body .= $ol->transnoentities('HvMailStandingOrder')."\n\n";
			}
		} else {
			$body .= $ol->transnoentities('HvMailNothingToPay')."\n\n";
		}
		if (substr($lang, 0, 2) !== substr($orig, 0, 2)) {
			$body .= $ol->transnoentities('HvMailCopyNote')."\n\n";
		}
		$mgr = null;
		if (getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0) {
			$mgr = new Societe($this->db);
			if ($mgr->fetch(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID')) <= 0) {
				$mgr = null;
			}
		}
		$body .= $ol->transnoentities('HvMailRegards')."\n".($mgr ? $mgr->name."\n".$ol->transnoentities('HvMailOnBehalf', $mysoc->name) : $mysoc->name)."\n";

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
		if (substr($lang, 0, 2) !== substr($orig, 0, 2)) {
			$body .= $ol->transnoentities('HvMailCopyNote')."\n\n";
		}
		$mgr = null;
		if (getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID') > 0) {
			$mgr = new Societe($this->db);
			if ($mgr->fetch(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID')) <= 0) {
				$mgr = null;
			}
		}
		$body .= $ol->transnoentities('HvMailRegards')."\n".($mgr ? $mgr->name."\n".$ol->transnoentities('HvMailOnBehalf', $mysoc->name) : $mysoc->name)."\n";
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
		if ($testto === '') {
			$this->db->query("INSERT INTO ".$this->db->prefix()."hv_mail_log (entity, fk_invoice, sendto, files, status, error, datec, fk_user) VALUES (".((int) $conf->entity).", ".($fi ? (int) $fi->id : 0).", '".$this->db->escape(implode(', ', $to))."', '".$this->db->escape(implode(', ', $names))."', 'ok', '', '".$this->db->idate(dol_now())."', ".((int) $user->id).")");
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
