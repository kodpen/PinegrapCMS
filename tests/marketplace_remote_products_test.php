<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stored marketplace catalogue
 * (includes/api/outbound/remote_products.php): two marketplace codes that
 * differ only by case or Turkish letters stay two products, in the table, in
 * the SQL that says a row is matched and in the code that compares codes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/remote_products.php');

// escape() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function remote_products_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

function remote_products_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_marketplace_remote_products_table_compares_codes_byte_for_byte()
{
	$migration = remote_products_test_source('includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_marketplace_remote_products(); // 11.0.40', $migration, 'the step is called');
	pg_assert_contains('remote_code          VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', $migration,
		'"ABC-1" and "abc-1", "ŞAPKA-01" and "SAPKA-01" are two rows, not one row the second overwrites');
	pg_assert_contains('UNIQUE KEY idx_code (account_id, remote_code(191))', $migration);
	pg_assert_contains("'marketplace_remote_products',", remote_products_test_source('install/index.php'),
		'a reinstall drops it with the other tables (get_tables())');
}

function test_marketplace_remote_products_matched_sql_compares_bytes_and_keeps_the_index()
{
	$sql = mp_remote_products_matched_sql();

	pg_assert_contains('(marketplace_product_map.remote_code = marketplace_remote_products.remote_code COLLATE utf8mb4_unicode_ci)', $sql,
		'compared in the map column\'s collation, so its index is used');
	pg_assert_contains('(CAST(marketplace_product_map.remote_code AS BINARY) = CAST(marketplace_remote_products.remote_code AS BINARY))', $sql,
		'and byte for byte, so a twin is not counted as matched');
}

function test_marketplace_remote_products_map_code_sql_is_exact()
{
	remote_products_test_db_stub();

	$sql = mp_remote_products_map_code_sql("ab'c");

	pg_assert_contains("(marketplace_product_map.remote_code = 'ab\\'c')", $sql);
	pg_assert_contains("(CAST(marketplace_product_map.remote_code AS BINARY) = CAST('ab\\'c' AS BINARY))", $sql);
}

function test_marketplace_remote_products_unmatched_rows_keep_a_twin_code()
{
	$rows = array(
		array('id' => 1, 'remote_code' => 'ABC-1'),
		array('id' => 2, 'remote_code' => 'abc-1'),
		array('id' => 3, 'remote_code' => 'ŞAPKA-01'),
		array('id' => 4, 'remote_code' => 'SAPKA-01'),
	);

	$left = mp_remote_products_unmatched_rows($rows, array('ABC-1', 'ŞAPKA-01'));

	pg_assert_same(array(2, 4), array_map(function ($row) { return $row['id']; }, $left),
		'only the exact codes count as matched');
}

function test_marketplace_remote_products_same_code_is_exact()
{
	pg_assert_true(mp_remote_products_same_code('ABC-1', 'ABC-1'));
	pg_assert_false(mp_remote_products_same_code('ABC-1', 'abc-1'));
	pg_assert_false(mp_remote_products_same_code('ŞAPKA-01', 'SAPKA-01'));
}
