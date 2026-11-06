<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog listing widget's sort clause
 * (pg_catalog_listing_sort_sql() in includes/fn/widgets_catalog.php): while
 * a search or filter spans the whole group subtree, a product can join
 * several group rows, so its catalog position is the lowest sort order of
 * them, never one row picked by the server.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Browsing one group: one group row per product, read as it is.
function test_catalog_listing_sort_direct_group_reads_the_row()
{
	pg_assert_same(
		array('sql' => 'xref.sort_order ASC', 'xref' => true),
		pg_catalog_listing_sort_sql('sort_order', 'ASC', 12, false));
}

// Searching or filtering under a group: the rows of every subgroup joined,
// so the order is the product's lowest sort order among them.
function test_catalog_listing_sort_subtree_takes_the_lowest_row()
{
	pg_assert_same(
		array('sql' => 'MIN(xref.sort_order) DESC', 'xref' => true),
		pg_catalog_listing_sort_sql('sort_order', 'DESC', 12, true));
}

// No group: catalog order has no meaning, the product id keeps it stable.
function test_catalog_listing_sort_without_group_falls_back_to_id()
{
	pg_assert_same(
		array('sql' => 'products.id ASC', 'xref' => false),
		pg_catalog_listing_sort_sql('sort_order', 'ASC', 0, true));
}

// The product's own columns, and an unknown field.
function test_catalog_listing_sort_product_columns()
{
	pg_assert_same(array('sql' => 'products.short_description DESC', 'xref' => false), pg_catalog_listing_sort_sql('name', 'DESC', 12, true));
	pg_assert_same(array('sql' => 'products.price ASC', 'xref' => false), pg_catalog_listing_sort_sql('price', 'ASC', 0, false));
	pg_assert_same(array('sql' => 'products.timestamp DESC', 'xref' => false), pg_catalog_listing_sort_sql('created_at', 'DESC', 12, false));
	pg_assert_same(array('sql' => 'products.id DESC', 'xref' => false), pg_catalog_listing_sort_sql('id', 'DESC', 12, true));
	pg_assert_same(null, pg_catalog_listing_sort_sql('', 'ASC', 12, true));
	pg_assert_same(null, pg_catalog_listing_sort_sql('weight', 'ASC', 12, true));
}

// A direction other than ASC or DESC is never written into the query.
function test_catalog_listing_sort_direction_is_whitelisted()
{
	pg_assert_same(array('sql' => 'products.price ASC', 'xref' => false), pg_catalog_listing_sort_sql('price', 'asc', 0, false));
	pg_assert_same(array('sql' => 'products.price DESC', 'xref' => false), pg_catalog_listing_sort_sql('price', 'ASC; DROP', 0, false));
}
