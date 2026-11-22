<?php
/* Copyright (C) 2026 migora gmbh — GPL v3+ */
/**
 * \file    kuechenangebot/class/kakalk.class.php
 * \brief   Küchenkalkulation: Daten, Berechnung, Kennzahlen-Vergleich
 */

class KaKalk
{
	/** @var DoliDB */
	public $db;
	public $id = 0;
	public $entity = 1;
	public $fk_soc = 0;
	public $fk_propal = 0;
	public $art = 'privat';
	public $lang2 = '';
	public $label = '';
	public $objekt = '';
	/** @var array */
	public $params = array();
	/** @var array{bausteine:array,nobilia:array} */
	public $hinweise = array('bausteine' => array(), 'nobilia' => array());
	public $note_de = '';
	public $note_lang2 = '';
	public $datec;
	public $fk_user_creat = 0;
	/** @var KaTyp[] */
	public $typen = array();
	public $error = '';

	/** Standardwerte der Kalkulationsparameter */
	public static function defaultParams($art = 'privat')
	{
		$mfh = ($art === 'mfh');
		return array(
			'aufschlag_modus' => 'pct',
			'aufschlag_wert' => (float) getDolGlobalString($mfh ? 'KA_AUFSCHLAG_MFH' : 'KA_AUFSCHLAG_PRIVAT', $mfh ? '20' : '30'),
			'transport_modus' => 'none', // none | projekt | kueche
			'transport_betrag' => 0,
			'montage_modus' => 'stunden', // pauschal | stunden | lfm
			'montage_pauschal' => 0,
			'montage_arbeiter' => 2,
			'montage_std' => 0, // Stunden je Arbeiter und Küche (0 = aus lfm × Richtwert)
			'montage_satz' => (float) getDolGlobalString('KA_SATZ_STUNDE', '65'),
			'montage_lfm_satz' => (float) getDolGlobalString('KA_SATZ_LFM', '0'),
			'montage_std_kalk' => 0, // geschätzte Stunden je Küche bei Pauschale / lfm (für Kosten)
			'muell_modus' => 'none', // none | pauschal | stunden
			'muell_basis' => 'projekt', // projekt | kueche
			'muell_pauschal' => 0,
			'muell_std' => 0,
			'muell_satz' => (float) getDolGlobalString('KA_SATZ_STUNDE', '65'),
			'muell_faktor' => 1,
			'tva' => (float) getDolGlobalString('KA_TVA', '20'),
			'gueltig_tage' => (int) getDolGlobalString('KA_GUELTIG_TAGE', '30'),
			'plaene_anhaengen' => 0,
			'zeige_geraetepreise' => 0,
		);
	}

	public function __construct($db)
	{
		$this->db = $db;
		$this->params = self::defaultParams();
	}

	public function fetch($id = 0, $fk_propal = 0)
	{
		global $conf;
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'ka_kalk WHERE entity = '.((int) $conf->entity);
		$sql .= $id ? ' AND rowid = '.((int) $id) : ' AND fk_propal = '.((int) $fk_propal);
		$sql .= ' ORDER BY rowid DESC LIMIT 1';
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$o = $this->db->fetch_object($res);
		if (!$o) {
			return 0;
		}
		$this->id = (int) $o->rowid;
		$this->entity = (int) $o->entity;
		$this->fk_soc = (int) $o->fk_soc;
		$this->fk_propal = (int) $o->fk_propal;
		$this->art = $o->art;
		$this->lang2 = (string) $o->lang2;
		$this->label = (string) $o->label;
		$this->objekt = (string) $o->objekt;
		$this->params = array_merge(self::defaultParams($this->art), (array) json_decode((string) $o->params, true));
		$h = (array) json_decode((string) $o->hinweise, true);
		$this->hinweise = array_merge(array('bausteine' => array(), 'nobilia' => array()), $h);
		$this->note_de = (string) $o->note_de;
		$this->note_lang2 = (string) $o->note_lang2;
		$this->datec = $this->db->jdate($o->datec);
		$this->fk_user_creat = (int) $o->fk_user_creat;
		$this->typen = KaTyp::fetchAll($this->db, $this->id);
		return 1;
	}

	public function save($user)
	{
		global $conf;
		$calc = $this->compute();
		$fields = array(
			'fk_soc' => $this->fk_soc ? (int) $this->fk_soc : 'NULL',
			'fk_propal' => $this->fk_propal ? (int) $this->fk_propal : 'NULL',
			'art' => "'".$this->db->escape($this->art)."'",
			'lang2' => "'".$this->db->escape($this->lang2)."'",
			'label' => "'".$this->db->escape($this->label)."'",
			'objekt' => "'".$this->db->escape($this->objekt)."'",
			'params' => "'".$this->db->escape(json_encode($this->params))."'",
			'hinweise' => "'".$this->db->escape(json_encode($this->hinweise))."'",
			'note_de' => "'".$this->db->escape($this->note_de)."'",
			'note_lang2' => "'".$this->db->escape($this->note_lang2)."'",
			'total_ek' => (float) $calc['ek'],
			'total_kosten' => (float) $calc['selbstkosten'],
			'total_ht' => (float) $calc['vk_netto'],
			'total_lfm' => (float) $calc['lfm'],
			'total_stueck' => (float) $calc['stueck'],
			'montage_std' => (float) $calc['montage_std'],
		);
		if ($this->id) {
			$set = array();
			foreach ($fields as $k => $v) {
				$set[] = $k.' = '.$v;
			}
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'ka_kalk SET '.implode(', ', $set).' WHERE rowid = '.((int) $this->id);
		} else {
			$fields['entity'] = (int) $conf->entity;
			$fields['datec'] = "'".$this->db->idate(dol_now())."'";
			$fields['fk_user_creat'] = (int) $user->id;
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'ka_kalk ('.implode(', ', array_keys($fields)).') VALUES ('.implode(', ', $fields).')';
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if (!$this->id) {
			$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'ka_kalk');
			$this->entity = (int) $conf->entity;
		}
		return $this->id;
	}

	public function delete()
	{
		foreach ($this->typen as $t) {
			$t->delete();
		}
		$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'ka_kalk WHERE rowid = '.((int) $this->id));
		dol_delete_dir_recursive($this->getDir());
		return 1;
	}

	/** Ablageordner der Kalkulation (Nobilia-PDFs, 3D-Bilder) */
	public function getDir()
	{
		global $conf;
		$base = !empty($conf->kuechenangebot->multidir_output[$this->entity]) ? $conf->kuechenangebot->multidir_output[$this->entity] : $conf->kuechenangebot->dir_output;
		return $base.'/kalk/'.((int) $this->id);
	}

	// ------------------------------------------------------------------
	// Berechnung
	// ------------------------------------------------------------------

	/**
	 * Gesamte Kalkulation
	 * @return array
	 */
	public function compute()
	{
		$p = $this->params;
		$kStd = (float) getDolGlobalString('KA_KOSTEN_STUNDE', '45');
		$stdLfm = (float) getDolGlobalString('KA_STD_PRO_LFM', '2.5');
		$gk = (float) getDolGlobalString($this->art === 'mfh' ? 'KA_GK_MFH' : 'KA_GK_PRIVAT', $this->art === 'mfh' ? '8' : '12');

		$out = array('typen' => array(), 'ek' => 0.0, 'stueck' => 0.0, 'lfm' => 0.0, 'montage_std' => 0.0,
			'moebel_vk' => 0.0, 'montage_vk' => 0.0, 'transport_vk' => 0.0, 'muell_vk' => 0.0,
			'montage_kosten' => 0.0, 'transport_kosten' => 0.0, 'muell_kosten' => 0.0, 'gk_pct' => $gk);

		// Projektbezogene Beträge (werden nach EK-Anteil auf die Küchen verteilt)
		$stueck = 0;
		$ekSum = 0;
		foreach ($this->typen as $t) {
			$stueck += max(0, (float) $t->qty);
			$ekSum += $t->getEk() * max(0, (float) $t->qty);
		}
		$transportProjekt = ($p['transport_modus'] === 'projekt') ? (float) $p['transport_betrag'] : 0;
		$muellJe = 0;
		if ($p['muell_modus'] === 'pauschal') {
			$muellJe = (float) $p['muell_pauschal'];
		} elseif ($p['muell_modus'] === 'stunden') {
			$muellJe = (float) $p['muell_std'] * (float) $p['muell_satz'] * ((float) $p['muell_faktor'] ?: 1);
		}
		$muellProjekt = ($p['muell_basis'] === 'projekt') ? $muellJe : 0;
		$muellKueche = ($p['muell_basis'] === 'kueche') ? $muellJe : 0;
		$muellStdJe = ($p['muell_modus'] === 'stunden') ? (float) $p['muell_std'] * ((float) $p['muell_faktor'] ?: 1) : 0;

		foreach ($this->typen as $t) {
			$n = max(0, (float) $t->qty);
			$ek = $t->getEk();
			$lfm = (float) $t->lfm;
			$share = $ekSum > 0 ? ($ek * $n) / $ekSum : ($stueck > 0 ? $n / $stueck : 0);
			$perPiece = $n > 0 ? 1 / $n : 0;

			$moebel = ($p['aufschlag_modus'] === 'pauschal') ? $ek + (float) $p['aufschlag_wert'] : $ek * (1 + (float) $p['aufschlag_wert'] / 100);

			if ($p['montage_modus'] === 'pauschal') {
				$montage = (float) $p['montage_pauschal'];
				$std = (float) $p['montage_std_kalk'] ?: $lfm * $stdLfm;
			} elseif ($p['montage_modus'] === 'lfm') {
				$montage = $lfm * (float) $p['montage_lfm_satz'];
				$std = (float) $p['montage_std_kalk'] ?: $lfm * $stdLfm;
			} else {
				$stdJe = (float) $p['montage_std'] ?: ($lfm * $stdLfm / max(1, (int) $p['montage_arbeiter']));
				$std = max(1, (int) $p['montage_arbeiter']) * $stdJe;
				$montage = $std * (float) $p['montage_satz'];
			}
			$transport = (($p['transport_modus'] === 'kueche') ? (float) $p['transport_betrag'] : 0) + $transportProjekt * $share * $perPiece;
			$muell = $muellKueche + $muellProjekt * $share * $perPiece;
			$muellKosten = ($p['muell_modus'] === 'stunden')
				? ($muellStdJe * $kStd) * (($p['muell_basis'] === 'kueche') ? 1 : $share * $perPiece)
				: $muell;

			$vkCalc = $moebel + $montage + $transport + $muell;
			$vk = ($t->vk_override !== null && $t->vk_override > 0) ? (float) $t->vk_override : round($vkCalc, 2);
			$kosten = $ek + $transport + $muellKosten + $std * $kStd;

			$row = array(
				'id' => $t->id, 'label' => $t->label, 'qty' => $n, 'lfm' => $lfm, 'ek' => $ek,
				'moebel' => $moebel, 'montage' => $montage, 'montage_std' => $std, 'transport' => $transport, 'muell' => $muell,
				'vk_calc' => $vkCalc, 'vk' => $vk, 'kosten' => $kosten, 'kosten_gk' => $kosten * (1 + $gk / 100),
			);
			$out['typen'][] = $row;
			$out['ek'] += $ek * $n;
			$out['stueck'] += $n;
			$out['lfm'] += $lfm * $n;
			$out['montage_std'] += $std * $n;
			$out['moebel_vk'] += $moebel * $n;
			$out['montage_vk'] += $montage * $n;
			$out['transport_vk'] += $transport * $n;
			$out['muell_vk'] += $muell * $n;
			$out['montage_kosten'] += $std * $kStd * $n;
			$out['transport_kosten'] += $transport * $n;
			$out['muell_kosten'] += $muellKosten * $n;
		}
		$out['vk_calc'] = $out['moebel_vk'] + $out['montage_vk'] + $out['transport_vk'] + $out['muell_vk'];
		$out['vk_netto'] = 0;
		foreach ($out['typen'] as $r) {
			$out['vk_netto'] += $r['vk'] * $r['qty'];
		}
		$out['vk_netto'] = round($out['vk_netto'], 2);
		$out['tva'] = (float) $p['tva'];
		$out['mwst'] = round($out['vk_netto'] * $out['tva'] / 100, 2);
		$out['vk_brutto'] = $out['vk_netto'] + $out['mwst'];
		$out['selbstkosten_direkt'] = $out['ek'] + $out['montage_kosten'] + $out['transport_kosten'] + $out['muell_kosten'];
		$out['selbstkosten'] = $out['selbstkosten_direkt'] * (1 + $gk / 100);
		$out['db'] = $out['vk_netto'] - $out['selbstkosten'];
		$out['db_pct'] = $out['vk_netto'] > 0 ? $out['db'] / $out['vk_netto'] * 100 : 0;
		$out['faktor'] = $out['ek'] > 0 ? $out['vk_netto'] / $out['ek'] : 0;
		return $out;
	}

	/**
	 * Kennzahlen bisheriger Küchen gleicher Art: angenommene/fakturierte Angebote des Moduls + händische Einträge
	 * @return array{n:int,stueck:float,ek:float,vk:float,lfm:float,kosten:float,faktor:float,vk_lfm:float,vk_stueck:float,db_pct:float}
	 */
	public function benchmark()
	{
		global $conf;
		$art = $this->db->escape($this->art);
		$rows = array();
		$sql = 'SELECT k.total_ek as ek, k.total_ht as vk, k.total_lfm as lfm, k.total_stueck as stueck, k.total_kosten as kosten';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'ka_kalk as k INNER JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = k.fk_propal';
		$sql .= ' WHERE k.entity = '.((int) $conf->entity)." AND k.art = '".$art."' AND p.fk_statut IN (2, 4)";
		if ($this->id) {
			$sql .= ' AND k.rowid <> '.((int) $this->id);
		}
		$res = $this->db->query($sql);
		while ($res && ($o = $this->db->fetch_object($res))) {
			$rows[] = $o;
		}
		$sql = 'SELECT ek, vk, lfm, stueck, kosten FROM '.MAIN_DB_PREFIX.'ka_kennzahl WHERE entity = '.((int) $conf->entity)." AND art = '".$art."'";
		$res = $this->db->query($sql);
		while ($res && ($o = $this->db->fetch_object($res))) {
			$rows[] = $o;
		}
		$b = array('n' => count($rows), 'stueck' => 0.0, 'ek' => 0.0, 'vk' => 0.0, 'lfm' => 0.0, 'kosten' => 0.0, 'ek_mit_kosten' => 0.0, 'vk_mit_kosten' => 0.0);
		foreach ($rows as $o) {
			$b['stueck'] += (float) $o->stueck;
			$b['ek'] += (float) $o->ek;
			$b['vk'] += (float) $o->vk;
			$b['lfm'] += (float) $o->lfm;
			if ((float) $o->kosten > 0) {
				$b['kosten'] += (float) $o->kosten;
				$b['vk_mit_kosten'] += (float) $o->vk;
			}
		}
		$b['faktor'] = $b['ek'] > 0 ? $b['vk'] / $b['ek'] : 0;
		$b['vk_lfm'] = $b['lfm'] > 0 ? $b['vk'] / $b['lfm'] : 0;
		$b['vk_stueck'] = $b['stueck'] > 0 ? $b['vk'] / $b['stueck'] : 0;
		$b['db_pct'] = $b['vk_mit_kosten'] > 0 ? ($b['vk_mit_kosten'] - $b['kosten']) / $b['vk_mit_kosten'] * 100 : 0;
		return $b;
	}

	/** Liste aller Kalkulationen */
	public static function fetchList($db, $limit = 200)
	{
		global $conf;
		$sql = 'SELECT k.rowid, k.label, k.art, k.total_ht, k.total_stueck, k.datec, k.fk_propal, k.fk_soc, s.nom as socname, p.ref as propref, p.fk_statut as propstatus';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'ka_kalk as k';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = k.fk_soc';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'propal as p ON p.rowid = k.fk_propal';
		$sql .= ' WHERE k.entity = '.((int) $conf->entity).' ORDER BY k.rowid DESC'.$db->plimit($limit);
		$out = array();
		$res = $db->query($sql);
		while ($res && ($o = $db->fetch_object($res))) {
			$out[] = $o;
		}
		return $out;
	}
}

/**
 * Ein Küchentyp (ein Nobilia-Angebot) mit Stückzahl
 */
class KaTyp
{
	public $db;
	public $id = 0;
	public $fk_kalk = 0;
	public $rang = 0;
	public $label = '';
	public $qty = 1;
	public $preis_modus = 'eur';
	public $katalog = 0.0;
	public $faktor = 1.0;
	public $lfm = 0.0;
	public $vk_override = null;
	/** @var array Ergebnis von KaNobiliaParser::parseOffer (+ plaene) */
	public $data = array();
	public $file_an = '';
	public $file_bd = '';
	public $image = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Einkaufspreis je Küche (Katalogwert × Faktor) */
	public function getEk()
	{
		return round((float) $this->katalog * ((float) $this->faktor ?: 0), 2);
	}

	public static function fetchAll($db, $fk_kalk)
	{
		$out = array();
		$res = $db->query('SELECT * FROM '.MAIN_DB_PREFIX.'ka_typ WHERE fk_kalk = '.((int) $fk_kalk).' ORDER BY rang, rowid');
		while ($res && ($o = $db->fetch_object($res))) {
			$t = new KaTyp($db);
			$t->id = (int) $o->rowid;
			$t->fk_kalk = (int) $o->fk_kalk;
			$t->rang = (int) $o->rang;
			$t->label = (string) $o->label;
			$t->qty = (float) $o->qty;
			$t->preis_modus = (string) $o->preis_modus;
			$t->katalog = (float) $o->katalog;
			$t->faktor = (float) $o->faktor;
			$t->lfm = (float) $o->lfm;
			$t->vk_override = ($o->vk_override === null) ? null : (float) $o->vk_override;
			$t->data = (array) json_decode((string) $o->data, true);
			$t->file_an = (string) $o->file_an;
			$t->file_bd = (string) $o->file_bd;
			$t->image = (string) $o->image;
			$out[] = $t;
		}
		return $out;
	}

	public function save()
	{
		$f = array(
			'fk_kalk' => (int) $this->fk_kalk,
			'rang' => (int) $this->rang,
			'label' => "'".$this->db->escape($this->label)."'",
			'qty' => (float) $this->qty,
			'preis_modus' => "'".$this->db->escape($this->preis_modus)."'",
			'katalog' => (float) $this->katalog,
			'faktor' => (float) $this->faktor,
			'lfm' => (float) $this->lfm,
			'vk_override' => ($this->vk_override === null || $this->vk_override <= 0) ? 'NULL' : (float) $this->vk_override,
			'data' => "'".$this->db->escape(json_encode($this->data))."'",
			'file_an' => "'".$this->db->escape($this->file_an)."'",
			'file_bd' => "'".$this->db->escape($this->file_bd)."'",
			'image' => "'".$this->db->escape($this->image)."'",
		);
		if ($this->id) {
			$set = array();
			foreach ($f as $k => $v) {
				$set[] = $k.' = '.$v;
			}
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'ka_typ SET '.implode(', ', $set).' WHERE rowid = '.((int) $this->id);
		} else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'ka_typ ('.implode(', ', array_keys($f)).') VALUES ('.implode(', ', $f).')';
		}
		if (!$this->db->query($sql)) {
			return -1;
		}
		if (!$this->id) {
			$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'ka_typ');
		}
		return $this->id;
	}

	public function delete()
	{
		return $this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'ka_typ WHERE rowid = '.((int) $this->id)) ? 1 : -1;
	}

	/** Merkmal nach Label (erstes passendes) */
	public function merkmal($label)
	{
		foreach ((array) ($this->data['merkmale'] ?? array()) as $m) {
			if ($m['label'] === $label) {
				return $m['value'];
			}
		}
		return '';
	}
}
