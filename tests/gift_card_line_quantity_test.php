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

function test_gift_card_quantity_refusal_is_taken_once()
{
	pg_gift_card_quantity_refusal(false);
	pg_assert_false(pg_gift_card_quantity_refusal_take(), 'nothing refused yet');

	pg_gift_card_quantity_refusal(true);
	pg_assert_true(pg_gift_card_quantity_refusal_take(), 'a refused add is reported');
	pg_assert_false(pg_gift_card_quantity_refusal_take(), 'and only once');
}

function test_gift_card_line_limit_is_checked_at_every_door()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$doors = array(
		'includes/fn/ecommerce.php' => 'pg_gift_card_quantity_refusal(true)',
		'shopping_cart.php' => 'pg_gift_card_line_quantity_allowed(',
		'express_order.php' => 'pg_gift_card_line_quantity_allowed(',
		'cart_action.php' => 'pg_order_item_quantity_allowed(',
		'submit_order.php' => 'pg_order_gift_card_lines_over_limit(',
	);

	foreach ($doors as $file => $call) {
		pg_assert_true(strpos((string) file_get_contents($root . $file), $call) !== false, $file . ' checks the gift card line limit');
	}

	foreach (array('shopping_cart.php', 'express_order.php', 'cart_action.php', 'add_to_cart.php', 'catalog_detail.php', 'order_form.php') as $file) {
		pg_assert_true(strpos((string) file_get_contents($root . $file), 'pg_gift_card_quantity_refusal_take()') !== false, $file . ' shows a refused gift card add');
	}
}
