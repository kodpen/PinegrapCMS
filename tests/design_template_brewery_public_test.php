<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Craft Brewery design template's public pages
 * (includes/design_templates/pinegrap-brewery/pages_public.php): the four
 * pages with their keys, every page link names a page of the finished
 * template and none a shop page, the widgets the home places are the ones
 * the other parts define, the home shows every tour and review, the
 * taproom every beer on tap, every dish and the opening hours, search
 * anchors and classes are the template's own, no fixed date, and every
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

require_once(__DIR__ . '/design_template_brewery_test.php');

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_bwp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/pages_public.php';
	$out = array('part' => $public_part, 'beers' => $beers, 'tours' => $tours, 'testimonials' => $testimonials,
		'food' => $food, 'hours' => $hours, 'brewery' => $brewery, 'money' => $money, 'strength_text' => $strength_text);
	return $out;
}

// Every node of a tree, depth first.
function _test_bwp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bwp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_bwp_pages()
{
	$pages = array();
	foreach (_test_bwp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The texts of a page's tree.
function _test_bwp_texts($tree)
{
	$nodes = array();
	_test_bwp_nodes($tree, $nodes);
	$texts = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
	}
	return $texts;
}

function test_brewery_public_pages_are_the_plans()
{
	$part = _test_bwp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'taproom', 'about', 'faq'), array_keys(_test_bwp_pages()), 'the four public pages');
	foreach (_test_bwp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_brewery_public_links_name_template_pages()
{
	$known = array_flip(_test_bw_page_keys());
	$json = json_encode(_test_bwp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 6, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_bw_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured beers, the coming events and the news, the
// widgets the beers and events and the standard parts define; this part
// defines none.
function test_brewery_public_home_widgets()
{
	pg_assert_same(array(), _test_bwp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_bwp_pages() as $key => $p) {
		$nodes = array();
		_test_bwp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_beers', 'home_events', 'home_posts')), $widgets, 'only the home places widgets');
}

// The home has one h1 and shows every tour and every review; the taproom
// lists every beer on tap with its strength, every dish with its price
// and every day's hours, and its address.
function test_brewery_public_pages_show_the_data()
{
	$d = _test_bwp_load();
	$pages = _test_bwp_pages();
	foreach ($pages as $key => $p) {
		$nodes = array();
		_test_bwp_nodes($p['tree'], $nodes);
		$h1 = 0;
		foreach ($nodes as $n) {
			if (($n['props']['tag'] ?? '') === 'h1') $h1++;
		}
		pg_assert_same(1, $h1, "$key: one h1");
	}
	$home = _test_bwp_texts($pages['home']['tree']);
	foreach ($d['tours'] as $key => $t) pg_assert_true(in_array($t['title'], $home, true), "home: tour $key");
	foreach ($d['testimonials'] as $n => $q) pg_assert_true(in_array($q['quote'], $home, true), "home: review $n");
	$taproom = _test_bwp_texts($pages['taproom']['tree']);
	foreach ($d['beers'] as $key => $b) {
		pg_assert_true(in_array($b['name'], $taproom, true), "taproom: beer $key on tap");
		pg_assert_true(in_array(call_user_func($d['strength_text'], $key), $taproom, true), "taproom: beer $key strength");
	}
	foreach ($d['food'] as $n => $f) {
		pg_assert_true(in_array($f['title'], $taproom, true), "taproom: dish $n");
		pg_assert_true(in_array(call_user_func($d['money'], $f['price']), $taproom, true), "taproom: dish $n price");
	}
	foreach ($d['hours'] as $n => $h) pg_assert_true(in_array($h['days'], $taproom, true) && in_array($h['time'], $taproom, true), "taproom: hours $n");
	pg_assert_true(in_array($d['brewery']['address'], $taproom, true), 'taproom: the address');
	pg_assert_contains('{{page:book_online}}', json_encode($pages['taproom']['tree']), 'the taproom offers a private tasting');
	pg_assert_contains('{{page:tour_booking}}', json_encode($pages['about']['tree']), 'the about page offers a tour');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_brewery_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_bwp_pages() as $key => $p) {
		$nodes = array();
		_test_bwp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|pmz|pai)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'bw-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
	pg_assert_contains('pbw-hero', json_encode(_test_bwp_pages()['home']['tree']), 'the home hero');
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_brewery_public_no_fixed_dates()
{
	$json = json_encode(_test_bwp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a beer reads "bira", the taproom
// "tadım salonu", a tour "tur".
function test_brewery_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^(Our |All )?[Bb]eers?$/', $key)) pg_assert_true(mb_stripos($tr, 'bira', 0, 'UTF-8') !== false, "'$key' says bira");
		if (preg_match('/^(Visit the )?Taproom$/', $key)) pg_assert_true(mb_stripos($tr, 'tadım salonu', 0, 'UTF-8') !== false, "'$key' says tadım salonu");
		if (preg_match('/\b[Tt]ours?\b/', $key) && strpos($key, ',') === false) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
	}
}
