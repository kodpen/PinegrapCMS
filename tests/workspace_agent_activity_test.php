<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agents' activity on the Agents card
 * (includes/workspace/agent_activity.php) that need no database: the request
 * values an agent is matched by in both shapes, the counts of 7 and 30 days,
 * the buttons a reader is offered, and the place hidden from a reader who
 * may not read the channel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_agent_activity_keys()
{
	pg_assert_same(array('agent:4', 'app:4'), ws_agent_activity_keys(array('kind' => 'app', 'id' => 4)), 'both shapes of an app agent');
	pg_assert_same(array('model:7'), ws_agent_activity_keys(array('kind' => 'model', 'id' => 7)));
	pg_assert_same(array(), ws_agent_activity_keys(array('kind' => 'claude', 'id' => 1)));
	pg_assert_same(array(), ws_agent_activity_keys(array('kind' => 'app', 'id' => 0)));
	pg_assert_true(in_array(ws_agent_request_value(array('kind' => 'app', 'id' => 4)), ws_agent_activity_keys(array('kind' => 'app', 'id' => 4)), true), 'the value the queue writes is matched');
}

function test_workspace_agent_activity_agent_id()
{
	pg_assert_same(4, ws_agent_activity_agent_id('agent:4'));
	pg_assert_same(4, ws_agent_activity_agent_id('app:4'));
	pg_assert_same(12, ws_agent_activity_agent_id('model:12'));
	pg_assert_same(0, ws_agent_activity_agent_id('claude'));
	pg_assert_same(0, ws_agent_activity_agent_id('mcp'));
	pg_assert_same(0, ws_agent_activity_agent_id('app:0'));
	pg_assert_same(0, ws_agent_activity_agent_id('app:4x'));
}

function test_workspace_agent_activity_counts()
{
	$counts = ws_agent_activity_counts(array(
		array('agent' => 'app:4', 'status' => 'answered', 'week' => 1, 'n' => 3),
		array('agent' => 'agent:4', 'status' => 'answered', 'week' => 1, 'n' => 2),
		array('agent' => 'agent:4', 'status' => 'answered', 'week' => 0, 'n' => 5),
		array('agent' => 'agent:4', 'status' => 'queued', 'week' => 1, 'n' => 1),
		array('agent' => 'agent:4', 'status' => 'running', 'week' => 0, 'n' => 1),
		array('agent' => 'model:9', 'status' => 'failed', 'week' => '1', 'n' => '2'),
		array('agent' => 'claude', 'status' => 'answered', 'week' => 1, 'n' => 8),
	));

	pg_assert_same(array(4, 9), array_keys($counts), 'Claude is not a registry agent');
	pg_assert_same(array('waiting' => 1, 'answered' => 5, 'failed' => 0, 'cancelled' => 0), $counts[4]['d7'], 'both shapes summed');
	pg_assert_same(array('waiting' => 2, 'answered' => 10, 'failed' => 0, 'cancelled' => 0), $counts[4]['d30'], 'the week is within the month');
	pg_assert_same(2, $counts[9]['d7']['failed']);
	pg_assert_same(array(), ws_agent_activity_counts(array()));
}

function test_workspace_agent_activity_bucket()
{
	pg_assert_same('waiting', ws_agent_activity_bucket('queued'));
	pg_assert_same('waiting', ws_agent_activity_bucket('sent'));
	pg_assert_same('waiting', ws_agent_activity_bucket('running'));
	pg_assert_same('answered', ws_agent_activity_bucket('answered'));
	pg_assert_same('failed', ws_agent_activity_bucket('failed'));
	pg_assert_same('cancelled', ws_agent_activity_bucket('cancelled'));
}

function test_workspace_agent_activity_counts_text()
{
	pg_assert_same('no requests', ws_agent_activity_counts_text(array('waiting' => 0, 'answered' => 0, 'failed' => 0, 'cancelled' => 0)));
	pg_assert_same('1 waiting, 3 answered', ws_agent_activity_counts_text(array('waiting' => 1, 'answered' => 3, 'failed' => 0, 'cancelled' => 0)));
	pg_assert_same('2 failed, 1 cancelled', ws_agent_activity_counts_text(array('failed' => 2, 'cancelled' => 1)));
}

function test_workspace_agent_activity_actions()
{
	$asker = array('id' => 5, 'role' => 3);
	$admin = array('id' => 1, 'role' => 0);
	$other = array('id' => 6, 'role' => 1);
	$push = array('kind' => 'app', 'status' => 'active', 'door_kind' => 'push');
	$poll = array('kind' => 'app', 'status' => 'active', 'door_kind' => 'poll');
	$model = array('kind' => 'model', 'status' => 'active', 'door_kind' => '');
	$queued = array('status' => 'queued', 'requested_by' => 5, 'message_id' => 10, 'channel_id' => 2);
	$failed = array('status' => 'failed', 'requested_by' => 5, 'message_id' => 10, 'channel_id' => 2);

	pg_assert_same(array('cancel' => true, 'retry' => false, 'knock' => true), ws_agent_activity_actions($asker, $queued, $push));
	pg_assert_same(array('cancel' => true, 'retry' => false, 'knock' => true), ws_agent_activity_actions($admin, $queued, $push));
	pg_assert_same(array('cancel' => false, 'retry' => false, 'knock' => false), ws_agent_activity_actions($other, $queued, $push), 'a Designer who did not ask has none');
	pg_assert_same(array('cancel' => true, 'retry' => false, 'knock' => false), ws_agent_activity_actions($asker, $queued, $poll), 'a poll door is not knocked');
	pg_assert_same(array('cancel' => true, 'retry' => false, 'knock' => false), ws_agent_activity_actions($asker, $queued, $model));
	pg_assert_same(array('cancel' => true, 'retry' => false, 'knock' => false), ws_agent_activity_actions($asker, array('status' => 'running') + $queued, $push), 'a claimed one is cancelled, not knocked');
	pg_assert_same(array('cancel' => false, 'retry' => true, 'knock' => false), ws_agent_activity_actions($asker, $failed, $model));
	pg_assert_same(false, ws_agent_activity_actions($asker, array('message_id' => 0) + $failed, $push)['retry'], 'a wake has no message to ask again');
	pg_assert_same(false, ws_agent_activity_actions($asker, $failed, array('status' => 'paused') + $push)['retry'], 'a paused agent is not asked');
	pg_assert_same(false, ws_agent_activity_actions($asker, $queued, array('status' => 'paused') + $push)['knock']);
	pg_assert_same(array('cancel' => false, 'retry' => false, 'knock' => false), ws_agent_activity_actions($admin, array('status' => 'answered') + $queued, $push));
	pg_assert_same(array('cancel' => false, 'retry' => false, 'knock' => false), ws_agent_activity_actions(null, $queued, $push));
}

function test_workspace_agent_activity_place()
{
	$row = array('channel_id' => 3, 'message_id' => 40);

	pg_assert_same(array('name' => '#general', 'url' => 'workspace.php?channel=3&message=40'), ws_agent_activity_place($row, 'general', true));
	pg_assert_same(array('name' => '', 'url' => ''), ws_agent_activity_place($row, 'secret', false), 'no name and no link for a channel the reader may not read');
	pg_assert_same(array('name' => '', 'url' => ''), ws_agent_activity_place($row, null, true), 'a channel that is gone');
	pg_assert_same(array('name' => '#ops', 'url' => 'workspace.php?channel=3'), ws_agent_activity_place(array('message_id' => 0) + $row, 'ops', true), 'a wake has its channel only');
	pg_assert_same(array('name' => '', 'url' => ''), ws_agent_activity_place(array('channel_id' => 0, 'message_id' => 0), 'x', true));
}

function test_workspace_ai_usage_tokens()
{
	pg_assert_same(array('in' => 120, 'out' => 30), ws_ai_usage_tokens(array('usage' => array('prompt_tokens' => 120, 'completion_tokens' => 30, 'total_tokens' => 150))), 'the OpenAI shape');
	pg_assert_same(array('in' => 7, 'out' => 2), ws_ai_usage_tokens(array('usage' => array('input_tokens' => '7', 'output_tokens' => '2'))), 'input and output names');
	pg_assert_same(array('in' => 0, 'out' => 0), ws_ai_usage_tokens(array('choices' => array())), 'no usage counts nothing');
	pg_assert_same(array('in' => 0, 'out' => 0), ws_ai_usage_tokens(null));
	pg_assert_same(array('in' => 0, 'out' => 5), ws_ai_usage_tokens(array('usage' => array('prompt_tokens' => -3, 'completion_tokens' => 5))), 'never below 0');
	pg_assert_same(array('in' => 0, 'out' => 0), ws_ai_usage_tokens(array('usage' => 'many')));
}

function test_workspace_ai_tokens_periods()
{
	$periods = ws_ai_tokens_periods(array(
		array('week' => 1, 'tokens_in' => '100', 'tokens_out' => '20'),
		array('week' => '0', 'tokens_in' => 50, 'tokens_out' => 5),
		array('week' => 1, 'tokens_in' => null, 'tokens_out' => null),
	));

	pg_assert_same(array('in' => 100, 'out' => 20), $periods['d7']);
	pg_assert_same(array('in' => 150, 'out' => 25), $periods['d30'], 'the week is within the month');
	pg_assert_same(array('d7' => array('in' => 0, 'out' => 0), 'd30' => array('in' => 0, 'out' => 0)), ws_ai_tokens_periods(array()));
}

function test_workspace_agent_activity_tokens()
{
	$tokens = ws_agent_activity_tokens(array(
		array('agent' => 'model:9', 'status' => 'answered', 'week' => 1, 'tokens_in' => 10, 'tokens_out' => 4),
		array('agent' => 'model:9', 'status' => 'failed', 'week' => 0, 'tokens_in' => 6, 'tokens_out' => 0),
		array('agent' => 'app:4', 'status' => 'answered', 'week' => 1, 'tokens_in' => 0, 'tokens_out' => 0),
		array('agent' => 'ai', 'status' => 'answered', 'week' => 1, 'tokens_in' => 99, 'tokens_out' => 99),
	));

	pg_assert_same(array(9, 4), array_keys($tokens), 'Pinegrap AI is not a registry agent');
	pg_assert_same(array('in' => 10, 'out' => 4), $tokens[9]['d7']);
	pg_assert_same(array('in' => 16, 'out' => 4), $tokens[9]['d30'], 'every status counts');
}

function test_workspace_ai_tokens_text()
{
	$text = ws_ai_tokens_line(array('d7' => array('in' => 5, 'out' => 2), 'd30' => array('in' => 8, 'out' => 3)));

	pg_assert_true(strpos($text, '5') !== false && strpos($text, '8') !== false, 'both periods in the line');
}
