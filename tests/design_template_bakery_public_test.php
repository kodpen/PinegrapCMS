<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bakery design template's public pages
 * (includes/design_templates/pinegrap-bakery/pages_public.php): the eight
 * pages of the plan in the public folder and without the shop, links that
 * name template pages and no shop page, the widgets only the home places,
 * a daily bake that lists every bake once under its kind, a cakes page
 * that offers every cake to order, a wholesale page that offers every
 * range, the template's ids and classes, no fixed dates and every literal
 * in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_bk_page_keys()).
require_once(__DIR__ . '/design_template_bakery_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_bkp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/pages_public.php';
	$out = array('part' => $public_part, 'bakes' => $bakes, 'categories' => $categories, 'cakes' => $cakes,
		'cake_choices' => $cake_choices, 'occasion_choices' => $occasion_choices, 'range_titles' => $range_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_bkp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bkp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_bkp_pages()
{
	$pages = array();
	foreach (_test_bkp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_bkp_queries($tree, $page)
{
	$nodes = array();
	_test_bkp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_bakery_public_pages_are_the_plans()
{
	$part = _test_bkp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'bakes', 'cakes', 'how_to_order', 'wholesale', 'visit', 'about', 'faq'), array_keys(_test_bkp_pages()), 'the eight public pages');
	$names = array();
	foreach (_test_bkp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_bakery_public_links_name_template_pages()
{
	$known = array_flip(_test_bk_page_keys());
	$json = json_encode(_test_bkp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_bk_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the classes list, the Google reviews and the notes, the
// widgets the classes and the standard parts define; this part defines
// none.
function test_bakery_public_home_widgets()
{
	pg_assert_same(array(), _test_bkp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_bkp_pages() as $key => $p) {
		$nodes = array();
		_test_bkp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_classes', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// The daily bake lists every bake once, in its kind's section; the anchors
// the home links are the sections'.
function test_bakery_public_daily_bake_lists_every_bake()
{
	$d = _test_bkp_load();
	$pages = _test_bkp_pages();
	$nodes = array();
	_test_bkp_nodes($pages['bakes']['tree'], $nodes);
	$names = array();
	$ids = array();
	foreach ($nodes as $n) {
		if (($n['props']['_label'] ?? '') === lang('Bake Name')) $names[] = $n['props']['text'];
		if (isset($n['props']['id'])) $ids[$n['props']['id']] = true;
	}
	$all = array();
	foreach ($d['bakes'] as $b) $all[] = $b['name'];
	sort($all);
	sort($names);
	pg_assert_same($all, $names, 'every bake once');
	foreach (array_keys($d['categories']) as $key) pg_assert_true(isset($ids['bky-cat-' . $key]), "a section for $key");
	foreach (array('home', 'about') as $page) {
		preg_match_all('/\{\{page:bakes\}\}#([a-z0-9-]+)/', json_encode($pages[$page]['tree']), $m);
		foreach ($m[1] as $id) pg_assert_true(isset($ids[$id]), "$page: anchor $id is on the daily bake");
	}
}

// The cakes page offers every cake to order with its cake and occasion
// chosen, the wholesale page every range: in the words the forms use.
function test_bakery_public_cards_open_the_requests()
{
	$d = _test_bkp_load();
	$pages = _test_bkp_pages();
	$named = array();
	foreach (_test_bkp_queries($pages['cakes']['tree'], 'cake_order') as $q) {
		pg_assert_true(in_array($q['cake'], $d['cake_choices'], true), 'a Cake answer');
		pg_assert_true(in_array($q['occasion'], $d['occasion_choices'], true), 'an Occasion answer');
		$named[] = $q['cake'];
	}
	pg_assert_same(count($d['cakes']), count(array_unique($named)), 'every cake on the cakes page');
	$ranges = array();
	foreach (_test_bkp_queries($pages['wholesale']['tree'], 'wholesale_enquiry') as $q) {
		pg_assert_true(in_array($q['range'], $d['range_titles'], true), 'a Range answer');
		$ranges[] = $q['range'];
	}
	pg_assert_same(count($d['range_titles']), count(array_unique($ranges)), 'every range on the wholesale page');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist and no faded
// text.
function test_bakery_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_bkp_pages() as $key => $p) {
		$nodes = array();
		_test_bkp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pmk|pwd|pch|pcn|pmu|pph|psh|ptv|pmz|pai)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false($c === 'ratio-3x4', "$key: Bootstrap has no ratio-3x4");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'bky-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_bakery_public_no_fixed_dates()
{
	$json = json_encode(_test_bkp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a baking class reads "atölye", wholesale
// "toptan"; shared keys whose Turkish names another thing are not used.
function test_bakery_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bBaking Class(es)?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		if (preg_match('/\bWholesale\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'toptan', 0, 'UTF-8') !== false, "'$key' says toptan");
	}
	// Classes reads Sınıflar, Testimonials Müşteri Yorumları.
	foreach (array('Classes', 'Class', 'Testimonials') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "the pages do not use lang('$k')");
	}
}
