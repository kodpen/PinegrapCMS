<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the card refund of an order that has no invoice yet
 * (includes/erp/order_refund.php): the order's own lines, built the way the
 * invoice would be, go through erp_return_build() with the taxes given back
 * so far at 0, so a line given back whole takes the tax the order charged on
 * it and "Everything" comes to the order's total.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/returns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_refund.php');

// A cart at 20% VAT with an order discount, as the checkout writes it: the
// order_items rows (tax_total the whole line, the rate on the line) and the
// order's header figures (submit_order.php: the discount taken off the tax
// once, by its share of the subtotal).
function pg_test_erp_order_refund_cart($cart, $discount)
{
	$items = array();
	$subtotal = 0;
	$tax = 0;

	foreach ($cart as $index => $entry) {
		list($quantity, $price) = $entry;
		$line_total = $price * $quantity;
		$line_tax = erp_apply_rate($line_total, 20);
		$items[] = array(
			'id' => 100 + $index,
			'product_id' => 10 + $index,
			'product_name' => 'Item ' . $index,
			'short_description' => '',
			'quantity' => $quantity,
			'price' => $price,
			'tax_total' => $line_tax,
			'tax_rate' => '20.000',
			'product_tax_rate' => '20.000',
		);
		$subtotal += $line_total;
		$tax += $line_tax;
	}

	if ($discount > 0) {
		$tax -= (int) round($tax * ($discount / $subtotal));
	}

	$order = array(
		'order_date' => strtotime('2026-10-21'),
		'discount' => $discount,
		'tax' => $tax,
		'shipping' => 0,
		'surcharge' => 0,
		'installment_charges' => 0,
		'gift_card_discount' => 0,
		'total' => $subtotal - $discount + $tax,
	);

	return array($order, $items);
}

// The refund source of an order without an invoice, as
// erp_order_refund_lines() builds it from the rows.
function pg_test_erp_order_refund_source($order, $items)
{
	$built = erp_order_lines($order, $items);

	return array('source' => 'order', 'invoice_id' => 0, 'invoice_number' => '',
		'lines' => erp_order_refund_order_lines($built['lines'], $items));
}

// Everything chosen: every line at its full quantity.
function pg_test_erp_order_refund_everything($source)
{
	$quantities = array();

	foreach ($source['lines'] as $line) {
		$quantities[(int) $line['id']] = (int) $line['quantity'];
	}

	return erp_order_refund_amount($source, $quantities);
}

// The two discounted carts of the report: the invoice the order would get
// meets the order's total, and so does the refund of everything - 113.06 and
// 229.90, not a kurus short. Each line's tax was tied to the order's header;
// taken again on the sum of the lines it came to 113.05 and 229.89, the card
// got a kurus less and the order was never marked cancelled.
function test_erp_order_refund_of_everything_meets_the_order()
{
	foreach (array(
		array(array(array(2, 2215), array(3, 1421), array(1, 3083)), 2355, 11306),
		array(array(array(3, 5803), array(2, 2053), array(1, 5093)), 7450, 22990),
	) as $case) {
		list($cart, $discount, $total) = $case;
		list($order, $items) = pg_test_erp_order_refund_cart($cart, $discount);
		pg_assert_same($total, $order['total']);
		pg_assert_same($total, erp_order_lines($order, $items)['totals']['grand_total']);

		$refund = pg_test_erp_order_refund_everything(pg_test_erp_order_refund_source($order, $items));
		pg_assert_same('', $refund['error']);
		pg_assert_same($total, $refund['amount']);
		pg_assert_true($refund['is_full']);
		pg_assert_same($total, array_sum($refund['lines']));
	}
}

// The shipping of 39.99 with the tax inside it: the line carries 33.33 and
// 6.66 (erp_split_gross()), and given back it is 39.99 again, not the 40.00
// that 20% on 33.33 comes to. The line is the shipping line erp_order_lines()
// builds, keyed -1 as the dialog keys it.
function test_erp_order_refund_of_the_shipping_line_gives_back_what_was_charged()
{
	$shipping = array(
		'product_id' => 0,
		'description' => 'Shipping',
		'quantity' => 1,
		'unit_code' => 'C62',
		'unit_price' => 3333,
		'discount_amount' => 0,
		'tax_rate' => 20.0,
		'tax_total' => 666,
		'line_total' => 3333,
	);

	$lines = erp_order_refund_order_lines(array($shipping), array());
	pg_assert_same(-1, $lines[0]['id']);

	$refund = erp_order_refund_amount(array('source' => 'order', 'invoice_id' => 0, 'invoice_number' => '', 'lines' => $lines), array(-1 => 1));
	pg_assert_same(3999, $refund['amount']);
}

// A piece of a line gives back no more tax than the line carries, and the
// pieces together give back exactly the line's tax: 2 x 5.00 charged 0.50 of
// tax (the header's figure), one back is 0.50 at most, not the 1.00 that 20%
// on 5.00 comes to, and the second takes what is left.
function test_erp_order_refund_of_a_piece_is_bounded_by_the_line_tax()
{
	list($order, $items) = pg_test_erp_order_refund_cart(array(array(2, 500)), 0);
	$items[0]['tax_total'] = 50;
	$order['tax'] = 50;
	$order['total'] = 1050;

	$source = pg_test_erp_order_refund_source($order, $items);
	pg_assert_same(50, $source['lines'][0]['tax_total']);
	pg_assert_same(0, $source['lines'][0]['returned_tax']);

	$one = erp_order_refund_amount($source, array(100 => 1));
	pg_assert_same(550, $one['amount']);
	pg_assert_false($one['is_full']);

	$both = erp_order_refund_amount($source, array(100 => 2));
	pg_assert_same(1050, $both['amount']);
	pg_assert_true($both['is_full']);
}
