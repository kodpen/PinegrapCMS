<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product list's Modify Selected price amount and the product
 * import's stock quantity: both read a figure the panel's way (1.250 on a
 * Turkish panel is twelve hundred and fifty), as the Catalog Manager does,
 * and the import marks a tracked product with no stock out of stock.
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

// Methods 1 and 2 are an amount in minor units, 3 and 4 a percentage.
function test_products_mass_price_value()
{
	$decimal = pg_number_separators()['decimal'];
	$thousands = ($decimal === ',') ? '.' : ',';

	pg_assert_same(125000, pg_products_mass_price_value('1', '1' . $thousands . '250'), 'grouped the panel\'s way');
	pg_assert_same(125000, pg_products_mass_price_value('2', '1.250,00'), 'Turkish grouped with decimals');
	pg_assert_same(3050, pg_products_mass_price_value('1', '30' . $decimal . '50'), 'decimal the panel\'s way');
	pg_assert_same(3000, pg_products_mass_price_value('1', ' 30 '), 'trimmed');
	pg_assert_same(10.0, pg_products_mass_price_value('3', '10'), 'percent');
	pg_assert_same(12.5, pg_products_mass_price_value('4', '12,5'), 'percent with a comma');

	foreach (array('', 'abc', '0', '-5', '0,00') as $refused) {
		pg_assert_same(null, pg_products_mass_price_value('1', $refused), 'refused amount: ' . $refused);
	}

	pg_assert_same(null, pg_products_mass_price_value('5', '10'), 'unknown method');
}

// The screen reads the amount with the shared reader, never by stripping
// commas, and the box is a masked money field, not a number box.
function test_products_mass_price_is_read_the_panel_way()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');

	pg_assert_true(strpos($screen, 'pg_products_mass_price_value(') !== false, 'reads with the shared reader');
	pg_assert_same(false, strpos($screen, "str_replace(',', '', \$raw_price_value)"), 'commas are not stripped');

	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_products.php');
	$box = substr($template, (int) strpos($template, "'id' => 'price_value'"), 300);

	pg_assert_same(false, strpos($box, "'type' => 'number'"), 'not a number box');
	pg_assert_contains('pg_money_mask_attributes()', $box, 'a money mask');
}

// The import reads the stock quantity cell with Modify Selected's reader,
// names a row whose cell is not a whole count and sets the out-of-stock mark
// with the product screens' rule.
function test_products_import_reads_the_stock_quantity_the_panel_way()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');", $source, 'loads the reader');
	pg_assert_true(strpos($source, 'pg_products_mass_quantity_value(') !== false, 'reads with the shared reader');
	pg_assert_true(strpos($source, 'pg_products_mass_quantity_value(') < strpos($source, 'UPDATE products'), 'before the row is written');
	pg_assert_same(2, substr_count($source, 'pg_products_import_stock_flag('), 'update and insert paths');
	pg_assert_contains('stock quantity in them is not a number: {var:1}.', $source, 'the rows left out are named');
}
