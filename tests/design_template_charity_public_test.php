<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Charity design template's public pages
 * (includes/design_templates/pinegrap-charity/pages_public.php): the six
 * pages load from the parts, each has its key, folder, title and
 * description, every page link names a page of the finished template and
 * none needs the shop, the pages place the list widgets of the other
 * parts, every cause has its anchored search section, ids are unique, the
 * template's classes are its own, no date is written into the sample, and
 * every literal has its Turkish.
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
require_once(__DIR__ . '/design_template_charity_test.php');

// The public pages as the template file reads them: parts.php first, then
// pages_public.php in the same scope. Read once per run.
function _test_charity_public_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	$part = include $dir . '/pages_public.php';
	$out = array('part' => $part, 'causes' => $causes, 'campaigns' => $campaigns);
	return $out;
}

function test_charity_public_pages_are_the_plans()
{
	$d = _test_charity_public_load();
	pg_assert_same(array(), $d['part']['widgets'], 'no widget of its own');
	$keys = array();
	foreach ($d['part']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		pg_assert_true($p['name'] !== '' && $p['title'] !== '' && $p['meta_description'] !== '', $p['key'] . ': name, title, description');
		pg_assert_false(isset($p['requires']), $p['key'] . ': stands without the shop');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': a page tree');
	}
	pg_assert_same(array('home', 'about', 'our_work', 'impact', 'transparency', 'faq'), $keys);
}

function test_charity_public_links_name_pages_that_stand_without_the_shop()
{
	$d = _test_charity_public_load();
	$json = json_encode($d['part']['pages']);
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_charity_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "public pages do not link to $key");
	}
	foreach (array('home_campaigns', 'home_events', 'home_posts', 'reports') as $w) pg_assert_contains('"templateWidget":"' . $w . '"', $json, "a page places $w");
	pg_assert_contains('"templateShared":"donate_band"', $json, 'the donate band');
	pg_assert_contains('{{page:donate}}', $json, 'the pages lead to the donate page');
}

// Every cause's anchor (#cause-<key>, opened by the footer and the tiles)
// is a search section on the our-work page; ids are unique on each page.
function test_charity_public_anchors()
{
	$d = _test_charity_public_load();
	foreach ($d['part']['pages'] as $p) {
		$nodes = array();
		_test_charity_nodes($p['tree'], $nodes);
		$ids = array();
		$sections = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id'])) continue;
			pg_assert_false(isset($ids[$n['props']['id']]), $p['key'] . ': id ' . $n['props']['id'] . ' once');
			$ids[$n['props']['id']] = true;
			if (!empty($n['props']['searchSection'])) $sections[$n['props']['id']] = true;
		}
		if ($p['key'] === 'our_work') {
			foreach (array_keys($d['causes']) as $key) pg_assert_true(isset($sections['cause-' . $key]), "cause-$key is a search section");
		}
		if ($p['key'] !== 'home') pg_assert_true(count($sections) > 0, $p['key'] . ': has search sections');
	}
}

// Every campaign shows on the our-work page under its cause.
function test_charity_public_every_campaign_under_its_cause()
{
	$d = _test_charity_public_load();
	$json = '';
	foreach ($d['part']['pages'] as $p) if ($p['key'] === 'our_work') $json = json_encode($p['tree']);
	foreach ($d['campaigns'] as $c) pg_assert_contains(json_encode($c['title']), $json, 'campaign on our work');
}

function test_charity_public_classes_and_dates()
{
	$d = _test_charity_public_load();
	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_charity_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pc|pl|plx|plw|psl)-/', $c), "class $c is not another template's");
		}
	}
	$json = json_encode($d['part']['pages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_charity_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bcampaigns?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'kampanya', 0, 'UTF-8') !== false, "'$key' says kampanya");
		}
	}
}
