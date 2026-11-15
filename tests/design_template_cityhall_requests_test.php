<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the City Hall design template's citizen requests and meeting
 * requests (includes/design_templates/pinegrap-cityhall/mail.php and
 * requests.php): the pages and widgets, the two forms (their fields, pick
 * lists, the topic read from ?topic= as every service-area page sends it,
 * the office-only fields, the private photo), the record pages with their
 * messages, the lists and e-mails binding fields their form has, every
 * page link naming a template page, ids and classes the template's own,
 * and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_cy_page_keys()).
require_once(__DIR__ . '/design_template_cityhall_test.php');

// The requests' part as the template file reads it: parts.php, mail.php,
// notices.php, then requests.php in the same scope. Read once per run.
function _test_cyr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/notices.php';
	$part = include $dir . '/requests.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'topics' => $request_topic_choices, 'kinds' => $request_kinds, 'neighbourhoods' => $neighbourhood_choices,
		'departments' => $department_titles, 'statuses' => $request_statuses, 'contact_choices' => $contact_choices,
		'subjects' => $meeting_subjects, 'times' => $meeting_times, 'meeting_statuses' => $meeting_statuses,
		'public' => $public, 'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_cyr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_cyr_nodes($c, $out);
}

// The name, options and settings of every form control of a tree.
function _test_cyr_controls($node)
{
	$nodes = array();
	_test_cyr_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$value = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'value') $value = $a['value'];
		}
		if (!isset($out[$name])) $out[$name] = array('tag' => $tag, 'options' => array(), 'cf' => $n['props']['_cf'] ?? array());
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $out[$name]['options'][] = $a['value'];
			}
		}
		// A radio group: each button's value is an answer.
		if ($tag === 'input' && $value !== '' && $name !== 'consent') $out[$name]['options'][] = $value;
	}
	return $out;
}

function test_cityhall_requests_pages_and_widgets()
{
	$d = _test_cyr_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('request', 'request_view', 'my_requests', 'staff_requests', 'meet', 'meeting_view', 'my_meetings', 'staff_meetings',
		'privacy', 'email_request_new', 'email_request_received', 'email_meeting_new', 'email_meeting_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('request_form', 'request_detail', 'request_messages', 'my_requests_list', 'staff_requests_list',
		'meeting_form', 'meeting_detail', 'meeting_messages', 'my_meetings_list', 'staff_meetings_list',
		'email_request_admin', 'email_request_resident', 'email_meeting_admin', 'email_meeting_resident', 'email_comment'), array_keys($d['widgets']));
	foreach (array('request', 'meet', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('request_view', 'my_requests', 'meeting_view', 'my_meetings') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	foreach (array('staff_requests', 'staff_meetings', 'email_request_new', 'email_request_received', 'email_meeting_new', 'email_meeting_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true($d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	foreach (array('request_detail', 'meeting_detail') as $key) {
		pg_assert_same('submitter_only', $d['widgets'][$key]['config']['access_control'], "$key is its sender's");
	}
	foreach (array('my_requests_list', 'my_meetings_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['viewer_filter_submitter']), "$key lists the own records");
	}
	foreach (array('staff_requests_list', 'staff_meetings_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['search_enabled']), "$key: the staff search");
	}
}

// The request form: its fields, the topic from ?topic= as every service
// area's page sends it, the office-only status and department, the
// private photo, the contact fields.
function test_cityhall_requests_form()
{
	$d = _test_cyr_load();
	$form = $d['pages']['request']['form'];
	pg_assert_same('{{tab:request_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_request_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_request_received}}', $form['confirm_page_id']);
	$c = _test_cyr_controls($d['widgets']['request_form']['tree']);
	pg_assert_same(array('kind', 'topic', 'neighbourhood', 'place', 'description', 'photo', 'first_name', 'last_name', 'email', 'phone',
		'contact_method', 'status', 'department', 'consent'), array_keys($c));
	pg_assert_same($d['kinds'], $c['kind']['options'], 'the kinds');
	pg_assert_same($d['topics'], $c['topic']['options'], 'the topics');
	pg_assert_same('topic', $c['topic']['cf']['url_param'] ?? '', 'the topic reads ?topic=');
	pg_assert_same($d['neighbourhoods'], $c['neighbourhood']['options'], 'the neighbourhoods');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_same($d['departments'], $c['department']['options'], 'the departments');
	foreach (array('status', 'department') as $name) pg_assert_true(!empty($c[$name]['cf']['office_use_only']), "$name is the city hall's");
	pg_assert_same('{{folder:private}}', $c['photo']['cf']['upload_folder_id'] ?? '', 'the photo is private');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'the ways to reach the resident');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['request_form']['tree']), 'the consent links the privacy page');
	// Every ?topic= a link sends is an answer.
	$seen = 0;
	foreach ($d['public']['pages'] as $p) {
		$nodes = array();
		_test_cyr_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:request}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:request}}?')), $q);
			pg_assert_same(array('topic'), array_keys($q), "{$p['key']}: the request link sends the topic");
			pg_assert_true(in_array($q['topic'], $c['topic']['options'], true), "?topic={$q['topic']} is an answer");
			$seen++;
		}
	}
	pg_assert_true($seen >= 7, 'every service area asks for a request with its topic');
}

function test_cityhall_requests_meeting_form()
{
	$d = _test_cyr_load();
	$form = $d['pages']['meet']['form'];
	pg_assert_same('{{tab:meeting_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_meeting_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_meeting_received}}', $form['confirm_page_id']);
	pg_assert_true($form['form_name'] !== $d['pages']['request']['form']['form_name'], 'a form of its own');
	$c = _test_cyr_controls($d['widgets']['meeting_form']['tree']);
	pg_assert_same(array('subject', 'preferred_time', 'preferred_days', 'details', 'first_name', 'last_name', 'email', 'phone',
		'contact_method', 'organisation', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['subjects'], $c['subject']['options'], 'the subjects');
	pg_assert_same($d['times'], $c['preferred_time']['options'], 'the times');
	pg_assert_same($d['meeting_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the office\'s');
}

// The record pages carry their messages and mail each one; the pages, the
// lists and the e-mails bind fields their form has.
function test_cityhall_requests_record_and_mail()
{
	$d = _test_cyr_load();
	$cases = array(
		'request_form' => array('request_view', array('request_detail', 'my_requests_list', 'staff_requests_list', 'email_request_admin', 'email_request_resident'), 'staff_requests_list'),
		'meeting_form' => array('meeting_view', array('meeting_detail', 'my_meetings_list', 'staff_meetings_list', 'email_meeting_admin', 'email_meeting_resident'), 'staff_meetings_list'),
	);
	foreach ($cases as $form => $c) {
		list($view, $widgets, $staff) = $c;
		$comments = $d['pages'][$view]['comments'];
		pg_assert_true($comments['allow_new'] && $comments['login'], "$view: messages from the signed-in sender");
		pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
		$fields = array_keys(_test_cyr_controls($d['widgets'][$form]['tree']));
		foreach ($widgets as $key) {
			$nodes = array();
			_test_cyr_nodes($d['widgets'][$key]['tree'], $nodes);
			foreach ($nodes as $n) {
				foreach (array('text', 'href') as $prop) {
					$token = (string)($n['props']['_bindings'][$prop] ?? '');
					if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
					pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
				}
			}
		}
		foreach ($d['widgets'][$staff]['config']['search_fields'] as $name) {
			pg_assert_true(in_array($name, $fields, true), "$staff searches $name");
		}
		$json = json_encode($d['widgets'][$widgets[0]]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$view: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$view: the not-found note");
		foreach (array($widgets[3], $widgets[4]) as $key) {
			pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
			pg_assert_contains('{{page:' . $view . '}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
		}
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
}

function test_cityhall_requests_links_ids_and_classes()
{
	$d = _test_cyr_load();
	$known = array_flip(_test_cy_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['shared'] as $t) _test_cyr_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_cyr_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_cyr_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'pcy-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cy-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a citizen request reads "talep" (or its
// form "talebi") and the mayor's office "başkan".
function test_cityhall_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'requests.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (strpos($words, '-') !== false && strpos($words, ' ') === false) continue;
			if (preg_match('/\bmayor\'s office\b/i', $words)) pg_assert_true(mb_stripos($tr, 'başkan', 0, 'UTF-8') !== false, "'$key' says başkan");
			if (preg_match('/\bcitizen requests?\b/i', $words)) pg_assert_true((mb_stripos($tr, 'talep', 0, 'UTF-8') !== false || mb_stripos($tr, 'talebi', 0, 'UTF-8') !== false), "'$key' says talep");
		}
	}
}
