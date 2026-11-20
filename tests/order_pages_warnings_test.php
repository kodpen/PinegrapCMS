<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the warnings the system layouts of the order preview and order
 * receipt pages wrote. The order preview read its recurring flags, applied
 * offers, charged lines and tax, shipping and recurring rows on an empty cart
 * and on an order with no shipping recipient, where nothing had set them; both
 * pages read a recipient's shipping row for items that are not shipped; and the
 * order receipt read its settings row on a page that has none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_pages_warnings_test_source($file)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Everything the totals and payment parts read starts out before the cart is
// looked at, so an empty cart and an order without shipping read defaults.
function test_order_preview_totals_start_before_the_cart()
{
	$source = order_pages_warnings_test_source('get_order_preview.php');
	$system = strpos($source, "if (\$layout_type == 'system') {");
	$cart = strpos($source, 'if ($ship_tos) {');

	pg_assert_true($system !== false && $cart !== false && $system < $cart, 'system layout and cart branch');

	$defaults = array(
		"\$recurring_products_exist = false;",
		"\$recurring_transaction = false;",
		"\$applied_offers = array();",
		"\$charged_item_ids = array();",
		"\$output_grand_tax = '';",
		"\$output_grand_shipping = '';",
		"\$output_recurring_products = '';",
	);

	foreach ($defaults as $default) {
		$at = strpos($source, $default, $system);

		pg_assert_true($at !== false && $at < $cart, $default);
	}

	// The cart branch no longer resets them, so what was set stays set.
	$loop = strpos($source, 'foreach ($ship_tos as $ship_to_id) {', $cart);
	$head = substr($source, $cart, $loop - $cart);

	pg_assert_same(0, substr_count($head, '$recurring_transaction ='), 'recurring flag set once');
	pg_assert_same(0, substr_count($head, '$applied_offers ='), 'applied offers set once');
	pg_assert_same(0, substr_count($head, '$charged_item_ids ='), 'charged lines set once');
}

// A recipient without a shipping address (ship to 0) gets an empty shipping
// row, set for each recipient before its items are drawn.
function test_order_pages_shipping_row_starts_for_each_recipient()
{
	$blocks = array(
		'get_order_preview.php' => 'if (pg_op_recipient_block_shown(',
		'get_order_receipt.php' => 'if ($output_products) {',
	);

	foreach ($blocks as $file => $block) {
		$source = order_pages_warnings_test_source($file);
		$loop = strpos($source, 'foreach ($ship_tos as $ship_to_id) {');
		$started = strpos($source, "\$output_shipping = '';", (int) $loop);
		$drawn = strpos($source, $block, (int) $loop);

		pg_assert_true($loop !== false, $file . ' recipient loop');
		pg_assert_true($started !== false && $drawn !== false && $started < $drawn, $file . ' shipping row starts empty');
	}
}

// A page made an order receipt without its settings row reads the short
// description.
function test_order_receipt_without_settings_row()
{
	$source = order_pages_warnings_test_source('get_order_receipt.php');

	pg_assert_true(strpos($source, "\$properties = get_page_type_properties(\$page_id, 'order receipt') ?: array();") !== false, 'settings row fallback');
	pg_assert_true(strpos($source, "\$product_description_type = \$properties['product_description_type'] ?? '';") !== false, 'description type default');
}
