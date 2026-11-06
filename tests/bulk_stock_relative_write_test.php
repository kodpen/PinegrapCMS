<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bulk stock change of the product list (Edit Selected,
 * edit_products.php) and the Catalog Manager (handle_products_bulk.php):
 * an increase or a decrease is written against the column the UPDATE finds,
 * so a sale, a scan or another edit between the screen's read and its write
 * is kept.
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

function test_bulk_quantity_sql_by_mode()
{
	pg_assert_true(function_exists('pg_explorer_bulk_quantity_sql'), 'helper exists');

	if (!function_exists('pg_explorer_bulk_quantity_sql')) {
		return;
	}

	pg_assert_same("inventory_quantity = '7'", pg_explorer_bulk_quantity_sql('value', 7), 'a set value is written as itself');
	pg_assert_same(
		'inventory_quantity = IF(inventory_quantity <= 4294967290, inventory_quantity + 5, inventory_quantity)',
		pg_explorer_bulk_quantity_sql('increase', 5),
		'an increase adds to the stored count and stops short of the top'
	);
	pg_assert_same(
		'inventory_quantity = IF(inventory_quantity > 3, inventory_quantity - 3, 0)',
		pg_explorer_bulk_quantity_sql('decrease', 3),
		'a decrease takes from the stored count, never below zero'
	);
	pg_assert_same("inventory_quantity = '0'", pg_explorer_bulk_quantity_sql('value', -2), 'a negative value is zero');
	pg_assert_same(
		'inventory_quantity = IF(inventory_quantity <= 0, inventory_quantity + 4294967295, inventory_quantity)',
		pg_explorer_bulk_quantity_sql('increase', 4294967295),
		'the top itself'
	);
}

// Neither screen writes a count it worked out from its own read: the shared
// SET piece goes into the UPDATE, and the out-of-stock mark of a relative
// change is set from the row as stored after the write.
function test_bulk_screens_write_the_stock_change_in_the_update()
{
	$screens = array(
		'Edit Selected' => (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php'),
		'Catalog Manager' => (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php'),
	);

	foreach ($screens as $name => $source) {
		pg_assert_contains('pg_explorer_bulk_quantity_sql(', $source, $name . ': the shared SET piece');
		pg_assert_same(0, preg_match('/inventory_quantity = \'" \. (e\()?\$new_(inventory_)?quantity/', $source), $name . ': no absolute count from the read');
		pg_assert_contains('pg_stock_refresh_out_of_stock($product_id)', $source, $name . ': the mark from the stored row');
	}
}
