<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the delay box of the workspace's flows
 * (includes/workspace/flow_runs.php): its settings, the range it keeps (a
 * scheduled action's start delay: at most a week), its publish problems, the
 * moment it waits until, the output it declares and writes, that the boxes
 * after it are not ready while it waits and are once it settles, and, read
 * from the source, the one conditional write that makes it wait, the look
 * that settles it when its time comes, and the try that only says what it
 * would do.
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
function ws_flow_delay_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Started by hand, a wait, then a task.
function ws_flow_delay_test_graph()
{
	return ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array()),
			array('id' => 'wait', 'kind' => 'delay', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('amount' => 2, 'unit' => 'hours')),
			array('id' => 'call', 'kind' => 'task', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('title' => 'Call')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'wait', 'on' => 'next'),
			array('from' => 'wait', 'to' => 'call', 'on' => 'done'),
		),
	));
}

// The kind is a box of its own, with one outcome and its declared output.
function test_workspace_flow_delay_kind()
{
	$kind = ws_flow_kinds()['delay'];

	pg_assert_false($kind['trigger']);
	pg_assert_false($kind['step'], 'no scheduled step runs it');
	pg_assert_same(array('done'), $kind['outcomes']);
	pg_assert_same(array('waited_until'), array_column($kind['outputs'], 'key'));
	pg_assert_same(array('text'), array_column($kind['outputs'], 'type'));
}

// Its settings: a whole amount and a unit; anything else is dropped.
function test_workspace_flow_delay_config()
{
	pg_assert_same(array('amount' => 2, 'unit' => 'hours'), ws_flow_config_normalize('delay', array('amount' => '2', 'unit' => 'hours', 'x' => 1)));
	pg_assert_same(array('amount' => '', 'unit' => 'minutes'), ws_flow_config_normalize('delay', array()));
	pg_assert_same(array('amount' => '', 'unit' => 'minutes'), ws_flow_config_normalize('delay', array('amount' => 'soon', 'unit' => 'weeks')));
	pg_assert_same(array('amount' => 0, 'unit' => 'days'), ws_flow_config_normalize('delay', array('amount' => -3, 'unit' => 'days')));
	pg_assert_same(array('amount' => 99999, 'unit' => 'days'), ws_flow_config_normalize('delay', array('amount' => 10 ** 9, 'unit' => 'days')), 'held to a number the problems name');

	pg_assert_same(120, ws_flow_delay_minutes(array('amount' => 2, 'unit' => 'hours')));
	pg_assert_same(4320, ws_flow_delay_minutes(array('amount' => 3, 'unit' => 'days')));
	pg_assert_same(0, ws_flow_delay_minutes(array('amount' => '', 'unit' => 'minutes')));
}

// The range of a scheduled action's start delay: a minute to a week.
function test_workspace_flow_delay_problems()
{
	pg_assert_same(10080, WS_FLOW_DELAY_MAX);
	pg_assert_same(array(), ws_flow_delay_problems(array('amount' => 1, 'unit' => 'minutes'), 'Wait'));
	pg_assert_same(array(), ws_flow_delay_problems(array('amount' => 7, 'unit' => 'days'), 'Wait'));
	pg_assert_same(1, count(ws_flow_delay_problems(array('amount' => '', 'unit' => 'minutes'), 'Wait')), 'no time set');
	pg_assert_same(1, count(ws_flow_delay_problems(array('amount' => 0, 'unit' => 'hours'), 'Wait')), 'nothing to wait');
	pg_assert_same(1, count(ws_flow_delay_problems(array('amount' => 169, 'unit' => 'hours'), 'Wait')), 'over a week');

	$check = ws_flow_graph_check(ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'config' => array()),
			array('id' => 'wait', 'kind' => 'delay', 'config' => array('amount' => 8, 'unit' => 'days')),
		),
		'edges' => array(array('from' => 'start', 'to' => 'wait', 'on' => 'next')),
	)));
	$codes = array();

	foreach ($check as $problem) {
		$codes[] = $problem['code'] . ':' . $problem['node'];
	}

	pg_assert_true(in_array('bad_config:wait', $codes, true), 'a publish problem');
}

// The moment it waits until, the key it waits on, and how a look settles it.
function test_workspace_flow_delay_until_and_settlement()
{
	$from = 1700000000;
	$until = ws_flow_delay_until(array('amount' => 2, 'unit' => 'hours'), $from);

	pg_assert_same($from + 7200, $until);
	pg_assert_same('delay:' . $until, ws_flow_delay_key($until));
	pg_assert_same($until, ws_flow_delay_key_until('delay:' . $until));
	pg_assert_same(0, ws_flow_delay_key_until('task:12'));
	pg_assert_same(0, ws_flow_delay_key_until(''));

	// Not before its time; done, with its output, after.
	pg_assert_same('', ws_flow_delay_settlement('delay:' . $until, $until - 1)['status']);

	$done = ws_flow_delay_settlement('delay:' . $until, $until);

	pg_assert_same('done', $done['status']);
	pg_assert_same('done', $done['outcome']);
	pg_assert_same(array('waited_until' => ws_flow_time($until)), $done['data']);

	// It waits on no event, so nothing but its look wakes it, and a box
	// stopped half way never takes it for one waiting.
	pg_assert_same(null, ws_flow_effect_wait('delay:' . $until));
	pg_assert_same(0, ws_flow_effect_task_id('delay:' . $until));
}

// The boxes after it are not ready while it waits and are once it settles.
function test_workspace_flow_delay_holds_the_next_boxes()
{
	$graph = ws_flow_delay_test_graph();
	$waiting = array(
		'start' => array('status' => 'done', 'outcome' => 'next'),
		'wait'  => array('status' => 'waiting', 'outcome' => ''),
	);

	pg_assert_same(array(), ws_flow_walk($graph, $waiting)['ready']);
	pg_assert_same('waiting', ws_flow_run_state($graph, $waiting));

	$settled = ws_flow_delay_settlement(ws_flow_delay_key(100), 100);
	$done = array(
		'start' => array('status' => 'done', 'outcome' => 'next'),
		'wait'  => array('status' => $settled['status'], 'outcome' => $settled['outcome']),
	);

	pg_assert_same(array('call'), ws_flow_walk($graph, $done)['ready']);
}

// Read from the source: the box waits in one conditional write, on its
// time, never sleeping; its look settles it; the drivers see it due.
function test_workspace_flow_delay_runs_by_its_look()
{
	$run = ws_flow_delay_test_source('ws_flow_step_run');

	pg_assert_contains("case 'delay':", $run);
	pg_assert_contains('ws_flow_delay_run(', $run);

	$delay = ws_flow_delay_test_source('ws_flow_delay_run');

	pg_assert_true((bool) preg_match("/effect = '[^;]*status = 'waiting'[^;]*wait_key = '[^;]*wake_at = '[^;]*AND status = 'running'/s", $delay), 'effect, waiting, key and time in one conditional UPDATE');
	pg_assert_false(strpos($delay, 'sleep(') !== false, 'no sleeping');
	pg_assert_contains('ws_flow_run_refresh(', $delay);

	$recheck = ws_flow_delay_test_source('ws_flow_step_recheck');

	pg_assert_contains('ws_flow_delay_recheck(', $recheck);
	pg_assert_true(strpos($recheck, 'ws_flow_delay_recheck(') < strpos($recheck, 'ws_flow_effect_task_id('), 'before the task is looked for');

	$look = ws_flow_delay_test_source('ws_flow_delay_recheck');

	pg_assert_contains('ws_flow_delay_settlement(', $look);
	pg_assert_contains("ws_flow_step_settle(\$step, 'waiting', 'done', 'done'", $look);

	// ws_flow_due() and ws_flow_steps_recheck() read a waiting box by its
	// next look.
	pg_assert_contains("status = 'waiting' AND wake_at > 0 AND wake_at <= ", ws_flow_delay_test_source('ws_flow_due'));
	pg_assert_contains("status = 'waiting' AND wake_at > 0 AND wake_at <= ", ws_flow_delay_test_source('ws_flow_steps_recheck'));
}

// Trying the box says what it would do without waiting.
function test_workspace_flow_delay_try()
{
	$try = ws_flow_delay_test_source('ws_flow_box_try');

	pg_assert_contains("case 'delay':", $try);
	pg_assert_contains('ws_flow_delay_until(', $try);
	pg_assert_false(strpos($try, 'ws_flow_delay_run(') !== false, 'never waits');

	pg_assert_same('2 hours', ws_flow_delay_text(array('amount' => 2, 'unit' => 'hours')));
	pg_assert_same('1 day', ws_flow_delay_text(array('amount' => 1, 'unit' => 'days')));
	pg_assert_same('45 minutes', ws_flow_delay_text(array('amount' => 45, 'unit' => 'minutes')));
}
