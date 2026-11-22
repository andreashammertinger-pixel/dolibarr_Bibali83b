<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kaplanconverter.class.php
 * \brief   Plankopf der Planerin durch migora ersetzen (PHP-Fassung des „migora Planumwandler“,
 *          Repository migora_logo_change): Logo + Kontakt links und Kundenadresse rechts werden
 *          aus dem PDF entfernt (nicht nur überdeckt), danach migora-Logo, Kontakt und
 *          Kundenbezeichnung neu gezeichnet. Ergebnis ist ein neu geschriebenes PDF.
 */
require_once __DIR__.'/kapdfreader.class.php';

class KaPlanConverter extends KaPdfReader
{
	/** Bezugsgröße A4 quer in pt, Bereiche [x1, y1, x2, y2] mit y von oben (wie in der Web-App) */
	const A4W = 841.92;
	const A4H = 595.32;
	public static $RM_LEFT = array(8, 520, 335, 576);     // Logo + Kontakt Planerin
	public static $RM_RIGHT = array(495, 520, 840, 573.5); // Kundenadresse (alt)
	public static $LABEL = array(690, 505, 840, 520);      // kleine Projektbezeichnung oben rechts

	/** @var array<int,string> Objektnummer → neuer, unkomprimierter Inhalt */
	protected $newStreams = array();
	/** @var array<int,array{removed:int,label:string}> je Seite */
	public $pagesInfo = array();

	/**
	 * Umwandeln
	 * @param string   $outfile  Ziel-PDF
	 * @param string[] $contact  Zeilen rechtsbündig neben dem Logo (Name, Telefon, E-Mail)
	 * @param string   $customer Kundenbezeichnung rechts im Plankopf
	 * @param string   $logo     JPEG des Plankopf-Logos (Seitenverhältnis ca. 3,1 : 1)
	 * @return bool
	 */
	public function convert($outfile, $contact, $customer, $logo)
	{
		if (!$this->pages) {
			$this->error = 'Keine Seiten gefunden';
			return false;
		}
		if (preg_match('#/Encrypt\s#', $this->data)) {
			$this->error = 'Verschlüsseltes PDF wird nicht unterstützt';
			return false;
		}
		$info = @getimagesize($logo);
		if (!$info || $info[2] != IMAGETYPE_JPEG) {
			$this->error = 'Plankopf-Logo fehlt oder ist kein JPEG';
			return false;
		}

		$maxnum = max(array_keys($this->objects));
		$new = array(); // num → [dict, streamBytes|null]
		$next = function () use (&$maxnum) {
			return ++$maxnum;
		};

		// gemeinsame Objekte: Logo, Schriften
		$logoNum = $next();
		$new[$logoNum] = array('<< /Type /XObject /Subtype /Image /Width '.$info[0].' /Height '.$info[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode >>', (string) file_get_contents($logo));
		$timesNum = $next();
		$new[$timesNum] = array('<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>', null);
		$helvNum = $next();
		$new[$helvNum] = array('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', null);
		$pageDicts = array();

		foreach ($this->pages as $idx => $pnum) {
			$mb = $this->mediaBox($pnum);
			$P = array('x' => $mb[0], 'y' => $mb[1], 'W' => $mb[2] - $mb[0], 'H' => $mb[3] - $mb[1]);
			$P['sx'] = $P['W'] / self::A4W;
			$P['sy'] = $P['H'] / self::A4H;
			$rec = array('removed' => 0, 'label' => '', 'done' => array());
			$res = $this->pageResources($pnum);
			foreach ($this->contentRefs($pnum) as $ref) {
				$this->editStream($ref, $res, array(1, 0, 0, 1, 0, 0), $P, $rec);
			}
			$this->pagesInfo[$idx + 1] = array('removed' => $rec['removed'], 'label' => trim(preg_replace('/\s+/u', ' ', $rec['label'])));

			// Overlay als Form-XObject mit eigenen Ressourcen
			$ov = $this->overlay($P, $contact, $customer);
			$ovNum = $next();
			$bbox = sprintf('[%.4F %.4F %.4F %.4F]', $mb[0], $mb[1], $mb[2], $mb[3]);
			$new[$ovNum] = array('<< /Type /XObject /Subtype /Form /BBox '.$bbox.' /Resources << /XObject << /MigLogo '.$logoNum.' 0 R >> /Font << /MigT '.$timesNum.' 0 R /MigH '.$helvNum.' 0 R >> >> >>', $ov);
			$preNum = $next();
			$new[$preNum] = array('<< >>', "q\n");
			$postNum = $next();
			$new[$postNum] = array('<< >>', "\nQ\nq /MigOverlay Do Q\n");

			// Seite: Ressourcen und Inhalt neu verknüpfen
			$resText = trim($res) !== '' ? trim($res) : '<< >>';
			$xo = $this->dictValue($resText, 'XObject');
			if ($xo !== null) {
				$xoText = (string) $this->resolve($xo);
				$xoNew = preg_replace('/>>\s*$/', ' /MigOverlay '.$ovNum.' 0 R >>', $xoText);
				$resNew = $this->setKey($resText, 'XObject', $xoNew);
			} else {
				$resNew = $this->setKey($resText, 'XObject', '<< /MigOverlay '.$ovNum.' 0 R >>');
			}
			$resNum = $next();
			$new[$resNum] = array($resNew, null);
			$contents = array($preNum.' 0 R');
			foreach ($this->contentRefs($pnum) as $ref) {
				$contents[] = $ref.' 0 R';
			}
			$contents[] = $postNum.' 0 R';
			$pd = $this->dict($pnum);
			$pd = $this->setKey($pd, 'Resources', $resNum.' 0 R');
			$pd = $this->setKey($pd, 'Contents', '['.implode(' ', $contents).']');
			$pageDicts[$pnum] = $pd;
		}

		// Datei schreiben
		$out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		$gens = array();
		foreach ($this->objects as $num => $o) {
			$d = isset($pageDicts[$num]) ? $pageDicts[$num] : $o['dict'];
			if (preg_match('#/Type\s*/(XRef|ObjStm)\b#', $d) || preg_match('#/Linearized\b#', $d)) {
				continue;
			}
			$gen = isset($o['gen']) ? (int) $o['gen'] : 0;
			$offsets[$num] = strlen($out);
			$gens[$num] = $gen;
			if ($o['stream'] !== null) {
				if (isset($this->newStreams[$num])) {
					$bytes = gzcompress($this->newStreams[$num]);
					$d = $this->removeKeys($d, array('Filter', 'DecodeParms', 'Length'));
					$d = $this->setKey($d, 'Filter', '/FlateDecode');
				} else {
					$bytes = (string) $this->getStream($num, false);
					$d = $this->removeKeys($d, array('Length'));
				}
				$d = $this->setKey($d, 'Length', (string) strlen($bytes));
				$out .= $num.' '.$gen." obj\n".$d."\nstream\n".$bytes."\nendstream\nendobj\n";
			} else {
				$out .= $num.' '.$gen." obj\n".$d."\nendobj\n";
			}
		}
		foreach ($new as $num => $o) {
			$offsets[$num] = strlen($out);
			$gens[$num] = 0;
			if ($o[1] !== null) {
				$bytes = (strpos($o[0], '/DCTDecode') !== false) ? $o[1] : gzcompress($o[1]);
				$d = (strpos($o[0], '/DCTDecode') !== false) ? $o[0] : $this->setKey($o[0], 'Filter', '/FlateDecode');
				$d = $this->setKey($d, 'Length', (string) strlen($bytes));
				$out .= $num." 0 obj\n".$d."\nstream\n".$bytes."\nendstream\nendobj\n";
			} else {
				$out .= $num." 0 obj\n".$o[0]."\nendobj\n";
			}
		}
		$size = max(array_keys($offsets)) + 1;
		$xref = strlen($out);
		$out .= "xref\n0 ".$size."\n0000000000 65535 f \n";
		for ($i = 1; $i < $size; $i++) {
			$out .= isset($offsets[$i]) ? sprintf("%010d %05d n \n", $offsets[$i], $gens[$i]) : "0000000000 65535 f \n";
		}
		$trailer = '/Size '.$size;
		if (preg_match_all('#/Root\s+(\d+\s+\d+\s+R)#', $this->data, $m)) {
			$trailer .= ' /Root '.end($m[1]);
		} else {
			$this->error = 'Katalog (Root) nicht gefunden';
			return false;
		}
		if (preg_match_all('#/Info\s+(\d+\s+\d+\s+R)#', $this->data, $m)) {
			$inf = end($m[1]);
			if (isset($offsets[(int) $inf])) {
				$trailer .= ' /Info '.$inf;
			}
		}
		$out .= "trailer\n<< ".$trailer." >>\nstartxref\n".$xref."\n%%EOF\n";
		return file_put_contents($outfile, $out) !== false;
	}

	/** Bezeichnung (z. B. „BVH Milan“) aus dem Plankopf der ersten Seite lesen, ohne umzuwandeln */
	public function readLabel()
	{
		if (!$this->pages) {
			return '';
		}
		$pnum = $this->pages[0];
		$mb = $this->mediaBox($pnum);
		$P = array('x' => $mb[0], 'y' => $mb[1], 'W' => $mb[2] - $mb[0], 'H' => $mb[3] - $mb[1]);
		$P['sx'] = $P['W'] / self::A4W;
		$P['sy'] = $P['H'] / self::A4H;
		$rec = array('removed' => 0, 'label' => '', 'done' => array());
		$keep = $this->newStreams;
		foreach ($this->contentRefs($pnum) as $ref) {
			$this->editStream($ref, $this->pageResources($pnum), array(1, 0, 0, 1, 0, 0), $P, $rec);
		}
		$this->newStreams = $keep;
		return trim(preg_replace('/\s+/u', ' ', $rec['label']));
	}

	// ------------------------------------------------------------------

	protected function mediaBox($pnum)
	{
		$num = $pnum;
		for ($i = 0; $i < 20 && $num; $i++) {
			$v = $this->dictValue($this->dict($num), 'MediaBox');
			if ($v !== null) {
				$v = (string) $this->resolve($v);
				if (preg_match_all('/[-+]?[0-9]*\.?[0-9]+/', $v, $m) && count($m[0]) >= 4) {
					return array_map('floatval', array_slice($m[0], 0, 4));
				}
			}
			$num = $this->refNum($this->dictValue($this->dict($num), 'Parent'));
		}
		return array(0, 0, self::A4W, self::A4H);
	}

	/** @return int[] Objektnummern der Inhaltsströme */
	protected function contentRefs($pnum)
	{
		$c = $this->dictValue($this->dict($pnum), 'Contents');
		if ($c === null) {
			return array();
		}
		if (preg_match('/^(\d+)\s+\d+\s+R$/', trim($c), $m) && strpos(ltrim($this->dict((int) $m[1])), '[') === 0) {
			$c = $this->dict((int) $m[1]);
		}
		preg_match_all('/(\d+)\s+\d+\s+R/', $c, $m);
		return array_map('intval', $m[1]);
	}

	protected function hit($x, $y, $r, $P)
	{
		$X = ($x - $P['x']) / $P['sx'];
		$Y = ($P['H'] - ($y - $P['y'])) / $P['sy'];
		return $X >= $r[0] && $X <= $r[2] && $Y >= $r[1] && $Y <= $r[3];
	}

	protected static function apply($m, $x, $y)
	{
		return array($m[0] * $x + $m[2] * $y + $m[4], $m[1] * $x + $m[3] * $y + $m[5]);
	}

	protected function editStream($num, $resources, $ctm, $P, &$rec)
	{
		if (isset($rec['done'][$num])) {
			return;
		}
		$rec['done'][$num] = 1;
		$bytes = isset($this->newStreams[$num]) ? $this->newStreams[$num] : $this->getStream($num);
		if ($bytes === null) {
			return;
		}
		$nb = $this->processStream($bytes, $resources, $ctm, $P, $rec);
		if ($nb !== null) {
			$this->newStreams[$num] = $nb;
		}
	}

	/**
	 * Text- und Bildoperatoren in den Bereichen entfernen
	 * @return string|null geänderter Inhalt oder null
	 */
	protected function processStream($s, $resources, $startCtm, $P, &$rec)
	{
		$ops = $this->posOps($s);
		$cut = array();
		$ctm = $startCtm;
		$stack = array();
		$I = array(1, 0, 0, 1, 0, 0);
		$tm = $I;
		$tlm = $I;
		$TL = 0;
		$xobjs = null;
		foreach ($ops as $o) {
			$nums = array();
			foreach ($o['args'] as $a) {
				if ($a['t'] === 'num') {
					$nums[] = $a['v'];
				}
			}
			switch ($o['op']) {
				case 'q':
					$stack[] = $ctm;
					break;
				case 'Q':
					if ($stack) {
						$ctm = array_pop($stack);
					}
					break;
				case 'cm':
					if (count($nums) == 6) {
						$ctm = self::mul($nums, $ctm);
					}
					break;
				case 'BT':
					$tm = $tlm = $I;
					break;
				case 'Tm':
					if (count($nums) == 6) {
						$tm = $tlm = $nums;
					}
					break;
				case 'Td':
				case 'TD':
					if (count($nums) >= 2) {
						if ($o['op'] === 'TD') {
							$TL = -$nums[1];
						}
						$tlm = self::mul(array(1, 0, 0, 1, $nums[0], $nums[1]), $tlm);
						$tm = $tlm;
					}
					break;
				case 'TL':
					if ($nums) {
						$TL = $nums[0];
					}
					break;
				case 'T*':
				case "'":
				case '"':
					$tlm = self::mul(array(1, 0, 0, 1, 0, -$TL), $tlm);
					$tm = $tlm;
					if ($o['op'] === 'T*') {
						break;
					}
					// fall through
				case 'Tj':
				case 'TJ':
					$m = self::mul($tm, $ctm);
					list($x, $y) = self::apply($m, 0, 0);
					if ($this->hit($x, $y, self::$RM_LEFT, $P) || $this->hit($x, $y, self::$RM_RIGHT, $P)) {
						$cut[] = array($o['st'], $o['en'], ($o['op'] === "'" || $o['op'] === '"') ? ' T* ' : ' ');
						$rec['removed']++;
					} elseif ($this->hit($x, $y, self::$LABEL, $P)) {
						$rec['label'] .= $this->showText($o['args'], $resources);
					}
					break;
				case 'Do':
					$name = null;
					foreach ($o['args'] as $a) {
						if ($a['t'] === 'name') {
							$name = $a['v'];
						}
					}
					if ($xobjs === null) {
						$xobjs = $this->xobjects($resources);
					}
					if ($name === null || !isset($xobjs[$name])) {
						break;
					}
					$ref = $xobjs[$name];
					$d = $this->dict($ref);
					if (preg_match('#/Subtype\s*/Image#', $d)) {
						list($x, $y) = self::apply($ctm, 0.5, 0.5);
						if ($this->hit($x, $y, self::$RM_LEFT, $P) || $this->hit($x, $y, self::$RM_RIGHT, $P)) {
							$cut[] = array($o['st'], $o['en'], ' ');
							$rec['removed']++;
						}
					} elseif (preg_match('#/Subtype\s*/Form#', $d)) {
						$fm = $I;
						$mv = $this->dictValue($d, 'Matrix');
						if ($mv && preg_match_all('/[-+]?[0-9]*\.?[0-9]+/', $mv, $mm) && count($mm[0]) == 6) {
							$fm = array_map('floatval', $mm[0]);
						}
						$fr = $this->dictValue($d, 'Resources');
						$fres = $fr !== null ? (string) $this->resolve($fr) : $resources;
						$this->editStream($ref, $fres, self::mul($fm, $ctm), $P, $rec);
					}
					break;
			}
		}
		if (!$cut) {
			return null;
		}
		usort($cut, function ($a, $b) {
			return $a[0] <=> $b[0];
		});
		$out = '';
		$pos = 0;
		foreach ($cut as $c) {
			if ($c[0] < $pos) {
				continue;
			}
			$out .= substr($s, $pos, $c[0] - $pos).$c[2];
			$pos = $c[1];
		}
		return $out.substr($s, $pos);
	}

	protected function showText($args, $resources)
	{
		$t = '';
		foreach ($args as $a) {
			if ($a['t'] === 'str') {
				$t .= self::decodeString($a['v']);
			}
		}
		return str_replace("\0", '', $t);
	}

	/**
	 * Tokenizer mit Positionen (wie in der Web-App)
	 * @return array<int,array{op:string,args:array,st:int,en:int}>
	 */
	protected function posOps($s)
	{
		$n = strlen($s);
		$i = 0;
		$ops = array();
		$args = array();
		$ws = " \t\r\n\f\0";
		$delim = '()<>[]{}/%';
		while ($i < $n) {
			$c = $s[$i];
			if (strpos($ws, $c) !== false) {
				$i++;
				continue;
			}
			if ($c === '%') {
				while ($i < $n && $s[$i] !== "\n" && $s[$i] !== "\r") {
					$i++;
				}
				continue;
			}
			$st = $i;
			if ($c === '(') {
				$d = 1;
				$i++;
				$buf = '';
				while ($i < $n && $d > 0) {
					$ch = $s[$i];
					if ($ch === '\\') {
						$nx = isset($s[$i + 1]) ? $s[$i + 1] : '';
						$map = array('n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f");
						if (isset($map[$nx])) {
							$buf .= $map[$nx];
							$i += 2;
						} elseif ($nx >= '0' && $nx <= '7') {
							$oct = '';
							$i++;
							while (strlen($oct) < 3 && $i < $n && $s[$i] >= '0' && $s[$i] <= '7') {
								$oct .= $s[$i++];
							}
							$buf .= chr(octdec($oct) & 255);
						} elseif ($nx === "\n" || $nx === "\r") {
							$i += 2;
						} else {
							$buf .= $nx;
							$i += 2;
						}
						continue;
					}
					if ($ch === '(') {
						$d++;
					} elseif ($ch === ')') {
						$d--;
						if ($d === 0) {
							$i++;
							break;
						}
					}
					$buf .= $ch;
					$i++;
				}
				$args[] = array('t' => 'str', 'st' => $st, 'en' => $i, 'v' => $buf);
				continue;
			}
			if ($c === '<') {
				if (isset($s[$i + 1]) && $s[$i + 1] === '<') {
					$args[] = array('t' => 'x', 'st' => $st, 'en' => $i + 2);
					$i += 2;
					continue;
				}
				$e = strpos($s, '>', $i);
				if ($e === false) {
					$e = $n - 1;
				}
				$hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($s, $i + 1, $e - $i - 1));
				if (strlen($hex) % 2) {
					$hex .= '0';
				}
				$args[] = array('t' => 'str', 'st' => $st, 'en' => $e + 1, 'v' => (string) hex2bin($hex));
				$i = $e + 1;
				continue;
			}
			if ($c === '>') {
				$i += (isset($s[$i + 1]) && $s[$i + 1] === '>') ? 2 : 1;
				$args[] = array('t' => 'x', 'st' => $st, 'en' => $i);
				continue;
			}
			if (strpos('[]{}', $c) !== false) {
				$args[] = array('t' => 'x', 'st' => $st, 'en' => $i + 1);
				$i++;
				continue;
			}
			if ($c === '/') {
				$i++;
				while ($i < $n && strpos($ws, $s[$i]) === false && strpos($delim, $s[$i]) === false) {
					$i++;
				}
				$args[] = array('t' => 'name', 'st' => $st, 'en' => $i, 'v' => substr($s, $st + 1, $i - $st - 1));
				continue;
			}
			while ($i < $n && strpos($ws, $s[$i]) === false && strpos($delim, $s[$i]) === false) {
				$i++;
			}
			if ($i === $st) {
				$i++;
				continue;
			}
			$w = substr($s, $st, $i - $st);
			if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $w)) {
				$args[] = array('t' => 'num', 'st' => $st, 'en' => $i, 'v' => (float) $w);
			} elseif ($w === 'true' || $w === 'false' || $w === 'null') {
				$args[] = array('t' => 'x', 'st' => $st, 'en' => $i);
			} else {
				$ops[] = array('op' => $w, 'args' => $args, 'st' => $args ? $args[0]['st'] : $st, 'en' => $i);
				$args = array();
				if ($w === 'ID') { // Inline-Bild überspringen
					if (preg_match('/\sEI(?=\s|$)/', $s, $mm, PREG_OFFSET_CAPTURE, $i)) {
						$e = $mm[0][1] + 3;
					} else {
						$e = $n;
					}
					$ops[] = array('op' => 'EI', 'args' => array(), 'st' => $e - 2, 'en' => $e);
					$i = $e;
				}
			}
		}
		return $ops;
	}

	/** Inhalt des Overlays (Koordinaten wie in der Web-App) */
	protected function overlay($P, $contact, $customer)
	{
		$X = function ($v) use ($P) {
			return $P['x'] + $v * $P['sx'];
		};
		$Y = function ($v) use ($P) {
			return $P['y'] + $P['H'] - $v * $P['sy'];
		};
		$o = sprintf("q %.4F 0 0 %.4F %.4F %.4F cm /MigLogo Do Q\n", 148.2 * $P['sx'], 47.2 * $P['sy'], $X(17.1), $Y(569.1));
		$fs = 14.4 * $P['sy'];
		$base = array(532.6, 550.3, 568.0);
		$o .= "0 g\n";
		foreach (array_values($contact) as $i => $t) {
			if ($i > 2 || trim($t) === '') {
				continue;
			}
			$enc = self::winAnsi(trim($t));
			$w = self::textWidth($enc, 'times') * $fs / 1000;
			$o .= sprintf("BT /MigT %.4F Tf %.4F %.4F Td (%s) Tj ET\n", $fs, $X(329.9) - $w, $Y($base[$i]), self::esc($enc));
		}
		$customer = trim($customer);
		if ($customer !== '') {
			$enc = self::winAnsi($customer);
			$size = 12 * $P['sy'];
			$maxW = 285 * $P['sx'];
			while (self::textWidth($enc, 'helvetica') * $size / 1000 > $maxW && $size > 6) {
				$size -= 0.5;
			}
			$o .= sprintf("BT /MigH %.4F Tf %.4F %.4F Td (%s) Tj ET\n", $size, $X(535), $Y(549.0), self::esc($enc));
		}
		return $o;
	}

	public static function winAnsi($t)
	{
		$r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', str_replace(array('đ', 'Đ'), array('d', 'D'), $t));
		return $r === false ? (string) mb_convert_encoding($t, 'Windows-1252', 'UTF-8') : $r;
	}

	public static function esc($t)
	{
		return strtr($t, array('\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => ' ', "\n" => ' '));
	}

	/** Breite in 1/1000 em für Standardschriften (Metriken aus TCPDF) */
	public static function textWidth($t, $font)
	{
		static $cws = array();
		if (!isset($cws[$font])) {
			$cw = array();
			$f = (defined('TCPDF_PATH') ? TCPDF_PATH : DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/').'fonts/'.$font.'.php';
			if (is_readable($f)) {
				include $f;
			}
			$cws[$font] = $cw;
		}
		$w = 0;
		$len = strlen($t);
		for ($i = 0; $i < $len; $i++) {
			$c = ord($t[$i]);
			$w += isset($cws[$font][$c]) ? $cws[$font][$c] : 500;
		}
		return $w;
	}

	protected static function mul($a, $b)
	{
		return array(
			$a[0] * $b[0] + $a[1] * $b[2], $a[0] * $b[1] + $a[1] * $b[3],
			$a[2] * $b[0] + $a[3] * $b[2], $a[2] * $b[1] + $a[3] * $b[3],
			$a[4] * $b[0] + $a[5] * $b[2] + $b[4], $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
		);
	}

	/** Schlüssel in einem Dictionary-Text entfernen */
	protected function removeKeys($dict, $keys)
	{
		foreach ($keys as $k) {
			for ($guard = 0; $guard < 3; $guard++) {
				$v = $this->dictValue($dict, $k);
				if ($v === null) {
					break;
				}
				$re = '#/'.preg_quote($k, '#').'(?![A-Za-z0-9_.\-])\s*'.preg_quote($v, '#').'#';
				$nd = preg_replace($re, ' ', $dict, 1);
				if ($nd === null || $nd === $dict) {
					break;
				}
				$dict = $nd;
			}
		}
		return $dict;
	}

	/** Schlüssel setzen (vorhandenen ersetzen) */
	protected function setKey($dict, $key, $value)
	{
		$dict = $this->removeKeys(trim($dict), array($key));
		$dict = rtrim($dict);
		if (substr($dict, -2) !== '>>') {
			$dict = '<< >>';
		}
		return substr($dict, 0, -2).' /'.$key.' '.$value.' >>';
	}
}
