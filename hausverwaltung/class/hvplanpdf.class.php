<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvrepository.class.php');
dol_include_once('/hausverwaltung/class/hvcalculator.class.php');
dol_include_once('/hausverwaltung/class/hvreserveplan.class.php');

/**
 * Rücklagen-Plan als PDF in einer Sprache (Inhalt wie die Druckansicht der Seite Rücklagen-Plan):
 * Zusammenfassung je Haus, Prognose je Jahr, Planpositionen.
 * Wird z. B. beim Versand eines Rundschreibens jedes Mal neu erzeugt.
 */
class HvPlanPdf
{
	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param string $lang Sprache (z. B. hr_HR)
	 * @param string $file Zieldatei
	 * @return bool
	 */
	public function write($lang, $file)
	{
		global $conf, $mysoc;
		$T = new Translate('', $conf);
		$T->setDefaultLang($lang);
		$T->loadLangs(array('main', 'banks', 'hausverwaltung@hausverwaltung'));
		$repo = new HvRepository($this->db);
		$buildings = $repo->getBuildings(false);
		$items = $repo->getReservePlan(false);
		$proj = (new HvReservePlan($this->db))->projection();
		$fm = function ($v) use ($T, $conf) {
			return price($v, 0, $T, 1, 2, 2, $conf->currency);
		};
		$e = function ($s) {
			return dol_escape_htmltag((string) $s);
		};
		$th = 'style="background-color:#2e5a3f;color:#ffffff;font-weight:bold"';
		$sep = 'border-right:1.6px solid #000000;'; // dicke Trennlinie nach jedem Haus (Prognose)
		$r = 'align="right"';

		$h = '<h1 style="font-size:15pt;color:#2e5a3f">'.$e($T->transnoentities('HvReservePlan').' – '.$mysoc->name).'</h1>';
		$h .= '<p style="font-size:8pt;color:#555">'.$e($T->transnoentities('HvPlanAssumptions', price($proj['inflation'], 0, $T), dol_print_date(dol_now(), 'day', false, $T))).'</p>';

		// Zusammenfassung je Haus
		$h .= '<table cellpadding="3" border="0.2" style="font-size:8pt"><tr><td '.$th.'>'.$e($T->transnoentities('HvBuilding')).'</td><td '.$th.' '.$r.'>'.$e($T->transnoentities('HvBalance')).'</td><td '.$th.' '.$r.'>'.$e($T->transnoentities('HvPlanMonthly')).'</td><td '.$th.' '.$r.'>'.$e($T->transnoentities('HvPlanCurrentRate')).'</td><td '.$th.' '.$r.'>'.$e($T->transnoentities('HvPlanRequiredRate')).'</td><td '.$th.' '.$r.'>'.$e($T->transnoentities('HvPlanMinBalance')).'</td><td '.$th.'>'.$e($T->transnoentities('Status')).'</td></tr>';
		foreach ($proj['buildings'] as $o) {
			$ok = $o['shortfall_year'] === null;
			$h .= '<tr><td>'.$e(hvBuildingName($o['building'], $T)).'</td><td '.$r.'>'.$fm($o['start']).'</td><td '.$r.'>'.$fm($o['monthly']).'</td>';
			$h .= '<td '.$r.'>'.price($o['current_rate'], 0, $T, 1, 2, 2).' €/m²</td><td '.$r.'>'.price($o['required_rate'], 0, $T, 1, 2, 2).' €/m²</td>';
			$h .= '<td '.$r.($ok ? '' : ' style="color:#a00000"').'>'.$fm($o['min_balance']).'</td>';
			$h .= '<td>'.$e($ok ? $T->transnoentities('HvPlanOk') : $T->transnoentities('HvPlanShortfall', $o['shortfall_year'])).'</td></tr>';
		}
		$h .= '</table><p style="font-size:7pt;color:#666">'.$e($T->transnoentities('HvPlanRequiredHelp')).'</p>';

		// Prognose
		$h .= '<h3 style="font-size:11pt;color:#2e5a3f">'.$e($T->transnoentities('HvPlanProjection')).'</h3>';
		$h .= '<table cellpadding="1.5" border="0.2" style="font-size:7pt"><tr><td '.$th.' width="6%">'.$e($T->transnoentities('Year')).'</td>';
		$nb = max(1, count($proj['buildings']));
		$cw = round(66 / ($nb * 3), 2);
		foreach ($proj['buildings'] as $o) {
			$h .= '<td '.$th.' '.$r.' width="'.$cw.'%">'.$e(hvBuildingName($o['building'], $T)).'<br>'.$e($T->transnoentities('HvMovesIn')).'</td><td '.$th.' '.$r.' width="'.$cw.'%">'.$e($T->transnoentities('HvMovesOut')).'</td><td '.$r.' width="'.$cw.'%" style="background-color:#2e5a3f;color:#ffffff;font-weight:bold;'.$sep.'">'.$e($T->transnoentities('HvPlanEndBalance')).'</td>';
		}
		$h .= '<td '.$th.' width="28%">'.$e($T->transnoentities('HvPlanMeasures')).'</td></tr>';
		foreach ($proj['years'] as $yr) {
			$h .= '<tr nobr="true"><td width="6%">'.$yr.'</td>';
			$labels = array();
			foreach ($proj['buildings'] as $o) {
				$row = $o['rows'][$yr];
				$h .= '<td '.$r.' width="'.$cw.'%">'.$fm($row['in']).'</td><td '.$r.' width="'.$cw.'%">'.($row['out'] > 0 ? $fm(-$row['out']) : '').'</td><td '.$r.' width="'.$cw.'%" style="'.$sep.($row['end'] < 0 ? 'color:#a00000;' : '').'"><b>'.$fm($row['end']).'</b></td>';
				foreach ($row['items'] as $it) {
					$labels[hvI18n($it['obj'], 'label', $lang)] = true;
				}
			}
			$h .= '<td width="28%" style="font-size:6.5pt">'.$e(implode('; ', array_keys($labels))).'</td></tr>';
		}
		$h .= '</table>';

		// Planpositionen – immer auf neuer Seite, damit Überschrift und Tabelle zusammenbleiben
		$h2 = '';
		$h2 .= '<h3 style="font-size:11pt;color:#2e5a3f">'.$e($T->transnoentities('HvPlanItems')).'</h3>';
		$h2 .= '<table cellpadding="3" border="0.2" style="font-size:8pt"><thead><tr><td '.$th.' width="8%">'.$e($T->transnoentities('HvPlanYear')).'</td><td '.$th.' width="40%">'.$e($T->transnoentities('Label')).'</td><td '.$th.' width="17%">'.$e($T->transnoentities('HvBuilding')).'</td><td '.$th.' '.$r.' width="12%">'.$e($T->transnoentities('HvPlanAmount')).'</td><td '.$th.' width="23%">'.$e($T->transnoentities('HvPlanBasis')).'</td></tr></thead>';
		$sum = 0;
		foreach ($items as $p) {
			if (!$p->active) {
				continue;
			}
			$sum += $p->amount;
			$b = (int) $p->fk_building ? ($buildings[(int) $p->fk_building] ?? null) : null;
			$note = hvI18n($p, 'note', $lang);
			$h2 .= '<tr nobr="true"><td width="8%">'.((int) $p->year_planned).'</td><td width="40%">'.$e(hvI18n($p, 'label', $lang)).($note ? '<br><span style="color:#666;font-size:7pt">'.$e($note).'</span>' : '').'</td>';
			$h2 .= '<td width="17%">'.$e(hvBuildingName($b, $T)).(!$b ? ' <span style="color:#666">('.$e($T->transnoentities('HvKey_'.($p->alloc_key ?: 'area'))).')</span>' : '').'</td>';
			$h2 .= '<td '.$r.' width="12%">'.$fm($p->amount).'</td><td width="23%" style="font-size:7pt">'.$e(hvI18n($p, 'basis', $lang)).'</td></tr>';
		}
		$h2 .= '<tr nobr="true"><td colspan="3" width="65%"><b>'.$e($T->transnoentities('Total').' ('.$T->transnoentities('HvPlanTodayPrices').')').'</b></td><td '.$r.' width="12%"><b>'.$fm($sum).'</b></td><td width="23%"></td></tr></table>';
		$h2 .= '<p style="font-size:7pt;color:#666">'.$e($T->transnoentities('HvPlanDisclaimer')).'</p>';

		$fmt = pdf_getFormat();
		$pdf = pdf_getInstance(array($fmt['width'], $fmt['height']));
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetMargins(12, 12, 12);
		$pdf->SetAutoPageBreak(true, 16);
		$pdf->SetFont(getDolGlobalString('HAUSVERWALTUNG_PDF_FONT', 'dejavusans'), '', 9);
		$pdf->SetTitle($T->transnoentities('HvReservePlan'));
		$pdf->AddPage('L');
		$pdf->writeHTML($h.'<br pagebreak="true" />'.$h2, true, false, true, false, '');
		// Fußzeile (Version, Zeit, KI-Hinweis) auf jeder Seite
		$stamp = hvDocStamp($T);
		$pdf->SetFont('', '', 6.5);
		$pdf->SetTextColor(110, 110, 110);
		for ($i = 1, $n = $pdf->getNumPages(); $i <= $n; $i++) {
			$pdf->setPage($i);
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->SetFont('', '', 6.5);
			$pdf->SetTextColor(110, 110, 110);
			$pdf->SetMargins(12, 12, 12);
			$pdf->SetXY(12, $pdf->getPageHeight() - 10);
			$pdf->MultiCell($pdf->getPageWidth() - 24, 3, $stamp.' · '.$i.'/'.$n, 0, 'C');
		}
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);
		return is_readable($file);
	}
}
