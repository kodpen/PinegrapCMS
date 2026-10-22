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

// With the lines' rates kept, a discount takes the tax again per rate on
// what it leaves of the lines (pg_order_charged_tax()), as submit_order()
// does: 1.01 at 20% and 1.27 at 10% less 1.00 carry 0.11 and 0.07.
function test_express_order_totals_discounted_tax_per_rate()
{
	$lines = array(
		express_order_totals_test_line(101, 1, 20, array('tax_rate' => '20.000')),
		express_order_totals_test_line(127, 1, 13, array('tax_rate' => '10.000')),
	);

	$totals = pg_express_order_charged_totals($lines, 100, 0, 0, 0, '2026-10-21', false);
	pg_assert_same(18, $totals['tax'], 'the tax per rate after the discount');
	pg_assert_same(228 - 100 + 18, $totals['total'], 'the total with it');
}

// Where the shipping holds tax on the order's invoice, a shipping that would
// leave the rate's total out of reach is a minor unit lower, as submit_order()
// charges it: 39.95 with 7.99 of tax and shipping 9.99 would come to 57.93,
// which no amount at 20% reaches, so the shipping shows 9.98 and the total
// 57.92. Without the rule the shipping is the recipients' sum.
function test_express_order_totals_settle_the_shipping()
{
	$lines = array(express_order_totals_test_line(3995, 1, 799, array('tax_rate' => '20.000')));

	$totals = pg_express_order_charged_totals($lines, 0, 999, 0, 0, '2026-10-21', false, true);
	pg_assert_same(998, $totals['shipping']);
	pg_assert_same(5792, $totals['total']);

	$totals = pg_express_order_charged_totals($lines, 0, 999, 0, 0, '2026-10-21', false);
	pg_assert_same(999, $totals['shipping']);
	pg_assert_same(5793, $totals['total']);

	// The card surcharge is settled the same way, after the shipping.
	$totals = pg_express_order_charged_totals($lines, 0, 998, 0, 2, '2026-10-21', false, true);
	pg_assert_same(116, $totals['surcharge'], '2% of 57.92 is 1.16; 57.92 + 1.16 = 59.08 is reached');
	pg_assert_same(5792 + 116, $totals['total_with_surcharge']);
}

// The shipping script carries what the totals were drawn with. On a page drawn
// with the shipping a minor unit lower (9.99 stored, 9.98 charged) it keeps
// the lower figure while the methods the page was drawn with stay picked, and
// moves the total by the difference in shipping instead of adding up the
// subtotal, tax and shipping it reads, which left an order discount out.
function test_express_order_widget_script_carries_the_drawn_totals()
{
	$js = _eo_render_widget_js(7, 'pg-eo-form-w7', true, array(array('id' => 377)), '/',
		array('shipping_drawn' => 999, 'shipping' => 998, 'total' => 5792));

	pg_assert_true(strpos($js, 'var SHIP_DRAWN   = 999;') !== false, 'the stored shipping');
	pg_assert_true(strpos($js, 'var SHIP_CHARGED = 998;') !== false, 'the shipping charged');
	pg_assert_true(strpos($js, 'var TOTAL_SHOWN  = 5792;') !== false, 'the total shown');
	pg_assert_true(strpos($js, 'if (shipCents === SHIP_DRAWN) shipCents = SHIP_CHARGED;') !== false, 'the lower figure kept');
	pg_assert_true(strpos($js, 'TOTAL_SHOWN + shipCents - SHIP_CHARGED') !== false, 'the total moved by the shipping');

	$js = _eo_render_widget_js(7, 'pg-eo-form-w7', false, array(), '/');
	pg_assert_true(strpos($js, 'var SHIP_DRAWN   = 0;') !== false, 'nothing given');
	pg_assert_true(strpos($js, 'var TOTAL_SHOWN  = 0;') !== false, 'nothing given');
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
