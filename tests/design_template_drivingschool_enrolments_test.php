<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Driving School design template's e-mail builders and
 * course enrolment requests
 * (includes/design_templates/pinegrap-drivingschool/mail.php and
 * enrolments.php): the pages and widgets, the enrolment form (its fields,
 * pick lists, the course read from ?course= as the course page sends it,
 * the office-only status, the contact fields), the record page with its
 * messages, the lists and e-mails binding fields the form has, every page
 * link naming a template page, ids and classes the template's own, and
 * every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys and helpers of the template (_test_ds_page_keys(),
// _test_ds_nodes()).
require_once(__DIR__ . '/design_template_drivingschool_test.php');

// The enrolments' part as the template file reads it: parts.php, mail.php,
// courses.php, then enrolments.php in the same scope. Read once per run.
function _test_dse_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$courses_part = include $dir . '/courses.php';
	$part = include $dir . '/enrolments.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'course_choices' => $enrolment_course_choices, 'branches' => $branch_titles, 'times' => $lesson_time_choices,
		'theory' => $theory_choices, 'statuses' => $enrolment_statuses, 'contact_choices' => $contact_choices,
		'courses_part' => $courses_part, 'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

// The name, options and settings of every form control of a tree.
function _test_dse_controls($node)
{
	$nodes = array();
	_test_ds_nodes($node, $nodes);
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

function test_drivingschool_enrolments_pages_and_widgets()
{
	$d = _test_dse_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('enrol', 'enrolment_view', 'my_enrolments', 'staff_enrolments', 'privacy',
		'email_enrolment_new', 'email_enrolment_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('enrol_form', 'enrolment_detail', 'enrolment_messages', 'my_enrolments_list', 'staff_enrolments_list',
		'email_enrolment_admin', 'email_enrolment_learner', 'email_comment'), array_keys($d['widgets']));
	foreach (array('enrol', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('enrolment_view', 'my_enrolments') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	foreach (array('staff_enrolments', 'email_enrolment_new', 'email_enrolment_received', 'email_reply') as $key) {
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
	pg_assert_same('submitter_only', $d['widgets']['enrolment_detail']['config']['access_control'], 'the enrolment is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_enrolments_list']['config']['viewer_filter_submitter']), 'My Enrolments lists the own records');
	pg_assert_true(!empty($d['widgets']['staff_enrolments_list']['config']['search_enabled']), 'the staff search');
}

// The enrolment form: its fields, the course from ?course= as the course
// page sends it, the office-only status, the contact fields.
function test_drivingschool_enrolments_form()
{
	$d = _test_dse_load();
	$form = $d['pages']['enrol']['form'];
	pg_assert_same('{{tab:enrolment_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_enrolment_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_enrolment_received}}', $form['confirm_page_id']);
	$c = _test_dse_controls($d['widgets']['enrol_form']['tree']);
	pg_assert_same(array('course', 'branch', 'lesson_times', 'date_of_birth', 'theory_test', 'provisional_licence', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['course_choices'], $c['course']['options'], 'the courses');
	pg_assert_same('course', $c['course']['cf']['url_param'] ?? '', 'the course reads ?course=');
	pg_assert_same($d['branches'], $c['branch']['options'], 'the branches');
	pg_assert_same($d['times'], $c['lesson_times']['options'], 'the lesson times');
	pg_assert_same($d['theory'], $c['theory_test']['options'], 'the theory test answers');
	pg_assert_same(array(lang('Yes'), lang('No')), $c['provisional_licence']['options'], 'a provisional licence or not');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the school\'s');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'the ways to reach the learner');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['enrol_form']['tree']), 'the consent links the privacy page');
	// The course page sends ?course= with the record's name; every sample
	// course's name is an answer.
	foreach ($d['courses_part']['pages'] as $p) {
		if ($p['key'] !== 'course_new') continue;
		foreach ($p['form']['sample_records'] as $r) {
			pg_assert_true(in_array($r['name'], $c['course']['options'], true), "?course={$r['name']} is an answer");
		}
	}
}

// The record page carries its messages and mails each one; the page, the
// lists and the e-mails bind fields the form has.
function test_drivingschool_enrolments_record_and_mail()
{
	$d = _test_dse_load();
	$comments = $d['pages']['enrolment_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys(_test_dse_controls($d['widgets']['enrol_form']['tree']));
	foreach (array('enrolment_detail', 'my_enrolments_list', 'staff_enrolments_list', 'email_enrolment_admin', 'email_enrolment_learner') as $key) {
		$nodes = array();
		_test_ds_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $prop) {
				$token = (string)($n['props']['_bindings'][$prop] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
			}
		}
	}
	foreach ($d['widgets']['staff_enrolments_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff list searches $name");
	}
	$json = json_encode($d['widgets']['enrolment_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	foreach (array('email_enrolment_admin', 'email_enrolment_learner') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:enrolment_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
}

function test_drivingschool_enrolments_links_ids_and_classes()
{
	$d = _test_dse_load();
	$known = array_flip(_test_ds_page_keys());
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
	foreach ($d['shared'] as $t) _test_ds_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_ds_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_ds_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'pdr-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'drv-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; an enrolment reads "kayıt", a course
// "kurs", a lesson "ders".
function test_drivingschool_enrolments_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'enrolments.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (strpos($words, '-') !== false && strpos($words, ' ') === false) continue;
			if (preg_match('/\benrol(ment)?s?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'kayıt', 0, 'UTF-8') !== false, "'$key' says kayıt");
			if (preg_match('/\bcourses?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'kurs', 0, 'UTF-8') !== false, "'$key' says kurs");
			if (preg_match('/\blessons?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
		}
	}
}
