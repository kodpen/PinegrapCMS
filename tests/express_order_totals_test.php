<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the totals the express order widget of a page built with the
 * visual editor shows and posts (pg_express_order_charged_totals(),
 * includes/fn/widgets_cart.php): the amount submit_order.php charges when
 * the order is placed, with the cart's order discount, the applied gift
 * cards and only the lines charged today.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A cart line as the widget reads it.
function express_order_totals_test_line($price, $quantity, $tax = 0, $extra = array())
{
	return $extra + array(
		'price'                => $price,
		'quantity'             => $quantity,
		'tax_total'            => $tax,
		'recurring'            => 0,
		'recurring_start_date' => '',
		'start_days'           => 0,
		'gift_card'            => 0,
	);
}

// The order discount and the gift cards are in the total, as submit_order() charges it.
function test_express_order_totals_discount_and_gift_card()
{
	$line = express_order_totals_test_line(1000, 3);

	$totals = pg_express_order_charged_totals(array($line), 300, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(3000, $totals['subtotal'], 'the subtotal');
	pg_assert_same(300, $totals['discount'], 'the order discount');
	pg_assert_same(2700, $totals['total'], 'the total less the discount');

	$totals = pg_express_order_charged_totals(array($line), 300, 0, 500, 0, '2026-10-21', false);
	pg_assert_same(500, $totals['gift_card_discount'], 'the gift card');
	pg_assert_same(2200, $totals['total'], 'the total less both');

	$totals = pg_express_order_charged_totals(array($line), 300, 0, 9000, 0, '2026-10-21', false);
	pg_assert_same(2700, $totals['gift_card_discount'], 'a gift card is taken at most the total');
	pg_assert_same(0, $totals['total'], 'nothing is left to pay');

	$totals = pg_express_order_charged_totals(array($line), 5000, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(3000, $totals['discount'], 'a discount at most the subtotal');
	pg_assert_same(0, $totals['total'], 'the total does not go below zero');

	$card = express_order_totals_test_line(5000, 1, 0, array('gift_card' => 1));
	$totals = pg_express_order_charged_totals(array($card), 0, 0, 500, 0, '2026-10-21', false);
	pg_assert_same(0, $totals['gift_card_discount'], 'no gift card on an order of gift cards alone');
	pg_assert_same(5000, $totals['total'], 'the gift card product is paid in full');
}

// The tax is lowered by the discount's share, and never goes below zero.
function test_express_order_totals_tax_and_shipping()
{
	$totals = pg_express_order_charged_totals(array(express_order_totals_test_line(1000, 3, 600)), 300, 1500, 0, 0, '2026-10-21', false);
	pg_assert_same(540, $totals['tax'], 'the tax less a tenth');
	pg_assert_same(1500, $totals['shipping'], 'the shipping');
	pg_assert_same(3000 - 300 + 540 + 1500, $totals['total'], 'subtotal, discount, tax and shipping');

	$totals = pg_express_order_charged_totals(array(express_order_totals_test_line(1000, 1, 200), express_order_totals_test_line(-500, 1, -300)), 0, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(0, $totals['tax'], 'a negative tax is zero');
	pg_assert_same(500, $totals['total'], 'the lines without it');
}

// Only the lines charged today count: a recurring line starting later does
// not, nor any recurring line on ClearCommerce.
function test_express_order_totals_lines_charged_today()
{
	$lines = array(
		express_order_totals_test_line(1000, 1),
		express_order_totals_test_line(5000, 1, 1000, array('recurring' => 1, 'start_days' => 30)),
	);

	$totals = pg_express_order_charged_totals($lines, 0, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(1000, $totals['subtotal'], 'the subscription that starts in 30 days is not in the subtotal');
	pg_assert_same(0, $totals['tax'], 'nor its tax');
	pg_assert_same(1000, $totals['total'], 'today\'s charge');

	$lines[1]['start_days'] = 0;
	$totals = pg_express_order_charged_totals($lines, 0, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(6000, $totals['subtotal'], 'one starting today is');

	$totals = pg_express_order_charged_totals($lines, 0, 0, 0, 0, '2026-10-21', true);
	pg_assert_same(1000, $totals['subtotal'], 'ClearCommerce charges recurring lines itself');

	$lines[1]['recurring_start_date'] = '2026-11-01';
	$totals = pg_express_order_charged_totals($lines, 0, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(1000, $totals['subtotal'], 'a written start date in the future');

	$totals = pg_express_order_charged_totals($lines, 3000, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(1000, $totals['discount'], 'the discount at most today\'s subtotal');
}

// The card surcharge is on what is left to pay.
function test_express_order_totals_surcharge()
{
	$line = express_order_totals_test_line(1000, 3);

	$totals = pg_express_order_charged_totals(array($line), 300, 0, 500, 3, '2026-10-21', false);
	pg_assert_same(66, $totals['surcharge'], '3% of 2200');
	pg_assert_same(2266, $totals['total_with_surcharge'], 'the total with it');
	pg_assert_same(2200, $totals['total'], 'the total without it');

	$totals = pg_express_order_charged_totals(array($line), 0, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(0, $totals['surcharge'], 'no surcharge set');
}
