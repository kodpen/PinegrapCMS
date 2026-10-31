<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the decimal cells of the product, contact and user importers:
 * pg_products_import_decimal_cell() (includes/fn/ecommerce.php, needs
 * product_builder.php) reads weight, dimensions and the commission rate
 * limit as the product screen does, and the contact and user importers send
 * affiliate_commission_rate through pg_parse_commission_rate(). A ';' CSV
 * from a Turkish spreadsheet writes "7,5", which the non-strict session
 * would store as 7.00.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

function test_products_import_decimal_cell_reads_a_decimal_comma()
{
	pg_assert_same('0.1250', pg_products_import_decimal_cell('weight', '0,125'), 'weight with a comma');
	pg_assert_same('0.1250', pg_products_import_decimal_cell('weight', '0.125'), 'weight with a dot');
	pg_assert_same('12.5000', pg_products_import_decimal_cell('length', '12,5'), 'length');
	pg_assert_same('3.0000', pg_products_import_decimal_cell('width', '3'), 'width');
	pg_assert_same('1234.5600', pg_products_import_decimal_cell('height', '1.234,56'), 'height grouped');
	pg_assert_same('0.0001', pg_products_import_decimal_cell('weight', '0,00012'), 'four places');
	pg_assert_same('7.50', pg_products_import_decimal_cell('commission_rate_limit', '7,5'), 'rate with a comma');
	pg_assert_same('7.50', pg_products_import_decimal_cell('commission_rate_limit', '7.50'), 'rate as exported');
	pg_assert_same('999.99', pg_products_import_decimal_cell('commission_rate_limit', '1500'), 'rate held to the top');
}

function test_products_import_decimal_cell_leaves_other_columns()
{
	pg_assert_same(null, pg_products_import_decimal_cell('price', '12,50'), 'price has its own reader');
	pg_assert_same(null, pg_products_import_decimal_cell('tax_rate', '8,25'), 'tax rate has its own reader');
	pg_assert_same(null, pg_products_import_decimal_cell('short_description', '0,125'), 'text column');
}

function test_contact_and_user_imports_read_the_commission_rate()
{
	pg_assert_same('12.50', pg_parse_commission_rate('12,5'), 'comma');
	pg_assert_same('0.00', pg_parse_commission_rate(''), 'empty');

	$contacts = file_get_contents(PG_FUNCTIONS_DIR . '/import_contacts.php');
	pg_assert_same(2, substr_count($contacts, 'pg_import_contact_cell('), 'both contact import modes read the cell');

	$users = file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');
	pg_assert_true(strpos($users, 'pg_import_contact_cell(') !== false, 'user import reads the contact cell');

	pg_assert_same('12.50', pg_import_contact_cell('affiliate_commission_rate', ' 12,5 '), 'rate cell');
	pg_assert_same('0.00', pg_import_contact_cell('affiliate_commission_rate', ''), 'empty rate cell');
	pg_assert_same('7,5 Main St', pg_import_contact_cell('business_address_1', ' 7,5 Main St '), 'text cell trimmed only');
}

function test_products_import_writes_decimal_cells_through_the_reader()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');
	pg_assert_same(2, substr_count($source, 'pg_products_import_decimal_cell('), 'update and insert paths');
}

function test_products_import_decimal_cell_reads_a_spreadsheet_exponent()
{
	pg_assert_same('0.0010', pg_products_import_decimal_cell('weight', '1E-3'), 'small weight as a spreadsheet writes it');
	pg_assert_same('1500.0000', pg_products_import_decimal_cell('length', '1.5e+3'), 'exponent with a fraction');
}

function test_products_import_row_decimals_refuses_a_cell_that_is_not_a_number()
{
	// The issue's Turkish ';' row: name;price;weight;length;width;height;commission_rate_limit
	$positions = array('weight' => 2, 'length' => 3, 'width' => 4, 'height' => 5, 'commission_rate_limit' => 6);

	pg_assert_same(false, pg_products_import_row_decimals_refused(array('K', '1.250,50', '1,5', '30,5', '20,25', '10,75', '7,5'), $positions), 'Turkish decimals');
	pg_assert_same(false, pg_products_import_row_decimals_refused(array('K', '1', ' 0.1250 ', '', '', '', ''), $positions), 'exported dots and empty cells');
	pg_assert_same(false, pg_products_import_row_decimals_refused(array('K', '1', '1 234,5'), $positions), 'space-grouped, short row');
	pg_assert_same(false, pg_products_import_row_decimals_refused(array('K', '1', '1E-3'), $positions), 'spreadsheet exponent');
	pg_assert_same(false, pg_products_import_row_decimals_refused(array('K', '1', '1,5', '30,5', '20,25', '10,75', '1500'), $positions), 'rate above the top is held, as on the product screen');

	pg_assert_same(true, pg_products_import_row_decimals_refused(array('K', '1', 'abc'), $positions), 'words');
	pg_assert_same(true, pg_products_import_row_decimals_refused(array('K', '1', '1,5 kg'), $positions), 'a unit');
	pg_assert_same(true, pg_products_import_row_decimals_refused(array('K', '1', '-2'), $positions), 'negative (unsigned column)');
	pg_assert_same(true, pg_products_import_row_decimals_refused(array('K', '1', '1', '1', '1', '1', 'yedi'), $positions), 'rate not a number');
	pg_assert_same(true, pg_products_import_row_decimals_refused(array('K', '1', '', '100000000'), $positions), 'above decimal(12,4)');
	pg_assert_same(true, pg_products_import_row_decimals_refused(array('K', '1', '.,'), $positions), 'separators only');
	pg_assert_same(false, pg_products_import_row_decimals_refused(array('K', 'abc'), array()), 'no decimal column');
}

function test_products_import_leaves_out_a_row_whose_decimal_cell_is_not_a_number()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');
	pg_assert_true(strpos($source, 'pg_products_import_row_decimals_refused(') !== false, 'the import loop checks the row');
	pg_assert_true(strpos($source, 'is not a number: {var:1}.') !== false, 'the rows left out are named');
}
