<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that an account a form's auto-registration opens is handed over with
 * a set password link and never with its password: the link's token, its
 * address and lifetime, the text blocks of the sender's and the
 * administrator's copies, and the value old layouts print under "Password".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The token is an HMAC of the account and the issue time: the same inputs
// draw the same link again, any other input a different one.
function test_auto_registration_link_token_is_signed_and_stable()
{
	$token = pg_set_password_link_token(42, 1760000000, 'key-a');

	pg_assert_same(1, preg_match('/^[0-9a-f]{64}$/', $token), 'shape');
	pg_assert_same($token, pg_set_password_link_token('42', '1760000000', 'key-a'), 'stable');
	pg_assert_true($token !== pg_set_password_link_token(43, 1760000000, 'key-a'), 'other account');
	pg_assert_true($token !== pg_set_password_link_token(42, 1760000001, 'key-a'), 'other issue time');
	pg_assert_true($token !== pg_set_password_link_token(42, 1760000000, 'key-b'), 'other key');
}

// Nothing to sign with, or nothing to sign: no token, so no link.
function test_auto_registration_link_token_needs_key_account_and_time()
{
	pg_assert_same('', pg_set_password_link_token(42, 1760000000, ''));
	pg_assert_same('', pg_set_password_link_token(0, 1760000000, 'key'));
	pg_assert_same('', pg_set_password_link_token(42, 0, 'key'));
}

// The same 24 hours set_password.php accepts.
function test_auto_registration_link_lifetime_matches_set_password()
{
	pg_assert_true(pg_set_password_link_live(1000, 1000), 'just issued');
	pg_assert_true(pg_set_password_link_live(1000, 1000 + 86400), 'last second');
	pg_assert_false(pg_set_password_link_live(1000, 1000 + 86401), 'expired');
	pg_assert_false(pg_set_password_link_live(0, 10), 'never issued');
}

// The token goes in "k", after "?" or "&".
function test_auto_registration_link_url()
{
	pg_assert_same('https://example.com/set-password?k=abc', pg_set_password_link_url('https://example.com', '/set-password', 'abc'));
	pg_assert_same('https://example.com/p?x=1&k=abc', pg_set_password_link_url('https://example.com', '/p?x=1', 'abc'));
	pg_assert_same('', pg_set_password_link_url('https://example.com', '/set-password', ''));
	pg_assert_same('', pg_set_password_link_url('https://example.com', '', 'abc'));
}

// The sender's plain-text copy: the address and the link, no password.
function test_auto_registration_plain_text_for_sender()
{
	$text = pg_cf_new_account_plain_text('a@example.com', 'https://example.com/set-password?k=abc', true);

	pg_assert_contains('Email: a@example.com', $text);
	pg_assert_contains('Set your password: https://example.com/set-password?k=abc', $text);
	pg_assert_false(strpos($text, 'Password:') !== false, 'no password line');

	$without_link = pg_cf_new_account_plain_text('a@example.com', '', true);

	pg_assert_contains('Forgot password', $without_link);
	pg_assert_false(strpos($without_link, 'k=') !== false, 'no link');
}

// The administrator's and a watcher's copy: that an account was opened,
// never the link, even when one is passed.
function test_auto_registration_plain_text_for_others()
{
	$text = pg_cf_new_account_plain_text('a@example.com', 'https://example.com/set-password?k=abc', false);

	pg_assert_contains('A new account was created with this submission: a@example.com', $text);
	pg_assert_false(strpos($text, 'k=abc') !== false, 'no link');
	pg_assert_same('', pg_cf_new_account_plain_text('', 'https://example.com/set-password?k=abc', true), 'no account');
}

// The system layouts' block escapes the address and the link.
function test_auto_registration_html_block()
{
	$html = pg_cf_new_account_html('a<b@example.com', 'https://example.com/s?x=1&k=abc', true);

	pg_assert_contains('a&lt;b@example.com', $html);
	pg_assert_contains('<a href="https://example.com/s?x=1&amp;k=abc">Set your password</a>', $html);

	$staff = pg_cf_new_account_html('a@example.com', 'https://example.com/s?k=abc', false);

	pg_assert_contains('A new account was created with this submission: a@example.com', $staff);
	pg_assert_false(strpos($staff, 'k=abc') !== false, 'no link for staff');
	pg_assert_same('', pg_cf_new_account_html('', 'https://example.com/s?k=abc', true));
}

// Old layouts print $auto_registration_password under a "Password" label:
// it carries the link for the sender and nothing for anyone else.
function test_auto_registration_password_note()
{
	pg_assert_same('Set it with this link: https://example.com/s?k=abc', pg_cf_new_account_password_note('https://example.com/s?k=abc', true));
	pg_assert_contains('Forgot password', pg_cf_new_account_password_note('', true));
	pg_assert_same('', pg_cf_new_account_password_note('https://example.com/s?k=abc', false));
}

// Outside a submission the visitor's own page is the sender's; a watcher's
// copy is marked while it is drawn.
function test_auto_registration_recipient_flag()
{
	pg_assert_true(pg_cf_new_account_recipient());
	pg_cf_new_account_recipient(false);
	pg_assert_false(pg_cf_new_account_for_submitter(), 'watcher');
	pg_cf_new_account_recipient(true);
	pg_assert_true(pg_cf_new_account_for_submitter(), 'sender');

	pg_tr_suspend(true);
	pg_assert_false(pg_cf_new_account_for_submitter(), 'administrator copy');
	pg_tr_suspend(false);
}

// No screen reads a password for the account any more: the session does not
// hold one, and nothing that draws the account looks for it.
function test_auto_registration_no_password_in_session_or_output()
{
	$files = array(
		'custom_form.php',
		'get_form_item_view.php',
		'get_custom_form_confirmation_screen_content.php',
		'includes/fn/widgets.php',
		'includes/fn/custom_form.php',
		'includes/templates/form_item_view.php',
	);

	foreach ($files as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match('/custom_form_auto_registration\'\]\[[^\]]+\]\[\'password\'\]/', $source), $file . ': session password');
		pg_assert_same(0, preg_match('/\$new_account\[\'password\'\]/', $source), $file . ': widget password');
	}

	pg_assert_same(0, preg_match('/\$random_password/', file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php')), 'custom_form.php keeps no password');
}
