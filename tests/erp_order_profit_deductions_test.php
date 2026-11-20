<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a marketplace's real deductions on an order's profit
 * (includes/erp/order_profit_deductions.php): settlement records matched to
 * orders by package, then by order number; converted into the order's own
 * currency only through money.php; left out when the order is no finished
 * sale or no rate is known; the read's state kept per account; the pass
 * stays off with Accounting off and out of every money ledger.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit_deductions.php');

// Two orders of a Trendyol account: 41 in lira, finished; 42 cancelled.
function erp_order_profit_deductions_test_orders($changes = array())
{
	$orders = array(
		'package' => array(
			'1111111111' => array('order_id' => 41, 'currency' => 'TRY', 'status' => 'complete'),
			'1111111112' => array('order_id' => 42, 'currency' => 'TRY', 'status' => 'cancelled'),
		),
		'number' => array(
			'501915861' => array(array('order_id' => 41, 'currency' => 'TRY', 'status' => 'complete')),
		),
	);

	return array_replace_recursive($orders, $changes);
}

function erp_order_profit_deductions_test_record($changes = array())
{
	return array_merge(array('ref' => 'settlement:725041340', 'package_id' => '1111111111', 'order_number' => '501915861',
		'amount' => 6750, 'currency' => 'TRY', 'date' => 1763460000), $changes);
}

// No rate is ever needed for lira on a lira order.
function erp_order_profit_deductions_test_no_rate()
{
	return function ($code, $date) {
		return false;
	};
}

// A record becomes a cost row of the order its package names, under the
// record's own reference, in the order's minor units.
function test_erp_order_profit_deductions_match_by_package()
{
	$result = erp_order_profit_deduction_rows(array(
		erp_order_profit_deductions_test_record(),
		erp_order_profit_deductions_test_record(array('ref' => 'settlement:725099001', 'amount' => -625)),
	), erp_order_profit_deductions_test_orders(), erp_order_profit_deductions_test_no_rate());

	pg_assert_same(array(
		array('order_id' => 41, 'ref' => 'settlement:725041340', 'amount' => 6750),
		array('order_id' => 41, 'ref' => 'settlement:725099001', 'amount' => -625),
	), $result['rows']);
	pg_assert_same(0, $result['skipped']);
}

// Without a known package the order number names the order, but only when
// one order of the account carries it.
function test_erp_order_profit_deductions_match_by_order_number_once()
{
	$orders = erp_order_profit_deductions_test_orders(array('number' => array(
		'777' => array(
			array('order_id' => 50, 'currency' => 'TRY', 'status' => 'complete'),
			array('order_id' => 51, 'currency' => 'TRY', 'status' => 'complete'),
		),
	)));

	$result = erp_order_profit_deduction_rows(array(
		erp_order_profit_deductions_test_record(array('package_id' => '999')),
		erp_order_profit_deductions_test_record(array('ref' => 'settlement:2', 'package_id' => '', 'order_number' => '777')),
		erp_order_profit_deductions_test_record(array('ref' => 'settlement:3', 'package_id' => '', 'order_number' => '404')),
	), $orders, erp_order_profit_deductions_test_no_rate());

	pg_assert_same(array(array('order_id' => 41, 'ref' => 'settlement:725041340', 'amount' => 6750)), $result['rows']);
	pg_assert_same(2, $result['skipped']);
}

// A cancelled order is no finished sale: the pass would remove its rows, so
// none is written.
function test_erp_order_profit_deductions_skip_an_unfinished_order()
{
	$result = erp_order_profit_deduction_rows(array(
		erp_order_profit_deductions_test_record(array('package_id' => '1111111112', 'order_number' => '')),
	), erp_order_profit_deductions_test_orders(), erp_order_profit_deductions_test_no_rate());

	pg_assert_same(array(), $result['rows']);
	pg_assert_same(1, $result['skipped']);
}

// A deduction in another currency is converted at the record's day through
// money.php, base units per unit on both sides, round() once.
function test_erp_order_profit_deductions_convert_into_the_order_currency()
{
	$orders = erp_order_profit_deductions_test_orders(array('package' => array(
		'1111111111' => array('order_id' => 41, 'currency' => 'EUR', 'status' => 'complete'),
	)));

	$asked = array();
	$rate_of = function ($code, $date) use (&$asked) {
		$asked[] = $code . '@' . $date;

		return array('TRY' => 1.0, 'EUR' => 48.5)[$code] ?? false;
	};

	$result = erp_order_profit_deduction_rows(array(erp_order_profit_deductions_test_record()), $orders, $rate_of);

	// 6750 kurus × 1 / 48.5 = 139.17… euro cents.
	pg_assert_same(array(array('order_id' => 41, 'ref' => 'settlement:725041340', 'amount' => 139)), $result['rows']);
	pg_assert_same(array('TRY@' . date('Y-m-d', 1763460000), 'EUR@' . date('Y-m-d', 1763460000)), $asked);
	pg_assert_same(139, erp_order_profit_deduction_convert(6750, 'TRY', 'EUR', '2025-11-18', $rate_of));
	pg_assert_same(-139, erp_order_profit_deduction_convert(-6750, 'TRY', 'EUR', '2025-11-18', $rate_of));
	pg_assert_same(6750, erp_order_profit_deduction_convert(6750, 'TRY', 'TRY', '2025-11-18', erp_order_profit_deductions_test_no_rate()));
}

// No rate, or a record whose currency is not known: nothing is written and
// the order keeps its estimate.
function test_erp_order_profit_deductions_skip_what_cannot_be_converted()
{
	$orders = erp_order_profit_deductions_test_orders(array('package' => array(
		'1111111111' => array('order_id' => 41, 'currency' => 'EUR', 'status' => 'complete'),
	)));

	$result = erp_order_profit_deduction_rows(array(
		erp_order_profit_deductions_test_record(),
	), $orders, erp_order_profit_deductions_test_no_rate());

	pg_assert_same(array(), $result['rows']);
	pg_assert_same(1, $result['skipped']);

	$result = erp_order_profit_deduction_rows(array(
		erp_order_profit_deductions_test_record(array('currency' => '')),
	), erp_order_profit_deductions_test_orders(), erp_order_profit_deductions_test_no_rate());

	pg_assert_same(array(), $result['rows']);
	pg_assert_same(1, $result['skipped']);
	pg_assert_false(erp_order_profit_deduction_convert(10, '', 'TRY', '2025-11-18', erp_order_profit_deductions_test_no_rate()));
}

// The read's state per account: a stored one as it was, anything else a
// first read thirty days back.
function test_erp_order_profit_deductions_state()
{
	$now = 1763900000;

	pg_assert_same(array('from' => $now - 2592000, 'type' => 0, 'page' => 0), erp_order_profit_deduction_state('', $now));
	pg_assert_same(array('from' => $now - 2592000, 'type' => 0, 'page' => 0), erp_order_profit_deduction_state('{bad', $now));
	pg_assert_same(array('from' => 1763000000, 'type' => 2, 'page' => 1),
		erp_order_profit_deduction_state('{"from":1763000000,"type":2,"page":1}', $now));
	pg_assert_same('erp_order_profit_deductions_7', erp_order_profit_deduction_state_key(7));
}

// The real deductions replace the listing's commission estimate: the sale's
// commission less the return's, and the order is final.
function test_erp_order_profit_deductions_replace_the_estimate()
{
	$result = erp_order_profit_compute(array(
		'order' => array('id' => 41, 'status' => 'complete', 'total' => 58998, 'gift_card_discount' => 0, 'tax' => 9833,
			'refunded_amount' => 0, 'shipping' => 0, 'payment_method' => '', 'erp_invoice_id' => 0),
		'items' => array(array('id' => 501, 'product_id' => 7, 'quantity' => 1, 'price' => 58998, 'tax_total' => 0)),
		'invoice_moves' => array(),
		'kept' => array(),
		'averages' => array(7 => 30000),
		'components' => array(),
		'rates' => array(),
		'shipping_methods' => array(),
		'marketplace' => array('account_id' => 7, 'commission' => array(7 => 15.0)),
		'recorded' => array(
			array('kind' => 'marketplace', 'source' => 'marketplace', 'ref' => 'settlement:725041340', 'amount' => 6750),
			array('kind' => 'marketplace', 'source' => 'marketplace', 'ref' => 'settlement:725099001', 'amount' => -625),
		),
	));

	$estimates = array_filter($result['costs'], function ($row) {
		return $row['kind'] === 'marketplace';
	});

	pg_assert_same(array(), array_values($estimates));
	pg_assert_same(6125, $result['profit']['cost_marketplace']);
	pg_assert_same('final', $result['profit']['state']);
}

// With Accounting off nothing is read or written.
function test_erp_order_profit_deductions_off_reads_nothing()
{
	$result = erp_order_profit_deductions_run(function () {
		return 10;
	}, false);

	pg_assert_same(array('reason' => 'off', 'done' => 0), $result);
}

// The deductions write cost rows only: no receipt, till, bank or account
// move, no invoice settled, no payout record.
function test_erp_order_profit_deductions_write_only_cost_rows()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/order_profit_deductions.php');

	preg_match_all('/\b(?:INSERT(?:\s+IGNORE)?\s+INTO|(?<!KEY )UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?([a-z_]+)/', $source, $found);

	pg_assert_same(array('erp_order_costs'), array_values(array_unique($found[1])));
	pg_assert_false(strpos($source, "source_ref, amount") === false, 'the row names its reference');
}
