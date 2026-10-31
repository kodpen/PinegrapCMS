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
