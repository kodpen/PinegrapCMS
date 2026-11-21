<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mailbox's drawer in Site Settings › Mailboxes
 * (includes/workspace/mail_settings.php): the names it posts, which the
 * category's save module and the settings search read, stay the same; its
 * four tabs, the folds, Routing as rows, one line of help a field and the
 * foot.
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

/**
 * The distinct names a drawer's markup posts, in order of first use.
 *
 * @param string $html
 * @return string[]
 */
function workspace_mail_drawer_test_names($html)
{
	preg_match_all('/ name="([^"]+)"/', (string) $html, $m);

	return array_values(array_unique($m[1]));
}

function test_workspace_mail_drawer_posted_names()
{
	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'enabled' => 1, 'channel_id' => 4, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'reply_via' => 'box',
		'settings' => json_encode(array('provider' => 'other', 'host' => 'imap.example.com', 'port' => 993, 'routes' => array(array('match' => '+billing', 'channel_id' => 4)))));
	$html = ws_mail_box_form('', $box, array(4 => array('id' => 4, 'name' => 'support')), true, 'pg_settings_modal_form');
	$names = workspace_mail_drawer_test_names($html);
	$fields = array('name', 'address', 'kept_in', 'channel_id', 'auth_server', 'route_match][', 'route_channel][', 'provider', 'host', 'port', 'google_way', 'user', 'password',
		'connection_id', 'folder', 'after_fetch', 'move_folder', 'reply_via', 'smtp_host', 'smtp_port', 'sent_copy', 'sent_folder', 'read_sent', 'signature', 'support',
		'first_reply_hours', 'auto_reply', 'auto_reply_text', 'ai_triage', 'reads_bounces', 'enabled');

	foreach ($fields as $field) {
		pg_assert_true(in_array('ws_mail[3][' . $field . ']', $names, true), 'the drawer posts ws_mail[3][' . $field . ']');
	}

	foreach ($names as $name) {
		$own = (strpos($name, 'ws_mail[3][') === 0) && in_array(substr($name, 11, -1), $fields, true);
		pg_assert_true($own || in_array($name, array('ws_mail_box_save', 'ws_mail_test', 'ws_mail_remove'), true), 'no other name is posted: ' . $name);
	}

	pg_assert_contains('name="ws_mail_test" value="3" form="pg_settings_modal_form"', $html, 'Test connection, in the Reading tab');
	pg_assert_same(false, strpos($html, ' required'), 'nothing in the drawer blocks the pane\'s Save');

	// The ids the settings search and the refused-save marks find.
	foreach (array('name', 'address', 'provider', 'host', 'port', 'user', 'password', 'connection', 'folder', 'after', 'reply_via', 'smtp_host', 'signature', 'auth', 'reads_bounces', 'enabled') as $id) {
		pg_assert_contains('id="ws_mail_3_' . $id . '"', $html, 'the id ws_mail_3_' . $id . ' stays');
	}
}

function workspace_mail_drawer_test_box($settings = array())
{
	return array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'enabled' => 1, 'channel_id' => 4, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'reply_via' => 'box',
		'last_error' => '', 'settings' => json_encode($settings));
}

function test_workspace_mail_drawer_tabs()
{
	$html = ws_mail_box_form('', workspace_mail_drawer_test_box(array('provider' => 'gmail', 'host' => 'imap.gmail.com')), array(4 => array('id' => 4, 'name' => 'support')), true, 'f');

	pg_assert_same(array('general' => lang('General'), 'reading' => lang('Reading'), 'answers' => lang('Answers'), 'routing' => lang('Routing')), ws_mail_box_tab_labels(false), 'four tabs');
	pg_assert_same(array('general', 'reading', 'answers'), array_keys(ws_mail_box_tab_labels(true)), 'a box kept in the mailbox itself has no Routing');
	pg_assert_same(4, substr_count($html, 'role="tabpanel"'), 'a pane a tab');
	pg_assert_same(3, substr_count($html, 'role="tabpanel" id="ws_mail_3_pane_') - substr_count($html, 'aria-labelledby="ws_mail_3_tab_general">'), 'three panes besides General');
	pg_assert_same(3, substr_count($html, '" hidden>'), 'only General is open');

	$bar = ws_mail_box_tabs('ws_mail_3_', array('general' => array('General', ''), 'reading' => array('Reading', '')))['bar'];
	pg_assert_contains('<span class="pg-seg" role="tablist"', $bar, 'the segmented control');
	pg_assert_contains('role="tab" id="ws_mail_3_tab_general" aria-selected="true" aria-controls="ws_mail_3_pane_general"', $bar);
	pg_assert_contains('aria-selected="false" aria-controls="ws_mail_3_pane_reading" tabindex="-1"', $bar);

	// Each field sits in its tab.
	$pane = function ($key) use ($html) {
		$start = strpos($html, 'id="ws_mail_3_pane_' . $key . '"');
		$end = strpos($html, 'role="tabpanel"', $start + 10);

		return substr($html, $start, ($end === false) ? strlen($html) - $start : $end - $start);
	};
	foreach (array('general' => array('name', 'address', 'kept_in', 'channel_id', 'enabled', 'support', 'signature'),
		'reading' => array('provider', 'host', 'port', 'user', 'password', 'connection_id', 'folder', 'after_fetch', 'auth_server', 'reads_bounces'),
		'answers' => array('reply_via', 'smtp_host', 'sent_copy', 'sent_folder', 'read_sent', 'first_reply_hours', 'auto_reply', 'auto_reply_text', 'ai_triage'),
		'routing' => array('route_match][', 'route_channel][')) as $key => $names) {
		foreach ($names as $name) {
			pg_assert_contains('name="ws_mail[3][' . $name . ']"', $pane($key), $name . ' in ' . $key);
		}
	}
}

function test_workspace_mail_drawer_reading()
{
	$gmail = ws_mail_box_server_fields('p_', null, array('provider' => 'gmail', 'host' => 'imap.gmail.com'));
	$advanced = substr($gmail, strpos($gmail, '<details class="pg-start-advanced">'));

	pg_assert_contains('name="host"', $advanced, 'a service fills the server: it waits under Advanced');
	pg_assert_contains('name="auth_server"', $advanced, 'the checking server under Advanced');
	pg_assert_contains('name="reads_bounces"', $advanced, 'Reads bounces under Advanced');
	pg_assert_contains(h(lang('The server whose sender check is trusted.')), $advanced);

	$other = ws_mail_box_server_fields('p_', null, array('provider' => 'other', 'host' => 'imap.example.com'));
	pg_assert_true(strpos($other, 'name="host"') < strpos($other, '<details'), 'another server shows its server in the tab');
	pg_assert_same(1, substr_count($other, 'name="host"'), 'one server field');

	pg_assert_same('none', ws_mail_box_signin_way('', 'connection', 0, ''), 'no service, no server: nothing to sign in');
	pg_assert_same('password', ws_mail_box_signin_way('', 'connection', 0, 'imap.example.com'), 'a server without a service');
	pg_assert_same('account', ws_mail_box_signin_way('microsoft', 'password', 0, ''), 'Microsoft signs in with an account');
	pg_assert_same('account', ws_mail_box_signin_way('gmail', 'connection', 0, ''));
	pg_assert_same('password', ws_mail_box_signin_way('gmail', 'password', 0, ''), 'Gmail with an app password');
	pg_assert_same('password', ws_mail_box_signin_way('yandex', 'connection', 0, ''));
	pg_assert_same('account', ws_mail_box_signin_way('other', 'connection', 5, ''), 'a chosen account stays shown');
	pg_assert_true(ws_mail_box_server_own('other', ''));
	pg_assert_true(ws_mail_box_server_own('', 'imap.example.com'));
	pg_assert_false(ws_mail_box_server_own('gmail', 'imap.gmail.com'));

	pg_assert_contains('<div class="md:col-6 d-none" data-ws-mail-when="signin=password">', ws_mail_box_server_fields('p_', null, array('provider' => 'microsoft')), 'no password for Microsoft');
	pg_assert_contains('<div class="md:col-12 d-none" data-ws-mail-when="signin=account">', ws_mail_box_server_fields('p_', null, array('provider' => 'yahoo')), 'no account for Yahoo');
	pg_assert_contains('<div class="md:col-6 d-none" data-ws-mail-when="signin!=none">', ws_mail_box_server_fields('p_', null, array()), 'no folder for .eml files only');
	pg_assert_same(false, strpos($gmail, h(lang('What each service needs'))), 'the service list is gone: the chosen one\'s line shows');
	pg_assert_contains('<div class="pg-field-help" data-ws-mail-when="provider=gmail">', $gmail, 'Gmail\'s line');
}

function test_workspace_mail_drawer_help_is_a_line()
{
	$html = ws_mail_box_form('', workspace_mail_drawer_test_box(array('provider' => 'other', 'host' => 'imap.example.com', 'routes' => array(array('match' => '+billing', 'channel_id' => 4)))),
		array(4 => array('id' => 4, 'name' => 'support')), true, 'f');
	// The Gmail part's steps go to their own drawer in a later step.
	$html = preg_replace('#<div class="md:col-12[^"]*" data-ws-mail-when="provider=gmail">\s*<div class="pg-card-inner p-3">.*?</div>\s*</div>\s*</div>#s', '', $html);

	preg_match_all('#<div class="pg-field-help[^"]*"[^>]*>([^<]*)</div>#', $html, $m);
	pg_assert_true(count($m[1]) > 10, 'help lines are found');

	foreach ($m[1] as $line) {
		pg_assert_true(mb_strlen(html_entity_decode($line, ENT_QUOTES)) <= 130, 'one line of help: ' . $line);
		pg_assert_true(substr_count(html_entity_decode($line, ENT_QUOTES), '. ') === 0, 'one sentence: ' . $line);
	}

	pg_assert_same(false, strpos($html, '<h3 class="pg-group-label'), 'no group headings with paragraphs: the tabs name the parts');
}

function test_workspace_mail_drawer_foot_and_head()
{
	$box = workspace_mail_drawer_test_box();
	$foot = ws_mail_box_drawer_foot($box, 'f', true);

	pg_assert_true(strpos($foot, 'pg-button-danger') < strpos($foot, 'pg-button-quiet'), 'Remove mailbox at the left');
	pg_assert_true(strpos($foot, 'pg-button-quiet') < strpos($foot, 'pg-button-fill'), 'Save last');
	pg_assert_same(1, substr_count($foot, 'pg-button-fill'), 'one filled button');
	pg_assert_contains('name="ws_mail_remove" value="3" form="f" class="pg-button pg-button-danger" data-ws-mail-confirm="' . h(ws_mail_box_remove_confirm($box)) . '"', $foot, 'Remove asks MT1\'s confirm');
	pg_assert_contains(h(lang('Remove mailbox')), $foot);
	pg_assert_contains('name="ws_mail_box_save" value="3" form="f" class="pg-button pg-button-fill">' . h(lang('Save')), $foot, 'Save names the box');
	pg_assert_contains('data-pgdrawer-close>' . h(lang('Cancel')), $foot, 'Cancel closes the dialog\'s panel');
	pg_assert_contains('data-bs-dismiss="drawer">' . h(lang('Cancel')), ws_mail_box_drawer_foot($box, 'f', false), 'and the page\'s drawer');

	$new = ws_mail_box_drawer_foot(null, 'f', true);
	pg_assert_same(false, strpos($new, 'ws_mail_remove'), 'nothing to remove on a new box');
	pg_assert_contains('name="ws_mail_box_save" value="0"', $new);

	$drawer = pg_settings_drawer('x', 'Support', 'body', true, 'foot', 'support@example.com <span class="pg-pill pg-tone-teal">Reading</span>', '<span class="pg-seg"></span>');
	pg_assert_contains('Support</h3><p class="small fg-2 mb-0 mt-1 d-flex flex-wrap align-items-center gap-3">support@example.com <span class="pg-pill pg-tone-teal">Reading</span></p>', $drawer, 'the head: name, address, pill');
	pg_assert_contains('<div class="pg-sm-drawer-bar"><span class="pg-seg"></span></div>', $drawer, 'the tabs in a band under the head');
	pg_assert_same(false, strpos(pg_settings_drawer('x', 'T', 'b', true), 'pg-sm-drawer-bar'), 'other drawers are unchanged');
}
