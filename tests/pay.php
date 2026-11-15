<?php
define('NOSESSION', 1);
require $argv[1].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
$user->fetch(0, 'admin'); $user->getrights();
$langs->setDefaultLang('de_DE');
// Kundenzahlung voll für Rechnung 1 (Apt 1.1) und teilweise 50 € für Rechnung 9 (Apt 2.1)
$db->begin();
$p = new Paiement($db); $p->datepaye = dol_mktime(12,0,0,11,4,2026); $p->amounts = array(1 => 126.65, 9 => 50); $p->paiementid = dol_getIdFromCode($db, 'VIR', 'c_paiement', 'code', 'id', 1); $p->num_payment='T1';
$id = $p->create($user, 1); echo "pay=$id\n";
$r = $p->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', 1, '', ''); echo "bank=$r ".$p->error."\n";
$db->commit();
// Lieferantenrechnungen freigeben und bezahlen
foreach (array(1, 2) as $fid) { $ff = new FactureFournisseur($db); $ff->fetch($fid); $ff->validate($user); }
$db->begin();
$pf = new PaiementFourn($db); $pf->datepaye = dol_mktime(12,0,0,11,15,2026); $pf->amounts = array(1 => 158.03, 2 => 75); $pf->paiementid = $p->paiementid; $pf->num_payment='S1';
$id = $pf->create($user, 1); echo "spay=$id ".$pf->error."\n";
$r = $pf->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', 1, '', ''); echo "sbank=$r ".$pf->error."\n";
$db->commit();
