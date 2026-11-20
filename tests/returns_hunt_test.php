<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults found in customer returns: the refund due date of a
 * return whose order can no longer be refunded (cancelled), and the line of
 * a gift card on the customer's return form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one top-level function, up to the next one.
function returns_hunt_test_body($relative, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A cancelled order's money goes back with the cancellation and its return
// is never refunded (pg_return_refund_order_open()): no refund falls due, so
// no reminder rings and no date shows as due or overdue.
function test_returns_hunt_refund_due_of_a_cancelled_order()
{
	$shipped = gmmktime(12, 0, 0, 10, 10, 2026);
	$row = array('status' => 'shipped', 'shipped_at' => $shipped, 'received_at' => 0);

	pg_assert_same('2026-10-24', pg_return_refund_due($row), 'no order status given: as before');
	pg_assert_same('2026-10-24', pg_return_refund_due($row, 'complete'), 'a completed order');
	pg_assert_same('2026-10-24', pg_return_refund_due($row, 'exported'), 'an exported order');
	pg_assert_same('', pg_return_refund_due($row, 'cancelled'), 'a cancelled order');
	pg_assert_same('', pg_return_refund_due($row, 'incomplete'), 'an incomplete order');
	pg_assert_same('', pg_return_refund_due($row, ''), 'an order that is gone');
}

// Every reader of the due date passes the order's status.
function test_returns_hunt_refund_due_callers_pass_the_order_status()
{
	$remind = returns_hunt_test_body('includes/fn/returns.php', 'pg_return_remind_refunds');
	pg_assert_contains('orders.status AS order_status', $remind, 'the reminder reads the order status');
	pg_assert_contains("pg_return_refund_due(\$row, (string) \$row['order_status'])", $remind, 'and passes it');

	$rows = returns_hunt_test_body('includes/fn/returns.php', 'pg_returns_panel_rows');
	pg_assert_contains('orders.status AS order_status', $rows, 'the list rows carry the order status');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_returns.php');
	pg_assert_contains("pg_return_refund_due(\$row, (string) \$row['order_status'])", $list, 'the list passes it');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_return.php');
	pg_assert_contains("pg_return_refund_due(\$return, \$order ? (string) \$order['status'] : '')", $screen, 'the return screen passes it');

	$view = returns_hunt_test_body('includes/fn/returns.php', 'pg_return_customer_view');
	pg_assert_contains("\$shown['refund_deadline'] = ''", $view, 'the customer reads no refund date on a cancelled order');
}
