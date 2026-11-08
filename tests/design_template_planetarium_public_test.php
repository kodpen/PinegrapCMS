<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's public pages
 * (includes/design_templates/pinegrap-planetarium/pages_public.php): the
 * pages of the plan in the public folder and without the shop, links that
 * name template pages and no shop page, anchors that exist, the widgets
 * only the home places, the home's own order opening on tonight's sky, the
 * Planetarium palette blocks drawn with their classes and rules, prices
 * from the one price table, form and filter links in the lists' own words,
 * the template's ids and classes, heading levels, no fixed dates and every
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

require_once(__DIR__ . '/design_template_planetarium_test.php');

// parts.php, cards.php and the public pages in one scope, as the template
// file reads them. Read once per run.
function _test_plp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public_part = include $dir . '/pages_public.php';
	$out = array('part' => $public_part, 'money' => $money, 'ticket_prices' => $ticket_prices, 'membership_price_text' => $membership_price_text,
		'memberships' => $memberships, 'audience_titles' => $audience_titles, 'grade_titles' => $grade_titles, 'visit_choices' => $visit_choices);
	return $out;
}

function _test_plp_pages()
{
	$pages = array();
	foreach (_test_plp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function _test_plp_nodes($tree)
{
	$nodes = array();
	_test_planetarium_nodes($tree, $nodes);
	return $nodes;
}

function _test_plp_has_class($node, $class)
{
	return in_array($class, preg_split('/\s+/', (string)($node['props']['cssClass'] ?? '')), true);
}

// The links of a tree that open $page with a query, as name => values.
function _test_plp_queries($tree, $page)
{
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach (_test_plp_nodes($tree) as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

function test_planetarium_public_pages_are_the_plans()
{
	$part = _test_plp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'tonight', 'visit', 'tickets', 'memberships', 'observatory', 'faq'), array_keys(_test_plp_pages()), 'the public pages');
	$known = array_flip(_test_planetarium_page_keys());
	$names = array();
	foreach (_test_plp_pages() as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
		$json = json_encode($p['tree']);
		foreach (array('account_bar', 'header', 'footer') as $shared) pg_assert_contains('"templateShared":"' . $shared . '"', $json, "$key: the $shared");
	}
}

function test_planetarium_public_links_name_template_pages()
{
	$known = array_flip(_test_planetarium_page_keys());
	$json = json_encode(_test_plp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) >= 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_planetarium_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	// An anchor a link names is a section of the page it opens.
	foreach (_test_plp_pages() as $key => $p) {
		preg_match_all('/"href":"(?:\{\{page:([a-z_]+)\}\})?#([a-z0-9-]+)"/', json_encode($p['tree']), $a, PREG_SET_ORDER);
		foreach ($a as $hit) {
			$target = $hit[1] !== '' ? $hit[1] : $key;
			pg_assert_contains('"id":"' . $hit[2] . '"', json_encode(_test_plp_pages()[$target]['tree']), "$key: #{$hit[2]} is on $target");
		}
	}
}

function test_planetarium_public_home_widgets()
{
	pg_assert_same(array(), _test_plp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_plp_pages() as $key => $p) {
		foreach (_test_plp_nodes($p['tree']) as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_shows', 'home_nights', 'home_posts')), $widgets, 'only the home places widgets');
}

// The home opens on tonight's sky on the night ground (the telescope
// status first, its title the page's h1, no hero slogan), then the shows,
// the nights, the moon strip and the news; no figures row, quotes or
// questions on it.
function test_planetarium_public_home_order()
{
	$home = _test_plp_pages()['home'];
	$main = null;
	foreach (_test_plp_nodes($home['tree']) as $n) if (($n['props']['tag'] ?? '') === 'main') $main = $n;
	pg_assert_true($main !== null, 'the home has a main');
	$ids = array();
	foreach ($main['children'] as $section) $ids[] = $section['props']['id'] ?? '';
	pg_assert_same(array('plm-home-tonight', 'plm-home-shows', 'plm-home-nights', 'plm-home-moon', ''), $ids, 'the home\'s order');
	pg_assert_same('dark', _test_planetarium_attr($main['children'][0], 'data-bs-theme'), 'tonight is on the night ground');
	pg_assert_same('dark', _test_planetarium_attr($main['children'][3], 'data-bs-theme'), 'the moon is on the night ground');
	$first_heading = null;
	foreach (_test_plp_nodes($main['children'][0]) as $n) {
		if ($first_heading === null && preg_match('/^h[1-6]$/', (string)($n['props']['tag'] ?? ''))) $first_heading = $n['props']['tag'];
	}
	pg_assert_same('h1', $first_heading, 'tonight\'s title is the home\'s h1');
	$json = json_encode($home['tree']);
	foreach (array('"accordion"', 'Testimonial', 'Figure') as $not) pg_assert_false(strpos($json, $not) !== false, "no $not on the home");
}

// The blocks: tonight with its status pill, dark hours as times and the
// current cloud step; the moon's eight phases with three telescope nights;
// the four objects with their seen-with pills. Every pg-sky class is a
// rule of the Planetarium family, and every entrance is one the
// interaction normalizer keeps.
function test_planetarium_public_places_the_palette_blocks()
{
	$pages = _test_plp_pages();
	foreach (array('home' => 'plm-home-tonight', 'tonight' => 'plm-tonight') as $key => $id) {
		$section = null;
		foreach (_test_plp_nodes($pages[$key]['tree']) as $n) if (($n['props']['id'] ?? '') === $id) $section = $n;
		pg_assert_true($section !== null, "$key: tonight's sky");
		$times = 0;
		$current = 0;
		foreach (_test_plp_nodes($section) as $n) {
			if (($n['props']['tag'] ?? '') === 'time') {
				$times++;
				pg_assert_same($n['props']['text'], _test_planetarium_attr($n, 'datetime'), "$key: a time carries its value");
				pg_assert_true(_test_plp_has_class($n, 'pg-sky-time'), "$key: a time in tabular figures");
			}
			if (_test_planetarium_attr($n, 'aria-current') === 'true') $current++;
		}
		pg_assert_same(12, $times, "$key: four dark hours and four planets' rise and set");
		pg_assert_same(1, $current, "$key: tonight's cloud step is current");
		pg_assert_contains(json_encode(lang('Telescopes open tonight')), json_encode($section), "$key: the telescope status");
	}
	foreach (array('home', 'tonight') as $key) {
		$phases = 0;
		$marked = 0;
		foreach (_test_plp_nodes($pages[$key]['tree']) as $n) {
			if (($n['props']['_label'] ?? '') === lang('Phase')) {
				$phases++;
				if (strpos(json_encode($n), json_encode(lang('Telescope night'))) !== false) $marked++;
			}
		}
		pg_assert_same(8, $phases, "$key: eight moon phases");
		pg_assert_same(3, $marked, "$key: three telescope nights marked");
	}
	$objects = 0;
	foreach (_test_plp_nodes($pages['tonight']['tree']) as $n) if (($n['props']['_label'] ?? '') === lang('Object Card')) $objects++;
	pg_assert_same(4, $objects, 'tonight: four objects through the telescope');
	// An orb only decorates; the words beside it carry the meaning.
	foreach ($pages as $key => $p) {
		foreach (_test_plp_nodes($p['tree']) as $n) {
			if (_test_plp_has_class($n, 'pg-sky-orb')) pg_assert_same('true', _test_planetarium_attr($n, 'aria-hidden'), "$key: an orb is hidden");
		}
	}
	$rules = _pg_designer_block_css_parse((string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_planetarium.js'));
	preg_match_all('/\bpg-[a-z0-9-]+/', json_encode($pages), $m);
	foreach (array('pg-sky-orb', 'pg-sky-time', 'pg-sky-wane') as $class) pg_assert_true(in_array($class, $m[0], true), "$class is used");
	foreach (array_unique($m[0]) as $class) pg_assert_true(isset($rules['rules'][$class]), "$class is a rule of the Planetarium family");
	$fx = array();
	foreach ($pages as $key => $p) {
		foreach (_test_plp_nodes($p['tree']) as $n) {
			if (!isset($n['props']['_fx'])) continue;
			$kept = pg_fx_normalize($n['props']['_fx']);
			pg_assert_true(is_array($kept) && $kept['effect'] === $n['props']['_fx']['effect'], "$key: an interaction the normalizer keeps");
			$fx[$n['props']['_fx']['effect']] = true;
		}
	}
	ksort($fx);
	pg_assert_same(array('fade', 'parallax', 'slide-up'), array_keys($fx), 'rise, fade and the sky photo\'s drift');
}

// The tickets and memberships pages say the prices of the one table.
function test_planetarium_public_prices_are_the_tables()
{
	$d = _test_plp_load();
	$pages = _test_plp_pages();
	$tickets = json_encode($pages['tickets']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['ticket_prices'] as $key => $prices) pg_assert_contains(json_encode($d['money']($prices), JSON_UNESCAPED_UNICODE), $tickets, "ticket $key: its price");
	$memberships = json_encode($pages['memberships']['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($d['memberships']) as $key) pg_assert_contains(json_encode($d['membership_price_text']($key), JSON_UNESCAPED_UNICODE), $memberships, "membership $key: its price");
}

// Filter and form links use the lists' own answers; the visit page links
// every grade.
function test_planetarium_public_links_use_the_answers()
{
	$d = _test_plp_load();
	foreach (_test_plp_pages() as $key => $p) {
		$q = _test_plp_queries($p['tree'], 'shows');
		foreach ($q['audience'] ?? array() as $v) pg_assert_true(in_array($v, $d['audience_titles'], true), "$key: an audience answer");
		$q = _test_plp_queries($p['tree'], 'visit_request');
		foreach ($q['grade'] ?? array() as $v) pg_assert_true(in_array($v, $d['grade_titles'], true), "$key: a grade answer");
		foreach ($q['visit'] ?? array() as $v) pg_assert_true(in_array($v, $d['visit_choices'], true), "$key: a visit answer");
	}
	pg_assert_same($d['grade_titles'], _test_plp_queries(_test_plp_pages()['visit']['tree'], 'visit_request')['grade'], 'the visit page links every grade');
	pg_assert_same($d['audience_titles'], _test_plp_queries(_test_plp_pages()['home']['tree'], 'shows')['audience'], 'the home links every audience');
}

function test_planetarium_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_plp_pages() as $key => $p) {
		foreach (_test_plp_nodes($p['tree']) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (preg_match('/^([a-z]{3})-/', $c, $pre) && $pre[1][0] === 'p') pg_assert_true($pre[1] === 'ppm', "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted'), true), "$key: no fixed colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'plm-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	pg_assert_true(count($ids) > 20, 'search anchors found');
}

// Heading levels: one h1 per page, no level skipped.
function test_planetarium_public_heading_levels()
{
	foreach (_test_plp_pages() as $key => $p) {
		$levels = array();
		foreach (_test_plp_nodes($p['tree']) as $n) {
			$tag = (string)($n['props']['tag'] ?? '');
			if (preg_match('/^h([1-6])$/', $tag, $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

function test_planetarium_public_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_plp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the pages has its Turkish, with the template's words.
function test_planetarium_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		// A page name is an address in plain letters (gozlemevi).
		if (preg_match('/^[a-z_]+$/', $key)) continue;
		if (preg_match('/\bDome\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kubbe', 0, 'UTF-8') !== false, "'$key' says kubbe");
		if (preg_match('/\bObservatory\b/i', $key)) pg_assert_true(mb_stripos($tr, 'gözlemev', 0, 'UTF-8') !== false, "'$key' says gözlemevi");
		if (preg_match('/\bTelescope Nights?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'teleskop gece', 0, 'UTF-8') !== false, "'$key' says teleskop gecesi");
		if ($key === 'News') pg_assert_same('Haberler', $tr, 'News is Haberler');
	}
}
