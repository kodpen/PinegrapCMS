<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_order_print_totals() in includes/fn/order_view.php: the
 * customer's printed invoice (order_invoice_print.php) lists every figure
 * that makes up the order total, so its rows add up to the total it prints.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _order_print_totals_order($figures = array())
{
	return $figures + array(
		'subtotal' => 10000, 'discount' => 0, 'tax' => 0, 'shipping' => 0, 'surcharge' => 0,
		'gift_card_discount' => 0, 'installment_charges' => 0, 'total' => 10000,
	);
}

// The signed rows above the total, summed.
function _order_print_totals_sum($rows)
{
	$sum = 0;
	foreach ($rows as $row) {
		if (empty($row['grand'])) {
			$sum += $row['negative'] ? -$row['amount'] : $row['amount'];
		}
	}
	return $sum;
}

function _order_print_totals_keys($rows)
{
	return array_map(function ($row) { return $row['key']; }, $rows);
}

// A plain order: the subtotal and the total.
function test_order_print_totals_plain_order()
{
	$rows = pg_order_print_totals(_order_print_totals_order());
	pg_assert_same(array('subtotal', 'total'), _order_print_totals_keys($rows));
	pg_assert_true(end($rows)['grand'], 'the total is the last, grand row');
}

// A gift card lowers the total: its row is printed, as a deduction.
function test_order_print_totals_gift_card_row()
{
	$order = _order_print_totals_order(array('shipping' => 1500, 'gift_card_discount' => 4000, 'total' => 7500));
	$rows = pg_order_print_totals($order);
	pg_assert_same(array('subtotal', 'shipping', 'gift_card', 'total'), _order_print_totals_keys($rows));
	pg_assert_true($rows[2]['negative'], 'the gift card is taken off');
	pg_assert_same(7500, _order_print_totals_sum($rows), 'the rows add up to the total');
}

// A bank instalment difference raises the total: its row is printed.
function test_order_print_totals_installment_charge_row()
{
	$order = _order_print_totals_order(array('discount' => 1000, 'tax' => 1800, 'installment_charges' => 520,
		'payment_installment' => 3, 'total' => 11320));
	$rows = pg_order_print_totals($order);
	pg_assert_same(array('subtotal', 'discount', 'tax', 'installment_charges', 'total'), _order_print_totals_keys($rows));
	pg_assert_false($rows[3]['negative']);
	pg_assert_same(11320, _order_print_totals_sum($rows), 'the rows add up to the total');
}

// An order row without the newer columns still prints.
function test_order_print_totals_missing_columns()
{
	$rows = pg_order_print_totals(array('subtotal' => 500, 'total' => 500));
	pg_assert_same(array('subtotal', 'total'), _order_print_totals_keys($rows));
	pg_assert_same(500, $rows[1]['amount']);
}
