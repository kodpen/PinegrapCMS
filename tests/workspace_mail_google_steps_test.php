<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a Gmail or Google Workspace mailbox signs in
 * (ws_mail_google_signin(), includes/workspace/mail_settings.php) and for
 * Google's and Microsoft's set-up drawers (ws_mail_setup_steps()): the box's
 * form keeps "Sign in with" and one button, the drawer the numbered steps in
 * order, with the providers' pages opening in a new tab. Also the account's
 * state with its link to Connections, and connect.php's answer shown on the
 * category.
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

function test_workspace_mail_google_signin_shown_with_gmail()
{
	$html = ws_mail_google_signin('gmail', 'ws_mail_3_');

	pg_assert_contains('<div class="md:col-12" data-ws-mail-when="provider=gmail">', $html, 'shown with Gmail');
	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-google-setup"', $html, 'one button opens the set-up drawer');
	pg_assert_contains(h(lang('Set up the Google application')), $html);
	pg_assert_same(false, strpos($html, '<ol'), 'no steps in the box\'s form');
	pg_assert_same(false, strpos($html, 'myaccount.google.com'), 'Google\'s pages are in the drawer');
	pg_assert_true(strpos($html, 'style=') === false, 'no inline style');

	foreach (array('', 'microsoft', 'other', 'yandex') as $provider) {
		pg_assert_contains('<div class="md:col-12 d-none" data-ws-mail-when="provider=gmail">', ws_mail_google_signin($provider), 'hidden for ' . ($provider === '' ? 'none' : $provider));
	}
}

function test_workspace_mail_microsoft_setup_button_in_reading()
{
	$fields = ws_mail_box_server_fields('ws_mail_3_', null, array('provider' => 'microsoft'));

	pg_assert_contains('<div class="md:col-12" data-ws-mail-when="provider=microsoft"><button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-microsoft-setup"', $fields);
	pg_assert_contains('<div class="md:col-12 d-none" data-ws-mail-when="provider=microsoft">', ws_mail_box_server_fields('ws_mail_3_', null, array('provider' => 'gmail')), 'hidden with another service');
}

function test_workspace_mail_google_steps_in_the_box_form()
{
	$fields = ws_mail_box_server_fields('ws_mail_3_', null, array('provider' => 'gmail'));

	pg_assert_contains('data-ws-mail-when="provider=gmail"', $fields);
	pg_assert_true(strpos($fields, 'name="provider"') < strpos($fields, 'provider=gmail') && strpos($fields, 'provider=gmail') < strpos($fields, 'name="user"'), 'between the service and the user name');
	pg_assert_contains('|provider|', ws_mail_settings_script(), 'the service shows and hides them');
}

function test_workspace_mail_connect_refusal_shown_on_the_category()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mailboxes.php');

	pg_assert_contains('pg_connect_flash_take()', $source, 'connect.php\'s message is read');
	pg_assert_contains('$liveform->add_error(h($ws_flash[\'text\']))', $source, 'a refusal is written on the page');
}

function test_workspace_mail_google_ways()
{
	pg_assert_same('connection', ws_mail_google_way(array()), 'a new box starts on the connected account');
	pg_assert_same('password', ws_mail_google_way(array('secret' => 'sealed')), 'a box with an app password keeps showing it');
	pg_assert_same('connection', ws_mail_google_way(array('secret' => 'sealed', 'connection_id' => 4)), 'an account chosen wins');

	$html = ws_mail_google_signin('gmail', 'ws_mail_3_', 'password');

	pg_assert_contains('<select class="pg-input" name="google_way" id="ws_mail_3_google_way">', $html);
	pg_assert_contains('<option value="password" selected>', $html);
	pg_assert_true(strpos($html, 'value="connection"') < strpos($html, 'value="password"'), 'the connected account is the first way');
	pg_assert_contains('<option value="connection" selected>', ws_mail_google_signin('gmail'), 'a new box on the connected account');
	pg_assert_contains('google_way|host|connection_id)', ws_mail_settings_script(), 'the choice shows and hides the fields');
}

function test_workspace_mail_google_setup_drawer()
{
	$parts = array('connect' => '<button id="connect-here"></button>', 'addresses' => array('https://example.com/pinegrap/google_auth.php', 'https://example.com/pinegrap/connect.php'),
		'console' => 'https://console.cloud.google.com/apis/credentials');
	$html = ws_mail_setup_steps('google', $parts);
	$steps = substr($html, 0, strpos($html, '</ol>'));

	pg_assert_same(5, substr_count($steps, '<li class="stepper-item active">'), 'five numbered steps');
	pg_assert_contains('<ol class="stepper pg-stepper-cards">', $html, 'the Design examples\' numbered steps');
	pg_assert_contains('href="https://console.cloud.google.com/apis/credentials" target="_blank"', $steps, 'Google Cloud Console in a new tab');
	pg_assert_contains('id="ws_mail_google_redirect_1" value="https://example.com/pinegrap/google_auth.php" readonly', $steps);
	pg_assert_contains('id="ws_mail_google_redirect_2" value="https://example.com/pinegrap/connect.php" readonly', $steps);
	pg_assert_contains('data-ws-mail-copy="ws_mail_google_redirect_2"', $steps, 'each with its copy button');
	pg_assert_contains('<div data-ws-mail-copies>', $steps, 'the copy\'s status beside the addresses');
	pg_assert_contains('data-ws-mail-copies', ws_mail_settings_script(), 'the script finds the status there');
	pg_assert_contains('href="https://console.cloud.google.com/apis/library/gmail.googleapis.com"', $steps, 'the Gmail API to turn on');
	pg_assert_contains('<button id="connect-here"></button>', $steps, 'the account\'s state and link');
	pg_assert_true(strpos($steps, 'redirect_1') < strpos($steps, 'gmail.googleapis.com') && strpos($steps, 'gmail.googleapis.com') < strpos($steps, 'connect-here'), 'in the order they are done');
	pg_assert_true(strpos(ws_mail_setup_steps('google', array()), 'data-ws-mail-copy') === false, 'no address, no copy button');

	$warning = substr($html, strpos($html, '</ol>'), strpos($html, '<details') - strpos($html, '</ol>'));

	foreach (array('Testing', 'Test users', 'Internal', 'seven days') as $word) {
		pg_assert_contains($word, $warning, 'Google\'s warning names ' . $word);
	}

	$password = substr($html, strpos($html, '<details class="pg-start-advanced'));
	pg_assert_contains(h(lang('Use an app password instead')), $password, 'the app password folded');
	pg_assert_contains('href="https://myaccount.google.com/security" target="_blank" rel="noopener noreferrer"', $password);
	pg_assert_true(strpos($password, 'myaccount.google.com/security') < strpos($password, 'myaccount.google.com/apppasswords'), '2-Step Verification comes before the app password');
	pg_assert_same(3, substr_count($password, '<li class="stepper-item active">'), 'three steps');
	pg_assert_true(strpos($html, 'style=') === false, 'no inline style');
}

function test_workspace_mail_microsoft_setup_drawer()
{
	$parts = array('connect' => '<button id="connect-here"></button>', 'addresses' => array('https://example.com/pinegrap/microsoft_auth.php', 'https://example.com/pinegrap/connect.php'),
		'console' => 'https://entra.microsoft.com/');
	$html = ws_mail_setup_steps('microsoft', $parts);

	pg_assert_same(4, substr_count($html, '<li class="stepper-item active">'), 'four numbered steps');
	pg_assert_contains('href="https://entra.microsoft.com/" target="_blank"', $html, 'the Entra admin center in a new tab');
	pg_assert_contains('id="ws_mail_microsoft_redirect_2" value="https://example.com/pinegrap/connect.php" readonly', $html);
	pg_assert_contains('<button id="connect-here"></button>', $html);
	pg_assert_same(false, strpos($html, '<details'), 'no app password for Microsoft');
	pg_assert_same(false, strpos($html, 'gmail'), 'nothing of Google');
	pg_assert_same('pgset-ws-mail-microsoft-setup', ws_mail_setup_drawer_id('microsoft'));
	pg_assert_same('pgset-ws-mail-google-setup', ws_mail_setup_drawer_id('google'));

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mailboxes.php');
	pg_assert_contains('ws_settings_mail_setup_drawer($ws_provider', $source, 'the category prints both set-up drawers');
}

function test_workspace_mail_google_account_state_in_the_box_form()
{
	$unset = ws_mail_connection_html(array('role' => 1), 'google', 0, false, true);
	pg_assert_contains(h(lang('The Google application is not set up yet.')), $unset, 'the application not set up is said');
	pg_assert_contains('href="' . h(ws_mail_connections_url('google')) . '" target="_blank" rel="noopener"', $unset, 'Google\'s page in a new tab');
	pg_assert_contains('bi-google', $unset);
	pg_assert_true(strpos(ws_mail_connection_html(array('role' => 3), 'google', 1, true, true), '<a ') === false, 'a user below manager is told who can');
}
