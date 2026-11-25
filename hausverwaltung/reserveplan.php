<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Rücklagen-Planung (Mehrjahresprogramm): Planpositionen + Prognose je Gebäude; Druckansicht in jeder Dokumentsprache */
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvreserveplan.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'banks'));
if (!$user->hasRight('hausverwaltung', 'read') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie – sie enthält Daten aller Einheiten
	accessforbidden();
}
$canwrite = $user->hasRight('hausverwaltung', 'write');
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$print = GETPOSTINT('print');
$repo = new HvRepository($db);
$form = new Form($db);
$buildings = $repo->getBuildings(false);
$docLangs = hvDocLangs();
$orig = $docLangs[0];

/** Übersetzungen aus Formular/Import in JSON */
function hvPlanI18nFromInput($src, $langsList)
{
	$out = array();
	foreach ($langsList as $l) {
		foreach (array('label', 'basis', 'note') as $f) {
			$v = trim((string) ($src[$f][$l] ?? ''));
			if ($v !== '') {
				$out[$l][$f] = $v;
			}
		}
	}
	return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
}

if ($canwrite && $action === 'save') {
	$tr = array('label' => GETPOST('tr_label', 'array'), 'basis' => GETPOST('tr_basis', 'array'), 'note' => GETPOST('tr_note', 'array'));
	$fields = array(
		'label' => GETPOST('label', 'alphanohtml'),
		'fk_building' => GETPOSTINT('fk_building'),
		'alloc_key' => in_array(GETPOST('alloc_key', 'aZ09'), HvCalculator::keys(), true) ? GETPOST('alloc_key', 'aZ09') : 'area',
		'year_planned' => GETPOSTINT('year_planned'),
		'amount' => (float) price2num(GETPOST('amount', 'alphanohtml')),
		'basis' => GETPOST('basis', 'alphanohtml'),
		'position' => GETPOSTINT('position'),
		'active' => GETPOSTINT('active') ? 1 : 0,
		'note' => GETPOST('note', 'restricthtml'),
		'i18n' => hvPlanI18nFromInput(array_map(function ($a) {
			return array_map('strip_tags', (array) $a);
		}, $tr), $docLangs),
	);
	// Hauptfelder gelten für die Sprache, in der geschrieben wurde; bei Bestätigung in die anderen Sprachen übersetzen
	$aiErr = '';
	$srcL = hvSrcLang();
	$trNew = !empty($fields['i18n']) ? (array) json_decode($fields['i18n'], true) : array();
	$allPlan = $repo->getReservePlan(false);
	$oldP = ($id && isset($allPlan[$id])) ? $allPlan[$id] : null;
	$oldTr = ($oldP && !empty($oldP->i18n)) ? (array) json_decode($oldP->i18n, true) : array();
	$chg = array();
	foreach (array('label', 'basis', 'note') as $f) {
		$v = trim(strip_tags((string) $fields[$f]));
		$was = $oldP ? (string) ($oldTr[$srcL][$f] ?? $oldP->$f) : '';
		if ($v !== '') {
			$trNew[$srcL][$f] = $v;
		}
		if ($v !== '' && $v !== $was) {
			$chg[$f] = $v;
		}
	}
	$fields['i18n'] = $trNew ? json_encode($trNew, JSON_UNESCAPED_UNICODE) : null;
	if ($chg && GETPOST('hv_translate', 'int') === '1') {
		dol_include_once('/hausverwaltung/class/hvtranslator.class.php');
		if (HvTranslator::enabled()) {
			$skip = array();
			foreach ($trNew as $l => $vals) {
				foreach ((array) $vals as $f => $v) {
					if ($l !== $srcL && ($oldTr[$l][$f] ?? '') !== $v) {
						$skip[] = $l; // von Hand geändert
					}
				}
			}
			$fields['i18n'] = hvAiTranslateI18n($fields['i18n'], $chg, $srcL, array_unique($skip), $aiErr);
		}
	}
	if ($fields['label'] === '' || $fields['year_planned'] < 2000) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label').' / '.$langs->transnoentities('HvPlanYear')), null, 'errors');
		$action = $id ? 'edit' : 'create';
	} elseif ($repo->save('hv_reserve_plan', $fields, $id) > 0) {
		setEventMessages($langs->trans('HvSaved'), $aiErr !== '' ? array($langs->trans('HvAiFailed', $aiErr)) : null, $aiErr !== '' ? 'warnings' : 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($repo->error, null, 'errors');
	}
}
// Alle Planpositionen direkt in der Tabelle bearbeiten (ohne Import)
if ($canwrite && $action === 'saveall') {
	$existing = $repo->getReservePlan(false);
	$rows = (array) GETPOST('rows', 'array');
	$changed = $added = $deleted = 0;
	$errs = array();
	dol_include_once('/hausverwaltung/class/hvtranslator.class.php');
	$gridLang = hvSrcLang();
	$aiNew = HvTranslator::enabled();
	$aiYes = $aiNew && GETPOST('hv_translate', 'int') === '1';
	$toTranslate = array();
	foreach ($rows as $rid => $r) {
		$rid = (int) $rid;
		$r = array_map(function ($v) {
			return is_string($v) ? trim(strip_tags($v)) : $v;
		}, (array) $r);
		if ($rid > 0 && !isset($existing[$rid])) {
			continue;
		}
		if ($rid > 0 && !empty($r['del'])) {
			$repo->delete('hv_reserve_plan', $rid);
			$deleted++;
			continue;
		}
		$label = (string) ($r['label'] ?? '');
		$year = (int) ($r['year'] ?? 0);
		if ($rid <= 0 && $label === '') {
			continue; // leere neue Zeile
		}
		if ($label === '' || $year < 2000) {
			$errs[] = ($label !== '' ? $label : '#'.$rid).': '.$langs->trans('HvPlanYear').' / '.$langs->trans('Label');
			continue;
		}
		$fields = array(
			'label' => $label,
			'fk_building' => (int) ($r['building'] ?? 0),
			'alloc_key' => in_array($r['key'] ?? '', HvCalculator::keys(), true) ? $r['key'] : 'area',
			'year_planned' => $year,
			'amount' => (float) price2num((string) ($r['amount'] ?? '0')),
			'basis' => (string) ($r['basis'] ?? ''),
			'active' => !empty($r['active']) ? 1 : 0,
		);
		if ($rid > 0) {
			$old = $existing[$rid];
			// Texte gelten für die Sprache des Bearbeiters
			$tr = !empty($old->i18n) ? (array) json_decode($old->i18n, true) : array();
			$same = true;
			$rowChg = array();
			foreach (array('label', 'basis') as $f) {
				$was = (string) ($tr[$gridLang][$f] ?? $old->$f);
				if ($fields[$f] !== $was) {
					$same = false;
					if ($fields[$f] !== '') {
						$tr[$gridLang][$f] = $fields[$f];
						$rowChg[$f] = $fields[$f];
					}
				}
			}
			foreach (array('fk_building', 'alloc_key', 'year_planned', 'amount', 'active') as $k) {
				if ((string) $old->$k != (string) $fields[$k]) {
					$same = false;
				}
			}
			$fields['i18n'] = $tr ? json_encode($tr, JSON_UNESCAPED_UNICODE) : null;
			if (!$same && $repo->save('hv_reserve_plan', $fields, $rid) > 0) {
				$changed++;
				if ($rowChg && $aiYes) {
					$toTranslate[$rid] = $rowChg;
				}
			}
		} else {
			$fields['position'] = 0;
			$fields['note'] = '';
			$fields['i18n'] = json_encode(array($gridLang => array_filter(array('label' => $fields['label'], 'basis' => $fields['basis']))), JSON_UNESCAPED_UNICODE);
			$nid = $repo->save('hv_reserve_plan', $fields, 0);
			if ($nid > 0) {
				$added++;
				if ($aiNew) {
					$toTranslate[$nid] = array_filter(array('label' => $fields['label'], 'basis' => $fields['basis']));
				}
			}
		}
	}
	// geänderte und neue Texte in einem Durchgang übersetzen
	if ($toTranslate) {
		$flat = array();
		foreach ($toTranslate as $rid => $vals) {
			foreach ($vals as $f => $v) {
				$flat[$rid.'.'.$f] = $v;
			}
		}
		$tl = new HvTranslator();
		$res = $tl->translate($flat, $gridLang, hvDocLangs());
		if ($res === false) {
			$errs[] = $langs->trans('HvAiFailed', $tl->error);
		} else {
			$now = $repo->getReservePlan(false);
			foreach ($toTranslate as $rid => $vals) {
				if (!isset($now[$rid])) {
					continue;
				}
				$tr = !empty($now[$rid]->i18n) ? (array) json_decode($now[$rid]->i18n, true) : array();
				foreach ($res as $l => $tv) {
					foreach (array_keys($vals) as $f) {
						if (isset($tv[$rid.'.'.$f])) {
							$tr[$l][$f] = $tv[$rid.'.'.$f];
						}
					}
				}
				$repo->save('hv_reserve_plan', array('i18n' => json_encode($tr, JSON_UNESCAPED_UNICODE)), $rid);
			}
		}
	}
	setEventMessages($langs->trans('HvPlanSavedAll', $changed, $added, $deleted), $errs ?: null, $errs ? 'warnings' : 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].($errs ? '?action=editall' : '').'#planitems');
	exit;
}
if ($canwrite && $action === 'confirm_delete' && GETPOST('confirm') === 'yes') {
	$repo->delete('hv_reserve_plan', $id);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
// Rücklagen-Satz je Haus festlegen (protokolliert)
if ($canwrite && $action === 'setrates') {
	$projNow = (new HvReservePlan($db))->projection();
	$rr = new HvReserveRate($db);
	$rows = (array) GETPOST('rate', 'array');
	$n = 0;
	foreach ($buildings as $bid => $b) {
		if (!isset($rows[$bid])) {
			continue;
		}
		$r = (array) $rows[$bid];
		$mode = in_array($r['mode'] ?? '', HvReserveRate::modes(), true) ? $r['mode'] : 'manual';
		$factor = (float) price2num((string) ($r['factor'] ?? '1'));
		$manual = (float) price2num((string) ($r['manual'] ?? '0'));
		$note = trim(strip_tags((string) ($r['note'] ?? '')));
		$pb = $projNow['buildings'][$bid] ?? null;
		$newRate = HvReserveRate::compute($mode, $factor, $manual, $pb ? (float) $pb['required_rate'] : 0, HvReserveRate::legalMin());
		$same = $mode === (string) ($b->reserve_mode ?? '') && abs($newRate - (float) ($b->reserve_rate ?? 0)) < 0.00001 && $note === (string) ($b->reserve_note ?? '') && ($mode !== 'factor' || abs($factor - (float) ($b->reserve_factor ?? 1)) < 0.00001);
		if ($same) {
			continue;
		}
		if ($rr->setRate($user, $bid, $mode, $factor, $manual, $note, $projNow) !== false) {
			$n++;
		} else {
			setEventMessages($rr->error, null, 'errors');
		}
	}
	setEventMessages($langs->trans('HvRateApplied', $n), null);
	header('Location: '.$_SERVER['PHP_SELF'].'#rates');
	exit;
}
if ($canwrite && $action === 'import') {
	// JSON: {"reserveplan":[{label, basis, note (Text oder {"hr_HR": "...", "de_DE": "..."}), building (Kürzel oder leer), key, year, amount, active}]}
	$data = (!empty($_FILES['planfile']['tmp_name']) && is_uploaded_file($_FILES['planfile']['tmp_name'])) ? json_decode(file_get_contents($_FILES['planfile']['tmp_name']), true) : null;
	if (!is_array($data) || !isset($data['reserveplan']) || !is_array($data['reserveplan'])) {
		setEventMessages($langs->trans('HvImportBadFile'), null, 'errors');
	} else {
		$bmap = array();
		foreach ($buildings as $b) {
			$bmap[$b->ref] = (int) $b->rowid;
		}
		$n = 0;
		$skipped = array();
		$dupes = 0;
		$existing = array();
		if (GETPOSTINT('replace')) {
			// Bestehenden Plan ersetzen (nur nach gültiger Datei)
			$db->query("DELETE FROM ".$db->prefix()."hv_reserve_plan WHERE entity = ".((int) $conf->entity));
		} else {
			foreach ($repo->getReservePlan(false) as $ex) {
				$existing[mb_strtolower(trim($ex->label)).'|'.((int) $ex->fk_building).'|'.((int) $ex->year_planned)] = 1;
			}
		}
		$base = function ($v) use ($langs) {
			if (!is_array($v)) {
				return (string) $v;
			}
			return (string) ($v[$langs->defaultlang] ?? $v['de_DE'] ?? reset($v));
		};
		foreach ($data['reserveplan'] as $i => $p) {
			$fkb = 0;
			if (!empty($p['building'])) {
				if (!isset($bmap[$p['building']])) {
					$skipped[] = $base($p['label']).' ('.$p['building'].')';
					continue;
				}
				$fkb = $bmap[$p['building']];
			}
			$dk = mb_strtolower(trim($base($p['label']))).'|'.$fkb.'|'.((int) $p['year']);
			if (isset($existing[$dk])) {
				$dupes++;
				continue;
			}
			$existing[$dk] = 1;
			$tr = array();
			foreach (array('label', 'basis', 'note') as $f) {
				if (isset($p[$f]) && is_array($p[$f])) {
					$tr[$f] = $p[$f];
				}
			}
			if ($repo->save('hv_reserve_plan', array('label' => $base($p['label']), 'fk_building' => $fkb, 'alloc_key' => in_array($p['key'] ?? 'area', HvCalculator::keys(), true) ? ($p['key'] ?? 'area') : 'area', 'year_planned' => (int) $p['year'], 'amount' => (float) $p['amount'], 'basis' => $base($p['basis'] ?? ''), 'note' => $base($p['note'] ?? ''), 'position' => $i + 1, 'active' => isset($p['active']) ? (int) $p['active'] : 1, 'i18n' => hvPlanI18nFromInput($tr, array_unique(array_merge($docLangs, array_keys((array) ($p['label'] ?? array()))))))) > 0) {
				$n++;
			}
		}
		$warn = array();
		if ($skipped) {
			$warn[] = $langs->trans('HvPlanSkipped').': '.implode(', ', $skipped);
		}
		if ($dupes) {
			$warn[] = $langs->trans('HvPlanDupes', $dupes);
		}
		setEventMessages($langs->trans('HvPlanImported', $n), $warn ?: null, $warn ? 'warnings' : 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$items = $repo->getReservePlan(false);
$plan = new HvReservePlan($db);
$proj = $plan->projection();

// Ausgabesprache: Druckansicht in der gewählten Dokumentsprache, sonst Oberflächensprache
$plang = preg_replace('/[^a-zA-Z_]/', '', (string) GETPOST('lang', 'alpha'));
if (!in_array($plang, $docLangs, true)) {
	$plang = $orig;
}
if ($print) {
	$T = new Translate('', $conf);
	$T->setDefaultLang($plang);
	$T->loadLangs(array('main', 'banks', 'hausverwaltung@hausverwaltung'));
} else {
	$T = $langs;
}
$fm = function ($v) use ($T, $conf) {
	return price($v, 0, $T, 1, 2, 2, $conf->currency);
};
$txt = function ($p, $f) use ($print, $plang, $langs) {
	return hvI18n($p, $f, $print ? $plang : $langs->defaultlang);
};
$bname = function ($b) use ($print, $T) {
	return $print ? hvBuildingName($b, $T) : ($b ? $b->ref.' – '.$b->label : $T->trans('HvAllBuildings'));
};

if ($print) {
	top_htmlhead('', $T->transnoentities('HvReservePlan'));
	print '<body style="padding:16px;background:#fff;font-family:DejaVu Sans,Arial,sans-serif"><style>@media print{.noprint{display:none}} table{border-collapse:collapse;width:100%} td,th{padding:3px 6px;border-bottom:1px solid #ddd;font-size:11px;text-align:left} td.right,th.right{text-align:right;white-space:nowrap} .error{color:#a00} .badge{padding:2px 6px;border-radius:3px;font-size:10px;white-space:nowrap} .badge-status4{background:#d8efdc;color:#1d5e2a} .badge-status8{background:#f8d7d7;color:#8a1010} .copy{background:#fdecc8;color:#784600;padding:6px 10px;font-size:11px;margin-bottom:10px}</style>';
	print '<div class="noprint" style="margin-bottom:8px"><button onclick="window.print()">'.$T->trans('Print').'</button></div>';
	print '<h2>'.$T->trans('HvReservePlan').' – '.dol_escape_htmltag($mysoc->name).'</h2><p>'.$T->trans('HvPlanAssumptions', price($proj['inflation'], 0, $T), dol_print_date(dol_now(), 'day', false, $T)).'</p>';
} else {
	llxHeader('', $langs->trans('HvReservePlan'), hvHelpUrl('ruecklage/'), '', 0, 0, '', '', '', 'mod-hausverwaltung');
	print hvOutdatedBanner();
	$btn = '';
	foreach ($docLangs as $l) {
		$btn .= '<a class="butAction small" href="'.$_SERVER['PHP_SELF'].'?print=1&lang='.$l.'" target="_blank">'.$langs->trans('HvPrintView').' '.strtoupper(substr($l, 0, 2)).'</a>';
	}
	if ($canwrite) {
		$btn .= dolGetButtonTitle($langs->trans('HvPlanNewItem'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create');
	}
	print load_fiche_titre($langs->trans('HvReservePlan'), $btn, 'bank_account');
	print '<div class="opacitymedium">'.$langs->trans('HvPlanAssumptions', price($proj['inflation']), dol_print_date(dol_now(), 'day')).'</div><br>';
	if ($action === 'delete') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDelete'), 'confirm_delete', '', 0, 1);
	}
	if ($canwrite && in_array($action, array('create', 'edit'))) {
		$p = ($id && isset($items[$id])) ? $items[$id] : (object) array('label' => '', 'fk_building' => 0, 'alloc_key' => 'area', 'year_planned' => (int) dol_print_date(dol_now(), '%Y') + 5, 'amount' => '', 'basis' => '', 'position' => 0, 'active' => 1, 'note' => '', 'i18n' => '');
		$tr = !empty($p->i18n) ? json_decode($p->i18n, true) : array();
		dol_include_once('/hausverwaltung/class/hvtranslator.class.php');
		$ai = HvTranslator::enabled();
		$srcL = hvSrcLang();
		$shown = function ($f) use ($tr, $p, $srcL) {
			return (string) ($tr[$srcL][$f] ?? $p->$f);
		};
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="hv-ask-translate" data-hastr="'.(count($tr) > 0 ? '1' : '0').'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.$id.'">';
		print '<table class="border centpercent tableforfieldedit">';
		if ($ai) {
			print '<tr><td>'.$langs->trans('HvAiWrittenIn').'</td><td>'.hvSrcLangSelect().' <span class="opacitymedium small">'.$langs->trans('HvAiAutoHelp').'</span></td></tr>';
		}
		print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Label').'</td><td><input name="label" class="minwidth400" data-hvsrc="1" data-orig="'.dol_escape_htmltag($shown('label')).'" value="'.dol_escape_htmltag($shown('label')).'"></td></tr>';
		print '<tr><td>'.$langs->trans('HvBuilding').'</td><td>'.hvSelectBuilding('fk_building', (string) (int) $p->fk_building, $buildings, true).' '.$langs->trans('HvAllocKey').' <select name="alloc_key" class="flat">';
		foreach (HvCalculator::keys() as $k) {
			print '<option value="'.$k.'"'.($p->alloc_key === $k ? ' selected' : '').'>'.$langs->trans('HvKey_'.$k).'</option>';
		}
		print '</select> <span class="opacitymedium">('.$langs->trans('HvPlanKeyHelp').')</span></td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('HvPlanYear').'</td><td><input name="year_planned" class="width75" value="'.((int) $p->year_planned).'"></td></tr>';
		print '<tr><td>'.$langs->trans('HvPlanAmount').'</td><td><input name="amount" class="width100" value="'.dol_escape_htmltag(price2num($p->amount)).'"> €</td></tr>';
		print '<tr><td>'.$langs->trans('HvPlanBasis').'</td><td><input name="basis" class="minwidth400" data-hvsrc="1" data-orig="'.dol_escape_htmltag($shown('basis')).'" value="'.dol_escape_htmltag($shown('basis')).'"></td></tr>';
		print '<tr><td>'.$langs->trans('Active').'</td><td><input type="checkbox" name="active" value="1"'.($p->active ? ' checked' : '').'> <span class="opacitymedium">'.$langs->trans('HvPlanActiveHelp').'</span></td></tr>';
		print '<tr><td>'.$langs->trans('Note').'</td><td><textarea name="note" class="quatrevingtpercent" rows="2" data-hvsrc="1" data-orig="'.dol_escape_htmltag($shown('note')).'">'.dol_escape_htmltag($shown('note')).'</textarea></td></tr>';
		print '</table><br><div class="opacitymedium">'.$langs->trans('HvPlanTranslationsHelp').'</div>';
		print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Language').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('HvPlanBasis').'</th><th>'.$langs->trans('Note').'</th></tr>';
		foreach ($docLangs as $l) {
			print '<tr class="oddeven"><td>'.$l.($l === $orig ? ' <span class="opacitymedium">(Original)</span>' : '').'</td>';
			foreach (array('label', 'basis', 'note') as $f) {
				print '<td><input name="tr_'.$f.'['.$l.']" class="centpercent" value="'.dol_escape_htmltag($tr[$l][$f] ?? '').'"></td>';
			}
			print '</tr>';
		}
		print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a></div></form><br>';
		print hvTranslateAskJs();
	}
}

// Zusammenfassung je Gebäude
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$T->trans('HvBuilding').'</th><th>'.$T->trans('HvReserveAccount').'</th><th class="right">'.$T->trans('HvBalance').'</th><th class="right">'.$T->trans('HvPlanMonthly').'</th><th class="right">'.$T->trans('HvPlanCurrentRate').'</th><th class="right">'.$T->trans('HvPlanRequiredRate').'</th><th class="right">'.$T->trans('HvPlanMinBalance').'</th><th>'.$T->trans('Status').'</th></tr>';
foreach ($proj['buildings'] as $o) {
	$ok = $o['shortfall_year'] === null;
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($bname($o['building'])).'</td><td>'.dol_escape_htmltag((string) $o['account']).'</td>';
	print '<td class="right">'.$fm($o['start']).'</td><td class="right">'.$fm($o['monthly']).'</td>';
	print '<td class="right">'.price($o['current_rate'], 0, $T, 1, 2, 2).' €/m²</td><td class="right">'.price($o['required_rate'], 0, $T, 1, 2, 2).' €/m²</td>';
	print '<td class="right '.($ok ? '' : 'error').'">'.$fm($o['min_balance']).'</td>';
	print '<td>'.($ok ? '<span class="badge badge-status4">'.$T->trans('HvPlanOk').'</span>' : '<span class="badge badge-status8 error">'.$T->trans('HvPlanShortfall', $o['shortfall_year']).'</span>').'</td></tr>';
}
print '</table></div><div class="opacitymedium" style="font-size:11px">'.$T->trans('HvPlanRequiredHelp').'</div><br>';

// Festlegung des Rücklagen-Satzes je Haus (gesetzliches Minimum, Plan, Faktor, manuell) – nachvollziehbar mit Verlauf
$legal = HvReserveRate::legalMin();
$modeTxt = function ($m, $f) use ($T) {
	return $m === 'factor' ? $T->trans('HvRateMode_plan').' × '.price($f, 0, $T, 1, 0, 2) : $T->trans('HvRateMode_'.($m ?: 'manual'));
};
print '<a id="rates"></a>';
print $print ? '<h3>'.$T->trans('HvRateTitle').'</h3>' : load_fiche_titre($T->trans('HvRateTitle'), '', '');
print '<div class="opacitymedium small">'.$T->trans('HvRateLegalSource', price(getDolGlobalString('HAUSVERWALTUNG_RESERVE_LEGAL_PCT', '0.54'), 0, $T, 1, 0, 2), price(getDolGlobalString('HAUSVERWALTUNG_ETALON_PRICE', '796.34'), 0, $T, 1, 2, 2), price($legal, 0, $T, 1, 4, 4)).'</div>';
if (!$print && $canwrite) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'#rates"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setrates">';
}
print '<div class="div-table-responsive"><table class="noborder centpercent hvrates"><tr class="liste_titre"><th>'.$T->trans('HvBuilding').'</th><th class="right">m²</th><th class="right">'.$T->trans('HvRateLegal').'</th><th class="right">'.$T->trans('HvRatePlanMin').'</th><th>'.$T->trans('HvRateMode').'</th><th class="right">'.$T->trans('HvRateNew').'</th><th class="right">'.$T->trans('HvRateMonthly').'</th><th>'.$T->trans('HvRateNote').'</th></tr>';
foreach ($proj['buildings'] as $bid => $o) {
	$b = $buildings[$bid] ?? $o['building'];
	$planMin = (float) $o['required_rate'];
	$cur = (float) ($b->reserve_rate ?? 0) > 0 ? (float) $b->reserve_rate : (float) $o['current_rate'];
	$m = (string) ($b->reserve_mode ?? 'manual');
	$fac = (float) ($b->reserve_factor ?? 1);
	print '<tr class="oddeven" data-plan="'.price2num($planMin).'" data-legal="'.price2num($legal).'" data-area="'.price2num($o['area']).'"><td>'.dol_escape_htmltag($bname($b)).'</td><td class="right">'.price($o['area'], 0, $T, 1, 2, 2).'</td>';
	print '<td class="right">'.price($legal, 0, $T, 1, 2, 2).' €</td><td class="right">'.price($planMin, 0, $T, 1, 2, 2).' €</td>';
	if (!$print && $canwrite) {
		$sel = '<select name="rate['.$bid.'][mode]" class="flat hvmode">';
		foreach (HvReserveRate::modes() as $mm) {
			$sel .= '<option value="'.$mm.'"'.($mm === $m ? ' selected' : '').'>'.$langs->trans('HvRateMode_'.$mm).'</option>';
		}
		$sel .= '</select>';
		print '<td class="nowraponall">'.$sel.' <span class="hvfac"'.($m === 'factor' ? '' : ' style="display:none"').'>× <input name="rate['.$bid.'][factor]" class="width50 right hvfacin" value="'.price2num($fac).'"></span><span class="hvman"'.($m === 'manual' ? '' : ' style="display:none"').'> <input name="rate['.$bid.'][manual]" class="width50 right hvmanin" value="'.price2num($cur).'"> €</span></td>';
		print '<td class="right"><strong class="hvnew">'.price($cur, 0, $T, 1, 2, 2).' €</strong><br><span class="opacitymedium small">'.$langs->trans('HvRateCurrent').': '.price($cur, 0, $T, 1, 2, 2).' €</span><div class="hvwarn small" style="color:#a00;font-weight:bold"></div></td>';
		print '<td class="right hvmon">'.$fm($cur * $o['area']).'</td>';
		print '<td><input name="rate['.$bid.'][note]" class="minwidth200 widthcentpercentminusx" value="'.dol_escape_htmltag((string) ($b->reserve_note ?? '')).'" placeholder="'.dol_escape_htmltag($langs->trans('HvRateNotePlaceholder')).'"></td></tr>';
	} else {
		print '<td>'.dol_escape_htmltag($modeTxt($m, $fac)).'</td><td class="right"><strong>'.price($cur, 0, $T, 1, 2, 2).' €</strong></td><td class="right">'.$fm($cur * $o['area']).'</td><td class="small">'.dol_escape_htmltag((string) ($b->reserve_note ?? '')).'</td></tr>';
	}
}
print '</table></div>';
print '<div class="opacitymedium small">'.$T->trans('HvRateExplain').'</div>';
if (!$print && $canwrite) {
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('HvRateApply').'"> <span class="opacitymedium small">'.$langs->trans('HvRateApplyHelp').'</span></div></form>';
	$jsT = array('below_legal' => dol_escape_js($langs->transnoentities('HvRateBelowLegal')), 'below_plan' => dol_escape_js($langs->transnoentities('HvRateBelowPlan')));
	print '<script>
jQuery(function ($) {
	var fmt = function (v) { return v.toFixed(2).replace(".", ","); };
	var ceil2 = function (x) { return Math.ceil(Math.round(x * 1e6) / 1e4) / 100; };
	$("table.hvrates tr[data-plan]").each(function () {
		var tr = $(this), plan = parseFloat(tr.data("plan")), legal = parseFloat(tr.data("legal")), area = parseFloat(tr.data("area"));
		var upd = function () {
			var m = tr.find(".hvmode").val(), f = parseFloat(String(tr.find(".hvfacin").val()).replace(",", ".")) || 0, man = parseFloat(String(tr.find(".hvmanin").val()).replace(",", ".")) || 0, r;
			tr.find(".hvfac").toggle(m === "factor"); tr.find(".hvman").toggle(m === "manual");
			if (m === "legal") r = ceil2(legal); else if (m === "plan") r = ceil2(Math.max(plan, legal)); else if (m === "factor") r = ceil2(Math.max(plan * f, legal)); else r = man;
			tr.find(".hvnew").text(fmt(r) + " €");
			tr.find(".hvmon").text(fmt(r * area) + " €");
			var w = []; if (r < legal - 1e-9) w.push("'.$jsT['below_legal'].'"); if (r < plan - 1e-9) w.push("'.$jsT['below_plan'].'");
			tr.find(".hvwarn").text(w.join(" "));
		};
		tr.find("select, input").on("change keyup", upd); upd();
	});
});
</script>';
}
// Verlauf
$hist = (new HvReserveRate($db))->history($print ? 20 : 50);
if ($hist) {
	print $print ? '<h4>'.$T->trans('HvRateHistory').'</h4>' : '<br><details'.(GETPOST('history', 'int') ? ' open' : '').'><summary class="opacitymedium" style="cursor:pointer">'.$T->trans('HvRateHistory').' ('.count($hist).')</summary>';
	print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$T->trans('HvRateDate').'</th><th>'.$T->trans('HvBuilding').'</th><th>'.$T->trans('HvRateMode').'</th><th class="right">'.$T->trans('HvRateChange').'</th><th class="right">'.$T->trans('HvRateLegal').'</th><th class="right">'.$T->trans('HvRatePlanMin').'</th><th class="right">'.$T->trans('HvRatePlanVolume').'</th><th class="right">'.$T->trans('HvRateBalance').'</th><th>'.$T->trans('HvRateNote').'</th>'.($print ? '' : '<th>'.$langs->trans('User').'</th>').'</tr>';
	foreach ($hist as $h) {
		$hb = $buildings[(int) $h->fk_building] ?? null;
		print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($db->jdate($h->datec), 'day', false, $T).'</td><td>'.dol_escape_htmltag($hb ? $bname($hb) : '#'.$h->fk_building).'</td><td>'.dol_escape_htmltag($modeTxt($h->mode, (float) $h->factor)).'</td>';
		print '<td class="right nowraponall">'.price($h->rate_old, 0, $T, 1, 2, 2).' → <strong>'.price($h->rate_new, 0, $T, 1, 2, 2).'</strong> €</td><td class="right">'.($h->legal_min !== null ? price($h->legal_min, 0, $T, 1, 2, 2) : '').'</td><td class="right">'.($h->plan_min !== null ? price($h->plan_min, 0, $T, 1, 2, 2) : '').'</td>';
		print '<td class="right">'.($h->plan_total !== null ? $fm($h->plan_total) : '').'</td><td class="right">'.($h->balance !== null ? $fm($h->balance) : '').'</td><td class="small">'.dol_escape_htmltag((string) $h->note).'</td>'.($print ? '' : '<td class="small">'.dol_escape_htmltag((string) $h->login).'</td>').'</tr>';
	}
	print '</table></div>'.($print ? '' : '</details>');
}
print '<br>';

// Prognose je Gebäude und Jahr
print $print ? '<h3>'.$T->trans('HvPlanProjection').'</h3>' : load_fiche_titre($T->trans('HvPlanProjection'), '', '');
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$T->trans('Year').'</th>';
foreach ($proj['buildings'] as $o) {
	print '<th class="right">'.dol_escape_htmltag($bname($o['building'])).'<br>'.$T->trans('HvMovesIn').'</th><th class="right">'.$T->trans('HvMovesOut').'</th><th class="right">'.$T->trans('HvPlanEndBalance').'</th>';
}
print '<th>'.$T->trans('HvPlanMeasures').'</th></tr>';
foreach ($proj['years'] as $yr) {
	print '<tr class="oddeven"><td>'.$yr.'</td>';
	$labels = array();
	foreach ($proj['buildings'] as $o) {
		$r = $o['rows'][$yr];
		print '<td class="right">'.$fm($r['in']).'</td><td class="right">'.($r['out'] > 0 ? $fm(-$r['out']) : '').'</td><td class="right '.($r['end'] < 0 ? 'error' : '').'"><strong>'.$fm($r['end']).'</strong></td>';
		foreach ($r['items'] as $it) {
			$labels[$txt($it['obj'], 'label')] = true;
		}
	}
	print '<td class="small">'.dol_escape_htmltag(implode('; ', array_keys($labels))).'</td></tr>';
}
print '</table></div><br>';

// Planpositionen
$itemBtns = '';
if (!$print && $canwrite && $action !== 'editall') {
	$itemBtns = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=editall#planitems">'.img_picto('', 'edit', 'class="pictofixedwidth"').$langs->trans('HvPlanEditAll').'</a>';
	$itemBtns .= '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=create">'.img_picto('', 'add', 'class="pictofixedwidth"').$langs->trans('HvPlanNewItem').'</a>';
}
print '<a id="planitems"></a>';
print $print ? '<h3>'.$T->trans('HvPlanItems').'</h3>' : load_fiche_titre($T->trans('HvPlanItems'), $itemBtns, '');
if (!$print && $canwrite && $action === 'editall') {
	print '<div class="opacitymedium">'.$langs->trans('HvPlanEditAllHelp').'</div>';
	$gl = hvSrcLang();
	$anyTr = false;
	foreach ($items as $p0) {
		if (!empty($p0->i18n)) {
			$anyTr = true;
		}
	}
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="hv-ask-translate" data-hastr="'.($anyTr ? '1' : '0').'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="saveall"><input type="hidden" name="hv_srclang" value="'.dol_escape_htmltag($gl).'">';
	print '<style>.hvplanedit td{vertical-align:middle}.hvplanedit input.hvw{width:100%;box-sizing:border-box}.hvplanedit select{max-width:170px}</style>';
	print '<div class="div-table-responsive"><table class="noborder centpercent hvplanedit"><tr class="liste_titre"><th>'.$langs->trans('HvPlanYear').'</th><th style="min-width:320px">'.$langs->trans('Label').'</th><th>'.$langs->trans('HvBuilding').' / '.$langs->trans('HvAllocKey').'</th><th class="right">'.$langs->trans('HvPlanAmount').' (€)</th><th style="min-width:240px">'.$langs->trans('HvPlanBasis').'</th><th class="center">'.$langs->trans('Active').'</th><th class="center">'.$langs->trans('Delete').'</th></tr>';
	$keySel = function ($name, $cur) use ($langs) {
		$h = '<select name="'.$name.'" class="flat">';
		foreach (HvCalculator::keys() as $k) {
			$h .= '<option value="'.$k.'"'.($cur === $k ? ' selected' : '').'>'.$langs->trans('HvKey_'.$k).'</option>';
		}
		return $h.'</select>';
	};
	$rowsEdit = $items;
	for ($i = 1; $i <= 3; $i++) {
		$rowsEdit['new'.$i] = (object) array('rowid' => -$i, 'label' => '', 'fk_building' => 0, 'alloc_key' => 'area', 'year_planned' => '', 'amount' => '', 'basis' => '', 'active' => 1);
	}
	foreach ($rowsEdit as $p) {
		$n = 'rows['.((int) $p->rowid).']';
		$isNew = (int) $p->rowid < 0;
		print '<tr class="oddeven'.($isNew ? ' hvnewrow' : '').'">';
		print '<td><input name="'.$n.'[year]" class="width50" inputmode="numeric" value="'.dol_escape_htmltag((string) $p->year_planned).'"'.($isNew ? ' placeholder="'.((int) dol_print_date(dol_now(), '%Y') + 5).'"' : '').'></td>';
		$ptr = !empty($p->i18n) ? (array) json_decode($p->i18n, true) : array();
		$lblV = (string) ($ptr[$gl]['label'] ?? $p->label);
		$basV = (string) ($ptr[$gl]['basis'] ?? $p->basis);
		$nw = $isNew ? ' data-hvnew="1"' : '';
		print '<td><input name="'.$n.'[label]" class="hvw" data-hvsrc="1"'.$nw.' data-orig="'.dol_escape_htmltag($lblV).'" value="'.dol_escape_htmltag($lblV).'"'.($isNew ? ' placeholder="'.dol_escape_htmltag($langs->trans('HvPlanNewItem')).'"' : '').'></td>';
		print '<td>'.hvSelectBuilding($n.'[building]', (string) (int) $p->fk_building, $buildings, true).' '.'<br>'.$keySel($n.'[key]', $p->alloc_key ?: 'area').'</td>';
		print '<td class="right"><input name="'.$n.'[amount]" class="width75 right" inputmode="decimal" value="'.($p->amount !== '' ? dol_escape_htmltag(price2num($p->amount)) : '').'"></td>';
		print '<td><input name="'.$n.'[basis]" class="hvw" data-hvsrc="1"'.$nw.' data-orig="'.dol_escape_htmltag($basV).'" value="'.dol_escape_htmltag($basV).'"></td>';
		print '<td class="center"><input type="checkbox" name="'.$n.'[active]" value="1"'.($p->active ? ' checked' : '').'></td>';
		print '<td class="center">'.($isNew ? '' : '<input type="checkbox" name="'.$n.'[del]" value="1">').'</td></tr>';
	}
	print '</table></div><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'#planitems">'.$langs->trans('Cancel').'</a></div></form>';
	print hvTranslateAskJs();
	llxFooter();
	$db->close();
	exit;
}
print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>'.$T->trans('HvPlanYear').'</th><th>'.$T->trans('Label').'</th><th>'.$T->trans('HvBuilding').'</th><th class="right">'.$T->trans('HvPlanAmount').'</th><th>'.$T->trans('HvPlanBasis').'</th>'.(!$print ? '<th class="center">'.$langs->trans('Active').'</th><th></th>' : '').'</tr>';
$sum = 0;
foreach ($items as $p) {
	if ($print && !$p->active) {
		continue;
	}
	if ($p->active) {
		$sum += $p->amount;
	}
	$b = (int) $p->fk_building ? ($buildings[(int) $p->fk_building] ?? null) : null;
	$note = $txt($p, 'note');
	$lbl = dol_escape_htmltag($txt($p, 'label'));
	if (!$print && $canwrite) {
		$lbl = '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$p->rowid.'" title="'.dol_escape_htmltag($langs->trans('HvPlanEditDetails')).'">'.$lbl.'</a>';
	}
	print '<tr class="oddeven'.($p->active ? '' : ' opacitymedium').'"><td>'.((int) $p->year_planned).'</td><td>'.$lbl.($note ? '<br><span class="opacitymedium small">'.dol_escape_htmltag($note).'</span>' : '').'</td>';
	print '<td>'.dol_escape_htmltag($bname($b)).(!$b ? ' <span class="opacitymedium">('.$T->trans('HvKey_'.($p->alloc_key ?: 'area')).')</span>' : '').'</td>';
	print '<td class="right">'.$fm($p->amount).'</td><td class="small">'.dol_escape_htmltag($txt($p, 'basis')).'</td>';
	if (!$print) {
		$hasTr = !empty($p->i18n) ? count(json_decode($p->i18n, true)) : 0;
		print '<td class="center">'.yn($p->active).'</td><td class="right nowraponall"><span class="opacitymedium small" title="'.$langs->trans('HvPlanTranslations').'">'.$hasTr.'/'.count($docLangs).'</span> ';
		if ($canwrite) {
			print '<a class="editfielda paddingright" href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$p->rowid.'">'.img_edit().'</a><a href="'.$_SERVER['PHP_SELF'].'?action=delete&token='.newToken().'&id='.$p->rowid.'">'.img_delete().'</a>';
		}
		print '</td>';
	}
	print '</tr>';
}
print '<tr class="liste_total"><td colspan="3">'.$T->trans('Total').' ('.$T->trans('HvPlanTodayPrices').')</td><td class="right">'.$fm($sum).'</td><td colspan="'.($print ? 1 : 3).'"></td></tr></table></div>';

if ($print) {
	print '<p style="font-size:10px;color:#666;margin-top:12px">'.$T->trans('HvPlanDisclaimer').'</p>';
	print '<p style="font-size:9px;color:#999">'.dol_escape_htmltag(hvDocStamp($T)).'</p></body></html>';
	$db->close();
	exit;
}
if ($canwrite) {
	print '<br><details><summary class="opacitymedium" style="cursor:pointer">'.$langs->trans('HvPlanImport').' ('.$langs->trans('HvPlanImportOptional').')</summary><br>';
	print '<p>'.$langs->trans('HvPlanImportHelp').' <a href="'.dol_buildpath('/hausverwaltung/data/vorlage_ruecklagenplan.json', 1).'" download>'.$langs->trans('HvImportTemplate').'</a></p>';
	print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="import"><input type="file" name="planfile" accept=".json,application/json"> <label><input type="checkbox" name="replace" value="1" checked> '.$langs->trans('HvPlanReplace').'</label> <input type="submit" class="button" value="'.$langs->trans('HvPlanImport').'"></form></details>';
}
llxFooter();
$db->close();
