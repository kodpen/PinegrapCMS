<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a scheduled action drawn as a graph (ws_scheduled_graph() in
 * includes/workspace/scheduled.php): the boxes from the card a staff member
 * gets, the arrows by outcome, where the boxes sit, and the chips for the
 * actions that start it, those it starts and its retry.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module files hold functions and constants only; loading them runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// A staff card as ws_scheduled_present() gives it, with only what the graph reads.
function workspace_scheduled_graph_test_item($extra = array())
{
	return $extra + array(
		'id'                 => 7,
		'when'               => 'Every day at 09:00',
		'next'               => '27.10.2026 09:00',
		'joins'              => false,
		'on_event'           => false,
		'trigger_only'       => false,
		'repeats'            => true,
		'conditions'         => array(),
		'action_text'        => 'Post in #sales',
		'action_target'      => 0,
		'action_target_name' => '',
		'follow'             => array(),
		'starters'           => array(),
	);
}

function workspace_scheduled_graph_test_ids($list, $key = 'id')
{
	return array_map(function ($item) use ($key) { return $item[$key]; }, $list);
}

function test_workspace_scheduled_graph_when_and_step()
{
	$graph = ws_scheduled_graph(workspace_scheduled_graph_test_item());

	pg_assert_same(1, $graph['v'], 'version');
	pg_assert_same(array('when', 'step'), workspace_scheduled_graph_test_ids($graph['nodes']), 'the when box, then the step');
	pg_assert_same(array('when', 'step'), workspace_scheduled_graph_test_ids($graph['nodes'], 'kind'), 'their kinds');
	pg_assert_same('Every day at 09:00', $graph['nodes'][0]['label'], 'the when box says when');
	pg_assert_same('Next: 27.10.2026 09:00', $graph['nodes'][0]['meta'], 'and when next');
	pg_assert_same('bi-arrow-repeat', $graph['nodes'][0]['icon'], 'a repeating time');
	pg_assert_same('Post in #sales', $graph['nodes'][1]['label'], 'the step says what it does');
	pg_assert_same(array(array('from' => 'when', 'to' => 'step', 'on' => 'next', 'label' => '')), $graph['edges'], 'one arrow');
	pg_assert_same(array(0, 256), workspace_scheduled_graph_test_ids($graph['nodes'], 'x'), 'one column each');
	pg_assert_same(array(0, 0), workspace_scheduled_graph_test_ids($graph['nodes'], 'y'), 'on one row');
	pg_assert_same(array('starters' => array(), 'started' => array(), 'self_retry' => array()), $graph['chips'], 'no chips');

	$paused = ws_scheduled_graph(workspace_scheduled_graph_test_item(array('next' => '', 'repeats' => false)));
	pg_assert_same('', $paused['nodes'][0]['meta'], 'no next run, no line for it');
	pg_assert_same('bi-calendar-event', $paused['nodes'][0]['icon'], 'a single time');

	pg_assert_same('bi-person-plus', ws_scheduled_graph(workspace_scheduled_graph_test_item(array('joins' => true)))['nodes'][0]['icon'], 'somebody joining');
	pg_assert_same('bi-broadcast', ws_scheduled_graph(workspace_scheduled_graph_test_item(array('on_event' => true)))['nodes'][0]['icon'], 'an event');
	pg_assert_same('bi-diagram-3', ws_scheduled_graph(workspace_scheduled_graph_test_item(array('trigger_only' => true)))['nodes'][0]['icon'], 'started by another action');
}

function test_workspace_scheduled_graph_conditions_in_a_row()
{
	$graph = ws_scheduled_graph(workspace_scheduled_graph_test_item(array('conditions' => array('On working days', 'Between 09:00 and 17:00'))));

	pg_assert_same(array('when', 'if_1', 'if_2', 'step'), workspace_scheduled_graph_test_ids($graph['nodes']), 'a box per condition, in order');
	pg_assert_same(array('condition', 'condition'), array($graph['nodes'][1]['kind'], $graph['nodes'][2]['kind']), 'condition boxes');
	pg_assert_same('Between 09:00 and 17:00', $graph['nodes'][2]['label'], 'each says what must hold');
	pg_assert_same(array('when>if_1', 'if_1>if_2', 'if_2>step'), array_map(function ($edge) { return $edge['from'] . '>' . $edge['to']; }, $graph['edges']), 'one after the other');
	pg_assert_same(array(0, 256, 512, 768), workspace_scheduled_graph_test_ids($graph['nodes'], 'x'), 'a column each');
}

function test_workspace_scheduled_graph_follow_ups_by_outcome()
{
	$graph = ws_scheduled_graph(workspace_scheduled_graph_test_item(array('follow' => array(
		array('on' => 'done', 'label' => 'Done', 'text' => 'Notify Ayşe', 'target' => 0, 'target_name' => ''),
		array('on' => 'failed', 'label' => 'Failed', 'text' => 'Start “Report”', 'target' => 9, 'target_name' => 'Report'),
		array('on' => 'skipped', 'label' => 'Skipped', 'text' => 'Post in #ops', 'target' => 0, 'target_name' => ''),
	))));

	pg_assert_same(array('when', 'step', 'then_1', 'then_2', 'then_3'), workspace_scheduled_graph_test_ids($graph['nodes']), 'a box per follow-up, in order');
	pg_assert_same(array('follow', 'follow', 'follow'), array($graph['nodes'][2]['kind'], $graph['nodes'][3]['kind'], $graph['nodes'][4]['kind']), 'follow-up boxes');
	pg_assert_same(array('bi-arrow-return-right', 'bi-diagram-3'), array($graph['nodes'][2]['icon'], $graph['nodes'][3]['icon']), 'one starting an action has the chain sign');
	pg_assert_same(array(512, 512, 512), array($graph['nodes'][2]['x'], $graph['nodes'][3]['x'], $graph['nodes'][4]['x']), 'all in the column after the step');
	pg_assert_same(array(0, 112, 224), array($graph['nodes'][2]['y'], $graph['nodes'][3]['y'], $graph['nodes'][4]['y']), 'stacked');
	pg_assert_same(array(112, 112), array($graph['nodes'][0]['y'], $graph['nodes'][1]['y']), 'the boxes before them on the middle of the stack');
	pg_assert_same(array('from' => 'step', 'to' => 'then_2', 'on' => 'failed', 'label' => 'Failed'), $graph['edges'][2], 'the arrow named by its outcome');
	pg_assert_same(array(array('id' => 9, 'name' => 'Report', 'from' => 'then_2')), $graph['chips']['started'], 'the action it starts as a chip');

	$two = ws_scheduled_graph(workspace_scheduled_graph_test_item(array('follow' => array(
		array('on' => 'done', 'label' => 'Done', 'text' => 'a', 'target' => 0, 'target_name' => ''),
		array('on' => 'failed', 'label' => 'Failed', 'text' => 'b', 'target' => 0, 'target_name' => ''),
	))));
	pg_assert_same(array(64, 64), array($two['nodes'][0]['y'], $two['nodes'][1]['y']), 'the middle of two, on the grid');
}

function test_workspace_scheduled_graph_chips()
{
	$graph = ws_scheduled_graph(workspace_scheduled_graph_test_item(array(
		'action_target'      => 9,
		'action_target_name' => 'Report',
		'follow'             => array(
			array('on' => 'failed', 'label' => 'Failed', 'text' => 'Start “Daily”', 'target' => 7, 'target_name' => 'Daily'),
			array('on' => 'done', 'label' => 'Done', 'text' => 'Start “Report”', 'target' => 9, 'target_name' => 'Report'),
			array('on' => 'skipped', 'label' => 'Skipped', 'text' => 'Start “Close”', 'target' => 11, 'target_name' => 'Close'),
		),
		'starters'           => array(array('id' => 3, 'name' => 'Morning'), array('id' => 7, 'name' => 'Daily')),
	)));

	pg_assert_same(array(array('id' => 3, 'name' => 'Morning')), $graph['chips']['starters'], 'the actions that start it, without itself');
	pg_assert_same(array(array('id' => 9, 'name' => 'Report', 'from' => 'step'), array('id' => 11, 'name' => 'Close', 'from' => 'then_3')), $graph['chips']['started'], 'those it starts, each once');
	pg_assert_same(array('Failed'), $graph['chips']['self_retry'], 'starting itself again on an outcome is its retry');
	pg_assert_same(5, count($graph['nodes']), 'the retry is still drawn as its follow-up');
}
