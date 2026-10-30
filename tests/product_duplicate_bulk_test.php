<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bulk product copy (duplicate_products() in
 * duplicate_products.php): like duplicate_product(), the copy writes the
 * columns later versions added through pg_product_copy_extra_sql(), the
 * gallery pictures and a bundle's contents.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of duplicate_products(), up to the end of the file.
function product_duplicate_bulk_test_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_products.php');
	$start = strpos($source, 'function duplicate_products(');

	return ($start === false) ? '' : substr($source, $start);
}

function test_duplicate_products_copies_everything()
{
	$body = product_duplicate_bulk_test_body();

	pg_assert_true($body !== '', 'duplicate_products() is found');
	pg_assert_contains("pg_product_copy_extra_sql(\$row, pg_ai_fields_copy('product', \$row,", $body);
	pg_assert_contains("'short_description' => \$short_description,", $body, 'the mark follows the changed short description');
	pg_assert_contains("\" . \$extra['columns'] . \"", $body);
	pg_assert_contains("\" . \$extra['values'] . \"", $body);
	pg_assert_contains('FROM products_images_xref WHERE product = ', $body);
	pg_assert_contains('INSERT INTO products_images_xref (product, file_name)', $body);
	pg_assert_contains('pg_bundle_items_save($new_product_id, pg_bundle_items($id));', $body);
	pg_assert_contains('pg_bundle_refresh($new_product_id);', $body);
	pg_assert_true(strpos($body, '$is_bundle = !empty($row[\'bundle\']);') !== false
		&& strpos($body, '$is_bundle = !empty($row[\'bundle\']);') < strpos($body, 'while ($row = '),
		'the bundle switch is read before the zone loop reuses $row');
}
