<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the account e-mail change made by the affiliate sign-up form
 * (affiliate_sign_up_form.php): the new address must not be another
 * account's username or e-mail address, and a new address ends a pending
 * password reset or set password link mailed to the old one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _affiliate_sign_up_email_change_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/affiliate_sign_up_form.php');
}

// The address is compared with usernames too, as registration and the
// e-mail preferences screen do, and the member's own account is left out by id.
function test_affiliate_sign_up_email_checked_against_usernames()
{
	$source = _affiliate_sign_up_email_change_source();

	pg_assert_contains('"SELECT user_id FROM user WHERE ((user_username = ?) OR (user_email = ?)) AND (user_id != ?)"', $source, 'username and e-mail of other accounts');
	pg_assert_same(false, strpos($source, '(user_email = ?) AND (user_username != ?)'), 'the e-mail-only check is gone');
}

// A changed address clears user.token; an unchanged one leaves a link the
// member just asked for alone.
function test_affiliate_sign_up_email_change_clears_pending_reset_link()
{
	$source = _affiliate_sign_up_email_change_source();

	pg_assert_contains('user.user_email,', $source, 'the stored address is read');
	pg_assert_contains('$sql_clear_reset_link = ((string) $liveform->get_field_value(\'email_address\') !== (string) $current_email_address) ? \', token = NULL, token_timestamp = 0\' : \'\';', $source, 'cleared only when the address changes');
	pg_assert_contains('user_timestamp = UNIX_TIMESTAMP()" . $sql_clear_reset_link', $source, 'the UPDATE carries it');
}
