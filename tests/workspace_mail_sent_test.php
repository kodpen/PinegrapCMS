<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what happens after an answer leaves a Workspace mailbox
 * (includes/workspace/mail_reply.php, includes/workspace/mail.php): whether
 * a copy goes into the box's sent folder, the box form's copy fields, the
 * delivery status notifications read as "not delivered", and how such a
 * line is drawn. Sending, the IMAP copy and ws_mail_ingest() itself reach a
 * server or the database and are not run here.
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

// A delivery status notification for the answer pg.c7.ab@shop.test, as a
// Postfix server writes one; $status is the per-recipient block.
function workspace_mail_sent_test_dsn($status, $original = "Message-ID: <pg.c7.ab@shop.test>\r\nSubject: Re: Order [#T7]\r\n", $report = 'delivery-status')
{
	return pg_mail_parse(implode("\r\n", array(
		'From: Mail Delivery System <MAILER-DAEMON@mx.shop.test>',
		'To: support@shop.test',
		'Subject: Undelivered Mail Returned to Sender',
		'Message-ID: <20261028.dsn@mx.shop.test>',
		'Auto-Submitted: auto-replied',
		'MIME-Version: 1.0',
		'Content-Type: multipart/report; report-type=' . $report . '; boundary="B"',
		'',
		'--B',
		'Content-Type: text/plain; charset=us-ascii',
		'',
		'I\'m sorry to have to inform you that your message could not be delivered.',
		'--B',
		'Content-Type: message/delivery-status',
		'',
		'Reporting-MTA: dns; mx.shop.test',
		'Arrival-Date: Wed, 28 Oct 2026 10:00:00 +0300',
		'',
		$status,
		'--B',
		'Content-Type: text/rfc822-headers',
		'',
		$original,
		'--B--',
		'',
	)), array('with_content' => true));
}

function test_workspace_mail_dsn_reads_a_failure()
{
	$dsn = ws_mail_dsn(workspace_mail_sent_test_dsn(implode("\r\n", array(
		'Final-Recipient: rfc822; ayse@example.com',
		'Original-Recipient: rfc822;ayse@example.com',
		'Action: failed',
		'Status: 5.1.1',
		'Diagnostic-Code: smtp; 550 5.1.1 <ayse@example.com>: Recipient address',
		'    rejected: User unknown',
	))));

	pg_assert_same('pg.c7.ab@shop.test', $dsn['original_id'], 'the answer it returns');
	pg_assert_same('ayse@example.com', $dsn['recipient']);
	pg_assert_same('5.1.1', $dsn['status']);
	pg_assert_same('550 5.1.1 <ayse@example.com>: Recipient address rejected: User unknown', $dsn['diagnostic'], 'a folded line joined');
}

function test_workspace_mail_dsn_ignores_what_is_not_a_failure()
{
	pg_assert_same(null, ws_mail_dsn(workspace_mail_sent_test_dsn("Final-Recipient: rfc822; a@example.com\r\nAction: delayed\r\nStatus: 4.4.1")), 'a delay');
	pg_assert_same(null, ws_mail_dsn(workspace_mail_sent_test_dsn("Final-Recipient: rfc822; a@example.com\r\nAction: delivered\r\nStatus: 2.0.0")), 'a success report');
	pg_assert_same(null, ws_mail_dsn(workspace_mail_sent_test_dsn("Final-Recipient: rfc822; a@example.com\r\nAction: failed\r\nStatus: 5.1.1", "Subject: no id\r\n")), 'the returned mail names no Message-ID');
	pg_assert_same(null, ws_mail_dsn(workspace_mail_sent_test_dsn("Final-Recipient: rfc822; a@example.com\r\nAction: failed", "Message-ID: <x@y>\r\n", 'disposition-notification')), 'a read receipt');
	pg_assert_same(null, ws_mail_dsn(pg_mail_parse("From: a@example.com\r\nSubject: Hi\r\n\r\nAction: failed\r\n", array('with_content' => true))), 'a plain mail that only says the words');

	$second = ws_mail_dsn(workspace_mail_sent_test_dsn("Final-Recipient: rfc822; a@example.com\r\nAction: delivered\r\n\r\nFinal-Recipient: rfc822; b@example.com\r\nAction: failed\r\nStatus: bogus"));
	pg_assert_same(array('b@example.com', ''), array($second['recipient'], $second['status']), 'the failed recipient; a status not of the x.y.z form is left out');
}

function test_workspace_mail_bounce_item_is_told_apart()
{
	pg_assert_true(ws_mail_item_is_bounce(array('direction' => 'in', 'conversation_id' => 0, 'user_id' => 5)));
	pg_assert_false(ws_mail_item_is_bounce(array('direction' => 'in', 'conversation_id' => 3, 'user_id' => 0)), 'the customer\'s mail');
	pg_assert_false(ws_mail_item_is_bounce(array('direction' => 'out', 'conversation_id' => 3, 'user_id' => 5)), 'an answer');
	pg_assert_false(ws_mail_item_is_bounce(null));
}

function test_workspace_mail_bounce_is_drawn_as_not_delivered()
{
	$item = array('id' => 9, 'direction' => 'in', 'conversation_id' => 0, 'user_id' => 5, 'from_email' => 'mailer-daemon@mx.shop.test', 'from_name' => '',
		'to_list' => 'ayse@example.com', 'subject' => 'Undelivered', 'body_text' => '5.1.1 <b>User unknown</b>', 'auth' => 'none', 'refused_files' => '', 'conversation' => null);
	$html = ws_mail_card_html(array('id' => 120, 'file_id' => 0, 'body' => 'x'), $item);

	pg_assert_contains(h(lang('Not delivered')), $html);
	pg_assert_contains(h(lang(array('string' => 'To {var:1}', 'vars' => 'ayse@example.com'))), $html, 'whom it did not reach');
	pg_assert_contains('5.1.1 &lt;b&gt;User unknown&lt;/b&gt;', $html, 'the server\'s words as text');
	pg_assert_true(strpos($html, h(lang('Sender not verified'))) === false, 'not drawn as a mail from a stranger');
	pg_assert_contains(h(lang('No reason was given.')), ws_mail_card_html(array('id' => 120, 'file_id' => 0, 'body' => ''), array_merge($item, array('body_text' => ''))));
}

function test_workspace_mail_reply_copy_plan()
{
	$box = array('transport' => 'imap', 'settings' => json_encode(array('host' => 'imap.shop.test', 'user' => 'u', 'secret' => 's', 'sent_folder' => 'Sent')));
	$route = array('ok' => true, 'smtp' => array('host' => 'smtp.shop.test'));

	pg_assert_same(array('copy' => true, 'reason' => '', 'folder' => 'Sent'), ws_mail_reply_copy_plan($box, $route), 'an IMAP box keeps a copy');
	pg_assert_same('', ws_mail_reply_copy_plan(array('transport' => 'imap', 'settings' => json_encode(array('host' => 'h', 'user' => 'u', 'secret' => 's'))), $route)['folder'], 'no folder typed: found on the server');
	pg_assert_true(ws_mail_reply_copy_plan($box, array('ok' => true, 'smtp' => null))['copy'], 'through the site\'s settings too');

	$off = array_merge($box, array('settings' => json_encode(array('host' => 'h', 'user' => 'u', 'secret' => 's', 'sent_copy' => 0))));
	pg_assert_same('off', ws_mail_reply_copy_plan($off, $route)['reason'], 'switched off');
	pg_assert_same('no_imap', ws_mail_reply_copy_plan(array('transport' => 'imap', 'settings' => json_encode(array('smtp_host' => 'smtp.shop.test'))), $route)['reason'], 'a box that only sends');
	pg_assert_same('no_imap', ws_mail_reply_copy_plan(array_merge($box, array('transport' => 'graph')), $route)['reason'], 'a connected account later');

	foreach (array('smtp.gmail.com', 'SMTP.Office365.com', 'smtp-mail.outlook.com') as $host) {
		pg_assert_same('service', ws_mail_reply_copy_plan($box, array('ok' => true, 'smtp' => array('host' => $host)))['reason'], $host . ' keeps its own');
	}

	pg_assert_true(ws_mail_reply_copy_plan($box, array('ok' => true, 'smtp' => array('host' => 'smtp.notgmail.com')))['copy'], 'only the service\'s own domain');
}

function test_workspace_mail_box_copy_fields()
{
	$base = array('name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 4, 'auth_server' => '');
	$ticked = ws_mail_box_input(array_merge($base, array('sent_copy' => '1', 'sent_folder' => ' Sent Items ')));

	pg_assert_same(array(1, 'Sent Items'), array($ticked['values']['sent_copy'], $ticked['values']['sent_folder']));
	pg_assert_same(0, ws_mail_box_input($base)['values']['sent_copy'], 'off unless ticked');
	pg_assert_same(lang('The sent folder\'s name is at most 255 characters.'),
		ws_mail_box_input(array_merge($base, array('host' => 'imap.example.com', 'user' => 'u', 'sent_folder' => str_repeat('a', 256))))['error']);

	$new = ws_mail_box_form('workspace_settings.php', null, array());
	pg_assert_contains('name="sent_copy" value="1" id="ws_mail_0_sent_copy" checked', $new, 'a new box keeps a copy');

	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 0, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'enabled' => 1, 'reply_via' => 'box',
		'settings' => json_encode(array('host' => 'imap.example.com', 'sent_copy' => 0, 'sent_folder' => 'Gönderilmiş')));
	$html = ws_mail_box_form('workspace_settings.php', $box, array());

	pg_assert_contains('name="sent_copy" value="1" id="ws_mail_3_sent_copy">', $html, 'switched off stays off');
	pg_assert_contains('value="Gönderilmiş"', $html);

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("'smtp_port', 'sent_copy', 'sent_folder') as \$key", $source, 'kept in the box\'s settings');
}

function test_workspace_mail_sent_copy_and_bounce_wiring()
{
	$reply = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_reply.php');
	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	$ingest = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js');

	pg_assert_contains("'keep_mime'          => true,", $reply, 'the answer asks for the mail as sent');
	pg_assert_contains("pg_mail_last_sent_mime(\$mail->getSentMIMEMessage());", $mail, 'email() hands it back');
	pg_assert_contains("'copy' => \$copy()", $reply, 'copied after the record is written');
	pg_assert_contains('data.copy_note', $script, 'a missing copy is told');

	// The failure is read before the loop gate would drop it as automatic.
	pg_assert_true(strpos($ingest, '$dsn = ws_mail_dsn($parsed);') < strpos($ingest, '$loop = ws_mail_loop_reason($parsed'), 'before the loop gate');
	pg_assert_contains("direction = 'out' AND rfc_message_hash = '\" . e(ws_mail_hash(\$dsn['original_id'], ''))", $ingest, 'only a failure of the box\'s own answer');

	pg_assert_true(ws_mail_ingest_message(array('status' => 'bounce', 'reason' => ''))['notice'], 'the .eml upload says what it did');
	pg_assert_same('taken', ws_mail_fetch_outcome_for_test(array('status' => 'bounce', 'reason' => '')), 'a reader takes it out of the way');
}

function ws_mail_fetch_outcome_for_test($result)
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');

	return ws_mail_fetch_outcome($result);
}
