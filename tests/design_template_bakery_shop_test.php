<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bakery design template's pickup shop
 * (includes/design_templates/pinegrap-bakery/shop.php): the catalog follows
 * the template's data (the bakes by kind, the whole cakes at their smallest
 * size, the gift boxes, the class places that are never collected), every
 * page and widget needs the shop, the widgets point at each other, the
 * receipt, links, ids and classes the template's own, no fixed date and
 * every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_bakery_test.php');

// The shop as the template file reads it: parts.php, mail.php, then
// shop.php in the same scope. Read once per run.
function _test_bks_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$before = array($bakes, $cakes, $cake_sizes, $baking_classes, $categories);
	$shop = include $dir . '/shop.php';
	$out = array('shop' => $shop, 'bakes' => $bakes, 'cakes' => $cakes, 'classes' => $baking_classes, 'boxes' => $gift_boxes,
		'before' => $before, 'after' => array($bakes, $cakes, $cake_sizes, $baking_classes, $categories));
	return $out;
}

function _test_bks_page($key)
{
	foreach (_test_bks_load()['shop']['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

function test_bakery_shop_catalog_follows_the_data()
{
	$d = _test_bks_load();
	pg_assert_same($d['before'], $d['after'], 'shop.php leaves the data as it is');
	pg_assert_same('pinegrap_bakery_catalog', $d['shop']['catalog']['folder']);
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(array('bakery_shop', 'bk_breads', 'bk_pastries', 'bk_cakes', 'bk_boxes', 'bk_classes'), array_keys($groups));
	pg_assert_same('root', $groups['bakery_shop']['parent'], 'under the site\'s top group');
	pg_assert_false(isset($groups['bakery_shop']['products']), 'the top group holds groups only');
	$orders = array();
	$skus = array();
	$titles = array();
	foreach ($groups as $gk => $g) {
		if ($gk === 'bakery_shop') continue;
		pg_assert_same('bakery_shop', $g['parent'], "$gk sits in the Bakery Shop");
		$orders[] = $g['sort_order'];
		pg_assert_true(count($g['products']) > 0, "$gk has products");
		foreach ($g['products'] as $p) {
			pg_assert_true((bool)preg_match('/^BAKERY-[A-Z0-9-]+$/', $p['sku']), $p['sku'] . ': the template\'s own SKU');
			pg_assert_true(trim($p['title']) !== '' && trim($p['description']) !== '', $p['sku'] . ': title and description');
			pg_assert_true(_test_bk_price_ok($p['price']), $p['sku'] . ': a price in every currency');
			pg_assert_same($gk !== 'bk_classes', $p['shippable'], $p['sku'] . ($gk === 'bk_classes' ? ': a class place is never collected' : ': collected at the counter'));
			$skus[] = $p['sku'];
			$titles[$gk][] = $p['title'];
		}
	}
	pg_assert_same(range(1, 5), $orders, 'the groups in order');
	pg_assert_same(count($skus), count(array_unique($skus)), 'no SKU twice');
	// Every bake once, in the group of its kind, at its own price.
	$bake_titles = array();
	foreach ($groups as $gk => $g) {
		foreach ((array)($g['products'] ?? array()) as $p) {
			foreach ($d['bakes'] as $key => $b) {
				if ($p['sku'] !== 'BAKERY-' . strtoupper($key)) continue;
				$want = array('bread' => 'bk_breads', 'pastry' => 'bk_pastries', 'tart' => 'bk_pastries', 'cake' => 'bk_cakes');
				pg_assert_same($want[$b['category']], $gk, "$key in its kind's group");
				pg_assert_same($b['price'], $p['price'], "$key: the daily bake's price");
				$bake_titles[] = $key;
			}
		}
	}
	pg_assert_same(count($d['bakes']), count($bake_titles), 'every bake once');
	// The whole cakes: each but a tiered one, at its smallest size's price.
	$whole = 0;
	foreach ($d['cakes'] as $key => $c) {
		$found = null;
		foreach ($groups['bk_cakes']['products'] as $p) {
			if ($p['sku'] === 'BAKERY-CAKE-' . strtoupper($key)) $found = $p;
		}
		if ($c['sizes'][0] === 'tiered') {
			pg_assert_same(null, $found, "$key is made to order only");
			continue;
		}
		pg_assert_true($found !== null, "$key is sold whole");
		pg_assert_same($c['price'], $found['price'], "$key: the smallest size's price");
		pg_assert_contains($c['name'], $found['title'], "$key: its name");
		$whole++;
	}
	pg_assert_true($whole >= 3, 'whole cakes');
	pg_assert_same(count($d['boxes']), count($groups['bk_boxes']['products']), 'every gift box');
	pg_assert_same(count($d['classes']), count($groups['bk_classes']['products']), 'a place per class');
	$n = 0;
	foreach ($d['classes'] as $key => $c) {
		$p = $groups['bk_classes']['products'][$n++];
		pg_assert_same($c['price'], $p['price'], "$key: the class's price");
		pg_assert_contains($c['name'], $p['title'], "$key: the class's name");
	}
	pg_assert_true(count($d['shop']['catalog']['details']) >= 3, 'the Details tab');
}

function test_bakery_shop_parts_need_the_shop()
{
	$d = _test_bks_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, $p['key'] . ': meta description fits a result line');
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), $p['key'] . ': the page name is an address');
	}
	pg_assert_same(_test_bk_shop_keys(), $keys, 'the shop\'s pages');
	foreach ($d['shop']['widgets'] as $wk => $w) pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	pg_assert_same('private', _test_bks_page('email_order')['folder'], 'the receipt is private');
	pg_assert_true(_test_bks_page('cart')['noindex'], 'cart is not indexed');
	pg_assert_false(_test_bks_page('product')['noindex'], 'product pages are indexed');
	pg_assert_false(isset(_test_bks_page('shop')['noindex']), 'the shop page is indexed');
}

function test_bakery_shop_widgets_point_at_each_other()
{
	$d = _test_bks_load();
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
	pg_assert_same('{{product_group:bakery_shop}}', $c['product_group_id']);
	pg_assert_same('drill_down', $c['group_navigation'], 'the groups open in place');
	pg_assert_same('{{tab:product}}', $c['detail_page_id']);
	pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
	pg_assert_same('{{tab:shop}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the shop page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_with_shipping']);
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping'], 'class places alone skip the shipping step');
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_total', '__billing_address_html', '__shipping_address_html', '__item_short_description', '__order_shipping') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_contains('"eo_visible_if":"has_shipping"', $receipt, 'the pickup or delivery address only when something is collected');
	preg_match_all('/#[0-9a-f]{6}\b/i', $receipt, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#e11d48', '#fecdd3', '#fff1f2', '#ffffff', '#1f2937', '#4b5563'), true), "receipt colour $hex is the template's");
	}
}

function test_bakery_shop_links_ids_classes_and_dates()
{
	$d = _test_bks_load();
	$known = array_flip(_test_bk_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 7, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_bk_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) {
		if (is_array($wd['tree'])) _test_bk_nodes($wd['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(p[a-z]{2,3}|pf|pm)-/', $c) && strpos($c, 'pbk-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bky-') === 0, "id $id is the template's");
	}
	$text = json_encode($d['shop'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $text), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $text), 'no month in the sample');
}

// Every literal has its Turkish; a class reads "atölye", a cake "pasta",
// pickup "teslim alma".
function test_bakery_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, ' ') === false) continue;
		if (preg_match('/\b(Baking )?Class(es)?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		if (preg_match('/\bcakes?\b/i', $key) && !preg_match('/\bcake order\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'pasta', 0, 'UTF-8') !== false, "'$key' says pasta");
		if (preg_match('/\bpickup\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'teslim al', 0, 'UTF-8') !== false, "'$key' says teslim alma");
	}
	foreach (array('Classes', 'Class') as $k) pg_assert_false(strpos($src, "lang('$k')") !== false, "no lang('$k')");
}
