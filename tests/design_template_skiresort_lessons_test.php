<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ski Resort design template's lesson requests
 * (includes/design_templates/pinegrap-skiresort/mail.php and lessons.php):
 * the pages and widgets of the plan, each widget on its page, the request
 * form taking the lesson, level and age group from the address in the
 * lists' own words with the status office-only and the first day not
 * before today, every lesson card opening it with answers of those lists,
 * the request's page, lists and e-mails reading the form, the reply e-mail,
 * the template's ids and classes, no fixed dates and every literal in
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_skiresort_test.php');

// parts.php, cards.php, the public pages, mail.php and lessons.php in one
// scope, as the template file reads them. Read once per run.
function _test_skl_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	include $dir . '/mail.php';
	$part = include $dir . '/lessons.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'public' => $public, 'pages' => $pages, 'widgets' => $part['widgets'],
		'lists' => array('lesson' => $lesson_choices, 'level' => $level_titles, 'age' => $age_titles),
		'lesson_statuses' => $lesson_statuses, 'lesson_day_choices' => $lesson_day_choices, 'skier_counts' => $skier_counts,
		'email_header' => $email_header_tree(), 'email_footer' => $email_footer_tree());
	return $out;
}

function _test_skl_nodes($tree)
{
	$nodes = array();
	_test_sk_nodes($tree, $nodes);
	return $nodes;
}

// The name, options and _cf of every form control of a tree.
function _test_skl_controls($tree)
{
	$out = array();
	foreach (_test_skl_nodes($tree) as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (isset($out[$name])) continue;
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

function test_skiresort_lessons_pages_and_widgets()
{
	$d = _test_skl_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('lesson_request', 'lesson_view', 'my_lessons', 'staff_lessons', 'privacy', 'email_lesson_new', 'email_lesson_received', 'email_reply'),
		array_keys($d['pages']));
	pg_assert_same(array('lesson_form', 'lesson_detail', 'lesson_messages', 'my_lessons_list', 'staff_lessons_list', 'email_lesson_admin', 'email_lesson_client', 'email_comment'),
		array_keys($d['widgets']));
	$known = array_flip(_test_sk_page_keys());
	$folders = array('lesson_request' => 'public', 'lesson_view' => 'registration', 'my_lessons' => 'registration', 'staff_lessons' => 'private', 'privacy' => 'public');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($folders[$key] ?? 'private', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page is out of the search");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_sk_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
	// The e-mail frame places the shared header and footer, drawn by mail.php.
	pg_assert_contains('"templateShared":"email_header"', json_encode($d['pages']['email_lesson_new']['tree']), 'the e-mail header is shared');
	pg_assert_contains('{{site_name}}', json_encode($d['email_header']), 'the e-mail header names the site');
	pg_assert_contains('#075985', json_encode($d['email_header']), 'the e-mail header keeps the sky colour for mail programs');
}

// A lesson is a request: the form opens the request's page, mails both
// sides and opens an account; it starts from the address in the lists'
// own words; the status is the office's.
function test_skiresort_lessons_form_is_a_request()
{
	$d = _test_skl_load();
	$form = $d['pages']['lesson_request']['form'];
	pg_assert_false(isset($form['collection']), 'a request, not a collection');
	pg_assert_same('{{tab:lesson_view}}', $form['confirmation_page_id'], 'what is sent opens its page');
	pg_assert_same('{{tab:email_lesson_new}}', $form['notify_page_id'], 'the resort is mailed');
	pg_assert_same('{{tab:email_lesson_received}}', $form['confirm_page_id'], 'the guest is mailed');
	pg_assert_same(1, $form['auto_registration'], 'a guest gets an account');
	pg_assert_contains('^^lesson^^', $form['notify_subject'], 'the subject names the lesson');
	$c = _test_skl_controls($d['widgets']['lesson_form']['tree']);
	pg_assert_same(array('lesson', 'level', 'age_group', 'sport', 'first_day', 'days', 'skiers', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'), array_keys($c), 'the request\'s fields');
	foreach (array('lesson' => 'lesson', 'level' => 'level', 'age_group' => 'age') as $field => $param) {
		pg_assert_same($param, $c[$field]['cf']['url_param'] ?? '', "$field starts from ?$param=");
		pg_assert_same($d['lists'][$param], $c[$field]['options'], "$field offers its list");
	}
	pg_assert_same('today', $c['first_day']['cf']['rules']['min_date'] ?? '', 'the first day is not before today');
	pg_assert_same($d['lesson_day_choices'], $c['days']['options'], 'days offer their list');
	pg_assert_same($d['skier_counts'], $c['skiers']['options'], 'skiers offer their list');
	pg_assert_same($d['lesson_statuses'], $c['status']['options'], 'the status offers its list');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the office\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['lesson_form']['tree']), 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', json_encode($d['widgets']['lesson_form']['tree']), 'the form has a CAPTCHA');
	$words = strtolower(json_encode($d['part']));
	pg_assert_false(strpos($words, 'book now') !== false || strpos($words, 'booking confirmed') !== false, 'never a booking');
}

// Every link that opens the request (the lesson cards, the levels of the
// ski school page, the header) uses answers of the request's lists.
function test_skiresort_lessons_links_use_the_lists_words()
{
	$d = _test_skl_load();
	$trees = array();
	foreach ($d['public']['pages'] as $p) $trees[$p['key']] = $p['tree'];
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	$seen = array();
	foreach ($trees as $key => $tree) {
		foreach (_test_skl_nodes($tree) as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (!preg_match('/^\{\{page:lesson_request\}\}\?(.*)$/', $href, $m)) continue;
			parse_str($m[1], $q);
			foreach ($q as $name => $value) {
				pg_assert_true(isset($d['lists'][$name]) && in_array($value, $d['lists'][$name], true), "$key: $name '$value' is an answer");
				$seen[$name] = true;
			}
		}
	}
	foreach (array('lesson', 'level', 'age') as $param) pg_assert_true(isset($seen[$param]), "a link sends ?$param=");
}

// The request's page reads the record (the sender's own), its messages are
// the page's comments mailed with the reply e-mail; the lists read the
// form, the guest's own filtered to the viewer, the staff's searchable.
function test_skiresort_lessons_view_lists_and_mails_read_the_form()
{
	$d = _test_skl_load();
	$w = $d['widgets'];
	pg_assert_same('submitter_only', $w['lesson_detail']['config']['access_control'], 'the request is the sender\'s');
	pg_assert_same('{{tab:lesson_request}}', $w['lesson_detail']['config']['custom_form_page_id'], 'of the Lesson Requests form');
	pg_assert_same('{{tab:email_reply}}', $d['pages']['lesson_view']['comments']['email_page'], 'messages are mailed');
	pg_assert_same('comments', $w['lesson_messages']['config']['regionType'], 'the messages are comments');
	pg_assert_true(!empty($w['my_lessons_list']['config']['viewer_filter_submitter']), 'My Lessons shows the guest\'s own');
	pg_assert_true(!empty($w['staff_lessons_list']['config']['search_enabled']), 'the staff list is searchable');
	foreach (array('my_lessons_list', 'staff_lessons_list') as $key) {
		pg_assert_same('{{tab:lesson_view}}', $w[$key]['config']['detail_page_id'], "$key opens the request");
	}
	$fields = array_keys(_test_skl_controls($w['lesson_form']['tree']));
	foreach (array('lesson_detail', 'my_lessons_list', 'staff_lessons_list', 'email_lesson_admin', 'email_lesson_client') as $key) {
		foreach (_test_skl_nodes($w[$key]['tree']) as $n) {
			$token = $n['props']['_bindings']['text'] ?? '';
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
		}
		if ($key !== 'my_lessons_list' && $key !== 'staff_lessons_list') {
			pg_assert_same('form_item_view', $w[$key]['config']['regionType'], "$key reads one record");
		}
	}
	foreach (array('email_lesson_admin', 'email_lesson_client') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:lesson_view}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the request");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the System region');
}

function test_skiresort_lessons_ids_classes_and_headings()
{
	$d = _test_skl_load();
	$ids = array();
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_skl_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pgl|pmz|pph|pvt|pre|pev|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'skr-') === 0 || strpos($id, 'comment_') === 0, "$key: id $id is the template's");
			if (strpos($id, 'comment_') === 0) continue;
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	foreach (array('lesson_request', 'my_lessons', 'privacy') as $key) {
		$levels = array();
		foreach (_test_skl_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

function test_skiresort_lessons_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_skl_load()['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	// The comment stars are SVG pictures: their namespace address is no date.
	$json = str_replace('http://www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_skiresort_lessons_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'lessons.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			if (preg_match('/\bski school\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'kayak okul', 0, 'UTF-8') !== false, "'$key' says kayak okulu");
			if (preg_match('/\blesson requests?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'ders tale', 0, 'UTF-8') !== false, "'$key' says ders talebi");
		}
	}
}
