<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the oversold warning (#10682): which lines of a completed sale
 * took fewer units than they asked while the product takes no back orders,
 * how they are counted on the one bell line, the bell line's words, the
 * order screen's "taken from stock" note, and that every taker raises it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function stock_oversold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_stock_oversold_products_counts_short_lines_without_back_orders()
{
	$rows = array(
		// The issue's case: two orders of the last unit, this one took none.
		array('product_id' => 7, 'asked' => 1, 'taken' => 0, 'backorder' => 0),
		// A bundle's component line of 2 x C with one C left.
		array('product_id' => 9, 'asked' => 2, 'taken' => '1', 'backorder' => '0'),
		// The same product short on a second line counts once.
		array('product_id' => 7, 'asked' => 3, 'taken' => 1, 'backorder' => 0),
		// Back orders on: selling beyond the stock is what the shop allows.
		array('product_id' => 11, 'asked' => 5, 'taken' => 0, 'backorder' => 1),
		// Fully taken.
		array('product_id' => 12, 'asked' => 2, 'taken' => 2, 'backorder' => 0),
		// Not recorded: an untracked product or an earlier order.
		array('product_id' => 13, 'asked' => 2, 'taken' => null, 'backorder' => 0),
		array('product_id' => 14, 'asked' => 2, 'taken' => '', 'backorder' => 0),
	);

	pg_assert_same(array(7, 9), pg_stock_oversold_products($rows), 'short lines without back orders, each product once');
	pg_assert_same(array(), pg_stock_oversold_products(array()), 'no lines, no warning');
}

function test_stock_oversold_line_note_only_below_the_quantity()
{
	pg_assert_same(true, pg_stock_taken_short(0, 1), 'none taken');
	pg_assert_same(true, pg_stock_taken_short('1', '3'), 'as the database gives it');
	pg_assert_same(false, pg_stock_taken_short(3, 3), 'all taken');
	pg_assert_same(false, pg_stock_taken_short(null, 3), 'not recorded');
	pg_assert_same(false, pg_stock_taken_short('', 3), 'not recorded, empty');
}

function test_stock_oversold_bell_line()
{
	$notifications = stock_oversold_test_source('includes/notifications.php');
	$core = stock_oversold_test_source('includes/fn/core.php');
	$sales = stock_oversold_test_source('includes/fn/stock_sales.php');

	pg_assert_contains("(\$action == 'out_stock') || (\$action == 'oversold')", $notifications, 'reaches whoever the out_stock line reaches');
	pg_assert_contains("\$action == 'oversold'", $notifications, 'has its own words');
	pg_assert_contains("'Sold beyond stock #{var:1}'", $notifications, 'short title with the order number');
	pg_assert_contains("array('new_order', 'order_return', 'out_stock', 'oversold',", $notifications, 'a shop row');
	pg_assert_contains("'action'      => 'oversold'", $sales, 'one line per order');
	pg_assert_contains("'order_total' => (string) count(\$products)", $sales, 'the products counted on the line');
	pg_assert_contains("'oversold'      => array('order', 'order_id')", stock_oversold_test_source('includes/api/resources/account.php'), 'the API names the order');
	pg_assert_same(false, strpos($core, "(\$action == 'oversold')"), 'no device push: found when the panel is next opened, as out_stock');
}

function test_stock_oversold_every_taker_raises_it()
{
	$completion = stock_oversold_test_source('includes/fn/order_completion.php');
	$marketplace = stock_oversold_test_source('includes/api/outbound/orders.php');

	pg_assert_contains('pg_stock_oversold_warn($order_id, $actor', $completion, 'checkout and the counter sale, after their lines');
	pg_assert_contains("pg_stock_oversold_warn(\$order_id, 'system'", $marketplace, 'marketplace import');
}

function test_stock_oversold_order_screen_note()
{
	$view = stock_oversold_test_source('view_order.php');

	pg_assert_same(2, substr_count($view, 'pg_order_view_stock_taken_fact('), 'both line lists');
	pg_assert_same(3, substr_count($view, '$stock_taken_column'), 'set once, read by both queries');

	$fact = pg_order_view_stock_taken_fact(1, 3);
	pg_assert_contains(lang('Taken from stock'), $fact, 'the caption');
	pg_assert_contains(lang(array('string' => '{var:1} of {var:2}', 'vars' => array(1, 3))), $fact, 'a of b');
	pg_assert_same('', pg_order_view_stock_taken_fact(3, 3), 'all taken: no note');
	pg_assert_same('', pg_order_view_stock_taken_fact(null, 3), 'not recorded: no note');
}
