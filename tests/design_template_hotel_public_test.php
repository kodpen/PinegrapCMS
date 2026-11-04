<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Hotel design template's public pages
 * (includes/design_templates/pinegrap-hotel/pages_public.php): the eight
 * pages with their keys, every page link names a page of the finished
 * template and none a shop page, the widgets the home places are the ones
 * the other parts define, the rates table opens the booking request with
 * the room chosen, search anchors and classes are the template's own, no
 * fixed date, and every literal has its Turkish.
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
function _test_htp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel/parts.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel/pages_public.php';
	$out = array('part' => $public_part, 'rooms' => $rooms, 'booking_room_choices' => $booking_room_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_htp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_htp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_htp_pages()
{
	$pages = array();
	foreach (_test_htp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_hotel_public_pages_are_the_plans()
{
	$part = _test_htp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'about', 'dining', 'spa', 'location', 'offers', 'rates', 'faq'), array_keys(_test_htp_pages()), 'the eight public pages');
	foreach (_test_htp_pages() as $key => $p) {
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
function test_hotel_public_links_name_template_pages()
{
	$known = array_flip(array(
		'home', 'about', 'dining', 'spa', 'location', 'offers', 'rates', 'faq',
		'room_new', 'rooms', 'room',
		'book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'meetings', 'event_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register',
	));
	$json = json_encode(_test_htp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('vouchers', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the rooms list, the Google reviews and the journal, the
// widgets the rooms and the standard parts define; this part defines none.
function test_hotel_public_home_widgets()
{
	pg_assert_same(array(), _test_htp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_htp_pages() as $key => $p) {
		$nodes = array();
		_test_htp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_rooms', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A row of the rates table for each sample room, its button opening the
// booking request with the room chosen in the words of the Room list.
function test_hotel_public_rates_open_the_request()
{
	$d = _test_htp_load();
	$nodes = array();
	_test_htp_nodes(_test_htp_pages()['rates']['tree'], $nodes);
	$rooms = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:book}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:book}}?')), $q);
		pg_assert_true(isset($q['room']) || isset($q['plan']), 'the link chooses a room or a plan');
		if (isset($q['room'])) {
			pg_assert_true(in_array($q['room'], $d['booking_room_choices'], true), 'the link names a Room answer');
			$rooms[] = $q['room'];
		}
	}
	pg_assert_same(count($d['rooms']), count(array_unique($rooms)), 'a row per sample room');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, and no Bootstrap 5.3 class that does not exist.
function test_hotel_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_htp_pages() as $key => $p) {
		$nodes = array();
		_test_htp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'ht-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]) || $key === 'home', "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_hotel_public_no_fixed_dates()
{
	$json = json_encode(_test_htp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, a booking reads "rezervasyon" and a suite
// "süit"; shared keys whose Turkish names another thing are not used.
function test_hotel_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\b[Bb]ookings?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		if (preg_match('/\b[Ss]uites?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'süit', 0, 'UTF-8') !== false, "'$key' says süit");
	}
	// Room reads Salon, Rate Oran, Ask İste: a hall, a ratio, a bare verb.
	foreach (array('Rooms', 'Room', 'Rate', 'Ask') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "the pages do not use lang('$k')");
	}
}
