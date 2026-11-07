<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Coworking design template's public pages
 * (includes/design_templates/pinegrap-coworking/pages_public.php): the
 * six pages of the plan in the public folder and without the shop, links
 * that name template pages and no shop page, the widgets only the home
 * places, the plans page that offers every plan for a tour and lists the
 * offices and meeting rooms with their prices, the template's ids and
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
function _test_cwp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/pages_public.php';
	$prices = array();
	foreach (array_keys($spaces) as $key) $prices[$key] = $space_price($key);
	$out = array('part' => $public_part, 'spaces' => $spaces, 'plans' => $plans, 'prices' => $prices,
		'kind_titles' => $kind_titles, 'tour_choices' => $tour_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_cwp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cwp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_cwp_pages()
{
	$pages = array();
	foreach (_test_cwp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_cwp_queries($tree, $page)
{
	$nodes = array();
	_test_cwp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_coworking_public_pages_are_the_plans()
{
	$part = _test_cwp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'plans', 'amenities', 'about', 'faq', 'location'), array_keys(_test_cwp_pages()), 'the six public pages');
	$names = array();
	foreach (_test_cwp_pages() as $key => $p) {
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
function test_coworking_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'plans', 'amenities', 'about', 'faq', 'location',
		'spaces', 'space', 'space_new', 'book_online',
		'tour', 'tour_view', 'my_tours', 'staff_tours', 'privacy', 'email_reply',
		'office_enquiry', 'enquiry_view', 'staff_enquiries',
		'events', 'event', 'event_new', 'event_signup', 'signup_view', 'my_signups', 'staff_signups',
		'members', 'house_guide',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_cwp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('memberships', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the spaces, the events, the Google reviews and the
// posts, the widgets the spaces, the events and the standard parts
// define; this part defines none.
function test_coworking_public_home_widgets()
{
	pg_assert_same(array(), _test_cwp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_cwp_pages() as $key => $p) {
		$nodes = array();
		_test_cwp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_spaces', 'home_events', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// The plans page offers every plan for a tour in the Plan list's words,
// lists every office and meeting room with its price, and links the
// office enquiry and the room booking; the kind links of the home and
// about pages open the space list filtered.
function test_coworking_public_plans_and_kinds()
{
	$d = _test_cwp_load();
	$pages = _test_cwp_pages();
	$tours = _test_cwp_queries($pages['plans']['tree'], 'tour');
	pg_assert_same(count($d['plans']), count($tours), 'a tour link per plan');
	foreach (array('home', 'plans') as $key) {
		foreach (_test_cwp_queries($pages[$key]['tree'], 'tour') as $q) {
			pg_assert_true(in_array($q['plan'], $d['tour_choices'], true), "$key: a tour link names a Plan answer");
		}
	}
	$texts = array();
	$nodes = array();
	_test_cwp_nodes($pages['plans']['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
	}
	foreach ($d['spaces'] as $key => $s) {
		if (!in_array($s['kind'], array('office', 'meeting'), true)) continue;
		pg_assert_true(in_array($s['name'], $texts, true), "plans: lists $key");
		pg_assert_true(in_array($d['prices'][$key], $texts, true), "plans: shows the price of $key");
	}
	$json = json_encode($pages['plans']['tree']);
	foreach (array('office_enquiry', 'book_online') as $target) {
		pg_assert_contains('{{page:' . $target . '}}', $json, "plans links $target");
	}
	foreach (array('home', 'about') as $key) {
		$kinds = _test_cwp_queries($pages[$key]['tree'], 'spaces');
		pg_assert_same(count($d['kind_titles']), count($kinds), "$key: a link per kind");
		foreach ($kinds as $q) pg_assert_true(in_array($q['kind'], $d['kind_titles'], true), "$key: the link names a Kind answer");
	}
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist and no faded
// text.
function test_coworking_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_cwp_pages() as $key => $p) {
		$nodes = array();
		_test_cwp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pch|pcn|pmu|pph|psh|ptv|pwd|pmk|psc|pai|pmz)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false($c === 'ratio-3x4', "$key: Bootstrap has no ratio-3x4");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'cw-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_coworking_public_no_fixed_dates()
{
	$json = json_encode(_test_cwp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a desk reads "masa", a meeting room
// "toplantı odası", a membership "üyelik", a tour "tur", an event
// "etkinlik"; shared keys whose Turkish names another thing are not used.
function test_coworking_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bDesks?\b/', $key)) pg_assert_true(mb_stripos($tr, 'masa', 0, 'UTF-8') !== false, "'$key' says masa");
		if (preg_match('/\bMeeting Rooms?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'toplantı oda', 0, 'UTF-8') !== false, "'$key' says toplantı odası");
		if (preg_match('/\bMembership\b/', $key)) pg_assert_true(mb_stripos($tr, 'üyelik', 0, 'UTF-8') !== false, "'$key' says üyelik");
		if (preg_match('/\b[Tt]ours?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		if (preg_match('/\bEvents?\b/', $key)) pg_assert_true(mb_stripos($tr, 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
	}
	foreach (array('Classes', 'Strength', 'Testimonials') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "the pages do not use lang('$k')");
	}
}
