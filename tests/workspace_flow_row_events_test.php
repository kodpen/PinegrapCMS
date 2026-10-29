<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the row box of a flow: a workflow table's row added, changed or
 * deleted starts the flow. Its settings and rule, the refusals of its table
 * and column, how a row event matches the rule, the event kept where the
 * store writes a row (quiet during an import), and the values it gives
 * {{trigger.<field>}}: the table, the row, who wrote it and its cells.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// Two columns of a table, as ws_wtable_columns() gives them.
function ws_flow_row_test_columns()
{
	return array(
		array('id' => 7, 'name' => 'Customer', 'type' => 'text'),
		array('id' => 9, 'name' => 'Amount', 'type' => 'money'),
	);
}

function test_workspace_flow_row_box_is_a_start()
{
	pg_assert_same('row', ws_flow_anchor_kinds()['row']['rule']);
	pg_assert_true(ws_flow_kinds()['row']['trigger'], 'a row box starts a flow');
	pg_assert_same(array('next'), ws_flow_kinds()['row']['outcomes']);
	pg_assert_same('event', ws_flow_start_names()['row'], 'listed among the flows started on an event');
	pg_assert_same(array('created', 'updated', 'deleted'), array_keys(ws_flow_row_whats()));
	pg_assert_same(array('created', 'updated', 'deleted'), ws_flow_setting_values()['row.what'], 'the catalogue offers the same words');

	foreach (ws_flow_row_whats() as $what => $info) {
		pg_assert_true(ws_events_is_own($info['event']), $info['event'] . ' is one of the workspace\'s own events, kept by ws_event()');
		pg_assert_same('workspace.wtable.row.' . $what, $info['event']);
	}
}

function test_workspace_flow_row_settings_and_rule()
{
	pg_assert_same(array('table_id' => 0, 'what' => 'created', 'column_id' => 0), ws_flow_anchor_normalize('row', array()), 'a new box: no table, a row added');
	pg_assert_same(array('table_id' => 4, 'what' => 'created', 'column_id' => 0), ws_flow_anchor_normalize('row', array('table_id' => '4', 'what' => 'moved', 'column_id' => 9, 'extra' => 1)),
		'an unknown word is a row added; a column only for a change; unknown keys dropped');
	pg_assert_same(array('table_id' => 4, 'what' => 'updated', 'column_id' => 9), ws_flow_anchor_normalize('row', array('table_id' => 4, 'what' => 'updated', 'column_id' => '9')));
	pg_assert_same(array('table_id' => 0, 'what' => 'deleted', 'column_id' => 0), ws_flow_anchor_normalize('row', array('table_id' => -3, 'what' => 'deleted')));

	pg_assert_same(array('type' => 'row', 'event' => 'workspace.wtable.row.updated', 'table_id' => 4, 'column_id' => 9),
		ws_flow_anchor_rule('row', ws_flow_anchor_normalize('row', array('table_id' => 4, 'what' => 'updated', 'column_id' => 9))),
		'the rule names the store\'s event, so ws_events_awaited() reads it');

	pg_assert_same(1, count(ws_flow_anchor_problems('row', ws_flow_anchor_normalize('row', array()), '“Row”')), 'no table is a problem');
	pg_assert_same(array(), ws_flow_anchor_problems('row', ws_flow_anchor_normalize('row', array('table_id' => 4)), '“Row”'));

	$graph = array('nodes' => array(array('id' => 'row_1', 'kind' => 'row', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array())), 'edges' => array());
	$codes = array_column(ws_flow_graph_check($graph), 'code');
	pg_assert_true(in_array('bad_config', $codes, true), 'publishing a row box without a table is refused');
}

function test_workspace_flow_row_refusals()
{
	pg_assert_same('', ws_flow_row_refusal('', 0, array()), 'the table read, any column');
	pg_assert_same('', ws_flow_row_refusal('', 9, array(7, 9)), 'a column of the table');
	pg_assert_same(lang('That column is not in the table.'), ws_flow_row_refusal('', 8, array(7, 9)));
	pg_assert_same(lang('Choose a table you may read.'), ws_flow_row_refusal('refused', 0, array()), 'not reached: refused');
	pg_assert_same(lang('The owner of this table has left the team; it starts no flow until they are back.'), ws_flow_row_refusal('paused', 0, array()), 'owner gone: paused');
	pg_assert_same('paused', ws_wtable_flow_gate_for_test(false, 'edit'), 'the gate pauses for an owner gone');
}

// ws_wtable_flow_gate() is in wtable_shares.php, which needs the store: its
// rule copied here would drift, so the file is read for it.
function ws_wtable_flow_gate_for_test($present, $access)
{
	if (!function_exists('ws_wtable_flow_gate')) {
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_shares.php');
	}

	return ws_wtable_flow_gate(array('present' => $present, 'access' => $access, 'need' => 'view'));
}

function test_workspace_flow_row_event_matches_its_rule()
{
	$rule = array('type' => 'row', 'event' => 'workspace.wtable.row.updated', 'table_id' => 4, 'column_id' => 0);
	$payload = array('table_id' => 4, 'row_id' => 12, 'changed' => array(7));

	pg_assert_true(ws_events_rule_matches($rule, 'workspace.wtable.row.updated', $payload), 'a change of the table');
	pg_assert_false(ws_events_rule_matches($rule, 'workspace.wtable.row.created', $payload), 'another event');
	pg_assert_false(ws_events_rule_matches($rule, 'workspace.wtable.row.updated', array('table_id' => 5) + $payload), 'another table');
	pg_assert_true(ws_events_rule_matches(array('column_id' => 7) + $rule, 'workspace.wtable.row.updated', $payload), 'the column written');
	pg_assert_false(ws_events_rule_matches(array('column_id' => 9) + $rule, 'workspace.wtable.row.updated', $payload), 'a change that did not write the column');
	pg_assert_false(ws_events_rule_matches(array('table_id' => 0) + $rule, 'workspace.wtable.row.updated', array('table_id' => 0) + $payload), 'a rule without a table matches nothing');
	pg_assert_true(ws_events_rule_matches(array('type' => 'event', 'event' => 'workspace.wtable.row.updated'), 'workspace.wtable.row.updated', $payload), 'an event rule is matched by its event and filters, as before');
	pg_assert_false(ws_events_rule_matches(array('type' => 'join', 'event' => 'workspace.wtable.row.updated'), 'workspace.wtable.row.updated', $payload), 'a join rule is no event rule');
}

function test_workspace_flow_row_events_reach_the_waiting_rules()
{
	$start = pg_test_function_source('ws_events_start');
	$awaited = pg_test_function_source('ws_events_awaited');

	pg_assert_contains("array('event', 'row')", $start, 'ws_events_start() queues the row rules too');
	pg_assert_contains("array('event', 'row')", $awaited, 'ws_events_awaited() reads the events the row rules wait for');
	pg_assert_contains("'workspace.wtable.'", pg_test_function_source('ws_events_creator_sees'), 'the owner has to read the table when the row comes');
	pg_assert_contains('ws_wtable_flow_check', pg_test_function_source('ws_events_creator_sees'));
	pg_assert_contains("=== 'row'", pg_test_function_source('ws_flow_anchor_start'), 'the table is asked again as the run starts');
}

function test_workspace_flow_row_event_is_kept_where_the_store_writes()
{
	$announce = pg_test_function_source('ws_wtable_row_announce');
	$quiet = strpos($announce, 'ws_wtable_row_announce_quiet()');
	$event = strpos($announce, 'ws_event($event');
	$channels = strpos($announce, 'if (empty($channel_ids)) {');

	pg_assert_true(($quiet !== false) && ($event !== false) && ($quiet < $event), 'an import stays quiet for flows too');
	pg_assert_true(($channels !== false) && ($event < $channels), 'a table shared in no public channel still starts flows');
	pg_assert_contains('ws_events_awaited()', $announce, 'the row is read only while a flow waits or a webhook goes out');

	if (!function_exists('ws_wtable_row_flow_payload')) {
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_shares.php');
	}

	pg_assert_same(array('table_id' => 4, 'table_name' => 'Deals', 'row_id' => 12, 'actor_id' => 3, 'changed' => array(7, 9), 'cells' => array('7' => 'Acme', '9' => 1250)),
		ws_wtable_row_flow_payload(array('id' => '4', 'name' => 'Deals'), array('id' => 12, 'cells' => (object) array('7' => 'Acme', '9' => 1250)), '3', array('7', 9)));
}

function test_workspace_flow_row_outputs()
{
	pg_assert_same(array('table_id', 'table_name', 'row_id', 'actor_id'), array_column(ws_flow_row_outputs(array()), 'key'));
	pg_assert_same(array('table_id', 'table_name', 'row_id', 'actor_id', 'cell_7', 'cell_9'), array_column(ws_flow_trigger_outputs('row', array('columns' => ws_flow_row_test_columns())), 'key'));

	$cells = array_slice(ws_flow_row_outputs(ws_flow_row_test_columns()), 4);
	pg_assert_same(array(array('key' => 'cell_7', 'type' => 'text', 'label' => 'Customer'), array('key' => 'cell_9', 'type' => 'number', 'label' => 'Amount')), $cells, 'a cell by its column\'s name');

	foreach (ws_flow_row_outputs(ws_flow_row_test_columns()) as $output) {
		pg_assert_true((bool) preg_match('/^[a-z_][a-z0-9_]{0,31}$/', $output['key']), $output['key'] . ' fits {{trigger.<field>}}');
	}

	pg_assert_same('Acme', ws_flow_row_cell_text('Acme'));
	pg_assert_same('1250', ws_flow_row_cell_text(1250));
	pg_assert_same('1', ws_flow_row_cell_text(true), 'a ticked box');
	pg_assert_same('', ws_flow_row_cell_text(false), 'a box not ticked');
	pg_assert_same('', ws_flow_row_cell_text(null));
	pg_assert_same('31', ws_flow_row_cell_text(array('kind' => 'order', 'id' => 31)), 'a record link: the record\'s id');
	pg_assert_same('', ws_flow_row_cell_text(array('a' => 1)), 'anything else is empty');
}

function test_workspace_flow_row_trigger_values()
{
	$context = array('trigger' => 'row', 'node' => 'row_1', 'event' => 'workspace.wtable.row.updated', 'event_payload' => array(
		'table_id' => 4, 'table_name' => 'Deals', 'row_id' => 12, 'actor_id' => 3, 'changed' => array(7),
		'cells' => array('7' => 'Acme <b>', '9' => 1250, '11' => 'a column gone since'),
	));
	$names = array('columns' => ws_flow_row_test_columns());

	pg_assert_same(array('table_id' => '4', 'table_name' => 'Deals', 'row_id' => '12', 'actor_id' => '3', 'cell_7' => 'Acme <b>', 'cell_9' => '1250'),
		ws_step_trigger_values($context, true, $names), 'the declared values, the cell of a column gone left out');
	pg_assert_same(array('table_id' => '', 'table_name' => '', 'row_id' => '', 'actor_id' => '', 'cell_7' => '', 'cell_9' => ''),
		ws_step_trigger_values($context, false, $names), 'an owner who no longer reads the table gets nothing');

	unset($context['event_payload']);
	pg_assert_same(array('table_id' => '', 'table_name' => '', 'row_id' => '', 'actor_id' => '', 'cell_7' => '', 'cell_9' => ''),
		ws_step_trigger_values($context, true, $names), 'a payload left out for its size: every field empty');

	pg_assert_same(array(), ws_step_trigger_values(array('trigger' => 'manual') + $context, true, $names), 'a run by hand gives none');
}

function test_workspace_flow_row_references_are_checked()
{
	$graph = array(
		'nodes' => array(
			array('id' => 'row_1', 'kind' => 'row', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('table_id' => 4)),
			array('id' => 'post_1', 'kind' => 'post', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('channel_id' => 5, 'body' => '{{trigger.row_id}} {{trigger.event}}')),
		),
		'edges' => array(array('from' => 'row_1', 'to' => 'post_1', 'on' => 'next')),
	);
	$refs = array_values(array_filter(ws_flow_graph_check($graph), function ($problem) {
		return $problem['code'] === 'bad_reference';
	}));

	pg_assert_same(1, count($refs), 'row_id is given, event is not');
	pg_assert_contains('trigger.event', $refs[0]['message'] ?? json_encode($refs[0]));
}

function test_workspace_flow_row_start_reads()
{
	$config = array('table_id' => 4, 'what' => 'updated', 'column_id' => 9);
	$graph = array('nodes' => array(array('id' => 'row_1', 'kind' => 'row', 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config)), 'edges' => array());

	pg_assert_same(lang('When a table row changes'), ws_flow_row_said($config, ''), 'a table the person does not read is not named');
	pg_assert_same(lang(array('string' => '{var:1} in “{var:2}”', 'vars' => array(lang('A row is changed'), 'Deals'))) . ' · Amount', ws_flow_row_said($config, 'Deals', 'Amount'));
	pg_assert_same(array('text' => 'Deals words', 'rule' => null), ws_flow_start_rule($graph, function () { return true; }, function ($given) {
		return ($given['table_id'] === 4) ? 'Deals words' : '';
	}), 'the row box says its words itself, with no rule');
	pg_assert_same(array('text' => lang('When a table row changes'), 'rule' => null), ws_flow_start_rule($graph, function () { return true; }));
}
