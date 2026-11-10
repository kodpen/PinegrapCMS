<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the HS code and the country of origin in the products list's
 * Modify Selected (edit_products.php): the values the submit asks for, the
 * refusals before any write, and the dialog and the UPDATE behind
 * pg_product_customs_ready().
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

// No choice is no change; Clear is ''; Set to takes the screen's parsers and
// gives false for a bad or an empty value.
function test_products_mass_customs_values()
{
	pg_assert_same(array('hs_code' => null, 'origin_country' => null),
		pg_products_mass_customs_values(array()), 'nothing asked');
	pg_assert_same(array('hs_code' => null, 'origin_country' => null),
		pg_products_mass_customs_values(array('edit_hs_code' => '6109.10', 'edit_origin_country' => 'TR')), 'values without a choice');
	pg_assert_same(array('hs_code' => '6109.10', 'origin_country' => 'TR'),
		pg_products_mass_customs_values(array('edit_hs_code_method' => 'set', 'edit_hs_code' => ' 6109.10 ',
			'edit_origin_country_method' => 'set', 'edit_origin_country' => 'tr')), 'set, normalised');
	pg_assert_same(array('hs_code' => '', 'origin_country' => ''),
		pg_products_mass_customs_values(array('edit_hs_code_method' => 'clear', 'edit_hs_code' => 'x',
			'edit_origin_country_method' => 'clear', 'edit_origin_country' => 'ZZ')), 'clear ignores the box');
	pg_assert_same(array('hs_code' => false, 'origin_country' => false),
		pg_products_mass_customs_values(array('edit_hs_code_method' => 'set', 'edit_hs_code' => '6109 10',
			'edit_origin_country_method' => 'set', 'edit_origin_country' => 'ZZ')), 'bad values');
	pg_assert_same(array('hs_code' => false, 'origin_country' => false),
		pg_products_mass_customs_values(array('edit_hs_code_method' => 'set', 'edit_hs_code' => '',
			'edit_origin_country_method' => 'set', 'edit_origin_country' => '')), 'set with nothing');
	pg_assert_same(array('hs_code' => null, 'origin_country' => null),
		pg_products_mass_customs_values(array('edit_hs_code_method' => 'drop', 'edit_origin_country_method' => array('set'))), 'unknown choice');
}

// A bad or an empty value under Set to refuses the whole submit.
function test_products_mass_edit_problem_customs()
{
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_hs_code_method' => 'set', 'edit_hs_code' => '6109.10.00.00.11')), 'GTIP');
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_hs_code_method' => 'clear')), 'clear');
	pg_assert_same('hs_code_invalid', pg_products_mass_edit_problem(array('edit_hs_code_method' => 'set', 'edit_hs_code' => '61A9')), 'letter');
	pg_assert_same('hs_code_invalid', pg_products_mass_edit_problem(array('edit_hs_code_method' => 'set', 'edit_hs_code' => str_repeat('1', 17))), 'too long');
	pg_assert_same('hs_code_missing', pg_products_mass_edit_problem(array('edit_hs_code_method' => 'set', 'edit_hs_code' => '  ')), 'empty code');
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_origin_country_method' => 'set', 'edit_origin_country' => 'DE')), 'country');
	pg_assert_same('origin_country_invalid', pg_products_mass_edit_problem(array('edit_origin_country_method' => 'set', 'edit_origin_country' => 'XX')), 'unknown country');
	pg_assert_same('origin_country_missing', pg_products_mass_edit_problem(array('edit_origin_country_method' => 'set', 'edit_origin_country' => '')), 'shop default under set');
}

function test_products_mass_change_besides_price_customs()
{
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_hs_code_method' => 'set', 'edit_hs_code' => '6109'), null), 'HS code');
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_origin_country_method' => 'clear'), null), 'country');
	pg_assert_false(pg_products_mass_change_besides_price(array('edit_hs_code_method' => '', 'edit_origin_country_method' => ''), null), 'no change');
}

// The dialog draws the fields only with the columns, and the screen writes
// them only then, through the parsed values.
function test_products_mass_customs_source()
{
	$template = file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_products.php');
	$gate = strpos($template, 'pg_product_customs_ready()');
	pg_assert_true($gate !== false, 'dialog asks pg_product_customs_ready()');
	pg_assert_true(($gate !== false) && (strpos($template, 'name="edit_hs_code_method"') > $gate), 'HS code field after the gate');
	pg_assert_true(($gate !== false) && (strpos($template, 'pg_product_origin_country_options(') > $gate), 'country field after the gate');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	pg_assert_true(strpos($screen, 'pg_products_mass_customs_values(') !== false, 'screen reads the parsed values');
	pg_assert_true(preg_match('/UPDATE products\s+SET[^;]*\$sql_customs/s', $screen) === 1, 'UPDATE carries the customs part');
	pg_assert_true(preg_match('/if \(!pg_product_customs_ready\(\)\)\s*\{\s*unset\(\$_POST\[\'edit_hs_code_method\'\]/', $screen) === 1, 'choices dropped without the columns');
}
