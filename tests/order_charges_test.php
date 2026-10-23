<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the charges an order adds to its lines when they hold tax
 * (includes/fn/ecommerce.php): pg_tax_rate_amount() and
 * pg_order_charges_reachable(), the shipping and surcharge the checkout
 * charges so that every rate's total is one the tax, taken on the whole, can
 * reach. The order's invoice (erp_order_lines(), erp_rate_amount()) must then
 * take each rate's tax on the sum of its lines, goods and charges together,
 * the way an e-document provider takes it.
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

// The checkout's copy gives the ERP's amount for every total and rate: at
// 20% 57.92 is 48.27 + 9.65, 57.94 is 48.28 + 9.66, 57.93 none.
function test_order_tax_rate_amount_matches_the_erp()
{
	pg_assert_same(4827, pg_tax_rate_amount(5792, 20));
	pg_assert_same(4828, pg_tax_rate_amount(5794, 20));
	pg_assert_same(null, pg_tax_rate_amount(5793, 20), '57.93 at 20%');

	mt_srand(20261022);
	$rates = array(1, 8, 10, 18, 20, 7.5, 0.5, 26);

	for ($run = 0; $run < 5000; $run++) {
		$total = mt_rand(0, (mt_rand(0, 1) ? 2000 : 2000000));
		$rate = $rates[mt_rand(0, count($rates) - 1)];

		pg_assert_same(erp_rate_amount($total, $rate), pg_tax_rate_amount($total, $rate), 'total ' . $total . ' at ' . $rate);
	}
}

// 39.95 with 7.99 of tax and shipping 9.99 come to 57.93, which no amount at
// 20% reaches: the shipping is charged at 9.98. 9.98 itself is left alone.
function test_order_charges_reachable_lowers_the_shipping_a_kurus()
{
	$lines = array(442 => array('amount' => 3995, 'rate' => '20.000'));

	pg_assert_same(array('shipping' => 998), pg_order_charges_reachable($lines, 0, array('shipping' => 999)));
	pg_assert_same(array('shipping' => 998), pg_order_charges_reachable($lines, 0, array('shipping' => 998)));
	pg_assert_same(array('shipping' => 0), pg_order_charges_reachable($lines, 0, array('shipping' => 0)));
}

// Goods at 0% or lines without a kept rate leave the charges as they are.
function test_order_charges_reachable_leaves_untaxed_and_unrated_lines()
{
	pg_assert_same(array('shipping' => 999), pg_order_charges_reachable(array(1 => array('amount' => 3995, 'rate' => '0.000')), 0, array('shipping' => 999)));
	pg_assert_same(array('shipping' => 999), pg_order_charges_reachable(array(1 => array('amount' => 3995, 'rate' => null)), 0, array('shipping' => 999)));
	pg_assert_same(array('shipping' => 999), pg_order_charges_reachable(array(), 0, array('shipping' => 999)));
}

// The surcharge is settled after the shipping, with the shipping's share in
// the rate's total: 39.95 + 7.99 + 9.98 = 57.92, and a surcharge of 0.01
// would make 57.93, so it is charged at nothing more than it can be.
function test_order_charges_reachable_settles_the_surcharge_after_the_shipping()
{
	$lines = array(1 => array('amount' => 3995, 'rate' => 20));

	$settled = pg_order_charges_reachable($lines, 0, array('shipping' => 999, 'surcharge' => 2));
	pg_assert_same(998, $settled['shipping']);
	pg_assert_same(2, $settled['surcharge'], '57.92 + 0.02 = 57.94 is reached');

	$settled = pg_order_charges_reachable($lines, 0, array('shipping' => 998, 'surcharge' => 7));
	pg_assert_same(6, $settled['surcharge'], '57.92 + 0.07 = 57.99 is out of reach, 57.98 is not');

	$settled = pg_order_charges_reachable($lines, 0, array('shipping' => 998, 'surcharge' => 1));
	pg_assert_same(1, $settled['surcharge'], 'a single minor unit is never taken off to nothing');
}

// On random orders the invoice made from what the checkout charges takes
// each rate's tax on the sum of its lines, goods and charges together, and
// comes to the order's total.
function test_order_charges_reachable_meet_the_invoice()
{
	mt_srand(20261023);
	$rates = array('0.000', '1.000', '10.000', '20.000', '20.000');
	$lowered = 0;

	for ($run = 0; $run < 1500; $run++) {
		$lines = array();
		$count = mt_rand(1, 8);

		for ($i = 0; $i < $count; $i++) {
			$lines[300 + $i] = array(
				'amount' => mt_rand(1, (mt_rand(0, 1) ? 2000 : 150000)),
				'rate' => $rates[mt_rand(0, count($rates) - 1)],
			);
		}

		$subtotal = array_sum(array_column($lines, 'amount'));
		$discount = mt_rand(0, 2) ? 0 : mt_rand(1, $subtotal);
		$taxes = pg_order_line_taxes($lines);
		$charged = array();
		$items = array();

		foreach ($lines as $id => $line) {
			$charged[$id] = array('amount' => $line['amount'], 'rate' => $line['rate'], 'tax' => $taxes[$id]);
			$items[] = array(
				'product_id' => 7,
				'product_name' => 'Item',
				'short_description' => '',
				'quantity' => 1,
				'price' => $line['amount'],
				'tax_total' => $taxes[$id],
				'tax_rate' => $line['rate'],
			);
		}

		$tax = ($discount > 0) ? pg_order_charged_tax($charged, $discount) : array_sum($taxes);
		$shipping = mt_rand(0, 3) ? mt_rand(1, 5000) : 0;
		$surcharge = mt_rand(0, 4) ? 0 : mt_rand(1, 900);
		$settled = pg_order_charges_reachable($charged, $discount, array('shipping' => $shipping, 'surcharge' => $surcharge));

		pg_assert_true(($shipping - $settled['shipping'] >= 0) && ($shipping - $settled['shipping'] <= 10), 'run ' . $run . ' shipping');
		pg_assert_true(($surcharge - $settled['surcharge'] >= 0) && ($surcharge - $settled['surcharge'] <= 10), 'run ' . $run . ' surcharge');
		$lowered += ($shipping - $settled['shipping']) + ($surcharge - $settled['surcharge']);

		$order = array('discount' => $discount, 'tax' => $tax, 'shipping' => $settled['shipping'], 'surcharge' => $settled['surcharge']);
		$built = erp_order_lines($order, $items, true);

		pg_assert_same($subtotal - $discount + $tax + $settled['shipping'] + $settled['surcharge'], $built['totals']['grand_total'], 'run ' . $run . ' total');

		// Each rate's tax is the tax on the sum of its lines' amounts.
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

	pg_assert_true($lowered > 0, 'some charges were lowered');
}

// A card instalment charge is settled after the shipping and the surcharge
// as charged: 57.92 and 2.05 come to 59.97, out of reach at 20%, so 2.04 is
// taken; 2.06 (59.98) is reached as it is. A single minor unit is never
// taken to nothing, and lines without a kept rate leave it alone.
function test_order_installment_charge_goes_a_kurus_lower()
{
	$lines = array(442 => array('amount' => 3995, 'rate' => '20.000', 'tax' => 799));
	$charges = array('shipping' => 998, 'surcharge' => 0);

	pg_assert_same(204, pg_order_installment_charge($lines, 0, $charges, 205));
	pg_assert_same(206, pg_order_installment_charge($lines, 0, $charges, 206));
	pg_assert_same(0, pg_order_installment_charge($lines, 0, $charges, 0));
	pg_assert_same(205, pg_order_installment_charge(array(), 0, $charges, 205));
	pg_assert_same(205, pg_order_installment_charge(array(1 => array('amount' => 3995, 'rate' => null)), 0, $charges, 205));

	// With a second rate the charge is shared so that both are reached, at
	// the price the bank quoted.
	$two = array(
		442 => array('amount' => 3995, 'rate' => '20.000', 'tax' => 799),
		443 => array('amount' => 49995, 'rate' => '10.000', 'tax' => 5000),
	);
	pg_assert_same(205, pg_order_installment_charge($two, 0, $charges, 205));
}
