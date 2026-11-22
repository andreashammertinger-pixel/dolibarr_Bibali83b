<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/card.php
 * \brief   Küchenkalkulation: Kunde → Nobilia-Unterlagen → Kalkulation/Preis-Check → Hinweise → Angebot + PDF
 */
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
dol_include_once('/kuechenangebot/class/kaservice.class.php');

$langs->loadLangs(array('kuechenangebot@kuechenangebot', 'propal', 'companies'));

if (!$user->hasRight('kuechenangebot', 'read') || !empty($user->socid)) {
	accessforbidden();
}
$canwrite = $user->hasRight('kuechenangebot', 'write');

$id = GETPOSTINT('id');
$propalid = GETPOSTINT('propalid');
$action = GETPOST('action', 'aZ09');

$kalk = new KaKalk($db);
$propal = null;
if ($id > 0) {
	if ($kalk->fetch($id) <= 0) {
		accessforbidden('Kalkulation nicht gefunden');
	}
} elseif ($propalid > 0) {
	$propal = new Propal($db);
	if ($propal->fetch($propalid) <= 0) {
		accessforbidden();
	}
	if ($kalk->fetch(0, $propalid) <= 0) {
		$kalk->fk_propal = $propalid;
		$kalk->fk_soc = $propal->socid;
	}
}
if (!$kalk->id && !$kalk->fk_propal && $action !== 'create' && $action !== 'save') {
	$action = 'create';
}
if (!$kalk->id) {
	$kalk->lang2 = getDolGlobalString('KA_LANG2_DEFAULT', '');
}
$svc = new KaService($db);

// ------------------------------------------------------------------
// Aktionen
// ------------------------------------------------------------------
if ($action === 'save' && $canwrite) {
	$error = 0;
	$isnew = !$kalk->id;
	$artBefore = $kalk->art;
	$oldPlanLabel = KaService::planLabel($kalk);

	// 1. Kunde
	$socid = GETPOSTINT('socid');
	$newname = trim(GETPOST('nc_type', 'aZ09') === 'company' ? GETPOST('nc_company', 'alphanohtml') : GETPOST('nc_firstname', 'alphanohtml').' '.GETPOST('nc_lastname', 'alphanohtml'));
	if ($socid <= 0 && $newname !== '') {
		$dups = $svc->findDuplicates(GETPOST('nc_type', 'aZ09') === 'company' ? $newname : GETPOST('nc_lastname', 'alphanohtml'), GETPOST('nc_email', 'alphanohtml'));
		if ($dups && !GETPOST('nc_force', 'int')) {
			setEventMessages($langs->trans('KaCustomerDuplicates', implode(', ', $dups)), null, 'warnings');
			$error++;
		} else {
			$socid = $svc->createCustomer($user, array(
				'type' => GETPOST('nc_type', 'aZ09'), 'company' => GETPOST('nc_company', 'alphanohtml'),
				'firstname' => GETPOST('nc_firstname', 'alphanohtml'), 'lastname' => GETPOST('nc_lastname', 'alphanohtml'),
				'address' => GETPOST('nc_address', 'alphanohtml'), 'zip' => GETPOST('nc_zip', 'alphanohtml'), 'town' => GETPOST('nc_town', 'alphanohtml'),
				'country_id' => GETPOSTINT('nc_country_id'), 'phone' => GETPOST('nc_phone', 'alphanohtml'), 'email' => GETPOST('nc_email', 'alphanohtml'),
				'lang' => GETPOST('nc_lang', 'aZ09'),
			));
			if ($socid > 0) {
				setEventMessages($langs->trans('KaCustomerCreated', $newname), null);
			} else {
				setEventMessages($langs->trans($svc->error), $svc->errors, 'errors');
				$error++;
				$socid = 0;
			}
		}
	}
	if ($socid > 0) {
		$kalk->fk_soc = $socid;
	}
	$art = GETPOST('art', 'aZ09');
	$kalk->art = in_array($art, array('privat', 'mfh')) ? $art : 'privat';
	$l2 = GETPOST('lang2', 'aZ09');
	$kalk->lang2 = array_key_exists($l2, kaLang2Options()) ? $l2 : '';
	$kalk->label = GETPOST('label', 'alphanohtml');
	$kalk->objekt = GETPOST('objekt', 'restricthtml');

	// 3. Kalkulation
	if (GETPOSTISSET('p_aufschlag_modus')) {
		$p = $kalk->params;
		if ($isnew || $artBefore !== $kalk->art) {
			$def = KaKalk::defaultParams($kalk->art);
			if ($isnew) {
				$p = $def;
			}
		}
		foreach (array('aufschlag_modus', 'transport_modus', 'montage_modus', 'muell_modus', 'muell_basis') as $k) {
			$p[$k] = GETPOST('p_'.$k, 'aZ09');
		}
		foreach (array('aufschlag_wert', 'transport_betrag', 'montage_pauschal', 'montage_arbeiter', 'montage_std', 'montage_satz', 'montage_lfm_satz',
			'montage_std_kalk', 'muell_pauschal', 'muell_std', 'muell_satz', 'muell_faktor', 'tva', 'gueltig_tage') as $k) {
			$p[$k] = kaNum('p_'.$k, (float) ($p[$k] ?? 0));
		}
		$p['plan_label'] = trim(GETPOST('p_plan_label', 'alphanohtml'));
		$p['verbraucher'] = GETPOST('p_verbraucher', 'int') ? 1 : 0;
		if ($isnew && $newname !== '') {
			$p['verbraucher'] = (GETPOST('nc_type', 'aZ09') === 'company') ? 0 : 1;
		}
		$kalk->params = $p;
	} elseif ($isnew) {
		$kalk->params = KaKalk::defaultParams($kalk->art);
	}

	// 4. Hinweise
	if (GETPOSTISSET('notes_posted')) {
		$bs = GETPOST('bausteine', 'array');
		$kalk->hinweise['bausteine'] = array_values(array_filter(array_map('strval', is_array($bs) ? $bs : array())));
		$nob = GETPOST('nobilia', 'array');
		$map = array();
		foreach ($kalk->typen as $t) {
			foreach (array_merge((array) ($t->data['hinweise'] ?? array()), (array) ($t->data['plaene']['hinweise'] ?? array())) as $h) {
				$map[md5($h)] = $h;
			}
		}
		$kalk->hinweise['nobilia'] = array();
		foreach ((is_array($nob) ? $nob : array()) as $h) {
			if (isset($map[$h])) {
				$kalk->hinweise['nobilia'][] = $map[$h];
			}
		}
		$kalk->hinweise['init'] = 1;
		$kalk->note_de = GETPOST('note_de', 'restricthtml');
		$kalk->note_lang2 = GETPOST('note_lang2', 'restricthtml');
	}
	if (empty($kalk->hinweise['init'])) {
		foreach (kaFetchBausteine($db) as $code => $b) {
			if ($b->vorauswahl || ($code === 'MFH' && $kalk->art === 'mfh')) {
				$kalk->hinweise['bausteine'][] = $code;
			}
		}
		$kalk->hinweise['init'] = 1;
	}

	if (!$error || $kalk->id) {
		if ($kalk->save($user) < 0) {
			setEventMessages($kalk->error, null, 'errors');
			$error++;
		}
	}

	if ($kalk->id) {
		// 2. Küchentypen bearbeiten
		$posted = GETPOST('typ', 'array');
		foreach ($kalk->typen as $t) {
			if (!isset($posted[$t->id]) || !is_array($posted[$t->id])) {
				continue;
			}
			$d = $posted[$t->id];
			$t->label = dol_string_nohtmltag((string) ($d['label'] ?? $t->label));
			$t->qty = max(0, (float) str_replace(',', '.', (string) ($d['qty'] ?? $t->qty)));
			$newmode = (($d['preis_modus'] ?? 'eur') === 'punkte') ? 'punkte' : 'eur';
			$fak = (float) str_replace(',', '.', (string) ($d['faktor'] ?? $t->faktor));
			if ($newmode !== $t->preis_modus && abs($fak - (float) getDolGlobalString($t->preis_modus === 'punkte' ? 'KA_FAKTOR_PUNKTE' : 'KA_FAKTOR_EUR', '1')) < 0.00001) {
				$fak = (float) getDolGlobalString($newmode === 'punkte' ? 'KA_FAKTOR_PUNKTE' : 'KA_FAKTOR_EUR', '1');
			}
			$t->preis_modus = $newmode;
			$t->faktor = $fak;
			$t->katalog = (float) str_replace(array('.', ','), array('', '.'), (string) ($d['katalog'] ?? $t->katalog));
			$t->lfm = (float) str_replace(',', '.', (string) ($d['lfm'] ?? $t->lfm));
			$vo = trim((string) ($d['vk_override'] ?? ''));
			$t->vk_override = ($vo === '') ? null : (float) str_replace(array('.', ','), array('', '.'), $vo);
			$t->save();

			// Dateien ersetzen / eigenes Bild
			$an = !empty($_FILES['replace_an_'.$t->id]['tmp_name']) ? $_FILES['replace_an_'.$t->id] : null;
			$bd = !empty($_FILES['replace_bd_'.$t->id]['tmp_name']) ? $_FILES['replace_bd_'.$t->id] : null;
			if ($an || $bd) {
				if (!$svc->importNobilia($kalk, $t, $an ? $an['tmp_name'] : null, $bd ? $bd['tmp_name'] : null, $an ? $an['name'] : '', $bd ? $bd['name'] : '')) {
					setEventMessages($langs->trans('KaParseError', $svc->error), null, 'errors');
				}
			}
			if (!empty($_FILES['img_'.$t->id]['tmp_name'])) {
				if ($svc->setImage($kalk, $t, $_FILES['img_'.$t->id]['tmp_name']) < 0) {
					setEventMessages($svc->error, null, 'errors');
				}
			}
		}
		// neuer Küchentyp
		if (!empty($_FILES['file_an']['tmp_name'])) {
			$bd = !empty($_FILES['file_bd']['tmp_name']) ? $_FILES['file_bd'] : null;
			$t = $svc->importNobilia($kalk, null, $_FILES['file_an']['tmp_name'], $bd ? $bd['tmp_name'] : null, $_FILES['file_an']['name'], $bd ? $bd['name'] : '');
			if ($t) {
				$t->qty = max(1, (float) str_replace(',', '.', GETPOST('new_qty', 'alphanohtml') ?: '1'));
				$t->faktor = (float) getDolGlobalString('KA_FAKTOR_EUR', '1');
				if (GETPOST('new_label', 'alphanohtml')) {
					$t->label = GETPOST('new_label', 'alphanohtml');
				}
				$t->save();
				setEventMessages($langs->trans('KaParsed', count($t->data['merkmale']), count($t->data['positionen']), count($t->data['geraete']), kaMoney($t->katalog)), null);
				if (!$t->image) {
					setEventMessages($langs->trans('KaNoImage'), null, 'warnings');
				}
			} else {
				setEventMessages($langs->trans('KaParseError', $svc->error), null, 'errors');
			}
		} elseif (!empty($_FILES['file_bd']['tmp_name'])) {
			setEventMessages($langs->trans('KaFileAn').': '.$langs->trans('ErrorFieldRequired', $langs->transnoentities('KaFileAn')), null, 'warnings');
		}
		$kalk->fetch($kalk->id);
		$kalk->save($user); // Summen aktualisieren
		if (KaService::planLabel($kalk) !== $oldPlanLabel) {
			$svc->convertAllPlans($kalk); // Bezeichnung im Plankopf geändert
		}

		if (GETPOST('generate', 'alpha') && !$error) {
			$pid = $svc->generateProposal($user, $kalk);
			if ($pid > 0) {
				setEventMessages(null, $svc->messages);
			} else {
				setEventMessages($svc->error, $svc->errors, 'errors');
			}
		} elseif (!$error) {
			setEventMessages($langs->trans('KaSaved'), null);
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$kalk->id.(GETPOST('anchor', 'aZ09') ? '#'.GETPOST('anchor', 'aZ09') : ''));
		exit;
	}
	$action = 'create';
}

if ($action === 'deltyp' && $canwrite && $kalk->id) {
	foreach ($kalk->typen as $t) {
		if ($t->id == GETPOSTINT('typid')) {
			foreach (array($t->file_an, $t->file_bd, $t->image, KaService::planFile($t)) as $f) {
				if ($f) {
					@unlink($kalk->getDir().'/'.$f);
				}
			}
			$t->delete();
		}
	}
	$kalk->fetch($kalk->id);
	$kalk->save($user);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$kalk->id.'#kitchens');
	exit;
}

if ($action === 'confirm_delete' && $canwrite && $kalk->id && GETPOST('confirm', 'alpha') === 'yes') {
	$kalk->delete();
	header('Location: '.dol_buildpath('/kuechenangebot/list.php', 1));
	exit;
}

// ------------------------------------------------------------------
// Anzeige
// ------------------------------------------------------------------
$form = new Form($db);
$title = $langs->trans('KaTabKalk');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-kuechenangebot page-card');

if ($kalk->fk_propal) {
	if (!$propal) {
		$propal = new Propal($db);
		$propal->fetch($kalk->fk_propal);
	}
	if ($propal->id) {
		$propal->fetch_thirdparty();
		$head = propal_prepare_head($propal);
		print dol_get_fiche_head($head, 'kuechenkalk', $langs->trans('Proposal'), -1, 'propal');
		$linkback = '<a href="'.DOL_URL_ROOT.'/comm/propal/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
		dol_banner_tab($propal, 'ref', $linkback, 1, 'ref', 'ref', '');
		print dol_get_fiche_end();
	}
} else {
	print load_fiche_titre($title.($kalk->label ? ' – '.dol_escape_htmltag($kalk->label) : ''), '', 'propal');
}

if ($action === 'delete') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$kalk->id, $langs->trans('KaDeleteKalk'), $langs->trans('KaConfirmDelete'), 'confirm_delete', '', 0, 1);
}

$calc = $kalk->compute();
$disabled = $canwrite ? '' : ' disabled';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" enctype="multipart/form-data" id="kaform">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="anchor" id="ka_anchor" value="">';
if ($kalk->id) {
	print '<input type="hidden" name="id" value="'.$kalk->id.'">';
} elseif ($kalk->fk_propal) {
	print '<input type="hidden" name="propalid" value="'.$kalk->fk_propal.'">';
}

// --- 1. Kunde und Projekt
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('KaStepCustomer').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate fieldrequired">'.$langs->trans('KaCustomerExisting').'</td><td>';
print $form->select_company($kalk->fk_soc ?: '', 'socid', '((s.client:IN:1,2,3))', 1, 0, 0, array(), 0, 'minwidth300');
print ' <span class="opacitymedium small">'.$langs->trans('KaCustomerSearchHint').'</span>';
if ($kalk->fk_soc) {
	$soc = new Societe($db);
	if ($soc->fetch($kalk->fk_soc) > 0) {
		print ' &nbsp; '.$soc->getNomUrl(1);
	}
}
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaCustomerNew').'</td><td>';
print '<details'.($kalk->fk_soc ? '' : ' open').'><summary class="cursorpointer">'.$langs->trans('KaCustomerNew').'</summary>';
print '<table class="nobordernopadding"><tr><td colspan="4">';
print '<label><input type="radio" name="nc_type" value="private" checked> '.$langs->trans('KaCustomerTypePrivate').'</label> &nbsp; ';
print '<label><input type="radio" name="nc_type" value="company"> '.$langs->trans('KaCustomerTypeCompany').'</label></td></tr>';
print '<tr><td>'.$langs->trans('KaFirstname').'</td><td><input name="nc_firstname" class="minwidth150"></td><td>'.$langs->trans('KaLastname').'</td><td><input name="nc_lastname" class="minwidth150"></td></tr>';
print '<tr><td>'.$langs->trans('KaCompanyName').'</td><td colspan="3"><input name="nc_company" class="minwidth300"></td></tr>';
print '<tr><td>'.$langs->trans('KaAddress').'</td><td colspan="3"><input name="nc_address" class="minwidth300"></td></tr>';
print '<tr><td>'.$langs->trans('KaZip').' / '.$langs->trans('KaTown').'</td><td colspan="3"><input name="nc_zip" class="width75"> <input name="nc_town" class="minwidth200"></td></tr>';
print '<tr><td>'.$langs->trans('KaCountry').'</td><td colspan="3">'.$form->select_country(getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY') ? (int) getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY') : '', 'nc_country_id', '', 0, 'minwidth200').'</td></tr>';
print '<tr><td>'.$langs->trans('KaPhone').'</td><td><input name="nc_phone" class="minwidth150"></td><td>'.$langs->trans('KaEmail').'</td><td><input name="nc_email" type="email" class="minwidth200"></td></tr>';
print '<tr><td>'.$langs->trans('KaCustomerLang').'</td><td colspan="3"><select name="nc_lang"><option value="de_DE">Deutsch</option><option value="hr_HR">Hrvatski</option><option value="en_US">English</option><option value="sl_SI">Slovenščina</option></select></td></tr>';
print '<tr><td></td><td colspan="3"><label><input type="checkbox" name="nc_force" value="1"> '.$langs->trans('KaCustomerForce').'</label></td></tr>';
print '</table></details></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('KaArt').'</td><td>';
foreach (array('privat' => 'KaArtPrivat', 'mfh' => 'KaArtMfh') as $k => $lab) {
	print '<label class="marginrightonly"><input type="radio" name="art" value="'.$k.'"'.($kalk->art === $k ? ' checked' : '').$disabled.'> '.$langs->trans($lab).'</label> ';
}
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaVerbraucher').'</td><td><label><input type="checkbox" name="p_verbraucher" value="1"'.(!empty($kalk->params['verbraucher']) ? ' checked' : '').$disabled.'> '.$langs->trans('KaVerbraucher').'</label> <span class="opacitymedium small">'.$langs->trans('KaVerbraucherHint').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaLang2').'</td><td><select name="lang2"'.$disabled.'><option value="">'.$langs->trans('KaLang2None').'</option>';
foreach (kaLang2Options() as $k => $lab) {
	print '<option value="'.$k.'"'.($kalk->lang2 === $k ? ' selected' : '').'>'.dol_escape_htmltag($lab).'</option>';
}
print '</select> <span class="opacitymedium small">'.$langs->trans('KaLang2Hint').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaLabel').'</td><td><input name="label" class="minwidth500" value="'.dol_escape_htmltag($kalk->label).'"'.$disabled.'></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaObjekt').'</td><td><textarea name="objekt" rows="2" class="minwidth500"'.$disabled.'>'.dol_escape_htmltag($kalk->objekt).'</textarea></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaPlanLabel').'</td><td><input name="p_plan_label" class="minwidth500" value="'.dol_escape_htmltag($kalk->params['plan_label'] ?? '').'" placeholder="'.dol_escape_htmltag(KaService::planLabel($kalk)).'"'.$disabled.'> <span class="opacitymedium small">'.$langs->trans('KaPlanLabelHint').'</span></td></tr>';
print '</table></div><br>';

// --- 2. Küchentypen
print '<a id="kitchens"></a>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="9">'.$langs->trans('KaStepKitchens').'</td></tr>';
if ($kalk->typen) {
	print '<tr class="liste_titre_filter"><td>'.$langs->trans('KaImage').'</td><td>'.$langs->trans('KaKitchenLabel').'</td><td class="right">'.$langs->trans('KaQty').'</td>';
	print '<td>'.$langs->trans('KaPriceBase').'</td><td class="right">'.$langs->trans('KaCatalogValue').'</td><td class="right">'.$form->textwithpicto($langs->trans('KaFactor'), $langs->trans('KaFactorHint')).'</td>';
	print '<td class="right">'.$langs->trans('KaEkPiece').'</td><td class="right">'.$form->textwithpicto($langs->trans('KaLfm'), $langs->trans('KaLfmHint')).'</td>';
	print '<td class="right">'.$form->textwithpicto($langs->trans('KaVkOverride'), $langs->trans('KaVkOverrideHint')).'</td></tr>';
}
foreach ($kalk->typen as $t) {
	$n = 'typ['.$t->id.']';
	print '<tr class="oddeven">';
	print '<td rowspan="2" class="center" style="width:170px">';
	if ($t->image) {
		$src = dol_buildpath('/kuechenangebot/image.php', 1).'?id='.$kalk->id.'&typ='.$t->id.'&t='.dol_now();
		print '<a href="'.$src.'" target="_blank"><img src="'.$src.'" style="max-width:160px;max-height:100px;border:1px solid #ddd"></a>';
	} else {
		print '<span class="warning">'.$langs->trans('KaNoImage').'</span>';
	}
	print '</td>';
	print '<td><input name="'.$n.'[label]" value="'.dol_escape_htmltag($t->label).'" class="minwidth150"'.$disabled.'><br><span class="opacitymedium small">'.dol_escape_htmltag(($t->data['meta']['planungsinformation'] ?? '').' · '.($t->data['meta']['angebot'] ?? '')).'</span></td>';
	print '<td class="right"><input name="'.$n.'[qty]" value="'.kaFmt($t->qty, 0).'" class="width50 right"'.$disabled.'></td>';
	print '<td><select name="'.$n.'[preis_modus]"'.$disabled.'><option value="eur"'.($t->preis_modus === 'eur' ? ' selected' : '').'>'.$langs->trans('KaPriceEur').'</option><option value="punkte"'.($t->preis_modus === 'punkte' ? ' selected' : '').'>'.$langs->trans('KaPricePoints').'</option></select></td>';
	print '<td class="right"><input name="'.$n.'[katalog]" value="'.kaFmt($t->katalog).'" class="width75 right"'.$disabled.'></td>';
	print '<td class="right"><input name="'.$n.'[faktor]" value="'.kaFmt($t->faktor, 4).'" class="width50 right"'.$disabled.'></td>';
	print '<td class="right nowraponall"><b>'.kaMoney($t->getEk()).'</b></td>';
	print '<td class="right"><input name="'.$n.'[lfm]" value="'.kaFmt($t->lfm).'" class="width50 right"'.$disabled.'></td>';
	print '<td class="right"><input name="'.$n.'[vk_override]" value="'.($t->vk_override ? kaFmt($t->vk_override) : '').'" class="width75 right" placeholder="'.kaFmt($calc['typen'][array_search($t, $kalk->typen, true)]['vk_calc'] ?? 0).'"'.$disabled.'></td>';
	print '</tr>';
	print '<tr class="oddeven"><td colspan="8" class="small">';
	$devs = array();
	foreach ((array) ($t->data['geraete'] ?? array()) as $d) {
		$devs[] = dol_escape_htmltag($d['name']).($d['eek'] ? ' <span class="badge badge-secondary">'.dol_escape_htmltag($d['eek']).'</span>' : '');
	}
	print '<b>'.$langs->trans('KaDevices').':</b> '.($devs ? implode(' · ', $devs) : '-').'<br>';
	if (!empty($t->data['summen'])) {
		$s = array();
		foreach ($t->data['summen'] as $k => $v) {
			$s[] = dol_escape_htmltag($k).' '.kaMoney($v, false);
		}
		print '<span class="opacitymedium">Nobilia: '.implode(' · ', $s).'</span><br>';
	}
	if ($t->file_bd) {
		$st = $t->data['plan_status'] ?? '';
		$pf = $kalk->getDir().'/'.KaService::planFile($t);
		print '<b>'.$langs->trans('KaPlans').':</b> ';
		if (is_readable($pf)) {
			print '<a href="'.dol_buildpath('/kuechenangebot/plan.php', 1).'?id='.$kalk->id.'&typ='.$t->id.'" target="_blank">'.img_mime('x.pdf').' '.$langs->trans('KaPlansMigora').'</a> ';
		}
		if ($st === 'warn') {
			print '<span class="warning">'.$langs->trans('KaPlansWarn', (int) ($t->data['plan_missing'] ?? 0)).'</span> ';
		} elseif ($st === 'error') {
			print '<span class="error">'.$langs->trans('KaPlansError').'</span> ';
		}
		$released = !empty($propal->id) && KaService::plansReleased($propal);
		print '<span class="opacitymedium">'.$langs->trans($released ? 'KaPlansReleased' : 'KaPlansInternal').'</span><br>';
	}
	print '<details><summary class="cursorpointer">'.$langs->trans('KaShowPositions').'</summary><table class="noborder small">';
	foreach ((array) ($t->data['positionen'] ?? array()) as $p) {
		print '<tr><td>'.dol_escape_htmltag($p['pos']).'</td><td>'.dol_escape_htmltag($p['artikel']).'</td><td>'.dol_escape_htmltag(dol_trunc($p['text'], 110)).'</td><td class="right nowraponall">'.($p['preis'] !== null ? kaMoney($p['preis'], false) : '').'</td></tr>';
	}
	print '</table></details>';
	if ($canwrite) {
		print '<div class="paddingtop">'.$langs->trans('KaReplaceFiles').': AN <input type="file" name="replace_an_'.$t->id.'" accept=".pdf,application/pdf"> BD <input type="file" name="replace_bd_'.$t->id.'" accept=".pdf,application/pdf"> ';
		print $langs->trans('KaImageReplace').' <input type="file" name="img_'.$t->id.'" accept="image/jpeg,image/png"> ';
		print '<a class="reposition marginleftonly" href="'.$_SERVER['PHP_SELF'].'?id='.$kalk->id.'&action=deltyp&typid='.$t->id.'&token='.newToken().'">'.img_delete().' '.$langs->trans('KaDeleteKitchen').'</a></div>';
	}
	print '</td></tr>';
}
if (!$kalk->typen) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('KaNoKitchens').'</span></td></tr>';
}
if ($canwrite) {
	print '<tr class="oddeven"><td colspan="9"><b>'.$langs->trans('KaAddKitchen').'</b><br>';
	print $langs->trans('KaFileAn').' <input type="file" name="file_an" accept=".pdf,application/pdf"> &nbsp; ';
	print $langs->trans('KaFileBd').' <input type="file" name="file_bd" accept=".pdf,application/pdf"> &nbsp; ';
	print $langs->trans('KaKitchenLabel').' <input name="new_label" class="width150" placeholder="Typ …"> ';
	print $langs->trans('KaQty').' <input name="new_qty" value="1" class="width50 right"> ';
	print '<input type="submit" class="button smallpaddingimp" value="'.$langs->trans('KaUpload').'" onclick="document.getElementById(\'ka_anchor\').value=\'kitchens\'">';
	print '</td></tr>';
}
print '</table></div><br>';

// --- 3. Kalkulation
$p = $kalk->params;
$sel = function ($name, $opts, $cur) use ($langs, $disabled) {
	$h = '<select name="p_'.$name.'" class="ka-mode" data-group="'.$name.'"'.$disabled.'>';
	foreach ($opts as $k => $lab) {
		$h .= '<option value="'.$k.'"'.((string) $cur === (string) $k ? ' selected' : '').'>'.$langs->trans($lab).'</option>';
	}
	return $h.'</select>';
};
$inp = function ($name, $val, $dec = 2, $cls = 'width75') use ($p, $disabled) {
	return '<input name="p_'.$name.'" value="'.kaFmt($val, $dec).'" class="'.$cls.' right"'.$disabled.'>';
};
print '<a id="calc"></a>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('KaStepCalc').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('KaMarkup').'</td><td>'.$sel('aufschlag_modus', array('pct' => 'KaMarkupPct', 'pauschal' => 'KaMarkupFlat'), $p['aufschlag_modus']).' '.$inp('aufschlag_wert', $p['aufschlag_wert']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaTransport').'</td><td>'.$sel('transport_modus', array('none' => 'KaTransportNone', 'projekt' => 'KaTransportProject', 'kueche' => 'KaTransportKitchen'), $p['transport_modus']).' € '.$inp('transport_betrag', $p['transport_betrag']).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaAssembly').'</td><td>'.$sel('montage_modus', array('stunden' => 'KaAssemblyHours', 'pauschal' => 'KaAssemblyFlat', 'lfm' => 'KaAssemblyLfm'), $p['montage_modus']);
print '<div class="ka-sub" data-group="montage_modus" data-show="stunden">'.$langs->trans('KaWorkers').' '.$inp('montage_arbeiter', $p['montage_arbeiter'], 0, 'width50').' × '.$langs->trans('KaHoursPerWorker').' '.$inp('montage_std', $p['montage_std'], 1, 'width50').' <span class="opacitymedium small">('.$langs->trans('KaHoursAuto').')</span> × '.$langs->trans('KaRate').' '.$inp('montage_satz', $p['montage_satz']).'</div>';
print '<div class="ka-sub" data-group="montage_modus" data-show="pauschal">€ '.$inp('montage_pauschal', $p['montage_pauschal']).' '.$langs->trans('KaPerKitchen').'</div>';
print '<div class="ka-sub" data-group="montage_modus" data-show="lfm">'.$langs->trans('KaRateLfm').' '.$inp('montage_lfm_satz', $p['montage_lfm_satz']).'</div>';
print '<div class="ka-sub" data-group="montage_modus" data-show="pauschal lfm">'.$langs->trans('KaHoursEst').' '.$inp('montage_std_kalk', $p['montage_std_kalk'], 1, 'width50').' <span class="opacitymedium small">('.$langs->trans('KaHoursAuto').')</span></div>';
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaWaste').'</td><td>'.$sel('muell_modus', array('none' => 'KaWasteNone', 'pauschal' => 'KaWasteFlat', 'stunden' => 'KaWasteHours'), $p['muell_modus']).' ';
print $sel('muell_basis', array('projekt' => 'KaWasteBasisProject', 'kueche' => 'KaWasteBasisKitchen'), $p['muell_basis']);
print '<div class="ka-sub" data-group="muell_modus" data-show="pauschal">€ '.$inp('muell_pauschal', $p['muell_pauschal']).'</div>';
print '<div class="ka-sub" data-group="muell_modus" data-show="stunden">'.$langs->trans('Hours').' '.$inp('muell_std', $p['muell_std'], 1, 'width50').' × '.$langs->trans('KaRate').' '.$inp('muell_satz', $p['muell_satz']).' × '.$langs->trans('KaCostFactor').' '.$inp('muell_faktor', $p['muell_faktor'], 2, 'width50').'</div>';
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('KaVatRate').' / '.$langs->trans('KaValidDays').'</td><td>'.$inp('tva', $p['tva'], 1, 'width50').' % &nbsp; '.$inp('gueltig_tage', $p['gueltig_tage'], 0, 'width50').' '.$langs->trans('Days').'</td></tr>';
print '</table></div>';

// Ergebnis
if ($kalk->typen) {
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('KaResult').'</td><td class="right">'.$langs->trans('KaQty').'</td><td class="right">'.$langs->trans('KaLfm').'</td><td class="right">'.$langs->trans('KaEkPiece').'</td>';
	print '<td class="right">'.$langs->trans('KaFurniture').'</td><td class="right">'.$langs->trans('KaAssembly').'</td><td class="right">'.$langs->trans('KaHours').'</td><td class="right">'.$langs->trans('KaTransport').'</td><td class="right">'.$langs->trans('KaWaste').'</td>';
	print '<td class="right">'.$langs->trans('KaPerKitchen').' ('.$langs->trans('KaNet').')</td><td class="right">'.$langs->trans('KaTotal').'</td></tr>';
	foreach ($calc['typen'] as $r) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($r['label']).'</td><td class="right">'.kaFmt($r['qty'], 0).'</td><td class="right">'.kaFmt($r['lfm']).'</td><td class="right">'.kaMoney($r['ek']).'</td>';
		print '<td class="right">'.kaMoney($r['moebel']).'</td><td class="right">'.kaMoney($r['montage']).'</td><td class="right">'.kaFmt($r['montage_std'], 1).'</td><td class="right">'.kaMoney($r['transport']).'</td><td class="right">'.kaMoney($r['muell']).'</td>';
		print '<td class="right"><b>'.kaMoney($r['vk']).'</b>'.(abs($r['vk'] - $r['vk_calc']) > 0.01 ? '<br><span class="opacitymedium small">'.kaMoney($r['vk_calc']).'</span>' : '').'</td><td class="right">'.kaMoney($r['vk'] * $r['qty']).'</td></tr>';
	}
	print '<tr class="liste_total"><td>'.$langs->trans('KaTotal').'</td><td class="right">'.kaFmt($calc['stueck'], 0).'</td><td class="right">'.kaFmt($calc['lfm']).'</td><td class="right">'.kaMoney($calc['ek']).'</td>';
	print '<td class="right">'.kaMoney($calc['moebel_vk']).'</td><td class="right">'.kaMoney($calc['montage_vk']).'</td><td class="right">'.kaFmt($calc['montage_std'], 1).'</td><td class="right">'.kaMoney($calc['transport_vk']).'</td><td class="right">'.kaMoney($calc['muell_vk']).'</td>';
	print '<td></td><td class="right"><b>'.kaMoney($calc['vk_netto']).'</b><br>'.$langs->trans('KaGross').' '.kaMoney($calc['vk_brutto']).'</td></tr>';
	print '</table></div>';

	// Preis-Check
	$b = $kalk->benchmark();
	$artlabel = $langs->trans($kalk->art === 'mfh' ? 'KaArtMfh' : 'KaArtPrivat');
	print '<a id="check"></a><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('KaStepCheck').' – '.dol_escape_htmltag($artlabel).'</td></tr>';
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('KaCostsDirect').'</td><td class="right">'.kaMoney($calc['selbstkosten_direkt']).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('KaOverhead', kaFmt($calc['gk_pct'], 1)).'</td><td class="right">'.kaMoney($calc['selbstkosten'] - $calc['selbstkosten_direkt']).'</td></tr>';
	print '<tr class="oddeven"><td><b>'.$langs->trans('KaBreakEven').'</b></td><td class="right"><b>'.kaMoney($calc['selbstkosten']).'</b></td></tr>';
	if ($b['n'] > 0) {
		if ($b['faktor'] > 0) {
			print '<tr class="oddeven"><td>'.$langs->trans('KaAvgFactor', kaFmt($b['faktor'], 3)).'</td><td class="right">'.kaMoney($calc['ek'] * $b['faktor']).'</td></tr>';
		}
		if ($b['vk_lfm'] > 0 && $calc['lfm'] > 0) {
			print '<tr class="oddeven"><td>'.$langs->trans('KaAvgLfm', kaMoney($b['vk_lfm'])).'</td><td class="right">'.kaMoney($calc['lfm'] * $b['vk_lfm']).'</td></tr>';
		}
		if ($b['vk_stueck'] > 0) {
			print '<tr class="oddeven"><td>'.$langs->trans('KaAvgPiece').'</td><td class="right">'.kaMoney($b['vk_stueck']).' × '.kaFmt($calc['stueck'], 0).' = '.kaMoney($b['vk_stueck'] * $calc['stueck']).'</td></tr>';
		}
	}
	print '<tr class="oddeven"><td><b>'.$langs->trans('KaYourPrice').'</b></td><td class="right"><b>'.kaMoney($calc['vk_netto']).'</b> ('.$langs->trans('KaFactorShort').' '.kaFmt($calc['faktor'], 3).')</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('KaMargin').'</td><td class="right">'.kaMoney($calc['db']).' ('.kaFmt($calc['db_pct'], 1).' %)</td></tr>';
	print '<tr><td colspan="2">';
	if ($calc['vk_netto'] < $calc['selbstkosten']) {
		print '<div class="error">'.$langs->trans('KaBelowCost').'</div>';
	}
	if ($b['n'] > 0) {
		$ref = $calc['ek'] * $b['faktor'];
		if ($ref > 0) {
			$dev = ($calc['vk_netto'] - $ref) / $ref * 100;
			$msg = $dev < -10 ? 'KaBelowAvg' : ($dev > 10 ? 'KaAboveAvg' : 'KaInRange');
			print '<div class="'.($msg === 'KaInRange' ? 'ok' : 'warning').'">'.$langs->trans($msg).' ('.($dev >= 0 ? '+' : '').kaFmt($dev, 1).' %)</div>';
		}
		print '<span class="opacitymedium small">'.$langs->trans('KaBasedOn', $b['n'], $artlabel, kaFmt($b['stueck'], 0)).'</span>';
		if ($b['n'] < 3) {
			print '<br><span class="opacitymedium small">'.$langs->trans('KaFewData').'</span>';
		}
	} else {
		print '<span class="opacitymedium">'.$langs->trans('KaNoData').'</span>';
	}
	print '</td></tr></table></div>';
}
print '<br>';

// --- 4. Hinweise
print '<a id="notes"></a><input type="hidden" name="notes_posted" value="1">';
$preset = empty($kalk->hinweise['init']);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('KaStepNotes').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate tdtop">'.$langs->trans('KaNotesBausteine').'<br><a class="small" href="'.dol_buildpath('/kuechenangebot/bausteine.php', 1).'">'.$langs->trans('KaBausteinEdit').'</a></td><td>';
$l2 = $kalk->lang2 ? KaDict::short($kalk->lang2) : '';
foreach (kaFetchBausteine($db) as $code => $bs) {
	$checked = $preset ? ($bs->vorauswahl || ($code === 'MFH' && $kalk->art === 'mfh')) : in_array($code, (array) $kalk->hinweise['bausteine']);
	print '<div class="paddingbottom"><label><input type="checkbox" name="bausteine[]" value="'.dol_escape_htmltag($code).'"'.($checked ? ' checked' : '').$disabled.'> <b>'.dol_escape_htmltag($bs->label).'</b>: '.dol_escape_htmltag($bs->text_de).'</label>';
	if ($l2 && $l2 !== 'de' && empty($bs->{'text_'.$l2})) {
		print ' <span class="warning small">'.$langs->trans('KaTranslationMissing').'</span>';
	}
	print '</div>';
}
print '</td></tr>';
$nobHints = array();
foreach ($kalk->typen as $t) {
	foreach (array_merge((array) ($t->data['hinweise'] ?? array()), (array) ($t->data['plaene']['hinweise'] ?? array())) as $h) {
		if (!kaIsInternalHint($h)) {
			$nobHints[md5($h)] = $h;
		}
	}
}
if ($nobHints) {
	print '<tr class="oddeven"><td class="tdtop">'.$langs->trans('KaNotesNobilia').'</td><td>';
	foreach ($nobHints as $hash => $h) {
		$checked = $preset || in_array($h, (array) $kalk->hinweise['nobilia']);
		print '<div class="paddingbottom"><label><input type="checkbox" name="nobilia[]" value="'.$hash.'"'.($checked ? ' checked' : '').$disabled.'> '.dol_escape_htmltag($h).'</label></div>';
	}
	print '</td></tr>';
}
print '<tr class="oddeven"><td class="tdtop">'.$langs->trans('KaNoteFree').'</td><td><textarea name="note_de" rows="3" class="centpercent"'.$disabled.'>'.dol_escape_htmltag($kalk->note_de).'</textarea></td></tr>';
if ($kalk->lang2) {
	print '<tr class="oddeven"><td class="tdtop">'.$langs->trans('KaNoteFreeLang2').' ('.dol_escape_htmltag($kalk->lang2).')</td><td><textarea name="note_lang2" rows="3" class="centpercent"'.$disabled.'>'.dol_escape_htmltag($kalk->note_lang2).'</textarea></td></tr>';
}
print '</table></div><br>';

// --- 5. Erstellen
print '<div class="center">';
if ($canwrite) {
	print '<input type="submit" class="button button-save" value="'.$langs->trans('KaRecalc').'"> ';
	if ($kalk->id && $kalk->typen) {
		print '<input type="submit" class="button" name="generate" value="'.$langs->trans($kalk->fk_propal ? 'KaUpdateProposal' : 'KaCreateProposal').'"> ';
	}
}
print '</div></form>';

if ($kalk->fk_propal && !empty($propal->id)) {
	$dir = $conf->propal->multidir_output[$propal->entity].'/'.dol_sanitizeFileName($propal->ref);
	print '<br>'.load_fiche_titre($langs->trans('KaDocuments'), '', '');
	print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('File').'</td><td class="right">'.$langs->trans('Size').'</td><td class="right">'.$langs->trans('Date').'</td></tr>';
	foreach (dol_dir_list($dir, 'files', 0, '\.pdf$', '', 'name', SORT_ASC, 1) as $f) {
		$url = DOL_URL_ROOT.'/document.php?modulepart=propal&entity='.$propal->entity.'&file='.urlencode(dol_sanitizeFileName($propal->ref).'/'.$f['name']);
		print '<tr class="oddeven"><td><a href="'.$url.'" target="_blank">'.img_mime($f['name']).' '.dol_escape_htmltag($f['name']).'</a></td><td class="right">'.dol_print_size($f['size']).'</td><td class="right">'.dol_print_date($f['date'], 'dayhour').'</td></tr>';
	}
	print '</table>';
	print '<div class="tabsAction"><a class="butAction" href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.$propal->id.'">'.$langs->trans('KaOpenProposal').' '.dol_escape_htmltag($propal->ref).'</a></div>';
}
if ($kalk->id && $canwrite) {
	print '<div class="tabsAction"><a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$kalk->id.'&action=delete&token='.newToken().'">'.$langs->trans('KaDeleteKalk').'</a></div>';
}

// Felder je nach Modus ein-/ausblenden
print '<script>
jQuery(function($){
	function ka(){ $(".ka-sub").each(function(){ var g=$(this).data("group"); var v=$("select[name=p_"+g+"]").val(); $(this).toggle((" "+$(this).data("show")+" ").indexOf(" "+v+" ")>=0); }); }
	$(".ka-mode").on("change", ka); ka();
});
</script>';

llxFooter();
$db->close();
