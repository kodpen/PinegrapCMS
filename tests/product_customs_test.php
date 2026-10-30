<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customs data of a product (2026.11.0, 11.0.130):
 * products.hs_code and products.origin_country, their upgrade step, how a
 * typed value is read (includes/fn/product_customs.php), how the product
 * screen saves and draws them (product_builder.php), and how a copy, the
 * product file and its import carry them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_customs_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function product_customs_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// The step is called once with its label and adds both columns with the
// idempotent helper, so a second run finds them and changes nothing; no raw
// statement, and one sentence for the operator.
function test_product_customs_step()
{
	$source = product_customs_test_source('includes/migrations/2026.11.0.php');
	$entry = product_customs_test_body($source, 'upgrade_to_2026_11_0');
	$step = product_customs_test_body($source, 'upgrade_2026_11_0_product_customs');

	pg_assert_true(preg_match('/upgrade_2026_11_0_product_customs\(\);\s+\/\/ 11\.0\.130\b/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.130\b/', $entry), 'the label is used once');

	pg_assert_contains("install_add_column('products', 'hs_code', \"VARCHAR(16) NOT NULL DEFAULT ''\")", $step);
	pg_assert_contains("install_add_column('products', 'origin_country', \"CHAR(2) NOT NULL DEFAULT ''\")", $step);
	pg_assert_contains('install_note(', $step);
	pg_assert_false(strpos($step, 'db(') !== false, 'no raw statement');
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no hand-written ALTER');
}

// install_add_column() asks for the column by its exact name before it adds
// it: the second run of the step is a skip.
function test_product_customs_step_helper_checks_first()
{
	$helper = product_customs_test_body(product_customs_test_source('includes/migrations/runner.php'), 'install_add_column');

	pg_assert_true($helper !== '', 'the helper exists');
	pg_assert_true(preg_match('/install_column_exists|information_schema|install_skipped/', $helper) === 1, 'checks before it adds');
}

// Both columns are read only after the exact-name probe.
function test_product_customs_ready_probe()
{
	$body = product_customs_test_body(product_customs_test_source('includes/fn/product_customs.php'), 'pg_product_customs_ready');

	pg_assert_contains("pg_schema_has('products', 'hs_code')", $body);
	pg_assert_contains("pg_schema_has('products', 'origin_country')", $body);
	pg_assert_false(pg_product_customs_ready(), 'no database in the test run');
}

function test_product_hs_code_kept()
{
	pg_assert_same('', pg_product_hs_code_parse(''), 'empty is the shop default');
	pg_assert_same('', pg_product_hs_code_parse('   '));
	pg_assert_same('', pg_product_hs_code_parse(null));
	pg_assert_same('610910', pg_product_hs_code_parse('610910'));
	pg_assert_same('6109.10', pg_product_hs_code_parse('6109.10'));
	pg_assert_same('6109.10.00.00.11', pg_product_hs_code_parse(' 6109.10.00.00.11 '), 'a 12-digit tariff number, trimmed');
	pg_assert_same('1234567890123456', pg_product_hs_code_parse('1234567890123456'), '16 characters');
	pg_assert_same('610910', pg_product_hs_code_parse(610910), 'a number from a client');
}

function test_product_hs_code_refused()
{
	pg_assert_same(false, pg_product_hs_code_parse('12345678901234567'), '17 characters');
	pg_assert_same(false, pg_product_hs_code_parse('6109 10'), 'a space inside');
	pg_assert_same(false, pg_product_hs_code_parse('6109-10'));
	pg_assert_same(false, pg_product_hs_code_parse('HS6109'));
	pg_assert_same(false, pg_product_hs_code_parse('6109..10'), 'two dots');
	pg_assert_same(false, pg_product_hs_code_parse('.6109'), 'a leading dot');
	pg_assert_same(false, pg_product_hs_code_parse('6109.'), 'a trailing dot');
	pg_assert_same(false, pg_product_hs_code_parse('６１０９'), 'full-width digits');
	pg_assert_same(false, pg_product_hs_code_parse(array('6109')), 'a posted list');
	pg_assert_same(false, pg_product_hs_code_parse("6109' OR '1'='1"));
}

function test_product_origin_country()
{
	pg_assert_same('', pg_product_origin_country_parse(''), 'empty is the shop default');
	pg_assert_same('', pg_product_origin_country_parse(null));
	pg_assert_same('TR', pg_product_origin_country_parse('TR'));
	pg_assert_same('CN', pg_product_origin_country_parse(' cn '), 'upper-cased and trimmed');
	pg_assert_same('DE', pg_product_origin_country_parse('de'));

	pg_assert_same(false, pg_product_origin_country_parse('XX'), 'not a country');
	pg_assert_same(false, pg_product_origin_country_parse('TUR'), 'alpha-3');
	pg_assert_same(false, pg_product_origin_country_parse('T'));
	pg_assert_same(false, pg_product_origin_country_parse('Turkey'), 'a name');
	pg_assert_same(false, pg_product_origin_country_parse(array('TR')));
}

function test_product_customs_problem()
{
	pg_assert_same('', pg_product_customs_problem('', ''));
	pg_assert_same('', pg_product_customs_problem('6109.10', 'tr'));
	pg_assert_same('The HS code may hold only digits and dots, up to 16 characters.', pg_product_customs_problem('61-09', 'TR'));
	pg_assert_same('The country of origin is not a known country.', pg_product_customs_problem('6109', 'ZZ'));
}

// What the product screen stores from its form.
function test_product_customs_from_post()
{
	pg_assert_same(array('hs_code' => '6109.10', 'origin_country' => 'TR'),
		pg_product_customs_from_post(array('hs_code' => ' 6109.10 ', 'origin_country' => 'tr')));
	pg_assert_same(array('hs_code' => '', 'origin_country' => ''), pg_product_customs_from_post(array()), 'nothing posted');
	pg_assert_same(array('hs_code' => '', 'origin_country' => ''),
		pg_product_customs_from_post(array('hs_code' => 'abc', 'origin_country' => 'ZZ')), 'never stored as typed');
}

// The screen saves both behind the probe, refuses a bad value before any
// write, and draws both fields from the stored row with the shop-default hint.
function test_product_customs_product_screen()
{
	$source = product_customs_test_source('product_builder.php');

	$common = product_customs_test_body($source, 'pg_pb_common_from_post');
	pg_assert_true(preg_match('/if \(pg_product_customs_ready\(\)\) \{\s+\$common = array_merge\(\$common, pg_product_customs_from_post\(\$_POST\)\);/', $common) === 1, 'saved behind the probe');

	$validate = product_customs_test_body($source, 'pg_pb_validate_product_post');
	pg_assert_contains('pg_product_customs_problem(', $validate);
	pg_assert_contains('output_error(lang($customs_problem)', $validate);

	pg_assert_contains("h(\$v('hs_code', ''))", $source);
	pg_assert_contains("pg_product_origin_country_options(\$v('origin_country', ''))", $source);
	pg_assert_contains("lang('Declared on labels of parcels sent abroad. Empty uses the shop default of the label service.')", $source);
	pg_assert_contains('$output_customs_fields . $output_zones_field', $source, 'inside the shipping part');
}

function test_product_customs_country_options()
{
	$html = pg_product_origin_country_options('de');

	pg_assert_true(strpos($html, '<option value="">') === 0, 'the shop default first');
	pg_assert_same(1, substr_count($html, 'selected="selected"'));
	pg_assert_true(preg_match('/<option value="DE" selected="selected">[^<]+\(DE\)<\/option>/', $html) === 1, 'the stored code selected');
	pg_assert_contains('<option value="CN">', $html, 'every country, not only the shipping ones');

	$unknown = pg_product_origin_country_options('QQ');
	pg_assert_contains('<option value="QQ" selected="selected">QQ (QQ)</option>', $unknown, 'an unknown stored code is not dropped');

	pg_assert_same(0, substr_count(pg_product_origin_country_options(''), 'selected="selected"'));
}

// A copy starts with the original's customs data; a row read before the
// upgrade adds nothing to the statement.
function test_product_customs_copy_sql()
{
	pg_assert_same(array('columns' => '', 'values' => ''), pg_product_customs_copy_sql(array('name' => 'A')));
	pg_assert_same(array('columns' => 'hs_code, origin_country,', 'values' => "'6109.10', 'TR',"),
		pg_product_customs_copy_sql(array('hs_code' => '6109.10', 'origin_country' => 'TR')));
	pg_assert_same(array('columns' => 'hs_code, origin_country,', 'values' => "'', '',"),
		pg_product_customs_copy_sql(array('hs_code' => "1' OR '1", 'origin_country' => 'Turkey')), 'a value the screen refuses is not copied');

	foreach (array('includes/fn/ecommerce.php' => 'duplicate_product', 'duplicate_products.php' => 'duplicate_products') as $file => $function) {
		$source = product_customs_test_source($file);
		$body = ($function === 'duplicate_products') ? $source : product_customs_test_body($source, $function);

		pg_assert_contains('$customs = pg_product_customs_copy_sql($row);', $body, $file);
		pg_assert_contains("\" . \$customs['columns'] . \"", $body, $file);
		pg_assert_contains("\" . \$customs['values'] . \"", $body, $file);
	}
}

// The import reads the cells with the screen's rules: kept normalised, an
// empty cell left empty, a bad one leaves the row out.
function test_product_customs_import_row()
{
	$positions = array('hs_code' => 1, 'origin_country' => 2);

	pg_assert_same(array('SKU', '6109.10', 'TR'), pg_product_customs_import_row(array('SKU', ' 6109.10', 'tr'), $positions));
	pg_assert_same(array('SKU', '', ''), pg_product_customs_import_row(array('SKU', '', ''), $positions));
	pg_assert_same(array('SKU'), pg_product_customs_import_row(array('SKU'), $positions), 'a short row');
	pg_assert_same(false, pg_product_customs_import_row(array('SKU', '61 09', 'TR'), $positions));
	pg_assert_same(false, pg_product_customs_import_row(array('SKU', '6109', 'Turkey'), $positions));
	pg_assert_same(array('SKU', 'x'), pg_product_customs_import_row(array('SKU', 'x'), array()), 'no customs columns');

	$import = product_customs_test_source('import_products.php');
	pg_assert_contains("case 'hs_code':", $import);
	pg_assert_contains("case 'origin_country':", $import);
	pg_assert_contains('return pg_product_customs_ready() ? $column_name : false;', $import);
	pg_assert_contains('pg_product_customs_import_row($row, $customs_positions)', $import);

	$export = product_customs_test_source('export_products_f.php');
	pg_assert_contains("array('heading' => 'hs_code', 'column' => 'hs_code', 'kind' => 'text')", $export);
	pg_assert_contains("array('heading' => 'origin_country', 'column' => 'origin_country', 'kind' => 'text')", $export);
}
