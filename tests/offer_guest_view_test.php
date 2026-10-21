<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guest view of the offer checks (includes/fn/ecommerce.php):
 * pg_offer_guest_view() is switched on while prices for everybody are worked
 * out, and the cart readers then see an empty cart whatever the shopping
 * session holds, without reading the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_offer_guest_view_switches_and_reads()
{
	pg_assert_false(pg_offer_guest_view(), 'off by default');
	pg_assert_true(pg_offer_guest_view(true), 'switched on');
	pg_assert_true(pg_offer_guest_view(), 'reading keeps it on');
	pg_assert_false(pg_offer_guest_view(false), 'switched off');
	pg_assert_false(pg_offer_guest_view(), 'reading keeps it off');
}

function test_offer_guest_view_sees_an_empty_cart()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('ecommerce' => array('order_id' => '41', 'special_offer_code' => 'CODE'));

	require_once(PG_FUNCTIONS_DIR . '/check_for_products_in_cart.php');
	pg_offer_guest_view(true);
	pg_assert_same(0, pg_offer_cart_quantity(), 'no pieces in the cart');
	pg_assert_same(0, pg_offer_cart_quantity(5), 'no pieces for a recipient either');
	pg_assert_same(0, get_order_subtotal(), 'no subtotal');
	pg_assert_false(check_for_products_in_cart(array('products' => array(array('id' => 7)), 'quantity' => 1)), 'no products in the cart');
	pg_offer_guest_view(false);
	pg_assert_same('41', $_SESSION['ecommerce']['order_id'], 'the session is left as it was');

	$_SESSION = $saved;
}
