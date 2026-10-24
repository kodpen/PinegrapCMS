<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the store's one order status rule (includes/fn/order_status.php,
 * pg_order_status_move()): what each move between the statuses asks of the
 * store, the sentence a refused move gets, and the order screen's full
 * refund, which cancels through process_order_cancellation() instead of
 * writing the status by hand.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every move between the four statuses the column holds: from (row) => to
// (column) => answer.
function order_status_move_test_matrix()
{
	return array(
		'incomplete' => array('incomplete' => 'none', 'complete' => 'refuse', 'exported' => 'refuse', 'cancelled' => 'refuse'),
		'complete'   => array('incomplete' => 'refuse', 'complete' => 'none', 'exported' => 'set', 'cancelled' => 'cancel'),
		'exported'   => array('incomplete' => 'refuse', 'complete' => 'set', 'exported' => 'none', 'cancelled' => 'cancel'),
		'cancelled'  => array('incomplete' => 'refuse', 'complete' => 'restore', 'exported' => 'restore', 'cancelled' => 'none'),
	);
}

function test_order_status_move_full_matrix()
{
	foreach (order_status_move_test_matrix() as $from => $answers) {
		foreach ($answers as $to => $answer) {
			pg_assert_same($answer, pg_order_status_move($from, $to), $from . ' to ' . $to);
		}
	}
}

function test_order_status_move_none_and_set()
{
	pg_assert_same('none', pg_order_status_move('complete', 'complete'), 'the same status moves nothing');
	pg_assert_same('none', pg_order_status_move('cancelled', 'cancelled'), 'a cancelled order asked to cancel stays as it is');
	pg_assert_same('set', pg_order_status_move('complete', 'exported'), 'complete to exported is a plain write');
	pg_assert_same('set', pg_order_status_move('exported', 'complete'), 'and back');

	// A status lost to the old single-L value ('') may be set or cancelled.
	pg_assert_same('set', pg_order_status_move('', 'complete'), 'a lost status set to complete');
	pg_assert_same('set', pg_order_status_move('', 'exported'), 'or exported');
	pg_assert_same('cancel', pg_order_status_move('', 'cancelled'), 'or cancelled through the cancellation');
}

function test_order_status_move_cancel_and_restore()
{
	pg_assert_same('cancel', pg_order_status_move('complete', 'cancelled'), 'complete to cancelled');
	pg_assert_same('cancel', pg_order_status_move('exported', 'cancelled'), 'exported to cancelled');

	// pg_order_restore_cancelled() takes only these two targets.
	pg_assert_same('restore', pg_order_status_move('cancelled', 'complete'), 'cancelled to complete');
	pg_assert_same('restore', pg_order_status_move('cancelled', 'exported'), 'cancelled to exported');
}

function test_order_status_move_refuses()
{
	// incomplete is the cart's status, either way.
	foreach (array('complete', 'exported', 'cancelled', '') as $status) {
		pg_assert_same('refuse', pg_order_status_move($status, 'incomplete'), $status . ' is not turned into a cart');
		pg_assert_same('refuse', pg_order_status_move('incomplete', $status), 'a cart is not made ' . $status);
	}

	// Out of cancelled only to complete or exported, the restore's targets.
	foreach (array('', 'canceled', 'shipped', 'Complete') as $status) {
		pg_assert_same('refuse', pg_order_status_move('cancelled', $status), 'cancelled to ' . var_export($status, true));
	}

	// The single-L spelling is never written and never moved from.
	pg_assert_same('refuse', pg_order_status_move('complete', 'canceled'), 'canceled with one L is not written');
	pg_assert_same('refuse', pg_order_status_move('canceled', 'complete'), 'nor moved out of');
	pg_assert_same('refuse', pg_order_status_move('canceled', 'cancelled'), 'nor cancelled again');

	// Values the column does not hold.
	pg_assert_same('refuse', pg_order_status_move('complete', ''), 'an empty status is not written');
	pg_assert_same('refuse', pg_order_status_move('complete', 'shipped'), 'nor an unknown one');
}

function test_order_status_move_reads_its_arguments_as_strings()
{
	pg_assert_same('none', pg_order_status_move(null, ''), 'null is the empty status');
	pg_assert_same('set', pg_order_status_move(null, 'complete'), 'a null status set to complete');
}

// The basket sentence when either side is incomplete, a plain one otherwise;
// both name the two statuses.
function test_order_status_move_refusal_sentence()
{
	$basket = pg_order_status_move_refusal('complete', 'incomplete');

	pg_assert_same(lang(array(
		'string' => 'An order cannot be moved from {var:1} to {var:2}: a basket becomes an order only through the checkout, and an order does not go back to being a basket.',
		'vars'   => array('complete', 'incomplete'))), $basket, 'an order to a basket');
	pg_assert_contains('incomplete', pg_order_status_move_refusal('incomplete', 'cancelled'), 'a basket to cancelled');

	$other = pg_order_status_move_refusal('cancelled', 'canceled');

	pg_assert_same(lang(array('string' => 'An order cannot be moved from {var:1} to {var:2}.', 'vars' => array('cancelled', 'canceled'))), $other, 'any other refusal');
	pg_assert_false(strpos($other, 'basket') !== false, 'says nothing about a basket');
}

// A full refund on the order screen cancels the order through the store's
// cancellation, without a second void, and no status is written by hand.
function test_order_status_full_refund_cancels_through_the_helper()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');

	$start = strpos($source, "get_field_value('submit_payment_refund') == 'refund'");
	$end = ($start === false) ? false : strpos($source, "get_field_value('submit_mark_paid') == 'paid'", $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the refund handler is found');

	$handler = substr($source, $start, $end - $start);
	$recorded = strpos($handler, 'UPDATE orders SET refunded_amount');
	$cancelled = strpos($handler, 'process_order_cancellation(');
	$audited = strpos($handler, "pg_audit_record('refund'");

	pg_assert_true(($recorded !== false) && ($cancelled !== false) && ($audited !== false) && ($recorded < $cancelled) && ($cancelled < $audited), 'refund recorded, then cancelled, then one audit line');
	pg_assert_contains("lang('The full amount was refunded.')", $handler, 'the reason the customer reads');
	pg_assert_true((bool) preg_match('/process_order_cancellation\(\s*\$order_id,\s*lang\(\'The full amount was refunded\.\'\),\s*true,[^\n]*\n\s*\(int\) \(isset\(\$user\[\'id\'\]\) \? \$user\[\'id\'\] : 0\),[^\n]*\n\s*false\s/', $handler), 'as staff, with the operator\'s id and no void');

	pg_assert_same(0, preg_match('/UPDATE\s+orders\s+SET[^"]*\bstatus\s*=/i', $source), 'view_order.php writes no order status by hand');
}
