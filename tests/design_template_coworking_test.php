<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Coworking design template's parts
 * (includes/design_templates/pinegrap-coworking/): the space's data hangs
 * together (spaces naming a known kind and floor with seats and a price,
 * a space of every kind, plans with a price and a term, events in a known
 * space and topic, the forms' pick lists without an answer twice), a price
 * is written as the shop writes it, every page link of the shared parts
 * names a page of the finished template and none names a shop page, the
 * footer opens the space list filtered, the space and event cards read
 * their forms' fields, the kind, plan and trial links make their choice
 * in the forms, the classes are the template's own, the sample carries no
 * fixed date and every literal has its Turkish.
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
function _test_cw_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/parts.php';
	$kind_cards = array();
	foreach (array_keys($kinds) as $key) $kind_cards[$key] = $kind_card($key);
	$space_cards = array();
	foreach (array_keys($spaces) as $key) $space_cards[$key] = $space_card($key);
	$plan_cards = array();
	foreach (array_keys($plans) as $key) $plan_cards[$key] = $plan_card($key);
	$amenity_cards = array();
	foreach (array_keys($amenities) as $key) $amenity_cards[$key] = $amenity_card($key);
	$event_cards = array();
	foreach (array_keys($events) as $key) $event_cards[$key] = $event_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'hub'               => $hub,
		'opening_hours'     => $opening_hours,
		'floors'            => $floors,
		'kinds'             => $kinds,
		'spaces'            => $spaces,
		'money'             => $money,
		'space_price'       => $space_price,
		'plans'             => $plans,
		'plan_price'        => $plan_price,
		'amenities'         => $amenities,
		'perks'             => $perks,
		'event_topics'      => $event_topics,
		'events'            => $events,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'tour_times'        => $tour_times,
		'lists'             => array(
			'kind_titles'      => $kind_titles,
			'space_names'      => $space_names,
			'floor_titles'     => $floor_titles,
			'topic_titles'     => $topic_titles,
			'event_titles'     => $event_titles,
			'plan_choices'     => $plan_choices,
			'tour_choices'     => $tour_choices,
			'tour_times'       => $tour_times,
			'team_sizes'       => $team_sizes,
			'start_choices'    => $start_choices,
			'work_choices'     => $work_choices,
			'tour_statuses'    => $tour_statuses,
			'enquiry_statuses' => $enquiry_statuses,
			'signup_statuses'  => $signup_statuses,
		),
		'radios'            => array('contact_choices' => $contact_choices),
		'kind_cards'        => $kind_cards,
		'space_cards'       => $space_cards,
		'plan_cards'        => $plan_cards,
		'amenity_cards'     => $amenity_cards,
		'event_cards'       => $event_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'notice'            => $notice(),
		'tour_band'         => $tour_band(),
		'shared'            => array($account_bar_tree(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_cw_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cw_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_cw_trees()
{
	$d = _test_cw_load();
	return array_merge(array($d['header'], $d['footer'], $d['notice'], $d['tour_band']), $d['shared'],
		array_values($d['kind_cards']), array_values($d['space_cards']), array_values($d['plan_cards']),
		array_values($d['amenity_cards']), array_values($d['event_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_cw_page_keys()
{
	return array_merge(array(
		'home', 'plans', 'amenities', 'about', 'faq', 'location',
		'spaces', 'space', 'space_new', 'book_online',
		'tour', 'tour_view', 'my_tours', 'staff_tours', 'privacy', 'email_reply',
		'office_enquiry', 'enquiry_view', 'staff_enquiries',
		'events', 'event', 'event_new', 'event_signup', 'signup_view', 'my_signups', 'staff_signups',
		'members', 'house_guide',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_cw_shop_keys());
}

// The pages that exist only with the shop.
function _test_cw_shop_keys()
{
	return array('memberships', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_coworking_data_hangs_together()
{
	$d = _test_cw_load();
	foreach (array('tagline', 'story', 'address', 'region') as $f) pg_assert_true(trim($d['hub'][$f]) !== '', "hub: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['hub']['phone']), 'hub: phone number');
	pg_assert_true(strpos($d['hub']['email'], '@example.com') !== false, 'hub: a sample address');
	foreach ($d['kinds'] as $key => $k) {
		pg_assert_true(in_array($k['unit'], array('hour', 'day', 'month'), true), "kind $key: a known unit");
		foreach (array('icon', 'title', 'text') as $f) pg_assert_true(trim($k[$f]) !== '', "kind $key: $f");
	}
	$kinds = array();
	foreach ($d['spaces'] as $key => $s) {
		pg_assert_true(isset($d['kinds'][$s['kind']]), "space $key: known kind");
		pg_assert_true(isset($d['floors'][$s['floor']]), "space $key: known floor");
		pg_assert_true(is_int($s['seats']) && $s['seats'] > 0, "space $key: seats");
		pg_assert_true(isset($s['price']['USD']) && $s['price']['USD'] > 0, "space $key: a price");
		pg_assert_true(count($s['features']) >= 3, "space $key: features");
		foreach (array('name', 'summary') as $f) pg_assert_true(trim($s[$f]) !== '', "space $key: $f");
		$kinds[$s['kind']] = true;
	}
	pg_assert_same(count($d['kinds']), count($kinds), 'a space of every kind');
	$skus = array();
	$featured = 0;
	foreach ($d['plans'] as $key => $p) {
		pg_assert_true((bool)preg_match('/^CW-[A-Z0-9-]+$/', $p['sku']), "plan $key: the template's SKU");
		pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, "plan $key: a price");
		pg_assert_true(is_int($p['days']) && $p['days'] > 0, "plan $key: a term in days");
		pg_assert_true(in_array($p['unit'], array('day', 'month'), true), "plan $key: paid by the day or the month");
		pg_assert_true(count($p['points']) >= 3, "plan $key: points");
		if ($p['featured']) $featured++;
		$skus[$p['sku']] = true;
	}
	pg_assert_same(count($d['plans']), count($skus), 'no SKU twice');
	pg_assert_same(1, $featured, 'one plan marked as the most chosen');
	pg_assert_same(1, $d['plans']['day']['days'], 'a day pass is one day of membership');
	foreach ($d['events'] as $key => $e) {
		pg_assert_true(isset($d['event_topics'][$e['topic']]), "event $key: known topic");
		pg_assert_true(isset($d['spaces'][$e['space']]), "event $key: held in a known space");
		pg_assert_true(is_int($e['in_days']) && $e['in_days'] > 0, "event $key: dated after the opening day");
		pg_assert_true((bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $e['time']), "event $key: a start time");
		pg_assert_true(is_int($e['minutes']) && $e['minutes'] > 0, "event $key: minutes");
	}
	pg_assert_same(4, count($d['figures']), 'four figures');
	pg_assert_true(count($d['perks']) >= 3, 'perks');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['kinds'][$t['kind']]), "testimonial $n: about a kind of space");
		pg_assert_contains($d['kinds'][$t['kind']]['title'], $t['role'], "testimonial $n: names the kind");
	}
	// The pick lists: one answer per kind, space, floor, topic, event and
	// plan (plus the trial day and the office), no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['kinds']), count($l['kind_titles']), 'an answer per kind');
	pg_assert_same(count($d['spaces']), count($l['space_names']), 'an answer per space');
	pg_assert_same(count($d['floors']), count($l['floor_titles']), 'an answer per floor');
	pg_assert_same(count($d['event_topics']), count($l['topic_titles']), 'an answer per topic');
	pg_assert_same(count($d['events']), count($l['event_titles']), 'an answer per event');
	pg_assert_same(count($d['plans']) + 2, count($l['tour_choices']), 'tour: an answer per plan, the trial day and the office');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	$sorted = $l['tour_times'];
	sort($sorted);
	pg_assert_same($sorted, $l['tour_times'], 'the tour times in order');
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list, with the unit's words.
function test_coworking_price_is_written_as_the_shop_writes_it()
{
	$d = _test_cw_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$text = function ($prices) use ($base, $symbol) {
		return pg_money_text(isset($prices[$base]) ? $prices[$base] : $prices['USD'], $symbol);
	};
	pg_assert_same($text($d['plans']['open']['price']), call_user_func($d['money'], $d['plans']['open']['price']));
	pg_assert_same(sprintf(lang('%s a month'), $text($d['plans']['open']['price'])), call_user_func($d['plan_price'], 'open'), 'a monthly plan');
	pg_assert_same(sprintf(lang('%s a day'), $text($d['plans']['day']['price'])), call_user_func($d['plan_price'], 'day'), 'the day pass');
	pg_assert_same(sprintf(lang('%s an hour'), $text($d['spaces']['boardroom']['price'])), call_user_func($d['space_price'], 'boardroom'), 'a meeting room');
	pg_assert_same(sprintf(lang('%s a month'), $text($d['spaces']['office4']['price'])), call_user_func($d['space_price'], 'office4'), 'an office');
}

function test_coworking_page_links_name_template_pages()
{
	$known = array_flip(_test_cw_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_cw_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_coworking_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_cw_trees());
	foreach (_test_cw_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_cw_load();
	pg_assert_contains('{{page:tour}}', json_encode($d['header']), 'the header books a tour');
	$nodes = array();
	_test_cw_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links found in a tree that open the given page with a query.
function _test_cw_query_links($tree, $page)
{
	$nodes = array();
	_test_cw_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer and the kind cards open the space list filtered on a kind,
// in the words the Kind list uses.
function test_coworking_kind_links_filter_the_space_list()
{
	$d = _test_cw_load();
	$seen = array();
	foreach (_test_cw_query_links($d['footer'], 'spaces') as $q) {
		pg_assert_true(in_array($q['kind'], $d['lists']['kind_titles'], true), 'the footer link names a Kind answer');
		$seen[$q['kind']] = true;
	}
	pg_assert_same(count($d['kinds']), count($seen), 'a footer link per kind');
	foreach ($d['kind_cards'] as $key => $card) {
		$q = _test_cw_query_links($card, 'spaces');
		pg_assert_same(1, count($q), "kind $key: one link");
		pg_assert_same($d['kinds'][$key]['title'], $q[0]['kind'], "kind $key: the link names the kind");
	}
}

// The tokens a card binds and the texts it shows on the canvas.
function _test_cw_card_reads($card)
{
	$nodes = array();
	_test_cw_nodes($card, $nodes);
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

// A space card and an event card read every value from their forms'
// fields, with the sample's own words on the canvas.
function test_coworking_cards_read_their_forms()
{
	$d = _test_cw_load();
	foreach ($d['space_cards'] as $key => $card) {
		list($tokens, $texts) = _test_cw_card_reads($card);
		foreach (array('photo', 'name', 'form_item_view', 'kind', 'summary', 'seats', 'floor', 'rate') as $t) {
			pg_assert_true(isset($tokens[$t]), "space $key: card reads $t");
		}
		$s = $d['spaces'][$key];
		pg_assert_true(in_array($s['name'], $texts, true), "space $key: sample name on the canvas");
		pg_assert_true(in_array($d['kinds'][$s['kind']]['title'], $texts, true), "space $key: sample kind on the canvas");
		pg_assert_true(in_array(call_user_func($d['space_price'], $key), $texts, true), "space $key: sample price on the canvas");
	}
	foreach ($d['event_cards'] as $key => $card) {
		list($tokens, $texts) = _test_cw_card_reads($card);
		foreach (array('title', 'form_item_view', 'topic', 'event_date', 'start_time', 'summary', 'place') as $t) {
			pg_assert_true(isset($tokens[$t]), "event $key: card reads $t");
		}
		$e = $d['events'][$key];
		pg_assert_true(in_array($e['title'], $texts, true), "event $key: sample title on the canvas");
		pg_assert_true(in_array($d['spaces'][$e['space']]['name'], $texts, true), "event $key: sample place on the canvas");
	}
}

// A plan card opens the tour request with the plan chosen, the notice and
// the band with the free trial day: in the words the Plan list uses (the
// form starts from ?plan=).
function test_coworking_links_choose_in_the_tour_request()
{
	$d = _test_cw_load();
	$l = $d['lists'];
	foreach ($d['plan_cards'] as $key => $card) {
		$q = _test_cw_query_links($card, 'tour');
		pg_assert_same(1, count($q), "plan $key: one tour link");
		pg_assert_same($d['plans'][$key]['title'], $q[0]['plan'], "plan $key: the link names the plan");
		pg_assert_true(in_array($q[0]['plan'], $l['tour_choices'], true), "plan $key: a Plan answer");
	}
	foreach (array('notice', 'tour_band') as $part) {
		$q = _test_cw_query_links($d[$part], 'tour');
		pg_assert_true(count($q) >= 1, "$part: a trial link");
		pg_assert_same(lang('Free trial day'), $q[0]['plan'], "$part: asks for the trial day");
		pg_assert_true(in_array($q[0]['plan'], $l['tour_choices'], true), "$part: a Plan answer");
	}
}

// Classes the template adds of its own start with pcw-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with cw-.
function test_coworking_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_cw_trees() as $t) _test_cw_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pch|pcn|pmu|pph|psh|ptv|pwd|pmk|psc|pai|pmz)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'pcw-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cw-') === 0, "id $id is the template's");
	}
	foreach (array('pcw-brand', 'pcw-display', 'pcw-eyebrow', 'pcw-price', 'pcw-lift') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_coworking_no_fixed_dates()
{
	$d = _test_cw_load();
	$json = json_encode(array($d['spaces'], $d['plans'], $d['amenities'], $d['perks'], $d['events'], _test_cw_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a desk is "masa", a meeting room "toplantı
// odası", a membership "üyelik", a tour "tur", an event "etkinlik".
function test_coworking_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/parts.php');
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
}
