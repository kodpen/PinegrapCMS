<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Craft Brewery design template's shop and Beer Club
 * (includes/design_templates/pinegrap-brewery/shop.php): the pages and
 * widgets with their keys, every one needing the shop, the catalog's four
 * groups with a product per item of parts.php at its prices (beer and
 * merch shipped, gift cards and club terms not, the terms renewing the
 * membership by their days), the listings, the members' area in the club
 * folder, every page link naming a page of the finished template, the
 * template's own ids, classes and e-mail colours, no fixed date, and every
 * literal with its Turkish.
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
require_once(__DIR__ . '/design_template_brewery_test.php');

// parts.php, mail.php and shop.php in one scope, as the template file
// reads them. Read once per run.
function _test_bws_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/shop.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'catalog' => $part['catalog'],
		'packs' => $packs, 'gift_cards' => $gift_cards, 'club_terms' => $club_terms, 'club_perks' => $club_perks, 'brewery' => $brewery);
	return $out;
}

// Every node of a tree, depth first.
function _test_bws_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_bws_nodes($c, $out);
}

function test_brewery_shop_pages_and_widgets()
{
	$d = _test_bws_load();
	pg_assert_same(array('pages', 'widgets', 'catalog'), array_keys($d['part']), 'the part returns pages, widgets and the catalog');
	pg_assert_same(array('shop', 'club', 'product', 'cart', 'checkout', 'order', 'email_order', 'club_home', 'club_releases'), array_keys($d['pages']));
	$keys = array_keys($d['pages']);
	sort($keys);
	$plan = _test_bw_shop_keys();
	sort($plan);
	pg_assert_same($plan, $keys, 'the shop pages of the plan');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same('ecommerce', $p['requires'] ?? '', "$key needs the shop");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach (array('club_home', 'club_releases') as $key) {
		pg_assert_same('club', $d['pages'][$key]['folder'], "only members read $key");
		pg_assert_false($d['pages'][$key]['search'], "$key is out of the search");
		pg_assert_false($d['pages'][$key]['sitemap'], "$key is out of the sitemap");
	}
	foreach (array('shop', 'club', 'product') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['email_order']['folder'], 'the receipt page is private');
	pg_assert_true(!empty($d['pages']['email_order']['noindex']), 'the receipt page is not indexed');
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] === '') continue;
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('', $d['widgets']['cart_link']['page'], 'the cart link sits in the account bar');
	pg_assert_same('{{tab:email_order}}', $d['widgets']['checkout']['config']['order_receipt_email_page_id'], 'the checkout sends the receipt');
	pg_assert_same('{{tab:shop}}', $d['widgets']['product_detail']['config']['catalog_listing_page_id'], 'the product page leads back to the shop');
}

// The catalog: four groups, a product per item of parts.php with its
// prices; beer and merch shipped, gift cards and club terms not; the terms
// renew the membership by their days.
function test_brewery_shop_catalog()
{
	$d = _test_bws_load();
	$c = $d['catalog'];
	pg_assert_same('pinegrap_brewery_catalog', $c['folder'], 'the catalog\'s picture folder');
	pg_assert_same(array('packs', 'merch', 'gift_cards', 'club'), array_keys($c['groups']), 'the groups');
	$items = array(
		'packs'      => array_diff_key($d['packs'], array('glass' => 1, 'shirt' => 1)),
		'merch'      => array_intersect_key($d['packs'], array('glass' => 1, 'shirt' => 1)),
		'gift_cards' => $d['gift_cards'],
		'club'       => $d['club_terms'],
	);
	pg_assert_same(count($d['packs']), count($items['packs']) + count($items['merch']), 'every item of the shop is in a group');
	pg_assert_same(3, count($items['packs']), 'three beer packs');
	$skus = array();
	foreach ($items as $group => $list) {
		$g = $c['groups'][$group];
		pg_assert_same('root', $g['parent'], "$group is under the site's top group");
		pg_assert_true(trim($g['title']) !== '' && trim($g['description']) !== '', "$group has a title and a description");
		pg_assert_same(count($list), count($g['products']), "$group: a product per item");
		foreach (array_values($list) as $n => $item) {
			$p = $g['products'][$n];
			pg_assert_same($item['sku'], $p['sku'], "$group product $n: its SKU");
			pg_assert_same(0, strpos($p['sku'], 'BREWERY-'), "$group product $n: the SKU names the template");
			pg_assert_same($item['price'], $p['price'], "$group product $n: the page's prices");
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) pg_assert_true(isset($p['price'][$cur]), "$group product $n: a $cur price");
			pg_assert_same($n + 1, $p['sort_order'], "$group product $n: in order");
			$skus[] = $p['sku'];
			if ($group === 'packs' || $group === 'merch') {
				pg_assert_false(isset($p['shippable']), "$group product $n is posted");
			} else {
				pg_assert_same(false, $p['shippable'], "$group product $n is not shipped");
			}
			if ($group === 'club') {
				pg_assert_same($item['days'], $p['membership_days'], "club term $n renews the membership by its days");
			} else {
				pg_assert_false(isset($p['membership_days']), "$group product $n is no membership");
			}
		}
	}
	pg_assert_same(count($skus), count(array_unique($skus)), 'every SKU once');
	pg_assert_true(in_array($d['brewery']['adults'], $c['details'], true), 'every product page says the beers are for adults');
	$listings = array('pack_catalog' => array('shop', 'packs'), 'merch_catalog' => array('shop', 'merch'),
		'gift_card_catalog' => array('shop', 'gift_cards'), 'club_catalog' => array('club', 'club'));
	foreach ($listings as $key => $l) {
		$cfg = $d['widgets'][$key]['config'];
		pg_assert_same($l[0], $d['widgets'][$key]['page'], "$key sits on the {$l[0]} page");
		pg_assert_same('catalog_listing', $cfg['regionType'], "$key is a listing");
		pg_assert_same('{{product_group:' . $l[1] . '}}', $cfg['product_group_id'], "$key lists the {$l[1]} group");
		pg_assert_same('{{tab:product}}', $cfg['detail_page_id'], "$key opens the product page");
		pg_assert_same('{{tab:cart}}', $cfg['add_to_cart_next_page_id'], "$key adds to this shop's cart");
		pg_assert_true(trim((string)$cfg['empty_message']) !== '', "$key says something when empty");
	}
}

// The club page shows every perk and leads to the members' area; the shop
// and the receipt say the beers are for adults.
function test_brewery_shop_club_and_notes()
{
	$d = _test_bws_load();
	$club = json_encode($d['pages']['club']['tree']);
	foreach ($d['club_perks'] as $n => $perk) pg_assert_contains(json_encode($perk), $club, "club perk $n is shown");
	pg_assert_contains('{{page:club_home}}', $club, 'the club page leads to the members\' area');
	pg_assert_contains('{{page:club_releases}}', json_encode($d['pages']['club_home']['tree']), 'the members\' home leads to the club releases');
	pg_assert_contains('{{page:club}}', json_encode($d['pages']['shop']['tree']), 'the shop leads to the club');
	$adults = json_encode($d['brewery']['adults']);
	pg_assert_contains($adults, json_encode($d['pages']['shop']['tree']), 'the shop says the beers are for adults');
	pg_assert_contains($adults, json_encode($d['widgets']['email_order']['tree']), 'the receipt says the beers are for adults');
}

function test_brewery_shop_links_ids_and_classes()
{
	$d = _test_bws_load();
	$known = array_flip(array_merge(_test_bw_page_keys(), array('email_order')));
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_bws_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) if (is_array($w['tree'])) _test_bws_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psp|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "class $c fades text");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bw-') === 0, "id $id is the template's");
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/shop.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $src, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#92400e', '#fde68a', '#fffbeb', '#ffffff', '#1f2937', '#4b5563'), true), "e-mail colour $hex is the template's");
	}
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'no month');
}

// Every literal has its Turkish; the club reads "Bira Kulübü", a beer
// "bira", the taproom "tadım salonu".
function test_brewery_shop_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/shop.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (strpos($key, 'Beer Club') !== false) pg_assert_true(mb_stripos($tr, 'Bira Kulüb', 0, 'UTF-8') !== false, "'$key' says Bira Kulübü");
		if (preg_match('/^Beer\b/', $key)) pg_assert_true(mb_stripos($tr, 'bira', 0, 'UTF-8') !== false, "'$key' says bira");
		if (preg_match('/\b[Tt]aproom\b/', $key)) pg_assert_true(mb_stripos($tr, 'tadım salonu', 0, 'UTF-8') !== false, "'$key' says tadım salonu");
	}
}
