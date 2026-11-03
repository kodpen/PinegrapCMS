<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the catalog listing's search (products and child groups,
 * includes/fn/widgets_catalog.php) builds its LIKE pattern with
 * pg_list_like_pattern(), so a % or _ typed into the search box is a plain
 * character and does not match every product.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No raw '%term%' pattern is left in the catalog's queries; the search
// pattern comes from pg_list_like_pattern(), escaped once with e().
function test_catalog_search_like_pattern_is_escaped()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');

	pg_assert_same(0, preg_match('/LIKE \'%\$/', $source), 'no raw LIKE pattern around a variable');
	pg_assert_same(2, substr_count($source, 'e(pg_list_like_pattern($search_query))'), 'product and child group searches');
}

// What the pattern makes of the characters LIKE would read as wildcards.
function test_catalog_search_like_pattern_keeps_wildcards_plain()
{
	pg_assert_same('%\\_%', pg_list_like_pattern('_'), 'underscore');
	pg_assert_same('%100\\%%', pg_list_like_pattern('100%'), 'percent');
}
