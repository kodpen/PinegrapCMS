<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Accounting > Profitability (includes/erp/profitability.php): an
 * order's profit split over its lines, the product by channel groups and
 * their totals, the channel names, and the typed rates and target margin as
 * the settings card reads them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/profitability.php');

function erp_profitability_test_profit($revenue, $product, $payment = 0, $marketplace = 0, $shipping = 0, $other = 0, $state = 'final')
{
	return array(
		'order_id' => 7, 'channel' => 'web', 'state' => $state, 'revenue' => $revenue,
		'cost_product' => $product, 'cost_payment' => $payment, 'cost_marketplace' => $marketplace,
		'cost_shipping' => $shipping, 'cost_other' => $other,
		'net' => $revenue - $product - $payment - $marketplace - $shipping - $other,
	);
}

function test_erp_profitability_split_by_weight()
{
	// Two lines, 3:1 by their gross: revenue and every cost go 3:1, and each
	// figure adds up to the order's exactly.
	$profit = erp_profitability_test_profit(1000, 401, 33, 0, 100);
	$lines = array(
		array('id' => 1, 'product_id' => 10, 'quantity' => 3, 'weight' => 300),
		array('id' => 2, 'product_id' => 11, 'quantity' => 1, 'weight' => 100),
	);
	$split = erp_profitability_split($profit, $lines, array());

	pg_assert_same(2, count($split), 'one row per line');
	pg_assert_same(750, $split[0]['revenue'], 'revenue by weight');
	pg_assert_same(250, $split[1]['revenue'], 'revenue by weight, second line');
	pg_assert_same(401, $split[0]['cost_product'] + $split[1]['cost_product'], 'product cost adds up');
	pg_assert_same(33, $split[0]['cost_payment'] + $split[1]['cost_payment'], 'payment fee adds up');
	pg_assert_same(75, $split[0]['cost_shipping'], 'shipping by weight');
	pg_assert_same($profit['net'], $split[0]['net'] + $split[1]['net'], 'net adds up to the order\'s');
	pg_assert_same($split[0]['revenue'] - $split[0]['cost_product'] - $split[0]['cost_payment'] - $split[0]['cost_shipping'], $split[0]['net'], 'a line\'s net is its revenue less its costs');
	pg_assert_same(3, $split[0]['quantity'], 'units kept');
	pg_assert_same(10, $split[0]['product_id'], 'product kept');
}

function test_erp_profitability_split_item_costs()
{
	// A line with its own product cost row (item:<id>) keeps it; the rest of
	// the order's product cost goes to the lines without one.
	$profit = erp_profitability_test_profit(1000, 700);
	$lines = array(
		array('id' => 1, 'product_id' => 10, 'quantity' => 1, 'weight' => 500),
		array('id' => 2, 'product_id' => 11, 'quantity' => 1, 'weight' => 500),
	);
	$split = erp_profitability_split($profit, $lines, array(1 => 600));
	pg_assert_same(600, $split[0]['cost_product'], 'the line\'s own cost row');
	pg_assert_same(100, $split[1]['cost_product'], 'the remainder to the other line');

	// Every line has its row: each keeps its own.
	$split = erp_profitability_split($profit, $lines, array(1 => 650, 2 => 50));
	pg_assert_same(array(650, 50), array($split[0]['cost_product'], $split[1]['cost_product']), 'both own rows');

	// Rows that do not add up to the order's figure (a row written after the
	// profit row): the difference is spread by weight, so the sum holds.
	$split = erp_profitability_split($profit, $lines, array(1 => 600, 2 => 200));
	pg_assert_same(700, $split[0]['cost_product'] + $split[1]['cost_product'], 'the order\'s product cost holds');
}

function test_erp_profitability_split_no_lines()
{
	// An order without lines is one row of no product, carrying everything.
	$profit = erp_profitability_test_profit(500, 0, 20);
	$split = erp_profitability_split($profit, array(), array());
	pg_assert_same(1, count($split), 'one row');
	pg_assert_same(0, $split[0]['product_id'], 'no product');
	pg_assert_same(480, $split[0]['net'], 'the whole net');

	// Lines of no weight (all free): spread evenly.
	$lines = array(
		array('id' => 1, 'product_id' => 10, 'quantity' => 1, 'weight' => 0),
		array('id' => 2, 'product_id' => 11, 'quantity' => 1, 'weight' => 0),
	);
	$split = erp_profitability_split(erp_profitability_test_profit(0, 100), $lines, array());
	pg_assert_same(array(50, 50), array($split[0]['cost_product'], $split[1]['cost_product']), 'even split without weights');
}

function test_erp_profitability_group()
{
	$rows = array(
		array('order_id' => 1, 'product_id' => 10, 'channel' => 'web', 'state' => 'final', 'quantity' => 2, 'revenue' => 1000, 'cost_product' => 600, 'cost_payment' => 30, 'cost_marketplace' => 0, 'cost_shipping' => 0, 'cost_other' => 0, 'net' => 370),
		array('order_id' => 2, 'product_id' => 10, 'channel' => 'web', 'state' => 'estimate', 'quantity' => 1, 'revenue' => 500, 'cost_product' => 300, 'cost_payment' => 20, 'cost_marketplace' => 0, 'cost_shipping' => 0, 'cost_other' => 0, 'net' => 180),
		array('order_id' => 2, 'product_id' => 10, 'channel' => 'web', 'state' => 'estimate', 'quantity' => 1, 'revenue' => 500, 'cost_product' => 300, 'cost_payment' => 0, 'cost_marketplace' => 0, 'cost_shipping' => 0, 'cost_other' => 0, 'net' => 200),
		array('order_id' => 3, 'product_id' => 10, 'channel' => 'marketplace:4', 'state' => 'incomplete', 'quantity' => 1, 'revenue' => 400, 'cost_product' => 0, 'cost_payment' => 0, 'cost_marketplace' => 500, 'cost_shipping' => 0, 'cost_other' => 0, 'net' => -100),
	);
	$groups = erp_profitability_group($rows);

	pg_assert_same(2, count($groups), 'one group per product and channel');
	pg_assert_same('web', $groups[0]['channel'], 'the most profitable first');
	pg_assert_same(2, $groups[0]['orders'], 'orders counted once each');
	pg_assert_same(4.0, $groups[0]['units'], 'units added up');
	pg_assert_same(2000, $groups[0]['revenue'], 'revenue added up');
	pg_assert_same(750, $groups[0]['net'], 'net added up');
	pg_assert_same(37.5, $groups[0]['margin'], 'margin from the sums');
	pg_assert_same(1, $groups[0]['estimate'], 'orders with an estimate');
	pg_assert_same(1, $groups[1]['incomplete'], 'orders with no product cost');
	pg_assert_same(-25.0, $groups[1]['margin'], 'a loss margin');

	$totals = erp_profitability_totals($groups);
	pg_assert_same(2400, $totals['all']['revenue'], 'grand revenue');
	pg_assert_same(650, $totals['all']['net'], 'grand net');
	pg_assert_same(3, $totals['all']['orders'], 'orders over all groups');
	pg_assert_same(array('web', 'marketplace:4'), array_keys($totals['channels']), 'channels in order of net');
	pg_assert_same(-100, $totals['channels']['marketplace:4']['net'], 'a channel\'s net');
	pg_assert_same(null, erp_profitability_totals(array())['all']['margin'], 'no revenue, no margin');
}

function test_erp_profitability_channel_label()
{
	pg_assert_same('Web store', erp_profitability_channel_label('web', array()), 'the web store');
	pg_assert_same('Trendyol', erp_profitability_channel_label('marketplace:4', array(4 => 'Trendyol')), 'a marketplace account by its name');
	pg_assert_same('Marketplace account #9', erp_profitability_channel_label('marketplace:9', array()), 'an account no longer there');
}

function test_erp_profitability_channel_in()
{
	pg_assert_same('', erp_profitability_channel_in(''), 'all channels');
	pg_assert_same('web', erp_profitability_channel_in('web'), 'the web store');
	pg_assert_same('marketplace:12', erp_profitability_channel_in('marketplace:12'), 'an account');
	pg_assert_same('', erp_profitability_channel_in('marketplace:x'), 'a bad account');
	pg_assert_same('', erp_profitability_channel_in(array('web')), 'an array');
	pg_assert_same('', erp_profitability_channel_in("web' OR 1"), 'anything else');
}

function test_erp_profitability_rate_in()
{
	$rate = erp_profitability_rate_in('2,49', '0,25');
	pg_assert_same('', $rate['error'], 'a typed rate is taken');
	pg_assert_same(2.49, $rate['percent'], 'percent with a decimal comma');
	pg_assert_same(25, $rate['fixed'], 'the fixed part in minor units');
	pg_assert_same(false, $rate['empty'], 'not empty');

	pg_assert_same(true, erp_profitability_rate_in('', '')['empty'], 'both empty removes the rate');
	pg_assert_same(true, erp_profitability_rate_in(' ', '')['empty'], 'blank removes it too');
	pg_assert_same(0.0, erp_profitability_rate_in('', '5')['percent'], 'only a fixed part');
	pg_assert_same(500, erp_profitability_rate_in('', '5')['fixed'], 'five in minor units');
	pg_assert_same(1.125, erp_profitability_rate_in('1.125', '')['percent'], 'three decimals');

	pg_assert_true(erp_profitability_rate_in('101', '')['error'] !== '', 'over 100 percent refused');
	pg_assert_true(erp_profitability_rate_in('-1', '')['error'] !== '', 'a negative percent refused');
	pg_assert_true(erp_profitability_rate_in('1.2345', '')['error'] !== '', 'four decimals refused');
	pg_assert_true(erp_profitability_rate_in('abc', '')['error'] !== '', 'not a number refused');
	pg_assert_true(erp_profitability_rate_in('', '-3')['error'] !== '', 'a negative fixed part refused');
	pg_assert_true(erp_profitability_rate_in('', 'abc')['error'] !== '', 'a fixed part that is not a number refused');
}

function test_erp_profitability_target_in()
{
	pg_assert_same(array('error' => '', 'value' => ''), erp_profitability_target_in(''), 'empty: no target');
	pg_assert_same(array('error' => '', 'value' => '12.5'), erp_profitability_target_in('12,5'), 'a decimal comma');
	pg_assert_same(array('error' => '', 'value' => '30'), erp_profitability_target_in(' 30 '), 'a whole number');
	pg_assert_same(array('error' => '', 'value' => '0'), erp_profitability_target_in('0'), 'zero is a target');
	pg_assert_true(erp_profitability_target_in('100')['error'] !== '', '100 percent refused');
	pg_assert_true(erp_profitability_target_in('-5')['error'] !== '', 'a negative target refused');
	pg_assert_true(erp_profitability_target_in('12.55')['error'] !== '', 'two decimals refused');
	pg_assert_true(erp_profitability_target_in('x')['error'] !== '', 'not a number refused');
}

function test_erp_profitability_below_target()
{
	pg_assert_same(false, erp_profitability_below_target(12.0, null), 'no target, never below');
	pg_assert_same(true, erp_profitability_below_target(12.0, 20.0), 'below the target');
	pg_assert_same(false, erp_profitability_below_target(20.0, 20.0), 'at the target');
	pg_assert_same(false, erp_profitability_below_target(null, 20.0), 'no margin, not marked');
}
