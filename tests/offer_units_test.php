<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the offer engine's unit count (includes/fn/ecommerce.php): how
 * many units of a cart line a product discount covers, and the unit price a
 * line is charged when only some of its units are discounted ("the cheapest
 * item is free", a gift taken from a line the shopper is already paying for).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// "The cheapest item is free" frees one item; a product or a group target
// discounts every unit of the line.
function test_offer_units_cheapest_target_covers_one_unit()
{
	pg_assert_same(1, pg_offer_action_discounted_units(array('discount_product_target' => 'cheapest'), 2));
	pg_assert_same(1, pg_offer_action_discounted_units(array('discount_product_target' => 'cheapest'), '10'), 'quantity as the database gives it');
	pg_assert_same(1, pg_offer_action_discounted_units(array('discount_product_target' => 'cheapest'), 1));
	pg_assert_same(0, pg_offer_action_discounted_units(array('discount_product_target' => 'cheapest'), 0), 'an empty line');
}

function test_offer_units_product_and_group_targets_cover_the_line()
{
	pg_assert_same(5, pg_offer_action_discounted_units(array('discount_product_target' => 'product'), 5));
	pg_assert_same(5, pg_offer_action_discounted_units(array('discount_product_target' => 'group'), '5'));
	pg_assert_same(3, pg_offer_action_discounted_units(array(), 3), 'a row written before the target column');
}

// One free item on a line of two: each unit is charged half.
function test_offer_units_one_free_unit_is_spread_over_the_line()
{
	pg_assert_same(5000, pg_offer_spread_unit_price(10000, 2, 0, 1), 'X x2, one free');
	pg_assert_same(900, pg_offer_spread_unit_price('1000', '10', 0, 1), 'Y x10, one free');
	pg_assert_same(5000, pg_offer_spread_unit_price(10000, 4, 0, 2), 'two of four free');
}

// A line whose every unit is discounted gets the discounted price as before,
// fractions included (the column rounds it).
function test_offer_units_whole_line_keeps_the_discounted_price()
{
	pg_assert_same(0, pg_offer_spread_unit_price(10000, 1, 0, 1), 'one unit, free');
	pg_assert_same(0, pg_offer_spread_unit_price(10000, 2, 0, 3), 'gift larger than the line');
	pg_assert_same(12749.15, pg_offer_spread_unit_price(14999, 1, 12749.15, 1), '15% off one unit');
	pg_assert_same(500, pg_offer_spread_unit_price(1000, 0, 500, 0), 'an empty line');
}

function test_offer_units_nothing_discounted_keeps_the_line_price()
{
	pg_assert_same(1000, pg_offer_spread_unit_price(1000, 3, 0, 0));
}

// A gift taken from a line the shopper pays for is worked out from the line's
// total: the gift's units come off at the paid units' own price, not at the
// line's unit price, which may already be the average "the cheapest item is
// free" or an earlier gift spread over the line.
function test_offer_units_gift_on_a_plain_line_matches_the_spread()
{
	pg_assert_same(1333, pg_offer_gift_line_unit_price(2000, 3, 2000, 0, 1), 'Y x3 at 20,00, one free: 39,99');
	pg_assert_same(5000, pg_offer_gift_line_unit_price(10000, 2, 10000, 0, 1), 'X x2, one free');
	pg_assert_same(833, pg_offer_gift_line_unit_price(1000, 3, 1000, 500, 1), 'half off one of three');
	pg_assert_same(720, pg_offer_gift_line_unit_price(900, 5, 900, 0, 1), 'Y x5 at 10% off, one free: 4 x 900');
}

// "The cheapest item is free" already spread one free unit over the line
// (Y x3 at 10,00 -> 666); a gift of one more unit takes a paid unit at 10,00
// off the line's total, so two units are free, not one and a bit.
function test_offer_units_gift_on_a_cheapest_free_line_frees_a_second_unit()
{
	pg_assert_same(0, pg_offer_gift_line_unit_price(500, 2, 1000, 0, 1), 'Y x2: both free');
	pg_assert_same(332, pg_offer_gift_line_unit_price(666, 3, 1000, 0, 1), 'Y x3: one paid, 9,96 after two rounded spreads');
	pg_assert_same(500, pg_offer_gift_line_unit_price(750, 4, 1000, 0, 1), 'Y x4: two paid, 20,00');
	pg_assert_same(600, pg_offer_gift_line_unit_price(800, 5, 1000, 0, 1), 'Y x5: three paid, 30,00');
	pg_assert_same(0, pg_offer_gift_line_unit_price(666, 3, 1000, 0, 2), 'Y x3, a gift of two: nothing left to pay');
}

// Two gifts of the same product on one line: the second one is taken from a
// line the first already spread (Y x5 -> 800), again at the paid units' price.
function test_offer_units_second_gift_on_the_same_line()
{
	pg_assert_same(600, pg_offer_gift_line_unit_price(800, 5, 1000, 0, 1), 'Y x5, two gifts: 30,00');
	pg_assert_same(0, pg_offer_gift_line_unit_price(500, 2, 1000, 0, 1), 'Y x2, two gifts: 0');
}

// A gift never raises the line's price, and covers the whole line at most.
function test_offer_units_gift_line_price_bounds()
{
	pg_assert_same(800, pg_offer_gift_line_unit_price(800, 5, 1000, 1200, 1), 'gift dearer than the paid units: unchanged');
	pg_assert_same(500, pg_offer_gift_line_unit_price(500, 2, 1000, 0, 0), 'no gift units: unchanged');
	pg_assert_same(0, pg_offer_gift_line_unit_price(1000, 2, 1000, 0, 2), 'gift as large as the line: the gift price');
	pg_assert_same(250, pg_offer_gift_line_unit_price(1000, 2, 1000, 250, 3), 'gift larger than the line: the gift price');
	pg_assert_same(0, pg_offer_gift_line_unit_price(0, 3, 1000, 0, 1), 'a line already free stays free');
	pg_assert_same(666, pg_offer_gift_line_unit_price('666', '3', '1000', '0', '0'), 'values as the database gives them');
}

// The spread price rounds down: the line never costs more than its units at
// their own prices, and the customer keeps less than one minor unit per unit.
function test_offer_units_spread_price_rounds_down()
{
	pg_assert_same(666, pg_offer_spread_unit_price(1000, 3, 0, 1), '2000 / 3');
	pg_assert_same(7499, pg_offer_spread_unit_price(14999, 2, 0, 1), '14999 / 2');
	pg_assert_same(833, pg_offer_spread_unit_price(1000, 3, 500, 1), 'half off one of three');
	pg_assert_same(13874, pg_offer_spread_unit_price(14999, 2, 12749.15, 1), '15% off one of two, unit rounded first');

	foreach (array(1, 7, 99, 999, 1499, 14999) as $price) {
		for ($quantity = 2; $quantity <= 7; $quantity++) {
			for ($free = 1; $free < $quantity; $free++) {
				$exact = ($price * ($quantity - $free)) + (round($price / 4) * $free);
				$charged = pg_offer_spread_unit_price($price, $quantity, round($price / 4), $free) * $quantity;
				pg_assert_true(($charged <= $exact) && (($exact - $charged) < $quantity), "price $price, quantity $quantity, discounted $free: charged $charged of $exact");
			}
		}
	}
}
