<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the box of a flow that reads the rows of a workflow table
 * (rows_get, includes/workspace/flow_rows_get.php) and for "Read a row"
 * finding the first row that meets conditions (row_get by match): the
 * settings each keeps, the editor's problems, the check against the
 * table's columns, the values the box gives (the count, the totals of each
 * number column, the rows as a list a For each box walks), its sentences,
 * the values of the run filled into its conditions, and the places a kind
 * of box is named (catalogue, templates, members, tiers, masks). Read from
 * the source: the run reads the rows through the store, the condition
 * engine and the owner's view.
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
function workspace_flow_rows_get_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// A table's columns as the store gives them, by id.
function workspace_flow_rows_get_test_columns()
{
	$columns = array(
		'1' => array('Name', 'text', array()),
		'2' => array('Stock', 'number', array()),
		'3' => array('Price', 'money', array('decimals' => 2)),
		'4' => array('State', 'status', array('choices' => array(array('key' => 'open', 'label' => 'Open')))),
		'5' => array('Total', 'formula', array('expression' => 'Price * Stock')),
		'6' => array('Paid', 'checkbox', array()),
		'7' => array('Due', 'date', array()),
	);
	$out = array();

	foreach ($columns as $id => $column) {
		$out[$id] = array('id' => (int) $id, 'name' => $column[0], 'type' => $column[1], 'options' => ws_wtable_column_options_check($column[1], $column[2])['value']);
	}

	return $out;
}

// Conditions as the box keeps them, a JSON list.
function workspace_flow_rows_get_test_where($conditions)
{
	return (string) json_encode($conditions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// The kind, its settings and the words they take; a step of its own.
function test_workspace_flow_rows_get_kind()
{
	$kinds = ws_step_kinds();

	pg_assert_true(isset($kinds['rows_get']));
	pg_assert_same('Read rows of a table', $kinds['rows_get']['label']);
	pg_assert_same('rows_get', $kinds['rows_get']['type']);
	pg_assert_same(array('where'), $kinds['rows_get']['texts'], 'its conditions read the run\'s values');
	pg_assert_same(array(), $kinds['rows_get']['lines']);
	pg_assert_same(array('count', 'rows'), array_column($kinds['rows_get']['outputs'], 'key'));
	pg_assert_same(array('number', 'text'), array_column($kinds['rows_get']['outputs'], 'type'));
	pg_assert_same(array('row', 'where'), $kinds['row_get']['texts'], 'a read of one row reads values in its conditions too');
	pg_assert_true(ws_flow_kinds()['rows_get']['step']);

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');

	pg_assert_same(array('table_id', 'match', 'where', 'sort', 'dir', 'limit', 'retry', 'retry_after'), array_column($catalogue['rows_get']['settings'], 'key'));
	pg_assert_same(array('table_id', 'by', 'row', 'match', 'where', 'retry', 'retry_after'), array_column($catalogue['row_get']['settings'], 'key'));

	$values = array_column($catalogue['rows_get']['settings'], 'values', 'key');

	pg_assert_same(array('all', 'any'), $values['match']);
	pg_assert_same(array('asc', 'desc'), $values['dir']);
	pg_assert_same(array('id', 'position', 'last', 'number', 'key', 'match'), array_column($catalogue['row_get']['settings'], 'values', 'key')['by']);
	pg_assert_same(array('id', 'position', 'last', 'number', 'key', 'match'), ws_step_row_get_bys());
	pg_assert_same(array('id', 'position', 'last', 'number', 'key'), ws_step_row_bys(), 'a change finds no row by conditions');
	pg_assert_same(50, WS_FLOW_LOOP_ITEMS_MAX, 'the list holds at most what a For each box walks');
}

// Its settings: the table, how the conditions join, the conditions as a
// JSON list (one given as a list is written as its JSON), the sort column
// and way, and the rows of its list, 1 to 50.
function test_workspace_flow_rows_get_config()
{
	$where = array(array('column' => 2, 'op' => 'lt', 'value' => '5'));

	pg_assert_same(array('table_id' => 0, 'match' => 'all', 'where' => '', 'sort' => 0, 'dir' => 'asc', 'limit' => 50), ws_step_config_normalize('rows_get', array()));
	pg_assert_same(array('table_id' => 4, 'match' => 'any', 'where' => '[{"column":2,"op":"lt","value":"5"}]', 'sort' => 3, 'dir' => 'desc', 'limit' => 10),
		ws_step_config_normalize('rows_get', array('table_id' => '4', 'match' => 'any', 'where' => $where, 'sort' => '3', 'dir' => 'desc', 'limit' => '10')));
	pg_assert_same('[]', ws_step_config_normalize('rows_get', array('where' => ' [] '))['where'], 'a text is kept as written, trimmed');

	$odd = ws_step_config_normalize('rows_get', array('match' => 'most', 'dir' => 'up', 'sort' => -2, 'limit' => 500));
	pg_assert_same(array('all', 'asc', 0, 50), array($odd['match'], $odd['dir'], $odd['sort'], $odd['limit']));
	pg_assert_same(1, ws_step_config_normalize('rows_get', array('limit' => '1'))['limit']);
	pg_assert_same(50, ws_step_config_normalize('rows_get', array('limit' => 0))['limit'], 'no limit is the most');

	// Read a row finds the first row that meets conditions; any other way
	// keeps none.
	pg_assert_same(array('table_id' => 5, 'by' => 'match', 'row' => '', 'match' => 'any', 'where' => '[{"column":2,"op":"lt","value":"5"}]'),
		ws_step_config_normalize('row_get', array('table_id' => 5, 'by' => 'match', 'row' => '3', 'match' => 'any', 'where' => $where)));
	pg_assert_same(array('table_id' => 5, 'by' => 'position', 'row' => '1', 'match' => 'all', 'where' => ''),
		ws_step_config_normalize('row_get', array('table_id' => 5, 'by' => 'position', 'row' => '1', 'where' => $where)));
	pg_assert_same('id', ws_step_config_normalize('row_set', array('by' => 'match'))['by'], 'a change finds no row by conditions');
}

// The editor's problems: the table, a JSON list of at most ten conditions,
// each with its column, its comparison and, unless it asks for an empty
// cell, a value typed or one value alone.
function test_workspace_flow_rows_get_problems()
{
	$problems = function ($config, $kind = 'rows_get') {
		return ws_step_config_problems($kind, ws_step_config_normalize($kind, $config), 'Stock');
	};
	$where = 'workspace_flow_rows_get_test_where';

	pg_assert_same(array(), $problems(array('table_id' => 4)), 'no condition: every row');
	pg_assert_same(array(), $problems(array('table_id' => 4, 'where' => $where(array(
		array('column' => 2, 'op' => 'lt', 'value' => '5'),
		array('column' => 1, 'op' => 'eq', 'value' => '{{trigger.cell_3}}'),
		array('column' => 1, 'op' => 'starts', 'value' => '{{node.get.cell_1|Un}}'),
		array('column' => 3, 'op' => 'empty', 'value' => ''),
	)))));
	pg_assert_same(array('The box Stock needs the table it reads.'), $problems(array()));
	pg_assert_same(array('The conditions of the box Stock are not a JSON list, each condition a column, a comparison and a value.'), $problems(array('table_id' => 4, 'where' => '{"column":2}')));
	pg_assert_same(array('The box Stock has more than 10 conditions.'), $problems(array('table_id' => 4, 'where' => $where(array_fill(0, 11, array('column' => 2, 'op' => 'empty'))))));
	pg_assert_same(array(
		'The box Stock, condition 1: Choose its column.',
		'The box Stock, condition 2: Choose how it compares.',
		'The box Stock, condition 3: Give the value it compares with.',
		'The box Stock, condition 4: Give one value alone, or type the value: not both.',
	), $problems(array('table_id' => 4, 'where' => $where(array(
		array('op' => 'eq', 'value' => 'x'),
		array('column' => 2, 'op' => 'like', 'value' => 'x'),
		array('column' => 2, 'op' => 'gt', 'value' => ' '),
		array('column' => 1, 'op' => 'eq', 'value' => 'Mr {{trigger.cell_1}}'),
	)))));

	pg_assert_same(array('The box Stock finds its row by conditions, but has none.'), $problems(array('table_id' => 4, 'by' => 'match'), 'row_get'));
	pg_assert_same(array(), $problems(array('table_id' => 4, 'by' => 'match', 'where' => $where(array(array('column' => 1, 'op' => 'eq', 'value' => 'Un')))), 'row_get'));
	pg_assert_same(array('The box Stock, condition 1: Choose its column.'), $problems(array('table_id' => 4, 'by' => 'match', 'where' => $where(array(array('op' => 'eq', 'value' => 'Un')))), 'row_get'));
}

// Checked against the table's columns at publish, each run and a try: a
// column the table has, a comparison its type takes, a typed value that
// fits it (a value of the run is known only as the box runs), a sort
// column the table has.
function test_workspace_flow_rows_get_refusal()
{
	$columns = workspace_flow_rows_get_test_columns();
	$refusal = function ($conditions, $sort = 0, $needs = false) use ($columns) {
		return ws_step_where_refusal($columns, array('where' => workspace_flow_rows_get_test_where($conditions), 'sort' => $sort), $needs);
	};

	pg_assert_same('', $refusal(array()));
	pg_assert_same('', $refusal(array(array('column' => 2, 'op' => 'gte', 'value' => '{{trigger.cell_9}}')), 3), 'a value of the run');
	pg_assert_same('', $refusal(array(array('column' => 3, 'op' => 'lt', 'value' => '1250'), array('column' => 4, 'op' => 'eq', 'value' => 'open'), array('column' => 6, 'op' => 'eq', 'value' => '0'), array('column' => 7, 'op' => 'lte', 'value' => '2026-11-09'))));
	pg_assert_same('Condition 1: It names a column the table no longer has.', $refusal(array(array('column' => 99, 'op' => 'empty'))));
	pg_assert_same('Condition 1: The column “Name” cannot be compared with “is less than”.', $refusal(array(array('column' => 1, 'op' => 'lt', 'value' => 'b'))));
	pg_assert_same('Condition 2: The column “Stock” holds numbers, written as 12.5: “beş” is not one.', $refusal(array(array('column' => 1, 'op' => 'empty'), array('column' => 2, 'op' => 'eq', 'value' => 'beş'))));
	pg_assert_same('Condition 1: The column “Price” holds money in minor units, 1250 for 12.50: “12.50” is not a whole number.', $refusal(array(array('column' => 3, 'op' => 'eq', 'value' => '12.50'))));
	pg_assert_same('Condition 1: The column “Due” holds dates, written as 2026-11-09 or 2026-11-09 14:30: “9.11.2026” is not one.', $refusal(array(array('column' => 7, 'op' => 'eq', 'value' => '9.11.2026'))));
	pg_assert_same('Condition 1: The column “State” has no status with the key “Open”.', $refusal(array(array('column' => 4, 'op' => 'eq', 'value' => 'Open'))));
	pg_assert_same('Condition 1: The column “Paid” is a checkbox: compare it with 1 (ticked) or 0 (not ticked).', $refusal(array(array('column' => 6, 'op' => 'eq', 'value' => 'yes'))));
	pg_assert_same('The box sorts by a column the table no longer has.', $refusal(array(), 42));
	pg_assert_same('The conditions are not a JSON list of conditions.', ws_step_where_refusal($columns, array('where' => '{'), false));
	pg_assert_same('The box finds its row by conditions, but has none.', $refusal(array(), 0, true));
	pg_assert_same('Condition 1: Choose its column.', $refusal(array(array('op' => 'eq', 'value' => 'x'))));
}

// The values it gives: the count and the list first, then the sum, the
// average, the smallest and the largest value of each number, money and
// formula column of its table.
function test_workspace_flow_rows_get_outputs()
{
	$outputs = ws_step_outputs('rows_get', array('table_id' => 4, 'columns' => array_values(workspace_flow_rows_get_test_columns())));

	pg_assert_same(array('count', 'rows', 'sum_2', 'avg_2', 'min_2', 'max_2', 'sum_3', 'avg_3', 'min_3', 'max_3', 'sum_5', 'avg_5', 'min_5', 'max_5'), array_column($outputs, 'key'));
	pg_assert_same(array('Rows found', 'The rows as a list', 'Sum of Stock', 'Average of Stock', 'Smallest Stock', 'Largest Stock'), array_slice(array_column($outputs, 'label'), 0, 6));
	pg_assert_same(array('number'), array_values(array_unique(array_column(array_slice($outputs, 2), 'type'))));
	pg_assert_same(array('count', 'rows'), array_column(ws_step_outputs('rows_get', array('columns' => array())), 'key'));
	pg_assert_true(in_array('*', ws_step_outputs_held()['rows_get'], true), 'held from whoever may not change the flow');
}

// What the box gives once it read the rows: the count of every row that
// met the conditions; each total as the text it fills in (money in minor
// units, its average rounded; none empty); the rows of its list with their
// id, number and cells, as a row read gives them, whole rows only while
// they fit what a For each box takes.
function test_workspace_flow_rows_get_data()
{
	$columns = workspace_flow_rows_get_test_columns();
	$rows = array();

	foreach (array(array('1' => 'Un', '2' => 4, '3' => 1250, '6' => true), array('1' => 'Şeker "toz"', '2' => 1, '3' => 995)) as $place => $cells) {
		$rows[] = ws_wtable_row_out(array('id' => 30 + $place, 'position' => $place + 1, 'revision' => 1, 'cells' => json_encode((object) $cells),
			'created_by' => 1, 'created_at' => 0, 'updated_by' => 1, 'updated_at' => 0), $columns);
	}

	$items = ws_step_rows_get_items($columns, $rows);

	pg_assert_same(array('row_id' => 30, 'position' => 1, 'cell_1' => 'Un', 'cell_2' => '4', 'cell_3' => '1250', 'cell_4' => '', 'cell_5' => '50', 'cell_6' => '1', 'cell_7' => ''), $items[0]);
	pg_assert_same('Şeker "toz"', $items[1]['cell_1']);

	$list = ws_step_rows_get_list($items, WS_FLOW_LOOP_ITEMS_BYTES);
	pg_assert_same(2, $list['kept']);
	pg_assert_same($items, json_decode($list['json'], true));

	$cut = ws_step_rows_get_list($items, strlen((string) json_encode(array($items[0]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) + 5);
	pg_assert_same(1, $cut['kept'], 'whole rows only');
	pg_assert_same(array($items[0]), json_decode($cut['json'], true));
	pg_assert_same(array('json' => '[]', 'kept' => 0), ws_step_rows_get_list(array(), 100));

	$data = ws_step_rows_get_data($columns, 7, ws_wtable_rows_totals($columns, $rows), $list['json']);

	pg_assert_same(7, $data['count'], 'every row that met the conditions');
	pg_assert_same($list['json'], $data['rows']);
	pg_assert_same(array('5', '2.5', '1', '4'), array($data['sum_2'], $data['avg_2'], $data['min_2'], $data['max_2']));
	pg_assert_same(array('2245', '1123', '995', '1250'), array($data['sum_3'], $data['avg_3'], $data['min_3'], $data['max_3']), 'money in minor units, 1122.5 rounded');
	pg_assert_same(array('59.95', '29.975', '9.95', '50'), array($data['sum_5'], $data['avg_5'], $data['min_5'], $data['max_5']));

	$none = ws_step_rows_get_data($columns, 0, ws_wtable_rows_totals($columns, array()), '[]');
	pg_assert_same(array(0, '[]', '0', '', '', ''), array($none['count'], $none['rows'], $none['sum_3'], $none['avg_3'], $none['min_3'], $none['max_3']), 'nothing sums to nought');

	// As the run keeps them: the count a number, the totals as text, so a
	// sum with decimals is never cut to a whole number.
	$kept = ws_step_result_data('rows_get', array('columns' => array_values($columns)), array('ok' => true, 'data' => $data));
	pg_assert_same(7, $kept['count']);
	pg_assert_same('2.5', $kept['avg_2']);
	pg_assert_same('59.95', $kept['sum_5']);
	pg_assert_same($list['json'], $kept['rows']);
}

// The run's values go into the conditions as the inside of their JSON
// strings, so a value with quotes or braces is read back as it was.
function test_workspace_flow_rows_get_fill()
{
	$action = array('type' => 'rows_get', 'where' => workspace_flow_rows_get_test_where(array(array('column' => 1, 'op' => 'eq', 'value' => '{{node.get.cell_1}}'), array('column' => 2, 'op' => 'lt', 'value' => '{{trigger.cell_9|5}}'))));
	$values = array('get' => array('cell_1' => 'Şeker "toz" {{x}}'), WS_STEP_TRIGGER => array());
	$filled = ws_step_fill_action('rows_get', $action, $values);
	$list = ws_wtable_where_list($filled['where']);

	pg_assert_same('json', ws_step_fill_target('rows_get', $action));
	pg_assert_same("Şeker \"toz\" {{x}}", $list[0]['value'], 'the value as the box gave it');
	pg_assert_same('5', $list[1]['value'], 'a fallback for an empty value');

	$tried = ws_step_try_texts('rows_get', $action, array(WS_STEP_TRIGGER => array()));
	pg_assert_same(array('{{node.get.cell_1}}'), $tried['empty'], 'a try names the value a run would leave empty');
	pg_assert_same(array($action['where']), ws_flow_node_texts(array('kind' => 'rows_get', 'config' => $action)), 'the reference check reads the conditions');
}

// Its sentences: what it would read, and what it read.
function test_workspace_flow_rows_get_sentences()
{
	pg_assert_same('Reads every row of the table “Stock”, at most 50 in its list.', ws_step_rows_get_text(array('table_name' => 'Stock', 'where' => '', 'limit' => 50)));
	pg_assert_same('Reads the rows of the table “Stock” that meet its conditions, at most 5 in its list.', ws_step_rows_get_text(array('table_name' => 'Stock', 'where' => '[{"column":2,"op":"empty","value":""}]', 'limit' => 5)));
	pg_assert_same('Rows of the table “Stock” that met the conditions: 3.', ws_step_rows_get_said('Stock', 3, 3, true));
	pg_assert_same('Rows of the table “Stock”: 0.', ws_step_rows_get_said('Stock', 0, 0, false));
	pg_assert_same('Rows of the table “Stock” that met the conditions: 70. The list holds the first 50 of them.', ws_step_rows_get_said('Stock', 70, 50, true));

	pg_assert_same('Rows of a table were read.', ws_step_result_shown('rows_get', 'done', 'Rows of the table “Stock”: 3.', false));
	pg_assert_same('No rows of the table were read.', ws_step_result_shown('rows_get', 'failed', 'The table “Stock” is gone.', false));
	pg_assert_same('Rows of the table “Stock”: 3.', ws_step_result_shown('rows_get', 'done', 'Rows of the table “Stock”: 3.', true));

	// Read a row by conditions.
	pg_assert_same('Reads the first row of the table “Stock” that meets its conditions.', ws_step_row_get_text(array('table_name' => 'Stock', 'by' => 'match', 'row' => '')));
	pg_assert_same('No row of the table “Stock” meets the conditions.', ws_step_row_missing_text('match', '', 'Stock'));
	pg_assert_same('', ws_step_row_refusal('match', ''), 'its conditions are checked on their own');
	pg_assert_same(array(), ws_step_row_problem('row_get', array('by' => 'match', 'row' => ''), 'UN'), 'no row text to ask for');
}

// A reader who may not change the flow sees the columns and comparisons,
// never the values, which may be a customer's data; a template's table
// takes its conditions and sort with it.
function test_workspace_flow_rows_get_masks_and_templates()
{
	$where = workspace_flow_rows_get_test_where(array(array('column' => 1, 'op' => 'eq', 'value' => 'Ayşe'), array('column' => 2, 'op' => 'empty', 'value' => '')));
	$masked = ws_flow_row_box_masked(array('table_id' => 4, 'match' => 'all', 'where' => $where, 'sort' => 2, 'dir' => 'asc', 'limit' => 5));

	pg_assert_same('[{"column":1,"op":"eq","value":""},{"column":2,"op":"empty","value":""}]', $masked['where']);
	pg_assert_same('', ws_flow_row_box_masked(array('where' => '{'))['where'], 'a text the server cannot read keeps nothing');
	pg_assert_same('', ws_flow_row_box_masked(array('table_id' => 4, 'by' => 'match', 'row' => '', 'match' => 'all', 'where' => '[]'))['row']);

	$graph = ws_flow_graph_present(array('nodes' => array(array('id' => 'rows', 'kind' => 'rows_get', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('table_id' => 4, 'where' => $where))), 'edges' => array(), 'notes' => array()), false);
	pg_assert_same('[{"column":1,"op":"eq","value":""},{"column":2,"op":"empty","value":""}]', $graph['nodes'][0]['config']->where);

	$cleared = ws_flow_template_table_cleared('rows_get', array('table_id' => 4, 'match' => 'any', 'where' => $where, 'sort' => 2, 'dir' => 'desc', 'limit' => 5));
	pg_assert_same(array('', 0, 'any', 'desc', 5), array($cleared['where'], $cleared['sort'], $cleared['match'], $cleared['dir'], $cleared['limit']));
	pg_assert_same('', ws_flow_template_table_cleared('row_get', array('table_id' => 4, 'by' => 'match', 'row' => '', 'where' => $where))['where']);
	pg_assert_same(array('table_id' => 'table'), ws_flow_template_setting_keys()['rows_get']);
	pg_assert_same('view', ws_flow_template_table_need(array('graph' => array('nodes' => array(array('id' => 'rows', 'kind' => 'rows_get'))), 'settings' => array(array('type' => 'table', 'targets' => array(array('rows', 'table_id')))))));
}

// A role-3 member builds it as they build a read of one row; an agent's
// tier reads it as a read.
function test_workspace_flow_rows_get_members_and_tiers()
{
	pg_assert_true(in_array('rows_get', ws_flow_member_kinds(), true));
	pg_assert_same(WS_TIER_READ, ws_flow_box_tiers()['rows_get']);

	$hints = array_column(ws_flow_ai_catalogue_hints(ws_flow_kind_catalogue(), array()), 'hint', 'kind');

	pg_assert_contains('reads the rows of a workflow table that meet its conditions', (string) ($hints['rows_get'] ?? ''), 'Pinegrap AI learns the box');
	pg_assert_contains('match (the first row that meets the conditions in where)', (string) ($hints['row_get'] ?? ''));
}

// Read from the source: the box reads the rows through the store with the
// owner's view, picks them with the condition engine, totals and sorts
// them, and runs and is tried as a step of its own; Read a row finds its
// row by conditions the same way.
function test_workspace_flow_rows_get_reads_through_the_store()
{
	$check = workspace_flow_rows_get_test_source('ws_step_rows_get_check');

	pg_assert_contains("ws_wtable_flow_check(\$viewer, (int) \$table['id'], 'view')", $check, 'read with the owner\'s view');
	pg_assert_contains('ws_step_where_refusal(', $check);

	$do = workspace_flow_rows_get_test_source('ws_step_rows_get_do');

	foreach (array('ws_wtable_rows(', 'ws_wtable_rows_where(', 'ws_wtable_rows_totals(', 'ws_wtable_rows_sorted(', 'ws_step_rows_get_list(', 'WS_FLOW_LOOP_ITEMS_BYTES') as $call) {
		pg_assert_contains($call, $do);
	}

	pg_assert_contains("ws_step_rows_get_check(\$viewer, ws_step_config_normalize('rows_get', \$config))", workspace_flow_rows_get_test_source('ws_step_check'));
	pg_assert_contains('ws_step_rows_get_do($owner, $action)', workspace_flow_rows_get_test_source('ws_step_run'));
	pg_assert_contains('ws_step_rows_get_text($action)', workspace_flow_rows_get_test_source('ws_flow_box_try'));
	pg_assert_contains('ws_step_where_refusal(', workspace_flow_rows_get_test_source('ws_step_row_get_check'));
	pg_assert_contains('ws_wtable_rows_where(', workspace_flow_rows_get_test_source('ws_step_row_match'));
	pg_assert_contains('ws_step_row_match(', workspace_flow_rows_get_test_source('ws_step_row_get_do'));

	$api = '';

	foreach (ws_api_routes() as $route) {
		foreach ((array) ($route['params'] ?? array()) as $field) {
			if ((($field['name'] ?? '') === 'nodes') && (strpos((string) ($field['description'] ?? ''), 'row_get') !== false)) {
				$api = (string) $field['description'];
			}
		}
	}

	pg_assert_contains('rows_get, which reads the rows of a workflow table', $api, 'the API names the box');
	pg_assert_contains('match, the first row that meets conditions', $api);
}
