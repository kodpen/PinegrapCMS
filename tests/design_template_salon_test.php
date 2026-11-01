<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Beauty Salon design template's parts
 * (includes/design_templates/pinegrap-salon/): the salon's data hangs
 * together, the public pages load with their keys and root trees, every
 * page link names a page of the template, and the search anchors are
 * unique.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php first, then each
// page file in the same scope. Read once per run.
function _test_salon_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-salon';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$out = array(
		'categories' => $categories,
		'services'   => $services,
		'team'       => $team,
		'pages'      => $public,
		'shared'     => array($account_bar_tree(), $header(), $footer(), $booking_band(), $notice()),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_salon_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_salon_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_salon_page_keys()
{
	return array(
		'home', 'services', 'team', 'lookbook', 'about',
		'appointment', 'appointment_view', 'my_appointments', 'privacy',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post',
		'my_account', 'search', 'staff', 'error',
	);
}

function test_salon_data_hangs_together()
{
	$d = _test_salon_load();
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(isset($d['categories'][$s['category']]), "service $key: known category");
		pg_assert_true($s['title'] !== '' && $s['price'] !== '' && $s['duration'] !== '', "service $key: title, price, duration");
	}
	foreach ($d['team'] as $key => $t) {
		foreach ($t['categories'] as $c) pg_assert_true(isset($d['categories'][$c]), "team $key: known category $c");
	}
	foreach ($d['categories'] as $key => $c) {
		$used = false;
		foreach ($d['services'] as $s) if ($s['category'] === $key) $used = true;
		pg_assert_true($used, "category $key has a service");
	}
}

function test_salon_public_pages_load()
{
	$d = _test_salon_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree']), 'page has key, name, title, tree');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('home', 'services', 'team', 'lookbook', 'about'), $keys);
}

// A {{page:<key>}} placeholder anywhere in a page or shared part names a
// page of the finished template.
function test_salon_page_links_name_template_pages()
{
	$d = _test_salon_load();
	$known = array_flip(_test_salon_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	$json = json_encode($trees);
	preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

// Search anchors are unique per page, and every category and team member
// has one on its page.
function test_salon_search_sections()
{
	$d = _test_salon_load();
	$by_page = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_salon_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		}
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		$by_page[$p['key']] = $ids;
	}
	foreach (array_keys($d['categories']) as $key) pg_assert_true(in_array('category-' . $key, $by_page['services'], true), "services: #category-$key");
	foreach (array_keys($d['team']) as $key) pg_assert_true(in_array('member-' . $key, $by_page['team'], true), "team: #member-$key");
}

// A link's window is its target prop, never an _attrs target
// (designer/link-target-in-attrs.md); text is never left empty on a link.
function test_salon_links_have_no_attrs_target()
{
	$d = _test_salon_load();
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	$nodes = array();
	foreach ($trees as $t) _test_salon_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array() as $a) {
			pg_assert_true($a['name'] !== 'target', 'no target in _attrs');
		}
	}
}
