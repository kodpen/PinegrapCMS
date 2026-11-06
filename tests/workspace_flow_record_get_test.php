<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the box of the workspace's flows that reads one record of the
 * site (kind record_get, includes/workspace/flow_record_get.php): an order,
 * product, contact, booking, task or submitted form named by its id or by
 * one value of the run. Its kind and settings, what it lacks before
 * publishing, the values it gives the boxes after it (the record, found and
 * the record's fields), a record that is not there or out of reach read as
 * found 0 with empty fields, and what a reader who may not change the flow
 * reads. Read from the source: the kind is checked with the owner's rights
 * as the change box checks them, the record each time the box runs.
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
require_once(__DIR__ . '/helpers/source_reader.php');

// A function's source without its comment lines.
function workspace_flow_record_get_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// A step kind of its own: it leaves on done or failed, reads its id as a
// value of the run, keeps all its values from readers who may not change
// the flow, and a role-3 member may build it.
function test_workspace_flow_record_get_kind()
{
	$kinds = ws_step_kinds();

	pg_assert_true(isset($kinds['record_get']));
	pg_assert_same('record_get', $kinds['record_get']['type']);
	pg_assert_same(array('id'), $kinds['record_get']['texts']);
	pg_assert_same(array('id'), $kinds['record_get']['lines'], 'an id is one line');
	pg_assert_same(array('record_id', 'found'), array_column($kinds['record_get']['outputs'], 'key'));
	pg_assert_same(array('done', 'failed'), ws_flow_kinds()['record_get']['outcomes']);
	pg_assert_true(ws_flow_kinds()['record_get']['step']);
	pg_assert_same(array('*'), ws_step_outputs_held()['record_get'], 'its record stays with whoever may change the flow');
	pg_assert_true(in_array('record_get', ws_flow_member_kinds(), true), 'a role-3 member reads records they reach');
	pg_assert_false(in_array('record_get', ws_flow_card_outbound_kinds(), true), 'reading a record sends nothing out');
	pg_assert_same('json', ws_step_fill_target('record_get', array('type' => 'record_get')), 'its id is filled as a row box\'s');
	pg_assert_contains('record_get', ws_flow_ai_catalogue_text(ws_flow_ai_catalogue_hints(ws_flow_kind_catalogue(), array())), 'Pinegrap AI drafts it');
}

// The kinds it reads, each with fields of its own and no key taken twice.
function test_workspace_flow_record_get_kinds()
{
	$fields = ws_flow_record_get_fields();

	pg_assert_same(array('order', 'product', 'contact', 'booking', 'task', 'form', 'cart_quote'), array_keys($fields));

	foreach ($fields as $kind => $info) {
		pg_assert_true($info['label'] !== '', $kind);
		pg_assert_true(count($info['fields']) > 0, $kind);

		foreach ($info['fields'] as $key => $field) {
			pg_assert_true(preg_match('/^[a-z][a-z0-9_]{0,31}$/', $key) === 1, $kind . '.' . $key);
			pg_assert_false(in_array($key, array('record_id', 'found'), true), $kind . '.' . $key . ' is no key of its own');
			pg_assert_true(in_array($field[0], array('text', 'number', 'money', 'bool', 'time', 'day', 'answers'), true), $kind . '.' . $key);
		}
	}

	// A card number, its code, a booking's manage link or a cart quote's
	// link never leave the record.
	foreach (array('card_number', 'card_verification_number', 'cardholder', 'manage_token', 'ip_address', 'token_hash', 'token_hint') as $column) {
		foreach ($fields as $kind => $info) {
			pg_assert_false(in_array($column, array_column($info['fields'], 1), true), $kind . ' gives ' . $column);
		}
	}

	$outputs = ws_flow_record_get_outputs('order');

	pg_assert_same('order_number', $outputs[0]['key']);
	pg_assert_same('number', array_column($outputs, 'type', 'key')['total'], 'money is a number');
	pg_assert_same('text', array_column($outputs, 'type', 'key')['email']);
	pg_assert_same(array(), ws_flow_record_get_outputs('nothing'));
}

// Its settings: the kind and the id, a literal id or one value alone.
function test_workspace_flow_record_get_config()
{
	pg_assert_same(array('record' => 'order', 'id' => '{{trigger.record_id}}'), ws_step_config_normalize('record_get', array('record' => 'order', 'id' => " {{trigger.record_id}}\n", 'fields' => array('x' => 1))));
	pg_assert_same(array('record' => '', 'id' => ''), ws_step_config_normalize('record_get', array()));
	pg_assert_same(array('record' => '', 'id' => '5'), ws_step_config_normalize('record_get', array('record' => 'page', 'id' => 5)), 'a kind it does not read is none');

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');

	pg_assert_same(array('record', 'id', 'retry', 'retry_after'), array_column($catalogue['record_get']['settings'], 'key'), 'its settings, then the tries');

	$problems = function ($config) {
		return implode(' ', ws_step_config_problems('record_get', ws_step_config_normalize('record_get', $config), 'Read'));
	};

	pg_assert_contains('needs the kind of record it reads', $problems(array('id' => '4')));
	pg_assert_contains('needs the record it reads', $problems(array('record' => 'contact')));
	pg_assert_contains('by its id or by one value', $problems(array('record' => 'contact', 'id' => 'contact {{trigger.record_id}}')));
	pg_assert_same('', $problems(array('record' => 'contact', 'id' => '{{trigger.record_id}}')));
	pg_assert_same('', $problems(array('record' => 'task', 'id' => '17')));
}

// Its values: the record and found, then the fields of its kind, every one
// as the text it fills in; money in units with two decimals, a time as the
// site writes it in a value, a yes as 1.
function test_workspace_flow_record_get_outputs()
{
	$config = array('record' => 'contact', 'id' => '3');
	$keys = array_column(ws_step_outputs('record_get', $config), 'key');

	pg_assert_same(array('record_id', 'found'), array_slice($keys, 0, 2));
	pg_assert_true(in_array('email', $keys, true));
	pg_assert_true(in_array('first_name', $keys, true));
	pg_assert_same(array('record_id', 'found'), array_column(ws_step_outputs('record_get', array('record' => '', 'id' => '')), 'key'), 'no kind, no fields');

	pg_assert_same('12.50', ws_flow_record_get_money(1250));
	pg_assert_same('0.05', ws_flow_record_get_money(5));
	pg_assert_same('-3.00', ws_flow_record_get_money(-300));
	pg_assert_same('0.00', ws_flow_record_get_money(null));

	$order = array('id' => 9, 'order_number' => '100245', 'status' => 'complete', 'total' => 1999, 'billing_email_address' => 'a@example.com', 'tax_exempt' => 1, 'order_date' => 0);
	$data = ws_step_record_get_data('order', $order);

	pg_assert_same(9, $data['record_id']);
	pg_assert_same(1, $data['found']);
	pg_assert_same('100245', $data['order_number']);
	pg_assert_same('19.99', $data['total']);
	pg_assert_same('a@example.com', $data['email']);
	pg_assert_same('1', $data['tax_exempt']);
	pg_assert_same('', $data['order_date'], 'no time is no date');
	pg_assert_same('', $data['po_number'], 'a column it lacks is empty');

	$none = ws_step_record_get_data('order', null);

	pg_assert_same(0, $none['record_id']);
	pg_assert_same(0, $none['found']);
	pg_assert_same('', $none['total']);
	pg_assert_same(array_column(ws_step_outputs('record_get', array('record' => 'order', 'id' => '1')), 'key'), array_keys($none), 'every value it declares');

	$form = ws_step_record_get_data('form', array('id' => 4, 'reference_code' => 'AB12', 'complete' => 0, '__answers' => array('Name' => 'Ada', 'Note' => "two\nlines")));

	pg_assert_same('0', $form['complete']);
	pg_assert_same("Name: Ada\nNote: two lines", $form['answers'], 'one answer a line');

	// A cart quote: its state now, an open one past its last day expired,
	// and whether it was ordered; no last day is none.
	$now = 1700000000;
	$quote = ws_step_record_get_data('cart_quote', ws_step_record_get_cart_quote_row(array('id' => 12, 'status' => 'open', 'valid_until' => $now - 60, 'order_id' => 0, '__lines' => '3', '__first_name' => 'Ada'), $now));

	pg_assert_same(12, $quote['record_id']);
	pg_assert_same('expired', $quote['status']);
	pg_assert_same('0', $quote['ordered']);
	pg_assert_same('3', $quote['lines']);
	pg_assert_same('Ada', $quote['first_name']);
	pg_assert_same('', $quote['company'], 'a contact column it lacks is empty');

	$quote = ws_step_record_get_data('cart_quote', ws_step_record_get_cart_quote_row(array('id' => 12, 'status' => 'ordered', 'valid_until' => 0, 'order_id' => 40), $now));

	pg_assert_same('ordered', $quote['status']);
	pg_assert_same('1', $quote['ordered']);
	pg_assert_same('40', $quote['order_id']);
	pg_assert_same('', $quote['valid_until'], 'no last day');
	pg_assert_same('open', ws_step_record_get_data('cart_quote', ws_step_record_get_cart_quote_row(array('id' => 1, 'status' => 'open', 'valid_until' => $now + 60), $now))['status']);

	$task = ws_step_record_get_data('task', array('id' => 2, 'title' => 'Call', 'due_date' => '0000-00-00'));

	pg_assert_same('', $task['due_date'], 'the zero date is none');

	// Kept as the text it fills in, a money value's decimals included.
	$kept = ws_step_result_data('record_get', array('record' => 'order', 'id' => '9'), array('ok' => true, 'record_id' => 9, 'data' => $data));

	pg_assert_same('19.99', $kept['total']);
	pg_assert_same(9, $kept['record_id']);
	pg_assert_same(1, $kept['found']);

	$kept = ws_step_result_data('record_get', array('record' => 'order', 'id' => '9'), array('ok' => true, 'record_id' => 0, 'data' => $none));

	pg_assert_same(null, $kept['record_id']);
	pg_assert_same(0, $kept['found']);
}

// Who reads what: a reader who may not change the flow reads that a record
// was read, or not, and the box's id masked.
function test_workspace_flow_record_get_readers()
{
	pg_assert_same('A record was read.', ws_step_result_shown('record_get', 'done', 'Read the order 9.', false));
	pg_assert_same('No record was read.', ws_step_result_shown('record_get', 'failed', 'x', false));
	pg_assert_same('Read the order 9.', ws_step_result_shown('record_get', 'done', 'Read the order 9.', true));

	$graph = array('nodes' => array(array('id' => 'get', 'kind' => 'record_get', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('record' => 'order', 'id' => '42'))), 'edges' => array(), 'notes' => array());
	$shown = ws_flow_graph_present($graph, false);

	pg_assert_same('', ((array) $shown['nodes'][0]['config'])['id']);
	pg_assert_same('order', ((array) $shown['nodes'][0]['config'])['record'], 'the kind stays');
	pg_assert_same('42', ((array) ws_flow_graph_present($graph, true)['nodes'][0]['config'])['id']);
}

// The source: the kind's right and each record as the change box asks them
// (ws_change_allowed()), a booking by the agenda's own rule; a record out
// of reach is read as one that is not there.
function test_workspace_flow_record_get_reach_source()
{
	$check = workspace_flow_record_get_test_source('ws_step_record_get_check');

	pg_assert_contains('ws_step_record_get_kind_allowed', $check);
	pg_assert_contains('ws_step_row_names_row', $check);

	$allowed = workspace_flow_record_get_test_source('ws_step_record_get_kind_allowed');

	pg_assert_contains('ws_change_allowed', $allowed);
	pg_assert_contains('pg_booking_agenda_user_scope', $allowed);

	$reach = workspace_flow_record_get_test_source('ws_step_record_get_read');

	pg_assert_contains('ws_change_allowed', $reach);
	pg_assert_contains('ws_change_record', $reach);
	pg_assert_contains('pg_booking_agenda_scope_allows', $reach);
	pg_assert_contains("'ecommerce'", $allowed, 'a cart quote by its screens\' right');
	pg_assert_contains('pg_cart_quotes_ready', $allowed);
	pg_assert_contains('ws_step_record_get_cart_quote_row', $reach);
	pg_assert_false(strpos($reach, 'q.*') !== false, 'a cart quote is read by named columns, its link\'s hash left out');

	$run = workspace_flow_record_get_test_source('ws_step_run');

	pg_assert_contains('ws_step_record_get_do($owner, $action)', $run);
	pg_assert_contains("'record_get'", workspace_flow_record_get_test_source('ws_step_check'));
}
