<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for sending Workspace mail through the mail queue (mail_send.php):
 * the checks before anything is written, the outcome handler's rules (sent
 * is final, an older queue row leaves the item alone, the same outcome twice
 * changes nothing, a row finished before its id was written still counts),
 * Send again, the card's send state, email()'s Cc, the update window and the
 * wiring. The writes need a database and are checked in their source.
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

function workspace_mail_send_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function workspace_mail_send_test_mail($changes = array())
{
	return array_merge(array('to' => 'ali@example.com', 'cc' => '', 'bcc' => '', 'subject' => 'Your order', 'text' => 'Hello', 'files' => array()), $changes);
}

function test_workspace_mail_send_addresses()
{
	pg_assert_same(array('a@x.test', 'b@x.test'), ws_mail_send_addresses('A@x.test, b@x.test; a@x.test'), 'text, lower case, each once');
	pg_assert_same(array('a@x.test'), ws_mail_send_addresses(array(' <a@x.test> ', '')), 'a list, brackets dropped');
	pg_assert_same(array(), ws_mail_send_addresses(''), 'nothing');
}

function test_workspace_mail_send_input_refusals()
{
	$ok = ws_mail_send_input(workspace_mail_send_test_mail(array('cc' => 'ALI@example.com, veli@example.com', 'bcc' => 'veli@example.com, can@example.com')));
	pg_assert_true($ok['ok'], 'a plain mail');
	pg_assert_same(array('veli@example.com'), $ok['cc'], 'an address of To is not copied again');
	pg_assert_same(array('can@example.com'), $ok['bcc'], 'nor one of Cc');

	$many = array();

	for ($i = 1; $i <= 21; $i++) {
		$many[] = 'p' . $i . '@example.com';
	}

	pg_assert_same('too_many', ws_mail_send_input(workspace_mail_send_test_mail(array('to' => array_slice($many, 0, 10), 'cc' => array_slice($many, 10, 6), 'bcc' => array_slice($many, 16))))['error'], '21 addresses');
	pg_assert_true(ws_mail_send_input(workspace_mail_send_test_mail(array('to' => array_slice($many, 0, 20))))['ok'], '20 addresses');
	pg_assert_same('no_to', ws_mail_send_input(workspace_mail_send_test_mail(array('to' => '', 'cc' => 'a@example.com')))['error'], 'no To');

	$bad = ws_mail_send_input(workspace_mail_send_test_mail(array('cc' => 'not-an-address')));
	pg_assert_same('bad_address', $bad['error'], 'an invalid Cc');
	pg_assert_same('not-an-address', $bad['detail'], 'named');

	pg_assert_same('no_subject', ws_mail_send_input(workspace_mail_send_test_mail(array('subject' => "  \n ")))['error'], 'empty subject');
	pg_assert_same('empty', ws_mail_send_input(workspace_mail_send_test_mail(array('text' => ' ')))['error'], 'empty text');
	pg_assert_same('too_long', ws_mail_send_input(workspace_mail_send_test_mail(array('text' => str_repeat('a', WS_MAIL_REPLY_MAX + 1))))['error'], 'too long');
	pg_assert_true(ws_mail_send_input(workspace_mail_send_test_mail(array('text' => str_repeat('ş', WS_MAIL_REPLY_MAX))))['ok'], 'the longest text, in characters');
}

function test_workspace_mail_send_input_files()
{
	$file = array('name' => 'note.txt', 'bytes' => 'plain words');

	$taken = ws_mail_send_input(workspace_mail_send_test_mail(array('files' => array($file))));
	pg_assert_true($taken['ok'], 'one file');
	pg_assert_same('txt', $taken['files'][0]['extension'], 'its extension');

	pg_assert_same('too_many_files', ws_mail_send_input(workspace_mail_send_test_mail(array('files' => array_fill(0, 11, $file))))['error'], '11 files');
	pg_assert_true(ws_mail_send_input(workspace_mail_send_test_mail(array('files' => array_fill(0, 10, $file))))['ok'], '10 files');

	$refused = ws_mail_send_input(workspace_mail_send_test_mail(array('files' => array(array('name' => 'shell.php', 'bytes' => '<?php echo 1;')))));
	pg_assert_same('file_refused', $refused['error'], 'a refused type');
	pg_assert_contains('shell.php', $refused['detail'], 'named with the reason');
	pg_assert_same('file_refused', ws_mail_send_input(workspace_mail_send_test_mail(array('files' => array(array('name' => 'empty.txt', 'bytes' => '')))))['error'], 'no bytes');
}

function test_workspace_mail_send_limits()
{
	$blocked = function ($address) {
		return $address === 'gone@example.com';
	};

	pg_assert_same(array('ok' => true, 'error' => '', 'detail' => ''), ws_mail_send_limits(array('a@example.com'), $blocked, 29), 'within the hour');
	pg_assert_same('hourly', ws_mail_send_limits(array('a@example.com'), $blocked, 30)['error'], 'the 31st mail of the hour');

	$cc = ws_mail_send_limits(array('a@example.com', 'gone@example.com'), $blocked, 0);
	pg_assert_same('blocked', $cc['error'], 'a Cc on the bounce list');
	pg_assert_same('gone@example.com', $cc['detail'], 'refused by name');
	pg_assert_contains('gone@example.com', ws_mail_send_error_text('blocked', 'gone@example.com'), 'the words name it');
}

function test_workspace_mail_send_subject_and_ref()
{
	pg_assert_same('Your order [#T12]', ws_mail_send_subject('Your  order', 12), 'the tag, no "Re:"');
	pg_assert_same('Your order [#T12]', ws_mail_send_subject('Your order [#T12]', 12), 'not twice');
	pg_assert_same('12:0', ws_mail_send_ref(12, 0));
	pg_assert_same(array('item' => 12, 'previous' => 7), ws_mail_send_ref_parse('12:7'));
	pg_assert_same(array('item' => 0, 'previous' => 0), ws_mail_send_ref_parse('12'), 'not a reference');
}

function test_workspace_mail_send_outcome_handler_is_registered()
{
	$handlers = pg_mail_outcome_handlers();

	pg_assert_true(isset($handlers[WS_MAIL_SEND_HANDLER]), 'the Workspace entry');
	pg_assert_same(array('function' => 'ws_mail_send_outcome', 'file' => 'includes/workspace/mail_send.php'), $handlers[WS_MAIL_SEND_HANDLER]);
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $handlers[WS_MAIL_SEND_HANDLER]['file']), 'its file');
	pg_assert_true(function_exists('ws_mail_send_outcome'), 'its function');

	// It runs with no person behind it and loads the Workspace itself.
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');", workspace_mail_send_test_source('includes/workspace/mail_send.php'), 'loads what it needs');
}

function test_workspace_mail_send_outcome_change_rules()
{
	$queued = array('id' => 5, 'direction' => 'out', 'send_state' => 'queued', 'outbox_id' => 40);

	$sent = ws_mail_send_outcome_change($queued, 40, 0, 'sent', '', 1000);
	pg_assert_same(array('send_state' => 'sent', 'send_error' => '', 'outbox_id' => 40, 'sent_at' => 1000), $sent['set'], 'sent');
	pg_assert_true($sent['copy'] && !$sent['notify'], 'copied, nobody told');

	$failed = ws_mail_send_outcome_change($queued, 40, 0, 'failed', 'Mailbox full', 1000);
	pg_assert_same(array('send_state' => 'failed', 'send_error' => 'Mailbox full', 'outbox_id' => 40), $failed['set'], 'failed with its reason');
	pg_assert_true($failed['notify'] && !$failed['copy'], 'the writer is told');

	pg_assert_same('Every recipient is on the bounce list.', ws_mail_send_outcome_change($queued, 40, 0, 'suppressed', '', 1000)['set']['send_error'], 'suppressed reads as failed');

	// The same outcome twice changes nothing.
	pg_assert_same(null, ws_mail_send_outcome_change(array_merge($queued, $failed['set']), 40, 0, 'failed', 'Mailbox full', 1000), 'failed twice');
	pg_assert_same(null, ws_mail_send_outcome_change(array_merge($queued, $sent['set']), 40, 0, 'sent', '', 1001), 'sent twice');

	// Retried on the Mail queue screen: sent after failed.
	pg_assert_same('sent', ws_mail_send_outcome_change(array_merge($queued, $failed['set']), 40, 0, 'sent', '', 1002)['set']['send_state'], 'sent after failed');

	// Sent is final.
	pg_assert_same(null, ws_mail_send_outcome_change(array_merge($queued, $sent['set']), 40, 0, 'failed', 'late', 1003), 'no failed after sent');

	pg_assert_same(null, ws_mail_send_outcome_change(array('direction' => 'out', 'send_state' => '', 'outbox_id' => 0), 0, 0, 'failed', 'x', 1), 'an answer written before the state was kept');
	pg_assert_same(null, ws_mail_send_outcome_change(array('direction' => 'in', 'send_state' => 'queued', 'outbox_id' => 0), 0, 0, 'failed', 'x', 1), 'a mail that came in');
	pg_assert_same(null, ws_mail_send_outcome_change($queued, 40, 0, 'queued', '', 1), 'no outcome');
}

function test_workspace_mail_send_outcome_older_row_and_race()
{
	// Sent again: the new row 41 is written on the item; the old row 40,
	// retried on the Mail queue screen, fails again.
	$resent = array('id' => 5, 'direction' => 'out', 'send_state' => 'queued', 'outbox_id' => 41);
	pg_assert_same(null, ws_mail_send_outcome_change($resent, 40, 0, 'failed', 'old', 1000), 'an older row\'s failure leaves the item alone');
	pg_assert_same(null, ws_mail_send_outcome_change(array_merge($resent, array('send_state' => 'failed', 'outbox_id' => 41)), 40, 0, 'failed', 'old', 1000), 'nor the failure it shows');

	// An older row that did leave: the mail reached them after all.
	pg_assert_same('sent', ws_mail_send_outcome_change($resent, 40, 0, 'sent', '', 1000)['set']['send_state'], 'an older row\'s sent counts');

	// The queue finished the row before its id was written on the item.
	$first = array('id' => 5, 'direction' => 'out', 'send_state' => 'queued', 'outbox_id' => 0);
	$change = ws_mail_send_outcome_change($first, 55, 0, 'failed', 'refused', 1000);
	pg_assert_same(55, $change['set']['outbox_id'], 'the first attempt\'s row counts and is written');

	// The same while sent again: the item still names the row before.
	$again = array('id' => 5, 'direction' => 'out', 'send_state' => 'queued', 'outbox_id' => 40);
	pg_assert_same(56, ws_mail_send_outcome_change($again, 56, 40, 'failed', 'refused', 1000)['set']['outbox_id'], 'the row queued after the one named');
	pg_assert_same(null, ws_mail_send_outcome_change($again, 56, 39, 'failed', 'refused', 1000), 'not a row queued after another');

	// The id is written only over the attempt it follows, so a handler that
	// ran first is not undone.
	pg_assert_contains("AND send_state = 'queued' AND outbox_id = '\" . (int) \$previous . \"'\")", workspace_mail_send_test_source('includes/workspace/mail_send.php'), 'the id written after the enqueue');
}

function test_workspace_mail_send_state_and_badge()
{
	pg_assert_same('sent', ws_mail_send_state(array('direction' => 'out')), 'no column yet: sent');
	pg_assert_same('sent', ws_mail_send_state(array('direction' => 'out', 'send_state' => '')), 'an earlier answer');
	pg_assert_same('queued', ws_mail_send_state(array('direction' => 'out', 'send_state' => 'queued')));
	pg_assert_same('failed', ws_mail_send_state(array('direction' => 'out', 'send_state' => 'failed')));
	pg_assert_same('', ws_mail_send_state(array('direction' => 'in', 'send_state' => '')), 'a mail that came in');

	pg_assert_contains('Sent by e-mail', ws_mail_send_badge_html(array('direction' => 'out')), 'as before');
	pg_assert_contains('Waiting to be sent', ws_mail_send_badge_html(array('direction' => 'out', 'send_state' => 'queued')));

	$failed = ws_mail_send_badge_html(array('direction' => 'out', 'send_state' => 'failed', 'send_error' => '<b>550</b> refused'));
	pg_assert_contains('Not sent', $failed);
	pg_assert_contains('&lt;b&gt;550&lt;/b&gt; refused', $failed, 'the reason, escaped');

	pg_assert_contains('ws_mail_send_badge_html($item)', workspace_mail_send_test_source('includes/workspace/mail.php'), 'the card draws it');
}

function test_workspace_mail_resend_offer()
{
	$member = array('id' => 3, 'member' => true);
	$failed = array('id' => 9, 'direction' => 'out', 'send_state' => 'failed');

	pg_assert_same(9, ws_mail_resend_offer($failed, true, $member), 'a failed mail');
	pg_assert_same(0, ws_mail_resend_offer(array_merge($failed, array('send_state' => 'queued')), true, $member), 'not while it waits');
	pg_assert_same(0, ws_mail_resend_offer(array_merge($failed, array('send_state' => 'sent')), true, $member), 'not once sent');
	pg_assert_same(0, ws_mail_resend_offer(array_merge($failed, array('direction' => 'in')), true, $member), 'not a mail that came in');
	pg_assert_same(0, ws_mail_resend_offer($failed, false, $member), 'not for a reader who may not post');
	pg_assert_same(0, ws_mail_resend_offer($failed, true, array('id' => 4, 'member' => false)), 'never outside the team');

	$answer = array_merge($failed, array('message_id' => 50));

	pg_assert_same(9, ws_mail_resend_offer($answer, true, $member, array('id' => 50)), 'on the item\'s own message');
	pg_assert_same(0, ws_mail_resend_offer($answer, true, $member, array('id' => 51)), 'not on each of its files');

	$source = workspace_mail_send_test_source('includes/workspace/mail_send.php');
	pg_assert_contains("WHERE id = '\" . (int) \$item['id'] . \"' AND send_state = 'failed' AND outbox_id", $source, 'one press counts: only a failed item is taken');
	pg_assert_contains("if (mysqli_affected_rows(db::\$con) <= 0) {\n        return \$refuse('not_failed');", $source, 'a second press is refused');
}

// Before 11.0.185 has run nothing is sent or written, and the handler
// returns at once (the test run has no database, so the columns are absent).
function test_workspace_mail_send_update_window()
{
	$viewer = array('id' => 3, 'member' => true, 'role' => 2);

	pg_assert_false(ws_mail_send_ready(), 'no columns, not ready');
	pg_assert_same('not_ready', ws_mail_send($viewer, workspace_mail_send_test_mail(array('box_id' => 1, 'channel_id' => 1)))['error'], 'a new mail');
	pg_assert_same('not_ready', ws_mail_resend($viewer, 9)['error'], 'Send again');
	pg_assert_same(null, ws_mail_send_outcome(41, 'failed', '9:0', 'x', ''), 'the handler returns quietly');
	pg_assert_same('The database has not been upgraded yet.', ws_mail_send_error_text('not_ready'));

	pg_assert_contains("pg_schema_has('ws_mail_items', 'send_state')", workspace_mail_send_test_source('includes/workspace/mail_send.php'), 'the column is asked for');
	pg_assert_contains("if (!function_exists('ws_mail_resend') || !ws_mail_send_ready()) {", workspace_mail_send_test_source('includes/workspace/actions.php'), 'the action asks too');
}

function test_email_carries_cc()
{
	$source = workspace_mail_send_test_source('includes/fn/mail.php');

	pg_assert_contains("\$cc = isset(\$properties['cc']) ? \$properties['cc'] : '';", $source, 'read');
	pg_assert_contains('$mail->addCC($ccAddr);', $source, 'a list');
	pg_assert_contains('$mail->addCC($cc);', $source, 'one address');
	pg_assert_contains("pg_log_lang(array('string' => 'CC'))", $source, 'in the log of a mail that could not be sent');
	pg_assert_contains("'cc'          => array_values(\$mail->getCcAddresses()),", workspace_mail_send_test_source('includes/fn/mail_transport.php'), 'a provider\'s API reads it');
}

// An answer from any recipient finds the conversation by its references;
// the subject tag alone only from the customer, the first To.
function test_workspace_mail_send_answers_find_the_conversation()
{
	$message_id = ws_mail_reply_message_id(12, 'ab12', 'shop.test');
	$parsed = array('in_reply_to' => array(), 'references' => array('x@client.test', $message_id));

	pg_assert_true(in_array($message_id, ws_mail_thread_ids($parsed), true), 'the sent mail\'s id is looked up');
	pg_assert_same(ws_mail_hash($message_id, ''), ws_mail_hash(ws_mail_thread_ids(array('in_reply_to' => array($message_id)))[0], ''), 'by the hash the item keeps');

	$find = workspace_mail_send_test_source('includes/workspace/mail.php');
	pg_assert_contains("AND rfc_message_hash IN (\" . implode(',', \$hashes) . \") AND conversation_id > 0", $find, 'references: anyone');
	pg_assert_contains("AND customer_email = '\" . e(\$from_email) . \"'\");", $find, 'the tag: the customer only');

	$send = workspace_mail_send_test_source('includes/workspace/mail_send.php');
	pg_assert_contains("e(\$input['to'][0])", $send, 'the customer is the first To');
	pg_assert_contains("'waiting', '\" . (int) \$viewer['id'] . \"'", $send, 'waiting, held by the writer');
	pg_assert_contains("e(ws_mail_hash(\$message_id, ''))", $send, 'the item keeps the hash');
}

function test_workspace_mail_send_wiring()
{
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_send.php');", workspace_mail_send_test_source('includes/workspace/bootstrap.php'), 'loaded with the Workspace');
	pg_assert_contains("case 'ws_mail_resend':", workspace_mail_send_test_source('includes/workspace/actions.php'), 'the action');
	pg_assert_contains("'mail_resend' => ", workspace_mail_send_test_source('includes/workspace/messages.php'), 'the payload');
	pg_assert_contains("case 'mail_unsent':", workspace_mail_send_test_source('includes/workspace/notify.php'), 'the inbox line');
	pg_assert_contains('mail_unsent (an e-mail the owner wrote in the workspace could not be sent', workspace_mail_send_test_source('includes/workspace/api.php'), 'named for applications');
	pg_assert_contains('ws_mail_send_js_strings()', workspace_mail_send_test_source('includes/workspace/screen.php'), 'the texts');

	$js = workspace_mail_send_test_source('assets/js/workspace_mail.js');
	pg_assert_contains('if (message.mail_resend) {', $js, 'the button');
	pg_assert_contains("h.api('ws_mail_resend', { item_id: message.mail_resend })", $js, 'its request');

	$properties = workspace_mail_send_test_source('includes/workspace/mail_send.php');
	pg_assert_contains("'type'               => 'workspace',", $properties, 'labelled on the Mail queue screen');
	pg_assert_contains("'secret'             => true,", $properties, 'the text stays out of notices');
	pg_assert_contains("'notify_sender'      => false,", $properties, 'the writer hears in the Workspace');
	pg_assert_contains("'on_outcome' => WS_MAIL_SEND_HANDLER, 'outcome_ref' => \$ref", $properties, 'queued with the handler');
	pg_assert_true(strpos($properties, "'queue'") === false, 'never email()\'s queue, which calls no handler');

	foreach (array_keys(ws_mail_send_js_strings()) as $key) {
		pg_assert_contains("h.t('" . $key . "')", $js, $key . ' is used');
	}
}

function test_workspace_mail_send_headers_of_an_answer()
{
	pg_assert_same(array('Message-ID' => '<pg.c4.ab@shop.test>'), ws_mail_send_headers(array('rfc_message_id' => 'pg.c4.ab@shop.test', 'in_reply_to' => '', 'reference_ids' => '')),
		'a new mail answers nothing');

	$item = array('rfc_message_id' => 'pg.c4.cd@shop.test', 'in_reply_to' => 'm2@client.test', 'reference_ids' => 'm1@client.test m2@client.test');

	pg_assert_same(ws_mail_reply_headers(array('rfc_message_id' => 'm2@client.test', 'reference_ids' => 'm1@client.test'), 'pg.c4.cd@shop.test'),
		ws_mail_send_headers($item), 'an answer keeps the threading it was written with');
	pg_assert_same('<m1@client.test> <m2@client.test>', ws_mail_send_headers($item)['References'], 'the answered mail once, last');
}

// The answer goes through ws_mail_send() with its conversation: the
// conversation's box, channel and customer, the answered mail's threading,
// the conversation waiting, the queue.
function test_workspace_mail_send_answer_wiring()
{
	$source = workspace_mail_send_test_source('includes/workspace/mail_send.php');

	pg_assert_contains("\$target = ws_mail_reply_target(\$viewer, (int) \$mail['conversation_id']);", $source, 'the channel\'s writers, an open box, a customer');
	pg_assert_contains("\$mail['to'] = (string) \$conversation['customer_email'];", $source, 'to the customer, whatever the form says');
	pg_assert_contains("if (\$check['conversation']) {\n        return ws_mail_send_answer(\$viewer, \$check, \$writer, \$now);", $source, 'no new conversation');
	pg_assert_contains("'\" . e(\$answered ? (string) \$answered['rfc_message_id'] : '') . \"', '\" . e(\$references) . \"',", $source, 'the threading kept on the item');
	pg_assert_contains("status = 'waiting', closed_at = 0,", $source, 'answered: waiting for the customer');
	pg_assert_contains("\$state = is_array(\$item) ? ws_mail_send_dispatch(\$item, 0) : 'failed';", $source);
	pg_assert_contains("'body'               => ws_mail_answer_body(", $source, 'quoted when it leaves');
	pg_assert_same('box_off', (ws_mail_send_error_text('box_off') === ws_mail_reply_error_text('no_box')) ? 'box_off' : '', 'the conversation\'s mailbox is off');
	pg_assert_same(ws_mail_reply_error_text('no_customer'), ws_mail_send_error_text('no_customer'));
}
