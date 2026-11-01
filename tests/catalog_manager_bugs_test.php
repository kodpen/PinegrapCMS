<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Catalog Manager and Edit Selected write paths: a price the
 * products.price column cannot hold is refused instead of cut to its top, a
 * rename refuses a product code another product holds, and the product
 * export leaves the Recycle Bin out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/export_products_f.php');

// products.price is a signed INT of minor units: 2147483647 is the top.
function test_catalog_manager_price_bound()
{
	pg_assert_same(2147483647, PG_PRODUCT_PRICE_MAX);
	pg_assert_false(pg_product_price_too_large(0), 'free');
	pg_assert_false(pg_product_price_too_large(2147483647), 'the top');
	pg_assert_true(pg_product_price_too_large(2147483648), 'one above');
	pg_assert_true(pg_product_price_too_large(3000000000.0), 'a float above');
}

// An increase that would pass the top keeps the old price and says why.
function test_catalog_manager_bulk_price_change_refuses_too_large()
{
	pg_assert_same(array('price' => 2000000000, 'refused' => true, 'too_large' => true), pg_bulk_price_change(2000000000, '4', '10'), 'percent');
	pg_assert_same(array('price' => 2147483000, 'refused' => true, 'too_large' => true), pg_bulk_price_change(2147483000, '2', 1000), 'amount');
	pg_assert_same(array('price' => 2147483647, 'refused' => false, 'too_large' => false), pg_bulk_price_change(2147483000, '2', 647), 'up to the top');
	pg_assert_same(array('price' => 1000, 'refused' => true, 'too_large' => false), pg_bulk_price_change(1000, '1', 1000), 'down to zero');
}

// Each Catalog Manager and Edit Selected price writer asks before it writes.
function test_catalog_manager_price_writers_check_the_bound()
{
	$quick = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');
	pg_assert_contains('pg_product_price_too_large($quick_number)', $quick, 'quick price');

	$bulk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');
	pg_assert_contains('pg_product_price_too_large($new_price)', $bulk, 'Catalog Manager bulk edit');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	pg_assert_contains("\$change['too_large']", $edit, 'Edit Selected');
}

// A product rename asks who holds the code, as the product screen does.
function test_catalog_manager_rename_checks_the_code_holder()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog.php');
	$start = strpos($source, 'function pg_explorer_handle_catalog_rename');
	$body = substr($source, $start, strpos($source, 'function ', $start + 10) - $start);

	pg_assert_contains('pg_product_name_holder($name, $item_id)', $body);
	pg_assert_contains('pg_product_name_taken_text($name, $holder)', $body);
}

// Every scope of the export leaves the Recycle Bin out.
function test_catalog_manager_export_where_leaves_the_bin_out()
{
	$live = " AND (products.recycled = '0')";

	pg_assert_same("WHERE (1 = 1) AND (products.recycled = '0')", pg_products_export_where(array(), array(), $live), 'all');
	pg_assert_same('', pg_products_export_where(array(), array(), ''), 'all, before the bin');
	pg_assert_same("WHERE (id IN (3,4)) AND (products.recycled = '0')", pg_products_export_where(array(3, 4), array(), $live), 'products');
	pg_assert_contains("product_group IN (7,8))) AND (products.recycled = '0')", pg_products_export_where(array(), array(7, 8), $live), 'groups');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/export_products.php');
	pg_assert_contains("pg_catalog_bin_live_sql('products')", $source);
}
