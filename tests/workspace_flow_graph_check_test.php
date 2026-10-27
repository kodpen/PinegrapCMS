<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the reference check of a flow's graph (ws_flow_graph_check()):
 * a {{node.<id>.<field>}} in a box's texts names a box the flow has, one
 * that comes before the reader, and a value that box gives; one on a branch
 * that may not run before the reader is a warning, never a problem that
 * stops publishing.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A box of the graph.
function ws_flow_ref_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// A post box writing in channel 5.
function ws_flow_ref_test_post($id, $body = 'Hello')
{
	return ws_flow_ref_test_node($id, 'post', array('channel_id' => 5, 'body' => $body));
}

// An arrow of the graph.
function ws_flow_ref_test_edge($from, $to, $on)
{
	return array('from' => $from, 'to' => $to, 'on' => $on);
}

// The graph's reference findings: node => code, and whether it is a warning.
function ws_flow_ref_test_found($nodes, $edges)
{
	$out = array();

	foreach (ws_flow_graph_check(array('nodes' => $nodes, 'edges' => $edges)) as $problem) {
		if (in_array($problem['code'], array('bad_reference', 'skippable_reference'), true)) {
			$out[] = array($problem['node'], $problem['code'], !empty($problem['warning']));
		}
	}

	return $out;
}

function test_workspace_flow_reference_to_an_unknown_box()
{
	$found = ws_flow_ref_test_found(array(
		ws_flow_ref_test_node('start', 'manual'),
		ws_flow_ref_test_node('job', 'task', array('title' => 'Call {{node.ghost.text}}')),
	), array(ws_flow_ref_test_edge('start', 'job', 'next')));

	pg_assert_same(array(array('job', 'bad_reference', false)), $found);
}

function test_workspace_flow_reference_to_a_box_after_the_reader()
{
	$found = ws_flow_ref_test_found(array(
		ws_flow_ref_test_node('start', 'manual'),
		ws_flow_ref_test_post('post_1', 'Before: {{node.post_2.message_id}}'),
		ws_flow_ref_test_post('post_2', 'Self: {{node.post_2.text}}'),
	), array(
		ws_flow_ref_test_edge('start', 'post_1', 'next'),
		ws_flow_ref_test_edge('post_1', 'post_2', 'done'),
	));

	pg_assert_same(array(array('post_1', 'bad_reference', false), array('post_2', 'bad_reference', false)), $found, 'a box later on, and the box itself');
}

function test_workspace_flow_reference_to_a_field_the_box_does_not_give()
{
	$found = ws_flow_ref_test_found(array(
		ws_flow_ref_test_node('start', 'manual'),
		ws_flow_ref_test_post('post_1'),
		ws_flow_ref_test_post('post_2', '{{node.post_1.task_id}}'),
	), array(
		ws_flow_ref_test_edge('start', 'post_1', 'next'),
		ws_flow_ref_test_edge('post_1', 'post_2', 'done'),
	));

	pg_assert_same(array(array('post_2', 'bad_reference', false)), $found);
}

// A box only one outcome of a condition reaches may not have run when a box
// after the branches meet reads it: a warning, which does not stop
// publishing: ws_flow_problems() leaves it out, ws_flow_warnings() has it.
function test_workspace_flow_reference_to_a_branch_that_may_be_skipped()
{
	$nodes = array(
		ws_flow_ref_test_node('start', 'manual'),
		ws_flow_ref_test_node('check', 'condition', array('rule' => array('type' => 'workday'))),
		ws_flow_ref_test_post('post_yes'),
		ws_flow_ref_test_post('post_no'),
		ws_flow_ref_test_post('after', 'Posted {{node.post_yes.message_id}}'),
	);
	$edges = array(
		ws_flow_ref_test_edge('start', 'check', 'next'),
		ws_flow_ref_test_edge('check', 'post_yes', 'yes'),
		ws_flow_ref_test_edge('check', 'post_no', 'no'),
		ws_flow_ref_test_edge('post_yes', 'after', 'always'),
		ws_flow_ref_test_edge('post_no', 'after', 'always'),
	);

	pg_assert_same(array(array('after', 'skippable_reference', true)), ws_flow_ref_test_found($nodes, $edges));

	pg_assert_same(array('skippable_reference'), array_column(ws_flow_warnings(array('nodes' => $nodes, 'edges' => $edges)), 'code'), 'the warnings alone');

	// A step's failed outcome may be taken instead of done.
	$found = ws_flow_ref_test_found(array(
		ws_flow_ref_test_node('start', 'manual'),
		ws_flow_ref_test_post('post_1'),
		ws_flow_ref_test_post('post_2'),
		ws_flow_ref_test_post('after', '{{node.post_2.message_id}}'),
	), array(
		ws_flow_ref_test_edge('start', 'post_1', 'next'),
		ws_flow_ref_test_edge('post_1', 'post_2', 'done'),
		ws_flow_ref_test_edge('post_1', 'after', 'always'),
		ws_flow_ref_test_edge('post_2', 'after', 'always'),
	));

	pg_assert_same(array(array('after', 'skippable_reference', true)), $found, 'post_2 runs only when post_1 is done');
}

function test_workspace_flow_reference_valid()
{
	// One box after another, a count of a report, and two threads that both
	// always run meeting again.
	$found = ws_flow_ref_test_found(array(
		ws_flow_ref_test_node('start', 'manual'),
		ws_flow_ref_test_node('report_1', 'report', array('channel_id' => 5, 'title' => 'Day', 'metrics' => array(array('key' => 'tasks_open', 'param' => 4)))),
		ws_flow_ref_test_post('post_1'),
		ws_flow_ref_test_post('post_2', '{{node.post_1.message_id}} {{node.post_1.text}} {{node.report_1.tasks_open_4}} {{node.start.outcome}}'),
		ws_flow_ref_test_node('job', 'task', array('title' => 'Read {{node.post_2.message_id}}', 'description' => '{{node.report_1.message_id}}')),
	), array(
		ws_flow_ref_test_edge('start', 'report_1', 'next'),
		ws_flow_ref_test_edge('start', 'post_1', 'next'),
		ws_flow_ref_test_edge('post_1', 'post_2', 'done'),
		ws_flow_ref_test_edge('report_1', 'post_2', 'always'),
		ws_flow_ref_test_edge('post_2', 'job', 'done'),
	));

	pg_assert_same(array(), $found);
}
