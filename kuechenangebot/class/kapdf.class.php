<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kapdf.class.php
 * \brief   Küchenangebot als PDF im migora-Layout:
 *          Deckblatt (3D-Bild, Highlights, Gesamtpreis), Leistungsumfang, Ausstattung,
 *          Hinweise, Angebotsbestimmungen, AGB-QR-Code, Annahme mit Unterschrift.
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/kuechenangebot/class/kakalk.class.php');
dol_include_once('/kuechenangebot/class/kadict.class.php');
dol_include_once('/kuechenangebot/lib/kuechenangebot.lib.php');

class KaPdf
{
	public $db;
	public $error = '';
	/** @var TCPDF */
	protected $pdf;
	/** @var Translate */
	protected $l;
	protected $lang = 'de_DE';
	protected $color = array(174, 157, 149);
	protected $dark = array(60, 60, 60);
	protected $ml = 15;
	protected $mr = 15;
	protected $pw = 210;
	protected $ph = 297;
	protected $font = 'helvetica';
	/** @var Propal */
	protected $object;
	/** @var KaKalk */
	protected $kalk;
	protected $calc = array();
	/** @var KaKalk|null Vorversion */
	protected $prev = null;
	/** @var Propal|null Angebot der Vorversion */
	protected $prevPropal = null;
	/** @var array{0:float,1:float} Schriftgröße/Zeilenhöhe der Highlights */
	protected $hlFont = array(7.9, 3.7);

	public function __construct($db)
	{
		$this->db = $db;
		$hex = preg_replace('/[^0-9A-Fa-f]/', '', getDolGlobalString('KA_PDF_FARBE', 'AE9D95'));
		if (strlen($hex) === 6) {
			$this->color = array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
		}
	}

	protected function t($key)
	{
		$args = func_get_args();
		array_shift($args);
		$a = array_pad($args, 4, '');
		return $this->l->transnoentitiesnoconv($key, (string) $a[0], (string) $a[1], (string) $a[2], (string) $a[3]);
	}

	/** Nobilia-Begriff in Ausgabesprache */
	protected function tr($s)
	{
		return KaDict::tr((string) $s, $this->lang);
	}

	/**
	 * PDF erzeugen
	 * @param Propal $object
	 * @param KaKalk $kalk
	 * @param string $lang de_DE | hr_HR | en_US
	 * @param string $file Zieldatei
	 * @return int 1 = OK
	 */
	public function build($object, $kalk, $lang, $file)
	{
		global $conf, $mysoc;
		$this->object = $object;
		$this->kalk = $kalk;
		$this->lang = $lang;
		$this->calc = $kalk->compute();
		$this->prev = null;
		$this->prevPropal = null;
		if (!empty($kalk->params['vorgaenger'])) {
			$pk = new KaKalk($this->db);
			if ($pk->fetch((int) $kalk->params['vorgaenger']) > 0) {
				$this->prev = $pk;
				if ($pk->fk_propal) {
					$pp = new Propal($this->db);
					if ($pp->fetch($pk->fk_propal) > 0) {
						$this->prevPropal = $pp;
					}
				}
			}
		}

		$this->l = new Translate('', $conf);
		$this->l->setDefaultLang($lang);
		$this->l->loadLangs(array('main', 'dict', 'companies', 'propal', 'kuechenangebot@kuechenangebot'));

		$pdf = pdf_getInstance(array($this->pw, $this->ph));
		$this->pdf = $pdf;
		$this->font = getDolGlobalString('KA_PDF_FONT', 'dejavusans');
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetAutoPageBreak(true, 22);
		$pdf->SetMargins($this->ml, 36, $this->mr);
		$pdf->SetCreator('Dolibarr '.DOL_VERSION.' / Küchenangebot');
		$pdf->SetAuthor($mysoc->name);
		$pdf->SetTitle($this->t('KaPdfOfferNo').' '.$object->ref);
		$pdf->SetSubject($this->t('KaPdfTitle'));
		$pdf->SetCompression(true);
		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}

		$this->pageCover();
		$this->pageDetails();

		// Kopf/Fuß auf allen Seiten nachträglich
		$n = $pdf->getNumPages();
		for ($i = 1; $i <= $n; $i++) {
			$pdf->setPage($i);
			$pdf->SetAutoPageBreak(false, 0);
			$this->header($i == 1);
			$this->footer($i, $n);
		}
		$pdf->lastPage();
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);
		return 1;
	}

	// ------------------------------------------------------------------
	// Kopf und Fuß
	// ------------------------------------------------------------------

	protected function logoFile()
	{
		global $conf, $mysoc;
		if (empty($mysoc->logo)) {
			return '';
		}
		$dir = $conf->mycompany->dir_output.'/logos/';
		foreach (array($dir.$mysoc->logo, $dir.'thumbs/'.$mysoc->logo_small) as $f) {
			if ($f && is_readable($f) && !is_dir($f)) {
				return $f;
			}
		}
		return '';
	}

	protected function header($first)
	{
		global $mysoc;
		$pdf = $this->pdf;
		$logo = $this->logoFile();
		$h = $first ? 20 : 13;
		if ($logo) {
			$info = @getimagesize($logo);
			$w = ($info && $info[1]) ? $h * $info[0] / $info[1] : 40;
			if ($w > 70) {
				$w = 70;
				$h = $w * $info[1] / $info[0];
			}
			$pdf->Image($logo, $this->ml, 10, $w, $h);
		} else {
			$pdf->SetFont($this->font, 'B', 18);
			$pdf->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
			$pdf->SetXY($this->ml, 10);
			$pdf->Cell(80, 10, $mysoc->name, 0, 0, 'L');
		}
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
		$pdf->SetFont($this->font, '', 7.5);
		$lines = array_filter(array(
			$mysoc->name,
			$mysoc->address,
			trim($mysoc->zip.' '.$mysoc->town),
			$mysoc->phone ? 'Tel. '.$mysoc->phone : '',
			$mysoc->email,
			$mysoc->url,
		));
		$pdf->SetXY(120, 10);
		$pdf->MultiCell($this->pw - $this->mr - 120, 3.4, implode("\n", $lines), 0, 'R');
		$y = 10 + max($h, 3.4 * count($lines)) + 2;
		$pdf->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->SetLineWidth(0.6);
		$pdf->Line($this->ml, $y, $this->pw - $this->mr, $y);
		$pdf->SetLineWidth(0.2);
		if (KaDict::short($this->lang) !== 'de') {
			$pdf->SetFont($this->font, 'I', 7.5);
			$pdf->SetTextColor($this->color[0] * 0.6, $this->color[1] * 0.6, $this->color[2] * 0.6);
			$pdf->SetXY($this->ml, $y + 0.8);
			$pdf->Cell($this->pw - $this->ml - $this->mr, 3.5, $this->t('KaPdfTranslationNote'), 0, 0, 'R');
		}
	}

	protected function footer($page, $total)
	{
		global $mysoc;
		$pdf = $this->pdf;
		$y = $this->ph - 16;
		$pdf->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->Line($this->ml, $y, $this->pw - $this->mr, $y);
		$pdf->SetFont($this->font, '', 6.8);
		$pdf->SetTextColor(110, 110, 110);
		$parts = array($mysoc->name, $mysoc->address, trim($mysoc->zip.' '.$mysoc->town));
		$l2 = array();
		if ($mysoc->tva_intra) {
			$l2[] = ($mysoc->country_code === 'AT' ? 'UID' : $this->l->transnoentitiesnoconv('VATIntraShort')).' '.$mysoc->tva_intra;
		}
		foreach (array(1, 2, 3, 4) as $k) {
			$v = $mysoc->{'idprof'.$k};
			if ($v) {
				$lab = $this->l->transcountrynoentities('ProfId'.$k, $mysoc->country_code);
				$l2[] = ($lab && strpos($lab, 'ProfId') === false ? $lab.' ' : '').$v;
			}
		}
		if (!empty($mysoc->managers)) {
			$l2[] = $this->l->transnoentitiesnoconv('Managers').': '.$mysoc->managers;
		}
		$pdf->SetXY($this->ml, $y + 1.5);
		$pdf->MultiCell(150, 3, implode(' · ', array_filter($parts))."\n".implode(' · ', $l2), 0, 'L');
		$pdf->SetXY($this->pw - $this->mr - 35, $y + 1.5);
		$pdf->Cell(35, 3, $this->t('KaPdfPage', $page, $total), 0, 0, 'R');
	}

	// ------------------------------------------------------------------
	// Deckblatt
	// ------------------------------------------------------------------

	protected function pageCover()
	{
		global $conf;
		$pdf = $this->pdf;
		$o = $this->object;
		$pdf->AddPage();
		$w = $this->pw - $this->ml - $this->mr;

		// Empfänger
		$soc = $o->thirdparty;
		$y = 42;
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
		$pdf->SetFont($this->font, '', 10);
		$addr = array($soc->name);
		if ($soc->address) {
			$addr[] = $soc->address;
		}
		$addr[] = trim($soc->zip.' '.$soc->town);
		if ($soc->country_code && $soc->country_code !== 'AT') {
			$addr[] = $this->l->transnoentitiesnoconv('Country'.$soc->country_code);
		}
		$pdf->SetXY($this->ml, $y);
		$pdf->MultiCell(90, 4.6, implode("\n", array_filter($addr)), 0, 'L');

		// Angebotsdaten
		$valid = $o->fin_validite ?: ($o->date + 86400 * (int) $this->kalk->params['gueltig_tage']);
		$info = array(
			array($this->t('KaPdfOfferNo'), $o->ref),
			array($this->t('KaPdfDate'), $this->date($o->date)),
			array($this->t('KaPdfValidUntil'), $this->date($valid)),
		);
		if (!empty($soc->code_client)) {
			$info[] = array($this->t('KaPdfCustomerNo'), $soc->code_client);
		}
		if ($this->kalk->version() > 1) {
			$vt = (string) $this->kalk->version();
			if ($this->prevPropal) {
				$vt .= ' ('.$this->t('KaPdfVersionReplaces', $this->prevPropal->ref).')';
			}
			$info[] = array($this->t('KaPdfVersion'), $vt);
		}
		if ($this->kalk->label) {
			$info[] = array($this->t('KaPdfProject'), $this->kalk->label);
		}
		$contact = getDolGlobalString('KA_KONTAKT');
		if ($contact) {
			$info[] = array($this->t('KaPdfContact'), $contact);
		}
		$yy = $y;
		foreach ($info as $row) {
			$pdf->SetFont($this->font, '', 8.5);
			$pdf->SetTextColor(120, 120, 120);
			$pdf->SetXY(115, $yy);
			$pdf->Cell(33, 4.4, $row[0], 0, 0, 'L');
			$pdf->SetFont($this->font, 'B', 8.5);
			$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
			$pdf->SetXY(148, $yy);
			$pdf->MultiCell($this->pw - $this->mr - 148, 4.4, $row[1], 0, 'L');
			$yy = max($yy + 4.4, $pdf->GetY());
		}
		$y = max($pdf->GetY(), $yy, 66) + 5;

		// Titel
		$pdf->SetXY($this->ml, $y);
		$pdf->SetFont($this->font, 'B', 18);
		$pdf->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->Cell($w, 8, $this->t('KaPdfTitle'), 0, 1, 'L');
		$pdf->SetFont($this->font, '', 9.5);
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
		$pdf->SetX($this->ml);
		$pdf->MultiCell($w, 4.5, $this->t('KaPdfIntro'), 0, 'L');
		if (!empty($this->kalk->params['verbraucher'])) {
			$pdf->SetX($this->ml);
			$pdf->SetFont($this->font, 'B', 8.8);
			$pdf->MultiCell($w, 4.3, $this->t('KaPdfIndividual'), 0, 'L');
		}
		$y = $pdf->GetY() + 2;

		// 3D-Bild: so groß, dass Highlights und Preis noch auf die Seite passen
		$typ = $this->kalk->typen ? $this->kalk->typen[0] : null;
		$img = $typ ? $this->imageFile($typ) : '';
		$hls = $this->highlights($typ);
		// Schriftgröße und Bildhöhe so wählen, dass Highlights und Preis auf Seite 1 passen
		$avail = $this->ph - 22 - $y - 6;
		$hfs = 7.9;
		$lh = 3.7;
		$minImg = $img ? 40 : 0;
		for ($try = 0; $try < 8; $try++) {
			$pdf->SetFont($this->font, '', $hfs);
			$need = 8;
			foreach ($hls as $hl) {
				$need += max(1, $pdf->getNumLines($hl[1], 135 - $this->ml - 5 - 37)) * $lh + 0.8;
			}
			$need = max($need, 52);
			if ($need + $minImg <= $avail || $hfs <= 6.6) {
				break;
			}
			$hfs -= 0.2;
			$lh = $hfs * 0.47;
		}
		$this->hlFont = array($hfs, $lh);
		if ($img) {
			$size = @getimagesize($img);
			$iw = $w;
			$ih = ($size && $size[0]) ? $iw * $size[1] / $size[0] : 100;
			$maxh = min(100, $avail - $need);
			if ($maxh < $minImg) {
				$maxh = $minImg;
			}
			if ($ih > $maxh) {
				$ih = $maxh;
				$iw = $ih * $size[0] / $size[1];
			}
			$x = $this->ml + ($w - $iw) / 2;
			$pdf->Image($img, $x, $y, $iw, $ih, '', '', '', true, 200);
			$pdf->SetDrawColor(220, 220, 220);
			$pdf->Rect($x, $y, $iw, $ih);
			$y += $ih + 0.8;
			$pdf->SetFont($this->font, 'I', 6.5);
			$pdf->SetTextColor(130, 130, 130);
			$pdf->SetXY($this->ml, $y);
			$pdf->Cell($w, 3, $this->t('KaPdfPicture'), 0, 1, 'R');
			$y += 4;
		}

		// Preisbox rechts
		$bx = 135;
		$bw = $this->pw - $this->mr - $bx;
		$by = $y;
		$c = $this->calc;
		$pdf->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->SetTextColor(255, 255, 255);
		$lines = array();
		$boxh = 44;
		if (count($this->kalk->typen) > 1 || $c['stueck'] > 1) {
			$boxh += 5;
		}
		$pdf->Rect($bx, $by, $bw, $boxh, 'F');
		$pdf->SetXY($bx + 3, $by + 3);
		$pdf->SetFont($this->font, '', 10);
		$pdf->Cell($bw - 6, 5, $this->t('KaPdfTotalPrice'), 0, 2, 'L');
		$pdf->SetFont($this->font, 'B', 19);
		$pdf->Cell($bw - 6, 10, kaMoney($c['vk_brutto']), 0, 2, 'L');
		$pdf->SetFont($this->font, '', 8.5);
		$pdf->MultiCell($bw - 6, 4, $this->t('KaPdfInclDelivery')."\n".$this->t('KaPdfInclVat', $this->num($c['tva'])), 0, 'L');
		$pdf->SetX($bx + 3);
		$pdf->SetFont($this->font, '', 8);
		$pdf->Cell($bw - 6, 4.5, $this->t('KaPdfNetPrice', kaMoney($c['vk_netto'])), 0, 2, 'L');
		if (count($this->kalk->typen) > 1 || $c['stueck'] > 1) {
			$pdf->SetX($bx + 3);
			$pdf->Cell($bw - 6, 4.5, $this->t('KaPdfTypes', $this->num($c['stueck'], 0), count($this->kalk->typen)), 0, 2, 'L');
		}
		$pdf->SetXY($bx + 3, $by + $boxh - 7);
		$pdf->SetFont($this->font, 'B', 8.5);
		$pdf->Cell($bw - 6, 4, 'nobilia', 0, 0, 'L');

		// Highlights links
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
		$hx = $this->ml;
		$hw = $bx - $this->ml - 5;
		$pdf->SetXY($hx, $by);
		$pdf->SetFont($this->font, 'B', 11);
		$pdf->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->Cell($hw, 6, $this->t('KaPdfHighlights'), 0, 1, 'L');
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
		foreach ($hls as $hl) {
			$this->bulletRow($hx, $hw, $hl[0], $hl[1], $this->hlFont[0], $this->hlFont[1]);
		}
	}

	/** Bild der Küche */
	protected function imageFile($typ)
	{
		if (!$typ || !$typ->image) {
			return '';
		}
		$f = $this->kalk->getDir().'/'.$typ->image;
		return is_readable($f) ? $f : '';
	}

	/**
	 * Highlights für das Deckblatt
	 * @return array<int,array{0:string,1:string}>
	 */
	protected function highlights($typ)
	{
		$out = array();
		$out[] = array($this->t('KaPdfManufacturer'), $this->t('KaPdfManufacturerText'));
		if ($typ) {
			$prog = $typ->merkmal('Programm');
			$front = $typ->merkmal('Front-Kombination');
			if ($prog || $front) {
				$out[] = array($this->t('KaPdfFront'), trim(($prog ? 'nobilia '.$prog : '').($front ? ', '.$this->tr($front) : ''), ', '));
			}
			$k = $typ->merkmal('Korpusfarbe außen');
			if ($k) {
				$ka = $typ->merkmal('Korpusausführung');
				$out[] = array($this->t('KaPdfCorpus'), $this->tr($k).($ka ? ' ('.$this->tr($ka).')' : ''));
			}
			$a = $typ->merkmal('Arbeitsplatten-Farbe');
			if ($a) {
				$aa = $typ->merkmal('Arbeitsplatten-Ausführung');
				$out[] = array($this->t('KaPdfWorktop'), $this->tr($a).($aa ? ', '.$this->tr($aa) : ''));
			}
			if ($this->hasPosition($typ, '/^Nischenwandverkleidung|^Nischenrückwand/u')) {
				$n = $typ->merkmal('Nischenfarbe');
				if ($n) {
					$out[] = array($this->t('KaPdfNiche'), $this->tr($n));
				}
			}
			$g = $typ->merkmal('Griff-Kombination');
			if ($g) {
				$out[] = array($this->t('KaPdfHandles'), $this->tr($g));
			}
			$dev = array();
			$sink = array();
			foreach ((array) ($typ->data['geraete'] ?? array()) as $d) {
				$name = $this->tr($d['name']);
				if (in_array($d['kategorie'], array('spuele', 'armatur'))) {
					$sink[] = $this->deviceShort($name, $d);
				} elseif ($d['kategorie'] !== 'sonstiges' && !preg_match('/Aktivkohlefilter/u', $d['name'])) {
					$dev[] = $name.($d['eek'] && $d['kategorie'] !== 'licht' ? ' · EEK '.$d['eek'] : '');
				}
			}
			if ($dev) {
				$out[] = array($this->t('KaPdfDevices'), implode("\n", $dev));
			}
			if ($sink) {
				$out[] = array($this->t('KaPdfSink'), implode("\n", $sink));
			}
		}
		$out[] = array($this->t('KaPdfAssembly'), $this->t('KaPdfAssemblyText'));
		$out[] = array($this->t('KaPdfDurable'), $this->t('KaPdfDurableText'));
		$out[] = array($this->t('KaPdfWarranty'), $this->t('KaPdfWarrantyText'));
		return $out;
	}

	protected function deviceShort($name, $d)
	{
		// Spüle: bis zur ersten Detailangabe kürzen
		$name = preg_replace('/\s+(reversibel|reverzibilno|reversible|ohne Ablauf.*|bez .*|without .*)$/u', '', $name);
		return rtrim($name, ' ,…');
	}

	protected function hasPosition($typ, $regex)
	{
		foreach ((array) ($typ->data['positionen'] ?? array()) as $p) {
			if (preg_match($regex, $p['text'])) {
				return true;
			}
		}
		return false;
	}

	protected function bulletRow($x, $w, $label, $text, $fs = 8.3, $lh = 3.7)
	{
		$pdf = $this->pdf;
		$y = $pdf->GetY();
		$pdf->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->Rect($x, $y + 1.4, 1.6, 1.6, 'F');
		$pdf->SetXY($x + 3.5, $y);
		$pdf->SetFont($this->font, 'B', $fs);
		$pdf->Cell(33, $lh, $label, 0, 0, 'L');
		$pdf->SetFont($this->font, '', $fs);
		$pdf->SetXY($x + 37, $y);
		$pdf->MultiCell($w - 37, $lh, $text, 0, 'L');
		$pdf->SetY($pdf->GetY() + 0.8);
	}

	protected function date($ts)
	{
		return KaDict::short($this->lang) === 'en' ? dol_print_date($ts, '%d/%m/%Y') : dol_print_date($ts, '%d.%m.%Y');
	}

	protected function num($v, $dec = null)
	{
		if ($dec === null) {
			$dec = (floor((float) $v) == (float) $v) ? 0 : 1;
		}
		return number_format((float) $v, $dec, ',', '.');
	}

	// ------------------------------------------------------------------
	// Folgeseiten
	// ------------------------------------------------------------------

	protected function heading($text)
	{
		$pdf = $this->pdf;
		if ($pdf->GetY() > $this->ph - 50) {
			$pdf->AddPage();
		}
		$pdf->Ln(2);
		$pdf->SetX($this->ml);
		$pdf->SetFont($this->font, 'B', 11.5);
		$pdf->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->Cell($this->pw - $this->ml - $this->mr, 7, $text, 0, 1, 'L');
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
	}

	protected function pageDetails()
	{
		$pdf = $this->pdf;
		$pdf->AddPage();
		$pdf->SetY(36);
		$w = $this->pw - $this->ml - $this->mr;
		$c = $this->calc;

		// Leistungsumfang und Preis
		$this->heading($this->t('KaPdfScope'));
		$cols = array(array($this->t('KaPdfPos'), 12, 'L'), array($this->t('KaPdfDesc'), 98, 'L'), array($this->t('KaPdfQty'), 18, 'R'),
			array($this->t('KaPdfUnitPrice'), 26, 'R'), array($this->t('KaPdfAmount'), 26, 'R'));
		$pdf->SetFont($this->font, 'B', 8.5);
		$pdf->SetFillColor(240, 236, 234);
		$pdf->SetX($this->ml);
		foreach ($cols as $col) {
			$pdf->Cell($col[1], 6, $col[0], 0, 0, $col[2], true);
		}
		$pdf->Ln(6);
		$i = 1;
		foreach ($this->kalk->typen as $k => $typ) {
			$row = $c['typen'][$k];
			$y = $pdf->GetY() + 1.5;
			if ($y > $this->ph - 45) {
				$pdf->AddPage();
				$y = 36;
			}
			$title = (count($this->kalk->typen) > 1 || $typ->label) ? $this->t('KaPdfKitchenLine', $typ->label ?: ('#'.$i)) : $this->t('KaPdfKitchenLineShort');
			$prog = trim($typ->merkmal('Programm').' '.$this->tr($typ->merkmal('Front-Kombination')));
			$pdf->SetFont($this->font, '', 8.8);
			$pdf->SetXY($this->ml, $y);
			$pdf->Cell(12, 5, (string) $i, 0, 0, 'L');
			$pdf->SetFont($this->font, 'B', 8.8);
			$pdf->MultiCell(98, 5, $title, 0, 'L');
			$y2 = $pdf->GetY();
			$pdf->SetFont($this->font, '', 7.8);
			$pdf->SetTextColor(100, 100, 100);
			$pdf->SetXY($this->ml + 12, $y2);
			$sub = $this->t('KaPdfKitchenSub', $prog ?: '-');
			$posl = array();
			foreach ($this->kalk->positionsFor($k) as $pos) {
				$posl[] = $pos['label'];
			}
			if ($posl) {
				$sub .= "\n".$this->t('KaPdfInclChanges', implode('; ', $posl));
			}
			$pdf->MultiCell(98, 3.8, $sub, 0, 'L');
			$y3 = $pdf->GetY();
			$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
			$pdf->SetFont($this->font, '', 8.8);
			$pdf->SetXY($this->ml + 110, $y);
			$pdf->Cell(18, 5, $this->num($row['qty']).' '.$this->t('KaPdfPieces'), 0, 0, 'R');
			$pdf->Cell(26, 5, kaMoney($row['vk']), 0, 0, 'R');
			$pdf->Cell(26, 5, kaMoney($row['vk'] * $row['qty']), 0, 0, 'R');
			$pdf->SetY($y3 + 1);
			$pdf->SetDrawColor(225, 225, 225);
			$pdf->Line($this->ml, $pdf->GetY(), $this->pw - $this->mr, $pdf->GetY());
			$i++;
		}
		$pdf->Ln(2);
		$this->sumRow($this->t('KaPdfSubtotal'), kaMoney($c['vk_netto']));
		$this->sumRow($this->t('KaPdfVat', $this->num($c['tva'])), kaMoney($c['mwst']));
		$this->sumRow($this->t('KaPdfGrandTotal'), kaMoney($c['vk_brutto']), true);
		if (!empty($this->kalk->params['verbraucher'])) {
			$pdf->Ln(2);
			$pdf->setCellPaddings(0, 0, 0, 0);
			$pdf->SetX($this->ml);
			$pdf->SetFont($this->font, 'B', 8.5);
			$pdf->MultiCell($w, 4.2, $this->t('KaPdfIndividual'), 0, 'L');
		}
		$basis = $this->basisText();
		if ($basis !== '') {
			$pdf->Ln(1.5);
			$pdf->setCellPaddings(0, 0, 0, 0);
			$pdf->SetFont($this->font, '', 8.5);
			$pdf->writeHTMLCell($w, 4.2, $this->ml, $pdf->GetY(), '<b>'.dol_escape_htmltag($this->t('KaPdfBasis')).':</b> '.dol_escape_htmltag($basis), 0, 1, false, true, 'L');
		}

		// Änderungen gegenüber der Vorversion
		$changes = $this->changes();
		if ($changes) {
			$this->heading($this->t('KaPdfChanges', $this->prev->version()).($this->prevPropal ? ' ('.$this->prevPropal->ref.')' : ''));
			foreach ($changes as $ch) {
				$this->para($ch, 8.3, true);
			}
		}

		// Ausstattung je Küchentyp
		foreach ($this->kalk->typen as $k => $typ) {
			$this->typeDetails($typ, $k);
		}

		// Hinweise
		$notes = $this->notes();
		if ($notes) {
			$this->heading($this->t('KaPdfNotes'));
			foreach ($notes as $n) {
				$this->para($n, 8.3, true);
			}
		}

		// Angebotsbestimmungen
		$terms = kaTerms($this->lang);
		if ($terms) {
			$this->heading($this->t('KaPdfTerms'));
			$nr = 1;
			foreach ($terms as $t) {
				$this->term($nr++, $t);
			}
		}

		// AGB mit QR-Code
		$url = getDolGlobalString('KA_AGB_URL');
		if ($url) {
			if ($pdf->GetY() > $this->ph - 50) {
				$pdf->AddPage();
				$pdf->SetY(36);
			}
			$y = $pdf->GetY() + 3;
			$style = array('border' => 0, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false);
			$pdf->write2DBarcode($url, 'QRCODE,M', $this->ml, $y, 22, 22, $style, 'N');
			$pdf->SetXY($this->ml + 26, $y + 2);
			$pdf->SetFont($this->font, '', 8.3);
			$pdf->MultiCell($w - 26, 4, $this->t('KaPdfAgb', $url), 0, 'L');
			$pdf->SetY(max($pdf->GetY(), $y + 23));
		}

		$this->acceptance();
	}

	protected function sumRow($label, $value, $bold = false)
	{
		$pdf = $this->pdf;
		$pdf->SetX($this->ml + 100);
		$pdf->SetFont($this->font, $bold ? 'B' : '', $bold ? 10 : 8.8);
		if ($bold) {
			$pdf->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
			$pdf->SetTextColor(255, 255, 255);
		}
		$pdf->Cell(54, $bold ? 7 : 5, $label, 0, 0, 'L', $bold);
		$pdf->Cell(26, $bold ? 7 : 5, $value, 0, 1, 'R', $bold);
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
	}

	/**
	 * Änderungen gegenüber der Vorversion als kurze Sätze
	 * @return string[]
	 */
	protected function changes()
	{
		if (!$this->prev) {
			return array();
		}
		$out = array();
		$prevCalc = $this->prev->compute();
		foreach ($this->kalk->typen as $k => $t) {
			$name = $t->label ?: '#'.($k + 1);
			$o = isset($this->prev->typen[$k]) ? $this->prev->typen[$k] : null;
			if (!$o) {
				$out[] = $this->t('KaPdfChgNewType', $name);
				continue;
			}
			$pre = count($this->kalk->typen) > 1 ? $name.': ' : '';
			if ((float) $o->qty != (float) $t->qty) {
				$out[] = $pre.$this->t('KaPdfChgQty', $this->num($o->qty), $this->num($t->qty));
			}
			$h = function ($kalk, $typ, $f) {
				$file = $kalk->getDir().'/'.$typ->$f;
				return ($typ->$f && is_readable($file)) ? md5_file($file) : '';
			};
			if ($h($this->prev, $o, 'file_an') !== $h($this->kalk, $t, 'file_an') || $h($this->prev, $o, 'file_bd') !== $h($this->kalk, $t, 'file_bd') || abs((float) $o->katalog - (float) $t->katalog) > 0.01) {
				$out[] = $pre.$this->t('KaPdfChgPlanning');
			}
			// Ausstattung
			$om = array();
			foreach ((array) ($o->data['merkmale'] ?? array()) as $m) {
				$om[$m['label']] = $m['value'];
			}
			$n = 0;
			foreach ((array) ($t->data['merkmale'] ?? array()) as $m) {
				if (isset($om[$m['label']]) && $om[$m['label']] !== $m['value'] && $n < 10) {
					$out[] = $pre.$this->tr($m['label']).': '.$this->tr($om[$m['label']]).' → '.$this->tr($m['value']);
					$n++;
				}
			}
			// Geräte
			$od = array_map(function ($d) {
				return $d['name'];
			}, (array) ($o->data['geraete'] ?? array()));
			$nd = array_map(function ($d) {
				return $d['name'];
			}, (array) ($t->data['geraete'] ?? array()));
			foreach (array_diff($nd, $od) as $d) {
				$out[] = $pre.$this->t('KaPdfChgAdded', $this->tr($d));
			}
			foreach (array_diff($od, $nd) as $d) {
				$out[] = $pre.$this->t('KaPdfChgRemoved', $this->tr($d));
			}
			// Zusatz-/Minderpositionen
			$ok = array();
			foreach ($this->prev->positionsFor($k) as $pos) {
				$ok[] = $pos['label'].'|'.(float) $pos['betrag'];
			}
			foreach ($this->kalk->positionsFor($k) as $pos) {
				if (!in_array($pos['label'].'|'.(float) $pos['betrag'], $ok, true)) {
					$out[] = $pre.$pos['label'];
				}
			}
		}
		foreach ($this->prev->typen as $k => $o) {
			if (!isset($this->kalk->typen[$k])) {
				$out[] = $this->t('KaPdfChgRemovedType', $o->label ?: '#'.($k + 1));
			}
		}
		$diff = $this->calc['vk_brutto'] - $prevCalc['vk_brutto'];
		$out[] = $this->t('KaPdfChgPrice', kaMoney($prevCalc['vk_brutto']), kaMoney($this->calc['vk_brutto']), ($diff >= 0 ? '+' : '−').' '.kaMoney(abs($diff)));
		return $out;
	}

	/** „Grundlage dieses Angebots: Gespräch … sowie folgende Unterlagen: …“ */
	protected function basisText()
	{
		$talk = trim((string) ($this->kalk->params['grundlage'] ?? ''));
		if ($talk === '') {
			$talk = $this->t('KaPdfBasisTalk');
		}
		$names = array();
		if (!empty($this->kalk->params['grundlage_docs'])) {
			foreach (glob($this->kalk->getDir().'/unterlagen/*') ?: array() as $f) {
				if (is_file($f)) {
					$n = pathinfo($f, PATHINFO_FILENAME);
					$names[] = trim(preg_replace('/\s+/', ' ', str_replace(array('_', '-'), ' ', preg_replace('/\.noexe$/', '', $n))));
				}
			}
			sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		}
		return $names ? $this->t('KaPdfBasisWithDocs', $talk, implode(', ', $names)) : $talk.'.';
	}

	/** Ausstattung und Geräte eines Küchentyps */
	protected function typeDetails($typ, $k)
	{
		$pdf = $this->pdf;
		$w = $this->pw - $this->ml - $this->mr;
		$multi = count($this->kalk->typen) > 1;
		if ($k > 0 && $this->imageFile($typ) && $pdf->GetY() > $this->ph - 100) {
			$pdf->AddPage();
			$pdf->SetY(36);
		}
		$this->heading($this->t('KaPdfDetails').($multi || $typ->label ? ' – '.($typ->label ?: '#'.($k + 1)) : ''));

		// weiteres 3D-Bild bei mehreren Typen
		if ($k > 0) {
			$img = $this->imageFile($typ);
			if ($img) {
				$size = @getimagesize($img);
				$iw = 95;
				$ih = ($size && $size[0]) ? $iw * $size[1] / $size[0] : 58;
				if ($pdf->GetY() + $ih > $this->ph - 25) {
					$pdf->AddPage();
					$pdf->SetY(36);
				}
				$pdf->Image($img, $this->ml, $pdf->GetY(), $iw, $ih, '', '', '', true, 150);
				$pdf->SetY($pdf->GetY() + $ih + 2);
			}
		}

		$rows = array();
		foreach ((array) ($typ->data['merkmale'] ?? array()) as $m) {
			if ($m['value'] === '' || $m['value'] === 'Standard' || preg_match('/Design(ausführung|farbe)|Blende \(vorne\)/u', $m['label'])) {
				continue;
			}
			$rows[] = array($this->tr($m['label']), $this->tr($m['value']));
		}
		if ($rows) {
			$half = (int) ceil(count($rows) / 2);
			$colw = ($w - 6) / 2;
			$y0 = $pdf->GetY();
			$ymax = $y0;
			foreach (array(array_slice($rows, 0, $half), array_slice($rows, $half)) as $ci => $list) {
				$y = $y0;
				$x = $this->ml + $ci * ($colw + 6);
				foreach ($list as $r) {
					if ($y > $this->ph - 25) {
						break;
					}
					$pdf->SetFont($this->font, '', 7.6);
					$pdf->SetTextColor(120, 120, 120);
					$pdf->SetXY($x, $y);
					$pdf->MultiCell(38, 3.6, $r[0], 0, 'L');
					$h1 = $pdf->GetY() - $y;
					$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
					$pdf->SetXY($x + 39, $y);
					$pdf->MultiCell($colw - 39, 3.6, $r[1], 0, 'L');
					$y = max($y + $h1, $pdf->GetY()) + 0.5;
				}
				$ymax = max($ymax, $y);
			}
			$pdf->SetY($ymax + 1);
		}

		// Geräte
		$dev = (array) ($typ->data['geraete'] ?? array());
		if ($dev) {
			if ($pdf->GetY() > $this->ph - 40) {
				$pdf->AddPage();
				$pdf->SetY(36);
			}
			$pdf->SetX($this->ml);
			$pdf->SetFont($this->font, 'B', 9);
			$pdf->Cell($w, 6, $this->t('KaPdfDevices').' / '.$this->t('KaPdfSink'), 0, 1, 'L');
			foreach ($dev as $d) {
				if ($pdf->GetY() > $this->ph - 25) {
					$pdf->AddPage();
					$pdf->SetY(36);
				}
				$y = $pdf->GetY();
				$pdf->SetFont($this->font, '', 7.8);
				$pdf->SetTextColor(120, 120, 120);
				$pdf->SetXY($this->ml, $y);
				$pdf->Cell(32, 3.8, $this->t('KaCat_'.$d['kategorie']), 0, 0, 'L');
				$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
				$pdf->MultiCell(118, 3.8, $this->tr($d['name']), 0, 'L');
				$y2 = $pdf->GetY();
				if ($d['eek'] && $d['kategorie'] !== 'licht') {
					$pdf->SetXY($this->ml + 152, $y);
					$pdf->Cell($w - 152, 3.8, 'EEK '.$d['eek'], 0, 0, 'R');
				}
				$pdf->SetY($y2 + 0.4);
			}
		}
	}

	/** Hinweise in Ausgabesprache */
	protected function notes()
	{
		$l = KaDict::short($this->lang);
		$out = array();
		$bs = kaFetchBausteine($this->db, false);
		foreach ((array) $this->kalk->hinweise['bausteine'] as $code) {
			if (!isset($bs[$code])) {
				continue;
			}
			$b = $bs[$code];
			$t = ($l !== 'de' && !empty($b->{'text_'.$l})) ? $b->{'text_'.$l} : $b->text_de;
			if (trim((string) $t) !== '') {
				$out[] = $t;
			}
		}
		foreach ((array) $this->kalk->hinweise['nobilia'] as $t) {
			$out[] = $this->tr($t);
		}
		$free = ($l !== 'de' && trim($this->kalk->note_lang2) !== '') ? $this->kalk->note_lang2 : $this->kalk->note_de;
		if (trim($free) !== '') {
			$out[] = trim($free);
		}
		return $out;
	}

	protected function para($text, $fs = 8.3, $bullet = false)
	{
		$pdf = $this->pdf;
		$w = $this->pw - $this->ml - $this->mr;
		if ($pdf->GetY() > $this->ph - 28) {
			$pdf->AddPage();
			$pdf->SetY(36);
		}
		$y = $pdf->GetY();
		$x = $this->ml;
		if ($bullet) {
			$pdf->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
			$pdf->Rect($x, $y + 1.3, 1.5, 1.5, 'F');
			$x += 4;
		}
		$pdf->SetFont($this->font, '', $fs);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w - ($x - $this->ml), 3.9, $text, 0, 'L');
		$pdf->SetY($pdf->GetY() + 0.8);
	}

	protected function term($nr, $text)
	{
		$pdf = $this->pdf;
		$w = $this->pw - $this->ml - $this->mr;
		if ($pdf->GetY() > $this->ph - 28) {
			$pdf->AddPage();
			$pdf->SetY(36);
		}
		$y = $pdf->GetY();
		$label = '';
		if (preg_match('/^([^:]{2,40}):\s*(.+)$/u', $text, $m)) {
			$label = $m[1];
			$text = $m[2];
		}
		$pdf->SetFont($this->font, '', 7.6);
		$pdf->SetXY($this->ml, $y);
		$pdf->Cell(6, 3.6, $nr.'.', 0, 0, 'L');
		$html = ($label !== '' ? '<b>'.dol_escape_htmltag($label).':</b> ' : '').dol_escape_htmltag($text);
		$pdf->setCellPaddings(0, 0, 0, 0);
		$pdf->writeHTMLCell($w - 6, 3.6, $this->ml + 6, $y, $html, 0, 1, false, true, 'L');
		$pdf->SetY($pdf->GetY() + 0.6);
	}

	/** Annahme mit Unterschrift (bleibt zusammen) */
	protected function acceptance()
	{
		$pdf = $this->pdf;
		$w = $this->pw - $this->ml - $this->mr;
		global $mysoc;
		// Privatkunden: Bestätigungen zum Rücktrittsrecht (§ 18 Abs 1 Z 3 und Z 1 FAGG)
		$confirm = !empty($this->kalk->params['verbraucher']) ? array($this->t('KaPdfConfirmCustom'), $this->t('KaPdfConfirmStart')) : array();
		$pdf->SetFont($this->font, '', 7.8);
		$extra = 0;
		foreach ($confirm as $c) {
			$extra += $pdf->getNumLines($c, $w - 15) * 3.5 + 1.5;
		}
		$extra = $confirm ? $extra + 2 : 0;
		$boxh = 50 + $extra;
		if ($pdf->GetY() > $this->ph - 25 - $boxh) {
			$pdf->AddPage();
			$pdf->SetY(36);
		}
		$pdf->Ln(3);
		$y = $pdf->GetY();
		$pdf->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->SetLineWidth(0.4);
		$pdf->Rect($this->ml, $y, $w, $boxh);
		$pdf->SetLineWidth(0.2);
		$pdf->SetXY($this->ml + 4, $y + 3);
		$pdf->SetFont($this->font, 'B', 10.5);
		$pdf->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->Cell($w - 8, 6, $this->t('KaPdfAcceptance'), 0, 2, 'L');
		$pdf->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
		$pdf->SetFont($this->font, '', 8.8);
		$txt = $this->t('KaPdfAcceptanceText', $this->object->ref, $this->date($this->object->date), kaMoney($this->calc['vk_brutto']));
		$pdf->MultiCell($w - 8, 4.4, $txt, 0, 'L');
		$pdf->SetX($this->ml + 4);
		$pdf->SetFont($this->font, 'B', 12);
		$pdf->Cell($w - 8, 7, $this->t('KaPdfTotalPrice').': '.kaMoney($this->calc['vk_brutto']).'  ('.$this->t('KaPdfInclVat', $this->num($this->calc['tva'])).')', 0, 2, 'L');
		if ($confirm) {
			$cy = $pdf->GetY() + 2;
			$pdf->SetDrawColor($this->dark[0], $this->dark[1], $this->dark[2]);
			foreach ($confirm as $c) {
				$pdf->Rect($this->ml + 4, $cy + 0.4, 3.2, 3.2);
				$pdf->SetFont($this->font, '', 7.8);
				$pdf->SetXY($this->ml + 10, $cy);
				$pdf->MultiCell($w - 15, 3.5, $c, 0, 'L');
				$cy = $pdf->GetY() + 1.5;
			}
		}
		$ly = $y + 42 + $extra;
		$pdf->SetDrawColor(120, 120, 120);
		$cw = ($w - 20) / 3;
		$labels = array($this->t('KaPdfPlaceDate'), $this->t('KaPdfSignCustomer'), $this->t('KaPdfSignCompany', $mysoc->name));
		foreach ($labels as $i => $lab) {
			$x = $this->ml + 4 + $i * ($cw + 6);
			$pdf->Line($x, $ly, $x + $cw, $ly);
			$pdf->SetXY($x, $ly + 0.8);
			$pdf->SetFont($this->font, '', 7.5);
			$pdf->Cell($cw, 3.5, $lab, 0, 0, 'L');
		}
		$pdf->SetY($y + $boxh + 2);
		$pdf->SetFont($this->font, 'I', 9);
		$pdf->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
		$pdf->SetX($this->ml);
		$pdf->Cell($w, 5, $this->t('KaPdfRegards'), 0, 1, 'L');
	}
}
