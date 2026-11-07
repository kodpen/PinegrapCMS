<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Public Library design template's public pages
 * (includes/design_templates/pinegrap-library/pages_public.php): the pages
 * with their keys, a page per branch under a slug of its own, every page
 * link names a page of the finished template and none a shop page, the
 * widgets the home places are the ones the other parts define, the
 * filtered links name answers of their lists, each branch page shows its
 * own hours and books its own rooms, search anchors and classes are the
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

// The parts' helpers: _test_lb_nodes() and _test_lb_page_keys().
require_once __DIR__ . '/design_template_library_test.php';

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_lbp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/pages_public.php';
	$out = array('part' => $public_part, 'branches' => $branches, 'rooms' => $rooms,
		'lists' => array('format' => $format_titles, 'audience' => $audience_titles, 'kind' => $event_kind_titles, 'room' => $room_titles));
	return $out;
}

// The pages of this part, by key.
function _test_lbp_pages()
{
	$pages = array();
	foreach (_test_lbp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of every link to $page in a tree.
function _test_lbp_queries($tree, $page)
{
	$nodes = array();
	_test_lb_nodes($tree, $nodes);
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

function test_library_public_pages_are_the_plans()
{
	$d = _test_lbp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$expected = array('home', 'visit');
	foreach (array_keys($d['branches']) as $key) $expected[] = 'branch_' . $key;
	$expected = array_merge($expected, array('children', 'digital', 'spaces', 'about', 'faq'));
	pg_assert_same($expected, array_keys(_test_lbp_pages()), 'the public pages, a page per branch');
	$names = array();
	foreach (_test_lbp_pages() as $key => $p) {
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
function test_library_public_links_name_template_pages()
{
	$known = array_flip(_test_lb_page_keys());
	$json = json_encode(_test_lbp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the new arrivals, the coming events, the Google reviews
// and the news, the widgets the catalogue, the events and the standard
// parts define; this part defines none.
function test_library_public_home_widgets()
{
	pg_assert_same(array(), _test_lbp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_lbp_pages() as $key => $p) {
		$nodes = array();
		_test_lb_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_arrivals', 'home_events', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A link that opens the catalogue, the event list or the room booking
// with a choice made names an answer of that list.
function test_library_public_filtered_links_name_answers()
{
	$d = _test_lbp_load();
	$seen = 0;
	foreach (_test_lbp_pages() as $key => $p) {
		foreach (array('catalogue' => array('format', 'audience'), 'events' => array('kind'), 'room_booking' => array('room')) as $page => $params) {
			foreach (_test_lbp_queries($p['tree'], $page) as $q) {
				pg_assert_true(count($q) === 1 && in_array(key($q), $params, true), "$key: {{page:$page}} filters on a known field");
				pg_assert_true(in_array(current($q), $d['lists'][key($q)], true), "$key: '" . current($q) . "' is an answer of " . key($q));
				$seen++;
			}
		}
	}
	pg_assert_true($seen >= 8, 'filtered links found');
}

// Each branch page shows its own hours and address, and books only the
// rooms of that branch; the spaces page books every room once.
function test_library_public_branch_pages_are_their_own()
{
	$d = _test_lbp_load();
	$pages = _test_lbp_pages();
	foreach ($d['branches'] as $key => $b) {
		$tree = $pages['branch_' . $key]['tree'];
		$json = json_encode($tree, JSON_UNESCAPED_UNICODE);
		pg_assert_same($b['title'], $pages['branch_' . $key]['title'], "branch_$key: titled with the branch");
		pg_assert_contains(json_encode($b['address'], JSON_UNESCAPED_UNICODE), $json, "branch_$key: its address");
		foreach ($b['hours'] as $h) pg_assert_contains(json_encode($h['days'], JSON_UNESCAPED_UNICODE), $json, "branch_$key: its hours");
		$own = array();
		foreach ($d['rooms'] as $r) {
			if ($r['branch'] === $key) $own[] = array('room' => $r['title']);
		}
		pg_assert_same($own, _test_lbp_queries($tree, 'room_booking'), "branch_$key: books its own rooms");
	}
	$all = array();
	foreach ($d['rooms'] as $r) $all[] = array('room' => $r['title']);
	pg_assert_same($all, _test_lbp_queries($pages['spaces']['tree'], 'room_booking'), 'spaces: books every room');
	$visit = json_encode($pages['visit']['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array_keys($d['branches']) as $key) pg_assert_contains('{{page:branch_' . $key . '}}', $visit, "visit links branch_$key");
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_library_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_lbp_pages() as $key => $p) {
		$nodes = array();
		_test_lb_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'lib-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as old a year
// later.
function test_library_public_no_fixed_dates()
{
	$json = json_encode(_test_lbp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a branch reads "şube", the catalogue
// "katalog".
function test_library_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bBranch(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'şube', 0, 'UTF-8') !== false, "'$key' says şube");
		if (preg_match('/\bCatalogue\b/', $key)) pg_assert_true(mb_stripos($tr, 'katalo', 0, 'UTF-8') !== false, "'$key' says katalog (or its declined katalo-)");
	}
}
