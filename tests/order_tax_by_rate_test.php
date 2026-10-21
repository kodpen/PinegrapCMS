<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order's taxes taken per rate (includes/fn/ecommerce.php):
 * pg_tax_by_rate(), pg_tax_spread(), the line taxes the checkout writes
 * (pg_order_line_taxes()) and its tax after an order discount
 * (pg_order_charged_tax()). They must be the figures the ERP gives the same
 * lines (erp_rate_vat(), erp_allocate(), erp_order_lines()) and the ones an
 * e-document provider works out from the rates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Two lines of 1.02 at 20% carry 0.41 between them, taken on 2.04, not
// 0.20 + 0.20; the kurus of the spread goes to the first line.
function test_order_tax_by_rate_takes_the_group()
{
	pg_assert_same(array(11 => 21, 12 => 20), pg_tax_by_rate(array(
		11 => array('amount' => 102, 'rate' => 20),
		12 => array('amount' => 102, 'rate' => '20.000'),
	)));
}

// Rates are groups of their own; no rate is no tax; a negative amount is
// taxed alone, half away from zero.
function test_order_tax_by_rate_keeps_rates_apart()
{
	pg_assert_same(
		array('a' => 125, 'b' => 13, 'c' => 0, 'd' => 0, 'e' => -21),
		pg_tax_by_rate(array(
			'a' => array('amount' => 1245, 'rate' => 10),
			'b' => array('amount' => 1250, 'rate' => 1),
			'c' => array('amount' => 999, 'rate' => 0),
			'd' => array('amount' => 500),
			'e' => array('amount' => -105, 'rate' => 20),
		))
	);
}

// The spread adds up, puts the remainder on the largest remainders, the
// first of equals first, and on the first line when there is no weight.
function test_order_tax_spread_hands_out_the_remainder()
{
	pg_assert_same(array(34, 33, 33), pg_tax_spread(100, array(1, 1, 1)));
	pg_assert_same(array('x' => 0, 'y' => 5), pg_tax_spread(5, array('x' => 1, 'y' => 99)));
	pg_assert_same(array(7, 0), pg_tax_spread(7, array(0, 0)));
	pg_assert_same(array(), pg_tax_spread(7, array()));
}

// On random orders the checkout's taxes are the ERP's, line for line
// (erp_rate_vat() and erp_allocate(), includes/erp/money.php).
function test_order_tax_by_rate_matches_the_erp()
{
	mt_srand(20261021);
	$rates = array(0, 1, 8, 10, 18, 20, 7.5);

	for ($run = 0; $run < 3000; $run++) {
		$lines = array();
		$erp = array();
		$count = mt_rand(1, 20);
		$equal = (mt_rand(0, 2) === 0) ? mt_rand(1, 999) : 0;

		for ($i = 0; $i < $count; $i++) {
			$amount = ($equal && mt_rand(0, 1)) ? $equal : mt_rand(0, (mt_rand(0, 1) ? 999 : 500000));
			$rate = $rates[mt_rand(0, count($rates) - 1)];
			$lines[100 + $i] = array('amount' => $amount, 'rate' => $rate);
			$erp[100 + $i] = array('net' => $amount, 'tax_rate' => $rate);
		}

		$expected = array();
		foreach (erp_rate_vat($erp)['lines'] as $key => $figures) {
			$expected[$key] = $figures['vat'];
		}

		pg_assert_same($expected, pg_tax_by_rate($lines), 'run ' . $run);
	}
}

// The lines charged with the order take their tax per rate together, in the
// order of their ids; a line charged on its own (a recurring line that
// starts later, one saved for later) takes its own.
function test_order_line_taxes_take_the_charged_lines_together()
{
	pg_assert_same(array(11 => 21, 12 => 20, 13 => 20), pg_order_line_taxes(array(
		13 => array('amount' => 102, 'rate' => 20),
		12 => array('amount' => 102, 'rate' => 20, 'alone' => true),
		11 => array('amount' => 102, 'rate' => 20, 'alone' => false),
	)));
}

// Without a discount the order's tax is its lines'. A discount is shared over
// the lines and the tax taken again per rate on what is left: 1.01 at 20% and
// 1.27 at 10% less 1.00 keep 0.57 and 0.71, so 0.11 + 0.07 = 0.18 - the
// figure the order's invoice and an e-document provider come to. The
// discount's share of the header's tax made it 0.19.
function test_order_charged_tax_takes_a_discount_per_rate()
{
	$lines = array(
		31 => array('amount' => 101, 'rate' => '20.000', 'tax' => 20),
		32 => array('amount' => 127, 'rate' => '10.000', 'tax' => 13),
	);

	pg_assert_same(33, pg_order_charged_tax($lines, 0));
	pg_assert_same(18, pg_order_charged_tax($lines, 100));
	// The discount counts up to the subtotal and the tax never goes below zero.
	pg_assert_same(0, pg_order_charged_tax($lines, 500));
}

// A line whose rate was not kept cannot be taxed again: the tax is lowered by
// the discount's share of the subtotal, as before the rate was kept.
function test_order_charged_tax_without_a_rate_takes_the_header_share()
{
	$lines = array(
		31 => array('amount' => 101, 'rate' => '20.000', 'tax' => 20),
		32 => array('amount' => 127, 'rate' => null, 'tax' => 13),
	);

	pg_assert_same(33 - 14, pg_order_charged_tax($lines, 100));
	pg_assert_same(0, pg_order_charged_tax(array(array('amount' => 100, 'tax' => -5)), 10));
}

// On random orders the checkout's tax after a discount is the one the order's
// invoice takes on its lines (erp_order_lines(): erp_allocate() and
// erp_rate_vat()), so the invoice meets the order with nothing to tie back.
function test_order_charged_tax_meets_the_invoice()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');

	mt_srand(20261022);
	$rates = array('0.000', '1.000', '10.000', '20.000');

	for ($run = 0; $run < 2000; $run++) {
		$lines = array();
		$count = mt_rand(1, 12);

		for ($i = 0; $i < $count; $i++) {
			$lines[200 + $i] = array(
				'amount' => mt_rand(1, (mt_rand(0, 1) ? 999 : 250000)),
				'rate' => $rates[mt_rand(0, count($rates) - 1)],
			);
		}

		$subtotal = array_sum(array_column($lines, 'amount'));
		$discount = mt_rand(1, $subtotal);
		$taxes = pg_order_line_taxes($lines);
		$charged = array();
		$items = array();
		$weights = array();

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
			$weights[] = $line['amount'];
		}

		$tax = pg_order_charged_tax($charged, $discount);

		// The invoice's own figure before it is tied back to the order.
		$nets = array();
		foreach (erp_allocate($discount, $weights) as $index => $share) {
			$nets[$index] = array('net' => $weights[$index] - $share, 'tax_rate' => $items[$index]['tax_rate']);
		}

		pg_assert_same(erp_rate_vat($nets)['vat'], $tax, 'run ' . $run);

		$built = erp_order_lines(array('discount' => $discount, 'tax' => $tax), $items);
		pg_assert_same($tax, $built['totals']['tax_total'], 'run ' . $run . ' invoice');
	}
}
