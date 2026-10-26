<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the workspace's flows
 * (includes/workspace/flows.php): the graph kept in its shape
 * (ws_flow_graph_normalize()) and the check that says what stops a graph
 * from being published (ws_flow_graph_check()), each of its problem codes,
 * a join, a loop, and the notes of the canvas left out of it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only; the assign rules of a task box are the
// templates' (ws_template_assign_valid()).
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');

// A box of the graph.
function ws_flows_test_node($id, $kind, $label = '', $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => $label, 'x' => 0, 'y' => 0, 'config' => $config);
}

// An arrow of the graph.
function ws_flows_test_edge($from, $to, $on = '')
{
	return array('from' => $from, 'to' => $to, 'on' => $on);
}

// The codes of a check, in order.
function ws_flows_test_codes($graph)
{
	return array_map(function ($problem) {
		return $problem['code'];
	}, ws_flow_graph_check($graph));
}

// The process of a new customer: started by hand, three tasks one after
// another, then the end.
function ws_flows_test_process()
{
	return array(
		'v'     => 1,
		'nodes' => array(
			ws_flows_test_node('start', 'manual', 'New customer'),
			ws_flows_test_node('contract', 'task', 'Send contract', array('title' => 'Send contract', 'assign' => 'creator', 'due_in' => 1)),
			ws_flows_test_node('payment', 'task', 'Take payment', array('title' => 'Take payment', 'assignees' => array(12), 'due_in' => 3)),
			ws_flows_test_node('account', 'task', 'Open the account', array('title' => 'Open the account', 'assign' => 'department_lead')),
			ws_flows_test_node('finish', 'end'),
		),
		'edges' => array(
			ws_flows_test_edge('start', 'contract', 'next'),
			ws_flows_test_edge('contract', 'payment', 'done'),
			ws_flows_test_edge('payment', 'account', 'done'),
			ws_flows_test_edge('account', 'finish', 'done'),
		),
		'notes' => array(),
	);
}

function test_workspace_flows_process_chain_is_valid()
{
	pg_assert_same(array(), ws_flow_graph_check(ws_flows_test_process()), 'the process may be published');
	pg_assert_same(array(), ws_flow_graph_check(json_encode(ws_flows_test_process())), 'read from its JSON too');
}

// A box with two arrows in waits for both: a join is no loop.
function test_workspace_flows_join_is_valid()
{
	$graph = array(
		'nodes' => array(
			ws_flows_test_node('start', 'manual'),
			ws_flows_test_node('contract', 'task', '', array('title' => 'Send contract')),
			ws_flows_test_node('invoice', 'task', '', array('title' => 'Send invoice')),
			ws_flows_test_node('welcome', 'task', '', array('title' => 'Welcome call')),
			ws_flows_test_node('finish', 'end'),
		),
		'edges' => array(
			ws_flows_test_edge('start', 'contract'),
			ws_flows_test_edge('start', 'invoice'),
			ws_flows_test_edge('contract', 'welcome', 'done'),
			ws_flows_test_edge('invoice', 'welcome', 'done'),
			ws_flows_test_edge('welcome', 'finish', 'cancelled'),
		),
	);

	pg_assert_same(array(), ws_flow_graph_check($graph));
}

// Arrows that lead back are refused, and only the boxes on the loop are
// named, not the box that follows it.
function test_workspace_flows_cycle_is_refused()
{
	$graph = array(
		'nodes' => array(
			ws_flows_test_node('start', 'manual'),
			ws_flows_test_node('a', 'task', 'First', array('title' => 'First')),
			ws_flows_test_node('b', 'task', 'Second', array('title' => 'Second')),
			ws_flows_test_node('after', 'task', 'After', array('title' => 'After')),
		),
		'edges' => array(
			ws_flows_test_edge('start', 'a'),
			ws_flows_test_edge('a', 'b', 'done'),
			ws_flows_test_edge('b', 'a', 'done'),
			ws_flows_test_edge('b', 'after', 'cancelled'),
		),
	);

	$cycle = array_values(array_filter(ws_flow_graph_check($graph), function ($problem) {
		return $problem['code'] === 'cycle';
	}));

	pg_assert_same(array('a', 'b'), array_map(function ($problem) {
		return $problem['node'];
	}, $cycle), 'the two boxes on the loop');
	pg_assert_same('The box First is in a loop: the arrows lead back to it.', $cycle[0]['text']);
}

// Each code once, from the smallest graph that has it.
function test_workspace_flows_each_problem_code()
{
	$start = ws_flows_test_node('start', 'manual');
	$end = ws_flows_test_node('finish', 'end');
	$task = ws_flows_test_node('job', 'task', '', array('title' => 'Job'));

	pg_assert_same(array('no_trigger'), ws_flows_test_codes(array('nodes' => array($end))));
	pg_assert_same(array('no_trigger'), ws_flows_test_codes(array()), 'an empty canvas');

	$two = ws_flows_test_codes(array('nodes' => array($start, ws_flows_test_node('again', 'manual'), $end),
		'edges' => array(ws_flows_test_edge('start', 'finish'), ws_flows_test_edge('again', 'finish'))));
	pg_assert_same(array('two_triggers'), $two, 'unreachable is not asked with two starts');

	pg_assert_same(array('arrow_into_trigger', 'bad_outcome', 'cycle', 'cycle'), ws_flows_test_codes(array('nodes' => array($start, $end),
		'edges' => array(ws_flows_test_edge('start', 'finish'), ws_flows_test_edge('finish', 'start')))), 'into the start, from an end');

	pg_assert_same(array('duplicate_id'), ws_flows_test_codes(array('nodes' => array($start, $end, $end), 'edges' => array(ws_flows_test_edge('start', 'finish')))));
	pg_assert_same(array('unknown_kind'), ws_flows_test_codes(array('nodes' => array($start, ws_flows_test_node('odd', 'telepathy')), 'edges' => array(ws_flows_test_edge('start', 'odd')))));

	$bad_id = ws_flow_graph_check(array('nodes' => array($start, ws_flows_test_node('Step 1', 'end', 'Done')), 'edges' => array(ws_flows_test_edge('start', 'Step 1'))));
	pg_assert_same(array('bad_id'), array_column($bad_id, 'code'));
	pg_assert_same('Step 1', $bad_id[0]['node']);

	pg_assert_same(array('bad_edge'), ws_flows_test_codes(array('nodes' => array($start), 'edges' => array(ws_flows_test_edge('start', 'nowhere')))), 'an arrow to no box');
	pg_assert_same(array('bad_edge', 'unreachable'), ws_flows_test_codes(array('nodes' => array($start, $task), 'edges' => array(ws_flows_test_edge('job', 'job', 'done')))), 'a box joined to itself');

	$outcome = ws_flow_graph_check(array('nodes' => array($start, $task, $end), 'edges' => array(ws_flows_test_edge('start', 'job'), ws_flows_test_edge('job', 'finish', 'approved'))));
	pg_assert_same(array('bad_outcome'), array_column($outcome, 'code'));
	pg_assert_same(1, $outcome[0]['edge'], 'the arrow by its place');
	pg_assert_same('job', $outcome[0]['node']);

	pg_assert_same(array('duplicate_edge'), ws_flows_test_codes(array('nodes' => array($start, $end),
		'edges' => array(ws_flows_test_edge('start', 'finish'), ws_flows_test_edge('start', 'finish', 'next')))), 'the outcome left out is next');

	$unreachable = ws_flow_graph_check(array('nodes' => array($start, $task, $end), 'edges' => array(ws_flows_test_edge('start', 'finish'))));
	pg_assert_same(array('unreachable'), array_column($unreachable, 'code'));
	pg_assert_same('job', $unreachable[0]['node']);

	$many = array($start);

	for ($i = 1; $i <= WS_FLOW_MAX_NODES; $i++) {
		$many[] = ws_flows_test_node('end_' . $i, 'end');
	}

	$edges = array_map(function ($node) {
		return ws_flows_test_edge('start', $node['id']);
	}, array_slice($many, 1));

	pg_assert_same(array(), ws_flows_test_codes(array('nodes' => array_slice($many, 0, WS_FLOW_MAX_NODES), 'edges' => array_slice($edges, 0, WS_FLOW_MAX_NODES - 1))), 'as many boxes as allowed');
	pg_assert_same(array('too_many'), ws_flows_test_codes(array('nodes' => $many, 'edges' => $edges)), 'one box more');

	$notes = array_fill(0, WS_FLOW_MAX_NOTES + 1, array('id' => 'n', 'text' => 'Note'));
	pg_assert_same(array('too_many'), ws_flows_test_codes(array('nodes' => array($start, $end), 'edges' => array(ws_flows_test_edge('start', 'finish')), 'notes' => $notes)), 'one note more');
}

// What a task box needs: a title, and people or a rule for whom it goes to,
// not both, and a rule the templates know.
function test_workspace_flows_task_box_settings()
{
	$check = function ($config) {
		return ws_flows_test_codes(array(
			'nodes' => array(ws_flows_test_node('start', 'manual'), ws_flows_test_node('job', 'task', '', $config)),
			'edges' => array(ws_flows_test_edge('start', 'job')),
		));
	};

	pg_assert_same(array(), $check(array('title' => 'Job', 'assign' => 'user:4')));
	pg_assert_same(array('bad_config'), $check(array('title' => '   ')), 'no title');
	pg_assert_same(array('bad_config'), $check(array('title' => 'Job', 'assign' => 'creator', 'assignees' => array(3))), 'people and a rule');
	pg_assert_same(array('bad_config'), $check(array('title' => 'Job', 'assign' => 'the boss')), 'a rule nobody knows');
}

// The graph comes back in its shape whatever was sent.
function test_workspace_flows_normalize_keeps_the_shape()
{
	$graph = ws_flow_graph_normalize(array(
		'v'     => 7,
		'extra' => true,
		'nodes' => array(
			array('id' => ' start ', 'kind' => 'manual', 'label' => "  Start\n\there  ", 'x' => 250000, 'y' => '-250000.6', 'colour' => 'red',
				'config' => array('subject' => 'Order!', 'title' => 'dropped')),
			array('id' => 'job', 'kind' => 'task', 'label' => str_repeat('ş', 200), 'x' => 12.6, 'y' => 'left',
				'config' => array('title' => str_repeat('a', 250), 'assignees' => array('7', 7, -1, 'x', 9), 'due_in' => '9999', 'priority' => 'whenever', 'colour' => 'red')),
			array('id' => 'other', 'kind' => 'task', 'config' => array('title' => 'Other', 'assignees' => range(1, 30), 'due_in' => '', 'assign' => 'creator')),
			'not a box',
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'job'),
			array('from' => 'job', 'to' => 'other', 'on' => '', 'label' => 'x'),
			array('from' => 'ghost', 'to' => 'job'),
			7,
		),
		'notes' => array(
			array('id' => 'n1', 'text' => str_repeat('b', 2500) . "\r\n", 'x' => -100001, 'y' => 5, 'w' => -40, 'h' => 'tall'),
		),
	));

	pg_assert_same(1, $graph['v']);
	pg_assert_same(array('v', 'nodes', 'edges', 'notes'), array_keys($graph), 'unknown keys dropped');
	pg_assert_same(3, count($graph['nodes']), 'a box that is not an object is dropped');

	$start = $graph['nodes'][0];
	pg_assert_same('start', $start['id'], 'trimmed');
	pg_assert_same('Start here', $start['label'], 'one line');
	pg_assert_same(100000, $start['x'], 'held at the edge');
	pg_assert_same(-100000, $start['y']);
	pg_assert_same(array('id', 'kind', 'label', 'x', 'y', 'config'), array_keys($start));
	pg_assert_same(array('subject' => 'order'), $start['config'], 'the start keeps its subject only');

	$job = $graph['nodes'][1];
	pg_assert_same(160, mb_strlen($job['label']), 'label cut to 160 characters');
	pg_assert_same(13, $job['x'], 'rounded');
	pg_assert_same(0, $job['y'], 'not a number');
	pg_assert_same(200, mb_strlen($job['config']['title']));
	pg_assert_same(array(7, 9), $job['config']['assignees'], 'whole numbers above 0, once each');
	pg_assert_same(3650, $job['config']['due_in']);
	pg_assert_same('normal', $job['config']['priority']);
	pg_assert_same(array('title', 'description', 'assignees', 'assign', 'due_in', 'priority'), array_keys($job['config']));

	pg_assert_same(20, count($graph['nodes'][2]['config']['assignees']), 'at most 20 people');
	pg_assert_same('', $graph['nodes'][2]['config']['due_in'], 'no due day');

	pg_assert_same(3, count($graph['edges']));
	pg_assert_same(array('from' => 'start', 'to' => 'job', 'on' => 'next'), $graph['edges'][0], 'a start leaves on next');
	pg_assert_same('done', $graph['edges'][1]['on'], 'a task on done');
	pg_assert_same('', $graph['edges'][2]['on'], 'no box, no outcome to guess');

	$note = $graph['notes'][0];
	pg_assert_same(2000, mb_strlen($note['text']), 'note text cut to 2,000 characters');
	pg_assert_same(-100000, $note['x']);
	pg_assert_same(0, $note['w'], 'a size is not below 0');
	pg_assert_same(0, $note['h']);

	pg_assert_same(ws_flow_graph_empty(), ws_flow_graph_normalize('{not json'), 'unreadable JSON is an empty graph');
	pg_assert_same($graph, ws_flow_graph_normalize($graph), 'normalizing twice changes nothing');

	$broken = ws_flow_graph_normalize(array('nodes' => array(array('id' => str_repeat('ş', 70) . "\xC3", 'kind' => 'end', 'label' => "Bad \xFF byte"))));
	pg_assert_true(is_string(json_encode($broken)), 'whatever came in can be written as JSON');
	pg_assert_same(64, mb_strlen($broken['nodes'][0]['id']), 'an id is cut by characters');
}

// The notes of the canvas are never checked: ids missing or twice, empty
// texts, anywhere on it.
function test_workspace_flows_notes_are_ignored()
{
	$graph = ws_flows_test_process();
	$graph['notes'] = array(
		array('id' => '', 'text' => ''),
		array('id' => 'start', 'text' => 'The same id as a box'),
		array('id' => 'start', 'text' => 'Twice', 'x' => 999999),
	);

	pg_assert_same(array(), ws_flow_graph_check($graph));
}

function test_workspace_flows_node_ids()
{
	foreach (array('a', 'start', 'step_2', 'a' . str_repeat('b', 31)) as $id) {
		pg_assert_true(ws_flow_node_id_valid($id), $id);
	}

	foreach (array('', '2nd', 'Start', 'step-2', 'a' . str_repeat('b', 32), 'ş', 12, null) as $id) {
		pg_assert_false(ws_flow_node_id_valid($id), var_export($id, true));
	}
}
