<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checks of the product import (import_products.php) and the
 * products list's Modify Selected window (edit_products.php): a tax rate
 * outside 0..100 is refused instead of held to the bound, a name or short
 * description past its column is refused instead of cut, a Modify Selected
 * submit that asks for a stock change without a figure, a tax rate without
 * a valid rate or the same shipping zone both allowed and disallowed is
 * refused before any product is touched, and the list shows no day count
 * for a product that has none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_list.php');

function test_tax_rate_problem_refuses_out_of_range()
{
	pg_assert_same('', pg_tax_rate_problem(''), 'empty follows the zone');
	pg_assert_same('', pg_tax_rate_problem('  '), 'blank follows the zone');
	pg_assert_same('', pg_tax_rate_problem('0'));
	pg_assert_same('', pg_tax_rate_problem('100'));
	pg_assert_same('', pg_tax_rate_problem('8,25'), 'a decimal comma');
	pg_assert_same('out_of_range', pg_tax_rate_problem('150'));
	pg_assert_same('out_of_range', pg_tax_rate_problem('-5'));
	pg_assert_same('out_of_range', pg_tax_rate_problem('100.001'));
	pg_assert_same('not_number', pg_tax_rate_problem('KDV'));
	pg_assert_same('not_number', pg_tax_rate_problem('%20'));
}

function test_import_row_text_too_long()
{
	$positions = array('name' => 0, 'short_description' => 1);

	pg_assert_false(pg_products_import_row_text_too_long(array(str_repeat('a', 100), str_repeat('b', 100)), $positions), 'exactly 100 fits');
	pg_assert_false(pg_products_import_row_text_too_long(array(str_repeat('ş', 100), ''), $positions), 'characters, not bytes');
	pg_assert_true(pg_products_import_row_text_too_long(array('PGTEST-5' . str_repeat('b', 100), 'x'), $positions), 'a name of 108');
	pg_assert_true(pg_products_import_row_text_too_long(array('PGTEST-2', str_repeat('c', 120)), $positions), 'a short description of 120');
	pg_assert_false(pg_products_import_row_text_too_long(array('PGTEST-2'), $positions), 'a missing cell');
}

function test_mass_edit_problem()
{
	pg_assert_same('', pg_products_mass_edit_problem(array()), 'nothing asked is no problem of its own');
	pg_assert_same('quantity_missing', pg_products_mass_edit_problem(array('edit_inventory_quantity_process' => 'value', 'edit_inventory_quantity' => '')));
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_inventory_quantity_process' => 'increase', 'edit_inventory_quantity' => '5')));
	pg_assert_same('tax_rate_missing', pg_products_mass_edit_problem(array('edit_tax_rate_method' => 'set', 'edit_tax_rate_value' => '')));
	pg_assert_same('tax_rate_range', pg_products_mass_edit_problem(array('edit_tax_rate_method' => 'set', 'edit_tax_rate_value' => '150')));
	pg_assert_same('tax_rate_range', pg_products_mass_edit_problem(array('edit_tax_rate_method' => 'set', 'edit_tax_rate_value' => '-5')));
	pg_assert_same('tax_rate_missing', pg_products_mass_edit_problem(array('edit_tax_rate_method' => 'set', 'edit_tax_rate_value' => 'abc')));
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_tax_rate_method' => 'set', 'edit_tax_rate_value' => '20')));
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_tax_rate_method' => 'zone', 'edit_tax_rate_value' => '150')), 'following the zone needs no rate');
	pg_assert_same('zones_overlap', pg_products_mass_edit_problem(array('edit_allowed_zones' => '3,7', 'edit_disallowed_zones' => '7')));
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_allowed_zones' => '3', 'edit_disallowed_zones' => '7')));
}

function test_product_list_days_empty()
{
	pg_assert_same('', pg_product_list_days(null), 'NULL');
	pg_assert_same('', pg_product_list_days(''), 'empty');
	pg_assert_same('', pg_product_list_days('0'), 'zero');
	pg_assert_same(lang(array('string' => '{var:1} days', 'vars' => array('30'))), pg_product_list_days('30'));
	pg_assert_same(lang(array('string' => '{var:1} day', 'vars' => array('1'))), pg_product_list_days('1'));
}

function test_import_and_mass_edit_screens_check_before_sending()
{
	$import = file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');
	pg_assert_contains("'accept'=>'.csv,.xlsx,.ods'", $import, 'the picker offers the read types');
	pg_assert_contains('reportValidity()', $import, 'the form is checked before the warning');
	pg_assert_contains("empty(\$_FILES['file']['name'])", $import, 'no warning without a file field');

	$window = file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_products.php');
	pg_assert_contains('reportValidity()', $window, 'the tax rate box is checked before sending');
}
