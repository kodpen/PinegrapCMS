<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Hotel design template's shop
 * (includes/design_templates/pinegrap-hotel/shop.php): the catalog has a
 * product for each voucher at its price, sent by e-mail, every page and
 * widget needs the shop, the listing and the shop pages point at each
 * other, every link names a page of the finished template, the classes are
 * the template's own and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(__DIR__ . '/design_template_hotel_test.php');

// The shop as the template file reads it: parts.php, the e-mail parts,
// then shop.php in the same scope. Read once per run.
function _test_hts_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel';
	include $dir . '/parts.php';
	if (is_file($dir . '/mail.php')) {
		include $dir . '/mail.php';
	} else {
		// The e-mail parts' shapes, until the template's own mail.php is in.
		$mail_style = function ($css) use ($attr) {
			return array('_attrs' => array($attr('style', $css)));
		};
		$mail_page = function ($children) use ($root) {
			return $root($children);
		};
		$mail_heading = function ($text) use ($heading) {
			return $heading('h1', $text, 'h4');
		};
		$mail_para = function ($text, $class = 'mb-3', $style = '') use ($para) {
			return $para($text, $class);
		};
		$mail_button = function ($text, $href) use ($link) {
			return $link($text, $href, 'btn btn-primary');
		};
		$mail_box = function ($children, $name) use ($el) {
			return $el('div', 'p-3', $name, $children);
		};
		$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);
	}
	$samples = $vouchers;
	$shop = include $dir . '/shop.php';
	$out = array('shop' => $shop, 'vouchers' => $samples, 'after' => $vouchers, 'money' => $money);
	return $out;
}

function _test_hts_page($key)
{
	$d = _test_hts_load();
	foreach ($d['shop']['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

function test_hotel_shop_catalog_follows_the_vouchers()
{
	$d = _test_hts_load();
	pg_assert_same($d['vouchers'], $d['after'], 'shop.php leaves the samples as they are');
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(array('vouchers'), array_keys($groups), 'one group');
	pg_assert_same('root', $groups['vouchers']['parent'], 'under the site\'s top group');
	$want = array();
	foreach ($d['vouchers'] as $v) $want[$v['sku']] = $v['price'];
	$got = array();
	$featured = 0;
	foreach ($groups['vouchers']['products'] as $p) {
		$got[$p['sku']] = $p['price'];
		pg_assert_true($p['title'] !== '' && $p['description'] !== '', $p['sku'] . ': title and description');
		pg_assert_same(false, $p['shippable'], $p['sku'] . ': sent by e-mail, not shipped');
		if (!empty($p['featured'])) $featured++;
	}
	pg_assert_same($want, $got, 'a product for each voucher at its price');
	pg_assert_same(1, $featured, 'one voucher featured');
	pg_assert_same('pinegrap_hotel_catalog', $d['shop']['catalog']['folder']);
}

function test_hotel_shop_parts_need_the_shop()
{
	$d = _test_hts_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
	}
	pg_assert_same(_test_ht_shop_keys(), $keys, 'the shop\'s pages');
	foreach ($d['shop']['widgets'] as $wk => $w) {
		pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	}
	pg_assert_same('private', _test_hts_page('email_order')['folder'], 'the receipt is private');
	pg_assert_true(_test_hts_page('cart')['noindex'], 'cart is not indexed');
	pg_assert_false(_test_hts_page('product')['noindex'], 'product pages are indexed');
	pg_assert_false(isset(_test_hts_page('vouchers')['noindex']), 'the vouchers page is indexed');
}

function test_hotel_shop_widgets_point_at_each_other()
{
	$d = _test_hts_load();
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
	$c = $w['voucher_catalog']['config'];
	pg_assert_same('catalog_listing', $c['regionType']);
	pg_assert_same('{{product_group:vouchers}}', $c['product_group_id']);
	pg_assert_same('{{tab:product}}', $c['detail_page_id']);
	pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
	$listing = json_encode($w['voucher_catalog']['tree']);
	pg_assert_contains('"catalog_add_to_cart"', $listing, 'Add to cart');
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['vouchers']['gift250']['price'])), $listing, 'the sample card\'s price');
	pg_assert_same('{{tab:vouchers}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the vouchers page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_total', '__billing_address_html', '__item_short_description') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_false(strpos($receipt, '__shipping_address_html') !== false, 'a voucher has no shipping address');
}

function test_hotel_shop_links_name_template_pages()
{
	$d = _test_hts_load();
	$known = array_flip(_test_ht_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 7, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_ht_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) {
		if (is_array($wd['tree'])) _test_ht_nodes($wd['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pc|pl|plx|pas)-/', $c), "class $c is not another template's");
		}
	}
}

function test_hotel_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
	}
}
