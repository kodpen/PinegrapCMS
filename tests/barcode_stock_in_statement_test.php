<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the barcode stock screens (barcode_increase_inventory.php,
 * barcode_decrease_inventory.php) on a site without stock locations: the
 * scanned units are added to or taken from the stock in the UPDATE itself,
 * never written back as a figure worked out from a read made earlier, so a
 * sale or another scan between the read and the write is not overwritten.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function barcode_stock_in_statement_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// No absolute stock figure is written by either screen.
function test_barcode_stock_screens_write_no_figure_read_before()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $file) {
		$source = barcode_stock_in_statement_test_source($file);

		pg_assert_true(strpos($source, '"inventory_quantity = ?,"') === false, $file . ': no absolute figure written');
		pg_assert_contains('$new_inventory_quantity = (int) db_value("SELECT inventory_quantity FROM products WHERE id = \'" . (int) $id . "\'");', $source, $file . ': the stock read back after the write');
	}
}

// The increase adds in the statement, with the column's top in the WHERE.
function test_barcode_increase_adds_in_the_statement()
{
	$source = barcode_stock_in_statement_test_source('barcode_increase_inventory.php');

	pg_assert_contains('inventory_quantity = inventory_quantity + ?', $source, 'added in the statement');
	pg_assert_contains('AND (inventory_quantity <= ?)', $source, 'the top guarded in the statement');
}

// The decrease takes in the statement and stops at 0 there, the flag raised
// from the stock left after it.
function test_barcode_decrease_takes_in_the_statement()
{
	$source = barcode_stock_in_statement_test_source('barcode_decrease_inventory.php');

	pg_assert_contains('inventory_quantity = GREATEST(0, CAST(inventory_quantity AS SIGNED) - ?)', $source, 'taken in the statement');
	pg_assert_contains("AND (inventory = '1') AND (inventory_quantity <= 0) AND (out_of_stock != '1')", $source, 'flag from the stock left');
}
