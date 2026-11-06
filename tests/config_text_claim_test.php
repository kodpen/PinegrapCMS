<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the claimed pass time of the general job's window passes
 * (pg_config_text_claim() in includes/fn/config_text.php): the write takes
 * the row only while it holds the value the pass read, and the booking
 * reminder and store credit notice passes send only after their own claim,
 * so two overlapping runs that read the same pass time never both send.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A row read as missing is claimed by an INSERT a present row ignores.
function test_config_text_claim_query_first_pass()
{
	pg_assert_true(function_exists('pg_config_text_claim_query'), 'the claim builder exists');

	list($sql, $params) = pg_config_text_claim_query('booking_reminders_at', '', '1700000600');

	pg_assert_same('INSERT IGNORE INTO config_text (name, value) VALUES (?, ?)', $sql);
	pg_assert_same(array('booking_reminders_at', '1700000600'), $params);
}

// A row read with a value is claimed only while it still holds that value.
function test_config_text_claim_query_later_pass()
{
	list($sql, $params) = pg_config_text_claim_query('credit_mails_at', '1700000000', '1700000600');

	pg_assert_same('UPDATE config_text SET value = ? WHERE (name = ?) AND (value = ?)', $sql);
	pg_assert_same(array('1700000600', 'credit_mails_at', '1700000000'), $params);
}

// A bad name or an empty value is refused before the database.
function test_config_text_claim_refuses_bad_input()
{
	pg_assert_false(pg_config_text_claim('Bad Name', '', '1'));
	pg_assert_false(pg_config_text_claim('credit_mails_at', '1', ''));
}

// Both window passes claim the time they read before anything is sent.
function test_window_passes_claim_the_time_they_read()
{
	$booking = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/booking_messages.php');
	$credit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/store_credit_mails.php');

	pg_assert_contains("pg_config_text_claim('booking_reminders_at', \$stored, (string) \$now)", $booking, 'booking reminders claim the time read');
	pg_assert_false(strpos($booking, "pg_config_text_save('booking_reminders_at'") !== false, 'booking reminders no longer write unconditionally');
	pg_assert_true(strpos($booking, 'pg_config_text_claim(') < strpos($booking, "pg_booking_message_send(\$row, 'reminder')"), 'the claim comes before the send');

	pg_assert_contains("pg_config_text_claim('credit_mails_at', \$stored, (string) \$now)", $credit, 'credit notices claim the time read');
	pg_assert_false(strpos($credit, "pg_config_text_save('credit_mails_at'") !== false, 'credit notices no longer write unconditionally');
	pg_assert_true(strpos($credit, 'pg_config_text_claim(') < strpos($credit, "pg_credit_mail_send((int) \$card['id'], 'credit_expiring')"), 'the claim comes before the send');
}
