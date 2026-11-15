<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceHausverwaltungTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'financial';
		$this->description = 'Hausverwaltung: automatische Rücklagen-Umbuchung bei Zahlungen';
		$this->version = '1.0.0';
		$this->picto = 'building';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('hausverwaltung')) {
			return 0;
		}
		if (!in_array($action, array('PAYMENT_ADD_TO_BANK', 'PAYMENT_CUSTOMER_DELETE', 'PAYMENT_SUPPLIER_DELETE', 'BILL_VALIDATE', 'BILL_UNVALIDATE', 'BILL_DELETE'), true)) {
			return 0;
		}
		dol_include_once('/hausverwaltung/class/hvreserve.class.php');
		$r = new HvReserve($this->db);
		if ($action === 'PAYMENT_ADD_TO_BANK') {
			$res = $r->onPaymentAddedToBank($object, $user);
		} elseif ($action === 'BILL_VALIDATE') {
			$res = $r->onInvoiceValidated($object, $user);
		} elseif ($action === 'BILL_UNVALIDATE' || $action === 'BILL_DELETE') {
			$res = $r->onInvoiceUnvalidated($object, $user);
		} else {
			$res = $r->onPaymentDeleted($object, $user);
		}
		if ($res < 0) {
			$this->errors[] = $r->error;
			return -1;
		}
		dol_syslog("Trigger '".$this->name."' for action '".$action."' : ".$res." reserve moves", LOG_DEBUG);
		return $res > 0 ? 1 : 0;
	}
}
