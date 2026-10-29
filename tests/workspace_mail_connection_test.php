<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Workspace mailbox read and answered through a connected
 * Microsoft account (purpose mail_read): the purpose, the answer's route
 * and sent copy, what a box signs in with, the card's connect button and
 * email()'s own connection. Signing in to Microsoft and sending through
 * Graph need the provider and are not run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_workspace_mail_read_purpose()
{
	$purpose = include(PG_FUNCTIONS_DIR . '/includes/connect/purposes/mail_read.php');

	pg_assert_same('site', $purpose['owner']);
	pg_assert_same(array('microsoft'), array_keys($purpose['scopes']), 'Microsoft only (owner answer 2)');
	pg_assert_true(in_array('https://outlook.office.com/IMAP.AccessAsUser.All', $purpose['scopes']['microsoft'], true), 'reads over IMAP');
	pg_assert_true(in_array('https://graph.microsoft.com/Mail.Send', $purpose['scopes']['microsoft'], true), 'answers as the site mail does');
	pg_assert_same(array('https://outlook.office.com/IMAP.AccessAsUser.All'), $purpose['imap_scopes']['microsoft'], 'one resource per token');

	$mail = include(PG_FUNCTIONS_DIR . '/includes/connect/purposes/mail.php');
	pg_assert_same($mail['send']['microsoft']['scopes'], array_values(array_intersect($purpose['scopes']['microsoft'], $mail['send']['microsoft']['scopes'])), 'the sending call\'s scopes are consented');
}

function test_workspace_mail_reply_route_through_a_connection()
{
	$box = array('address' => 'support@contoso.com', 'reply_via' => 'connection', 'settings' => '{"connection_id":7,"host":"outlook.office365.com","user":"support@contoso.com"}');

	pg_assert_same(array('ok' => true, 'error' => '', 'smtp' => null, 'connection' => 7), ws_mail_reply_route($box, 'example.com'));
	pg_assert_same('connection', ws_mail_reply_route(array_merge($box, array('settings' => '{}')), 'example.com')['error'], 'no account chosen');
	pg_assert_same('connection', ws_mail_reply_route(array_merge($box, array('reply_via' => 'odd')), 'example.com')['error']);

	// Graph files what it sends in the account's sent folder itself.
	pg_assert_same('service', ws_mail_reply_copy_plan($box, ws_mail_reply_route($box, 'example.com'))['reason']);

	// Read through the account, sent through a server: the copy signs in with the account.
	$smtp = array('ok' => true, 'error' => '', 'smtp' => array('host' => 'smtp.example.com'));
	pg_assert_true(ws_mail_reply_copy_plan(array_merge($box, array('reply_via' => 'box', 'transport' => 'imap')), $smtp)['copy']);

	pg_assert_true(ws_mail_reply_error_text('connection') !== ws_mail_reply_error_text('not_sent'));
}

function test_workspace_mail_box_credentials()
{
	// Without the connected accounts' tables an account is never found.
	$account = ws_mail_box_credentials(array('settings' => '{"connection_id":7}'));
	pg_assert_same(array(false, 'no_account', ''), array($account['ok'], $account['error'], $account['token']));

	$password = ws_mail_box_credentials(array('settings' => '{}'));
	pg_assert_same('no_password', $password['error'], 'no kept password');

	pg_assert_true(ws_mail_error_text('no_account', '', '') !== ws_mail_error_text('odd', '', ''), 'its own words');
	pg_assert_same(array(), ws_mail_box_accounts(), 'none without the tables');

	$settings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	$fetch = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');
	$reply = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_reply.php');

	foreach (array('settings' => $settings, 'fetch' => $fetch, 'reply' => $reply) as $name => $source) {
		pg_assert_contains('ws_mail_box_login($session, $box, $credentials)', $source, $name . ' signs in through the box\'s credentials');
		pg_assert_true(strpos($source, 'pg_imap_login($session, (string) $settings') === false, $name . ' never signs in with the password alone');
	}
}

function test_workspace_mail_box_form_connection()
{
	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@contoso.com', 'channel_id' => 0, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'enabled' => 1, 'reply_via' => 'connection',
		'settings' => '{"provider":"microsoft","host":"outlook.office365.com","connection_id":7}', 'auto_reply' => 0, 'auto_reply_text' => '');
	$html = ws_mail_box_form('workspace_settings.php', $box, array());

	pg_assert_contains('name="connection_id" id="ws_mail_3_connection"', $html);
	pg_assert_contains('<option value="7" selected>' . h(lang('An account no longer connected')) . '</option>', $html, 'a gone account stays named');
	pg_assert_contains('<option value="connection" selected>', $html);
	pg_assert_contains('<option value="0">' . h(lang('None: sign in with the password')) . '</option>', ws_mail_box_form('workspace_settings.php', null, array()));
}

function test_workspace_mail_connect_button()
{
	pg_assert_contains('name="purpose" value="mail_read"', ws_mail_connect_form(array('role' => 2)), 'a manager connects');
	pg_assert_contains('value="workspace_settings.php#ws-mail"', ws_mail_connect_form(array('role' => 0)), 'back to the card');
	pg_assert_true(strpos(ws_mail_connect_form(array('role' => 3)), 'connect.php') === false, 'a member is told who can');
}

function test_workspace_mail_email_takes_its_connection()
{
	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	$reply = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_reply.php');
	$status = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_status.php');

	pg_assert_contains("if (!\$smtp && !empty(\$properties['connection'])) {", $mail);
	pg_assert_contains("in_array('mail_read', (array) \$connection['purposes'], true)", $mail, 'only an account for mailboxes');
	pg_assert_contains("\$properties['connection'] = (int) \$check['route']['connection'];", $reply, 'an answer');
	pg_assert_contains("\$properties['connection'] = (int) \$route['connection'];", $status, 'the automatic answer');
	pg_assert_same(7, json_decode(pg_mail_properties_encode(array('connection' => 7, 'queue' => true)), true)['connection'], 'kept through the queue');
}
