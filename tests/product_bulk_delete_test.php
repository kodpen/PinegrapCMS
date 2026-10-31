<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Products list's bulk delete and bulk enable: the message for
 * the ticked products that stayed because they are part of a bundle, and the
 * SEO score marked stale when the enabled state is written in bulk.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No component refused: no message.
function test_bundle_bulk_delete_refusal_none()
{
	pg_assert_same('', pg_bundle_bulk_delete_refusal(0));
	pg_assert_same('', pg_bundle_bulk_delete_refusal(-2));
}

// One or more components refused: a sentence whose verb agrees with the
// count, each a literal key.
function test_bundle_bulk_delete_refusal_counts()
{
	pg_assert_same(
		lang(array('string' => '{var:1} product stayed because it is part of a bundle. Take it out of the bundle before deleting it.', 'vars' => array(1))),
		pg_bundle_bulk_delete_refusal(1));

	pg_assert_same(
		lang(array('string' => '{var:1} products stayed because they are part of a bundle. Take them out of the bundle before deleting them.', 'vars' => array(3))),
		pg_bundle_bulk_delete_refusal(3));

	pg_assert_true(strpos(pg_bundle_bulk_delete_refusal(3), '3') !== false, 'the count is in the sentence');
}

// Delete Selected checks for a component before binning or deleting and
// marks the error after the loop.
function test_edit_products_delete_counts_components()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');

	pg_assert_true(strpos($src, 'pg_bundle_is_component($product_id)') !== false, 'component checked');
	pg_assert_true(strpos($src, 'pg_bundle_bulk_delete_refusal($number_of_components)') !== false, 'refusal told');
}

// Both bulk enable writes mark the SEO analysis stale, as the product screen
// save and the list's quick edit do.
function test_bulk_enable_marks_seo_stale()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	pg_assert_true(preg_match('/\$sql_enabled = "enabled = [^\n]*seo_analysis_current = \'0\',/', $src) === 1, 'Modify Selected');

	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');
	pg_assert_true(strpos($src, '$sql_parts[] = "seo_analysis_current = \'0\'";') !== false, 'Catalog Manager');
}
