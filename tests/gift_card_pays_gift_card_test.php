<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cap on store credit at checkout (pg_gift_card_lines_part(),
 * pg_gift_card_applied_discount() and pg_express_order_charged_totals(),
 * includes/fn/widgets_cart.php): credit never pays for the gift card product
 * lines of a mixed cart, so it never turns into a plain gift card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A cart line as the express order widget reads it.
function gift_card_pays_gift_card_test_line($price, $gift_card, $tax = 0, $rate = null)
{
	return array('price' => $price, 'quantity' => 1, 'tax_total' => $tax, 'tax_rate' => $rate,
		'recurring' => 0, 'recurring_start_date' => '', 'start_days' => 0, 'gift_card' => $gift_card);
}

function test_gift_card_credit_does_not_pay_gift_card_line()
{
	$lines = array(gift_card_pays_gift_card_test_line(100, 0), gift_card_pays_gift_card_test_line(10000, 1));

	$totals = pg_express_order_charged_totals($lines, 0, 0, array('credit' => 10100, 'cards' => 0), 0, '2026-11-17', false);
	pg_assert_same(100, $totals['gift_card_discount']);
	pg_assert_same(10000, $totals['total']);

	// One balance given as an int is counted as credit.
	$totals = pg_express_order_charged_totals($lines, 0, 0, 10100, 0, '2026-11-17', false);
	pg_assert_same(100, $totals['gift_card_discount']);
	pg_assert_same(10000, $totals['total']);
}

function test_gift_card_credit_pays_shipping_and_the_other_lines()
{
	$lines = array(gift_card_pays_gift_card_test_line(1000, 0), gift_card_pays_gift_card_test_line(5000, 1));

	// Shipping is not a gift card line: credit pays it.
	$totals = pg_express_order_charged_totals($lines, 0, 500, array('credit' => 9999, 'cards' => 0), 0, '2026-11-17', false);
	pg_assert_same(1500, $totals['gift_card_discount']);
	pg_assert_same(5000, $totals['total']);

	// Less credit than the rest: all of it is spent.
	$totals = pg_express_order_charged_totals($lines, 0, 0, array('credit' => 300, 'cards' => 0), 0, '2026-11-17', false);
	pg_assert_same(300, $totals['gift_card_discount']);
	pg_assert_same(5700, $totals['total']);
}

function test_gift_card_credit_cap_takes_the_discount_share_and_tax()
{
	// 1000 + 3000 (gift card), discount 400 shared 100 / 300; 20% tax taken
	// again on what is left: 180 and 540. The gift card line comes to
	// 3000 - 300 + 540 = 3240 of the 4320 total.
	$lines = array(gift_card_pays_gift_card_test_line(1000, 0, 200, '20.000'), gift_card_pays_gift_card_test_line(3000, 1, 600, '20.000'));
	$charged = pg_order_charged_today_totals($lines, 400, '2026-11-17', false);

	pg_assert_same(3240, pg_gift_card_lines_part($charged['charged'], $charged['discount']));

	$totals = pg_express_order_charged_totals($lines, 400, 0, array('credit' => 99999, 'cards' => 0), 0, '2026-11-17', false);
	pg_assert_same(1080, $totals['gift_card_discount']);
	pg_assert_same(3240, $totals['total']);
}

function test_gift_card_plain_cards_fill_the_rest()
{
	// Credit is held to the part that is not gift card lines; plain cards
	// pay what is left of the total.
	pg_assert_same(array('credit' => 100, 'cards' => 5000, 'discount' => 5100), pg_gift_card_applied_discount(10100, 10000, 10100, 5000));
	pg_assert_same(array('credit' => 100, 'cards' => 10000, 'discount' => 10100), pg_gift_card_applied_discount(10100, 10000, 10100, 99999));
	pg_assert_same(array('credit' => 0, 'cards' => 0, 'discount' => 0), pg_gift_card_applied_discount(0, 0, 500, 500));
}

function test_gift_card_cart_of_gift_cards_alone_unchanged()
{
	$lines = array(gift_card_pays_gift_card_test_line(5000, 1), gift_card_pays_gift_card_test_line(2000, 1));

	$totals = pg_express_order_charged_totals($lines, 0, 0, array('credit' => 9000, 'cards' => 9000), 0, '2026-11-17', false);
	pg_assert_same(0, $totals['gift_card_discount']);
	pg_assert_same(7000, $totals['total']);
}

function test_gift_card_cart_without_gift_card_lines_unchanged()
{
	$lines = array(gift_card_pays_gift_card_test_line(1000, 0, 200, '20.000'), gift_card_pays_gift_card_test_line(500, 0, 100, '20.000'));

	pg_assert_same(0, pg_gift_card_lines_part(pg_order_charged_today_totals($lines, 300, '2026-11-17', false)['charged'], 300));

	$totals = pg_express_order_charged_totals($lines, 300, 0, array('credit' => 600, 'cards' => 200), 0, '2026-11-17', false);
	pg_assert_same(800, $totals['gift_card_discount']);
	pg_assert_same(640, $totals['total']);

	$totals = pg_express_order_charged_totals($lines, 0, 0, array('credit' => 99999, 'cards' => 0), 0, '2026-11-17', false);
	pg_assert_same(1800, $totals['gift_card_discount']);
	pg_assert_same(0, $totals['total']);
}
