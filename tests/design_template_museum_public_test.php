<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Museum design template's public pages
 * (includes/design_templates/pinegrap-museum/pages_public.php): the pages
 * with their keys, every page link names a page of the finished template
 * and none a shop page, the widgets the home places are the ones the other
 * parts define, the visit page lists every ticket and the support page
 * every tier with the price the shop will show, the department cards open
 * the highlights filtered, search anchors and classes are the template's
 * own, no fixed date, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// _test_mz_page_keys(): the pages of the finished template.
require_once __DIR__ . '/design_template_museum_test.php';

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_mzp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/pages_public.php';
	$admission_texts = array();
	foreach (array_keys($admission) as $key) $admission_texts[$key] = $admission_price_text($key);
	$tier_texts = array();
	foreach (array_keys($tiers) as $key) $tier_texts[$key] = $tier_price_text($key);
	$out = array('part' => $public_part, 'admission' => $admission, 'tiers' => $tiers, 'departments' => $departments,
		'admission_texts' => $admission_texts, 'tier_texts' => $tier_texts, 'opening_hours' => $opening_hours, 'objects' => $objects);
	return $out;
}

// Every node of a tree, depth first.
function _test_mzp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mzp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_mzp_pages()
{
	$pages = array();
	foreach (_test_mzp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The texts of a tree.
function _test_mzp_texts($tree)
{
	$nodes = array();
	_test_mzp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $out[] = $n['props']['text'];
	}
	return $out;
}

function test_museum_public_pages_are_the_plans()
{
	$d = _test_mzp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'visit', 'support', 'about', 'faq'), array_keys(_test_mzp_pages()), 'the public pages');
	$names = array();
	foreach (_test_mzp_pages() as $key => $p) {
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
// page that only the shop makes, so the pages stand without it.
function test_museum_public_links_name_template_pages()
{
	$known = array_flip(_test_mz_page_keys());
	$json = json_encode(_test_mzp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) >= 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('tickets', 'membership', 'product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the current exhibitions, the coming events, the Google
// reviews and the news, the widgets the other parts define; this part
// defines none.
function test_museum_public_home_widgets()
{
	pg_assert_same(array(), _test_mzp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_mzp_pages() as $key => $p) {
		$nodes = array();
		_test_mzp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_exhibitions', 'home_events', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// The visit page lists every ticket with the price the shop shows and the
// opening hours day by day; the support page lists every tier the same way.
function test_museum_public_prices_are_the_shops()
{
	$d = _test_mzp_load();
	$pages = _test_mzp_pages();
	$visit = _test_mzp_texts($pages['visit']['tree']);
	foreach ($d['admission'] as $key => $a) {
		pg_assert_true(in_array($a['title'], $visit, true), "visit: the $key ticket");
		pg_assert_true(in_array($d['admission_texts'][$key], $visit, true), "visit: the $key price");
	}
	foreach ($d['opening_hours'] as $h) pg_assert_true(in_array($h['days'], $visit, true), 'visit: the hours of ' . $h['days']);
	$support = _test_mzp_texts($pages['support']['tree']);
	foreach ($d['tiers'] as $key => $t) {
		pg_assert_true(in_array($t['title'], $support, true), "support: the $key tier");
		pg_assert_true(in_array($d['tier_texts'][$key], $support, true), "support: the $key price");
	}
	pg_assert_contains('{{page:book_online}}', json_encode($pages['visit']), 'the visit page books a timed entry');
}

// The home walks the highlights trail: four works in walking order, each
// with its room as an icon and a word from the highlight's own data; the
// department cards left the home for the collection page.
function test_museum_public_home_trail()
{
	$d = _test_mzp_load();
	$nodes = array();
	_test_mzp_nodes(_test_mzp_pages()['home']['tree'], $nodes);
	$stops = array();
	$rooms = array();
	foreach ($nodes as $n) {
		pg_assert_false(strpos((string)($n['props']['href'] ?? ''), '{{page:collection}}?') === 0, 'no department card on the home');
		if (($n['props']['tag'] ?? '') === 'h3' && strpos((string)($n['props']['cssClass'] ?? ''), 'pmz-display h5') === 0) $stops[] = $n['props']['text'];
		if (($n['props']['_label'] ?? '') === lang('Room') && isset($n['props']['text'])) $rooms[] = $n['props']['text'];
	}
	$want = array();
	$want_rooms = array();
	foreach (array('coin', 'harbour', 'dancer', 'tile') as $k) {
		$want[] = $d['objects'][$k]['title'];
		$want_rooms[] = $d['objects'][$k]['gallery'];
	}
	pg_assert_same($want, $stops, 'four stops in walking order');
	pg_assert_same($want_rooms, $rooms, 'each stop names its room');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_museum_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_mzp_pages() as $key => $p) {
		$nodes = array();
		_test_mzp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^opacity-/', $c), "$key: no faded text");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'mz-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old
// exhibition a year later.
function test_museum_public_no_fixed_dates()
{
	$json = json_encode(_test_mzp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(1[5-9]|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; an exhibition reads "sergi", a member
// "üye", a ticket "bilet".
function test_museum_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bExhibitions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'sergi', 0, 'UTF-8') !== false, "'$key' says sergi");
		if (preg_match('/\bMembers?(hip)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'üye', 0, 'UTF-8') !== false, "'$key' says üye");
		if (preg_match('/\bTickets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bilet', 0, 'UTF-8') !== false, "'$key' says bilet");
	}
}
