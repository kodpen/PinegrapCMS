<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a flow's problem texts name its boxes and the values they
 * read (ws_flow_box_names(), ws_flow_value_label()): a box by its title,
 * else by its kind's name, numbered when several untitled boxes share a
 * kind; a value as "Box › Value"; never a box id or a raw {{…}} token.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_loops.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A box of the graph.
function ws_flow_names_test_node($id, $kind, $config = array(), $label = '')
{
	return array('id' => $id, 'kind' => $kind, 'label' => $label, 'x' => 0, 'y' => 0, 'config' => $config);
}

function test_workspace_flow_box_names_title_kind_and_number()
{
	$nodes = array(
		ws_flow_names_test_node('start', 'manual'),
		ws_flow_names_test_node('post_1', 'post', array('channel_id' => 5, 'body' => 'A')),
		ws_flow_names_test_node('post_2', 'post', array('channel_id' => 5, 'body' => 'B')),
		ws_flow_names_test_node('post_3', 'post', array('channel_id' => 5, 'body' => 'C'), 'Tell the team'),
		ws_flow_names_test_node('end_1', 'end'),
		ws_flow_names_test_node('job', 'task', array('title' => 'Call the customer')),
	);
	$names = ws_flow_box_names($nodes);

	pg_assert_same('Started by hand', $names['start'], 'the only box of its kind: its kind name');
	pg_assert_same('Post a message (1)', $names['post_1'], 'two untitled boxes of one kind are numbered');
	pg_assert_same('Post a message (2)', $names['post_2']);
	pg_assert_same('Tell the team', $names['post_3'], 'a title wins and does not count');
	pg_assert_same('End', $names['end_1']);
	pg_assert_same('Call the customer', $names['job'], 'a task box by its task title, as the canvas');
}

function test_workspace_flow_box_names_title_with_values()
{
	$nodes = array(
		ws_flow_names_test_node('start', 'manual'),
		ws_flow_names_test_node('check', 'web_check', array('url' => 'https://example.com')),
		ws_flow_names_test_node('post_1', 'post', array('channel_id' => 5, 'body' => 'A'), 'Status {{node.check.outcome}}'),
		ws_flow_names_test_node('post_2', 'post', array('channel_id' => 5, 'body' => 'B'), 'Lost {{node.gone.text}}'),
	);
	$names = ws_flow_box_names($nodes);

	pg_assert_same('Status [Check a web address › Outcome]', $names['post_1'], 'a value in a title is named as the canvas names it');
	pg_assert_same('Post a message', $names['post_2'], 'a value no box gives: the kind name in place of the title');
}

function test_workspace_flow_value_label()
{
	$nodes = array(
		'start' => ws_flow_names_test_node('start', 'manual'),
		'check' => ws_flow_names_test_node('check', 'web_check', array('url' => 'https://example.com')),
	);
	$names = ws_flow_box_names($nodes);

	pg_assert_same('Check a web address › Outcome', ws_flow_value_label('check', 'outcome', $nodes, $names));
	pg_assert_same('Check a web address › Status code', ws_flow_value_label('check', 'status_code', $nodes, $names));
	pg_assert_same('Check a web address › nothing', ws_flow_value_label('check', 'nothing', $nodes, $names), 'a field the box does not give keeps its key');
	pg_assert_same('a value of a box that is not in the flow', ws_flow_value_label('web_check_1', 'outcome', $nodes, $names));
}

// The problems of a graph with untitled boxes, a box gone and values read:
// no text holds a box id or a {{…}} token.
function test_workspace_flow_problems_name_boxes_as_the_canvas()
{
	$graph = array(
		'nodes' => array(
			ws_flow_names_test_node('start', 'manual'),
			ws_flow_names_test_node('value_1', 'value', array('rule' => 'sum', 'a' => '{{node.web_check_1.outcome}}', 'b' => '')),
			ws_flow_names_test_node('post_1', 'post', array('channel_id' => 5, 'body' => 'Code {{node.web_check_1.status_code}} {{node.post_2.message_id}}')),
			ws_flow_names_test_node('post_2', 'post', array('channel_id' => 5, 'body' => 'B')),
			ws_flow_names_test_node('end_1', 'end'),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'post_1', 'on' => 'next'),
			array('from' => 'post_1', 'to' => 'post_2', 'on' => 'done'),
		),
	);
	$texts = array_column(ws_flow_graph_check($graph), 'text');

	pg_assert_true(count($texts) >= 3, 'the graph has problems: ' . implode(' | ', $texts));

	foreach ($texts as $text) {
		pg_assert_true(preg_match('/value_1|end_1|post_[12]\b|web_check_1|\{\{/', $text) !== 1, 'no id or token in: ' . $text);
	}

	pg_assert_true(in_array('No arrow from the start leads to the box End.', $texts, true), 'the end box by its kind: ' . implode(' | ', $texts));
	pg_assert_true(in_array('The box Post a message (1) reads a value of a box that is not in the flow.', $texts, true), 'a box gone: ' . implode(' | ', $texts));
	pg_assert_true(in_array('The box Post a message (1) reads Post a message (2) › Message, but the box Post a message (2) does not come before it.', $texts, true), 'a value by its box and label: ' . implode(' | ', $texts));
}
