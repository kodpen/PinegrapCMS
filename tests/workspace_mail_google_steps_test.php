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
	$html = ws_mail_google_steps('gmail', 'ws_mail_0_', 'password');
	$html = substr($html, strpos($html, 'google_way=password'));

	pg_assert_contains('<div class="md:col-12" data-ws-mail-when="provider=gmail">', ws_mail_google_steps('gmail'), 'shown with Gmail');
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
	pg_assert_contains('|provider|', ws_mail_settings_script(), 'the service shows and hides them');
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
	pg_assert_contains('if (ws_mail_connect_ready($ws_provider)) {', $source, 'a hidden Connect form only for an application that is set up');
	pg_assert_contains("foreach (array('microsoft', 'google') as \$ws_provider)", $source, 'Microsoft\'s and Google\'s');
}

function test_workspace_mail_google_ways()
{
	pg_assert_same('connection', ws_mail_google_way(array()), 'a new box starts on the connected account');
	pg_assert_same('password', ws_mail_google_way(array('secret' => 'sealed')), 'a box with an app password keeps showing it');
	pg_assert_same('connection', ws_mail_google_way(array('secret' => 'sealed', 'connection_id' => 4)), 'an account chosen wins');

	$html = ws_mail_google_steps('gmail', 'ws_mail_3_', 'password');

	pg_assert_contains('<select class="pg-input" name="google_way" id="ws_mail_3_google_way">', $html);
	pg_assert_contains('<option value="password" selected>', $html);
	pg_assert_contains('<div class="mt-3 d-none" data-ws-mail-when="google_way=connection">', $html, 'the connected-account way hidden');
	pg_assert_contains('<div class="mt-3" data-ws-mail-when="google_way=password">', $html, 'the app password shown');
	pg_assert_true(strpos($html, 'google_way=connection') < strpos($html, 'google_way=password'), 'the connected account is the first way');
	pg_assert_contains('google_way)', ws_mail_settings_script(), 'the choice shows and hides them');

	$new = ws_mail_google_steps('gmail');
	pg_assert_contains('<div class="mt-3" data-ws-mail-when="google_way=connection">', $new);
	pg_assert_contains('<div class="mt-3 d-none" data-ws-mail-when="google_way=password">', $new);

	// The box's drawer gives the button and the addresses.
	$parts = array('connect' => '<button id="connect-here"></button>', 'addresses' => array('https://example.com/pinegrap/google_auth.php', 'https://example.com/pinegrap/connect.php'));
	$with = ws_mail_google_steps('gmail', 'ws_mail_3_', 'connection', $parts);
	$way = substr($with, strpos($with, 'google_way=connection'), strpos($with, 'google_way=password') - strpos($with, 'google_way=connection'));

	pg_assert_contains('<button id="connect-here"></button>', $way, 'the Connect button in the connected-account way');
	pg_assert_contains('id="ws_mail_3_google_redirect_1" value="https://example.com/pinegrap/google_auth.php" readonly', $way);
	pg_assert_contains('id="ws_mail_3_google_redirect_2" value="https://example.com/pinegrap/connect.php" readonly', $way);
	pg_assert_contains('data-ws-mail-copy="ws_mail_3_google_redirect_2"', $way, 'each with its copy button');
	pg_assert_contains('href="https://console.cloud.google.com/apis/library/gmail.googleapis.com"', $way, 'the Gmail API to turn on');
	pg_assert_contains('data-ws-mail-copy', ws_mail_settings_script(), 'the script copies');
	pg_assert_true(strpos(ws_mail_google_steps('gmail'), 'data-ws-mail-copy') === false, 'no address without the drawer\'s');

	foreach (array('Testing', 'Test users', 'Internal', 'seven days') as $word) {
		pg_assert_contains($word, $way, 'Google\'s warning names ' . $word);
	}

	pg_assert_true(strpos($with, 'style=') === false, 'no inline style');
}

function test_workspace_mail_connect_google_button()
{
	if (!function_exists('pg_connect_purpose') || !pg_connect_purpose('mail_read')) {
		pg_assert_same('', ws_mail_connect_form(array('role' => 1), 'f', false, 'google'), 'nothing without the purpose');
		return;
	}

	$off = ws_mail_connect_form(array('role' => 1), 'f', false, 'google');
	pg_assert_true(strpos($off, '<button') === false, 'no button while the Google application is not set up');
	pg_assert_contains('<a href="connections.php#connections-google">' . h(lang('Set up the Google application first')) . '</a>', $off, 'a link to the application');

	$on = ws_mail_connect_form(array('role' => 1), 'f', true, 'google');
	pg_assert_contains('<button type="submit" form="pgset_mail_connect_google"', $on, 'the button submits the Google form');
	pg_assert_contains('bi-google', $on);
	pg_assert_contains(h(lang('Connect a Google account for mailboxes')), $on);

	pg_assert_contains('name="provider" value="google"', ws_mail_connect_form(array('role' => 1), null, true, 'google'), 'connect.php is asked for Google');
	pg_assert_true(strpos(ws_mail_connect_form(array('role' => 3), 'f', true, 'google'), '<button') === false, 'a user below manager is told who can');
	pg_assert_same('pgset_mail_connect', ws_mail_connect_form_id('microsoft'), 'Microsoft\'s form keeps its id');
}
