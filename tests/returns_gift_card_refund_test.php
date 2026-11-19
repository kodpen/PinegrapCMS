<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the refund of a return whose order was paid in part or in whole
 * with gift cards or store credit (includes/fn/returns.php): a payment
 * service gives back at most the payment money left, store credit and a
 * recorded refund also give back the gift card part still owed, and that part
 * is written as an order_refunds row of type return_gift_card that adds
 * nothing to orders.refunded_amount.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function returns_gift_card_refund_body($file, $function)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' exists');

	return substr($source, $start, strpos($source, "\n}\n", $start) - $start);
}

// An order paid wholly with store credit: nothing is left of the payment, the
// whole refund is the gift card part.
function test_returns_gift_card_refund_split_all_credit()
{
	pg_assert_same(array('payment' => 0, 'gift_card' => 10000), pg_return_refund_split('credit', 10000, 0, 10000), 'credit');
	pg_assert_same(array('payment' => 0, 'gift_card' => 10000), pg_return_refund_split('manual', 10000, 0, 10000), 'manual');
	pg_assert_same(array('payment' => 0, 'gift_card' => 4000), pg_return_refund_split('credit', 4000, 0, 10000), 'part of it');
	pg_assert_same(null, pg_return_refund_split('credit', 10001, 0, 10000), 'one kurus more is refused');
	pg_assert_same(null, pg_return_refund_split('driver', 100, 0, 10000), 'a payment service cannot give back what a card paid');
}

// 60.00 of credit and 40.00 by card: the payment part comes first.
function test_returns_gift_card_refund_split_mixed()
{
	pg_assert_same(array('payment' => 4000, 'gift_card' => 6000), pg_return_refund_split('credit', 10000, 4000, 6000), 'whole order as credit');
	pg_assert_same(array('payment' => 3000, 'gift_card' => 0), pg_return_refund_split('credit', 3000, 4000, 6000), 'within the payment');
	pg_assert_same(array('payment' => 4000, 'gift_card' => 1000), pg_return_refund_split('manual', 5000, 4000, 6000), 'recorded, beyond the payment');
	pg_assert_same(array('payment' => 4000, 'gift_card' => 0), pg_return_refund_split('driver', 4000, 4000, 6000), 'driver up to the payment');
	pg_assert_same(null, pg_return_refund_split('driver', 4001, 4000, 6000), 'driver beyond the payment');
	pg_assert_same(null, pg_return_refund_split('manual', 10001, 4000, 6000), 'beyond both');
}

// A gift card part a cancellation or an earlier return gave back is not owed:
// the caller passes what is still owed, and nothing below 0 counts.
function test_returns_gift_card_refund_split_edges()
{
	pg_assert_same(array('payment' => 0, 'gift_card' => 0), pg_return_refund_split('manual', 0, 0, 0), 'a recorded 0');
	pg_assert_same(array('payment' => 2000, 'gift_card' => 0), pg_return_refund_split('credit', 2000, 2000, -500), 'owed below 0 reads 0');
	pg_assert_same(null, pg_return_refund_split('credit', 2500, 2000, -500), 'nothing owed, nothing beyond the payment');
	pg_assert_same(null, pg_return_refund_split('credit', -1, 2000, 0), 'a negative amount');
	pg_assert_same(array('payment' => 2000, 'gift_card' => 0), pg_return_refund_split('anything', 2000, 2000, 500), 'an unknown method is held to the payment');
}

// The plan reads the gift card part still owed and caps the suggestion by
// what the default method can give back.
function test_returns_gift_card_refund_plan_reads_owed()
{
	$body = returns_gift_card_refund_body('includes/fn/returns.php', 'pg_return_refund_plan');

	pg_assert_contains('pg_order_gift_card_owed(', $body, 'reads the owed gift card part');
	pg_assert_contains("'gift_card_owed'", $body, 'returns it');
	pg_assert_contains("'credit_remaining'", $body, 'and the cap of credit and recorded refunds');
}

// The refund splits the amount, grows refunded_amount by the payment part
// only, writes the gift card part as its own row and puts the bonus on the
// payment part only.
function test_returns_gift_card_refund_writes_rows()
{
	$body = returns_gift_card_refund_body('includes/fn/returns.php', 'pg_return_refund');

	pg_assert_contains('pg_return_refund_split(', $body, 'splits the amount');
	pg_assert_contains("'return_gift_card'", $body, 'writes the gift card row');
	pg_assert_contains("pg_order_refunded_add_expr(\$split['payment'])", $body, 'refunded_amount grows by the payment part');
	pg_assert_contains("\$split['payment'])", $body, 'bonus base is the payment part');
	pg_assert_false(strpos($body, "if (\$amount > \$plan['remaining'])") !== false, 'no flat payment cap left');
}

// The API refund checks the same split.
function test_returns_gift_card_refund_api_split()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/returns.php');

	pg_assert_contains('pg_return_refund_split(', $source, 'API uses the split');
	pg_assert_false(strpos($source, "if (\$amount > (int)\$plan['remaining'])") !== false, 'no flat payment cap left');
}
