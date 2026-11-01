<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the typed barcodes of Create Product: a barcode that fails the
 * site's format, that another product carries, or that two variant rows
 * share is refused before anything is written, instead of the product being
 * created without it.
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

function test_product_barcode_typed_posted_single_product()
{
	pg_assert_same(array('8690000000017'), pg_pb_posted_barcodes(array('pg_pb_barcode' => ' 8690000000017 ')));
	pg_assert_same(array(), pg_pb_posted_barcodes(array('pg_pb_barcode' => '  ')), 'a blank field means generate one');
	pg_assert_same(array(), pg_pb_posted_barcodes(array()));
	pg_assert_same(array('111'),
		pg_pb_posted_barcodes(array('pg_pb_barcode' => '111', 'variants_json' => '[{"name":"A","barcode":"222"}]')),
		'a single product takes the Barcode field, as the save does');
}

function test_product_barcode_typed_posted_variant_set()
{
	pg_assert_same(array('111', '222'),
		pg_pb_posted_barcodes(array('pg_pb_barcode' => '999', 'variants_json' => '[{"name":"A","barcode":"111"},{"name":"B","barcode":""},{"name":"C","barcode":"222"}]')),
		'a set takes each row\'s own code; empty rows are generated or left empty');
}

function test_product_barcode_typed_posted_edit()
{
	pg_assert_same(array(), pg_pb_posted_barcodes(array('pg_pb_barcode' => '111'), 7), 'the edit screen has its own barcode card');
}

function test_product_barcode_typed_problem()
{
	$taken = function ($barcode) {
		return $barcode === '8690000000017';
	};

	$no_code = function ($barcode) {
		return null;
	};

	pg_assert_same('', pg_pb_barcodes_problem(array(), 'EAN13', $taken, $no_code));
	pg_assert_same('', pg_pb_barcodes_problem(array('8690000000024'), 'EAN13', $taken, $no_code));

	pg_assert_contains('barcode format (EAN13)', pg_pb_barcodes_problem(array('12AB'), 'EAN13', $taken, $no_code), 'format');
	pg_assert_contains('12AB', pg_pb_barcodes_problem(array('12AB'), 'EAN13', $taken, $no_code), 'names the code');
	pg_assert_contains('Another product already carries this barcode.', pg_pb_barcodes_problem(array('8690000000017'), 'EAN13', $taken, $no_code), 'taken');
	pg_assert_contains('Two variants have the barcode', pg_pb_barcodes_problem(array('ab-1', 'AB-1'), 'CODE128', $taken, $no_code), 'repeated among rows, case aside');
	pg_assert_contains('&lt;b&gt;', pg_pb_barcodes_problem(array('<b>'), 'EAN13', $taken, $no_code), 'escaped for a page');
}

function test_product_barcode_typed_problem_another_products_code()
{
	$taken = function ($barcode) {
		return false;
	};

	$code_holder = function ($barcode) {
		return (strtolower($barcode) === 'korna-12')
			? array('id' => 42, 'name' => 'KORNA-12', 'short_description' => 'Korna', 'recycled' => '0')
			: null;
	};

	pg_assert_same('', pg_pb_barcodes_problem(array('KORNA-24'), 'CODE128', $taken, $code_holder), 'no product has that code');
	pg_assert_contains('product code of Korna', pg_pb_barcodes_problem(array('korna-12'), 'CODE128', $taken, $code_holder), 'another product\'s code, case aside');
}

function test_product_barcode_typed_on_another_rows_code()
{
	pg_assert_same('', pg_pb_posted_barcode_on_other_row(array('name' => 'A-1', 'pg_pb_barcode' => 'A-1')), 'a single product may carry its own code');
	pg_assert_same('', pg_pb_posted_barcode_on_other_row(array('variants_json' => '[{"name":"A","barcode":"A"},{"name":"B","barcode":"B"}]')), 'each row its own code');
	pg_assert_same('b', pg_pb_posted_barcode_on_other_row(array('variants_json' => '[{"name":"A","barcode":"b"},{"name":"B","barcode":""}]')), 'a row\'s barcode is the next row\'s code, case aside');
	pg_assert_same('', pg_pb_posted_barcode_on_other_row(array('variants_json' => '[{"name":"A","barcode":"B"},{"name":"B","barcode":""}]'), 7), 'the edit screen has its own barcode card');
}

function test_product_barcode_typed_checked_before_anything_is_written()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$start = strpos($source, "\nfunction pg_pb_validate_product_post(");
	$end = strpos($source, "\nfunction ", $start + 10);
	$body = substr($source, $start, $end - $start);

	pg_assert_contains('pg_pb_posted_barcodes($_POST, $product_id)', $body);
	pg_assert_contains('pg_pb_barcodes_problem(', $body);
	pg_assert_contains('pg_pb_posted_barcode_on_other_row($_POST, $product_id)', $body);
}
