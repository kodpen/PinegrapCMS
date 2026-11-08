<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Veterinary design template's shop
 * (includes/design_templates/pinegrap-veterinary/shop.php): the catalog has
 * a product for each health plan at its monthly price times its months,
 * none shipped, and a shipped product for each pet care item; every page
 * and widget needs the shop, the listings and the shop pages point at each
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

// The page and shop keys of the finished template (_test_vt_page_keys(),
// _test_vt_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_veterinary_test.php';

// The shop as the template file reads it: parts.php, mail.php, then
// shop.php in the same scope. Read once per run.
function _test_vts_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$before = array($plans, $species);
	$shop = include $dir . '/shop.php';
	$pages = array();
	foreach ($shop['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('shop' => $shop, 'pages' => $pages, 'before' => $before, 'after' => array($plans, $species),
		'plans' => $plans, 'species' => $species, 'care' => $care_products, 'money' => $money);
	return $out;
}

// Every node of a tree, depth first.
function _test_vts_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_vts_nodes($child, $out);
	}
}

function test_veterinary_shop_catalog_follows_the_data()
{
	$d = _test_vts_load();
	pg_assert_same($d['before'], $d['after'], 'shop.php leaves the data as it is');
	$catalog = $d['shop']['catalog'];
	pg_assert_same('pinegrap_veterinary_catalog', $catalog['folder']);
	pg_assert_same(3, count($catalog['details']), 'the Details tab');
	pg_assert_same(array('plans', 'care'), array_keys($catalog['groups']), 'two groups');
	foreach ($catalog['groups'] as $g => $group) {
		pg_assert_same('root', $group['parent'], "$g under the site's top group");
		pg_assert_true($group['title'] !== '' && $group['description'] !== '', "$g: title and description");
	}

	// A plan's product pays its year: the monthly price times its months.
	$expect = array();
	$plan_keys = array();
	foreach ($d['plans'] as $key => $p) {
		$plan_keys[$p['sku']] = $key;
		$year = array();
		foreach ($p['price'] as $c => $amount) $year[$c] = $amount * $p['months'];
		$expect[$p['sku']] = $year;
	}
	$got = array();
	$featured = array();
	foreach ($catalog['groups']['plans']['products'] as $p) {
		$got[$p['sku']] = $p['price'];
		pg_assert_same(false, $p['shippable'], $p['sku'] . ': a plan is not shipped');
		if (!empty($p['featured'])) $featured[] = $p['sku'];
		$monthly = call_user_func($d['money'], $d['plans'][$plan_keys[$p['sku']]]['price']);
		pg_assert_contains($monthly, $p['description'], $p['sku'] . ': the description names the monthly price');
	}
	pg_assert_same($expect, $got, 'a product per plan in the data\'s order, at a year of its price');
	pg_assert_same(array('VET-PLAN-ADULT'), $featured, 'the plan most owners choose is featured');

	$skus = array();
	$featured = 0;
	foreach ($catalog['groups'] as $group) {
		foreach ($group['products'] as $p) {
			pg_assert_false(isset($skus[$p['sku']]), $p['sku'] . ': no SKU twice');
			$skus[$p['sku']] = true;
			pg_assert_true(strpos($p['sku'], 'VET-') === 0, $p['sku'] . ': the template\'s SKU');
			pg_assert_true($p['title'] !== '' && $p['description'] !== '' && $p['keywords'] !== '', $p['sku'] . ': title, description and keywords');
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($p['price'][$c]) && $p['price'][$c] > 0, $p['sku'] . ": $c price");
		}
	}
	pg_assert_same(count($d['care']), count($catalog['groups']['care']['products']), 'a product per pet care item');
	foreach ($catalog['groups']['care']['products'] as $p) {
		pg_assert_false(isset($p['shippable']), $p['sku'] . ': pet care products are shipped');
		if (!empty($p['featured'])) $featured++;
	}
	pg_assert_same(1, $featured, 'one pet care product featured');
	foreach ($d['care'] as $key => $c) {
		foreach ($c['species'] as $s) pg_assert_true(isset($d['species'][$s]), "care $key: pet $s is one the practice treats");
	}
}

function test_veterinary_shop_parts_need_the_shop()
{
	$d = _test_vts_load();
	pg_assert_same(_test_vt_shop_keys(), array_keys($d['pages']), 'the shop\'s pages');
	$all = _test_vt_page_keys();
	foreach (array_keys($d['pages']) as $key) {
		pg_assert_true(in_array($key, $all, true), "$key is a page of the template");
		pg_assert_same('ecommerce', $d['pages'][$key]['requires'] ?? '', "$key needs the shop");
	}
	foreach ($d['shop']['widgets'] as $wk => $w) pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	pg_assert_same('private', $d['pages']['email_order']['folder'], 'the receipt is private');
	foreach (array('cart', 'checkout', 'order') as $key) pg_assert_true($d['pages'][$key]['noindex'], "$key is not indexed");
	pg_assert_false($d['pages']['product']['noindex'], 'product pages are indexed');
	pg_assert_false(isset($d['pages']['shop']['noindex']), 'the shop page is indexed');
	pg_assert_true(mb_strlen($d['pages']['shop']['meta_description'], 'UTF-8') <= 160, 'shop: meta description fits a result line');
}

function test_veterinary_shop_widgets_point_at_each_other()
{
	$d = _test_vts_load();
	$w = $d['shop']['widgets'];
	pg_assert_same(array('plan_catalog', 'care_catalog', 'cart_link', 'product_detail', 'cart', 'checkout', 'order_view', 'email_order'), array_keys($w));
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
	$year = array();
	foreach ($d['plans']['adult']['price'] as $c => $amount) $year[$c] = $amount * $d['plans']['adult']['months'];
	$listings = array(
		'plan_catalog' => array('plans', $year),
		'care_catalog' => array('care', $d['care']['dental_kit']['price']),
	);
	foreach ($listings as $wk => list($group, $price)) {
		$c = $w[$wk]['config'];
		pg_assert_same('catalog_listing', $c['regionType']);
		pg_assert_same('{{product_group:' . $group . '}}', $c['product_group_id'], "$wk lists its group");
		pg_assert_same('{{tab:product}}', $c['detail_page_id']);
		pg_assert_same(true, $c['add_to_cart_stay_on_page'], "$wk stays on the page after an add");
		pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
		$tree = json_encode($w[$wk]['tree']);
		foreach (array('"catalog_add_to_cart"', '"__detail_url"', '"__price_formatted"', '"__short_description"') as $t) pg_assert_contains($t, $tree, "$wk card: $t");
		pg_assert_contains(trim(json_encode(call_user_func($d['money'], $price)), '"'), $tree, "$wk: the sample card's price");
	}
	pg_assert_same('{{tab:shop}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the shop page');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_with_shipping']);
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_date', '__order_total', '__order_shipping', '__shipping_address_html', '__billing_address_html',
		'__item_short_description', '__item_qty', '__item_total', 'has_shipping') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_false(strpos($receipt, '#9f1239') !== false || strpos($receipt, '#b91c1c') !== false, 'no other template\'s e-mail colour');
}

function test_veterinary_shop_links_ids_and_classes()
{
	$d = _test_vts_load();
	$known = array_flip(_test_vt_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['shop']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 9, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_vts_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $wd) {
		if (is_array($wd['tree'])) _test_vts_nodes($wd['tree'], $nodes);
	}
	pg_assert_true(count($nodes) > 100, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai|pth)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'vt-') === 0, "id $id is the template's");
	}
	// The health plans page already uses vt-plans: the shop's own ids differ.
	$json = json_encode(array($d['pages'], $d['shop']['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false(strpos($json, '"vt-plans"') !== false, 'vt-plans stays the health plans page\'s');
	// No year in the pages' words (an old page a year later).
	pg_assert_false((bool)preg_match('/\b(1[5-9]|20)[0-9]{2}\b/', $json), 'no year in the shop');
}

// Every literal has its Turkish; a plan reads "plan", a pet "evcil hayvan",
// a vet "veteriner hekim".
function test_veterinary_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Hh]ealth [Pp]lans?\b/', $key)) pg_assert_true(mb_stripos($tr, 'sağlık plan', 0, 'UTF-8') !== false, "'$key' says sağlık planı");
		if (preg_match('/\b[Pp]et\b/', $key)) pg_assert_true(mb_stripos($tr, 'evcil hayvan', 0, 'UTF-8') !== false, "'$key' says evcil hayvan");
		if (preg_match('/\b[Vv]ets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veteriner hekim', 0, 'UTF-8') !== false, "'$key' says veteriner hekim");
	}
}
