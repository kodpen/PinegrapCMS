<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the value that adds a refund to an order's refunded total. The
 * order screen's refund reads the total before the payment service call; a
 * return or store credit refund recorded during that call adds to the
 * column, so the screen must add as well, never write back the total it
 * read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_refunded_amount_adds_to_the_column()
{
	pg_assert_same('COALESCE(refunded_amount, 0) + 1250', pg_order_refunded_add_expr(1250));
}

function test_order_refunded_amount_takes_whole_minor_units()
{
	// A float or string amount is rounded, never truncated.
	pg_assert_same('COALESCE(refunded_amount, 0) + 1000', pg_order_refunded_add_expr(999.6));
	pg_assert_same('COALESCE(refunded_amount, 0) + 5', pg_order_refunded_add_expr('5'));
}

function test_order_refunded_amount_screen_adds_and_reads_back()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');

	$start = strpos($source, "get_field_value('submit_payment_refund') == 'refund'");
	$end = ($start === false) ? false : strpos($source, "get_field_value('submit_mark_paid') == 'paid'", $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the refund handler is found');

	$handler = substr($source, $start, $end - $start);

	pg_assert_same(0, preg_match('/refunded_amount\s*=\s*\'"/', $handler), 'no read total written back');
	pg_assert_contains('UPDATE orders SET refunded_amount = " . pg_order_refunded_add_expr($refund_cents)', $handler, 'the refund is added to the column');

	$added = strpos($handler, 'pg_order_refunded_add_expr(');
	$read_back = strpos($handler, 'SELECT COALESCE(refunded_amount, 0) FROM orders');
	$full = strpos($handler, 'if ($new_refunded_total >= ');

	pg_assert_true(($added !== false) && ($read_back > $added) && ($full > $read_back), 'the full-refund check reads the total after the add');
}
