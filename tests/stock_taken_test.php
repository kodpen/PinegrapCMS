<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a sale took from the stock (order_items.stock_taken and
 * order_item_components.stock_taken, step 11.0.269): how much a cancel puts
 * back, the migration step's shape, and that every taker records what it
 * took and every marketplace cancel reads it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_taken_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The body of one function in a file, up to the next top-level function.
function stock_taken_test_function($file, $name)
{
	$source = stock_taken_test_source($file);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 10);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_stock_taken_put_back_is_the_smaller_of_taken_and_asked()
{
	// The issue's case: a bundle of 2 x C sold while C had no stock (back
	// orders on) took nothing, so its cancel puts nothing back.
	pg_assert_same(0, pg_stock_taken_put_back(0, 2), 'nothing taken, nothing back');
	pg_assert_same(0, pg_stock_taken_put_back('0', 2), 'as the database gives it');
	pg_assert_same(1, pg_stock_taken_put_back(1, 3), 'short stock: only what was there');
	pg_assert_same(3, pg_stock_taken_put_back(3, 3), 'all taken, all back');
	pg_assert_same(2, pg_stock_taken_put_back(5, 2), 'never more than asked');
	pg_assert_same(4, pg_stock_taken_put_back(null, 4), 'not recorded (an earlier order, an untracked product): the whole quantity, as before');
	pg_assert_same(0, pg_stock_taken_put_back(null, -1), 'never below 0');
}

function test_stock_taken_migration_step()
{
	$migration = stock_taken_test_source('includes/migrations/2026.11.0.php');
	$step = stock_taken_test_function('includes/migrations/2026.11.0.php', 'upgrade_2026_11_0_stock_taken');

	pg_assert_contains("upgrade_2026_11_0_stock_taken();             // 11.0.269", $migration, 'called from the open version with its label');
	pg_assert_contains("install_add_column('order_items', 'stock_taken', \"INT UNSIGNED NULL DEFAULT NULL\");", $step, 'the order line\'s column');
	pg_assert_contains("install_add_column('order_item_components', 'stock_taken', \"INT UNSIGNED NULL DEFAULT NULL\");", $step, 'the component line\'s column');
	pg_assert_contains('install_note(', $step, 'a note for the operator');
	pg_assert_false(strpos($step, 'db(') !== false, 'no data moved');
	pg_assert_false(stripos($step, 'KEY') !== false, 'no key');
}

function test_stock_taken_every_taker_records_and_every_cancel_reads()
{
	$takers = array(
		array('includes/fn/bundles.php', 'pg_bundle_take_stock', "pg_stock_taken_record('order_item_components'"),
		array('includes/fn/order_completion.php', 'pg_order_complete_line_stock', "pg_stock_taken_record('order_items'"),
		array('includes/api/outbound/orders.php', 'mp_order_take_stock', "pg_stock_taken_record('order_items'"),
	);

	foreach ($takers as $taker) {
		pg_assert_contains($taker[2], stock_taken_test_function($taker[0], $taker[1]), $taker[1] . ' records what it took');
	}

	// The bundle taker serves the checkout, the counter sale and the
	// marketplace: each reaches it.
	pg_assert_contains('pg_bundle_take_stock(', stock_taken_test_function('includes/fn/order_completion.php', 'pg_order_complete_line_stock'), 'checkout and counter sale');
	pg_assert_contains('pg_bundle_take_stock(', stock_taken_test_function('includes/api/outbound/orders.php', 'mp_order_take_stock'), 'marketplace');

	foreach (array(array('includes/fn/bundles.php', 'pg_bundle_return_stock', 'order_item_components'), array('includes/api/outbound/orders.php', 'mp_order_return_stock', 'order_items')) as $reader) {
		$body = stock_taken_test_function($reader[0], $reader[1]);

		pg_assert_contains('pg_stock_taken_put_back(', $body, $reader[1] . ' puts back only what was taken');
		pg_assert_contains("pg_stock_taken_returned('" . $reader[2] . "'", $body, $reader[1] . ' lowers what was taken, so a second cancel gives nothing');
		pg_assert_contains('if ($units === 0)', $body, $reader[1] . ': nothing taken, nothing written or announced');
	}

	// The record and the lowering touch only these two tables.
	pg_assert_contains("in_array(\$table, array('order_items', 'order_item_components'), true)", stock_taken_test_function('includes/fn/stock_sales.php', 'pg_stock_taken_record'));
	pg_assert_contains('stock_taken IS NOT NULL', stock_taken_test_function('includes/fn/stock_sales.php', 'pg_stock_taken_returned'), 'a row with nothing recorded is left alone');
}
