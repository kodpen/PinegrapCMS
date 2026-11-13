<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the design templates from App Studio to Events, the
 * Bookshop and the Boutique fit a phone: the account bar's links wrap as a
 * whole with their labels kept on one line, so a second site language does
 * not push Sign Up past the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The templates these rules were applied to, by id.
function _test_tpl_fitae_ids()
{
	return array(
		'pinegrap-appstudio', 'pinegrap-architecture', 'pinegrap-bakery', 'pinegrap-bookshop',
		'pinegrap-boutique', 'pinegrap-brewery', 'pinegrap-campsite', 'pinegrap-carrental',
		'pinegrap-charity', 'pinegrap-cityhall', 'pinegrap-construction', 'pinegrap-courses',
		'pinegrap-coworking', 'pinegrap-dealership', 'pinegrap-drivingschool', 'pinegrap-events',
	);
}

function _test_tpl_fitae_template($id)
{
	static $cache = array();
	if (!isset($cache[$id])) {
		$cache[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	}
	return $cache[$id];
}

// Calls $fn($node) for every node of the trees in $value.
function _test_tpl_fitae_walk($value, $fn)
{
	if (!is_array($value)) return;
	if (isset($value['type']) && isset($value['props']) && is_array($value['props'])) $fn($value);
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_fitae_walk($child, $fn);
	}
}

function _test_tpl_fitae_classes($node)
{
	$class = isset($node['props']['cssClass']) && is_string($node['props']['cssClass']) ? $node['props']['cssClass'] : '';
	return preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
}

// On a phone, with more than one site language, Sign Up ran past the page:
// the row of the switcher, the login region and the cart did not wrap. The
// row wraps as a whole now (to the end, or the middle where the bar is
// centred) and no label breaks.
function test_design_template_fit_ae_account_bar_wraps()
{
	foreach (_test_tpl_fitae_ids() as $id) {
		$tpl = _test_tpl_fitae_template($id);
		pg_assert_true(is_array($tpl), "$id: the template loads");
		if (!is_array($tpl)) continue;
		$row = null;
		$switcher = null;
		_test_tpl_fitae_walk(isset($tpl['shared']['account_bar']['tree']) ? $tpl['shared']['account_bar']['tree'] : null, function ($node) use (&$row, &$switcher) {
			foreach ($node['children'] as $child) {
				if (isset($child['props']['componentType']) && $child['props']['componentType'] === 'language_switcher') {
					$row = $node;
					$switcher = $child;
				}
			}
		});
		pg_assert_true($row !== null, "$id: the account bar holds the language switcher");
		if ($row === null) continue;
		$classes = _test_tpl_fitae_classes($row);
		foreach (array('flex-wrap', 'row-gap-1') as $c) pg_assert_contains($c, $classes, "$id: account links $c");
		pg_assert_true(in_array('justify-content-end', $classes, true) || in_array('justify-content-center', $classes, true), "$id: the links wrap to one side or the middle");
		pg_assert_contains('text-nowrap', _test_tpl_fitae_classes($switcher), "$id: the switcher's label stays on one line");

		$links = array();
		$button = null;
		_test_tpl_fitae_walk(isset($tpl['widgets']['login_region']['tree']) ? $tpl['widgets']['login_region']['tree'] : null, function ($node) use (&$links, &$button) {
			$href = isset($node['props']['_bindings']['href']) ? $node['props']['_bindings']['href'] : '';
			if ($href === '__login_url' || $href === '__register_url') $links[$href] = $node;
			if (in_array('dropdown-toggle', _test_tpl_fitae_classes($node), true)) $button = $node;
		});
		pg_assert_same(array('__login_url', '__register_url'), array_keys($links), "$id: Log In and Sign Up in the login region");
		foreach ($links as $href => $node) {
			pg_assert_contains('text-nowrap', _test_tpl_fitae_classes($node), "$id: $href stays on one line");
		}
		pg_assert_true($button !== null && in_array('text-nowrap', _test_tpl_fitae_classes($button), true), "$id: the signed-in name stays on one line");
	}
}
