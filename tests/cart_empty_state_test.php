<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the empty shopping cart drawn by the visual editor's cart widget.
 * A cart tree without the has_items flag showed the empty-cart message as a
 * green success alert and gave the visitor no way back to the shop; it now
 * shows a neutral message and a Continue Shopping link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');

// A message and a shop address give a neutral block with a link, no alert.
function test_cart_empty_state_message_and_link()
{
	$html = pg_cart_empty_state_html('Your cart is empty.', '/shop?a=1&b=2');

	pg_assert_true(strpos($html, 'alert') === false, 'no alert');
	pg_assert_contains('class="pg-cart-empty', $html, 'empty cart block');
	pg_assert_contains('<p class="text-body-secondary mb-3">Your cart is empty.</p>', $html, 'message paragraph');
	pg_assert_contains('<a class="btn btn-primary" href="/shop?a=1&amp;b=2">', $html, 'link with an escaped address');
	pg_assert_contains('Continue Shopping</a>', $html, 'link label');
}

// The message is escaped.
function test_cart_empty_state_escapes_message()
{
	$html = pg_cart_empty_state_html('<b>Empty</b>', '/shop');

	pg_assert_contains('&lt;b&gt;Empty&lt;/b&gt;', $html, 'escaped message');
	pg_assert_true(strpos($html, '<b>') === false, 'no raw markup');
}

// A missing part is left out; nothing at all gives an empty string.
function test_cart_empty_state_missing_parts()
{
	$no_link = pg_cart_empty_state_html('Your cart is empty.', '');
	pg_assert_true(strpos($no_link, '<a ') === false, 'no link without an address');
	pg_assert_contains('<p ', $no_link, 'message kept');

	$no_message = pg_cart_empty_state_html('', '/shop');
	pg_assert_true(strpos($no_message, '<p ') === false, 'no paragraph without a message');
	pg_assert_contains('<a ', $no_message, 'link kept');

	pg_assert_same('', pg_cart_empty_state_html('', ''), 'both empty');
}

// The tree without has_items no longer sends the message as a liveform notice.
function test_cart_empty_state_no_success_notice()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');

	pg_assert_true(strpos($source, "_pg_sw_render_message_only(\$tree_decoded, 'shopping_cart', h(\$_pg_cart_empty_msg)") === false, 'message not passed as a notice');
	pg_assert_contains("pg_cart_empty_state_html(\$_pg_cart_empty_msg", $source, 'empty state drawn');
}
