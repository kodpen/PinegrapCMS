<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the panel list searches build their LIKE pattern with
 * pg_list_like_pattern(), so % and _ typed into a search box are plain
 * characters: no file keeps a raw '%' . term . '%' pattern.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_list_like_sites()
{
	return array(
		'view_orders.php',
		'includes/fn/contact_list.php',
		'includes/fn/submitted_form_list.php',
		'includes/panel/search.php',
	);
}

function test_list_like_sites_bind_no_raw_pattern()
{
	foreach (pg_test_list_like_sites() as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match('/LIKE \'%" \. escape\(/', $source), $file . ': raw LIKE pattern in the query');
		pg_assert_same(0, preg_match('/\'%\' \. (?:\$[a-z_]+|[a-z_]+\(.*?\)) \. \'%\'/', $source), $file . ': raw LIKE pattern as a value');
		pg_assert_true((strpos($source, 'pg_list_like_pattern(') !== false) || (strpos($source, 'pg_list_search_where(') !== false), $file . ': pattern from pg_list_like_pattern() or pg_list_search_where()');
	}
}

function test_list_like_sites_order_filters_all_escaped()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');

	pg_assert_same(47, substr_count($source, "LIKE '\" . escape(pg_list_like_pattern("), 'view_orders.php: every filter box (43 lines, four with two columns)');
	// The product name box matches the SKU and the product name with one
	// escaped pattern.
	pg_assert_contains('$product_name_like = escape(pg_list_like_pattern(', $source, 'view_orders.php: the product name box is escaped');
	pg_assert_contains('products.short_description LIKE \'" . $product_name_like', $source, 'view_orders.php: the product name box also matches the product name');
}
