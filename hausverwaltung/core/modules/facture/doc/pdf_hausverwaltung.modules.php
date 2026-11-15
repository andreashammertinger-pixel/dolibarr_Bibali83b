<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

/**
 * PDF-Vorlage "Vorschreibung" – Layout nach Excel-Blatt "Rechnung" (Bibali83B_Betriebskosten_Template.xlsx).
 * Sprache = Sprache des Eigentümers (Geschäftspartner), wird von generateDocument() übergeben.
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');

class pdf_hausverwaltung extends ModelePDFFactures
{
	public $db;
	public $name;
	public $description;
	public $type;
	public $page_largeur;
	public $page_hauteur;
	public $format;
	public $marge_gauche;
	public $marge_droite;
	public $marge_haute;
	public $marge_basse;
	public $emetteur;
	/** @var string '' = Original, sonst Sprachcode der Übersetzungskopie */
	public $copylang = '';

	/** Sprache des Originals (Kroatien: hr_HR) */
	public static function originalLang()
	{
		return getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
	}

	public function __construct($db)
	{
		global $langs, $mysoc;
		$langs->loadLangs(array('main', 'bills'));
		$this->db = $db;
		$this->name = 'hausverwaltung';
		$this->description = 'Vorschreibung Hausverwaltung (Layout Bibali 83b)';
		$this->update_main_doc_field = 1;
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = 18;
		$this->marge_droite = 15;
		$this->marge_haute = 15;
		$this->marge_basse = 12;
		$this->option_logo = 1;
		$this->option_multilang = 1;
		$this->option_draft_watermark = 1;
		$this->emetteur = $mysoc;
	}

	/**
	 * @param Facture   $object
	 * @param Translate $outputlangs
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs, $mysoc;

		$object->fetch_thirdparty();
		$orig = self::originalLang();
		if ($this->copylang === '') {
			// Original: immer in der Originalsprache, unabhängig von der Auswahl beim Erzeugen
			$outputlangs = new Translate('', $conf);
			$outputlangs->setDefaultLang($orig);
		} elseif (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'dict', 'companies', 'bills', 'banks', 'hausverwaltung@hausverwaltung'));
		$this->emetteur = $mysoc;

		$object->fetch_thirdparty();
		if (empty($object->lines)) {
			$object->fetch_lines();
		}
		$objectref = dol_sanitizeFileName($object->ref);
		$dir = $conf->facture->multidir_output[$object->entity ?? $conf->entity].'/'.$objectref;
		$file = $dir.'/'.$objectref.($this->copylang ? '_'.$this->copylang : '').'.pdf';
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $outputlangs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		$pdf = pdf_getInstance($this->format);
		$fs = pdf_getPDFFontSize($outputlangs);
		$pdf->SetAutoPageBreak(1, 0);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		// Unicode-Schrift: č ć đ (hr), č (sl) und Kyrillisch (uk) – die Standard-Helvetica kann diese nicht
		$pdf->SetFont(getDolGlobalString('HAUSVERWALTUNG_PDF_FONT', 'dejavusans'));
		$pdf->Open();
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('HvPdfTitle'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($mysoc->name));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->AddPage();
		if (empty($object->array_options)) {
			$object->fetch_optionals();
		}
		$hvperiod = (string) ($object->array_options['options_hv_period'] ?? '');
		$isSettlement = (bool) preg_match('/^(\d{4})-A$/', $hvperiod, $sm);
		$titleKey = $isSettlement ? 'HvPdfTitleSettlement' : 'HvPdfTitle';

		$L = $this->marge_gauche;
		$W = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$R = $this->page_largeur - $this->marge_droite;
		$mid = $L + $W * 0.55;
		$dark = array(46, 90, 63);

		// --- Logo (Firma der Gemeinschaft)
		$logo = '';
		if (!empty($mysoc->logo)) {
			$logodir = !empty($conf->mycompany->multidir_output[$object->entity ?? $conf->entity]) ? $conf->mycompany->multidir_output[$object->entity ?? $conf->entity] : $conf->mycompany->dir_output;
			$logo = $logodir.'/logos/'.$mysoc->logo;
		}
		if ($logo && is_readable($logo)) {
			$h = min(18, pdf_getHeightForLogo($logo));
			$pdf->Image($logo, $L, $this->marge_haute, 0, $h);
		}

		// --- Titel "Vorschreibung" + Datum / Fälligkeit (rechts)
		$pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetFont('', 'B', $fs + 10);
		$pdf->SetXY($mid, $this->marge_haute);
		$pdf->Cell($R - $mid, 10, $outputlangs->transnoentities($titleKey), 0, 1, 'R');
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $fs - 1);
		$y = $this->marge_haute + 12;
		$rows = array(
			array($outputlangs->transnoentities('Date').':', dol_print_date($object->date, 'day', false, $outputlangs, true)),
			array($outputlangs->transnoentities('DateDue').':', dol_print_date($object->date_lim_reglement, 'day', false, $outputlangs, true)),
			array($outputlangs->transnoentities('Ref').':', $object->ref),
		);
		foreach ($rows as $r) {
			$pdf->SetXY($mid, $y);
			$pdf->Cell(($R - $mid) * 0.5, 5, $r[0], 0, 0, 'R');
			$pdf->Cell(($R - $mid) * 0.5, 5, $r[1], 0, 1, 'R');
			$y += 5;
		}
		$dueDay = getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5);
		if (!$isSettlement) {
			$pdf->SetFont('', 'I', $fs - 2);
			$pdf->SetXY($mid, $y);
			$pdf->Cell($R - $mid, 5, $outputlangs->transnoentities('HvPdfDueMonthly', $dueDay), 0, 1, 'R');
		}

		// Kennzeichnung der Übersetzungskopie: oben links, neben Titel/Datum (unter dem Logo, falls vorhanden)
		if ($this->copylang) {
			$origlangs = new Translate('', $conf);
			$origlangs->setDefaultLang($orig);
			$origlangs->loadLangs(array('hausverwaltung@hausverwaltung'));
			$bw = $mid - $L - 6;
			$by = ($logo && is_readable($logo)) ? $this->marge_haute + 20 : $this->marge_haute + 2;
			$pdf->SetFillColor(253, 236, 200);
			$pdf->SetTextColor(120, 70, 0);
			$pdf->SetFont('', 'B', $fs - 1);
			$pdf->SetXY($L, $by);
			$pdf->Cell($bw, 6, $outputlangs->transnoentities('HvPdfCopy'), 0, 2, 'L', 1);
			$pdf->SetFont('', '', $fs - 3);
			$pdf->MultiCell($bw, 3.8, $outputlangs->transnoentities('HvPdfCopyNote')."\n".$origlangs->transnoentities('HvPdfCopyNote'), 0, 'L', 1);
			$pdf->SetTextColor(0, 0, 0);
		}

		// --- Adressblöcke "An" (links) / "Von" (rechts)
		$yAddr = 58;
		$pdf->SetFont('', 'B', $fs - 1);
		$pdf->SetXY($L, $yAddr - 6);
		$pdf->Cell(80, 5, $outputlangs->transnoentities('HvPdfTo'), 0, 0, 'L');
		$pdf->SetXY($mid, $yAddr - 6);
		$pdf->Cell($R - $mid, 5, $outputlangs->transnoentities('HvPdfFrom'), 0, 0, 'L');

		// Empfänger: alle Rechnungskontakte (Eigentümer) mit OIB; Adresse vom ersten. Ohne Kontakt: Geschäftspartner.
		$contacts = array();
		foreach ($object->getIdContact('external', 'BILLING') as $cid) {
			require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
			$c = new Contact($this->db);
			if ($c->fetch($cid) > 0) {
				$contacts[] = $c;
			}
		}
		// Postanschrift (nur postrelevante Angaben), darunter mit Abstand OIB + E-Mail je Eigentümer
		$toLines = array();
		$infoLines = array();
		$multi = count($contacts) > 1;
		if ($contacts) {
			foreach ($contacts as $c) {
				$name = $c->getFullName($outputlangs, 1);
				$toLines[] = $name;
				$oib = hvOib($c) ?: (!$multi ? hvOib($object->thirdparty) : '');
				$mail = !empty($c->email) ? $c->email : (!$multi ? (string) $object->thirdparty->email : '');
				if ($multi) {
					$infoLines[] = $name.':';
				}
				if ($oib) {
					$infoLines[] = ($multi ? '   ' : '').'OIB: '.$oib;
				}
				if ($mail) {
					$infoLines[] = ($multi ? '   ' : '').$outputlangs->transnoentities('Email').': '.$mail;
				}
			}
			$src = (!empty($contacts[0]->address) || !empty($contacts[0]->town)) ? $contacts[0] : $object->thirdparty;
		} else {
			$toLines[] = $object->thirdparty->name;
			$oib = hvOib($object->thirdparty);
			if ($oib) {
				$infoLines[] = 'OIB: '.$oib;
			}
			if (!empty($object->thirdparty->email)) {
				$infoLines[] = $outputlangs->transnoentities('Email').': '.$object->thirdparty->email;
			}
			$src = $object->thirdparty;
		}
		$pdf->SetFont('', '', $fs);
		$pdf->SetXY($L, $yAddr);
		$pdf->MultiCell(85, 5, $outputlangs->convToOutputCharset(implode("\n", $toLines)."\n".$this->addressLines($src)), 0, 'L');
		if ($infoLines) {
			$pdf->SetFont('', '', $fs - 2);
			$pdf->SetXY($L, $pdf->GetY() + 8);
			$pdf->MultiCell(85, 4.2, $outputlangs->convToOutputCharset(implode("\n", $infoLines)), 0, 'L');
		}
		$yAfterTo = $pdf->GetY();

		// Absender: Gemeinschaft (Name, OIB, Adresse, Kontakt) + Verwalter (Name, OIB, Kontakt)
		$oib = hvOib($mysoc);
		$from = $mysoc->name."\n".($oib ? "OIB: ".$oib."\n" : '').$this->addressLines($mysoc)."\n";
		if (!empty($mysoc->email)) {
			$from .= "\n".$outputlangs->transnoentities('Email').': '.$mysoc->email;
		}
		if (!empty($mysoc->url)) {
			$from .= "\n".$outputlangs->transnoentities('Web').': '.$mysoc->url;
		}
		$mgr = $this->manager();
		if ($mgr) {
			$mgroib = hvOib($mgr);
			$from .= "\n\n".$outputlangs->transnoentities('HvPdfManagedBy').":\n".$mgr->name.($mgroib ? "\nOIB: ".$mgroib : '');
			if (!empty($mgr->email)) {
				$from .= "\n".$outputlangs->transnoentities('Email').': '.$mgr->email;
			}
			if (!empty($mgr->url)) {
				$from .= "\n".$outputlangs->transnoentities('Web').': '.$mgr->url;
			}
		}
		$pdf->SetFont('', '', $fs - 2);
		$pdf->SetXY($mid, $yAddr);
		$pdf->MultiCell($R - $mid, 4.2, $outputlangs->convToOutputCharset($from), 0, 'L');
		$yAfterFrom = $pdf->GetY();

		// --- Betreff
		$y = max(110, $yAfterFrom + 6, $yAfterTo + 6);
		$period = $object->array_options['options_hv_period'] ?? '';
		$plabel = $period ? substr($period, 5, 2).'/'.substr($period, 0, 4) : dol_print_date($object->date, '%m/%Y');
		$unitInfo = $this->unitInfo($object);
		$pdf->SetFont('', 'B', $fs + 1);
		$pdf->SetXY($L, $y);
		$pdf->Cell($W, 6, $outputlangs->transnoentities($isSettlement ? 'HvPdfSubjectSettlement' : 'HvPdfSubject', $mysoc->name), 0, 1, 'L');
		if ($unitInfo) {
			$pdf->SetFont('', 'B', $fs);
			$pdf->SetX($L);
			$pdf->Cell($W, 5.5, $outputlangs->transnoentities('HvUnit').' '.$unitInfo['ref'].'   ·   '.price($unitInfo['area'], 0, $outputlangs, 1, 2, 2).' m²', 0, 1, 'L');
		}
		$pdf->SetFont('', '', $fs - 1);
		$pdf->SetX($L);
		$pdf->Cell($W, 5, $isSettlement ? $outputlangs->transnoentities('HvPdfSublineSettlement', $sm[1], dol_print_date($object->date_lim_reglement, 'day', false, $outputlangs, true)) : $outputlangs->transnoentities('HvPdfSubline', $plabel, $dueDay), 0, 1, 'L');
		$y = $pdf->GetY() + 4;

		// --- Positionstabelle
		$showVat = false;
		foreach ($object->lines as $l) {
			if ((float) $l->tva_tx != 0.0) {
				$showVat = true;
			}
		}
		$cols = $showVat
			? array(array('Description', 0.45, 'L'), array('VAT', 0.10, 'R'), array('PriceUHT', 0.15, 'R'), array('Qty', 0.13, 'R'), array('TotalHT', 0.17, 'R'))
			: array(array('Description', 0.53, 'L'), array('HvPdfRatePerM2', 0.16, 'R'), array('HvPdfM2', 0.14, 'R'), array('Total', 0.17, 'R'));
		$pdf->SetFillColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('', 'B', $fs - 1);
		$pdf->SetXY($L, $y);
		foreach ($cols as $c) {
			$pdf->Cell($W * $c[1], 7, $outputlangs->transnoentities($c[0]), 0, 0, $c[2], 1);
		}
		$pdf->Ln();
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $fs - 1);
		$i = 0;
		$lineText = $this->copylang ? $this->translatedLineTexts($object, $outputlangs) : array();
		$kpd = $this->kpdCodes($object);
		foreach ($object->lines as $l) {
			$desc = $lineText[(int) $l->fk_product] ?? ($l->desc ?: $l->product_label);
			if (!empty($kpd[(int) $l->fk_product])) {
				$desc .= "\n".'KPD '.$kpd[(int) $l->fk_product];
			}
			$vals = $showVat
				? array($desc, vatrate($l->tva_tx, true), price($l->subprice, 0, $outputlangs, 1, 2, 2), (float) $l->qty, price($l->total_ht, 0, $outputlangs, 1, 2, 2))
				: array($desc, price($l->subprice * (1 + $l->tva_tx / 100), 0, $outputlangs, 1, 2, getDolGlobalInt('HAUSVERWALTUNG_RATE_DECIMALS', 2)), price($l->qty, 0, $outputlangs, 1, 2, 2), price($l->total_ttc, 0, $outputlangs, 1, 2, 2));
			if (!$showVat && (float) $l->qty == 1.0 && $unitInfo && (float) $unitInfo['area'] != 1.0) {
				// Pauschalzeile (Guthaben / Nachzahlung): kein €/m² und keine m²
				$vals[1] = '';
				$vals[2] = '';
			}
			$pdf->SetFillColor(240, 244, 241);
			$fill = ($i++ % 2) ? 1 : 0;
			$yl = $pdf->GetY();
			$pdf->SetX($L);
			$pdf->MultiCell($W * $cols[0][1], 6.5, $outputlangs->convToOutputCharset(dol_htmlentitiesbr_decode(strip_tags($desc))), 0, 'L', $fill, 1, '', '', true, 0, false, true, 0, 'T');
			$h = $pdf->GetY() - $yl;
			$x = $L + $W * $cols[0][1];
			for ($k = 1; $k < count($cols); $k++) {
				$pdf->SetXY($x, $yl);
				$pdf->Cell($W * $cols[$k][1], 6.5, $vals[$k], 0, 0, $cols[$k][2], $fill, '', 0, false, 'T', 'T');
				if ($h > 6.5 && $fill) {
					$pdf->SetXY($x, $yl + 6.5);
					$pdf->Cell($W * $cols[$k][1], $h - 6.5, '', 0, 0, 'L', 1);
				}
				$x += $W * $cols[$k][1];
			}
			$pdf->SetY($yl + $h);
		}
		$pdf->SetDrawColor($dark[0], $dark[1], $dark[2]);
		$pdf->Line($L, $pdf->GetY(), $R, $pdf->GetY());

		// --- Summen
		$y = $pdf->GetY() + 3;
		$tw = $W * 0.40;
		$tx = $R - $tw;
		$tot = $showVat
			? array(array('TotalHT', $object->total_ht, ''), array('TotalVAT', $object->total_tva, ''), array('TotalTTC', $object->total_ttc, 'B'))
			: array(array('HvPdfTotal', $object->total_ttc, 'B'));
		foreach ($tot as $t) {
			$pdf->SetFont('', $t[2], $t[2] ? $fs + 1 : $fs - 1);
			$pdf->SetXY($tx, $y);
			$pdf->Cell($tw * 0.55, 6, $outputlangs->transnoentities($t[0]), 0, 0, 'L');
			$pdf->Cell($tw * 0.45, 6, price($t[1], 0, $outputlangs, 1, 2, 2, $conf->currency), 0, 1, 'R');
			$y += 6.5;
		}
		if (!$showVat) {
			$pdf->SetFont('', 'I', $fs - 3);
			$pdf->SetXY($tx, $y);
			$pdf->MultiCell($tw, 4, $outputlangs->transnoentities('HvPdfNoVat'), 0, 'R');
			$y = $pdf->GetY();
		}

		// --- Zahlungsbedingungen / Bankverbindung
		$y = max($y + 8, 200);
		$pdf->SetFont('', 'B', $fs - 1);
		$pdf->SetXY($L, $y);
		$pdf->Cell($W, 5, $outputlangs->transnoentities('HvPdfPaymentTerms'), 0, 1, 'L');
		$pdf->SetFont('', '', $fs - 1);
		$pdf->SetX($L);
		$pdf->Cell($W, 5, $outputlangs->transnoentities('HvPdfPayBy'), 0, 1, 'L');
		$acc = null;
		if (!empty($object->fk_account)) {
			$acc = new Account($this->db);
			if ($acc->fetch($object->fk_account) <= 0) {
				$acc = null;
			}
		}
		$bank = array(
			array('HvPdfAccountHolder', $acc && $acc->owner_name ? $acc->owner_name : ($acc && !empty($acc->proprio) ? $acc->proprio : $mysoc->name)),
			array('IBAN', $acc ? $acc->iban : ''),
			array('BIC', $acc ? $acc->bic : ''),
			array('HvPdfPaymentRef', $object->ref),
		);
		foreach ($bank as $b) {
			if ($b[1] === '' || $b[1] === null) {
				continue;
			}
			$pdf->SetX($L + 4);
			$pdf->SetFont('', '', $fs - 1);
			$pdf->Cell(42, 5, $outputlangs->transnoentities($b[0]).':', 0, 0, 'L');
			$pdf->SetFont('', 'B', $fs - 1);
			$pdf->Cell($W - 46, 5, $outputlangs->convToOutputCharset($b[1]), 0, 1, 'L');
		}

		// --- Fußzeile
		$parts = array($mysoc->name);
		foreach (array($mysoc->url, $mysoc->email) as $p) {
			if (!empty($p)) {
				$parts[] = $p;
			}
		}
		if (hvOib($mysoc)) {
			$parts[] = 'OIB '.hvOib($mysoc);
		}
		$pdf->SetDrawColor($dark[0], $dark[1], $dark[2]);
		$pdf->Line($L, $this->page_hauteur - $this->marge_basse - 7, $R, $this->page_hauteur - $this->marge_basse - 7);
		$pdf->SetFont('', '', $fs - 3);
		$pdf->SetTextColor(90, 90, 90);
		$pdf->SetXY($L, $this->page_hauteur - $this->marge_basse - 6);
		$pdf->Cell($W, 4, $outputlangs->convToOutputCharset(implode('  |  ', $parts)), 0, 0, 'C');
		$pdf->SetTextColor(0, 0, 0);

		if ($object->statut == Facture::STATUS_DRAFT) {
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $outputlangs->transnoentities('Draft'));
		}

		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);
		$this->result = array('fullpath' => $file);

		// Original erzeugt -> Übersetzungskopie für nicht-kroatische Eigentümer
		if ($this->copylang === '') {
			$ownerlang = !empty($object->thirdparty->default_lang) ? $object->thirdparty->default_lang : '';
			foreach (glob($dir.'/'.$objectref.'_??_??.pdf') ?: array() as $old) {
				dol_delete_file($old, 0, 0, 0, null, false, 0);
			}
			if ($ownerlang && substr($ownerlang, 0, 2) !== substr($orig, 0, 2)) {
				$copy = new self($this->db);
				$copy->copylang = $ownerlang;
				$cl = new Translate('', $conf);
				$cl->setDefaultLang($ownerlang);
				if ($copy->write_file($object, $cl) <= 0) {
					dol_syslog('pdf_hausverwaltung: copy failed '.$copy->error, LOG_WARNING);
				}
			}
			$this->result = array('fullpath' => $file);
		}
		return 1;
	}

	/**
	 * Positionstexte der Vorschreibung in der Sprache der Kopie neu aufbauen
	 * (gespeichert sind sie in der Originalsprache).
	 *
	 * @return array<int,string> fk_product => Text
	 */
	protected function translatedLineTexts($object, $outputlangs)
	{
		$out = array();
		if (empty($object->array_options)) {
			$object->fetch_optionals();
		}
		$period = $object->array_options['options_hv_period'] ?? '';
		$unitId = (int) ($object->array_options['options_hv_unit'] ?? 0);
		if (!$period || !$unitId) {
			return $out;
		}
		$mlabel = substr($period, 5, 2).'/'.substr($period, 0, 4);
		$sql = "SELECT u.ref as uref, b.ref as bref, b.fk_product_reserve FROM ".$this->db->prefix()."hv_unit u LEFT JOIN ".$this->db->prefix()."hv_building b ON b.rowid = u.fk_building WHERE u.rowid = ".$unitId;
		$res = $this->db->query($sql);
		$o = $res ? $this->db->fetch_object($res) : null;
		if (!$o) {
			return $out;
		}
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$p = new Product($this->db);
		if ($p->fetch(0, 'HV-BK') > 0) {
			$out[(int) $p->id] = $outputlangs->transnoentities('HvLineBk', $mlabel, $o->uref);
		}
		$p = new Product($this->db);
		if ($p->fetch(0, 'HV-VW') > 0) {
			$out[(int) $p->id] = $outputlangs->transnoentities('HvLineAdmin', $mlabel);
		}
		foreach ($object->lines as $l) {
			if (!empty($l->fk_product) && preg_match('/(\d{4})/', (string) $l->desc, $ym)) {
				$pr = new Product($this->db);
				if ($pr->fetch((int) $l->fk_product) > 0 && in_array($pr->ref, array('HV-GUT', 'HV-BKA'), true)) {
					$out[(int) $l->fk_product] = $outputlangs->transnoentities($pr->ref === 'HV-GUT' ? 'HvLineCredit' : 'HvLineSettlement', $ym[1]);
				}
			}
		}
		if (!empty($o->fk_product_reserve)) {
			$out[(int) $o->fk_product_reserve] = $outputlangs->transnoentities('HvLineReserve', $outputlangs->transnoentities('HvBuildingNo', preg_replace('/\D/', '', $o->bref) ?: $o->bref), $mlabel);
		}
		return $out;
	}

	/** Adresszeilen (Straße, PLZ Ort, Land auf Englisch) */
	protected function addressLines($o)
	{
		global $conf;
		$lines = array();
		if (!empty($o->address)) {
			$lines[] = trim($o->address);
		}
		$zt = trim(($o->zip ?? '').' '.($o->town ?? ''));
		if ($zt !== '') {
			$lines[] = $zt;
		}
		$cid = !empty($o->country_id) ? (int) $o->country_id : 0;
		if ($cid > 0) {
			static $en = null;
			if ($en === null) {
				$en = new Translate('', $conf);
				$en->setDefaultLang('en_US');
				$en->loadLangs(array('dict'));
			}
			$lines[] = getCountry($cid, '', $this->db, $en);
		}
		return implode("\n", $lines);
	}

	/** Apartment-Nr. und m² aus der Vorschreibung (Einheit + Menge der Positionen) */
	protected function unitInfo($object)
	{
		if (empty($object->array_options)) {
			$object->fetch_optionals();
		}
		$unitId = (int) ($object->array_options['options_hv_unit'] ?? 0);
		if ($unitId <= 0) {
			return null;
		}
		$res = $this->db->query("SELECT ref FROM ".$this->db->prefix()."hv_unit WHERE rowid = ".$unitId);
		$o = $res ? $this->db->fetch_object($res) : null;
		if (!$o) {
			return null;
		}
		$area = 0;
		dol_include_once('/hausverwaltung/class/hvrepository.class.php');
		$repo = new HvRepository($this->db);
		$units = $repo->getUnits(false);
		if (isset($units[$unitId])) {
			$area = (float) $units[$unitId]->area;
		}
		if ($area <= 0) {
			foreach ($object->lines as $l) {
				$area = max($area, (float) $l->qty);
			}
		}
		return array('ref' => $o->ref, 'area' => $area);
	}

	/** @return array<int,string> fk_product => KPD-Code */
	protected function kpdCodes($object)
	{
		$ids = array();
		foreach ($object->lines as $l) {
			if (!empty($l->fk_product)) {
				$ids[] = (int) $l->fk_product;
			}
		}
		$out = array();
		if (!$ids) {
			return $out;
		}
		$res = $this->db->query("SELECT fk_object, kpd FROM ".$this->db->prefix()."product_extrafields WHERE fk_object IN (".implode(',', $ids).")");
		while ($res && ($o = $this->db->fetch_object($res))) {
			if (!empty($o->kpd)) {
				$out[(int) $o->fk_object] = $o->kpd;
			}
		}
		return $out;
	}

	/** Hausverwalter (Lieferant aus den Moduleinstellungen) */
	protected function manager()
	{
		$id = getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID');
		if ($id <= 0) {
			return null;
		}
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$s = new Societe($this->db);
		return $s->fetch($id) > 0 ? $s : null;
	}
}
