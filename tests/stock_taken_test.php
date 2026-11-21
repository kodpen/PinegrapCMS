<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a sale took from the stock (order_items.stock_taken and
 * order_item_components.stock_taken, step 11.0.268): how much a cancel puts
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

	pg_assert_contains("upgrade_2026_11_0_stock_taken();             // 11.0.268", $migration, 'called from the open version with its label');
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

// 1. A product on two lines of one order: each line records its own share,
// the second from the stock the first one left.
function test_stock_taken_two_lines_of_one_product_record_their_own_share()
{
	$stock = 3;
	$first = pg_stock_taken_of($stock, 2);
	$stock -= $first;
	$second = pg_stock_taken_of($stock, 2);

	pg_assert_same(array(2, 1), array($first, $second), 'stock 3, two lines of 2: 2 then 1');
	pg_assert_same(0, pg_stock_taken_of(0, 2), 'nothing left: 0');
	pg_assert_same(1, pg_stock_taken_of(9, 2, 1), 'with locations: what the location gave');
	pg_assert_same(0, pg_stock_taken_of(9, 2, -3), 'never below 0');

	// The checkout's second line starts from what the first left ($stock_left).
	$checkout = stock_taken_test_function('includes/fn/order_completion.php', 'pg_order_complete_line_stock');
	pg_assert_contains('$inventory_quantity = $stock_left[$product_id];', $checkout, 'checkout: a later line reads what the line above left');
	pg_assert_contains("pg_stock_taken_of(\$inventory_quantity, \$line['quantity']", $checkout);

	// A bundle's component reads the carried stock too.
	$bundle = stock_taken_test_function('includes/fn/bundles.php', 'pg_bundle_take_stock');
	pg_assert_contains('$before = $stock_left[$product_id];', $bundle, 'bundle: a component starts from the carried stock');
	pg_assert_contains('pg_stock_taken_of($before, $per_bundle * $quantity', $bundle);

	// The marketplace's plain line reads the stock right before its own
	// write, and leaves it for a bundle line further down.
	$marketplace = stock_taken_test_function('includes/api/outbound/orders.php', 'mp_order_take_stock');
	pg_assert_true(strpos($marketplace, '$before = max(0, (int) db_value(') < strpos($marketplace, "pg_stock_total_write(\$product_id, 'adjust', -\$quantity"), 'marketplace: the stock is read before this line writes');
	pg_assert_contains('$stock_left[$product_id] = $left;', $marketplace, 'marketplace: a later bundle line starts from what this line left');
}

// 2. An untracked product keeps NULL: every taker leaves before recording.
function test_stock_taken_untracked_keeps_null()
{
	$checks = array(
		array('includes/fn/order_completion.php', 'pg_order_complete_line_stock', 'if ($inventory != 1) {', "pg_stock_taken_record('order_items'"),
		array('includes/api/outbound/orders.php', 'mp_order_take_stock', "if (empty(\$product['inventory'])) {", "pg_stock_taken_record('order_items'"),
		array('includes/fn/bundles.php', 'pg_bundle_take_stock', "if (!\$now || ((string) \$now['inventory'] !== '1')) {", "pg_stock_taken_record('order_item_components'"),
	);

	foreach ($checks as $check) {
		$body = stock_taken_test_function($check[0], $check[1]);
		$leave = strpos($body, $check[2]);

		pg_assert_true(($leave !== false) && ($leave < strpos($body, $check[3])), $check[1] . ': an untracked product leaves before anything is recorded');
	}

	// A line from before the step keeps the column's NULL default.
	pg_assert_contains('"INT UNSIGNED NULL DEFAULT NULL"', stock_taken_test_function('includes/migrations/2026.11.0.php', 'upgrade_2026_11_0_stock_taken'));
}

// 3. A cancel on a line with stock_taken 0 writes no stock and announces
// nothing: the skip comes before the write and the announcement.
function test_stock_taken_zero_writes_and_announces_nothing()
{
	pg_assert_same(0, pg_stock_taken_put_back(0, 5));

	$readers = array(
		array('includes/api/outbound/orders.php', 'mp_order_return_stock', array('pg_stock_total_write(', 'inventory_quantity = inventory_quantity +', 'pg_marketplace_product_changed(')),
		array('includes/fn/bundles.php', 'pg_bundle_return_stock', array('pg_stock_total_write(', 'inventory_quantity = inventory_quantity +', 'pg_product_stock_changed(')),
	);

	foreach ($readers as $reader) {
		$body = stock_taken_test_function($reader[0], $reader[1]);
		$skip = strpos($body, 'if ($units === 0) {');

		pg_assert_true($skip !== false, $reader[1] . ' skips a line that took nothing');

		foreach ($reader[2] as $after) {
			pg_assert_true(strpos($body, $after, (int) $skip) !== false, $reader[1] . ': ' . $after . ' comes after the skip');
			pg_assert_true(strpos($body, $after) > $skip, $reader[1] . ': no ' . $after . ' before the skip');
		}
	}
}

// 4. A fresh install runs every step from the starter site, so it gets both
// columns from this step; a second run finds them and skips each
// (install_add_column() asks install_column_exists() first).
function test_stock_taken_step_is_rerunnable()
{
	$runner = stock_taken_test_function('includes/migrations/runner.php', 'install_add_column');

	pg_assert_contains('if (install_column_exists($table, $column)) {', $runner, 'an existing column is skipped');
	pg_assert_contains('return install_skipped(', $runner);
	pg_assert_same(2, substr_count(stock_taken_test_function('includes/migrations/2026.11.0.php', 'upgrade_2026_11_0_stock_taken'), 'install_add_column('), 'two columns, through the idempotent helper only');
}
