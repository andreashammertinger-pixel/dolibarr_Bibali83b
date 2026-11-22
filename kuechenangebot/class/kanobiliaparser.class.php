<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kanobiliaparser.class.php
 * \brief   Liest Nobilia-/CARAT-Angebote (AN…pdf) und Planunterlagen (BD…pdf)
 */
require_once __DIR__.'/kapdfreader.class.php';

class KaNobiliaParser
{
	/** Bekannte Gerätemarken (Beschreibung beginnt damit) */
	public static $brands = array('LAURUS', 'BLANCO', 'CARLO NOBILI', 'ELICA', 'BOSCH', 'SIEMENS', 'NEFF', 'AEG', 'MIELE', 'BAUKNECHT', 'GORENJE',
		'ELECTROLUX', 'WHIRLPOOL', 'SMEG', 'GAGGENAU', 'BORA', 'NOVY', 'BERBEL', 'FRANKE', 'SCHOCK', 'GROHE', 'HANSGROHE', 'LIEBHERR',
		'BEKO', 'GRUNDIG', 'CANDY', 'HOOVER', 'AMICA', 'PITSOS', 'BALAY', 'CONSTRUCTA', 'SAMSUNG', 'LG', 'QUOOKER', 'NOBILIA', 'FALMEC', 'SILVERLINE');

	/** Gerätekategorien nach Stichwort (Reihenfolge = Priorität) */
	public static $categories = array(
		'geschirrspueler' => array('Geschirrspüler'),
		'kuehlen' => array('Kühlautomat', 'Kühlschrank', 'Kühl-Gefrier', 'Gefrierschrank', 'Kühlgerät'),
		'kochfeld' => array('Kochfeld', 'Induktion'),
		'backofen' => array('Einbauherd', 'Backofen', 'Herd', 'Dampfgarer', 'Kompakt'),
		'mikrowelle' => array('Mikrowelle'),
		'dunst' => array('Dunsthaube', 'Flachschirmhaube', 'Wandhaube', 'Inselhaube', 'Muldenlüfter', 'Lüfter', 'Haube', 'Aktivkohlefilter', 'Abluft'),
		'spuele' => array('Spüle', 'Einbau-Spüle', 'Unterbau-Spüle'),
		'armatur' => array('Mischbatterie', 'Armatur'),
		'licht' => array('Leuchte', 'LED'),
		'abfall' => array('Abfall', 'Mülltrenn', 'Müllsammler'),
	);

	/** @var string */
	public $error = '';

	/**
	 * Angebot lesen
	 * @param string $file Pfad zur AN-PDF
	 * @return array|null
	 */
	public function parseOffer($file)
	{
		$r = KaPdfReader::fromFile($file);
		if (!$r->getPageCount()) {
			$this->error = 'PDF konnte nicht gelesen werden';
			return null;
		}
		$res = array(
			'meta' => array(), 'merkmale' => array(), 'positionen' => array(), 'summen' => array(),
			'hinweise' => array(), 'geraete' => array(), 'lfm' => 0.0, 'katalog' => 0.0,
		);
		$phase = 'head';
		$col = array('pos' => 71, 'menge' => 104, 'artikel' => 153, 'desc' => 256, 'preis' => 489);
		$cur = null;
		$hint = '';
		$lastMerkmal = -1;
		for ($p = 1; $p <= $r->getPageCount(); $p++) {
			foreach ($r->getPageLines($p) as $line) {
				$t = trim($line['text']);
				$ch = $line['chunks'];
				if (preg_match('/^Angebot vom .*Seite:/u', str_replace("\t", ' ', $t))) {
					continue; // Kopfzeile Folgeseiten
				}
				if (preg_match('/^Pos\tMenge\tArtikel/u', $t)) {
					foreach ($ch as $c) {
						$k = array('Pos' => 'pos', 'Menge' => 'menge', 'Artikel' => 'artikel', 'Beschreibung' => 'desc', 'Preis' => 'preis');
						if (isset($k[trim($c['t'])])) {
							$col[$k[trim($c['t'])]] = $c['x'];
						}
					}
					$phase = 'pos';
					continue;
				}
				if ($phase === 'head' || $phase === 'merk') {
					$flat = str_replace("\t", ' ', $t);
					if (preg_match('/^Angebot\s+(\S+)\s+Kd\.Nr\.\s+(\S+)\s+Datum\s+([0-9.]+)/u', $flat, $m)) {
						$res['meta']['angebot'] = $m[1];
						$res['meta']['kdnr'] = $m[2];
						$res['meta']['datum'] = $m[3];
						continue;
					}
					if (preg_match('/^(Planungsinformation|Kommission|Fachberater|Projekt)\s*:\s*(.+)$/u', $flat, $m)) {
						$res['meta'][strtolower($m[1])] = trim($m[2]);
						continue;
					}
					// Merkmal: Label \t : \t CODE \t Text
					if (count($ch) >= 3 && trim($ch[1]['t']) === ':') {
						$label = trim($ch[0]['t']);
						$code = trim($ch[2]['t']);
						$val = isset($ch[3]) ? trim($ch[3]['t']) : $code;
						if (!isset($ch[3])) {
							$code = '';
						}
						if ($label === 'Lieferant') {
							$res['meta']['lieferant'] = $val;
						} else {
							$res['merkmale'][] = array('label' => $label, 'code' => $code, 'value' => $val);
							$lastMerkmal = count($res['merkmale']) - 1;
						}
						$phase = 'merk';
						continue;
					}
					if ($phase === 'merk' && count($ch) == 1 && $lastMerkmal >= 0 && preg_match('/^\(.*\)$/u', $t)) {
						$res['merkmale'][$lastMerkmal]['label'] .= ' '.$t; // umbrochenes Label
					}
					continue;
				}
				if ($phase === 'pos') {
					$first = $ch[0];
					// Summenblock
					if (count($ch) == 2 && abs($first['x'] - $col['pos']) < 8 && preg_match('/^[\d.]+,\d{2}$/', trim($ch[1]['t'])) && !preg_match('/^\d/', trim($first['t']))) {
						$res['summen'][trim($first['t'])] = self::num($ch[1]['t']);
						if (trim($first['t']) === 'Gesamtbetrag') {
							$phase = 'foot';
						}
						$cur = null;
						continue;
					}
					if (abs($first['x'] - $col['pos']) < 8 && preg_match('/^\d+(\.\d+)*$/', trim($first['t']))) {
						$pos = array('pos' => trim($first['t']), 'menge' => 0, 'artikel' => '', 'lr' => '', 'text' => '', 'preis' => null);
						foreach (array_slice($ch, 1) as $c) {
							$v = trim($c['t']);
							if ($c['x'] < $col['artikel'] - 5 && preg_match('/^[\d.]+$/', $v)) {
								$pos['menge'] = (float) $v;
							} elseif ($c['x'] < $col['desc'] - 20) {
								$pos['artikel'] = $v;
							} elseif ($c['x'] < $col['desc'] - 2 && preg_match('/^[LR]$/', $v)) {
								$pos['lr'] = $v;
							} elseif ($c['x'] >= $col['preis'] - 30 && preg_match('/^-?[\d.]+,\d{2}$/', $v)) {
								$pos['preis'] = self::num($v);
							} else {
								$pos['text'] .= ($pos['text'] === '' ? '' : ' ').$v;
							}
						}
						$pos['level'] = substr_count($pos['pos'], '.');
						$res['positionen'][] = $pos;
						$cur = count($res['positionen']) - 1;
						continue;
					}
					if ($cur !== null && $first['x'] >= $col['desc'] - 5) {
						foreach ($ch as $c) {
							$v = trim($c['t']);
							if ($c['x'] >= $col['preis'] - 30 && preg_match('/^-?[\d.]+,\d{2}$/', $v)) {
								$res['positionen'][$cur]['preis'] = self::num($v);
							} else {
								$res['positionen'][$cur]['text'] .= "\n".$v;
							}
						}
					}
					continue;
				}
				if ($phase === 'foot') {
					$flat = trim(str_replace("\t", ' ', $t));
					if ($hint !== '' && preg_match('/^[A-ZÄÖÜ]/u', $flat) && preg_match('/[.!:]$/u', $hint)) {
						$res['hinweise'][] = $hint;
						$hint = '';
					}
					$hint .= ($hint === '' ? '' : ' ').$flat;
				}
			}
		}
		if ($hint !== '') {
			$res['hinweise'][] = $hint;
		}
		foreach ($res['positionen'] as &$pp) {
			$pp['text'] = self::cleanText($pp['text']);
		}
		unset($pp);
		$res['katalog'] = isset($res['summen']['Gesamtbetrag']) ? $res['summen']['Gesamtbetrag'] : array_sum(array_map(function ($x) {
			return (float) $x['preis'];
		}, $res['positionen']));
		$res['geraete'] = $this->detectDevices($res['positionen']);
		$res['lfm'] = $this->guessRunningMeters($res['positionen']);
		$res['arbeitsplatte_mm'] = $this->sumWidth($res['positionen'], '/^Arbeitsplatte/u');
		return $res;
	}

	/** Zeilen einer Beschreibung zu Fließtext verbinden (Silbentrennung beachten) */
	public static function cleanText($t)
	{
		$lines = explode("\n", $t);
		$out = '';
		foreach ($lines as $l) {
			$l = trim(preg_replace('/\s+/u', ' ', $l));
			if ($l === '') {
				continue;
			}
			if ($out === '') {
				$out = $l;
			} elseif (preg_match('/[a-zäöüß]-$/u', $out) && preg_match('/^[a-zäöüß]/u', $l)) {
				$out = substr($out, 0, -1).$l; // getrenntes Wort
			} elseif (preg_match('/-$/u', $out)) {
				$out .= $l; // z. B. "LED-" + "Innenbeleuchtung"
			} else {
				$out .= ' '.$l;
			}
		}
		return preg_replace('/\s+([,.:])/u', '$1', $out);
	}

	/** "1.234,50" → 1234.5 */
	public static function num($s)
	{
		$s = trim(str_replace(array(' ', "\xC2\xA0"), '', (string) $s));
		if (preg_match('/,\d{1,2}$/', $s)) {
			$s = str_replace(array('.', ','), array('', '.'), $s);
		}
		return (float) $s;
	}

	/**
	 * Geräte, Spüle, Armatur erkennen
	 * @return array<int,array{pos:string,kategorie:string,marke:string,name:string,eek:string,preis:float}>
	 */
	public function detectDevices($positions)
	{
		$out = array();
		foreach ($positions as $p) {
			$t = $p['text'];
			$brand = '';
			foreach (self::$brands as $b) {
				if (stripos($t, $b) === 0) {
					$brand = $b;
					break;
				}
			}
			$cat = '';
			$head = self::shortName($t);
			foreach (self::$categories as $k => $words) {
				foreach ($words as $w) {
					if (mb_stripos($head, $w) !== false) {
						$cat = $k;
						break 2;
					}
				}
			}
			$isdevice = $brand !== '' || preg_match('/Energieeffizienzklasse|Anschlusswert/u', $t);
			// Schränke wie "Geräte-Umbau Kühlautomat" oder "Wandschrank für Mikrowellengerät" sind keine Geräte
			if (!$isdevice || preg_match('/^(Geräte-Umbau|Umbau|Wandschrank|Unterschrank|Hochschrank|Herd-Umbauschrank|Spülenschrank|Durchgehende Türfront)/u', $t)) {
				continue;
			}
			if ($cat === 'licht' && $brand === '') {
				$cat = 'licht';
			}
			$eek = '';
			if (preg_match('/Energieeffizienz(?:klasse)?\s*([A-G](?:\+{1,3})?)\b/u', $t, $m)) {
				$eek = $m[1];
			}
			$out[] = array(
				'pos' => $p['pos'], 'kategorie' => $cat ?: 'sonstiges', 'marke' => $brand,
				'name' => $head, 'eek' => $eek, 'preis' => (float) $p['preis'], 'artikel' => $p['artikel'],
			);
		}
		return $out;
	}

	/** Kurzbezeichnung eines Geräts: Text bis zur ersten Detailangabe */
	public static function shortName($t)
	{
		$t = preg_replace('/^CARLO NOBILI:\s*/u', 'CARLO NOBILI ', $t);
		$t = preg_replace('/^ELICA:\s*/u', 'ELICA ', $t);
		$cut = preg_split('/\s*(Ausstattung:|Betriebsarten:|Kochzonen:|Technische Daten|Lieferumfang:|Untermaß:|mit Schalter|Gehäuse|, \d+ Programme|reversibel|ohne Ablauf|mit Ablauf)/u', $t);
		$s = trim($cut[0]);
		$s = preg_replace('/\s+,/u', ',', $s);
		$s = rtrim($s, ' ,');
		if (mb_strlen($s) > 90) {
			$s = mb_substr($s, 0, 87).'…';
		}
		return $s;
	}

	/** Summe "Breite: … mm" bzw. "Länge: … mm" von Positionen, deren Text auf $regex passt */
	protected function sumWidth($positions, $regex, $key = 'Breite')
	{
		$sum = 0;
		foreach ($positions as $p) {
			if (preg_match($regex, $p['text']) && preg_match('/'.$key.':\s*([\d.]+)\s*mm/u', $p['text'], $m)) {
				$sum += (float) str_replace('.', '', $m[1]) * max(1, (float) $p['menge']);
			}
		}
		return $sum;
	}

	/**
	 * Vorschlag Laufmeter: Sockelblenden ab 1 m Länge (Front ohne Seitenstücke), sonst Arbeitsplatten
	 * @return float Meter
	 */
	public function guessRunningMeters($positions)
	{
		$sockel = 0;
		foreach ($positions as $p) {
			if (preg_match('/^Sockelblende/u', $p['text']) && preg_match('/Länge:\s*([\d.]+)\s*mm/u', $p['text'], $m)) {
				$l = (float) str_replace('.', '', $m[1]);
				if ($l >= 1000) {
					$sockel += $l;
				}
			}
		}
		$apl = $this->sumWidth($positions, '/^Arbeitsplatte/u');
		$mm = max($sockel, $apl);
		return round($mm / 1000, 2);
	}

	/**
	 * Planunterlagen lesen: Hinweise + größtes Bild (3D-Perspektive) speichern
	 * @param string $file    BD-PDF
	 * @param string $outfile Ziel-JPEG
	 * @param int    $maxpx   längste Seite (verkleinern für kleinere PDFs)
	 * @return array{bild:bool,hinweise:array,seiten:array}
	 */
	public function parsePlans($file, $outfile, $maxpx = 2000)
	{
		$r = KaPdfReader::fromFile($file);
		$res = array('bild' => false, 'hinweise' => array(), 'seiten' => array());
		$perspPage = 0;
		for ($p = 1; $p <= $r->getPageCount(); $p++) {
			$title = '';
			foreach ($r->getPageLines($p) as $l) {
				foreach ($l['chunks'] as $c) {
					$v = trim($c['t']);
					if (preg_match('/^(Perspektive|Grundriss|Frontansicht.*|Seitenansicht.*|Wandabwicklung.*|Draufsicht|Ansicht.*)$/u', $v)) {
						$title = $v;
					}
					if (preg_match('/(keine Gewähr|Abweichungen|unverbindlich)/u', $v) && mb_strlen($v) > 30 && !in_array($v, $res['hinweise'])) {
						$res['hinweise'][] = $v;
					}
				}
			}
			$res['seiten'][$p] = $title;
			if ($title === 'Perspektive' && !$perspPage) {
				$perspPage = $p;
			}
		}
		$best = null;
		foreach ($r->getImages($perspPage) as $im) {
			if (!$best || $im['width'] * $im['height'] > $best['width'] * $best['height']) {
				$best = $im;
			}
		}
		if (!$best && $perspPage) {
			foreach ($r->getImages() as $im) {
				if (!$best || $im['width'] * $im['height'] > $best['width'] * $best['height']) {
					$best = $im;
				}
			}
		}
		if ($best && $best['width'] >= 400) {
			$tmp = $outfile.'.tmp.jpg';
			if ($r->saveImageAsJpeg($best['obj'], $tmp)) {
				$res['bild'] = self::resizeJpeg($tmp, $outfile, $maxpx);
				@unlink($tmp);
			}
		}
		return $res;
	}

	/** JPEG verkleinern (GD); ohne GD wird kopiert */
	public static function resizeJpeg($src, $dst, $maxpx)
	{
		$info = @getimagesize($src);
		if (!$info) {
			return false;
		}
		list($w, $h) = $info;
		if (!function_exists('imagecreatefromstring') || max($w, $h) <= $maxpx) {
			return copy($src, $dst);
		}
		$im = @imagecreatefromstring((string) file_get_contents($src));
		if (!$im) {
			return copy($src, $dst);
		}
		$f = $maxpx / max($w, $h);
		$nw = (int) round($w * $f);
		$nh = (int) round($h * $f);
		$out = imagecreatetruecolor($nw, $nh);
		imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
		$ok = imagejpeg($out, $dst, 85);
		imagedestroy($im);
		imagedestroy($out);
		return $ok;
	}
}
