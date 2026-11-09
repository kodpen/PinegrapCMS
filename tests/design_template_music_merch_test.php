<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Music Artist design template's merch shop
 * (includes/design_templates/pinegrap-music/merch.php): the shop pages
 * and widgets, every one only with the shop; the catalog's two groups with
 * the merch, a signed vinyl of every release and the gift cards, at the
 * prices parts.php gives, merch posted and gift cards not; the cards the
 * listings draw; the receipt with the postage and the delivery address;
 * every page link naming a page of the finished template; the template's
 * own classes; and every literal with its Turkish.
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
require_once(__DIR__ . '/design_template_music_test.php');

// parts.php, mail.php and merch.php in one scope, as the template file
// reads them. Read once per run.
function _test_mum_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/merch.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'catalog' => $part['catalog'],
		'merch' => $merch, 'gift_cards' => $gift_cards, 'releases' => $releases, 'money' => $money);
	return $out;
}

// Every node of a tree, depth first.
function _test_mum_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_mum_nodes($c, $out);
}

function test_music_merch_pages_need_the_shop()
{
	$d = _test_mum_load();
	pg_assert_same(array('pages', 'widgets', 'catalog'), array_keys($d['part']), 'the part returns pages, widgets and the catalog');
	pg_assert_same(array('merch', 'product', 'cart', 'checkout', 'order', 'email_order'), array_keys($d['pages']));
	pg_assert_same(_test_mu_shop_keys(), array_keys($d['pages']), 'the shop pages are the template\'s shop keys');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same('ecommerce', $p['requires'] ?? '', "$key needs the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	pg_assert_true(empty($d['pages']['email_order']['search']) && !empty($d['pages']['email_order']['noindex']), 'the receipt page is out of search');
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] === '') continue;
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a shop page");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('{{tab:email_order}}', $d['widgets']['checkout']['config']['order_receipt_email_page_id'], 'the checkout sends the receipt');
	pg_assert_same('{{tab:merch}}', $d['widgets']['product_detail']['config']['catalog_listing_page_id'], 'a product\'s breadcrumb starts at the merch page');
}

// The catalog: the merch, a signed vinyl of every release, then the gift
// cards; the template's SKUs, once each; the prices parts.php gives; merch
// posted, gift cards not.
function test_music_merch_catalog()
{
	$d = _test_mum_load();
	$c = $d['catalog'];
	pg_assert_same('pinegrap_music_catalog', $c['folder']);
	pg_assert_same(array('merch', 'gift_cards'), array_keys($c['groups']));
	foreach ($c['groups'] as $key => $g) pg_assert_same('root', $g['parent'], "$key sits under the top group");
	$skus = array();
	foreach ($c['groups'] as $g) {
		foreach ($g['products'] as $p) {
			pg_assert_true((bool)preg_match('/^MUSIC-[A-Z0-9-]+$/', $p['sku']), "{$p['sku']}: the template's SKU");
			pg_assert_false(isset($skus[$p['sku']]), "{$p['sku']}: once");
			$skus[$p['sku']] = $p;
			pg_assert_true(isset($p['price']['USD']) && $p['price']['USD'] > 0, "{$p['sku']}: a price");
			foreach (array('title', 'description') as $f) pg_assert_true(trim($p[$f]) !== '', "{$p['sku']}: $f");
		}
	}
	foreach ($d['merch'] as $key => $m) {
		pg_assert_true(isset($skus[$m['sku']]), "merch $key is sold");
		pg_assert_same($m['price'], $skus[$m['sku']]['price'], "merch $key: the price parts.php gives");
		pg_assert_false(isset($skus[$m['sku']]['shippable']), "merch $key is posted (shippable by default)");
	}
	foreach ($d['gift_cards'] as $key => $g) {
		pg_assert_true(isset($skus[$g['sku']]), "gift card $key is sold");
		pg_assert_same($g['price'], $skus[$g['sku']]['price'], "gift card $key: the price parts.php gives");
		pg_assert_same(false, $skus[$g['sku']]['shippable'], "gift card $key is sent by e-mail");
	}
	foreach (array_keys($d['releases']) as $key) {
		pg_assert_true(isset($skus['MUSIC-VINYL-' . strtoupper($key)]), "release $key is sold on vinyl");
	}
	$featured = 0;
	foreach ($c['groups']['merch']['products'] as $p) if (!empty($p['featured'])) $featured++;
	pg_assert_same(3, $featured, 'three featured merch products');
	pg_assert_true(count($c['details']) >= 2, 'the Details tab lines');
}

// The listings draw the merch and the gift cards of their group, the
// product's link and price from the catalog and Add to cart; the card's
// sample price is the one the cart will show.
function test_music_merch_listings()
{
	$d = _test_mum_load();
	$expect = array('merch_catalog' => array('merch', $d['merch']['shirt']), 'gift_card_catalog' => array('gift_cards', $d['gift_cards']['gift25']));
	foreach ($expect as $key => $e) {
		$w = $d['widgets'][$key];
		pg_assert_same('catalog_listing', $w['config']['regionType'], "$key: a catalog listing");
		pg_assert_same('{{product_group:' . $e[0] . '}}', $w['config']['product_group_id'], "$key lists its group");
		pg_assert_same('{{tab:product}}', $w['config']['detail_page_id'], "$key opens the product page");
		pg_assert_same('{{tab:cart}}', $w['config']['add_to_cart_next_page_id'], "$key adds to this shop's cart");
		$json = json_encode($w['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		foreach (array('__short_description', '__detail_url', '__price_formatted', 'catalog_add_to_cart') as $t) {
			pg_assert_contains('"' . $t . '"', $json, "$key: the card reads $t");
		}
		pg_assert_contains(call_user_func($d['money'], $e[1]['price']), $json, "$key: the sample price");
		pg_assert_contains($e[1]['title'], $json, "$key: the sample title");
	}
}

// The receipt shows the postage and the delivery address while the order
// has them, the billing address, and links the tour and the order.
function test_music_merch_receipt()
{
	$d = _test_mum_load();
	$json = json_encode($d['widgets']['email_order']['tree']);
	foreach (array('__order_no', '__order_date', '__item_short_description', '__item_qty', '__item_total', '__order_subtotal',
		'__order_shipping', '__order_total', '__shipping_address_html', '__billing_address_html') as $t) {
		pg_assert_contains('"' . $t . '"', $json, "the receipt prints $t");
	}
	foreach (array('has_shipping', 'has_shipping_cost', 'has_discount', 'has_tax') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the receipt shows a part while $flag");
	}
	pg_assert_contains('{{page:tour}}', $json, 'the receipt links the tour dates');
	pg_assert_contains('{{page:order}}?order_id=^^__order_id^^', $json, 'the receipt links the order');
	pg_assert_contains('"templateWidget":"email_order"', json_encode($d['pages']['email_order']['tree']), 'the receipt page draws the receipt');
}

function test_music_merch_links_and_classes()
{
	$d = _test_mum_load();
	$known = array_flip(_test_mu_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($trees), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mum_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_mum_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mu-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; merch reads "grup ürünleri", a show
// "konser", the tour "turne".
function test_music_merch_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/merch.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Mm]erch\b/', $key)) pg_assert_true(mb_stripos($tr, 'grup ürün', 0, 'UTF-8') !== false || mb_stripos($tr, 'grup-urun', 0, 'UTF-8') !== false, "'$key' says grup ürünleri");
		if (preg_match('/\bShows?\b/', $key)) pg_assert_true(mb_stripos($tr, 'konser', 0, 'UTF-8') !== false, "'$key' says konser");
		if (preg_match('/^Tour\b/', $key)) pg_assert_true(mb_stripos($tr, 'turne', 0, 'UTF-8') !== false, "'$key' says turne");
	}
}
