<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a support request's priority and source
 * (includes/workspace/mail_priority.php): the priorities, the filter, the
 * badge, a change, the first-answer hours per priority and their box form
 * fields, the source label, and where the list, the pane, the box form and
 * the doors use them. Reading and writing the columns needs the database
 * and is not run here.
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

function test_workspace_mail_priority_values()
{
	pg_assert_same(array(1, 2, 3, 4), array_keys(ws_mail_priorities()));
	pg_assert_same(2, ws_mail_priority_of(array()), 'a row stored before the column is Normal');
	pg_assert_same(4, ws_mail_priority_of(array('priority' => '4')));
	pg_assert_same(2, ws_mail_priority_of(array('priority' => 9)), 'an unknown value is Normal');
	pg_assert_same(3, ws_mail_priority_filter('3'));
	pg_assert_same(0, ws_mail_priority_filter(''), 'every priority');
	pg_assert_same(0, ws_mail_priority_filter('7'));
	pg_assert_same(0, ws_mail_priority_filter(array('1')));
}

function test_workspace_mail_priority_badge()
{
	pg_assert_same('', ws_mail_priority_html(array('priority' => 2)), 'Normal draws nothing');
	pg_assert_contains('theme-danger', ws_mail_priority_html(array('priority' => 4)));
	pg_assert_contains(h(lang('Urgent')), ws_mail_priority_html(array('priority' => 4)));
	pg_assert_contains(h(lang('Low')), ws_mail_priority_html(array('priority' => 1)));
}

function test_workspace_mail_priority_change()
{
	$conversation = array('status' => 'open', 'assignee_id' => 0, 'priority' => 2);

	pg_assert_same(array('priority' => 4, 'updated_at' => 1000), ws_mail_conversation_changes($conversation, array('priority' => '4'), 7, 1000)['fields'], 'raised');
	pg_assert_same('nothing', ws_mail_conversation_changes($conversation, array('priority' => '2'), 7, 1000)['error'], 'the same priority');
	pg_assert_same('priority', ws_mail_conversation_changes($conversation, array('priority' => '5'), 7, 1000)['error']);
	pg_assert_same('nothing', ws_mail_conversation_changes($conversation, array('priority' => ''), 7, 1000)['error'], 'no priority posted');
	pg_assert_same(array('status' => 'closed', 'closed_at' => 1000, 'priority' => 1, 'updated_at' => 1000),
		ws_mail_conversation_changes($conversation, array('status' => 'closed', 'priority' => '1'), 7, 1000)['fields'], 'with the status');
	pg_assert_true(ws_mail_conversation_error_text('priority') !== ws_mail_conversation_error_text('nothing'));
}

function test_workspace_mail_priority_hours()
{
	$box = array('settings' => json_encode(array('support' => true, 'first_reply_hours' => 24, 'priority_hours' => array('1' => 72, '4' => 2))));
	$plain = array('settings' => json_encode(array('support' => false, 'priority_hours' => array('4' => 2))));

	pg_assert_same(array(1 => 72, 3 => 0, 4 => 2), ws_mail_priority_hours($box));
	pg_assert_same(array(1 => 0, 3 => 0, 4 => 0), ws_mail_priority_hours($plain), 'a plain box has none');
	pg_assert_same(array(1 => 0, 3 => 0, 4 => 2), ws_mail_priority_hours($plain, true), 'its form keeps them');

	$hours = ws_mail_priority_hours($box);

	pg_assert_same(2, ws_mail_priority_reply_hours(24, $hours, array('priority' => 4)), 'Urgent\'s own');
	pg_assert_same(24, ws_mail_priority_reply_hours(24, $hours, array('priority' => 3)), 'High follows Normal\'s');
	pg_assert_same(24, ws_mail_priority_reply_hours(24, $hours, array()), 'a row without a priority is Normal');
	pg_assert_same(72, ws_mail_priority_reply_hours(0, $hours, array('priority' => 1)), 'Low\'s own without a box target');
}

function test_workspace_mail_priority_hours_input()
{
	$ok = ws_mail_priority_hours_input(array('priority_hours_1' => ' 48 ', 'priority_hours_3' => '', 'priority_hours_4' => '1'));

	pg_assert_true($ok['ok']);
	pg_assert_same(array(1 => 48, 3 => 0, 4 => 1), $ok['hours']);

	$wrong = ws_mail_priority_hours_input(array('priority_hours_3' => '2.5'));

	pg_assert_same(false, $wrong['ok']);
	pg_assert_same('priority_hours_3', $wrong['field']);
	pg_assert_same(false, ws_mail_priority_hours_input(array('priority_hours_4' => (string) (WS_MAIL_FIRST_REPLY_MAX + 1)))['ok'], 'at most the first answer\'s longest');

	$fields = ws_mail_priority_hours_fields('p_', array('settings' => json_encode(array('priority_hours' => array('3' => 8)))));

	pg_assert_contains('name="priority_hours_3"', $fields);
	pg_assert_contains('value="8"', $fields);
	pg_assert_true(strpos($fields, 'name="priority_hours_2"') === false, 'Normal is the first answer\'s own field');
}

function test_workspace_mail_source_label()
{
	pg_assert_same(array('mail', 'whatsapp', 'sms', 'form', 'chat', 'account'), ws_mail_sources());
	pg_assert_same('', ws_mail_source_html(array('source' => 'mail')), 'a mail has no label');
	pg_assert_same('', ws_mail_source_html(array()), 'a row stored before the column is a mail');
	pg_assert_same('', ws_mail_source_html(array('source' => 'whatsapp')), 'the transport badge says WhatsApp');
	pg_assert_contains(h(lang('Form')), ws_mail_source_html(array('source' => 'form', 'source_id' => 5)));
	pg_assert_true(strpos(ws_mail_source_html(array('source' => 'form', 'source_id' => 5)), '<a ') === false, 'a list row is a link itself');
	pg_assert_contains('href="/p/edit_submitted_form.php?id=5"', ws_mail_source_html(array('source' => 'form', 'source_id' => 5), '/p/', true), 'the pane leads back to the record');
	pg_assert_contains('data-ws-mail-source-chat="9"', ws_mail_source_html(array('source' => 'chat', 'source_id' => 9), '/p/', true), 'and to the chat');
	pg_assert_true(strpos(ws_mail_source_html(array('source' => 'account'), '/p/', true), '<a ') === false, 'My Account has nothing to lead to');
	pg_assert_same(false, ws_mail_source_set(0, 'form', 1));
	pg_assert_same(false, ws_mail_source_set(3, 'mail'), 'a mail is the default');
	pg_assert_same(false, ws_mail_source_set(3, 'fax'));
}

function test_workspace_mail_priority_list()
{
	$filters = ws_mail_list_filters(array('priority' => '4'));

	pg_assert_same(4, $filters['priority']);
	pg_assert_same('priority=4', ws_mail_list_query($filters));
	pg_assert_same(0, ws_mail_list_filters(array())['priority']);

	$where = ws_mail_list_where($filters, 7, array(3), '', array(8));

	pg_assert_contains("priority = '4' AND box_id NOT IN (8)", $where, 'only a support box\'s conversations have a priority');
	pg_assert_true(strpos(ws_mail_list_where(ws_mail_list_filters(array()), 7, array(3)), 'priority') === false, 'no priority asked');

	$row = array('id' => 1, 'customer_name' => 'A', 'customer_email' => 'a@example.com', 'subject' => 'S', 'last_in_at' => 1, 'last_out_at' => 0,
		'box_name' => '', 'channel_name' => '', 'assignee_name' => '', 'first_reply_hours' => 0, 'status' => 'open', 'priority' => 4, 'source' => 'form', 'source_id' => 2);

	pg_assert_contains(h(lang('Urgent')), ws_mail_list_rows_html(array($row), $filters, 10));
	pg_assert_contains(h(lang('Form')), ws_mail_list_rows_html(array($row), $filters, 10));
	pg_assert_true(strpos(ws_mail_list_rows_html(array(array('support' => false) + $row), $filters, 10), h(lang('Urgent'))) === false, 'a plain box shows no priority');
}

function test_workspace_mail_priority_wiring()
{
	$read = function ($file) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	};

	pg_assert_contains("includes/workspace/mail_priority.php", $read('includes/workspace/bootstrap.php'));
	pg_assert_contains("upgrade_2026_11_0_ws_mail_priority_source(); // 11.0.288", $read('includes/migrations/2026.11.0.php'));
	pg_assert_contains("'source' => 'form'", $read('includes/fn/support_forms.php'));
	pg_assert_contains("'source' => 'chat'", $read('includes/fn/chat_requests.php'));
	pg_assert_contains("'source' => 'account'", $read('includes/fn/support_requests.php'));
	pg_assert_contains('ws_mail_source_set($conversation_id, $transport)', $read('includes/workspace/mail_phone.php'));
	pg_assert_contains("ws_mail_source_set(\$conversation_id, (string) \$options['source']", $read('includes/workspace/mail.php'));
	pg_assert_contains('ws_mail_priority_hours_fields($prefix, $box)', $read('includes/workspace/mail_settings.php'));
	pg_assert_contains('ws_mail_priority_hours_input($post)', $read('includes/workspace/mail_settings.php'));
	pg_assert_contains("'priority' => is_scalar(\$request['priority']", $read('includes/workspace/actions.php'));
	pg_assert_contains("\$state['priority'] = ws_mail_priority_of(\$conversation)", $read('includes/workspace/mail_pane.php'));
	pg_assert_contains("t('mail_priority_' + key)", $read('assets/js/workspace_mail.js'));
	pg_assert_contains('data-ws-mail-source-chat', $read('assets/js/workspace_mail.js'));
	pg_assert_contains('ws_mail_conversation_reply_hours(', $read('includes/workspace/api_mail.php'));
}
