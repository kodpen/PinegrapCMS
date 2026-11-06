<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel Agency design template's shop
 * (includes/design_templates/pinegrap-travel/shop.php): the pages are the
 * shop's keys and each needs the shop, the catalog holds a deposit per
 * sample tour and the gift cards at parts.php's prices, none shippable,
 * the listing and the shop pages point at each other, the receipt leads to
 * the traveller's bookings, ids and classes are the template's own, and
 * every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, and the node reader of the
// bookings' tests.
require_once(__DIR__ . '/design_template_travel_test.php');
require_once(__DIR__ . '/design_template_travel_bookings_test.php');

// The shop as the template file reads it: parts.php, mail.php, then
// shop.php in the same scope. Read once per run.
function _test_tvs_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/shop.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'catalog' => $part['catalog'],
		'tours' => $tours, 'gift_cards' => $gift_cards, 'deposit' => $deposit);
	return $out;
}

function test_travel_shop_pages_need_the_shop()
{
	$d = _test_tvs_load();
	pg_assert_same(_test_tv_shop_keys(), array_keys($d['pages']), 'the shop\'s pages');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same('ecommerce', $p['requires'] ?? '', "$key needs the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] === '') continue;
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a shop page");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_true(!empty($d['pages']['email_order']['noindex']) && $d['pages']['email_order']['search'] === false, 'the receipt is out of the search');
	pg_assert_same('{{tab:email_order}}', $d['widgets']['checkout']['config']['order_receipt_email_page_id']);
	pg_assert_same('{{tab:packages}}', $d['widgets']['product_detail']['config']['catalog_listing_page_id']);
	pg_assert_same('{{product_group:packages}}', $d['widgets']['package_catalog']['config']['product_group_id']);
	pg_assert_contains('{{page:my_bookings}}', json_encode($d['widgets']['email_order']['tree']), 'the receipt leads to the bookings');
}

// One group: a deposit per sample tour at the deposit's prices, then the
// gift cards at theirs; every SKU the template's own and once, nothing
// shipped.
function test_travel_shop_catalog()
{
	$d = _test_tvs_load();
	$groups = $d['catalog']['groups'];
	pg_assert_same(array('packages'), array_keys($groups), 'one group');
	pg_assert_same('root', $groups['packages']['parent'], 'under the site\'s top group');
	$want = array();
	foreach ($d['tours'] as $key => $t) $want['TRAVEL-DEPOSIT-' . strtoupper($key)] = $d['deposit'];
	foreach ($d['gift_cards'] as $g) $want[$g['sku']] = $g['price'];
	$got = array();
	foreach ($groups['packages']['products'] as $p) {
		pg_assert_false(isset($got[$p['sku']]), "{$p['sku']} once");
		pg_assert_true(strpos($p['sku'], 'TRAVEL-') === 0, "{$p['sku']} is the template's");
		pg_assert_false($p['shippable'], "{$p['sku']} is not shipped");
		pg_assert_true(trim($p['title']) !== '' && trim($p['description']) !== '', "{$p['sku']}: name and words");
		$got[$p['sku']] = $p['price'];
	}
	pg_assert_same($want, $got, 'the products and their prices');
	pg_assert_true(count($d['catalog']['details']) >= 2, 'the details tab');
}

function test_travel_shop_ids_and_classes()
{
	$d = _test_tvs_load();
	$known = array_flip(_test_tv_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($trees), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($trees as $t) _test_tvb_nodes(is_array($t) && isset($t['tree']) ? $t['tree'] : $t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'tv-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a tour reads "tur", a deposit "kapora".
function test_travel_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Tt]ours?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		if (preg_match('/\b[Dd]eposits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
	}
}
