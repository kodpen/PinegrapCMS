<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ferry design template's public pages
 * (includes/design_templates/pinegrap-ferry/pages_public.php): the pages
 * with their keys, a page per route under a slug of its own, every page
 * link names a page of the finished template and none a shop page, the
 * widgets the home places are the ones the other parts define, the route
 * pages open the booking request and the notice list with the route
 * chosen and show the route's sailings, fares and ports, the timetables
 * and fares pages show every route, the map links open in a new window,
 * search anchors and classes are the template's own, no fixed date, and
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

// The page keys of the finished template (_test_fy_page_keys()).
require_once(__DIR__ . '/design_template_ferry_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_fyp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry/pages_public.php';
	$fare_words = array();
	foreach (array_keys($routes) as $r) {
		foreach (array_keys($fare_kinds) as $k) {
			if ($fare($k, $r) !== '') $fare_words[$r][] = $fare($k, $r);
		}
	}
	$out = array('part' => $public_part, 'routes' => $routes, 'ports' => $ports, 'route_titles' => $route_titles,
		'notice_route_choices' => $notice_route_choices, 'fare_titles' => $fare_titles, 'fare_words' => $fare_words, 'fare_kinds' => $fare_kinds);
	return $out;
}

// Every node of a tree, depth first.
function _test_fyp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fyp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_fyp_pages()
{
	$pages = array();
	foreach (_test_fyp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_fyp_queries($tree, $page)
{
	$nodes = array();
	_test_fyp_nodes($tree, $nodes);
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

function test_ferry_public_pages_are_the_plans()
{
	$d = _test_fyp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'routes');
	foreach (array_keys($d['routes']) as $key) $expected[] = 'route_' . $key;
	$expected = array_merge($expected, array('ports', 'timetables', 'fares', 'onboard', 'travel', 'about', 'faq'));
	pg_assert_same($expected, array_keys(_test_fyp_pages()), 'the public pages, a page per route');
	$known = array_flip(_test_fy_page_keys());
	$names = array();
	foreach (_test_fyp_pages() as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
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
function test_ferry_public_links_name_template_pages()
{
	$known = array_flip(_test_fy_page_keys());
	$json = json_encode(_test_fyp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode(_test_fyp_pages(), JSON_UNESCAPED_SLASHES)), 'no ?view= link');
}

// The home places the latest travel notices, the Google reviews and the
// news, the widgets the notice and standard parts define; this part
// defines none.
function test_ferry_public_home_widgets()
{
	pg_assert_same(array(), _test_fyp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_fyp_pages() as $key => $p) {
		$nodes = array();
		_test_fyp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_notices', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A route's page opens the booking request and the notice list with the
// route chosen, in the words of their Route lists; its timetable books the
// same route, its fare table books a fare; it shows its own fares and both
// its ports.
function test_ferry_public_route_pages()
{
	$d = _test_fyp_load();
	$pages = _test_fyp_pages();
	foreach ($d['routes'] as $key => $r) {
		$tree = $pages['route_' . $key]['tree'];
		$json = json_encode($tree, JSON_UNESCAPED_UNICODE);
		foreach (_test_fyp_queries($tree, 'booking_request') as $q) {
			pg_assert_true(
				(isset($q['route']) && $q['route'] === $r['title']) || (isset($q['fare']) && in_array($q['fare'], $d['fare_titles'], true)),
				"route_$key: a booking link chooses this route or a fare");
		}
		$routed = array_filter(_test_fyp_queries($tree, 'booking_request'), function ($q) { return isset($q['route']); });
		pg_assert_true(count($routed) >= 2, "route_$key: the buttons and the timetable book the route");
		pg_assert_true(in_array($r['title'], $d['route_titles'], true), "route_$key: a booking route answer");
		pg_assert_same(array(array('route' => $r['title'])), _test_fyp_queries($tree, 'notices'), "route_$key: one notice link with the route");
		pg_assert_true(in_array($r['title'], $d['notice_route_choices'], true), "route_$key: a notice route answer");
		foreach ($d['fare_words'][$key] as $w) pg_assert_contains($w, $json, "route_$key: shows the fare $w");
		foreach (array($r['from'], $r['to']) as $port) pg_assert_contains('"id":"fy-port-' . $port . '"', $json, "route_$key: shows the port $port");
		pg_assert_contains($r['sailings']['high']['out'][0], $json, "route_$key: its first summer sailing");
	}
}

// The timetables page shows every route's table, the fares page every
// route's fares and a booking link per fare, the ports page every port.
function test_ferry_public_timetables_fares_ports()
{
	$d = _test_fyp_load();
	$pages = _test_fyp_pages();
	$json = json_encode($pages['timetables']['tree'], JSON_UNESCAPED_UNICODE);
	$routed = array();
	foreach (_test_fyp_queries($pages['timetables']['tree'], 'booking_request') as $q) {
		if (isset($q['route'])) $routed[] = $q['route'];
	}
	pg_assert_same($d['route_titles'], $routed, 'a booking link per route, in order');
	foreach ($d['routes'] as $key => $r) {
		foreach ($r['sailings']['low']['back'] as $t) pg_assert_contains('"text":"' . $t . '","_attrs":[{"name":"datetime","value":"' . $t . '"}]', $json, "timetables: winter return $t of $key");
	}
	$json = json_encode($pages['fares']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['fare_words'] as $key => $words) foreach ($words as $w) pg_assert_contains($w, $json, "fares: $w for $key");
	$fares = array();
	foreach (_test_fyp_queries($pages['fares']['tree'], 'booking_request') as $q) {
		if (isset($q['fare'])) $fares[] = $q['fare'];
	}
	$grouped = array();
	foreach (array('foot', 'vehicle', 'extra') as $group) {
		foreach ($d['fare_kinds'] as $f) if ($f['group'] === $group) $grouped[] = $f['title'];
	}
	pg_assert_same($grouped, $fares, 'a booking link per fare, by group');
	$json = json_encode($pages['ports']['tree']);
	foreach (array_keys($d['ports']) as $port) pg_assert_contains('"id":"fy-port-' . $port . '"', $json, "ports: $port");
}

// Every map link opens in a new window through the link's target, with
// its port's coordinates.
function test_ferry_public_map_links()
{
	$d = _test_fyp_load();
	$nodes = array();
	_test_fyp_nodes(_test_fyp_pages()['ports']['tree'], $nodes);
	$found = 0;
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, 'https://www.openstreetmap.org/') !== 0) continue;
		$found++;
		pg_assert_same('_blank', $n['props']['target'] ?? '', 'the map opens in a new window');
		foreach ($n['props']['_attrs'] ?? array() as $a) pg_assert_false($a['name'] === 'target', 'no target in _attrs');
	}
	pg_assert_same(count($d['ports']), $found, 'a map link per port');
	foreach ($d['ports'] as $key => $p) {
		list($lat, $lon) = array_map('trim', explode(',', $p['coordinates']));
		pg_assert_contains('mlat=' . $lat . '&mlon=' . $lon, json_encode(_test_fyp_pages()['ports']['tree'], JSON_UNESCAPED_SLASHES), "the map marks $key");
	}
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_ferry_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_fyp_pages() as $key => $p) {
		$nodes = array();
		_test_fyp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmn|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^opacity-/', $c), "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'fy-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old
// timetable a year later.
function test_ferry_public_no_fixed_dates()
{
	$json = json_encode(_test_fyp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December|Easter)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a booking request reads "rezervasyon
// talebi", a group enquiry "grup talebi", a travel notice "sefer duyurusu".
function test_ferry_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/Booking Requests?/i', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon tale', 0, 'UTF-8') !== false, "'$key' says rezervasyon talebi");
		if (preg_match('/Group Enquir(y|ies)/i', $key)) pg_assert_true(mb_stripos($tr, 'grup tale', 0, 'UTF-8') !== false, "'$key' says grup talebi");
		if (preg_match('/Travel Notices?/i', $key)) pg_assert_true(mb_stripos($tr, 'sefer duyuru', 0, 'UTF-8') !== false, "'$key' says sefer duyurusu");
	}
}
