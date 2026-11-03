<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Phone Launch Site design template's shop
 * (includes/design_templates/pinegrap-launch/shop.php): the catalog has a
 * product for each model and storage size at the price table's figures and
 * the accessories, the buy page stands without the shop and carries the
 * anchors the other parts link to, only the shop's pages and widgets need
 * the shop, the listings and the shop pages point at each other, every
 * link names a page of the finished template, and every literal has its
 * Turkish.
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
require_once(__DIR__ . '/design_template_launch_test.php');

// The shop as the template file reads it: parts.php, the e-mail parts,
// then shop.php in the same scope. Read once per run.
function _test_launch_shop_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch';
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
	$shop = include $dir . '/shop.php';
	$out = array(
		'shop'     => $shop,
		'models'   => $models,
		'prices'   => $phone_prices,
		'sku'      => $phone_sku,
		'acc'      => $accessories,
		'money'    => $money,
	);
	return $out;
}

function _test_launch_shop_page($key)
{
	$d = _test_launch_shop_load();
	foreach ($d['shop']['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

function test_launch_shop_catalog_follows_the_price_table()
{
	$d = _test_launch_shop_load();
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(array('phones', 'accessories'), array_keys($groups), 'two groups');
	foreach ($groups as $key => $g) {
		pg_assert_same('root', $g['parent'], "$key under the site's top group");
		pg_assert_true($g['title'] !== '', "$key: title");
	}

	$want = array();
	foreach ($d['models'] as $mk => $m) {
		foreach ($m['storage'] as $gb) $want[call_user_func($d['sku'], $mk, $gb)] = $d['prices'][$mk][$gb];
	}
	$got = array();
	foreach ($groups['phones']['products'] as $p) {
		$got[$p['sku']] = $p['price'];
		pg_assert_true($p['title'] !== '' && $p['description'] !== '', $p['sku'] . ': title and description');
		pg_assert_false(isset($p['shippable']), $p['sku'] . ': a phone is shipped');
	}
	pg_assert_same($want, $got, 'a phone product for each model and size, smallest first, at its price');

	$featured = array();
	foreach ($groups['phones']['products'] as $p) {
		if (!empty($p['featured'])) $featured[] = $p['sku'];
	}
	pg_assert_true(count($featured) > 0, 'the most chosen model is featured');
	foreach ($featured as $sku) pg_assert_contains('-ONE-', $sku, 'only the featured model');

	$acc = array();
	foreach ($groups['accessories']['products'] as $p) $acc[$p['sku']] = $p['price'];
	$want_acc = array();
	foreach ($d['acc'] as $a) $want_acc[$a['sku']] = $a['price'];
	pg_assert_same($want_acc, $acc, 'every accessory at its price');
	pg_assert_same('pinegrap_launch_catalog', $d['shop']['catalog']['folder']);
}

function test_launch_buy_page_stands_without_the_shop()
{
	$buy = _test_launch_shop_page('buy');
	pg_assert_true($buy !== null, 'buy page');
	pg_assert_false(isset($buy['requires']), 'buy page without the shop');
	$json = json_encode($buy['tree']);
	pg_assert_contains('"templateWidget":"buy_phones"', $json, 'buy page places the phones on sale');
	foreach (array('buy-mini', 'buy-one', 'buy-plus', 'accessories') as $id) {
		pg_assert_contains('"id":"' . $id . '"', $json, "anchor #$id the cards and the footer link to");
	}
	pg_assert_contains('{{page:newsletter}}', $json, 'the launch list');
	pg_assert_contains('{{page:contact}}', $json, 'a question');
	pg_assert_contains('{{page:tradein}}?model=one', $json, 'a trade-in quote names the model');
	// Without the shop the page links to none of its pages; the widget that
	// does is left out with it.
	foreach (_test_launch_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "buy page does not link to $key");
	}
	// The prices the page writes are the ones the products are made with.
	$d = _test_launch_shop_load();
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['prices']['plus']['512'])), $json, 'a size\'s price');
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['acc']['buds']['price'])), $json, 'an accessory\'s price');
}

function test_launch_shop_parts_need_the_shop()
{
	$d = _test_launch_shop_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		if ($p['key'] === 'buy') continue;
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
	}
	pg_assert_same(array('buy', 'accessories', 'product', 'cart', 'checkout', 'order', 'email_order'), $keys);
	foreach ($d['shop']['widgets'] as $wk => $w) {
		pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	}
	pg_assert_same('private', _test_launch_shop_page('email_order')['folder'], 'the receipt is private');
	pg_assert_true(_test_launch_shop_page('cart')['noindex'], 'cart is not indexed');
	pg_assert_false(_test_launch_shop_page('product')['noindex'], 'product pages are indexed');
}

function test_launch_shop_widgets_point_at_each_other()
{
	$d = _test_launch_shop_load();
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
	foreach (array('buy_phones' => 'phones', 'accessory_catalog' => 'accessories') as $wk => $group) {
		$c = $w[$wk]['config'];
		pg_assert_same('catalog_listing', $c['regionType']);
		pg_assert_same('{{product_group:' . $group . '}}', $c['product_group_id']);
		pg_assert_same('{{tab:product}}', $c['detail_page_id']);
		pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], "$wk: the notice names this shop's cart");
		pg_assert_contains('"catalog_add_to_cart"', json_encode($w[$wk]['tree']), "$wk: Add to cart");
	}
	pg_assert_same('{{tab:buy}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the buy page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_with_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_total', '__billing_address_html', '__shipping_address_html', '__item_short_description') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
}

function test_launch_shop_links_name_template_pages()
{
	$d = _test_launch_shop_load();
	$known = array_flip(_test_launch_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_launch_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) _test_launch_nodes($wd['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pc|pl)-/', $c), "class $c is not another template's");
		}
	}
}

function test_launch_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (strpos($key, 'Orbit') !== false && isset($map[$key])) pg_assert_contains('Orbit', $map[$key], "'$key' keeps the name Orbit");
		if (stripos($key, 'trade-in') !== false && isset($map[$key])) pg_assert_true(mb_stripos($map[$key], 'takas', 0, 'UTF-8') !== false, "'$key' says takas");
	}
}
