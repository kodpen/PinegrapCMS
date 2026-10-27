<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the timeout of a waiting task box of the workspace's flows
 * (includes/workspace/flow_runs.php): the setting and its range, the
 * timeout outcome, the moment it runs out and the next look that meets it,
 * the arrow on timeout a box without one may not have, how a box that ran
 * out settles, that the boxes after it on timeout are ready once it does,
 * and, read from the source, that the wait keeps its next look within the
 * deadline and that the task's own end and the timeout settle the box in
 * one conditional write, so the two never both win.
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

// A function's source without its comments.
function ws_flow_timeout_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Started by hand, a task with a timeout, a reminder on timeout and a
// next step on done.
function ws_flow_timeout_test_graph($timeout = 24)
{
	return ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'config' => array()),
			array('id' => 'call', 'kind' => 'task', 'config' => array('title' => 'Call', 'timeout' => $timeout)),
			array('id' => 'remind', 'kind' => 'task', 'config' => array('title' => 'Remind')),
			array('id' => 'next', 'kind' => 'end', 'config' => array()),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'call', 'on' => 'next'),
			array('from' => 'call', 'to' => 'next', 'on' => 'done'),
			array('from' => 'call', 'to' => 'remind', 'on' => 'timeout'),
		),
	));
}

// The task box leaves on timeout as well as on done and cancelled.
function test_workspace_flow_timeout_outcome()
{
	pg_assert_same(array('done', 'cancelled', 'timeout'), ws_flow_kinds()['task']['outcomes']);
	pg_assert_same(720, WS_FLOW_TIMEOUT_MAX, 'as long as an approval may wait for its decision');
}

// The setting: '' for none, else whole hours from 1 to the most.
function test_workspace_flow_timeout_config()
{
	$normalize = function ($timeout) {
		return ws_flow_config_normalize('task', array('title' => 'Call', 'timeout' => $timeout))['timeout'];
	};

	pg_assert_same(24, $normalize('24'));
	pg_assert_same(1, $normalize(0), 'at least an hour');
	pg_assert_same(720, $normalize(5000), 'at most the most');
	pg_assert_same('', $normalize(''));
	pg_assert_same('', $normalize('soon'));
	pg_assert_same('', $normalize(true));
	pg_assert_same('', ws_flow_config_normalize('task', array('title' => 'Call'))['timeout'], 'none by default: a box waits as it always did');

	pg_assert_same(24, ws_flow_timeout_hours(array('timeout' => 24)));
	pg_assert_same(0, ws_flow_timeout_hours(array('timeout' => '')));
	pg_assert_same(0, ws_flow_timeout_hours(array()));
}

// The moment it runs out, counted from when the box began, and the next
// look, which never falls after it.
function test_workspace_flow_timeout_moment()
{
	$since = 1700000000;

	pg_assert_same($since + 2 * 3600, ws_flow_timeout_at(array('timeout' => 2), $since));
	pg_assert_same(0, ws_flow_timeout_at(array('timeout' => ''), $since), 'no timeout');
	pg_assert_same(0, ws_flow_timeout_at(array('timeout' => 2), 0), 'no start known');

	pg_assert_same($since + WS_FLOW_RECHECK, ws_flow_timeout_next_look($since, 0), 'hourly without a timeout');
	pg_assert_same($since + WS_FLOW_RECHECK, ws_flow_timeout_next_look($since, $since + 5 * 3600), 'hourly while it is far');
	pg_assert_same($since + 600, ws_flow_timeout_next_look($since, $since + 600), 'at the deadline when it comes sooner');
	pg_assert_same($since, ws_flow_timeout_next_look($since, $since - 10), 'at once once it passed');

	pg_assert_false(ws_flow_timed_out($since + 3600, $since + 3599));
	pg_assert_true(ws_flow_timed_out($since + 3600, $since + 3600));
	pg_assert_false(ws_flow_timed_out(0, $since), 'never without a timeout');
}

// An arrow on timeout needs a timeout on the box.
function test_workspace_flow_timeout_problems()
{
	$codes = function ($graph) {
		$out = array();

		foreach (ws_flow_graph_check($graph) as $problem) {
			$out[] = $problem['code'] . ':' . $problem['node'];
		}

		return $out;
	};

	pg_assert_false(in_array('bad_config:call', $codes(ws_flow_timeout_test_graph(24)), true), 'a timeout and its arrow');
	pg_assert_false(in_array('bad_outcome:call', $codes(ws_flow_timeout_test_graph(24)), true), 'timeout is an outcome of the box');
	pg_assert_true(in_array('bad_config:call', $codes(ws_flow_timeout_test_graph('')), true), 'an arrow on timeout and no timeout');

	pg_assert_same(array(), ws_flow_timeout_problems(array('timeout' => 24), 'Call', true));
	pg_assert_same(array(), ws_flow_timeout_problems(array('timeout' => 24), 'Call', false), 'a timeout without an arrow fails the box');
	pg_assert_same(array(), ws_flow_timeout_problems(array('timeout' => ''), 'Call', false));
	pg_assert_same(1, count(ws_flow_timeout_problems(array('timeout' => ''), 'Call', true)));
}

// Run out: done on timeout where an arrow leaves on it, else failed, as a
// cancelled task fails a box without an arrow on cancelled.
function test_workspace_flow_timeout_settlement()
{
	pg_assert_same(array('status' => 'done', 'outcome' => 'timeout'), ws_flow_timeout_settlement(true));
	pg_assert_same(array('status' => 'failed', 'outcome' => 'timeout'), ws_flow_timeout_settlement(false));

	$graph = ws_flow_timeout_test_graph(24);
	$settled = ws_flow_timeout_settlement(ws_flow_outcome_routed($graph, 'call', 'timeout'));
	$steps = array(
		'start' => array('status' => 'done', 'outcome' => 'next'),
		'call'  => array('status' => $settled['status'], 'outcome' => $settled['outcome']),
	);

	pg_assert_same(array('remind'), ws_flow_walk($graph, $steps)['ready'], 'the timeout branch goes on');
	pg_assert_same(array('next'), ws_flow_walk($graph, $steps)['skipped'], 'the done branch is skipped');

	$waiting = array(
		'start' => array('status' => 'done', 'outcome' => 'next'),
		'call'  => array('status' => 'waiting', 'outcome' => ''),
	);

	pg_assert_same(array(), ws_flow_walk($graph, $waiting)['ready'], 'nothing while it waits');
}

// Read from the source: the task box waits with its next look within the
// deadline; its look reads the task first and then the deadline, and settles
// it in the one conditional write any other end of it goes through; the
// hourly look keeps the deadline too.
function test_workspace_flow_timeout_wait_and_look()
{
	$task = ws_flow_timeout_test_source('ws_flow_step_task');

	pg_assert_contains('ws_flow_timeout_at(', $task);
	pg_assert_contains('ws_flow_timeout_next_look(', $task);

	$look = ws_flow_timeout_test_source('ws_flow_step_recheck');

	pg_assert_contains('ws_flow_timed_out(', $look);
	pg_assert_contains('ws_flow_timeout_settlement(', $look);
	pg_assert_true(strpos($look, 'ws_flow_task_settlement(') < strpos($look, 'ws_flow_timed_out('), 'a task that ended counts before the deadline');
	pg_assert_contains("ws_flow_step_settle(\$step, 'waiting', \$timeout['status'], \$timeout['outcome']", $look);
	pg_assert_true((bool) preg_match("/SET wake_at = '[^;]*AND status = 'waiting' AND wake_at > /s", $look), 'the next look brought back to the deadline, only while it waits');

	$settle = ws_flow_timeout_test_source('ws_flow_step_settle');

	pg_assert_contains("AND status = '\" . e(\$from) . \"'", $settle, 'from waiting only: the event and the timeout never both settle it');
}
