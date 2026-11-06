<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the export's status write (pg_order_export_mark_sql()): an order
 * export moves an order from complete to exported only while it is still
 * complete, so a cancellation that lands during a long export is never
 * written over with exported.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_export_mark_sql_guards_on_complete()
{
	$sql = preg_replace('/\s+/', ' ', pg_order_export_mark_sql(42));

	pg_assert_true(strpos($sql, "UPDATE orders SET status = 'exported'") === 0, 'moves to exported');
	pg_assert_true(strpos($sql, "WHERE id = '42' AND status = 'complete'") !== false, 'only while still complete');
	pg_assert_true(strpos($sql, pg_order_touch_sql()) !== false, 'moves the change stamp');
}

function test_order_export_mark_sql_whole_number_id()
{
	$sql = pg_order_export_mark_sql("7' OR '1'='1");

	pg_assert_true(strpos($sql, "id = '7'") !== false, 'id cast to a whole number');
	pg_assert_true(strpos($sql, "OR '1'") === false, 'nothing but the number reaches the query');
}

function test_order_export_writers_use_the_guarded_write()
{
	foreach (array('view_orders.php', 'shipworks.php') as $file) {
		$code = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);

		pg_assert_true(strpos($code, "SET status = 'exported'") === false, $file . ' writes no unguarded export status');
		pg_assert_true(strpos($code, 'pg_order_export_mark_sql(') !== false, $file . ' uses the guarded write');
	}
}
