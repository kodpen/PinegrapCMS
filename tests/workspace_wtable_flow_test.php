<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Run from a workflow table (includes/workspace/wtable_flow.php):
 * the rows compiled into a draft flow (order, the after column against the
 * row before, person and days, empty rows, a person who may not be given a
 * task), the columns the window opens with, and who may make one.
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

function workspace_wtable_flow_columns()
{
	return array(
		11 => array('type' => 'status'),
		12 => array('type' => 'text'),
		13 => array('type' => 'person'),
		14 => array('type' => 'number'),
		15 => array('type' => 'text'),
		16 => array('type' => 'number'),
	);
}

function workspace_wtable_flow_map()
{
	return array('step' => 12, 'person' => 13, 'days' => 14, 'after' => 16);
}

function workspace_wtable_flow_row($id, $position, $step, $person = null, $days = null, $after = null)
{
	return array('id' => $id, 'position' => $position, 'cells' => array(12 => $step, 13 => $person, 14 => $days, 16 => $after));
}

function workspace_wtable_flow_edges($graph)
{
	$edges = array();

	foreach ($graph['edges'] as $edge) {
		$edges[] = $edge['from'] . '>' . $edge['to'] . ':' . $edge['on'];
	}

	return $edges;
}

function workspace_wtable_flow_node($graph, $id)
{
	foreach ($graph['nodes'] as $node) {
		if ($node['id'] === $id) {
			return $node;
		}
	}

	return null;
}

function test_workspace_wtable_flow_rows_in_order_from_the_row_before()
{
	$rows = array(
		workspace_wtable_flow_row(7, 1, 'Call the customer'),
		workspace_wtable_flow_row(3, 2, 'Send the offer'),
		workspace_wtable_flow_row(9, 3, 'Sign'),
	);
	$compiled = ws_flow_from_wtable($rows, workspace_wtable_flow_map(), array());
	$graph = $compiled['graph'];

	pg_assert_same(3, $compiled['tasks']);
	pg_assert_same(array('start', 'row_7', 'row_3', 'row_9'), array_column($graph['nodes'], 'id'), 'a start box, then the rows in order');
	pg_assert_same('manual', $graph['nodes'][0]['kind']);
	pg_assert_same('task', $graph['nodes'][1]['kind']);
	pg_assert_same('Send the offer', $graph['nodes'][2]['config']['title']);
	pg_assert_same(array('start>row_7:next', 'row_7>row_3:done', 'row_3>row_9:done'), workspace_wtable_flow_edges($graph));
	pg_assert_same(array(), ws_flow_graph_check($graph), 'a flow with no problem');
	pg_assert_true($graph['nodes'][1]['x'] < $graph['nodes'][2]['x'], 'laid out from the start');
}

function test_workspace_wtable_flow_after_column()
{
	$rows = array(
		workspace_wtable_flow_row(1, 1, 'Open the account'),
		workspace_wtable_flow_row(2, 2, 'Send the welcome pack', null, null, 1),
		workspace_wtable_flow_row(3, 3, 'Book the training', null, null, 1),
		workspace_wtable_flow_row(4, 4, 'Ask for feedback', null, null, 6),
		workspace_wtable_flow_row(5, 5, 'Close', null, null, 5),
	);
	$compiled = ws_flow_from_wtable($rows, workspace_wtable_flow_map(), array());

	pg_assert_same(array('start>row_1:next', 'row_1>row_2:done', 'row_1>row_3:done', 'row_3>row_4:done', 'row_4>row_5:done'), workspace_wtable_flow_edges($compiled['graph']), 'two rows after row 1 run side by side');
	pg_assert_same(array(4, 5), $compiled['after_ignored'], 'a later row and the row itself fall back to the row before');
	pg_assert_same(array(), ws_flow_graph_check($compiled['graph']));
}

function test_workspace_wtable_flow_person_and_days()
{
	$rows = array(
		workspace_wtable_flow_row(1, 1, 'Prepare', 21, 3),
		workspace_wtable_flow_row(2, 2, 'Check', 99, 2.6),
		workspace_wtable_flow_row(3, 3, 'Ship', null, -4),
		workspace_wtable_flow_row(4, 4, 'Invoice', 21, 99999),
	);
	$compiled = ws_flow_from_wtable($rows, workspace_wtable_flow_map(), array(21));
	$graph = $compiled['graph'];

	pg_assert_same(array(21), workspace_wtable_flow_node($graph, 'row_1')['config']['assignees']);
	pg_assert_same(3, workspace_wtable_flow_node($graph, 'row_1')['config']['due_in']);
	pg_assert_same(array(), workspace_wtable_flow_node($graph, 'row_2')['config']['assignees'], 'a person who may not be given a task: no assignee');
	pg_assert_same(3, workspace_wtable_flow_node($graph, 'row_2')['config']['due_in'], 'days rounded, not cut');
	pg_assert_same(0, workspace_wtable_flow_node($graph, 'row_3')['config']['due_in']);
	pg_assert_same(3650, workspace_wtable_flow_node($graph, 'row_4')['config']['due_in']);
	pg_assert_same(array(2), $compiled['unknown'], 'the row is named');
	pg_assert_same(4, $compiled['tasks'], 'the row still becomes a task');
}

function test_workspace_wtable_flow_empty_rows_and_no_days_column()
{
	$rows = array(
		workspace_wtable_flow_row(1, 1, '  '),
		workspace_wtable_flow_row(2, 2, "First\n step", null, 5),
		workspace_wtable_flow_row(3, 3, null),
		workspace_wtable_flow_row(4, 4, 'Second', null, null, 3),
	);
	$map = array('step' => 12, 'person' => 0, 'days' => 0, 'after' => 16);
	$compiled = ws_flow_from_wtable($rows, $map, array());
	$graph = $compiled['graph'];

	pg_assert_same(array(1, 3), $compiled['empty'], 'rows without a step are skipped');
	pg_assert_same('First step', workspace_wtable_flow_node($graph, 'row_2')['config']['title'], 'one line');
	pg_assert_same('', workspace_wtable_flow_node($graph, 'row_2')['config']['due_in'], 'no days column: no due day');
	pg_assert_same(array('start>row_2:next', 'row_2>row_4:done'), workspace_wtable_flow_edges($graph), 'after an empty row: the row before');
	pg_assert_same(array(4), $compiled['after_ignored']);

	$none = ws_flow_from_wtable(array(workspace_wtable_flow_row(1, 1, '')), $map, array());
	pg_assert_same(0, $none['tasks']);
}

function test_workspace_wtable_flow_box_limit()
{
	$rows = array();

	for ($i = 1; $i <= WS_FLOW_MAX_NODES + 5; $i++) {
		$rows[] = workspace_wtable_flow_row($i, $i, 'Step ' . $i);
	}

	$compiled = ws_flow_from_wtable($rows, workspace_wtable_flow_map(), array());

	pg_assert_same(WS_FLOW_MAX_NODES - 1, $compiled['tasks']);
	pg_assert_same(6, $compiled['left_out']);
	pg_assert_same(WS_FLOW_MAX_NODES, count($compiled['graph']['nodes']));
}

function test_workspace_wtable_flow_prefill_by_type()
{
	pg_assert_same(array('step' => 12, 'person' => 13, 'days' => 14, 'after' => 16), ws_wtable_flow_prefill(workspace_wtable_flow_columns()), 'first match wins; the second number column is after');

	$one_number = array(1 => array('type' => 'number'), 2 => array('type' => 'text'));
	pg_assert_same(array('step' => 2, 'person' => 0, 'days' => 1, 'after' => 0), ws_wtable_flow_prefill($one_number));
	pg_assert_same(array('step' => 0, 'person' => 0, 'days' => 0, 'after' => 0), ws_wtable_flow_prefill(array()));
}

function test_workspace_wtable_flow_map_keeps_types()
{
	$columns = workspace_wtable_flow_columns();

	pg_assert_same(array('step' => 15, 'person' => 13, 'days' => 16, 'after' => 14), ws_wtable_flow_map($columns, array('step' => '15', 'person' => '13', 'days' => '16', 'after' => '14')));
	pg_assert_same(array('step' => 0, 'person' => 0, 'days' => 14, 'after' => 0), ws_wtable_flow_map($columns, array('step' => '13', 'person' => '12', 'days' => '14', 'after' => '14')), 'wrong type or taken: none');
	pg_assert_same(array('step' => 0, 'person' => 0, 'days' => 0, 'after' => 0), ws_wtable_flow_map($columns, 'x'));
	pg_assert_same(array('step' => 0, 'person' => 0, 'days' => 0, 'after' => 0), ws_wtable_flow_map($columns, array('step' => array(12))), 'an array is no column');
}

function test_workspace_wtable_flow_rights()
{
	pg_assert_same('', ws_wtable_flow_refusal('owner', true));
	pg_assert_same('', ws_wtable_flow_refusal('view', true), 'reading the table is enough');
	pg_assert_same('not_found', ws_wtable_flow_refusal('', true), 'a table not reached');
	pg_assert_same('not_found', ws_wtable_flow_refusal('', false));
	pg_assert_same('forbidden', ws_wtable_flow_refusal('edit', false), 'no flows for one who may not build them');
}
