<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order's cost record and profit (includes/erp/order_profit.php):
 * product cost from the invoice's sale moves, then the kept average, then a
 * bundle's components; typed rates per payment method, shipping method and
 * marketplace account; the listing's commission estimate; refunds; revenue
 * by erp_profit_report()'s rule; a later real cost reworking the order; and
 * that the pass stays out of every money ledger.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');

// An order of two plain lines, paid by card, shipped by method 3.
function erp_order_profit_test_input($changes = array())
{
	$input = array(
		'order' => array(
			'id' => 41, 'status' => 'complete', 'total' => 12000, 'gift_card_discount' => 0, 'tax' => 2000,
			'refunded_amount' => 0, 'shipping' => 500, 'payment_method' => 'Credit/Debit Card', 'erp_invoice_id' => 0,
		),
		'items' => array(
			array('id' => 501, 'product_id' => 7, 'quantity' => 2, 'price' => 3000, 'tax_total' => 1200),
			array('id' => 502, 'product_id' => 9, 'quantity' => 1, 'price' => 3500, 'tax_total' => 700),
		),
		'invoice_moves' => array(),
		'kept' => array(),
		'averages' => array(7 => 1000, 9 => 2000),
		'components' => array(),
		'rates' => array(),
		'shipping_methods' => array(3),
		'marketplace' => null,
		'recorded' => array(),
	);

	foreach ($changes as $key => $value) {
		$input[$key] = $value;
	}

	return $input;
}

// Pick the cost rows of one kind as ref => [source, amount].
function erp_order_profit_test_rows($result, $kind)
{
	$rows = array();

	foreach ($result['costs'] as $row) {
		if ($row['kind'] === $kind) {
			$rows[$row['ref']] = array($row['source'], $row['amount']);
		}
	}

	ksort($rows);

	return $rows;
}

// An invoiced order takes its product cost from the invoice's sale moves,
// one row for the invoice, whatever the averages say today.
function test_erp_order_profit_product_cost_from_invoice_moves()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array(
		'order' => array_merge(erp_order_profit_test_input()['order'], array('erp_invoice_id' => 88)),
		'invoice_moves' => array(
			array('product_id' => 7, 'quantity' => 2, 'cost_total' => 1800),
			array('product_id' => 9, 'quantity' => 1, 'cost_total' => 2100),
		),
	)));

	pg_assert_same(array('invoice:88' => array('invoice', 3900)), erp_order_profit_test_rows($result, 'product'));
	pg_assert_same(3900, $result['profit']['cost_product']);
	pg_assert_same('final', $result['profit']['state']);
}

// A move written with cost 0 (no cost known when the invoice was issued) is
// no real cost: the lines are costed at the average instead.
function test_erp_order_profit_zero_cost_move_falls_back_to_average()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array(
		'order' => array_merge(erp_order_profit_test_input()['order'], array('erp_invoice_id' => 88)),
		'invoice_moves' => array(
			array('product_id' => 7, 'quantity' => 2, 'cost_total' => 0),
			array('product_id' => 9, 'quantity' => 1, 'cost_total' => 2100),
		),
	)));

	pg_assert_same(array('item:501' => array('average', 2000), 'item:502' => array('average', 2000)),
		erp_order_profit_test_rows($result, 'product'));
}

// Without an invoice each line is costed at the average the pass first meets,
// and that figure is kept: a later average never rewrites it.
function test_erp_order_profit_average_is_kept()
{
	$first = erp_order_profit_compute(erp_order_profit_test_input());

	pg_assert_same(array('item:501' => array('average', 2000), 'item:502' => array('average', 2000)),
		erp_order_profit_test_rows($first, 'product'));

	$later = erp_order_profit_compute(erp_order_profit_test_input(array(
		'averages' => array(7 => 5000, 9 => 9000),
		'kept' => array('item:501' => 2000, 'item:502' => 2000),
	)));

	pg_assert_same(4000, $later['profit']['cost_product'], 'the kept average stays');
}

// A bundle line is costed by the unit cost its components were sold with,
// times the quantity per bundle and the bundles on the line.
function test_erp_order_profit_bundle_components()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array(
		'items' => array(array('id' => 601, 'product_id' => 30, 'quantity' => 2, 'price' => 5000, 'tax_total' => 2000)),
		'averages' => array(),
		'components' => array(601 => array(
			array('product_id' => 7, 'quantity' => 2, 'unit_cost' => 400),
			array('product_id' => 9, 'quantity' => 1, 'unit_cost' => 700),
		)),
	)));

	pg_assert_same(array('item:601' => array('bundle', 3000)), erp_order_profit_test_rows($result, 'product'));
	pg_assert_same('final', $result['profit']['state']);
}

// A line with no cost anywhere leaves the order Incomplete.
function test_erp_order_profit_unknown_cost_is_incomplete()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array('averages' => array(7 => 1000))));

	pg_assert_same(array('item:501' => array('average', 2000)), erp_order_profit_test_rows($result, 'product'));
	pg_assert_same('incomplete', $result['profit']['state']);
}

// Typed rates: percent plus fixed per payment method, a fixed cost per
// shipping method; either makes the order an Estimate.
function test_erp_order_profit_typed_rates()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array(
		'rates' => array(
			'payment' => array('Credit/Debit Card' => array('percent' => 2.49, 'fixed' => 25)),
			'shipping' => array('3' => array('percent' => 0, 'fixed' => 450)),
		),
	)));

	// 2.49% of 120.00 is 2.988, so 2.99, plus 0.25.
	pg_assert_same(array('' => array('rate', 324)), erp_order_profit_test_rows($result, 'payment'));
	pg_assert_same(array('' => array('rate', 450)), erp_order_profit_test_rows($result, 'shipping'));
	pg_assert_same('estimate', $result['profit']['state']);
	pg_assert_same(10000 - 4000 - 324 - 450, $result['profit']['net']);
}

// A marketplace order: the listing's commission rate times the line amount
// is the estimate; a product without one takes the account's typed rate.
function test_erp_order_profit_listing_estimate()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array(
		'marketplace' => array('account_id' => 5, 'commission' => array(7 => 15.0)),
		'rates' => array(
			'payment' => array('Credit/Debit Card' => array('percent' => 2.49, 'fixed' => 25)),
			'marketplace' => array('5' => array('percent' => 10, 'fixed' => 0)),
		),
	)));

	// Line 501: (2 x 30.00 + 12.00) x 15% = 10.80; line 502: (35.00 + 7.00) x 10% = 4.20;
	// one row, an estimate because a line took the listing's rate.
	pg_assert_same(array('' => array('estimate', 1500)), erp_order_profit_test_rows($result, 'marketplace'));
	pg_assert_same(array(), erp_order_profit_test_rows($result, 'payment'), 'the marketplace takes the payment');
	pg_assert_same('marketplace:5', $result['profit']['channel']);
}

// A refund lowers the revenue; costs stay.
function test_erp_order_profit_refund()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array(
		'order' => array_merge(erp_order_profit_test_input()['order'], array('refunded_amount' => 2500)),
	)));

	pg_assert_same(7500, $result['profit']['revenue']);
	pg_assert_same(3500, $result['profit']['net']);
}

// Revenue is erp_profit_report()'s for an uninvoiced order: total plus the
// gift card part, less tax (erp_profit_uninvoiced_orders()).
function test_erp_order_profit_revenue_matches_profit_report()
{
	$orders = array(
		array('total' => 12000, 'gift_card_discount' => 0, 'tax' => 2000, 'refunded_amount' => 0),
		array('total' => 5000, 'gift_card_discount' => 1500, 'tax' => 1083, 'refunded_amount' => 0),
		array('total' => 0, 'gift_card_discount' => 4000, 'tax' => 667, 'refunded_amount' => 0),
	);

	$report = 0;
	$ours = 0;

	foreach ($orders as $order) {
		$report += $order['total'] + $order['gift_card_discount'] - $order['tax'];
		$ours += erp_order_profit_revenue($order);
	}

	pg_assert_same($report, $ours);
}

// A real figure recorded later (a gateway's fee, a label's price, a
// marketplace's deduction) replaces the rate or estimate of its kind.
function test_erp_order_profit_later_cost_reworks()
{
	$rates = array(
		'payment' => array('Credit/Debit Card' => array('percent' => 2.49, 'fixed' => 25)),
		'shipping' => array('3' => array('percent' => 0, 'fixed' => 450)),
	);

	$before = erp_order_profit_compute(erp_order_profit_test_input(array('rates' => $rates)));

	$after = erp_order_profit_compute(erp_order_profit_test_input(array(
		'rates' => $rates,
		'recorded' => array(
			array('kind' => 'payment', 'source' => 'gateway', 'ref' => 'iyzico:123', 'amount' => 310),
			array('kind' => 'shipping', 'source' => 'label', 'ref' => 'label:9', 'amount' => 390),
		),
	)));

	pg_assert_same(array(), erp_order_profit_test_rows($after, 'payment'), 'the pass writes no payment row beside the real one');
	pg_assert_same(array(), erp_order_profit_test_rows($after, 'shipping'));
	pg_assert_same(310, $after['profit']['cost_payment']);
	pg_assert_same(390, $after['profit']['cost_shipping']);
	pg_assert_same($before['profit']['net'] + 324 + 450 - 310 - 390, $after['profit']['net']);
	pg_assert_same('final', $after['profit']['state']);
}

// Margin: net over revenue without tax, as the profit report writes it.
function test_erp_order_profit_margin()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input());

	pg_assert_same(6000, $result['profit']['net']);
	pg_assert_same(60.0, $result['profit']['margin']);
}

// Accounting off: the pass reads and writes nothing (no database is reached
// in the test runner, so any query would fail the run).
function test_erp_order_profit_run_off()
{
	$result = erp_order_profit_run(function () {
		return 10;
	}, false);

	pg_assert_same('off', $result['reason']);
	pg_assert_same(0, $result['done']);
}

// The module writes its own two tables and nothing else: no receipt, till,
// bank or account move, no invoice settled, no payout record.
function test_erp_order_profit_writes_only_its_tables()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');

	preg_match_all('/\b(?:INSERT(?:\s+IGNORE)?\s+INTO|(?<!KEY )UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?([a-z_]+)/', $source, $found);

	$tables = array_values(array_unique($found[1]));
	sort($tables);

	pg_assert_same(array('erp_order_costs', 'erp_order_profit'), $tables);
}

// The fingerprint changes with every figure the profit is built from.
function test_erp_order_profit_basis()
{
	$order = erp_order_profit_test_input()['order'];
	$basis = erp_order_profit_basis($order);

	pg_assert_same(32, strlen($basis));
	pg_assert_false($basis === erp_order_profit_basis(array_merge($order, array('refunded_amount' => 1))));
	pg_assert_false($basis === erp_order_profit_basis(array_merge($order, array('erp_invoice_id' => 5))));
	pg_assert_same($basis, erp_order_profit_basis($order));
}

// A rate or estimate is one row per kind with an empty reference: two
// shipping methods with rates add up into one row, and no two rows of an
// order share kind and reference (the table's unique key).
function test_erp_order_profit_rate_rows_never_pile_up()
{
	$result = erp_order_profit_compute(erp_order_profit_test_input(array(
		'shipping_methods' => array(3, 4, 3),
		'rates' => array(
			'payment' => array('Credit/Debit Card' => array('percent' => 0, 'fixed' => 100)),
			'shipping' => array('3' => array('percent' => 0, 'fixed' => 450), '4' => array('percent' => 0, 'fixed' => 300)),
		),
	)));

	pg_assert_same(array('' => array('rate', 750)), erp_order_profit_test_rows($result, 'shipping'));

	$keys = array();

	foreach ($result['costs'] as $row) {
		$keys[] = $row['kind'] . '|' . $row['ref'];

		if (in_array($row['source'], array('rate', 'estimate'), true)) {
			pg_assert_same('', $row['ref'], 'a rate or estimate row has the empty reference');
		}
	}

	pg_assert_same(count($keys), count(array_unique($keys)));
}

// The margin is net x 100 / revenue rounded to one decimal, and null when
// there is no revenue.
function test_erp_order_profit_margin_rule()
{
	pg_assert_same(33.3, erp_order_profit_margin(1, 3));
	pg_assert_same(-12.5, erp_order_profit_margin(-125, 1000));
	pg_assert_same(null, erp_order_profit_margin(-500, 0));

	$free = erp_order_profit_compute(erp_order_profit_test_input(array(
		'order' => array_merge(erp_order_profit_test_input()['order'], array('total' => 0, 'tax' => 0)),
	)));

	pg_assert_same(0, $free['profit']['revenue']);
	pg_assert_same(null, $free['profit']['margin']);
}
