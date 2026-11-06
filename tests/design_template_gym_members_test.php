<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Gym design template's shop and members' area
 * (includes/design_templates/pinegrap-gym/members.php): the pages in their
 * folders, only the shop pages and widgets needing the shop, the catalog
 * a membership product per plan with the plan's prices and days, the
 * listing reading the Memberships group, the members' pages in the
 * members folder, links to template pages, the template's ids and
 * classes, and every literal in Turkish.
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
function _test_gym_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/mail.php';
	$member_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/members.php';
	$out = array('part' => $member_part, 'plans' => $plans, 'shared' => json_encode(array($header(), $footer(), $join_band())));
	return $out;
}

// Every node of a tree, depth first.
function _test_gym_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_gym_nodes($child, $out);
	}
}

function _test_gym_pages()
{
	$pages = array();
	foreach (_test_gym_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_gym_members_pages_and_folders()
{
	$part = _test_gym_load()['part'];
	pg_assert_same(array('pages', 'widgets', 'catalog'), array_keys($part), 'the part returns pages, widgets and the catalog');
	$folders = array('memberships' => 'public', 'product' => 'public', 'cart' => 'public', 'checkout' => 'public', 'order' => 'public',
		'email_order' => 'private', 'members' => 'members', 'workouts' => 'members');
	$pages = _test_gym_pages();
	pg_assert_same(array_keys($folders), array_keys($pages), 'the pages');
	foreach ($folders as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
		$shop = $folder !== 'members';
		pg_assert_same($shop, ($pages[$key]['requires'] ?? '') === 'ecommerce', "$key: " . ($shop ? 'needs' : 'stands without') . ' the shop');
	}
	foreach (array('members', 'workouts') as $key) pg_assert_false($pages[$key]['sitemap'], "$key: not in the sitemap");
	foreach ($part['widgets'] as $key => $w) {
		pg_assert_same('ecommerce', $w['requires'] ?? '', "$key needs the shop");
		if ($w['page'] !== '') pg_assert_true(isset($pages[$w['page']]), "$key sits on a page of the part");
	}
}

// A membership product per plan: its SKU, prices and days, not shipped.
function test_gym_members_catalog()
{
	$d = _test_gym_load();
	$groups = $d['part']['catalog']['groups'];
	pg_assert_same(array('memberships'), array_keys($groups), 'one group');
	pg_assert_same('root', $groups['memberships']['parent'], 'under the top group');
	$products = $groups['memberships']['products'];
	pg_assert_same(count($d['plans']), count($products), 'a product per plan');
	foreach (array_values($d['plans']) as $n => $p) {
		$pr = $products[$n];
		pg_assert_same($p['sku'], $pr['sku'], "plan $n: SKU");
		pg_assert_same($p['price'], $pr['price'], "plan $n: the plan's prices");
		pg_assert_same($p['days'], $pr['membership_days'], "plan $n: renews the membership by its days");
		pg_assert_false($pr['shippable'], "plan $n: not shipped");
		pg_assert_false(isset($pr['private_folder']), "plan $n: grants no folder of its own");
	}
	$w = $d['part']['widgets']['membership_catalog'];
	pg_assert_same('{{product_group:memberships}}', $w['config']['product_group_id'], 'the listing reads the Memberships group');
	pg_assert_same('{{tab:memberships}}', $d['part']['widgets']['product_detail']['config']['catalog_listing_page_id'], 'back to the memberships');
}

// Links name template pages; the shared parts never link a shop page.
function test_gym_members_links()
{
	$d = _test_gym_load();
	$json = json_encode(array(_test_gym_pages(), $d['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(array('home', 'classes', 'timetable', 'plans', 'facilities', 'about', 'faq', 'location',
		'trainers', 'trainer', 'book_online', 'class_booking', 'join', 'privacy', 'contact', 'my_account', 'my_bookings',
		'memberships', 'product', 'cart', 'checkout', 'order', 'email_order', 'members', 'workouts'));
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (array('memberships', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($d['shared'], '{{page:' . $key . '}}') !== false, "the shared parts do not link $key");
	}
	pg_assert_contains('{{page:workouts}}', json_encode(_test_gym_pages()['members']['tree']), 'the members\' home links the workouts');
}

// Ids carry gy-, classes are Bootstrap's or the template's, no faded text.
function test_gym_members_ids_and_classes()
{
	$nodes = array();
	foreach (_test_gym_pages() as $p) _test_gym_nodes($p['tree'], $nodes);
	foreach (_test_gym_load()['part']['widgets'] as $w) _test_gym_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'gy-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a membership reads "üyelik".
function test_gym_members_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym/members.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bMemberships?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'üyelik', 0, 'UTF-8') !== false, "'$key' says üyelik");
	}
}
