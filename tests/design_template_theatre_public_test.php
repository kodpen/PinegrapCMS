<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theatre design template's public pages
 * (includes/design_templates/pinegrap-theatre/pages_public.php): the pages
 * with their keys, every page link names a page of the finished template
 * and none a shop page, the widgets the home places are the ones the other
 * parts define, the visit page lists every ticket band and the box office
 * hours and the support page every friends tier with the price the shop
 * will show, the genre cards open the season filtered and the access cards
 * the performances filtered, search anchors and classes are the template's
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

// _test_th_page_keys(): the pages of the finished template.
require_once __DIR__ . '/design_template_theatre_test.php';

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_thp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/pages_public.php';
	$ticket_texts = array();
	foreach (array_keys($tickets) as $key) $ticket_texts[$key] = $ticket_price_text($key);
	$tier_texts = array();
	foreach (array_keys($tiers) as $key) $tier_texts[$key] = $tier_price_text($key);
	$out = array('part' => $public_part, 'tickets' => $tickets, 'tiers' => $tiers, 'genres' => $genres, 'access_types' => $access_types,
		'ticket_texts' => $ticket_texts, 'tier_texts' => $tier_texts, 'box_office_hours' => $box_office_hours);
	return $out;
}

// Every node of a tree, depth first.
function _test_thp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_thp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_thp_pages()
{
	$pages = array();
	foreach (_test_thp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The texts of a tree.
function _test_thp_texts($tree)
{
	$nodes = array();
	_test_thp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $out[] = $n['props']['text'];
	}
	return $out;
}

function test_theatre_public_pages_are_the_plans()
{
	$d = _test_thp_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'visit', 'support', 'about', 'faq'), array_keys(_test_thp_pages()), 'the public pages');
	$names = array();
	foreach (_test_thp_pages() as $key => $p) {
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
function test_theatre_public_links_name_template_pages()
{
	$known = array_flip(_test_th_page_keys());
	$json = json_encode(_test_thp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) >= 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('tickets', 'friends', 'product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the productions now playing, the next performances, the
// Google reviews and the news, the widgets the other parts define; this
// part defines none.
function test_theatre_public_home_widgets()
{
	pg_assert_same(array(), _test_thp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_thp_pages() as $key => $p) {
		$nodes = array();
		_test_thp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_productions', 'home_performances', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// The visit page lists every ticket band with the price the shop shows and
// the box office hours day by day; the support page lists every friends
// tier the same way.
function test_theatre_public_prices_are_the_shops()
{
	$d = _test_thp_load();
	$pages = _test_thp_pages();
	$visit = _test_thp_texts($pages['visit']['tree']);
	foreach ($d['tickets'] as $key => $a) {
		pg_assert_true(in_array($a['title'], $visit, true), "visit: the $key ticket");
		pg_assert_true(in_array($d['ticket_texts'][$key], $visit, true), "visit: the $key price");
	}
	foreach ($d['box_office_hours'] as $h) pg_assert_true(in_array($h['days'], $visit, true), 'visit: the hours of ' . $h['days']);
	$support = _test_thp_texts($pages['support']['tree']);
	foreach ($d['tiers'] as $key => $t) {
		pg_assert_true(in_array($t['title'], $support, true), "support: the $key tier");
		pg_assert_true(in_array($d['tier_texts'][$key], $support, true), "support: the $key price");
	}
	pg_assert_contains('{{page:whats_on}}', json_encode($pages['visit']), 'the visit page finds a performance');
}

// The home opens the season on each genre in the words of the Genre list;
// the visit page opens the performances of each access kind in the words
// of the Access list, under the anchor the footer links (#th-access).
function test_theatre_public_filter_links()
{
	$d = _test_thp_load();
	$pages = _test_thp_pages();
	$seen = function ($tree, $page, $param) {
		$nodes = array();
		_test_thp_nodes($tree, $nodes);
		$out = array();
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
			if (isset($q[$param])) $out[] = $q[$param];
		}
		return $out;
	};
	$titles = array();
	foreach ($d['genres'] as $g) $titles[] = $g['title'];
	pg_assert_same($titles, $seen($pages['home']['tree'], 'season', 'genre'), 'a card per genre, filtering the season');
	$titles = array();
	foreach ($d['access_types'] as $a) $titles[] = $a['title'];
	pg_assert_same($titles, $seen($pages['visit']['tree'], 'whats_on', 'access'), 'a card per access kind, filtering the performances');
	pg_assert_contains('"id":"th-access"', json_encode($pages['visit']['tree']), 'the visit page has the access anchor');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_theatre_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_thp_pages() as $key => $p) {
		$nodes = array();
		_test_thp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^opacity-/', $c), "$key: no faded text");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'th-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as an old
// season a year later.
function test_theatre_public_no_fixed_dates()
{
	$json = json_encode(_test_thp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(1[5-9]|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a performance reads "temsil", a friend
// "dost", a ticket "bilet".
function test_theatre_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bPerformances?\b/', $key)) pg_assert_true(mb_stripos($tr, 'temsil', 0, 'UTF-8') !== false, "'$key' says temsil");
		if (preg_match('/\bFriends?\b/', $key)) pg_assert_true(mb_stripos($tr, 'dost', 0, 'UTF-8') !== false, "'$key' says dost");
		if (preg_match('/\bTickets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bilet', 0, 'UTF-8') !== false, "'$key' says bilet");
	}
}
