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

	// Any result at or below zero, a free or negative price included.
	pg_assert_same(array('price' => 0, 'refused' => true), pg_bulk_price_change(0, '3', '10'), 'a free product');
	pg_assert_same(array('price' => 0, 'refused' => true), pg_bulk_price_change(0, '1', 0), 'a free product, nothing off');
	pg_assert_same(array('price' => -1000, 'refused' => true), pg_bulk_price_change(-1000, '3', '25'), 'a negative price');
	pg_assert_same(array('price' => -1000, 'refused' => true), pg_bulk_price_change(-1000, '1', -500), 'a negative price raised');
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

// Stored prices come as strings; an amount rounds to whole minor units; an
// unknown method changes nothing, a near miss of a method's name included.
function test_bulk_price_change_reads_stored_values()
{
	pg_assert_same(array('price' => 1250, 'refused' => false), pg_bulk_price_change('1000', 2, '250'));
	pg_assert_same(array('price' => 1251, 'refused' => false), pg_bulk_price_change(1000, '2', 250.7), 'rounded, not cut');
	pg_assert_same(array('price' => 1000, 'refused' => false), pg_bulk_price_change('1000', '5', 300));
	pg_assert_same(array('price' => 1000, 'refused' => false), pg_bulk_price_change(1000, '01', 300));
	pg_assert_same(array('price' => 0, 'refused' => false), pg_bulk_price_change(0, ' 3', '10'));
}

// The screen refuses an empty, non-numeric or non-positive value before any
// product is touched, as the Catalog Manager's bulk price change does.
function test_bulk_price_change_screen_refuses_a_value_that_is_no_amount()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');

	pg_assert_contains("!is_numeric(\$raw_price_value) || ((float) \$raw_price_value <= 0)", $source);
	pg_assert_contains("lang('Enter an amount for the price change.')", $source);
	pg_assert_contains("(\$_POST['edit_change_price_method'] != '') && !\$price_value_refused", $source);
}
