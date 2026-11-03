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

// The bulk quantity box reads a grouped figure whole and refuses one that is
// not a whole count, so 1.200 no longer sets every selected product to 1.
function test_explorer_bulk_quantity_value()
{
	pg_assert_same(7, pg_explorer_bulk_quantity_value('7'), 'number');
	pg_assert_same(7, pg_explorer_bulk_quantity_value(' 7 '), 'trimmed');
	pg_assert_same(-2, pg_explorer_bulk_quantity_value('-2'), 'a negative value is kept for the mode to settle');
	pg_assert_same(1200, pg_explorer_bulk_quantity_value('1.200,00'), 'Turkish grouped');
	pg_assert_same(1200, pg_explorer_bulk_quantity_value('1,200.00'), 'English grouped');

	$decimal = pg_number_separators()['decimal'];
	$thousands = ($decimal === ',') ? '.' : ',';

	pg_assert_same(1200, pg_explorer_bulk_quantity_value('1' . $thousands . '200'), 'grouped the panel\'s way');

	foreach (array('', 'abc', '3.7', '2,5', '1' . $decimal . '200') as $refused) {
		pg_assert_same(null, pg_explorer_bulk_quantity_value($refused), 'refused: ' . $refused);
	}
}

// Modify Selected on the product list reads its stock quantity with the
// Catalog Manager's reader and takes a whole count of zero or more only: a
// Turkish 1.200 was written as 1, 12.5 as 12 and a decrease by -5 raised the
// stock.
function test_products_mass_quantity_value()
{
	pg_assert_same(0, pg_products_mass_quantity_value('0'), 'zero');
	pg_assert_same(25, pg_products_mass_quantity_value(' 25 '), 'trimmed');
	pg_assert_same(1200, pg_products_mass_quantity_value('1.200,00'), 'Turkish grouped');

	$decimal = pg_number_separators()['decimal'];
	$thousands = ($decimal === ',') ? '.' : ',';

	pg_assert_same(1200, pg_products_mass_quantity_value('1' . $thousands . '200'), 'grouped the panel\'s way');

	foreach (array('', 'abc', '12.5', '2,5', '-5', '-0,5') as $refused) {
		pg_assert_same(null, pg_products_mass_quantity_value($refused), 'refused: ' . $refused);
	}
}

// The screen refuses an unreadable quantity before the product loop, writes
// the count it read and never the posted text.
function test_products_mass_change_quantity_is_read_before_the_write()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');", $screen, 'loads the reader');
	pg_assert_true(strpos($screen, 'pg_products_mass_quantity_value(') !== false, 'reads with the shared reader');
	pg_assert_true(strpos($screen, 'pg_products_mass_quantity_value(') < strpos($screen, 'foreach ($_POST[\'products\'] as $product_id)'), 'before any product is touched');
	pg_assert_true(strpos($screen, 'pg_explorer_bulk_product_new_quantity(') !== false, 'the new count is worked out by the shared helper');
	pg_assert_same(0, preg_match('/[+\-]\s*\$_POST\[\'edit_inventory_quantity\'\]/', $screen), 'no arithmetic on the posted text');
	pg_assert_same(false, strpos($screen, "'\" . e(\$_POST['edit_inventory_quantity']"), 'the posted text is not written');

	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_products.php');
	$box = substr($template, (int) strpos($template, "'id' => 'inventory_quantity'"), 400);

	pg_assert_contains("'data-inputmask-alias'=>'integer'", $box, 'the box is an integer field');
}

// products.inventory_quantity is INT UNSIGNED and the session is not strict,
// so a larger count was stored as 4294967295 without a word.
function test_inventory_quantity_too_large()
{
	pg_assert_same(4294967295, (int) PG_INVENTORY_QUANTITY_MAX, 'the INT UNSIGNED top');
	pg_assert_false(pg_inventory_quantity_too_large(0));
	pg_assert_false(pg_inventory_quantity_too_large(4294967295), 'the top itself fits');
	pg_assert_true(pg_inventory_quantity_too_large(4294967296), 'one past it');
	pg_assert_true(pg_inventory_quantity_too_large(5000000000.0), 'a float');
	pg_assert_true(pg_inventory_quantity_too_large('5000000000'), 'text');
}

function test_bulk_quantity_value_past_the_column()
{
	pg_assert_same(4294967295, pg_explorer_bulk_quantity_value('4294967295'), 'the top');
	pg_assert_true(pg_inventory_quantity_too_large(pg_explorer_bulk_quantity_value('5000000000')), 'handed back for the screen to refuse');
	pg_assert_true(pg_inventory_quantity_too_large(pg_products_mass_quantity_value('5000000000')), 'Edit Selected and the import read the same');
	pg_assert_true(pg_inventory_quantity_too_large(pg_explorer_bulk_product_new_quantity(20, 'increase', 4294967290)), 'an increase past the top is seen');
	pg_assert_false(pg_inventory_quantity_too_large(pg_explorer_bulk_product_new_quantity(5, 'increase', 4294967290)), 'one that reaches it is not');
}

// Each stock writer refuses or skips a count past the column before it
// writes: the Catalog Manager's bulk edit, Edit Selected, the product import
// and the barcode increase.
function test_stock_writers_refuse_a_count_past_the_column()
{
	$bulk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');

	pg_assert_contains('pg_inventory_quantity_too_large($bulk_quantity_value)', $bulk, 'Catalog Manager: the typed figure');
	pg_assert_contains('pg_inventory_quantity_too_large($new_quantity)', $bulk, 'Catalog Manager: each product\'s new count');
	pg_assert_contains('$quantity_too_large++', $bulk, 'skipped and counted');
	pg_assert_contains("'quantity_too_large' => \$quantity_too_large", $bulk, 'in the answer');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	$loop = strpos($screen, 'foreach ($_POST[\'products\'] as $product_id)');

	pg_assert_true(strpos($screen, 'pg_inventory_quantity_too_large($quantity_value)') < $loop, 'Edit Selected: the typed figure, before any product is touched');
	pg_assert_true(strpos($screen, 'pg_inventory_quantity_too_large($new_inventory_quantity)') > $loop, 'Edit Selected: each product\'s new count');
	pg_assert_contains('$number_of_too_large_quantity_products', $screen, 'skipped and counted');

	$import = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');

	pg_assert_contains('pg_inventory_quantity_too_large($row_quantity)', $import, 'import: the row is left out');
	pg_assert_contains('$rows_quantity_too_large[] = $row_label', $import, 'named by its number');

	$scan = (string) file_get_contents(PG_FUNCTIONS_DIR . '/barcode_increase_inventory.php');

	pg_assert_true(strpos($scan, 'pg_inventory_quantity_too_large($new_inventory_quantity)') !== false, 'barcode increase: refused');
	pg_assert_true(strpos($scan, 'pg_inventory_quantity_too_large($new_inventory_quantity)') < strpos($scan, 'UPDATE products'), 'before the write');
}
