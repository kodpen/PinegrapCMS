<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for check_for_products_in_cart() (check_for_products_in_cart.php):
 * the cart lines an offer's "required products" rule, its product-group
 * condition and its up-sell nudge count. A line an offer put in the cart
 * (order_items.added_by_offer = 1) is not counted, so a gift cannot keep
 * alive the offer that placed it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The query that reads the cart's lines, cut out of the function's source.
function offer_required_products_gift_query()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/check_for_products_in_cart.php');
	$start = strpos($source, 'SELECT product_id');
	pg_assert_true($start !== false, 'the cart query is found');
	$end = strpos($source, 'GROUP BY', $start);
	pg_assert_true($end !== false, 'the cart query ends with its GROUP BY');

	return (string) substr($source, $start, $end - $start);
}

// "Buy 2 from {X, Y}, get Y free": X x2 places the gift Y as its own line.
// With one X taken out, X x1 plus the gift made 2 again and the free Y stayed.
function test_offer_required_products_leave_out_offer_lines()
{
	pg_assert_contains("added_by_offer = '0'", offer_required_products_gift_query(), 'gift lines are not counted');
}

// A line saved for later still counts toward nothing (cart-save-for-later.md).
function test_offer_required_products_keep_the_active_line_filter()
{
	pg_assert_contains('pg_order_item_active_sql()', offer_required_products_gift_query(), 'saved lines are not counted');
}
