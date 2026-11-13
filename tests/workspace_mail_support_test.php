<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mailbox's "Support box" switch (includes/workspace/
 * mail_status.php and the places that ask it): a plain box keeps no status,
 * holder, first-answer time or automatic answer, its conversations count as
 * open and held by nobody in the lists, and its form keeps what it had for
 * the day it is a support box again. Saving a box and reading the lists
 * need the database and are checked here by their source.
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

// A box row as the form and the readers get it.
function workspace_mail_support_test_box($settings, $changes = array())
{
	return array_merge(array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 5, 'folder' => 'INBOX', 'after_fetch' => 'seen',
		'enabled' => 1, 'reply_via' => 'box', 'settings' => $settings, 'auto_reply' => 1, 'auto_reply_text' => 'Thanks, we got it.'), $changes);
}

function test_workspace_mail_support_box_is_support()
{
	pg_assert_true(ws_mail_box_is_support(null), 'no box: as before');
	pg_assert_true(ws_mail_box_is_support(array('settings' => '')), 'no settings');
	pg_assert_true(ws_mail_box_is_support(array('settings' => '{"first_reply_hours":8}')), 'a box saved before the switch stays a support box');
	pg_assert_true(ws_mail_box_is_support(array('settings' => '{"support":true}')));
	pg_assert_false(ws_mail_box_is_support(array('settings' => '{"support":false}')), 'a plain box');
}

function test_workspace_mail_support_target_and_automatic_answer()
{
	$plain = workspace_mail_support_test_box('{"support":false,"first_reply_hours":8}');
	$support = workspace_mail_support_test_box('{"support":true,"first_reply_hours":8}');

	pg_assert_same(8, ws_mail_first_reply_hours($support));
	pg_assert_same(0, ws_mail_first_reply_hours($plain), 'a plain box has no target');
	pg_assert_same(8, ws_mail_first_reply_hours($plain, true), 'but keeps the one it had');
	pg_assert_true(ws_mail_auto_reply_due($support, 'pass', 0));
	pg_assert_false(ws_mail_auto_reply_due($plain, 'pass', 0), 'a plain box never answers by itself');

	$conversation = array('first_in_at' => 100, 'first_reply_at' => 0, 'status' => 'open');
	pg_assert_false(ws_mail_first_reply_overdue($conversation, ws_mail_first_reply_hours($plain), 1000000), 'never overdue');
}

function test_workspace_mail_support_state_offer()
{
	$conversation = array('status' => 'waiting', 'assignee_id' => 7);

	pg_assert_same('waiting', ws_mail_state_offer(array('conversation' => $conversation, 'support' => true), 3, 7)['status']);
	pg_assert_same('waiting', ws_mail_state_offer(array('conversation' => $conversation), 3, 7)['status'], 'an item without the flag: as before');
	pg_assert_same(null, ws_mail_state_offer(array('conversation' => $conversation, 'support' => false), 3, 7), 'nothing to change on a plain box');
}

function test_workspace_mail_support_list_where()
{
	$where = function ($words, $plain) {
		return ws_mail_list_where(ws_mail_list_filters($words), 7, array(3), '', $plain);
	};

	pg_assert_same("channel_id IN (3) AND message_id > 0 AND status = 'open' AND last_in_at > 0", $where(array(), array()), 'no plain box: as before');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND (status = 'open' OR box_id IN (2,4)) AND last_in_at > 0", $where(array(), array(2, '4', 0)),
		'Incoming: a plain box\'s conversations are open');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND status = 'waiting' AND box_id NOT IN (2) AND last_in_at > 0", $where(array('status' => 'waiting'), array(2)), 'never waiting');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND status = 'closed' AND box_id NOT IN (2) AND last_in_at > 0", $where(array('status' => 'closed'), array(2)), 'never closed');
	pg_assert_same("channel_id IN (3) AND message_id > 0", $where(array('status' => 'all', 'view' => 'all'), array(2)), 'All: every conversation');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND assignee_id = '7' AND box_id NOT IN (2)", $where(array('status' => 'all', 'view' => 'all', 'holder' => 'me'), array(2)), 'held by nobody');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND (assignee_id = 0 OR box_id IN (2))", $where(array('status' => 'all', 'view' => 'all', 'holder' => 'none'), array(2)));
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND first_reply_at = 0 AND status <> 'closed' AND box_id NOT IN (2)",
		$where(array('status' => 'all', 'view' => 'all', 'overdue' => '1'), array(2)), 'never overdue');
}

function test_workspace_mail_support_list_rows_and_toolbar()
{
	$row = array('id' => 4, 'channel_id' => 3, 'message_id' => 90, 'subject' => 'Order', 'customer_name' => 'Ayşe', 'customer_email' => 'ayse@example.com',
		'status' => 'waiting', 'assignee_id' => 0, 'assignee_name' => '', 'first_in_at' => 100, 'first_reply_at' => 0, 'last_in_at' => 100, 'last_out_at' => 0,
		'box_name' => 'Info', 'channel_name' => '', 'first_reply_hours' => 0, 'overdue' => false, 'support' => false,
		'mails' => 1, 'snippet' => '', 'unread' => false, 'unsent' => '');
	$filters = ws_mail_list_filters(array());

	pg_assert_same(false, strpos(ws_mail_list_rows_html(array($row), $filters, 1000), h(lang('Waiting for the customer'))), 'a plain box\'s row shows no status');
	pg_assert_contains(h(lang('Waiting for the customer')), ws_mail_list_rows_html(array(array('support' => true) + $row), $filters, 1000), 'a support box\'s row does');

	pg_assert_true(ws_mail_list_support(array(2 => 'Info', 3 => 'Support'), array(2)), 'one support box among them');
	pg_assert_false(ws_mail_list_support(array(2 => 'Info'), array(2)), 'plain boxes only');
	pg_assert_false(ws_mail_list_support(array(), array()), 'no conversation at all');

	$saved = $_GET;
	$_GET = array();
	$plain = ws_mail_list_filters_html($filters, array(2 => 'Info', 4 => 'Sales'), false, false);
	$support = ws_mail_list_filters_html($filters, array(2 => 'Info', 4 => 'Sales'), false, true);
	$_GET = $saved;

	pg_assert_same(false, strpos($plain, 'name="status"'), 'no Status');
	pg_assert_same(false, strpos($plain, 'name="holder"'), 'no Held by');
	pg_assert_same(false, strpos($plain, h(lang('First answer overdue'))), 'no overdue toggle');
	pg_assert_contains('name="box"', $plain, 'the mailbox filter stays');
	pg_assert_contains('name="status"', $support);
	pg_assert_contains(h(lang('First answer overdue')), $support);
}

function test_workspace_mail_support_form()
{
	$plain = ws_mail_box_form('workspace_settings.php', workspace_mail_support_test_box('{"support":false,"first_reply_hours":8}'), array());

	pg_assert_contains('<input type="checkbox" name="support" value="1" id="ws_mail_3_support">', $plain, 'a plain box: switch off');
	pg_assert_contains('<div class="md:col-12 d-none" data-ws-mail-when="support=1">', $plain, 'its support half hidden');
	pg_assert_contains('value="8"', $plain, 'the kept target still in the hidden half');

	$support = ws_mail_box_form('workspace_settings.php', workspace_mail_support_test_box('{"first_reply_hours":8}'), array());
	pg_assert_contains('<input type="checkbox" name="support" value="1" id="ws_mail_3_support" checked>', $support, 'a box saved before the switch: on');
	pg_assert_contains('<div class="md:col-12" data-ws-mail-when="support=1">', $support);
	pg_assert_true(strpos($support, 'data-ws-mail-new') === false, 'not a new box');

	$new = ws_mail_box_form('workspace_settings.php', null, array());
	pg_assert_contains('name="support" value="1" id="ws_mail_0_support" checked data-ws-mail-new>', $new, 'a new box starts on, the script follows Kept in');

	$script = ws_mail_settings_script();
	pg_assert_contains("support.checked = (target.value !== 'mailbox');", $script, 'a new box kept in the mailbox itself starts plain');
	pg_assert_contains("support.removeAttribute('data-ws-mail-new');", $script, 'until the person sets the switch');
	pg_assert_contains("(select.type === 'checkbox') ? (select.checked ? select.value : '')", $script, 'a switch shows its half while on');
}

function test_workspace_mail_support_triage_body()
{
	$suggestion = array('summary' => 'Where is my order?', 'status' => 'waiting', 'member_id' => 7, 'draft' => 'Hello');
	$plain = ws_mail_triage_body($suggestion, array(7 => 'Deniz'), array());
	$support = ws_mail_triage_body($suggestion, array(7 => 'Deniz'), ws_mail_statuses());

	pg_assert_same(false, strpos($plain, 'Deniz'), 'nobody to give it to');
	pg_assert_same(false, strpos($plain, h(lang('Waiting for the customer'))), 'no status');
	pg_assert_same('Hello', ws_mail_triage_draft_of($plain), 'the draft stays');
	pg_assert_contains('Deniz', $support);
}

function test_workspace_mail_support_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	$settings = $read('includes/workspace/mail_settings.php');
	pg_assert_contains("\$settings['support'] = \$support;", $settings, 'the switch is kept in the box\'s settings');
	pg_assert_contains("'auto_reply' => (int) (\$box['auto_reply'] ?? 0), 'auto_reply_text' => (string) (\$box['auto_reply_text'] ?? '')", $settings, 'a plain box keeps its automatic answer');
	pg_assert_contains("array('ok' => true, 'error' => '', 'hours' => ws_mail_first_reply_hours(\$box, true))", $settings, 'and its target');
	pg_assert_contains("ws_mail_support_field(\$prefix, \$box)", $settings);

	pg_assert_contains("(\$card && (\$item['support'] ?? true) && function_exists('ws_mail_status_html'))", $read('includes/workspace/mail.php'), 'the card shows no state for a plain box');
	pg_assert_contains("\$items[\$id]['support'] = ", $read('includes/workspace/mail.php'), 'the card and its offer know the box');
	pg_assert_contains("'support' => ws_mail_box_is_support(\$box)", $read('includes/workspace/mail_pane.php'), 'the pane offers no state for a plain box');
	pg_assert_contains("\$charset, ws_mail_plain_box_ids());", $read('includes/workspace/mail_list.php'), 'the list');
	pg_assert_contains("'', ws_mail_plain_box_ids());", $read('includes/workspace/mail_reads.php'), 'the menu\'s count');
	pg_assert_contains("ws_mail_list_support(\$boxes, ws_mail_plain_box_ids())", $read('workspace_mail.php'), 'the toolbar');
	pg_assert_contains("ws_mail_box_is_support(\$box) ? ws_mail_statuses() : array()", $read('includes/workspace/mail_triage.php'), 'the suggestion');
}
