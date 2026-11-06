<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the affiliate code an order is submitted with
 * (pg_order_submit_affiliate_code()): the visitor's code from the session or
 * cookie wins, and the code the cart stored on the order (an affiliate code
 * typed as an offer code) is kept when the visitor carries none, as the
 * off-site payment completion already reads it from the order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once dirname(__DIR__) . '/pinegrap/includes/fn/ecommerce.php';

function test_order_affiliate_code_visitor_code_wins()
{
	pg_assert_same('LINK', pg_order_submit_affiliate_code('LINK', 'CART'));
	pg_assert_same('LINK', pg_order_submit_affiliate_code('LINK', ''));
}

function test_order_affiliate_code_cart_code_kept_without_visitor_code()
{
	// Visitor tracking off: the cart wrote the code on the order only.
	pg_assert_same('CART', pg_order_submit_affiliate_code('', 'CART'));
	pg_assert_same('CART', pg_order_submit_affiliate_code(null, 'CART'));
}

function test_order_affiliate_code_none()
{
	pg_assert_same('', pg_order_submit_affiliate_code('', ''));
	pg_assert_same('', pg_order_submit_affiliate_code('', null));
}

function test_order_affiliate_code_submit_reads_order_code()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/submit_order.php');
	pg_assert_contains("\$affiliate_code = pg_order_submit_affiliate_code(get_affiliate_code(), \$order_affiliate_code);", $source);
	pg_assert_contains("\$order_affiliate_code = (string) \$row['affiliate_code'];", $source);
}
