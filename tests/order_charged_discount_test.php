<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order discount that today's charge takes off
 * (includes/fn/ecommerce.php): which cart lines are charged when the order is
 * placed, and that the discount is never more than their subtotal.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_charged_discount_within_the_subtotal()
{
	pg_assert_same(1000, pg_order_charged_discount(1000, 3000));
	pg_assert_same(1000, pg_order_charged_discount('1000', '3000'), 'as the session and the database give it');
	pg_assert_same(1500, pg_order_charged_discount(1500.0, 3000), 'round() gives a float');
	pg_assert_same(3000, pg_order_charged_discount(3000, 3000), 'the whole subtotal');
}

// The cart works the discount out over every line; a recurring line that
// starts later is not in today's subtotal.
function test_order_charged_discount_never_above_the_subtotal()
{
	pg_assert_same(1000, pg_order_charged_discount(5500, 1000), 'a mixed cart');
	pg_assert_same(0, pg_order_charged_discount(5000, 0), 'only a subscription that starts later');
	pg_assert_same(0, pg_order_charged_discount(5000, -200), 'a subtotal below zero');
}

function test_order_charged_discount_nothing()
{
	pg_assert_same(0, pg_order_charged_discount(0, 3000));
	pg_assert_same(0, pg_order_charged_discount(null, 3000), 'no discount in the session');
	pg_assert_same(0, pg_order_charged_discount(-100, 3000), 'never below zero');
}

function test_order_charged_discount_line_charged_today()
{
	pg_assert_true(pg_order_line_charged_today(0, '0000-00-00', 0, '2026-10-20', false), 'not recurring');
	pg_assert_true(pg_order_line_charged_today(null, '', null, '2026-10-20', true), 'not recurring, ClearCommerce');
	pg_assert_true(pg_order_line_charged_today(1, '2026-10-20', 30, '2026-10-20', false), 'starts today');
	pg_assert_true(pg_order_line_charged_today('1', '2026-09-01', 0, '2026-10-20', false), 'started before');
	pg_assert_false(pg_order_line_charged_today(1, '2026-10-21', 0, '2026-10-20', false), 'starts tomorrow');
	pg_assert_false(pg_order_line_charged_today(1, '2026-10-20', 0, '2026-10-20', true), 'ClearCommerce charges it itself');
}

// Before the order is submitted a recurring line has no start date yet: it
// starts the product's delay after today.
function test_order_charged_discount_line_without_a_start_date()
{
	pg_assert_true(pg_order_line_charged_today(1, '0000-00-00', 0, '2026-10-20', false), 'no delay');
	pg_assert_true(pg_order_line_charged_today(1, '', '0', '2026-10-20', false), 'no delay, empty date');
	pg_assert_false(pg_order_line_charged_today(1, '0000-00-00', 30, '2026-10-20', false), 'starts in 30 days');
	pg_assert_false(pg_order_line_charged_today(1, null, '1', '2026-12-31', false), 'starts next year');
}
