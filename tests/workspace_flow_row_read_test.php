<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the box of the workspace's flows that reads one row of a
 * workflow table (kind row_get, includes/workspace/flow_row_get.php): its
 * kind and settings, what it lacks before publishing, the values it gives
 * the boxes after it (the row, found, a value per column of its table), a
 * row that is not there read as found 0 with empty cells, and what a reader
 * who may not change the flow reads. Read from the source: the table is
 * checked at view with the owner's rights, the box reads through the store
 * as the run reaches it, and a table whose owner has left the team pauses
 * it as the boxes that write rows are paused.
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
function workspace_flow_row_read_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The columns of a table, as ws_wtable_columns() gives them.
function workspace_flow_row_read_test_columns()
{
	return array(
		'3' => array('id' => 3, 'name' => 'Name', 'type' => 'text', 'options' => array()),
		'4' => array('id' => 4, 'name' => 'Count', 'type' => 'number', 'options' => array('decimals' => 1)),
		'5' => array('id' => 5, 'name' => 'Done', 'type' => 'checkbox', 'options' => array()),
		'6' => array('id' => 6, 'name' => 'Contact', 'type' => 'link', 'options' => array('kinds' => array('contact'))),
	);
}

// A step kind of its own beside the boxes that write rows: it leaves on done
// or failed, reads its row as a value of the run, keeps all its values from
// readers who may not change the flow, and a role-3 member may build it.
function test_workspace_flow_row_read_kind()
{
	$kinds = ws_step_kinds();

	pg_assert_true(isset($kinds['row_get']));
	pg_assert_same('row_get', $kinds['row_get']['type']);
	pg_assert_same(array('row'), $kinds['row_get']['texts']);
	pg_assert_same(array('row'), $kinds['row_get']['lines'], 'a row is one line');
	pg_assert_same(array('row_id', 'found'), array_column($kinds['row_get']['outputs'], 'key'));
	pg_assert_same(array('done', 'failed'), ws_flow_kinds()['row_get']['outcomes']);
	pg_assert_true(ws_flow_kinds()['row_get']['step']);
	pg_assert_false(in_array('row_get', ws_step_row_kinds(), true), 'it writes nothing');
	pg_assert_same(array('*'), ws_step_outputs_held()['row_get'], 'its row and cells stay with whoever may change the flow');
	pg_assert_true(in_array('row_get', ws_flow_member_kinds(), true), 'a role-3 member reads a table they reach');
	pg_assert_false(in_array('row_get', ws_flow_card_outbound_kinds(), true), 'reading a row sends nothing out');
	pg_assert_same('json', ws_step_fill_target('row_get', array('type' => 'row_get')), 'its row is filled as the change box\'s');
	pg_assert_contains('row_get', ws_flow_ai_catalogue_text(ws_flow_ai_catalogue_hints(ws_flow_kind_catalogue(), array())), 'Pinegrap AI drafts it');
}

// Its settings: the table and the row, as a change box names its row.
function test_workspace_flow_row_read_config()
{
	pg_assert_same(array('table_id' => 12, 'row' => '{{trigger.row_id}}'), ws_step_config_normalize('row_get', array('table_id' => '12', 'row' => " {{trigger.row_id}}\n", 'cells' => '{"3":"x"}')));
	pg_assert_same(array('table_id' => 0, 'row' => ''), ws_step_config_normalize('row_get', array()));

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');

	pg_assert_same(array('table_id', 'row', 'retry', 'retry_after'), array_column($catalogue['row_get']['settings'], 'key'), 'its settings, then the tries');

	$problems = function ($config) {
		return implode(' ', ws_step_config_problems('row_get', ws_step_config_normalize('row_get', $config), 'Read'));
	};

	pg_assert_same('', $problems(array('table_id' => 4, 'row' => '15')), 'a whole box');
	pg_assert_same('', $problems(array('table_id' => 4, 'row' => '{{node.find.value}}')), 'a row named by one value');
	pg_assert_contains('needs the table it reads', $problems(array('row' => '15')));
	pg_assert_contains('needs the row it reads', $problems(array('table_id' => 4)));
	pg_assert_contains('by its id or by one value', $problems(array('table_id' => 4, 'row' => 'row {{trigger.row_id}}')));
}

// The values it gives: the row and found, then one per column of its table,
// typed as a row start box types them.
function test_workspace_flow_row_read_outputs()
{
	$outputs = ws_step_outputs('row_get', array('table_id' => 4, 'row' => '15', 'columns' => array_values(workspace_flow_row_read_test_columns())));

	pg_assert_same(array('row_id', 'found', 'cell_3', 'cell_4', 'cell_5', 'cell_6'), array_column($outputs, 'key'));
	pg_assert_same(array('number', 'number', 'text', 'number', 'text', 'text'), array_column($outputs, 'type'));
	pg_assert_same('Count', $outputs[3]['label'], 'a cell by its column\'s name');
	pg_assert_same(array('row_id', 'found'), array_column(ws_step_outputs('row_get', array('table_id' => 0, 'row' => '')), 'key'), 'no table: no cells');
}

// A row found gives its id, found 1 and each cell as {{node.*}} fills it in;
// a row that is not there gives no id, found 0 and every cell empty.
function test_workspace_flow_row_read_data()
{
	$columns = workspace_flow_row_read_test_columns();
	$found = ws_step_row_get_data($columns, array('3' => 'Visits', '4' => 2.5, '5' => true, '6' => array('kind' => 'contact', 'id' => 12)), 15);

	pg_assert_same(array('row_id' => 15, 'found' => 1, 'cell_3' => 'Visits', 'cell_4' => '2.5', 'cell_5' => '1', 'cell_6' => '12'), $found);
	pg_assert_same(array('row_id' => 0, 'found' => 0, 'cell_3' => '', 'cell_4' => '', 'cell_5' => '', 'cell_6' => ''), ws_step_row_get_data($columns, null, 15));
	pg_assert_same('', ws_step_row_get_data($columns, array('3' => 'x'), 15)['cell_4'], 'an empty cell is empty');

	// As the run keeps them: a cell is the text it fills in, decimals and
	// all; no row is no id, found 0.
	$config = array('table_id' => 4, 'row' => '15', 'columns' => array_values($columns));
	$kept = ws_step_result_data('row_get', $config, array('ok' => true, 'row_id' => 15, 'data' => $found));

	pg_assert_same(array('row_id' => 15, 'found' => 1, 'cell_3' => 'Visits', 'cell_4' => '2.5', 'cell_5' => '1', 'cell_6' => '12'), $kept);

	$none = ws_step_result_data('row_get', $config, array('ok' => true, 'row_id' => 0, 'data' => ws_step_row_get_data($columns, null, 15)));

	pg_assert_same(array('row_id' => null, 'found' => 0, 'cell_3' => '', 'cell_4' => '', 'cell_5' => '', 'cell_6' => ''), $none);

	$values = ws_step_node_values(array(array('node_id' => 'get', 'status' => 'done', 'outcome' => 'done', 'result' => json_encode(array('text' => 'Read', 'data' => $none)))));

	pg_assert_same('0', $values['get']['found'], 'a later condition reads found 0');
	pg_assert_false(isset($values['get']['row_id']), 'no row id: {{node.get.row_id}} fills in empty');
}

// Whoever may not change the flow reads that a row was read, never the
// table's name, the row it names nor its cells.
function test_workspace_flow_row_read_kept_back()
{
	pg_assert_same('Read the row 15 of the table “Counters”.', ws_step_result_shown('row_get', 'done', 'Read the row 15 of the table “Counters”.', true));
	pg_assert_same('A row of a table was read.', ws_step_result_shown('row_get', 'done', 'Read the row 15 of the table “Counters”.', false));
	pg_assert_same('No row of the table was read.', ws_step_result_shown('row_get', 'failed', 'The table “Counters” is gone.', false));
	pg_assert_same(array(), ws_step_outputs_shown('row_get', array('row_id' => 15, 'found' => 1, 'cell_3' => 'Visits'), false));

	pg_assert_same(array('table_id' => 4, 'row' => ''), ws_flow_row_box_masked(array('table_id' => 4, 'row' => '{{trigger.row_id}}')), 'no cells added to a box that has none');

	$graph = ws_flow_graph_present(array('nodes' => array(array('id' => 'get', 'kind' => 'row_get', 'config' => array('table_id' => 4, 'row' => '15'))), 'edges' => array(), 'notes' => array()));

	pg_assert_same('', $graph['nodes'][0]['config']->row);
	pg_assert_same('Reads a row of the table “Counters”.', ws_step_row_get_text(array('table_name' => 'Counters')));
}

// A template's table setting asks only read reach for it, and a literal
// row is the template's own table's, cleared with it.
function test_workspace_flow_row_read_templates()
{
	pg_assert_same('', ws_flow_template_table_cleared('row_get', array('table_id' => 4, 'row' => '15'))['row']);
	pg_assert_same('{{trigger.row_id}}', ws_flow_template_table_cleared('row_get', array('table_id' => 4, 'row' => '{{trigger.row_id}}'))['row']);

	$template = array(
		'graph'    => array('nodes' => array(array('id' => 'get', 'kind' => 'row_get'))),
		'settings' => array(array('type' => 'table', 'targets' => array(array('get', 'table_id')))),
	);

	pg_assert_same('view', ws_flow_template_table_need($template));
}

// Read from the source: checked at view with the owner's rights at publish,
// run and try; a paused table makes it wait; it reads through the store
// with the formulas worked out; a missing row is done, not failed.
function test_workspace_flow_row_read_runs_as_the_owner()
{
	pg_assert_contains("ws_step_row_get_check(\$viewer, ws_step_config_normalize('row_get', \$config))", workspace_flow_row_read_test_source('ws_step_check'));

	$check = workspace_flow_row_read_test_source('ws_step_row_get_check');

	pg_assert_contains("ws_wtable_flow_check(\$viewer, (int) \$table['id'], 'view')", $check, 'read reach is enough');
	pg_assert_contains("if (\$gate === 'paused')", $check);
	pg_assert_contains('ws_step_row_names_row($row)', $check);
	pg_assert_false(strpos($check, 'ws_wtable_row(') !== false, 'a row id is not refused when the row is gone');

	$run = workspace_flow_row_read_test_source('ws_step_run');

	pg_assert_contains('$done = ws_step_row_get_do($owner, $action);', $run);
	pg_assert_contains("ws_step_row_wait(\$step, (int) (\$node['config']['table_id'] ?? 0), \$check['error'])", $run, 'a paused table makes the box wait');
	pg_assert_contains('$said = ws_step_row_get_text($action);', workspace_flow_row_read_test_source('ws_flow_box_try'), 'a box tried says what it would read');

	$do = workspace_flow_row_read_test_source('ws_step_row_get_do');

	pg_assert_contains('ws_wtable_row_present(ws_wtable_row_out($stored, $columns))', $do, 'formulas worked out as a read does');
	pg_assert_contains("'ok'     => true,", $do, 'a row not there is no failure');
}
