<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marina design template's public pages
 * (includes/design_templates/pinegrap-marina/pages_public.php): the pages
 * with their keys, a page per kind of berth under a slug of its own, every
 * page link names a page of the finished template and none a shop page,
 * the widgets the home places are the ones the other parts define, the
 * berth-kind pages open the berth request with the kind chosen, the rates
 * page shows every season's rate and opens the request per season, the
 * map link opens in a new window, search anchors and classes are the
 * template's own, no fixed date, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_mn_page_keys()).
require_once(__DIR__ . '/design_template_marina_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_mnp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/pages_public.php';
	$prices = array();
	$low_rates = array();
	foreach (array_keys($kinds) as $k) {
		foreach (array_keys($seasons) as $s) $prices[] = $season_rate($k, $s);
		$low_rates[$k] = $season_rate($k, 'low');
	}
	$out = array('part' => $public_part, 'kinds' => $kinds, 'request_kind_choices' => $request_kind_choices,
		'season_titles' => $season_titles, 'prices' => $prices, 'low_rates' => $low_rates, 'harbour' => $harbour);
	return $out;
}

// Every node of a tree, depth first.
function _test_mnp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mnp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_mnp_pages()
{
	$pages = array();
	foreach (_test_mnp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_mnp_queries($tree, $page)
{
	$nodes = array();
	_test_mnp_nodes($tree, $nodes);
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

function test_marina_public_pages_are_the_plans()
{
	$d = _test_mnp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'berths');
	foreach (array_keys($d['kinds']) as $key) $expected[] = 'berth_' . $key;
	$expected = array_merge($expected, array('rates', 'services', 'facilities', 'arriving', 'rules', 'about', 'faq'));
	pg_assert_same($expected, array_keys(_test_mnp_pages()), 'the public pages, a page per kind of berth');
	$names = array();
	foreach (_test_mnp_pages() as $key => $p) {
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
function test_marina_public_links_name_template_pages()
{
	$known = array_flip(_test_mn_page_keys());
	$json = json_encode(_test_mnp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode(_test_mnp_pages(), JSON_UNESCAPED_SLASHES)), 'no ?view= link');
}

// The home places the featured charter boats, the coming events, the
// Google reviews and the news, the widgets the charters, events and
// standard parts define; this part defines none.
function test_marina_public_home_widgets()
{
	pg_assert_same(array(), _test_mnp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_mnp_pages() as $key => $p) {
		$nodes = array();
		_test_mnp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_charters', 'home_events', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A kind's page opens the berth request with the kind chosen, in the
// words of the request's Kind list, and shows its own rates.
function test_marina_public_kind_links_choose()
{
	$d = _test_mnp_load();
	$pages = _test_mnp_pages();
	foreach ($d['kinds'] as $key => $k) {
		$tree = $pages['berth_' . $key]['tree'];
		$request = array_values(array_filter(_test_mnp_queries($tree, 'berth_request'), function ($q) { return isset($q['kind']); }));
		pg_assert_same(array(array('kind' => $k['title'])), $request, "berth_$key: one berth request link with the kind");
		pg_assert_true(in_array($k['title'], $d['request_kind_choices'], true), "berth_$key: a berth request answer");
		// The rate table of the kind: its season links only.
		foreach (_test_mnp_queries($tree, 'berth_request') as $q) {
			pg_assert_true(isset($q['kind']) || (isset($q['season']) && in_array($q['season'], $d['season_titles'], true)), "berth_$key: a season link names a season");
		}
		pg_assert_contains($d['low_rates'][$key], json_encode($tree, JSON_UNESCAPED_UNICODE), "berth_$key: its low season rate");
	}
}

// The rates page shows the rate of every kind in every season and opens
// the berth request with each season chosen.
function test_marina_public_rates_page()
{
	$d = _test_mnp_load();
	$tree = _test_mnp_pages()['rates']['tree'];
	$json = json_encode($tree, JSON_UNESCAPED_UNICODE);
	foreach ($d['prices'] as $price) pg_assert_contains($price, $json, "the rates page shows $price");
	$seasons = array();
	foreach (_test_mnp_queries($tree, 'berth_request') as $q) {
		if (isset($q['season'])) $seasons[$q['season']] = true;
	}
	pg_assert_same($d['season_titles'], array_keys($seasons), 'a request link per season');
}

// The map opens in a new window through the link's target, with the
// harbour's coordinates.
function test_marina_public_map_link()
{
	$d = _test_mnp_load();
	$nodes = array();
	_test_mnp_nodes(_test_mnp_pages()['arriving']['tree'], $nodes);
	$found = false;
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, 'https://www.openstreetmap.org/') !== 0) continue;
		$found = true;
		pg_assert_same('_blank', $n['props']['target'] ?? '', 'the map opens in a new window');
		list($lat, $lon) = array_map('trim', explode(',', $d['harbour']['coordinates']));
		pg_assert_contains('mlat=' . $lat . '&mlon=' . $lon, $href, 'the map marks the harbour');
		foreach ($n['props']['_attrs'] ?? array() as $a) pg_assert_false($a['name'] === 'target', 'no target in _attrs');
	}
	pg_assert_true($found, 'the arriving page links the map');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_marina_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_mnp_pages() as $key => $p) {
		$nodes = array();
		_test_mnp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^opacity-/', $c), "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'mn-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old
// season a year later.
function test_marina_public_no_fixed_dates()
{
	$json = json_encode(_test_mnp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December|Easter)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a berth request reads "bağlama talebi",
// a charter enquiry "kiralama talebi", a season "sezon".
function test_marina_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/Berth Requests?/i', $key)) pg_assert_true(mb_stripos($tr, 'bağlama tale', 0, 'UTF-8') !== false, "'$key' says bağlama talebi");
		if (preg_match('/Charter Enquir(y|ies)/i', $key)) pg_assert_true(mb_stripos($tr, 'kiralama tale', 0, 'UTF-8') !== false, "'$key' says kiralama talebi");
		if (preg_match('/Season/', $key)) pg_assert_true(mb_stripos($tr, 'sezon', 0, 'UTF-8') !== false, "'$key' says sezon");
	}
}
