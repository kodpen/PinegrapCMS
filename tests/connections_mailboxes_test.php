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

function test_connections_mailboxes_return_address_is_checked()
{
	pg_assert_same('settings_mailboxes.php#pgset-ws-mail', pg_connections_screen_mail_return('settings_mailboxes.php#pgset-ws-mail'), 'back to the card');
	pg_assert_same('settings_mailboxes.php', pg_connections_screen_mail_return('settings_mailboxes.php'));

	foreach (array('', 'https://example.com/x.php', '//example.com/settings_mailboxes.php', '/settings_mailboxes.php', 'settings_mailboxes.php#a b',
		"settings_mailboxes.php\r\nX: y", 'connections.php', '../settings_mailboxes.php', 'javascript:alert(1)', array('settings_mailboxes.php')) as $asked) {
		pg_assert_same('', pg_connections_screen_mail_return($asked), 'refused: ' . (is_string($asked) ? $asked : 'an array'));
	}
}

function test_connections_mailboxes_link_to_the_page()
{
	pg_assert_same('connections.php?service=google&mail_return=settings_mailboxes.php%23pgset-ws-mail#connections-mail', ws_mail_connections_url('google'));
	pg_assert_same('connections.php?service=microsoft&mail_return=settings_mailboxes.php%23pgset-ws-mail#connections-mail', ws_mail_connections_url('other'), 'Microsoft by default');
	pg_assert_same('settings_mailboxes.php#pgset-ws-mail', pg_connections_screen_mail_return(rawurldecode('settings_mailboxes.php%23pgset-ws-mail')), 'the page takes the address the card gives');
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

	// In a box's drawer the page opens in a new tab, so the half-filled form
	// stays as it is.
	pg_assert_contains('target="_blank" rel="noopener"', ws_mail_connection_html(array('role' => 1), 'google', 0, true, true));
	pg_assert_true(strpos($off, 'target="_blank"') === false, 'the card opens it in place');

	$card = connections_mailboxes_test_source('includes/workspace/mail_settings.php');
	pg_assert_true(strpos($card, 'function ws_mail_connect_form(') === false, 'no Connect form is left');
	pg_assert_true(strpos(connections_mailboxes_test_source('includes/settings/mailboxes.php'), 'action="connect.php"') === false, 'no hidden Connect form on the category');
	pg_assert_contains('pg_connect_flash_take()', connections_mailboxes_test_source('includes/settings/mailboxes.php'), 'connect.php\'s answer is still shown on the category');
}
