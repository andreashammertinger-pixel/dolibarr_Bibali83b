<?php
define('NOSESSION', 1);
require $argv[1].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
$user->fetch(0, 'admin'); $user->getrights();
$f = new Facture($db); $f->fetch(1); $f->setUnpaid($user); $p = new Paiement($db); $p->fetch(2); $r = $p->delete($user); echo "delete=$r ".$p->error."\n";
