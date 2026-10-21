<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the price an offer's gift is charged at (includes/fn/ecommerce.php,
 * pg_offer_gift_price()): the product's price less the action's amount or
 * percentage, rounded, and never below zero.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_offer_gift_price_amount()
{
	pg_assert_same(2000, pg_offer_gift_price(3000, 1000, 0));
	pg_assert_same(2000, pg_offer_gift_price('3000', '1000', '0'), 'as the database gives it');
	pg_assert_same(0, pg_offer_gift_price(3000, 3000, 0), 'the whole price');
}

// An amount larger than the price, or a price lowered after the offer was set
// up, gives a free gift, not a line that takes money off the order.
function test_offer_gift_price_never_below_zero()
{
	pg_assert_same(0, pg_offer_gift_price(3000, 5000, 0), 'amount above the price');
	pg_assert_same(0, pg_offer_gift_price('3000', '5000', '0'), 'as the database gives it');
	pg_assert_same(0, pg_offer_gift_price(0, 1000, 0), 'a free product');
	pg_assert_same(0, pg_offer_gift_price(null, 1000, 0), 'a product that is gone');
}

function test_offer_gift_price_percentage()
{
	pg_assert_same(0, pg_offer_gift_price(3000, 0, 100), 'free');
	pg_assert_same(1500, pg_offer_gift_price(3000, 0, 50));
	pg_assert_same(1699, pg_offer_gift_price(1999, 0, 15), 'rounded down, 1699.15');
	pg_assert_same(851, pg_offer_gift_price(1001, 0, '15'), 'rounded up, 850.85');
	pg_assert_same(3000, pg_offer_gift_price(3000, 0, 0), 'no discount');
}
