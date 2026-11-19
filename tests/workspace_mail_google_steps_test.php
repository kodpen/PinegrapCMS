<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the steps that connect a Gmail or Google Workspace mailbox in a
 * box's form (ws_mail_google_steps(), includes/workspace/mail_settings.php):
 * shown only while that service is chosen, in order, with Google's pages
 * opening in a new tab, and the form's script showing and hiding them when
 * the service changes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_workspace_mail_google_steps_shown_with_gmail()
{
	$html = ws_mail_google_steps('gmail');

	pg_assert_contains('<div class="md:col-12" data-ws-mail-when="provider=gmail">', $html, 'shown with Gmail');
	pg_assert_contains('href="https://myaccount.google.com/security" target="_blank" rel="noopener noreferrer"', $html);
	pg_assert_contains('href="https://myaccount.google.com/apppasswords"', $html);
	pg_assert_true(strpos($html, 'myaccount.google.com/security') < strpos($html, 'myaccount.google.com/apppasswords'), '2-Step Verification comes before the app password');
	pg_assert_same(6, substr_count(substr($html, 0, strpos($html, '</ol>')), '<li>'), 'six steps');
	pg_assert_true(strpos($html, 'style=') === false, 'no inline style');
}

function test_workspace_mail_google_steps_hidden_with_another_service()
{
	foreach (array('', 'microsoft', 'other', 'yandex') as $provider) {
		pg_assert_contains('<div class="md:col-12 d-none" data-ws-mail-when="provider=gmail">', ws_mail_google_steps($provider), 'hidden for ' . ($provider === '' ? 'none' : $provider));
	}
}

function test_workspace_mail_google_steps_in_the_box_form()
{
	$fields = ws_mail_box_server_fields('ws_mail_3_', null, array('provider' => 'gmail'));

	pg_assert_contains('data-ws-mail-when="provider=gmail"', $fields);
	pg_assert_true(strpos($fields, 'name="provider"') < strpos($fields, 'provider=gmail') && strpos($fields, 'provider=gmail') < strpos($fields, 'name="user"'), 'between the service and the user name');
	pg_assert_contains('|provider)', ws_mail_settings_script(), 'the service shows and hides them');
}
