<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Florist design template's parts
 * (includes/design_templates/pinegrap-florist/): the florist's data hangs
 * together (bouquets naming a known occasion, a price for every bouquet,
 * plan, event service and delivery area, sample work naming a known kind
 * and season, testimonials about a known bouquet, the forms' pick lists
 * without an answer twice), a price is written as the shop writes it,
 * every page link of the shared parts names a page of the finished
 * template and none names a shop page, the footer opens Our Work filtered
 * and the order request with an occasion chosen, a work card reads its
 * form's fields, the bouquet, occasion, plan and event cards open their
 * request with their choice made, the classes are the template's own, the
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
function _test_fl_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/parts.php';
	$cards = array('bouquet' => array(), 'occasion' => array(), 'plan' => array(), 'event' => array(), 'work' => array());
	foreach (array_keys($bouquets) as $key) $cards['bouquet'][$key] = $bouquet_card($key);
	foreach (array_keys($occasions) as $key) $cards['occasion'][$key] = $occasion_card($key);
	foreach (array_keys($plans) as $key) $cards['plan'][$key] = $plan_card($key);
	foreach (array_keys($event_services) as $key) $cards['event'][$key] = $event_card($key);
	foreach (array_keys($works) as $key) $cards['work'][$key] = $work_card($key);
	$more = array();
	foreach (array_keys($testimonials) as $n) $more[] = $testimonial_card($n);
	foreach (array_keys($zones) as $key) $more[] = $zone_card($key);
	foreach (array_keys($seasons) as $key) $more[] = $season_card($key);
	$out = array(
		'florist'            => $florist,
		'cutoff_text'        => $cutoff_text,
		'occasions'          => $occasions,
		'bouquets'           => $bouquets,
		'bouquet_prices'     => $bouquet_prices,
		'sizes'              => $sizes,
		'plans'              => $plans,
		'plan_prices'        => $plan_prices,
		'plan_total'         => $plan_total,
		'event_services'     => $event_services,
		'event_prices'       => $event_prices,
		'zones'              => $zones,
		'zone_fees'          => $zone_fees,
		'slots'              => $slots,
		'seasons'            => $seasons,
		'care'               => $care,
		'works'              => $works,
		'why_us'             => $why_us,
		'figures'            => $figures,
		'testimonials'       => $testimonials,
		'extras'             => $extras,
		'money'              => $money,
		'texts'              => array(
			'bouquet' => $bouquet_price_text,
			'total'   => $plan_total_text,
			'event'   => $event_price_text,
			'flowers' => $work_flowers_text,
		),
		'lists'              => array(
			'occasion_titles'  => $occasion_titles,
			'bouquet_titles'   => $bouquet_titles,
			'size_titles'      => $size_titles,
			'plan_titles'      => $plan_titles,
			'event_titles'     => $event_titles,
			'zone_titles'      => $zone_titles,
			'slot_titles'      => $slot_titles,
			'season_titles'    => $season_titles,
			'work_titles'      => $work_titles,
			'occasion_choices' => $occasion_choices,
			'bouquet_choices'  => $bouquet_choices,
			'event_choices'    => $event_choices,
			'weekday_choices'  => $weekday_choices,
			'budget_choices'   => $budget_choices,
			'request_statuses' => $request_statuses,
			'enquiry_statuses' => $enquiry_statuses,
		),
		'radios'             => array('card_choices' => $card_choices, 'contact_choices' => $contact_choices),
		'cards'              => $cards,
		'more_cards'         => $more,
		'header'             => $header(),
		'footer'             => $footer(),
		'band'               => $delivery_band(),
		'shared'             => array($account_bar_tree(), $notice(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_fl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fl_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_fl_trees()
{
	$d = _test_fl_load();
	$trees = array_merge(array($d['header'], $d['footer'], $d['band']), $d['shared'], $d['more_cards']);
	foreach ($d['cards'] as $set) $trees = array_merge($trees, array_values($set));
	return $trees;
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_fl_page_keys()
{
	return array_merge(array(
		'home', 'bouquets', 'occasions', 'subscriptions', 'events', 'delivery', 'care', 'about', 'faq',
		'work', 'work_item', 'work_new',
		'order_request', 'request_view', 'my_requests', 'staff_requests', 'privacy', 'email_reply', 'email_request_new', 'email_request_received',
		'event_enquiry', 'event_enquiry_view', 'staff_events', 'subscribe', 'subscription_view', 'consultation',
		'email_event_new', 'email_event_received', 'email_subscription_new', 'email_subscription_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_fl_shop_keys());
}

// The pages that exist only with the shop.
function _test_fl_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_florist_data_hangs_together()
{
	$d = _test_fl_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'promise') as $f) pg_assert_true(trim($d['florist'][$f]) !== '', "florist: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['florist']['phone']), 'florist: phone number');
	pg_assert_true(strpos($d['florist']['email'], '@example.com') !== false, 'florist: a sample address');
	pg_assert_true((bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $d['florist']['cutoff']), 'florist: a cut-off time');
	pg_assert_true(count($d['occasions']) >= 6, 'six occasions');
	pg_assert_same(array_keys($d['bouquets']), array_keys($d['bouquet_prices']), 'a price per bouquet and no other');
	$featured = 0;
	$occasions = array();
	foreach ($d['bouquets'] as $key => $b) {
		pg_assert_true(isset($d['occasions'][$b['occasion']]), "bouquet $key: known occasion");
		foreach (array('title', 'style', 'text') as $f) pg_assert_true(trim($b[$f]) !== '', "bouquet $key: $f");
		pg_assert_true(count($b['flowers']) >= 1, "bouquet $key: its flowers");
		if ($b['featured']) $featured++;
		$occasions[$b['occasion']] = true;
	}
	pg_assert_same(count($d['occasions']), count($occasions), 'a bouquet for every occasion');
	pg_assert_same(4, $featured, 'four bouquets on the home');
	pg_assert_same(3, count($d['sizes']), 'three sizes');
	pg_assert_same(array_keys($d['plans']), array_keys($d['plan_prices']), 'a price per plan and no other');
	$featured = 0;
	foreach ($d['plans'] as $key => $p) {
		pg_assert_true(is_int($p['deliveries']) && $p['deliveries'] > 0, "plan $key: deliveries in three months");
		pg_assert_true(count($p['includes']) >= 4, "plan $key: what it includes");
		if ($p['featured']) $featured++;
	}
	pg_assert_same(1, $featured, 'one plan marked most loved');
	pg_assert_same(array_keys($d['event_services']), array_keys($d['event_prices']), 'a price per event service and no other');
	foreach ($d['event_services'] as $key => $s) pg_assert_true(count($s['includes']) >= 4, "event service $key: what it includes");
	pg_assert_same(array_keys($d['zones']), array_keys($d['zone_fees']), 'a fee per delivery area and no other');
	pg_assert_same(3, count($d['slots']), 'three delivery times');
	pg_assert_same(4, count($d['seasons']), 'four seasons');
	foreach ($d['seasons'] as $key => $s) pg_assert_true(count($s['flowers']) >= 3, "season $key: its flowers");
	pg_assert_true(count($d['care']) >= 4, 'four care steps or more');
	pg_assert_true(count($d['works']) >= 4, 'four sample works');
	$featured = 0;
	$types = array();
	foreach ($d['works'] as $key => $w) {
		pg_assert_true(isset($d['event_services'][$w['type']]), "work $key: known kind");
		pg_assert_true(isset($d['seasons'][$w['season']]), "work $key: known season");
		foreach (array('title', 'summary', 'story') as $f) pg_assert_true(trim($w[$f]) !== '', "work $key: $f");
		pg_assert_true(count($w['flowers']) >= 2, "work $key: flowers");
		if ($w['featured']) $featured++;
		$types[$w['type']] = true;
	}
	pg_assert_same(count($d['event_services']), count($types), 'work of every kind');
	pg_assert_same(3, $featured, 'three works on the home');
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['bouquets'][$t['bouquet']]), "testimonial $n: about a known bouquet");
		foreach (array('name', 'role', 'quote') as $f) pg_assert_true(trim($t[$f]) !== '', "testimonial $n: $f");
	}
	$skus = array();
	foreach ($d['extras'] as $key => $x) {
		pg_assert_true((bool)preg_match('/^FLORIST-[A-Z0-9-]+$/', $x['sku']), "extra $key: the template's SKU");
		pg_assert_true(isset($x['price']['USD']) && $x['price']['USD'] > 0, "extra $key: a price");
		pg_assert_true(is_bool($x['shippable']), "extra $key: shippable or not");
		$skus[$x['sku']] = true;
	}
	pg_assert_same(count($d['extras']), count($skus), 'no SKU twice');
	$l = $d['lists'];
	pg_assert_same(count($d['occasions']) + 1, count($l['occasion_choices']), 'an answer per occasion and just because');
	pg_assert_same(count($d['bouquets']) + 1, count($l['bouquet_choices']), 'an answer per bouquet and one made for me');
	pg_assert_same(count($d['event_services']) + 1, count($l['event_choices']), 'an answer per event service and something else');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(6, count($l['request_statuses']), 'six states of an order request');
	pg_assert_same(5, count($l['enquiry_statuses']), 'five states of an event enquiry');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list. Three months of a plan cost its
// deliveries times the price of one, in every currency.
function test_florist_price_is_written_as_the_shop_writes_it()
{
	$d = _test_fl_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$pick = function ($prices) use ($base, $symbol) {
		return pg_money_text(isset($prices[$base]) ? $prices[$base] : $prices['USD'], $symbol);
	};
	pg_assert_same($pick($d['bouquet_prices']['blush']), call_user_func($d['money'], $d['bouquet_prices']['blush']));
	pg_assert_same(sprintf(lang('From %s'), $pick($d['bouquet_prices']['blush'])), call_user_func($d['texts']['bouquet'], 'blush'), 'the bouquet line');
	pg_assert_true(strpos(json_encode($d['cards']['plan']['weekly']), json_encode($pick($d['plan_prices']['weekly']))) !== false, 'the plan card shows the price of one delivery');
	$fee = 0;
	foreach ($d['more_cards'] as $card) {
		$json = json_encode($card);
		if (strpos($json, json_encode($d['zones']['centre']['title'])) !== false && strpos($json, json_encode($pick($d['zone_fees']['centre']))) !== false) $fee++;
	}
	pg_assert_same(1, $fee, 'the centre card shows its delivery fee');
	pg_assert_same(sprintf(lang('Starting at %s'), $pick($d['event_prices']['weddings'])), call_user_func($d['texts']['event'], 'weddings'), 'the event line');
	foreach ($d['plans'] as $key => $p) {
		$total = call_user_func($d['plan_total'], $key);
		foreach ($d['plan_prices'][$key] as $cur => $amount) pg_assert_same($amount * $p['deliveries'], $total[$cur], "plan $key: three months in $cur");
		pg_assert_same(sprintf(lang('%1$s for %2$d deliveries over three months'), $pick($total), $p['deliveries']), call_user_func($d['texts']['total'], $key), "plan $key: the total line");
	}
	pg_assert_same(sprintf(lang('Order by %s for delivery today'), $d['florist']['cutoff']), call_user_func($d['cutoff_text']), 'the cut-off line');
	foreach ($d['works'] as $key => $w) {
		pg_assert_same($w['flowers'], explode("\n", call_user_func($d['texts']['flowers'], $key)), "work $key: a line per flower");
	}
}

function test_florist_page_links_name_template_pages()
{
	$known = array_flip(_test_fl_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_fl_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_florist_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_fl_trees());
	foreach (_test_fl_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_fl_load();
	pg_assert_contains('{{page:order_request}}', json_encode($d['header']), 'the header opens the order request');
	pg_assert_contains('{{page:delivery}}', json_encode($d['band']), 'the band links the delivery page');
	pg_assert_contains($d['florist']['cutoff'], json_encode($d['shared'][1]), 'the notice names the cut-off');
	$nodes = array();
	_test_fl_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links in a tree that start with a prefix, their query read.
function _test_fl_queries($tree, $prefix)
{
	$nodes = array();
	_test_fl_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer opens Our Work filtered on each kind and the order request
// with each occasion chosen, in the words the forms' lists use.
function test_florist_footer_filters_and_chooses()
{
	$d = _test_fl_load();
	$seen = array();
	foreach (_test_fl_queries($d['footer'], '{{page:work}}?') as $q) {
		pg_assert_true(isset($q['type']) && in_array($q['type'], $d['lists']['event_titles'], true), 'the link names a kind');
		$seen[$q['type']] = true;
	}
	pg_assert_same(count($d['event_services']), count($seen), 'a link per kind');
	$seen = array();
	foreach (_test_fl_queries($d['footer'], '{{page:order_request}}?') as $q) {
		pg_assert_true(isset($q['occasion']) && in_array($q['occasion'], $d['lists']['occasion_choices'], true), 'the link names an occasion');
		$seen[$q['occasion']] = true;
	}
	pg_assert_same(count($d['occasions']), count($seen), 'a link per occasion');
}

// A work card reads every value from the Our Work form's fields, with the
// sample's own words on the canvas.
function test_florist_work_card_reads_its_record()
{
	$d = _test_fl_load();
	foreach ($d['cards']['work'] as $key => $card) {
		$nodes = array();
		_test_fl_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo_1', 'title', 'form_item_view', 'type', 'season', 'summary') as $t) {
			pg_assert_true(isset($tokens[$t]), "work $key: card reads $t");
		}
		$w = $d['works'][$key];
		pg_assert_true(in_array($w['title'], $texts, true), "work $key: sample title on the canvas");
		pg_assert_true(in_array($d['event_services'][$w['type']]['title'], $texts, true), "work $key: sample kind on the canvas");
	}
}

// The bouquet, occasion, plan and event cards open their request with
// their choice made, in the words the form's lists use (the form starts
// from the query).
function test_florist_cards_choose_in_the_request()
{
	$d = _test_fl_load();
	$sets = array(
		'bouquet'  => array('{{page:order_request}}?', 'bouquet', 'bouquet_choices'),
		'occasion' => array('{{page:order_request}}?', 'occasion', 'occasion_choices'),
		'plan'     => array('{{page:subscribe}}?', 'plan', 'plan_titles'),
		'event'    => array('{{page:event_enquiry}}?', 'event', 'event_choices'),
	);
	foreach ($sets as $set => $s) {
		foreach ($d['cards'][$set] as $key => $card) {
			$qs = _test_fl_queries($card, $s[0]);
			pg_assert_same(1, count($qs), "$set $key: one request link");
			pg_assert_true(isset($qs[0][$s[1]]) && in_array($qs[0][$s[1]], $d['lists'][$s[2]], true), "$set $key: the link names an answer");
		}
	}
}

// Classes the template adds of its own start with pfl-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with fl-.
function test_florist_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_fl_trees() as $t) _test_fl_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmk|pmu|pmz|plx|pdl|plw|psl|psh|psc|pco|pcl|pcn|pgy|pch|ptv|pph|pwd|pai)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pfl-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fl-') === 0, "id $id is the template's");
	}
	foreach (array('pfl-brand', 'pfl-display', 'pfl-eyebrow', 'pfl-price', 'pfl-lift', 'pfl-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_florist_no_fixed_dates()
{
	$d = _test_fl_load();
	// Prices are figures, not dates: the extras are read without theirs.
	$extras = array();
	foreach ($d['extras'] as $key => $x) {
		unset($x['price']);
		$extras[$key] = $x;
	}
	$json = json_encode(array($d['works'], $d['bouquets'], $d['plans'], $extras, _test_fl_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a bouquet is "buket", a subscription
// "abonelik", delivery "teslimat", an occasion "özel gün".
function test_florist_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bBouquets?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'buket', 0, 'UTF-8') !== false, "'$key' says buket");
		if (preg_match('/\bSubscriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'abonel', 0, 'UTF-8') !== false, "'$key' says abonelik");
		if (preg_match('/\bDelivery\b/', $key)) pg_assert_true(mb_stripos($tr, 'teslim', 0, 'UTF-8') !== false, "'$key' says teslimat");
		if (preg_match('/\bOccasions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'özel gün', 0, 'UTF-8') !== false, "'$key' says özel gün");
	}
}
