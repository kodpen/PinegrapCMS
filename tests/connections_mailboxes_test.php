<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the accounts that read mailboxes on Connections: Google's and
 * Microsoft's pages carry "Connect an account for mailboxes" under the
 * Mailboxes category's gate, the address a connection started from Site
 * Settings › Mailboxes returns to, and the Mailboxes card and box form that
 * show the account's state and lead to the page without a Connect button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function connections_mailboxes_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_mailboxes_action_keeps_the_mailboxes_gate()
{
	foreach (array('google', 'microsoft') as $id) {
		pg_assert_same(array('status', 'mailboxes', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'mailboxes' => true)), $id . ': who may open Mailboxes connects an account for them');
		pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'mailboxes' => false)), $id . ': not without the Mailboxes category');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => false, 'mailboxes' => true)), $id . ': connect.php connects the site\'s accounts for a manager only');
	}

	pg_assert_same(array('status', 'mailboxes', 'signin', 'details', 'uses'), pg_connections_screen_page_cards('google', array('manager' => true, 'mailboxes' => true, 'signin' => true)), 'inside Status, before the sign-in application');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('github', array('manager' => true, 'mailboxes' => true)), 'only Google\'s and Microsoft\'s pages have it');

	$page = connections_mailboxes_test_source('connections.php');
	pg_assert_contains("'mailboxes' => connections_mailboxes_may_manage(\$user)", $page, 'the page asks the category\'s gate');
	pg_assert_contains("return pg_settings_may_open('mailboxes', \$user);", $page);
	pg_assert_contains('data-purpose="mail_read"', $page, 'the action connects for Reading mailboxes');
}

function test_connections_mailboxes_link_to_the_page()
{
	// The return is the one of every lead (tests/connections_back_test.php):
	// the E-mail category's Mailboxes card, by its keys.
	pg_assert_same('connections.php?service=google&from=email%2Fpgset-ws-mail#connections-mail', ws_mail_connections_url('google'));
	pg_assert_same('connections.php?service=microsoft&from=email%2Fpgset-ws-mail#connections-mail', ws_mail_connections_url('other'), 'Microsoft by default');
	pg_assert_same('email/pgset-ws-mail', pg_connections_back_legacy(array('mail_return' => 'settings_mailboxes.php#pgset-ws-mail'), 'google'), 'an older link still returns to the card');
}

function test_connections_mailboxes_card_shows_the_state_without_a_button()
{
	$off = ws_mail_connection_html(array('role' => 1), 'google', 0, true);
	pg_assert_contains(h(lang('Not connected')), $off);
	pg_assert_contains('>' . h(lang('Connect in Connections')) . '</a>', $off);
	pg_assert_contains('href="' . h(ws_mail_connections_url('google')) . '"', $off);
	pg_assert_true(strpos($off, '<button') === false && strpos($off, 'connect.php') === false, 'no Connect button');

	$on = ws_mail_connection_html(array('role' => 1), 'microsoft', 2, true);
	pg_assert_contains(h(lang('Connected')), $on);
	pg_assert_contains('>' . h(lang('Manage in Connections')) . '</a>', $on);

	$unset = ws_mail_connection_html(array('role' => 1), 'microsoft', 0, false);
	pg_assert_contains(h(lang('The Microsoft application is not set up yet.')), $unset, 'the application not set up is said');

	$member = ws_mail_connection_html(array('role' => 3), 'google', 0, true);
	pg_assert_true(strpos($member, '<a ') === false, 'a user below manager is told who can, with no link to Connections');

	// In a box's drawer too the page opens in the same tab: the drawer and
	// the half-filled form come back with the way back (backend.src.js).
	pg_assert_true(strpos(ws_mail_connection_html(array('role' => 1), 'google', 0, true, true), 'target="_blank"') === false, 'the drawer opens it in place');
	pg_assert_true(strpos($off, 'target="_blank"') === false, 'the card opens it in place');

	$card = connections_mailboxes_test_source('includes/workspace/mail_settings.php');
	pg_assert_true(strpos($card, 'function ws_mail_connect_form(') === false, 'no Connect form is left');
	pg_assert_true(strpos(connections_mailboxes_test_source('includes/settings/mailboxes.php'), 'action="connect.php"') === false, 'no hidden Connect form on the category');
	pg_assert_contains('pg_connect_flash_take()', connections_mailboxes_test_source('includes/settings/mailboxes.php'), 'connect.php\'s answer is still shown on the category');
}

function test_connections_mailboxes_state_reads_the_purposes_each_account_holds()
{
	$send = array('purposes' => array('mail'), 'status' => 'ok');
	$read = array('purposes' => array('mail', 'mail_read'), 'status' => 'ok');
	$analytics = array('purposes' => array('google_analytics'), 'status' => 'ok');

	pg_assert_same(array('count' => 0, 'other' => array()), ws_mail_connection_state(array()), 'no account');
	pg_assert_same(array('count' => 1, 'other' => array()), ws_mail_connection_state(array($send, $read)), 'one account reads mailboxes');
	pg_assert_same(array('count' => 0, 'other' => array('mail', 'google_analytics')), ws_mail_connection_state(array($send, $analytics, $send)),
		'connected on Connections for sending only: the purposes it holds, once each');
	pg_assert_same(array('count' => 0, 'other' => array()), ws_mail_connection_state(array(array('purposes' => array()))), 'an account with no purpose');
}

function test_connections_mailboxes_state_line_names_the_missing_sign_in()
{
	$state = array('count' => 0, 'other' => array('mail'));

	foreach (array('google', 'microsoft') as $provider) {
		$html = ws_mail_connection_html(array('role' => 1), $provider, $state, true);
		pg_assert_true(strpos($html, '>' . h(lang('Not connected')) . '<') === false, $provider . ': not a bare Not connected');
		pg_assert_contains(h(lang('Connected for another purpose')), $html, $provider);
		pg_assert_contains(h(lang('Sending e-mail')), $html, $provider . ': the purpose it holds is named');
		pg_assert_contains(h(lang('Reading mailboxes')), $html, $provider . ': the missing sign-in is named');
		pg_assert_contains('>' . h(lang('Sign in for mailboxes in Connections')) . '</a>', $html, $provider . ': leads to it');
		pg_assert_contains('href="' . h(ws_mail_connections_url($provider)) . '"', $html, $provider);

		$member = ws_mail_connection_html(array('role' => 3), $provider, $state, true);
		pg_assert_contains(h(lang('Connected for another purpose')), $member, $provider . ': a member reads the same state');
		pg_assert_true(strpos($member, '<a ') === false, $provider . ': with no link');
	}
}

function test_connections_mailboxes_connected_account_leads_to_adding_the_mailbox()
{
	$google = ws_mail_connection_html(array('role' => 1), 'google', array('count' => 1, 'other' => array()), true);
	pg_assert_contains(h(lang('Connected')), $google);
	pg_assert_contains(h(lang('Next, add the mailbox with Add a mailbox and choose Mail service: Gmail or Google Workspace.')), $google, 'the next step on the card');

	$microsoft = ws_mail_connection_html(array('role' => 1), 'microsoft', 1, true);
	pg_assert_contains(h(lang('Next, add the mailbox with Add a mailbox and choose Mail service: Microsoft 365 or Outlook.com.')), $microsoft, 'a count still reads as before');

	pg_assert_true(strpos(ws_mail_connection_html(array('role' => 1), 'google', 1, true, true), 'Next, add') === false, 'not inside the box form being filled');
	pg_assert_true(strpos(ws_mail_connection_html(array('role' => 1), 'google', 0, true), 'Next, add') === false, 'not while nothing is connected');
}
