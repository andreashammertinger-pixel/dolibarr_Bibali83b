<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

dol_include_once('/hausverwaltung/class/hvstatementpdf.class.php');
dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
dol_include_once('/hausverwaltung/class/hvreserveplan.class.php');
dol_include_once('/hausverwaltung/class/hvrepository.class.php');

/**
 * Erläuterung zur Vorschreibung je Einheit: wie sich der Betrag zusammensetzt (Sätze, Kostenarten, Rücklage, Ablauf).
 * Original (hr_HR) + Kopie in der Sprache des Eigentümers, abgelegt neben der Rechnung:
 * <documents>/facture/<REF>/<REF>_info.pdf und <REF>_info_<lang>.pdf
 */
class HvInfoPdf extends HvStatementPdf
{
	public static function infoFiles(Facture $f)
	{
		global $conf;
		$ref = dol_sanitizeFileName($f->ref);
		$dir = $conf->facture->multidir_output[$f->entity ?? $conf->entity].'/'.$ref;
		$out = array();
		if (is_readable($dir.'/'.$ref.'_info.pdf')) {
			$out[] = $dir.'/'.$ref.'_info.pdf';
		}
		foreach (glob($dir.'/'.$ref.'_info_??_??.pdf') ?: array() as $p) {
			$out[] = $p;
		}
		return $out;
	}

	/**
	 * Erläuterung zu einer Vorschreibung erzeugen.
	 *
	 * @return string[] erzeugte Dateien
	 */
	public function generateForInvoice(Facture $f)
	{
		global $conf;
		$f->fetch_optionals();
		$period = (string) ($f->array_options['options_hv_period'] ?? '');
		$unitId = (int) ($f->array_options['options_hv_unit'] ?? 0);
		if ($unitId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $period)) {
			$this->error = 'no unit/period';
			return array();
		}
		$periodTo = (string) ($f->array_options['options_hv_period_to'] ?? '') ?: $period;
		$calc = new HvCalculator($this->db);
		$rows = $calc->compute();
		if (empty($rows[$unitId])) {
			$this->error = 'unit not found';
			return array();
		}
		$row = $rows[$unitId];
		$u = $row['unit'];
		$b = $calc->buildings[(int) $u->fk_building] ?? null;

		// Kostenarten mit Anteil der Einheit (Monatswerte)
		$items = array();
		$sumShare = 0;
		foreach ($calc->costitems as $c) {
			$key = !empty($c->alloc_key) && in_array($c->alloc_key, HvCalculator::keys(), true) ? $c->alloc_key : 'area';
			$alloc = $calc->allocate(HvCalculator::monthly($c), (int) $c->fk_building, $key);
			if (!array_key_exists($unitId, $alloc)) {
				continue;
			}
			$m = HvCalculator::monthly($c);
			$items[] = array('costitem' => $c, 'key' => $key, 'year' => $m * 12, 'share' => $alloc[$unitId], 'pct' => $m > 0 ? 100 * $alloc[$unitId] / $m : 0);
			$sumShare += $alloc[$unitId];
		}

		// Sätze je Gebäude
		$rates = array();
		foreach ($calc->buildings as $bid => $bb) {
			$a = $bk = $rs = $ad = 0;
			foreach ($rows as $r) {
				if ((int) $r['unit']->fk_building === (int) $bid) {
					$a += $r['area'];
					$bk += $r['bk'];
					$rs += $r['reserve'];
					$ad += $r['admin'];
				}
			}
			if ($a > 0) {
				$rates[$bid] = array('building' => $bb, 'area' => $a, 'bk' => $bk / $a, 'reserve' => $rs / $a, 'admin' => $ad / $a);
			}
		}

		// Rücklage: Prognose und nächste Maßnahmen
		$proj = (new HvReservePlan($this->db))->projection();
		$pb = $proj['buildings'][(int) $u->fk_building] ?? null;
		$next = array();
		$y0 = (int) dol_print_date(dol_now(), '%Y');
		foreach ((new HvRepository($this->db))->getReservePlan(true) as $p) {
			if ((int) $p->year_planned >= $y0 && in_array((int) $p->fk_building, array(0, (int) $u->fk_building), true)) {
				$next[] = $p;
			}
			if (count($next) >= 4) {
				break;
			}
		}

		$d = array('unit' => $u, 'building' => $b, 'invoice' => $f, 'period' => $period, 'periodTo' => $periodTo, 'months' => max(1, HvInvoicer::months($period, $periodTo)),
			'row' => $row, 'items' => $items, 'sumShare' => $sumShare, 'rates' => $rates, 'proj' => $pb, 'next' => $next, 'calc' => $calc);

		$soc = new Societe($this->db);
		$soc->fetch((int) $f->socid);
		$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
		$ref = dol_sanitizeFileName($f->ref);
		$dir = $conf->facture->multidir_output[$f->entity ?? $conf->entity].'/'.$ref;
		dol_mkdir($dir);
		foreach (self::infoFiles($f) as $old) {
			dol_delete_file($old, 0, 0, 0, null, false, 0);
		}
		$files = array();
		if ($this->write($d, $soc, 0, $orig, false, $dir.'/'.$ref.'_info.pdf')) {
			$files[] = $dir.'/'.$ref.'_info.pdf';
		}
		$ol = (string) $soc->default_lang;
		if ($ol !== '' && substr($ol, 0, 2) !== substr($orig, 0, 2)) {
			if ($this->write($d, $soc, 0, $ol, true, $dir.'/'.$ref.'_info_'.$ol.'.pdf')) {
				$files[] = $dir.'/'.$ref.'_info_'.$ol.'.pdf';
			}
		}
		return $files;
	}

	protected function docTitle($T, $d, $year)
	{
		return $T->transnoentities('HvInfoTitle', HvInvoicer::periodLabel($d['period'], $d['periodTo']));
	}

	protected function titleShort($T)
	{
		return $T->transnoentities('HvInfoTitleShort');
	}

	protected function headRows($T, $d, $year)
	{
		return array(
			array($T->transnoentities('HvPdfWDate').':', dol_print_date(dol_now(), 'day', false, $T, true)),
			array($T->transnoentities('HvStmtPeriod').':', HvInvoicer::periodLabel($d['period'], $d['periodTo'])),
			array($T->transnoentities('HvInfoForInvoice').':', $d['invoice']->ref),
		);
	}

	/** Abschnittsüberschrift */
	protected function section($pdf, $T, $text, $ctx)
	{
		extract($ctx);
		if ($pdf->GetY() > $ph - 60) {
			$pdf->AddPage();
		}
		$pdf->SetFont('', 'B', $fs);
		$pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetX($L);
		$pdf->Cell($W, 7, $text, 0, 1, 'L');
		$pdf->SetTextColor(0, 0, 0);
	}

	/** Tabelle mit Kopfzeile */
	protected function table($pdf, $T, $cols, $rows, $ctx, $sumRow = null)
	{
		extract($ctx);
		$pdf->SetFillColor($dark[0], $dark[1], $dark[2]);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('', 'B', $fs - 2);
		$pdf->SetX($L);
		foreach ($cols as $c) {
			$pdf->Cell($W * $c[1], 6.5, $c[0], 0, 0, $c[2], 1);
		}
		$pdf->Ln();
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $fs - 2);
		$i = 0;
		foreach ($rows as $vals) {
			$pdf->SetFillColor(240, 244, 241);
			$fill = ($i++ % 2) ? 1 : 0;
			$yl = $pdf->GetY();
			$pdf->SetX($L);
			$pdf->MultiCell($W * $cols[0][1], 5.2, $vals[0], 0, 'L', $fill, 1, '', '', true, 0, false, true, 0, 'T');
			$h = $pdf->GetY() - $yl;
			$x = $L + $W * $cols[0][1];
			for ($k = 1; $k < count($cols); $k++) {
				$pdf->SetXY($x, $yl);
				$pdf->Cell($W * $cols[$k][1], $h, (string) $vals[$k], 0, 0, $cols[$k][2], $fill, '', 1, false, 'T', 'T');
				$x += $W * $cols[$k][1];
			}
			$pdf->SetY($yl + $h);
		}
		$pdf->SetDrawColor($dark[0], $dark[1], $dark[2]);
		$pdf->Line($L, $pdf->GetY(), $R, $pdf->GetY());
		if ($sumRow) {
			$pdf->SetFont('', 'B', $fs - 1);
			$x = $L;
			$yl = $pdf->GetY() + 0.5;
			foreach ($cols as $k => $c) {
				$pdf->SetXY($x, $yl);
				$pdf->Cell($W * $c[1], 6, (string) ($sumRow[$k] ?? ''), 0, 0, $c[2]);
				$x += $W * $c[1];
			}
			$pdf->SetY($yl + 6);
		}
		$pdf->SetY($pdf->GetY() + 2);
	}

	protected function body($pdf, $T, $d, $soc, $year, $lang, $ctx)
	{
		global $conf, $mysoc;
		extract($ctx);
		$u = $d['unit'];
		$row = $d['row'];
		$f = $d['invoice'];
		$plabel = HvInvoicer::periodLabel($d['period'], $d['periodTo']);
		$num = function ($v, $dec = 2) use ($T) {
			return price($v, 0, $T, 1, $dec, $dec);
		};

		// Betreff
		$pdf->SetFont('', 'B', $fs + 1);
		$pdf->SetXY($L, $y);
		$pdf->Cell($W, 6, $T->transnoentities('HvInfoTitle', $plabel).' – '.$mysoc->name, 0, 1, 'L');
		$pdf->SetFont('', 'B', $fs);
		$pdf->SetX($L);
		$pdf->Cell($W, 5.5, $T->transnoentities('HvUnit').' '.$u->ref.'   ·   '.$num($u->area).' m²   ·   '.hvBuildingName($d['building'], $T), 0, 1, 'L');
		$pdf->SetFont('', '', $fs - 1);
		$pdf->SetX($L);
		$pdf->MultiCell($W, 4.5, $T->transnoentities('HvInfoIntro', $plabel), 0, 'L');
		$pdf->SetY($pdf->GetY() + 2);

		// 1. Ihre monatliche Vorschreibung
		$this->section($pdf, $T, '1. '.$T->transnoentities('HvInfoSecMonthly'), $ctx);
		$adminNetRate = (float) $u->admin_rate;
		$rowsM = array(
			array($T->transnoentities('HvInfoPosBk'), $num($row['bk_rate']), $num($row['area']), $fm($row['bk'])),
			array($T->transnoentities('HvInfoPosReserve', hvBuildingName($d['building'], $T)), $num($row['reserve_rate']), $num($row['area']), $fm($row['reserve'])),
			array($T->transnoentities('HvInfoPosAdmin', $num($adminNetRate), $num(HvCalculator::adminVatRate(), 0)), $num($row['admin_rate']), $num($row['area']), $fm($row['admin'])),
		);
		$cols = array(array($T->transnoentities('HvInfoColPosition'), 0.52, 'L'), array($T->transnoentities('HvInfoColRate'), 0.16, 'R'), array('m²', 0.14, 'R'), array($T->transnoentities('HvInfoColMonth'), 0.18, 'R'));
		$this->table($pdf, $T, $cols, $rowsM, $ctx, array($T->transnoentities('HvInfoPerMonth'), $num($row['bk_rate'] + $row['reserve_rate'] + $row['admin_rate']), '', $fm($row['total'])));
		$pdf->SetFont('', '', $fs - 1);
		$total = $row['total'] * $d['months'];
		$lines = array();
		if ($d['months'] > 1) {
			$lines[] = array($T->transnoentities('HvInfoTimesMonths', $d['months']), $fm($total));
		}
		if (abs((float) $f->total_ttc - $total) >= 0.01) {
			$lines[] = array($T->transnoentities('HvInfoCreditApplied'), $fm((float) $f->total_ttc - $total));
		}
		$lines[] = array($T->transnoentities('HvInfoInvoiceTotal', $f->ref), $fm((float) $f->total_ttc));
		$tw = $W * 0.6;
		foreach ($lines as $i => $ln) {
			$last = ($i === count($lines) - 1);
			$pdf->SetFont('', $last ? 'B' : '', $fs - 1);
			$pdf->SetX($R - $tw);
			$pdf->Cell($tw * 0.7, 5.5, $ln[0], 0, 0, 'L');
			$pdf->Cell($tw * 0.3, 5.5, $ln[1], 0, 1, 'R');
		}
		$pdf->SetY($pdf->GetY() + 3);

		// 2. Betriebskosten im Detail
		$this->section($pdf, $T, '2. '.$T->transnoentities('HvInfoSecBk'), $ctx);
		$rowsB = array();
		foreach ($d['items'] as $it) {
			$c = $it['costitem'];
			$sb = (int) $c->fk_building ? ($d['calc']->buildings[(int) $c->fk_building] ?? null) : null;
			$rowsB[] = array(hvI18n($c, 'label', $lang), hvBuildingName($sb, $T).' · '.$T->transnoentities('HvKey_'.$it['key']), $fm($it['year']), $num($it['pct']).' %', $fm($it['share']));
		}
		$colsB = array(array($T->transnoentities('HvStmtColItem'), 0.34, 'L'), array($T->transnoentities('HvStmtScope'), 0.23, 'L'), array($T->transnoentities('HvInfoColYear'), 0.14, 'R'), array($T->transnoentities('HvStmtShare'), 0.10, 'R'), array($T->transnoentities('HvInfoColYourMonth'), 0.19, 'R'));
		$this->table($pdf, $T, $colsB, $rowsB, $ctx, array($T->transnoentities('HvInfoSum'), '', '', '', $fm($d['sumShare'])));
		$pdf->SetFont('', '', $fs - 2);
		$pdf->SetX($L);
		$pdf->MultiCell($W, 4.2, $T->transnoentities('HvInfoBkNote', $fm($d['sumShare']), $num($row['area']), $num($row['bk_rate'])), 0, 'L');
		$pdf->SetY($pdf->GetY() + 3);

		// 3. Sätze je Haus
		if (count($d['rates']) > 1) {
			$this->section($pdf, $T, '3. '.$T->transnoentities('HvInfoSecRates'), $ctx);
			$rowsR = array();
			foreach ($d['rates'] as $bid => $r) {
				$rowsR[] = array(hvBuildingName($r['building'], $T).((int) $bid === (int) $u->fk_building ? '  ◄' : ''), $num($r['area']), $num($r['bk']), $num($r['reserve']), $num($r['admin']), $num($r['bk'] + $r['reserve'] + $r['admin']));
			}
			$colsR = array(array($T->transnoentities('HvBuilding'), 0.24, 'L'), array('m²', 0.12, 'R'), array($T->transnoentities('HvInfoColBk'), 0.19, 'R'), array($T->transnoentities('HvInfoColReserve'), 0.15, 'R'), array($T->transnoentities('HvInfoColAdmin'), 0.15, 'R'), array($T->transnoentities('HvInfoColTotalRate'), 0.15, 'R'));
			$this->table($pdf, $T, $colsR, $rowsR, $ctx);
			$pdf->SetFont('', '', $fs - 2);
			$pdf->SetX($L);
			$pdf->MultiCell($W, 4.2, $T->transnoentities('HvInfoRatesWhy'), 0, 'L');
			$pdf->SetY($pdf->GetY() + 3);
		}

		// 4. Rücklage
		$n = count($d['rates']) > 1 ? 4 : 3;
		$this->section($pdf, $T, $n.'. '.$T->transnoentities('HvInfoSecReserve', hvBuildingName($d['building'], $T)), $ctx);
		$pdf->SetFont('', '', $fs - 1);
		$pdf->SetX($L);
		$txt = $T->transnoentities('HvInfoReserveText');
		$pb = $d['proj'];
		if ($pb) {
			$txt .= "\n".$T->transnoentities('HvInfoReserveFacts', $fm($pb['start']), $fm($pb['monthly']), $num($pb['current_rate']), $num($pb['required_rate']));
		}
		$pdf->MultiCell($W, 4.6, $txt, 0, 'L');
		if ($d['next']) {
			$pdf->SetY($pdf->GetY() + 1);
			$pdf->SetFont('', 'B', $fs - 2);
			$pdf->SetX($L);
			$pdf->Cell($W, 5, $T->transnoentities('HvInfoNextMeasures'), 0, 1, 'L');
			$pdf->SetFont('', '', $fs - 2);
			foreach ($d['next'] as $p) {
				$pdf->SetX($L + 3);
				$pdf->Cell(16, 4.6, (string) $p->year_planned, 0, 0, 'L');
				$pdf->Cell($W - 3 - 16 - 35, 4.6, hvI18n($p, 'label', $lang).((int) $p->fk_building === 0 ? ' ('.$T->transnoentities('HvAllBuildings').')' : ''), 0, 0, 'L', 0, '', 1);
				$pdf->Cell(35, 4.6, $fm($p->amount), 0, 1, 'R');
			}
			$pdf->SetX($L + 3);
			$pdf->SetFont('', 'I', $fs - 3);
			$pdf->Cell($W - 3, 4.2, $T->transnoentities('HvInfoPlanNote'), 0, 1, 'L');
		}
		$pdf->SetY($pdf->GetY() + 3);

		// 5. Ablauf, Zahlung, Kontakt
		$this->section($pdf, $T, ($n + 1).'. '.$T->transnoentities('HvInfoSecCycle'), $ctx);
		$pdf->SetFont('', '', $fs - 1);
		$pts = array(
			$T->transnoentities('HvInfoCyclePay', getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5), $f->ref),
			$T->transnoentities('HvInfoCycleSettlement'),
			$T->transnoentities('HvInfoCycleFixed'),
		);
		$url = getDolGlobalString('HAUSVERWALTUNG_OWNER_PORTAL_URL');
		if ($url !== '') {
			$pts[] = $T->transnoentities('HvInfoPortal', $url);
		}
		$mgr = $helper->manager();
		if ($mgr) {
			$pts[] = $T->transnoentities('HvInfoContact', $mgr->name.(!empty($mgr->email) ? ', '.$mgr->email : '').(!empty($mgr->phone) ? ', '.$mgr->phone : ''));
		}
		foreach ($pts as $p) {
			$pdf->SetX($L);
			$pdf->Cell(4, 4.8, '•', 0, 0, 'L');
			$pdf->MultiCell($W - 4, 4.8, $p, 0, 'L');
		}
	}
}
