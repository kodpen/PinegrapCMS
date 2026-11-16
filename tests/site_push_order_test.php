<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order's moments sent as visitor notifications
 * (includes/fn/site_push_order.php): the text of a shipped and a delivered
 * notice, their limits, and an unknown moment refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_order.php');

function test_site_push_order_text_shipped_and_delivered()
{
	$shipped = pg_site_push_order_text('shipped', '1042', 'en');
	pg_assert_same('Your order has shipped', $shipped['title']);
	pg_assert_same('Order 1042 is on its way.', $shipped['body']);

	$delivered = pg_site_push_order_text('delivered', '1042', 'en');
	pg_assert_same('Your order has been delivered', $delivered['title']);
	pg_assert_same('Order 1042 has been delivered.', $delivered['body']);
}

function test_site_push_order_text_unknown_moment_is_null()
{
	pg_assert_same(null, pg_site_push_order_text('cancelled', '1042', 'en'));
	pg_assert_same(null, pg_site_push_order_text('', '1042', 'en'));
}

function test_site_push_order_text_fits_the_message_columns()
{
	$text = pg_site_push_order_text('shipped', str_repeat('9', 300), 'en');
	pg_assert_true(mb_strlen($text['title'], 'UTF-8') <= 60);
	pg_assert_true(mb_strlen($text['body'], 'UTF-8') <= 200);
}
