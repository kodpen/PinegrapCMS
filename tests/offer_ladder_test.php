<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order discount (includes/fn/ecommerce.php): which percentage
 * a tiered order discount takes off a subtotal, that a cart below the first
 * rung gets nothing rather than the first rung's rate the action row keeps,
 * what one action takes off, and which of several order discounts wins.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The offer editor's "tiered cart discount" template, as pg_offer_tiers()
// returns it: highest rung first, amounts in minor units.
function _offer_ladder_template()
{
	return array(
		array('min' => 50000, 'percent' => 20),
		array('min' => 25000, 'percent' => 10),
		array('min' => 10000, 'percent' => 5));
}

function test_offer_ladder_below_the_first_rung_discounts_nothing()
{
	pg_assert_same(0, pg_offer_order_discount_percentage(_offer_ladder_template(), 5000, 5), '50 TL cart');
	pg_assert_same(0, pg_offer_order_discount_percentage(_offer_ladder_template(), 9999, 5), 'one kuruş short');
	pg_assert_same(0, pg_offer_order_discount_percentage(_offer_ladder_template(), 0, 5), 'empty cart');
}

function test_offer_ladder_takes_the_rung_reached()
{
	pg_assert_same(5, pg_offer_order_discount_percentage(_offer_ladder_template(), 10000, 5), 'exactly the first rung');
	pg_assert_same(5, pg_offer_order_discount_percentage(_offer_ladder_template(), 24999, 5));
	pg_assert_same(10, pg_offer_order_discount_percentage(_offer_ladder_template(), 25000, 5));
	pg_assert_same(20, pg_offer_order_discount_percentage(_offer_ladder_template(), 120000, 5), 'above the top rung');
	pg_assert_same(10, pg_offer_order_discount_percentage(_offer_ladder_template(), '30000', 5), 'subtotal as a string');
}

// A ladder that starts at zero gives every cart its first rung.
function test_offer_ladder_from_zero_covers_every_cart()
{
	$tiers = array(array('min' => 20000, 'percent' => 15), array('min' => 0, 'percent' => 3));
	pg_assert_same(3, pg_offer_order_discount_percentage($tiers, 0, 3));
	pg_assert_same(3, pg_offer_order_discount_percentage($tiers, 19999, 3));
	pg_assert_same(15, pg_offer_order_discount_percentage($tiers, 20000, 3));
}

// Without a ladder the action's own percentage stands.
function test_offer_ladder_none_keeps_the_action_percentage()
{
	pg_assert_same(5, pg_offer_order_discount_percentage(array(), 5000, 5));
	pg_assert_same('12', pg_offer_order_discount_percentage(array(), 100, '12'), 'as the database gives it');
}

function test_offer_ladder_value_of_one_action()
{
	pg_assert_same(0, pg_offer_order_discount_value(0, 5, _offer_ladder_template(), 5000), 'below the first rung');
	pg_assert_same(500, pg_offer_order_discount_value(0, 5, _offer_ladder_template(), 10000), 'first rung');
	pg_assert_same(3000, pg_offer_order_discount_value(0, 5, _offer_ladder_template(), 30000), 'second rung');
	pg_assert_same(250, pg_offer_order_discount_value(0, 5, array(), 5000), 'flat percentage');
	pg_assert_same(17, pg_offer_order_discount_value(0, '5', array(), 333), 'rounded, 16.65');
	pg_assert_same(1000, pg_offer_order_discount_value('1000', 0, array(), 5000), 'amount');
	pg_assert_same(500, pg_offer_order_discount_value('1000', 0, array(), 500), 'amount above the subtotal');
	pg_assert_same(1000, pg_offer_order_discount_value(1000, 10, array(), 50000), 'an amount comes before a percentage');
	pg_assert_same(0, pg_offer_order_discount_value(0, 10, array(), 0), 'empty cart');
}

function test_offer_ladder_best_order_discount()
{
	$ladder = array('offer_id' => 7, 'amount' => 0, 'percentage' => 5, 'tiers' => _offer_ladder_template());
	$flat = array('offer_id' => 9, 'amount' => 0, 'percentage' => 3, 'tiers' => array());
	$amount = array('offer_id' => 11, 'amount' => '1000', 'percentage' => 0, 'tiers' => array());

	pg_assert_same(array(0, 0), pg_offer_best_order_discount(array(), 5000), 'no candidates');
	pg_assert_same(array(0, 0), pg_offer_best_order_discount(array($ladder), 5000), 'only a ladder, below its first rung');
	pg_assert_same(array(9, 150), pg_offer_best_order_discount(array($ladder, $flat), 5000), 'below the rung the flat offer stands');
	pg_assert_same(array(7, 600), pg_offer_best_order_discount(array($ladder, $flat), 12000), 'from the rung on the ladder wins');
	pg_assert_same(array(11, 1000), pg_offer_best_order_discount(array($ladder, $amount), 12000), 'an amount worth more');
	pg_assert_same(array(7, 1000), pg_offer_best_order_discount(array($ladder, $amount), 20000), 'a tie goes to the first');
}
