<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's dome shows
 * (includes/design_templates/pinegrap-planetarium/shows.php): the pages and
 * widgets of the plan, each widget on its page and today's shows on the
 * home, the Dome Shows form as a collection whose samples fill its fields
 * with answers of its lists and dates from today on, the lists keeping the
 * shows from today on, nearest first, by the audience in the address, the
 * record page binding the show's fields, the template's ids and classes,
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

require_once(__DIR__ . '/design_template_planetarium_public_test.php');

// parts.php, cards.php, the public pages and shows.php in one scope, as
// the template file reads them. Read once per run.
function _test_pls_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/shows.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$out = array('part' => $part, 'home' => $home, 'public' => $public, 'pages' => $pages, 'widgets' => $part['widgets'],
		'shows' => $shows, 'audience_titles' => $audience_titles, 'featured' => $ppm_featured_choices,
		'show_on' => $show_on, 'list' => $ppm_record_list, 'filter' => $ppm_filter);
	return $out;
}

// The controls of a form tree by name: tag, answers and _cf.
function _test_pls_controls($tree)
{
	$out = array();
	foreach (_test_plp_nodes($tree) as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			$v = _test_planetarium_attr($o, 'value');
			if ($v !== null && $v !== '') $options[] = $v;
		}
		$out[_test_planetarium_attr($n, 'name')] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

function test_planetarium_shows_pages_and_widgets()
{
	$d = _test_pls_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('shows', 'show', 'show_new'), array_keys($d['pages']));
	pg_assert_same(array('show_form', 'shows_list', 'home_shows', 'show_detail'), array_keys($d['widgets']));
	$known = array_flip(_test_planetarium_page_keys());
	$names = array();
	foreach ($d['public']['pages'] as $p) $names[$p['name']] = true;
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($key === 'show_new' ? 'private' : 'public', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
	}
	pg_assert_true(!empty($d['pages']['show_new']['noindex']), 'show_new is not indexed');
	pg_assert_contains('"templateShared":"staff_bar"', json_encode($d['pages']['show_new']['tree']), 'show_new is a staff page');
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') continue;
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_contains('"templateWidget":"home_shows"', json_encode($d['home']), 'the home page places home_shows');
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_planetarium_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
}

// The form is a collection with the field ids the cards read; every
// sample fills a field, its audience is an answer of the list, and its
// date is today or later.
function test_planetarium_shows_form_and_samples()
{
	$d = _test_pls_load();
	$form = $d['pages']['show_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['shows']), count($form['sample_records']), 'one sample per show');
	$controls = _test_pls_controls($d['widgets']['show_form']['tree']);
	pg_assert_same(array('title', 'show_date', 'showtimes', 'audience', 'language', 'length', 'age', 'summary', 'details', 'photo_1', 'featured'),
		array_keys($controls), 'the show\'s fields');
	pg_assert_same('date', _test_pls_type($d['widgets']['show_form']['tree'], 'show_date'), 'the date is a date');
	pg_assert_same($d['audience_titles'], $controls['audience']['options'], 'audience offers its list');
	pg_assert_same($d['featured'], $controls['featured']['options'], 'featured offers its list');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the office\'s field');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo takes pictures only');
	$today = date('Y-m-d');
	$featured_today = 0;
	foreach ($form['sample_records'] as $sample) {
		foreach ($sample as $field => $value) pg_assert_true(isset($controls[$field]), "sample field $field is a field of the form");
		pg_assert_true(in_array($sample['audience'], $d['audience_titles'], true), "audience '{$sample['audience']}' is an answer");
		pg_assert_true(in_array($sample['featured'], $d['featured'], true), 'featured is an answer');
		pg_assert_true((bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $sample['show_date']) && $sample['show_date'] >= $today, "{$sample['title']}: dated today or later");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}(, [0-9]{2}:[0-9]{2})*$/', $sample['showtimes']), "{$sample['title']}: showtimes");
		pg_assert_true(strpos($sample['details'], "\n") !== false, "{$sample['title']}: details one per line");
		if ($sample['featured'] === $d['featured'][0] && $sample['show_date'] === $today) $featured_today++;
	}
	pg_assert_same(3, $featured_today, 'the home finds three featured shows today');
}

// The type attribute of the input named $name.
function _test_pls_type($tree, $name)
{
	foreach (_test_plp_nodes($tree) as $n) {
		if (_test_planetarium_attr($n, 'name') === $name) return _test_planetarium_attr($n, 'type');
	}
	return null;
}

// The lists keep the shows from today on, nearest first; the show list
// by the audience in the address, the home's today's featured three.
function test_planetarium_shows_lists_from_today_on()
{
	$d = _test_pls_load();
	foreach (array('shows_list', 'home_shows') as $key) {
		$c = $d['widgets'][$key]['config'];
		pg_assert_same('form_list_view', $c['regionType'], "$key: a list");
		pg_assert_same('{{tab:show_new}}', $c['custom_form_page_id'], "$key: of the Dome Shows form");
		pg_assert_same('{{tab:show}}', $c['detail_page_id'], "$key: opens the show");
		pg_assert_same(array('show_date', 'ASC'), array($c['order_by_field'], $c['order_by_direction']), "$key: nearest first");
		pg_assert_true(trim((string)$c['empty_message']) !== '', "$key: says when nothing is on");
	}
	$list = $d['widgets']['shows_list']['config'];
	pg_assert_same(array('field' => 'show_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
		$list['filters'][0], 'the list from today on');
	pg_assert_same(array('field' => 'audience', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'audience'),
		$list['filters'][1], 'the list by ?audience=');
	$home = $d['widgets']['home_shows']['config'];
	pg_assert_same(3, $home['max_results'], 'three on the home');
	pg_assert_same(array('field' => 'show_date', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'current date'), $home['filters'][0], 'today\'s');
	pg_assert_same(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $d['featured'][0], 'dynamic_value' => ''), $home['filters'][1], 'the featured ones');
	// The audience links open the list in the list's own words.
	foreach (array($d['pages']['shows']['tree'], $d['home']) as $tree) {
		$q = _test_plp_queries($tree, 'shows');
		pg_assert_same(array('audience'), array_keys($q), 'only ?audience= opens the list');
		foreach ($q['audience'] as $v) pg_assert_true(in_array($v, $d['audience_titles'], true), "?audience=$v is an answer");
	}
}

// Every value of the lists and the show's page is bound to a field of the
// form; the show's page has its found and not-found states.
function test_planetarium_shows_bind_the_form()
{
	$d = _test_pls_load();
	$fields = array_keys(_test_pls_controls($d['widgets']['show_form']['tree']));
	foreach (array('shows_list', 'home_shows', 'show_detail') as $key) {
		$bound = array();
		foreach (_test_plp_nodes($d['widgets'][$key]['tree']) as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $name => $token) {
				if ($name === 'eo_visible_if' || in_array($token, array('form_item_view', 'not_found', '__edit_url', 'can_edit'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
				$bound[$token] = true;
			}
		}
		foreach (array('title', 'show_date', 'showtimes', 'audience', 'length', 'age', 'language', 'summary', 'photo_1') as $token) pg_assert_true(isset($bound[$token]), "$key reads $token");
	}
	$detail = json_encode($d['widgets']['show_detail']['tree']);
	foreach (array('record_found', 'record_not_found') as $flag) pg_assert_contains('"' . $flag . '"', $detail, "the show page has $flag");
	pg_assert_contains('"details"', $detail, 'the show page reads the details');
	pg_assert_contains('"data-bs-theme","value":"dark"', $detail, 'the facts on the night ground');
	$q = _test_plp_queries($d['widgets']['show_detail']['tree'], 'visit_request');
	pg_assert_same(array('visit'), array_keys($q), 'the school visit opens with its kind');
	pg_assert_true(in_array($q['visit'][0], _test_plp_load()['visit_choices'], true), 'the kind is an answer');
}

function test_planetarium_shows_ids_classes_and_headings()
{
	$d = _test_pls_load();
	$ids = array();
	foreach ($d['public']['pages'] as $p) {
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
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'btn-outline-light', 'text-muted'), true), "$key: no fixed colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'plm-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once, beside the public pages' ids");
			$ids[$id] = true;
		}
	}
	// The shows page: one h1, the list's cards (h3) under its own h2.
	$levels = array(3);
	foreach (_test_plp_nodes($d['pages']['shows']['tree']) as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'shows: one h1');
	$set = array_values(array_unique($levels));
	sort($set);
	pg_assert_same(range(1, max($set)), $set, 'shows: no heading level skipped');
	// The show's page: its title is the h1, the rest h2.
	$levels = array();
	foreach (_test_plp_nodes($d['widgets']['show_detail']['tree']) as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'show: one h1');
	$set = array_values(array_unique($levels));
	sort($set);
	pg_assert_same(array(1, 2), $set, 'show: h1 and h2 only');
}

// The sample dates are counted from today; nothing else carries a year or
// a month.
function test_planetarium_shows_no_fixed_dates()
{
	$d = _test_pls_load();
	$part = $d['part'];
	unset($part['pages'][2]['form']['sample_records']);
	$json = rawurldecode(json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	foreach ($d['shows'] as $s) {
		$date = $d['show_on']($s['day']);
		$json = str_replace(array(date('d.m.Y', strtotime($date)), date('m.Y', strtotime($date)), $date), '', $json);
	}
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_planetarium_shows_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/shows.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/\bdome\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kubbe', 0, 'UTF-8') !== false, "'$key' says kubbe");
		if (preg_match('/\bshowtimes?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'seans', 0, 'UTF-8') !== false, "'$key' says seans");
	}
}
