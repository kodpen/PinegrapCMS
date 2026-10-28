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
		'view_comments.php',
		'view_themes.php',
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
		pg_assert_same(0, preg_match('/\'%\' \. (?:mb_strtolower\()?\$[a-z_]+\)? \. \'%\'/', $source), $file . ': raw LIKE pattern as a value');
		pg_assert_true(strpos($source, 'pg_list_like_pattern(') !== false, $file . ': pattern from pg_list_like_pattern()');
	}
}

function test_list_like_sites_order_filters_all_escaped()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');

	pg_assert_same(48, substr_count($source, "LIKE '\" . escape(pg_list_like_pattern("), 'view_orders.php: every filter box (44 lines, four with two columns)');
}
