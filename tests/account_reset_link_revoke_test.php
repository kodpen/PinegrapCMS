<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a pending password reset or set password link (user.token) dies
 * when the account's owner changes the password (change_password.php) or the
 * e-mail address (email_preferences.php): a link mailed to the old mailbox
 * must not set a new password for up to 24 hours after that.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The password update clears the token in the same statement.
function test_change_password_clears_pending_reset_link()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/change_password.php');

	pg_assert_same(1, preg_match('/user_password_algo = 2,\s*token = NULL,\s*token_timestamp = 0\b/', $source), 'the UPDATE clears token and token_timestamp');
}

// A new address clears the token; an unchanged address leaves a link the
// owner just asked for alone.
function test_email_change_clears_pending_reset_link()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/email_preferences.php');

	pg_assert_contains('$sql_clear_reset_link = ($liveform->get_field_value(\'email_address\') != $current_email_address) ? " token = NULL, token_timestamp = 0," : \'\';', $source, 'cleared only when the address changes');
	pg_assert_contains('"UPDATE user SET user_email = ?,$sql_update_username$sql_clear_reset_link user_contact = ? WHERE user_id = ?"', $source, 'the UPDATE carries it');
}
