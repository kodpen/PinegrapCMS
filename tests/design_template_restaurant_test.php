<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant design template's parts
 * (includes/design_templates/pinegrap-restaurant/): the restaurant's data
 * hangs together (bookable times inside the sittings, dishes of a known
 * course with known diet tags, tasting menus made of the menu's dishes,
 * wines of a known kind with a bottle price, events dated in the coming
 * weeks on their weekday, vouchers with the template's SKU, the forms'
 * pick lists without an answer twice), a price is written as the shop
 * writes it, every page link of the shared parts names a page of the
 * finished template and none names a shop page, the footer links the
 * food pages, a dish card reads the Menu form's fields, the
 * tasting and room links make their choice in the request forms, the
 * classes are the template's own, the sample carries no fixed date and
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

// The parts as the template file reads them. Read once per run.
function _test_rt_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/parts.php';
	$dish_cards = array();
	$dish_lines = array();
	foreach (array_keys($dishes) as $key) {
		$dish_cards[$key] = $dish_card($key);
		$dish_lines[$key] = $dish_line($key);
	}
	$tasting_cards = array();
	foreach (array_keys($tastings) as $key) $tasting_cards[$key] = $tasting_card($key);
	$wine_trees = array();
	foreach (array_keys($wine_groups) as $key) $wine_trees[$key] = $wine_group($key);
	$room_cards = array();
	foreach (array_keys($rooms) as $key) $room_cards[$key] = $room_card($key);
	$team_cards = array();
	foreach (array_keys($team) as $key) $team_cards[$key] = $team_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'restaurant'        => $restaurant,
		'days'              => $days,
		'sittings'          => $sittings,
		'opening_hours'     => $opening_hours,
		'courses'           => $courses,
		'diets'             => $diets,
		'badges'            => $badges,
		'dishes'            => $dishes,
		'dish_records'      => $dish_records,
		'tastings'          => $tastings,
		'wine_groups'       => $wine_groups,
		'wines'             => $wines,
		'team'              => $team,
		'rooms'             => $rooms,
		'events'            => $events,
		'event_on'          => $event_on,
		'vouchers'          => $vouchers,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'money'             => $money,
		'per_guest'         => $per_guest,
		'lists'             => array(
			'time_choices'         => $time_choices,
			'course_titles'        => $course_titles,
			'diet_titles'          => $diet_titles,
			'tasting_choices'      => $tasting_choices,
			'event_titles'         => $event_titles,
			'room_choices'         => $room_choices,
			'guest_choices'        => $guest_choices,
			'occasion_choices'     => $occasion_choices,
			'seating_choices'      => $seating_choices,
			'enquiry_kinds'        => $enquiry_kinds,
			'reservation_statuses' => $reservation_statuses,
			'enquiry_statuses'     => $enquiry_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'dish_cards'        => $dish_cards,
		'dish_lines'        => $dish_lines,
		'tasting_cards'     => $tasting_cards,
		'wine_trees'        => $wine_trees,
		'room_cards'        => $room_cards,
		'team_cards'        => $team_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $reserve_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_rt_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rt_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_rt_trees()
{
	$d = _test_rt_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['dish_cards']), array_values($d['dish_lines']),
		array_values($d['tasting_cards']), array_values($d['wine_trees']), array_values($d['room_cards']),
		array_values($d['team_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_rt_page_keys()
{
	return array_merge(array(
		'home', 'tasting', 'wine', 'about', 'faq', 'location',
		'menu', 'dish', 'dish_new', 'events', 'event', 'event_new',
		'reserve', 'reservation_view', 'my_reservations', 'staff_reservations', 'privacy', 'email_reply',
		'email_reservation_new', 'email_reservation_received', 'book_online',
		'private_dining', 'private_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'email_enquiry_new', 'email_enquiry_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_rt_shop_keys());
}

// The pages that exist only with the shop.
function _test_rt_shop_keys()
{
	return array('vouchers', 'product', 'cart', 'checkout', 'order', 'email_order');
}

// Minutes since midnight of an HH:MM time.
function _test_rt_minutes($time)
{
	list($h, $m) = explode(':', $time);
	return (int)$h * 60 + (int)$m;
}

function test_restaurant_data_hangs_together()
{
	$d = _test_rt_load();
	foreach (array('tagline', 'story', 'address', 'region') as $f) pg_assert_true(trim($d['restaurant'][$f]) !== '', "restaurant: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['restaurant']['phone']), 'restaurant: phone number');
	pg_assert_true(strpos($d['restaurant']['email'], '@example.com') !== false, 'restaurant: a sample address');
	pg_assert_same(array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'), array_keys($d['days']), 'the week from Monday');
	$open = 0;
	foreach ($d['days'] as $key => $day) {
		foreach (array_keys($d['sittings']) as $s) pg_assert_true(is_bool($day[$s]), "day $key: $s is on or off");
		if ($day['lunch'] || $day['dinner']) $open++;
	}
	pg_assert_true($open >= 5 && $open < 7, 'open most days, one closing day');
	// Every bookable time lies inside a sitting, half an hour apart, and
	// the kitchen stays open after the last table.
	foreach ($d['sittings'] as $key => $s) {
		pg_assert_true(_test_rt_minutes($s['first']) <= _test_rt_minutes($s['last']), "sitting $key: first table before the last");
		pg_assert_true(_test_rt_minutes($s['last']) + 60 <= _test_rt_minutes($s['close']), "sitting $key: an hour of kitchen after the last table");
	}
	foreach ($d['lists']['time_choices'] as $t) {
		$in = false;
		foreach ($d['sittings'] as $s) {
			if (_test_rt_minutes($t) >= _test_rt_minutes($s['first']) && _test_rt_minutes($t) <= _test_rt_minutes($s['last'])) $in = true;
		}
		pg_assert_true($in, "time $t: inside a sitting");
		pg_assert_same(0, _test_rt_minutes($t) % 30, "time $t: on the half hour");
	}
	pg_assert_true(in_array('12:00', $d['lists']['time_choices'], true) && in_array('21:30', $d['lists']['time_choices'], true), 'first and last table');
	// Dishes: a known course and known diet tags, every course served.
	$served = array();
	$names = array();
	foreach ($d['dishes'] as $key => $dish) {
		pg_assert_true(isset($d['courses'][$dish['course']]), "dish $key: known course");
		foreach ($dish['diet'] as $tag) pg_assert_true(in_array($tag, $d['lists']['diet_titles'], true), "dish $key: known diet tag");
		pg_assert_true($dish['badge'] === '' || in_array($dish['badge'], $d['badges'], true), "dish $key: known badge");
		pg_assert_true(isset($dish['price']['USD']) && $dish['price']['USD'] > 0, "dish $key: a price");
		foreach (array('name', 'text') as $f) pg_assert_true(trim($dish[$f]) !== '', "dish $key: $f");
		$served[$dish['course']] = true;
		$names[] = $dish['name'];
	}
	pg_assert_same(count($d['courses']), count($served), 'a dish in every course');
	pg_assert_same(count($d['dishes']), count($d['dish_records']), 'a record per dish');
	foreach ($d['dish_records'] as $n => $r) {
		pg_assert_true(in_array($r['course'], $d['lists']['course_titles'], true), "record $n: a Course answer");
		pg_assert_true(in_array($r['featured'], array(lang('Yes'), lang('No')), true), "record $n: featured yes or no");
	}
	// Tasting menus: made of the menu's own dishes, one marked.
	$featured = 0;
	foreach ($d['tastings'] as $key => $t) {
		pg_assert_true(count($t['courses']) >= 4, "tasting $key: courses");
		foreach ($t['courses'] as $c) pg_assert_true(in_array($c, $names, true), "tasting $key: '$c' is a dish of the menu");
		pg_assert_true($t['price']['USD'] > 0 && $t['pairing']['USD'] > 0, "tasting $key: prices");
		if ($t['featured']) $featured++;
	}
	pg_assert_same(1, $featured, 'one tasting menu marked');
	// Wines: a known kind, a bottle price, every kind poured.
	$kinds = array();
	foreach ($d['wines'] as $n => $w) {
		pg_assert_true(isset($d['wine_groups'][$w['group']]), "wine $n: known kind");
		pg_assert_true($w['bottle']['USD'] > 0, "wine $n: a bottle price");
		pg_assert_true($w['glass']['USD'] === 0 || $w['glass']['USD'] < $w['bottle']['USD'], "wine $n: a glass costs less than the bottle");
		$kinds[$w['group']] = true;
	}
	pg_assert_same(count($d['wine_groups']), count($kinds), 'a wine of every kind');
	foreach ($d['rooms'] as $key => $r) {
		pg_assert_true(is_int($r['seated']) && $r['seated'] > 0, "room $key: seats");
		pg_assert_true(is_int($r['standing']) && $r['standing'] >= 0, "room $key: standing");
	}
	// Events: on their weekday, in the coming weeks.
	foreach ($d['events'] as $n => $e) {
		$date = call_user_func($d['event_on'], $e['weekday'], $e['weeks']);
		pg_assert_same($e['weekday'], (int)date('N', strtotime($date)), "event $n: on its weekday");
		pg_assert_true($date > date('Y-m-d'), "event $n: still to come");
		pg_assert_true(strtotime($date) <= strtotime('+4 weeks'), "event $n: within four weeks");
		pg_assert_true((bool)preg_match('/^\d{2}:\d{2}$/', $e['time']), "event $n: a time");
	}
	$skus = array();
	foreach ($d['vouchers'] as $key => $v) {
		pg_assert_true((bool)preg_match('/^RESTAURANT-[A-Z0-9-]+$/', $v['sku']), "voucher $key: the template's SKU");
		pg_assert_true($v['price']['USD'] > 0, "voucher $key: a price");
		$skus[$v['sku']] = true;
	}
	pg_assert_same(count($d['vouchers']), count($skus), 'no SKU twice');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) pg_assert_contains($t['about'], $t['role'], "testimonial $n: names what it is about");
	// The pick lists: no answer twice; the menu choice is à la carte or a
	// tasting menu, the room and event lists one answer each.
	$l = $d['lists'];
	pg_assert_same(count($d['tastings']) + 1, count($l['tasting_choices']), 'an answer per tasting menu and à la carte');
	pg_assert_same(count($d['rooms']), count($l['room_choices']), 'an answer per room');
	pg_assert_same(count($d['events']), count($l['event_titles']), 'an answer per event');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_restaurant_price_is_written_as_the_shop_writes_it()
{
	$d = _test_rt_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['tastings']['seasons']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('%s per guest'), pg_money_text($amount, $symbol)), call_user_func($d['per_guest'], $prices), 'the per-guest line');
	$lamb = $d['dishes']['lamb']['price'];
	$found = false;
	foreach ($d['dish_records'] as $r) {
		if ($r['name'] === $d['dishes']['lamb']['name']) $found = $r['price'] === call_user_func($d['money'], $lamb);
	}
	pg_assert_true($found, 'a dish record keeps its price in the shop\'s words');
}

function test_restaurant_page_links_name_template_pages()
{
	$known = array_flip(_test_rt_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_rt_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_restaurant_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_rt_trees());
	foreach (_test_rt_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_rt_load();
	pg_assert_contains('{{page:reserve}}', json_encode($d['header']), 'the header reserves a table');
	$nodes = array();
	_test_rt_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links found in a tree that open the given page with a query.
function _test_rt_query_links($tree, $page)
{
	$nodes = array();
	_test_rt_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer links the menu, the tasting menus, the wine list and the
// chef's table; no link carries a ?course= the menu's tabs would ignore.
function test_restaurant_footer_links_the_food_pages()
{
	$d = _test_rt_load();
	$json = json_encode($d['footer']);
	foreach (array('menu', 'tasting', 'wine', 'book_online') as $key) pg_assert_contains('{{page:' . $key . '}}', $json, "the footer links $key");
	pg_assert_same(array(), _test_rt_query_links($d['footer'], 'menu'), 'no filtered menu link');
}

// A dish card and a dish line read every value from the Menu form's
// fields, with the sample's own words on the canvas.
function test_restaurant_dish_card_reads_the_dish()
{
	$d = _test_rt_load();
	foreach (array('dish_cards' => array('photo', 'name', 'form_item_view', 'course', 'price', 'description', 'diet', 'badge'),
		'dish_lines' => array('name', 'form_item_view', 'price', 'description', 'diet', 'badge')) as $set => $want) {
		foreach ($d[$set] as $key => $card) {
			$nodes = array();
			_test_rt_nodes($card, $nodes);
			$tokens = array();
			$texts = array();
			foreach ($nodes as $node) {
				if (!empty($node['props']['_bindings'])) {
					foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
				}
				if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
			}
			foreach ($want as $t) pg_assert_true(isset($tokens[$t]), "$set $key: reads $t");
			pg_assert_true(in_array($d['dishes'][$key]['name'], $texts, true), "$set $key: sample name on the canvas");
			pg_assert_true(in_array(call_user_func($d['money'], $d['dishes'][$key]['price']), $texts, true), "$set $key: sample price on the canvas");
		}
	}
}

// A tasting card opens the reservation with the menu chosen, a room card
// the enquiry with the room: in the words the forms' lists use (the forms
// start from ?tasting= and ?room=).
function test_restaurant_links_choose_in_the_request_forms()
{
	$d = _test_rt_load();
	$l = $d['lists'];
	foreach ($d['tasting_cards'] as $key => $card) {
		$q = _test_rt_query_links($card, 'reserve');
		pg_assert_same(1, count($q), "tasting $key: one reservation link");
		pg_assert_same($d['tastings'][$key]['title'], $q[0]['tasting'], "tasting $key: names the menu");
		pg_assert_true(in_array($q[0]['tasting'], $l['tasting_choices'], true), "tasting $key: a Menu answer");
	}
	foreach ($d['room_cards'] as $key => $card) {
		$q = _test_rt_query_links($card, 'private_enquiry');
		pg_assert_same(1, count($q), "room $key: one enquiry link");
		pg_assert_true(in_array($q[0]['room'], $l['room_choices'], true), "room $key: a Room answer");
	}
	foreach (array('tasting', 'room') as $p) pg_assert_same($p, pg_sw_url_param_name($p), "?$p= is a usable name");
}

// Classes the template adds of its own start with prt-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with rt-.
function test_restaurant_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_rt_trees() as $t) _test_rt_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'prt-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'rt-') === 0, "id $id is the template's");
	}
	foreach (array('prt-brand', 'prt-display', 'prt-eyebrow', 'prt-price', 'prt-lift', 'prt-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_restaurant_no_fixed_dates()
{
	$d = _test_rt_load();
	// Prices are figures, not dates (a lira price of 2000 is no year).
	$words = function ($set) {
		foreach ($set as $k => $item) {
			foreach (array('price', 'pairing', 'glass', 'bottle') as $f) unset($set[$k][$f]);
		}
		return $set;
	};
	$json = json_encode(array($words($d['dishes']), $words($d['tastings']), $words($d['wines']), $d['rooms'], $words($d['events']), $words($d['vouchers']), _test_rt_trees()),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a reservation is "rezervasyon", a tasting
// menu "tadım menüsü", a voucher "hediye çeki".
function test_restaurant_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bReservations?\b/', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		if (preg_match('/\bTasting Menus?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tadım menü', 0, 'UTF-8') !== false, "'$key' says tadım menüsü");
		if (preg_match('/\bGift Voucher\b/', $key)) pg_assert_true(mb_stripos($tr, 'hediye çeki', 0, 'UTF-8') !== false, "'$key' says hediye çeki");
	}
	// The shared keys this template must not use: their Turkish names
	// another thing (Starters reads Hazır düzenler, Course Kurs, Glass
	// Cam, Dish Ürün, Find Us Bize Ulaşın like Contact Us).
	foreach (array('Starters', 'Course', 'Courses', 'Glass', 'Dish', 'Dish Card', 'Room Card', 'Find Us') as $k) {
		pg_assert_false(strpos($src, "lang('$k')") !== false, "the template does not say '$k' alone");
	}
}
