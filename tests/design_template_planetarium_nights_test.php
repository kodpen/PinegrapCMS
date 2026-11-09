<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's telescope nights
 * (includes/design_templates/pinegrap-planetarium/nights.php): the pages
 * and widgets of the plan, each widget on its page and the next nights on
 * the home, the Telescope Nights form as a collection whose samples fill
 * its fields with answers of its lists and dates from today on, the status
 * as the office's field, the lists keeping the nights from today on,
 * nearest first, the record page binding the night's fields, the
 * template's ids and classes, heading levels, no fixed dates and every
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

require_once(__DIR__ . '/design_template_planetarium_shows_test.php');

// parts.php, cards.php, the public pages, shows.php and nights.php in one
// scope, as the template file reads them. Read once per run.
function _test_pln_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$shows_part = include $dir . '/shows.php';
	$part = include $dir . '/nights.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$out = array('part' => $part, 'home' => $home, 'public' => $public, 'shows_part' => $shows_part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'nights' => $nights, 'place_titles' => $place_titles, 'status_titles' => $night_status_titles, 'show_on' => $show_on);
	return $out;
}

function test_planetarium_nights_pages_and_widgets()
{
	$d = _test_pln_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('nights', 'night', 'night_new'), array_keys($d['pages']));
	pg_assert_same(array('night_form', 'nights_list', 'home_nights', 'night_detail'), array_keys($d['widgets']));
	$known = array_flip(_test_planetarium_page_keys());
	$names = array();
	foreach (array_merge($d['public']['pages'], $d['shows_part']['pages']) as $p) $names[$p['name']] = true;
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($key === 'night_new' ? 'private' : 'public', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
	}
	pg_assert_true(!empty($d['pages']['night_new']['noindex']), 'night_new is not indexed');
	pg_assert_contains('"templateShared":"staff_bar"', json_encode($d['pages']['night_new']['tree']), 'night_new is a staff page');
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') continue;
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_contains('"templateWidget":"home_nights"', json_encode($d['home']), 'the home page places home_nights');
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_planetarium_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
	pg_assert_contains('{{page:faq}}#plm-faq-nights', $all, 'the questions about the nights');
	pg_assert_contains('"id":"plm-faq-nights"', json_encode($d['public']['pages']), 'the anchor exists on the faq');
}

// The form is a collection with the field ids the cards read; every
// sample fills a field, its place and status are answers of their lists,
// and its date is today or later.
function test_planetarium_nights_form_and_samples()
{
	$d = _test_pln_load();
	$form = $d['pages']['night_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['nights']), count($form['sample_records']), 'one sample per night');
	$controls = _test_pls_controls($d['widgets']['night_form']['tree']);
	pg_assert_same(array('title', 'night_date', 'start_time', 'target', 'place', 'places', 'summary', 'photo_1', 'status'),
		array_keys($controls), 'the night\'s fields');
	pg_assert_same('date', _test_pls_type($d['widgets']['night_form']['tree'], 'night_date'), 'the date is a date');
	pg_assert_same($d['place_titles'], $controls['place']['options'], 'place offers its list');
	pg_assert_same($d['status_titles'], $controls['status']['options'], 'status offers its list');
	pg_assert_same(3, count($d['status_titles']), 'On, Called Off, Moved');
	pg_assert_true(!empty($controls['status']['cf']['office_use_only']), 'status is the office\'s field');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo takes pictures only');
	$today = date('Y-m-d');
	$prev = '';
	foreach ($form['sample_records'] as $sample) {
		foreach ($sample as $field => $value) pg_assert_true(isset($controls[$field]), "sample field $field is a field of the form");
		pg_assert_true(in_array($sample['place'], $d['place_titles'], true), "place '{$sample['place']}' is an answer");
		pg_assert_true(in_array($sample['status'], $d['status_titles'], true), "status '{$sample['status']}' is an answer");
		pg_assert_true((bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $sample['night_date']) && $sample['night_date'] >= $today, "{$sample['title']}: dated today or later");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}$/', $sample['start_time']), "{$sample['title']}: start time");
		pg_assert_true($sample['night_date'] >= $prev, "{$sample['title']}: the samples in date order");
		$prev = $sample['night_date'];
	}
	pg_assert_true(count($form['sample_records']) >= 3, 'the home finds three nights');
}

// The lists keep the nights from today on, nearest first; the home the
// next three.
function test_planetarium_nights_lists_from_today_on()
{
	$d = _test_pln_load();
	$from_today = array('field' => 'night_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date');
	foreach (array('nights_list', 'home_nights') as $key) {
		$c = $d['widgets'][$key]['config'];
		pg_assert_same('form_list_view', $c['regionType'], "$key: a list");
		pg_assert_same('{{tab:night_new}}', $c['custom_form_page_id'], "$key: of the Telescope Nights form");
		pg_assert_same('{{tab:night}}', $c['detail_page_id'], "$key: opens the night");
		pg_assert_same(array('night_date', 'ASC'), array($c['order_by_field'], $c['order_by_direction']), "$key: nearest first");
		pg_assert_same(array($from_today), $c['filters'], "$key: from today on");
		pg_assert_true(trim((string)$c['empty_message']) !== '', "$key: says when nothing is planned");
	}
	pg_assert_same(3, $d['widgets']['home_nights']['config']['max_results'], 'three on the home');
	pg_assert_true(!empty($d['widgets']['nights_list']['config']['search_enabled']), 'the list has a search');
	// The nights page explains each status with its pill.
	$page = json_encode($d['pages']['nights']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['status_titles'] as $title) pg_assert_contains('"text":' . json_encode($title, JSON_UNESCAPED_UNICODE), $page, "the nights page explains $title");
}

// Every value of the lists and the night's page is bound to a field of
// the form; the night's page has its found and not-found states.
function test_planetarium_nights_bind_the_form()
{
	$d = _test_pln_load();
	$fields = array_keys(_test_pls_controls($d['widgets']['night_form']['tree']));
	foreach (array('nights_list', 'home_nights', 'night_detail') as $key) {
		$bound = array();
		foreach (_test_plp_nodes($d['widgets'][$key]['tree']) as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $name => $token) {
				if ($name === 'eo_visible_if' || in_array($token, array('form_item_view', 'not_found', '__edit_url', 'can_edit'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
				$bound[$token] = true;
			}
		}
		foreach (array('title', 'night_date', 'start_time', 'target', 'place', 'places', 'status', 'photo_1') as $token) pg_assert_true(isset($bound[$token]), "$key reads $token");
	}
	$detail = json_encode($d['widgets']['night_detail']['tree']);
	foreach (array('record_found', 'record_not_found') as $flag) pg_assert_contains('"' . $flag . '"', $detail, "the night page has $flag");
	pg_assert_contains('"summary"', $detail, 'the night page reads the summary');
	pg_assert_contains('"data-bs-theme","value":"dark"', $detail, 'the facts on the night ground');
}

function test_planetarium_nights_ids_classes_and_headings()
{
	$d = _test_pln_load();
	$ids = array();
	foreach (array_merge($d['public']['pages'], $d['shows_part']['pages']) as $p) {
		foreach (_test_plp_nodes($p['tree']) as $n) {
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '') $ids[$id] = true;
		}
	}
	foreach ($d['shows_part']['widgets'] as $w) {
		foreach (_test_plp_nodes($w['tree']) as $n) {
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
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'btn-outline-light', 'text-muted'), true), "$key: no fixed colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'plm-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once, beside the other pages' ids");
			$ids[$id] = true;
		}
	}
	// The nights page: one h1, the list's cards (h3) under its own h2.
	$levels = array(3);
	foreach (_test_plp_nodes($d['pages']['nights']['tree']) as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'nights: one h1');
	$set = array_values(array_unique($levels));
	sort($set);
	pg_assert_same(range(1, max($set)), $set, 'nights: no heading level skipped');
	// The night's page: its title is the h1, the rest h2.
	$levels = array();
	foreach (_test_plp_nodes($d['widgets']['night_detail']['tree']) as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'night: one h1');
	$set = array_values(array_unique($levels));
	sort($set);
	pg_assert_same(array(1, 2), $set, 'night: h1 and h2 only');
}

// The sample dates are counted from today; nothing else carries a year or
// a month.
function test_planetarium_nights_no_fixed_dates()
{
	$d = _test_pln_load();
	$part = $d['part'];
	unset($part['pages'][2]['form']['sample_records']);
	$json = rawurldecode(json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	foreach ($d['nights'] as $n) {
		$date = $d['show_on']($n['day']);
		$json = str_replace(array(date('d.m.Y', strtotime($date)), date('m.Y', strtotime($date)), $date), '', $json);
	}
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_planetarium_nights_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/nights.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/\btelescope nights?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'teleskop gece', 0, 'UTF-8') !== false, "'$key' says teleskop gecesi");
	}
}
