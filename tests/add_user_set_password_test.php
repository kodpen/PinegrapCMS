<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the e-mail an administrator's new account sends its owner
 * carries a set password link, never the password the account was created
 * with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The address, the link and the sign-in lines; no password line.
function test_add_user_email_body_carries_the_link()
{
	$body = pg_new_user_email_body('a@example.com', 'https://example.com/set-password?k=abc', "Login:\nhttps://example.com/\n");

	pg_assert_contains('Email: a@example.com', $body);
	pg_assert_contains('Set your password: https://example.com/set-password?k=abc', $body);
	pg_assert_contains("Login:\nhttps://example.com/", $body);
	pg_assert_false(strpos($body, 'Password:') !== false, 'no password line');
}

// Without a link (no encryption key) the owner is pointed at the reset form.
function test_add_user_email_body_without_link()
{
	$body = pg_new_user_email_body('a@example.com', '', '');

	pg_assert_contains('Forgot password', $body);
	pg_assert_false(strpos($body, 'k=') !== false, 'no link');
}

// add_user.php neither mails the random password nor repeats it in the
// notice of a notified user, and sends the link as a secret e-mail.
function test_add_user_does_not_mail_the_password()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_user.php');

	pg_assert_same(0, preg_match('/lang\(\'Password\'\)\s*\.\s*":\s*\$random_password/', $source), 'password line in the e-mail');
	pg_assert_false(strpos($source, 'has been emailed to the user.\',\'vars\'=>h($random_password)') !== false, 'password in the notice');
	pg_assert_contains('pg_new_user_email_body(', $source);
	pg_assert_contains("'secret' => true", $source);
}

// Bulk import with "Notify User" sends the same "New Account" e-mail and
// never writes the random password into it; the link goes out as a secret
// e-mail.
function test_import_users_does_not_mail_the_password()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_users.php');

	pg_assert_same(0, preg_match('/lang\(\'Password\'\)\s*\.\s*":\s*\$random_password/', $source), 'password line in the e-mail');
	pg_assert_contains('pg_new_user_email_body(', $source);
	pg_assert_contains("'secret' => true", $source);
}
