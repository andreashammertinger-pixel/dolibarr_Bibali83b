<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvsettlement.class.php');
dol_include_once('/hausverwaltung/core/modules/facture/doc/pdf_hausverwaltung.modules.php');
dol_include_once('/hausverwaltung/class/hvmailer.class.php');

/**
 * Abrechnungsblatt (Jahresabrechnung Betriebskosten) je Einheit.
 * Original in der Originalsprache (hr_HR), Kopie in der Sprache des Eigentümers mit Hinweis "Übersetzung".
 * Ablage: <documents>/hausverwaltung/statements/<Jahr>/ABR-<Jahr>-<Einheit>[_<lang>].pdf
 */
class HvStatementPdf
{
	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public static function dir($year)
	{
		global $conf;
		$base = !empty($conf->hausverwaltung->multidir_output[$conf->entity]) ? $conf->hausverwaltung->multidir_output[$conf->entity] : (!empty($conf->hausverwaltung->dir_output) ? $conf->hausverwaltung->dir_output : DOL_DATA_ROOT.'/hausverwaltung');
		return $base.'/statements/'.((int) $year);
	}

	public static function fileName($year, $unitRef, $lang = '')
	{
		return 'ABR-'.((int) $year).'-'.dol_sanitizeFileName($unitRef).($lang ? '_'.$lang : '').'.pdf';
	}

	/** Vorhandene Dateien einer Einheit (Original zuerst) */
	public static function files($year, $unitRef)
	{
		$d = self::dir($year);
		$out = array();
		if (is_readable($d.'/'.self::fileName($year, $unitRef))) {
			$out[] = $d.'/'.self::fileName($year, $unitRef);
		}
		foreach (glob($d.'/ABR-'.((int) $year).'-'.dol_sanitizeFileName($unitRef).'_??_??.pdf') ?: array() as $f) {
			$out[] = $f;
		}
		return $out;
	}

	/**
	 * Original + ggf. Kopie erzeugen.
	 *
	 * @return string[] erzeugte Dateien
	 */
	public function generate($year, $unitId)
	{
		global $conf;
		$st = new HvSettlement($this->db);
		$d = $st->detail($year, $unitId);
		if (!$d || empty($d['unit']->fk_soc)) {
			$this->error = 'no data';
			return array();
		}
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$soc = new Societe($this->db);
		$soc->fetch((int) $d['unit']->fk_soc);
		$dir = self::dir($year);
		dol_mkdir($dir);
		foreach (glob($dir.'/ABR-'.((int) $year).'-'.dol_sanitizeFileName($d['unit']->ref).'*.pdf') ?: array() as $old) {
			dol_delete_file($old, 0, 0, 0, null, false, 0);
		}
		$files = array();
		$f = $dir.'/'.self::fileName($year, $d['unit']->ref);
		if ($this->write($d, $soc, $year, $orig, false, $f)) {
			$files[] = $f;
		}
		$ol = (string) $soc->default_lang;
		if ($ol !== '' && substr($ol, 0, 2) !== substr($orig, 0, 2)) {
			$f2 = $dir.'/'.self::fileName($year, $d['unit']->ref, $ol);
			if ($this->write($d, $soc, $year, $ol, true, $f2)) {
				$files[] = $f2;
			}
		}
		// Kopie in die Dokumente des Apartments (Geschäftspartner): sichtbar für den Eigentümer-Zugang (externe Benutzer)
		if ($files && getDolGlobalString('HAUSVERWALTUNG_STMT_TO_THIRDPARTY', '1') === '1') {
			$sd = self::thirdpartyDir((int) $soc->id);
			dol_mkdir($sd);
			foreach (glob($sd.'/ABR-'.((int) $year).'-'.dol_sanitizeFileName($d['unit']->ref).'*.pdf') ?: array() as $old) {
				dol_delete_file($old, 0, 0, 0, null, false, 0);
			}
			foreach ($files as $fl) {
				dol_copy($fl, $sd.'/'.basename($fl), '0', 1);
			}
		}
		return $files;
	}

	/** Dokumentordner des Geschäftspartners */
	public static function thirdpartyDir($socid)
	{
		global $conf;
		$base = !empty($conf->societe->multidir_output[$conf->entity]) ? $conf->societe->multidir_output[$conf->entity] : $conf->societe->dir_output;
		// Standard: direkt im Dokumentordner (der Reiter „Dokumente“ zeigt keine Unterordner)
		$sub = getDolGlobalString('HAUSVERWALTUNG_STMT_SUBDIR', '');
		return $base.'/'.((int) $socid).($sub !== '' ? '/'.dol_sanitizeFileName($sub) : '');
	}

	protected function write($d, Societe $soc, $year, $lang, $isCopy, $file)
	{
		global $conf, $mysoc;
		$T = new Translate('', $conf);
		$T->setDefaultLang($lang);
		$T->loadLangs(array('main', 'dict', 'companies', 'bills', 'banks', 'hausverwaltung@hausverwaltung'));
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$helper = new pdf_hausverwaltung($this->db);
		$fmt = pdf_getFormat();
		$pw = $fmt['width'];
		$ph = $fmt['height'];
		$pdf = pdf_getInstance(array($pw, $ph));
		$fs = (int) getDolGlobalString('MAIN_PDF_FORCE_FONT_SIZE', '10'); // gleiche Schriftgröße in allen Sprachen
		$pdf->SetAutoPageBreak(1, 25);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(getDolGlobalString('HAUSVERWALTUNG_PDF_FONT', 'dejavusans'));
		$L = 18;
		$R = $pw - 15;
		$W = $R - $L;
		$mid = $L + $W * 0.55;
		$top = 15;
		$dark = array(46, 90, 63);
		$pdf->SetMargins($L, $top, 15);
		$pdf->SetTitle($this->docTitle($T, $d, $year));
		$pdf->AddPage();
		$fm = function ($v) use ($T, $conf) {
			return price($v, 0, $T, 1, 2, 2, $conf->currency);
		};

		// Kopf
		$pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetFont('', 'B', $fs + 8);
		$pdf->SetXY($mid, $top);
		$pdf->Cell($R - $mid, 10, $this->titleShort($T), 0, 1, 'R');
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $fs - 1);
		$y = $top + 12;
		foreach ($this->headRows($T, $d, $year) as $r) {
			$pdf->SetXY($mid, $y);
			$pdf->Cell(($R - $mid) * 0.5, 5, $r[0], 0, 0, 'R');
			$pdf->Cell(($R - $mid) * 0.5, 5, $r[1], 0, 1, 'R');
			$y += 5;
		}
		if ($isCopy) {
			$O = new Translate('', $conf);
			$O->setDefaultLang($orig);
			$O->loadLangs(array('hausverwaltung@hausverwaltung'));
			$pdf->SetFillColor(253, 236, 200);
			$pdf->SetTextColor(120, 70, 0);
			$pdf->SetFont('', 'B', $fs - 1);
			$pdf->SetXY($L, $top + 2);
			$pdf->Cell($mid - $L - 6, 6, $T->transnoentities('HvPdfCopy'), 0, 2, 'L', 1);
			$pdf->SetFont('', '', $fs - 3);
			$pdf->MultiCell($mid - $L - 6, 3.8, $T->transnoentities('HvPdfCopyNote')."\n".$O->transnoentities('HvPdfCopyNote'), 0, 'L', 1);
			$pdf->SetTextColor(0, 0, 0);
		}

		// An / Von
		$yA = 58;
		$pdf->SetFont('', 'B', $fs - 1);
		$pdf->SetXY($L, $yA - 6);
		$pdf->Cell(80, 5, $T->transnoentities('HvPdfTo'), 0, 0, 'L');
		$pdf->SetXY($mid, $yA - 6);
		$pdf->Cell($R - $mid, 5, $T->transnoentities('HvPdfFrom'), 0, 0, 'L');
		$inv = new HvInvoicer($this->db);
		$contacts = array();
		foreach ($inv->ownerContacts((int) $soc->id) as $cid) {
			$c = new Contact($this->db);
			if ($c->fetch($cid) > 0) {
				$contacts[] = $c;
			}
		}
		$toLines = array();
		$info = array();
		$multi = count($contacts) > 1;
		foreach ($contacts as $c) {
			$n = $c->getFullName($T, 1);
			$toLines[] = $n;
			$oib = hvOib($c);
			if ($multi) {
				$info[] = $n.':';
			}
			if ($oib) {
				$info[] = ($multi ? '   ' : '').'OIB: '.$oib;
			}
			if (!empty($c->email)) {
				$info[] = ($multi ? '   ' : '').$T->transnoentities('HvPdfWEmail').': '.$c->email;
			}
		}
		if (!$contacts) {
			$toLines[] = $soc->name;
		}
		$src = ($contacts && (!empty($contacts[0]->address) || !empty($contacts[0]->town))) ? $contacts[0] : $soc;
		$pdf->SetFont('', '', $fs);
		$pdf->SetXY($L, $yA);
		$pdf->MultiCell(85, 5, implode("\n", $toLines)."\n".$helper->addressLines($src), 0, 'L');
		if ($info) {
			$pdf->SetFont('', '', $fs - 2);
			$pdf->SetXY($L, $pdf->GetY() + 8);
			$pdf->MultiCell(85, 4.2, implode("\n", $info), 0, 'L');
		}
		$yTo = $pdf->GetY();
		$oib = hvOib($mysoc);
		$regno = trim((string) ($mysoc->idprof2 ?? ''));
		$from = $mysoc->name."\n".($oib ? 'OIB: '.$oib."\n" : '').($regno !== '' ? $T->transnoentities('HvPdfRegNo').': '.$regno."\n" : '').$helper->addressLines($mysoc);
		$mgr = $helper->manager();
		if ($mgr) {
			$mo = hvOib($mgr);
			$from .= "\n\n".$T->transnoentities('HvPdfManagedBy').":\n".$mgr->name.($mo ? "\nOIB: ".$mo : '').(!empty($mgr->email) ? "\n".$T->transnoentities('HvPdfWEmail').': '.$mgr->email : '');
		}
		$pdf->SetFont('', '', $fs - 2);
		$pdf->SetXY($mid, $yA);
		$pdf->MultiCell($R - $mid, 4.2, $from, 0, 'L');
		$y = max(110, $pdf->GetY() + 6, $yTo + 6);

		$this->body($pdf, $T, $d, $soc, $year, $lang, compact('L', 'R', 'W', 'mid', 'top', 'dark', 'fs', 'fm', 'orig', 'helper', 'ph', 'pw', 'y'));

		// Fußzeile (ohne automatischen Seitenumbruch auf die letzte Seite)
		$pdf->SetAutoPageBreak(false, 0);
		if (getDolGlobalString('HAUSVERWALTUNG_PDF_DIGITAL_NOTE', '1') === '1') {
			$pdf->SetFont('', 'I', $fs - 2);
			$pdf->SetTextColor(90, 90, 90);
			$pdf->SetXY($L, $ph - 12 - 12.5);
			$pdf->Cell($W, 4.5, $T->transnoentities('HvPdfDigitalNote'), 0, 0, 'C');
		}
		$parts = array($mysoc->name);
		foreach (array($mysoc->url, $mysoc->email) as $p) {
			if (!empty($p)) {
				$parts[] = $p;
			}
		}
		if ($oib) {
			$parts[] = 'OIB '.$oib;
		}
		$pdf->SetDrawColor($dark[0], $dark[1], $dark[2]);
		$pdf->Line($L, $ph - 12 - 7, $R, $ph - 12 - 7);
		$pdf->SetFont('', '', $fs - 3);
		$pdf->SetTextColor(90, 90, 90);
		$pdf->SetXY($L, $ph - 12 - 6);
		$pdf->Cell($W, 4, implode('  |  ', $parts), 0, 0, 'C');
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);
		return is_readable($file);
	}

	protected function docTitle($T, $d, $year)
	{
		return $T->transnoentities('HvStmtTitle', $year);
	}

	protected function titleShort($T)
	{
		return $T->transnoentities('HvStmtTitleShort');
	}

	protected function headRows($T, $d, $year)
	{
		return array(array($T->transnoentities('HvPdfWDate').':', dol_print_date(dol_now(), 'day', false, $T, true)), array($T->transnoentities('HvStmtPeriod').':', '01.01.–31.12.'.$year), array($T->transnoentities('HvPdfWRef').':', 'ABR-'.$year.'-'.$d['unit']->ref));
	}

	/** Inhalt zwischen Adressblock und Fußzeile */
	protected function body($pdf, $T, $d, $soc, $year, $lang, $ctx)
	{
		global $conf, $mysoc;
		extract($ctx);
		// Betreff
		$pdf->SetFont('', 'B', $fs + 1);
		$pdf->SetXY($L, $y);
		$pdf->Cell($W, 6, $T->transnoentities('HvStmtTitle', $year).' – '.$mysoc->name, 0, 1, 'L');
		$pdf->SetFont('', 'B', $fs);
		$pdf->SetX($L);
		$pdf->Cell($W, 5.5, $T->transnoentities('HvUnit').' '.$d['unit']->ref.'   ·   '.price($d['unit']->area, 0, $T, 1, 2, 2).' m²   ·   '.hvBuildingName($d['building'], $T), 0, 1, 'L');
		$pdf->SetFont('', '', $fs - 1);
		$pdf->SetX($L);
		$pdf->MultiCell($W, 4.5, $T->transnoentities('HvStmtIntro', $year), 0, 'L');
		$y = $pdf->GetY() + 3;

		// Tabelle Kostenarten
		$cols = array(array('HvStmtColItem', 0.40, 'L'), array('HvStmtScope', 0.20, 'L'), array('HvStmtTotal', 0.15, 'R'), array('HvStmtShare', 0.10, 'R'), array('HvStmtYourShare', 0.15, 'R'));
		$pdf->SetFillColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('', 'B', $fs - 2);
		$pdf->SetXY($L, $y);
		foreach ($cols as $c) {
			$pdf->Cell($W * $c[1], 7, $T->transnoentities($c[0]), 0, 0, $c[2], 1);
		}
		$pdf->Ln();
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $fs - 2);
		$i = 0;
		$calc = new HvCalculator($this->db);
		foreach ($d['lines'] as $ln) {
			$c = $ln['costitem'];
			$scopeB = (int) $c->fk_building ? ($calc->buildings[(int) $c->fk_building] ?? null) : null;
			$scope = hvBuildingName($scopeB, $T).' · '.$T->transnoentities('HvKey_'.$ln['key']);
			$vals = array(hvI18n($c, 'label', $lang), $scope, $fm($ln['total']), price($ln['pct'], 0, $T, 1, 2, 2).' %', $fm($ln['amount']));
			$pdf->SetFillColor(240, 244, 241);
			$fill = ($i++ % 2) ? 1 : 0;
			$yl = $pdf->GetY();
			$pdf->SetX($L);
			$pdf->MultiCell($W * $cols[0][1], 5.5, $vals[0], 0, 'L', $fill, 1, '', '', true, 0, false, true, 0, 'T');
			$h = $pdf->GetY() - $yl;
			$x = $L + $W * $cols[0][1];
			for ($k = 1; $k < count($cols); $k++) {
				$pdf->SetXY($x, $yl);
				$pdf->Cell($W * $cols[$k][1], $h, $vals[$k], 0, 0, $cols[$k][2], $fill, '', 1, false, 'T', 'T');
				$x += $W * $cols[$k][1];
			}
			$pdf->SetY($yl + $h);
		}
		if (!$d['lines']) {
			$pdf->SetX($L);
			$pdf->Cell($W, 6, $T->transnoentities('HvStmtNoCosts'), 0, 1, 'L');
		}
		$pdf->SetDrawColor($dark[0], $dark[1], $dark[2]);
		$pdf->Line($L, $pdf->GetY(), $R, $pdf->GetY());

		// Ergebnis
		$y = $pdf->GetY() + 3;
		$tw = $W * 0.55;
		$tx = $R - $tw;
		$sum = array(array('HvStmtYourCosts', $d['actual'], ''), array('HvStmtPrepaid', $d['prepaid'], ''));
		foreach ($sum as $s) {
			$pdf->SetFont('', '', $fs - 1);
			$pdf->SetXY($tx, $y);
			$pdf->Cell($tw * 0.7, 6, $T->transnoentities($s[0], $year), 0, 0, 'L');
			$pdf->Cell($tw * 0.3, 6, $fm($s[1]), 0, 1, 'R');
			$y += 6;
		}
		$pdf->Line($tx, $y, $R, $y);
		$saldo = (float) $d['saldo'];
		$pdf->SetFont('', 'B', $fs + 1);
		$pdf->SetXY($tx, $y + 1);
		$pdf->Cell($tw * 0.7, 7, $T->transnoentities($saldo >= 0 ? 'HvStmtCredit' : 'HvStmtAdditional'), 0, 0, 'L');
		$pdf->Cell($tw * 0.3, 7, $fm(abs($saldo)), 0, 1, 'R');
		$y = $pdf->GetY() + 3;

		// Erläuterung Saldo
		$pdf->SetFont('', '', $fs - 1);
		$pdf->SetXY($L, $y);
		if (abs($saldo) < 0.005) {
			$txt = $T->transnoentities('HvStmtBalanced');
		} elseif ($saldo > 0) {
			$txt = $T->transnoentities('HvStmtCreditText');
		} else {
			$txt = $T->transnoentities('HvStmtAdditionalText');
			if (!empty($d['booked']->fk_invoice)) {
				$fi = new Facture($this->db);
				if ($fi->fetch((int) $d['booked']->fk_invoice) > 0) {
					$acc = new Account($this->db);
					$iban = (!empty($fi->fk_account) && $acc->fetch($fi->fk_account) > 0) ? HvMailer::formatIban($acc->iban) : '';
					$txt = $T->transnoentities('HvStmtAdditionalInvoice', $fi->ref, dol_print_date($fi->date_lim_reglement, 'day', false, $T), $iban);
				}
			}
		}
		$pdf->MultiCell($W, 4.8, $txt, 0, 'L');
		$y = $pdf->GetY() + 5;

		// Rücklage (Information)
		$pdf->SetFont('', 'B', $fs - 1);
		$pdf->SetXY($L, $y);
		$pdf->Cell($W, 5, $T->transnoentities('HvStmtReserveTitle', hvBuildingName($d['building'], $T)), 0, 1, 'L');
		$pdf->SetFont('', '', $fs - 2);
		$bal = null;
		if ($d['building'] && !empty($d['building']->fk_bank_reserve)) {
			$acc = new Account($this->db);
			if ($acc->fetch($d['building']->fk_bank_reserve) > 0) {
				$bal = (float) $acc->solde(1);
			}
		}
		$pdf->SetX($L);
		$pdf->MultiCell($W, 4.5, $T->transnoentities('HvStmtReserveText', $year, $fm($d['reserve'])).($bal !== null ? ' '.$T->transnoentities('HvStmtReserveBalance', $fm($bal)) : ''), 0, 'L');
	}
}
