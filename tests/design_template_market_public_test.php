<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marketplace design template's public pages
 * (includes/design_templates/pinegrap-market/pages_public.php): the seven
 * pages load from the parts, each has its key, folder, title and
 * description, every page link names a page of the finished template and
 * none needs the shop, every category has its anchored search section,
 * the search sections and questions have unique anchors, the template's
 * classes are its own, no date is written into the sample, and every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_market_test.php');

// The public pages as the template file reads them: parts.php first, then
// pages_public.php in the same scope. Read once per run.
function _test_market_public_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	include $dir . '/parts.php';
	$part = include $dir . '/pages_public.php';
	$out = array('part' => $part, 'categories' => $categories, 'sellers' => $sellers, 'products' => $market_products);
	return $out;
}

function _test_market_public_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_market_public_nodes($child, $out);
	}
}

function test_market_public_pages_are_the_plans()
{
	$d = _test_market_public_load();
	pg_assert_same(array(), $d['part']['widgets'], 'no widget of its own');
	$keys = array();
	foreach ($d['part']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		pg_assert_true($p['name'] !== '' && $p['title'] !== '' && $p['meta_description'] !== '', $p['key'] . ': name, title, description');
		pg_assert_false(isset($p['requires']), $p['key'] . ': stands without the shop');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': a page tree');
	}
	pg_assert_same(array('home', 'categories', 'how_it_works', 'sell', 'about', 'faq', 'gift_guide'), $keys);
}

function test_market_public_links_name_pages_that_stand_without_the_shop()
{
	$d = _test_market_public_load();
	$json = json_encode($d['part']['pages']);
	$known = array_flip(_test_market_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_market_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "public pages do not link to $key");
	}
	foreach (array('home_sellers', 'home_posts') as $w) pg_assert_contains('"templateWidget":"' . $w . '"', $json, "home places $w");
	pg_assert_contains('"templateShared":"maker_band"', $json, 'the maker band');
}

// Every category's anchor (#category-<key>, opened by the menu, footer and
// tiles) is a search section on the categories page; anchors are unique.
function test_market_public_anchors()
{
	$d = _test_market_public_load();
	foreach ($d['part']['pages'] as $p) {
		$nodes = array();
		_test_market_public_nodes($p['tree'], $nodes);
		$ids = array();
		$sections = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id'])) continue;
			pg_assert_false(isset($ids[$n['props']['id']]), $p['key'] . ': id ' . $n['props']['id'] . ' once');
			$ids[$n['props']['id']] = true;
			if (!empty($n['props']['searchSection'])) $sections[$n['props']['id']] = true;
		}
		if ($p['key'] === 'categories') {
			foreach (array_keys($d['categories']) as $key) pg_assert_true(isset($sections['category-' . $key]), "category-$key is a search section");
		}
		if ($p['key'] !== 'home') pg_assert_true(count($sections) > 0, $p['key'] . ': has search sections');
	}
}

function test_market_public_classes_and_dates()
{
	$d = _test_market_public_load();
	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_market_public_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pc|pl|plx|plw|psl)-/', $c), "class $c is not another template's");
		}
	}
	$json = json_encode($d['part']['pages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_market_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bmakers?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'üretici', 0, 'UTF-8') !== false, "'$key' says üretici");
		}
	}
}
