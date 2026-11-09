<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Campsite design template's public pages
 * (includes/design_templates/pinegrap-campsite/pages_public.php): the
 * pages with their keys, a page per kind of stay under a slug of its own,
 * every page link names a page of the finished template and none a shop
 * page, the widgets the home places are the ones the other parts define,
 * the stay-kind pages open the stay request and the unit list with the
 * kind chosen, the prices page shows every season's price and opens the
 * request per season, the map link opens in a new window, search anchors
 * and classes are the template's own, no fixed date, and every literal
 * has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_cp_page_keys()).
require_once(__DIR__ . '/design_template_campsite_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_cpp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite/pages_public.php';
	$prices = array();
	foreach (array_keys($kinds) as $k) {
		foreach (array_keys($seasons) as $s) $prices[] = $season_price($k, $s);
	}
	$out = array('part' => $public_part, 'kinds' => $kinds, 'kind_titles' => $kind_titles, 'request_kind_choices' => $request_kind_choices,
		'season_titles' => $season_titles, 'prices' => $prices, 'park' => $park);
	return $out;
}

// Every node of a tree, depth first.
function _test_cpp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cpp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_cpp_pages()
{
	$pages = array();
	foreach (_test_cpp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_cpp_queries($tree, $page)
{
	$nodes = array();
	_test_cpp_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:' . $page . '}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_campsite_public_pages_are_the_plans()
{
	$d = _test_cpp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'stays');
	foreach (array_keys($d['kinds']) as $key) $expected[] = 'stay_' . $key;
	$expected = array_merge($expected, array('facilities', 'activities', 'prices', 'location', 'rules', 'about', 'faq'));
	pg_assert_same($expected, array_keys(_test_cpp_pages()), 'the public pages, a page per kind of stay');
	$names = array();
	foreach (_test_cpp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_campsite_public_links_name_template_pages()
{
	$known = array_flip(_test_cp_page_keys());
	$json = json_encode(_test_cpp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode(_test_cpp_pages(), JSON_UNESCAPED_SLASHES)), 'no ?view= link');
}

// The home places the featured units, the Google reviews and the news,
// the widgets the units and the standard parts define; this part defines
// none.
function test_campsite_public_home_widgets()
{
	pg_assert_same(array(), _test_cpp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_cpp_pages() as $key => $p) {
		$nodes = array();
		_test_cpp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_units', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A kind's page opens the stay request and the unit list with the kind
// chosen, in the words of the Kind lists, and shows its own prices.
function test_campsite_public_kind_links_choose()
{
	$d = _test_cpp_load();
	$pages = _test_cpp_pages();
	foreach ($d['kinds'] as $key => $k) {
		$tree = $pages['stay_' . $key]['tree'];
		$book = array_values(array_filter(_test_cpp_queries($tree, 'book'), function ($q) { return isset($q['kind']); }));
		pg_assert_same(array(array('kind' => $k['title'])), $book, "stay_$key: one stay request link with the kind");
		pg_assert_true(in_array($k['title'], $d['request_kind_choices'], true), "stay_$key: a stay request answer");
		pg_assert_same(array(array('kind' => $k['title'])), _test_cpp_queries($tree, 'units'), "stay_$key: the units of the kind");
		pg_assert_true(in_array($k['title'], $d['kind_titles'], true), "stay_$key: a Kind answer");
		// The price table of the kind: its season links only.
		foreach (_test_cpp_queries($tree, 'book') as $q) {
			pg_assert_true(isset($q['kind']) || (isset($q['season']) && in_array($q['season'], $d['season_titles'], true)), "stay_$key: a season link names a season");
		}
	}
}

// The prices page shows the price of every kind in every season and opens
// the stay request with each season chosen.
function test_campsite_public_prices_page()
{
	$d = _test_cpp_load();
	$tree = _test_cpp_pages()['prices']['tree'];
	$json = json_encode($tree, JSON_UNESCAPED_UNICODE);
	foreach ($d['prices'] as $price) pg_assert_contains($price, $json, "the prices page shows $price");
	$seasons = array();
	foreach (_test_cpp_queries($tree, 'book') as $q) {
		if (isset($q['season'])) $seasons[$q['season']] = true;
	}
	pg_assert_same($d['season_titles'], array_keys($seasons), 'a request link per season');
}

// The map opens in a new window through the link's target, with the
// park's coordinates.
function test_campsite_public_map_link()
{
	$d = _test_cpp_load();
	$nodes = array();
	_test_cpp_nodes(_test_cpp_pages()['location']['tree'], $nodes);
	$found = false;
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, 'https://www.openstreetmap.org/') !== 0) continue;
		$found = true;
		pg_assert_same('_blank', $n['props']['target'] ?? '', 'the map opens in a new window');
		list($lat, $lon) = array_map('trim', explode(',', $d['park']['coordinates']));
		pg_assert_contains('mlat=' . $lat . '&mlon=' . $lon, $href, 'the map marks the park');
		foreach ($n['props']['_attrs'] ?? array() as $a) pg_assert_false($a['name'] === 'target', 'no target in _attrs');
	}
	pg_assert_true($found, 'the location page links the map');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_campsite_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_cpp_pages() as $key => $p) {
		$nodes = array();
		_test_cpp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^opacity-/', $c), "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'cp-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old
// season a year later.
function test_campsite_public_no_fixed_dates()
{
	$json = json_encode(_test_cpp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December|Easter)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a stay request reads "konaklama talebi",
// a season "sezon", a pitch "yer", a cabin "kulübe".
function test_campsite_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bStay Requests?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'konaklama tale', 0, 'UTF-8') !== false, "'$key' says konaklama talebi");
		if (preg_match('/\bSeason\b/', $key)) pg_assert_true(mb_stripos($tr, 'sezon', 0, 'UTF-8') !== false, "'$key' says sezon");
		if (preg_match('/\bPitch(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'yer', 0, 'UTF-8') !== false, "'$key' says yer");
		if (preg_match('/\bCabins?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kulübe', 0, 'UTF-8') !== false, "'$key' says kulübe");
	}
}
