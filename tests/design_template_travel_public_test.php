<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel Agency design template's public pages
 * (includes/design_templates/pinegrap-travel/pages_public.php): the five
 * pages with their keys, every page link names a page of the finished
 * template and none a shop page, the widgets the home places are the ones
 * the other parts define, the price table opens the booking request with
 * the tour chosen, the deposit reads as the shop writes it, search anchors
 * and classes are the template's own, no fixed date, and every literal has
 * its Turkish.
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
function _test_tvp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/pages_public.php';
	$out = array('part' => $public_part, 'tours' => $tours, 'booking_tour_choices' => $booking_tour_choices,
		'deposit_text' => $money($deposit), 'tour_prices' => $tour_prices, 'money' => $money);
	return $out;
}

// Every node of a tree, depth first.
function _test_tvp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_tvp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_tvp_pages()
{
	$pages = array();
	foreach (_test_tvp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_travel_public_pages_are_the_plans()
{
	$part = _test_tvp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'about', 'travel_info', 'deals', 'faq'), array_keys(_test_tvp_pages()), 'the five public pages');
	foreach (_test_tvp_pages() as $key => $p) {
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
function test_travel_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'about', 'travel_info', 'faq', 'deals',
		'destinations', 'destination', 'destination_new', 'tours', 'tour', 'tour_new',
		'book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_tvp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('packages', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the featured tours and destinations, the Google reviews
// and the journal, the widgets the tours and the standard parts define;
// this part defines none.
function test_travel_public_home_widgets()
{
	pg_assert_same(array(), _test_tvp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_tvp_pages() as $key => $p) {
		$nodes = array();
		_test_tvp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_tours', 'home_destinations', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A row of the price table for each sample tour, with its price as the
// shop writes it and a button opening the booking request with the tour
// chosen in the words of the Tour list.
function test_travel_public_price_table_opens_the_request()
{
	$d = _test_tvp_load();
	$nodes = array();
	_test_tvp_nodes(_test_tvp_pages()['deals']['tree'], $nodes);
	$tours = array();
	$texts = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['text'])) $texts[] = $n['props']['text'];
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:book}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:book}}?')), $q);
		pg_assert_true(isset($q['tour']) || isset($q['deal']), 'the link chooses a tour or a deal');
		if (isset($q['tour'])) {
			pg_assert_true(in_array($q['tour'], $d['booking_tour_choices'], true), 'the link names a Tour answer');
			$tours[] = $q['tour'];
		}
	}
	pg_assert_same(count($d['tours']), count(array_unique($tours)), 'a row per sample tour');
	foreach ($d['tour_prices'] as $key => $p) {
		pg_assert_true(in_array(call_user_func($d['money'], $p), $texts, true), "tour $key: its price in the table");
	}
}

// The deposit reads the same amount on the travel information page and in
// the questions, written as the shop writes it.
function test_travel_public_deposit_reads_one_amount()
{
	$d = _test_tvp_load();
	$pages = _test_tvp_pages();
	foreach (array('travel_info', 'faq') as $key) {
		pg_assert_contains($d['deposit_text'], json_encode($pages[$key]['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "$key names the deposit");
	}
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_travel_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_tvp_pages() as $key => $p) {
		$nodes = array();
		_test_tvp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'tv-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_travel_public_no_fixed_dates()
{
	$json = json_encode(_test_tvp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a tour reads "tur", a destination
// "destinasyon", a booking "rezervasyon" and a deposit "kapora".
function test_travel_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bTours?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		if (preg_match('/\bDestinations?\b/', $key)) pg_assert_true(mb_stripos($tr, 'destinasyon', 0, 'UTF-8') !== false, "'$key' says destinasyon");
		if (preg_match('/\b[Bb]ooking\b/', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		if (preg_match('/\b[Dd]eposits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
	}
}
