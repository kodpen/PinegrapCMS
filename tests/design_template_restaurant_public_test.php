<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant design template's public pages
 * (includes/design_templates/pinegrap-restaurant/pages_public.php): the six
 * pages of the plan in the public folder and without the shop, links that
 * name template pages and no shop page, the widgets only the home places,
 * a tasting page that offers every tasting menu to reserve, a wine list
 * that shows every wine once under its kind, the template's ids and
 * classes, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_rtp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/pages_public.php';
	$out = array('part' => $public_part, 'tastings' => $tastings, 'tasting_choices' => $tasting_choices,
		'wines' => $wines, 'wine_groups' => $wine_groups, 'team' => $team, 'room_choices' => $room_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_rtp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rtp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_rtp_pages()
{
	$pages = array();
	foreach (_test_rtp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_rtp_queries($tree, $page)
{
	$nodes = array();
	_test_rtp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_restaurant_public_pages_are_the_plans()
{
	$part = _test_rtp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'tasting', 'wine', 'about', 'faq', 'location'), array_keys(_test_rtp_pages()), 'the six public pages');
	$names = array();
	foreach (_test_rtp_pages() as $key => $p) {
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
function test_restaurant_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'tasting', 'wine', 'about', 'faq', 'location',
		'menu', 'dish', 'dish_new', 'events', 'event', 'event_new',
		'reserve', 'reservation_view', 'my_reservations', 'staff_reservations', 'privacy', 'email_reply', 'book_online',
		'private_dining', 'private_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_rtp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('vouchers', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the favourite dishes, the next events, the Google
// reviews and the journal's posts, the widgets the menu and the standard
// parts define; this part defines none.
function test_restaurant_public_home_widgets()
{
	pg_assert_same(array(), _test_rtp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_rtp_pages() as $key => $p) {
		$nodes = array();
		_test_rtp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_dishes', 'home_events', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// The tasting page offers every tasting menu to reserve once, in the
// words of the Menu list; the home's tasting links do too; the wine list
// shows every wine once, under its kind, with a link from the top to
// each kind; the about page shows every member of the team.
function test_restaurant_public_tasting_wine_and_team()
{
	$d = _test_rtp_load();
	$pages = _test_rtp_pages();
	$named = array();
	foreach (_test_rtp_queries($pages['tasting']['tree'], 'reserve') as $q) {
		if (!isset($q['tasting'])) continue;
		pg_assert_true(in_array($q['tasting'], $d['tasting_choices'], true), 'tasting: the link names a Menu answer');
		$named[] = $q['tasting'];
	}
	$all = array();
	foreach ($d['tastings'] as $t) $all[] = $t['title'];
	pg_assert_same($all, $named, 'every tasting menu once on the tasting page');
	foreach (_test_rtp_queries($pages['home']['tree'], 'reserve') as $q) {
		if (isset($q['tasting'])) pg_assert_true(in_array($q['tasting'], $d['tasting_choices'], true), 'home: a Menu answer');
	}
	foreach (array('home', 'faq', 'about') as $key) {
		foreach (_test_rtp_queries($pages[$key]['tree'], 'private_enquiry') as $q) {
			if (isset($q['room'])) pg_assert_true(in_array($q['room'], $d['room_choices'], true), "$key: a Room answer");
		}
	}
	$json = json_encode($pages['wine']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['wines'] as $w) pg_assert_same(1, substr_count($json, '"' . $w['name'] . '"'), "wine {$w['name']} once");
	foreach (array_keys($d['wine_groups']) as $g) {
		pg_assert_true(strpos($json, '"#rt-wine-' . $g . '"') !== false, "a link to the $g wines");
		pg_assert_true(strpos($json, '"rt-wine-' . $g . '"') !== false, "the $g wines under their anchor");
	}
	$about = json_encode($pages['about']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['team'] as $t) pg_assert_contains('"' . $t['name'] . '"', $about, "about: {$t['name']} on the team");
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist and no faded
// text.
function test_restaurant_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_rtp_pages() as $key => $p) {
		$nodes = array();
		_test_rtp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pai|pmz)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false($c === 'ratio-3x4', "$key: Bootstrap has no ratio-3x4");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'rt-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_restaurant_public_no_fixed_dates()
{
	$json = json_encode(_test_rtp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, a reservation reads "rezervasyon", a
// tasting menu "tadım menüsü"; shared keys whose Turkish names another
// thing are not used.
function test_restaurant_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bReservations?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		if (preg_match('/\bTasting Menus?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'tadım menü', 0, 'UTF-8') !== false, "'$key' says tadım menüsü");
	}
	foreach (array('Starters', 'Course', 'Courses', 'Glass', 'Dish', 'Find Us', 'Testimonials') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "the pages do not use lang('$k')");
	}
}
