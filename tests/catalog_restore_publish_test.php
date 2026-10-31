<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Catalog Manager's restore, delete and Publish/Unpublish, and
 * the Catalog Manager bulk enable: a restore touches only rows that are in
 * the bin, the delete says why a bundle component stayed, and every write of
 * a product's enabled state queues the marketplace send and marks the SEO
 * score stale; the bin badge counts what the bin shows.
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

function catalog_restore_publish_test_body($relative, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function catalog_restore_publish_test_flat($text)
{
	return preg_replace('/\s+/', ' ', (string) $text);
}

// A product restore writes only a binned row and counts only a written one,
// so Undo of a Delete leaves a product the delete refused as it was.
function test_catalog_restore_product_touches_only_binned_rows()
{
	$body = catalog_restore_publish_test_flat(catalog_restore_publish_test_body('includes/explorer/handle_catalog.php', 'pg_explorer_handle_catalog_restore'));
	$product = substr($body, strpos($body, "elseif (\$item_kind == 'product')"));

	pg_assert_true(strpos($product, "WHERE (id = '\" . e(\$item_id) . \"') AND (recycled = '1')") !== false, 'restore limited to the bin');
	pg_assert_true(strpos($product, 'mysqli_affected_rows(') !== false, 'counted only when written');
	pg_assert_true(strpos($product, "seo_analysis_current = '0'") !== false, 'SEO marked stale');
	pg_assert_true(strpos($product, 'pg_marketplace_product_changed(') !== false, 'marketplace told');
}

// Variants that come back with their set tell the marketplaces and mark the
// SEO score stale too.
function test_catalog_restore_variants_are_announced()
{
	$body = catalog_restore_publish_test_flat(catalog_restore_publish_test_body('includes/explorer/handle_catalog.php', 'pg_explorer_handle_catalog_restore'));
	$group = substr($body, 0, strpos($body, "elseif (\$item_kind == 'product')"));

	pg_assert_true(strpos($group, "seo_analysis_current = '0' WHERE (id IN (\" . \$sql_variant_ids . \")) AND (recycled = '1')") !== false, 'variants marked stale');
	pg_assert_true(strpos($group, 'pg_marketplace_product_changed(') !== false, 'variants sent');
}

// The delete reports the bundle components it refused.
function test_catalog_delete_reports_bundle_components()
{
	$body = catalog_restore_publish_test_body('includes/explorer/handle_catalog.php', 'pg_explorer_handle_catalog_recycle');

	pg_assert_true(strpos($body, 'pg_bundle_is_component($item_id)') !== false, 'component checked');
	pg_assert_true(strpos($body, 'pg_bundle_bulk_delete_refusal($components)') !== false, 'refusal told');
}

// Publish/Unpublish marks groups and products stale and sends a product.
function test_catalog_enable_marks_seo_and_sends()
{
	$body = catalog_restore_publish_test_body('includes/explorer/handle_catalog.php', 'pg_explorer_handle_catalog_enable');

	pg_assert_same(2, substr_count($body, "seo_analysis_current = '0'"), 'groups and products marked stale');
	pg_assert_true(strpos($body, 'pg_marketplace_product_changed(') !== false, 'product sent');
}

// The Catalog Manager bulk enable queues the marketplace send.
function test_catalog_bulk_enable_sends_to_marketplaces()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');

	pg_assert_true(strpos($source, "|| (\$bulk_enabled !== '')") !== false, 'enabled change queues a send');
}

// The bin badge counts only the groups the bin lists: the tops of the binned
// subtrees, not the subgroups inside them.
function test_catalog_bin_top_group_count()
{
	pg_assert_same(0, pg_catalog_bin_top_group_count(array()));

	$map = array(
		5 => array('id' => 5, 'parent_id' => 1),
		6 => array('id' => 6, 'parent_id' => 5),
		7 => array('id' => 7, 'parent_id' => 6),
		9 => array('id' => 9, 'parent_id' => 2));

	pg_assert_same(2, pg_catalog_bin_top_group_count($map));
}
