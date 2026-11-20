<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the break-even price of a product on a channel
 * (erp_order_profit_break_even()) and the bell for a product sold at a loss
 * (erp_alert_profit_loss_plan(), erp_alert_profit_loss_state()): with and
 * without a target margin, no sales, a zero or negative cost, and the once
 * per product and channel rule with its reset when the product turns
 * profitable.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/alerts.php');

function erp_break_even_test_group($units, $revenue, $product, $payment = 0, $marketplace = 0, $shipping = 0, $other = 0)
{
	return array(
		'product_id' => 10, 'channel' => 'web', 'units' => $units, 'revenue' => $revenue,
		'cost_product' => $product, 'cost_payment' => $payment, 'cost_marketplace' => $marketplace,
		'cost_shipping' => $shipping, 'cost_other' => $other,
		'net' => $revenue - $product - $payment - $marketplace - $shipping - $other,
	);
}

function test_erp_order_profit_break_even_without_target()
{
	// 10 units for 10000: the goods and shipping cost 700 a unit, the payment
	// fee is 5% of the price. 700 / 0.95 = 736.84, so 737 is the lowest whole
	// price that loses nothing.
	$even = erp_order_profit_break_even(erp_break_even_test_group(10, 10000, 6000, 500, 0, 1000), null);

	pg_assert_same(737, $even['price'], 'break-even price');
	pg_assert_same(null, $even['target_price'], 'no target margin, no target price');
	pg_assert_same(1000, $even['average_price'], 'average price sold at');
}

function test_erp_order_profit_break_even_with_target()
{
	// The same costs at a 20% target margin: 700 / (1 - 0.05 - 0.20) = 933.33.
	$even = erp_order_profit_break_even(erp_break_even_test_group(10, 10000, 6000, 500, 0, 1000), 20.0);

	pg_assert_same(737, $even['price'], 'break-even price kept');
	pg_assert_same(934, $even['target_price'], 'price at the target margin');

	// Marketplace deductions count as a share of the price, like the fee.
	$even = erp_order_profit_break_even(erp_break_even_test_group(4, 4000, 2000, 0, 800), null);
	pg_assert_same(625, $even['price'], '500 / (1 - 0.20)');
}

function test_erp_order_profit_break_even_no_sales()
{
	$none = array('price' => null, 'target_price' => null, 'average_price' => null);

	pg_assert_same($none, erp_order_profit_break_even(null, 20.0), 'no group: nothing to show');
	pg_assert_same($none, erp_order_profit_break_even(erp_break_even_test_group(0, 0, 0), 20.0), 'no units sold');
}

function test_erp_order_profit_break_even_zero_and_negative_cost()
{
	$even = erp_order_profit_break_even(erp_break_even_test_group(5, 5000, 0, 250), 10.0);
	pg_assert_same(0, $even['price'], 'no cost: every price covers it');
	pg_assert_same(0, $even['target_price'], 'no cost: every price reaches the target');

	$even = erp_order_profit_break_even(erp_break_even_test_group(5, 5000, -400), null);
	pg_assert_same(0, $even['price'], 'a negative cost is no cost');

	// Free goods: no revenue, so no fee share; the price is the unit cost.
	$even = erp_order_profit_break_even(erp_break_even_test_group(2, 0, 300), null);
	pg_assert_same(150, $even['price'], 'unit cost without fees');
	pg_assert_same(null, $even['average_price'], 'nothing was charged');
}

function test_erp_order_profit_break_even_unreachable()
{
	// Fees take 90% of the price: break-even at 10x the unit cost, and a 20%
	// target margin cannot be reached at any price.
	$even = erp_order_profit_break_even(erp_break_even_test_group(1, 1000, 100, 900), 20.0);

	pg_assert_same(1000, $even['price'], 'break-even with 90% fees');
	pg_assert_same(null, $even['target_price'], 'target out of reach');

	$even = erp_order_profit_break_even(erp_break_even_test_group(1, 1000, 100, 1000), null);
	pg_assert_same(null, $even['price'], 'fees take the whole price: no break-even');
}

function test_erp_alert_profit_loss_plan_once_per_product_and_channel()
{
	$groups = array(
		array('product_id' => 10, 'channel' => 'web', 'net' => -100),
		array('product_id' => 11, 'channel' => 'web', 'net' => 50),
		array('product_id' => 10, 'channel' => 'marketplace:3', 'net' => -20),
		array('product_id' => 0, 'channel' => 'web', 'net' => -5),
	);

	$first = erp_alert_profit_loss_plan($groups, array());
	pg_assert_same(2, count($first['announce']), 'two losses announced, lines without a product left out');
	pg_assert_same(10, $first['announce'][0]['product_id'], 'the biggest loss first');
	pg_assert_same(array('10|marketplace:3', '10|web'), $first['notified'], 'both remembered');

	$second = erp_alert_profit_loss_plan($groups, $first['notified']);
	pg_assert_same(array(), $second['announce'], 'not announced twice');
	pg_assert_same($first['notified'], $second['notified'], 'still remembered');
}

function test_erp_alert_profit_loss_plan_resets_when_profitable()
{
	$notified = array('10|marketplace:3', '10|web');

	// Profitable on the web again; nothing sold on the marketplace: that one
	// stays remembered until it turns profitable.
	$plan = erp_alert_profit_loss_plan(array(array('product_id' => 10, 'channel' => 'web', 'net' => 30)), $notified);
	pg_assert_same(array(), $plan['announce'], 'nothing to announce');
	pg_assert_same(array('10|marketplace:3'), $plan['notified'], 'the profitable one is forgotten');

	$again = erp_alert_profit_loss_plan(array(array('product_id' => 10, 'channel' => 'web', 'net' => -1)), $plan['notified']);
	pg_assert_same(1, count($again['announce']), 'a new loss after a profit is announced again');

	$even = erp_alert_profit_loss_plan(array(array('product_id' => 10, 'channel' => 'marketplace:3', 'net' => 0)), $notified);
	pg_assert_same(array('10|web'), $even['notified'], 'net zero is no loss');
}

function test_erp_alert_profit_loss_state()
{
	pg_assert_same(array('day' => '', 'keys' => array()), erp_alert_profit_loss_state(''), 'nothing kept');
	pg_assert_same(array('day' => '', 'keys' => array()), erp_alert_profit_loss_state('{broken'), 'junk read as nothing');
	pg_assert_same(
		array('day' => '2026-11-20', 'keys' => array('10|web')),
		erp_alert_profit_loss_state('{"day":"2026-11-20","keys":["10|web",5,""]}'),
		'only text keys kept'
	);
	pg_assert_same(
		array('day' => '2026-11-20', 'keys' => array('10|web')),
		erp_alert_profit_loss_state(erp_alert_profit_loss_state_text('2026-11-20', array('10|web'))),
		'written and read back'
	);
}
