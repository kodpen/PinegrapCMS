<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bike Shop design template's shop
 * (includes/design_templates/pinegrap-bikeshop/shop.php): the catalog has
 * the four groups of the data with a product for each part, accessory and
 * voucher and a bike once per frame size, at the prices the public pages
 * show; only parts and accessories are posted; every page and widget needs
 * the shop, the listings and the shop pages point at each other, every
 * link names a page of the finished template, the ids and classes are the
 * template's own, no fixed date and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_bikeshop_test.php');

// The shop as the template file reads it: parts.php, cards.php, mail.php,
// then shop.php in the same scope. Read once per run.
function _test_bikeshops_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	include $dir . '/mail.php';
	$data = function () use (&$products, &$product_prices, &$product_groups, &$frame_sizes, &$shop) {
		return array($products, $product_prices, $product_groups, $frame_sizes, $shop);
	};
	$before = $data();
	$part = include $dir . '/shop.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'before' => $before, 'after' => $data(),
		'products' => $products, 'product_prices' => $product_prices, 'product_groups' => $product_groups,
		'frame_sizes' => $frame_sizes, 'money' => $money, 'sku' => $product_sku);
	return $out;
}

function test_bikeshop_shop_catalog_follows_the_data()
{
	$d = _test_bikeshops_load();
	pg_assert_same($d['before'], $d['after'], 'shop.php leaves the data as it is');
	$catalog = $d['part']['catalog'];
	pg_assert_same('pinegrap_bikeshop_catalog', $catalog['folder']);
	pg_assert_same(3, count($catalog['details']), 'the Details tab');
	pg_assert_same(array('bikes', 'parts', 'accessories', 'vouchers'), array_keys($catalog['groups']), 'the four groups');
	$skus = array();
	foreach ($catalog['groups'] as $g => $group) {
		pg_assert_same('root', $group['parent'], "$g under the site's top group");
		pg_assert_same($d['product_groups'][$g]['title'], $group['title'], "$g: the data's title");
		pg_assert_true($group['description'] !== '' && $group['keywords'] !== '', "$g: description and keywords");
		$expect = array();
		foreach ($d['products'] as $key => $p) {
			if ($p['group'] !== $g) continue;
			$code = call_user_func($d['sku'], $key);
			if ($g === 'bikes') {
				foreach (array_keys($d['frame_sizes']) as $size) $expect[$code . '-' . $size] = $d['product_prices'][$key];
			} else {
				$expect[$code] = $d['product_prices'][$key];
			}
		}
		$got = array();
		foreach ($group['products'] as $p) {
			pg_assert_true(strpos($p['sku'], $d['product_groups'][$g]['sku']) === 0, $p['sku'] . ': the group\'s code');
			pg_assert_true((bool)preg_match('/^BKS-(BIKE|PART|ACC|GIFT)-[A-Z0-9-]+$/', $p['sku']), $p['sku'] . ': a SKU of letters, digits and hyphens');
			pg_assert_false(isset($skus[$p['sku']]), $p['sku'] . ': no SKU twice');
			$skus[$p['sku']] = true;
			$got[$p['sku']] = $p['price'];
			pg_assert_true($p['title'] !== '' && $p['description'] !== '' && $p['keywords'] !== '', $p['sku'] . ': title, description and keywords');
			pg_assert_same(in_array($g, array('parts', 'accessories'), true), $p['shippable'], $p['sku'] . ': only parts and accessories are posted');
			pg_assert_true(empty($p['donation']), $p['sku'] . ': a voucher has its price');
		}
		pg_assert_same($expect, $got, "$g: a product per item (a bike per size) in the data's order, at its prices");
	}
	// A bike's size is in its name and its words; the newest bikes of the
	// home are featured once, in the middle size.
	$featured = array();
	foreach ($catalog['groups']['bikes']['products'] as $p) {
		$size = substr($p['sku'], strrpos($p['sku'], '-') + 1);
		pg_assert_contains($size, $p['title'], $p['sku'] . ': the size in the name');
		pg_assert_contains($d['frame_sizes'][$size], $p['description'], $p['sku'] . ': the riders of the size');
		if (!empty($p['featured'])) $featured[] = $p['sku'];
	}
	$want = array();
	foreach ($d['products'] as $key => $p) {
		if ($p['group'] === 'bikes' && !empty($p['featured'])) $want[] = call_user_func($d['sku'], $key) . '-M';
	}
	pg_assert_same($want, $featured, 'the newest bikes featured in size M');
	foreach (array('parts', 'accessories', 'vouchers') as $g) {
		foreach ($catalog['groups'][$g]['products'] as $p) pg_assert_true(empty($p['featured']), $p['sku'] . ': not featured');
	}
}

function test_bikeshop_shop_parts_need_the_shop()
{
	$d = _test_bikeshops_load();
	pg_assert_same(array('pages', 'widgets', 'catalog'), array_keys($d['part']));
	pg_assert_same(array('shop', 'product', 'cart', 'checkout', 'order', 'email_order'), array_keys($d['pages']), 'the shop\'s pages');
	$shop_keys = _test_bikeshop_shop_keys();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(in_array($key, $shop_keys, true), "$key is a shop page of the template");
		pg_assert_same('ecommerce', $p['requires'] ?? '', "$key needs the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
	}
	foreach ($d['part']['widgets'] as $wk => $w) pg_assert_same('ecommerce', $w['requires'], "widget $wk needs the shop");
	pg_assert_same('private', $d['pages']['email_order']['folder'], 'the receipt is private');
	foreach (array('cart', 'checkout', 'order') as $key) pg_assert_true($d['pages'][$key]['noindex'], "$key is not indexed");
	pg_assert_false($d['pages']['product']['noindex'], 'product pages are indexed');
	pg_assert_false(isset($d['pages']['shop']['noindex']), 'the shop is indexed');
	pg_assert_true(mb_strlen($d['pages']['shop']['meta_description'], 'UTF-8') <= 160, 'shop: meta description fits a result line');
}

function test_bikeshop_shop_widgets_point_at_each_other()
{
	$d = _test_bikeshops_load();
	$w = $d['part']['widgets'];
	pg_assert_same(array('bike_catalog', 'part_catalog', 'accessory_catalog', 'voucher_catalog', 'cart_link', 'product_detail', 'cart', 'checkout', 'order_view', 'email_order'), array_keys($w));
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
	$listings = array('bike_catalog' => 'bikes', 'part_catalog' => 'parts', 'accessory_catalog' => 'accessories', 'voucher_catalog' => 'vouchers');
	foreach ($listings as $wk => $group) {
		$c = $w[$wk]['config'];
		pg_assert_same('catalog_listing', $c['regionType']);
		pg_assert_same('{{product_group:' . $group . '}}', $c['product_group_id'], "$wk lists its group");
		pg_assert_same('{{tab:product}}', $c['detail_page_id']);
		pg_assert_same('{{tab:cart}}', $c['add_to_cart_next_page_id'], 'the notice names this shop\'s cart');
		pg_assert_true($c['empty_message'] !== '', "$wk: words for an empty group");
		$tree = json_encode($w[$wk]['tree']);
		foreach (array('"catalog_add_to_cart"', '"__detail_url"', '"__price_formatted"', '"__short_description"') as $t) pg_assert_contains($t, $tree, "$wk card: $t");
		foreach ($d['products'] as $key => $p) {
			if ($p['group'] !== $group) continue;
			pg_assert_contains(json_encode(call_user_func($d['money'], $d['product_prices'][$key])), $tree, "$wk: the sample card's price from the table");
			break;
		}
	}
	pg_assert_same('{{tab:shop}}', $w['product_detail']['config']['catalog_listing_page_id'], 'the breadcrumb starts at the shop');
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_with_shipping']);
	pg_assert_same('{{tab:checkout}}', $w['cart']['config']['next_page_id_without_shipping']);
	pg_assert_same('{{tab:order}}', $w['checkout']['config']['next_page_id']);
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('order_view', $w['email_order']['config']['regionType']);
	$receipt = json_encode($w['email_order']['tree']);
	foreach (array('__order_no', '__order_date', '__order_total', '__order_shipping', '__billing_address_html', '__shipping_address_html', '__item_short_description', '__item_qty', '__item_total') as $t) {
		pg_assert_contains('"' . $t . '"', $receipt, "receipt shows $t");
	}
	pg_assert_contains('"has_shipping"', $receipt, 'the postage and the delivery address only when posted');
	$page = json_encode($d['pages']['email_order']['tree']);
	pg_assert_contains('"templateShared":"email_header"', $page, 'the shared e-mail header');
	pg_assert_contains('"templateShared":"email_footer"', $page, 'the shared e-mail footer');
}

function test_bikeshop_shop_links_ids_and_classes()
{
	$d = _test_bikeshops_load();
	$known = array_flip(_test_bikeshop_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['part']), $m);
	pg_assert_true(count(array_unique($m[1])) >= 9, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['part']['widgets'] as $key => $wd) {
		if (is_array($wd['tree'])) $trees['widget ' . $key] = $wd['tree'];
	}
	$ids = array();
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_bikeshop_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (preg_match('/^([a-z]{3})-/', $c, $pre) && $pre[1][0] === 'p') pg_assert_true($pre[1] === 'pbs', "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "$key: no faded text ($c)");
				pg_assert_false((bool)preg_match('/^rounded(-[1-5]|-pill)$/', $c), "$key: square corners ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'bikeshop-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	foreach (array('bikes', 'parts', 'accessories', 'vouchers', 'how') as $s) pg_assert_true(isset($ids['bikeshop-shop-' . $s]), "shop: search section $s");
	$nodes = array();
	_test_bikeshop_nodes($d['pages']['shop']['tree'], $nodes);
	$levels = array();
	foreach ($nodes as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $hm)) $levels[] = (int)$hm[1];
	}
	$set = array_values(array_unique($levels));
	sort($set);
	pg_assert_same(range(1, max($set)), $set, 'shop: no heading level skipped');
	// No year or month in the pages' words; the prices are figures.
	$json = json_encode(array($d['pages'], $d['part']['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the shop');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the shop');
}

// Every literal has its Turkish; a voucher reads "hediye çeki", the
// workshop "atölye", a part "parça".
function test_bikeshop_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "shop.php: Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = mb_strtolower($map[$key], 'UTF-8');
		if (preg_match('/\bvouchers?\b/i', $key)) pg_assert_true(mb_strpos($tr, 'hediye çek', 0, 'UTF-8') !== false, "'$key' says hediye çeki");
		if (preg_match('/\bworkshop\b/i', $key)) pg_assert_true(mb_strpos($tr, 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		if (preg_match('/\bparts?\b/i', $key)) pg_assert_true(mb_strpos($tr, 'parça', 0, 'UTF-8') !== false, "'$key' says parça");
	}
}
