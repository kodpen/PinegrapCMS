<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that every shipped design template reads its list filters and its
 * form controls' starting values from address parameters a site can use:
 * a reserved name (?view=, ?id=, ?page=, ...) is turned into '' by
 * pg_sw_url_param_name() and the filter is silently left out, so every
 * link that sets it would list everything.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every _cf.url_param in a tree, keyed by where it was found.
function _test_dtup_tree_params($node, $where, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props']['_cf']['url_param']) && $node['props']['_cf']['url_param'] !== '') {
		$out[] = array($where, $node['props']['_cf']['url_param']);
	}
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dtup_tree_params($child, $where, $out);
	}
}

// Every address parameter a template reads: the list filters set to a URL
// parameter and the form controls' starting values.
function _test_dtup_params($tpl)
{
	$out = array();
	foreach (isset($tpl['widgets']) && is_array($tpl['widgets']) ? $tpl['widgets'] : array() as $key => $w) {
		$filters = isset($w['config']['filters']) && is_array($w['config']['filters']) ? $w['config']['filters'] : array();
		foreach ($filters as $f) {
			if (isset($f['dynamic_value']) && $f['dynamic_value'] === 'url parameter') {
				$out[] = array("widget $key filter", isset($f['dynamic_value_attribute']) ? $f['dynamic_value_attribute'] : '');
			}
		}
		if (isset($w['tree'])) _test_dtup_tree_params($w['tree'], "widget $key", $out);
	}
	foreach (array('pages', 'shared') as $set) {
		foreach (isset($tpl[$set]) && is_array($tpl[$set]) ? $tpl[$set] : array() as $key => $item) {
			_test_dtup_tree_params($item, "$set $key", $out);
		}
	}
	return $out;
}

function test_design_templates_url_params_are_not_reserved()
{
	$files = glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php');
	pg_assert_true(!empty($files), 'templates found');
	$checked = 0;
	foreach ($files as $file) {
		$tpl = _pg_tpl_include($file);
		if (!is_array($tpl)) continue;
		$id = basename($file, '.php');
		foreach (_test_dtup_params($tpl) as $p) {
			pg_assert_same($p[1], pg_sw_url_param_name($p[1]), "$id, {$p[0]}: ?{$p[1]}= is a usable name");
			$checked++;
		}
	}
	pg_assert_true($checked > 0, 'some parameters checked');
}
