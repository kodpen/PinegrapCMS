<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Florist design template's shop
 * (includes/design_templates/pinegrap-florist/shop.php): the catalog
 * follows the data (a group per occasion with its bouquets, a prepaid
 * bundle per plan at its three months' price, the extras with their own
 * SKUs and shipping), every page and widget needs the shop, the widgets
 * point at each other, the receipt reads the order, links name template
 * pages, classes and ids are the template's own, and every literal has its
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

require_once(__DIR__ . '/design_template_florist_test.php');

// The shop as the template file reads it: parts.php, mail.php, then
// shop.php in the same scope. Read once per run.
function _test_fls_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$before = array($occasions, $bouquets, $bouquet_prices, $plans, $plan_prices, $extras);
	$shop = include $dir . '/shop.php';
	$totals = array();
	foreach (array_keys($plans) as $key) $totals[$key] = $plan_total($key);
	$out = array('shop' => $shop, 'occasions' => $occasions, 'bouquets' => $bouquets, 'bouquet_prices' => $bouquet_prices,
		'plans' => $plans, 'totals' => $totals, 'extras' => $extras,
		'before' => $before, 'after' => array($occasions, $bouquets, $bouquet_prices, $plans, $plan_prices, $extras));
	return $out;
}

function _test_fls_page($key)
{
	foreach (_test_fls_load()['shop']['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

function test_florist_shop_catalog_follows_the_data()
{
	$d = _test_fls_load();
	pg_assert_same($d['before'], $d['after'], 'shop.php leaves the data as it is');
	pg_assert_same('pinegrap_florist_catalog', $d['shop']['catalog']['folder']);
	$groups = $d['shop']['catalog']['groups'];
	$want = array('florist_shop');
	foreach (array_keys($d['occasions']) as $key) $want[] = 'fl_' . $key;
	$want[] = 'fl_subscriptions';
	$want[] = 'fl_extras';
	pg_assert_same($want, array_keys($groups), 'the shop, an occasion each, the subscriptions, the extras');
	pg_assert_same('root', $groups['florist_shop']['parent'], 'under the site\'s top group');
	pg_assert_false(isset($groups['florist_shop']['products']), 'the top group holds groups only');
	$orders = array();
	foreach ($groups as $gk => $g) {
		if ($gk === 'florist_shop') continue;
		pg_assert_same('florist_shop', $g['parent'], "$gk sits in the Flower Shop");
		$orders[] = $g['sort_order'];
		pg_assert_true(count($g['products']) > 0, "$gk has products");
	}
	pg_assert_same(range(1, count($orders)), $orders, 'the groups in order');
	$skus = array();
	$bouquet_count = 0;
	foreach ($d['occasions'] as $okey => $o) {
		pg_assert_same($o['title'], $groups['fl_' . $okey]['title'], "$okey: the occasion's name");
		$titles = array();
		foreach ($groups['fl_' . $okey]['products'] as $p) $titles[] = $p['title'];
		$want = array();
		foreach ($d['bouquets'] as $bkey => $b) {
			if ($b['occasion'] === $okey) $want[] = $b['title'];
		}
		pg_assert_same($want, $titles, "$okey: its bouquets");
		$bouquet_count += count($titles);
	}
	pg_assert_same(count($d['bouquets']), $bouquet_count, 'every bouquet once');
	$by_title = array();
	foreach ($d['bouquets'] as $bkey => $b) $by_title[$b['title']] = $bkey;
	foreach ($groups as $gk => $g) {
		foreach ((array)($g['products'] ?? array()) as $p) {
			pg_assert_true((bool)preg_match('/^FLORIST-[A-Z0-9-]+$/', $p['sku']), $p['sku'] . ': the template\'s own SKU');
			pg_assert_true(trim($p['title']) !== '' && trim($p['description']) !== '', $p['sku'] . ': title and description');
			pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, $p['sku'] . ': a price');
			$skus[] = $p['sku'];
			if (strpos($gk, 'fl_') === 0 && isset($d['occasions'][substr($gk, 3)])) {
				$bkey = $by_title[$p['title']];
				pg_assert_same($d['bouquet_prices'][$bkey], $p['price'], $p['sku'] . ': the bouquet\'s price');
				pg_assert_true($p['shippable'], $p['sku'] . ': delivered');
				pg_assert_same(!empty($d['bouquets'][$bkey]['featured']), $p['featured'], $p['sku'] . ': featured as on the home');
			}
		}
	}
	pg_assert_same(count($skus), count(array_unique($skus)), 'no SKU twice');
	$n = 0;
	foreach ($d['plans'] as $key => $plan) {
		$p = $groups['fl_subscriptions']['products'][$n++];
		pg_assert_same($d['totals'][$key], $p['price'], "$key: three months at once");
		pg_assert_contains($plan['title'], $p['title'], "$key: the plan's name");
		pg_assert_contains((string)$plan['deliveries'], $p['description'], "$key: its deliveries");
	}
	pg_assert_same(count($d['plans']), $n, 'a bundle per plan');
	$n = 0;
	foreach ($d['extras'] as $x) {
		$p = $groups['fl_extras']['products'][$n++];
		pg_assert_same(array($x['sku'], $x['price'], $x['shippable']), array($p['sku'], $p['price'], $p['shippable']), $x['sku'] . ': as parts.php has it');
	}
	pg_assert_same(count($d['extras']), $n, 'every extra');
	pg_assert_true(count($d['shop']['catalog']['details']) >= 3, 'the Details tab');
}

function test_florist_shop_parts_need_the_shop()
{
	$d = _test_fls_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, $p['key'] . ': meta description fits a result line');
	}
	pg_assert_same(_test_fl_shop_keys(), $keys, 'the shop\'s pages');
	foreach ($d['shop']['widgets'] as $wk => $w) {
		pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	}
	pg_assert_same('private', _test_fls_page('email_order')['folder'], 'the receipt is private');
	pg_assert_true(_test_fls_page('cart')['noindex'], 'cart is not indexed');
	pg_assert_false(_test_fls_page('product')['noindex'], 'product pages are indexed');
	pg_assert_false(isset(_test_fls_page('shop')['noindex']), 'the shop page is indexed');
}

function test_florist_shop_widgets_point_at_each_other()
{
	$d = _test_fls_load();
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
	pg_assert_same('{{product_group:florist_shop}}', $c['product_group_id']);
	pg_assert_same('drill_down', $c['group_navigation'], 'the groups open in place');
	pg_assert_same('{{tab:product}}', $c['detail_page_id']);
	pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
	pg_assert_same('{{tab:shop}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the shop page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_with_shipping']);
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_total', '__billing_address_html', '__shipping_address_html', '__item_short_description', '__order_shipping') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_contains('"eo_visible_if":"has_shipping"', $receipt, 'the delivery address only when something is delivered');
}

function test_florist_shop_links_ids_and_classes()
{
	$d = _test_fls_load();
	$known = array_flip(_test_fl_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 7, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_fl_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) {
		if (is_array($wd['tree'])) _test_fl_nodes($wd['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pwd|pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fl-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a subscription reads "abonelik", a
// bouquet "buket" and a delivery "teslim". Page names are address words.
function test_florist_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/\b[Ss]ubscriptions?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'abonel', 0, 'UTF-8') !== false, "'$key' says abonelik");
		if (preg_match('/\b[Bb]ouquets?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'buket', 0, 'UTF-8') !== false, "'$key' says buket");
		if (preg_match('/\b[Dd]elivery\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'teslim', 0, 'UTF-8') !== false, "'$key' says teslim");
	}
}
