<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the orders of a contact (view_orders_for_contact.php): open carts
 * are left out and each row shows its status.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_view_orders_for_contact_leaves_out_open_carts()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders_for_contact.php');

	pg_assert_contains('$where = "WHERE contact_id = ? AND status != \'incomplete\' ";', $screen, 'an open cart is not an order of the contact');
	pg_assert_same(3, substr_count($screen, '$where'), 'the count and the page share the one condition');
}

function test_view_orders_for_contact_shows_the_status()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders_for_contact.php');

	pg_assert_contains("'status'       => lang('Status'),", $screen, 'the status column sorts');
	pg_assert_contains("'status'       => 'status',", $screen, 'the status sort code orders by the column');
	pg_assert_contains("get_column_heading(lang('Status'), \$sort_levels, '', \$sort_extras, 'status')", $screen, 'the status heading');
	pg_assert_contains('pg_order_list_status_html($row, $paid_at_column_exists)', $screen, 'the badge the orders list draws');
	pg_assert_contains('$paid_at_column_exists = pg_schema_has(\'orders\', \'paid_at\');', $screen, 'the paid_at column is probed before it is read');
	pg_assert_contains('"SELECT id, order_number, total, order_date, status, payment_method, parasut_exported, parasut_invoice_id" . ($paid_at_column_exists ? ", paid_at" : "") . " "', $screen, 'the row carries what the badge reads');
}
