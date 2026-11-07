<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Museum design template's shop
 * (includes/design_templates/pinegrap-museum/shop.php): the catalog has a
 * product for each admission ticket and each membership tier at the
 * prices the visit and support pages show, none shipped; every page and
 * widget needs the shop, the listings and the shop pages point at each
 * other, every link names a page of the finished template, the ids and
 * classes are the template's own and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_mz_page_keys()).
require_once __DIR__ . '/design_template_museum_test.php';

// The shop as the template file reads it: parts.php, mail.php, then
// shop.php in the same scope. Read once per run.
function _test_mzs_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$before = array($admission, $admission_prices, $tiers, $tier_prices);
	$shop = include $dir . '/shop.php';
	$pages = array();
	foreach ($shop['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('shop' => $shop, 'pages' => $pages, 'before' => $before,
		'after' => array($admission, $admission_prices, $tiers, $tier_prices),
		'admission' => $admission, 'admission_prices' => $admission_prices, 'tiers' => $tiers, 'tier_prices' => $tier_prices, 'money' => $money);
	return $out;
}

function test_museum_shop_catalog_follows_the_data()
{
	$d = _test_mzs_load();
	pg_assert_same($d['before'], $d['after'], 'shop.php leaves the data as it is');
	$catalog = $d['shop']['catalog'];
	pg_assert_same('pinegrap_museum_catalog', $catalog['folder']);
	pg_assert_same(3, count($catalog['details']), 'the Details tab');
	pg_assert_same(array('admission', 'membership'), array_keys($catalog['groups']), 'two groups');
	$want = array(
		'admission'  => array('MUSEUM-TICKET-', $d['admission'], $d['admission_prices']),
		'membership' => array('MUSEUM-MEMBER-', $d['tiers'], $d['tier_prices']),
	);
	$skus = array();
	foreach ($want as $g => list($prefix, $items, $prices)) {
		$group = $catalog['groups'][$g];
		pg_assert_same('root', $group['parent'], "$g under the site's top group");
		pg_assert_true($group['title'] !== '' && $group['description'] !== '', "$g: title and description");
		$expect = array();
		foreach ($items as $key => $item) $expect[$prefix . strtoupper($key)] = $prices[$key];
		$got = array();
		$featured = 0;
		foreach ($group['products'] as $p) {
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
}

function test_museum_shop_parts_need_the_shop()
{
	$d = _test_mzs_load();
	pg_assert_same(array('tickets', 'membership', 'product', 'cart', 'checkout', 'order', 'email_order'), array_keys($d['pages']), 'the shop\'s pages');
	$all = _test_mz_page_keys();
	foreach (array_keys($d['pages']) as $key) {
		pg_assert_true(in_array($key, $all, true), "$key is a page of the template");
		pg_assert_same('ecommerce', $d['pages'][$key]['requires'] ?? '', "$key needs the shop");
	}
	foreach ($d['shop']['widgets'] as $wk => $w) pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	pg_assert_same('private', $d['pages']['email_order']['folder'], 'the receipt is private');
	foreach (array('cart', 'checkout', 'order') as $key) pg_assert_true($d['pages'][$key]['noindex'], "$key is not indexed");
	pg_assert_false($d['pages']['product']['noindex'], 'product pages are indexed');
	foreach (array('tickets', 'membership') as $key) {
		pg_assert_false(isset($d['pages'][$key]['noindex']), "$key is indexed");
		pg_assert_true(mb_strlen($d['pages'][$key]['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
}

function test_museum_shop_widgets_point_at_each_other()
{
	$d = _test_mzs_load();
	$w = $d['shop']['widgets'];
	pg_assert_same(array('ticket_catalog', 'membership_catalog', 'cart_link', 'product_detail', 'cart', 'checkout', 'order_view', 'email_order'), array_keys($w));
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
	$listings = array('ticket_catalog' => array('admission', $d['admission_prices']['adult']), 'membership_catalog' => array('membership', $d['tier_prices']['joint']));
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
	pg_assert_same('{{tab:tickets}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the tickets page');
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

function test_museum_shop_links_ids_and_classes()
{
	$d = _test_mzs_load();
	$known = array_flip(_test_mz_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 9, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mz_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) {
		if (is_array($wd['tree'])) _test_mz_nodes($wd['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mz-') === 0, "id $id is the template's");
	}
	// No year in the pages' words (an old page a year later); the catalog's
	// prices are figures, not years.
	$json = json_encode(array($d['pages'], $d['shop']['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(1[5-9]|20)[0-9]{2}\b/', $json), 'no year in the shop');
}

// Every literal has its Turkish; a ticket reads "bilet", a membership
// "üyelik", a member "üye".
function test_museum_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Tt]ickets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bilet', 0, 'UTF-8') !== false, "'$key' says bilet");
		if (preg_match('/\b[Mm]embers?(hips?)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'üye', 0, 'UTF-8') !== false, "'$key' says üye");
	}
}
