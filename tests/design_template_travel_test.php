<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel Agency design template's parts
 * (includes/design_templates/pinegrap-travel/): the agency's data hangs
 * together (sample destinations naming a known region, sample tours naming
 * a known destination and style with an itinerary day per day, a price for
 * every tour, deals, gift cards and testimonials with what they need, the
 * forms' pick lists without an answer twice), a price is written as the
 * shop writes it, every page link of the shared parts names a page of the
 * finished template and none names a shop page, the footer opens the lists
 * filtered, the cards read their form's fields, a deal card opens the
 * booking request with it chosen, the classes are the template's own, the
 * sample carries no fixed date and every literal has its Turkish.
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
function _test_tv_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/parts.php';
	$tour_cards = array();
	foreach (array_keys($tours) as $key) $tour_cards[$key] = $tour_card($key);
	$destination_cards = array();
	foreach (array_keys($destinations) as $key) $destination_cards[$key] = $destination_card($key);
	$deal_cards = array();
	foreach (array_keys($deals) as $key) $deal_cards[$key] = $deal_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'agency'            => $agency,
		'regions'           => $regions,
		'destinations'      => $destinations,
		'styles'            => $styles,
		'tours'             => $tours,
		'tour_prices'       => $tour_prices,
		'deposit'           => $deposit,
		'money'             => $money,
		'price_text'        => $price_text,
		'length_text'       => $length_text,
		'itinerary_text'    => $itinerary_text,
		'included'          => $included,
		'not_included'      => $not_included,
		'deals'             => $deals,
		'why_us'            => $why_us,
		'gift_cards'        => $gift_cards,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'lists'             => array(
			'region_titles'        => $region_titles,
			'destination_names'    => $destination_names,
			'style_titles'         => $style_titles,
			'tour_names'           => $tour_names,
			'booking_tour_choices' => $booking_tour_choices,
			'deal_choices'         => $deal_choices,
			'booking_statuses'     => $booking_statuses,
			'trip_kinds'           => $trip_kinds,
			'destination_choices'  => $destination_choices,
			'length_choices'       => $length_choices,
			'budget_choices'       => $budget_choices,
			'enquiry_statuses'     => $enquiry_statuses,
		),
		'radios'            => array('room_choices' => $room_choices, 'contact_choices' => $contact_choices),
		'tour_cards'        => $tour_cards,
		'destination_cards' => $destination_cards,
		'deal_cards'        => $deal_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $trip_band(), $staff_bar(), $post_card(), $ai_summary(), $tour_facts()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_tv_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_tv_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_tv_trees()
{
	$d = _test_tv_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['tour_cards']),
		array_values($d['destination_cards']), array_values($d['deal_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_tv_page_keys()
{
	return array_merge(array(
		'home', 'about', 'travel_info', 'faq', 'deals',
		'destinations', 'destination', 'destination_new', 'tours', 'tour', 'tour_new',
		'book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply', 'email_booking_new', 'email_booking_received',
		'enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_tv_shop_keys());
}

// The pages that exist only with the shop.
function _test_tv_shop_keys()
{
	return array('packages', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_travel_data_hangs_together()
{
	$d = _test_tv_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'licence') as $f) pg_assert_true(trim($d['agency'][$f]) !== '', "agency: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['agency']['phone']), 'agency: phone number');
	pg_assert_true(strpos($d['agency']['email'], '@example.com') !== false, 'agency: a sample address');
	pg_assert_same(6, count($d['destinations']), 'six sample destinations');
	$regions = array();
	foreach ($d['destinations'] as $key => $dest) {
		pg_assert_true(isset($d['regions'][$dest['region']]), "destination $key: known region");
		foreach (array('name', 'country', 'summary', 'season') as $f) pg_assert_true(trim($dest[$f]) !== '', "destination $key: $f");
		$regions[$dest['region']] = true;
	}
	pg_assert_same(count($d['regions']), count($regions), 'a destination in every region');
	pg_assert_same(6, count($d['tours']), 'six sample tours');
	$styles = array();
	$featured = 0;
	$toured = array();
	foreach ($d['tours'] as $key => $t) {
		pg_assert_true(isset($d['destinations'][$t['destination']]), "tour $key: known destination");
		pg_assert_true(isset($d['styles'][$t['style']]), "tour $key: known style");
		pg_assert_true(is_int($t['days']) && $t['days'] >= 2 && $t['days'] <= 21, "tour $key: days is a number");
		pg_assert_true(is_int($t['group']) && $t['group'] >= 2 && $t['group'] <= 12, "tour $key: a small group");
		foreach (array('name', 'summary', 'departures') as $f) pg_assert_true(trim($t[$f]) !== '', "tour $key: $f");
		pg_assert_true(count($t['highlights']) >= 4, "tour $key: highlights");
		pg_assert_same($t['days'], count($t['itinerary']), "tour $key: an itinerary entry per day");
		foreach ($t['itinerary'] as $n => $day) pg_assert_true(trim($day[0]) !== '' && trim($day[1]) !== '', "tour $key: day " . ($n + 1));
		pg_assert_true(isset($d['tour_prices'][$key]['USD']), "tour $key: has a price");
		if ($t['featured']) $featured++;
		$styles[$t['style']] = true;
		$toured[$t['destination']] = true;
	}
	pg_assert_same(count($d['destinations']), count($toured), 'a tour to every destination');
	pg_assert_true(count($styles) >= 3, 'tours in three styles or more');
	pg_assert_same(3, $featured, 'three tours on the home');
	pg_assert_same(array_keys($d['tours']), array_keys($d['tour_prices']), 'a price per tour and no other');
	foreach ($d['tour_prices'] as $key => $p) {
		foreach ($d['deposit'] as $cur => $amount) pg_assert_true($p[$cur] > $amount, "tour $key: the price is more than the deposit in $cur");
	}
	pg_assert_true(count($d['included']) >= 4 && count($d['not_included']) >= 3, 'what a tour includes and leaves out');
	pg_assert_same(3, count($d['deals']), 'three deals');
	foreach ($d['deals'] as $key => $o) {
		foreach (array('title', 'badge', 'text', 'terms') as $f) pg_assert_true(trim($o[$f]) !== '', "deal $key: $f");
	}
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	$skus = array();
	foreach ($d['gift_cards'] as $key => $g) {
		pg_assert_true((bool)preg_match('/^TRAVEL-[A-Z0-9-]+$/', $g['sku']), "gift card $key: the template's SKU");
		pg_assert_true(isset($g['price']['USD']) && $g['price']['USD'] > 0, "gift card $key: a price");
		$skus[$g['sku']] = true;
	}
	pg_assert_same(count($d['gift_cards']), count($skus), 'no SKU twice');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['tours'][$t['tour']]), "testimonial $n: about a sample tour");
		pg_assert_contains($d['tours'][$t['tour']]['name'], $t['role'], "testimonial $n: names the tour");
	}
	// The pick lists: one answer per region, destination, style, tour and
	// deal (plus "not decided", "no deal", "somewhere else", "not sure"),
	// no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['regions']), count($l['region_titles']), 'an answer per region');
	pg_assert_same(count($d['destinations']), count($l['destination_names']), 'an answer per destination');
	pg_assert_same(count($d['styles']), count($l['style_titles']), 'an answer per style');
	pg_assert_same(count($d['tours']) + 1, count($l['booking_tour_choices']), 'booking: an answer per tour and not decided');
	pg_assert_same(count($d['deals']) + 1, count($l['deal_choices']), 'an answer per deal and none');
	pg_assert_same(count($d['destinations']) + 2, count($l['destination_choices']), 'enquiry: an answer per destination, elsewhere and not sure');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['booking_statuses']), 'five states of a booking request');
	pg_assert_same(4, count($l['enquiry_statuses']), 'four states of an enquiry');
}

// A tour's price shows one amount: the base currency's figure, the USD
// figure for a currency the table does not list.
function test_travel_price_is_written_as_the_shop_writes_it()
{
	$d = _test_tv_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['tour_prices']['kyoto'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('From %s per traveller'), pg_money_text($amount, $symbol)), call_user_func($d['price_text'], 'kyoto'), 'the price line');
	pg_assert_same(sprintf(lang('%1$d days, %2$d nights'), 6, 5), call_user_func($d['length_text'], 'kyoto'), 'the length line');
}

// The itinerary is one line per day, numbered from one, in the day's order.
function test_travel_itinerary_text_is_a_line_per_day()
{
	$d = _test_tv_load();
	foreach ($d['tours'] as $key => $t) {
		$lines = explode("\n", call_user_func($d['itinerary_text'], $key));
		pg_assert_same($t['days'], count($lines), "tour $key: a line per day");
		foreach ($t['itinerary'] as $n => $day) {
			pg_assert_same(sprintf(lang('Day %1$d — %2$s: %3$s'), $n + 1, $day[0], $day[1]), $lines[$n], "tour $key: day " . ($n + 1));
		}
	}
}

function test_travel_page_links_name_template_pages()
{
	$known = array_flip(_test_tv_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_tv_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_travel_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_tv_trees());
	foreach (_test_tv_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_tv_load();
	pg_assert_contains('{{page:book}}', json_encode($d['header']), 'the header books a tour');
	$nodes = array();
	_test_tv_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the destination list filtered on each region and the
// tour list on each style, in the words the Region and Style lists use.
function test_travel_footer_filters_the_lists()
{
	$d = _test_tv_load();
	$nodes = array();
	_test_tv_nodes($d['footer'], $nodes);
	$seen = array('region' => array(), 'style' => array());
	$lists = array('{{page:destinations}}?' => array('region', 'region_titles'), '{{page:tours}}?' => array('style', 'style_titles'));
	foreach ($nodes as $node) {
		if (!isset($node['props']['href'])) continue;
		foreach ($lists as $prefix => $w) {
			if (strpos($node['props']['href'], $prefix) !== 0) continue;
			parse_str(substr($node['props']['href'], strlen($prefix)), $q);
			pg_assert_true(isset($q[$w[0]]) && in_array($q[$w[0]], $d['lists'][$w[1]], true), "the link names a {$w[0]} answer");
			$seen[$w[0]][$q[$w[0]]] = true;
		}
	}
	pg_assert_same(count($d['regions']), count($seen['region']), 'a link per region');
	pg_assert_same(count($d['styles']), count($seen['style']), 'a link per style');
}

// The values a card reads from its form's fields, and its texts.
function _test_tv_card_reads($card)
{
	$nodes = array();
	_test_tv_nodes($card, $nodes);
	$tokens = array();
	$texts = array();
	foreach ($nodes as $node) {
		if (!empty($node['props']['_bindings'])) {
			foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
		}
		if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
	}
	return array($tokens, $texts);
}

// A tour card reads every value from the Tours form's fields, a
// destination card from the Destinations form's, with the sample's own
// words on the canvas.
function test_travel_cards_read_their_records()
{
	$d = _test_tv_load();
	foreach ($d['tour_cards'] as $key => $card) {
		list($tokens, $texts) = _test_tv_card_reads($card);
		foreach (array('photo_1', 'name', 'form_item_view', 'style', 'summary', 'length', 'group_size', 'destination', 'departures', 'price') as $t) {
			pg_assert_true(isset($tokens[$t]), "tour $key: card reads $t");
		}
		$t = $d['tours'][$key];
		pg_assert_true(in_array($t['name'], $texts, true), "tour $key: sample name on the canvas");
		pg_assert_true(in_array($d['destinations'][$t['destination']]['name'], $texts, true), "tour $key: sample destination on the canvas");
		pg_assert_true(in_array(call_user_func($d['price_text'], $key), $texts, true), "tour $key: sample price on the canvas");
	}
	foreach ($d['destination_cards'] as $key => $card) {
		list($tokens, $texts) = _test_tv_card_reads($card);
		foreach (array('cover', 'name', 'form_item_view', 'region', 'country', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "destination $key: card reads $t");
		}
		$dest = $d['destinations'][$key];
		pg_assert_true(in_array($dest['name'], $texts, true), "destination $key: sample name on the canvas");
		pg_assert_true(in_array($d['regions'][$dest['region']]['title'], $texts, true), "destination $key: sample region on the canvas");
	}
}

// A deal card opens the booking request with the deal chosen, in the
// words the Deal list uses (the form starts from ?deal=).
function test_travel_deal_card_chooses_in_the_booking_request()
{
	$d = _test_tv_load();
	foreach ($d['deal_cards'] as $key => $card) {
		$nodes = array();
		_test_tv_nodes($card, $nodes);
		$hrefs = array();
		foreach ($nodes as $node) {
			if (isset($node['props']['href']) && strpos($node['props']['href'], '{{page:book}}?') === 0) $hrefs[] = $node['props']['href'];
		}
		pg_assert_same(1, count($hrefs), "deal $key: one booking link");
		parse_str(substr($hrefs[0], strlen('{{page:book}}?')), $q);
		pg_assert_true(in_array($q['deal'], $d['lists']['deal_choices'], true), "deal $key: the link names an answer");
	}
}

// Classes the template adds of its own start with ptv-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with tv-.
function test_travel_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_tv_trees() as $t) _test_tv_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch)-/', $c), "class $c is not another template's");
			if (strpos($c, 'ptv-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'tv-') === 0, "id $id is the template's");
	}
	foreach (array('ptv-brand', 'ptv-display', 'ptv-eyebrow', 'ptv-price', 'ptv-lift', 'ptv-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later; departures are words.
function test_travel_no_fixed_dates()
{
	$d = _test_tv_load();
	$json = json_encode(array($d['destinations'], $d['tours'], $d['deals'], $d['gift_cards'], _test_tv_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a tour is "tur", a destination "destinasyon",
// a booking "rezervasyon", a deposit "kapora".
function test_travel_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bTours?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		if (preg_match('/\bDestinations?\b/', $key)) pg_assert_true(mb_stripos($tr, 'destinasyon', 0, 'UTF-8') !== false, "'$key' says destinasyon");
		if (preg_match('/\bBookings?\b/', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		if (preg_match('/\bdeposits?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
	}
}
