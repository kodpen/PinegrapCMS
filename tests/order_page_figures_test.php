<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order discount, tax and total an order's page shows,
 * pg_order_page_figures() (includes/fn/ecommerce.php): a placed order's own
 * figures, never the visitor's cart's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An orders row as the pages read it, with the database's strings.
function pg_test_order_page_row($status, $discount = '0', $tax = '0', $total = '0', $id = '448')
{
	return array('id' => $id, 'status' => $status, 'discount' => $discount, 'tax' => $tax, 'total' => $total);
}

// Order 1182 on the sandbox: 129.95 with 13.00 off, 23.39 tax, 9.98 shipping.
function test_order_page_figures_placed_order_shows_its_own()
{
	$order = pg_test_order_page_row('complete', '1300', '2339', '15032');

	pg_assert_same(array('discount' => 1300, 'tax' => 2339, 'total' => 15032), pg_order_page_figures($order, array()));
	pg_assert_same(array('discount' => 1300, 'tax' => 2339, 'total' => 15032), pg_order_page_figures($order, null), 'no cart in the session');
}

// The visitor's cart has a discount of its own: it does not reach an order
// already placed, and an order placed without one shows none.
function test_order_page_figures_cart_discount_stays_on_the_cart()
{
	$session = array('order_id' => 449, 'order_discount' => 5000);

	pg_assert_same(1300, pg_order_page_figures(pg_test_order_page_row('complete', '1300', '2339', '15032'), $session)['discount']);
	pg_assert_same(
		array('discount' => 0, 'tax' => 2599, 'total' => 16592),
		pg_order_page_figures(pg_test_order_page_row('complete', '0', '2599', '16592'), $session),
		'placed without a discount'
	);
}

// Right after checkout the session still holds the order's id until the
// receipt is drawn; the stored figures win there too.
function test_order_page_figures_placed_order_still_in_the_session()
{
	$session = array('order_id' => '448', 'order_discount' => 1250);

	pg_assert_same(1300, pg_order_page_figures(pg_test_order_page_row('complete', '1300', '2339', '15032'), $session)['discount']);
}

function test_order_page_figures_every_placed_status()
{
	foreach (array('complete', 'exported', 'cancelled') as $status) {
		pg_assert_same(
			array('discount' => 500, 'tax' => 900, 'total' => 5400),
			pg_order_page_figures(pg_test_order_page_row($status, '500', '900', '5400'), array('order_id' => 1, 'order_discount' => 7000)),
			$status
		);
	}
}

// The visitor's cart is not placed: its discount is the one the cart worked
// out, its tax and total are worked out from its lines.
function test_order_page_figures_the_visitors_cart()
{
	$cart = pg_test_order_page_row('incomplete', '0', '0', '0', '449');

	pg_assert_same(array('discount' => 5000, 'tax' => null, 'total' => null), pg_order_page_figures($cart, array('order_id' => '449', 'order_discount' => 5000)));
	pg_assert_same(array('discount' => 0, 'tax' => null, 'total' => null), pg_order_page_figures($cart, array('order_id' => 449)), 'no discount');
	pg_assert_same(1500, pg_order_page_figures($cart, array('order_id' => 449, 'order_discount' => 1500.0))['discount'], 'round() gives a float');
}

// Another unplaced order (a cart saved on the account) has no discount until
// it is opened as the cart again.
function test_order_page_figures_saved_cart()
{
	$saved = pg_test_order_page_row('incomplete', '0', '0', '0', '300');

	pg_assert_same(array('discount' => 0, 'tax' => null, 'total' => null), pg_order_page_figures($saved, array('order_id' => 449, 'order_discount' => 5000)));
	pg_assert_same(0, pg_order_page_figures($saved, array('order_discount' => 5000))['discount'], 'no cart in the session');
	pg_assert_same(0, pg_order_page_figures(pg_test_order_page_row('incomplete', '0', '0', '0', '0'), array('order_id' => 0, 'order_discount' => 5000))['discount'], 'no order');
}
