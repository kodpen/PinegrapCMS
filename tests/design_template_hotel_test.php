<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Hotel design template's parts
 * (includes/design_templates/pinegrap-hotel/): the hotel's data hangs
 * together (sample rooms naming a known kind and view, a rate for every
 * room, plans, offers, event spaces and vouchers with what they need, the
 * forms' pick lists without an answer twice), a rate is written as the
 * shop writes a price, every page link of the shared parts names a page of
 * the finished template and none names a shop page, the footer opens the
 * room list filtered, a room card reads the Rooms form's fields, a plan or
 * an offer card opens the booking request with it chosen, the classes are
 * the template's own, the sample carries no fixed date and every literal
 * has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_ht_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel/parts.php';
	$room_cards = array();
	foreach (array_keys($rooms) as $key) $room_cards[$key] = $room_card($key);
	$plan_cards = array();
	foreach (array_keys($rate_plans) as $key) $plan_cards[$key] = $plan_card($key);
	$offer_cards = array();
	foreach (array_keys($offers) as $key) $offer_cards[$key] = $offer_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'hotel'             => $hotel,
		'room_types'        => $room_types,
		'views'             => $views,
		'rooms'             => $rooms,
		'room_rates'        => $room_rates,
		'money'             => $money,
		'rate_text'         => $rate_text,
		'plan_price'        => $plan_price,
		'rate_plans'        => $rate_plans,
		'offers'            => $offers,
		'facilities'        => $facilities,
		'dining'            => $dining,
		'treatments'        => $treatments,
		'event_spaces'      => $event_spaces,
		'vouchers'          => $vouchers,
		'nearby'            => $nearby,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'room_type_titles'     => $room_type_titles,
			'view_titles'          => $view_titles,
			'room_names'           => $room_names,
			'booking_room_choices' => $booking_room_choices,
			'plan_choices'         => $plan_choices,
			'offer_choices'        => $offer_choices,
			'arrival_choices'      => $arrival_choices,
			'booking_statuses'     => $booking_statuses,
			'event_kinds'          => $event_kinds,
			'event_space_choices'  => $event_space_choices,
			'event_size_choices'   => $event_size_choices,
			'event_statuses'       => $event_statuses,
		),
		'radios'            => array('bed_choices' => $bed_choices, 'contact_choices' => $contact_choices),
		'room_cards'        => $room_cards,
		'plan_cards'        => $plan_cards,
		'offer_cards'       => $offer_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $booking_band(), $staff_bar(), $post_card(), $ai_summary(), $room_facts()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_ht_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ht_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_ht_trees()
{
	$d = _test_ht_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['room_cards']),
		array_values($d['plan_cards']), array_values($d['offer_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_ht_page_keys()
{
	return array_merge(array(
		'home', 'about', 'dining', 'spa', 'location', 'offers', 'rates', 'faq',
		'room_new', 'rooms', 'room',
		'book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'meetings', 'event_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_ht_shop_keys());
}

// The pages that exist only with the shop.
function _test_ht_shop_keys()
{
	return array('vouchers', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_hotel_data_hangs_together()
{
	$d = _test_ht_load();
	foreach (array('tagline', 'story', 'address', 'region', 'reception') as $f) pg_assert_true(trim($d['hotel'][$f]) !== '', "hotel: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['hotel']['phone']), 'hotel: phone number');
	pg_assert_true(strpos($d['hotel']['email'], '@example.com') !== false, 'hotel: a sample address');
	foreach (array('check_in', 'check_out') as $f) pg_assert_true((bool)preg_match('/^\d{2}:\d{2}$/', $d['hotel'][$f]), "hotel: $f is a time");
	pg_assert_same(6, count($d['rooms']), 'six sample rooms');
	$types = array();
	$views = array();
	$featured = 0;
	foreach ($d['rooms'] as $key => $r) {
		pg_assert_true(isset($d['room_types'][$r['type']]), "room $key: known kind");
		pg_assert_true(isset($d['views'][$r['view']]), "room $key: known view");
		pg_assert_true(is_int($r['guests']) && $r['guests'] >= 1 && $r['guests'] <= 6, "room $key: guests is a number");
		pg_assert_true(is_int($r['size']) && $r['size'] > 10, "room $key: size in m²");
		foreach (array('name', 'beds', 'summary', 'description') as $f) pg_assert_true(trim($r[$f]) !== '', "room $key: $f");
		pg_assert_true(count($r['amenities']) >= 4, "room $key: amenities");
		pg_assert_true(isset($d['room_rates'][$key]['USD']), "room $key: has a rate");
		if ($r['featured']) $featured++;
		$types[$r['type']] = true;
		$views[$r['view']] = true;
	}
	pg_assert_same(count($d['room_types']), count($types), 'a sample of every kind of room');
	pg_assert_same(count($d['views']), count($views), 'a sample of every view');
	pg_assert_same(3, $featured, 'three rooms on the home');
	pg_assert_same(array_keys($d['rooms']), array_keys($d['room_rates']), 'a rate per room and no other');
	// A family room holds more guests than a guest room.
	foreach ($d['rooms'] as $key => $r) {
		if ($r['type'] === 'family') pg_assert_true($r['guests'] >= 4, "room $key: a family room sleeps four");
	}
	pg_assert_same(3, count($d['rate_plans']), 'three board plans');
	pg_assert_true($d['rate_plans']['room']['extra'] === null, 'room only adds nothing');
	foreach ($d['rate_plans'] as $key => $p) {
		pg_assert_true(count($p['points']) >= 3, "plan $key: points");
		if ($p['extra'] !== null) pg_assert_true(isset($p['extra']['USD']), "plan $key: a price per guest");
	}
	pg_assert_same(3, count($d['offers']), 'three offers');
	foreach ($d['offers'] as $key => $o) {
		foreach (array('title', 'badge', 'text', 'terms') as $f) pg_assert_true(trim($o[$f]) !== '', "offer $key: $f");
	}
	foreach ($d['event_spaces'] as $key => $s) {
		pg_assert_true(is_int($s['capacity']) && $s['capacity'] > 0, "space $key: capacity");
		pg_assert_true(is_int($s['size']) && $s['size'] > 0, "space $key: size");
	}
	$skus = array();
	foreach ($d['vouchers'] as $key => $v) {
		pg_assert_true((bool)preg_match('/^HOTEL-[A-Z0-9-]+$/', $v['sku']), "voucher $key: the template's SKU");
		pg_assert_true(isset($v['price']['USD']) && $v['price']['USD'] > 0, "voucher $key: a price");
		$skus[$v['sku']] = true;
	}
	pg_assert_same(count($d['vouchers']), count($skus), 'no SKU twice');
	foreach ($d['treatments'] as $n => $t) pg_assert_true(is_int($t['minutes']) && $t['minutes'] > 0, "treatment $n: minutes");
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['rooms'][$t['room']]), "testimonial $n: about a sample room");
		pg_assert_contains($d['rooms'][$t['room']]['name'], $t['role'], "testimonial $n: names the room");
	}
	// The pick lists: one answer per kind, view, room, plan, offer and
	// space (plus "any room", "no offer", "not sure"), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['room_types']), count($l['room_type_titles']), 'an answer per kind of room');
	pg_assert_same(count($d['views']), count($l['view_titles']), 'an answer per view');
	pg_assert_same(count($d['rooms']) + 1, count($l['booking_room_choices']), 'booking: an answer per room and any room');
	pg_assert_same(count($d['rate_plans']), count($l['plan_choices']), 'an answer per plan');
	pg_assert_same(count($d['offers']) + 1, count($l['offer_choices']), 'an answer per offer and none');
	pg_assert_same(count($d['event_spaces']) + 1, count($l['event_space_choices']), 'an answer per space and not sure');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['booking_statuses']), 'five states of a booking request');
	pg_assert_same(5, count($l['event_statuses']), 'five states of an event enquiry');
}

// A rate and a voucher show one amount: the base currency's figure, the
// USD figure for a currency the table does not list.
function test_hotel_rate_is_written_as_the_shop_writes_it()
{
	$d = _test_ht_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['room_rates']['superior'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('From %s a night'), pg_money_text($amount, $symbol)), call_user_func($d['rate_text'], 'superior'), 'the rate line');
	pg_assert_same(lang('Included in the room rate'), call_user_func($d['plan_price'], 'room'), 'room only adds nothing');
	$extra = $d['rate_plans']['half']['extra'];
	$amount = isset($extra[$base]) ? $extra[$base] : $extra['USD'];
	pg_assert_same(sprintf(lang('+ %s per guest a night'), pg_money_text($amount, $symbol)), call_user_func($d['plan_price'], 'half'), 'half board per guest');
}

function test_hotel_page_links_name_template_pages()
{
	$known = array_flip(_test_ht_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_ht_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_hotel_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_ht_trees());
	foreach (_test_ht_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_ht_load();
	pg_assert_contains('{{page:book}}', json_encode($d['header']), 'the header asks for a room');
	$nodes = array();
	_test_ht_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer's room links open the room list filtered on each kind and
// each view, in the words the Type and View lists use.
function test_hotel_footer_filters_the_room_list()
{
	$d = _test_ht_load();
	$nodes = array();
	_test_ht_nodes($d['footer'], $nodes);
	$seen = array('type' => array(), 'view' => array());
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:rooms}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:rooms}}?')), $q);
		if (isset($q['type'])) {
			pg_assert_true(in_array($q['type'], $d['lists']['room_type_titles'], true), 'the link names a Type answer');
			$seen['type'][$q['type']] = true;
		} else {
			pg_assert_true(isset($q['room_view']), 'the link sets ?room_view= (?view= is reserved)');
			pg_assert_true(in_array($q['room_view'], $d['lists']['view_titles'], true), 'the link names a View answer');
			$seen['view'][$q['room_view']] = true;
		}
	}
	pg_assert_same(count($d['room_types']), count($seen['type']), 'a link per kind of room');
	pg_assert_same(count($d['views']), count($seen['view']), 'a link per view');
}

// A room card reads every value from the Rooms form's fields, with the
// sample's own words on the canvas.
function test_hotel_room_card_reads_the_room()
{
	$d = _test_ht_load();
	foreach ($d['room_cards'] as $key => $card) {
		$nodes = array();
		_test_ht_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo_1', 'name', 'form_item_view', 'type', 'summary', 'guests', 'size', 'beds', 'view', 'rate') as $t) {
			pg_assert_true(isset($tokens[$t]), "room $key: card reads $t");
		}
		$r = $d['rooms'][$key];
		pg_assert_true(in_array($r['name'], $texts, true), "room $key: sample name on the canvas");
		pg_assert_true(in_array($d['room_types'][$r['type']]['title'], $texts, true), "room $key: sample kind on the canvas");
		pg_assert_true(in_array(call_user_func($d['rate_text'], $key), $texts, true), "room $key: sample rate on the canvas");
	}
}

// A plan or an offer card opens the booking request with it chosen, in
// the words the Board and Offer lists use (the form starts from ?plan=
// and ?offer=).
function test_hotel_cards_choose_in_the_booking_request()
{
	$d = _test_ht_load();
	foreach (array('plan' => array('plan_cards', 'plan_choices'), 'offer' => array('offer_cards', 'offer_choices')) as $param => $w) {
		foreach ($d[$w[0]] as $key => $card) {
			$nodes = array();
			_test_ht_nodes($card, $nodes);
			$hrefs = array();
			foreach ($nodes as $node) {
				if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:book}}?') === 0) $hrefs[] = $node['props']['href'];
			}
			pg_assert_same(1, count($hrefs), "$param $key: one booking link");
			parse_str(substr($hrefs[0], strlen('{{page:book}}?')), $q);
			pg_assert_true(in_array($q[$param], $d['lists'][$w[1]], true), "$param $key: the link names an answer");
		}
	}
}

// Classes the template adds of its own start with pht-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with ht-.
function test_hotel_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_ht_trees() as $t) _test_ht_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pht-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ht-') === 0, "id $id is the template's");
	}
	foreach (array('pht-brand', 'pht-display', 'pht-eyebrow', 'pht-price', 'pht-lift', 'pht-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_hotel_no_fixed_dates()
{
	$d = _test_ht_load();
	$json = json_encode(array($d['rooms'], $d['rate_plans'], $d['offers'], $d['dining'], $d['vouchers'], _test_ht_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a room is "oda", a suite "süit", a booking
// request "rezervasyon".
function test_hotel_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bBookings?\b/', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		if (preg_match('/\bSuites?\b/', $key)) pg_assert_true(mb_stripos($tr, 'süit', 0, 'UTF-8') !== false, "'$key' says süit");
	}
	// The shared keys this template must not use: their Turkish names
	// another thing (Rooms reads Salonlar, a hall).
	pg_assert_false(strpos($src, "lang('Rooms')") !== false, "the template says 'Rooms and Suites', not 'Rooms'");
}
