<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Museum design template's learning pages and timed entry
 * (includes/design_templates/pinegrap-museum/learning.php): the pages and
 * widgets, the learning page's session cards opening the request on their
 * session, the school visit request with its office-only status, consent
 * and e-mails, the request's page, lists and messages, the timed entry
 * page with the booking widget's starter beside the request, every page
 * link names a template page, ids and classes are the template's own, and
 * every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_mz_page_keys()).
require_once __DIR__ . '/design_template_museum_test.php';

// The learning part as the template file reads it: parts.php and mail.php
// first, then exhibitions.php, events.php and learning.php in the same
// scope. Read once per run.
function _test_mzl_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/exhibitions.php';
	$events_part = include $dir . '/events.php';
	$part = include $dir . '/learning.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$privacy = null;
	foreach ($events_part['pages'] as $p) if ($p['key'] === 'privacy') $privacy = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'sessions' => $sessions,
		'session_choices' => $school_session_choices, 'statuses' => $school_visit_statuses, 'privacy' => $privacy);
	return $out;
}

// Every node of a tree, depth first.
function _test_mzl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_mzl_nodes($c, $out);
}

// The name, options, _cf and required flag of every form control of a tree.
function _test_mzl_controls($node)
{
	$nodes = array();
	_test_mzl_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = '';
		$required = false;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
			if ($a['name'] === 'required') $required = true;
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (isset($out[$name])) continue;
		$out[$name] = array('tag' => $tag, 'type' => $type, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array(), 'required' => $required);
	}
	return $out;
}

function test_museum_learning_pages_and_widgets()
{
	$d = _test_mzl_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('learning', 'school_visit', 'school_visit_view', 'my_school_visits', 'staff_school_visits',
		'email_school_visit_new', 'email_school_visit_received', 'book_online'), array_keys($d['pages']));
	pg_assert_same(array('school_visit_form', 'school_visit_detail', 'school_visit_messages', 'my_school_visits_list',
		'staff_school_visits_list', 'email_school_visit_admin', 'email_school_visit_client', 'booking_slots'), array_keys($d['widgets']));
	$folders = array('learning' => 'public', 'school_visit' => 'public', 'book_online' => 'public',
		'school_visit_view' => 'registration', 'my_school_visits' => 'registration',
		'staff_school_visits' => 'private', 'email_school_visit_new' => 'private', 'email_school_visit_received' => 'private');
	foreach ($folders as $key => $folder) pg_assert_same($folder, $d['pages'][$key]['folder'], "$key is $folder");
	foreach (array('email_school_visit_new', 'email_school_visit_received') as $key) pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
}

// The learning page shows every session; each card opens the request on
// its session, an answer of the form's Session list.
function test_museum_learning_sessions_open_the_request()
{
	$d = _test_mzl_load();
	$nodes = array();
	_test_mzl_nodes($d['pages']['learning']['tree'], $nodes);
	$sent = array();
	$prefix = '{{page:school_visit}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		pg_assert_same(array('session'), array_keys($q), 'only ?session=');
		$sent[] = $q['session'];
	}
	$titles = array();
	foreach ($d['sessions'] as $s) $titles[] = $s['title'];
	pg_assert_same($titles, $sent, 'a card per session, in order');
	$controls = _test_mzl_controls($d['widgets']['school_visit_form']['tree']);
	pg_assert_same('session', $controls['session']['cf']['url_param'] ?? '', 'the session from ?session=');
	pg_assert_same($d['session_choices'], $controls['session']['options'], 'the sessions and "not sure yet"');
	foreach ($sent as $title) pg_assert_true(in_array($title, $controls['session']['options'], true), "'$title' is an answer");
}

// The school visit request: the fields, an office-only status starting at
// the first state, counts as numbers, a date not before today, a consent
// linking the privacy page; it opens an account and the request's page and
// mails both sides.
function test_museum_learning_school_visit_request()
{
	$d = _test_mzl_load();
	$controls = _test_mzl_controls($d['widgets']['school_visit_form']['tree']);
	pg_assert_same(array('session', 'school', 'year_group', 'pupils', 'adults', 'preferred_date', 'visit_time', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'), array_keys($controls), 'the fields of the request');
	foreach (array('session', 'school', 'year_group', 'pupils', 'adults', 'consent') as $f) pg_assert_true($controls[$f]['required'], "$f is required");
	pg_assert_false($controls['preferred_date']['required'], 'any day may suit');
	pg_assert_same('today', $controls['preferred_date']['cf']['rules']['min_date'] ?? '', 'no date in the past');
	foreach (array('pupils', 'adults') as $f) {
		pg_assert_same('number', $controls[$f]['type'], "$f is a number");
		pg_assert_same('number', $controls[$f]['cf']['kind'] ?? '', "$f is stored as a number");
	}
	pg_assert_same($d['statuses'], $controls['status']['options'], 'the states of a school visit');
	pg_assert_true(!empty($controls['status']['cf']['office_use_only']), 'the status is the learning team\'s');
	pg_assert_same('email_address', $controls['email']['cf']['contact_field'] ?? '', 'the e-mail fills the contact');
	$json = json_encode($d['widgets']['school_visit_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'a CAPTCHA');
	$f = $d['pages']['school_visit']['form'];
	pg_assert_same(1, $f['auto_registration'], 'an account for a new teacher');
	pg_assert_same('{{tab:school_visit_view}}', $f['confirmation_page_id'], 'the request\'s page after sending');
	pg_assert_same('{{tab:email_school_visit_new}}', $f['notify_page_id'], 'the museum\'s e-mail');
	pg_assert_same('{{tab:email_school_visit_received}}', $f['confirm_page_id'], 'the teacher\'s e-mail');
	pg_assert_contains('^^school^^', $f['notify_subject'], 'the subject names the school');
	foreach (array('email_school_visit_admin', 'email_school_visit_client') as $key) {
		pg_assert_same('{{tab:school_visit}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads a request");
		pg_assert_contains('{{page:school_visit_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree']), "$key opens the request");
	}
}

// The request's page is the teacher's own, with its messages; My School
// Visits lists the teacher's own, the staff list searches every request.
function test_museum_learning_request_pages()
{
	$d = _test_mzl_load();
	$view = $d['pages']['school_visit_view'];
	pg_assert_same('submitter_only', $d['widgets']['school_visit_detail']['config']['access_control'], 'the teacher\'s own');
	pg_assert_same('{{tab:email_reply}}', $view['comments']['email_page'], 'answers are mailed');
	pg_assert_same('comments', $d['widgets']['school_visit_messages']['config']['regionType'], 'the messages');
	$mine = $d['widgets']['my_school_visits_list']['config'];
	pg_assert_true(!empty($mine['viewer_filter']) && !empty($mine['viewer_filter_submitter']), 'the teacher\'s own');
	$staff = $d['widgets']['staff_school_visits_list']['config'];
	pg_assert_true(!empty($staff['search_enabled']), 'the staff search');
	pg_assert_false(isset($staff['viewer_filter']), 'the staff see every request');
	foreach (array('my_school_visits_list', 'staff_school_visits_list') as $key) {
		pg_assert_same('{{tab:school_visit_view}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens the request");
	}
}

// Timed entry: the booking widget with the starter layout on a public page,
// the school visit request and the booking page linking each other, and the
// privacy page the widget's consent needs.
function test_museum_learning_timed_entry()
{
	$d = _test_mzl_load();
	$w = $d['widgets']['booking_slots'];
	pg_assert_same(array('regionType' => 'booking'), $w['config'], 'the booking widget');
	pg_assert_same('starter', $w['tree'], 'the editor\'s starter layout');
	pg_assert_same(lang('book-online'), $d['pages']['book_online']['name'], 'the shared page name');
	pg_assert_contains('{{page:school_visit}}', json_encode($d['pages']['book_online']['tree']), 'a class asks first');
	pg_assert_contains('{{page:book_online}}', json_encode($d['pages']['school_visit']['tree']), 'a family books a time');
	pg_assert_same(lang('privacy-policy'), $d['privacy']['name'] ?? '', 'the privacy page the widget links');
}

function test_museum_learning_links_ids_and_classes()
{
	$d = _test_mzl_load();
	$known = array_flip(_test_mz_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('tickets', 'membership', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mzl_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_mzl_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mz-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a school visit reads "okul ziyareti".
function test_museum_learning_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/learning.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
		if (preg_match('/\bSchool Visits?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'okul ziyaret', 0, 'UTF-8') !== false, "'$key' says okul ziyareti");
	}
}
