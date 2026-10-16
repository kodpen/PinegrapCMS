<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shopping cart and express order widgets of pages built with
 * the visual editor. They called initialize_order() on every render, which
 * creates an order when the session has none, so each signed-out open of the
 * cart or checkout page (bots included, a new session each time) left an
 * empty incomplete order behind. The legacy cart and express order pages
 * never did: the order is created by the first real write (adding a product,
 * updating the cart, submitting the order).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Both widgets bring an order in the session up to date and create none.
function test_cart_widget_view_creates_no_order()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	$guarded = "if (function_exists('initialize_order') && !empty(\$_SESSION['ecommerce']['order_id'])) initialize_order();";

	pg_assert_same(2, substr_count($source, 'initialize_order();'), 'initialize_order() calls');
	pg_assert_same(2, substr_count($source, $guarded), 'calls run only for an order in the session');

	// The saved-cart link still swaps its order into the session first, so
	// the restored cart is the one brought up to date.
	foreach (array('_render_system_widget_shopping_cart', '_render_system_widget_express_order') as $function) {
		$start = strpos($source, 'function ' . $function . '(');
		pg_assert_true($start !== false, $function . ': found');

		$restore = strpos($source, '_eo_restore_cart_from_reference_code();', $start);
		$initialize = strpos($source, $guarded, $start);

		pg_assert_true($restore !== false && $initialize !== false && $restore < $initialize, $function . ': saved cart restored before the order is brought up to date');
	}
}

// Without an order the widgets read no order row and show an empty cart.
function test_cart_widget_without_order_is_empty()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');

	pg_assert_contains("\$_pg_cart_empty = (\$_pg_cart_oid <= 0)", $source, 'shopping cart: no order is an empty cart');
	pg_assert_contains("if (\$order_id > 0 && defined('ECOMMERCE_TAX') && ECOMMERCE_TAX === true) {", $source, 'express order: tax country written only to an order');
	pg_assert_contains("if (\$cart_count === 0) {", $source, 'express order: empty cart message');
}
