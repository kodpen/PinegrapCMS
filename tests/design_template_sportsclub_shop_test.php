<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sports Club design template's shop and members' page
 * (includes/design_templates/pinegrap-sportsclub/shop.php): the pages and
 * widgets of the plan, the catalog (kit shipped, season tickets not
 * shipped, memberships renewing the buyer's membership), the listings
 * reading their own group, every shop page and widget needing the shop,
 * the members' page in the membership folder linking no shop page, links
 * that name template pages and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the tree helpers of the
// collections test.
require_once __DIR__ . '/design_template_sportsclub_test.php';
require_once __DIR__ . '/design_template_sportsclub_collections_test.php';

// parts.php, mail.php and shop.php in one scope, as the template file reads
// them. Read once per run.
function _test_sps_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/shop.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'catalog' => $part['catalog'],
		'kit' => $kit, 'season_tickets' => $season_tickets, 'memberships' => $memberships);
	return $out;
}

function test_sportsclub_shop_pages_and_widgets()
{
	$d = _test_sps_load();
	pg_assert_same(array('pages', 'widgets', 'catalog'), array_keys($d['part']), 'the part returns pages, widgets and the catalog');
	pg_assert_same(array('kit_shop', 'season_tickets', 'membership', 'product', 'cart', 'checkout', 'order', 'email_order', 'members'),
		array_keys($d['pages']));
	pg_assert_same(_test_sp_shop_keys(), array_values(array_diff(array_keys($d['pages']), array('members'))), 'the shop pages of the plan');
	foreach ($d['pages'] as $key => $p) {
		if ($key === 'members') {
			pg_assert_false(isset($p['requires']), 'the members\' page stands without the shop');
			pg_assert_same('members', $p['folder'], 'only members read the members\' page');
			pg_assert_false($p['search'], 'the members\' page is out of the search');
		} else {
			pg_assert_same('ecommerce', $p['requires'] ?? '', "$key needs the shop");
		}
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	pg_assert_same('private', $d['pages']['email_order']['folder'], 'the receipt page is private');
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] === '') continue;
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('{{tab:email_order}}', $d['widgets']['checkout']['config']['order_receipt_email_page_id'], 'the checkout sends the receipt');
}

// The catalog: a group per kind, a product per item of parts.php with its
// prices; kit shipped, tickets and memberships not; memberships renew the
// buyer's membership by their days.
function test_sportsclub_shop_catalog()
{
	$d = _test_sps_load();
	$c = $d['catalog'];
	pg_assert_same(array('kit', 'season_tickets', 'memberships'), array_keys($c['groups']), 'the groups');
	$want = array('kit' => 'kit', 'season_tickets' => 'season_tickets', 'memberships' => 'memberships');
	$skus = array();
	foreach ($want as $group => $data) {
		$g = $c['groups'][$group];
		pg_assert_same('root', $g['parent'], "$group is under the site's top group");
		pg_assert_same(count($d[$data]), count($g['products']), "$group: a product per item");
		foreach (array_values($d[$data]) as $n => $item) {
			$p = $g['products'][$n];
			pg_assert_same($item['sku'], $p['sku'], "$group product $n: its SKU");
			pg_assert_same($item['price'], $p['price'], "$group product $n: the page's prices");
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) pg_assert_true(isset($p['price'][$cur]), "$group product $n: a $cur price");
			$skus[] = $p['sku'];
			if ($group === 'kit') {
				pg_assert_false(isset($p['shippable']) || isset($p['membership_days']), "kit product $n is shipped");
			} else {
				pg_assert_same(false, $p['shippable'], "$group product $n is not shipped");
			}
			if ($group === 'memberships') {
				pg_assert_same($item['days'], $p['membership_days'], "membership $n renews the membership");
			} else {
				pg_assert_false(isset($p['membership_days']), "$group product $n is no membership");
			}
		}
	}
	pg_assert_same(count($skus), count(array_unique($skus)), 'every SKU once');
	$listings = array('kit_catalog' => 'kit', 'season_ticket_catalog' => 'season_tickets', 'membership_catalog' => 'memberships');
	foreach ($listings as $key => $group) {
		$cfg = $d['widgets'][$key]['config'];
		pg_assert_same('catalog_listing', $cfg['regionType'], "$key is a listing");
		pg_assert_same('{{product_group:' . $group . '}}', $cfg['product_group_id'], "$key lists the $group group");
		pg_assert_same('{{tab:product}}', $cfg['detail_page_id'], "$key opens the product page");
		pg_assert_same('{{tab:cart}}', $cfg['add_to_cart_next_page_id'], "$key adds to this shop's cart");
	}
}

// Links name template pages; the members' page links no shop page; ids and
// classes are the template's; every literal has its Turkish.
function test_sportsclub_shop_links_and_words()
{
	$d = _test_sps_load();
	$known = array_flip(array_merge(_test_sp_page_keys(), array('email_order')));
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$members = json_encode($d['pages']['members']['tree']);
	foreach (_test_sp_shop_keys() as $key) {
		pg_assert_false(strpos($members, '{{page:' . $key . '}}') !== false, "the members' page does not link $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_spc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) if (is_array($w['tree'])) _test_spc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pmu|pmz|psh|pcn|ptv|pwd|pph|psc|pre|pev|pdp|plx|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'sp-') === 0, "id $id is the template's");
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/shop.php');
	$map = pg_test_language_map('tr');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bseason tickets?\b/i', str_replace('-', ' ', $key))) pg_assert_true(mb_stripos($map[$key], 'kombine', 0, 'UTF-8') !== false, "'$key' says kombine");
		if (preg_match('/\bmemberships?\b/i', str_replace('-', ' ', $key))) {
			pg_assert_true(mb_stripos($map[$key], 'üyel', 0, 'UTF-8') !== false || mb_stripos($map[$key], 'uyel', 0, 'UTF-8') !== false, "'$key' says üyelik");
		}
	}
}
