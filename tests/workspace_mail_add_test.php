<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Add a mailbox's three-step sheet (includes/workspace/mail_add.php):
 * the step line, the starting points as choice cards, the providers' own
 * buttons or the two "not set up" cards, the foot of each step, the result
 * line, the return address and the account it brings back, what waits for
 * Finish. No database.
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

function workspace_mail_add_test_source($relative)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative));
}

function test_workspace_mail_add_step_line()
{
	$first = ws_mail_add_steps_html(1);

	pg_assert_contains('<ol class="stepper pg-steps" aria-label="' . h(lang('Add a mailbox')) . '" data-ws-mail-add-line>', $first, 'the Step line pattern');
	pg_assert_same(3, substr_count($first, '<li class="stepper-item'), 'Choose, Sign in, Details; no Done step');
	pg_assert_same(1, substr_count($first, 'aria-current="step"'), 'one current step');
	pg_assert_same(0, substr_count($first, 'is-done'), 'nothing done on Choose');

	$third = ws_mail_add_steps_html(3);

	pg_assert_same(2, substr_count($third, '<li class="stepper-item is-done"'), 'Details: the two before it checked');
	pg_assert_contains('<li class="stepper-item" aria-current="step"><span class="pg-steps-name">' . h(lang('Details')), $third, 'Details is current');
}

function test_workspace_mail_add_choice_cards()
{
	$html = ws_mail_add_choice('ws_mail_start', 'ws_mail_0_start_gmail', 'gmail', array('tone' => 'blue', 'icon' => 'bi-google', 'title' => 'Gmail', 'line' => 'Gmail or Google Workspace.'), ' data-ws-mail-start');

	pg_assert_contains('<div class="form-field form-field-card"><input class="radio" type="radio" name="ws_mail_start" value="gmail" id="ws_mail_0_start_gmail" aria-describedby="ws_mail_0_start_gmail_help" data-ws-mail-start>', $html, 'a radio card');
	pg_assert_contains('<span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><i class="bi bi-google"></i></span><strong>Gmail</strong><span class="pg-start-mark" aria-hidden="true"><i class="bi bi-check"></i></span>', $html, 'tile, title and the mark');

	$note = ws_mail_add_choice('x', 'x_1', '1', array('title' => 'T', 'line' => 'L', 'note' => '2 minutes'));

	pg_assert_false(strpos($note, 'pg-icon-tile'), 'no tile without an icon');
	pg_assert_contains('L<span class="d-block pg-start-note">2 minutes</span>', $note, 'a duration as the note');
}

function test_workspace_mail_add_provider_buttons()
{
	$ready = ws_mail_add_signin_html(array('gmail' => true, 'microsoft' => true));

	pg_assert_contains('<button type="submit" form="pgset-ws-mail-add-gmail" class="pg-signin-google" data-ws-mail-add-button="gmail"><svg width="18" height="18"', $ready, 'Google\'s button submits its sign-in form');
	pg_assert_contains('<button type="submit" form="pgset-ws-mail-add-microsoft" class="pg-signin-microsoft" data-ws-mail-add-button="microsoft"><svg width="21" height="21"', $ready, 'and Microsoft\'s');
	pg_assert_same(1, substr_count($ready, h(lang('Sign in with Google'))), 'one Google button');
	pg_assert_false(strpos($ready, 'connections.php'), 'no link to Connections');
	pg_assert_false(strpos($ready, 'ws_mail_add_google'), 'no "not set up" cards');
	pg_assert_false(strpos($ready, 'pg-button'), 'no look A button class on a provider button');
	pg_assert_contains(h(lang('Pinegrap reads and answers this mailbox. Your password is not written to the site.')), $ready, 'the line under the button');

	$none = ws_mail_add_signin_html(array('gmail' => false, 'microsoft' => false));

	pg_assert_same(2, substr_count($none, 'name="ws_mail_add_google"'), 'Set up Google sign-in and Use an app password');
	pg_assert_contains(h(lang('Set up Google sign-in')), $none);
	pg_assert_contains(h(lang('Use an app password')), $none);
	pg_assert_contains('data-bs-target="#pgset-ws-mail-google-setup"', $none, 'setting up opens Google\'s numbered steps');
	pg_assert_contains('data-bs-target="#pgset-ws-mail-microsoft-setup"', $none, 'and Microsoft\'s');
	pg_assert_false(strpos($none, 'pg-signin-google'), 'no button that cannot work');
	pg_assert_false(strpos($none, 'form="pgset-ws-mail-add'), 'and no sign-in form');
}

function test_workspace_mail_add_foot()
{
	$foot = ws_mail_add_foot('pg_settings_modal_form', true);

	pg_assert_contains('class="pg-button pg-button-ghost" data-ws-mail-add-back hidden', $foot, 'Back ghost, hidden on Choose');
	pg_assert_contains('data-pgdrawer-close>' . h(lang('Cancel')), $foot, 'Cancel quiet');
	pg_assert_contains('<button type="button" class="pg-button pg-button-fill" data-ws-mail-add-next>' . h(lang('Continue')), $foot, 'Continue on Choose');
	pg_assert_contains('name="ws_mail_add_test" value="1" form="pg_settings_modal_form"', $foot, 'Test connection is a named button of the form');
	pg_assert_contains('name="ws_mail_box_save" value="0" form="pg_settings_modal_form" class="pg-button pg-button-fill" data-ws-mail-add-finish hidden>' . h(lang('Finish')), $foot, 'Finish saves the new box');
	pg_assert_contains('data-bs-dismiss="drawer"', ws_mail_add_foot('f', false), 'on the page Bootstrap closes it');
	pg_assert_true(pg_settings_keep_drawer_test_shut('pgset-ws-mail-0'), 'the sheet stays shut after its save; a come-back opens it');
}

/**
 * Mirrors pgSettingsKeepDrawer(): an add drawer's id ends in -0.
 *
 * @param string $id
 * @return bool
 */
function pg_settings_keep_drawer_test_shut($id)
{
	return (bool) preg_match('/-(0|add|new)$/', $id);
}

function test_workspace_mail_add_result_line()
{
	$ok = ws_mail_add_result(array('ok' => true), 'info@example.com', 'imap.gmail.com');

	pg_assert_same(true, $ok['ok']);
	pg_assert_same(lang(array('string' => '{var:1} connected', 'vars' => 'info@example.com')), $ok['title']);
	pg_assert_contains('<div class="pg-result mb-5" role="status"><span class="pg-icon-tile pg-tone-teal"', ws_mail_add_result_html($ok), 'the green Result line');

	$failed = ws_mail_add_result(array('ok' => false, 'error' => 'auth', 'detail' => ''), 'info@example.com', 'imap.gmail.com');
	$html = ws_mail_add_result_html($failed);

	pg_assert_same(lang('The connection test failed'), $failed['title']);
	pg_assert_contains(lang('The server refused the user name or password.'), $failed['line'], 'the server\'s refusal in words');
	pg_assert_contains('pg-icon-tile pg-tone-rose', $html, 'a failed test: the rose tile');
	pg_assert_contains('<div class="pg-value-row">', $html, 'in a value row, no recipe of its own');
	pg_assert_false(strpos($html, 'pg-result'), 'never the green line');
	pg_assert_same('Refused.', ws_mail_add_result(array('ok' => false, 'text' => 'Refused.'), '', '')['line'], 'a sign-in refusal as connect.php said it');
}

function test_workspace_mail_add_return_and_names()
{
	pg_assert_same('settings_mailboxes.php?ws_mail_add=gmail#pgset-ws-mail', ws_mail_add_return('gmail'));
	pg_assert_same('settings_mailboxes.php?ws_mail_add=microsoft#pgset-ws-mail', ws_mail_add_return('microsoft'));
	pg_assert_same(ws_mail_add_return('microsoft'), pg_connect_return_path(ws_mail_add_return('microsoft')), 'connect.php keeps the address');
	pg_assert_same('pgset-ws-mail-add-gmail', ws_mail_add_form_id('anything'));
	pg_assert_same('Info', ws_mail_add_name('info@example.com'));
	pg_assert_same('Support team', ws_mail_add_name('support.team@example.com'));
	pg_assert_same('Çağrı', ws_mail_add_name('çağrı@example.com'));
}

function test_workspace_mail_add_probe_box()
{
	$box = ws_mail_add_probe_box(array('provider' => 'gmail', 'host' => 'imap.gmail.com', 'port' => 993, 'user' => 'info@example.com', 'connection_id' => 7,
		'folder' => 'INBOX', 'after_fetch' => 'seen', 'name' => 'Info', 'address' => 'info@example.com'), '');
	$settings = ws_mail_box_settings($box);

	pg_assert_same(0, $box['id'], 'nothing stored');
	pg_assert_same('imap.gmail.com', $settings['host']);
	pg_assert_same(7, $settings['connection_id'], 'signs in with the account');
	pg_assert_false(isset($settings['secret']), 'no password with an account');
	pg_assert_same('sealed', ws_mail_box_settings(ws_mail_add_probe_box(array('host' => 'h'), 'sealed'))['secret'], 'the sealed password');
}

function test_workspace_mail_add_fill_and_pending()
{
	$fill = ws_mail_add_fill(array('step' => 'details', 'fields' => array('provider' => 'gmail', 'connection_id' => '7', 'secret' => 'x', 'enabled' => '0')));

	pg_assert_same(array('step' => 'details', 'fields' => array('provider' => 'gmail', 'connection_id' => '7')), $fill, 'only the fields the sheet fills; never a secret');
	pg_assert_same('signin', ws_mail_add_fill(array('step' => 'signin'))['step']);

	$saved = $_SESSION['software']['ws_mail_add'] ?? null;

	ws_mail_add_pending(array('step' => 'details', 'fields' => array('host' => 'imap.example.com', 'user' => 'u'), 'secret' => '', 'result' => array()));
	pg_assert_same('imap.example.com', ws_mail_add_pending()['fields']['host'], 'kept for Finish');

	$_SESSION['software']['ws_mail_add']['at'] = time() - WS_MAIL_ADD_PENDING_TTL - 1;
	pg_assert_same(null, ws_mail_add_pending(), 'gone once old');

	ws_mail_add_pending(false);
	pg_assert_false(isset($_SESSION['software']['ws_mail_add']), 'Finish clears it');

	if (pg_connect_can_seal()) {
		ws_mail_add_pending(array('step' => 'details', 'fields' => array('host' => 'imap.example.com', 'user' => 'u'), 'secret' => pg_connect_seal(array('password' => 'pw')), 'result' => array()));
		pg_assert_same('pw', ws_mail_add_finish_input(array('host' => 'imap.example.com', 'user' => 'u', 'password' => ''))['password'], 'Finish takes the tested password');
		pg_assert_same('', ws_mail_add_finish_input(array('host' => 'imap.other.com', 'user' => 'u', 'password' => ''))['password'], 'never for another server');
		pg_assert_same('new', ws_mail_add_finish_input(array('host' => 'imap.example.com', 'user' => 'u', 'password' => 'new'))['password'], 'a typed one wins');
		ws_mail_add_pending(false);
	}

	if ($saved !== null) {
		$_SESSION['software']['ws_mail_add'] = $saved;
	}
}

function test_workspace_mail_add_wiring()
{
	$category = workspace_mail_add_test_source('includes/settings/mailboxes.php');
	$save = workspace_mail_add_test_source('includes/settings/mailboxes.save.php');
	$connect = workspace_mail_add_test_source('connect.php');

	pg_assert_contains('ws_mail_add_returned($ws_added, pg_connect_last_take());', $category, 'the return tests the account');
	pg_assert_contains('ws_settings_mail_drawer(null, $ws_viewer, $ws_form, !empty($pg_settings_in_dialog), $ws_pending);', $category, 'the sheet with what came back');
	pg_assert_contains('ws_mail_add_pending(array(\'shown\' => true) + $ws_pending)', $category, 'a come-back opens the sheet once');
	pg_assert_contains('$ws_tested = ws_mail_add_test_run(ws_mail_settings_dialog_input($_POST, 0));', $save, 'Test connection before anything is stored');
	pg_assert_contains("\$liveform->mark_error('ws_mail[0][' . \$ws_tested['field'] . ']', h(\$ws_tested['error']));", $save, 'a failed test marks its field on Sign in');
	pg_assert_contains('ws_mail_add_finish_input($ws_input)', $save, 'Finish takes the tested password');
	pg_assert_contains("ws_mail_settings_post(\$ws_viewer, 'mail_test', \$liveform, array('box_id' => \$ws_new), null, \$ws_warn);", $save, 'and tests the stored box, for its pill');
	pg_assert_contains("\$result['label'])), \$result['connection_id']);", $connect, 'connect.php says which account it connected');

	$_SESSION['software']['connect_flash'] = null;
	pg_connect_flash('notice', 'Done.', 12);
	pg_assert_same(array('kind' => 'notice', 'text' => 'Done.', 'connection_id' => 12), pg_connect_flash_take());
	pg_assert_same(12, pg_connect_last_take()['connection_id'], 'the account waits for Mailboxes after the Sending tab showed the message');
	pg_assert_same(null, pg_connect_last_take(), 'once');

	$script = ws_mail_add_script();

	foreach (array('reveal: function (control)', 'resume: function ()', '[data-ws-mail-add-next]', '[data-ws-mail-add-back]', 'data-ws-mail-add-server') as $part) {
		pg_assert_contains($part, $script);
	}
}
