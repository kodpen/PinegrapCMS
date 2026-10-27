<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the values a run of a flow hands out per box
 * (includes/workspace/api_flows.php): the arrows into a box as the run took
 * them (ws_api_flow_step_threads_in()) and the values the box kept
 * (ws_api_flow_step_outputs()), with what a reader who may not change the
 * flow is not given.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

// start -> check; check -> post on done, -> mail on failed, -> join always;
// post -> join on done.
function ws_flow_run_values_test_graph()
{
	$edges = array(
		array('start', 'check', 'next'),
		array('check', 'post', 'done'),
		array('check', 'mail', 'failed'),
		array('check', 'join', 'always'),
		array('post', 'join', 'done'),
	);
	$out = array('nodes' => array(), 'edges' => array());

	foreach ($edges as $edge) {
		$out['edges'][] = array('from' => $edge[0], 'to' => $edge[1], 'on' => $edge[2]);
	}

	return $out;
}

// An arrow is taken when its box ended done on its outcome, or on any for
// always; a box still going, failed or not reached takes none.
function test_ws_flow_run_values_threads_in()
{
	$graph = ws_flow_run_values_test_graph();
	$states = ws_flow_step_states(array(
		array('node_id' => 'start', 'status' => 'done', 'outcome' => 'next'),
		array('node_id' => 'check', 'status' => 'done', 'outcome' => 'done'),
		array('node_id' => 'post', 'status' => 'running', 'outcome' => ''),
	));

	pg_assert_same(array(array('from' => 'start', 'on' => 'next', 'taken' => true)), ws_api_flow_step_threads_in($graph, $states, 'check'));
	pg_assert_same(array(array('from' => 'check', 'on' => 'failed', 'taken' => false)), ws_api_flow_step_threads_in($graph, $states, 'mail'));
	pg_assert_same(array(
		array('from' => 'check', 'on' => 'always', 'taken' => true),
		array('from' => 'post', 'on' => 'done', 'taken' => false),
	), ws_api_flow_step_threads_in($graph, $states, 'join'));
	pg_assert_same(array(), ws_api_flow_step_threads_in($graph, $states, 'start'));

	// A failed box takes no arrow, not even one on always.
	$states['check'] = array('status' => 'failed', 'outcome' => '');
	pg_assert_same(false, ws_api_flow_step_threads_in($graph, $states, 'join')[0]['taken']);

	// Without the run's boxes every arrow reads as not taken.
	pg_assert_same(false, ws_api_flow_step_threads_in($graph, array(), 'check')[0]['taken']);
}

// The data the box kept, numbers, texts and nulls as they are; anything else
// in it is left out; a long text is cut to the result's length.
function test_ws_flow_run_values_outputs()
{
	$stored = json_encode(array('text' => 'Sent.', 'data' => array(
		'status_code' => 200,
		'duration_ms' => null,
		'waited_until' => '2026-10-27',
		'nested' => array('a' => 1),
		'flag' => true,
	)));

	pg_assert_same(array('status_code' => 200, 'duration_ms' => null, 'waited_until' => '2026-10-27'), ws_api_flow_step_outputs('webhook', $stored, false));
	pg_assert_same(array(), ws_api_flow_step_outputs('webhook', 'Plain text kept before results were JSON', true));
	pg_assert_same(array(), ws_api_flow_step_outputs('webhook', null, true));
	pg_assert_same(array(), ws_api_flow_step_outputs('webhook', json_encode(array('text' => 'No data')), true));

	$long = json_encode(array('text' => '', 'data' => array('message' => str_repeat('a', WS_API_FLOW_RESULT_CHARS + 10))));
	pg_assert_same(WS_API_FLOW_RESULT_CHARS, mb_strlen(ws_api_flow_step_outputs('post', $long, true)['message']));
}

// A change box's record and the run a start-a-flow box started go only to
// whoever may change the flow.
function test_ws_flow_run_values_outputs_kept_back()
{
	$change = json_encode(array('text' => 'Changed.', 'data' => array('record_id' => 42)));
	$chain = json_encode(array('text' => 'Started.', 'data' => array('run_id' => 7)));

	pg_assert_same(array('record_id' => 42), ws_api_flow_step_outputs('change', $change, true));
	pg_assert_same(array(), ws_api_flow_step_outputs('change', $change, false));
	pg_assert_same(array('run_id' => 7), ws_api_flow_step_outputs('start_flow', $chain, true));
	pg_assert_same(array(), ws_api_flow_step_outputs('start_flow', $chain, false));
}

// The run's single read hands its boxes their states; the presenter fills
// both fields and the schema names them.
function test_ws_flow_run_values_wired()
{
	$present = (new ReflectionFunction('ws_api_flow_run_step_present'))->getNumberOfParameters();
	pg_assert_same(4, $present);
	pg_assert_same(array('from' => 'string', 'on' => 'string', 'taken' => 'boolean'), ws_api_flow_run_step_schema()['threads_in'][0]);
	pg_assert_same('object', ws_api_flow_run_step_schema()['outputs']);
}
