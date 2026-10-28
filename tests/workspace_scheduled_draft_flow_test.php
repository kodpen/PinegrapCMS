<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a drafted scheduled action opened as a new flow
 * (ws_scheduled_draft_flow_input(), scheduled_draft.php): the flow's name,
 * the graph its boxes run the draft with, money read back from minor units,
 * and what the flow keeps otherwise, told in sentences. Nothing here reads
 * the database.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled_draft.php');

// A draft's snapshot as Pinegrap AI keeps it: every morning, if sales are
// above 150.50, a post; a notice when it fails.
function workspace_scheduled_draft_flow_snapshot()
{
	return array(
		'sentence' => 'Every morning post the sales',
		'notes'    => array(),
		'data'     => array(
			'name'   => 'Morning sales',
			'rules'  => array(
				array('type' => 'at', 'date' => '2026-11-02', 'time' => '09:00', 'repeat' => 'daily', 'every' => 1),
				array('type' => 'metric', 'metric' => 'sales_today', 'param' => 0, 'op' => 'gt', 'value' => '15050'),
			),
			'action' => array('type' => 'post', 'channel_id' => 4, 'body' => 'Sales today'),
			'follow' => array(
				array('on' => 'failed', 'do' => array('type' => 'notify', 'user_ids' => array(7), 'text' => 'Not posted: {{result}}')),
			),
		),
	);
}

function workspace_scheduled_draft_flow_node($graph, $id)
{
	foreach ($graph['nodes'] as $node) {
		if ($node['id'] === $id) {
			return $node;
		}
	}

	return null;
}

// The draft's name and boxes: the start, its condition, the step, the
// follow-up on the failed arrow.
function test_workspace_scheduled_draft_flow_input()
{
	$input = ws_scheduled_draft_flow_input(workspace_scheduled_draft_flow_snapshot(), array('sales_today'));

	pg_assert_same('Morning sales', $input['name']);
	pg_assert_same('schedule', workspace_scheduled_draft_flow_node($input['graph'], 'start')['kind']);
	pg_assert_same('condition', workspace_scheduled_draft_flow_node($input['graph'], 'if1')['kind']);
	pg_assert_same('post', workspace_scheduled_draft_flow_node($input['graph'], 'step')['kind']);
	pg_assert_same('notify', workspace_scheduled_draft_flow_node($input['graph'], 'then1')['kind']);
	pg_assert_same('Not posted: {{node.step.text}}', workspace_scheduled_draft_flow_node($input['graph'], 'then1')['config']['text']);
	pg_assert_same(array(), $input['left']);
}

// A count in money is kept in minor units in the draft; the box takes it as
// written.
function test_workspace_scheduled_draft_flow_money()
{
	$input = ws_scheduled_draft_flow_input(workspace_scheduled_draft_flow_snapshot(), array('sales_today'));

	pg_assert_same('150.50', workspace_scheduled_draft_flow_node($input['graph'], 'if1')['config']['rule']['value']);
}

// A flow needs a name: a draft without one is named for what it was.
function test_workspace_scheduled_draft_flow_unnamed()
{
	$snapshot = workspace_scheduled_draft_flow_snapshot();
	$snapshot['data']['name'] = '  ';

	pg_assert_same('Scheduled action', ws_scheduled_draft_flow_input($snapshot, array())['name']);
}

// A snapshot without data still gives a flow the person can build on: a
// start by hand and an empty step box.
function test_workspace_scheduled_draft_flow_empty()
{
	$input = ws_scheduled_draft_flow_input(array(), array());

	pg_assert_same('manual', workspace_scheduled_draft_flow_node($input['graph'], 'start')['kind']);
	pg_assert_same('Scheduled action', $input['name']);
}

// What the flow keeps otherwise is told, as the graph's own sentences.
function test_workspace_scheduled_draft_flow_left()
{
	$snapshot = workspace_scheduled_draft_flow_snapshot();
	$snapshot['data']['rules'] = array($snapshot['data']['rules'][0]);
	$snapshot['data']['follow'][] = array('on' => 'skipped', 'do' => array('type' => 'notify', 'user_ids' => array(7), 'text' => 'Skipped'));

	pg_assert_same(1, count(ws_scheduled_draft_flow_input($snapshot, array())['left']));
}

// An application's proposed scheduled action is kept as a flow draft card:
// the snapshot a flow card reads (name, home, graph), its notes followed by
// what the flow keeps otherwise, the home its card opens the flow in.
function test_workspace_scheduled_draft_flow_snapshot()
{
	$data = workspace_scheduled_draft_flow_snapshot()['data'];
	$data['follow'][] = array('on' => 'skipped', 'do' => array('type' => 'notify', 'user_ids' => array(7), 'text' => 'Skipped'));
	$data['rules'] = array($data['rules'][0]);

	$snapshot = ws_scheduled_draft_flow_snapshot($data, 9, array('Clean up after the sale'), array());
	$left = ws_scheduled_draft_flow_input(array('data' => $data), array())['left'];

	pg_assert_same(array('sentence', 'data', 'notes', 'channel_id'), array_keys($snapshot));
	pg_assert_same('', $snapshot['sentence']);
	pg_assert_same(array('name', 'channel_id', 'graph'), array_keys($snapshot['data']));
	pg_assert_same('Morning sales', $snapshot['data']['name']);
	pg_assert_same(9, $snapshot['data']['channel_id']);
	pg_assert_same(9, $snapshot['channel_id']);
	pg_assert_same('schedule', workspace_scheduled_draft_flow_node($snapshot['data']['graph'], 'start')['kind']);
	pg_assert_same(1, count($left));
	pg_assert_same(array_merge(array('Clean up after the sale'), $left), $snapshot['notes'], 'the reason first, then what the flow keeps otherwise');
}

// No home: the card's flow is opened without one.
function test_workspace_scheduled_draft_flow_snapshot_no_home()
{
	$snapshot = ws_scheduled_draft_flow_snapshot(array(), 0, array(), array());

	pg_assert_same(0, $snapshot['channel_id']);
	pg_assert_same('Scheduled action', $snapshot['data']['name']);
	pg_assert_same(array(), $snapshot['notes']);
}
