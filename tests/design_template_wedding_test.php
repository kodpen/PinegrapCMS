<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Wedding Planner and Venue design template's parts
 * (includes/design_templates/pinegrap-wedding/): the planner's data hangs
 * together (packages naming a known space and fitting in it, sample
 * weddings naming a known space and season, a price for every service and
 * package, testimonials about a sample wedding, the timeline counting
 * down, the forms' pick lists without an answer twice), a price is written
 * as the shop writes it, every page link of the shared parts names a page
 * of the finished template and none names a shop page, the footer opens
 * the Real Weddings list filtered, a wedding card reads its form's fields,
 * the service, package and space cards open the enquiry with their choice
 * made, the classes are the template's own, the sample carries no fixed
 * date and every literal has its Turkish.
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
function _test_wd_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/parts.php';
	$cards = array('service' => array(), 'package' => array(), 'space' => array(), 'wedding' => array());
	foreach (array_keys($services) as $key) $cards['service'][$key] = $service_card($key);
	foreach (array_keys($packages) as $key) $cards['package'][$key] = $package_card($key);
	foreach (array_keys($spaces) as $key) $cards['space'][$key] = $space_card($key);
	foreach (array_keys($weddings) as $key) $cards['wedding'][$key] = $wedding_card($key);
	$more = array();
	foreach (array_keys($testimonials) as $n) $more[] = $testimonial_card($n);
	foreach (array_keys($timeline) as $n) $more[] = $timeline_stage($n);
	foreach (array_keys($gallery) as $n) $more[] = $gallery_item($n);
	$out = array(
		'planner'            => $planner,
		'spaces'             => $spaces,
		'seasons'            => $seasons,
		'services'           => $services,
		'service_prices'     => $service_prices,
		'packages'           => $packages,
		'package_prices'     => $package_prices,
		'deposit'            => $deposit,
		'money'              => $money,
		'package_price_text' => $package_price_text,
		'service_price_text' => $service_price_text,
		'capacity_text'      => $capacity_text,
		'guests_text'        => $guests_text,
		'suppliers_text'     => $suppliers_text,
		'weddings'           => $weddings,
		'gallery'            => $gallery,
		'timeline'           => $timeline,
		'why_us'             => $why_us,
		'gift_cards'         => $gift_cards,
		'figures'            => $figures,
		'testimonials'       => $testimonials,
		'lists'              => array(
			'space_titles'     => $space_titles,
			'season_titles'    => $season_titles,
			'package_titles'   => $package_titles,
			'service_titles'   => $service_titles,
			'couple_names'     => $couple_names,
			'package_choices'  => $package_choices,
			'service_choices'  => $service_choices,
			'space_choices'    => $space_choices,
			'season_choices'   => $season_choices,
			'budget_choices'   => $budget_choices,
			'enquiry_statuses' => $enquiry_statuses,
		),
		'radios'             => array('ceremony_choices' => $ceremony_choices, 'contact_choices' => $contact_choices),
		'cards'              => $cards,
		'more_cards'         => $more,
		'header'             => $header(),
		'footer'             => $footer(),
		'shared'             => array($account_bar_tree(), $notice(), $date_band(), $staff_bar(), $post_card(), $ai_summary()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_wd_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_wd_nodes($child, $out);
	}
}

// All the trees the parts make, cards included.
function _test_wd_trees()
{
	$d = _test_wd_load();
	$trees = array_merge(array($d['header'], $d['footer']), $d['shared'], $d['more_cards']);
	foreach ($d['cards'] as $set) $trees = array_merge($trees, array_values($set));
	return $trees;
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_wd_page_keys()
{
	return array_merge(array(
		'home', 'about', 'services', 'packages', 'venue', 'gallery', 'timeline', 'faq',
		'weddings', 'wedding', 'wedding_new',
		'enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'privacy', 'email_enquiry_new', 'email_enquiry_received', 'email_reply', 'book_visit',
		'email_new_message', 'email_message_received', 'email_welcome',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
		'email_account_welcome', 'email_password_reset',
	), _test_wd_shop_keys());
}

// The pages that exist only with the shop.
function _test_wd_shop_keys()
{
	return array('shop', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_wedding_data_hangs_together()
{
	$d = _test_wd_load();
	foreach (array('tagline', 'story', 'address', 'hours', 'promise') as $f) pg_assert_true(trim($d['planner'][$f]) !== '', "planner: $f");
	pg_assert_true((bool)preg_match('/^\+[0-9 ]+$/', $d['planner']['phone']), 'planner: phone number');
	pg_assert_true(strpos($d['planner']['email'], '@example.com') !== false, 'planner: a sample address');
	pg_assert_same(4, count($d['spaces']), 'four spaces');
	foreach ($d['spaces'] as $key => $s) {
		foreach (array('title', 'use', 'text') as $f) pg_assert_true(trim($s[$f]) !== '', "space $key: $f");
		pg_assert_true(is_int($s['seated']) && is_int($s['standing']) && $s['seated'] < $s['standing'], "space $key: more standing than seated");
	}
	pg_assert_same(3, count($d['services']), 'three planning services');
	pg_assert_same(array_keys($d['services']), array_keys($d['service_prices']), 'a price per service and no other');
	pg_assert_same(3, count($d['packages']), 'three packages');
	pg_assert_same(array_keys($d['packages']), array_keys($d['package_prices']), 'a price per package and no other');
	$featured = 0;
	foreach ($d['packages'] as $key => $p) {
		pg_assert_true(isset($d['spaces'][$p['space']]), "package $key: known space");
		pg_assert_true($p['min'] < $p['max'], "package $key: a guest range");
		pg_assert_true($p['max'] <= $d['spaces'][$p['space']]['standing'], "package $key: fits its space");
		pg_assert_true(count($p['includes']) >= 4, "package $key: what it includes");
		if ($p['featured']) $featured++;
	}
	pg_assert_same(1, $featured, 'one package marked most chosen');
	foreach ($d['services'] as $key => $s) pg_assert_true(count($s['includes']) >= 4, "service $key: what it includes");
	pg_assert_true(count($d['weddings']) >= 4, 'four sample weddings');
	$featured = 0;
	$spaces = array();
	$seasons = array();
	foreach ($d['weddings'] as $key => $w) {
		pg_assert_true(isset($d['spaces'][$w['space']]), "wedding $key: known space");
		pg_assert_true(isset($d['seasons'][$w['season']]), "wedding $key: known season");
		pg_assert_true(is_int($w['guests']) && $w['guests'] <= $d['spaces'][$w['space']]['standing'], "wedding $key: the guests fit the space");
		foreach (array('couple', 'summary', 'story', 'quote') as $f) pg_assert_true(trim($w[$f]) !== '', "wedding $key: $f");
		pg_assert_true(count($w['suppliers']) >= 2, "wedding $key: suppliers");
		if ($w['featured']) $featured++;
		$spaces[$w['space']] = true;
		$seasons[$w['season']] = true;
	}
	pg_assert_same(count($d['spaces']), count($spaces), 'a wedding in every space');
	pg_assert_same(count($d['seasons']), count($seasons), 'a wedding in every season');
	pg_assert_same(3, $featured, 'three weddings on the home');
	foreach ($d['gallery'] as $n => $g) pg_assert_true(isset($d['spaces'][$g['space']]), "gallery $n: known space");
	pg_assert_true(count($d['timeline']) >= 5, 'a timeline of five stages or more');
	foreach ($d['timeline'] as $n => $t) pg_assert_true(count($t['tasks']) >= 2, "timeline $n: tasks");
	pg_assert_same(4, count($d['why_us']), 'four reasons');
	pg_assert_same(4, count($d['figures']), 'four figures');
	foreach ($d['package_prices'] as $key => $p) {
		foreach ($d['deposit'] as $cur => $amount) {
			pg_assert_true($p[$cur] * $d['packages'][$key]['min'] > $amount, "package $key: the smallest wedding costs more than the deposit in $cur");
		}
	}
	$skus = array();
	foreach ($d['gift_cards'] as $key => $g) {
		pg_assert_true((bool)preg_match('/^WEDDING-[A-Z0-9-]+$/', $g['sku']), "gift card $key: the template's SKU");
		pg_assert_true(isset($g['price']['USD']) && $g['price']['USD'] > 0, "gift card $key: a price");
		$skus[$g['sku']] = true;
	}
	pg_assert_same(count($d['gift_cards']), count($skus), 'no SKU twice');
	foreach ($d['testimonials'] as $n => $t) {
		pg_assert_true(isset($d['weddings'][$t['wedding']]), "testimonial $n: about a sample wedding");
		pg_assert_same($d['weddings'][$t['wedding']]['couple'], $t['name'], "testimonial $n: the couple's own names");
	}
	$l = $d['lists'];
	pg_assert_same(count($d['spaces']) + 1, count($l['space_choices']), 'an answer per space and not sure');
	pg_assert_same(count($d['seasons']) + 1, count($l['season_choices']), 'an answer per season and flexible');
	pg_assert_same(count($d['packages']) + 1, count($l['package_choices']), 'an answer per package and not decided');
	pg_assert_same(count($d['services']) + 1, count($l['service_choices']), 'an answer per service and venue only');
	foreach ($l as $name => $list) pg_assert_same($list, array_values(array_unique($list)), "$name unique");
	foreach ($d['radios'] as $name => $list) {
		foreach ($list as $o) pg_assert_same($o[0], $o[1], "$name: the value is the answer's own words");
	}
	pg_assert_same(5, count($l['enquiry_statuses']), 'five states of an enquiry');
}

// A price shows one amount: the base currency's figure, the USD figure for
// a currency the table does not list.
function test_wedding_price_is_written_as_the_shop_writes_it()
{
	$d = _test_wd_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['package_prices']['classic'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('From %s per guest'), pg_money_text($amount, $symbol)), call_user_func($d['package_price_text'], 'classic'), 'the package line');
	$prices = $d['service_prices']['full'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(sprintf(lang('Starting at %s'), pg_money_text($amount, $symbol)), call_user_func($d['service_price_text'], 'full'), 'the service line');
	pg_assert_same(sprintf(lang('%1$d seated, %2$d standing'), 180, 250), call_user_func($d['capacity_text'], 'garden'), 'the capacity line');
	pg_assert_same(sprintf(lang('%1$d to %2$d guests'), 60, 180), call_user_func($d['guests_text'], 'classic'), 'the guests line');
}

// The suppliers are one line each, the role and the name.
function test_wedding_suppliers_text_is_a_line_each()
{
	$d = _test_wd_load();
	foreach ($d['weddings'] as $key => $w) {
		$lines = explode("\n", call_user_func($d['suppliers_text'], $key));
		pg_assert_same(count($w['suppliers']), count($lines), "wedding $key: a line per supplier");
		foreach ($w['suppliers'] as $n => $s) pg_assert_same(sprintf(lang('%1$s: %2$s'), $s[0], $s[1]), $lines[$n], "wedding $key: supplier " . ($n + 1));
	}
}

function test_wedding_page_links_name_template_pages()
{
	$known = array_flip(_test_wd_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(_test_wd_trees()), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// The shared parts stand on a site without the shop: none links a page
// that only the shop makes, and the menu marks the current page itself.
function test_wedding_shared_parts_stand_without_the_shop()
{
	$json = json_encode(_test_wd_trees());
	foreach (_test_wd_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	$d = _test_wd_load();
	pg_assert_contains('{{page:enquiry}}', json_encode($d['header']), 'the header opens the enquiry');
	pg_assert_contains('{{page:book_visit}}', json_encode($d['shared'][2]), 'the date band books a visit');
	$nodes = array();
	_test_wd_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
		pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
}

// The links in a tree that start with a prefix, their query read.
function _test_wd_queries($tree, $prefix)
{
	$nodes = array();
	_test_wd_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The footer opens the Real Weddings list filtered on each space and each
// season, in the words the Space and Season lists use.
function test_wedding_footer_filters_the_list()
{
	$d = _test_wd_load();
	$seen = array('space' => array(), 'season' => array());
	foreach (_test_wd_queries($d['footer'], '{{page:weddings}}?') as $q) {
		foreach (array('space' => 'space_titles', 'season' => 'season_titles') as $f => $list) {
			if (!isset($q[$f])) continue;
			pg_assert_true(in_array($q[$f], $d['lists'][$list], true), "the link names a $f answer");
			$seen[$f][$q[$f]] = true;
		}
	}
	pg_assert_same(count($d['spaces']), count($seen['space']), 'a link per space');
	pg_assert_same(count($d['seasons']), count($seen['season']), 'a link per season');
}

// A wedding card reads every value from the Real Weddings form's fields,
// with the sample's own words on the canvas.
function test_wedding_card_reads_its_record()
{
	$d = _test_wd_load();
	foreach ($d['cards']['wedding'] as $key => $card) {
		$nodes = array();
		_test_wd_nodes($card, $nodes);
		$tokens = array();
		$texts = array();
		foreach ($nodes as $node) {
			if (!empty($node['props']['_bindings'])) {
				foreach ($node['props']['_bindings'] as $token) $tokens[$token] = true;
			}
			if (isset($node['props']['text'])) $texts[] = $node['props']['text'];
		}
		foreach (array('photo_1', 'couple', 'form_item_view', 'space', 'season', 'summary', 'guests') as $t) {
			pg_assert_true(isset($tokens[$t]), "wedding $key: card reads $t");
		}
		$w = $d['weddings'][$key];
		pg_assert_true(in_array($w['couple'], $texts, true), "wedding $key: sample couple on the canvas");
		pg_assert_true(in_array($d['spaces'][$w['space']]['title'], $texts, true), "wedding $key: sample space on the canvas");
	}
}

// The service, package and space cards open the enquiry with their choice
// made, in the words the form's lists use (the form starts from the
// query: ?service=, ?package=, ?space=).
function test_wedding_cards_choose_in_the_enquiry()
{
	$d = _test_wd_load();
	$sets = array('service' => 'service_choices', 'package' => 'package_choices', 'space' => 'space_choices');
	foreach ($sets as $field => $list) {
		foreach ($d['cards'][$field] as $key => $card) {
			$qs = _test_wd_queries($card, '{{page:enquiry}}?');
			pg_assert_same(1, count($qs), "$field $key: one enquiry link");
			pg_assert_true(isset($qs[0][$field]) && in_array($qs[0][$field], $d['lists'][$list], true), "$field $key: the link names an answer");
		}
	}
}

// Classes the template adds of its own start with pwd-; another
// template's prefix copied in would stay unstyled (its rules are not in
// this one). Field ids start with wd-.
function test_wedding_classes_are_its_own()
{
	$nodes = array();
	foreach (_test_wd_trees() as $t) _test_wd_nodes($t, $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph)-/', $c), "class $c is not another template's");
			if (strpos($c, 'pwd-') === 0) $own[$c] = true;
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'wd-') === 0, "id $id is the template's");
	}
	foreach (array('pwd-brand', 'pwd-display', 'pwd-eyebrow', 'pwd-price', 'pwd-lift', 'pwd-quote') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later; the timeline counts the time left.
function test_wedding_no_fixed_dates()
{
	$d = _test_wd_load();
	$json = json_encode(array($d['weddings'], $d['timeline'], $d['packages'], $d['gift_cards'], _test_wd_trees()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way: a wedding is "düğün", the venue "mekân", a
// package "paket", an enquiry "talep".
function test_wedding_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/parts.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bWeddings?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'düğün', 0, 'UTF-8') !== false, "'$key' says düğün");
		if (preg_match('/\bVenue\b/i', $key)) pg_assert_true(mb_stripos($tr, 'mekân', 0, 'UTF-8') !== false, "'$key' says mekân");
		if (preg_match('/\bPackages?\b/', $key)) pg_assert_true(mb_stripos($tr, 'paket', 0, 'UTF-8') !== false, "'$key' says paket");
		if (preg_match('/\bEnquir(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false, "'$key' says talep");
	}
}
