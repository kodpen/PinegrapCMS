<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the invoice lines an order turns into, erp_order_lines()
 * (includes/erp/order_bridge.php): the rate each line carries, its tax, and
 * the order discount spread over the lines.
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

// An order_items row as the bridge reads it (order_items.*, the product's
// name and rate). $rate is order_items.tax_rate; false leaves the column out,
// the way a row read before the column existed comes.
function pg_test_erp_order_item($price, $quantity, $tax_total, $rate, $product_rate = null)
{
	$item = array(
		'product_id' => 7,
		'product_name' => 'Item',
		'short_description' => '',
		'quantity' => $quantity,
		'price' => $price,
		'tax_total' => $tax_total,
		'product_tax_rate' => $product_rate,
	);

	if ($rate !== false) {
		$item['tax_rate'] = $rate;
	}

	return $item;
}

// The rate the checkout wrote is the line's rate, even where the line's tax
// is not its own rate x amount: 1.02 carrying 0.21 of a group's 0.41 is at
// 20%, though 21% would give 0.21 on its own.
function test_erp_order_lines_take_the_stored_rate()
{
	$order = array('discount' => 0, 'tax' => 41);
	$built = erp_order_lines($order, array(
		pg_test_erp_order_item(102, 1, 21, '20.000'),
		pg_test_erp_order_item(102, 1, 20, '20.000'),
	));

	pg_assert_same(array(20.0, 20.0), array($built['lines'][0]['tax_rate'], $built['lines'][1]['tax_rate']));
	pg_assert_same(array(21, 20), array($built['lines'][0]['tax_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(245, $built['totals']['grand_total']);
}

// A line written before the column, or with it empty, has its rate worked
// back out of its amounts as before: the product's rate when it explains the
// tax, else the simplest one that does.
function test_erp_order_lines_work_the_rate_out_without_one()
{
	$order = array('discount' => 0, 'tax' => 361 + 20);
	$built = erp_order_lines($order, array(
		pg_test_erp_order_item(2008, 1, 361, false),
		pg_test_erp_order_item(102, 1, 20, null, '20.000'),
	));

	pg_assert_same(18.0, (float) $built['lines'][0]['tax_rate']);
	pg_assert_same(20.0, (float) $built['lines'][1]['tax_rate']);
}

// A zero rate written for an untaxed line stays zero.
function test_erp_order_lines_keep_a_zero_rate()
{
	$built = erp_order_lines(array('discount' => 0, 'tax' => 0), array(pg_test_erp_order_item(500, 2, 0, '0.000')));

	pg_assert_same(0.0, $built['lines'][0]['tax_rate']);
	pg_assert_same(1000, $built['totals']['grand_total']);
}

// An order_items row with its recurring columns, as the bridge reads it with
// the product's start (product_start).
function pg_test_erp_order_recurring_item($price, $period, $start_date, $product_start = 0)
{
	$item = pg_test_erp_order_item($price, 1, erp_apply_rate($price, 20), '20.000');
	$item['recurring_payment_period'] = $period;
	$item['recurring_start_date'] = $start_date;
	$item['product_start'] = $product_start;

	return $item;
}

// The checkout charges every line but a recurring one that starts after the
// order day (submit_order.php, pg_order_line_charged_today()), so those are
// the lines the invoice carries. The day is the order's, not today's; a line
// without a start date starts the product's start days after the order day,
// as at checkout. The rows that stay are indexed from 0 again, as
// erp_order_lines() and the refund dialog count them.
function test_erp_order_charged_items_leave_out_a_recurring_line_that_starts_later()
{
	$order = array('order_date' => strtotime('2026-10-21 14:30:00'));
	$items = array(
		pg_test_erp_order_recurring_item(10000, '', ''),
		pg_test_erp_order_recurring_item(5000, 'Monthly', '2026-11-04'),
		pg_test_erp_order_recurring_item(700, 'Monthly', '2026-10-21'),
		pg_test_erp_order_recurring_item(800, 'Monthly', '0000-00-00', 14),
		pg_test_erp_order_recurring_item(900, 'Monthly', '', 0),
	);

	$charged = erp_order_charged_items($order, $items, false);

	pg_assert_same(array(0, 1, 2), array_keys($charged));
	pg_assert_same(array(10000, 700, 900), array_column($charged, 'price'));
}

// On the ClearCommerce gateway no recurring line is charged with the order:
// the gateway bills them itself.
function test_erp_order_charged_items_leave_out_every_recurring_line_on_clearcommerce()
{
	$order = array('order_date' => strtotime('2026-10-21'));
	$items = array(
		pg_test_erp_order_recurring_item(10000, '', ''),
		pg_test_erp_order_recurring_item(700, 'Monthly', '2026-10-21'),
	);

	pg_assert_same(array(10000), array_column(erp_order_charged_items($order, $items, true), 'price'));
	pg_assert_same(array(10000, 700), array_column(erp_order_charged_items($order, $items, false), 'price'));
}

// The order of the report: a mug of 100.00 and a monthly box of 50.00 that
// starts in 14 days, both at 20%, with a 10.00 order discount. The checkout
// charged the mug alone: 100.00 - 10.00 + 18.00 = 108.00. Built from every line the
// invoice came to more and was refused; built from the charged lines it
// meets the order, and the discount lands on the line that earned it.
function test_erp_order_lines_of_the_charged_lines_meet_the_order()
{
	$order = array('order_date' => strtotime('2026-10-21'), 'discount' => 1000, 'tax' => 1800, 'total' => 10800);
	$items = array(
		pg_test_erp_order_recurring_item(10000, '', ''),
		pg_test_erp_order_recurring_item(5000, 'Monthly', '2026-11-04'),
	);

	$every = erp_order_lines($order, $items);
	pg_assert_same(16800, $every['totals']['grand_total']);

	$built = erp_order_lines($order, erp_order_charged_items($order, $items, false));
	pg_assert_same(1, count($built['lines']));
	pg_assert_same(1000, $built['lines'][0]['discount_amount']);
	pg_assert_same(1800, $built['lines'][0]['tax_total']);
	pg_assert_same(10800, $built['totals']['grand_total']);
}

// A discount re-takes the tax once per rate on the discounted lines and
// spreads it back: 1.03 and 2.03 at 20% less 0.03 come to 1.02 and 2.01,
// 0.61 of VAT on 3.03, 0.21 and 0.40 - not 0.20 and 0.40 with the order's
// kurus put on the larger line afterwards.
function test_erp_order_lines_take_a_discounted_rate_once()
{
	$order = array('discount' => 3, 'tax' => 61);
	$built = erp_order_lines($order, array(
		pg_test_erp_order_item(103, 1, 21, '20.000'),
		pg_test_erp_order_item(203, 1, 40, '20.000'),
	));

	pg_assert_same(array(1, 2), array($built['lines'][0]['discount_amount'], $built['lines'][1]['discount_amount']));
	pg_assert_same(array(21, 40), array($built['lines'][0]['tax_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(306 - 3 + 61, $built['totals']['grand_total']);
}

// A charge's tax is taken with the goods of its rate, on their sum: 1.03 at
// 20% with 0.21 of tax and shipping 1.00. Split alone the shipping is
// 0.83 + 0.17, 0.38 of tax on 1.86, where the tax on 1.86 is 0.37; taken
// together it is 0.84 + 0.16, 0.37 on 1.87, and the total stays 2.24.
function test_erp_order_lines_take_a_charge_tax_with_its_rate()
{
	$built = erp_order_lines(array('discount' => 0, 'tax' => 21, 'shipping' => 100), array(pg_test_erp_order_item(103, 1, 21, '20.000')), true);

	pg_assert_same(2, count($built['lines']));
	pg_assert_same(84, $built['lines'][1]['line_total'], 'the shipping net');
	pg_assert_same(16, $built['lines'][1]['tax_total'], 'the shipping tax');
	pg_assert_same(37, $built['totals']['tax_total'], 'the tax on 1.87');
	pg_assert_same(84, $built['totals']['shipping_total']);
	pg_assert_same(224, $built['totals']['grand_total']);
}

// 39.95 with 7.99 of tax and shipping 9.99 come to 57.93, which no amount at
// 20% reaches: the shipping keeps the split, 8.33 + 1.66, and the total the
// order's. Charged at 9.98, the rate's tax is the tax on its whole.
function test_erp_order_lines_keep_the_split_of_an_unreachable_total()
{
	$item = pg_test_erp_order_item(3995, 1, 799, '20.000');

	$built = erp_order_lines(array('discount' => 0, 'tax' => 799, 'shipping' => 999), array($item), true);
	pg_assert_same(array(833, 166), array($built['lines'][1]['line_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(5793, $built['totals']['grand_total']);

	$built = erp_order_lines(array('discount' => 0, 'tax' => 799, 'shipping' => 998), array($item), true);
	pg_assert_same(5792, $built['totals']['grand_total']);
	pg_assert_same(erp_apply_rate(3995 + $built['lines'][1]['line_total'], 20), $built['totals']['tax_total'], '9.65 on 48.27');
}

// Over two rates a charge whose share would leave a rate's total out of reach
// gives a minor unit to the other rate (pg_order_charge_shares()): 10.00 at
// 20% and 10.00 at 10%, shipping 1.01. In proportion 0.51 would bring the 20%
// lines to 12.51, which no amount reaches; 0.50 and 0.51 bring them to 12.50
// and 11.51, and each rate's tax is the tax on its whole.
function test_erp_order_lines_share_a_charge_between_rates_reachably()
{
	$built = erp_order_lines(array('discount' => 0, 'tax' => 300, 'shipping' => 101), array(
		pg_test_erp_order_item(1000, 1, 200, '20.000'),
		pg_test_erp_order_item(1000, 1, 100, '10.000'),
	), true);

	pg_assert_same(4, count($built['lines']));
	pg_assert_same(50, $built['lines'][2]['line_total'] + $built['lines'][2]['tax_total'], 'the 20% share');
	pg_assert_same(51, $built['lines'][3]['line_total'] + $built['lines'][3]['tax_total'], 'the 10% share');
	pg_assert_same(208, 200 + $built['lines'][2]['tax_total'], 'the tax on 10.42 at 20%');
	pg_assert_same(105, 100 + $built['lines'][3]['tax_total'], 'the tax on 10.46 at 10%');
	pg_assert_same(2401, $built['totals']['grand_total']);
}

// Untaxed charges are one line at 0, whatever the goods.
function test_erp_order_lines_put_untaxed_charges_at_zero()
{
	$built = erp_order_lines(array('discount' => 0, 'tax' => 799, 'shipping' => 999, 'surcharge' => 50), array(pg_test_erp_order_item(3995, 1, 799, '20.000')), false);

	pg_assert_same(3, count($built['lines']));
	pg_assert_same(array(0.0, 999, 0), array($built['lines'][1]['tax_rate'], $built['lines'][1]['line_total'], $built['lines'][1]['tax_total']));
	pg_assert_same(50, $built['totals']['surcharge_total']);
	pg_assert_same(5843, $built['totals']['grand_total']);
}

// An instalment charge the payment service set can leave the rate's total out
// of reach: 57.92 and 2.05 come to 59.97 at 20%. The order's invoice takes it
// at 2.04 when asked to (59.96, the tax on the whole), leaving a kurus to
// the payment; a refund of the lines asks nothing and keeps all of it.
function test_erp_order_lines_settle_an_installment_charge_out_of_reach()
{
	$order = array('discount' => 0, 'tax' => 799, 'shipping' => 998, 'installment_charges' => 205);
	$items = array(pg_test_erp_order_item(3995, 1, 799, '20.000'));

	$built = erp_order_lines($order, $items, true, true);
	$charge = end($built['lines']);
	pg_assert_same(204, $charge['line_total'] + $charge['tax_total'], 'the instalment a kurus lower');
	pg_assert_same(5996, $built['totals']['grand_total']);
	pg_assert_same(1, $built['totals']['charges_left']);
	pg_assert_same(erp_apply_rate(5996 - $built['totals']['tax_total'], 20), $built['totals']['tax_total'], 'the tax on the whole');

	$kept = erp_order_lines($order, $items, true);
	pg_assert_same(5997, $kept['totals']['grand_total'], 'left as charged without asking');
	pg_assert_same(0, $kept['totals']['charges_left']);

	// Reachable as charged, or untaxed: nothing moves.
	$reached = erp_order_lines(array_merge($order, array('installment_charges' => 206)), $items, true, true);
	pg_assert_same(5998, $reached['totals']['grand_total']);
	pg_assert_same(0, $reached['totals']['charges_left']);

	$untaxed = erp_order_lines($order, $items, false, true);
	pg_assert_same(0, $untaxed['totals']['charges_left']);
}

// An order placed before the checkout settled its shipping: 39.95 with 7.99
// of VAT and shipping 9.99 come to 57.93, out of reach at 20%. Asked to, the
// order's invoice takes the shipping at 9.98 (57.92); the surcharge, set
// after the shipping, goes lower in turn when it alone leaves the rate out
// of reach (57.92 + 0.07 = 57.99: 0.06).
function test_erp_order_lines_settle_an_unreachable_shipping_and_surcharge()
{
	$items = array(pg_test_erp_order_item(3995, 1, 799, '20.000'));

	$built = erp_order_lines(array('discount' => 0, 'tax' => 799, 'shipping' => 999), $items, true, true);
	pg_assert_same(998, $built['lines'][1]['line_total'] + $built['lines'][1]['tax_total'], 'the shipping a kurus lower');
	pg_assert_same(5792, $built['totals']['grand_total']);
	pg_assert_same(1, $built['totals']['charges_left']);

	$built = erp_order_lines(array('discount' => 0, 'tax' => 799, 'shipping' => 998, 'surcharge' => 7), $items, true, true);
	pg_assert_same(5798, $built['totals']['grand_total']);
	pg_assert_same(1, $built['totals']['charges_left']);
}

// On random orders with an instalment charge on top of what the checkout
// settled, the order's invoice reaches every rate's total by Paraşüt's
// arithmetic and leaves at most a few kurus of the charge to the payment.
function test_erp_order_lines_settled_installment_reaches_every_rate()
{
	mt_srand(20261024);
	$rates = array('1.000', '10.000', '20.000', '20.000', '20.000');
	$left_runs = 0;

	for ($run = 0; $run < 1500; $run++) {
		$lines = array();

		for ($i = 0, $count = mt_rand(1, 4); $i < $count; $i++) {
			$lines[800 + $i] = array('amount' => mt_rand(100, (mt_rand(0, 1) ? 5000 : 90000)), 'rate' => $rates[mt_rand(0, count($rates) - 1)]);
		}

		$taxes = pg_order_line_taxes($lines);
		$charged = array();
		$items = array();

		foreach ($lines as $id => $line) {
			$charged[$id] = array('amount' => $line['amount'], 'rate' => $line['rate'], 'tax' => $taxes[$id]);
			$items[] = pg_test_erp_order_item($line['amount'], 1, $taxes[$id], $line['rate']);
		}

		$subtotal = array_sum(array_column($lines, 'amount'));
		$discount = mt_rand(0, 3) ? 0 : mt_rand(1, (int) ($subtotal / 2));
		$tax = ($discount > 0) ? pg_order_charged_tax($charged, $discount) : array_sum($taxes);
		$settled = pg_order_charges_reachable($charged, $discount, array('shipping' => mt_rand(0, 1) ? mt_rand(1, 3000) : 0, 'surcharge' => 0));
		$installment = mt_rand(1, 4000);
		$order = array('discount' => $discount, 'tax' => $tax, 'shipping' => $settled['shipping'], 'surcharge' => 0, 'installment_charges' => $installment);
		$paid = $subtotal - $discount + $tax + $settled['shipping'] + $installment;

		$built = erp_order_lines($order, $items, true, true);
		$left = $built['totals']['charges_left'];

		pg_assert_true(($left >= 0) && ($left <= 10), 'run ' . $run . ' left ' . $left);
		pg_assert_same($paid, $built['totals']['grand_total'] + $left, 'run ' . $run . ' the payment');
		$left_runs += ($left > 0) ? 1 : 0;

		$nets = array();
		$vats = array();

		foreach ($built['lines'] as $line) {
			$key = number_format((float) $line['tax_rate'], 3, '.', '');
			$nets[$key] = ($nets[$key] ?? 0) + $line['line_total'] - $line['discount_amount'];
			$vats[$key] = ($vats[$key] ?? 0) + $line['tax_total'];
		}

		foreach ($nets as $key => $net) {
			if ((float) $key > 0) {
				pg_assert_same(erp_apply_rate($net, (float) $key), $vats[$key], 'run ' . $run . ' at ' . $key);
			}
		}
	}

	pg_assert_true($left_runs > 0, 'some instalment charges were lowered');
}
