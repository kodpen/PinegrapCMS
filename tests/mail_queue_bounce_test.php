<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Workspace mail's delivery failure on its queue rows
 * (includes/fn/mail_queue_list.php): which rows are looked up, how a row
 * is tied to its item and the item to its bounce, and the Not delivered
 * badge on the Sends list and the mail queue list. The reads are given, so
 * no test needs a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_queue.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_sends.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php';

function test_mail_queue_bounce_candidates()
{
	$records = array(
		array('id' => 1, 'status' => 'sent', 'mail_type' => 'workspace'),
		array('id' => 2, 'status' => 'failed', 'mail_type' => 'workspace'),
		array('id' => 3, 'status' => 'sent', 'mail_type' => 'order'),
		array('rid' => 4, 'status' => 'sent', 'mail_type' => 'workspace'),
	);

	pg_assert_same(array(1, 4), pg_mail_queue_bounce_candidates($records, 'status', 'mail_type'), 'sent Workspace rows only');
	pg_assert_same(array(), pg_mail_queue_bounced(array()), 'nothing to read');
}

function test_mail_queue_bounce_ties()
{
	$refs = array(10 => '54:0', 11 => '60:9', 12 => '61:0', 13 => 'bad', 14 => '62:0');
	$items = array(
		array('id' => 54, 'box_id' => 1, 'direction' => 'out', 'outbox_id' => 10, 'rfc_message_id' => '<pg.c48.a@x>'),
		// Send again moved the item to row 11's successor: row 11 is no longer its row.
		array('id' => 60, 'box_id' => 1, 'direction' => 'out', 'outbox_id' => 15, 'rfc_message_id' => '<pg.c50.b@x>'),
		array('id' => 61, 'box_id' => 1, 'direction' => 'out', 'outbox_id' => 12, 'rfc_message_id' => '<pg.c51.c@x>'),
		array('id' => 62, 'box_id' => 1, 'direction' => 'out', 'outbox_id' => 14, 'rfc_message_id' => '<pg.c52.d@x>'),
	);
	$bounces = array(
		array('box_id' => 1, 'in_reply_to' => '<pg.c48.a@x>', 'body_text' => "5.0.0 DNS lookup failed\n"),
		array('box_id' => 1, 'in_reply_to' => '<pg.c48.a@x>', 'body_text' => 'a second notice'),
		array('box_id' => 1, 'in_reply_to' => '<pg.c50.b@x>', 'body_text' => 'moved'),
		// Another box's mail with the same Message-ID is not this one's.
		array('box_id' => 2, 'in_reply_to' => '<pg.c52.d@x>', 'body_text' => 'other box'),
	);

	pg_assert_same(array(10 => '5.0.0 DNS lookup failed'), pg_mail_queue_bounce_ties($refs, $items, $bounces),
		'the first notice of the row\'s own item, in its own box');
}

function test_mail_queue_bounce_badges()
{
	$now = 1800000000;
	$row = array(
		'id' => '10', 'created_at' => $now - 3600, 'send_after' => '0', 'status' => 'sent', 'attempts' => '1', 'last_error' => '',
		'sent_at' => $now - 60, 'mail_type' => 'workspace', 'recipient' => 'tugba@kopden.com', 'subject' => 'Hello', 'bounce' => 'Domain doesn\'t exist',
	);
	$html = pg_list_row_html(pg_mail_queue_list_row($row, $now));
	pg_assert_contains(h(lang('Not delivered')), $html, 'the queue row');
	pg_assert_contains(h('Domain doesn\'t exist'), $html, 'with the server\'s words');

	$record = array('sid' => pg_messages_sends_sid(10, array_search('mail', pg_messages_sends_branches(), true)), 'at' => $now - 3600, 'channel' => 'email',
		'row_kind' => 'message', 'recipient' => 'tugba@kopden.com', 'what' => 'Hello', 'source' => 'workspace', 'state' => 'sent', 'done' => 0, 'total' => 0,
		'error' => '', 'attempts' => 1, 'next_at' => $now - 60, 'expires' => 0, 'bounce' => 'Domain doesn\'t exist');
	$html = pg_list_row_html(pg_messages_sends_row($record, $now));
	pg_assert_contains(h(lang('Not delivered')), $html, 'the Sends row');
	pg_assert_contains(h('Domain doesn\'t exist'), $html);

	unset($record['bounce']);
	pg_assert_false(strpos(pg_list_row_html(pg_messages_sends_row($record, $now)), h(lang('Not delivered'))) !== false, 'no failure: Sent');
}

function test_mail_queue_bounce_wiring()
{
	$sends = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/messages_sends.php');
	$queue = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');

	pg_assert_contains("\$bounced = pg_mail_queue_bounced(pg_mail_queue_bounce_candidates(\$mail, 'state', 'source'));", $sends, 'the Sends page\'s rows');
	pg_assert_contains("pg_mail_queue_bounced(pg_mail_queue_bounce_candidates(\$records, 'status', 'mail_type'))", $queue, 'the queue page\'s rows');
	pg_assert_contains("WHERE conversation_id = 0 AND direction = 'in' AND user_id > 0", $queue, 'bounces through idx_conversation');
}
