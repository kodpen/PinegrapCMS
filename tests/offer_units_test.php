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
