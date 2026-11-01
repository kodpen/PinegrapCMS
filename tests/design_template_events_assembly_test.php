<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assembled Events and Conferences design template
 * (includes/design_templates/pinegrap-events.php): it is offered as a
 * template, carries every part's pages in order and each widget once, every
 * placed widget, shared part and page placeholder names something the
 * template defines, the catalog is the tickets' one, the contact page's
 * parts are search sections, and the pev- classes equal their rules.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_events_tpl()
{
	static $tpl = null;
	if ($tpl === null) $tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-events.php');
	return $tpl;
}

// Every node of a tree, depth first.
function _test_events_tpl_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_events_tpl_nodes($child, $out);
	}
}

function test_events_template_offered()
{
	$all = pg_design_templates();
	pg_assert_true(isset($all['pinegrap-events']), 'the template is offered');
	pg_assert_true(!isset($all['parts']) && !isset($all['events']), 'the parts folder is not read as a template');
	$tpl = _test_events_tpl();
	pg_assert_same('1.0.2', $tpl['version']);
	pg_assert_same('bootstrap5', $tpl['framework']);
	pg_assert_same('bi-calendar2-event', $tpl['icon']);
	pg_assert_true(!isset($tpl['requires']), 'the template opens without the shop');
	pg_assert_same(4, count($tpl['highlights']));
	pg_assert_same(array('root', 'public', 'registration', 'private'), array_keys($tpl['folders']));
	pg_assert_true(!empty($tpl['contact_groups']['newsletter']['subscription']), 'the newsletter contact group');
}

function test_events_template_pages_and_widgets()
{
	$tpl = _test_events_tpl();
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-events';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array(
		include $dir . '/pages_public.php',
		include $dir . '/events.php',
		include $dir . '/tickets.php',
		include $dir . '/standard.php',
		include $dir . '/account.php',
	);
	$expected = array();
	$widget_count = 0;
	foreach ($parts as $part) {
		foreach ($part['pages'] as $p) $expected[] = $p['key'];
		$widget_count += count($part['widgets']);
	}
	$keys = array();
	foreach ($tpl['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same($expected, $keys, 'every part\'s pages, in order');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique');
	pg_assert_same($widget_count, count($tpl['widgets']), 'every widget once');
	pg_assert_same($parts[2]['catalog'], $tpl['catalog'], 'the tickets catalog');
	pg_assert_same(array('account_bar', 'notice', 'header', 'footer', 'staff_bar', 'register_band', 'email_header', 'email_footer'), array_keys($tpl['shared']));

	// Every placed widget and shared part is defined, every page and tab
	// placeholder names a page, every folder placeholder a folder.
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = $p['tree'];
	foreach ($tpl['widgets'] as $w) if (isset($w['tree'])) $trees[] = $w['tree'];
	foreach ($tpl['shared'] as $s) $trees[] = $s['tree'];
	$placed = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_events_tpl_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
			if (isset($n['props']['templateShared'])) pg_assert_true(isset($tpl['shared'][$n['props']['templateShared']]), 'shared part ' . $n['props']['templateShared']);
		}
	}
	foreach (array_keys($placed) as $key) pg_assert_true(isset($tpl['widgets'][$key]), 'placed widget ' . $key . ' is defined');
	foreach (array_keys($tpl['widgets']) as $key) pg_assert_true(isset($placed[$key]), 'widget ' . $key . ' is placed');
	$json = json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(in_array($key, $keys, true), 'placeholder names page ' . $key);
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($tpl['folders'][$key]), 'placeholder names folder ' . $key);
	foreach ($tpl['pages'] as $p) {
		if (isset($p['folder'])) pg_assert_true(isset($tpl['folders'][$p['folder']]), $p['key'] . ': its folder exists');
	}
}

function test_events_template_search_and_look()
{
	$tpl = _test_events_tpl();
	foreach ($tpl['pages'] as $p) {
		$nodes = array();
		_test_events_tpl_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
		pg_assert_same(count($ids), count(array_unique($ids)), $p['key'] . ': unique search anchors');
		if ($p['key'] === 'contact') {
			pg_assert_true(in_array('contact-details', $ids, true) && in_array('inbox-note', $ids, true), 'contact search sections');
		}
	}

	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	preg_match_all('/\.(pev-[a-z0-9-]+)/', $css, $m);
	$ruled = array_values(array_unique($m[1]));
	preg_match_all('/\bpev-[a-z0-9-]+/', json_encode(array($tpl['pages'], $tpl['widgets'], $tpl['shared'])), $m);
	$used = array_values(array_unique($m[0]));
	sort($ruled);
	sort($used);
	pg_assert_same($ruled, $used, 'pev- classes and rules match');
	pg_assert_same(2, count(json_decode($tpl['assets']['fonts'], true)), 'two fonts');
}
