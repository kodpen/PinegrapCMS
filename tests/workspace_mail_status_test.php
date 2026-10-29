<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the state of an e-mail conversation and the mailbox's automatic
 * answer (includes/workspace/mail_status.php): the statuses, what a card
 * shows and offers, the change a person makes, when an automatic answer is
 * due, the box form's automatic answer and how such an answer is drawn.
 * Writing and sending need the database and a mail server and are not run
 * here.
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

function test_workspace_mail_status_html()
{
	$html = ws_mail_status_html(array('status' => 'waiting'), 'Deniz <b>');

	pg_assert_contains(h(lang('Waiting for the customer')), $html);
	pg_assert_contains('theme-warning', $html);
	pg_assert_contains('Deniz &lt;b&gt;', $html, 'the holder\'s name as text');
	pg_assert_contains(h(lang('Open')), ws_mail_status_html(array('status' => 'odd'), ''), 'an unknown status reads as open');
	pg_assert_true(strpos(ws_mail_status_html(array('status' => 'closed'), ''), 'bi-person-check') === false, 'nobody holds it');
}

function test_workspace_mail_state_offer()
{
	$item = array('conversation' => array('id' => 3, 'message_id' => 100, 'status' => 'open', 'assignee_id' => 7));

	pg_assert_same(array('status' => 'open', 'mine' => true, 'taken' => true, 'assignee' => 7, 'me' => 7), ws_mail_state_offer($item, 3, 7));
	pg_assert_same(array('status' => 'open', 'mine' => false, 'taken' => true, 'assignee' => 7, 'me' => 8), ws_mail_state_offer($item, 3, 8), 'somebody else holds it');
	pg_assert_same(null, ws_mail_state_offer($item, 0, 7), 'not the card, or the reader may not answer');
	pg_assert_same(null, ws_mail_state_offer(array('conversation' => null), 3, 7));
	pg_assert_same(false, ws_mail_state_offer(array('conversation' => array('status' => 'closed', 'assignee_id' => 0)), 3, 0)['mine'], 'nobody is not the viewer 0');
}

function test_workspace_mail_conversation_changes()
{
	$conversation = array('status' => 'open', 'assignee_id' => 0);

	pg_assert_same(array('ok' => true, 'error' => '', 'fields' => array('status' => 'closed', 'closed_at' => 1000, 'updated_at' => 1000)),
		ws_mail_conversation_changes($conversation, array('status' => 'closed'), 7, 1000), 'closed with its time');
	pg_assert_same(array('status' => 'open', 'closed_at' => 0, 'updated_at' => 1000),
		ws_mail_conversation_changes(array('status' => 'closed', 'assignee_id' => 0), array('status' => 'open'), 7, 1000)['fields'], 'opened again');
	pg_assert_same(array('assignee_id' => 7, 'updated_at' => 1000), ws_mail_conversation_changes($conversation, array('assign' => 'me'), 7, 1000)['fields'], 'taken');
	pg_assert_same(array('assignee_id' => 0, 'updated_at' => 1000), ws_mail_conversation_changes(array('status' => 'open', 'assignee_id' => 7), array('assign' => 'none'), 7, 1000)['fields'], 'let go');
	pg_assert_same('status', ws_mail_conversation_changes($conversation, array('status' => 'spam'), 7, 1000)['error']);
	pg_assert_same('nothing', ws_mail_conversation_changes($conversation, array('status' => 'open'), 7, 1000)['error'], 'the same status');
	pg_assert_same('nothing', ws_mail_conversation_changes(array('status' => 'open', 'assignee_id' => 7), array('assign' => 'me'), 7, 1000)['error'], 'already held');
	pg_assert_same('nothing', ws_mail_conversation_changes($conversation, array('assign' => 'someone'), 7, 1000)['error'], 'me, none or an id');
	pg_assert_same(array('assignee_id' => 9, 'updated_at' => 1000), ws_mail_conversation_changes($conversation, array('assign' => '9'), 7, 1000)['fields'], 'given to somebody else');
	pg_assert_same(array('assignee_id' => 9, 'updated_at' => 1000), ws_mail_conversation_changes(array('status' => 'open', 'assignee_id' => 7), array('assign' => '9'), 7, 1000)['fields'], 'handed on');
	pg_assert_same(array('assignee_id' => 7, 'updated_at' => 1000), ws_mail_conversation_changes($conversation, array('assign' => '7'), 7, 1000)['fields'], 'one\'s own id is me');
	pg_assert_same('nothing', ws_mail_conversation_changes(array('status' => 'open', 'assignee_id' => 9), array('assign' => '9'), 7, 1000)['error'], 'already theirs');
	pg_assert_same('nothing', ws_mail_conversation_changes($conversation, array('assign' => '0'), 7, 1000)['error'], 'no id 0');

	foreach (array('no_access', 'status', 'assignee', 'nothing') as $error) {
		pg_assert_true(ws_mail_conversation_error_text($error) !== '', $error);
	}
}

function test_workspace_mail_first_reply_target()
{
	$conversation = array('status' => 'open', 'first_in_at' => 10000, 'first_reply_at' => 0);

	pg_assert_same(10000 + 4 * 3600, ws_mail_first_reply_due($conversation, 4));
	pg_assert_same(0, ws_mail_first_reply_due($conversation, 0), 'no target');
	pg_assert_same(0, ws_mail_first_reply_due(array_merge($conversation, array('first_reply_at' => 12000)), 4), 'answered');
	pg_assert_same(0, ws_mail_first_reply_due(array_merge($conversation, array('status' => 'closed')), 4), 'closed');
	pg_assert_same(10000 + 3600, ws_mail_first_reply_due(array_merge($conversation, array('status' => 'waiting')), 1), 'set waiting without an answer still counts');
	pg_assert_false(ws_mail_first_reply_overdue($conversation, 1, 10000 + 3600), 'due right now is not late');
	pg_assert_true(ws_mail_first_reply_overdue($conversation, 1, 10000 + 3601));

	pg_assert_contains(h(lang('First answer overdue')), ws_mail_first_reply_html($conversation, 1, 20000));
	pg_assert_contains('bi-alarm', ws_mail_first_reply_html($conversation, 10, 20000), 'due later');
	pg_assert_true(strpos(ws_mail_first_reply_html($conversation, 10, 20000), 'theme-danger') === false);
	pg_assert_same('', ws_mail_first_reply_html($conversation, 0, 20000));
	pg_assert_contains(h(lang('First answer overdue')), ws_mail_status_html($conversation, '', 1, 20000), 'on the card\'s state');

	pg_assert_same(6, ws_mail_first_reply_hours(array('settings' => '{"first_reply_hours":6}')));
	pg_assert_same(WS_MAIL_FIRST_REPLY_MAX, ws_mail_first_reply_hours(array('settings' => '{"first_reply_hours":99999}')), 'bounded');
	pg_assert_same(0, ws_mail_first_reply_hours(array('settings' => '')));
	pg_assert_same(0, ws_mail_first_reply_hours(null));

	pg_assert_same(array('ok' => true, 'error' => '', 'hours' => 0), ws_mail_first_reply_input(array()));
	pg_assert_same(24, ws_mail_first_reply_input(array('first_reply_hours' => ' 24 '))['hours']);
	pg_assert_false(ws_mail_first_reply_input(array('first_reply_hours' => '1.5'))['ok'], 'whole hours');
	pg_assert_false(ws_mail_first_reply_input(array('first_reply_hours' => '-1'))['ok']);
	pg_assert_false(ws_mail_first_reply_input(array('first_reply_hours' => (string) (WS_MAIL_FIRST_REPLY_MAX + 1)))['ok']);
	pg_assert_true(ws_mail_first_reply_input(array('first_reply_hours' => array('1')))['ok'], 'a posted array is none');

	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 0, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'enabled' => 1, 'reply_via' => 'box',
		'settings' => '{"first_reply_hours":8}', 'auto_reply' => 0, 'auto_reply_text' => '');
	pg_assert_contains('name="first_reply_hours" id="ws_mail_3_first_reply_hours" min="0" max="720" value="8"', ws_mail_box_form('workspace_settings.php', $box, array()));
	pg_assert_contains('id="ws_mail_0_first_reply_hours" min="0" max="720" value=""', ws_mail_box_form('workspace_settings.php', null, array()), 'none on a new box');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("\$settings['first_reply_hours'] = \$first['hours'];", $source, 'kept in the box\'s settings');
}

function test_workspace_mail_auto_reply_due()
{
	$box = array('auto_reply' => 1, 'auto_reply_text' => 'Thanks, we got it.');

	pg_assert_true(ws_mail_auto_reply_due($box, 'pass', 0));
	pg_assert_true(ws_mail_auto_reply_due($box, 'none', 0), 'an unchecked sender is answered');
	pg_assert_false(ws_mail_auto_reply_due($box, 'fail', 0), 'never to a forged sender');
	pg_assert_false(ws_mail_auto_reply_due($box, 'pass', 1), 'once a day per address');
	pg_assert_false(ws_mail_auto_reply_due(array('auto_reply' => 0, 'auto_reply_text' => 'x'), 'pass', 0), 'off by default');
	pg_assert_false(ws_mail_auto_reply_due(array('auto_reply' => 1, 'auto_reply_text' => ' '), 'pass', 0), 'no text');
}

function test_workspace_mail_auto_reply_form()
{
	pg_assert_same(array('ok' => true, 'error' => '', 'auto_reply' => 1, 'auto_reply_text' => "Thanks\nwe got it"),
		ws_mail_auto_reply_input(array('auto_reply' => '1', 'auto_reply_text' => " Thanks\r\nwe got it ")));
	pg_assert_same(lang('Write the automatic answer, or switch it off.'), ws_mail_auto_reply_input(array('auto_reply' => '1', 'auto_reply_text' => ''))['error']);
	pg_assert_true(ws_mail_auto_reply_input(array())['ok'], 'off and empty');
	pg_assert_false(ws_mail_auto_reply_input(array('auto_reply_text' => str_repeat('a', WS_MAIL_AUTO_REPLY_MAX + 1)))['ok'], 'too long');
	pg_assert_same('', ws_mail_auto_reply_input(array('auto_reply_text' => array('x')))['auto_reply_text'], 'a posted array is no text');

	$new = ws_mail_box_form('workspace_settings.php', null, array());
	pg_assert_contains('name="auto_reply" value="1" id="ws_mail_0_auto_reply">', $new, 'off on a new box');

	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 0, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'enabled' => 1, 'reply_via' => 'box',
		'settings' => '', 'auto_reply' => 1, 'auto_reply_text' => 'We got <it>');
	$html = ws_mail_box_form('workspace_settings.php', $box, array());
	pg_assert_contains('name="auto_reply" value="1" id="ws_mail_3_auto_reply" checked>', $html);
	pg_assert_contains('>We got &lt;it&gt;</textarea>', $html, 'the kept text, as text');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("auto_reply = '\" . (int) \$auto['auto_reply'] . \"', auto_reply_text = '\" . e(\$auto['auto_reply_text']) . \"',", $source, 'kept in the box\'s columns');
}

function test_workspace_mail_auto_reply_is_drawn_as_such()
{
	$item = array('id' => 9, 'direction' => 'out', 'user_id' => 0, 'from_email' => 'support@shop.test', 'from_name' => 'Support', 'to_list' => 'ayse@example.com',
		'subject' => 'Re: Hi', 'body_text' => 'We got it.', 'auth' => 'pass', 'refused_files' => '', 'conversation' => array('id' => 3, 'message_id' => 100, 'status' => 'open', 'assignee_id' => 0));
	$html = ws_mail_card_html(array('id' => 101, 'file_id' => 0, 'body' => 'We got it.'), $item);

	pg_assert_true(ws_mail_item_is_auto_reply($item));
	pg_assert_false(ws_mail_item_is_auto_reply(array_merge($item, array('user_id' => 4))), 'a person\'s answer');
	pg_assert_false(ws_mail_item_is_auto_reply(array_merge($item, array('direction' => 'in'))), 'the customer\'s mail');
	pg_assert_contains(h(lang('Automatic answer')), $html);
	pg_assert_true(strpos($html, h(lang('Sent by e-mail'))) === false);
	pg_assert_same('Support', ws_mail_sender($item)['name'], 'drawn under the box\'s name');
}

function test_workspace_mail_status_wiring()
{
	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	$ingest = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail.php');
	$reply = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_reply.php');
	$status = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_status.php');
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$messages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js');

	pg_assert_contains("\$mail->addCustomHeader('Auto-Submitted', 'auto-replied');", $mail, 'email() marks an automatic answer');
	pg_assert_contains("'auto_submitted'     => true,", $status);
	pg_assert_contains("'queue'              => true,", $status, 'a reader never waits on a server');
	pg_assert_contains("if (\$opened && function_exists('ws_mail_auto_reply'))", $ingest, 'only a new conversation');
	pg_assert_contains("status = 'waiting', closed_at = 0,", $reply, 'an answer waits for the customer');
	pg_assert_contains("case 'ws_mail_conversation':", $actions);
	pg_assert_contains("'mail_state'  => (\$mail && !\$deleted && function_exists('ws_mail_state_offer'))", $messages);
	pg_assert_contains("h.api('ws_mail_conversation', data)", $script);

	foreach (array_keys(ws_mail_status_js_strings()) as $key) {
		pg_assert_true((strpos($script, "'" . $key . "'") !== false) || (strpos($key, 'mail_status_') === 0), $key . ' is used');
	}

	pg_assert_contains("t('mail_status_' + key)", $script, 'the status words by key');

	// Given to somebody, and the customer writing again: the holder hears.
	pg_assert_contains("ws_notify(\$holder, 'mail_assigned',", $status);
	pg_assert_contains("ws_notify((int) \$conversation['assignee_id'], 'mail_customer',", $ingest);
	pg_assert_contains("ws_event('workspace.mail.received', array(", $ingest);
	pg_assert_true(strpos(substr($ingest, strpos($ingest, "ws_event('workspace.mail.received'"), 700), 'body') === false, 'no mail text in the event');
	pg_assert_contains("change({ assign: hold.value === '' ? 'none' : hold.value }, hold);", $script);
	pg_assert_contains("case 'mail_customer':", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php'));
	pg_assert_contains("'mail_customer') AND message_id <=", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channels.php'), 'read with the channel');
	pg_assert_same(array('channel', 5), ws_events_record_of('workspace.mail.received', array('channel_id' => 5)));
	pg_assert_true(isset(ws_flow_event_outputs()['workspace.mail.received']));
}
