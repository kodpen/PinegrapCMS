<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the gift card line quantity limit: a gift card line issues one
 * card per unit, each with its own recipient form, at most 100. A line of
 * more units must be refused in the cart and at checkout, so the amount
 * charged always matches the cards issued.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_gift_card_line_quantity_limit_matches_issued_cards()
{
	pg_assert_true(function_exists('pg_gift_card_line_quantity_allowed'), 'the limit has a helper');

	if (!function_exists('pg_gift_card_line_quantity_allowed')) {
		return;
	}

	pg_assert_true(pg_gift_card_line_quantity_allowed(1), 'one card');
	pg_assert_true(pg_gift_card_line_quantity_allowed(100), '100 cards');
	pg_assert_false(pg_gift_card_line_quantity_allowed(101), '101 units would be charged for 100 cards');
	pg_assert_false(pg_gift_card_line_quantity_allowed(150), '150 units would be charged for 100 cards');

	// Every allowed quantity issues exactly as many cards as it charges.
	foreach (array(1, 7, 99, 100) as $quantity) {
		pg_assert_same($quantity, pg_order_gift_card_count($quantity), $quantity . ' units issue ' . $quantity . ' cards');
	}
}
