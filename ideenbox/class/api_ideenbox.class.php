<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */

use Luracast\Restler\RestException;

dol_include_once('/ideenbox/class/ideenboxnote.class.php');

/**
 * Schnittstelle für die App „Ideenbox“: Ideen und ToDos erfassen und abgleichen.
 * Aufruf mit Kopfzeile DOLAPIKEY (API-Schlüssel des Benutzers).
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Ideenbox extends DolibarrApi
{
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	private function check($right = 'read')
	{
		if (!empty(DolibarrApiAccess::$user->socid) || !DolibarrApiAccess::$user->hasRight('ideenbox', $right)) {
			throw new RestException(403, 'Missing right ideenbox '.$right);
		}
	}

	/**
	 * Verbindung prüfen
	 *
	 * @return array
	 *
	 * @url GET ping
	 */
	public function ping()
	{
		global $conf, $mysoc;
		$this->check('read');
		$u = DolibarrApiAccess::$user;
		return array('ok' => true, 'now' => dol_now(), 'entity' => (int) $conf->entity, 'company' => $mysoc->name,
			'user' => trim($u->firstname.' '.$u->lastname) ?: $u->login, 'can_write' => (bool) $u->hasRight('ideenbox', 'write'), 'module_version' => '1.0.0');
	}

	/**
	 * Ideen/ToDos lesen (für den Abgleich: geändert seit „since“)
	 *
	 * @param int    $since  Unix-Zeit; nur seitdem geänderte Einträge
	 * @param string $status Komma-Liste, z. B. "0,1" (offen)
	 * @param string $type   idea | todo
	 * @param int    $limit  max. Anzahl
	 * @param int    $mine   1 = nur eigene / mir zugewiesene
	 * @return array
	 *
	 * @url GET notes
	 */
	public function index($since = 0, $status = '', $type = '', $limit = 500, $mine = 1)
	{
		$this->check('read');
		$filter = array('since' => (int) $since, 'type' => $type);
		if ($status !== '') {
			$filter['status'] = array_map('intval', explode(',', $status));
		}
		if ($mine || !DolibarrApiAccess::$user->hasRight('ideenbox', 'all')) {
			$filter['user'] = DolibarrApiAccess::$user->id;
		}
		$now = dol_now();
		$out = array();
		foreach (IdeenboxNote::fetchAll($this->db, $filter, min(2000, max(1, (int) $limit)), 'n.date_modif', 'ASC') as $n) {
			$out[] = $n->toArray();
		}
		return array('now' => $now, 'notes' => $out);
	}

	/**
	 * Einen Eintrag lesen
	 *
	 * @param int $id
	 * @return array
	 *
	 * @url GET notes/{id}
	 */
	public function get($id)
	{
		$this->check('read');
		$n = new IdeenboxNote($this->db);
		if ($n->fetch((int) $id) <= 0) {
			throw new RestException(404, 'Not found');
		}
		return $n->toArray();
	}

	/**
	 * Idee/ToDo anlegen. Gleiche uuid nochmals gesendet → vorhandener Eintrag wird aktualisiert (kein Duplikat).
	 *
	 * @param array $request_data  uuid, type (idea|todo), text, reminder (Unix-Zeit), status, priority, source (voice|keyboard), created (Unix-Zeit)
	 * @return array
	 *
	 * @url POST notes
	 */
	public function post($request_data = null)
	{
		$this->check('write');
		$d = (array) $request_data;
		$n = new IdeenboxNote($this->db);
		$uuid = isset($d['uuid']) ? substr(preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $d['uuid']), 0, 64) : '';
		if ($uuid !== '' && $n->fetch(0, $uuid) > 0) {
			return $this->apply($n, $d)->toArray();
		}
		$n = new IdeenboxNote($this->db);
		$n->uuid = $uuid !== '' ? $uuid : null;
		$this->fill($n, $d);
		if (!empty($d['created']) && (int) $d['created'] > 0) {
			$n->date_creation = (int) $d['created'];
		}
		$n->source = isset($d['source']) ? substr(preg_replace('/[^a-z]/', '', (string) $d['source']), 0, 16) : 'app';
		if ($n->create(DolibarrApiAccess::$user) <= 0) {
			throw new RestException(500, 'Error creating note: '.$n->error);
		}
		$n->fetch($n->id);
		return $n->toArray();
	}

	/**
	 * Eintrag ändern (Text, Termin, Status)
	 *
	 * @param int   $id
	 * @param array $request_data
	 * @return array
	 *
	 * @url PUT notes/{id}
	 */
	public function put($id, $request_data = null)
	{
		$this->check('write');
		$n = new IdeenboxNote($this->db);
		if ($n->fetch((int) $id) <= 0) {
			throw new RestException(404, 'Not found');
		}
		return $this->apply($n, (array) $request_data)->toArray();
	}

	private function fill(IdeenboxNote $n, array $d)
	{
		if (isset($d['type'])) {
			$n->type = $d['type'] === 'todo' ? 'todo' : 'idea';
		}
		if (isset($d['text'])) {
			$n->text = trim((string) $d['text']);
		}
		if (array_key_exists('reminder', $d)) {
			$n->date_reminder = ((int) $d['reminder'] > 0) ? (int) $d['reminder'] : null;
		}
		if (isset($d['status']) && in_array((int) $d['status'], array(0, 1, 2, 9), true)) {
			$n->status = (int) $d['status'];
		}
		if (isset($d['priority'])) {
			$n->priority = (int) $d['priority'] ? 1 : 0;
		}
	}

	private function apply(IdeenboxNote $n, array $d)
	{
		$hadReminder = !empty($n->date_reminder);
		$this->fill($n, $d);
		if ($n->update(DolibarrApiAccess::$user) <= 0) {
			throw new RestException(500, 'Error updating note: '.$n->error);
		}
		if (!$hadReminder && $n->date_reminder && getDolGlobalInt('IDEENBOX_AUTO_AGENDA', 1)) {
			$n->syncAgenda(DolibarrApiAccess::$user, true);
		}
		$n->fetch($n->id);
		return $n;
	}
}
