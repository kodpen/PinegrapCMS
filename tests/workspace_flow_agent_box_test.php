<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flows part of the agents' risk tiers
 * (includes/workspace/flow_agent_box.php): the "Ask an agent" box (kind
 * agent) - its settings, what it lacks, the message that wakes the agent,
 * the request it waits on, how a closed request settles it - and the tier of
 * a flow, which decides whether an application agent may start a run of it.
 * Read from the source where a function runs a query.
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
function workspace_flow_agent_box_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// A step kind of its own: its prompt takes values, what the agent said stays
// with whoever may change the flow.
function test_workspace_flow_agent_box_kind()
{
	$kinds = ws_step_kinds();

	pg_assert_true(isset($kinds['agent']));
	pg_assert_same('agent', $kinds['agent']['type']);
	pg_assert_same(array('prompt'), $kinds['agent']['texts']);
	pg_assert_same(array('answer', 'message_id'), array_column($kinds['agent']['outputs'], 'key'));
	pg_assert_same(array('done', 'failed'), ws_flow_kinds()['agent']['outcomes']);
	pg_assert_true(ws_flow_kinds()['agent']['step']);
	pg_assert_same(array('*'), ws_step_outputs_held()['agent']);
	pg_assert_same('message', ws_step_fill_target('agent', array('type' => 'agent')), 'the prompt becomes a message');
	pg_assert_false(in_array('agent', ws_flow_member_kinds(), true), 'a role-3 member does not ask the agents');
	pg_assert_same('The agent answered.', ws_step_result_shown('agent', 'done', '@bot answered.', false));
	pg_assert_same('The agent gave no answer.', ws_step_result_shown('agent', 'failed', 'x', false));
	pg_assert_contains('agent_id', ws_flow_ai_catalogue_text(ws_flow_ai_catalogue_hints(ws_flow_kind_catalogue(), array())), 'Pinegrap AI drafts it');
}

function test_workspace_flow_agent_box_settings()
{
	pg_assert_same(array('agent_id' => 0, 'channel_id' => 0, 'prompt' => '', 'closes_in' => WS_STEP_AGENT_CLOSES_DEFAULT), ws_step_agent_normalize(array()));

	$config = ws_step_agent_normalize(array('agent_id' => '4', 'channel_id' => 7, 'prompt' => 'Check {{trigger.order_id}}', 'closes_in' => 5000));

	pg_assert_same(4, $config['agent_id']);
	pg_assert_same(7, $config['channel_id']);
	pg_assert_same(WS_STEP_AGENT_CLOSES_MAX, $config['closes_in']);
	pg_assert_same(1, ws_step_agent_normalize(array('closes_in' => 0))['closes_in']);
	pg_assert_same(0, ws_step_agent_normalize(array('agent_id' => -3))['agent_id']);
	pg_assert_same(ws_step_agent_normalize(array('agent_id' => 2)), ws_step_config_normalize('agent', array('agent_id' => 2)));

	pg_assert_same(3, count(ws_step_agent_problems(ws_step_agent_normalize(array()), 'Ask')), 'agent, channel and prompt');
	pg_assert_same(array(), ws_step_agent_problems($config, 'Ask'));
	pg_assert_same(array(), ws_step_config_problems('agent', $config, 'Ask'));
}

// The message names the agent first, so it wakes under every wake rule.
function test_workspace_flow_agent_box_message_and_answer()
{
	pg_assert_same('@helper Check order 5', ws_step_agent_message('helper', "  Check order 5 \n", 4000));
	pg_assert_same('@hel', ws_step_agent_message('helper', 'x', 4));
	pg_assert_true(ws_agent_wake_matches(array('wake_rule' => 'prefix', 'wake_prefix' => '[ci]', 'handle' => 'helper', 'app_id' => 0), ws_step_agent_message('helper', 'Hi', 100), false));

	pg_assert_same('Done, shipped.', ws_step_agent_answer_text('<@user:3> Done, shipped.', 3));
	pg_assert_same('<@user:4> Done.', ws_step_agent_answer_text('<@user:4> Done.', 3), 'only the asker\'s mention is taken off');

	pg_assert_same('agent:12', ws_step_agent_key(12));
	pg_assert_same(12, ws_step_agent_key_request('agent:12'));
	pg_assert_same(0, ws_step_agent_key_request('ai:12'));
	pg_assert_same(0, ws_step_agent_key_request('agent:0'));
	pg_assert_same(1000 + 2 * 3600, ws_step_agent_deadline(array('closes_in' => 2), 1000));
}

function test_workspace_flow_agent_box_settlement()
{
	$done = ws_step_agent_settlement(array('status' => 'answered'), 'Yes', 9, false, 'bot');

	pg_assert_same('done', $done['status']);
	pg_assert_same('done', $done['outcome']);
	pg_assert_same(array('answer' => 'Yes', 'message_id' => 9), $done['data']);

	$quiet = ws_step_agent_settlement(array('status' => 'answered'), null, 0, false, 'bot');

	pg_assert_same(array('answer' => '', 'message_id' => null), $quiet['data'], 'no answer needed is done with no words');

	$failed = ws_step_agent_settlement(array('status' => 'failed', 'error' => 'No access.'), null, 0, false, 'bot');

	pg_assert_same('failed', $failed['status'], 'no arrow on failed: the box fails');
	pg_assert_same('failed', $failed['outcome']);
	pg_assert_contains('No access.', $failed['said']);
	pg_assert_same('done', ws_step_agent_settlement(array('status' => 'cancelled'), null, 0, true, 'bot')['status'], 'an arrow on failed is taken');

	foreach (array('queued', 'sent', 'running') as $open) {
		pg_assert_same(null, ws_step_agent_settlement(array('status' => $open), null, 0, true, 'bot'), $open . ' settles nothing');
	}
}

// The box writes in the owner's name, waits on the request its message
// made, and is settled where requests close and on its hourly look.
function test_workspace_flow_agent_box_wiring()
{
	$check = workspace_flow_agent_box_test_source('ws_step_agent_check');

	pg_assert_contains('ws_can_post_channel($viewer, $channel)', $check);
	pg_assert_contains('ws_step_agent_channel_ids(', $check, 'the agent is allowed in the channel');
	pg_assert_contains('ws_step_agent_check($viewer, $config)', workspace_flow_agent_box_test_source('ws_step_check'));

	$ask = workspace_flow_agent_box_test_source('ws_step_agent_ask');

	pg_assert_contains('ws_message_send($owner, $channel,', $ask);
	pg_assert_contains("WHERE message_id = '\" . (int) \$sent['message_id']", $ask);
	pg_assert_contains("status = 'waiting'", $ask);
	pg_assert_contains("AND status = 'running'", $ask, 'a conditional write');
	pg_assert_contains('ws_step_agent_ask($step, $owner, $action, $routed)', workspace_flow_agent_box_test_source('ws_step_run'));
	pg_assert_contains('ws_step_agent_recheck($step)', workspace_flow_agent_box_test_source('ws_flow_step_recheck'));

	foreach (array('ws_api_agent_request_answer', 'ws_api_agent_request_fail', 'ws_ai_deliver', 'ws_ai_finish_fail') as $function) {
		pg_assert_contains('ws_step_agent_request_closed(', workspace_flow_agent_box_test_source($function), $function . ' settles a waiting box');
	}

	pg_assert_contains("wait_event = '' AND wait_key = '", workspace_flow_agent_box_test_source('ws_step_agent_request_closed'), 'read through the wait index');
	pg_assert_contains("ws_flow_step_settle(\$step, 'waiting', 'skipped'", workspace_flow_agent_box_test_source('ws_step_agent_settle'), 'a run that ended lets it go');
}

// A flow's tier is its highest box; a kind not classed is tier 3.
function test_workspace_flow_agent_box_flow_tier()
{
	$types = array(
		'task'  => array('actions' => array('update', 'delete'), 'fields' => array('title' => array('text', 'Title', 255, 'title'))),
		'order' => array('actions' => array('update'), 'fields' => array('note' => array('text', 'Note', 255, 'note'), 'total' => array('money', 'Total', 0, 'total'))),
	);
	$graph = function ($nodes) {
		return array('nodes' => $nodes);
	};

	pg_assert_same(WS_TIER_READ, ws_flow_graph_tier($graph(array(array('kind' => 'manual'), array('kind' => 'post'), array('kind' => 'agent'), array('kind' => 'end'))), $types));
	pg_assert_same(WS_TIER_READ, ws_flow_box_tier(array('kind' => 'schedule'), $types), 'a start reads');
	pg_assert_same(WS_TIER_REVERSIBLE, ws_flow_graph_tier($graph(array(array('kind' => 'manual'), array('kind' => 'task'))), $types));
	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_flow_graph_tier($graph(array(array('kind' => 'task'), array('kind' => 'email'))), $types));
	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_flow_box_tier(array('kind' => 'something_new'), $types), 'unknown is 3');

	pg_assert_same(WS_TIER_REVERSIBLE, ws_flow_box_tier(array('kind' => 'change', 'config' => array('record' => 'task', 'action' => 'update', 'fields' => array('title' => 'x'))), $types));
	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_flow_box_tier(array('kind' => 'change', 'config' => array('record' => 'task', 'action' => 'delete')), $types), 'a deletion');
	pg_assert_same(WS_TIER_IRREVERSIBLE, ws_flow_box_tier(array('kind' => 'change', 'config' => array('record' => 'order', 'action' => 'update', 'fields' => array('total' => '5'))), $types), 'money');
	pg_assert_same(WS_TIER_REVERSIBLE, ws_flow_box_tier(array('kind' => 'change', 'config' => array('record' => 'order', 'action' => 'update', 'fields' => array('note' => 'x'))), $types));

	// Every kind a flow has is classed, or knowingly left at 3.
	$classed = ws_flow_box_tiers();

	foreach (ws_flow_kinds() as $kind => $info) {
		if (empty($info['trigger']) && ($kind !== 'change')) {
			pg_assert_true(isset($classed[$kind]), $kind . ' has its tier');
		}
	}
}

function test_workspace_flow_agent_box_start_refusal()
{
	pg_assert_same('', ws_flow_agent_start_refusal(2, 2, 'bot'));
	pg_assert_same('', ws_flow_agent_start_refusal(3, 1, 'bot'));
	pg_assert_contains('only in a channel where it is allowed', ws_flow_agent_start_refusal(0, 1, 'bot'));
	pg_assert_contains('needs tier 3', ws_flow_agent_start_refusal(2, 3, 'bot'));
	pg_assert_contains('needs tier 2', ws_flow_agent_start_refusal(1, 2, 'bot'));

	$start = workspace_flow_agent_box_test_source('ws_api_flow_runs_start');

	pg_assert_contains('ws_flow_start_agent(ws_api_app_id())', $start);
	pg_assert_contains('if (ws_api_is_assistant() && !$agent)', $start, 'Claude and Pinegrap AI stay refused');
	pg_assert_contains('ws_flow_graph_tier($checked[\'graph\'])', $start);
	pg_assert_true(strpos($start, 'ws_flow_agent_start_refusal(') < strpos($start, 'api_dry_run_stop('), 'a dry run meets the refusal');

	$tier = workspace_flow_agent_box_test_source('ws_flow_start_agent_tier');

	pg_assert_contains('ca.allowed = 1', $tier);
	pg_assert_contains("a.kind = 'app' AND a.status = 'active'", $tier);
}
