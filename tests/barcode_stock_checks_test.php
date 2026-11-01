<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the barcode stock screens (barcode_increase_inventory.php,
 * barcode_decrease_inventory.php) and the product screen's barcode save
 * (save_product_barcode): a scanned quantity is a whole count, and a product
 * that does not exist or is in the Recycle Bin is refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_barcode_scan_quantity()
{
	pg_assert_same(1, pg_barcode_scan_quantity(1), 'JSON integer');
	pg_assert_same(3, pg_barcode_scan_quantity(' 3 '), 'text, trimmed');
	pg_assert_same(1000000, pg_barcode_scan_quantity('1000000'), 'the top');

	foreach (array(1.5, '1.5', '2,5', '0', 0, '-2', '', 'abc', '1000001', true, null, array(1)) as $refused) {
		pg_assert_same(null, pg_barcode_scan_quantity($refused), 'refused: ' . var_export($refused, true));
	}
}

// Both screens read the quantity with the helper before the lookup, and
// refuse a product in the Recycle Bin before the stock is written.
function test_barcode_stock_screens_refuse_a_bad_quantity_and_a_binned_product()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('$quantity = pg_barcode_scan_quantity($request[\'quantity\'] ?? null);', $source, $file . ': quantity read');
		pg_assert_true(strpos($source, 'pg_catalog_product_binned($id)') !== false, $file . ': bin checked');
		pg_assert_true(strpos($source, 'pg_catalog_product_binned($id)') < strpos($source, 'UPDATE products'), $file . ': before the write');
	}
}

function test_save_product_barcode_checks_the_product()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/commerce.php');
	$save = substr($source, (int) strpos($source, 'function pg_panel_save_product_barcode('), 3000);

	pg_assert_contains('pg_catalog_product_binned($product_id)', $save, 'bin checked');
	pg_assert_contains("SELECT id FROM products WHERE id = '\" . e(\$product_id) . \"'", $save, 'existence checked');
	pg_assert_true(strpos($save, 'pg_catalog_product_binned($product_id)') < strpos($save, 'INSERT INTO product_barcodes'), 'before the insert');
}
