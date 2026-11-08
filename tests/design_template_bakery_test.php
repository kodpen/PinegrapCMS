<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bakery design template's parts
 * (includes/design_templates/pinegrap-bakery/): the bakery's data hangs
 * together (bakes of a known kind and tag, every kind with bakes, cakes of
 * a known occasion and size, classes of a known level, prices in every
 * table, the forms' pick lists without an answer twice), a price is
 * written as the shop writes it, every page link of the shared parts names
 * a page of the finished template and none names a shop page, the footer
 * opens the class list filtered and the daily bake at each kind, a class
 * card reads the Baking Classes form's fields, the cake and range links
 * make their choice in the request forms, the classes are the template's
 * own, the sample carries no fixed date and every literal has its Turkish.
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
function _test_bk_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/parts.php';
	$bake_sections = array();
	foreach (array_keys($categories) as $key) $bake_sections[$key] = $bake_section($key);
	$cake_cards = array();
	foreach (array_keys($cakes) as $key) $cake_cards[$key] = $cake_card($key);
	$class_cards = array();
	foreach (array_keys($baking_classes) as $key) $class_cards[$key] = $class_card($key);
	$range_cards = array();
	foreach (array_keys($wholesale_ranges) as $key) $range_cards[$key] = $range_card($key);
	$testimonial_cards = array();
	foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
	$out = array(
		'bakery'            => $bakery,
		'opening_hours'     => $opening_hours,
		'pickup'            => $pickup,
		'categories'        => $categories,
		'diet_tags'         => $diet_tags,
		'bakes'             => $bakes,
		'occasions'         => $occasions,
		'cake_sizes'        => $cake_sizes,
		'cakes'             => $cakes,
		'levels'            => $levels,
		'baking_classes'    => $baking_classes,
		'wholesale_ranges'  => $wholesale_ranges,
		'figures'           => $figures,
		'testimonials'      => $testimonials,
		'money'             => $money,
		'from_price'        => $from_price,
		'lists'             => array(
			'occasion_choices'     => $occasion_choices,
			'cake_choices'         => $cake_choices,
			'size_choices'         => $size_choices,
			'flavour_choices'      => $flavour_choices,
			'filling_choices'      => $filling_choices,
			'class_names'          => $class_names,
			'level_titles'         => $level_titles,
			'range_titles'         => $range_titles,
			'business_choices'     => $business_choices,
			'delivery_day_choices' => $delivery_day_choices,
			'cake_statuses'        => $cake_statuses,
			'booking_statuses'     => $booking_statuses,
			'enquiry_statuses'     => $enquiry_statuses,
		),
		'radios'            => array('fulfilment_choices' => $fulfilment_choices, 'contact_choices' => $contact_choices),
		'bake_sections'     => $bake_sections,
		'cake_cards'        => $cake_cards,
		'class_cards'       => $class_cards,
		'range_cards'       => $range_cards,
		'testimonial_cards' => $testimonial_cards,
		'header'            => $header(),
		'footer'            => $footer(),
		'shared'            => array($account_bar_tree(), $notice(), $order_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_bk_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bk_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_bk_trees()
{
	$d = _test_bk_load();
	return array_merge(array($d['header'], $d['footer']), $d['shared'], array_values($d['bake_sections']),
		array_values($d['cake_cards']), array_values($d['class_cards']), array_values($d['range_cards']), $d['testimonial_cards']);
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_bk_page_keys()
{
	return array_merge(array(
		'home', 'bakes', 'cakes', 'how_to_order', 'wholesale', 'visit', 'about', 'faq',
		'classes', 'class', 'class_new',
		'cake_order', 'cake_order_view', 'my_cake_orders', 'staff_cake_orders',
		'class_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_reply',
		'email_cake_order_new', 'email_cake_order_received', 'email_booking_new', 'email_booking_received',
		'wholesale_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	), _test_bk_shop_keys());
}

// The pages that exist only with the shop.
function _test_bk_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

// A price table: a positive figure per currency, USD always among them.
function _test_bk_price_ok($prices)
{
	if (!isset($prices['USD'])) return false;
	foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) {
		if (!isset($prices[$c]) || !is_int($prices[$c]) || $prices[$c] <= 0) return false;
	}
	return true;
}

function test_bakery_data_hangs_together()
{
	$d = _test_bk_load();
	foreach (array('tagline', 'story', 'address', 'region') as $f) pg_assert_true(trim($d['bakery'][$f]) !== '', "bakery: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['bakery']['phone']), 'bakery: phone number');
	pg_assert_true(strpos($d['bakery']['email'], '@example.com') !== false, 'bakery: a sample address');
	pg_assert_same(3, count($d['opening_hours']), 'opening hours in three lines');
	foreach ($d['pickup'] as $k => $text) pg_assert_true(trim($text) !== '', "pickup: $k");
	$kinds = array();
	foreach ($d['bakes'] as $key => $b) {
		pg_assert_true(isset($d['categories'][$b['category']]), "bake $key: known kind");
		foreach ($b['tags'] as $t) pg_assert_true(isset($d['diet_tags'][$t]), "bake $key: known tag $t");
		pg_assert_true(_test_bk_price_ok($b['price']), "bake $key: a price per currency");
		foreach (array('name', 'summary') as $f) pg_assert_true(trim($b[$f]) !== '', "bake $key: $f");
		$kinds[$b['category']] = true;
	}
	pg_assert_same(count($d['categories']), count($kinds), 'bakes of every kind');
	$occasions = array();
	foreach ($d['cakes'] as $key => $c) {
		pg_assert_true(isset($d['occasions'][$c['occasion']]), "cake $key: known occasion");
		pg_assert_true(count($c['sizes']) >= 1, "cake $key: a size");
		foreach ($c['sizes'] as $s) pg_assert_true(isset($d['cake_sizes'][$s]), "cake $key: known size $s");
		pg_assert_true(_test_bk_price_ok($c['price']), "cake $key: a price per currency");
		$occasions[$c['occasion']] = true;
	}
	pg_assert_true(isset($occasions['wedding']), 'a wedding cake');
	$levels = array();
	foreach ($d['baking_classes'] as $key => $c) {
		pg_assert_true(isset($d['levels'][$c['level']]), "class $key: known level");
		pg_assert_true(is_int($c['minutes']) && $c['minutes'] >= 60 && $c['minutes'] <= 300, "class $key: minutes");
		pg_assert_true(is_int($c['seats']) && $c['seats'] > 0, "class $key: seats");
		pg_assert_true(_test_bk_price_ok($c['price']), "class $key: a price per currency");
		foreach (array('name', 'summary', 'teacher', 'icon') as $f) pg_assert_true(trim($c[$f]) !== '', "class $key: $f");
		$levels[$c['level']] = true;
	}
	pg_assert_same(count($d['levels']), count($levels), 'a class at every level');
	pg_assert_same(4, count($d['figures']), 'four figures');
	// A testimonial names a bake, a cake or a class of the sample.
	$known = array();
	foreach (array($d['bakes'], $d['cakes'], $d['baking_classes']) as $table) {
		foreach ($table as $row) $known[$row['name']] = true;
	}
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($known[$t['about']]), "testimonial $n: about a bake, cake or class");
		pg_assert_contains($t['about'], $t['role'], "testimonial $n: names it");
	}
	// The pick lists: one answer per occasion, cake (plus a design of
	// one's own), size, class, level and range; no answer twice.
	$l = $d['lists'];
	pg_assert_same(count($d['occasions']), count($l['occasion_choices']), 'an answer per occasion');
	pg_assert_same(count($d['cakes']) + 1, count($l['cake_choices']), 'an answer per cake and one of my own');
	pg_assert_same(count($d['cake_sizes']), count($l['size_choices']), 'an answer per size');
	pg_assert_same(count($d['baking_classes']), count($l['class_names']), 'an answer per class');
	pg_assert_same(count($d['levels']), count($l['level_titles']), 'an answer per level');
	pg_assert_same(count($d['wholesale_ranges']), count($l['range_titles']), 'an answer per range');
	foreach ($l as $name => $list) {
		pg_assert_true(count($list) >= 2, "$name: answers");
		pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	}
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
}

// A bake shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list; a cake says "From" it.
function test_bakery_price_is_written_as_the_shop_writes_it()
{
	$d = _test_bk_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['bakes']['sourdough']['price'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	$cake = $d['cakes']['drip']['price'];
	$amount = isset($cake[$base]) ? $cake[$base] : $cake['USD'];
	pg_assert_same(sprintf(lang('From %s'), pg_money_text($amount, $symbol)), call_user_func($d['from_price'], $cake), 'the From line');
}

function test_bakery_page_links_name_template_pages()
{
	$known = array_flip(_test_bk_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_bk_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_bakery_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_bk_trees());
	foreach (_test_bk_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_bk_load();
	pg_assert_contains('{{page:cake_order}}', json_encode($d['header']), 'the header orders a cake');
	$nodes = array();
	_test_bk_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The footer opens the class list filtered on each level, in the words
// the Level list uses, and the daily bake at each kind's section, whose
// anchor the section carries.
function test_bakery_footer_filters_and_anchors()
{
	$d = _test_bk_load();
	$nodes = array();
	_test_bk_nodes($d['footer'], $nodes);
	$levels = array();
	$anchors = array();
	foreach ($nodes as $node) {
		$href = isset($node['props']['href']) ? $node['props']['href'] : '';
		if (strpos($href, '{{page:classes}}?') === 0) {
			parse_str(substr($href, strlen('{{page:classes}}?')), $q);
			pg_assert_true(in_array($q['level'], $d['lists']['level_titles'], true), 'the link names a Level answer');
			$levels[$q['level']] = true;
		}
		if (strpos($href, '{{page:bakes}}#') === 0) $anchors[] = substr($href, strlen('{{page:bakes}}#'));
	}
	pg_assert_same(count($d['levels']), count($levels), 'a link per level');
	pg_assert_same(count($d['categories']), count($anchors), 'a link per kind of bake');
	foreach ($anchors as $id) {
		$key = substr($id, strlen('bky-cat-'));
		pg_assert_true(isset($d['bake_sections'][$key]), "anchor $id: a kind");
		pg_assert_same($id, $d['bake_sections'][$key]['props']['id'], "anchor $id: the section carries it");
		pg_assert_true(!empty($d['bake_sections'][$key]['props']['searchSection']), "section $id: a search section");
	}
}

// A class card reads every value from the Baking Classes form's fields,
// with the sample's own words on the canvas.
function test_bakery_class_card_reads_the_class()
{
	$d = _test_bk_load();
	foreach ($d['class_cards'] as $key => $card) {
		$nodes = array();
		_test_bk_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo', 'name', 'form_item_view', 'level', 'summary', 'length', 'seats', 'price') as $t) {
			pg_assert_true(isset($tokens[$t]), "class $key: card reads $t");
		}
		$c = $d['baking_classes'][$key];
		pg_assert_true(in_array($c['name'], $texts, true), "class $key: sample name on the canvas");
		pg_assert_true(in_array($d['levels'][$c['level']], $texts, true), "class $key: sample level on the canvas");
	}
}

// The links found in a tree that open the given page with a query.
function _test_bk_query_links($tree, $page)
{
	$nodes = array();
	_test_bk_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

// A cake card opens the cake order with the cake and its occasion chosen,
// a range card the wholesale enquiry with the range: in the words the
// forms' lists use (the forms start from ?cake=, ?occasion=, ?range=).
function test_bakery_links_choose_in_the_request_forms()
{
	$d = _test_bk_load();
	$l = $d['lists'];
	foreach ($d['cake_cards'] as $key => $card) {
		$q = _test_bk_query_links($card, 'cake_order');
		pg_assert_same(1, count($q), "cake $key: one order link");
		pg_assert_same($d['cakes'][$key]['name'], $q[0]['cake'], "cake $key: the link names the cake");
		pg_assert_true(in_array($q[0]['cake'], $l['cake_choices'], true), "cake $key: a Cake answer");
		pg_assert_true(in_array($q[0]['occasion'], $l['occasion_choices'], true), "cake $key: an Occasion answer");
	}
	foreach ($d['range_cards'] as $key => $card) {
		$q = _test_bk_query_links($card, 'wholesale_enquiry');
		pg_assert_same(1, count($q), "range $key: one enquiry link");
		pg_assert_true(in_array($q[0]['range'], $l['range_titles'], true), "range $key: a Range answer");
	}
}

// Classes the template adds of its own start with pbk-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Ids start with bky-.
function test_bakery_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_bk_trees() as $t) _test_bk_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pmk|pwd|pch|pcn|pmu|pph|psh|ptv|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			if (strpos($c, 'pbk-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bky-') === 0, "id $id is the template's");
	}
	foreach (array('pbk-brand', 'pbk-display', 'pbk-eyebrow', 'pbk-price', 'pbk-lift', 'pbk-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_bakery_no_fixed_dates()
{
	$d = _test_bk_load();
	$json = json_encode(array($d['pickup'], $d['bakes'], $d['cakes'], $d['baking_classes'], $d['wholesale_ranges'], _test_bk_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a baking class is "atölye", wholesale
// "toptan", a cake "pasta".
function test_bakery_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bBaking Class(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		if (preg_match('/\bWholesale\b/', $key)) pg_assert_true(mb_stripos($tr, 'toptan', 0, 'UTF-8') !== false, "'$key' says toptan");
		if (preg_match('/\b(Celebration Cakes?|Cake Orders?|Order a Cake|Order this cake)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'pasta', 0, 'UTF-8') !== false, "'$key' says pasta");
	}
	// The shared keys this template must not use: their Turkish names
	// another thing (Classes reads Sınıflar, a school's).
	foreach (array('Classes', 'Class') as $k) {
		pg_assert_false(strpos($src, "lang('$k')") !== false, "the template does not say '$k' alone");
	}
}
