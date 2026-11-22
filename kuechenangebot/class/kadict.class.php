<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kadict.class.php
 * \brief   Fachwörterbuch Nobilia/Küche Deutsch → Kroatisch / Englisch für die Übersetzungskopie.
 *          Ganze Ausdrücke zuerst, danach einzelne Wörter. Unbekanntes bleibt deutsch.
 *          Eigene Ergänzungen: Konstante KA_DICT_<hr|en> mit Zeilen "deutsch = übersetzung".
 */

class KaDict
{
	/** @var array<string,array<string,string>> */
	protected static $dict = array(
		'hr' => array(
			// Merkmal-Bezeichnungen
			'Serie' => 'Serija', 'Programm' => 'Program', 'Front-Kombination' => 'Kombinacija fronte',
			'Korpusausführung' => 'Izvedba korpusa', 'Korpusfarbe außen' => 'Boja korpusa izvana', 'Korpusfarbe innen' => 'Boja korpusa iznutra',
			'Öffnungsunterstützung Schubkasten/Auszug' => 'Potpora otvaranju ladica', 'Griff-Kombination' => 'Kombinacija ručki', 'Grifflage' => 'Položaj ručki',
			'Auszugsausführung' => 'Izvedba izvlakača', 'Innenauszug-Ausführung' => 'Izvedba unutarnjeg izvlakača',
			'Nischenausführung' => 'Izvedba niše', 'Nischenfarbe' => 'Boja niše', 'Kanten- und Hintergrundfarbe Nischenverkleidung' => 'Boja rubova i pozadine obloge niše', 'Kanten- und Hintergrundfarbe' => 'Boja rubova i pozadine',
			'Wandschrankbeleuchtung unten' => 'Rasvjeta zidnih elemenata dolje', 'Wandschrankbeleuchtung oben' => 'Rasvjeta zidnih elemenata gore',
			'Farben Zubehör/Leuchten' => 'Boje pribora/svjetiljki', 'Sockelausführung' => 'Izvedba sokla', 'Sockelfarbe' => 'Boja sokla', 'Sockelhöhe' => 'Visina sokla',
			'Arbeitsplatten-Ausführung' => 'Izvedba radne ploče', 'Arbeitsplatten-Farbe' => 'Boja radne ploče', 'Arbeitsplatten-Kantenausf.' => 'Izvedba ruba radne ploče',
			'Arbeitsplatten-Kantenfarbe' => 'Boja ruba radne ploče',
			// Werte (Ausdrücke)
			'Eiche Sierra Nachbildung' => 'imitacija hrasta Sierra', 'Kunststoff matt, N-Kante' => 'plastika mat, N-rub', 'N-Kante, matt' => 'N-rub, mat',
			'Farbgleich im APL-Dekor' => 'u boji dekora radne ploče', 'ohne Öffnungsunterstützung' => 'bez potpore otvaranju', 'ohne Beleuchtung' => 'bez rasvjete',
			'Waagerecht/Waagerecht' => 'vodoravno/vodoravno', 'Standard (Reling)' => 'standard (reling)', 'Standard (Holzvorderstück)' => 'standard (drvena prednja strana)',
			'Dekor Matt' => 'dekor mat', 'Schwarz Hochglanz' => 'crna visoki sjaj', 'Metallgriff Schwarz' => 'metalna ručka crna', 'Weiß Hochglanz' => 'bijela visoki sjaj',
			'Möbel' => 'namještaj', 'Kunststoff' => 'plastika', 'Glaskeramik-Strahlungskochfeld' => 'staklokeramička ploča za kuhanje',
			'Integrierter Kühlautomat' => 'ugradbeni hladnjak', 'Vollintegrierter Geschirrspüler' => 'potpuno ugradbena perilica posuđa',
			'Teilintegrierter Geschirrspüler' => 'djelomično ugradbena perilica posuđa', 'Einbauherd' => 'ugradbeni štednjak', 'Einbaubackofen' => 'ugradbena pećnica',
			'Mikrowelle' => 'mikrovalna pećnica', 'Flachschirmhaube' => 'ravna napa', 'Aktivkohlefilter' => 'filtar s aktivnim ugljenom',
			'Hochdruck-Einhebelmischbatterie' => 'visokotlačna jednoručna miješalica', 'Mischbatterie' => 'miješalica', 'verchromt' => 'kromirano',
			'Edelstahl Naturfinish' => 'nehrđajući čelik prirodne završne obrade', 'Einbau-Spüle' => 'ugradbeni sudoper', 'Induktionskochfeld' => 'indukcijska ploča za kuhanje',
			'LED Nischenleuchte' => 'LED svjetiljka za nišu', 'herdgebunden' => 'vezano uz štednjak', 'Rahmenlos' => 'bez okvira', 'mit Linksanschlag' => 'otvaranje lijevo',
			'mit Rechtsanschlag' => 'otvaranje desno', 'Nachbildung' => 'imitacija',
			// Wörter
			'Weiß' => 'bijela', 'Schwarz' => 'crna', 'Grau' => 'siva', 'Anthrazit' => 'antracit', 'Beige' => 'bež', 'Eiche' => 'hrast', 'Nussbaum' => 'orah',
			'Kaschmir' => 'kašmir', 'Sand' => 'pijesak', 'Grün' => 'zelena', 'Blau' => 'plava', 'Matt' => 'mat', 'matt' => 'mat', 'Hochglanz' => 'visoki sjaj',
			'Lack' => 'lak', 'Glas' => 'staklo', 'Holz' => 'drvo', 'Edelstahl' => 'nehrđajući čelik', 'Keramik' => 'keramika', 'Quarzstein' => 'kvarcni kamen',
			'Standard' => 'standard', 'Kühlautomat' => 'hladnjak', 'Geschirrspüler' => 'perilica posuđa', 'Kochfeld' => 'ploča za kuhanje', 'Backofen' => 'pećnica',
			'Dunsthaube' => 'napa', 'Spüle' => 'sudoper', 'Schrank' => 'element', 'Leuchte' => 'svjetiljka', 'reversibel' => 'reverzibilno',
			'Küchenarmatur' => 'kuhinjska slavina', 'Hochdruck' => 'visokotlačna', 'Niederdruck' => 'niskotlačna', 'mit Rohrauslauf' => 's cijevnim izljevom',
			'mit Brause' => 's tušem', 'ausziehbar' => 'izvlačna', 'chrom' => 'krom', 'Chrom' => 'krom', 'Edelstahl-Optik' => 'izgled nehrđajućeg čelika',
			'Typ' => 'Tip', 'Beton' => 'beton', 'Marmor' => 'mramor', 'Stein' => 'kamen', 'Schiefer' => 'škriljevac', 'Terrazzo' => 'terrazzo',
			'Sand' => 'pijesak', 'Nischenleuchte' => 'svjetiljka za nišu', 'Unterbauleuchte' => 'podgradna svjetiljka', 'strukturiert' => 'strukturirano',
			'Supermatt' => 'supermat', 'Ultramatt' => 'ultramat', 'Seidenmatt' => 'svilenkasto mat', 'Samtmatt' => 'baršunasto mat', 'Furnier' => 'furnir',
			'Massivholz' => 'masivno drvo', 'Keramik' => 'keramika', 'Quarzkomposit' => 'kvarcni kompozit', 'Kompaktplatte' => 'kompaktna ploča',
		),
		'en' => array(
			'Serie' => 'Series', 'Programm' => 'Range', 'Front-Kombination' => 'Front combination',
			'Korpusausführung' => 'Carcase finish', 'Korpusfarbe außen' => 'Carcase colour outside', 'Korpusfarbe innen' => 'Carcase colour inside',
			'Öffnungsunterstützung Schubkasten/Auszug' => 'Opening support drawers', 'Griff-Kombination' => 'Handle combination', 'Grifflage' => 'Handle position',
			'Auszugsausführung' => 'Pull-out version', 'Innenauszug-Ausführung' => 'Internal pull-out version',
			'Nischenausführung' => 'Niche finish', 'Nischenfarbe' => 'Niche colour', 'Kanten- und Hintergrundfarbe Nischenverkleidung' => 'Edge and background colour splashback', 'Kanten- und Hintergrundfarbe' => 'Edge and background colour',
			'Wandschrankbeleuchtung unten' => 'Wall unit lighting below', 'Wandschrankbeleuchtung oben' => 'Wall unit lighting above',
			'Farben Zubehör/Leuchten' => 'Colours accessories/lights', 'Sockelausführung' => 'Plinth finish', 'Sockelfarbe' => 'Plinth colour', 'Sockelhöhe' => 'Plinth height',
			'Arbeitsplatten-Ausführung' => 'Worktop finish', 'Arbeitsplatten-Farbe' => 'Worktop colour', 'Arbeitsplatten-Kantenausf.' => 'Worktop edge',
			'Arbeitsplatten-Kantenfarbe' => 'Worktop edge colour',
			'Eiche Sierra Nachbildung' => 'Sierra oak effect', 'Kunststoff matt, N-Kante' => 'laminate matt, N-edge', 'N-Kante, matt' => 'N-edge, matt',
			'Farbgleich im APL-Dekor' => 'matching worktop decor', 'ohne Öffnungsunterstützung' => 'without opening support', 'ohne Beleuchtung' => 'without lighting',
			'Waagerecht/Waagerecht' => 'horizontal/horizontal', 'Standard (Reling)' => 'standard (rails)', 'Standard (Holzvorderstück)' => 'standard (wooden front)',
			'Dekor Matt' => 'decor matt', 'Schwarz Hochglanz' => 'black high gloss', 'Metallgriff Schwarz' => 'metal handle black', 'Weiß Hochglanz' => 'white high gloss',
			'Möbel' => 'furniture', 'Kunststoff' => 'laminate', 'Glaskeramik-Strahlungskochfeld' => 'ceramic radiant hob',
			'Integrierter Kühlautomat' => 'integrated refrigerator', 'Vollintegrierter Geschirrspüler' => 'fully integrated dishwasher',
			'Teilintegrierter Geschirrspüler' => 'semi-integrated dishwasher', 'Einbauherd' => 'built-in cooker', 'Einbaubackofen' => 'built-in oven',
			'Mikrowelle' => 'microwave', 'Flachschirmhaube' => 'flat-screen hood', 'Aktivkohlefilter' => 'charcoal filter',
			'Hochdruck-Einhebelmischbatterie' => 'high-pressure single-lever mixer tap', 'Mischbatterie' => 'mixer tap', 'verchromt' => 'chrome-plated',
			'Edelstahl Naturfinish' => 'stainless steel natural finish', 'Einbau-Spüle' => 'inset sink', 'Induktionskochfeld' => 'induction hob',
			'LED Nischenleuchte' => 'LED niche light', 'herdgebunden' => 'cooker-controlled', 'Rahmenlos' => 'frameless', 'mit Linksanschlag' => 'left-hinged',
			'mit Rechtsanschlag' => 'right-hinged', 'Nachbildung' => 'effect',
			'Weiß' => 'white', 'Schwarz' => 'black', 'Grau' => 'grey', 'Anthrazit' => 'anthracite', 'Beige' => 'beige', 'Eiche' => 'oak', 'Nussbaum' => 'walnut',
			'Kaschmir' => 'cashmere', 'Sand' => 'sand', 'Grün' => 'green', 'Blau' => 'blue', 'Matt' => 'matt', 'matt' => 'matt', 'Hochglanz' => 'high gloss',
			'Lack' => 'lacquer', 'Glas' => 'glass', 'Holz' => 'wood', 'Edelstahl' => 'stainless steel', 'Keramik' => 'ceramic', 'Quarzstein' => 'quartz',
			'Standard' => 'standard', 'Kühlautomat' => 'refrigerator', 'Geschirrspüler' => 'dishwasher', 'Kochfeld' => 'hob', 'Backofen' => 'oven',
			'Dunsthaube' => 'extractor hood', 'Spüle' => 'sink', 'Schrank' => 'unit', 'Leuchte' => 'light', 'reversibel' => 'reversible',
			'Küchenarmatur' => 'kitchen tap', 'Hochdruck' => 'high-pressure', 'Niederdruck' => 'low-pressure', 'mit Rohrauslauf' => 'with tubular spout',
			'mit Brause' => 'with spray', 'ausziehbar' => 'pull-out', 'chrom' => 'chrome', 'Chrom' => 'chrome', 'Edelstahl-Optik' => 'stainless steel look',
			'Typ' => 'Type', 'Beton' => 'concrete', 'Marmor' => 'marble', 'Stein' => 'stone', 'Schiefer' => 'slate', 'Terrazzo' => 'terrazzo',
			'Sand' => 'sand', 'Nischenleuchte' => 'niche light', 'Unterbauleuchte' => 'under-cabinet light', 'strukturiert' => 'textured',
			'Supermatt' => 'super matt', 'Ultramatt' => 'ultra matt', 'Seidenmatt' => 'silk matt', 'Samtmatt' => 'velvet matt', 'Furnier' => 'veneer',
			'Massivholz' => 'solid wood', 'Keramik' => 'ceramic', 'Quarzkomposit' => 'quartz composite', 'Kompaktplatte' => 'compact panel',
		),
	);

	/** Sätze aus den Nobilia-Unterlagen */
	protected static $sentences = array(
		'Wir übernehmen keine Gewähr für eine originalgetreue Darstellung aller Artikel. Farbliche und konstruktive Abweichungen sind möglich.' => array(
			'hr' => 'Ne jamčimo vjeran prikaz svih artikala. Moguća su odstupanja u boji i konstrukciji.',
			'en' => 'We do not guarantee a true-to-original representation of all articles. Deviations in colour and construction are possible.',
		),
	);

	/** Farbwörter (Endung) und Vorsilben für zusammengesetzte nobilia-Farben wie „Taupegrau“, „Seidengrau“, „Alpinweiß“ */
	protected static $colorEnd = array(
		'hr' => array('grau' => 'siva', 'weiß' => 'bijela', 'weiss' => 'bijela', 'schwarz' => 'crna', 'blau' => 'plava', 'grün' => 'zelena', 'beige' => 'bež',
			'braun' => 'smeđa', 'rot' => 'crvena', 'gelb' => 'žuta', 'rosé' => 'roza', 'creme' => 'krem'),
		'en' => array('grau' => 'grey', 'weiß' => 'white', 'weiss' => 'white', 'schwarz' => 'black', 'blau' => 'blue', 'grün' => 'green', 'beige' => 'beige',
			'braun' => 'brown', 'rot' => 'red', 'gelb' => 'yellow', 'rosé' => 'rose', 'creme' => 'cream'),
	);
	protected static $colorPre = array(
		'hr' => array('taupe' => 'taupe', 'seiden' => 'svilenkasto', 'stein' => 'kameno', 'achat' => 'ahat', 'lava' => 'lava', 'alpin' => 'alpsko', 'polar' => 'polarno',
			'fjord' => 'fjord', 'mineral' => 'mineralno', 'salbei' => 'kadulja', 'schiefer' => 'škriljevac', 'basalt' => 'bazalt', 'platin' => 'platinasto', 'platina' => 'platinasto',
			'arktis' => 'arktičko', 'perl' => 'biserno', 'sand' => 'pješčano', 'kristall' => 'kristalno', 'schilf' => 'trska', 'oliv' => 'maslinasto', 'nougat' => 'nugat',
			'quarz' => 'kvarc', 'kiesel' => 'šljunak', 'beton' => 'betonsko', 'terra' => 'terra', 'eisen' => 'željezno', 'graphit' => 'grafitno', 'carbon' => 'karbonsko',
			'onyx' => 'oniks', 'magnolia' => 'magnolija', 'kaschmir' => 'kašmir', 'anthrazit' => 'antracit', 'hell' => 'svijetlo', 'dunkel' => 'tamno', 'blau' => 'plavo'),
		'en' => array('taupe' => 'taupe', 'seiden' => 'silk', 'stein' => 'stone', 'achat' => 'agate', 'lava' => 'lava', 'alpin' => 'alpine', 'polar' => 'polar',
			'fjord' => 'fjord', 'mineral' => 'mineral', 'salbei' => 'sage', 'schiefer' => 'slate', 'basalt' => 'basalt', 'platin' => 'platinum', 'platina' => 'platinum',
			'arktis' => 'arctic', 'perl' => 'pearl', 'sand' => 'sand', 'kristall' => 'crystal', 'schilf' => 'reed', 'oliv' => 'olive', 'nougat' => 'nougat',
			'quarz' => 'quartz', 'kiesel' => 'pebble', 'beton' => 'concrete', 'terra' => 'terra', 'eisen' => 'iron', 'graphit' => 'graphite', 'carbon' => 'carbon',
			'onyx' => 'onyx', 'magnolia' => 'magnolia', 'kaschmir' => 'cashmere', 'anthrazit' => 'anthracite', 'hell' => 'light', 'dunkel' => 'dark', 'blau' => 'blue'),
	);
	/** Genitiv für „imitacija …“ */
	protected static $genHr = array('Eiche' => 'hrasta', 'Beton' => 'betona', 'Marmor' => 'mramora', 'Nussbaum' => 'oraha', 'Stein' => 'kamena', 'Holz' => 'drva',
		'Schiefer' => 'škriljevca', 'Ulme' => 'brijesta', 'Kiefer' => 'bora', 'Esche' => 'jasena', 'Buche' => 'bukve', 'Ahorn' => 'javora', 'Kirsche' => 'trešnje',
		'Lärche' => 'ariša', 'Fichte' => 'smreke', 'Walnuss' => 'oraha', 'Keramik' => 'keramike', 'Granit' => 'granita', 'Terrazzo' => 'terrazza', 'Metall' => 'metala',
		'Rost' => 'hrđe', 'Kupfer' => 'bakra', 'Stahl' => 'čelika');

	/** Zusammengesetztes Farbwort übersetzen, z. B. Taupegrau → taupe siva */
	protected static function color($word, $l)
	{
		if (!isset(self::$colorEnd[$l])) {
			return null;
		}
		$lw = mb_strtolower($word);
		foreach (self::$colorEnd[$l] as $end => $tr) {
			if (mb_strlen($lw) > mb_strlen($end) + 2 && mb_substr($lw, -mb_strlen($end)) === $end) {
				$pre = rtrim(mb_substr($lw, 0, -mb_strlen($end)), '-');
				$pt = isset(self::$colorPre[$l][$pre]) ? self::$colorPre[$l][$pre] : $pre;
				return $pt.' '.$tr;
			}
		}
		return null;
	}

	/** Sprachkürzel aus Dolibarr-Sprachcode (hr_HR → hr) */
	public static function short($lang)
	{
		return strtolower(substr((string) $lang, 0, 2));
	}

	/** @return array<string,string> */
	protected static function table($l)
	{
		static $cache = array();
		if (isset($cache[$l])) {
			return $cache[$l];
		}
		$t = isset(self::$dict[$l]) ? self::$dict[$l] : array();
		foreach (explode("\n", str_replace(array('\r\n', '\n', "\r"), "\n", getDolGlobalString('KA_DICT_'.strtoupper($l)))) as $line) {
			if (strpos($line, '=') !== false) {
				list($k, $v) = array_map('trim', explode('=', $line, 2));
				if ($k !== '') {
					$t[$k] = $v;
				}
			}
		}
		uksort($t, function ($a, $b) {
			return mb_strlen($b) <=> mb_strlen($a);
		});
		return $cache[$l] = $t;
	}

	/**
	 * Text übersetzen (de → Sprache). Deutsch bleibt unverändert.
	 * @param string $text
	 * @param string $lang z. B. hr_HR
	 */
	public static function tr($text, $lang)
	{
		$l = self::short($lang);
		if ($l === 'de' || $l === '' || $text === '') {
			return $text;
		}
		if (isset(self::$sentences[$text][$l])) {
			return self::$sentences[$text][$l];
		}
		$t = self::table($l);
		if (isset($t[$text])) {
			return $t[$text];
		}
		// „X Nachbildung“ → „imitacija X“ (hr, mit Genitiv) bzw. „X effect“ (en)
		if (preg_match('/^(.+?)\s+(Nachbildung|NB)$/u', $text, $m) && !isset($t[$text])) {
			$rest = $m[1];
			if ($l === 'hr') {
				$words = explode(' ', $rest, 2);
				$first = isset(self::$genHr[$words[0]]) ? self::$genHr[$words[0]] : self::tr($words[0], $lang);
				return 'imitacija '.$first.(isset($words[1]) ? ' '.self::tr($words[1], $lang) : '');
			}
			return self::tr($rest, $lang).' effect';
		}
		// Ausdrücke/Wörter ersetzen (nur ganze Wörter), Platzhalter verhindern Doppelersetzung
		$map = array();
		$i = 0;
		foreach ($t as $de => $tr) {
			$re = '/(?<![\p{L}\-])'.preg_quote($de, '/').'(?![\p{L}])/u';
			if (preg_match($re, $text)) {
				$ph = "\x01".($i++)."\x02";
				$map[$ph] = $tr;
				$text = preg_replace($re, $ph, $text);
			}
		}
		$text = strtr($text, $map);
		// verbliebene zusammengesetzte Farbwörter (z. B. Taupegrau, Seidengrau)
		return preg_replace_callback('/(?<![\p{L}])(\p{Lu}\p{Ll}+(?:grau|weiß|weiss|schwarz|blau|grün|beige|braun|rot|gelb|rosé|creme))(?![\p{L}])/u', function ($mm) use ($l) {
			$c = self::color($mm[1], $l);
			return $c === null ? $mm[1] : $c;
		}, $text);
	}
}
