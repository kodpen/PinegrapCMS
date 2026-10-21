<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the activity log line written when an order's payment is
 * recorded (pg_order_mark_paid(), includes/fn/ecommerce.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The line goes through lang() with the order number as a variable; who
// recorded it is the log's user column, not a user id in the text.
function test_order_paid_log_text()
{
	pg_assert_same('Order (#1173) was marked paid.', pg_order_paid_log_text('1173', 118), 'by an operator');
	pg_assert_same('Order (#1173) was marked paid automatically.', pg_order_paid_log_text('1173', 0), 'by the software');
	pg_assert_false(strpos(pg_order_paid_log_text('1173', 118), '118') !== false, 'no user id in the text');
}
