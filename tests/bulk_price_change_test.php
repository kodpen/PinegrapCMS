<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the price a bulk product edit gives a product
 * (includes/fn/ecommerce.php pg_bulk_price_change(), used by
 * edit_products.php): a change never leaves a product it lowers at or below
 * zero, whichever of the four methods is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The two decrease methods keep refusing a price at or below zero.
function test_bulk_price_change_decrease_keeps_the_price_above_zero()
{
	pg_assert_same(array('price' => 700, 'refused' => false), pg_bulk_price_change(1000, '1', 300));
	pg_assert_same(array('price' => 1000, 'refused' => true), pg_bulk_price_change(1000, '1', 1000), 'down to zero');
	pg_assert_same(array('price' => 1000, 'refused' => true), pg_bulk_price_change(1000, '1', 1500), 'below zero');
	pg_assert_same(array('price' => 750, 'refused' => false), pg_bulk_price_change(1000, '3', '25'));
	pg_assert_same(array('price' => 1000, 'refused' => true), pg_bulk_price_change(1000, '3', '100'), 'all of it');
}

// An increase by a negative amount or percentage is a decrease: it may lower
// a price, never to zero or below (increase by -150% wrote -5.00 for 10.00).
function test_bulk_price_change_increase_by_a_negative_value_stays_above_zero()
{
	pg_assert_same(array('price' => 500, 'refused' => false), pg_bulk_price_change(1000, '2', -500));
	pg_assert_same(array('price' => 1000, 'refused' => true), pg_bulk_price_change(1000, '2', -1000), 'down to zero');
	pg_assert_same(array('price' => 1000, 'refused' => true), pg_bulk_price_change(1000, '2', -1500), 'below zero');
	pg_assert_same(array('price' => 500, 'refused' => false), pg_bulk_price_change(1000, '4', '-50'));
	pg_assert_same(array('price' => 1000, 'refused' => true), pg_bulk_price_change(1000, '4', '-100'), 'down to zero');
	pg_assert_same(array('price' => 1000, 'refused' => true), pg_bulk_price_change(1000, '4', '-150'), 'below zero');
}

// Increases come out as before; a free product stays free.
function test_bulk_price_change_increase_as_before()
{
	pg_assert_same(array('price' => 1500, 'refused' => false), pg_bulk_price_change(1000, '2', 500));
	pg_assert_same(array('price' => 1099, 'refused' => false), pg_bulk_price_change(999, '4', '10'), 'round(1098.9)');
	pg_assert_same(array('price' => 500, 'refused' => false), pg_bulk_price_change(0, '2', 500));
	pg_assert_same(array('price' => 0, 'refused' => false), pg_bulk_price_change(0, '4', '10'), 'nothing to raise');
	pg_assert_same(array('price' => 0, 'refused' => false), pg_bulk_price_change(0, '2', 0));
}

// A price already below zero may be raised towards zero, not lowered.
function test_bulk_price_change_a_negative_price_only_goes_up()
{
	pg_assert_same(array('price' => -400, 'refused' => false), pg_bulk_price_change(-500, '2', 100));
	pg_assert_same(array('price' => -500, 'refused' => true), pg_bulk_price_change(-500, '2', -100));
}

// Stored prices come as strings; an unknown method changes nothing.
function test_bulk_price_change_reads_stored_values()
{
	pg_assert_same(array('price' => 1250, 'refused' => false), pg_bulk_price_change('1000', 2, '250'));
	pg_assert_same(array('price' => 1000, 'refused' => false), pg_bulk_price_change('1000', '5', 300));
}
