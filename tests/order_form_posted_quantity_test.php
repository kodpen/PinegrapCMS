<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quantity the order form's drop-down layout posts
 * (pg_order_form_posted_quantity()). The digit check alone let 0 through,
 * so a quantity product was added as a cart line of quantity 0 and a
 * donation as a line of amount 0.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A whole number of at least one is the quantity.
function test_order_form_posted_quantity_whole_numbers()
{
	pg_assert_same(1, pg_order_form_posted_quantity('1'), 'one');
	pg_assert_same(7, pg_order_form_posted_quantity('7'), 'seven');
	pg_assert_same(5, pg_order_form_posted_quantity('005'), 'leading zeros');
	pg_assert_same(120, pg_order_form_posted_quantity('120'), 'hundreds');
}

// Zero and anything that is not a whole number is refused (0).
function test_order_form_posted_quantity_refuses_zero_and_junk()
{
	pg_assert_same(0, pg_order_form_posted_quantity('0'), 'zero');
	pg_assert_same(0, pg_order_form_posted_quantity('000'), 'zeros');
	pg_assert_same(0, pg_order_form_posted_quantity(''), 'empty');
	pg_assert_same(0, pg_order_form_posted_quantity('-2'), 'negative');
	pg_assert_same(0, pg_order_form_posted_quantity('1.5'), 'fraction');
	pg_assert_same(0, pg_order_form_posted_quantity('abc'), 'text');
	pg_assert_same(0, pg_order_form_posted_quantity(null), 'null');
	pg_assert_same(0, pg_order_form_posted_quantity(array('3')), 'array');
}
