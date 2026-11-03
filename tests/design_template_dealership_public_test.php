<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Dealership design template's public pages
 * (includes/design_templates/pinegrap-dealership/pages_public.php): the six
 * pages with their keys, names and descriptions, every page link naming a
 * page of the finished template, no class of another template, unique
 * element ids and search anchors on each page, the questions opening their
 * own answers, and the home placing the widgets and band the other files
 * build.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The public pages as the template file reads them: parts.php first, then
// pages_public.php in the same scope. Read once per run.
function _test_dlp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	$pages = include $dir . '/pages_public.php';
	$out = array();
	foreach ($pages as $p) $out[$p['key']] = $p;
	return $out;
}

// Every node of a tree, depth first.
function _test_dlp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dlp_nodes($child, $out);
	}
}

function test_dealership_public_pages_are_the_plans()
{
	$pages = _test_dlp_load();
	pg_assert_same(array('home', 'service', 'finance', 'about', 'visit', 'faq'), array_keys($pages), 'the six public pages in order');
	$names = array();
	foreach ($pages as $key => $p) {
		pg_assert_same('public', $p['folder'], "$key: public folder");
		pg_assert_true((bool)preg_match('/^[a-z-]+$/', $p['name']), "$key: page name");
		pg_assert_true(!isset($names[$p['name']]), "$key: page name used once");
		$names[$p['name']] = true;
		pg_assert_true(trim($p['title']) !== '', "$key: title");
		$len = strlen($p['meta_description']);
		pg_assert_true($len > 60 && $len <= 170, "$key: description length ($len)");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
	}
}

function test_dealership_public_pages_link_template_pages()
{
	$known = array_flip(array(
		'home', 'service', 'finance', 'about', 'visit', 'faq',
		'vehicle_new', 'vehicles', 'new_cars', 'used_cars', 'vehicle',
		'test_drive', 'tradein', 'finance_apply', 'contact', 'blog', 'privacy',
	));
	foreach (_test_dlp_load() as $key => $p) {
		$nodes = array();
		_test_dlp_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			$encoded = json_encode($n['props']);
			preg_match_all('/\{\{page:([a-z_]+)\}\}/', $encoded, $m);
			foreach ($m[1] as $target) pg_assert_true(isset($known[$target]), "$key: link to a page of the template: $target");
			$class = isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '';
			pg_assert_false((bool)preg_match('/(^|\s)(pre|pap|pdt)-/', $class), "$key: no class of another template ($class)");
			if (isset($n['props']['id'])) {
				$id = $n['props']['id'];
				pg_assert_true(!isset($ids[$id]), "$key: id $id used once");
				$ids[$id] = true;
			}
		}
	}
}

function test_dealership_public_faq_opens_its_answers()
{
	$pages = _test_dlp_load();
	$nodes = array();
	_test_dlp_nodes($pages['faq']['tree'], $nodes);
	$targets = array();
	$panels = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) {
			if ($a['name'] === 'data-bs-target') $targets[] = substr($a['value'], 1);
			if ($a['name'] === 'data-bs-parent') {
				pg_assert_same('#dl-faq', $a['value'], 'answer closes with the others');
				$panels[] = $n['props']['id'];
			}
		}
	}
	pg_assert_true(count($targets) >= 6, 'questions on the page');
	pg_assert_same($targets, $panels, 'each question opens its own answer');
}

function test_dealership_home_places_the_other_parts()
{
	$pages = _test_dlp_load();
	$nodes = array();
	_test_dlp_nodes($pages['home']['tree'], $nodes);
	$widgets = array();
	$shared = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) $widgets[] = $n['props']['templateWidget'];
		if (isset($n['props']['templateShared'])) $shared[] = $n['props']['templateShared'];
	}
	foreach (array('home_vehicles', 'google_reviews', 'home_posts', 'newsletter_band') as $w) {
		pg_assert_true(in_array($w, $widgets, true), "home places widget $w");
	}
	foreach (array('account_bar', 'header', 'test_drive_band', 'footer') as $s) {
		pg_assert_true(in_array($s, $shared, true), "home places shared $s");
	}
	$json = json_encode($pages['home']['tree']);
	pg_assert_true(strpos($json, '{{page:vehicles}}?make=Aster') !== false, 'a make chip filters the stock list');
	pg_assert_true(strpos($json, '{{page:finance_apply}}?plan=') !== false, 'a finance card chooses the plan');
}
