<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's shop
 * (includes/design_templates/pinegrap-gallery/shop.php): every product sits
 * in its range's group (the featured ones also in the Shop group) with its
 * own title and price, by an artist or for an exhibition of parts.php, the
 * groups' parents come first, every product ships, the shop sends a larger
 * work to its enquiry, every page and widget needs the shop, links name
 * pages of the finished template, ids and classes are the template's, and
 * every literal has its Turkish in the template's words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page and shop keys of the finished template live in the parts test.
require_once __DIR__ . '/design_template_gallery_test.php';

// parts.php, cards.php, mail.php and shop.php in one scope, as the
// template file reads them. Read once per run.
function _test_gls_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	include $dir . '/mail.php';
	$shop = include $dir . '/shop.php';
	$out = array('shop' => $shop, 'ranges' => $shop_ranges, 'items' => $shop_items, 'sku' => $shop_sku, 'money' => $money,
		'artists' => $artists, 'exhibitions' => $exhibitions);
	return $out;
}

// Every node of a tree, depth first.
function _test_gls_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gls_nodes($child, $out);
	}
}

// The three ranges of the plan: editions, small works and catalogues; an
// edition or a small work is by an artist of the gallery, a catalogue is
// for one of its exhibitions.
function test_gallery_shop_ranges_and_their_items()
{
	$d = _test_gls_load();
	pg_assert_same(array('editions', 'small_works', 'catalogues'), array_keys($d['ranges']), 'the ranges');
	pg_assert_same(array_keys($d['ranges']), array_keys($d['items']), 'items for every range');
	foreach ($d['items'] as $range => $list) {
		pg_assert_true(count($list) >= 3, "$range: three products or more");
		foreach ($list as $p) {
			if ($range === 'catalogues') {
				pg_assert_true(isset($d['exhibitions'][$p['exhibition']]), "$range: an exhibition of the gallery");
			} else {
				pg_assert_true(isset($d['artists'][$p['artist']]), "$range: an artist of the gallery");
			}
		}
	}
}

// Every product is in its range's group once, with its own title and
// price, and ships; a featured one also in the Shop group.
function test_gallery_shop_products_sit_in_their_range()
{
	$d = _test_gls_load();
	$groups = $d['shop']['catalog']['groups'];
	$skus = array();
	$featured = 0;
	foreach ($d['items'] as $key => $list) {
		pg_assert_same('gallery_shop', $groups[$key]['parent'], "$key: under the Shop group");
		pg_assert_same($d['ranges'][$key]['title'], $groups[$key]['title'], "$key: the range's title");
		foreach (array_values($list) as $n => $p) {
			$sku = call_user_func($d['sku'], $key, $n + 1);
			pg_assert_false(isset($skus[$sku]), "$sku is unique");
			$skus[$sku] = true;
			$found = array();
			foreach ($groups as $gkey => $g) {
				foreach ($g['products'] as $gp) {
					if ($gp['sku'] !== $sku) continue;
					$found[] = $gkey;
					pg_assert_same($p['title'], $gp['title'], "$sku title");
					pg_assert_same($p['price'], $gp['price'], "$sku price");
					pg_assert_false(isset($gp['shippable']) && $gp['shippable'] === false, "$sku ships");
					foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($gp['price'][$c]) && $gp['price'][$c] > 0, "$sku: a $c price");
				}
			}
			$want = array($key);
			if (!empty($p['featured'])) {
				$want[] = 'gallery_shop';
				$featured++;
			}
			sort($want);
			sort($found);
			pg_assert_same($want, $found, "$sku groups");
		}
	}
	pg_assert_true($featured >= 3, 'the Shop group shows some products first');
}

// A parent is listed before its groups, and every key is one the loader
// takes.
function test_gallery_shop_group_order_and_keys()
{
	$d = _test_gls_load();
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(1 + count($d['ranges']), count($groups), 'every group listed');
	pg_assert_same('root', $groups['gallery_shop']['parent'], 'the Shop group under the site\'s top group');
	$seen = array('root' => true);
	foreach ($groups as $key => $g) {
		pg_assert_true((bool)preg_match('/^[a-z0-9_-]+$/', $key), "$key: a key the loader takes");
		pg_assert_true(isset($seen[$g['parent']]), "$key: its parent comes first");
		$seen[$key] = true;
	}
	pg_assert_same('pinegrap_gallery_catalog', $d['shop']['catalog']['folder'], 'the catalog\'s folder');
	pg_assert_true(count($d['shop']['catalog']['details']) >= 2, 'the Details tab lines');
}

// The works themselves are sold through the gallery: the shop and product
// pages send a larger work to the works and its enquiry.
function test_gallery_shop_sends_works_to_the_enquiry()
{
	$d = _test_gls_load();
	$seen = 0;
	foreach ($d['shop']['pages'] as $p) {
		if (!in_array($p['key'], array('shop', 'product'), true)) continue;
		$seen++;
		$tree = json_encode($p['tree']);
		pg_assert_contains('{{page:works}}', $tree, $p['key'] . ': the works');
		pg_assert_contains('{{page:enquire}}', $tree, $p['key'] . ': the enquiry');
	}
	pg_assert_same(2, $seen, 'the shop and product pages');
}

// Every page and widget here needs the shop; the receipt's sample price is
// the shop's own figure and the receipt sits in the e-mail frame.
function test_gallery_shop_needs_the_shop()
{
	$d = _test_gls_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
	}
	$want = _test_gl_shop_keys();
	sort($keys);
	sort($want);
	pg_assert_same($want, $keys, 'the shop pages');
	foreach ($d['shop']['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] !== '') pg_assert_true(in_array($w['page'], $keys, true), "$key sits on a shop page");
	}
	pg_assert_true(isset($d['shop']['widgets']['cart_link']), 'the account bar\'s cart link');
	pg_assert_same('', $d['shop']['widgets']['cart_link']['page'], 'the cart link on no single page');
	$json = json_encode($d['shop']['widgets']['email_order']['tree']);
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['items']['editions'][0]['price'])), $json, 'sample price as the shop writes it');
	foreach ($d['shop']['pages'] as $p) {
		if ($p['key'] === 'email_order') pg_assert_contains('"templateShared":"email_header"', json_encode($p['tree']), 'the receipt in the e-mail frame');
	}
}

// The links name pages of the finished template, and a group address
// names a group of the catalog.
function test_gallery_shop_links_name_template_pages()
{
	$d = _test_gls_load();
	$known = array_flip(_test_gl_page_keys());
	$json = json_encode(array($d['shop']['pages'], $d['shop']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 6, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	preg_match_all('/\{\{product_group(?:_path)?:([a-z0-9_-]+)\}\}/', $json, $g);
	pg_assert_true(count($g[1]) > 0, 'group addresses found');
	foreach (array_unique($g[1]) as $key) pg_assert_true(isset($d['shop']['catalog']['groups'][$key]), "group $key is in the catalog");
}

// Ids carry gl-, classes are Bootstrap's or the template's, no faded text.
function test_gallery_shop_ids_and_classes()
{
	$d = _test_gls_load();
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_gls_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $w) {
		if (is_array($w['tree'] ?? null)) _test_gls_nodes($w['tree'], $nodes);
	}
	pg_assert_true(count($nodes) > 50, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pth)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		// A search section's id is its node id, so this covers them too.
		if ($id !== '') pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; an artist reads "sanatçı", an exhibition
// "sergi", an edition "baskı" or "edisyon", the gallery "galeri" (page
// addresses excepted).
function test_gallery_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 20, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bartists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sanatç', 0, 'UTF-8') !== false, "'$key' says sanatçı");
		if (preg_match('/\bexhibitions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'serg', 0, 'UTF-8') !== false, "'$key' says sergi");
		if (preg_match('/\beditions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'baskı', 0, 'UTF-8') !== false || mb_stripos($tr, 'edisyon', 0, 'UTF-8') !== false, "'$key' says baskı or edisyon");
		if (preg_match('/\bgallery\b/i', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
	}
}
