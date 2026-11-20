<?php
/* Copyright (C) 2026 SmartImmo Holding — GPL v3+ */

/**
 * \file    ideenbox/class/ideenboxnote.class.php
 * \brief   Idee / ToDo aus der App „Ideenbox“ mit Übernahme in Agenda und Projektaufgaben
 */
class IdeenboxNote
{
	const STATUS_NEW = 0;
	const STATUS_ASSIGNED = 1;
	const STATUS_DONE = 2;
	const STATUS_DISCARDED = 9;

	/** @var DoliDB */
	public $db;
	public $error = '';
	public $errors = array();

	public $id;
	public $entity;
	public $uuid;
	public $type = 'idea';
	public $text = '';
	public $date_reminder;
	public $status = 0;
	public $priority = 0;
	public $source;
	public $fk_target_entity;
	public $fk_soc;
	public $fk_project;
	public $fk_actioncomm;
	public $fk_task;
	public $fk_user_author;
	public $fk_user_assign;
	public $date_creation;
	public $date_modif;
	public $date_done;
	/** @var string Ref der zuletzt angelegten Aufgabe */
	public $task_ref = '';

	private static $fields = array('entity', 'uuid', 'type', 'text', 'date_reminder', 'status', 'priority', 'source', 'fk_target_entity', 'fk_soc', 'fk_project', 'fk_actioncomm', 'fk_task', 'fk_user_author', 'fk_user_assign', 'date_creation', 'date_modif', 'date_done');
	private static $dates = array('date_reminder', 'date_creation', 'date_modif', 'date_done');
	private static $ints = array('entity', 'status', 'priority', 'fk_target_entity', 'fk_soc', 'fk_project', 'fk_actioncomm', 'fk_task', 'fk_user_author', 'fk_user_assign');

	public function __construct($db)
	{
		$this->db = $db;
	}

	public static function table($db)
	{
		return $db->prefix().'ideenbox_note';
	}

	/** Kurzbezeichnung (für Agenda/Aufgabe): erster Satz, max. 80 Zeichen */
	public function label()
	{
		$t = trim(preg_replace('/\s+/u', ' ', (string) $this->text));
		if (preg_match('/^(.{10,80}?)[.!?](\s|$)/u', $t, $m)) {
			$t = $m[1];
		}
		return dol_trunc($t, 80);
	}

	private function sqlValue($f)
	{
		$v = $this->$f;
		if (in_array($f, self::$dates, true)) {
			return ($v === null || $v === '' || $v === 0) ? 'NULL' : "'".$this->db->idate($v)."'";
		}
		if (in_array($f, self::$ints, true)) {
			if ($v === null || $v === '' || ($f !== 'status' && $f !== 'priority' && $f !== 'entity' && (int) $v <= 0)) {
				return in_array($f, array('status', 'priority'), true) ? '0' : 'NULL';
			}
			return (string) ((int) $v);
		}
		return ($v === null) ? 'NULL' : "'".$this->db->escape($v)."'";
	}

	/** @return int id or <0 */
	public function create(User $user)
	{
		global $conf;
		$this->type = in_array($this->type, array('idea', 'todo'), true) ? $this->type : 'idea';
		if (trim((string) $this->text) === '') {
			$this->error = 'Text fehlt';
			return -1;
		}
		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}
		$now = dol_now();
		if (empty($this->date_creation)) {
			$this->date_creation = $now;
		}
		$this->date_modif = $now;
		if (empty($this->fk_user_author)) {
			$this->fk_user_author = $user->id;
		}
		if (empty($this->fk_user_assign)) {
			$this->fk_user_assign = $user->id;
		}
		$vals = array();
		foreach (self::$fields as $f) {
			$vals[] = $this->sqlValue($f);
		}
		$sql = "INSERT INTO ".self::table($this->db)." (".implode(', ', self::$fields).") VALUES (".implode(', ', $vals).")";
		$this->db->begin();
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -2;
		}
		$this->id = $this->db->last_insert_id(self::table($this->db));
		$this->db->commit();
		if ($this->date_reminder && getDolGlobalInt('IDEENBOX_AUTO_AGENDA', 1)) {
			$this->syncAgenda($user, true);
		}
		return $this->id;
	}

	public function fetch($id, $uuid = '')
	{
		$sql = "SELECT rowid, ".implode(', ', self::$fields)." FROM ".self::table($this->db)." WHERE entity IN (".getEntity('ideenbox').")";
		if ($id > 0) {
			$sql .= " AND rowid = ".((int) $id);
		} else {
			$sql .= " AND uuid = '".$this->db->escape($uuid)."'";
		}
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($res);
		if (!$obj) {
			return 0;
		}
		$this->setFromObj($obj);
		return 1;
	}

	public function setFromObj($obj)
	{
		$this->id = (int) $obj->rowid;
		foreach (self::$fields as $f) {
			$v = $obj->$f;
			if (in_array($f, self::$dates, true)) {
				$v = $v ? $this->db->jdate($v) : null;
			} elseif (in_array($f, self::$ints, true)) {
				$v = $v === null ? null : (int) $v;
			}
			$this->$f = $v;
		}
		return $this;
	}

	/**
	 * @param  array $filter  keys: status (array|int), type, since (ts), search, unassigned (bool), due (overdue|today|week), user (id)
	 * @return IdeenboxNote[]
	 */
	public static function fetchAll($db, $filter = array(), $limit = 500, $sortfield = 'n.date_creation', $sortorder = 'DESC')
	{
		$sql = "SELECT n.rowid, n.".implode(', n.', self::$fields)." FROM ".self::table($db)." n WHERE n.entity IN (".getEntity('ideenbox').")";
		if (isset($filter['status']) && $filter['status'] !== '' && $filter['status'] !== null) {
			$st = array_map('intval', (array) $filter['status']);
			$sql .= " AND n.status IN (".implode(',', $st).")";
		}
		if (!empty($filter['type']) && in_array($filter['type'], array('idea', 'todo'), true)) {
			$sql .= " AND n.type = '".$db->escape($filter['type'])."'";
		}
		if (!empty($filter['since'])) {
			$sql .= " AND n.date_modif >= '".$db->idate((int) $filter['since'])."'";
		}
		if (!empty($filter['search'])) {
			$sql .= natural_search('n.text', $filter['search']);
		}
		if (!empty($filter['unassigned'])) {
			$sql .= " AND n.fk_soc IS NULL AND n.fk_project IS NULL AND n.fk_target_entity IS NULL";
		}
		if (!empty($filter['user'])) {
			$sql .= " AND (n.fk_user_assign = ".((int) $filter['user'])." OR n.fk_user_author = ".((int) $filter['user']).")";
		}
		if (!empty($filter['due'])) {
			$now = dol_now();
			$eod = dol_mktime(23, 59, 59, (int) dol_print_date($now, '%m'), (int) dol_print_date($now, '%d'), (int) dol_print_date($now, '%Y'));
			if ($filter['due'] === 'overdue') {
				$sql .= " AND n.date_reminder < '".$db->idate($now)."'";
			} elseif ($filter['due'] === 'today') {
				$sql .= " AND n.date_reminder <= '".$db->idate($eod)."'";
			} elseif ($filter['due'] === 'week') {
				$sql .= " AND n.date_reminder <= '".$db->idate($eod + 6 * 86400)."'";
			} elseif ($filter['due'] === 'none') {
				$sql .= " AND n.date_reminder IS NULL";
			}
		}
		$allowedsort = array('n.date_creation', 'n.date_reminder', 'n.type', 'n.status', 'n.text', 'n.rowid');
		if (!in_array($sortfield, $allowedsort, true)) {
			$sortfield = 'n.date_creation';
		}
		$sql .= $db->order($sortfield, strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC');
		if ($limit > 0) {
			$sql .= $db->plimit($limit);
		}
		$out = array();
		$res = $db->query($sql);
		if ($res) {
			while ($obj = $db->fetch_object($res)) {
				$n = new IdeenboxNote($db);
				$out[] = $n->setFromObj($obj);
			}
		} else {
			dol_syslog('IdeenboxNote::fetchAll '.$db->lasterror(), LOG_ERR);
		}
		return $out;
	}

	public function update(User $user, $syncagenda = true)
	{
		$this->type = in_array($this->type, array('idea', 'todo'), true) ? $this->type : 'idea';
		$this->date_modif = dol_now();
		if ((int) $this->status === self::STATUS_DONE && empty($this->date_done)) {
			$this->date_done = dol_now();
		} elseif ((int) $this->status !== self::STATUS_DONE) {
			$this->date_done = null;
		}
		$set = array();
		foreach (self::$fields as $f) {
			if ($f === 'entity' || $f === 'uuid' || $f === 'date_creation' || $f === 'fk_user_author') {
				continue;
			}
			$set[] = $f." = ".$this->sqlValue($f);
		}
		$sql = "UPDATE ".self::table($this->db)." SET ".implode(', ', $set)." WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($syncagenda) {
			$this->syncAgenda($user, false);
		}
		return 1;
	}

	public function delete(User $user)
	{
		$sql = "DELETE FROM ".self::table($this->db)." WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/** Entität des Ziels: Projekt > zugeordnete Firma > Sammelfirma */
	public function targetEntity()
	{
		if ($this->fk_project > 0) {
			$res = $this->db->query("SELECT entity FROM ".$this->db->prefix()."projet WHERE rowid = ".((int) $this->fk_project));
			if ($res && ($o = $this->db->fetch_object($res))) {
				return (int) $o->entity;
			}
		}
		return $this->fk_target_entity > 0 ? (int) $this->fk_target_entity : (int) $this->entity;
	}

	/**
	 * Agenda-Eintrag anlegen bzw. aktualisieren (Termin = Erinnerung, Zuständig, Geschäftspartner, Projekt, erledigt)
	 *
	 * @param bool $create  auch neu anlegen, wenn noch keiner existiert
	 * @return int
	 */
	public function syncAgenda(User $user, $create = true)
	{
		global $langs;
		if (!isModEnabled('agenda')) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$ac = new ActionComm($this->db);
		$exists = ($this->fk_actioncomm > 0 && $ac->fetch($this->fk_actioncomm) > 0);
		if (!$exists && (!$create || empty($this->date_reminder))) {
			return 0;
		}
		$langs->load('ideenbox@ideenbox');
		$prefix = $this->type === 'todo' ? $langs->transnoentitiesnoconv('IbTypeTodo') : $langs->transnoentitiesnoconv('IbTypeIdea');
		$ac->label = $prefix.': '.$this->label();
		$ac->note_private = $this->text;
		$ac->datep = $this->date_reminder ?: ($exists ? $ac->datep : dol_now());
		$ac->datef = $ac->datep;
		$ac->fulldayevent = 0;
		$ac->socid = $this->fk_soc > 0 ? $this->fk_soc : 0;
		$ac->fk_project = $this->fk_project > 0 ? $this->fk_project : 0;
		$owner = $this->fk_user_assign > 0 ? $this->fk_user_assign : $user->id;
		$ac->userownerid = $owner;
		$ac->percentage = ((int) $this->status === self::STATUS_DONE) ? 100 : (((int) $this->status === self::STATUS_DISCARDED) ? -1 : 0);
		if ($exists) {
			$ac->userassigned = array($owner => array('id' => $owner, 'transparency' => 1));
			$r = $ac->update($user, 1);
		} else {
			$ac->type_code = getDolGlobalString('IDEENBOX_AGENDA_TYPE', 'AC_OTH');
			$ac->code = 'AC_IDEENBOX';
			$ac->userassigned = array($owner => array('id' => $owner, 'transparency' => 1));
			$ac->elementtype = '';
			$r = $ac->create($user, 1);
			if ($r > 0) {
				$this->fk_actioncomm = $r;
				$this->db->query("UPDATE ".self::table($this->db)." SET fk_actioncomm = ".((int) $r)." WHERE rowid = ".((int) $this->id));
			}
		}
		if ($r <= 0) {
			$this->error = $ac->error;
			dol_syslog('IdeenboxNote::syncAgenda '.$ac->error, LOG_WARNING);
			return -1;
		}
		// Eintrag in der Firma des Ziels führen (Multicompany)
		$te = $this->targetEntity();
		if ($te > 0) {
			$this->db->query("UPDATE ".$this->db->prefix()."actioncomm SET entity = ".((int) $te)." WHERE id = ".((int) $this->fk_actioncomm));
		}
		return 1;
	}

	/**
	 * Als Aufgabe ins zugeordnete Projekt übernehmen
	 *
	 * @return int  id der Aufgabe oder <0
	 */
	public function convertToTask(User $user)
	{
		global $conf, $langs;
		if (!isModEnabled('project')) {
			$this->error = $langs->trans('IbErrorProjectModule');
			return -1;
		}
		if (!($this->fk_project > 0)) {
			$this->error = $langs->trans('IbErrorNoProject');
			return -2;
		}
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
		$sql = "SELECT rowid, entity, fk_soc FROM ".$this->db->prefix()."projet WHERE rowid = ".((int) $this->fk_project);
		$res = $this->db->query($sql);
		$p = $res ? $this->db->fetch_object($res) : null;
		if (!$p) {
			$this->error = $langs->trans('IbErrorNoProject');
			return -3;
		}
		$task = new Task($this->db);
		if ($this->fk_task > 0 && $task->fetch($this->fk_task) > 0) {
			$this->error = $langs->trans('IbTaskExists', $task->ref);
			return -4;
		}
		$task = new Task($this->db);
		$task->fk_project = (int) $p->rowid;
		$task->entity = (int) $p->entity;
		$task->label = $this->label();
		$task->description = $this->text;
		$task->date_c = dol_now();
		$task->date_start = dol_now();
		$task->date_end = $this->date_reminder ?: null;
		$task->fk_task_parent = 0;
		$task->progress = ((int) $this->status === self::STATUS_DONE) ? 100 : 0;
		$task->ref = $this->nextTaskRef($task, (int) $p->fk_soc);
		$id = $task->create($user);
		if ($id <= 0) {
			$this->error = $task->error ?: implode(', ', $task->errors);
			return -5;
		}
		$assign = $this->fk_user_assign > 0 ? $this->fk_user_assign : $user->id;
		$task->add_contact($assign, 'TASKEXECUTIVE', 'internal');
		$this->fk_task = $id;
		$this->task_ref = $task->ref;
		if ((int) $this->status === self::STATUS_NEW) {
			$this->status = self::STATUS_ASSIGNED;
		}
		$this->update($user, false);
		return $id;
	}

	private function nextTaskRef($task, $socid)
	{
		$model = getDolGlobalString('PROJECT_TASK_ADDON', 'mod_task_simple');
		$file = DOL_DOCUMENT_ROOT.'/core/modules/project/task/'.basename($model).'.php';
		if (is_readable($file)) {
			require_once $file;
			if (class_exists($model)) {
				require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
				$soc = new Societe($this->db);
				if ($socid > 0) {
					$soc->fetch($socid);
				}
				$mod = new $model();
				$ref = $mod->getNextValue($soc, $task);
				if ($ref) {
					return $ref;
				}
			}
		}
		return 'IB'.dol_print_date(dol_now(), '%y%m%d%H%M%S');
	}

	/** Daten für die App / REST */
	public function toArray()
	{
		$d = array(
			'id' => (int) $this->id, 'uuid' => (string) $this->uuid, 'type' => $this->type, 'text' => $this->text,
			'reminder' => $this->date_reminder ? (int) $this->date_reminder : null,
			'status' => (int) $this->status, 'priority' => (int) $this->priority, 'source' => $this->source,
			'created' => $this->date_creation ? (int) $this->date_creation : null,
			'modified' => $this->date_modif ? (int) $this->date_modif : null,
			'done' => $this->date_done ? (int) $this->date_done : null,
			'entity' => (int) $this->entity, 'target_entity' => $this->fk_target_entity ? (int) $this->fk_target_entity : null,
			'socid' => $this->fk_soc ? (int) $this->fk_soc : null, 'projectid' => $this->fk_project ? (int) $this->fk_project : null,
			'actioncommid' => $this->fk_actioncomm ? (int) $this->fk_actioncomm : null, 'taskid' => $this->fk_task ? (int) $this->fk_task : null,
			'assigned_to' => $this->fk_user_assign ? (int) $this->fk_user_assign : null,
		);
		$d['assignment'] = $this->assignmentLabel();
		return $d;
	}

	/** „Firma › Geschäftspartner › Projekt“ als Text */
	public function assignmentLabel()
	{
		$parts = array();
		if ($this->fk_target_entity > 0) {
			$l = ideenboxEntityLabel($this->db, $this->fk_target_entity);
			if ($l !== '') {
				$parts[] = $l;
			}
		}
		if ($this->fk_soc > 0) {
			$res = $this->db->query("SELECT nom FROM ".$this->db->prefix()."societe WHERE rowid = ".((int) $this->fk_soc));
			if ($res && ($o = $this->db->fetch_object($res))) {
				$parts[] = $o->nom;
			}
		}
		if ($this->fk_project > 0) {
			$res = $this->db->query("SELECT ref, title FROM ".$this->db->prefix()."projet WHERE rowid = ".((int) $this->fk_project));
			if ($res && ($o = $this->db->fetch_object($res))) {
				$parts[] = $o->ref.' '.$o->title;
			}
		}
		return implode(' › ', $parts);
	}

	public static function statusLabel($status, $mode = 0)
	{
		global $langs;
		$map = array(0 => array('IbStatusNew', 'status0'), 1 => array('IbStatusAssigned', 'status3'), 2 => array('IbStatusDone', 'status4'), 9 => array('IbStatusDiscarded', 'status9'));
		$m = $map[(int) $status] ?? $map[0];
		return dolGetStatus($langs->trans($m[0]), $langs->trans($m[0]), '', $m[1], $mode);
	}
}

/** Name einer Firma (Entität) – leer ohne Multicompany */
function ideenboxEntityLabel($db, $entityid)
{
	static $cache = array();
	if (!isset($cache[$entityid])) {
		$cache[$entityid] = '';
		$res = $db->query("SELECT label FROM ".$db->prefix()."entity WHERE rowid = ".((int) $entityid));
		if ($res && ($o = $db->fetch_object($res))) {
			$cache[$entityid] = $o->label;
		}
	}
	return $cache[$entityid];
}
