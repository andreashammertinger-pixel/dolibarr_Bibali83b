<?php
$sapi_type = php_sapi_name();
define('NOSESSION', 1);
require $argv[1].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
$user->fetch(0, 'admin'); $user->getrights();
foreach (array('modSociete','modFacture','modFournisseur','modBanque','modService','modProduct') as $m) { activateModule($m); }
$r = activateModule('modHausverwaltung'); print_r($r['errors'] ?? []);
dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_COUNTRY', '56:HR:Croatia', 'chaine', 0, '', 1);
// Konten
$ids = array();
foreach (array('BETRIEB' => 'Betriebskonto HV', 'RL1' => 'Rücklage Haus 1', 'RL2' => 'Rücklage Haus 2', 'RL3' => 'Rücklage Haus 3') as $ref => $label) {
  $a = new Account($db); $a->ref = $ref; $a->label = $label; $a->type = Account::TYPE_CURRENT; $a->currency_code = 'EUR'; $a->country_id = 56; $a->date_solde = dol_now(); $a->solde = 0;
  $ids[$ref] = $a->create($user); if ($ids[$ref] <= 0) { echo $a->error; }
}
print_r($ids);
// Geschäftspartner
foreach (array('1.1','1.2','1.3','1.4','1.5','1.6','1.7','1.8','2.1','2.2','3.1') as $u) {
  $s = new Societe($db); $s->name = 'Apartment '.$u; $s->client = 1; $s->code_client = -1; $s->country_id = 56; $s->default_lang = ($u=='2.2'?'de_DE':'hr_HR');
  $id = $s->create($user); if ($id <= 0) echo $s->error;
}
$s = new Societe($db); $s->name = 'WIENER OSIGURANJE'; $s->fournisseur = 1; $s->code_fournisseur = -1; $s->country_id = 56; $s->create($user);
echo "ok\n";
