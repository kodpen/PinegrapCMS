<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sports Club design template's registrations
 * (includes/design_templates/pinegrap-sportsclub/mail.php and
 * registrations.php): the pages and widgets of the plan, the academy
 * registration and the trial request with their fields, answers and
 * address parameters (?team=, ?position=), the record pages and their
 * messages, lists and e-mails that read only fields the forms have, links
 * that name template pages, the template's ids and classes and every
 * literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_sp_page_keys()) and the
// tree helpers of the collections test.
require_once __DIR__ . '/design_template_sportsclub_test.php';
require_once __DIR__ . '/design_template_sportsclub_collections_test.php';

// parts.php, squad.php, mail.php and registrations.php in one scope, as
// the template file reads them. Read once per run.
function _test_spr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
	include $dir . '/parts.php';
	include $dir . '/squad.php';
	include $dir . '/mail.php';
	$part = include $dir . '/registrations.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'teams' => $teams,
		'youth_team_titles' => $youth_team_titles, 'trial_team_choices' => $trial_team_choices, 'position_titles' => $position_titles,
		'experience_choices' => $experience_choices, 'contact_choices' => $contact_choices,
		'registration_statuses' => $registration_statuses, 'trial_statuses' => $trial_statuses);
	return $out;
}

function test_sportsclub_registrations_pages_and_widgets()
{
	$d = _test_spr_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('academy_register', 'registration_view', 'my_registrations', 'staff_registrations', 'trial', 'trial_view',
		'staff_trials', 'privacy', 'email_registration_new', 'email_registration_received', 'email_trial_new', 'email_trial_received',
		'email_reply'), array_keys($d['pages']));
	foreach (array('academy_register', 'trial', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('registration_view', 'trial_view', 'my_registrations') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	foreach ($d['pages'] as $key => $p) {
		if (strpos($key, 'staff_') === 0 || strpos($key, 'email_') === 0) {
			pg_assert_same('private', $p['folder'], "only the staff open $key");
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false, "$key is out of the search");
		}
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	foreach (array('registration_detail', 'trial_detail') as $key) {
		pg_assert_same('submitter_only', $d['widgets'][$key]['config']['access_control'], "$key is its sender's");
	}
	foreach (array('my_registrations_list', 'my_trials_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['viewer_filter_submitter']), "$key lists the visitor's own");
	}
	foreach (array('staff_registrations_list', 'staff_trials_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['search_enabled']), "$key: the staff search");
	}
}

// The forms: auto-registration, the e-mails, the fields, the answers from
// parts.php, the address parameters, the club's status and the consent.
function test_sportsclub_registrations_forms()
{
	$d = _test_spr_load();
	$forms = array(
		'academy_register' => array('registration_form', 'registration_view', 'email_registration_new', 'email_registration_received',
			array('child_first_name', 'child_last_name', 'child_birth_date', 'team', 'experience', 'medical_notes', 'notes',
				'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), 'registration_statuses'),
		'trial'            => array('trial_form', 'trial_view', 'email_trial_new', 'email_trial_received',
			array('team', 'position', 'birth_date', 'last_club', 'experience', 'notes',
				'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), 'trial_statuses'),
	);
	foreach ($forms as $page_key => $f) {
		$form = $d['pages'][$page_key]['form'];
		pg_assert_same('{{tab:' . $f[1] . '}}', $form['confirmation_page_id'], "$page_key opens its record");
		pg_assert_same(1, $form['auto_registration'], "$page_key opens an account");
		pg_assert_same('{{tab:' . $f[2] . '}}', $form['notify_page_id'], "$page_key: the club's e-mail");
		pg_assert_same('{{tab:' . $f[3] . '}}', $form['confirm_page_id'], "$page_key: the sender's e-mail");
		pg_assert_false(isset($form['collection']), "$page_key is a request, not a collection");
		$c = _test_spc_controls($d['widgets'][$f[0]]['tree']);
		pg_assert_same($f[4], array_keys($c), "$page_key: its fields");
		pg_assert_same($d[$f[5]], $c['status']['options'], "$page_key: the statuses");
		pg_assert_true(!empty($c['status']['cf']['office_use_only']), "$page_key: the status is the club's");
		pg_assert_same(array_column($d['contact_choices'], 0), _test_spr_radio_values($d['widgets'][$f[0]]['tree'], 'contact_method'), "$page_key: the ways to reach the sender");
		pg_assert_same(array_column($d['experience_choices'], 0), _test_spr_radio_values($d['widgets'][$f[0]]['tree'], 'experience'), "$page_key: the experience");
		pg_assert_same('radio', $c['experience']['type'], "$page_key: the experience is a choice");
		pg_assert_same('team', $c['team']['cf']['url_param'] ?? '', "$page_key: the team reads ?team=");
		foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
			pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$page_key: $name fills the contact");
		}
		pg_assert_contains('{{page:privacy}}', json_encode($d['widgets'][$f[0]]['tree']), "$page_key: the consent links the privacy page");
	}
	$r = _test_spc_controls($d['widgets']['registration_form']['tree']);
	pg_assert_same($d['youth_team_titles'], $r['team']['options'], 'a child registers for a youth team');
	pg_assert_same('date', $r['child_birth_date']['type'], 'the birth date is a date');
	$t = _test_spc_controls($d['widgets']['trial_form']['tree']);
	pg_assert_same($d['trial_team_choices'], $t['team']['options'], 'a trial for any team, or not sure yet');
	pg_assert_same('position', $t['position']['cf']['url_param'] ?? '', 'the position reads ?position=');
	pg_assert_same(array_merge($d['position_titles'], array(lang('Not sure yet'))), $t['position']['options'], 'the positions, or not sure yet');
}

// The values of a group of radio buttons, in order.
function _test_spr_radio_values($tree, $name)
{
	$nodes = array();
	_test_spc_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$a = array();
		foreach ((array)($n['props']['_attrs'] ?? array()) as $x) $a[$x['name']] = $x['value'];
		if (($a['type'] ?? '') === 'radio' && ($a['name'] ?? '') === $name) $out[] = $a['value'];
	}
	return $out;
}

// The team pages' links open the forms with an answer the form has.
function test_sportsclub_registrations_team_links()
{
	$d = _test_spr_load();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
	$public = _test_spr_public($dir);
	$forms = array('academy_register' => 'registration_form', 'trial' => 'trial_form');
	$seen = array();
	$nodes = array();
	foreach ($public['pages'] as $p) _test_spc_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		foreach ($forms as $page_key => $w) {
			if (strpos($href, '{{page:' . $page_key . '}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:' . $page_key . '}}?')), $q);
			$c = _test_spc_controls($d['widgets'][$w]['tree']);
			foreach ($q as $param => $value) {
				pg_assert_true(isset($c[$param]), "$page_key: ?$param= names a field");
				pg_assert_true(in_array($value, $c[$param]['options'], true), "$page_key: ?$param=$value is an answer");
			}
			$seen[$page_key] = true;
		}
	}
	pg_assert_true(isset($seen['academy_register'], $seen['trial']), 'the team pages link both forms with a team');
}

// pages_public.php in the same scope as parts.php, for its links.
function _test_spr_public($dir)
{
	static $part = null;
	if ($part !== null) return $part;
	include $dir . '/parts.php';
	include $dir . '/squad.php';
	$part = include $dir . '/pages_public.php';
	return $part;
}

// The record pages, their messages, the lists and the e-mails read only
// fields of their form (or the renderer's own tokens).
function test_sportsclub_registrations_record_and_mail()
{
	$d = _test_spr_load();
	foreach (array('registration_view', 'trial_view') as $key) {
		$comments = $d['pages'][$key]['comments'];
		pg_assert_true($comments['allow_new'] && $comments['login'], "$key: messages from the signed-in sender");
		pg_assert_same('{{tab:email_reply}}', $comments['email_page'], "$key: the reply e-mail");
	}
	$fields = array(
		'academy_register' => array_keys(_test_spc_controls($d['widgets']['registration_form']['tree'])),
		'trial'            => array_keys(_test_spc_controls($d['widgets']['trial_form']['tree'])),
	);
	$readers = array(
		'academy_register' => array('registration_detail', 'my_registrations_list', 'staff_registrations_list', 'email_registration_admin', 'email_registration_parent'),
		'trial'            => array('trial_detail', 'my_trials_list', 'staff_trials_list', 'email_trial_admin', 'email_trial_player'),
	);
	foreach ($readers as $form => $keys) {
		foreach ($keys as $key) {
			$w = $d['widgets'][$key];
			pg_assert_same('{{tab:' . $form . '}}', $w['config']['custom_form_page_id'], "$key reads the $form form");
			foreach (_test_spc_tokens($w['tree']) as $token) {
				if (strpos($token, '__') === 0 || in_array($token, array('form_item_view', 'not_found', 'reference_code', 'number_of_comments',
					'record_found', 'record_not_found', 'submitted_this_session', 'has_new_account', 'has_set_password_link', 'can_edit'), true)) continue;
				pg_assert_true(in_array($token, $fields[$form], true), "$key binds $token, a field of the $form form");
			}
			foreach ((array)($w['config']['search_fields'] ?? array()) as $name) {
				pg_assert_true(in_array($name, $fields[$form], true), "$key: the staff search reads $name");
			}
		}
	}
	foreach (array('registration_detail', 'trial_detail') as $key) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
		pg_assert_contains('__edit_url', $json, "$key: the staff's Edit button");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	foreach (array('email_registration_admin', 'email_registration_parent', 'email_trial_admin', 'email_trial_player') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		$view = strpos($key, 'registration') !== false ? 'registration_view' : 'trial_view';
		pg_assert_contains('{{page:' . $view . '}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
	}
}

// Every link names a page the finished template carries or an e-mail page
// of this part; ids and classes are the template's.
function test_sportsclub_registrations_links_ids_and_classes()
{
	$d = _test_spr_load();
	$known = array_flip(array_merge(_test_sp_page_keys(), array_keys($d['pages'])));
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_sp_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_spc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) if (is_array($w['tree'])) _test_spc_nodes($w['tree'], $nodes);
	$ids = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pmu|pmz|psh|pcn|ptv|pwd|pph|psc|pre|pev|pdp|plx|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) {
			pg_assert_true(strpos($id, 'sp-') === 0, "id $id is the template's");
			$ids[] = $id;
		}
	}
	foreach (array('registration_form', 'trial_form') as $key) {
		$own = array();
		$wn = array();
		_test_spc_nodes($d['widgets'][$key]['tree'], $wn);
		foreach ($wn as $n) if (($n['props']['id'] ?? '') !== '') $own[] = $n['props']['id'];
		pg_assert_same(count($own), count(array_unique($own)), "$key: every id once");
	}
}

// Every literal has its Turkish; a trial reads "seçme", the academy
// "altyapı".
function test_sportsclub_registrations_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'registrations.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$words = str_replace('-', ' ', $key);
			if (preg_match('/\btrials?\b/i', $words)) pg_assert_true(mb_stripos($map[$key], 'seçme', 0, 'UTF-8') !== false
				|| mb_stripos($map[$key], 'secme', 0, 'UTF-8') !== false, "'$key' says seçme");
			if (preg_match('/\bacademy\b/i', $words)) pg_assert_true(mb_stripos($map[$key], 'altyap', 0, 'UTF-8') !== false, "'$key' says altyapı");
		}
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', preg_replace('/@copyright[^\n]*/', '', $src)), "$file: no year");
	}
}
