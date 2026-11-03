<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order widget's empty cart
 * (pg_express_order_empty_html(), includes/fn/widgets_cart.php): the
 * messages the form carries, such as the notice of a sold-out line the
 * inventory check removed, are shown above the empty-cart box and taken from
 * the session, so the visitor learns why the cart is empty and the notice
 * does not turn up on a later visit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The messages come first, then the empty-cart box.
function test_express_order_empty_html_shows_messages()
{
	$html = pg_express_order_empty_html('<div class="alert">removed</div>');
	$msg = strpos($html, 'removed');
	$box = strpos($html, 'pg-eo-empty');
	pg_assert_true($msg !== false && $box !== false && $msg < $box, 'message above the box');
}

// Without messages the box is drawn alone.
function test_express_order_empty_html_without_messages()
{
	$html = pg_express_order_empty_html('');
	pg_assert_true(strpos($html, 'pg-eo-empty') !== false, 'box drawn');
	pg_assert_same(0, strpos($html, '<div class="pg-eo-empty'), 'the box comes first');
}

// The renderer prints the form's errors and notices there and clears them.
function test_express_order_empty_branch_consumes_messages()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	$at = strpos($src, 'if ($cart_count === 0) {');
	pg_assert_true($at !== false, 'empty branch found');
	$branch = substr($src, $at, 900);
	pg_assert_true(strpos($branch, 'pg_express_order_empty_html(') !== false, 'helper used');
	pg_assert_true(strpos($branch, 'clear_notices()') !== false, 'notices cleared');
	pg_assert_true(strpos($branch, 'unmark_errors()') !== false, 'errors cleared');
}
