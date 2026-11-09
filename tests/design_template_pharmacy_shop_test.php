<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pharmacy design template's shop
 * (includes/design_templates/pinegrap-pharmacy/shop.php): every product
 * sits in its range's group (the featured ones also in Health Products)
 * with its own title and price, the groups' parents come first, nothing
 * sold is a prescription medicine and the pages send a prescription to the
 * request, every page and widget needs the shop, links name pages of the
 * finished template, ids and classes are the template's, and every literal
 * has its Turkish in the template's words.
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
require_once __DIR__ . '/design_template_pharmacy_test.php';

// parts.php, mail.php and shop.php in one scope, as the template file reads
// them. Read once per run.
function _test_rxs_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$shop = include $dir . '/shop.php';
	$out = array('shop' => $shop, 'ranges' => $ranges, 'products' => $health_products, 'sku' => $product_sku, 'money' => $money);
	return $out;
}

// Every node of a tree, depth first.
function _test_rxs_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rxs_nodes($child, $out);
	}
}

// Every product is in its range's group once, with its own title and
// price; a featured one also in Health Products. Every range has products.
function test_pharmacy_shop_products_sit_in_their_range()
{
	$d = _test_rxs_load();
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_same(array_keys($d['ranges']), array_keys($d['products']), 'a product list for every range');
	$skus = array();
	foreach ($d['products'] as $key => $list) {
		pg_assert_same('health', $groups[$key]['parent'], "$key: under Health Products");
		pg_assert_same($d['ranges'][$key]['title'], $groups[$key]['title'], "$key: the range's title");
		pg_assert_true(count($list) >= 3, "$key: three products or more");
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
					foreach (array('USD', 'EUR', 'GBP', 'TRY') as $c) pg_assert_true(isset($gp['price'][$c]), "$sku: a $c price");
				}
			}
			$want = array($key);
			if (!empty($p['featured'])) $want[] = 'health';
			sort($want);
			sort($found);
			pg_assert_same($want, $found, "$sku groups");
		}
	}
}

// A parent is listed before its groups, and every key is one the loader
// takes.
function test_pharmacy_shop_group_order_and_keys()
{
	$d = _test_rxs_load();
	$groups = $d['shop']['catalog']['groups'];
	pg_assert_false(isset($d['ranges']['health']), 'health is no range key');
	pg_assert_same(1 + count($d['ranges']), count($groups), 'every group listed');
	$seen = array('root' => true);
	foreach ($groups as $key => $g) {
		pg_assert_true((bool)preg_match('/^[a-z0-9_-]+$/', $key), "$key: a key the loader takes");
		pg_assert_true(isset($seen[$g['parent']]), "$key: its parent comes first");
		$seen[$key] = true;
	}
	pg_assert_same('pinegrap_pharmacy_catalog', $d['shop']['catalog']['folder'], 'the catalog\'s folder');
}

// No prescription medicine is sold: the shop and product pages send a
// prescription to the request and a question to the pharmacist.
function test_pharmacy_shop_sends_prescriptions_to_the_request()
{
	$d = _test_rxs_load();
	$json = json_encode($d['shop']['catalog'], JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/prescription medicine/i', json_encode($d['products'])), 'no product is prescription medicine');
	pg_assert_contains(lang('Never prescription medicine.'), $json, 'the group says so');
	foreach ($d['shop']['pages'] as $p) {
		if (!in_array($p['key'], array('shop', 'product'), true)) continue;
		$tree = json_encode($p['tree']);
		pg_assert_contains('{{page:prescription}}', $tree, $p['key'] . ': sends a prescription to the request');
		pg_assert_contains('{{page:ask}}', $tree, $p['key'] . ': a question to the pharmacist');
		pg_assert_contains(json_encode(lang('Prescription medicine is never sold here')), $tree, $p['key'] . ': says so');
	}
}

// Every page and widget here needs the shop; the receipt's sample price is
// the shop's own figure.
function test_pharmacy_shop_needs_the_shop()
{
	$d = _test_rxs_load();
	$keys = array();
	foreach ($d['shop']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('ecommerce', $p['requires'] ?? '', $p['key'] . ' needs the shop');
	}
	$want = _test_rx_shop_keys();
	sort($keys);
	sort($want);
	pg_assert_same($want, $keys, 'the shop pages');
	foreach ($d['shop']['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] !== '') pg_assert_true(in_array($w['page'], $keys, true), "$key sits on a shop page");
	}
	$json = json_encode($d['shop']['widgets']['email_order']['tree']);
	pg_assert_contains(json_encode(call_user_func($d['money'], $d['products']['vitamins'][0]['price'])), $json, 'sample price as the shop writes it');
	pg_assert_contains('"templateShared":"email_header"', json_encode($d['shop']['pages'][count($d['shop']['pages']) - 1]['tree']), 'the receipt in the e-mail frame');
}

// The links name pages of the finished template, and a group address
// names a group of the catalog.
function test_pharmacy_shop_links_name_template_pages()
{
	$d = _test_rxs_load();
	$known = array_flip(_test_rx_page_keys());
	$json = json_encode(array($d['shop']['pages'], $d['shop']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 6, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	preg_match_all('/\{\{product_group(?:_path)?:([a-z0-9_-]+)\}\}/', $json, $g);
	pg_assert_true(count($g[1]) > 0, 'group addresses found');
	foreach (array_unique($g[1]) as $key) pg_assert_true(isset($d['shop']['catalog']['groups'][$key]), "group $key is in the catalog");
}

// Ids carry rx-, classes are Bootstrap's or the template's, no faded text.
function test_pharmacy_shop_ids_and_classes()
{
	$d = _test_rxs_load();
	$nodes = array();
	foreach ($d['shop']['pages'] as $p) _test_rxs_nodes($p['tree'], $nodes);
	foreach ($d['shop']['widgets'] as $w) {
		if (is_array($w['tree'] ?? null)) _test_rxs_nodes($w['tree'], $nodes);
	}
	pg_assert_true(count($nodes) > 50, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai|pvt)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		// A search section's id is its node id, so this covers them too.
		if ($id !== '') pg_assert_true(strpos($id, 'rx-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a pharmacist reads "eczacı", a pharmacy
// "eczane", a prescription "reçete" (page addresses excepted).
function test_pharmacy_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 20, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bPharmacists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczacı', 0, 'UTF-8') !== false, "'$key' says eczacı");
		elseif (preg_match('/\bPharmac(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczane', 0, 'UTF-8') !== false, "'$key' says eczane");
		if (preg_match('/\bPrescriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'reçete', 0, 'UTF-8') !== false, "'$key' says reçete");
	}
}
