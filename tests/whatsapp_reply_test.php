<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for answering a WhatsApp conversation from the Workspace: the
 * customer's 24 hours, the queue row of a text answer, the reference back to
 * the item, Meta's text request, the card's offer and badge, and the wiring
 * of the queue, the actions and the drawer. No database and no call to Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_reply.php');
require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_whatsapp_reply_window()
{
	pg_assert_same(86400, pg_whatsapp_window_left(1000, 1000), 'just written');
	pg_assert_same(3600, pg_whatsapp_window_left(1000, 1000 + 82800), 'an hour left');
	pg_assert_same(0, pg_whatsapp_window_left(1000, 1000 + 86400), 'closed at 24 hours');
	pg_assert_same(0, pg_whatsapp_window_left(0, 1000), 'the customer never wrote');

	pg_assert_same(lang(array('string' => 'WhatsApp takes a written answer for {var:1} h {var:2} min more.', 'vars' => array(5, 30))), pg_whatsapp_window_text(5 * 3600 + 30 * 60), 'hours and minutes');
	pg_assert_same(lang(array('string' => 'WhatsApp takes a written answer for {var:1} min more.', 'vars' => array(1))), pg_whatsapp_window_text(20), 'at least a minute');
	pg_assert_contains('24', pg_whatsapp_window_text(0), 'closed');
}

function test_whatsapp_reply_ref()
{
	pg_assert_same('wsr:12:0', pg_whatsapp_reply_ref(12, 0), 'first attempt');
	pg_assert_same(array('item' => 12, 'previous' => 7), pg_whatsapp_reply_ref_parse('wsr:12:7'), 'read back');
	pg_assert_same(array('item' => 0, 'previous' => 0), pg_whatsapp_reply_ref_parse('order:12:received'), 'another kind\'s reference');
	pg_assert_same(array('item' => 0, 'previous' => 0), pg_whatsapp_reply_ref_parse("wsr:12:7\n"), 'nothing after it');
}

function test_whatsapp_reply_row()
{
	$row = pg_whatsapp_reply_row('905321234567', "  Merhaba,\r\nkargonuz yolda. ", array('reference' => 'wsr:5:0', 'contact_id' => 9, 'window_ends' => 5000), 1000);

	pg_assert_same(array(
		'created_at' => 1000, 'send_after' => 1000, 'expires_at' => 5000, 'status' => 'queued', 'kind' => 'reply', 'recipient' => '905321234567',
		'reference' => 'wsr:5:0', 'contact_id' => 9, 'template_id' => 0, 'template_name' => '', 'template_language' => '', 'category' => 'SERVICE',
		'payload' => '{"type":"text","text":"Merhaba,\nkargonuz yolda."}',
	), $row, 'a text answer, expiring with the window');

	pg_assert_same(array('error' => 'empty'), pg_whatsapp_reply_row('905321234567', "  \n ", array('window_ends' => 5000), 1000), 'empty');
	pg_assert_same(array('error' => 'too_long'), pg_whatsapp_reply_row('905321234567', str_repeat('ş', 4097), array('window_ends' => 5000), 1000), 'too long');
	pg_assert_same('905321234567', pg_whatsapp_reply_row('905321234567', str_repeat('ş', 4096), array('window_ends' => 5000), 1000)['recipient'], 'at the limit');
	pg_assert_same(array('error' => 'window'), pg_whatsapp_reply_row('905321234567', 'x', array('window_ends' => 1000), 1000), 'window closed');
	pg_assert_same(array('error' => 'number'), pg_whatsapp_reply_row('', 'x', array('window_ends' => 5000), 1000), 'no number');
}

function test_whatsapp_reply_text_request()
{
	$app = array('app_id' => '1', 'app_secret' => 'secret', 'token' => 'token', 'phone_number_id' => '106540352242922', 'account_id' => '2');
	$request = pg_whatsapp_cloud_text_request($app, '905321234567', 'Merhaba');

	pg_assert_contains('/106540352242922/messages?', $request['url'], 'the number\'s messages');
	pg_assert_contains('appsecret_proof=', $request['url'], 'proof');
	pg_assert_same(array('messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => '+905321234567', 'type' => 'text',
		'text' => array('preview_url' => false, 'body' => 'Merhaba')), json_decode($request['body'], true), 'free text with the plus');
}

function test_whatsapp_reply_card()
{
	$conversation = array('id' => 3, 'message_id' => 40, 'last_in_at' => time() - 600);
	$item = array('id' => 8, 'message_id' => 40, 'conversation' => $conversation, 'transport' => 'whatsapp');
	$row = array('id' => 40);

	pg_assert_same(3, ws_mail_phone_reply_offer($row, $item, true), 'on the card, for a writer');
	pg_assert_same(0, ws_mail_phone_reply_offer($row, $item, false), 'not for a reader');
	pg_assert_same(0, ws_mail_phone_reply_offer(array('id' => 41), $item, true), 'not under an answer');
	pg_assert_same(0, ws_mail_phone_reply_offer($row, array('transport' => 'imap') + $item, true), 'not on a mail');
	pg_assert_true(ws_mail_phone_window($conversation, time()) > 85000, 'the window left');

	$out = array('direction' => 'out', 'send_state' => 'sent', 'transport' => 'whatsapp');
	pg_assert_contains(lang('Sent by WhatsApp'), ws_mail_send_badge_html($out), 'sent by WhatsApp');
	pg_assert_contains(lang('Sent by e-mail'), ws_mail_send_badge_html(array('transport' => 'imap') + $out), 'a mail as before');
	pg_assert_contains(lang('Waiting to be sent'), ws_mail_send_badge_html(array('send_state' => 'queued') + $out), 'waiting');
}

function test_whatsapp_reply_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	$queue = $read('includes/fn/whatsapp.php');
	pg_assert_contains("pg_whatsapp_cloud_send_text(\$app, \$row['recipient'], (string) (\$payload['text'] ?? ''))", $queue, 'a text payload goes as text');
	pg_assert_contains("pg_whatsapp_reply_outcome(\$id, (string) \$row['reference'], \$outcome['status'], (string) \$outcome['last_error']);", $queue, 'an answer learns how it went');

	pg_assert_contains("case 'ws_whatsapp_reply':", $read('includes/workspace/actions.php'), 'the action');
	pg_assert_contains("'ws_whatsapp_reply_form', 'ws_whatsapp_reply',", $read('includes/panel/actions.php'), 'registered, with the token');
	pg_assert_contains("return ws_mail_phone_resend(\$viewer, \$item);", $read('includes/workspace/mail_send.php'), 'Send again goes back to the WhatsApp queue');
	pg_assert_contains("'whatsapp_reply'  => (", $read('includes/workspace/messages.php'), 'the payload offers it');
	pg_assert_contains('ws_mail_phone_js_strings());', $read('includes/workspace/screen.php'), 'the drawer\'s texts');

	$script = $read('assets/js/workspace_mail.js');
	pg_assert_contains("h.api('ws_whatsapp_reply_form', { conversation_id: message.whatsapp_reply })", $script, 'whom and how long, asked first');
	pg_assert_contains("data.conversation_id = message.mail_reply || message.whatsapp_reply;", $script, 'status and holder on a WhatsApp card');
	pg_assert_contains('(message.mail_reply || message.mail_resend || message.whatsapp_reply) && window.PGWsMail', $read('assets/js/workspace.js'), 'drawn under a WhatsApp card');
}
