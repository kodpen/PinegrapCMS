<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks on shipworks.php getcount and getorders: the feed counts and
 * sends only orders a warehouse ships (complete or exported), so a cancelled
 * order never reaches ShipWorks as an order to ship, and the move of a sent
 * order from complete to exported leaves an Audit trail line as the order
 * list's exports do. The handler runs on a request and a database, so its
 * shape is checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one action of shipworks.php, from its case to the next one.
function shipworks_order_feed_test_action($action)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/shipworks.php');
	$start = strpos($src, "case '" . $action . "':");

	pg_assert_true($start !== false, 'the ' . $action . ' action is there');

	$end = strpos($src, "\n    case '", (int) $start + 1);

	return (string) substr($src, (int) $start, ($end === false) ? null : $end - $start);
}

// Only complete and exported orders are orders to ship; a cancelled order,
// one whose status was lost ('') and a cart are left out.
function test_shipworks_order_feed_status_sql()
{
	pg_assert_same("(orders.status IN ('complete', 'exported'))", pg_order_to_ship_sql());
	pg_assert_same("(o.status IN ('complete', 'exported'))", pg_order_to_ship_sql('o.status'));
}

// The count and the download take the same orders, so the count ShipWorks is
// told matches what it then downloads (#9142).
function test_shipworks_order_feed_leaves_cancelled_orders_out()
{
	foreach (array('getcount', 'getorders') as $action) {
		$source = shipworks_order_feed_test_action($action);

		pg_assert_true(strpos($source, 'pg_order_to_ship_sql()') !== false, $action . ' takes only orders to ship');
		pg_assert_true(strpos($source, "orders.status != 'incomplete'") === false, $action . ' no longer takes every status but a cart');
	}
}

// An order the download moves from complete to exported gets the same
// Audit trail line as the order list's exports, only when the UPDATE
// changed it (#9138).
function test_shipworks_order_feed_mark_is_audited()
{
	$source = shipworks_order_feed_test_action('getorders');

	$mark = strpos($source, "db(pg_order_export_mark_sql(\$order['id']));");
	$guard = strpos($source, 'if (mysqli_affected_rows(db::$con) > 0)', (int) $mark);
	$audit = strpos($source, "pg_audit('update', 'order', (int) \$order['id'], array('status' => 'complete'), array('status' => 'exported'), pg_audit_label_of('orders', \$order));");

	pg_assert_true($mark !== false, 'the export mark');
	pg_assert_true(($guard !== false) && ($guard > $mark), 'guarded by the rows it changed');
	pg_assert_true(($audit !== false) && ($audit > $guard), 'the audit line after the guard');
}
