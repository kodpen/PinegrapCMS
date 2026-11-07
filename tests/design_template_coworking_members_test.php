<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Coworking design template's shop and members' area
 * (includes/design_templates/pinegrap-coworking/members.php): the pages in
 * their folders, only the shop pages and widgets needing the shop, the
 * catalog a product per plan with the plan's prices and days (monthly
 * plans in Memberships, the day pass in Day Passes), the listings reading
 * their groups, the members' pages in the members folder, links to
 * template pages, the template's ids and classes, and every literal in
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

// parts.php, mail.php and the members part in one scope, as the template
// file reads them. Read once per run.
function _test_cwm_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/mail.php';
	$member_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/members.php';
	$out = array('part' => $member_part, 'plans' => $plans, 'tour_choices' => $tour_choices,
		'shared' => json_encode(array($header(), $footer(), $tour_band(), $notice())));
	return $out;
}

// Every node of a tree, depth first.
function _test_cwm_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cwm_nodes($child, $out);
	}
}

function _test_cwm_pages()
{
	$pages = array();
	foreach (_test_cwm_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_coworking_members_pages_and_folders()
{
	$part = _test_cwm_load()['part'];
	pg_assert_same(array('pages', 'widgets', 'catalog'), array_keys($part), 'the part returns pages, widgets and the catalog');
	$folders = array('memberships' => 'public', 'product' => 'public', 'cart' => 'public', 'checkout' => 'public', 'order' => 'public',
		'email_order' => 'private', 'members' => 'members', 'house_guide' => 'members');
	$pages = _test_cwm_pages();
	pg_assert_same(array_keys($folders), array_keys($pages), 'the pages');
	foreach ($folders as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
		$shop = $folder !== 'members';
		pg_assert_same($shop, ($pages[$key]['requires'] ?? '') === 'ecommerce', "$key: " . ($shop ? 'needs' : 'stands without') . ' the shop');
	}
	foreach (array('members', 'house_guide') as $key) pg_assert_false($pages[$key]['sitemap'], "$key: not in the sitemap");
	$names = array();
	foreach ($part['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] !== '') pg_assert_true(isset($pages[$w['page']]), "$key sits on a page of the part");
		$name = $w['page'] . '/' . $w['slug'];
		pg_assert_false(isset($names[$name]), "$key: its widget name is its own");
		$names[$name] = true;
	}
}

// A product per plan: its SKU, prices and days, not shipped; the day pass
// in Day Passes, the monthly plans in Memberships.
function test_coworking_members_catalog()
{
	$d = _test_cwm_load();
	$groups = $d['part']['catalog']['groups'];
	pg_assert_same(array('memberships', 'day_passes'), array_keys($groups), 'two groups');
	$by_sku = array();
	foreach ($groups as $gkey => $g) {
		pg_assert_same('root', $g['parent'], "$gkey: under the top group");
		pg_assert_true(count($g['products']) >= 1, "$gkey: has products");
		foreach ($g['products'] as $pr) $by_sku[$pr['sku']] = array($gkey, $pr);
	}
	pg_assert_same(count($d['plans']), count($by_sku), 'a product per plan');
	foreach ($d['plans'] as $key => $p) {
		list($gkey, $pr) = $by_sku[$p['sku']];
		pg_assert_same($p['days'] === 1 ? 'day_passes' : 'memberships', $gkey, "$key: in its group");
		pg_assert_same($p['price'], $pr['price'], "$key: the plan's prices");
		pg_assert_same($p['days'], $pr['membership_days'], "$key: renews the membership by its days");
		pg_assert_false($pr['shippable'], "$key: not shipped");
		pg_assert_false(isset($pr['private_folder']), "$key: grants no folder of its own");
	}
	pg_assert_same(1, $d['plans']['day']['days'], 'the day pass is one day');
	$w = $d['part']['widgets'];
	pg_assert_same('{{product_group:memberships}}', $w['membership_catalog']['config']['product_group_id'], 'monthly plans');
	pg_assert_same('{{product_group:day_passes}}', $w['day_pass_catalog']['config']['product_group_id'], 'day passes');
	pg_assert_same('{{tab:memberships}}', $w['product_detail']['config']['catalog_listing_page_id'], 'back to the memberships');
	pg_assert_same('{{tab:email_order}}', $w['checkout']['config']['order_receipt_email_page_id'], 'the receipt');
}

// Links name template pages; the shared parts never link a shop page; the
// tour link sends an answer of the tour's Plan.
function test_coworking_members_links()
{
	$d = _test_cwm_load();
	$json = json_encode(array(_test_cwm_pages(), $d['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(array('home', 'plans', 'spaces', 'book_online', 'tour', 'events', 'my_signups', 'privacy', 'contact', 'my_account',
		'memberships', 'product', 'cart', 'checkout', 'order', 'email_order', 'members', 'house_guide'));
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (array('memberships', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($d['shared'], '{{page:' . $key . '}}') !== false, "the shared parts do not link $key");
	}
	pg_assert_contains('{{page:house_guide}}', json_encode(_test_cwm_pages()['members']['tree']), 'the members\' home links the house guide');
	preg_match('/\{\{page:tour\}\}\?plan=([^"]+)/', str_replace('\\/', '/', json_encode(_test_cwm_pages()['memberships'])), $t);
	pg_assert_true(in_array(rawurldecode(json_decode('"' . $t[1] . '"')), $d['tour_choices'], true), 'the tour link sends a Plan answer');
}

// Ids carry cw-, classes are Bootstrap's or the template's, no faded text.
function test_coworking_members_ids_and_classes()
{
	$nodes = array();
	foreach (_test_cwm_pages() as $p) _test_cwm_nodes($p['tree'], $nodes);
	foreach (_test_cwm_load()['part']['widgets'] as $w) _test_cwm_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pre|pev|pf|pdp|pm|pma|pmz|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'cw-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a membership reads "üyelik", a day pass
// "günlük bilet".
function test_coworking_members_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/members.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bMemberships?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'üyelik', 0, 'UTF-8') !== false, "'$key' says üyelik");
		if (preg_match('/\bday pass(es)?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'günlük bilet', 0, 'UTF-8') !== false, "'$key' says günlük bilet");
	}
}
