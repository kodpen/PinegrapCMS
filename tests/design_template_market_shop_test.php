<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marketplace design template's shop
 * (includes/design_templates/pinegrap-market/shop.php): every product sits
 * in its category's group and its maker's group with the figures of
 * parts.php, the groups' parents come before them and their keys never
 * clash, every page and widget of the file needs the shop, its links name
 * pages of the finished template, and every literal has its Turkish.
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

// parts.php, mail.php and shop.php in one scope, as the template file reads
// them. Until mail.php is in the folder the e-mail builders are stood in
// with the same signatures, so the shop's own trees can be read.
function _test_market_shop_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	include $dir . '/parts.php';
	if (is_file($dir . '/mail.php')) {
		include $dir . '/mail.php';
	} else {
		$mail_style = function ($css) use ($attr) {
			return array('_attrs' => array($attr('style', $css)));
		};
		$mail_page = function ($children) use ($root) {
			return $root($children);
		};
		$mail_heading = function ($text) use ($heading) {
			return $heading('h1', $text, '');
		};
		$mail_para = function ($text, $class = 'mb-3', $style = '') use ($para) {
			return $para($text, $class);
		};
		$mail_button = function ($text, $href, $extra = array()) use ($link) {
			return $link($text, $href, 'btn btn-primary', array(), $extra);
		};
		$mail_box = function ($children, $name) use ($el) {
			return $el('div', '', $name, $children);
		};
		$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);
	}
	$shop = include $dir . '/shop.php';
	$out = array(
		'shop'       => $shop,
		'categories' => $categories,
		'sellers'    => $sellers,
		'products'   => $market_products,
		'sku'        => $product_sku,
		'money'      => $money,
	);
	return $out;
}

// Every product of the shop is in its category and in its maker's shop,
// once each, with its own title and price; the featured ones also in the
// Marketplace group.
function test_market_shop_products_sit_in_category_and_maker()
{
	$d = _test_market_shop_load();
	$groups = $d['shop']['catalog']['groups'];
	foreach ($d['sellers'] as $key => $s) {
		pg_assert_true(isset($groups[$key]), "$key: a group of its own");
		pg_assert_same('shops', $groups[$key]['parent'], "$key: under Shops");
		pg_assert_same($s['name'], $groups[$key]['title'], "$key: named after the shop");
		foreach (array_values($d['products'][$key]) as $n => $p) {
			$sku = call_user_func($d['sku'], $key, $n + 1);
			$found = array();
			foreach ($groups as $gkey => $g) {
				foreach ($g['products'] as $gp) {
					if ($gp['sku'] !== $sku) continue;
					$found[] = $gkey;
					pg_assert_same($p['title'], $gp['title'], "$sku title");
					pg_assert_same($p['price'], $gp['price'], "$sku price");
					pg_assert_contains($s['maker'], $gp['description'], "$sku names its maker");
				}
			}
			$want = array($s['category'], $key);
			if (!empty($p['featured'])) $want[] = 'market';
			sort($want);
			sort($found);
			pg_assert_same($want, $found, "$sku groups");
		}
	}
	foreach ($d['categories'] as $key => $c) {
		pg_assert_same('market', $groups[$key]['parent'], "$key: under Marketplace");
		pg_assert_same($c['title'], $groups[$key]['title'], "$key: its title");
		pg_assert_true(count($groups[$key]['products']) >= 3, "$key: its makers' products");
	}
}

// A parent is listed before its groups (the loader makes them in order)
// and no maker's key is a category's or a fixed group's.
function test_market_shop_group_order_and_keys()
{
	$d = _test_market_shop_load();
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(array(), array_intersect_key($d['sellers'], $d['categories']), 'maker and category keys differ');
	foreach (array('market', 'shops') as $fixed) {
		pg_assert_false(isset($d['sellers'][$fixed]) || isset($d['categories'][$fixed]), "$fixed is no maker or category key");
	}
	pg_assert_same(1 + count($d['categories']) + 1 + count($d['sellers']), count($groups), 'every group listed');
	$seen = array('root' => true);
	foreach ($groups as $key => $g) {
		pg_assert_true((bool)preg_match('/^[a-z0-9_-]+$/', $key), "$key: a key the loader takes");
		pg_assert_true(isset($seen[$g['parent']]), "$key: its parent comes first");
		$seen[$key] = true;
	}
}

// Every page and widget here needs the shop; the cards' sample price is
// the shop's own figure.
function test_market_shop_needs_the_shop()
{
	$d = _test_market_shop_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
	}
	sort($keys);
	pg_assert_same(array('cart', 'checkout', 'email_order', 'order', 'product', 'shop'), $keys, 'the shop pages');
	foreach ($d['shop']['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] !== '') pg_assert_true(in_array($w['page'], $keys, true), "$key sits on a shop page");
	}
	$json = json_encode($d['shop']['widgets']['email_order']['tree']);
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['products']['clay'][0]['price'])), $json, 'sample price as the shop writes it');
}

// The links name pages of the finished template, and a group address
// names a group of the catalog.
function test_market_shop_links_name_template_pages()
{
	$d = _test_market_shop_load();
	$known = array_flip(array(
		'home', 'categories', 'how_it_works', 'sell', 'about', 'faq', 'gift_guide',
		'shop', 'product', 'cart', 'checkout', 'order', 'email_order',
		'sellers', 'seller', 'seller_new', 'apply', 'help', 'help_view', 'my_help',
		'contact', 'search', 'my_account', 'privacy',
	));
	$json = json_encode(array($d['shop']['pages'], $d['shop']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 6, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	preg_match_all('/\{\{product_group(?:_path)?:([a-z0-9_-]+)\}\}/', $json, $g);
	pg_assert_true(count($g[1]) > 0, 'group addresses found');
	foreach (array_unique($g[1]) as $key) pg_assert_true(isset($d['shop']['catalog']['groups'][$key]), "group $key is in the catalog");
}

function test_market_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 20, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bmakers?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'üretici', 0, 'UTF-8') !== false, "'$key' says üretici");
		}
	}
}
