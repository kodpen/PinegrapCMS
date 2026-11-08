<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Driving School design template's lesson requests and
 * online booking (includes/design_templates/pinegrap-drivingschool/
 * lessons.php): the pages and widgets, the request form (its fields, pick
 * lists, the licence read from ?licence= and the instructor from
 * ?instructor= as the template's links send them, the day from today on,
 * the office-only status), the record page with its messages, the lists
 * and e-mails binding fields the form has, the booking page beside the
 * form, every page link naming a template page, ids and classes the
 * template's own, and every literal with its Turkish.
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
// _test_ds_nodes(), _test_ds_filter_links()) and the controls reader of
// the enrolments test.
require_once(__DIR__ . '/design_template_drivingschool_test.php');
require_once(__DIR__ . '/design_template_drivingschool_enrolments_test.php');

// The lessons' part as the template file reads it: parts.php, mail.php,
// courses.php, enrolments.php, then lessons.php in the same scope. Read
// once per run.
function _test_dsl_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$courses_part = include $dir . '/courses.php';
	include $dir . '/enrolments.php';
	$part = include $dir . '/lessons.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'licences' => $licence_titles, 'instructors' => $instructor_choices, 'lengths' => $lesson_lengths,
		'gearboxes' => $gearbox_titles, 'times' => $lesson_time_choices, 'statuses' => $lesson_statuses,
		'trees' => array_merge(array($footer(), $courses_part['widgets']['course_detail']['tree']),
			array_map(function ($p) { return $p['tree']; }, $public['pages'])),
		'instructor_cards' => array_map($instructor_card, array_keys($instructors)));
	return $out;
}

function test_drivingschool_lessons_pages_and_widgets()
{
	$d = _test_dsl_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('lesson_request', 'lesson_request_view', 'my_lesson_requests', 'staff_lesson_requests', 'book_online',
		'email_lesson_new', 'email_lesson_received'), array_keys($d['pages']));
	pg_assert_same(array('lesson_form', 'lesson_detail', 'lesson_messages', 'my_lesson_requests_list', 'staff_lesson_requests_list',
		'booking_slots', 'email_lesson_admin', 'email_lesson_learner'), array_keys($d['widgets']));
	foreach (array('lesson_request', 'book_online') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('lesson_request_view', 'my_lesson_requests') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	foreach (array('staff_lesson_requests', 'email_lesson_new', 'email_lesson_received') as $key) {
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
	pg_assert_same('submitter_only', $d['widgets']['lesson_detail']['config']['access_control'], 'the request is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_lesson_requests_list']['config']['viewer_filter_submitter']), 'the own requests');
	pg_assert_true(!empty($d['widgets']['staff_lesson_requests_list']['config']['search_enabled']), 'the staff search');
	// The booking page: the widget's starter, and the two pages link each
	// other.
	pg_assert_same(array('regionType' => 'booking'), $d['widgets']['booking_slots']['config']);
	pg_assert_same('starter', $d['widgets']['booking_slots']['tree']);
	pg_assert_contains('{{page:lesson_request}}', json_encode($d['pages']['book_online']['tree']), 'booking links the request');
	pg_assert_contains('{{page:book_online}}', json_encode($d['pages']['lesson_request']['tree']), 'the request links booking');
}

// The request form: its fields and pick lists, the licence and instructor
// every link sends, the day from today on, the office-only status.
function test_drivingschool_lessons_form()
{
	$d = _test_dsl_load();
	$form = $d['pages']['lesson_request']['form'];
	pg_assert_same('{{tab:lesson_request_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_lesson_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_lesson_received}}', $form['confirm_page_id']);
	$c = _test_dse_controls($d['widgets']['lesson_form']['tree']);
	pg_assert_same(array('licence', 'instructor', 'lesson_length', 'gearbox', 'preferred_date', 'preferred_time', 'pickup_point', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['licences'], $c['licence']['options'], 'the licences');
	pg_assert_same($d['instructors'], $c['instructor']['options'], 'the instructors, any first');
	pg_assert_same($d['lengths'], $c['lesson_length']['options'], 'the lengths');
	pg_assert_same($d['gearboxes'], $c['gearbox']['options'], 'the gearboxes');
	pg_assert_same($d['times'], $c['preferred_time']['options'], 'the times');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_same('licence', $c['licence']['cf']['url_param'] ?? '', 'the licence reads ?licence=');
	pg_assert_same('instructor', $c['instructor']['cf']['url_param'] ?? '', 'the instructor reads ?instructor=');
	pg_assert_same('today', $c['preferred_date']['cf']['rules']['min_date'] ?? '', 'a day from today on');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the school\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['lesson_form']['tree']), 'the consent links the privacy page');
	// Every ?licence= and ?instructor= a link sends is an answer.
	$seen = array('licence' => 0, 'instructor' => 0);
	foreach (array_merge($d['trees'], $d['instructor_cards']) as $tree) {
		foreach (_test_ds_filter_links($tree, 'lesson_request') as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($seen[$param]), "?$param is a field the form reads");
				if (!isset($seen[$param])) continue;
				pg_assert_true(in_array($value, $c[$param]['options'], true), "?$param=$value is an answer");
				$seen[$param]++;
			}
		}
	}
	pg_assert_true($seen['licence'] >= count($d['licences']), 'every licence page asks for a lesson');
	pg_assert_true($seen['instructor'] >= count($d['instructors']) - 1, 'every instructor card asks for a lesson');
	// The course page sends the record's licence field.
	pg_assert_contains('"page_id":"{{tab:lesson_request}}","params":[{"name":"licence","token":"licence"}]', json_encode($d['trees'][1]), 'the course page sends its licence');
}

// The record page carries its messages; the page, the lists and the
// e-mails bind fields the form has.
function test_drivingschool_lessons_record_and_mail()
{
	$d = _test_dsl_load();
	$comments = $d['pages']['lesson_request_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys(_test_dse_controls($d['widgets']['lesson_form']['tree']));
	foreach (array('lesson_detail', 'my_lesson_requests_list', 'staff_lesson_requests_list', 'email_lesson_admin', 'email_lesson_learner') as $key) {
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
	foreach ($d['widgets']['staff_lesson_requests_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff list searches $name");
	}
	$json = json_encode($d['widgets']['lesson_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	foreach (array('email_lesson_admin', 'email_lesson_learner') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:lesson_request_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
	}
}

function test_drivingschool_lessons_links_ids_and_classes()
{
	$d = _test_dsl_load();
	$known = array_flip(_test_ds_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ds_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) if (is_array($w['tree'])) _test_ds_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'pdr-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'drv-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a lesson reads "ders", a licence
// "ehliyet", an instructor "eğitmen".
function test_drivingschool_lessons_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool/lessons.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		if (strpos($words, '-') !== false && strpos($words, ' ') === false) continue;
		if (preg_match('/\blessons?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'ders', 0, 'UTF-8') !== false, "'$key' says ders");
		if (preg_match('/\blicences?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'ehliyet', 0, 'UTF-8') !== false, "'$key' says ehliyet");
		if (preg_match('/\binstructors?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'eğitmen', 0, 'UTF-8') !== false, "'$key' says eğitmen");
	}
}
