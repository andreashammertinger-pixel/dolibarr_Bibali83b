<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kapdfreader.class.php
 * \brief   Kleiner, eigenständiger PDF-Leser für Nobilia/CARAT-PDFs:
 *          Seiten, Textzeilen (mit x-Positionen) und Bilder (JPEG / RGB).
 *          Ohne externe Programme (kein pdftotext nötig).
 */

class KaPdfReader
{
	/** @var string */
	protected $data = '';
	/** @var array<int,array{dict:string,stream:?string}> */
	protected $objects = array();
	/** @var array<int,int> Seitenobjekte in Reihenfolge */
	protected $pages = array();
	/** @var string */
	public $error = '';

	/**
	 * @param string $data Roher PDF-Inhalt
	 */
	public function __construct($data)
	{
		$this->data = (string) $data;
		$this->parseObjects();
		$this->collectPages();
	}

	/**
	 * @param string $file Pfad
	 * @return KaPdfReader
	 */
	public static function fromFile($file)
	{
		return new static((string) @file_get_contents($file));
	}

	/** @return int */
	public function getPageCount()
	{
		return count($this->pages);
	}

	// ------------------------------------------------------------------
	// Objekte
	// ------------------------------------------------------------------

	protected function parseObjects()
	{
		$d = $this->data;
		if (!preg_match_all('/(?<![0-9])(\d+)\s+(\d+)\s+obj\b/', $d, $m, PREG_OFFSET_CAPTURE)) {
			$this->error = 'Keine PDF-Objekte gefunden';
			return;
		}
		$n = count($m[0]);
		for ($i = 0; $i < $n; $i++) {
			$num = (int) $m[1][$i][0];
			$start = $m[0][$i][1] + strlen($m[0][$i][0]);
			$end = strpos($d, 'endobj', $start);
			if ($end === false) {
				$end = strlen($d);
			}
			$body = substr($d, $start, $end - $start);
			$stream = null;
			$dict = $body;
			$sp = strpos($body, 'stream');
			if ($sp !== false && substr($body, $sp - 3, 3) !== 'end') {
				$dict = substr($body, 0, $sp);
				$s = $sp + 6;
				if (isset($body[$s]) && $body[$s] === "\r") {
					$s++;
				}
				if (isset($body[$s]) && $body[$s] === "\n") {
					$s++;
				}
				$len = null;
				if (preg_match('#/Length\s+(\d+)(?!\s+\d+\s+R)#', $dict, $lm)) {
					$len = (int) $lm[1];
				} elseif (preg_match('#/Length\s+(\d+)\s+(\d+)\s+R#', $dict, $lm)) {
					$len = -((int) $lm[1]); // indirekt, später auflösen
				}
				$es = strrpos($body, 'endstream');
				$raw = substr($body, $s, ($es !== false ? $es : strlen($body)) - $s);
				$stream = array('raw' => $raw, 'len' => $len);
			}
			$this->objects[$num] = array('dict' => trim($dict), 'stream' => $stream, 'gen' => (int) $m[2][$i][0]);
		}
		// indirekte Längen auflösen
		foreach ($this->objects as $num => $o) {
			if ($o['stream'] && $o['stream']['len'] !== null && $o['stream']['len'] < 0) {
				$ref = -$o['stream']['len'];
				if (isset($this->objects[$ref]) && preg_match('/^\s*(\d+)/', $this->objects[$ref]['dict'], $lm)) {
					$this->objects[$num]['stream']['len'] = (int) $lm[1];
				} else {
					$this->objects[$num]['stream']['len'] = null;
				}
			}
		}
		// Objektströme (PDF 1.5+)
		foreach ($this->objects as $num => $o) {
			if ($o['stream'] && preg_match('#/Type\s*/ObjStm#', $o['dict'])) {
				$content = $this->getStream($num);
				if ($content === null || !preg_match('#/First\s+(\d+)#', $o['dict'], $fm) || !preg_match('#/N\s+(\d+)#', $o['dict'], $nm)) {
					continue;
				}
				$first = (int) $fm[1];
				$head = preg_split('/\s+/', trim(substr($content, 0, $first)));
				$cnt = (int) $nm[1];
				$list = array();
				for ($k = 0; $k < $cnt && isset($head[2 * $k + 1]); $k++) {
					$list[] = array((int) $head[2 * $k], (int) $head[2 * $k + 1]);
				}
				foreach ($list as $k => $e) {
					$s = $first + $e[1];
					$l = isset($list[$k + 1]) ? $first + $list[$k + 1][1] - $s : strlen($content) - $s;
					if (!isset($this->objects[$e[0]])) {
						$this->objects[$e[0]] = array('dict' => trim(substr($content, $s, $l)), 'stream' => null);
					}
				}
			}
		}
	}

	/**
	 * Dekodierter Strom eines Objekts
	 * @param int  $num
	 * @param bool $decode Filter anwenden (DCTDecode bleibt immer roh)
	 * @return string|null
	 */
	public function getStream($num, $decode = true)
	{
		if (empty($this->objects[$num]['stream'])) {
			return null;
		}
		$st = $this->objects[$num]['stream'];
		$raw = $st['raw'];
		if ($st['len'] !== null && $st['len'] > 0 && $st['len'] <= strlen($raw)) {
			$raw = substr($raw, 0, $st['len']);
		} else {
			$raw = rtrim($raw, "\r\n");
		}
		if (!$decode) {
			return $raw;
		}
		$dict = $this->objects[$num]['dict'];
		if (preg_match('#/Filter\s*\[?\s*/FlateDecode#', $dict)) {
			$out = @gzuncompress($raw);
			if ($out === false) {
				$out = @gzinflate(substr($raw, 2));
			}
			if ($out === false) {
				return null;
			}
			return $out;
		}
		return $raw;
	}

	/** @return string */
	protected function dict($num)
	{
		return isset($this->objects[$num]) ? $this->objects[$num]['dict'] : '';
	}

	/**
	 * Wert eines Schlüssels (Rohtext): Referenz, Name, Zahl, Array oder Dictionary
	 * @param string $dict
	 * @param string $key ohne Schrägstrich
	 * @return string|null
	 */
	protected function dictValue($dict, $key)
	{
		$p = strpos($dict, '/'.$key);
		while ($p !== false) {
			$after = $p + strlen($key) + 1;
			$c = isset($dict[$after]) ? $dict[$after] : '';
			if ($c === '' || !preg_match('/[A-Za-z0-9_\-.]/', $c)) {
				break;
			}
			$p = strpos($dict, '/'.$key, $after);
		}
		if ($p === false) {
			return null;
		}
		$s = ltrim(substr($dict, $p + strlen($key) + 1));
		if ($s === '') {
			return null;
		}
		if (substr($s, 0, 2) === '<<') {
			return $this->balanced($s, '<<', '>>');
		}
		if ($s[0] === '[') {
			return $this->balanced($s, '[', ']');
		}
		if (preg_match('/^(\d+)\s+(\d+)\s+R/', $s, $m)) {
			return $m[0];
		}
		if (preg_match('#^(/[^\s/<>\[\]()]+|[-+0-9.]+|true|false|\([^)]*\))#', $s, $m)) {
			return $m[1];
		}
		return null;
	}

	protected function balanced($s, $open, $close)
	{
		$depth = 0;
		$len = strlen($s);
		for ($i = 0; $i < $len; $i++) {
			if (substr($s, $i, strlen($open)) === $open) {
				$depth++;
				$i += strlen($open) - 1;
			} elseif (substr($s, $i, strlen($close)) === $close) {
				$depth--;
				$i += strlen($close) - 1;
				if ($depth === 0) {
					return substr($s, 0, $i + 1);
				}
			}
		}
		return $s;
	}

	/** Referenz "12 0 R" → Dict-Inhalt, sonst Wert selbst */
	protected function resolve($val)
	{
		if ($val !== null && preg_match('/^(\d+)\s+\d+\s+R$/', trim($val), $m)) {
			return $this->dict((int) $m[1]);
		}
		return $val;
	}

	protected function refNum($val)
	{
		return ($val !== null && preg_match('/^(\d+)\s+\d+\s+R/', trim($val), $m)) ? (int) $m[1] : 0;
	}

	protected function collectPages()
	{
		$root = 0;
		foreach ($this->objects as $num => $o) {
			if (preg_match('#/Type\s*/Catalog#', $o['dict'])) {
				$root = $this->refNum($this->dictValue($o['dict'], 'Pages'));
				break;
			}
		}
		if ($root) {
			$this->walkPages($root, 0);
		}
		if (!$this->pages) { // Notlösung: alle Seitenobjekte in Dateireihenfolge
			foreach ($this->objects as $num => $o) {
				if (preg_match('#/Type\s*/Page(?!s)#', $o['dict'])) {
					$this->pages[] = $num;
				}
			}
		}
	}

	protected function walkPages($num, $depth)
	{
		if ($depth > 30) {
			return;
		}
		$d = $this->dict($num);
		if (preg_match('#/Type\s*/Pages#', $d)) {
			$kids = $this->resolve($this->dictValue($d, 'Kids'));
			if ($kids && preg_match_all('/(\d+)\s+\d+\s+R/', $kids, $m)) {
				foreach ($m[1] as $k) {
					$this->walkPages((int) $k, $depth + 1);
				}
			}
		} elseif (preg_match('#/Type\s*/Page#', $d)) {
			$this->pages[] = $num;
		}
	}

	/** Ressourcen-Dictionary einer Seite (mit Vererbung) */
	protected function pageResources($pagenum)
	{
		$num = $pagenum;
		for ($i = 0; $i < 20 && $num; $i++) {
			$d = $this->dict($num);
			$r = $this->dictValue($d, 'Resources');
			if ($r !== null) {
				return (string) $this->resolve($r);
			}
			$num = $this->refNum($this->dictValue($d, 'Parent'));
		}
		return '';
	}

	/** @return array<string,int> Name → Objektnummer */
	protected function xobjects($resources)
	{
		$out = array();
		$x = $this->resolve($this->dictValue($resources, 'XObject'));
		if ($x && preg_match_all('#/([^\s/<>\[\]]+)\s+(\d+)\s+\d+\s+R#', $x, $m)) {
			foreach ($m[1] as $k => $name) {
				$out[$name] = (int) $m[2][$k];
			}
		}
		return $out;
	}

	protected function pageContent($pagenum)
	{
		$c = $this->dictValue($this->dict($pagenum), 'Contents');
		$buf = '';
		if ($c === null) {
			return '';
		}
		if (preg_match('/^(\d+)\s+\d+\s+R$/', trim($c), $m) && strpos($this->dict((int) $m[1]), '[') === 0) {
			$c = $this->dict((int) $m[1]);
		}
		if (preg_match_all('/(\d+)\s+\d+\s+R/', $c, $m)) {
			foreach ($m[1] as $ref) {
				$buf .= $this->getStream((int) $ref)."\n";
			}
		}
		return $buf;
	}

	// ------------------------------------------------------------------
	// Text
	// ------------------------------------------------------------------

	/**
	 * Textzeilen einer Seite, von oben nach unten.
	 * @param int $page 1-basiert
	 * @return array<int,array{y:float,chunks:array<int,array{x:float,t:string}>,text:string}>
	 */
	public function getPageLines($page)
	{
		if (!isset($this->pages[$page - 1])) {
			return array();
		}
		$pnum = $this->pages[$page - 1];
		$res = $this->pageResources($pnum);
		$chunks = array();
		$this->runContent($this->pageContent($pnum), $res, array(1, 0, 0, 1, 0, 0), $chunks, 0);

		// nach Zeilen gruppieren (Toleranz 1.5 pt)
		usort($chunks, function ($a, $b) {
			return ($b['y'] <=> $a['y']) ?: ($a['x'] <=> $b['x']);
		});
		$lines = array();
		foreach ($chunks as $c) {
			if ($c['t'] === '' || trim($c['t']) === '') {
				continue;
			}
			$n = count($lines);
			if ($n && abs($lines[$n - 1]['y'] - $c['y']) < 1.5) {
				$lines[$n - 1]['chunks'][] = array('x' => $c['x'], 't' => $c['t']);
			} else {
				$lines[] = array('y' => $c['y'], 'chunks' => array(array('x' => $c['x'], 't' => $c['t'])));
			}
		}
		foreach ($lines as &$l) {
			usort($l['chunks'], function ($a, $b) {
				return $a['x'] <=> $b['x'];
			});
			$parts = array();
			foreach ($l['chunks'] as $ch) {
				$parts[] = trim($ch['t']);
			}
			$l['text'] = implode("\t", $parts);
		}
		unset($l);
		return $lines;
	}

	/** Ganzer Text des Dokuments (Zeilen mit Tab zwischen Spalten) */
	public function getText()
	{
		$out = array();
		for ($p = 1; $p <= $this->getPageCount(); $p++) {
			foreach ($this->getPageLines($p) as $l) {
				$out[] = $l['text'];
			}
			$out[] = "\f";
		}
		return implode("\n", $out);
	}

	protected static function mul($a, $b)
	{
		return array(
			$a[0] * $b[0] + $a[1] * $b[2], $a[0] * $b[1] + $a[1] * $b[3],
			$a[2] * $b[0] + $a[3] * $b[2], $a[2] * $b[1] + $a[3] * $b[3],
			$a[4] * $b[0] + $a[5] * $b[2] + $b[4], $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
		);
	}

	/**
	 * Inhaltsstrom ausführen (nur Text- und Matrix-Operatoren)
	 */
	protected function runContent($content, $resources, $ctm, &$chunks, $depth)
	{
		if ($depth > 5 || $content === '') {
			return;
		}
		$tokens = $this->tokenize($content);
		$stack = array();
		$gstack = array();
		$tm = array(1, 0, 0, 1, 0, 0);
		$tlm = $tm;
		$fs = 10;
		$tl = 0;
		$xobj = null;
		$twobyte = null;
		foreach ($tokens as $tok) {
			if ($tok[0] !== 'op') {
				$stack[] = $tok;
				continue;
			}
			$op = $tok[1];
			$nums = array();
			foreach ($stack as $s) {
				if ($s[0] === 'num') {
					$nums[] = (float) $s[1];
				}
			}
			switch ($op) {
				case 'q':
					$gstack[] = $ctm;
					break;
				case 'Q':
					if ($gstack) {
						$ctm = array_pop($gstack);
					}
					break;
				case 'cm':
					if (count($nums) >= 6) {
						$ctm = self::mul(array_slice($nums, -6), $ctm);
					}
					break;
				case 'BT':
					$tm = $tlm = array(1, 0, 0, 1, 0, 0);
					break;
				case 'Tf':
					if ($nums) {
						$fs = end($nums);
					}
					foreach ($stack as $s) {
						if ($s[0] === 'name') {
							$twobyte = $this->isTwoByteFont($resources, $s[1]);
						}
					}
					break;
				case 'TL':
					if ($nums) {
						$tl = end($nums);
					}
					break;
				case 'Tm':
					if (count($nums) >= 6) {
						$tm = $tlm = array_slice($nums, -6);
					}
					break;
				case 'Td':
				case 'TD':
					if (count($nums) >= 2) {
						list($tx, $ty) = array_slice($nums, -2);
						if ($op === 'TD') {
							$tl = -$ty;
						}
						$tlm = self::mul(array(1, 0, 0, 1, $tx, $ty), $tlm);
						$tm = $tlm;
					}
					break;
				case 'T*':
				case "'":
				case '"':
					$tlm = self::mul(array(1, 0, 0, 1, 0, -$tl), $tlm);
					$tm = $tlm;
					if ($op === 'T*') {
						break;
					}
					// fall through: Text zeigen
				case 'Tj':
				case 'TJ':
					$txt = '';
					foreach ($stack as $s) {
						if ($s[0] === 'str') {
							$txt .= self::decodeString($s[1], $twobyte);
						} elseif ($s[0] === 'arr') {
							foreach ($s[1] as $e) {
								if ($e[0] === 'str') {
									$txt .= self::decodeString($e[1], $twobyte);
								} elseif ($e[0] === 'num' && (float) $e[1] < -250) {
									$txt .= ' ';
								}
							}
						}
					}
					$m = self::mul($tm, $ctm);
					$chunks[] = array('x' => round($m[4], 2), 'y' => round($m[5], 2), 't' => $txt);
					// grobe Fortschreibung, damit Folgetext rechts landet
					$tm[4] += strlen($txt) * $fs * 0.5;
					break;
				case 'Do':
					$name = null;
					foreach ($stack as $s) {
						if ($s[0] === 'name') {
							$name = $s[1];
						}
					}
					if ($xobj === null) {
						$xobj = $this->xobjects($resources);
					}
					if ($name !== null && isset($xobj[$name])) {
						$fd = $this->dict($xobj[$name]);
						if (preg_match('#/Subtype\s*/Form#', $fd)) {
							$fm = array(1, 0, 0, 1, 0, 0);
							$mv = $this->dictValue($fd, 'Matrix');
							if ($mv && preg_match_all('/[-+0-9.]+/', $mv, $mm) && count($mm[0]) == 6) {
								$fm = array_map('floatval', $mm[0]);
							}
							$fres = $this->dictValue($fd, 'Resources');
							$fres = $fres !== null ? (string) $this->resolve($fres) : $resources;
							$this->runContent((string) $this->getStream($xobj[$name]), $fres, self::mul($fm, $ctm), $chunks, $depth + 1);
						}
					}
					break;
			}
			$stack = array();
		}
	}

	/**
	 * Zerlegt einen Inhaltsstrom in Tokens
	 * @return array<int,array>
	 */
	protected function tokenize($s)
	{
		$out = array();
		$len = strlen($s);
		$i = 0;
		$arrstack = array();
		while ($i < $len) {
			$c = $s[$i];
			if (ctype_space($c)) {
				$i++;
				continue;
			}
			$tok = null;
			if ($c === '%') {
				while ($i < $len && $s[$i] !== "\n" && $s[$i] !== "\r") {
					$i++;
				}
				continue;
			} elseif ($c === '(') {
				$depth = 1;
				$i++;
				$buf = '';
				while ($i < $len && $depth > 0) {
					$ch = $s[$i];
					if ($ch === '\\') {
						$i++;
						$n = isset($s[$i]) ? $s[$i] : '';
						$map = array('n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", '(' => '(', ')' => ')', '\\' => '\\');
						if (isset($map[$n])) {
							$buf .= $map[$n];
							$i++;
						} elseif (ctype_digit($n) && $n < '8') {
							$oct = '';
							while (strlen($oct) < 3 && isset($s[$i]) && $s[$i] >= '0' && $s[$i] <= '7') {
								$oct .= $s[$i++];
							}
							$buf .= chr(octdec($oct) & 0xFF);
						} elseif ($n === "\r" || $n === "\n") {
							$i++;
							if ($n === "\r" && isset($s[$i]) && $s[$i] === "\n") {
								$i++;
							}
						} else {
							$buf .= $n; // unbekannte Escape: Zeichen selbst
							$i++;
						}
						continue;
					}
					if ($ch === '(') {
						$depth++;
					} elseif ($ch === ')') {
						$depth--;
						if ($depth === 0) {
							$i++;
							break;
						}
					}
					$buf .= $ch;
					$i++;
				}
				$tok = array('str', $buf);
			} elseif ($c === '<' && isset($s[$i + 1]) && $s[$i + 1] === '<') {
				// Inline-Dictionary überspringen
				$depth = 0;
				while ($i < $len) {
					if (substr($s, $i, 2) === '<<') {
						$depth++;
						$i += 2;
					} elseif (substr($s, $i, 2) === '>>') {
						$depth--;
						$i += 2;
						if ($depth === 0) {
							break;
						}
					} else {
						$i++;
					}
				}
				continue;
			} elseif ($c === '<') {
				$e = strpos($s, '>', $i);
				if ($e === false) {
					$e = $len;
				}
				$hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($s, $i + 1, $e - $i - 1));
				if (strlen($hex) % 2) {
					$hex .= '0';
				}
				$tok = array('str', (string) hex2bin($hex));
				$i = $e + 1;
			} elseif ($c === '[') {
				$arrstack[] = array();
				$i++;
				continue;
			} elseif ($c === ']') {
				$i++;
				$arr = $arrstack ? array_pop($arrstack) : array();
				$tok = array('arr', $arr);
			} elseif ($c === '/') {
				$j = $i + 1;
				while ($j < $len && !ctype_space($s[$j]) && strpos('/[]()<>{}%', $s[$j]) === false) {
					$j++;
				}
				$tok = array('name', substr($s, $i + 1, $j - $i - 1));
				$i = $j;
			} elseif (preg_match('/[-+.0-9]/', $c)) {
				$j = $i + 1;
				while ($j < $len && preg_match('/[.0-9]/', $s[$j])) {
					$j++;
				}
				$tok = array('num', substr($s, $i, $j - $i));
				$i = $j;
			} else {
				$j = $i;
				while ($j < $len && !ctype_space($s[$j]) && strpos('/[]()<>{}%', $s[$j]) === false) {
					$j++;
				}
				if ($j === $i) {
					$j++;
				}
				$word = substr($s, $i, $j - $i);
				$i = $j;
				if ($word === 'BI') { // Inline-Bild überspringen
					$e = strpos($s, 'EI', $i);
					$i = ($e === false) ? $len : $e + 2;
					continue;
				}
				if ($word === 'true' || $word === 'false' || $word === 'null') {
					$tok = array('num', '0');
				} else {
					$tok = array('op', $word);
				}
			}
			if ($tok === null) {
				continue;
			}
			if ($arrstack && $tok[0] !== 'op') {
				$arrstack[count($arrstack) - 1][] = $tok;
			} elseif (!$arrstack) {
				$out[] = $tok;
			}
		}
		return $out;
	}

	/** @var array<string,bool> */
	protected $fontcache = array();

	/** Type0-Schrift (2 Byte je Zeichen)? */
	protected function isTwoByteFont($resources, $name)
	{
		$key = md5($resources).$name;
		if (!isset($this->fontcache[$key])) {
			$fonts = $this->resolve($this->dictValue($resources, 'Font'));
			$res = false;
			if ($fonts && preg_match('#/'.preg_quote($name, '#').'\s+(\d+)\s+\d+\s+R#', $fonts, $m)) {
				$res = (bool) preg_match('#/Subtype\s*/Type0#', $this->dict((int) $m[1]));
			}
			$this->fontcache[$key] = $res;
		}
		return $this->fontcache[$key];
	}

	/**
	 * Zeichenkette nach UTF-8: Type0 (UCS-2, wie CARAT/PDF Xpansion) oder WinAnsi
	 * @param string    $b
	 * @param bool|null $twobyte true = UTF-16BE, null = automatisch erkennen
	 */
	public static function decodeString($b, $twobyte = null)
	{
		$l = strlen($b);
		if ($l === 0) {
			return '';
		}
		if ($twobyte && $l % 2 === 0) {
			return (string) mb_convert_encoding($b, 'UTF-8', 'UTF-16BE');
		}
		if (substr($b, 0, 2) === "\xFE\xFF") {
			return (string) mb_convert_encoding(substr($b, 2), 'UTF-8', 'UTF-16BE');
		}
		if ($l % 2 === 0) {
			$allzero = true;
			for ($i = 0; $i < $l; $i += 2) {
				if ($b[$i] !== "\x00") {
					$allzero = false;
					break;
				}
			}
			if ($allzero) {
				$t = '';
				for ($i = 1; $i < $l; $i += 2) {
					$t .= $b[$i];
				}
				$b = $t;
			}
		}
		return (string) mb_convert_encoding($b, 'UTF-8', 'Windows-1252');
	}

	// ------------------------------------------------------------------
	// Bilder
	// ------------------------------------------------------------------

	/**
	 * Bilder einer Seite (bzw. aller Seiten bei $page = 0)
	 * @return array<int,array{obj:int,page:int,width:int,height:int,filter:string}>
	 */
	public function getImages($page = 0)
	{
		$out = array();
		$seen = array();
		foreach ($this->pages as $idx => $pnum) {
			if ($page && $idx + 1 != $page) {
				continue;
			}
			$queue = array($this->pageResources($pnum));
			$guard = 0;
			while ($queue && $guard++ < 50) {
				$res = array_shift($queue);
				foreach ($this->xobjects($res) as $name => $num) {
					$d = $this->dict($num);
					if (preg_match('#/Subtype\s*/Image#', $d)) {
						if (isset($seen[$idx.'-'.$num])) {
							continue;
						}
						$seen[$idx.'-'.$num] = 1;
						$f = $this->dictValue($d, 'Filter');
						$out[] = array(
							'obj' => $num, 'page' => $idx + 1,
							'width' => (int) $this->dictValue($d, 'Width'), 'height' => (int) $this->dictValue($d, 'Height'),
							'filter' => trim((string) $f, " /[]"),
						);
					} elseif (preg_match('#/Subtype\s*/Form#', $d)) {
						$fr = $this->dictValue($d, 'Resources');
						if ($fr !== null) {
							$queue[] = (string) $this->resolve($fr);
						}
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Bild als JPEG-Datei speichern (DCT direkt, sonst über GD aus RGB/Grau)
	 * @return bool
	 */
	public function saveImageAsJpeg($obj, $file)
	{
		$d = $this->dict($obj);
		$f = (string) $this->dictValue($d, 'Filter');
		if (strpos($f, 'DCTDecode') !== false) {
			$raw = $this->getStream($obj, false);
			if (strpos($f, 'FlateDecode') !== false) {
				$raw = @gzuncompress($raw);
			}
			return $raw && file_put_contents($file, $raw) !== false;
		}
		if (!function_exists('imagecreatetruecolor')) {
			return false;
		}
		$w = (int) $this->dictValue($d, 'Width');
		$h = (int) $this->dictValue($d, 'Height');
		$bpc = (int) ($this->dictValue($d, 'BitsPerComponent') ?: 8);
		$cs = (string) $this->resolve($this->dictValue($d, 'ColorSpace'));
		$comp = (strpos($cs, 'Gray') !== false) ? 1 : 3;
		$raw = $this->getStream($obj, true);
		if ($bpc != 8 || $w <= 0 || $h <= 0 || $raw === null) {
			return false;
		}
		$dp = $this->dictValue($d, 'DecodeParms');
		if ($dp && preg_match('#/Predictor\s+(\d+)#', $dp, $pm) && (int) $pm[1] >= 10) {
			$raw = self::unpredictPng($raw, $w * $comp, $comp);
		}
		if (strlen($raw) < $w * $h * $comp) {
			return false;
		}
		$im = imagecreatetruecolor($w, $h);
		$p = 0;
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) {
				if ($comp == 3) {
					$col = (ord($raw[$p]) << 16) | (ord($raw[$p + 1]) << 8) | ord($raw[$p + 2]);
				} else {
					$g = ord($raw[$p]);
					$col = ($g << 16) | ($g << 8) | $g;
				}
				imagesetpixel($im, $x, $y, $col);
				$p += $comp;
			}
		}
		$ok = imagejpeg($im, $file, 90);
		imagedestroy($im);
		return $ok;
	}

	protected static function unpredictPng($data, $rowlen, $bpp)
	{
		$out = '';
		$prev = str_repeat("\0", $rowlen);
		$len = strlen($data);
		for ($p = 0; $p + $rowlen < $len + 1 && $p < $len; $p += $rowlen + 1) {
			$type = ord($data[$p]);
			$row = substr($data, $p + 1, $rowlen);
			$cur = '';
			for ($i = 0; $i < strlen($row); $i++) {
				$a = $i >= $bpp ? ord($cur[$i - $bpp]) : 0;
				$b = ord($prev[$i]);
				$c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
				$x = ord($row[$i]);
				switch ($type) {
					case 1: $x += $a; break;
					case 2: $x += $b; break;
					case 3: $x += (int) floor(($a + $b) / 2); break;
					case 4:
						$pp = $a + $b - $c;
						$pa = abs($pp - $a); $pb = abs($pp - $b); $pc = abs($pp - $c);
						$x += ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
						break;
				}
				$cur .= chr($x & 0xFF);
			}
			$out .= $cur;
			$prev = $cur;
		}
		return $out;
	}
}
