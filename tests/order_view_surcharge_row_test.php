<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_ov_stamp_surcharge_row() in includes/fn/widgets.php: a saved
 * order view tree's card surcharge row is dropped on an order without a
 * surcharge, as the starter's row is; nothing else is touched.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _order_view_surcharge_row($token, $label, $props = array())
{
	return array('type' => 'semantic', 'props' => $props + array('tag' => 'div'), 'children' => array(
		array('type' => 'content', 'props' => array('text' => $label)),
		array('type' => 'content', 'props' => array('_bindings' => array('text' => $token))),
	));
}

// Only the surcharge row is stamped, on the row itself, not its amount.
function test_order_view_surcharge_row_stamps_only_the_row()
{
	$tree = array('type' => 'semantic', 'props' => array(), 'children' => array(
		_order_view_surcharge_row('__order_subtotal', 'Subtotal'),
		_order_view_surcharge_row('__order_surcharge', 'Card surcharge'),
		_order_view_surcharge_row('__order_total', 'Total'),
	));
	pg_ov_stamp_surcharge_row($tree);
	pg_assert_same('has_surcharge', $tree['children'][1]['props']['_bindings']['eo_visible_if'], 'the surcharge row');
	pg_assert_false(isset($tree['props']['_bindings']), 'not the totals');
	pg_assert_false(isset($tree['children'][0]['props']['_bindings']), 'not the subtotal row');
	pg_assert_false(isset($tree['children'][2]['props']['_bindings']), 'not the total row');
	pg_assert_false(isset($tree['children'][1]['children'][1]['props']['_bindings']['eo_visible_if']), 'not the amount inside the row');
}

// A row with a condition of its own keeps it.
function test_order_view_surcharge_row_keeps_a_condition()
{
	$tree = array('type' => 'semantic', 'props' => array(), 'children' => array(
		_order_view_surcharge_row('__order_surcharge', 'Card surcharge', array('_bindings' => array('eo_visible_if' => 'pays_by_card'))),
		_order_view_surcharge_row('__order_total', 'Total'),
	));
	pg_ov_stamp_surcharge_row($tree);
	pg_assert_same('pays_by_card', $tree['children'][0]['props']['_bindings']['eo_visible_if']);
}

// A node holding the surcharge and another figure is not the row.
function test_order_view_surcharge_row_not_with_another_figure()
{
	$row = array('type' => 'semantic', 'props' => array(), 'children' => array(
		array('type' => 'content', 'props' => array('_bindings' => array('text' => '__order_surcharge'))),
		array('type' => 'content', 'props' => array('_bindings' => array('text' => '__order_total'))),
	));
	pg_ov_stamp_surcharge_row($row);
	pg_assert_false(isset($row['props']['_bindings']), 'the shared node');
	pg_assert_same('has_surcharge', $row['children'][0]['props']['_bindings']['eo_visible_if'], 'the amount alone is the outermost surcharge-only node');
}

// The starter's surcharge row carries has_surcharge; the renderer stamps saved trees first.
function test_order_view_surcharge_row_starter_and_wiring()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	pg_assert_contains("totalRow(_sdT('Card surcharge'),'__order_surcharge','', 'has_surcharge')", file_get_contents($root . 'assets/js/style_designer.src.js'));
	$source = file_get_contents($root . 'includes/fn/widgets.php');
	pg_assert_contains("pg_ov_stamp_surcharge_row(\$tree_decoded);\n    if (function_exists('_eo_apply_visibility_bindings')) {\n        _eo_apply_visibility_bindings(\$tree_decoded, \$_ov_vis_ctx);", $source);
}
