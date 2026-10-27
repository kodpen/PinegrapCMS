<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a scheduled action drawn as a flow graph whose boxes run it the
 * same way (ws_flow_graph_from_scheduled(), flow_templates.php): the start
 * box of each "when", conditions on the yes arrow, the step's box, the
 * follow-ups on the arrows of their outcomes, the step starting itself again
 * as its own retry, {{result}} read from the step box, money written back
 * in major units, and what is told rather than drawn. Every graph passes
 * the publish check of its own settings.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_templates.php');

// A node of the graph by its id.
function ws_flow_from_scheduled_test_node($graph, $id)
{
	foreach ($graph['nodes'] as $node) {
		if ($node['id'] === $id) {
			return $node;
		}
	}

	return null;
}

// The arrows as from>to:on.
function ws_flow_from_scheduled_test_edges($graph)
{
	return array_map(function ($edge) {
		return $edge['from'] . '>' . $edge['to'] . ':' . $edge['on'];
	}, $graph['edges']);
}

// Every Monday at 9, if there are open tasks, a post; an e-mail when it
// fails, a notice whatever happens.
function ws_flow_from_scheduled_test_data()
{
	return array(
		'name'   => 'Monday tasks',
		'rules'  => array(
			array('type' => 'at', 'date' => '2026-11-02', 'time' => '09:00', 'repeat' => 'weekly', 'every' => 1, 'days' => array(1)),
			array('type' => 'metric', 'metric' => 'tasks_open', 'param' => 0, 'op' => 'gt', 'value' => '0'),
			array('type' => 'workday'),
		),
		'action' => array('type' => 'post', 'channel_id' => 4, 'body' => 'Open tasks this week'),
		'follow' => array(
			array('on' => 'failed', 'do' => array('type' => 'email', 'to' => array('a@example.com', 'b@example.com'), 'subject' => 'Not posted', 'body' => 'Why: {{result}}', 'page_id' => 0)),
			array('on' => 'always', 'do' => array('type' => 'notify', 'user_ids' => array(7), 'text' => 'Ran')),
		),
	);
}

function test_workspace_flow_from_scheduled_boxes_and_arrows()
{
	$made = ws_flow_graph_from_scheduled(ws_flow_from_scheduled_test_data());
	$graph = $made['graph'];

	pg_assert_same('Monday tasks', $made['name']);
	pg_assert_same(array(), $made['left']);
	pg_assert_same(array('start', 'if1', 'if2', 'step', 'then1', 'then2'), array_column($graph['nodes'], 'id'));
	pg_assert_same(array('schedule', 'condition', 'condition', 'post', 'email', 'notify'), array_column($graph['nodes'], 'kind'));
	pg_assert_same(array('start>if1:next', 'if1>if2:yes', 'if2>step:yes', 'step>then1:failed', 'step>then2:always'), ws_flow_from_scheduled_test_edges($graph));

	$start = ws_flow_from_scheduled_test_node($graph, 'start');

	pg_assert_same('weekly', $start['config']['repeat']);
	pg_assert_same(array(1), $start['config']['days']);
	pg_assert_same('09:00', $start['config']['time']);

	$mail = ws_flow_from_scheduled_test_node($graph, 'then1');

	pg_assert_same('a@example.com, b@example.com', $mail['config']['to']);
	pg_assert_same('Why: {{node.step.text}}', $mail['config']['body']);
	pg_assert_same('workday', ws_flow_from_scheduled_test_node($graph, 'if2')['config']['rule']['type']);
	pg_assert_same(array(), ws_flow_graph_check($graph), 'publishable as far as its own settings go');
}

// The other starts, and the steps whose box has another name.
function test_workspace_flow_from_scheduled_starts_and_kinds()
{
	$made = ws_flow_graph_from_scheduled(array('name' => 'x', 'rules' => array(array('type' => 'trigger')),
		'action' => array('type' => 'task', 'title' => 'Call', 'description' => '', 'priority' => 'high', 'assignees' => array(3), 'channel_id' => 0, 'due_in' => 2), 'follow' => array()));

	pg_assert_same(array('manual', 'task_open'), array_column($made['graph']['nodes'], 'kind'));
	pg_assert_same('high', ws_flow_from_scheduled_test_node($made['graph'], 'step')['config']['priority']);

	$made = ws_flow_graph_from_scheduled(array('rules' => array(array('type' => 'event', 'event' => 'order.created', 'status' => '', 'page_id' => 0, 'channel_id' => 0)),
		'action' => array('type' => 'trigger', 'target_id' => 12, 'delay' => 5), 'follow' => array()));

	pg_assert_same(array('event', 'start_action'), array_column($made['graph']['nodes'], 'kind'));
	pg_assert_same('order.created', $made['graph']['nodes'][0]['config']['event']);
	pg_assert_same(12, ws_flow_from_scheduled_test_node($made['graph'], 'step')['config']['target_id']);

	$made = ws_flow_graph_from_scheduled(array('rules' => array(array('type' => 'join', 'channel_id' => 9)),
		'action' => array('type' => 'post', 'channel_id' => -1, 'body' => 'Welcome'), 'follow' => array()));

	pg_assert_same(array('join', 'post'), array_column($made['graph']['nodes'], 'kind'));
	pg_assert_same(9, $made['graph']['nodes'][0]['config']['channel_id']);
	pg_assert_same(array(), ws_flow_graph_check($made['graph']), 'a post in the channel joined is publishable on a join');
}

// Skipped: the no arrow of every condition; without one it is told.
function test_workspace_flow_from_scheduled_skipped()
{
	$data = ws_flow_from_scheduled_test_data();
	$data['follow'] = array(array('on' => 'skipped', 'do' => array('type' => 'notify', 'user_ids' => array(7), 'text' => 'Skipped')));
	$made = ws_flow_graph_from_scheduled($data);

	pg_assert_same(array('start>if1:next', 'if1>if2:yes', 'if2>step:yes', 'if1>then1:no', 'if2>then1:no'), ws_flow_from_scheduled_test_edges($made['graph']));

	$data['rules'] = array($data['rules'][0]);
	$made = ws_flow_graph_from_scheduled($data);

	pg_assert_same(array('start', 'step'), array_column($made['graph']['nodes'], 'id'));
	pg_assert_same(1, count($made['left']));
}

// The action starting itself again on failed is the step box's own retry;
// another action started on failed stays a box.
function test_workspace_flow_from_scheduled_self_retry()
{
	$data = ws_flow_from_scheduled_test_data();
	$data['follow'] = array(array('on' => 'failed', 'do' => array('type' => 'trigger', 'target_id' => 40, 'delay' => 15)));

	$made = ws_flow_graph_from_scheduled($data, array(), 40);
	$step = ws_flow_from_scheduled_test_node($made['graph'], 'step');

	pg_assert_same(array('start', 'if1', 'if2', 'step'), array_column($made['graph']['nodes'], 'id'));
	pg_assert_same(WS_FLOW_RETRY_MAX, $step['config']['retry']);
	pg_assert_same(15, $step['config']['retry_after']);

	$made = ws_flow_graph_from_scheduled($data, array(), 41);

	pg_assert_same('start_action', ws_flow_from_scheduled_test_node($made['graph'], 'then1')['kind']);
	pg_assert_same(0, ws_flow_from_scheduled_test_node($made['graph'], 'step')['config']['retry']);
}

// A count in money is kept in minor units; the box takes it as written.
function test_workspace_flow_from_scheduled_money()
{
	$data = ws_flow_from_scheduled_test_data();
	$data['rules'][1] = array('type' => 'metric', 'metric' => 'sales_today', 'param' => 0, 'op' => 'gt', 'value' => '150050');
	$made = ws_flow_graph_from_scheduled($data, array('sales_today'));

	pg_assert_same('1500.50', ws_flow_from_scheduled_test_node($made['graph'], 'if1')['config']['rule']['value']);
}

// An approval's follow-up on done waits for the approval in a flow: told.
function test_workspace_flow_from_scheduled_approval_done()
{
	$made = ws_flow_graph_from_scheduled(array('rules' => array(array('type' => 'trigger')),
		'action' => array('type' => 'approval', 'channel_id' => 4, 'title' => 'Price', 'text' => 'Ten off', 'approvers' => array(5), 'rule' => 'any'),
		'follow' => array(array('on' => 'done', 'do' => array('type' => 'notify', 'user_ids' => array(5), 'text' => 'Asked')))));

	pg_assert_same(array('start>step:next', 'step>then1:approved'), ws_flow_from_scheduled_test_edges($made['graph']));
	pg_assert_same(1, count($made['left']));
}
