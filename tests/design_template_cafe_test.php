<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Cafe design template (includes/design_templates/pinegrap-cafe.php)
 * and the parts the Clinic template shares with it: the sample events fall
 * on their weekdays and are still to come, the catering guest count is a
 * whole number from 1, the menu price field explains its format, and the
 * consent boxes link the privacy page both templates carry.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A template as the loader reads it, read once per run.
function _test_tpl_cafe_load($id)
{
	static $out = array();
	if (!isset($out[$id])) {
		$out[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	}
	return $out[$id];
}

// A page of a template by its key, or null.
function _test_tpl_cafe_page($tpl, $key)
{
	foreach ($tpl['pages'] as $p) {
		if (isset($p['key']) && $p['key'] === $key) return $p;
	}
	return null;
}

// Every node of a tree, depth first.
function _test_tpl_cafe_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_tpl_cafe_nodes($child, $out);
	}
}

// The node with an id in a page's tree or in any widget's tree, or null.
function _test_tpl_cafe_node_by_id($tpl, $id)
{
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = isset($p['tree']) ? $p['tree'] : null;
	foreach ((isset($tpl['widgets']) ? $tpl['widgets'] : array()) as $w) $trees[] = isset($w['tree']) ? $w['tree'] : null;
	foreach ($trees as $tree) {
		$nodes = array();
		_test_tpl_cafe_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['id']) && $n['props']['id'] === $id) return $n;
		}
	}
	return null;
}

// The parent of the node with an id, searched in pages and widgets, or null.
function _test_tpl_cafe_parent_of($tpl, $id)
{
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = isset($p['tree']) ? $p['tree'] : null;
	foreach ((isset($tpl['widgets']) ? $tpl['widgets'] : array()) as $w) $trees[] = isset($w['tree']) ? $w['tree'] : null;
	foreach ($trees as $tree) {
		$nodes = array();
		_test_tpl_cafe_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach ((isset($n['children']) && is_array($n['children'])) ? $n['children'] : array() as $c) {
				if (isset($c['props']['id']) && $c['props']['id'] === $id) return $n;
			}
		}
	}
	return null;
}

function _test_tpl_cafe_attr($node, $name)
{
	foreach ((isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array()) as $a) {
		if (isset($a['name']) && $a['name'] === $name) return (string)$a['value'];
	}
	return null;
}

// The sample events of the Events form.
function _test_tpl_cafe_events()
{
	$page = _test_tpl_cafe_page(_test_tpl_cafe_load('pinegrap-cafe'), 'event_new');
	return $page['form']['sample_records'];
}

function test_design_template_cafe_event_weekdays()
{
	$by_title = array();
	foreach (_test_tpl_cafe_events() as $e) $by_title[$e['title']] = $e;
	pg_assert_true(isset($by_title[lang('Acoustic Friday')]), 'Acoustic Friday is a sample event');
	pg_assert_same('5', date('N', strtotime($by_title[lang('Acoustic Friday')]['event_date'])), 'Acoustic Friday falls on a Friday');
	$found = false;
	foreach ($by_title as $e) {
		if ($e['cafe'] !== lang('Business District')) continue;
		$found = true;
		pg_assert_true((int)date('N', strtotime($e['event_date'])) <= 5, $e['title'] . ': the Business District café is open on its day');
	}
	pg_assert_true($found, 'an event is at the Business District café');
}

function test_design_template_cafe_event_dates_ahead()
{
	$today = strtotime(date('Y-m-d'));
	foreach (_test_tpl_cafe_events() as $e) {
		$day = strtotime($e['event_date']);
		pg_assert_true($day > $today, $e['title'] . ': after today');
		pg_assert_true($day <= $today + 35 * 86400, $e['title'] . ': within 35 days');
	}
}

function test_design_template_cafe_guests_pattern()
{
	$node = _test_tpl_cafe_node_by_id(_test_tpl_cafe_load('pinegrap-cafe'), 'cf-quote-guests');
	pg_assert_true($node !== null, 'the guests control exists');
	$pattern = _test_tpl_cafe_attr($node, 'pattern');
	pg_assert_true($pattern !== null && $pattern !== '', 'the guests control has a pattern');
	pg_assert_true(!empty($node['props']['_cf']['validation_message']), 'the guests control has a validation message');
	// As custom_form.php checks a submitted value.
	$re = '/^(?:' . str_replace('/', '\\/', (string)$pattern) . ')$/u';
	pg_assert_same(1, preg_match($re, '12'), '12 guests');
	foreach (array('0', '-5', '1.5', '') as $bad) {
		pg_assert_same(0, preg_match($re, $bad), '"' . $bad . '" is refused');
	}
}

function test_design_template_cafe_price_hint()
{
	$tpl = _test_tpl_cafe_load('pinegrap-cafe');
	$node = _test_tpl_cafe_node_by_id($tpl, 'cf-dish-price');
	pg_assert_same('cf-dish-price-help', _test_tpl_cafe_attr($node, 'aria-describedby'), 'the price input names its hint');
	pg_assert_true(_test_tpl_cafe_node_by_id($tpl, 'cf-dish-price-help') !== null, 'the hint element exists');
}

function test_design_template_cafe_clinic_privacy_page()
{
	$consents = array(
		'pinegrap-cafe'   => array('cf-res-consent', 'cf-quote-consent'),
		'pinegrap-clinic' => array('cl-appt-consent'),
	);
	foreach ($consents as $id => $boxes) {
		$tpl = _test_tpl_cafe_load($id);
		pg_assert_true(_test_tpl_cafe_page($tpl, 'privacy') !== null, $id . ': a privacy page');
		foreach ($boxes as $box) {
			$parent = _test_tpl_cafe_parent_of($tpl, $box);
			pg_assert_true($parent !== null, $id . '/' . $box . ': the consent box exists');
			$nodes = array();
			_test_tpl_cafe_nodes($parent, $nodes);
			$linked = false;
			foreach ($nodes as $n) {
				if (isset($n['props']['href']) && $n['props']['href'] === '{{page:privacy}}') $linked = true;
			}
			pg_assert_true($linked, $id . '/' . $box . ': the consent links the privacy page');
		}
	}
}
