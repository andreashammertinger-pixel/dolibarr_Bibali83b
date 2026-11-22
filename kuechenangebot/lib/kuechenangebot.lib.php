<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/lib/kuechenangebot.lib.php
 * \brief   Hilfsfunktionen, Standardtexte (Angebotsbestimmungen, Textbausteine)
 */

/** Zusätzliche Sprachen für die Übersetzungskopie */
function kaLang2Options()
{
	return array('hr_HR' => 'Hrvatski (Kroatisch)', 'en_US' => 'English (Englisch)');
}

/** Register der Einstellungsseiten */
function kaAdminPrepareHead()
{
	global $langs;
	$h = 0;
	$head = array();
	$head[$h] = array(dol_buildpath('/kuechenangebot/admin/setup.php', 1), $langs->trans('KaMenuSetup'), 'setup');
	$h++;
	$head[$h] = array(dol_buildpath('/kuechenangebot/bausteine.php', 1), $langs->trans('KaMenuBausteine'), 'bausteine');
	$h++;
	$head[$h] = array(dol_buildpath('/kuechenangebot/kennzahlen.php', 1), $langs->trans('KaMenuKennzahlen'), 'kennzahlen');
	$h++;
	return $head;
}

/** Betrag deutsch formatiert: € 1.234,50 */
function kaMoney($v, $withsymbol = true)
{
	$s = number_format((float) $v, 2, ',', '.');
	return $withsymbol ? '€ '.$s : $s;
}

/** Zahl aus Formular (Komma oder Punkt) */
function kaNum($name, $default = 0)
{
	$v = GETPOST($name, 'alphanohtml');
	if ($v === '' || $v === null) {
		return $default;
	}
	$v = str_replace(array(' ', '€'), '', $v);
	if (preg_match('/,\d*$/', $v)) {
		$v = str_replace(array('.', ','), array('', '.'), $v);
	}
	return (float) $v;
}

/** Zahl für Eingabefeld */
function kaFmt($v, $dec = 2)
{
	return number_format((float) $v, $dec, ',', '');
}

/**
 * Angebotsbestimmungen: Einstellung oder Standardtext
 * @param string $lang de | hr | en
 * @return string[] eine Bestimmung je Eintrag
 */
function kaTerms($lang = 'de')
{
	$lang = strtolower(substr($lang, 0, 2));
	$txt = getDolGlobalString('KA_TERMS_'.strtoupper($lang));
	if ($txt === '') {
		$def = kaDefaultTerms();
		$txt = isset($def[$lang]) ? $def[$lang] : $def['de'];
	}
	$txt = str_replace('{GUELTIG_TAGE}', (string) getDolGlobalInt('KA_GUELTIG_TAGE', 30), $txt);
	return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $txt))));
}

/**
 * Überarbeitete Angebotsbestimmungen (Österreich, Möbel/Küchen) – Vorlage, keine Rechtsberatung.
 * Grundlage: Vorlage migora (Angebot 2024-0040), ABGB, KSchG, VGG, FAGG, UGB.
 */
function kaDefaultTerms()
{
	return array(
		'de' => "Gültigkeit: Dieses Angebot ist {GUELTIG_TAGE} Tage ab Angebotsdatum gültig. Grundlage sind die beiliegende Planung und die darin angegebenen Maße.
Leistungsumfang: Die Küche wird zum angebotenen Gesamtpreis inkl. Lieferung und Aufbau abgerechnet. Zusätzliche, nicht im Angebot enthaltene Leistungen werden nur nach Ihrer Zustimmung ausgeführt und nach tatsächlichem Aufwand bzw. Verbrauch abgerechnet.
Aufmaß: Vor der Bestellung erfolgt ein Aufmaß vor Ort. Ergeben sich dabei Abweichungen von der Planung, passen wir das Angebot an und stimmen es mit Ihnen ab.
Sonderwünsche: Änderungen und Sonderwünsche nach Auftragsbestätigung werden als Aufpreis gesondert verrechnet. Der Aufpreis wird Ihnen vorher bekannt gegeben und ist ab Ihrer Bestätigung zur Gänze (100 %) zu bezahlen.
Zahlung: 50 % bei Auftragsbestätigung, 50 % nach Fertigstellung, jeweils nach Rechnungserhalt ohne Abzug.
Zahlungsverzug: Bei Zahlungsverzug verrechnen wir Verzugszinsen in gesetzlicher Höhe (Verbraucher 4 % p. a., Unternehmer 9,2 Prozentpunkte über dem Basiszinssatz) sowie die zur Einbringung notwendigen und angemessenen Mahn- und Inkassokosten.
Rücktrittsrecht: Die Ware wird nach den Vorgaben des Kunden individuell geplant und angefertigt (Naturmaß, Farbe, Struktur, Größe, Ausführung, Geräte). Ein Rücktrittsrecht nach dem FAGG besteht daher auch bei Vertragsabschluss beim Hausbesuch oder online nicht (§ 18 Abs 1 Z 3 FAGG).
Storno: Tritt ein Verbraucher ohne berechtigten Grund vom Vertrag zurück, verrechnen wir die tatsächlich angefallenen Kosten (bereits bestellte oder gefertigte Ware, Planungs- und Aufmaßaufwand, Stornokosten der Lieferanten), höchstens das vereinbarte Entgelt abzüglich unserer Ersparnis. Unternehmer zahlen eine Stornogebühr von 30 % des Auftragswertes, für bereits bestellte, nach Maß gefertigte Ware und Sonderwünsche bis zu 100 % des darauf entfallenden Preises.
Eigentumsvorbehalt: Die gelieferte Ware bleibt bis zur vollständigen Bezahlung unser Eigentum.
Termine: Liefer- und Montagetermine werden nach Auftragsbestätigung und Aufmaß vereinbart. Lieferzeiten des Herstellers sind unverbindlich.
Baufreiheit: Für die Montage müssen die Räume baufrei, zugänglich und mit Strom versorgt sein. Elektro-, Gas-, Wasser- und Abwasserinstallationen sind, sofern nicht ausdrücklich angeboten, bauseits durch befugte Fachbetriebe herzustellen.
Abweichungen: Geringfügige, material- oder herstellungsbedingte Abweichungen in Farbe, Struktur und Maß gegenüber Mustern und Abbildungen bleiben vorbehalten, soweit sie Ihnen zumutbar sind.
Gewährleistung: Es gilt die gesetzliche Gewährleistung. Garantien der Gerätehersteller werden direkt über den Kundendienst des jeweiligen Herstellers abgewickelt.
Allgemeines: Es gilt österreichisches Recht. Gerichtsstand für Unternehmer ist Graz; für Verbraucher gelten die gesetzlichen Gerichtsstände.",
		'hr' => "Valjanost: Ova ponuda vrijedi {GUELTIG_TAGE} dana od datuma ponude. Osnova su priloženi plan i u njemu navedene mjere.
Opseg usluge: Kuhinja se obračunava po ponuđenoj ukupnoj cijeni uključujući isporuku i montažu. Dodatne usluge koje nisu sadržane u ponudi izvode se samo uz Vašu suglasnost i obračunavaju prema stvarnom utrošku.
Izmjera: Prije narudžbe obavlja se izmjera na licu mjesta. Ako se pritom pojave odstupanja od plana, prilagodit ćemo ponudu i uskladiti je s Vama.
Posebne želje: Izmjene i posebne želje nakon potvrde narudžbe obračunavaju se zasebno kao doplata. Doplata Vam se unaprijed priopćuje i od Vaše potvrde plaća se u cijelosti (100 %).
Plaćanje: 50 % pri potvrdi narudžbe, 50 % nakon dovršetka, svaki put po primitku računa bez odbitka.
Kašnjenje s plaćanjem: U slučaju kašnjenja s plaćanjem obračunavamo zatezne kamate u zakonskoj visini (potrošači 4 % godišnje, poduzetnici 9,2 postotna boda iznad osnovne kamatne stope) te nužne i primjerene troškove opomena i naplate.
Pravo na odustanak: Roba se planira i izrađuje pojedinačno prema zahtjevima kupca (stvarne mjere, boja, struktura, veličina, izvedba, uređaji). Stoga ne postoji pravo na odustanak prema FAGG-u ni kod sklapanja ugovora prilikom kućnog posjeta ili online (§ 18 st. 1 t. 3 FAGG).
Storno: Ako potrošač bez opravdanog razloga odustane od ugovora, obračunavamo stvarno nastale troškove (već naručena ili izrađena roba, troškovi planiranja i izmjere, troškovi storna dobavljača), najviše ugovorenu naknadu umanjenu za našu uštedu. Poduzetnici plaćaju naknadu za storno od 30 % vrijednosti narudžbe, a za već naručenu robu izrađenu po mjeri i posebne želje do 100 % pripadajuće cijene.
Pridržaj prava vlasništva: Isporučena roba ostaje naše vlasništvo do potpune isplate.
Rokovi: Rokovi isporuke i montaže dogovaraju se nakon potvrde narudžbe i izmjere. Rokovi isporuke proizvođača nisu obvezujući.
Spremnost prostora: Za montažu prostorije moraju biti slobodne, pristupačne i opskrbljene strujom. Električne, plinske, vodovodne i kanalizacijske instalacije, ako nisu izričito ponuđene, izvodi naručitelj putem ovlaštenih stručnih tvrtki.
Odstupanja: Zadržavamo pravo na manja odstupanja u boji, strukturi i mjerama u odnosu na uzorke i slike uvjetovana materijalom ili proizvodnjom, ako su Vam prihvatljiva.
Jamstvo: Vrijedi zakonsko jamstvo za nedostatke. Jamstva proizvođača uređaja ostvaruju se izravno putem servisne službe dotičnog proizvođača.
Općenito: Primjenjuje se austrijsko pravo. Mjesto nadležnosti za poduzetnike je Graz; za potrošače vrijede zakonska mjesta nadležnosti.",
		'en' => "Validity: This offer is valid for {GUELTIG_TAGE} days from the offer date. It is based on the enclosed planning and the dimensions stated therein.
Scope: The kitchen is invoiced at the offered total price including delivery and assembly. Additional services not included in the offer are carried out only with your consent and invoiced according to actual time and material.
Measurement: Before ordering, the site is measured. If deviations from the planning arise, we will adjust the offer and agree it with you.
Special requests: Changes and special requests after order confirmation are invoiced separately as a surcharge. The surcharge is communicated in advance and is payable in full (100 %) from your confirmation.
Payment: 50 % on order confirmation, 50 % after completion, each upon receipt of invoice without deduction.
Late payment: In case of late payment we charge statutory default interest (consumers 4 % p. a., businesses 9.2 percentage points above the base rate) as well as necessary and appropriate dunning and collection costs.
Right of withdrawal: The goods are individually planned and made to the customer's specifications (site measurements, colour, texture, size, design, appliances). Therefore no right of withdrawal under the FAGG exists, even if the contract is concluded during a home visit or online (§ 18 (1) 3 FAGG).
Cancellation: If a consumer withdraws from the contract without justified reason, we charge the costs actually incurred (goods already ordered or made, planning and measuring work, suppliers' cancellation costs), at most the agreed price less our savings. Businesses pay a cancellation fee of 30 % of the order value, and up to 100 % of the related price for goods already ordered and made to measure and for special requests.
Retention of title: Goods delivered remain our property until paid in full.
Dates: Delivery and assembly dates are agreed after order confirmation and measurement. Delivery times of the manufacturer are not binding.
Site readiness: For assembly the rooms must be free, accessible and supplied with electricity. Electrical, gas, water and drainage installations are, unless expressly offered, to be provided by the client through licensed specialists.
Deviations: Minor deviations in colour, texture and size compared to samples and illustrations due to material or production remain reserved, insofar as they are reasonable for you.
Warranty: The statutory warranty applies. Guarantees of appliance manufacturers are handled directly by the customer service of the respective manufacturer.
General: Austrian law applies. Place of jurisdiction for businesses is Graz; for consumers the statutory places of jurisdiction apply.",
	);
}

/**
 * Vorgefertigte Textbausteine
 * @return array<int,array{code:string,label:string,de:string,hr:string,en:string,vorauswahl:int}>
 */
function kaDefaultBausteine()
{
	return array(
		array('code' => 'AUFMASS', 'label' => 'Aufmaß vor Ort', 'vorauswahl' => 1,
			'de' => 'Vor der Bestellung erfolgt ein verbindliches Aufmaß vor Ort. Maßänderungen gegenüber der Planung können zu Preisänderungen führen.',
			'hr' => 'Prije narudžbe obavlja se obvezujuća izmjera na licu mjesta. Promjene mjera u odnosu na plan mogu dovesti do promjene cijene.',
			'en' => 'A binding on-site measurement takes place before ordering. Changes in dimensions compared to the planning may lead to price changes.'),
		array('code' => 'ANSCHLUSS', 'label' => 'Anschlüsse bauseits', 'vorauswahl' => 1,
			'de' => 'Elektro-, Wasser- und Abwasseranschlüsse sind bauseits nach unserem Installationsplan herzustellen. Arbeiten an der Elektro- und Wasserinstallation sind nicht enthalten.',
			'hr' => 'Električne, vodovodne i kanalizacijske priključke izvodi naručitelj prema našem planu instalacija. Radovi na električnim i vodovodnim instalacijama nisu uključeni.',
			'en' => 'Electrical, water and drainage connections are to be provided by the client according to our installation plan. Work on electrical and water installations is not included.'),
		array('code' => 'FARBE', 'label' => 'Farbabweichungen / 3D-Bild', 'vorauswahl' => 1,
			'de' => 'Farb- und Strukturabweichungen gegenüber Mustern und Abbildungen sind material- und herstellungsbedingt möglich. Die 3D-Darstellung ist unverbindlich.',
			'hr' => 'Moguća su odstupanja u boji i strukturi u odnosu na uzorke i slike uvjetovana materijalom i proizvodnjom. 3D prikaz nije obvezujući.',
			'en' => 'Deviations in colour and texture compared to samples and illustrations are possible due to material and production. The 3D illustration is not binding.'),
		array('code' => 'BAUFREI', 'label' => 'Baufreiheit', 'vorauswahl' => 1,
			'de' => 'Für die Montage müssen die Räume baufrei, besenrein und mit Strom versorgt sein; Wand-, Boden- und Malerarbeiten müssen abgeschlossen sein.',
			'hr' => 'Za montažu prostorije moraju biti slobodne, očišćene i opskrbljene strujom; zidni, podni i soboslikarski radovi moraju biti završeni.',
			'en' => 'For assembly the rooms must be free, swept clean and supplied with electricity; wall, floor and painting work must be completed.'),
		array('code' => 'WAND', 'label' => 'Tragfähige Wände', 'vorauswahl' => 0,
			'de' => 'Die Wände müssen für die Befestigung der Hängeschränke tragfähig sein. Bei Trockenbauwänden sind Verstärkungen bauseits vorzusehen.',
			'hr' => 'Zidovi moraju biti nosivi za pričvršćivanje visećih elemenata. Kod suhogradnje naručitelj mora predvidjeti ojačanja.',
			'en' => 'Walls must be load-bearing for fixing the wall units. For drywall, reinforcements must be provided by the client.'),
		array('code' => 'APL', 'label' => 'Arbeitsplatten-Fugen', 'vorauswahl' => 0,
			'de' => 'Arbeitsplatten werden in Standardlängen geliefert; Stoß- und Eckverbindungen sind technisch bedingt sichtbar.',
			'hr' => 'Radne ploče isporučuju se u standardnim duljinama; spojevi i kutni spojevi su iz tehničkih razloga vidljivi.',
			'en' => 'Worktops are supplied in standard lengths; joints and corner connections are visible for technical reasons.'),
		array('code' => 'ALTKUECHE', 'label' => 'Altküche', 'vorauswahl' => 0,
			'de' => 'Abbau und Entsorgung einer bestehenden Küche sind nur enthalten, wenn sie im Angebot ausdrücklich angeführt sind.',
			'hr' => 'Demontaža i zbrinjavanje postojeće kuhinje uključeni su samo ako su izričito navedeni u ponudi.',
			'en' => 'Removal and disposal of an existing kitchen are only included if expressly stated in the offer.'),
		array('code' => 'MFH', 'label' => 'Wohnbau: Preis je Küche', 'vorauswahl' => 0,
			'de' => 'Die Preise gelten je Küche bei Beauftragung der angeführten Stückzahl und Montage in einem zusammenhängenden Zeitraum. Abrufe in Teilmengen sind gesondert zu vereinbaren.',
			'hr' => 'Cijene vrijede po kuhinji uz narudžbu navedene količine i montažu u jednom neprekinutom razdoblju. Pozivi u dijelovima dogovaraju se zasebno.',
			'en' => 'Prices apply per kitchen when the stated quantity is ordered and assembled in one continuous period. Call-offs in partial quantities must be agreed separately.'),
	);
}

/** Textbausteine aus der Datenbank */
function kaFetchBausteine($db, $onlyactive = true)
{
	global $conf;
	$out = array();
	$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'ka_baustein WHERE entity = '.((int) $conf->entity).($onlyactive ? ' AND aktiv = 1' : '').' ORDER BY rang, rowid';
	$res = $db->query($sql);
	while ($res && ($o = $db->fetch_object($res))) {
		$out[$o->code] = $o;
	}
	return $out;
}

/** Standard-Textbausteine einfügen (nur fehlende Codes) */
function kaSeedBausteine($db)
{
	global $conf;
	$have = kaFetchBausteine($db, false);
	$rang = 10;
	foreach (kaDefaultBausteine() as $b) {
		if (!isset($have[$b['code']])) {
			$db->query('INSERT INTO '.MAIN_DB_PREFIX.'ka_baustein (entity, code, label, text_de, text_hr, text_en, vorauswahl, aktiv, rang) VALUES ('
				.((int) $conf->entity).", '".$db->escape($b['code'])."', '".$db->escape($b['label'])."', '".$db->escape($b['de'])."', '"
				.$db->escape($b['hr'])."', '".$db->escape($b['en'])."', ".((int) $b['vorauswahl']).', 1, '.$rang.')');
		}
		$rang += 10;
	}
}

/** Hinweise aus Nobilia-Unterlagen, die nicht an Kunden gehören (Planer-AGB, Einkaufshinweise) */
function kaIsInternalHint($t)
{
	return (bool) preg_match('/(AGB der|ACHTUNG|Katalogpunkte|Einkaufspreis|Händler|Blockverrechnung)/u', $t);
}
