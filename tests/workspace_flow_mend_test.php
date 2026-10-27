<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a failed box of a flow's run tried again or skipped by hand
 * (includes/workspace/flow_runs.php, ws_flow_step_mend()): which boxes and
 * runs take it, which boxes skipped behind the failed one are walked again,
 * and, read from the source, the conditional write that keeps a double click
 * from doing it twice, the owner-only check, the API routes that refuse an
 * assistant and a run the application does not reach, and the canvas
 * buttons offered to the owner alone.
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

// A graph of boxes and arrows ([from, to, on]), kept in its shape.
function ws_flow_mend_test_graph($nodes, $edges)
{
	$out = array('nodes' => array(), 'edges' => array());

	foreach ($nodes as $id => $kind) {
		$out['nodes'][] = array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => ($kind === 'task') ? array('title' => ucfirst($id)) : array());
	}

	foreach ($edges as $edge) {
		$out['edges'][] = array('from' => $edge[0], 'to' => $edge[1], 'on' => $edge[2]);
	}

	return ws_flow_graph_normalize($out);
}

// Steps as the runner reads them: node id => status and outcome.
function ws_flow_mend_test_steps($states)
{
	$out = array();

	foreach ($states as $id => $state) {
		$parts = explode(':', $state);
		$out[$id] = array('status' => $parts[0], 'outcome' => $parts[1] ?? '');
	}

	return $out;
}

// The source of a function, comments taken out.
function ws_flow_mend_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Only a failed box, of a run not done or cancelled, is tried again or
// skipped: a box at work, waiting, done or skipped is refused, as is a box
// of a run that ended any other way than failed.
function test_workspace_flow_mend_refusal()
{
	foreach (array('running', 'waiting', 'failed') as $run) {
		pg_assert_same('', ws_flow_step_mend_refusal($run, 'failed'), 'a failed box of a ' . $run . ' run');
	}

	foreach (array('ready', 'running', 'waiting', 'done', 'skipped') as $step) {
		pg_assert_same('not_failed', ws_flow_step_mend_refusal('failed', $step), $step);
	}

	pg_assert_same('ended', ws_flow_step_mend_refusal('cancelled', 'failed'));
	pg_assert_same('ended', ws_flow_step_mend_refusal('done', 'failed'));
}

// The boxes skipped behind a failed box are the ones walked again: those an
// arrow from it leads into, and on through the boxes skipped after them;
// a box skipped on a branch of its own, or one that ran, stays.
function test_workspace_flow_mend_skipped_behind()
{
	$graph = ws_flow_mend_test_graph(
		array('start' => 'manual', 'a' => 'task', 'b' => 'task', 'c' => 'task', 'side' => 'task', 'join' => 'task', 'finish' => 'end'),
		array(
			array('start', 'a', 'next'), array('a', 'b', 'done'), array('b', 'join', 'done'),
			array('start', 'side', 'next'), array('side', 'join', 'done'), array('join', 'finish', 'done'),
			array('a', 'c', 'cancelled'),
		)
	);
	$steps = ws_flow_mend_test_steps(array(
		'start' => 'done:next', 'a' => 'failed', 'b' => 'skipped', 'c' => 'skipped',
		'side' => 'done:done', 'join' => 'skipped', 'finish' => 'skipped',
	));

	pg_assert_same(array('b', 'c', 'join', 'finish'), ws_flow_skipped_behind($graph, $steps, 'a'));
	pg_assert_same(array(), ws_flow_skipped_behind($graph, $steps, 'side'), 'nothing skipped behind a box done');

	// Walked again with the box tried and done, the join goes on.
	unset($steps['b'], $steps['c'], $steps['join'], $steps['finish']);
	$steps['a'] = array('status' => 'done', 'outcome' => 'done');

	pg_assert_same(array('ready' => array('b'), 'skipped' => array('c')), ws_flow_walk($graph, $steps));

	// Skipped by hand, the run goes on as for a branch not taken: the join
	// still has the side's arrow.
	$steps['a'] = array('status' => 'skipped', 'outcome' => '');

	$walk = ws_flow_walk($graph, $steps);

	pg_assert_same(array('join'), $walk['ready']);
	pg_assert_same(array('b', 'c'), $walk['skipped']);
}

// Two clicks never do it twice: the box changes only from failed, the
// affected rows are counted before anything else is written, and only rows
// the walk skipped (never run, attempt 0) are taken back.
function test_workspace_flow_mend_is_conditional()
{
	$mend = ws_flow_mend_test_source('ws_flow_step_mend');

	pg_assert_contains("AND status = 'failed'", $mend);
	pg_assert_contains('mysqli_affected_rows(', $mend);
	pg_assert_true(strpos($mend, 'mysqli_affected_rows(') < strpos($mend, 'DELETE FROM ws_flow_run_steps'), 'counted before the skipped boxes go');
	pg_assert_contains("status = 'skipped' AND attempt = 0", $mend);
	pg_assert_contains("status IN ('running', 'waiting', 'failed')", $mend);
	pg_assert_contains('ws_flow_run_refresh(', $mend);
	pg_assert_contains('log_activity(', $mend);

	// A box tried again forgets what it began and the task it waited for.
	pg_assert_contains("effect = ''", $mend);
	pg_assert_contains("wait_key = ''", $mend);
}

// Only the owner tries a box again or skips it: the box runs again in their
// name. Refused before the box is read.
function test_workspace_flow_mend_is_the_owners()
{
	$check = ws_flow_mend_test_source('ws_flow_step_mend_check');

	pg_assert_contains('!ws_flow_share_manager($viewer, $flow)', $check);
	pg_assert_contains('ws_flow_run_access(', $check);
	pg_assert_true(strpos($check, 'ws_flow_share_manager(') < strpos($check, 'ws_flow_run_steps('), 'refused before the boxes are read');
	pg_assert_contains('ws_flow_step_mend_refusal(', $check);
}

// The API: two write routes, rehearsed, no MCP tool, refused to an
// assistant's application first, a run the application does not reach a
// 404.
function test_workspace_flow_mend_api()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

	$api = ws_flow_mend_test_source('ws_api_flow_runs_mend');

	pg_assert_true(strpos($api, 'ws_api_is_assistant()') < strpos($api, 'ws_api_flow_run_or_404('), 'refused first');
	pg_assert_contains("api_fail(403, 'forbidden'", $api);
	pg_assert_true(strpos($api, 'ws_flow_step_mend_check(') < strpos($api, 'api_dry_run_stop('), 'checked before the rehearsal stops');
	pg_assert_true(strpos($api, 'api_dry_run_stop(') < strpos($api, 'ws_flow_step_mend('), 'rehearsed before the write');

	$found = array();

	foreach (ws_api_routes() as $route) {
		if (in_array($route['id'], array('workspace.flow_runs.step_retry', 'workspace.flow_runs.step_skip'), true)) {
			$found[$route['id']] = $route;
		}
	}

	pg_assert_same(2, count($found));

	foreach ($found as $route) {
		pg_assert_same('POST', $route['method']);
		pg_assert_same('workspace:write', $route['scope']);
		pg_assert_true(!empty($route['dry_run']));
		pg_assert_false($route['mcp']);
	}

	pg_assert_same('/workspace/flow-runs/{id}/steps/{node}/retry', $found['workspace.flow_runs.step_retry']['path']);
	pg_assert_same('/workspace/flow-runs/{id}/steps/{node}/skip', $found['workspace.flow_runs.step_skip']['path']);
}

// The canvas offers Retry and Skip on a failed box to the flow's owner.
function test_workspace_flow_mend_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_contains("K.api('ws_flow_step_mend'", $js);
	pg_assert_true((bool) preg_match("/state\.flow && state\.flow\.manage && step && \(step\.status === 'failed'\)/", $js));
	pg_assert_contains("'ws_flow_step_mend'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'));
}
