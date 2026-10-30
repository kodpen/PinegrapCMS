<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product copy (duplicate_product() in includes/fn/ecommerce.php,
 * Edit Product > Duplicate and Duplicate Product Group): the columns later
 * versions added (AI write marks, VAT exemption code, bundle columns), the
 * gallery pictures and a bundle's contents go with the copy.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of duplicate_product(), up to the next top-level function.
function product_duplicate_test_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	$start = strpos($source, "\nfunction duplicate_product(");

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// escape() reads db::$con; without a connection it falls back to addslashes().
function product_duplicate_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// A database without the newer columns names none of them.
function test_product_copy_extra_sql_old_schema()
{
	pg_assert_same(array('columns' => '', 'values' => ''), pg_product_copy_extra_sql(array('name' => 'A'), false));
	pg_assert_same(array('columns' => '', 'values' => ''), pg_product_copy_extra_sql(null, false));
}

// Every column the source row has is copied, cleaned to what it may hold.
function test_product_copy_extra_sql_columns()
{
	product_duplicate_test_db_stub();

	$row = array('name' => 'A', 'vat_exemption_code' => '351', 'bundle' => '1', 'bundle_pricing' => 'sum', 'bundle_discount_bp' => '1250');

	pg_assert_same(array(
		'columns' => 'ai_fields,vat_exemption_code,bundle, bundle_pricing, bundle_discount_bp,',
		'values'  => "'{\\\"title\\\":\\\"0123456789abcdef\\\"}','351','1', 'sum', '1250',",
	), pg_product_copy_extra_sql($row, '{"title":"0123456789abcdef"}'));

	$extra = pg_product_copy_extra_sql($row, null);
	pg_assert_same('ai_fields,vat_exemption_code,bundle, bundle_pricing, bundle_discount_bp,', $extra['columns']);
	pg_assert_same("NULL,'351','1', 'sum', '1250',", $extra['values'], 'no marks is NULL, never {}');

	$plain = array('bundle' => '0', 'bundle_pricing' => "x' OR '1", 'bundle_discount_bp' => '20000', 'vat_exemption_code' => '');
	pg_assert_same("'','0', 'fixed', '0',", pg_product_copy_extra_sql($plain, false)['values'], 'unknown pricing is fixed, an out-of-range discount 0');
}

// duplicate_product() writes the extra columns, the gallery and a bundle's
// contents, and derives a bundle copy's stock from them.
function test_duplicate_product_copies_everything()
{
	$body = product_duplicate_test_body();

	pg_assert_contains("pg_ai_fields_copy('product', \$row,", $body);
	pg_assert_contains("\" . \$extra['columns'] . \"", $body);
	pg_assert_contains("\" . \$extra['values'] . \"", $body);
	pg_assert_contains('FROM products_images_xref WHERE product = ', $body);
	pg_assert_contains('INSERT INTO products_images_xref (product, file_name)', $body);
	pg_assert_contains('pg_bundle_items_save($new_product_id, pg_bundle_items($id));', $body);
	pg_assert_contains('pg_bundle_refresh($new_product_id);', $body);
	pg_assert_true(strpos($body, '$is_bundle = !empty($row[\'bundle\']);') < strpos($body, 'while ($row = '),
		'the bundle switch is read before the zone loop reuses $row');
}
