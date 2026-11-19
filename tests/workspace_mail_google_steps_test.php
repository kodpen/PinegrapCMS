<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the steps that connect a Gmail or Google Workspace mailbox in a
 * box's form (ws_mail_google_steps(), includes/workspace/mail_settings.php):
 * shown only while that service is chosen, in order, with Google's pages
 * opening in a new tab, and the form's script showing and hiding them when
 * the service changes. Also the Microsoft Connect button, drawn only while
 * the application is set up, and connect.php's answer shown on the category.
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

function test_workspace_mail_connect_without_the_microsoft_application()
{
	if (!function_exists('pg_connect_purpose') || !pg_connect_purpose('mail_read')) {
		pg_assert_same('', ws_mail_connect_form(array('role' => 1), 'f', false), 'nothing without the purpose');
		return;
	}

	$off = ws_mail_connect_form(array('role' => 1), 'f', false);
	pg_assert_true(strpos($off, '<button') === false, 'no button while the application is not set up');
	pg_assert_contains('<a href="connections.php#connections-microsoft">' . h(lang('Set up the Microsoft application first')) . '</a>', $off, 'a link to the application');

	$on = ws_mail_connect_form(array('role' => 1), 'f', true);
	pg_assert_contains('<button type="submit" form="pgset_mail_connect"', $on, 'the button once it is set up');
	pg_assert_true(strpos($on, 'connections-microsoft') === false);

	pg_assert_true(strpos(ws_mail_connect_form(array('role' => 3), 'f', true), '<button') === false, 'a user below manager is told who can');
}

function test_workspace_mail_connect_refusal_shown_on_the_category()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mailboxes.php');

	pg_assert_contains('pg_connect_flash_take()', $source, 'connect.php\'s message is read');
	pg_assert_contains('$liveform->add_error(h($ws_flash[\'text\']))', $source, 'a refusal is written on the page');
	pg_assert_contains('ws_mail_connect_ready()) {', $source, 'the hidden Connect form only when the application is set up');
}
