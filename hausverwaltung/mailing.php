<?php
/* Copyright (C) 2026 Green Villa and Apartment Resorts d.o.o. — GPL v3+ */
/** Rundschreiben an Eigentümer: Text, Übersetzungen, Anhänge je Sprache, Empfänger, Versand */
// Teilschritte des Versands (AJAX): Token nicht erneuern
if (isset($_POST['action']) && in_array($_POST['action'], array('mljobs', 'mlstep'), true)) {
	define('NOTOKENRENEWAL', 1);
	define('NOREQUIREMENU', 1);
	define('NOREQUIREHTML', 1);
}
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/hausverwaltung/lib/hausverwaltung.lib.php');
dol_include_once('/hausverwaltung/class/hvmailing.class.php');

$langs->loadLangs(array('hausverwaltung@hausverwaltung', 'mails', 'languages'));
if (!$user->hasRight('hausverwaltung', 'invoice') || !empty($user->socid)) {
	// Externe Benutzer (Eigentümer-Zugang) sehen die Hausverwaltung nie
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$form = new Form($db);
$ml = new HvMailing($db);
$self = $_SERVER['PHP_SELF'];
$back = function ($anchor = '') use ($self, &$ml) {
	header('Location: '.$self.'?id='.((int) $ml->id).($anchor ? '#'.$anchor : ''));
	exit;
};
$isLang = function ($l) {
	return $l === 'all' || in_array($l, hvDocLangs(), true);
};

if ($action === 'create') {
	if ($ml->create($user, hvSrcLang()) > 0) {
		$back();
	}
	setEventMessages($ml->error, null, 'errors');
}
if ($id > 0 && $ml->fetch($id) <= 0) {
	accessforbidden('Not found');
}
$runMode = '';
$runTo = '';
if ($ml->id) {
	// Versand/Übersetzung in Teilschritten: Plan des Laufs und einzelne Schritte (JSON)
	if ($action === 'mljobs' || $action === 'mlstep') {
		// Sitzung freigeben: sonst blockiert ein langer Schritt alle anderen Seiten dieses Benutzers
		session_write_close();
		top_httphead('application/json');
		if ($action === 'mljobs') {
			$r = $ml->startRun((string) GETPOST('mode', 'aZ09'), trim((string) GETPOST('testto', 'alphanohtml')));
			if (empty($r['error'])) {
				$units = HvMailing::units($db);
				$r['labels'] = array();
				foreach ($r['jobs'] as $j) {
					$lb = '';
					switch ($j['t']) {
						case 'text':
							$lb = $langs->transnoentities('HvMlStepText', $j['lang']);
							break;
						case 'doc':
							$lb = $langs->transnoentities('HvMlStepDoc', $ml->docs[$j['doc']]['title'] ?? $j['doc'], $j['lang']);
							break;
						case 'test':
							$lb = $langs->transnoentities('HvMlStepTest', $j['lang']);
							break;
						case 'send':
							$lb = $langs->transnoentities('HvMlStepSend', isset($units[$j['sid']]) ? $units[$j['sid']]['unit']->ref.' '.$units[$j['sid']]['socname'] : '#'.$j['sid']);
							break;
						default:
							$lb = $langs->transnoentities('HvMlStepFinish');
					}
					$r['labels'][] = $lb;
				}
			}
			print json_encode($r, JSON_UNESCAPED_UNICODE);
		} else {
			$job = json_decode((string) GETPOST('job', 'restricthtml'), true);
			print json_encode(is_array($job) ? $ml->step($user, (string) GETPOST('run', 'alphanohtml'), $job) : array('error' => 'job'), JSON_UNESCAPED_UNICODE);
		}
		$db->close();
		exit;
	}
	if ($action === 'runresult') {
		$st = $ml->runState((string) GETPOST('run', 'alphanohtml'));
		if ($st) {
			$sum = $ml->runSummary($st);
			$msg = $st['mode'] === 'test' ? $langs->trans('HvMlTestSent', $sum['ok'], $st['testto']) : ($st['mode'] === 'send' ? $langs->trans('HvMlSent', $sum['ok'], $sum['ok'] + $sum['err']) : $langs->trans('HvMlDocsTranslated', count($st['jobs']) - 1 - count($sum['errors'])));
			setEventMessages($msg, $sum['errors'] ?: null, $sum['errors'] ? 'warnings' : 'mesgs');
		}
		$back($st && $st['mode'] === 'prep' ? 'docs' : 'send');
	}
	if ($action === 'savetext') {
		$oldSubject = $ml->subject;
		$oldBody = $ml->body;
		$ml->srclang = hvSrcLang();
		$ml->subject = trim((string) GETPOST('subject', 'restricthtml'));
		$ml->body = trim(strip_tags((string) GETPOST('body', 'restricthtml')));
		dolibarr_set_const($db, 'HAUSVERWALTUNG_MAILING_GREETING', GETPOSTINT('greeting') ? '1' : '0', 'chaine', 0, '', $conf->entity);
		$msg = null;
		$changed = $ml->subject !== $oldSubject || $ml->body !== $oldBody;
		if ($changed && GETPOST('hv_translate', 'int') === '1' && HvTranslator::enabled()) {
			if ($ml->translateText($ml->recipientLangs()) < 0) {
				$msg = array($langs->trans('HvAiFailed', $ml->error));
			}
		}
		$ml->update(true);
		setEventMessages($langs->trans('HvSaved'), $msg, $msg ? 'warnings' : 'mesgs');
		$back('text');
	}
	if ($action === 'translate') {
		$r = $ml->translateText($ml->recipientLangs());
		$ml->update(true);
		setEventMessages($r < 0 ? $langs->trans('HvAiFailed', $ml->error) : $langs->trans('HvMlTranslated', $r), null, $r < 0 ? 'errors' : 'mesgs');
		$back('trans');
	}
	if ($action === 'savetr') {
		foreach ((array) GETPOST('tr', 'array') as $l => $v) {
			if (!$isLang($l) || $l === 'all') {
				continue;
			}
			$sub = trim(strip_tags((string) ($v['subject'] ?? '')));
			$bod = trim(strip_tags((string) ($v['body'] ?? '')));
			$was = $ml->i18n[$l] ?? array();
			if ($bod === '') {
				unset($ml->i18n[$l]);
			} else {
				$ml->i18n[$l] = array('subject' => $sub, 'body' => $bod, 'ai' => (($was['body'] ?? '') === $bod && ($was['subject'] ?? '') === $sub) ? (int) ($was['ai'] ?? 0) : 0);
			}
		}
		$ml->update(true);
		setEventMessages($langs->trans('HvSaved'), null);
		$back('trans');
	}
	if ($action === 'upload') {
		$l = (string) GETPOST('lang', 'alpha');
		if ($isLang($l) && !empty($_FILES['file']['name'])) {
			if ($ml->addUpload((string) GETPOST('doc', 'aZ09'), $l, $_FILES['file']) > 0) {
				$ml->update(true);
				setEventMessages($langs->trans('HvSaved'), null);
			} else {
				setEventMessages($ml->error, null, 'errors');
			}
		}
		$back('docs');
	}
	if ($action === 'fromdol') {
		$l = (string) GETPOST('lang', 'alpha');
		if ($isLang($l) && $ml->addFromDolibarr((string) GETPOST('doc', 'aZ09'), $l, (string) GETPOST('ecmfile', 'restricthtml')) > 0) {
			$ml->update(true);
			setEventMessages($langs->trans('HvSaved'), null);
		} else {
			setEventMessages($ml->error ?: 'Error', null, 'errors');
		}
		$back('docs');
	}
	if ($action === 'rmver') {
		$ml->removeVersion((string) GETPOST('doc', 'aZ09'), (string) GETPOST('lang', 'alpha'), GETPOSTINT('ai') ? true : false);
		$ml->update(true);
		$back('docs');
	}
	if ($action === 'addgen') {
		$r = $ml->addGenDoc((string) GETPOST('gen', 'aZ09'), (string) GETPOST('gen_'.GETPOST('gen', 'aZ09'), 'alphanohtml'));
		if ($r > 0) {
			$ml->update(true);
			setEventMessages($langs->trans('HvSaved'), null);
		} elseif ($r < 0) {
			setEventMessages($langs->trans('ErrorBadValueForParameter', $ml->error, 'gen'), null, 'errors');
		}
		$back('docs');
	}
	if ($action === 'saveopts') {
		$ml->ownlang_only = GETPOSTINT('ownlang_only') ? 1 : 0;
		$ml->update(true);
		setEventMessages($langs->trans('HvSaved'), null);
		$back('docs');
	}
	if ($action === 'mergedoc') {
		if ($ml->mergeDoc((string) GETPOST('doc', 'aZ09'), (string) GETPOST('into', 'aZ09')) > 0) {
			$ml->update(true);
			setEventMessages($langs->trans('HvMlMerged'), null);
		}
		$back('docs');
	}
	if ($action === 'rmdoc') {
		$ml->removeDoc((string) GETPOST('doc', 'aZ09'));
		$ml->update(true);
		$back('docs');
	}
	if ($action === 'aidocs') {
		$ml->update(true);
		$runMode = 'prep';
	}
	if ($action === 'saverec') {
		$ml->recipients = array_map('intval', (array) GETPOST('rec', 'array'));
		$ml->update(true);
		setEventMessages($langs->trans('HvSaved'), null);
		$back('rec');
	}
	if ($action === 'sendtest') {
		$to = trim((string) GETPOST('testto', 'alphanohtml'));
		if (!isValidEmail($to)) {
			setEventMessages($langs->trans('HvMailTestAddressInvalid'), null, 'errors');
			$back('send');
		}
		$runMode = 'test';
		$runTo = $to;
	}
	if ($action === 'confirm_send' && GETPOST('confirm') === 'yes') {
		if (!$ml->date_test) {
			setEventMessages($langs->trans('HvMlTestFirst'), null, 'errors');
			$back('send');
		}
		$runMode = 'send';
	}
	if ($action === 'confirm_delete' && GETPOST('confirm') === 'yes') {
		$ml->delete();
		header('Location: '.$self);
		exit;
	}
}

llxHeader('', $langs->trans('HvMailing'), hvHelpUrl('rundschreiben/'), '', 0, 0, '', '', '', 'mod-hausverwaltung');
print hvOutdatedBanner();

// ---------- Liste ----------
if (!$ml->id) {
	$btn = '<form method="POST" action="'.$self.'" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="create"><button class="butAction">'.img_picto('', 'add', 'class="pictofixedwidth"').$langs->trans('HvMlNew').'</button></form>';
	print load_fiche_titre($langs->trans('HvMailing'), $btn, 'email');
	print '<div class="opacitymedium">'.$langs->trans('HvMlHelp').'</div><br>';
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('MailTopic').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('DateCreation').'</th><th>'.$langs->trans('HvMlDateSent').'</th><th class="right">'.$langs->trans('HvMlRecipients').'</th></tr>';
	$all = HvMailing::listAll($db);
	if (!$all) {
		print '<tr><td colspan="6" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
	}
	foreach ($all as $o) {
		print '<tr class="oddeven"><td><a href="'.$self.'?id='.$o->rowid.'">#'.$o->rowid.'</a></td><td><a href="'.$self.'?id='.$o->rowid.'">'.dol_escape_htmltag($o->subject ?: '('.$langs->trans('HvMlNoSubject').')').'</a></td>';
		print '<td>'.($o->status === 'sent' ? '<span class="badge badge-status4">'.$langs->trans('HvMlStatusSent').'</span>' : '<span class="badge badge-status0">'.$langs->trans('Draft').'</span>').'</td>';
		print '<td>'.dol_print_date($db->jdate($o->datec), 'dayhour').'</td><td>'.dol_print_date($db->jdate($o->date_sent), 'dayhour').'</td><td class="right">'.((int) $o->nsent).' / '.count((array) json_decode((string) $o->recipients, true)).'</td></tr>';
	}
	print '</table>';
	llxFooter();
	$db->close();
	exit;
}

// ---------- Bearbeiten ----------
$units = HvMailing::units($db);
$rLangs = $ml->recipientLangs();
$ai = HvTranslator::enabled();
$fileLink = function ($f) use ($ml) {
	return '<a href="'.dol_buildpath('/hausverwaltung/file.php', 1).'?file='.urlencode('mailing/'.$ml->id.'/'.$f).'" target="_blank">'.img_picto('', 'pdf').' '.dol_escape_htmltag($f).'</a>';
};
$head = '<a class="butAction" href="'.$self.'">'.$langs->trans('BackToList').'</a><a class="butActionDelete" href="'.$self.'?id='.$ml->id.'&action=delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
print load_fiche_titre($langs->trans('HvMailing').' #'.$ml->id.' – '.($ml->status === 'sent' ? $langs->trans('HvMlStatusSent').' '.dol_print_date($ml->date_sent, 'dayhour') : $langs->trans('Draft')), $head, 'email');
if ($action === 'delete') {
	print $form->formconfirm($self.'?id='.$ml->id, $langs->trans('Delete'), $langs->trans('ConfirmDelete'), 'confirm_delete', '', 0, 1);
}
if ($action === 'send') {
	print $form->formconfirm($self.'?id='.$ml->id, $langs->trans('HvMlSendBtn'), $langs->trans('HvMlSendConfirm', count($ml->recipients)), 'confirm_send', '', 0, 1);
}

// Laufender Versand/Übersetzung: Schritte nacheinander abarbeiten (vermeidet Gateway Timeout)
if ($runMode !== '') {
	$titles = array('test' => 'HvMlTestBtn', 'send' => 'HvMlSendBtn', 'prep' => 'HvMlDocsAi');
	print '<div id="hvrun" class="info" style="margin:10px 0" data-mode="'.$runMode.'" data-testto="'.dol_escape_htmltag($runTo).'" data-token="'.newToken().'" data-url="'.dol_escape_htmltag($self.'?id='.$ml->id).'">';
	print '<strong>'.$langs->trans($titles[$runMode]).'</strong> – '.$langs->trans('HvMlRunHelp').'<div class="hvbar" style="height:8px;background:#ddd;margin:8px 0;border-radius:4px"><div style="height:8px;width:0;background:#2e5a3f;border-radius:4px"></div></div><ol class="hvsteps small" style="margin:0"></ol><div class="hvrunmsg"></div></div>';
	$jsT = array_map(function ($k) use ($langs) {
		return dol_escape_js($langs->transnoentities($k));
	}, array('wait' => 'HvMlRunWait', 'retry' => 'HvMlRunRetry', 'fail' => 'HvMlRunFail', 'done' => 'HvMlRunDone'));
	print '<script>
jQuery(function ($) {
	var box = $("#hvrun"), url = box.data("url"), token = box.data("token"), list = box.find(".hvsteps"), bar = box.find(".hvbar div");
	var post = function (d) { d.token = token; return $.ajax({ url: url, method: "POST", data: d, dataType: "json" }); };
	var fail = function (m) { box.removeClass("info").addClass("error"); box.find(".hvrunmsg").text("'.$jsT['fail'].' " + (m || "")); };
	window.onbeforeunload = function () { return "…"; };
	post({ action: "mljobs", mode: box.data("mode"), testto: box.data("testto") }).done(function (r) {
		if (!r || r.error) { window.onbeforeunload = null; return fail(r ? r.error : ""); }
		$.each(r.labels, function (i, l) { list.append($("<li>").text(l)); });
		var tries = 0;
		var run = function (i) {
			if (i >= r.jobs.length) {
				window.onbeforeunload = null;
				box.find(".hvrunmsg").text("'.$jsT['done'].'");
				window.location.href = url + "&action=runresult&run=" + encodeURIComponent(r.run);
				return;
			}
			var li = list.children().eq(i);
			li.find(".st").remove(); li.append($("<span class=\"st opacitymedium\">").text(" …"));
			post({ action: "mlstep", run: r.run, job: JSON.stringify(r.jobs[i]) }).done(function (x) {
				if (x && x.wait) { li.find(".st").text(" '.$jsT['wait'].'"); setTimeout(function () { run(i); }, 10000); return; }
				if (!x || x.error) { window.onbeforeunload = null; return fail(x ? x.error : ""); }
				tries = 0;
				li.find(".st").remove();
				li.append($("<span>").attr("class", x.ok ? "ok" : "error").text(" " + (x.ok ? "✔" : "✘") + (x.msg ? " " + x.msg : "")));
				bar.css("width", Math.round(100 * (i + 1) / r.jobs.length) + "%");
				run(i + 1);
			}).fail(function () {
				// Zeitüberschreitung des Webservers: der Schritt läuft auf dem Server weiter -> später nachfragen
				if (++tries > 60) { window.onbeforeunload = null; return fail(""); }
				li.find(".st").text(" '.$jsT['retry'].'");
				setTimeout(function () { run(i); }, 15000);
			});
		};
		run(0);
	}).fail(function () { window.onbeforeunload = null; fail(""); });
});
</script>';
}

// 1. Text
print '<a id="text"></a>'.load_fiche_titre('1. '.$langs->trans('HvMlText'), '', '');
print '<form method="POST" action="'.$self.'?id='.$ml->id.'" class="hv-ask-translate hvdirty" data-hastr="'.($ml->i18n ? '1' : '0').'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savetext">';
print '<table class="border centpercent tableforfieldedit">';
print '<tr><td class="titlefield">'.$langs->trans('HvAiWrittenIn').'</td><td>'.hvSrcLangSelect('hv_srclang', $ml->srclang).'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('MailTopic').'</td><td><input name="subject" class="minwidth500 widthcentpercentminusx" data-hvsrc="1" data-orig="'.dol_escape_htmltag($ml->subject).'" value="'.dol_escape_htmltag($ml->subject).'"></td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('MailText').'</td><td><textarea name="body" rows="12" class="centpercent" data-hvsrc="1" data-orig="'.dol_escape_htmltag($ml->body).'">'.dol_escape_htmltag($ml->body).'</textarea>';
print '<br><label><input type="checkbox" name="greeting" value="1"'.(getDolGlobalString('HAUSVERWALTUNG_MAILING_GREETING', '1') === '1' ? ' checked' : '').'> '.$langs->trans('HvMlGreeting').'</label></td></tr>';
print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
print hvTranslateAskJs();

// 2. Übersetzungen
print '<a id="trans"></a>'.load_fiche_titre('2. '.$langs->trans('HvMlTranslations'), $ai ? '<form method="POST" action="'.$self.'?id='.$ml->id.'" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="translate"><button class="butAction">'.img_picto('', 'language', 'class="pictofixedwidth"').$langs->trans('HvMlTranslateNow').'</button></form>' : '', '');
$others = array_values(array_diff($rLangs, array($ml->srclang)));
if (!$others) {
	print '<div class="opacitymedium">'.$langs->trans('HvMlNoOtherLangs').'</div>';
} else {
	print '<div class="opacitymedium">'.$langs->trans('HvMlTranslationsHelp').'</div>';
	print '<form method="POST" action="'.$self.'?id='.$ml->id.'" class="hvdirty"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savetr">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><th class="width100">'.$langs->trans('Language').'</th><th>'.$langs->trans('MailTopic').' / '.$langs->trans('MailText').'</th></tr>';
	foreach ($others as $l) {
		$t = $ml->i18n[$l] ?? array();
		$badge = empty($t['body']) ? '<span class="badge badge-status1">'.$langs->trans('HvMlMissing').'</span>' : (!empty($t['ai']) ? '<span class="badge badge-status4">KI</span>' : '<span class="badge badge-status6">'.$langs->trans('HvMlEdited').'</span>');
		print '<tr class="oddeven"><td><strong translate="no">'.$l.'</strong><br>'.$badge.'</td><td><input name="tr['.$l.'][subject]" class="centpercent" value="'.dol_escape_htmltag($t['subject'] ?? '').'"><textarea name="tr['.$l.'][body]" rows="6" class="centpercent">'.dol_escape_htmltag($t['body'] ?? '').'</textarea></td></tr>';
	}
	print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
}

// 3. Anhänge
print '<a id="docs"></a>'.load_fiche_titre('3. '.$langs->trans('HvMlDocs'), $ai && $ml->docs ? '<form method="POST" action="'.$self.'?id='.$ml->id.'" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="aidocs"><button class="butAction">'.img_picto('', 'language', 'class="pictofixedwidth"').$langs->trans('HvMlDocsAi').'</button></form>' : '', '');
print '<div class="opacitymedium">'.$langs->trans('HvMlDocsHelp').'</div>';
print '<form method="POST" action="'.$self.'?id='.$ml->id.'#docs" class="paddingtop paddingbottom"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="saveopts">';
print '<label><input type="checkbox" name="ownlang_only" value="1"'.($ml->ownlang_only ? ' checked' : '').' onchange="this.form.submit()"> <strong>'.$langs->trans('HvMlOwnLangOnly').'</strong></label> <span class="opacitymedium small">'.$langs->trans('HvMlOwnLangOnlyHelp').'</span></form>';
$langOpts = function ($withAll = true) {
	global $langs;
	$h = $withAll ? '<option value="all">'.$langs->trans('HvMlAllLangs').'</option>' : '';
	foreach (hvDocLangs() as $l) {
		$h .= '<option value="'.$l.'">'.$l.'</option>';
	}
	return $h;
};
$docOpts = '<option value="">'.$langs->trans('HvMlNewDoc').'</option>';
foreach ($ml->docs as $d) {
	if (!empty($d['gen'])) {
		continue;
	}
	$docOpts .= '<option value="'.$d['id'].'">'.dol_escape_htmltag($d['title']).'</option>';
}
print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('HvMlDoc').'</th><th>'.$langs->trans('HvMlVersions').'</th></tr>';
if (!$ml->docs) {
	print '<tr><td colspan="2" class="opacitymedium">'.$langs->trans('None').'</td></tr>';
}
foreach ($ml->docs as $d) {
	if (!empty($d['gen'])) {
		print '<tr class="oddeven"><td>'.img_picto('', 'pdf', 'class="pictofixedwidth"').'<strong>'.dol_escape_htmltag(HvMailing::genTitle($d, $langs)).'</strong> <span class="badge badge-status1">'.$langs->trans('HvMlGenBadge').'</span></td>';
		print '<td class="small"><span class="opacitymedium">'.$langs->trans('HvMlGenInfo').'</span> <a href="'.$self.'?id='.$ml->id.'&action=rmdoc&token='.newToken().'&doc='.$d['id'].'">'.img_delete().'</a></td></tr>';
		continue;
	}
	print '<tr class="oddeven"><td><strong>'.dol_escape_htmltag($d['title']).'</strong>';
	// weitere Sprachfassung direkt zu diesem Dokument hochladen
	print '<form method="POST" enctype="multipart/form-data" action="'.$self.'?id='.$ml->id.'" class="small paddingtop"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="upload"><input type="hidden" name="doc" value="'.$d['id'].'">';
	print $langs->trans('HvMlAddVersion').': <input type="file" name="file" class="small" style="max-width:190px"> <select name="lang" translate="no" class="flat small">'.$langOpts(false).'</select> <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Upload').'"></form>';
	$others = array();
	foreach ($ml->docs as $o) {
		if ($o['id'] !== $d['id'] && empty($o['gen'])) {
			$others[] = '<option value="'.$o['id'].'">'.dol_escape_htmltag($o['title']).'</option>';
		}
	}
	if ($others) {
		print '<form method="POST" action="'.$self.'?id='.$ml->id.'" class="small"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="mergedoc"><input type="hidden" name="doc" value="'.$d['id'].'">';
		print $langs->trans('HvMlMergeInto').': <select name="into" class="flat small">'.implode('', $others).'</select> <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('HvMlMergeBtn').'"></form>';
	}
	print '</td><td>';
	foreach (array('versions' => 0, 'ai' => 1) as $k => $isAi) {
		foreach ((array) $d[$k] as $l => $f) {
			print '<div>'.($l === 'all' ? $langs->trans('HvMlAllLangs') : '<span translate="no">'.$l.'</span>').($isAi ? ' <span class="badge badge-status4">KI</span>' : '').': '.$fileLink($f).' <a href="'.$self.'?id='.$ml->id.'&action=rmver&token='.newToken().'&doc='.$d['id'].'&lang='.$l.'&ai='.$isAi.'">'.img_delete().'</a></div>';
		}
	}
	print '</td></tr>';
}
print '</table>';
if ($ml->looksSplit()) {
	print '<div class="warning">'.img_warning('', '', 'pictofixedwidth').$langs->trans('HvMlLooksSplit').'</div>';
}
// Dokumente der Hausverwaltung – werden beim Versand je Apartment und Sprache neu erzeugt
$periods = HvMailing::invoicePeriods($db);
$yNow = (int) dol_print_date(dol_now(), '%Y');
print '<form method="POST" action="'.$self.'?id='.$ml->id.'" class="paddingtop"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="addgen">';
print $langs->trans('HvMlGenAdd').': <select name="gen" class="flat" id="hvgen"><option value="plan">'.$langs->trans('HvMlGenPlanOpt').'</option><option value="statement">'.$langs->trans('HvMlGenStatementOpt').'</option><option value="invoice"'.($periods ? '' : ' disabled').'>'.$langs->trans('HvMlGenInvoiceOpt').'</option></select> ';
print '<span class="hvgenp" data-k="statement">'.$langs->trans('Year').' <select name="gen_statement" class="flat">';
for ($y = $yNow; $y >= $yNow - 5; $y--) {
	print '<option value="'.$y.'"'.($y === $yNow - 1 ? ' selected' : '').'>'.$y.'</option>';
}
print '</select></span><span class="hvgenp" data-k="invoice">'.$langs->trans('HvPeriod').' <select name="gen_invoice" class="flat">';
foreach ($periods as $p) {
	print '<option value="'.dol_escape_htmltag($p).'">'.dol_escape_htmltag($p).'</option>';
}
print '</select></span> <input type="submit" class="button small" value="'.$langs->trans('Add').'"></form>';
print '<script>jQuery(function($){var u=function(){var k=$("#hvgen").val();$(".hvgenp").each(function(){$(this).toggle($(this).data("k")===k);});};$("#hvgen").on("change",u);u();});</script>';
print '<form method="POST" enctype="multipart/form-data" action="'.$self.'?id='.$ml->id.'" class="paddingtop"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="upload">';
print $langs->trans('HvMlUploadNew').': <input type="file" name="file"> '.$langs->trans('Language').' <select name="lang" translate="no" class="flat">'.$langOpts().'</select> '.$langs->trans('HvMlDoc').' <select name="doc" class="flat">'.$docOpts.'</select> <input type="submit" class="button small" value="'.$langs->trans('Upload').'"></form>';
$ecm = HvMailing::ecmFiles();
if (HvMailing::ecmDir() === '') {
	print '<div class="opacitymedium small paddingtop">'.$langs->trans('HvMlNoEcm').'</div>';
} else {
	print '<form method="POST" action="'.$self.'?id='.$ml->id.'" class="paddingtop"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="fromdol">';
	print $langs->trans('HvMlFromDolibarr').': <select name="ecmfile" class="flat minwidth300 maxwidth500">';
	foreach ($ecm as $f) {
		print '<option value="'.dol_escape_htmltag($f).'">'.dol_escape_htmltag($f).'</option>';
	}
	print '</select> '.$langs->trans('Language').' <select name="lang" translate="no" class="flat">'.$langOpts().'</select> '.$langs->trans('HvMlDoc').' <select name="doc" class="flat">'.$docOpts.'</select> <input type="submit" class="button small" value="'.$langs->trans('Add').'"'.($ecm ? '' : ' disabled').'></form>';
}
if ($ml->docs && $rLangs) {
	print '<br><table class="noborder"><tr class="liste_titre"><th>'.$langs->trans('Language').'</th><th>'.$langs->trans('HvMlAttachedFor').'</th></tr>';
	foreach ($rLangs as $l) {
		$names = array_map('basename', $ml->filesFor($l));
		foreach ($ml->docs as $d) {
			if (!empty($d['gen'])) {
				$names[] = HvMailing::genTitle($d, $langs);
			}
		}
		print '<tr class="oddeven"><td translate="no">'.$l.'</td><td class="small">'.dol_escape_htmltag(implode(', ', $names) ?: '–').'</td></tr>';
	}
	print '</table>';
}

// 4. Empfänger
print '<a id="rec"></a>'.load_fiche_titre('4. '.$langs->trans('HvMlRecipients'), '', '');
print '<form method="POST" action="'.$self.'?id='.$ml->id.'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="saverec">';
$bset = array();
foreach ($units as $u) {
	$bset[$u['building']] = true;
}
print '<div class="paddingbottom"><a href="#" class="hvsel" data-b="*">'.$langs->trans('All').'</a> · <a href="#" class="hvsel" data-b="">'.$langs->trans('None').'</a>';
foreach (array_keys($bset) as $b) {
	print ' · <a href="#" class="hvsel" data-b="'.dol_escape_htmltag($b).'">'.dol_escape_htmltag($b).'</a>';
}
print '</div><table class="noborder centpercent"><tr class="liste_titre"><th></th><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvBuilding').'</th><th>'.$langs->trans('HvIsOwner').'</th><th>'.$langs->trans('Language').'</th></tr>';
foreach ($units as $sid => $u) {
	$ok = (bool) $u['rcpt'];
	print '<tr class="oddeven"><td class="center"><input type="checkbox" name="rec[]" value="'.$sid.'" data-b="'.dol_escape_htmltag($u['building']).'"'.(in_array($sid, $ml->recipients, true) && $ok ? ' checked' : '').($ok ? '' : ' disabled').'></td>';
	print '<td>'.dol_escape_htmltag($u['unit']->ref).' <span class="opacitymedium small">'.dol_escape_htmltag($u['socname']).'</span></td><td>'.dol_escape_htmltag($u['building']).'</td>';
	print '<td class="small">'.($ok ? dol_escape_htmltag(implode(', ', array_map(function ($r) {
		return $r['name'].' <'.$r['email'].'>';
	}, $u['rcpt']))) : '<span class="error">'.$langs->trans('HvMailNoAddress').'</span>').'</td><td translate="no">'.dol_escape_htmltag($u['lang']).'</td></tr>';
}
print '</table><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div></form>';
print '<script>jQuery(function($){$(".hvsel").on("click",function(e){e.preventDefault();var b=$(this).data("b");$("input[name=\'rec[]\']:not(:disabled)").each(function(){$(this).prop("checked", b==="*" ? true : (b==="" ? false : ($(this).data("b")===b)));});});});</script>';

// 5. Versand
print '<a id="send"></a>'.load_fiche_titre('5. '.$langs->trans('HvMlSend'), '', '');
print '<div class="opacitymedium">'.$langs->trans('HvMlSendHelp').'</div>';
$tested = (bool) $ml->date_test;
if ($ml->sentSocids()) {
	print '<div class="opacitymedium small">'.$langs->trans('HvMlAlreadySentInfo').'</div>';
}
print '<div class="'.($tested ? 'ok' : 'warning').'" style="margin:6px 0">'.($tested ? img_picto('', 'tick', 'class="pictofixedwidth"').$langs->trans('HvMlTestedAt', dol_print_date($ml->date_test, 'dayhour')) : img_warning('', '', 'pictofixedwidth').$langs->trans('HvMlTestFirst')).'</div>';
print '<form method="POST" action="'.$self.'?id='.$ml->id.'#send"><input type="hidden" name="token" value="'.newToken().'">';
$noText = trim($ml->subject) === '' || trim($ml->body) === '';
if ($noText) {
	print '<div class="warning">'.img_warning('', '', 'pictofixedwidth').$langs->trans('HvMlTextMissing').'</div>';
}
print $langs->trans('HvMailTestTo').': <input name="testto" class="minwidth250" value="'.dol_escape_htmltag((string) $user->email).'"> <button class="button" name="action" value="sendtest"'.($noText ? ' disabled title="'.dol_escape_htmltag($langs->trans('HvMlTextMissing')).'"' : '').'>'.$langs->trans('HvMlTestBtn').'</button> ';
print '<button class="button button-save" name="action" value="send"'.(($ml->subject === '' || $ml->body === '' || !$ml->recipients || !$tested) ? ' disabled title="'.dol_escape_htmltag($langs->trans('HvMlTestFirst')).'"' : '').'>'.$langs->trans('HvMlSendBtn').' ('.count($ml->recipients).')</button></form>';
$log = $ml->log();
if ($log) {
	print '<br><table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('HvUnit').'</th><th>'.$langs->trans('HvMailTo').'</th><th>'.$langs->trans('Language').'</th><th>'.$langs->trans('HvMailFiles').'</th><th>'.$langs->trans('Status').'</th></tr>';
	foreach ($log as $o) {
		print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($o->datec), 'dayhour').'</td><td>'.dol_escape_htmltag((string) $o->nom).'</td><td class="small">'.dol_escape_htmltag((string) $o->email).'</td><td translate="no">'.dol_escape_htmltag((string) $o->lang).'</td><td class="small">'.dol_escape_htmltag((string) $o->files).'</td><td>'.($o->status === 'ok' ? '<span class="ok">OK</span>' : '<span class="error">'.dol_escape_htmltag((string) $o->error).'</span>').'</td></tr>';
	}
	print '</table>';
}

print '<script>
jQuery(function ($) {
	var dirty = null;
	$("form.hvdirty").on("input change", "input, textarea, select", function () { dirty = $(this).closest("form"); });
	$("form.hvdirty").on("submit", function () { dirty = null; });
	// Testversand/Versand/andere Aktionen nehmen immer den gespeicherten Text -> vorher speichern
	$(document).on("click", "button[name=action], input[type=submit]", function (e) {
		if (dirty && !$(this).closest("form").is(dirty)) {
			e.preventDefault();
			alert("'.dol_escape_js($langs->transnoentities('HvMlUnsaved')).'");
			$("html, body").animate({ scrollTop: dirty.offset().top - 80 }, 200);
			dirty.find("[type=submit]").first().focus();
		}
	});
	window.addEventListener("beforeunload", function (e) { if (dirty && !window.hvRunning) { e.preventDefault(); e.returnValue = ""; } });
});
</script>';
llxFooter();
$db->close();
