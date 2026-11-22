<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kacalcpdf.class.php
 * \brief   Interne Kalkulationsaufstellung als PDF (nur zum Check / als Vorlage, nicht für Kunden)
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/kuechenangebot/class/kakalk.class.php');
dol_include_once('/kuechenangebot/lib/kuechenangebot.lib.php');

class KaCalcPdf
{
	public $db;
	/** @var TCPDF */
	protected $pdf;
	protected $font = 'dejavusans';
	protected $ml = 15;
	protected $w = 180;
	protected $color = array(174, 157, 149);
	/** @var KaKalk */
	protected $kalk;

	public function __construct($db)
	{
		$this->db = $db;
		$hex = preg_replace('/[^0-9A-Fa-f]/', '', getDolGlobalString('KA_PDF_FARBE', 'AE9D95'));
		if (strlen($hex) === 6) {
			$this->color = array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
		}
	}

	/**
	 * PDF erzeugen und als String zurückgeben
	 * @param KaKalk $kalk
	 * @return string
	 */
	public function build($kalk)
	{
		global $mysoc, $langs;
		$this->kalk = $kalk;
		$p = $kalk->params;
		$c = $kalk->compute();
		$pdf = pdf_getInstance(array(210, 297));
		$this->pdf = $pdf;
		$this->font = getDolGlobalString('KA_PDF_FONT', 'dejavusans');
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetMargins($this->ml, 15, 15);
		$pdf->SetAutoPageBreak(true, 18);
		$pdf->SetTitle('Interne Kalkulation #'.$kalk->id);
		$pdf->SetAuthor($mysoc->name);
		$pdf->AddPage();

		// Kopf
		$pdf->SetFillColor(168, 50, 42);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont($this->font, 'B', 10);
		$pdf->Cell($this->w, 7, 'INTERN – Kalkulationsaufstellung, nicht für Kunden', 0, 1, 'C', true);
		$pdf->Ln(2);
		$pdf->SetTextColor(40, 40, 40);
		$pdf->SetFont($this->font, 'B', 15);
		$pdf->Cell($this->w, 8, 'Küchenkalkulation #'.$kalk->id.($kalk->version() > 1 ? ' – Version '.$kalk->version() : ''), 0, 1, 'L');

		$soc = new Societe($this->db);
		$socname = ($kalk->fk_soc && $soc->fetch($kalk->fk_soc) > 0) ? $soc->name.($soc->town ? ', '.$soc->town : '') : '-';
		$prop = '-';
		if ($kalk->fk_propal) {
			$pr = new Propal($this->db);
			if ($pr->fetch($kalk->fk_propal) > 0) {
				$prop = $pr->ref.' ('.$pr->getLibStatut(1).', '.dol_print_date($pr->date, 'day').', netto '.kaMoney($pr->total_ht).')';
			}
		}
		$this->kv(array(
			array('Kunde', $socname),
			array('Bauvorhaben', $kalk->label ?: '-'),
			array('Art', ($kalk->art === 'mfh' ? 'Mehrfamilienhaus / Wohnbau' : 'Private Küche').' · '.(!empty($p['verbraucher']) ? 'Privatkunde (Verbraucher)' : 'Firmenkunde (Nettopreise)')),
			array('Dolibarr-Angebot', $prop),
			array('Erstellt', dol_print_date(dol_now(), 'dayhour').' · '.$mysoc->name),
		));

		// Sätze
		$this->h('Verwendete Werte');
		$aufschlag = ($p['aufschlag_modus'] === 'pauschal') ? kaMoney($p['aufschlag_wert']).' je Küche' : kaFmt($p['aufschlag_wert'], 2).' % auf den Einkauf';
		if ($p['montage_modus'] === 'pauschal') {
			$mont = 'Pauschale '.kaMoney($p['montage_pauschal']).' je Küche';
		} elseif ($p['montage_modus'] === 'lfm') {
			$mont = kaMoney($p['montage_lfm_satz']).' je Laufmeter';
		} else {
			$mont = (int) $p['montage_arbeiter'].' Arbeiter × '.((float) $p['montage_std'] ? kaFmt($p['montage_std'], 1).' Std' : 'Std aus Laufmeter × '.kaFmt($p['std_pro_lfm'] ?? 0, 1)).' × '.kaMoney($p['montage_satz']).'/Std';
		}
		$trans = array('none' => 'kein', 'projekt' => 'Pauschale je Projekt', 'kueche' => 'Pauschale je Küche');
		$tr = $trans[$p['transport_modus']] ?? '-';
		if ($p['transport_modus'] !== 'none') {
			$tr .= ' '.kaMoney($p['transport_betrag']);
		}
		if ($p['muell_modus'] === 'pauschal') {
			$mu = 'Pauschale '.kaMoney($p['muell_pauschal']);
		} elseif ($p['muell_modus'] === 'stunden') {
			$mu = kaFmt($p['muell_std'], 1).' Std × '.kaMoney($p['muell_satz']).' × Faktor '.kaFmt($p['muell_faktor'], 2);
		} else {
			$mu = 'keine';
		}
		if ($p['muell_modus'] !== 'none') {
			$mu .= ($p['muell_basis'] === 'kueche') ? ' je Küche' : ' je Projekt';
		}
		$this->kv(array(
			array('Aufschlag', $aufschlag),
			array('Montage (Verkauf)', $mont),
			array('Antransport', $tr),
			array('Müllentsorgung', $mu),
			array('Interne Werte', 'Kosten/Monteurstunde '.kaMoney($p['kosten_stunde'] ?? 0).' · Richtwert '.kaFmt($p['std_pro_lfm'] ?? 0, 1).' Std/lfm · Gemeinkosten '.kaFmt($c['gk_pct'], 1).' %'),
			array('USt / Runden', kaFmt($p['tva'], 1).' %'.(!empty($p['runden']) ? ' · Endpreis gerundet auf '.kaFmt($p['runden'], 0).' € ('.(!empty($p['verbraucher']) ? 'brutto' : 'netto').')' : ' · keine Rundung')),
		));

		// Küchentypen
		$kStd = (float) ($p['kosten_stunde'] ?? 0);
		foreach ($kalk->typen as $k => $t) {
			$r = $c['typen'][$k];
			$this->h('Küchentyp: '.($t->label ?: '#'.($k + 1)).' – '.kaFmt($r['qty'], 0).' Stück', 85);
			$ab = $t->data['ab'] ?? null;
			$meta = $t->data['meta'] ?? array();
			$this->kv(array(
				array('Nobilia-Angebot', trim(($meta['angebot'] ?? '-').' vom '.($meta['datum'] ?? '-').' · '.($meta['planungsinformation'] ?? ''), ' ·')),
				array('Katalogwert', kaFmt($t->katalog).' ('.($t->preis_modus === 'punkte' ? 'Katalogpunkte' : 'Euro-Werte').') · Faktor '.kaFmt($t->faktor, 4)),
				array('Einkauf je Küche', kaMoney($t->getEk()).($ab && !empty($ab['angewendet']) ? ' – laut AB '.$ab['nr'].' vom '.$ab['datum'] : ' – Katalogwert × Faktor')),
				array('Laufmeter', kaFmt($r['lfm']).' m · Montage '.kaFmt($r['montage_std'], 1).' Std je Küche'),
			));
			$pos = $kalk->positionsFor($k);
			if ($pos) {
				$rows = array();
				foreach ($pos as $ps) {
					$tmp = clone $kalk;
					$tmp->params['positionen'] = array($ps);
					list($e1, $v1) = $tmp->positionDelta($k, $t);
					$rows[] = array($ps['label'], $ps['modus'] === 'nobilia' ? 'Nobilia-Wert '.kaFmt($ps['betrag']) : 'Verkaufspreis', kaMoney($e1), kaMoney($v1));
				}
				$this->table(array(array('Zusatz-/Minderposition', 80, 'L'), array('Basis', 40, 'L'), array('Einkauf', 30, 'R'), array('Verkauf netto', 30, 'R')), $rows);
			}
			$mi = $r['montage_std'] * $kStd;
			$muK = $r['kosten'] - $r['ek'] - $r['transport'] - $mi;
			$rows = array(
				array($kalk->positionsFor($k) ? 'Küche (Nobilia inkl. Positionen)' : 'Küche (Nobilia)', kaMoney($r['ek']), kaMoney($r['moebel']), kaMoney($r['moebel'] - $r['ek'])),
				array('Montage', kaMoney($mi), kaMoney($r['montage']), kaMoney($r['montage'] - $mi)),
				array('Antransport', kaMoney($r['transport']), kaMoney($r['transport']), kaMoney(0)),
				array('Müllentsorgung', kaMoney($muK), kaMoney($r['muell']), kaMoney($r['muell'] - $muK)),
				array('Summe berechnet', kaMoney($r['kosten']), kaMoney($r['vk_calc']), kaMoney($r['vk_calc'] - $r['kosten'])),
			);
			if (abs($r['vk'] - $r['vk_calc']) > 0.005) {
				$why = ($t->vk_override !== null && $t->vk_override > 0) ? 'fester Endpreis' : 'gerundet';
				$rows[] = array('Angebotspreis ('.$why.')', kaMoney($r['kosten']), kaMoney($r['vk']), kaMoney($r['vk'] - $r['kosten']));
			}
			$rows[] = array('Gemeinkosten '.kaFmt($c['gk_pct'], 1).' %', kaMoney($r['kosten_gk'] - $r['kosten']), '', kaMoney(-($r['kosten_gk'] - $r['kosten'])));
			$rows[] = array('Ergebnis je Küche', kaMoney($r['kosten_gk']), kaMoney($r['vk']), kaMoney($r['vk'] - $r['kosten_gk']));
			$this->table(array(array('je Küche', 72, 'L'), array('Kosten', 36, 'R'), array('Verkauf netto', 36, 'R'), array('Deckung', 36, 'R')), $rows, array(4, count($rows) - 1));
			$pdf->SetFont($this->font, '', 8);
			$pdf->Cell($this->w, 5, 'Verkauf je Küche brutto: '.kaMoney($r['vk'] * (1 + $c['tva'] / 100)).' · gesamt '.kaFmt($r['qty'], 0).' × '.kaMoney($r['vk']).' = '.kaMoney($r['vk'] * $r['qty']).' netto', 0, 1, 'L');
		}

		// Gesamt
		$this->h('Gesamt', 75);
		$rows = array(
			array('Einkauf Nobilia', kaMoney($c['ek'])),
			array('Montage intern ('.kaFmt($c['montage_std'], 1).' Std × '.kaMoney($kStd).')', kaMoney($c['montage_kosten'])),
			array('Antransport', kaMoney($c['transport_kosten'])),
			array('Müllentsorgung', kaMoney($c['muell_kosten'])),
			array('Direkte Kosten', kaMoney($c['selbstkosten_direkt'])),
			array('Gemeinkosten '.kaFmt($c['gk_pct'], 1).' %', kaMoney($c['selbstkosten'] - $c['selbstkosten_direkt'])),
			array('Preis kostendeckend (netto)', kaMoney($c['selbstkosten'])),
			array('Angebotspreis netto ('.kaFmt($c['stueck'], 0).' Küchen, '.kaFmt($c['lfm']).' lfm)', kaMoney($c['vk_netto'])),
			array('USt '.kaFmt($c['tva'], 1).' %', kaMoney($c['mwst'])),
			array('Angebotspreis brutto', kaMoney($c['vk_brutto'])),
			array('Deckungsbeitrag nach Gemeinkosten', kaMoney($c['db']).' ('.kaFmt($c['db_pct'], 1).' %)'),
			array('Faktor Verkauf / Einkauf', kaFmt($c['faktor'], 3)),
		);
		$this->table(array(array('', 130, 'L'), array('Betrag', 50, 'R')), $rows, array(4, 6, 7, 10));

		// Preis-Check
		$b = $kalk->benchmark();
		$this->h('Preis-Check gegen bisherige Küchen ('.($kalk->art === 'mfh' ? 'Mehrfamilienhaus' : 'privat').')');
		if ($b['n'] > 0) {
			$rows = array(
				array('Grundlage', $b['n'].' Projekte, '.kaFmt($b['stueck'], 0).' Küchen'),
				array('Ø Faktor Verkauf/Einkauf '.kaFmt($b['faktor'], 3), kaMoney($c['ek'] * $b['faktor'])),
			);
			if ($b['vk_lfm'] > 0) {
				$rows[] = array('Ø '.kaMoney($b['vk_lfm']).' je Laufmeter', kaMoney($c['lfm'] * $b['vk_lfm']));
			}
			if ($b['vk_stueck'] > 0) {
				$rows[] = array('Ø '.kaMoney($b['vk_stueck']).' je Küche', kaMoney($b['vk_stueck'] * $c['stueck']));
			}
			$rows[] = array('Dieses Angebot', kaMoney($c['vk_netto']));
			$this->table(array(array('', 130, 'L'), array('netto', 50, 'R')), $rows, array(count($rows) - 1));
		} else {
			$pdf->SetFont($this->font, '', 8.5);
			$pdf->MultiCell($this->w, 4.5, 'Noch keine Vergleichswerte. Angenommene Angebote zählen automatisch, frühere Küchen unter „Kennzahlen“ erfassen.', 0, 'L');
		}

		// Fuß auf allen Seiten
		$n = $pdf->getNumPages();
		for ($i = 1; $i <= $n; $i++) {
			$pdf->setPage($i);
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->SetXY($this->ml, 287);
			$pdf->SetFont($this->font, '', 7);
			$pdf->SetTextColor(150, 150, 150);
			$pdf->Cell(120, 4, 'INTERN · Küchenkalkulation #'.$kalk->id.' · '.dol_print_date(dol_now(), 'dayhour'), 0, 0, 'L');
			$pdf->Cell(60, 4, 'Seite '.$i.' von '.$n, 0, 0, 'R');
		}
		return $pdf->Output('', 'S');
	}

	protected function h($text, $need = 40)
	{
		$pdf = $this->pdf;
		if ($pdf->GetY() > 279 - $need) {
			$pdf->AddPage();
		}
		$pdf->Ln(3);
		$pdf->SetFont($this->font, 'B', 10.5);
		$pdf->SetTextColor($this->color[0] * 0.7, $this->color[1] * 0.7, $this->color[2] * 0.7);
		$pdf->Cell($this->w, 6, $text, 'B', 1, 'L');
		$pdf->SetTextColor(40, 40, 40);
		$pdf->Ln(1);
	}

	protected function kv($rows)
	{
		$pdf = $this->pdf;
		foreach ($rows as $r) {
			$y = $pdf->GetY();
			$pdf->SetFont($this->font, '', 8.3);
			$pdf->SetTextColor(110, 110, 110);
			$pdf->SetXY($this->ml, $y);
			$pdf->Cell(38, 4.4, $r[0], 0, 0, 'L');
			$pdf->SetTextColor(40, 40, 40);
			$pdf->MultiCell($this->w - 38, 4.4, (string) $r[1], 0, 'L');
		}
	}

	/**
	 * @param array $cols [Titel, Breite, Ausrichtung]
	 * @param array $rows
	 * @param int[] $bold Zeilenindizes fett
	 */
	protected function table($cols, $rows, $bold = array())
	{
		$pdf = $this->pdf;
		$pdf->SetFont($this->font, 'B', 8);
		$pdf->SetFillColor(240, 236, 234);
		$hasHead = false;
		foreach ($cols as $col) {
			if ($col[0] !== '') {
				$hasHead = true;
			}
		}
		if ($hasHead) {
			foreach ($cols as $col) {
				$pdf->Cell($col[1], 5, $col[0], 0, 0, $col[2], true);
			}
			$pdf->Ln(5);
		}
		foreach ($rows as $i => $r) {
			$pdf->SetFont($this->font, in_array($i, $bold, true) ? 'B' : '', 8.3);
			foreach ($cols as $j => $col) {
				$pdf->Cell($col[1], 4.8, (string) ($r[$j] ?? ''), 'B', 0, $col[2]);
			}
			$pdf->Ln(4.8);
		}
		$pdf->Ln(1);
	}
}
