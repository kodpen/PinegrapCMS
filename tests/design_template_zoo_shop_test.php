<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo design template's shop
 * (includes/design_templates/pinegrap-zoo/shop.php): the catalog has a
 * product for each ticket and membership and for each level of every
 * animal that can be adopted, at the prices the public pages show, none
 * shipped; every page and widget needs the shop, the listings and the shop
 * pages point at each other, every link names a page of the finished
 * template, the ids and classes are the template's own, no fixed date and
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

// The page keys of the finished template (_test_zoo_page_keys()).
require_once __DIR__ . '/design_template_zoo_test.php';

// The shop as the template file reads it: parts.php, cards.php, mail.php,
// then shop.php in the same scope. Read once per run.
function _test_zoos_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	include $dir . '/mail.php';
	$data = function () use (&$tickets, &$ticket_prices, &$memberships, &$membership_prices, &$animals, &$adoption_levels, &$adoption_prices) {
		return array($tickets, $ticket_prices, $memberships, $membership_prices, $animals, $adoption_levels, $adoption_prices);
	};
	$before = $data();
	$shop = include $dir . '/shop.php';
	$pages = array();
	foreach ($shop['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('shop' => $shop, 'pages' => $pages, 'before' => $before, 'after' => $data(),
		'tickets' => $tickets, 'ticket_prices' => $ticket_prices,
		'memberships' => $memberships, 'membership_prices' => $membership_prices,
		'animals' => $animals, 'adoption_levels' => $adoption_levels, 'adoption_prices' => $adoption_prices, 'money' => $money);
	return $out;
}

function test_zoo_shop_catalog_follows_the_data()
{
	$d = _test_zoos_load();
	pg_assert_same($d['before'], $d['after'], 'shop.php leaves the data as it is');
	$catalog = $d['shop']['catalog'];
	pg_assert_same('pinegrap_zoo_catalog', $catalog['folder']);
	pg_assert_same(3, count($catalog['details']), 'the Details tab');
	pg_assert_same(array('tickets', 'memberships', 'adoptions'), array_keys($catalog['groups']), 'three groups');
	$want = array('tickets' => array(), 'memberships' => array(), 'adoptions' => array());
	foreach ($d['tickets'] as $key => $t) $want['tickets']['ZOO-TICKET-' . strtoupper($key)] = $d['ticket_prices'][$key];
	foreach ($d['memberships'] as $key => $m) $want['memberships']['ZOO-MEMBER-' . strtoupper($key)] = $d['membership_prices'][$key];
	foreach ($d['animals'] as $akey => $a) {
		if (!$a['adopt']) continue;
		foreach ($d['adoption_levels'] as $lkey => $l) $want['adoptions']['ZOO-ADOPT-' . strtoupper($akey . '-' . $lkey)] = $d['adoption_prices'][$lkey];
	}
	pg_assert_true(count($want['adoptions']) >= 9, 'three levels of at least three animals');
	$skus = array();
	foreach ($want as $g => $expect) {
		$group = $catalog['groups'][$g];
		pg_assert_same('root', $group['parent'], "$g under the site's top group");
		pg_assert_true($group['title'] !== '' && $group['description'] !== '', "$g: title and description");
		$got = array();
		$featured = 0;
		foreach ($group['products'] as $p) {
			pg_assert_true((bool)preg_match('/^ZOO-(TICKET|MEMBER|ADOPT)-[A-Z0-9-]+$/', $p['sku']), $p['sku'] . ': a SKU of letters, digits and hyphens');
			pg_assert_false(isset($skus[$p['sku']]), $p['sku'] . ': no SKU twice');
			$skus[$p['sku']] = true;
			$got[$p['sku']] = $p['price'];
			pg_assert_true($p['title'] !== '' && $p['description'] !== '' && $p['keywords'] !== '', $p['sku'] . ': title, description and keywords');
			pg_assert_same(false, $p['shippable'], $p['sku'] . ': nothing is shipped');
			if (!empty($p['featured'])) $featured++;
		}
		pg_assert_same($expect, $got, "$g: a product per item in the data's order, at its prices");
		pg_assert_same(1, $featured, "$g: one product featured");
	}
	// An adoption's title names its animal and its level.
	$first = $catalog['groups']['adoptions']['products'][0];
	$akey = key(array_filter($d['animals'], function ($a) { return $a['adopt']; }));
	$lkey = key($d['adoption_levels']);
	pg_assert_contains($d['animals'][$akey]['title'], $first['title'], 'the adoption names its animal');
	pg_assert_contains($d['adoption_levels'][$lkey]['title'], $first['title'], 'the adoption names its level');
}

function test_zoo_shop_parts_need_the_shop()
{
	$d = _test_zoos_load();
	pg_assert_same(_test_zoo_shop_keys(), array_keys($d['pages']), 'the shop\'s pages');
	$all = _test_zoo_page_keys();
	foreach (array_keys($d['pages']) as $key) {
		pg_assert_true(in_array($key, $all, true), "$key is a page of the template");
		pg_assert_same('ecommerce', $d['pages'][$key]['requires'] ?? '', "$key needs the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $d['pages'][$key]['name']), "$key: the page name is an address");
	}
	foreach ($d['shop']['widgets'] as $wk => $w) pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	pg_assert_same('private', $d['pages']['email_order']['folder'], 'the receipt is private');
	foreach (array('cart', 'checkout', 'order') as $key) pg_assert_true($d['pages'][$key]['noindex'], "$key is not indexed");
	pg_assert_false($d['pages']['product']['noindex'], 'product pages are indexed');
	foreach (array('buy_tickets', 'adopt_shop') as $key) {
		pg_assert_false(isset($d['pages'][$key]['noindex']), "$key is indexed");
		pg_assert_true(mb_strlen($d['pages'][$key]['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
}

function test_zoo_shop_widgets_point_at_each_other()
{
	$d = _test_zoos_load();
	$w = $d['shop']['widgets'];
	pg_assert_same(array('ticket_catalog', 'membership_catalog', 'adoption_catalog', 'cart_link', 'product_detail', 'cart', 'checkout', 'order_view', 'email_order'), array_keys($w));
	$placed = array();
	foreach ($d['pages'] as $p) {
		preg_match_all('/"templateWidget":"([a-z_]+)"/', json_encode($p['tree']), $m);
		foreach ($m[1] as $k) $placed[$k] = $p['key'];
	}
	$slugs = array();
	foreach ($w as $wk => $def) {
		if ($def['page'] !== '') pg_assert_same($def['page'], $placed[$wk] ?? '', "widget $wk sits on its page");
		$slugs[$def['slug']][] = $wk;
	}
	pg_assert_same(array('order_view', 'email_order'), $slugs[lang('order')], 'only the order views share a slug');
	pg_assert_same(count($w) - 1, count($slugs), 'every other slug is its own');
	$listings = array(
		'ticket_catalog'     => array('tickets', $d['ticket_prices']['family']),
		'membership_catalog' => array('memberships', $d['membership_prices']['family']),
		'adoption_catalog'   => array('adoptions', $d['adoption_prices']['keeper']),
	);
	foreach ($listings as $wk => list($group, $price)) {
		$c = $w[$wk]['config'];
		pg_assert_same('catalog_listing', $c['regionType']);
		pg_assert_same('{{product_group:' . $group . '}}', $c['product_group_id'], "$wk lists its group");
		pg_assert_same('{{tab:product}}', $c['detail_page_id']);
		pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
		$tree = json_encode($w[$wk]['tree']);
		foreach (array('"catalog_add_to_cart"', '"__detail_url"', '"__price_formatted"', '"__short_description"') as $t) pg_assert_contains($t, $tree, "$wk card: $t");
		pg_assert_contains(json_encode(call_user_func($d['money'], $price)), $tree, "$wk: the sample card's price");
	}
	pg_assert_same('{{tab:buy_tickets}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the buy tickets page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_date', '__order_total', '__billing_address_html', '__item_short_description', '__item_qty', '__item_total') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_false(strpos($receipt, '__shipping_address_html') !== false, 'nothing shipped, no shipping address');
}

function test_zoo_shop_links_ids_and_classes()
{
	$d = _test_zoos_load();
	$known = array_flip(_test_zoo_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 9, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['shop']['widgets'] as $key => $wd) {
		if (is_array($wd['tree'])) $trees['widget ' . $key] = $wd['tree'];
	}
	$ids = array();
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_zoo_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pht|pma|pco|pcl|pgy|pch|psk|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'zoo-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	foreach (array('buy_tickets', 'adopt_shop') as $key) {
		$nodes = array();
		_test_zoo_nodes($d['pages'][$key]['tree'], $nodes);
		$levels = array();
		foreach ($nodes as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $hm)) $levels[] = (int)$hm[1];
		}
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
	// No year or month in the pages' words (an old page a year later); the
	// catalog's prices are figures, not years.
	$json = json_encode(array($d['pages'], $d['shop']['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the shop');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the shop');
}

// Every literal has its Turkish; a ticket reads "bilet", a membership
// "üyelik", an adoption "sahiplenme".
function test_zoo_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bTickets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bilet', 0, 'UTF-8') !== false, "'$key' says bilet");
		if (preg_match('/\bMemberships?\b/', $key)) pg_assert_true(mb_stripos($tr, 'üyeli', 0, 'UTF-8') !== false, "'$key' says üyelik");
		if (preg_match('/\badoptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sahiplen', 0, 'UTF-8') !== false, "'$key' says sahiplenme");
	}
}
