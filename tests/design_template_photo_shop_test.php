<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Photography Studio design template's shop
 * (includes/design_templates/pinegrap-photo/shop.php): the catalog has a
 * shoot deposit for each package and a product for each gift voucher at
 * the prices the pages show, none shipped; every page and widget needs the
 * shop, the listing and the shop pages point at each other, every link
 * names a page of the finished template, the ids and classes are the
 * template's own and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_photo_test.php');

// The shop as the template file reads it: parts.php, mail.php, then
// shop.php in the same scope. Read once per run.
function _test_phs_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$before = array($packages, $package_deposits, $gift_vouchers);
	$shop = include $dir . '/shop.php';
	$out = array('shop' => $shop, 'before' => $before, 'after' => array($packages, $package_deposits, $gift_vouchers),
		'packages' => $packages, 'deposits' => $package_deposits, 'vouchers' => $gift_vouchers, 'money' => $money);
	return $out;
}

function _test_phs_page($key)
{
	$d = _test_phs_load();
	foreach ($d['shop']['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

function test_photo_shop_catalog_follows_the_data()
{
	$d = _test_phs_load();
	pg_assert_same($d['before'], $d['after'], 'shop.php leaves the samples as they are');
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(array('sessions'), array_keys($groups), 'one group');
	pg_assert_same('root', $groups['sessions']['parent'], 'under the site\'s top group');
	$want = array();
	foreach ($d['packages'] as $key => $p) $want['PHOTO-DEPOSIT-' . strtoupper($key)] = $d['deposits'][$key];
	foreach ($d['vouchers'] as $v) $want[$v['sku']] = $v['price'];
	$got = array();
	$featured = 0;
	foreach ($groups['sessions']['products'] as $p) {
		pg_assert_false(isset($got[$p['sku']]), $p['sku'] . ': no SKU twice');
		$got[$p['sku']] = $p['price'];
		pg_assert_true((bool)preg_match('/^PHOTO-[A-Z0-9-]+$/', $p['sku']), $p['sku'] . ': the template\'s SKU');
		pg_assert_true($p['title'] !== '' && $p['description'] !== '', $p['sku'] . ': title and description');
		pg_assert_same(false, $p['shippable'], $p['sku'] . ': nothing is shipped');
		if (!empty($p['featured'])) $featured++;
	}
	pg_assert_same($want, $got, 'a deposit per package, then a product per voucher, at their prices');
	pg_assert_same(1, $featured, 'one voucher featured');
	pg_assert_same('pinegrap_photo_catalog', $d['shop']['catalog']['folder']);
}

function test_photo_shop_parts_need_the_shop()
{
	$d = _test_phs_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
	}
	pg_assert_same(_test_ph_shop_keys(), $keys, 'the shop\'s pages');
	foreach ($d['shop']['widgets'] as $wk => $w) {
		pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	}
	pg_assert_same('private', _test_phs_page('email_order')['folder'], 'the receipt is private');
	pg_assert_true(_test_phs_page('cart')['noindex'], 'cart is not indexed');
	pg_assert_false(_test_phs_page('product')['noindex'], 'product pages are indexed');
	pg_assert_false(isset(_test_phs_page('vouchers')['noindex']), 'the vouchers page is indexed');
	pg_assert_true(mb_strlen(_test_phs_page('vouchers')['meta_description'], 'UTF-8') <= 160, 'meta description fits a result line');
}

function test_photo_shop_widgets_point_at_each_other()
{
	$d = _test_phs_load();
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
	$c = $w['session_catalog']['config'];
	pg_assert_same('catalog_listing', $c['regionType']);
	pg_assert_same('{{product_group:sessions}}', $c['product_group_id']);
	pg_assert_same('{{tab:product}}', $c['detail_page_id']);
	pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
	$listing = json_encode($w['session_catalog']['tree']);
	pg_assert_contains('"catalog_add_to_cart"', $listing, 'Add to cart');
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['vouchers']['gift150']['price'])), $listing, 'the sample card\'s price');
	pg_assert_same('{{tab:vouchers}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the vouchers page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_total', '__billing_address_html', '__item_short_description') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_false(strpos($receipt, '__shipping_address_html') !== false, 'nothing shipped, no shipping address');
}

function test_photo_shop_links_ids_and_classes()
{
	$d = _test_phs_load();
	$known = array_flip(_test_ph_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 7, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_ph_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) {
		if (is_array($wd['tree'])) _test_ph_nodes($wd['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^opacity-/', $c), "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ph-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a deposit reads "kapora", a shoot "çekim"
// and a package "paket".
function test_photo_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Dd]eposits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
		if (preg_match('/\b[Ss]hoots?\b/', $key)) pg_assert_true(mb_stripos($tr, 'çekim', 0, 'UTF-8') !== false, "'$key' says çekim");
		if (preg_match('/\b[Pp]ackages?\b/', $key)) pg_assert_true(mb_stripos($tr, 'paket', 0, 'UTF-8') !== false, "'$key' says paket");
	}
}
