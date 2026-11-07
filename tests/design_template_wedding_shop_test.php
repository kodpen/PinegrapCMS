<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Wedding Planner and Venue design template's shop
 * (includes/design_templates/pinegrap-wedding/shop.php): a date-hold
 * deposit per venue package and a product per gift card at parts.php's
 * prices, none shippable; every page and widget needing the shop; the
 * widgets pointing at each other; the receipt without a shipping address;
 * links naming template pages; the template's own classes; every literal
 * with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_wedding_test.php');

// The shop as the template file reads it: parts.php, mail.php, then
// shop.php in the same scope. Read once per run.
function _test_wds_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$before = array($packages, $gift_cards, $deposit);
	$shop = include $dir . '/shop.php';
	$out = array('shop' => $shop, 'packages' => $packages, 'gift_cards' => $gift_cards, 'deposit' => $deposit,
		'before' => $before, 'money' => $money);
	return $out;
}

function _test_wds_page($key)
{
	foreach (_test_wds_load()['shop']['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

function test_wedding_shop_catalog_follows_the_data()
{
	$d = _test_wds_load();
	pg_assert_same($d['before'], array($d['packages'], $d['gift_cards'], $d['deposit']), 'shop.php leaves the data as it is');
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(array('wedding_shop'), array_keys($groups), 'one group');
	pg_assert_same('root', $groups['wedding_shop']['parent'], 'under the site\'s top group');
	$want = array();
	foreach (array_keys($d['packages']) as $key) $want['WEDDING-DEPOSIT-' . strtoupper($key)] = $d['deposit'];
	foreach ($d['gift_cards'] as $g) $want[$g['sku']] = $g['price'];
	$got = array();
	$featured = 0;
	foreach ($groups['wedding_shop']['products'] as $p) {
		$got[$p['sku']] = $p['price'];
		pg_assert_true($p['title'] !== '' && $p['description'] !== '', $p['sku'] . ': title and description');
		pg_assert_same(false, $p['shippable'], $p['sku'] . ': paid or sent by e-mail, not shipped');
		pg_assert_same(0, strpos($p['sku'], 'WEDDING-'), $p['sku'] . ': the template\'s own SKU');
		if (!empty($p['featured'])) $featured++;
	}
	pg_assert_same($want, $got, 'a deposit per package and a product per gift card, at their prices');
	pg_assert_same(1, $featured, 'the featured package\'s deposit only');
	pg_assert_same('pinegrap_wedding_catalog', $d['shop']['catalog']['folder']);
}

function test_wedding_shop_parts_need_the_shop()
{
	$d = _test_wds_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, $p['key'] . ': meta description fits a result line');
	}
	pg_assert_same(_test_wd_shop_keys(), $keys, 'the shop\'s pages');
	foreach ($d['shop']['widgets'] as $wk => $w) {
		pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	}
	pg_assert_same('private', _test_wds_page('email_order')['folder'], 'the receipt is private');
	pg_assert_true(_test_wds_page('cart')['noindex'], 'cart is not indexed');
	pg_assert_false(_test_wds_page('product')['noindex'], 'product pages are indexed');
	pg_assert_false(isset(_test_wds_page('shop')['noindex']), 'the shop page is indexed');
}

function test_wedding_shop_widgets_point_at_each_other()
{
	$d = _test_wds_load();
	$w = $d['shop']['widgets'];
	$placed = array();
	foreach ($d['shop']['pages'] as $p) {
		preg_match_all('/"templateWidget":"([a-z_]+)"/', json_encode($p['tree']), $m);
		foreach ($m[1] as $k) $placed[$k] = $p['key'];
	}
	foreach ($w as $wk => $def) {
		if ($def['page'] === '') continue;
		pg_assert_same($def['page'], $placed[$wk] ?? '', "widget $wk sits on its page");
	}
	pg_assert_true(isset($w['cart_link']), 'the account bar\'s cart link');
	$c = $w['shop_catalog']['config'];
	pg_assert_same('catalog_listing', $c['regionType']);
	pg_assert_same('{{product_group:wedding_shop}}', $c['product_group_id']);
	pg_assert_same('{{tab:product}}', $c['detail_page_id']);
	pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
	$listing = json_encode($w['shop_catalog']['tree']);
	pg_assert_contains('"catalog_add_to_cart"', $listing, 'Add to cart');
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['deposit'])), $listing, 'the sample card\'s price');
	pg_assert_same('{{tab:shop}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the shop page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_total', '__billing_address_html', '__item_short_description') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_false(strpos($receipt, '__shipping_address_html') !== false, 'nothing is shipped');
}

function test_wedding_shop_links_and_classes()
{
	$d = _test_wds_load();
	$known = array_flip(_test_wd_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 7, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_wd_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) {
		if (is_array($wd['tree'])) _test_wd_nodes($wd['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'wd-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a deposit reads "kapora" and a wedding
// "düğün". Page names are address words.
function test_wedding_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/\b[Dd]eposits?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
		if (preg_match('/\b[Ww]eddings?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'düğün', 0, 'UTF-8') !== false, "'$key' says düğün");
	}
}
