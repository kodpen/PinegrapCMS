<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quick edits of the Catalog Manager
 * (includes/explorer/handle_catalog_edit.php) and the Products list
 * (pg_pb_quick_update() in product_builder.php) on a bundle: a bundle's stock
 * and a sum-priced bundle's price are worked out by pg_bundle_refresh(), so
 * a typed value is refused; the Catalog Manager's short description edit
 * marks the SEO score stale as the Products list does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only a bundle priced as the sum of its products takes no price of its own.
function test_bundle_price_is_summed()
{
	pg_assert_true(pg_bundle_price_is_summed(array('bundle' => '1', 'bundle_pricing' => 'sum')), 'sum bundle');
	pg_assert_false(pg_bundle_price_is_summed(array('bundle' => '1', 'bundle_pricing' => 'fixed')), 'fixed bundle');
	pg_assert_false(pg_bundle_price_is_summed(array('bundle' => '0', 'bundle_pricing' => 'sum')), 'not a bundle');
	pg_assert_false(pg_bundle_price_is_summed(array('price' => '100')), 'no bundle columns');
	pg_assert_false(pg_bundle_price_is_summed(null), 'no row');
}

// The Catalog Manager refuses a bundle's stock before the tracking check and
// a sum bundle's price, reading the bundle columns only when they exist.
function test_catalog_quick_edit_refuses_bundle_writes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');

	pg_assert_contains("(pg_bundles_ready() ? ', bundle, bundle_pricing' : '')", $source, 'bundle columns read when ready');
	pg_assert_contains("if (pg_bundle_price_is_summed(\$quick_row)) {\n            respond(array('status' => 'error', 'message' => lang('A bundle priced as the sum of its products takes no price of its own.')));", $source, 'sum bundle price refused');

	$bundle = strpos($source, "if (pg_bundle_ids_among(array(\$quick_id))) {\n            respond(array('status' => 'error', 'message' => lang('This product is a bundle: its stock follows the products in it. Change their stock instead.')));");
	$tracked = strpos($source, "if (\$quick_row['inventory'] != '1') {");
	pg_assert_true($bundle !== false, 'bundle stock refused');
	pg_assert_true(($bundle !== false) && ($tracked !== false) && ($bundle < $tracked), 'bundle refused before the tracking check');
}

// A short description that really changed marks the SEO score stale.
function test_catalog_quick_edit_description_marks_seo_stale()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');

	pg_assert_contains("if ((\$quick_field === 'short_description') && \$quick_changed) {\n        \$quick_sql .= \", seo_analysis_current = '0'\";", $source, 'stale mark');
	pg_assert_true(strpos($source, "\$quick_sql .= \", seo_analysis_current = '0'\";") < strpos($source, 'UPDATE products'), 'set before the write');
}

// The Products list refuses a sum bundle's price with code 'bundle'.
function test_products_list_quick_price_refuses_sum_bundle()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');

	pg_assert_contains("(pg_bundles_ready() ? ', bundle, bundle_pricing' : '')", $source, 'bundle columns read when ready');
	pg_assert_contains("if (pg_bundle_price_is_summed(\$current)) {\n                return array('ok' => false, 'code' => 'bundle', 'message' => lang('A bundle priced as the sum of its products takes no price of its own.'));", $source, 'sum bundle price refused');
}
