<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Einstellungen + Import Startdaten */
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvinvoicer.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$langs->loadLangs(array('admin', 'banks', 'bills', 'hausverwaltung@hausverwaltung'));
if (!$user->admin) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$form = new Form($db);

if ($action === 'save') {
	dolibarr_set_const($db, 'HAUSVERWALTUNG_BANK_ACCOUNT', GETPOSTINT('HAUSVERWALTUNG_BANK_ACCOUNT'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_PAYMENT_MODE', GETPOSTINT('HAUSVERWALTUNG_PAYMENT_MODE'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_DUE_DAY', max(1, min(28, GETPOSTINT('HAUSVERWALTUNG_DUE_DAY'))), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_PLAN_INFLATION', price2num(GETPOST('HAUSVERWALTUNG_PLAN_INFLATION', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_PLAN_YEARS', max(5, min(40, GETPOSTINT('HAUSVERWALTUNG_PLAN_YEARS'))), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_SENT_TO_THIRDPARTY', GETPOSTINT('HAUSVERWALTUNG_SENT_TO_THIRDPARTY') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	foreach (array('HAUSVERWALTUNG_RESERVE_LEGAL_PCT', 'HAUSVERWALTUNG_ETALON_PRICE') as $cn) {
		$cv = price2num(GETPOST($cn, 'alphanohtml'));
		if ($cv !== '' && (float) $cv > 0) {
			dolibarr_set_const($db, $cn, $cv, 'chaine', 0, '', $conf->entity);
		}
	}
	dolibarr_set_const($db, 'HAUSVERWALTUNG_INFO_ATTACH', GETPOSTINT('HAUSVERWALTUNG_INFO_ATTACH') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	$hurl = trim(GETPOST('HAUSVERWALTUNG_HELP_URL', 'alphanohtml'));
	dolibarr_set_const($db, 'HAUSVERWALTUNG_HELP_URL', preg_match('#^https?://#i', $hurl) ? $hurl : '', 'chaine', 0, '', $conf->entity);
	$purl = trim(GETPOST('HAUSVERWALTUNG_OWNER_PORTAL_URL', 'alphanohtml'));
	dolibarr_set_const($db, 'HAUSVERWALTUNG_OWNER_PORTAL_URL', preg_match('#^https?://#i', $purl) ? $purl : '', 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_PDF_DIGITAL_NOTE', GETPOSTINT('HAUSVERWALTUNG_PDF_DIGITAL_NOTE') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_MAIL_FROM', GETPOST('HAUSVERWALTUNG_MAIL_FROM', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_MAIL_BCC', GETPOST('HAUSVERWALTUNG_MAIL_BCC', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_MANAGER_SOCID', GETPOSTINT('HAUSVERWALTUNG_MANAGER_SOCID'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_ADMIN_VAT_RATE', price2num(GETPOST('HAUSVERWALTUNG_ADMIN_VAT_RATE', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HAUSVERWALTUNG_VAT_RATE', price2num(GETPOST('HAUSVERWALTUNG_VAT_RATE', 'alphanohtml')), 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// KI-Übersetzung: Schlüssel speichern / löschen / testen (eigenes Formular, Schlüssel wird nie angezeigt)
dol_include_once('/hausverwaltung/class/hvtranslator.class.php');
if ($action === 'aisave') {
	$k = trim((string) GETPOST('ai_key', 'none'));
	if (GETPOSTINT('ai_delete')) {
		HvTranslator::saveKey($db, '');
	} elseif ($k !== '') {
		HvTranslator::saveKey($db, $k);
	}
	dolibarr_set_const($db, 'HAUSVERWALTUNG_AI_TRANSLATE', GETPOSTINT('ai_on') ? '1' : '0', 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null);
	header('Location: '.$_SERVER['PHP_SELF'].'#ai');
	exit;
}
if ($action === 'aitest') {
	$tl = new HvTranslator();
	$res = $tl->translate(array('t' => 'Gebäudeversicherung Haus 1'), 'de_DE', array('hr_HR', 'en_US'));
	if ($res && !empty($res['hr_HR']['t'])) {
		setEventMessages($langs->trans('HvAiTestOk', $res['hr_HR']['t'], $res['en_US']['t'] ?? '', (int) ($tl->usage['input_tokens'] ?? 0) + (int) ($tl->usage['output_tokens'] ?? 0)), null);
	} else {
		setEventMessages($langs->trans('HvAiFailed', $tl->error ?: '?'), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'#ai');
	exit;
}
if ($action === 'importseed') {
	$repo = new HvRepository($db);
	// Startdaten aus hochgeladener JSON-Datei (Aufbau siehe data/vorlage_startdaten.json)
	$data = null;
	if (!empty($_FILES['seedfile']['tmp_name']) && is_uploaded_file($_FILES['seedfile']['tmp_name'])) {
		$data = json_decode(file_get_contents($_FILES['seedfile']['tmp_name']), true);
	}
	if (!is_array($data) || !isset($data['buildings'], $data['units'], $data['costitems'])) {
		setEventMessages($langs->trans('HvImportBadFile'), null, 'errors');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	$bmap = array();
	foreach ($repo->getBuildings(false) as $b) {
		$bmap[$b->ref] = (int) $b->rowid;
	}
	$n = array(0, 0, 0);
	foreach ($data['buildings'] as $b) {
		if (!isset($bmap[$b['ref']])) {
			$bmap[$b['ref']] = $repo->save('hv_building', array('ref' => $b['ref'], 'label' => $b['label'], 'position' => (int) $b['position'], 'active' => 1));
			$n[0]++;
		}
	}
	$existingUnits = array();
	foreach ($repo->getUnits(false) as $u) {
		$existingUnits[$u->ref] = 1;
	}
	foreach ($data['units'] as $i => $u) {
		if (!isset($existingUnits[$u['ref']])) {
			$repo->save('hv_unit', array('ref' => $u['ref'], 'fk_building' => (int) $bmap[$u['building']], 'area' => (float) ($u['area'] ?? 0), 'share' => (float) ($u['share'] ?? 0), 'reserve_rate' => (float) ($u['reserve_rate'] ?? 0), 'admin_rate' => (float) ($u['admin_rate'] ?? 0), 'position' => $i + 1, 'active' => 1));
			$n[1]++;
		}
	}
	// Geschäftspartner automatisch zuordnen: Name endet auf "Apt 1.1" / "Apartment 1.1"
	// Geschäftspartner automatisch zuordnen: Name endet auf die Einheitennummer, z. B. "… Apt 1.1", "… Top 3", "Wohnung 12"
	// (oder in der Datei je Einheit "thirdparty": exakter Name des Geschäftspartners)
	$socs = array();
	$res = $db->query("SELECT rowid, nom FROM ".$db->prefix()."societe WHERE entity IN (".getEntity('societe').") AND client IN (1,3)");
	while ($res && ($o = $db->fetch_object($res))) {
		$socs[(int) $o->rowid] = $o->nom;
	}
	$byRefName = array();
	foreach ($data['units'] as $du) {
		if (!empty($du['thirdparty'])) {
			$byRefName[$du['ref']] = $du['thirdparty'];
		}
	}
	$linked = 0;
	foreach ($repo->getUnits(false) as $u) {
		if (!empty($u->fk_soc)) {
			continue;
		}
		$hits = array();
		foreach ($socs as $sid => $nom) {
			if (isset($byRefName[$u->ref]) ? (strcasecmp(trim($nom), trim($byRefName[$u->ref])) === 0) : preg_match('/(^|[^0-9a-z])'.preg_quote($u->ref, '/').'\s*$/i', $nom)) {
				$hits[] = $sid;
			}
		}
		if (count($hits) === 1) {
			$db->query("UPDATE ".$db->prefix()."hv_unit SET fk_soc = ".((int) $hits[0])." WHERE rowid = ".((int) $u->rowid));
			$linked++;
		}
	}
	if (!empty($data['reserveplan']) && count($repo->getReservePlan(false)) == 0) {
		$base = function ($v) {
			return is_array($v) ? (string) ($v['de_DE'] ?? reset($v)) : (string) $v;
		};
		foreach ($data['reserveplan'] as $i => $p) {
			$tr = array();
			foreach (array('label', 'basis', 'note') as $f) {
				foreach ((is_array($p[$f] ?? null) ? $p[$f] : array()) as $l => $v) {
					$tr[$l][$f] = $v;
				}
			}
			$p['label'] = $base($p['label']);
			$p['basis'] = $base($p['basis'] ?? '');
			$p['note'] = $base($p['note'] ?? '');
			$p['i18n'] = $tr ? json_encode($tr, JSON_UNESCAPED_UNICODE) : null;
			$repo->save('hv_reserve_plan', array('i18n' => $p['i18n'], 'label' => (string) $p['label'], 'fk_building' => !empty($p['building']) ? (int) ($bmap[$p['building']] ?? 0) : 0, 'alloc_key' => in_array($p['key'] ?? 'area', array('area', 'share', 'units'), true) ? ($p['key'] ?? 'area') : 'area', 'year_planned' => (int) $p['year'], 'amount' => (float) $p['amount'], 'basis' => (string) ($p['basis'] ?? ''), 'note' => (string) ($p['note'] ?? ''), 'position' => $i + 1, 'active' => isset($p['active']) ? (int) $p['active'] : 1));
		}
	}
	if (count($repo->getCostItems(false)) == 0) {
		foreach ($data['costitems'] as $i => $c) {
			$repo->save('hv_costitem', array('label' => $c['label'], 'fk_building' => !empty($c['building']) ? (int) $bmap[$c['building']] : 0, 'alloc_key' => in_array($c['key'] ?? 'area', array('area', 'share', 'units'), true) ? ($c['key'] ?? 'area') : 'area', 'period_months' => (int) $c['period'], 'amount_net' => (float) $c['net'], 'vat_rate' => (float) $c['vat'], 'reference' => $c['reference'] ?? '', 'note' => $c['note'] ?? '', 'position' => $i + 1, 'active' => 1));
			$n[2]++;
		}
	}
	// Leistungen anlegen
	$inv = new HvInvoicer($db);
	$inv->ensureBaseProducts($user);
	// Rücklage-Leistung je Gebäude: vorhandene Leistung verwenden (Standard-Muster L_Rue_<Gebäude>, z. B. L_Rue_H1), sonst HV-RL-<Gebäude> anlegen
	$pattern = getDolGlobalString('HAUSVERWALTUNG_RESERVE_PRODUCT_REF', 'L_Rue_%s');
	foreach ($repo->getBuildings(false) as $b) {
		if (empty($b->fk_product_reserve)) {
			$cand = new Product($db);
			if ($cand->fetch(0, sprintf($pattern, $b->ref)) > 0) {
				$db->query("UPDATE ".$db->prefix()."hv_building SET fk_product_reserve = ".((int) $cand->id)." WHERE rowid = ".((int) $b->rowid));
				$b->fk_product_reserve = $cand->id;
			}
		}
		$inv->ensureReserveProduct($user, $b);
	}
	setEventMessages($langs->trans('HvBuildings').': '.$n[0].', '.$langs->trans('HvUnits').': '.$n[1].', '.$langs->trans('HvCostPlan').': '.$n[2].', '.$langs->trans('HvOwner').': '.$linked, null);
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('HvSetupPage'), hvHelpUrl('einstellungen/'), '', 0, 0, '', '', '', 'mod-hausverwaltung page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('HvSetupPage'), $linkback, 'title_setup');
print dol_get_fiche_head(hvAdminPrepareHead(), 'settings', '', -1, 'building');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvOperatingAccount').'</td><td>';
$form->select_comptes(getDolGlobalInt('HAUSVERWALTUNG_BANK_ACCOUNT'), 'HAUSVERWALTUNG_BANK_ACCOUNT', 0, '', 1);
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvPaymentMode').'</td><td>';
$form->select_types_paiements(getDolGlobalInt('HAUSVERWALTUNG_PAYMENT_MODE'), 'HAUSVERWALTUNG_PAYMENT_MODE', '', 0, 1);
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvDueDay').'</td><td><input name="HAUSVERWALTUNG_DUE_DAY" class="width50" value="'.getDolGlobalInt('HAUSVERWALTUNG_DUE_DAY', 5).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvManager').'</td><td>'.$form->select_company(getDolGlobalInt('HAUSVERWALTUNG_MANAGER_SOCID'), 'HAUSVERWALTUNG_MANAGER_SOCID', '(s.fournisseur:=:1)', 1, 0, 0, array(), 0, 'minwidth300').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvAdminVatRate').'</td><td><input name="HAUSVERWALTUNG_ADMIN_VAT_RATE" class="width50" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_ADMIN_VAT_RATE', '25')).'"> %</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvPlanInflationSetting').'</td><td><input name="HAUSVERWALTUNG_PLAN_INFLATION" class="width50" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_PLAN_INFLATION', '3')).'"> % '.$langs->trans('HvPlanYearsSetting').' <input name="HAUSVERWALTUNG_PLAN_YEARS" class="width50" value="'.getDolGlobalInt('HAUSVERWALTUNG_PLAN_YEARS', 20).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvDigitalNoteSetting').'</td><td><input type="checkbox" name="HAUSVERWALTUNG_PDF_DIGITAL_NOTE" value="1"'.(getDolGlobalString('HAUSVERWALTUNG_PDF_DIGITAL_NOTE', '1') === '1' ? ' checked' : '').'> <span class="opacitymedium">'.$langs->trans('HvPdfDigitalNote').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvMailFromSetting').'</td><td><input name="HAUSVERWALTUNG_MAIL_FROM" class="minwidth250" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_MAIL_FROM')).'" placeholder="'.dol_escape_htmltag($mysoc->email).'"></td></tr>';
dol_include_once('/hausverwaltung/class/hvreserveplan.class.php');
print '<tr class="oddeven"><td>'.$langs->trans('HvRateLegalSetting').'</td><td><input name="HAUSVERWALTUNG_RESERVE_LEGAL_PCT" class="width50 right" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_RESERVE_LEGAL_PCT', '0.54')).'"> % '.$langs->trans('HvRateOfEtalon').' <input name="HAUSVERWALTUNG_ETALON_PRICE" class="width75 right" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_ETALON_PRICE', '796.34')).'"> €/m² → <strong>'.price(HvReserveRate::legalMin(), 0, $langs, 1, 4, 4).' €/m²/'.$langs->trans('Month').'</strong> <span class="opacitymedium small">'.$langs->trans('HvRateLegalSettingHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvSentArchiveSetting').'</td><td><input type="checkbox" name="HAUSVERWALTUNG_SENT_TO_THIRDPARTY" value="1"'.(getDolGlobalString('HAUSVERWALTUNG_SENT_TO_THIRDPARTY', '1') === '1' ? ' checked' : '').'> <span class="opacitymedium">'.$langs->trans('HvSentArchiveHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvInfoAttachSetting').'</td><td><input type="checkbox" name="HAUSVERWALTUNG_INFO_ATTACH" value="1"'.(getDolGlobalString('HAUSVERWALTUNG_INFO_ATTACH', '1') === '1' ? ' checked' : '').'> <span class="opacitymedium">'.$langs->trans('HvInfoAttachHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvHelpUrlSetting').'</td><td><input name="HAUSVERWALTUNG_HELP_URL" class="minwidth300" placeholder="https://…" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_HELP_URL')).'"> <span class="opacitymedium">'.$langs->trans('HvHelpUrlHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvOwnerPortalSetting').'</td><td><input name="HAUSVERWALTUNG_OWNER_PORTAL_URL" class="minwidth300" placeholder="https://…" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_OWNER_PORTAL_URL')).'"> <span class="opacitymedium">'.$langs->trans('HvOwnerPortalHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvMailBccSetting').'</td><td><input name="HAUSVERWALTUNG_MAIL_BCC" class="minwidth250" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_MAIL_BCC')).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('VAT').' (Vorschreibung)</td><td><input name="HAUSVERWALTUNG_VAT_RATE" class="width50" value="'.dol_escape_htmltag(getDolGlobalString('HAUSVERWALTUNG_VAT_RATE', '0')).'"> %</td></tr>';
print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form><br>';

// KI-Übersetzung
print '<a id="ai"></a>'.load_fiche_titre($langs->trans('HvAiTitle'), '', 'language');
print '<div class="opacitymedium">'.$langs->trans('HvAiHelp').'</div><br>';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" autocomplete="off"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="aisave">';
print '<table class="noborder centpercent"><tr class="liste_titre"><td class="titlefield">'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvAiKey').'</td><td><input type="password" name="ai_key" class="minwidth300" autocomplete="new-password" placeholder="'.dol_escape_htmltag(HvTranslator::hasKey() ? $langs->trans('HvAiKeySet') : 'sk-ant-…').'"> ';
print (HvTranslator::hasKey() ? '<span class="badge badge-status4">'.$langs->trans('HvAiKeySet').'</span> <label><input type="checkbox" name="ai_delete" value="1"> '.$langs->trans('Delete').'</label>' : '<span class="opacitymedium">'.$langs->trans('HvAiKeyHowto').'</span>').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('HvAiOn').'</td><td><input type="checkbox" name="ai_on" value="1"'.(getDolGlobalString('HAUSVERWALTUNG_AI_TRANSLATE', '1') === '1' ? ' checked' : '').'> <span class="opacitymedium">'.$langs->trans('HvAiOnHelp', getDolGlobalString('HAUSVERWALTUNG_AI_MODEL', HvTranslator::DEFAULT_MODEL)).'</span></td></tr>';
print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
if (HvTranslator::hasKey()) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="center"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="aitest"><input type="submit" class="button" value="'.$langs->trans('HvAiTest').'"></form>';
}
print '<br>';

print load_fiche_titre($langs->trans('HvImportSeed'), '', '');
print '<p>'.$langs->trans('HvImportSeedHelp').'</p>';
print '<p><a href="'.dol_buildpath('/hausverwaltung/data/vorlage_startdaten.json', 1).'" download>'.$langs->trans('HvImportTemplate').'</a> · <a href="'.dol_buildpath('/hausverwaltung/data/bibali83b_seed.json', 1).'" download>'.$langs->trans('HvImportExample').'</a></p>';
print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="importseed"><input type="file" name="seedfile" accept=".json,application/json"> <input type="submit" class="button" value="'.$langs->trans('HvImportSeed').'"></form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
