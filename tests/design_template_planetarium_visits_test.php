<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's school visits
 * (includes/design_templates/pinegrap-planetarium/visits.php) and its
 * e-mail builders (mail.php): the pages and widgets of the plan, each
 * widget on its page, the visit request taking the visit and the grade
 * from the address in the lists' own words with the status office-only
 * and the date not before today, every link that opens it using answers
 * of those lists, the request's page, lists and e-mails reading the form,
 * the e-mail pages out of the search, the template's ids and classes,
 * heading levels, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_planetarium_nights_test.php');

// parts.php, cards.php, the public pages, the shows and nights, mail.php
// and visits.php in one scope, as the template file reads them. Read once
// per run.
function _test_plv_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$shows_part = include $dir . '/shows.php';
	$nights_part = include $dir . '/nights.php';
	include $dir . '/mail.php';
	$part = include $dir . '/visits.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'public' => $public, 'others' => array($shows_part, $nights_part), 'pages' => $pages, 'widgets' => $part['widgets'],
		'lists' => array('visit' => $visit_choices, 'grade' => $grade_titles),
		'visit_statuses' => $visit_statuses, 'pupil_counts' => $pupil_counts,
		'shared' => array('email_header' => $email_header_tree(), 'email_footer' => $email_footer_tree()));
	return $out;
}

function test_planetarium_visits_pages_and_widgets()
{
	$d = _test_plv_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('visit_request', 'visit_view', 'my_visits', 'staff_visits', 'privacy', 'email_visit_new', 'email_visit_received', 'email_reply'),
		array_keys($d['pages']));
	pg_assert_same(array('visit_form', 'visit_detail', 'visit_messages', 'my_visits_list', 'staff_visits_list', 'email_visit_admin', 'email_visit_client', 'email_comment'),
		array_keys($d['widgets']));
	$known = array_flip(_test_planetarium_page_keys());
	$folders = array('visit_request' => 'public', 'privacy' => 'public', 'visit_view' => 'registration', 'my_visits' => 'registration');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($folders[$key] ?? 'private', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page is out of the search");
			$tree = json_encode($p['tree']);
			pg_assert_contains('"templateShared":"email_header"', $tree, "$key: the shared e-mail header");
			pg_assert_contains('"templateShared":"email_footer"', $tree, "$key: the shared e-mail footer");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_planetarium_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
	pg_assert_same('{{tab:email_reply}}', $d['pages']['visit_view']['comments']['email_page'], 'messages are mailed with the reply e-mail');
	pg_assert_false(strpos($all, '?view=') !== false, 'never ?view=');
	foreach ($d['shared'] as $key => $tree) pg_assert_contains('{{page:home}}', json_encode($tree), "$key links the home");
}

// A visit is a request: the form opens the request's page, mails both
// sides and opens an account; it starts from the address in the lists'
// own words; the status is the office's.
function test_planetarium_visits_form_is_a_request()
{
	$d = _test_plv_load();
	$form = $d['pages']['visit_request']['form'];
	pg_assert_false(isset($form['collection']), 'a request, not a collection');
	pg_assert_same('{{tab:visit_view}}', $form['confirmation_page_id'], 'what is sent opens its page');
	pg_assert_same('{{tab:email_visit_new}}', $form['notify_page_id'], 'the planetarium is mailed');
	pg_assert_same('{{tab:email_visit_received}}', $form['confirm_page_id'], 'the visitor is mailed');
	pg_assert_same(1, $form['auto_registration'], 'a visitor gets an account');
	pg_assert_contains('^^visit^^', $form['notify_subject'], 'the subject names the visit');
	$c = _test_pls_controls($d['widgets']['visit_form']['tree']);
	pg_assert_same(array('visit', 'grade', 'visit_date', 'pupils', 'show_wanted', 'accessibility', 'notes',
		'organisation', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'), array_keys($c), 'the request\'s fields');
	foreach (array('visit' => 'visit', 'grade' => 'grade') as $field => $param) {
		pg_assert_same($param, $c[$field]['cf']['url_param'] ?? '', "$field starts from ?$param=");
		pg_assert_same($d['lists'][$param], $c[$field]['options'], "$field offers its list");
	}
	pg_assert_same('today', $c['visit_date']['cf']['rules']['min_date'] ?? '', 'the visit is not before today');
	pg_assert_same($d['pupil_counts'], $c['pupils']['options'], 'pupils offer their list');
	pg_assert_same(4, count($d['visit_statuses']), 'Received, Confirmed, Waiting List, Cancelled');
	pg_assert_same($d['visit_statuses'], $c['status']['options'], 'the status offers its list');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the office\'s');
	$tree = json_encode($d['widgets']['visit_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $tree, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $tree, 'the form has a CAPTCHA');
}

// Every link that opens the request (the visit cards, the grade links)
// uses answers of the request's lists, and every list is reached.
function test_planetarium_visits_links_use_the_lists_words()
{
	$d = _test_plv_load();
	$trees = array();
	foreach ($d['public']['pages'] as $p) $trees[$p['key']] = $p['tree'];
	foreach ($d['others'] as $other) {
		foreach ($other['pages'] as $p) $trees[$p['key']] = $p['tree'];
		foreach ($other['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	}
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	$seen = array();
	foreach ($trees as $key => $tree) {
		foreach (_test_plp_nodes($tree) as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (!preg_match('/^\{\{page:visit_request\}\}\?(.*)$/', $href, $m)) continue;
			parse_str($m[1], $q);
			foreach ($q as $name => $value) {
				pg_assert_true(isset($d['lists'][$name]) && in_array($value, $d['lists'][$name], true), "$key: $name '$value' is an answer");
				$seen[$name][$value] = true;
			}
		}
	}
	foreach (array('visit', 'grade') as $param) pg_assert_true(isset($seen[$param]), "a link sends ?$param=");
	pg_assert_same(count($d['lists']['visit']) - 1, count($seen['visit']), 'every kind of visit has its card');
	pg_assert_same(count($d['lists']['grade']), count($seen['grade']), 'every grade has its link');
}

// The request's page reads the record (the sender's own); the lists read
// the form, the visitor's own filtered to the viewer, the staff's
// searchable; the e-mails bind only the form's fields.
function test_planetarium_visits_view_lists_and_mails_read_the_form()
{
	$d = _test_plv_load();
	$w = $d['widgets'];
	pg_assert_same('submitter_only', $w['visit_detail']['config']['access_control'], 'the request is the sender\'s');
	pg_assert_same('{{tab:visit_request}}', $w['visit_detail']['config']['custom_form_page_id'], 'of the Visit Requests form');
	pg_assert_same('comments', $w['visit_messages']['config']['regionType'], 'the messages are comments');
	pg_assert_same('comments', $w['email_comment']['config']['regionType'], 'the reply e-mail draws the comment');
	pg_assert_true(!empty($w['my_visits_list']['config']['viewer_filter_submitter']), 'My Visits shows the visitor\'s own');
	pg_assert_true(!empty($w['staff_visits_list']['config']['search_enabled']), 'the staff list is searchable');
	foreach (array('my_visits_list', 'staff_visits_list') as $key) {
		pg_assert_same('{{tab:visit_view}}', $w[$key]['config']['detail_page_id'], "$key opens the request");
		pg_assert_same('{{tab:visit_request}}', $w[$key]['config']['custom_form_page_id'], "$key reads the form");
	}
	$fields = array_keys(_test_pls_controls($w['visit_form']['tree']));
	foreach ($w['staff_visits_list']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $fields, true), "search field $f is a field of the form");
	foreach (array('visit_detail', 'my_visits_list', 'staff_visits_list', 'email_visit_admin', 'email_visit_client') as $key) {
		foreach (_test_plp_nodes($w[$key]['tree']) as $n) {
			$token = $n['props']['_bindings']['text'] ?? '';
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
		}
	}
	foreach (array('email_visit_admin', 'email_visit_client') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:visit_view}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the request");
	}
}

function test_planetarium_visits_ids_classes_and_headings()
{
	$d = _test_plv_load();
	$ids = array();
	$others = $d['public']['pages'];
	foreach ($d['others'] as $other) $others = array_merge($others, $other['pages'], array_values($other['widgets']));
	foreach ($others as $p) {
		foreach (_test_plp_nodes($p['tree']) as $n) {
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '') $ids[$id] = true;
		}
	}
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_plp_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (preg_match('/^([a-z]{3})-/', $c, $pre) && $pre[1][0] === 'p') pg_assert_true($pre[1] === 'ppm', "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted', 'text-body-tertiary'), true), "$key: no fixed or faint colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'plm-') === 0 || strpos($id, 'comment_') === 0, "$key: id $id is the template's");
			if (strpos($id, 'comment_') === 0) continue;
			pg_assert_false(isset($ids[$id]), "id $id once, beside the other pages' ids");
			$ids[$id] = true;
		}
	}
	foreach (array('visit_request', 'my_visits', 'privacy') as $key) {
		$levels = array();
		foreach (_test_plp_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

function test_planetarium_visits_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_plv_load()['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	// The comment stars are SVG pictures: their namespace address is no date.
	$json = str_replace('http://www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_planetarium_visits_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('visits.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			if (preg_match('/^[a-z-]+$/', $key)) continue;
			if (preg_match('/\bplanetarium\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'planetaryum', 0, 'UTF-8') !== false, "'$key' says planetaryum");
			if (preg_match('/\bschool visits?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'okul ziyaret', 0, 'UTF-8') !== false, "'$key' says okul ziyareti");
		}
	}
}
