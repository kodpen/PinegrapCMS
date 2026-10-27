<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the box that starts another flow (includes/workspace/flow_runs.php):
 * its settings, what refuses a start (itself, a flow not live, one not
 * started by hand, a chain at its depth), the start key that keeps a box
 * from starting two runs, and what the started run keeps of the one that
 * started it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A function's source without its comments.
function ws_flow_chain_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The box is a kind of its own, left on done or failed, giving the run it
// started; its settings are the flow it starts.
function test_workspace_flow_chain_is_a_kind()
{
	$kind = ws_flow_kinds()['start_flow'];

	pg_assert_false($kind['trigger']);
	pg_assert_false($kind['step']);
	pg_assert_same(array('done', 'failed'), $kind['outcomes']);
	pg_assert_same(array('run_id'), array_column($kind['outputs'], 'key'));

	pg_assert_same(array('flow_id' => 7), ws_flow_config_normalize('start_flow', array('flow_id' => '7', 'extra' => 1)));
	pg_assert_same(array('flow_id' => 0), ws_flow_config_normalize('start_flow', array('flow_id' => -3)));
	pg_assert_same(array('flow_id' => 0), ws_flow_config_normalize('start_flow', array('flow_id' => true)));
}

// A box without a flow cannot be published.
function test_workspace_flow_chain_problems()
{
	$graph = function ($flow_id) {
		return ws_flow_graph_normalize(array(
			'nodes' => array(
				array('id' => 'start', 'kind' => 'manual'),
				array('id' => 'next', 'kind' => 'start_flow', 'config' => array('flow_id' => $flow_id)),
			),
			'edges' => array(array('from' => 'start', 'to' => 'next')),
		));
	};
	$codes = function ($graph) {
		return array_map(function ($problem) {
			return $problem['code'] . ':' . $problem['node'];
		}, ws_flow_graph_check($graph));
	};

	pg_assert_same(array('bad_config:next'), $codes($graph(0)));
	pg_assert_same(array(), $codes($graph(4)));
}

// What refuses a start: the flow itself (flows have no loops), a flow that
// is not live, one that starts on its own, a chain 8 deep.
function test_workspace_flow_chain_refusal()
{
	$live = array('id' => 4, 'status' => 'live', 'version_id' => 9);

	pg_assert_same('', ws_flow_chain_refusal(3, $live, 'manual', 0));
	pg_assert_same('', ws_flow_chain_refusal(3, $live, 'manual', WS_SCHEDULED_CHAIN_DEPTH - 2));
	pg_assert_same('self', ws_flow_chain_refusal(4, $live, 'manual', 0));
	pg_assert_same('gone', ws_flow_chain_refusal(3, null, 'manual', 0));
	pg_assert_same('not_live', ws_flow_chain_refusal(3, array('status' => 'paused') + $live, 'manual', 0));
	pg_assert_same('not_live', ws_flow_chain_refusal(3, array('version_id' => 0) + $live, 'manual', 0));
	pg_assert_same('not_by_hand', ws_flow_chain_refusal(3, $live, 'schedule', 0));
	pg_assert_same('not_by_hand', ws_flow_chain_refusal(3, $live, '', 0));
	pg_assert_same('depth', ws_flow_chain_refusal(3, $live, 'manual', WS_SCHEDULED_CHAIN_DEPTH - 1), 'the run it starts would be the ninth');
	pg_assert_same(8, WS_SCHEDULED_CHAIN_DEPTH);
}

// One start per box and pass: the same box carried out twice meets its key.
function test_workspace_flow_chain_key()
{
	pg_assert_same('flow:12-next-0', ws_flow_chain_key(12, 'next', 0));
	pg_assert_same('flow:12-next-2', ws_flow_chain_key(12, 'next', 2));
	pg_assert_true(strlen(ws_flow_chain_key(99999999999, str_repeat('a', 32), 99)) <= 64);
}

// The run started keeps what started its source (the event, the newcomer)
// and names the flow and the box sentence that led to it, for {{source}}
// and {{source_result}}.
function test_workspace_flow_chain_context()
{
	$context = ws_flow_chain_context(
		array('id' => 4, 'name' => 'Welcome pack', 'channel_id' => 6),
		array('id' => 'start', 'kind' => 'manual'),
		array('trigger' => 'join', 'flow_name' => 'Greeter', 'newcomer' => 12, 'join_channel_id' => 5, 'channel_id' => 2),
		'Greeter',
		'written in #general'
	);

	pg_assert_same('chain', $context['trigger']);
	pg_assert_same('start', $context['node']);
	pg_assert_same(6, $context['channel_id']);
	pg_assert_same('Welcome pack', $context['flow_name']);
	pg_assert_same('Greeter', $context['source']);
	pg_assert_same('written in #general', $context['source_result']);
	pg_assert_same(12, $context['newcomer']);
	pg_assert_same(5, $context['join_channel_id']);

	pg_assert_same(
		array('source' => 'Greeter', 'source_result' => 'written in #general'),
		ws_step_trigger_context(array('source' => 'Greeter', 'source_result' => 'written in #general'))
	);
}

// Run as the source flow's owner with the target's rights to run it, never
// twice, its depth one more than the run that started it, within the
// target's open and hourly limits.
function test_workspace_flow_chain_start_source()
{
	$start = ws_flow_chain_test_source('ws_flow_chain_start');

	pg_assert_contains('ws_flow_access($owner, $flow)', $start);
	pg_assert_contains('ws_flow_chain_refusal(', $start);
	pg_assert_contains('ws_flow_start_refusal(', $start);
	pg_assert_contains('ws_flow_chain_key(', $start);
	pg_assert_contains("(int) \$run['depth'] + 1", $start);
	pg_assert_true(strpos($start, 'WS_STEP_BEGAN') < strpos($start, 'INSERT INTO ws_flow_runs'), 'the mark is written before the start');

	pg_assert_contains("case 'start_flow':", ws_flow_chain_test_source('ws_flow_step_run'));
	pg_assert_contains("'start_flow'", ws_flow_chain_test_source('ws_flow_publish_check'));
}

// {{source_result}}: the sentence of the box done whose arrow led in.
function test_workspace_flow_chain_source_result()
{
	$graph = array('edges' => array(
		array('from' => 'check', 'to' => 'next', 'on' => 'no'),
		array('from' => 'say', 'to' => 'next', 'on' => 'done'),
	));
	$values = array(
		'check' => array('text' => 'did not hold', 'status' => 'skipped'),
		'say'   => array('text' => 'written in #general', 'status' => 'done'),
	);

	pg_assert_same('written in #general', ws_flow_chain_source_result($graph, 'next', $values));
	pg_assert_same('', ws_flow_chain_source_result($graph, 'other', $values));
	pg_assert_same('', ws_flow_chain_source_result($graph, 'next', array()));
}

// A reader who may not change the flow does not read the name of the flow
// a box started.
function test_workspace_flow_chain_result_kept_back()
{
	$text = 'flow “Payroll” started, run 4';

	pg_assert_same($text, ws_step_result_shown('start_flow', 'done', $text, true));
	pg_assert_same('Another flow was started.', ws_step_result_shown('start_flow', 'done', $text, false));
	pg_assert_same('Another flow could not be started.', ws_step_result_shown('start_flow', 'failed', 'The flow to start is not live.', false));
}
