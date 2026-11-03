<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the price a shopping cart row strikes through
 * (_pg_cart_row_reference_cents(), includes/fn/widgets_cart.php): the
 * product's price for a row an offer or a member price lowered, the row's own
 * price for a donation, whose amount the visitor chooses and is no discount.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A lowered row keeps the product's price to strike through.
function test_cart_row_reference_cents_keeps_the_product_price()
{
	pg_assert_same(29995, _pg_cart_row_reference_cents(26996, 29995, 'quantity'), 'offer price');
	pg_assert_same(29995, _pg_cart_row_reference_cents(29995, 29995, ''), 'no discount');
	pg_assert_same(5000, _pg_cart_row_reference_cents(5000, null, 'quantity'), 'product gone: the row price');
}

// A donation below the product's suggested amount is what the visitor gives,
// not a discount.
function test_cart_row_reference_cents_donation_is_never_discounted()
{
	pg_assert_same(1000, _pg_cart_row_reference_cents(1000, 5000, 'donation'), 'smaller donation');
	pg_assert_same(9000, _pg_cart_row_reference_cents(9000, 5000, 'donation'), 'larger donation');
}

// The cart rows read their reference through it.
function test_cart_rows_use_reference_cents()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	pg_assert_true(strpos($src, '$orig_cents = _pg_cart_row_reference_cents(') !== false, 'shopping cart rows');
}
