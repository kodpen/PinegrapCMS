<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a flow box reads of the boxes before it
 * (includes/workspace/flow_runs.php): the values a settled box keeps in its
 * result, {{node.<id>.<field>}} filled from them, the boxes a run has not
 * settled yet read as empty, and, read from the source, the task box and the
 * scheduled actions' fill that use it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A step row of a run as ws_flow_run_steps() reads it.
function ws_flow_node_data_test_step($node_id, $status, $result)
{
	return array('node_id' => $node_id, 'status' => $status, 'result' => $result);
}

// The function's source without its comments.
function ws_flow_node_data_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The values of a result: its data, nothing for a plain text or no JSON.
function test_workspace_flow_node_data_result_data()
{
	pg_assert_same(array('task_id' => 4), ws_flow_result_data(ws_flow_result_pack('opened', array('task_id' => 4))));
	pg_assert_same(array(), ws_flow_result_data(ws_flow_result_pack('done')), 'no data');
	pg_assert_same(array(), ws_flow_result_data('plain words'), 'a text kept before');
	pg_assert_same(array(), ws_flow_result_data(null));
	pg_assert_same(array(), ws_flow_result_data('{"text":"x","data":"no array"}'), 'data that is no list');
}

// Only settled boxes are read: a box still at work may change its values.
function test_workspace_flow_node_data_values_of_settled_boxes()
{
	$values = ws_flow_node_values(array(
		ws_flow_node_data_test_step('start', 'done', ws_flow_result_pack('Started', array('started_by' => 7))),
		ws_flow_node_data_test_step('contract', 'done', ws_flow_result_pack('opened', array('task_id' => 12, 'title' => 'Sign'))),
		ws_flow_node_data_test_step('payment', 'waiting', ws_flow_result_pack('opened', array('task_id' => 13))),
		ws_flow_node_data_test_step('other', 'skipped', ws_flow_result_pack('Not reached in this run.')),
	));

	pg_assert_same(array(
		'start'    => array('started_by' => 7),
		'contract' => array('task_id' => 12, 'title' => 'Sign'),
		'other'    => array(),
	), $values);
}

// {{node.<id>.<field>}} becomes the value; an unknown box, field, a list or
// a box not settled becomes empty; other placeholders stay for their fill.
function test_workspace_flow_node_data_fill()
{
	$values = array(
		'contract' => array('task_id' => 12, 'title' => 'Sign the contract', 'done' => true, 'parts' => array('a', 'b'), 'customer' => array('name' => 'Ayşe')),
	);

	pg_assert_same('Check task 12: Sign the contract', ws_flow_fill_nodes('Check task {{node.contract.task_id}}: {{node.contract.title}}', $values));
	pg_assert_same('Ayşe', ws_flow_fill_nodes('{{node.contract.customer.name}}', $values), 'a field inside a field');
	pg_assert_same('[][][][]', ws_flow_fill_nodes('[{{node.payment.task_id}}][{{node.contract.missing}}][{{node.contract.parts}}][{{node.contract.done}}]', $values), 'empty when not there');
	pg_assert_same('{{date}} {{node.Bad id.x}} {{node.contract}}', ws_flow_fill_nodes('{{date}} {{node.Bad id.x}} {{node.contract}}', $values), 'not a node placeholder');
	pg_assert_same('plain', ws_flow_fill_nodes('plain', $values));
	pg_assert_same('{{date}}', ws_flow_fill_nodes('{{node.contract.title}}', array('contract' => array('title' => '{{date}}'))), 'a value is filled in as it is');
}

// The task box fills its title and description from the boxes before it and
// keeps the title it opened in its values; the start box keeps its starter.
function test_workspace_flow_node_data_task_box_fills()
{
	$task = ws_flow_node_data_test_source('ws_flow_step_task');

	pg_assert_true(strpos($task, 'ws_flow_node_values(ws_flow_run_steps(') !== false, 'reads the run\'s boxes');
	pg_assert_true(substr_count($task, 'ws_flow_fill_nodes(') >= 2, 'title and description');
	pg_assert_true(strpos($task, "'title' => \$title") !== false, 'keeps its title');

	$start = ws_flow_node_data_test_source('ws_flow_run_start');

	pg_assert_true(strpos($start, "'started_by'") !== false, 'the start box keeps its starter');

	$fill = ws_flow_node_data_test_source('ws_scheduled_fill');

	pg_assert_true(strpos($fill, "ws_flow_fill_nodes(") !== false, 'a step of a flow reads the boxes before it');
}
