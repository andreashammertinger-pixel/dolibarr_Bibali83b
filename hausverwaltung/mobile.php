<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Mobile Oberfläche: Eingangsrechnung fotografieren und zuordnen, offene Posten, Rücklagen */
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

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'bills', 'companies'));
if (!$user->hasRight('hausverwaltung', 'capture')) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$view = GETPOST('view', 'aZ09') ?: 'home';
$repo = new HvRepository($db);
$buildings = $repo->getBuildings();
$costitems = $repo->getCostItems();
$msg = '';
$err = '';

if ($action === 'capture' && $user->hasRight('fournisseur', 'facture', 'creer')) {
	$socid = GETPOSTINT('socid');
	$costitem = GETPOSTINT('costitem');
	$amount = (float) price2num(GETPOST('amount', 'alphanohtml'), 'MT');
	$vat = (float) price2num(GETPOST('vat', 'alphanohtml'));
	$date = dol_stringtotime(GETPOST('date', 'alphanohtml')) ?: dol_now();
	$refsupplier = trim(GETPOST('ref_supplier', 'alphanohtml'));
	$label = trim(GETPOST('label', 'alphanohtml'));
	if ($socid <= 0 || $costitem <= 0 || $amount == 0.0) {
		$err = $langs->trans('ErrorFieldRequired', $langs->transnoentities('HvSupplier').' / '.$langs->transnoentities('HvCostItem').' / '.$langs->transnoentities('HvAmountGrossInput'));
	} else {
		if ($refsupplier === '') {
			$refsupplier = 'MOBIL-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
		}
		$db->begin();
		$ff = new FactureFournisseur($db);
		$ff->socid = $socid;
		$ff->ref_supplier = $refsupplier;
		$ff->date = $date;
		$ff->type = FactureFournisseur::TYPE_STANDARD;
		$ff->label = $label ?: ($costitems[$costitem]->label ?? '');
		$ff->note_private = GETPOST('note', 'restricthtml');
		$ff->array_options = array('options_hv_costitem' => $costitem, 'options_hv_reserve' => GETPOSTINT('reserve') ? 1 : 0);
		$id = $ff->create($user);
		if ($id > 0) {
			$desc = $ff->label;
			$r = $ff->addline($desc, $amount, $vat, 0, 0, 1, 0, 0, 0, 0, 0, 0, 'TTC', 1);
			if ($r <= 0) {
				$id = -1;
			}
		}
		if ($id > 0) {
			$db->commit();
			$ff->fetch($id);
			if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
				$dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($ff->id, 2, 0, 0, $ff, 'invoice_supplier').dol_sanitizeFileName($ff->ref);
				dol_mkdir($dir);
				$name = dol_sanitizeFileName($_FILES['photo']['name']) ?: 'beleg.jpg';
				$resu = dol_move_uploaded_file($_FILES['photo']['tmp_name'], $dir.'/'.$name, 1, 0, $_FILES['photo']['error']);
				if (!is_numeric($resu) || $resu <= 0) {
					$err = 'Upload: '.$langs->trans((string) $resu);
				}
			}
			$msg = $langs->trans('HvSaved').': <a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$ff->id.'">'.dol_escape_htmltag($ff->ref.' / '.$ff->ref_supplier).'</a>';
		} else {
			$db->rollback();
			$err = $ff->error.' '.implode(', ', $ff->errors);
		}
	}
	$view = 'capture';
}

// --- Ausgabe: schlanke Mobilseite ---
top_htmlhead('', $langs->trans('Hausverwaltung'));
?>
<style>
body.hvm{margin:0;background:#f4f5f7;font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#222}
.hvm header{background:#2e5a3f;color:#fff;padding:14px 16px;font-size:18px;display:flex;justify-content:space-between;align-items:center}
.hvm header a{color:#fff;text-decoration:none}
.hvm main{padding:16px;max-width:640px;margin:0 auto}
.hvm .tiles{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.hvm .tile{background:#fff;border-radius:12px;padding:20px 12px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.1);text-decoration:none;color:#222;font-weight:600}
.hvm .tile span{display:block;font-size:30px;margin-bottom:6px}
.hvm form label{display:block;margin:12px 0 4px;font-weight:600;font-size:14px}
.hvm input,.hvm select,.hvm textarea{width:100%;box-sizing:border-box;font-size:17px;padding:10px;border:1px solid #ccc;border-radius:8px;background:#fff}
.hvm input[type=checkbox]{width:auto;transform:scale(1.4);margin-right:8px}
.hvm button{width:100%;margin-top:18px;padding:14px;font-size:18px;border:0;border-radius:10px;background:#2e5a3f;color:#fff}
.hvm .card{background:#fff;border-radius:12px;padding:12px 14px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.hvm .row{display:flex;justify-content:space-between;gap:8px}
.hvm .ok{background:#e3f4e8;padding:12px;border-radius:8px;margin-bottom:12px}
.hvm .err{background:#fde8e8;padding:12px;border-radius:8px;margin-bottom:12px}
.hvm .muted{color:#777;font-size:13px}
</style>
<?php
print '<body class="hvm"><header><a href="'.$_SERVER['PHP_SELF'].'">🏠 Bibali 83b</a><a href="'.dol_buildpath('/hausverwaltung/index.php', 1).'" class="muted" style="color:#cfe">Desktop</a></header><main>';
if ($msg) {
	print '<div class="ok">'.$msg.'</div>';
}
if ($err) {
	print '<div class="err">'.dol_escape_htmltag($err).'</div>';
}

if ($view === 'home') {
	print '<div class="tiles">';
	print '<a class="tile" href="?view=capture"><span>📷</span>'.$langs->trans('HvCapture').'</a>';
	print '<a class="tile" href="?view=open"><span>🧾</span>'.$langs->trans('HvOpenInvoices').'</a>';
	print '<a class="tile" href="?view=reserves"><span>🏦</span>'.$langs->trans('HvReserves').'</a>';
	print '<a class="tile" href="'.dol_buildpath('/hausverwaltung/generate.php', 1).'"><span>➕</span>'.$langs->trans('HvGenerate').'</a>';
	print '</div>';
} elseif ($view === 'capture') {
	// Lieferanten
	$suppliers = array();
	$res = $db->query("SELECT rowid, nom FROM ".$db->prefix()."societe WHERE fournisseur = 1 AND status = 1 AND entity IN (".getEntity('societe').") ORDER BY nom");
	while ($res && ($o = $db->fetch_object($res))) {
		$suppliers[(int) $o->rowid] = $o->nom;
	}
	print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="capture">';
	print '<label>'.$langs->trans('HvPhoto').'</label><input type="file" name="photo" accept="image/*,application/pdf" capture="environment">';
	print '<label>'.$langs->trans('HvCostItem').' *</label><select name="costitem" id="costitem" required><option value=""></option>';
	foreach ($costitems as $c) {
		print '<option value="'.$c->rowid.'" data-vat="'.price2num($c->vat_rate).'" data-soc="'.((int) $c->fk_soc_supplier).'">'.dol_escape_htmltag($c->label.' · '.hvBuildingLabel($c->fk_building, $buildings)).'</option>';
	}
	print '</select>';
	print '<label>'.$langs->trans('HvSupplier').' *</label><select name="socid" id="socid" required><option value=""></option>';
	foreach ($suppliers as $sid => $sn) {
		print '<option value="'.$sid.'">'.dol_escape_htmltag($sn).'</option>';
	}
	print '</select><div class="muted"><a href="'.DOL_URL_ROOT.'/societe/card.php?action=create&type=f&fournisseur=1">+ '.$langs->trans('NewSupplier').'</a></div>';
	print '<label>'.$langs->trans('HvInvoiceNumber').'</label><input name="ref_supplier" autocomplete="off">';
	print '<label>'.$langs->trans('Date').'</label><input type="date" name="date" value="'.dol_print_date(dol_now(), '%Y-%m-%d').'">';
	print '<div class="row"><div style="flex:2"><label>'.$langs->trans('HvAmountGrossInput').' *</label><input name="amount" inputmode="decimal" required></div><div style="flex:1"><label>'.$langs->trans('HvVatRate').'</label><input name="vat" id="vat" inputmode="decimal" value="25"></div></div>';
	print '<label>'.$langs->trans('Label').'</label><input name="label">';
	print '<label><input type="checkbox" name="reserve" value="1">'.$langs->trans('HvPaidFromReserve').'</label>';
	print '<label>'.$langs->trans('Note').'</label><textarea name="note" rows="2"></textarea>';
	print '<button type="submit">'.$langs->trans('Save').'</button></form>';
	print '<script>document.getElementById("costitem").addEventListener("change",function(){var o=this.options[this.selectedIndex];if(!o)return;if(o.dataset.vat!==undefined)document.getElementById("vat").value=o.dataset.vat;if(o.dataset.soc&&o.dataset.soc!=="0")document.getElementById("socid").value=o.dataset.soc;});</script>';
} elseif ($view === 'open') {
	$sql = "SELECT f.rowid, f.ref, f.total_ttc, f.date_lim_reglement, s.nom, fe.hv_period, (SELECT COALESCE(SUM(pf.amount),0) FROM ".$db->prefix()."paiement_facture pf WHERE pf.fk_facture = f.rowid) as paid";
	$sql .= " FROM ".$db->prefix()."facture f INNER JOIN ".$db->prefix()."facture_extrafields fe ON fe.fk_object = f.rowid LEFT JOIN ".$db->prefix()."societe s ON s.rowid = f.fk_soc";
	$sql .= " WHERE f.entity = ".((int) $conf->entity)." AND f.fk_statut = 1 AND f.paye = 0 AND fe.hv_period IS NOT NULL ORDER BY f.date_lim_reglement, s.nom";
	$res = $db->query($sql);
	$sum = 0;
	while ($res && ($o = $db->fetch_object($res))) {
		$rest = $o->total_ttc - $o->paid;
		$sum += $rest;
		$late = $db->jdate($o->date_lim_reglement) < dol_now();
		print '<a class="card" style="display:block;text-decoration:none;color:inherit" href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$o->rowid.'"><div class="row"><strong>'.dol_escape_htmltag($o->nom).'</strong><strong'.($late ? ' style="color:#c00"' : '').'>'.hvMoney($rest).'</strong></div><div class="muted">'.dol_escape_htmltag($o->ref.' · '.$o->hv_period).' · '.$langs->trans('DateDue').' '.dol_print_date($db->jdate($o->date_lim_reglement), 'day').'</div></a>';
	}
	print '<div class="card row"><strong>'.$langs->trans('Total').'</strong><strong>'.hvMoney($sum).'</strong></div>';
} elseif ($view === 'reserves') {
	foreach ($buildings as $b) {
		$bal = '-';
		if ($b->fk_bank_reserve) {
			$acc = new Account($db);
			if ($acc->fetch($b->fk_bank_reserve) > 0) {
				$bal = hvMoney($acc->solde(1));
			}
		}
		print '<div class="card row"><span>'.dol_escape_htmltag($b->ref.' – '.$b->label).'</span><strong>'.$bal.'</strong></div>';
	}
}
print '</main></body></html>';
$db->close();
