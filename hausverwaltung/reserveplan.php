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
if (!$user->hasRight('hausverwaltung', 'read')) {
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
	if ($fields['label'] === '' || $fields['year_planned'] < 2000) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label').' / '.$langs->transnoentities('HvPlanYear')), null, 'errors');
		$action = $id ? 'edit' : 'create';
	} elseif ($repo->save('hv_reserve_plan', $fields, $id) > 0) {
		setEventMessages($langs->trans('HvSaved'), null);
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($repo->error, null, 'errors');
	}
}
if ($canwrite && $action === 'confirm_delete' && GETPOST('confirm') === 'yes') {
	$repo->delete('hv_reserve_plan', $id);
	header('Location: '.$_SERVER['PHP_SELF']);
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
		setEventMessages($langs->trans('HvPlanImported', $n), $skipped ? array($langs->trans('HvPlanSkipped').': '.implode(', ', $skipped)) : null, $skipped ? 'warnings' : 'mesgs');
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
$txt = function ($p, $f) use ($print, $plang) {
	return $print ? hvI18n($p, $f, $plang) : (string) $p->$f;
};
$bname = function ($b) use ($print, $T) {
	return $print ? hvBuildingName($b, $T) : ($b ? $b->ref.' – '.$b->label : $T->trans('HvAllBuildings'));
};

if ($print) {
	top_htmlhead('', $T->transnoentities('HvReservePlan'));
	print '<body style="padding:16px;background:#fff;font-family:DejaVu Sans,Arial,sans-serif"><style>@media print{.noprint{display:none}} table{border-collapse:collapse;width:100%} td,th{padding:3px 6px;border-bottom:1px solid #ddd;font-size:11px;text-align:left} td.right,th.right{text-align:right;white-space:nowrap} .error{color:#a00} .badge{padding:2px 6px;border-radius:3px;font-size:10px;white-space:nowrap} .badge-status4{background:#d8efdc;color:#1d5e2a} .badge-status8{background:#f8d7d7;color:#8a1010} .copy{background:#fdecc8;color:#784600;padding:6px 10px;font-size:11px;margin-bottom:10px}</style>';
	print '<div class="noprint" style="margin-bottom:8px"><button onclick="window.print()">'.$T->trans('Print').'</button></div>';
	if (substr($plang, 0, 2) !== substr($orig, 0, 2)) {
		$O = new Translate('', $conf);
		$O->setDefaultLang($orig);
		$O->loadLangs(array('hausverwaltung@hausverwaltung'));
		print '<div class="copy"><b>'.$T->trans('HvPdfCopy').'</b><br>'.$T->trans('HvPdfCopyNote').'<br>'.$O->trans('HvPdfCopyNote').'</div>';
	}
	print '<h2>'.$T->trans('HvReservePlan').' – '.dol_escape_htmltag($mysoc->name).'</h2><p>'.$T->trans('HvPlanAssumptions', price($proj['inflation'], 0, $T), dol_print_date(dol_now(), 'day', false, $T)).'</p>';
} else {
	llxHeader('', $langs->trans('HvReservePlan'), '', '', 0, 0, '', '', '', 'mod-hausverwaltung');
	$btn = '';
	foreach ($docLangs as $l) {
		$btn .= '<a class="butAction small" href="'.$_SERVER['PHP_SELF'].'?print=1&lang='.$l.'" target="_blank">'.$langs->trans('HvPrintView').' '.strtoupper(substr($l, 0, 2)).'</a>';
	}
	if ($canwrite) {
		$btn .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create');
	}
	print load_fiche_titre($langs->trans('HvReservePlan'), $btn, 'bank_account');
	print '<div class="opacitymedium">'.$langs->trans('HvPlanAssumptions', price($proj['inflation']), dol_print_date(dol_now(), 'day')).'</div><br>';
	if ($action === 'delete') {
		print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('ConfirmDelete'), 'confirm_delete', '', 0, 1);
	}
	if ($canwrite && in_array($action, array('create', 'edit'))) {
		$p = ($id && isset($items[$id])) ? $items[$id] : (object) array('label' => '', 'fk_building' => 0, 'alloc_key' => 'area', 'year_planned' => (int) dol_print_date(dol_now(), '%Y') + 5, 'amount' => '', 'basis' => '', 'position' => 0, 'active' => 1, 'note' => '', 'i18n' => '');
		$tr = !empty($p->i18n) ? json_decode($p->i18n, true) : array();
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.$id.'">';
		print '<table class="border centpercent tableforfieldedit">';
		print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Label').'</td><td><input name="label" class="minwidth400" value="'.dol_escape_htmltag($p->label).'"></td></tr>';
		print '<tr><td>'.$langs->trans('HvBuilding').'</td><td>'.hvSelectBuilding('fk_building', (string) (int) $p->fk_building, $buildings, true).' '.$langs->trans('HvAllocKey').' <select name="alloc_key" class="flat">';
		foreach (HvCalculator::keys() as $k) {
			print '<option value="'.$k.'"'.($p->alloc_key === $k ? ' selected' : '').'>'.$langs->trans('HvKey_'.$k).'</option>';
		}
		print '</select> <span class="opacitymedium">('.$langs->trans('HvPlanKeyHelp').')</span></td></tr>';
		print '<tr><td class="fieldrequired">'.$langs->trans('HvPlanYear').'</td><td><input name="year_planned" class="width75" value="'.((int) $p->year_planned).'"></td></tr>';
		print '<tr><td>'.$langs->trans('HvPlanAmount').'</td><td><input name="amount" class="width100" value="'.dol_escape_htmltag(price2num($p->amount)).'"> €</td></tr>';
		print '<tr><td>'.$langs->trans('HvPlanBasis').'</td><td><input name="basis" class="minwidth400" value="'.dol_escape_htmltag($p->basis).'"></td></tr>';
		print '<tr><td>'.$langs->trans('Active').'</td><td><input type="checkbox" name="active" value="1"'.($p->active ? ' checked' : '').'> <span class="opacitymedium">'.$langs->trans('HvPlanActiveHelp').'</span></td></tr>';
		print '<tr><td>'.$langs->trans('Note').'</td><td><textarea name="note" class="quatrevingtpercent" rows="2">'.dol_escape_htmltag($p->note).'</textarea></td></tr>';
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
print $print ? '<h3>'.$T->trans('HvPlanItems').'</h3>' : load_fiche_titre($T->trans('HvPlanItems'), '', '');
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
	print '<tr class="oddeven'.($p->active ? '' : ' opacitymedium').'"><td>'.((int) $p->year_planned).'</td><td>'.dol_escape_htmltag($txt($p, 'label')).($note ? '<br><span class="opacitymedium small">'.dol_escape_htmltag($note).'</span>' : '').'</td>';
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
	print '<p style="font-size:10px;color:#666;margin-top:12px">'.$T->trans('HvPlanDisclaimer').'</p></body></html>';
	$db->close();
	exit;
}
if ($canwrite) {
	print '<br>'.load_fiche_titre($langs->trans('HvPlanImport'), '', '');
	print '<p>'.$langs->trans('HvPlanImportHelp').' <a href="'.dol_buildpath('/hausverwaltung/data/vorlage_ruecklagenplan.json', 1).'" download>'.$langs->trans('HvImportTemplate').'</a></p>';
	print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="import"><input type="file" name="planfile" accept=".json,application/json"> <input type="submit" class="button" value="'.$langs->trans('HvPlanImport').'"></form>';
}
llxFooter();
$db->close();
