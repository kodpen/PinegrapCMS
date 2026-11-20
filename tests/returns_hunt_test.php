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

// A gift card line has nothing to ask for, but it is not in a return: the
// customer's form says why it has no box.
function test_returns_hunt_gift_card_line_on_the_form()
{
	$html = pg_return_form_html(array(array(
		'ship_to_id' => 31,
		'label'      => '',
		'deadline'   => '',
		'lines'      => array(
			array('id' => 11, 'name' => 'Mug', 'quantity' => 1, 'returnable' => 1, 'gift_card' => false),
			array('id' => 12, 'name' => 'Gift card', 'quantity' => 1, 'returnable' => 0, 'gift_card' => true),
		),
	)), array('action' => '/r', 'send_to' => '/e', 'token' => '', 'order_id' => 7));

	pg_assert_contains('Gift cards cannot be returned', $html, 'the gift card line says why');
	pg_assert_false(strpos($html, 'Already in a return') !== false, 'not that it is in a return');

	$view = returns_hunt_test_body('includes/fn/returns.php', 'pg_return_customer_view');
	pg_assert_contains("'gift_card' => !empty(\$line['gift_card'])", $view, 'the view hands the flag to the form');
}

// Every line of the order given back by refunded returns: no purchase is
// left (owner decision #7202), so the waiting review request goes (#10651).
function test_returns_hunt_order_fully_refunded()
{
	pg_assert_true(pg_return_order_refunded_whole(array(
		array('quantity' => 2, 'refunded' => 2),
		array('quantity' => 1, 'refunded' => 1),
	)), 'every unit back and refunded');
	pg_assert_false(pg_return_order_refunded_whole(array(
		array('quantity' => 2, 'refunded' => 1),
		array('quantity' => 1, 'refunded' => 1),
	)), 'one unit kept');
	pg_assert_false(pg_return_order_refunded_whole(array(
		array('quantity' => 2, 'refunded' => 2),
		array('quantity' => 1, 'refunded' => 0),
	)), 'a line not returned (a download, a line sent to no address)');
	pg_assert_true(pg_return_order_refunded_whole(array(
		array('quantity' => 2, 'refunded' => 2),
		array('quantity' => 0, 'refunded' => 0),
	)), 'a line of no quantity counts for nothing');
	pg_assert_false(pg_return_order_refunded_whole(array()), 'no lines');
}

// The refund cancels the order's waiting review request once nothing of the
// order is left with the customer, after the ratings are refreshed.
function test_returns_hunt_refund_cancels_the_review_request()
{
	$refund = returns_hunt_test_body('includes/fn/returns.php', 'pg_return_refund');
	pg_assert_contains("if (pg_return_order_fully_refunded(\$return['order_id'])) {", $refund, 'the order is checked');
	pg_assert_contains("pg_review_request_cancel(\$return['order_id']);", $refund, 'and its request cancelled');
	pg_assert_true(strpos($refund, 'pg_review_refresh_products($returned_products)') < strpos($refund, 'pg_review_request_cancel('), 'after the ratings');
	pg_assert_true(strpos($refund, 'pg_review_request_cancel(') < strpos($refund, '$release();' . "\n\n" . '    $after'), 'under the lock, before it is let go');

	$reader = returns_hunt_test_body('includes/fn/returns.php', 'pg_return_order_fully_refunded');
	pg_assert_contains('order_returns.refunded_at > 0', $reader, 'only refunded returns count');
	pg_assert_contains('saved_for_later = 0', $reader, 'lines kept for later are no part of the order');
}
