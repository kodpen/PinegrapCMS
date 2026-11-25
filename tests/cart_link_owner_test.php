<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for whose account a cart becomes when it is opened through its
 * saved-cart link (?r=): the signed-in opener's, or no account for a guest.
 * The order used to stay on the account that built the cart, which then saw
 * the buyer's billing details in its order history and View Order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cart_link_owner.php');

// A signed-in opener takes the order over; the same account on another
// device writes nothing.
function test_cart_link_owner_signed_in_opener()
{
	pg_assert_same(array('user_id' => 7, 'contact_id' => 70), pg_cart_link_owner(array('user_id' => 3, 'contact_id' => 30), true, false, 7, 70));
	pg_assert_same(array('user_id' => 7, 'contact_id' => 0), pg_cart_link_owner(array('user_id' => 0, 'contact_id' => 0), true, false, '7', null));
	pg_assert_same(null, pg_cart_link_owner(array('user_id' => '3', 'contact_id' => '30'), true, false, 3, 30));
	pg_assert_same(null, pg_cart_link_owner(array('user_id' => '3', 'contact_id' => '0'), true, false, '3', null));
}

// A guest opener leaves the order with no account; a guest cart stays so.
function test_cart_link_owner_guest_opener()
{
	pg_assert_same(array('user_id' => 0, 'contact_id' => 0), pg_cart_link_owner(array('user_id' => 3, 'contact_id' => 30), false, false, '', ''));
	pg_assert_same(array('user_id' => 0, 'contact_id' => 0), pg_cart_link_owner(array('user_id' => 0, 'contact_id' => 30), false, false, '', ''));
	pg_assert_same(null, pg_cart_link_owner(array('user_id' => '0', 'contact_id' => '0'), false, false, '', ''));
}

// Staff ghosting a customer act as that customer: the order is not moved,
// as initialize_order() does not rebind it either.
function test_cart_link_owner_ghost_keeps_owner()
{
	pg_assert_same(null, pg_cart_link_owner(array('user_id' => 3, 'contact_id' => 30), true, true, 7, 70));
}

// A builder completing their own cart after someone else opened the link gets
// it back: the checkout's initialize_order() rebinds a session order to the
// signed-in visitor, and the pages that open a link call the take-over.
function test_cart_link_owner_wired_into_both_entry_points()
{
	$ecommerce = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	pg_assert_true(strpos($ecommerce, "db_q(\"UPDATE orders SET user_id = ?, contact_id = ? WHERE id = ?\"") !== false, 'initialize_order() rebinds a session order');

	$retrieve = file_get_contents(PG_FUNCTIONS_DIR . '/retrieve_order.php');
	pg_assert_true(strpos($retrieve, 'pg_cart_link_take_over(') !== false, 'retrieve_order() takes the order over');

	$widget = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');
	pg_assert_true(strpos($widget, 'pg_cart_link_take_over(') !== false, 'the designer widgets take the order over');
}
