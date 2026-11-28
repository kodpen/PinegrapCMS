<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a site chat made into a support request from the panel's chat
 * window (includes/fn/chat_requests.php): who may make one, whose words are
 * the customer's, the subject and text built from the chat, the Message-ID
 * that keeps a second click from opening another request, and the wiring.
 * No database and no mailbox.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/chat_requests.php');

function test_chat_requests_allowed()
{
	$chat = array('channel' => 'site', 'party_email' => 'ayse@example.com');

	pg_assert_true(pg_chat_request_allowed($chat, 'target', 2), 'the operator, a manager');
	pg_assert_true(pg_chat_request_allowed($chat, 'target', 0), 'an administrator');
	pg_assert_false(pg_chat_request_allowed($chat, 'target', 3), 'role 3 makes none');
	pg_assert_false(pg_chat_request_allowed($chat, 'initiator', 2), 'the member\'s own side');
	pg_assert_false(pg_chat_request_allowed($chat, false, 0), 'no side');
	pg_assert_false(pg_chat_request_allowed(array('channel' => 'backend', 'party_email' => 'ayse@example.com'), 'target', 0), 'a team chat');
	pg_assert_false(pg_chat_request_allowed(array('channel' => 'site', 'party_email' => ''), 'target', 0), 'no address');
	pg_assert_false(pg_chat_request_allowed(array('channel' => 'site', 'party_email' => 'not an address'), 'target', 0), 'not an address');
}

function test_chat_requests_from_customer()
{
	pg_assert_true(pg_chat_request_from_customer(array('sender_kind' => 'visitor', 'sender_user_id' => 0), 0), 'a visitor');
	pg_assert_true(pg_chat_request_from_customer(array('sender_kind' => 'user', 'sender_user_id' => 7), 7), 'the member who started it');
	pg_assert_false(pg_chat_request_from_customer(array('sender_kind' => 'user', 'sender_user_id' => 2), 7), 'the operator');
	pg_assert_false(pg_chat_request_from_customer(array('sender_kind' => 'user', 'sender_user_id' => 2), 0), 'the operator of a guest\'s chat');
	pg_assert_false(pg_chat_request_from_customer(array('sender_kind' => 'system', 'sender_user_id' => 0), 0), 'a system line');
}

function test_chat_requests_subject()
{
	pg_assert_same('Live chat #9', pg_chat_request_subject("  \n ", 9, 'Live chat #9'));
	pg_assert_same('My order has not arrived', pg_chat_request_subject("My order\n has  not arrived", 9, 'Live chat #9'));

	$long = pg_chat_request_subject(str_repeat('word ', 40), 9, 'Live chat #9');
	pg_assert_true(mb_strlen($long) <= PG_CHAT_REQUEST_SUBJECT_MAX, 'cut to the limit');
	pg_assert_same('…', mb_substr($long, -1));
	pg_assert_same('word word', mb_substr($long, 0, 9), 'cut at a word');
	pg_assert_false(strpos($long, ' …') !== false, 'no space before the ellipsis');
}

function test_chat_requests_body()
{
	$body = pg_chat_request_body('Live chat #9, started 2026-11-27 10:00.', array(
		array('who' => 'Ayşe', 'when' => '2026-11-27 10:00', 'text' => "Hello\r\nmy order?", 'file' => ''),
		array('who' => 'Ali', 'when' => '2026-11-27 10:01', 'text' => '', 'file' => 'invoice.pdf'),
		array('who' => 'Ayşe', 'when' => '2026-11-27 10:02', 'text' => '  ', 'file' => ''),
	), 'Attachment');

	pg_assert_same("Live chat #9, started 2026-11-27 10:00.\n\nAyşe (2026-11-27 10:00):\nHello\nmy order?\n\nAli (2026-11-27 10:01):\n[Attachment: invoice.pdf]\n", $body);
}

function test_chat_requests_mail()
{
	pg_assert_same('pg-chat-9-41@example.com', pg_chat_request_message_id(9, 41, 'example.com'));

	// The mail the box's intake reads back: the customer as the sender, the
	// box as the receiver, the chat's text as the body.
	$raw = pg_support_form_mail_raw(array(
		'from_email' => 'ayse@example.com',
		'from_name'  => 'Ayşe',
		'to'         => 'help@example.com',
		'subject'    => pg_chat_request_subject('Where is my order?', 9, 'Live chat #9'),
		'text'       => pg_chat_request_body('Live chat #9', array(array('who' => 'Ayşe', 'when' => 'x', 'text' => 'Where is my order?', 'file' => '')), 'Attachment'),
		'message_id' => pg_chat_request_message_id(9, 41, 'example.com'),
		'date'       => 1764237600,
	));
	$parsed = pg_mail_parse($raw, array('with_content' => true));

	pg_assert_same('ayse@example.com', $parsed['from']['email']);
	pg_assert_same('Where is my order?', $parsed['subject']);
	pg_assert_same('pg-chat-9-41@example.com', $parsed['message_id']);
	pg_assert_contains('Where is my order?', $parsed['text']);
}

function test_chat_requests_wiring()
{
	$chat = file_get_contents(PG_FUNCTIONS_DIR . '/chat.php');
	pg_assert_contains("case 'chat_request_boxes':", $chat);
	pg_assert_contains("case 'chat_request':", $chat);
	pg_assert_contains('pg_chat_request_make($conversation_id', $chat);
	pg_assert_contains("'make_request' => lang('Make a support request')", $chat);

	pg_assert_contains("'chat_request_boxes', 'chat_request',", file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'));

	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/chat_backend.src.js');
	pg_assert_contains("api('chat_request_boxes'", $js);
	pg_assert_contains("api('chat_request',", $js);

	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/chat_requests.php');
	pg_assert_contains('ws_mail_ingest($raw, $box, array(\'tie\' => $tie, \'source\' => \'chat\', \'source_id\' => (int) $chat[\'id\']))', $module);
	pg_assert_contains('pg_support_form_box_offered($box)', $module);
}
