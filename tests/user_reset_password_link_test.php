<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that an administrator's password reset sends the account's owner a
 * set password link, never a password, and that the panel notice follows
 * what the e-mail send returned.
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
function test_reset_password_email_body_carries_the_link()
{
	$body = pg_reset_password_email_body('a@example.com', 'https://example.com/set-password?k=abc', "Login:\nhttps://example.com/\n");

	pg_assert_contains('Email: a@example.com', $body);
	pg_assert_contains('Set a new password: https://example.com/set-password?k=abc', $body);
	pg_assert_contains("Login:\nhttps://example.com/", $body);
	pg_assert_false(strpos($body, 'Password:') !== false, 'no password line');
}

// Without a link (no encryption key) the owner is pointed at the reset form.
function test_reset_password_email_body_without_link()
{
	$body = pg_reset_password_email_body('a@example.com', '', '');

	pg_assert_contains('Forgot password', $body);
	pg_assert_false(strpos($body, 'k=') !== false, 'no link');
}

// The notice says the e-mail failed when email() returned false, and never
// carries a password.
function test_reset_password_notice_follows_the_send()
{
	$failed = pg_reset_password_notice('https://example.com/set-password?k=abc', false);
	$linked = pg_reset_password_notice('https://example.com/set-password?k=abc', true);
	$plain = pg_reset_password_notice('', true);

	pg_assert_contains('could not be sent', $failed);
	pg_assert_contains('link to set a new password has been e-mailed', $linked);
	pg_assert_contains('Forgot password', $plain);
	pg_assert_false(strpos($failed . $linked . $plain, 'k=abc') !== false, 'no link in the notice');
}
