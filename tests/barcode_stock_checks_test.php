<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the barcode stock screens (barcode_increase_inventory.php,
 * barcode_decrease_inventory.php) and the product screen's barcode save
 * (save_product_barcode): a scanned quantity is a whole count, a product that
 * does not exist or is in the Recycle Bin is refused, a barcode that is
 * another product's code is refused, and a scanned code that is a barcode of
 * one product and the code of another moves neither.
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

// The body of one function in a source file, up to the next top-level
// function.
function barcode_stock_checks_test_body($relative, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_save_product_barcode_checks_the_product()
{
	$save = barcode_stock_checks_test_body('includes/panel/commerce.php', 'pg_panel_save_product_barcode');

	pg_assert_contains('pg_catalog_product_binned($product_id)', $save, 'bin checked');
	pg_assert_contains("SELECT id FROM products WHERE id = '\" . e(\$product_id) . \"'", $save, 'existence checked');
	pg_assert_true(strpos($save, 'pg_catalog_product_binned($product_id)') < strpos($save, 'INSERT INTO product_barcodes'), 'before the insert');
}

// A scan resolves a barcode before a product code, so a barcode that is
// another product's code would move that product's stock on this one.
function test_save_product_barcode_refuses_another_products_code()
{
	$save = barcode_stock_checks_test_body('includes/panel/commerce.php', 'pg_panel_save_product_barcode');

	pg_assert_contains('$holder = pg_product_name_holder($barcode, $product_id, false);', $save, 'product codes looked up, the product\'s own left out');
	pg_assert_contains('pg_barcode_code_taken_text($barcode, $holder)', $save, 'the refusal names the product');
	pg_assert_true(strpos($save, 'pg_product_name_holder(') < strpos($save, 'INSERT INTO product_barcodes'), 'before the insert');
}

function test_barcode_code_taken_text()
{
	$holder = array('id' => 186, 'name' => 'FD-B', 'short_description' => 'Import <B>', 'recycled' => '0');

	$text = pg_barcode_code_taken_text('fd-b', $holder);

	pg_assert_contains('fd-b', $text, 'the barcode as typed');
	pg_assert_contains('Import <B>', $text, 'the holder by its title');
	pg_assert_contains('product code', $text);
	pg_assert_false(strpos($text, 'Recycle Bin') !== false);

	pg_assert_contains('Import &lt;B&gt;', pg_barcode_code_taken_text('fd-b', $holder, true), 'escaped for a page');
	pg_assert_contains('#186', pg_barcode_code_taken_text('fd-b', array_merge($holder, array('short_description' => ''))), 'by its number without a title');
	pg_assert_contains('Recycle Bin', pg_barcode_code_taken_text('fd-b', array_merge($holder, array('recycled' => '1'))), 'a binned product still holds its code');
}

function test_barcode_scan_choice()
{
	pg_assert_same(185, pg_barcode_scan_choice(185, 0), 'a barcode alone');
	pg_assert_same(186, pg_barcode_scan_choice(0, 186), 'a product code alone, as older labels carry');
	pg_assert_same(185, pg_barcode_scan_choice(185, 185), 'one product holds both');
	pg_assert_same(0, pg_barcode_scan_choice(0, 0), 'neither');
	pg_assert_same(null, pg_barcode_scan_choice(185, 186), 'a barcode of one product and the code of another: neither is picked');
	pg_assert_same(null, pg_barcode_scan_choice('185', '186'), 'ids as the database returns them');
}

function test_barcode_stock_screens_resolve_through_the_shared_lookup()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('$scanned = pg_barcode_scan_product($barcode);', $source, $file . ': one lookup');
		pg_assert_contains("\$scanned['refusal']", $source, $file . ': its refusal answered');
		pg_assert_false(strpos($source, '"name = ?"') !== false, $file . ': no product-code fallback of its own');
		pg_assert_true(strpos($source, 'pg_barcode_scan_product(') < strpos($source, 'UPDATE products'), $file . ': before the write');
	}

	$lookup = barcode_stock_checks_test_body('includes/fn/ecommerce.php', 'pg_barcode_scan_product');

	pg_assert_contains('FROM product_barcodes', $lookup, 'barcodes');
	pg_assert_contains('FROM products', $lookup, 'product codes');
	pg_assert_contains('pg_barcode_scan_choice(', $lookup, 'the two answers settled in one place');
}
