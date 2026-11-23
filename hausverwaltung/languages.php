<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Sprachen der Eigentümer-Dokumente: Übersicht, neue Sprache, Übersetzen (Texte + Kostenarten + Planpositionen), Export/Import für Übersetzer */
// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'admin'));
if (!$user->hasRight('hausverwaltung', 'write') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie – sie enthält Daten aller Einheiten
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$lang = preg_replace('/[^a-zA-Z_]/', '', (string) GETPOST('lang', 'alpha'));
$repo = new HvRepository($db);
$formadmin = new FormAdmin($db);
$orig = getDolGlobalString('HAUSVERWALTUNG_ORIGINAL_LANG', 'hr_HR');
$keys = hvDocKeys();
$ref = hvLangValues($orig);

/** Datensätze mit übersetzbaren Feldern */
function hvDataRows($repo)
{
	$rows = array();
	foreach ($repo->getCostItems(false) as $c) {
		$rows[] = array('table' => 'hv_costitem', 'obj' => $c, 'fields' => array('label'));
	}
	foreach ($repo->getReservePlan(false) as $p) {
		$rows[] = array('table' => 'hv_reserve_plan', 'obj' => $p, 'fields' => array('label', 'basis', 'note'));
	}
	return $rows;
}
function hvSetI18n($db, $table, $obj, $lang, $values)
{
	$tr = !empty($obj->i18n) ? json_decode($obj->i18n, true) : array();
	foreach ($values as $f => $v) {
		$v = trim(strip_tags((string) $v));
		if ($v === '') {
			unset($tr[$lang][$f]);
		} else {
			$tr[$lang][$f] = $v;
		}
	}
	if (isset($tr[$lang]) && !$tr[$lang]) {
		unset($tr[$lang]);
	}
	$db->query("UPDATE ".$db->prefix().$table." SET i18n = ".($tr ? "'".$db->escape(json_encode($tr, JSON_UNESCAPED_UNICODE))."'" : 'NULL')." WHERE rowid = ".((int) $obj->rowid));
}

if (in_array($action, array('applyterms', 'resetterms'), true)) {
	$n = hvApplyTerms($action === 'applyterms');
	setEventMessages($langs->trans($action === 'applyterms' ? 'HvTermsApplied' : 'HvTermsReset', $n), null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
if ($action === 'addlang' && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
	$list = array_filter(array_map('trim', explode(',', getDolGlobalString('HAUSVERWALTUNG_DOC_LANGS', 'hr_HR,de_DE,en_US,sl_SI,uk_UA'))));
	$list[] = $lang;
	dolibarr_set_const($db, 'HAUSVERWALTUNG_DOC_LANGS', implode(',', array_unique($list)), 'chaine', 0, '', $conf->entity);
	header('Location: '.$_SERVER['PHP_SELF'].'?action=edit&lang='.$lang);
	exit;
}
if ($action === 'save' && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
	$cur = hvLangValues($lang);
	$chgKeys = $chgData = array();
	foreach ((array) GETPOST('k', 'array') as $k => $v) {
		if (isset($keys[$k])) {
			$v = trim(strip_tags((string) $v));
			if ($v !== '' && ($cur[$k]['value'] ?? '') !== '' && $v !== $cur[$k]['value']) {
				$chgKeys[$k] = $v; // bestehender Text geändert
			}
			$fileVal = (($cur[$k]['src'] ?? '') === 'file') ? $cur[$k]['value'] : null;
			if ($v !== '' && $v !== $fileVal) {
				hvSaveTrans($lang, $k, $v);
			} elseif ($v === '' && ($cur[$k]['src'] ?? '') === 'db') {
				hvSaveTrans($lang, $k, '');
			}
		}
	}
	$d = (array) GETPOST('d', 'array');
	foreach (hvDataRows($repo) as $r) {
		$id = (int) $r['obj']->rowid;
		if (isset($d[$r['table']][$id])) {
			$vals = array_intersect_key((array) $d[$r['table']][$id], array_flip($r['fields']));
			$otr = !empty($r['obj']->i18n) ? (array) json_decode($r['obj']->i18n, true) : array();
			foreach ($vals as $f => $v) {
				$v = trim(strip_tags((string) $v));
				$was = (string) ($otr[$lang][$f] ?? '');
				if ($v !== '' && $was !== '' && $v !== $was) {
					$chgData[$r['table'].'|'.$id.'|'.$f] = $v;
				}
			}
			hvSetI18n($db, $r['table'], $r['obj'], $lang, $vals);
		}
	}
	// Änderungen auf Wunsch in die anderen Sprachen übernehmen
	$aiMsg = null;
	if (($chgKeys || $chgData) && GETPOST('hv_translate', 'int') === '1') {
		dol_include_once('/hausverwaltung/class/hvtranslator.class.php');
		if (HvTranslator::enabled()) {
			$tl = new HvTranslator();
			$res = $tl->translate($chgKeys + $chgData, $lang, hvDocLangs());
			if ($res === false) {
				$aiMsg = array($langs->trans('HvAiFailed', $tl->error));
			} else {
				hvAiStore($db, $repo, $res, $keys);
			}
		}
	}
	setEventMessages($langs->trans('HvSaved'), $aiMsg, $aiMsg ? 'warnings' : 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?action=edit&lang='.$lang);
	exit;
}
/** Übersetzungsergebnis speichern: Textbausteine (Schlüssel) und Datentexte (Tabelle|ID|Feld) */
function hvAiStore($db, $repo, array $res, array $keys)
{
	$rows = array();
	foreach (hvDataRows($repo) as $r) {
		$rows[$r['table']][(int) $r['obj']->rowid] = $r['obj'];
	}
	$n = 0;
	foreach ($res as $l => $vals) {
		foreach ($vals as $key => $v) {
			if (isset($keys[$key])) {
				hvSaveTrans($l, $key, $v);
				$n++;
			} elseif (preg_match('/^(hv_costitem|hv_reserve_plan)\|(\d+)\|(label|basis|note)$/', $key, $m) && isset($rows[$m[1]][(int) $m[2]])) {
				hvSetI18n($db, $m[1], $rows[$m[1]][(int) $m[2]], $l, array($m[3] => $v));
				$rows[$m[1]][(int) $m[2]] = $db->getRow("SELECT * FROM ".$db->prefix().$m[1]." WHERE rowid = ".((int) $m[2]));
				$n++;
			}
		}
	}
	return $n;
}

// Fehlende Texte einer Sprache automatisch übersetzen (z. B. neue Eigentümersprache)
if ($action === 'aitranslate' && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
	dol_include_once('/hausverwaltung/class/hvtranslator.class.php');
	$srcL = substr($lang, 0, 2) === 'de' ? $orig : 'de_DE';
	$srcVals = hvLangValues($srcL);
	$cur = hvLangValues($lang);
	$todo = array();
	foreach ($keys as $k => $deTxt) {
		if (!isset($cur[$k]) && ($srcVals[$k]['value'] ?? '') !== '') {
			$todo[$k] = $srcVals[$k]['value'];
		}
	}
	foreach (hvDataRows($repo) as $r) {
		$tr = !empty($r['obj']->i18n) ? (array) json_decode($r['obj']->i18n, true) : array();
		foreach ($r['fields'] as $f) {
			$sv = hvI18n($r['obj'], $f, $srcL);
			if ($sv !== '' && empty($tr[$lang][$f])) {
				$todo[$r['table'].'|'.((int) $r['obj']->rowid).'|'.$f] = $sv;
			}
		}
	}
	$n = 0;
	$msg = null;
	if ($todo && HvTranslator::enabled()) {
		$tl = new HvTranslator();
		$res = $tl->translate($todo, $srcL, array($lang));
		if ($res === false) {
			$msg = array($langs->trans('HvAiFailed', $tl->error));
		}
		if ($res) {
			$n = hvAiStore($db, $repo, $res, $keys);
		}
	}
	setEventMessages($langs->trans('HvAiTranslatedMissing', $n, $lang), $msg, $msg ? 'warnings' : 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?action=edit&lang='.$lang);
	exit;
}
if ($action === 'export' && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
	$cur = hvLangValues($lang);
	$de = hvLangValues('de_DE');
	$out = array('_hinweis' => 'Übersetzungsdatei Hausverwaltung. Bitte nur die Felder "value" in der Zielsprache ausfüllen (Platzhalter %s unverändert lassen) und die Datei unter Hausverwaltung → Sprachen importieren.', 'lang' => $lang, 'texts' => array(), 'data' => array());
	foreach ($keys as $k => $deTxt) {
		$out['texts'][$k] = array('de_DE' => $de[$k]['value'] ?? $deTxt, $orig => $ref[$k]['value'] ?? '', 'value' => $cur[$k]['value'] ?? '');
	}
	foreach (hvDataRows($repo) as $r) {
		foreach ($r['fields'] as $f) {
			if ((string) $r['obj']->$f === '') {
				continue;
			}
			$out['data'][] = array('table' => $r['table'], 'id' => (int) $r['obj']->rowid, 'field' => $f, 'de_DE' => hvI18n($r['obj'], $f, 'de_DE'), $orig => hvI18n($r['obj'], $f, $orig), 'value' => (function () use ($r, $f, $lang) {
				$tr = !empty($r['obj']->i18n) ? json_decode($r['obj']->i18n, true) : array();
				return $tr[$lang][$f] ?? '';
			})());
		}
	}
	header('Content-Type: application/json; charset=utf-8');
	header('Content-Disposition: attachment; filename="hausverwaltung_uebersetzung_'.$lang.'.json"');
	print json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	exit;
}
if ($action === 'import') {
	// Formate: Export einer Sprache {"lang":..,"texts":{key:{value}},"data":[{table,id,field,value}]}
	//          oder mehrsprachig {"data":[{table, id|match, field, i18n:{hr_HR:..,en_US:..}}]} (match = Bezeichnung im System)
	$data = (!empty($_FILES['trfile']['tmp_name']) && is_uploaded_file($_FILES['trfile']['tmp_name'])) ? json_decode(file_get_contents($_FILES['trfile']['tmp_name']), true) : null;
	$lang = (is_array($data) && preg_match('/^[a-z]{2}_[A-Z]{2}$/', (string) ($data['lang'] ?? ''))) ? $data['lang'] : '';
	if (!is_array($data) || ($lang === '' && empty($data['data']))) {
		setEventMessages($langs->trans('HvImportBadFile'), null, 'errors');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	$n = 0;
	$touched = $lang !== '' ? array($lang) : array();
	if ($lang !== '') {
		foreach ((array) ($data['texts'] ?? array()) as $k => $v) {
			$val = is_array($v) ? ($v['value'] ?? '') : $v;
			if (isset($keys[$k]) && trim((string) $val) !== '') {
				hvSaveTrans($lang, $k, strip_tags((string) $val));
				$n++;
			}
		}
	}
	$rows = $byLabel = array();
	foreach (hvDataRows($repo) as $r) {
		$rows[$r['table']][(int) $r['obj']->rowid] = $r['obj'];
		$byLabel[$r['table']][mb_strtolower(trim((string) $r['obj']->label))] = (int) $r['obj']->rowid;
	}
	$skipped = 0;
	foreach ((array) ($data['data'] ?? array()) as $e) {
		$t = (string) ($e['table'] ?? '');
		$f = (string) ($e['field'] ?? 'label');
		$id = (int) ($e['id'] ?? 0);
		if (!$id && isset($e['match'])) {
			$id = (int) ($byLabel[$t][mb_strtolower(trim((string) $e['match']))] ?? 0);
		}
		if (!isset($rows[$t][$id]) || !in_array($f, array('label', 'basis', 'note'), true)) {
			$skipped++;
			continue;
		}
		$vals = isset($e['i18n']) && is_array($e['i18n']) ? $e['i18n'] : ($lang !== '' ? array($lang => $e['value'] ?? '') : array());
		foreach ($vals as $l => $v) {
			if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', (string) $l) && trim((string) $v) !== '') {
				hvSetI18n($db, $t, $rows[$t][$id], $l, array($f => $v));
				$rows[$t][$id] = $db->getRow("SELECT * FROM ".$db->prefix().$t." WHERE rowid = ".$id);
				$touched[] = $l;
				$n++;
			}
		}
	}
	$list = array_filter(array_map('trim', explode(',', getDolGlobalString('HAUSVERWALTUNG_DOC_LANGS', 'hr_HR,de_DE,en_US,sl_SI,uk_UA'))));
	$new = array_diff(array_unique($touched), $list);
	if ($new) {
		dolibarr_set_const($db, 'HAUSVERWALTUNG_DOC_LANGS', implode(',', array_merge($list, $new)), 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans('HvLangImported', $n, implode(', ', array_unique($touched))), $skipped ? array($langs->trans('HvLangSkipped', $skipped)) : null, $skipped ? 'warnings' : 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].($lang !== '' ? '?action=edit&lang='.$lang : ''));
	exit;
}

llxHeader('', $langs->trans('HvLanguages'), hvHelpUrl('sprachen/'), '', 0, 0, '', '', '', 'mod-hausverwaltung');
print load_fiche_titre($langs->trans('HvLanguages'), '', 'language');
print '<div class="opacitymedium">'.$langs->trans('HvLanguagesHelp', $orig).'</div><br>';

// Übersicht
$owners = array();
$res = $db->query("SELECT s.default_lang, COUNT(*) as n FROM ".$db->prefix()."hv_unit u INNER JOIN ".$db->prefix()."societe s ON s.rowid = u.fk_soc WHERE u.entity = ".((int) $conf->entity)." GROUP BY s.default_lang");
while ($res && ($o = $db->fetch_object($res))) {
	$owners[(string) $o->default_lang] = (int) $o->n;
}
$dataRows = hvDataRows($repo);
print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Language').'</th><th class="right">'.$langs->trans('HvUnits').'</th><th>'.$langs->trans('HvLangDocTexts').'</th><th>'.$langs->trans('HvLangDataTexts').'</th><th></th></tr>';
foreach (hvDocLangs() as $l) {
	$v = hvLangValues($l);
	$have = count(array_intersect_key($v, $keys));
	$dn = $dt = 0;
	foreach ($dataRows as $r) {
		$dt++;
		$tr = !empty($r['obj']->i18n) ? json_decode($r['obj']->i18n, true) : array();
		if (!empty($tr[$l]['label']) || $l === getDolGlobalString('HAUSVERWALTUNG_DATA_LANG', 'de_DE')) {
			$dn++;
		}
	}
	$miss = count($keys) - $have;
	print '<tr class="oddeven"><td><strong>'.$l.'</strong>'.($l === $orig ? ' <span class="opacitymedium">(Original)</span>' : '').'</td><td class="right">'.($owners[$l] ?? 0).'</td>';
	print '<td>'.($miss ? '<span class="badge badge-status1">'.$langs->trans('HvLangMissing', $miss, count($keys)).'</span>' : '<span class="badge badge-status4">'.$langs->trans('HvLangComplete').'</span>').'</td>';
	print '<td>'.($dn < $dt ? '<span class="badge badge-status1">'.$dn.'/'.$dt.'</span>' : '<span class="badge badge-status4">'.$dn.'/'.$dt.'</span>').'</td>';
	print '<td class="right nowraponall"><a class="butAction small" href="'.$_SERVER['PHP_SELF'].'?action=edit&lang='.$l.'">'.$langs->trans('HvLangTranslate').'</a> <a class="butAction small" href="'.$_SERVER['PHP_SELF'].'?action=export&lang='.$l.'">'.$langs->trans('HvLangExport').'</a></td></tr>';
}
print '</table><br>';

// Begriffe der Hausverwaltung (Menüs von Dolibarr)
$termsOn = getDolGlobalString('HAUSVERWALTUNG_TERMS_APPLIED') === '1';
print load_fiche_titre($langs->trans('HvTermsTitle'), '', '');
print '<div class="opacitymedium">'.$langs->trans('HvTermsHelp').'</div>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>Dolibarr</th><th>de</th><th>hr</th><th>en</th><th>sl</th><th>uk</th></tr>';
foreach (hvTerms() as $k => $v) {
	print '<tr class="oddeven"><td class="opacitymedium">'.dol_escape_htmltag($k).'</td>';
	foreach ($v as $x) {
		print '<td>'.dol_escape_htmltag($x).'</td>';
	}
	print '</tr>';
}
print '</table>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="center"><input type="hidden" name="token" value="'.newToken().'">';
print ($termsOn ? '<span class="badge badge-status4">'.$langs->trans('HvTermsActive').'</span> <button class="button" name="action" value="resetterms">'.$langs->trans('HvTermsResetBtn').'</button>' : '<button class="button button-save" name="action" value="applyterms">'.$langs->trans('HvTermsApplyBtn').'</button>');
print '</form><br>';

// Neue Sprache + Import
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block marginrightonly"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="addlang">'.$langs->trans('HvLangAdd').': '.$formadmin->select_language('', 'lang', 0, array(), 1, 0, 0, 'minwidth200', 1).' <input type="submit" class="button small" value="'.$langs->trans('Add').'"></form>';
print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="import">'.$langs->trans('HvLangImport').': <input type="file" name="trfile" accept=".json,application/json"> <input type="submit" class="button small" value="'.$langs->trans('Import').'"></form><br><br>';

// Bearbeiten
if ($action === 'edit' && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
	$cur = hvLangValues($lang);
	$de = hvLangValues('de_DE');
	$onlyMissing = GETPOSTINT('missing');
	print load_fiche_titre($langs->trans('HvLangTranslate').' '.$lang, '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&lang='.$lang.'&missing='.($onlyMissing ? 0 : 1).'">'.$langs->trans($onlyMissing ? 'HvLangShowAll' : 'HvLangShowMissing').'</a>', '');
	dol_include_once('/hausverwaltung/class/hvtranslator.class.php');
	if (HvTranslator::enabled()) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="right"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="aitranslate"><input type="hidden" name="lang" value="'.$lang.'"><button class="butAction">'.img_picto('', 'language', 'class="pictofixedwidth"').$langs->trans('HvAiTranslateMissingBtn').'</button></form>';
	}
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="hv-ask-translate" data-hastr="1"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="lang" value="'.$lang.'">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvLangKey').'</th><th>de_DE</th><th>'.$orig.'</th><th>'.$lang.'</th></tr>';
	foreach ($keys as $k => $deTxt) {
		if ($onlyMissing && isset($cur[$k])) {
			continue;
		}
		print '<tr class="oddeven"><td class="small opacitymedium">'.$k.'</td><td class="small">'.dol_escape_htmltag($de[$k]['value'] ?? $deTxt).'</td><td class="small">'.dol_escape_htmltag($ref[$k]['value'] ?? '').'</td>';
		$cv = (string) ($cur[$k]['value'] ?? '');
		print '<td><input name="k['.$k.']" class="centpercent'.(isset($cur[$k]) ? '' : ' error').'"'.($cv !== '' ? ' data-hvsrc="1" data-orig="'.dol_escape_htmltag($cv).'"' : '').' value="'.dol_escape_htmltag($cv).'" placeholder="'.dol_escape_htmltag($deTxt).'"></td></tr>';
	}
	print '<tr class="liste_titre"><th colspan="4">'.$langs->trans('HvLangDataTexts').'</th></tr>';
	foreach ($dataRows as $r) {
		foreach ($r['fields'] as $f) {
			if ((string) $r['obj']->$f === '') {
				continue;
			}
			$tr = !empty($r['obj']->i18n) ? json_decode($r['obj']->i18n, true) : array();
			$val = $tr[$lang][$f] ?? '';
			if ($onlyMissing && $val !== '') {
				continue;
			}
			print '<tr class="oddeven"><td class="small opacitymedium">'.($r['table'] === 'hv_costitem' ? $langs->trans('HvCostItem') : $langs->trans('HvReservePlan')).' · '.$f.'</td><td class="small">'.dol_escape_htmltag(hvI18n($r['obj'], $f, 'de_DE')).'</td><td class="small">'.dol_escape_htmltag(hvI18n($r['obj'], $f, $orig)).'</td>';
			print '<td><input name="d['.$r['table'].']['.((int) $r['obj']->rowid).']['.$f.']" class="centpercent'.($val === '' && $lang !== getDolGlobalString('HAUSVERWALTUNG_DATA_LANG', 'de_DE') ? ' error' : '').'"'.($val !== '' ? ' data-hvsrc="1" data-orig="'.dol_escape_htmltag($val).'"' : '').' value="'.dol_escape_htmltag($val).'"></td></tr>';
		}
	}
	print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
	print hvTranslateAskJs();
}

llxFooter();
$db->close();
