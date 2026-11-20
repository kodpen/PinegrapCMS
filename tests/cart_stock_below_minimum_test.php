<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stock check of a cart line with no bundle
 * (pg_cart_line_stock_allowance(), used by check_inventory()). A line the
 * stock would cut below its product's minimum quantity used to be cut
 * anyway; check_quantity() raised it back on the next submit, so the order
 * bounced between two notices and never went through. Such a line is now
 * removed and leaves its units to the lines after it, as in a cart with a
 * bundle (pg_bundle_cart_allowance()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Stock 2 under a minimum of 3: the line is removed as not available.
function test_cart_line_stock_below_minimum_is_removed()
{
	$answer = pg_cart_line_stock_allowance(3, 2, 2, 3);
	pg_assert_same(0, $answer['allowed'], 'nothing allowed');
	pg_assert_same(true, $answer['remove'], 'line removed');
	pg_assert_same(true, $answer['unavailable'], 'not currently available');
}

// Enough stock for the minimum: the line is cut to the stock as before.
function test_cart_line_stock_cut_down_to_minimum_or_more()
{
	$answer = pg_cart_line_stock_allowance(5, 3, 3, 3);
	pg_assert_same(3, $answer['allowed'], 'cut to the stock');
	pg_assert_same(false, $answer['remove'], 'kept');

	$answer = pg_cart_line_stock_allowance(5, 4, 4, 0);
	pg_assert_same(4, $answer['allowed'], 'no minimum');
	pg_assert_same(false, $answer['remove'], 'kept without minimum');
}

// A line the stock covers keeps its quantity.
function test_cart_line_stock_covered()
{
	$answer = pg_cart_line_stock_allowance(2, 5, 5, 3);
	pg_assert_same(2, $answer['allowed'], 'covered');
	pg_assert_same(false, $answer['remove'], 'kept');
}

// Units an earlier line took: the stock alone covered the minimum, so the
// notice is the "limited" one.
function test_cart_line_stock_limited_by_earlier_lines()
{
	$answer = pg_cart_line_stock_allowance(3, 1, 4, 3);
	pg_assert_same(0, $answer['allowed'], 'nothing allowed');
	pg_assert_same(true, $answer['remove'], 'removed');
	pg_assert_same(false, $answer['unavailable'], 'limited notice');
}

// No stock left: removed as before, "not available" only when the product
// had none to begin with.
function test_cart_line_stock_none_left()
{
	$answer = pg_cart_line_stock_allowance(1, 0, 0, 0);
	pg_assert_same(true, $answer['remove'], 'removed');
	pg_assert_same(true, $answer['unavailable'], 'out of stock');

	$answer = pg_cart_line_stock_allowance(1, 0, 2, 0);
	pg_assert_same(true, $answer['remove'], 'removed');
	pg_assert_same(false, $answer['unavailable'], 'taken by earlier lines');
}
