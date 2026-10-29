<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source scan of pg_login_captcha_screen() in includes/fn/auth.php: the
 * sign-in question card labels the account box the way the sign-in card does
 * and keeps the forgot password link and the other sign-in buttons the card
 * it stands in for prints.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of pg_login_captcha_screen(), up to the next function.
function login_captcha_screen_test_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	$start = strpos($source, 'function pg_login_captcha_screen(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_login_captcha_screen_labels_identifier_as_email_or_username()
{
	$body = login_captcha_screen_test_body();

	pg_assert_contains("lang('Email or username')", $body);
	pg_assert_false(strpos($body, "lang('Email')") !== false, 'no bare Email label');
}

function test_login_captcha_screen_prints_forgot_password_link_after_form()
{
	$body = login_captcha_screen_test_body();
	$form_end = strpos($body, '</form>');
	$links = strpos($body, "' . \$links . '");

	pg_assert_contains('FORGOT_PASSWORD_LINK == true', $body);
	pg_assert_contains("lang('Forgot password?')", $body);
	pg_assert_contains("'/forgot_password.php?send_to='", $body);
	pg_assert_contains('$links = \'\';', $body);
	pg_assert_true($form_end !== false && $links !== false && $links > $form_end, 'links printed after the form');
	pg_assert_contains("h(urlencode(\$send_to))", $body);
}

function test_login_captcha_screen_prints_signin_buttons()
{
	$body = login_captcha_screen_test_body();

	pg_assert_contains('pg_signin_buttons($send_to, false)', $body);
	pg_assert_contains('<div class="d-flex flex-column gap-3 mt-5">', $body);
}

function test_login_captcha_screen_keeps_credential_field_names()
{
	$body = login_captcha_screen_test_body();

	pg_assert_contains('name="\' . h($screen[\'identifier\']) . \'"', $body);
	pg_assert_contains('name="\' . h($screen[\'password\']) . \'"', $body);
}
