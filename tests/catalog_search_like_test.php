<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the catalog listing's search (products and child groups,
 * includes/fn/widgets_catalog.php) builds its LIKE pattern with
 * pg_list_like_pattern() (through pg_list_search_where() in
 * pg_catalog_listing_search_sql()), so a % or _ typed into the search box is
 * a plain character and does not match every product.
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
// pattern comes from pg_list_like_pattern() through pg_list_search_where(),
// escaped once with e() by pg_catalog_listing_search_sql().
function test_catalog_search_like_pattern_is_escaped()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');

	pg_assert_same(0, preg_match('/LIKE \'%\$/', $source), 'no raw LIKE pattern around a variable');
	pg_assert_same(3, substr_count($source, 'pg_catalog_listing_search_sql($search_query, array('), 'product columns, attribute labels and child group searches');
	pg_assert_same(array('sql' => "(name LIKE ?)", 'params' => array('%100\\%%')), pg_list_search_where(array('100%'), array('name'), array(), ''), 'the condition takes the escaped pattern');
}

// What the pattern makes of the characters LIKE would read as wildcards.
function test_catalog_search_like_pattern_keeps_wildcards_plain()
{
	pg_assert_same('%\\_%', pg_list_like_pattern('_'), 'underscore');
	pg_assert_same('%100\\%%', pg_list_like_pattern('100%'), 'percent');
}
