<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's bulk product sub-action handlers
 * (includes/explorer/handle_products_bulk.php): the handlers live there,
 * behind the gate, pg_explorer_handle() calls each one for its sub-action,
 * and the pure parts (the group pickers' tree order, a product's new price
 * and its new stock quantity) behave as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// Each sub-action has its own handler, loaded by the explorer's functions and
// called from pg_explorer_handle().
function test_explorer_products_bulk_handlers_live_in_their_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');

	foreach (array('bulk_product_options', 'products_bulk_edit') as $action) {
		$function = 'pg_explorer_handle_' . $action;
		pg_assert_contains('function ' . $function . '(', $handlers, $function);
		pg_assert_true(function_exists($function), $function . ' loaded');
		pg_assert_contains("case 'explorer_" . $action . "':\n            " . $function . '($type, $request, $user);' . "\n            break;", $explorer, $function . ' is called by its case');
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handlers, 'gate');
	pg_assert_false(strpos($handlers, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');", $explorer, 'loaded by the explorer');
}

// The group pickers list every group in tree order, each with its depth.
function test_explorer_bulk_product_group_options()
{
	$map = array(
		3 => array('id' => '3', 'parent_id' => '0', 'name' => 'Shoes'),
		5 => array('id' => '5', 'parent_id' => '3', 'name' => 'Boots'),
		7 => array('id' => '7', 'parent_id' => '0', 'name' => 'Hats'),
		9 => array('id' => '9', 'parent_id' => '5', 'name' => 'Winter'),
		11 => array('id' => '11', 'parent_id' => '40', 'name' => 'Orphan'));

	pg_assert_same(array(
		array('id' => 3, 'name' => 'Shoes', 'depth' => 0),
		array('id' => 5, 'name' => 'Boots', 'depth' => 1),
		array('id' => 9, 'name' => 'Winter', 'depth' => 2),
		array('id' => 7, 'name' => 'Hats', 'depth' => 0)),
		pg_explorer_bulk_product_group_options($map), 'tree order, orphan left out');

	pg_assert_same(array(), pg_explorer_bulk_product_group_options(array()), 'no groups');
}

// Prices are integer minor units: a percentage is rounded, never truncated;
// a money amount arrives already in minor units.
function test_explorer_bulk_product_new_price()
{
	pg_assert_same(1150, pg_explorer_bulk_product_new_price(1000, 'increase', 150.0), 'increase');
	pg_assert_same(850, pg_explorer_bulk_product_new_price('1000', 'decrease', 150.0), 'decrease');
	pg_assert_same(1100, pg_explorer_bulk_product_new_price(1000, 'increase_percent', 10.0), 'increase percent');
	pg_assert_same(900, pg_explorer_bulk_product_new_price(1000, 'decrease_percent', 10.0), 'decrease percent');
	// 999 * 1.15 = 1148.85: rounded up, where a cast would give 1148.
	pg_assert_same(1149, pg_explorer_bulk_product_new_price(999, 'increase_percent', 15.0), 'rounded, not truncated');
	pg_assert_same(-50, pg_explorer_bulk_product_new_price(100, 'decrease', 150.0), 'below zero is left to the caller');
	pg_assert_same(100, pg_explorer_bulk_product_new_price(100, 'other', 10.0), 'unknown method');
}

// The bulk price change value: an amount is read the way the product screen
// reads it and turned into minor units; a percentage stays a percentage;
// empty, non-numeric, zero and negative values are refused.
function test_explorer_bulk_price_value()
{
	pg_assert_same(1250.0, pg_explorer_bulk_price_value('increase', '12,50'), 'comma');
	pg_assert_same(1250.0, pg_explorer_bulk_price_value('decrease', '12.50'), 'dot');
	pg_assert_same(125000.0, pg_explorer_bulk_price_value('increase', '1.250,00'), 'Turkish grouped');
	pg_assert_same(125000.0, pg_explorer_bulk_price_value('decrease', '1,250.00'), 'English grouped');
	pg_assert_same(12.5, pg_explorer_bulk_price_value('increase_percent', '12,5'), 'percent, comma');
	pg_assert_same(10.0, pg_explorer_bulk_price_value('decrease_percent', '10'), 'percent');

	$decimal = pg_number_separators()['decimal'];
	$thousands = ($decimal === ',') ? '.' : ',';

	pg_assert_same(150000.0, pg_explorer_bulk_price_value('increase', '1' . $thousands . '500'), 'three digits after the grouping separator');
	pg_assert_same(150.0, pg_explorer_bulk_price_value('increase', '1' . $decimal . '500'), 'three digits after the decimal separator');

	foreach (array('', 'abc', '0', '-5', '0,00') as $refused) {
		pg_assert_same(null, pg_explorer_bulk_price_value('increase', $refused), 'amount refused: ' . $refused);
		pg_assert_same(null, pg_explorer_bulk_price_value('increase_percent', $refused), 'percent refused: ' . $refused);
	}
}

// Stock is set, raised or lowered, and never goes below zero.
function test_explorer_bulk_product_new_quantity()
{
	pg_assert_same(7, pg_explorer_bulk_product_new_quantity(3, 'value', 7), 'value');
	pg_assert_same(10, pg_explorer_bulk_product_new_quantity(3, 'increase', 7), 'increase');
	pg_assert_same(1, pg_explorer_bulk_product_new_quantity(8, 'decrease', 7), 'decrease');
	pg_assert_same(0, pg_explorer_bulk_product_new_quantity(3, 'decrease', 7), 'not below zero');
	pg_assert_same(0, pg_explorer_bulk_product_new_quantity(3, 'value', -2), 'a negative value is zero');
}
