<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checkout cart table of the Express Order widget (the
 * default tree _eo_default_designer_tree() and the server fallback
 * _eo_render_cart_summary()). Its <thead> carried `d-none
 * d-md-table-header-group`, a class Bootstrap does not have, so the column
 * headings never showed, their widths never applied, the quantity stepper
 * broke onto three lines at 768px and the stacked phone rows had no names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The class names of every node of a tree, in document order.
function _test_eo_tree_classes($node, array &$out)
{
	if (!is_array($node)) {
		return;
	}
	if (isset($node['props']['tag'])) {
		$out[] = array($node['props']['tag'], isset($node['props']['cssClass']) ? (string) $node['props']['cssClass'] : '', isset($node['props']['text']) ? (string) $node['props']['text'] : '');
	}
	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		_test_eo_tree_classes($child, $out);
	}
}

// No table-header-group utility anywhere in the file: Bootstrap has none.
function test_express_order_cart_table_uses_no_missing_utility()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');

	pg_assert_same(0, preg_match('/d-(?:sm-|md-|lg-|xl-|xxl-)?table-header-group/', $source), 'no table-header-group class');
}

// The default tree hides the header row, not the <thead>, below lg, and the
// stacked value cells carry labels shown only below lg.
function test_express_order_default_tree_header_row_and_labels()
{
	$nodes = array();
	_test_eo_tree_classes(_eo_default_designer_tree(), $nodes);

	$thead = null;
	$header_row = null;
	$labels = 0;
	$steppers = array();
	foreach ($nodes as $i => $n) {
		if (($n[0] === 'thead') && ($thead === null)) {
			$thead = $n[1];
			$header_row = isset($nodes[$i + 1]) ? $nodes[$i + 1] : null;
		}
		if (($n[0] === 'span') && (strpos($n[1], 'd-lg-none') !== false)) {
			$labels++;
		}
		if (strpos($n[1], 'pg-qty-stepper') !== false) {
			$steppers[] = $n[1];
		}
	}

	pg_assert_same('', $thead, 'thead has no display class');
	pg_assert_same(array('tr', 'd-none d-lg-table-row'), array($header_row[0], $header_row[1]), 'header row hidden below lg');
	pg_assert_same(3, $labels, 'quantity, unit price and amount labelled when stacked');
	pg_assert_true((count($steppers) > 0) && (strpos($steppers[0], 'flex-nowrap') !== false), 'stepper stays on one line');
}

// The server fallback draws the same header row and labels.
function test_express_order_fallback_header_row_and_labels()
{
	$fmt = function ($cents) {
		return (string) $cents;
	};
	$html = _eo_render_cart_summary(array(array(
		'item_id' => 7, 'product_id' => 3, 'quantity' => 2, 'price' => 1000,
		'short_description' => 'Mug', 'product_name' => 'MUG',
	)), 'w1', 'f1', $fmt);

	pg_assert_contains('<thead><tr class="d-none d-lg-table-row">', $html, 'header row hidden below lg');
	pg_assert_same(3, substr_count($html, '<span class="d-lg-none text-muted small'), 'three stacked labels');
	pg_assert_contains('input-group input-group-sm flex-nowrap pg-qty-stepper', $html, 'stepper stays on one line');
}
