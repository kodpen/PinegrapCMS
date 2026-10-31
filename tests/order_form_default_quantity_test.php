<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_product_default_quantity() in includes/fn/ecommerce.php: the
 * quantity a checked, auto-selected or quick-added product enters the cart
 * with when no quantity box is shown. An empty or zero default is one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// NULL, '', 0 and negative defaults add one unit, never zero.
function test_order_form_default_quantity_empty_is_one()
{
	pg_assert_same(1, pg_product_default_quantity(null), 'null');
	pg_assert_same(1, pg_product_default_quantity(''), 'empty');
	pg_assert_same(1, pg_product_default_quantity('0'), 'zero text');
	pg_assert_same(1, pg_product_default_quantity(0), 'zero');
	pg_assert_same(1, pg_product_default_quantity(-3), 'negative');
	pg_assert_same(1, pg_product_default_quantity('abc'), 'not a number');
}

// A real default is kept as an integer.
function test_order_form_default_quantity_keeps_set_value()
{
	pg_assert_same(1, pg_product_default_quantity('1'), 'one');
	pg_assert_same(3, pg_product_default_quantity('3'), 'three text');
	pg_assert_same(12, pg_product_default_quantity(12), 'twelve');
}
