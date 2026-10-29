<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the table boxes of the workspace's flows
 * (includes/workspace/steps.php): one adds a row to a workflow table, the
 * other changes cells of a row, as the run reaches them. Their kinds and
 * settings, the cells as a JSON object of column ids, the checks of the
 * table, its columns and the row, the run's values filled in as the inside
 * of JSON strings, what they give the boxes after them and what a reader
 * who may not change the flow reads. Read from the source: the row is
 * written through the table's store with the owner's rights, a box stopped
 * half way never writes twice, and a table whose owner has left the team
 * pauses the box until they are back.
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
function workspace_flow_row_write_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The columns of a table, as ws_wtable_columns() gives them.
function workspace_flow_row_write_test_columns()
{
	return array(
		'3' => array('id' => 3, 'name' => 'Customer', 'type' => 'text', 'options' => array()),
		'4' => array('id' => 4, 'name' => 'Amount', 'type' => 'money', 'options' => array('decimals' => 2, 'currency' => '')),
		'5' => array('id' => 5, 'name' => 'Stage', 'type' => 'status', 'options' => array('choices' => array(
			array('key' => 'new', 'label' => 'New', 'color' => ''),
			array('key' => 'won', 'label' => 'Won', 'color' => ''),
		))),
		'6' => array('id' => 6, 'name' => 'Contact', 'type' => 'link', 'options' => array('kinds' => array('contact'))),
		'7' => array('id' => 7, 'name' => 'Total', 'type' => 'formula', 'options' => array('expression' => 'Amount * 2')),
		'8' => array('id' => 8, 'name' => 'Count', 'type' => 'number', 'options' => array('decimals' => 0)),
	);
}

// Two step kinds of their own, no scheduled action's step: they leave on
// done or failed, give the row they wrote, fill the run's values inside
// JSON strings and are marked on a draft card as boxes that write a record.
function test_workspace_flow_row_write_kinds()
{
	$kinds = ws_step_kinds();

	pg_assert_same(array('row_add', 'row_set'), ws_step_row_kinds());
	pg_assert_same(array('row_add', 'row_set'), array($kinds['row_add']['type'], $kinds['row_set']['type']), 'no scheduled action\'s step');
	pg_assert_same(array('cells'), $kinds['row_add']['texts']);
	pg_assert_same(array('row', 'cells'), $kinds['row_set']['texts']);
	pg_assert_same(array('row'), $kinds['row_set']['lines'], 'the row is one line');

	foreach (ws_step_row_kinds() as $kind) {
		pg_assert_true(ws_flow_kinds()[$kind]['step'], $kind . ' is a step box');
		pg_assert_false(ws_flow_kinds()[$kind]['trigger'], $kind . ' starts no flow');
		pg_assert_same(array('done', 'failed'), ws_flow_kinds()[$kind]['outcomes'], $kind);
		pg_assert_same(array('row_id'), array_column(ws_step_outputs($kind, ws_step_config_normalize($kind, array())), 'key'), $kind . ' gives the row it wrote');
		pg_assert_same('json', ws_step_fill_target($kind, array()), $kind . ' fills values inside JSON strings');
		pg_assert_true(in_array($kind, ws_flow_card_outbound_kinds(), true), $kind . ' is marked on a draft card');
	}

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');

	pg_assert_same(array('table_id', 'cells', 'retry', 'retry_after'), array_column($catalogue['row_add']['settings'], 'key'), 'its settings, then the tries');
	pg_assert_same(array('table_id', 'row', 'cells', 'retry', 'retry_after'), array_column($catalogue['row_set']['settings'], 'key'));
	pg_assert_same(array('integer', 'text', 'text'), array_slice(array_column($catalogue['row_set']['settings'], 'type'), 0, 3), 'the row and the cells are texts');
	pg_assert_same(array('row', 'cells'), $catalogue['row_set']['texts']);
}

// The settings as kept: the table's id, the cells as JSON text (an object
// given is written as its JSON), the row of a change on one line; the row
// is no setting of a box that adds one.
function test_workspace_flow_row_write_config()
{
	pg_assert_same(array('table_id' => 12, 'cells' => '{"3":"Demir","8":2}'), ws_step_config_normalize('row_add', array('table_id' => '12', 'cells' => array('3' => 'Demir', '8' => 2), 'row' => '9', 'other' => 1)));
	pg_assert_same(array('table_id' => 0, 'row' => '{{trigger.row_id}}', 'cells' => '{"5": "won"}'), ws_step_config_normalize('row_set', array('table_id' => -3, 'row' => " {{trigger.row_id}}\n", 'cells' => ' {"5": "won"} ')));
	pg_assert_same(array('table_id' => 0, 'row' => '15', 'cells' => ''), ws_step_config_normalize('row_set', array('row' => 15)));
	pg_assert_same(array('table_id' => 0, 'row' => '', 'cells' => ''), ws_step_config_normalize('row_set', array('row' => array(15), 'cells' => true)));
	pg_assert_same(WS_STEP_ROW_CELLS_MAX, mb_strlen(ws_step_config_normalize('row_add', array('cells' => str_repeat('x', WS_STEP_ROW_CELLS_MAX + 10)))['cells']));
	pg_assert_same(array('table_id' => 4, 'cells' => '', 'retry' => 2, 'retry_after' => 30), ws_flow_config_normalize('row_add', array('table_id' => 4, 'retry' => 2, 'retry_after' => 30)), 'a table box may try again after a failure');
}

// The cells are a JSON object whose keys are column ids; a value of the run
// goes inside a string, and one written outside quotes leaves no object.
function test_workspace_flow_row_write_cells()
{
	pg_assert_same(array(), ws_step_row_cells(''), 'none');
	pg_assert_same(array(), ws_step_row_cells(' {} '));
	pg_assert_same(
		array('3' => '{{trigger.cell_2}}', '6' => array('kind' => 'contact', 'id' => '{{node.find.contact_id}}'), '8' => 2),
		ws_step_row_cells('{"3": "{{trigger.cell_2}}", "6": {"kind": "contact", "id": "{{node.find.contact_id}}"}, "8": 2}'),
		'values of the run inside strings, a record link as its object'
	);
	pg_assert_same(null, ws_step_row_cells('{"8": {{node.count.value}}}'), 'a value outside quotes leaves no object');
	pg_assert_same(null, ws_step_row_cells('["Demir"]'), 'a list');
	pg_assert_same(null, ws_step_row_cells('{"Customer": "Demir"}'), 'a column by its name');
	pg_assert_same(null, ws_step_row_cells('{"0": "Demir"}'), 'no column 0');
	pg_assert_same(null, ws_step_row_cells('{"3": "Demir"'), 'not JSON');
	pg_assert_same(null, ws_step_row_cells('{{trigger.cells}}'), 'one value for all the cells');
}

// A value the run gives is known only as the box runs; the row of a change
// is its id, or one value alone.
function test_workspace_flow_row_write_values_and_row()
{
	pg_assert_true(ws_step_row_value_in('Call {{trigger.cell_2}}'));
	pg_assert_true(ws_step_row_value_in(array('kind' => 'contact', 'id' => '{{node.find.contact_id}}')), 'a record link\'s id');
	pg_assert_false(ws_step_row_value_in('Call'));
	pg_assert_false(ws_step_row_value_in(12));
	pg_assert_false(ws_step_row_value_in('{{record}}'), 'a scheduled action\'s placeholder is no value of the run');

	pg_assert_same(15, ws_step_row_id(' 15 '));
	pg_assert_same(0, ws_step_row_id('0'));
	pg_assert_same(0, ws_step_row_id('15a'));
	pg_assert_same(0, ws_step_row_id(''));

	pg_assert_true(ws_step_row_names_row('15'));
	pg_assert_true(ws_step_row_names_row('{{trigger.row_id}}'));
	pg_assert_true(ws_step_row_names_row(' {{node.row_add_1.row_id}} '));
	pg_assert_false(ws_step_row_names_row('row {{trigger.row_id}}'), 'one value alone');
	pg_assert_false(ws_step_row_names_row('{{trigger.row_id}}{{trigger.row_id}}'));
	pg_assert_false(ws_step_row_names_row('the last one'));
}

// What a table box still lacks as far as its own settings go.
function test_workspace_flow_row_write_problems()
{
	$problems = function ($kind, $config) {
		return implode(' | ', ws_step_config_problems($kind, ws_step_config_normalize($kind, $config), 'Lead'));
	};

	pg_assert_same('', $problems('row_add', array('table_id' => 4, 'cells' => array('3' => 'Demir'))), 'a whole box');
	pg_assert_same('', $problems('row_set', array('table_id' => 4, 'row' => '{{trigger.row_id}}', 'cells' => array('5' => 'won'))));
	pg_assert_same(3, count(ws_step_config_problems('row_set', ws_step_config_normalize('row_set', array()), 'Lead')), 'no table, row or cells');
	pg_assert_contains('needs the table it writes in', $problems('row_add', array('cells' => array('3' => 'Demir'))));
	pg_assert_contains('needs at least one cell', $problems('row_add', array('table_id' => 4)));
	pg_assert_contains('not a JSON object', $problems('row_add', array('table_id' => 4, 'cells' => '{"3": {{trigger.cell_2}}}')));
	pg_assert_contains('needs the row it changes', $problems('row_set', array('table_id' => 4, 'cells' => array('5' => 'won'))));
	pg_assert_contains('by its id', $problems('row_set', array('table_id' => 4, 'row' => 'the last one', 'cells' => array('5' => 'won'))));
}

// The cells and the row are the texts the flow's check reads: a value of a
// box that does not come before, or that the flow does not have, is a
// problem there, before the flow is published.
function test_workspace_flow_row_write_references_checked()
{
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual'),
			array('id' => 'add', 'kind' => 'row_add', 'config' => array('table_id' => 4, 'cells' => array('3' => '{{node.later.text}}', '8' => '{{node.missing.value}}'))),
			array('id' => 'set', 'kind' => 'row_set', 'config' => array('table_id' => 4, 'row' => '{{node.add.row_id}}', 'cells' => array('5' => 'won'))),
			array('id' => 'later', 'kind' => 'end'),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'add'),
			array('from' => 'add', 'to' => 'set'),
			array('from' => 'set', 'to' => 'later'),
		),
	));
	$found = array();

	foreach (ws_flow_graph_check($graph) as $problem) {
		$found[] = $problem['code'] . ':' . $problem['node'];
	}

	pg_assert_same(array('bad_reference:add', 'bad_reference:add'), $found, 'a later box and a missing one read in the cells; the row read from the box before is fine');
}

// The cells against the table's columns: a column it has that keeps cells,
// and a value written as it is one the column takes; a value of the run is
// looked at once the box runs.
function test_workspace_flow_row_write_cells_refusal()
{
	$columns = workspace_flow_row_write_test_columns();

	pg_assert_same('', ws_step_row_cells_refusal($columns, array('3' => 'Demir', '4' => 1250, '5' => 'won', '6' => array('kind' => 'contact', 'id' => 7), '8' => '3')), 'values each column takes');
	pg_assert_same('', ws_step_row_cells_refusal($columns, array('8' => '{{node.count.value}}', '5' => '{{trigger.cell_5}}')), 'values of the run');
	pg_assert_contains('no column 9', ws_step_row_cells_refusal($columns, array('9' => 'x')));
	pg_assert_contains('“Total” is worked out', ws_step_row_cells_refusal($columns, array('7' => '{{node.count.value}}')), 'a formula never');
	pg_assert_contains('“Count” does not take that value', ws_step_row_cells_refusal($columns, array('8' => 'many')));
	pg_assert_contains('minor units', ws_step_row_cells_refusal($columns, array('4' => '12.50')), 'money in minor units');
	pg_assert_contains('“Stage”', ws_step_row_cells_refusal($columns, array('5' => 'Won')), 'a status by its key');
	pg_assert_contains('{"kind": "contact"', ws_step_row_cells_refusal($columns, array('6' => 'contact:7')), 'a record link as its object');
}

// Filled in, the cells stay a JSON object: each value whole as the column
// gets it, quotes and line breaks kept, nothing added to it; the row is the
// id the value gave.
function test_workspace_flow_row_write_fill_keeps_the_json()
{
	$values = array(
		WS_STEP_TRIGGER => array('row_id' => '15', 'cell_3' => "Demir \"Ltd\"\nIstanbul {{record}} <@user:5>"),
		'count'         => array('value' => '3', 'text' => '', 'outcome' => 'done', 'status' => 'done'),
	);
	$filled = ws_step_fill_action('row_set', array(
		'type'  => 'row_set',
		'row'   => '{{trigger.row_id}}',
		'cells' => '{"3": "{{trigger.cell_3}}", "8": "{{node.count.value}}", "6": {"kind": "contact", "id": "{{node.count.value}}"}}',
	), $values);

	pg_assert_same('15', $filled['row']);
	pg_assert_same(15, ws_step_row_id($filled['row']));
	pg_assert_same(
		array('3' => "Demir \"Ltd\"\nIstanbul {{record}} <@user:5>", '8' => '3', '6' => array('kind' => 'contact', 'id' => '3')),
		ws_step_row_cells($filled['cells'])
	);
	pg_assert_same('', ws_step_row_cells_refusal(workspace_flow_row_write_test_columns(), ws_step_row_cells($filled['cells'])), 'and the columns take them');
}

// Each gives the row it wrote, which only the flow's editors read, as only
// they read the sentence that names the table.
function test_workspace_flow_row_write_values_given_and_kept_back()
{
	foreach (ws_step_row_kinds() as $kind) {
		pg_assert_same(array('row_id' => 41), ws_step_result_data($kind, array(), array('ok' => true, 'text' => 'Done', 'row_id' => 41, 'data' => array())), $kind . ' gives its row');
		pg_assert_same(array('row_id' => null), ws_step_result_data($kind, array(), array('ok' => false, 'text' => 'No', 'row_id' => 0, 'data' => array())), $kind . ' without a row');
		pg_assert_same(array(), ws_step_outputs_shown($kind, array('row_id' => 41), false), $kind . ': nothing to a reader who may not change the flow');
		pg_assert_same(array('row_id' => 41), ws_step_outputs_shown($kind, array('row_id' => 41), true));
	}

	pg_assert_same('Added a row to the table “Leads”.', ws_step_result_shown('row_add', 'done', 'Added a row to the table “Leads”.', true), 'the editors read the sentence');
	pg_assert_same('A row was added to a table.', ws_step_result_shown('row_add', 'done', 'Added a row to the table “Leads”.', false), 'the others not the table');
	pg_assert_same('No row was added to the table.', ws_step_result_shown('row_add', 'failed', 'The table “Leads” has 5000 rows.', false));
	pg_assert_same('A row of a table was changed.', ws_step_result_shown('row_set', 'done', 'Changed Stage in a row of the table “Leads”.', false));
	pg_assert_same('The row of the table was not changed.', ws_step_result_shown('row_set', '', 'The owner of the table “Leads” has left the team.', false));
}

// What a box does and why the store refused it, in words.
function test_workspace_flow_row_write_words()
{
	$columns = workspace_flow_row_write_test_columns();

	pg_assert_same(
		'The table “Leads” refused a value. “Count”: Write a number. #9: The column is not in the table.',
		ws_step_row_write_refusal(array('ok' => false, 'error' => 'cells_invalid', 'errors' => array('8' => 'not_number', '9' => 'unknown_column')), $columns, 'Leads')
	);
	pg_assert_contains('5000 rows', ws_step_row_write_refusal(array('ok' => false, 'error' => 'too_many_rows'), $columns, 'Leads'));
	pg_assert_contains('may no longer change the table “Leads”', ws_step_row_write_refusal(array('ok' => false, 'error' => 'not_found'), $columns, 'Leads'));
	pg_assert_contains('changed the row', ws_step_row_write_refusal(array('ok' => false, 'error' => 'changed_meanwhile'), $columns, 'Leads'));

	pg_assert_same('Adds a row to the table “Leads”, filling in: Customer, Amount.', ws_step_row_text(array('type' => 'row_add', 'table_name' => 'Leads', 'columns' => array('Customer', 'Amount'))));
	pg_assert_same('Changes Stage in a row of the table “Leads”.', ws_step_row_text(array('type' => 'row_set', 'table_name' => 'Leads', 'columns' => array('Stage'))));
	pg_assert_contains('ws_step_row_text($action)', workspace_flow_row_write_test_source('ws_flow_box_try'), 'a box tried says what it would write');
}

// A box waiting for its table's owner keeps the table in its wait key,
// which no other wait reads as its own.
function test_workspace_flow_row_write_wait_key()
{
	pg_assert_same('wtable:12', ws_step_row_wait_key(12));
	pg_assert_same(12, ws_step_row_wait_table('wtable:12'));
	pg_assert_same(0, ws_step_row_wait_table('task:12'));
	pg_assert_same(0, ws_step_row_wait_table('wtable:x'));
	pg_assert_same(0, ws_flow_effect_task_id('wtable:12'), 'no task');
	pg_assert_same(null, ws_flow_effect_wait('wtable:12'), 'nothing a stopped box waits for again');
	pg_assert_same(0, ws_flow_retry_key_until('wtable:12'));
	pg_assert_same(0, ws_flow_delay_key_until('wtable:12'));
}

// Checked with the owner's rights at publish and at each run, written
// through the table's store with them, after the box wrote that it began;
// a table whose owner left pauses it, and it looks again hourly.
function test_workspace_flow_row_write_runs_through_the_store()
{
	pg_assert_contains('ws_step_row_check($viewer, $kind', workspace_flow_row_write_test_source('ws_step_check'), 'a table box checks itself');

	$check = workspace_flow_row_write_test_source('ws_step_row_check');

	pg_assert_contains("ws_wtable_flow_check(\$viewer, (int) \$table['id'], 'edit')", $check, 'a table the owner may change');
	pg_assert_contains("\$gate === 'paused'", $check, 'a table whose owner left the team pauses the box');
	pg_assert_contains('ws_step_row_cells_refusal($columns, $cells)', $check);

	$run = workspace_flow_row_write_test_source('ws_step_run');
	$write = strpos($run, 'ws_step_row_do($owner, $action)');

	pg_assert_true($write !== false, 'the run writes the row');
	pg_assert_true(strpos($run, 'WS_STEP_BEGAN . $kind') < $write, 'began is written first: a box stopped half way never writes twice');
	pg_assert_true(strpos($run, "ws_event_depth((int) (\$run['depth'] ?? 0))") < $write, 'the row\'s event carries the run\'s depth');
	pg_assert_contains("ws_step_row_wait(\$step, (int) (\$node['config']['table_id'] ?? 0), \$check['error'])", $run, 'a paused table makes the box wait');

	$do = workspace_flow_row_write_test_source('ws_step_row_do');

	pg_assert_contains('ws_wtable_row_create($owner, $table, ', $do, 'a row added through the store');
	pg_assert_contains("ws_wtable_row_update(\$owner, \$table, \$row, array('cells' => \$cells, 'revision' => (int) \$row['revision']))", $do, 'a row changed on its revision');
	pg_assert_contains("'changed_meanwhile'", $do, 'read again once when changed in between');
	pg_assert_false((bool) preg_match('~\bdb\(~', $do), 'nothing written past the store');
	pg_assert_same('', ws_step_starter_refusal(array('started_by' => 9, 'actor_id' => 3), 'row_add', array(), function () {
		return null;
	}), 'the owner\'s rights alone, as every box but a change');

	pg_assert_contains('ws_step_row_recheck($step)', workspace_flow_row_write_test_source('ws_flow_step_recheck'), 'a waiting table box looks again');

	$wait = workspace_flow_row_write_test_source('ws_step_row_wait');

	pg_assert_contains("status = 'waiting', effect = '', wait_event = ''", $wait, 'it forgets that it began: nothing was written');
	pg_assert_contains("AND status = 'running'", $wait, 'from running only');
	pg_assert_contains('time() + WS_FLOW_RECHECK', $wait, 'it looks again in an hour');

	$again = workspace_flow_row_write_test_source('ws_step_row_recheck');

	pg_assert_contains("=== 'paused'", $again, 'still away: it waits on');
	pg_assert_contains("status = 'waiting' AND wait_key = '", $again, 'ready again from waiting only');
	pg_assert_contains("(int) (\$step['attempt'] ?? 0) - 1", $again, 'a pause counts no try');
}
