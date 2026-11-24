<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for member accounts: a sign-up that is remembered binds its session
 * to the token it mints, the reset link's automatic sign-in and a
 * remembered device keep to the e-mail block list, a panel or Workspace
 * change of address ends a pending reset link, and the set password form
 * takes its token as one string.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one function in a source file, from its line to the next
// top-level function.
function member_accounts_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// Both sign-ups mint the device token through pg_login_set_device_cookie(),
// remembered or not, so the session is bound to it (session-token-binding).
function test_member_sign_up_binds_its_token()
{
	foreach (array('pg_member_register', 'pg_member_activate') as $function) {
		$body = member_accounts_test_body('includes/fn/auth.php', $function);

		pg_assert_true($body !== '', $function . ' found');
		pg_assert_false(strpos($body, 'pg_auth_token_create(') !== false, $function . ' mints no token of its own');
		pg_assert_contains('pg_login_set_device_cookie($new_user_id, $remember);', $body, $function . ' binds the token, remembered or not');
	}
}

// The reset link signs in neither an account with a second factor nor a
// member whose address is on the block list; staff are never refused by the
// list, as on the sign-in screens.
function test_post_password_signin_refusal()
{
	pg_assert_same('', pg_post_password_signin_refusal(false, 3, false), 'a member');
	pg_assert_same('factor', pg_post_password_signin_refusal(true, 3, false), 'a second factor');
	pg_assert_same('factor', pg_post_password_signin_refusal(true, 3, true), 'the factor is named first');
	pg_assert_same('blocked', pg_post_password_signin_refusal(false, 3, true), 'a blocked member');
	pg_assert_same('blocked', pg_post_password_signin_refusal(false, '3', true), 'the role as the row gives it');
	pg_assert_same('', pg_post_password_signin_refusal(false, 2, true), 'a blocked manager');
	pg_assert_same('', pg_post_password_signin_refusal(false, 0, true), 'a blocked administrator');
}

// A remembered device of a blocked member signs nobody in.
function test_remember_me_refuses_blocked_member()
{
	$body = member_accounts_test_body('includes/fn/auth.php', 'initialize_user');

	pg_assert_contains('if ($token_user_id && pg_user_id_login_blocked($token_user_id)) {', $body, 'the remembered token is asked');
}

// The fragment clears the link only when the stored address differs from
// the new one, and must come before the user_email assignment in the SET.
function test_user_reset_link_clear_sql()
{
	$sql = pg_user_reset_link_clear_sql("o'neil@example.com");

	pg_assert_contains("token = IF(BINARY user_email = BINARY 'o\\'neil@example.com', token, NULL),", $sql, 'token, escaped address');
	pg_assert_contains("token_timestamp = IF(BINARY user_email = BINARY 'o\\'neil@example.com', token_timestamp, 0),", $sql, 'token_timestamp');
	pg_assert_true(strpos($sql, 'token =') < strpos($sql, 'token_timestamp ='), 'token first');
}

// Edit User and the Workspace's address change carry the fragment before
// they write the address.
function test_admin_email_change_clears_pending_reset_link()
{
	$body = member_accounts_test_body('includes/fn/user_write.php', 'pg_user_update');
	$clear = strpos($body, "pg_user_reset_link_clear_sql(\$fields['email'] ?? '')");
	$write = strpos($body, "user_email = '\" . escape(\$fields['email'] ?? '')");

	pg_assert_true($clear !== false, 'Edit User clears the link');
	pg_assert_true(($clear !== false) && ($write !== false) && ($clear < $write), 'before the address is written');

	$body = member_accounts_test_body('includes/workspace/changes.php', 'ws_change_write_user');

	pg_assert_contains('UPDATE user SET " . pg_user_reset_link_clear_sql((string) $values[\'email\']) . " user_email = ', $body, 'the Workspace clears the link first');
}

// The set password form's token is one string; a list is no token.
function test_set_password_token()
{
	pg_assert_same('abc123', pg_set_password_token('abc123'), 'a token');
	pg_assert_same('', pg_set_password_token(array('abc123')), 'a list');
	pg_assert_same('', pg_set_password_token(null), 'none');
	pg_assert_same('', pg_set_password_token(false), 'false');
	pg_assert_same('12', pg_set_password_token(12), 'a number');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/set_password.php');
	pg_assert_contains("\$token = pg_set_password_token(\$form->get('k'));", $source, 'set_password.php reads it so');
}

// The users list's inline change of an address clears the link too.
function test_user_list_email_change_clears_pending_reset_link()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_list_edit.php');

	pg_assert_contains("((\$change['column'] === 'user_email') ? pg_user_reset_link_clear_sql(\$change['value']) . ' ' : '')", $source, 'the inline edit clears the link');
}
