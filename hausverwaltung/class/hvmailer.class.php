<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');

/**
 * E-Mail-Versand von Vorschreibungen / Abrechnungen:
 * an alle Rechnungskontakte (Eigentümer) mit E-Mail, Anhänge = kroatisches Original + Übersetzungskopie,
 * Betreff/Text in der Sprache des Eigentümers (Geschäftspartner-Sprache).
 */
class HvMailer
{
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
		$iban = $holder = '';
		if (!empty($f->fk_account) && $acc->fetch($f->fk_account) > 0) {
			$iban = $acc->iban;
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
			if ($nMonths > 1) {
				$plan = HvInvoicer::installments($f->total_ttc, $period, $periodTo);
				$body .= $ol->transnoentities('HvMailAmountRange', $amount, $nMonths, price($plan[0]['amount'], 0, $ol, 1, 2, 2, $conf->currency), getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5), dol_print_date($plan[0]['due'], 'day', false, $ol))."\n";
			} else {
				$body .= $ol->transnoentities('HvMailAmount', $amount, $due)."\n";
			}
			$body .= $ol->transnoentities('HvMailBank', $holder, $iban, $f->ref)."\n\n";
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

	protected function log(User $user, Facture $f, $to, $files, $status, $error)
	{
		global $conf;
		$this->db->query("INSERT INTO ".$this->db->prefix()."hv_mail_log (entity, fk_invoice, sendto, files, status, error, datec, fk_user) VALUES (".((int) $conf->entity).", ".((int) $f->id).", '".$this->db->escape(implode(', ', $to))."', '".$this->db->escape(implode(', ', $files))."', '".$this->db->escape($status)."', '".$this->db->escape($error)."', '".$this->db->idate(dol_now())."', ".((int) $user->id).")");
	}
}
