<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One connected account for sending and reading mail
 * (includes/fn/mail_accounts.php): an account connected for a mailbox is
 * offered for sending as it is, a sending account chosen for a mailbox asks
 * the wider permission on its own row, Disconnect names what stops, removing
 * a mailbox keeps the account sending uses, and the Connect buttons of both
 * tabs of Site Settings › E-mail come from one helper. The provider's
 * consent screen is not run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_accounts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_sending_rows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function mail_accounts_test_account($id, $purposes, $provider = 'google', $label = 'info@example.com')
{
	return array('id' => $id, 'provider' => $provider, 'user_id' => 0, 'label' => $label, 'purposes' => $purposes, 'status' => 'active');
}

function mail_accounts_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// An account connected for a mailbox holds the provider's send permission
// (Gmail's full mail scope, Microsoft's Mail.Send), so How e-mail leaves
// offers it as it is, marked as the mailbox's.
function test_mail_accounts_a_mail_read_account_is_offered_for_sending()
{
	pg_assert_true(pg_mail_account_sends(array('mail_read')), 'an account for mailboxes sends');
	pg_assert_true(pg_mail_account_sends(array('mail')), 'an account for sending sends');
	pg_assert_true(pg_mail_account_sends(array('account', 'mail', 'mail_read')));
	pg_assert_false(pg_mail_account_sends(array('account')), 'a plain sign-in does not');
	pg_assert_false(pg_mail_account_sends(array('backup')), 'nor a backup account');

	// Both purposes carry what the sending call needs.
	$mail = include(PG_FUNCTIONS_DIR . '/includes/connect/purposes/mail.php');
	$read = include(PG_FUNCTIONS_DIR . '/includes/connect/purposes/mail_read.php');

	foreach (array_keys($mail['send']) as $provider) {
		pg_assert_true(isset($read['scopes'][$provider]), $provider . ' reads mailboxes too');
	}

	pg_assert_same(array(), array_values(array_diff($mail['send']['microsoft']['scopes'], $read['scopes']['microsoft'])), 'Mail.Send is granted to a mailbox account');
	pg_assert_true(in_array('https://mail.google.com/', $read['scopes']['google'], true), 'Gmail\'s full scope covers the send call');

	pg_assert_same('Connected for a mailbox', pg_mail_account_mark(mail_accounts_test_account(7, array('mail_read'))), 'a box\'s account is marked');
	pg_assert_same('', pg_mail_account_mark(mail_accounts_test_account(7, array('mail', 'mail_read'))), 'an account that also sends is not');
	pg_assert_same('', pg_mail_account_mark(mail_accounts_test_account(7, array('mail'))));

	$accounts = array(
		mail_accounts_test_account(9, array('mail_read'), 'google', 'support@example.com'),
		mail_accounts_test_account(7, array('mail'), 'google', 'news@example.com'),
	);
	$select = pg_mail_way_account_select('google', $accounts, 9, true, 'f');

	pg_assert_contains('<option value="9" selected>support@example.com · Connected for a mailbox</option>', $select, 'the box\'s account is offered and chosen');
	pg_assert_contains('<option value="7">news@example.com</option>', $select);

	// The save, the reader and email() take what the select offers.
	pg_assert_contains('pg_mail_account_sends($chosen[\'purposes\'])', mail_accounts_test_source('includes/fn/mail_settings.php'), 'the save keeps a mailbox account');
	pg_assert_contains('pg_mail_account_sends($account[\'purposes\'])', mail_accounts_test_source('includes/fn/mail.php'), 'email() sends through it');
	pg_assert_contains('$mail_accounts = pg_mail_sending_accounts();', mail_accounts_test_source('includes/settings/mail_sending.php'), 'the ways sheet lists both');
	pg_assert_contains('pg_mail_sending_accounts()', mail_accounts_test_source('includes/settings/mail_sending.save.php'), 'the way\'s check takes both');
}

// A sending account chosen for a mailbox asks the provider for reading too,
// on the same account: the form posts its row's number, so connect.php
// renews that row (its address as login hint, the purposes it holds kept)
// and pg_connect_finish() writes the row it finds, never a second one.
function test_mail_accounts_a_mail_account_chosen_for_a_box_asks_again_and_keeps_one_row()
{
	pg_assert_true(pg_mail_box_account_asks(mail_accounts_test_account(7, array('mail'))), 'a sending account asks');
	pg_assert_false(pg_mail_box_account_asks(mail_accounts_test_account(7, array('mail', 'mail_read'))), 'one that reads already does not');
	pg_assert_false(pg_mail_box_account_asks(mail_accounts_test_account(7, array('mail_read'))));
	pg_assert_false(pg_mail_box_account_asks(array('id' => 7, 'provider' => 'google', 'user_id' => 5, 'purposes' => array('mail'))), 'a person\'s own account is not the site\'s');
	pg_assert_false(pg_mail_box_account_asks(mail_accounts_test_account(7, array('mail'), 'dropbox')), 'a provider that reads no mailbox');

	$form = pg_mail_connect_form('pgset_mail_read_7', 'google', 'mail_read', 'settings_mailboxes.php#pgset-ws-mail', 7);

	pg_assert_contains('action="connect.php" id="pgset_mail_read_7"', $form);
	pg_assert_contains('<input type="hidden" name="purpose" value="mail_read">', $form, 'it asks for reading');
	pg_assert_contains('<input type="hidden" name="connection_id" value="7">', $form, 'on the account chosen');
	pg_assert_contains('<input type="hidden" name="return" value="settings_mailboxes.php#pgset-ws-mail">', $form);

	// connect.php: a renewal hints the account and keeps the purposes it
	// holds; the provider's answer is written to the row of that account.
	$start = mail_accounts_test_source('includes/fn/connect.php');
	pg_assert_contains("\$login_hint = (isset(\$provider['identity']) && (\$provider['identity'] === false)) ? '' : \$existing['label'];", $start, 'the account\'s address as login hint');
	pg_assert_contains('$scopes = array_merge($scopes, pg_connect_request_scopes($provider, $existing_purpose));', $start, 'the scopes it holds are asked again');
	pg_assert_contains('db("UPDATE connect_accounts SET " . $columns . " WHERE id = \'" . (int) $existing[\'id\'] . "\'");', $start, 'the same row is written');
	pg_assert_true(pg_connect_renewal_allowed(mail_accounts_test_account(7, array('mail')), 'google', 'site', 3), 'the site\'s own row may be renewed');

	// The box's form offers it, and the save refuses the account until it reads.
	$fields = ws_mail_box_account_option(mail_accounts_test_account(7, array('mail')), 0);
	pg_assert_contains('data-ws-mail-ask="7"', $fields, 'the option says it asks');

	$ask = ws_mail_box_account_ask_html(mail_accounts_test_account(7, array('mail')), true, false);
	pg_assert_contains('data-ws-mail-ask-for="7"', $ask);
	pg_assert_contains('form="pgset_mail_read_7"', $ask, 'its button submits the account\'s form');
	pg_assert_contains(' hidden', $ask, 'shown when the account is chosen');
	pg_assert_false(strpos(ws_mail_box_account_ask_html(mail_accounts_test_account(7, array('mail')), false, true), 'form=') !== false, 'below manager no button');

	pg_assert_contains("pg_mail_box_account_asks(\$account)", mail_accounts_test_source('includes/workspace/mail_settings.php'), 'the save names the permission to ask');
}

// Removing a mailbox never touches a connected account, and its confirm
// says the account sending uses stays.
function test_mail_accounts_removing_a_box_keeps_the_sending_account()
{
	foreach (ws_mail_box_remove_queries(3) as $sql) {
		pg_assert_false(stripos($sql, 'connect_accounts') !== false, 'no connected account is removed');
	}

	$box = array('id' => 3, 'name' => 'Support', 'settings' => '{}');
	pg_assert_same(ws_mail_box_remove_confirm($box), ws_mail_box_remove_confirm($box, ''), 'no account: as before');

	$kept = ws_mail_box_remove_confirm($box, pg_mail_box_remove_kept('info@example.com', array('sending' => array('system'), 'boxes' => array('Support'))));
	pg_assert_contains('info@example.com stays connected: the site\'s e-mail is still sent through it.', $kept);
	pg_assert_same('', pg_mail_box_remove_kept('info@example.com', array('sending' => array(), 'boxes' => array('Support'))), 'an account sending does not use');
}

// Disconnect on Connections names every use that stops.
function test_mail_accounts_disconnect_names_both_uses()
{
	$both = pg_mail_account_disconnect_confirm('info@example.com', array('sending' => array('system', 'campaign'), 'boxes' => array('Support', 'Sales')));
	pg_assert_contains('info@example.com', $both);
	pg_assert_contains('system e-mail and campaigns stop leaving through it', $both, 'sending');
	pg_assert_contains('the mailboxes Support, Sales stop being read', $both, 'reading');

	pg_assert_contains('the mailbox Support stops being read', pg_mail_account_disconnect_confirm('a@example.com', array('sending' => array(), 'boxes' => array('Support'))));
	pg_assert_contains('system e-mail stops leaving through it', pg_mail_account_disconnect_confirm('a@example.com', array('sending' => array('system'), 'boxes' => array())));
	pg_assert_same('The site will no longer use a@example.com, and every feature that does stops until an account is connected again.',
		pg_mail_account_disconnect_confirm('a@example.com', array('sending' => array(), 'boxes' => array())), 'no mail use: the sentence it had');

	$screen = mail_accounts_test_source('connections.php');
	pg_assert_same(0, substr_count($screen, "'The site will no longer use {var:1}, and every feature that does stops until an account is connected again.'"), 'every Disconnect of an account asks the one sentence');
	pg_assert_same(2, substr_count($screen, 'pg_mail_account_disconnect_confirm($account[\'label\'], pg_mail_account_uses($account[\'id\']))'), 'the drawer and the service page');
}

// The Connect buttons of both tabs: one helper draws the button and one
// its form outside the settings form.
function test_mail_accounts_connect_buttons_share_one_helper()
{
	$button = pg_mail_connect_button('pgset_mail_connect_google', 'Connect a Google account');
	pg_assert_contains('<button type="submit" form="pgset_mail_connect_google" class="pg-button pg-button-quiet pg-button-sm">', $button);
	pg_assert_contains('Connect a Google account', $button);

	pg_assert_contains('pg_mail_connect_button(', mail_accounts_test_source('includes/fn/mail_settings.php'), 'E-mail sending\'s buttons');
	pg_assert_contains('pg_mail_connect_form(', mail_accounts_test_source('includes/settings/mail_sending.php'), 'E-mail sending\'s forms');
	pg_assert_contains('pg_mail_connect_button(', mail_accounts_test_source('includes/workspace/mail_settings.php'), 'Mailboxes\' button');
	pg_assert_contains('pg_mail_connect_form(', mail_accounts_test_source('includes/workspace/mail_settings.php'), 'Mailboxes\' forms');
	pg_assert_contains('ws_mail_box_account_ask_forms(', mail_accounts_test_source('includes/settings/mailboxes.php'), 'printed outside the settings form');
	pg_assert_false(strpos(mail_accounts_test_source('includes/settings/mail_sending.php'), 'pg_connect_start_fields(') !== false, 'no form of its own');
}
