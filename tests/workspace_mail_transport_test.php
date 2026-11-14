<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for phone conversations beside e-mail in the Workspace
 * conversations list, the received event and the API (mail_list.php,
 * mail_phone.php, watch.php, api_mail.php): the transport filter, its
 * condition, the row of a WhatsApp conversation, the event's transport and
 * number, the event rule's filter and the API's fields. Reading the rows
 * needs the database and is not run here.
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

function workspace_mail_transport_test_read($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_workspace_mail_transport_kinds()
{
	pg_assert_same(array('email', 'whatsapp', 'sms'), array_keys(ws_mail_transport_labels()), 'e-mail, WhatsApp and SMS');
	pg_assert_same('whatsapp', ws_mail_transport_kind('whatsapp'));
	pg_assert_same('sms', ws_mail_transport_kind('sms'));

	foreach (array('imap', 'graph', 'gmail', 'push', '', null) as $box_transport) {
		pg_assert_same('email', ws_mail_transport_kind($box_transport), 'a mailbox\'s way of fetching is e-mail');
	}
}

function test_workspace_mail_transport_filter()
{
	pg_assert_same('', ws_mail_list_filters(array())['transport'], 'every kind by default');
	pg_assert_same('whatsapp', ws_mail_list_filters(array('transport' => 'whatsapp'))['transport']);
	pg_assert_same('', ws_mail_list_filters(array('transport' => 'imap'))['transport'], 'an unknown word falls back');
	pg_assert_same('', ws_mail_list_filters(array('transport' => array('sms')))['transport']);

	pg_assert_same('transport=whatsapp', ws_mail_list_query(ws_mail_list_filters(array('transport' => 'whatsapp'))), 'kept in the address');
	pg_assert_same('', ws_mail_list_query(ws_mail_list_filters(array())), 'left out when every kind');
}

function test_workspace_mail_transport_where()
{
	$filters = ws_mail_list_filters(array('view' => 'all', 'status' => 'all'));

	pg_assert_same("channel_id IN (3) AND message_id > 0", ws_mail_list_where($filters, 7, array(3)), 'no condition for every kind');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND box_id IN (SELECT b.id FROM ws_mail_boxes b WHERE b.transport = 'whatsapp')",
		ws_mail_list_where(array_merge($filters, array('transport' => 'whatsapp')), 7, array(3)), 'the WhatsApp boxes');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND box_id IN (SELECT b.id FROM ws_mail_boxes b WHERE b.transport = 'sms')",
		ws_mail_list_where(array_merge($filters, array('transport' => 'sms')), 7, array(3)), 'the SMS boxes');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND box_id NOT IN (SELECT b.id FROM ws_mail_boxes b WHERE b.transport IN ('whatsapp', 'sms'))",
		ws_mail_list_where(array_merge($filters, array('transport' => 'email')), 7, array(3)), 'e-mail: every box that is not a number\'s');
	pg_assert_same("channel_id IN (3) AND message_id > 0", ws_mail_list_where(array_merge($filters, array('transport' => "x' OR 1")), 7, array(3)), 'an unknown kind adds nothing');
}

function test_workspace_mail_transport_row()
{
	$row = array('id' => 8, 'channel_id' => 3, 'message_id' => 90, 'subject' => '', 'customer_name' => '', 'customer_email' => '', 'customer_phone' => '905321234567',
		'status' => 'open', 'assignee_id' => 0, 'assignee_name' => '', 'first_in_at' => 100, 'first_reply_at' => 0, 'last_in_at' => 100, 'last_out_at' => 0,
		'box_name' => 'WhatsApp +90 532 123 45 67', 'channel_name' => 'support', 'first_reply_hours' => 0, 'overdue' => false, 'support' => true,
		'mails' => 1, 'snippet' => 'Kargom nerede?', 'unread' => true, 'unsent' => '', 'transport' => 'whatsapp');
	$html = ws_mail_list_rows_html(array($row), ws_mail_list_filters(array()), 1000);

	pg_assert_contains('<span class="ws-mail-who">+905321234567</span>', $html, 'the number without a name');
	pg_assert_contains('bi-whatsapp', $html, 'the WhatsApp badge');
	pg_assert_same(false, strpos($html, h(lang('(no subject)'))), 'a message has no subject to miss');
	pg_assert_contains('Kargom nerede?', $html, 'its words');

	$named = ws_mail_list_rows_html(array(array_merge($row, array('customer_name' => 'Ayşe', 'transport' => 'sms'))), ws_mail_list_filters(array()), 1000);
	pg_assert_contains('<span class="ws-mail-who">Ayşe</span>', $named, 'the name first');
	pg_assert_contains(h(lang('SMS')), $named, 'the SMS badge');

	$mail = ws_mail_list_rows_html(array(array_merge($row, array('customer_email' => 'a@example.com', 'customer_phone' => '', 'transport' => 'email'))), ws_mail_list_filters(array()), 1000);
	pg_assert_same(false, strpos($mail, 'bi-whatsapp'), 'an e-mail keeps its row');
	pg_assert_contains(h(lang('(no subject)')), $mail);
}

function test_workspace_mail_transport_toolbar()
{
	$filters = ws_mail_list_filters(array());

	pg_assert_same(false, strpos(ws_mail_list_filters_html($filters, array(), false, true, array('email')), 'name="transport"'), 'one kind: no select');

	$html = ws_mail_list_filters_html($filters, array(), false, true, array('email', 'whatsapp'));
	pg_assert_contains('transport', $html, 'more than one kind: the select');
	pg_assert_contains(h(lang('WhatsApp')), $html);
	pg_assert_same(false, strpos($html, '>' . h(lang('SMS')) . '<'), 'only the kinds there are');
}

function test_workspace_mail_transport_event()
{
	$phone = workspace_mail_transport_test_read('includes/workspace/mail_phone.php');
	$start = strpos($phone, "ws_event('workspace.mail.received', array(");
	pg_assert_true($start !== false, 'a phone message starts the received event');

	$event = substr($phone, $start, 900);
	pg_assert_contains("'transport'", $event, 'with its transport');
	pg_assert_contains("'customer_phone'", $event, 'and its number');
	pg_assert_same(false, strpos($event, '$text'), 'never its text');
	pg_assert_contains("pg_announce('workspace.mail.received', array(", $phone, 'and the webhook, ids only');

	$mail = workspace_mail_transport_test_read('includes/workspace/mail.php');
	pg_assert_contains("'transport'       => 'email',", $mail, 'an e-mail says it is one');

	pg_assert_same('whatsapp', ws_events_filter_value('workspace.mail.received', array('transport' => 'whatsapp', 'status' => 'x')), 'the rule narrows by transport');
	pg_assert_same('email', ws_events_filter_value('workspace.mail.received', array()), 'an event kept before the transport is an e-mail');

	$outputs = array_map(function ($output) {
		return $output['key'] ?? ($output[0] ?? '');
	}, ws_flow_event_outputs()['workspace.mail.received']);
	pg_assert_true(in_array('transport', $outputs, true) && in_array('customer_phone', $outputs, true), 'a flow reads the transport and the number');
}

function test_workspace_mail_transport_api()
{
	$schema = ws_api_mail_conversation_schema();
	pg_assert_same('string', $schema['transport']);
	pg_assert_same('string?', $schema['customer_phone']);

	$conversation = array('id' => 8, 'box_id' => 2, 'channel_id' => 3, 'message_id' => 90, 'subject' => '', 'customer_email' => '', 'customer_name' => '', 'customer_phone' => '905321234567',
		'contact_id' => 0, 'status' => 'open', 'assignee_id' => 0, 'first_in_at' => 100, 'first_reply_at' => 0, 'last_in_at' => 100, 'last_out_at' => 0, 'closed_at' => 0, 'transport' => 'whatsapp');
	$out = ws_api_mail_conversation_present($conversation, 0, 1000);
	pg_assert_same('whatsapp', $out['transport']);
	pg_assert_same('+905321234567', $out['customer_phone'], 'E.164 with its plus');

	$mail = ws_api_mail_conversation_present(array_merge($conversation, array('customer_phone' => '', 'customer_email' => 'a@example.com', 'transport' => 'imap')), 0, 1000);
	pg_assert_same('email', $mail['transport']);
	pg_assert_same(null, $mail['customer_phone']);

	pg_assert_same('sms', ws_api_mail_list_filters(array('transport' => 'sms'))['transport'], 'the list narrows as the screen does');
}
