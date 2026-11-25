<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

/**
 * KI-Übersetzung kurzer Texte (Kostenarten, Planpositionen, Textbausteine) über die Claude-API (Messages API).
 * Direkter HTTP-Aufruf (cURL), keine zusätzlichen Bibliotheken.
 *
 * Einstellungen:
 *   HAUSVERWALTUNG_AI_KEY        API-Schlüssel (verschlüsselt gespeichert)
 *   HAUSVERWALTUNG_AI_TRANSLATE  1 = beim Speichern übersetzen (Standard, sobald ein Schlüssel gesetzt ist)
 *   HAUSVERWALTUNG_AI_MODEL      Modell (Standard claude-opus-5-5)
 *   HAUSVERWALTUNG_AI_URL        nur für Tests: andere Adresse der Messages API
 */
class HvTranslator
{
	const DEFAULT_MODEL = 'claude-opus-5-5';
	const API_URL = 'https://api.anthropic.com/v1/messages';
	const API_VERSION = '2026-10-21';

	public $error = '';
	/** @var array Verbrauch des letzten Aufrufs (input_tokens, output_tokens) */
	public $usage = array();

	public static function hasKey()
	{
		return getDolGlobalString('HAUSVERWALTUNG_AI_KEY') !== '';
	}

	public static function enabled()
	{
		return self::hasKey() && getDolGlobalString('HAUSVERWALTUNG_AI_TRANSLATE', '1') === '1';
	}

	protected static function apiKey()
	{
		$v = getDolGlobalString('HAUSVERWALTUNG_AI_KEY');
		if ($v === '') {
			return '';
		}
		return function_exists('dolDecrypt') ? dolDecrypt($v) : $v;
	}

	/** Schlüssel verschlüsselt speichern (leer = löschen) */
	public static function saveKey($db, $key)
	{
		global $conf;
		$key = trim((string) $key);
		if ($key === '') {
			return dolibarr_del_const($db, 'HAUSVERWALTUNG_AI_KEY', $conf->entity);
		}
		return dolibarr_set_const($db, 'HAUSVERWALTUNG_AI_KEY', function_exists('dolEncrypt') ? dolEncrypt($key) : $key, 'chaine', 0, '', $conf->entity);
	}

	public static function langName($code)
	{
		$names = array('hr' => 'Croatian', 'de' => 'German', 'en' => 'English', 'sl' => 'Slovenian', 'uk' => 'Ukrainian', 'it' => 'Italian', 'fr' => 'French', 'es' => 'Spanish', 'cs' => 'Czech', 'sk' => 'Slovak', 'hu' => 'Hungarian', 'pl' => 'Polish', 'nl' => 'Dutch', 'sr' => 'Serbian', 'bs' => 'Bosnian', 'ru' => 'Russian');
		$s = substr((string) $code, 0, 2);
		return ($names[$s] ?? $code).' ('.$code.')';
	}

	/** Fester Wortschatz, damit Fachbegriffe in allen Dokumenten gleich übersetzt werden */
	protected static function systemPrompt()
	{
		return "You translate short texts used in the documents and user interface of a property management system for a Croatian condominium (zajednica suvlasnika). "
			."The texts are cost type names, maintenance plan items, estimation notes and text blocks of letters to apartment owners (service charges, annual statements, explanations, e-mails).\n\n"
			."Rules:\n"
			."- Translate the meaning naturally and concisely, in the register of formal letters to owners (German \"Sie\", Croatian \"Vi\", Slovenian \"vi\", Ukrainian \"ви\").\n"
			."- Keep placeholders exactly as they are, in the same order and count: %s, %1\$s, %d, %%.\n"
			."- Keep numbers, amounts, dates, units (m², €/m², €), IBANs, reference numbers, file names, e-mail addresses and URLs unchanged.\n"
			."- Keep proper names unchanged: company names (e.g. \"Green Villa and Apartment Resorts d.o.o.\"), building names such as \"Zgrada Bibali 83B\", Croatian legal names (e.g. \"Zakon o upravljanju i održavanju zgrada\"), abbreviations such as OIB, KPD, NN.\n"
			."- A text that is a short label stays a short label; do not add explanations.\n"
			."- Use this glossary consistently (German | Croatian | English | Slovenian | Ukrainian):\n"
			."  Vorschreibung | zaduženje | service charge | obremenitev | нарахування\n"
			."  Betriebskosten | zajednički troškovi | operating costs | obratovalni stroški | експлуатаційні витрати\n"
			."  Rücklage | pričuva | reserve fund | rezervni sklad | резервний фонд\n"
			."  Verwaltung (Honorar) | upravljanje (naknada) | management (fee) | upravljanje (nadomestilo) | управління (плата)\n"
			."  Gemeinschaft der Eigentümer | zajednica suvlasnika | community of owners | skupnost etažnih lastnikov | спільнота співвласників\n"
			."  Eigentümer | vlasnik | owner | lastnik | власник\n"
			."  Verwalter | upravitelj | property manager | upravnik | управитель\n"
			."  Jahresabrechnung | godišnji obračun | annual statement | letni obračun | річний розрахунок\n"
			."  Erläuterung | objašnjenje | explanation | pojasnilo | пояснення\n"
			."  Guthaben | preplata | credit | dobroimetje | переплата\n"
			."  Nachzahlung | nedoplata | additional payment | doplačilo | доплата\n"
			."  Haus 1 | Kuća 1 | House 1 | Hiša 1 | Будинок 1\n"
			."  Wohnfläche | stambena površina | living area | stanovanjska površina | житлова площа\n"
			."  Verteilschlüssel | ključ raspodjele | allocation key | ključ delitve | ключ розподілу\n"
			."  Stiegenhaus | stubište | stairwell | stopnišče | сходова клітка\n"
			."  Kläranlage | uređaj za pročišćavanje | treatment plant | čistilna naprava | очисна споруда\n"
			."  Kanalanschluss | priključak na kanalizaciju | sewer connection | priključek na kanalizacijo | підключення до каналізації\n"
			."- For other languages, use the established legal and property management terms of that language.\n"
			."- Answer only with the JSON object required by the schema.";
	}

	/**
	 * Texte übersetzen.
	 *
	 * @param array<string,string> $texts   Schlüssel => Text in der Ausgangssprache
	 * @param string               $src     Ausgangssprache, z. B. de_DE
	 * @param string[]             $targets Zielsprachen
	 * @return array<string,array<string,string>>|false  Zielsprache => (Schlüssel => Übersetzung)
	 */
	public function translate(array $texts, $src, array $targets)
	{
		// größere Mengen in Teilen (z. B. neue Sprache mit über 100 Textbausteinen)
		$out = array();
		$usage = array('input_tokens' => 0, 'output_tokens' => 0);
		foreach (array_chunk($texts, 30, true) as $chunk) {
			$r = $this->translateChunk($chunk, $src, $targets);
			if ($r === false) {
				return $out ?: false;
			}
			foreach ($r as $l => $vals) {
				$out[$l] = ($out[$l] ?? array()) + $vals;
			}
			$usage['input_tokens'] += (int) ($this->usage['input_tokens'] ?? 0);
			$usage['output_tokens'] += (int) ($this->usage['output_tokens'] ?? 0);
		}
		$this->usage = $usage;
		return $out;
	}

	protected function translateChunk(array $texts, $src, array $targets)
	{
		$this->error = '';
		$texts = array_filter(array_map('strval', $texts), function ($t) {
			return trim($t) !== '';
		});
		$targets = array_values(array_unique(array_filter($targets, function ($l) use ($src) {
			return $l !== '' && $l !== $src;
		})));
		if (!$texts || !$targets) {
			return array();
		}
		$key = self::apiKey();
		if ($key === '') {
			$this->error = 'Kein API-Schlüssel hinterlegt';
			return false;
		}
		// Schlüssel für das JSON-Schema neutral benennen (t0, t1, …)
		$ids = array();
		$in = array();
		$i = 0;
		foreach ($texts as $k => $t) {
			$ids['t'.$i] = $k;
			$in['t'.$i] = $t;
			$i++;
		}
		$textProps = array();
		foreach (array_keys($in) as $tid) {
			$textProps[$tid] = array('type' => 'string');
		}
		$langProps = array();
		foreach ($targets as $l) {
			$langProps[$l] = array('type' => 'object', 'properties' => $textProps, 'required' => array_keys($textProps), 'additionalProperties' => false);
		}
		$schema = array('type' => 'object', 'properties' => $langProps, 'required' => $targets, 'additionalProperties' => false);
		$user = "Source language: ".self::langName($src)."\nTarget languages: ".implode(', ', array_map(array(__CLASS__, 'langName'), $targets))
			."\n\nTexts to translate (JSON, keys t0, t1, …):\n".json_encode($in, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
			."\n\nReturn one object per target language code with the same keys.";
		$body = array(
			'model' => getDolGlobalString('HAUSVERWALTUNG_AI_MODEL', self::DEFAULT_MODEL),
			'max_tokens' => 16000,
			'system' => array(array('type' => 'text', 'text' => self::systemPrompt(), 'cache_control' => array('type' => 'ephemeral'))),
			'messages' => array(array('role' => 'user', 'content' => $user)),
			'output_config' => array('effort' => 'low', 'format' => array('type' => 'json_schema', 'schema' => $schema)),
			// bei einer Ablehnung durch die Sicherheitsfilter übernimmt automatisch ein anderes Modell
			'fallbacks' => 'default',
		);
		list($code, $raw, $cerr) = $this->post($key, $body, true);
		if ($code === 400 && stripos((string) $raw, 'fallback') !== false) {
			unset($body['fallbacks']);
			list($code, $raw, $cerr) = $this->post($key, $body, false);
		}
		if ($raw === false) {
			$this->error = 'Verbindung zur Übersetzung fehlgeschlagen: '.$cerr;
			return false;
		}
		$res = json_decode((string) $raw, true);
		if ($code !== 200 || !is_array($res)) {
			$msg = is_array($res) && isset($res['error']['message']) ? $res['error']['message'] : substr((string) $raw, 0, 200);
			if ($code === 401) {
				$msg = 'API-Schlüssel ungültig ('.$msg.')';
			} elseif ($code === 429) {
				$msg = 'Zu viele Anfragen oder Guthaben/Limit erreicht – bitte später erneut versuchen ('.$msg.')';
			} elseif ($code >= 500) {
				$msg = 'Übersetzungsdienst vorübergehend nicht erreichbar ('.$msg.')';
			}
			$this->error = 'HTTP '.$code.': '.$msg;
			return false;
		}
		$this->usage = (array) ($res['usage'] ?? array());
		if (($res['stop_reason'] ?? '') === 'refusal') {
			$this->error = 'Übersetzung abgelehnt';
			return false;
		}
		if (($res['stop_reason'] ?? '') === 'max_tokens') {
			$this->error = 'Antwort unvollständig (zu viele Texte auf einmal)';
			return false;
		}
		$json = null;
		foreach ((array) ($res['content'] ?? array()) as $block) {
			if (($block['type'] ?? '') === 'text') {
				$json = json_decode((string) $block['text'], true);
				break;
			}
		}
		if (!is_array($json)) {
			$this->error = 'Antwort nicht lesbar';
			return false;
		}
		$out = array();
		foreach ($targets as $l) {
			foreach ($ids as $tid => $origKey) {
				$v = isset($json[$l][$tid]) ? trim(strip_tags((string) $json[$l][$tid])) : '';
				// Platzhalter müssen erhalten bleiben, sonst Übersetzung verwerfen
				if ($v !== '' && substr_count($v, '%s') === substr_count($texts[$origKey], '%s')) {
					$out[$l][$origKey] = $v;
				}
			}
		}
		return $out;
	}

	/** @return array{0:int,1:string|false,2:string} */
	protected function post($key, array $body, $withFallback, $timeout = 120)
	{
		$headers = array(
			'Content-Type: application/json',
			'x-api-key: '.$key,
			'anthropic-version: '.self::API_VERSION,
		);
		if ($withFallback) {
			$headers[] = 'anthropic-beta: server-side-fallback-2026-07-01';
		}
		$ch = curl_init(getDolGlobalString('HAUSVERWALTUNG_AI_URL', self::API_URL));
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => (int) $timeout,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
		));
		$raw = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$cerr = curl_error($ch);
		curl_close($ch);
		return array($code, $raw, $cerr);
	}

	/**
	 * PDF-Dokument übersetzen. Ergebnis: einfaches HTML (Überschriften, Absätze, Listen, Tabellen) für die Ausgabe als PDF.
	 *
	 * @return array{title:string,html:string}|false
	 */
	public function translatePdf($path, $src, $target)
	{
		$this->error = '';
		$key = self::apiKey();
		if ($key === '') {
			$this->error = 'Kein API-Schlüssel hinterlegt';
			return false;
		}
		if (!is_readable($path) || filesize($path) > 20 * 1024 * 1024) {
			$this->error = 'Datei nicht lesbar oder zu groß (max. 20 MB)';
			return false;
		}
		$schema = array('type' => 'object', 'properties' => array('title' => array('type' => 'string'), 'html' => array('type' => 'string')), 'required' => array('title', 'html'), 'additionalProperties' => false);
		$body = array(
			'model' => getDolGlobalString('HAUSVERWALTUNG_AI_MODEL', self::DEFAULT_MODEL),
			'max_tokens' => 16000,
			'system' => array(array('type' => 'text', 'text' => self::systemPrompt(), 'cache_control' => array('type' => 'ephemeral'))),
			'messages' => array(array('role' => 'user', 'content' => array(
				array('type' => 'document', 'source' => array('type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($path)))),
				array('type' => 'text', 'text' => "Translate this complete document from ".self::langName($src)." into ".self::langName($target).". "
					."Keep the structure: headings, paragraphs, lists, tables, dates, amounts, names, signatures and addresses. Do not summarize and do not omit anything. "
					."Return 'title' (the document title in the target language, or a short descriptive title) and 'html' using only these tags: h1, h2, h3, p, br, strong, em, ul, ol, li, table, tr, th, td."),
			))),
			'output_config' => array('effort' => 'low', 'format' => array('type' => 'json_schema', 'schema' => $schema)),
			'fallbacks' => 'default',
		);
		list($code, $raw, $cerr) = $this->post($key, $body, true, 300);
		if ($code === 400 && stripos((string) $raw, 'fallback') !== false) {
			unset($body['fallbacks']);
			list($code, $raw, $cerr) = $this->post($key, $body, false, 300);
		}
		$res = json_decode((string) $raw, true);
		if ($raw === false || $code !== 200 || !is_array($res)) {
			$this->error = $raw === false ? $cerr : ('HTTP '.$code.': '.(is_array($res) && isset($res['error']['message']) ? $res['error']['message'] : substr((string) $raw, 0, 200)));
			return false;
		}
		$this->usage = (array) ($res['usage'] ?? array());
		if (in_array($res['stop_reason'] ?? '', array('refusal', 'max_tokens'), true)) {
			$this->error = ($res['stop_reason'] === 'max_tokens') ? 'Dokument zu lang für eine Übersetzung' : 'Übersetzung abgelehnt';
			return false;
		}
		foreach ((array) ($res['content'] ?? array()) as $block) {
			if (($block['type'] ?? '') === 'text') {
				$j = json_decode((string) $block['text'], true);
				if (is_array($j) && !empty($j['html'])) {
					$allowed = '<h1><h2><h3><p><br><strong><em><b><i><ul><ol><li><table><tr><th><td><thead><tbody>';
					return array('title' => trim(strip_tags((string) ($j['title'] ?? ''))), 'html' => strip_tags((string) $j['html'], $allowed));
				}
			}
		}
		$this->error = 'Antwort nicht lesbar';
		return false;
	}
}
