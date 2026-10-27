<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the approval box of the workspace's flows (includes/workspace/
 * steps.php, flow_runs.php): its settings and problems, the outcomes it
 * leaves on, the key it waits on and the event that wakes it, how the way a
 * request ended settles it, that the boxes after it wait meanwhile, and,
 * read from the source, that the shared step gives the request's id, that
 * the box waits in one conditional write and that its hourly look covers it.
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
function ws_flow_approval_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Started by hand, an approval, then a post when it is approved.
function ws_flow_approval_test_graph()
{
	return ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'config' => array()),
			array('id' => 'ask', 'kind' => 'approval', 'config' => array('channel_id' => 3, 'title' => 'Discount', 'approvers' => array(5), 'rule' => 'any')),
			array('id' => 'tell', 'kind' => 'post', 'config' => array('channel_id' => 3, 'body' => 'Approved')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'ask', 'on' => 'next'),
			array('from' => 'ask', 'to' => 'tell'),
		),
	));
}

// A step box of its own, leaving on how the request ended or on failed.
function test_workspace_flow_approval_kind()
{
	$kind = ws_flow_kinds()['approval'];

	pg_assert_true($kind['step'], 'checked and run as a scheduled step');
	pg_assert_false($kind['trigger']);
	pg_assert_same(array('approved', 'rejected', 'expired', 'withdrawn', 'failed'), $kind['outcomes']);
	pg_assert_same(array('approval_id', 'message_id'), array_column($kind['outputs'], 'key'));
	pg_assert_same('approval', ws_step_kinds()['approval']['type']);

	// The other steps keep done and failed.
	pg_assert_same(array('done', 'failed'), ws_flow_kinds()['post']['outcomes']);

	// An arrow without an outcome leaves on the first: approved.
	pg_assert_same('approved', ws_flow_approval_test_graph()['edges'][1]['on']);
}

// Its settings: the channel, the title, the text, the people, the rule and
// a deadline in hours; anything else is dropped.
function test_workspace_flow_approval_config()
{
	$config = ws_flow_config_normalize('approval', array(
		'channel_id' => '4',
		'title'      => "Price\nchange",
		'text'       => 'Ten per cent off',
		'approvers'  => array('5', 5, 0, 'x', 7),
		'rule'       => 'all',
		'closes_in'  => '48',
		'other'      => 1,
	));

	pg_assert_same(array('channel_id' => 4, 'title' => 'Price change', 'text' => 'Ten per cent off', 'approvers' => array(5, 7), 'rule' => 'all', 'closes_in' => 48), $config);

	$empty = ws_flow_config_normalize('approval', array('rule' => 'most', 'closes_in' => 'soon'));

	pg_assert_same('any', $empty['rule']);
	pg_assert_same('', $empty['closes_in']);
	pg_assert_same(array(), $empty['approvers']);
	pg_assert_same(WS_STEP_APPROVAL_CLOSES_MAX, ws_flow_config_normalize('approval', array('closes_in' => 10 ** 6))['closes_in']);
	pg_assert_same(1, ws_flow_config_normalize('approval', array('closes_in' => 0))['closes_in'], 'at least an hour');
}

// A channel, a title and somebody to ask; without them it is not published.
function test_workspace_flow_approval_problems()
{
	$whole = ws_step_config_normalize('approval', array('channel_id' => 3, 'title' => 'Discount', 'approvers' => array(5)));

	pg_assert_same(array(), ws_step_config_problems('approval', $whole, 'Ask'));
	pg_assert_same(3, count(ws_step_config_problems('approval', ws_step_config_normalize('approval', array()), 'Ask')), 'channel, title, people');

	$codes = array();

	foreach (ws_flow_graph_check(ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'config' => array()),
			array('id' => 'ask', 'kind' => 'approval', 'config' => array('title' => 'Discount', 'approvers' => array(5))),
		),
		'edges' => array(array('from' => 'start', 'to' => 'ask', 'on' => 'next')),
	))) as $problem) {
		$codes[] = $problem['code'] . ':' . $problem['node'];
	}

	pg_assert_true(in_array('bad_config:ask', $codes, true), 'an approval needs a channel');
}

// The key it waits on, the event that wakes it, and a stale box resumes.
function test_workspace_flow_approval_key_and_wake()
{
	pg_assert_same('approval:12', ws_flow_approval_key(12));
	pg_assert_same(12, ws_flow_effect_approval_id('approval:12'));
	pg_assert_same(0, ws_flow_effect_approval_id('task:12'));
	pg_assert_same(0, ws_flow_effect_approval_id('approval:0'));
	pg_assert_same(0, ws_flow_effect_task_id('approval:12'));

	pg_assert_same('workspace.approval.decided', WS_FLOW_APPROVAL_WAIT);
	pg_assert_same('approval:12', ws_flow_wake_key('workspace.approval.decided', array('id' => 12, 'status' => 'approved')));
	pg_assert_same('', ws_flow_wake_key('workspace.approval.decided', array()));
	pg_assert_same('task:12', ws_flow_wake_key('workspace.task.status_changed', array('id' => 12)), 'a task still wakes by its own key');

	pg_assert_same(array('event' => WS_FLOW_APPROVAL_WAIT, 'key' => 'approval:12'), ws_flow_effect_wait('approval:12'));
	pg_assert_same('resume', ws_flow_stale_action(array('effect' => 'approval:12', 'attempt' => 1)), 'its request is out: it waits again');
	pg_assert_same('fail', ws_flow_stale_action(array('effect' => 'began:approval', 'attempt' => 1)), 'a request that may be out is never asked twice');
}

// How the way a request ended settles the box.
function test_workspace_flow_approval_settlement()
{
	// Still open: it waits on.
	pg_assert_same('', ws_flow_approval_settlement('', false)['status']);

	// A decision is a way on, with an arrow on it or without (the branch ends).
	pg_assert_same(array('status' => 'done', 'outcome' => 'approved'), ws_flow_approval_settlement('approved', false));
	pg_assert_same(array('status' => 'done', 'outcome' => 'rejected'), ws_flow_approval_settlement('rejected', false));

	// No decision: on its arrow, else the box fails.
	pg_assert_same(array('status' => 'done', 'outcome' => 'expired'), ws_flow_approval_settlement('expired', true));
	pg_assert_same(array('status' => 'failed', 'outcome' => 'expired'), ws_flow_approval_settlement('expired', false));
	pg_assert_same(array('status' => 'done', 'outcome' => 'withdrawn'), ws_flow_approval_settlement('withdrawn', true));
	pg_assert_same(array('status' => 'failed', 'outcome' => 'withdrawn'), ws_flow_approval_settlement('withdrawn', false));

	// A request gone is one withdrawn.
	pg_assert_same(array('status' => 'failed', 'outcome' => 'withdrawn'), ws_flow_approval_settlement(null, false));
}

// The boxes after it wait while it does, and follow its outcome.
function test_workspace_flow_approval_holds_the_next_boxes()
{
	$graph = ws_flow_approval_test_graph();
	$waiting = array(
		'start' => array('status' => 'done', 'outcome' => 'next'),
		'ask'   => array('status' => 'waiting', 'outcome' => ''),
	);

	pg_assert_same(array(), ws_flow_walk($graph, $waiting)['ready']);
	pg_assert_same('waiting', ws_flow_run_state($graph, $waiting));

	$approved = array('start' => $waiting['start'], 'ask' => array('status' => 'done', 'outcome' => 'approved'));
	$rejected = array('start' => $waiting['start'], 'ask' => array('status' => 'done', 'outcome' => 'rejected'));

	pg_assert_same(array('tell'), ws_flow_walk($graph, $approved)['ready']);
	pg_assert_same(array(), ws_flow_walk($graph, $rejected)['ready'], 'no arrow on rejected: the branch ends');
}

// Read from the source: the shared step gives the request's id; the box
// waits for it in one conditional write; the event and the hourly look
// settle it.
function test_workspace_flow_approval_waits_for_its_request()
{
	pg_assert_contains("'approval_id' => (int) \$created['approval_id']", ws_flow_approval_test_source('ws_scheduled_do'));
	pg_assert_contains("'closes_at'", ws_flow_approval_test_source('ws_scheduled_do'));

	$run = ws_flow_approval_test_source('ws_step_run');

	pg_assert_contains('ws_flow_approval_wait(', $run);
	pg_assert_true(strpos($run, 'ws_scheduled_do(') < strpos($run, 'ws_flow_approval_wait('), 'after the request is out');

	$wait = ws_flow_approval_test_source('ws_flow_approval_wait');

	pg_assert_true((bool) preg_match("/effect = '[^;]*status = 'waiting'[^;]*wait_event = '[^;]*wait_key = '[^;]*wake_at = '[^;]*AND status = 'running'/s", $wait), 'effect, waiting, event, key and next look in one conditional UPDATE');
	pg_assert_contains('WS_FLOW_RECHECK', $wait);
	pg_assert_contains('ws_events_awaited(true)', $wait);

	$recheck = ws_flow_approval_test_source('ws_flow_step_recheck');

	pg_assert_contains('ws_flow_approval_recheck(', $recheck);
	pg_assert_true(strpos($recheck, 'ws_flow_approval_recheck(') < strpos($recheck, 'ws_flow_effect_task_id('), 'before the task is looked for');

	$look = ws_flow_approval_test_source('ws_flow_approval_recheck');

	pg_assert_contains('ws_approval(', $look);
	pg_assert_contains('ws_flow_approval_settlement(', $look);
	pg_assert_contains("ws_flow_step_settle(\$step, 'waiting'", $look);
}

// Trying the box says what it would ask without asking.
function test_workspace_flow_approval_try()
{
	$try = ws_flow_approval_test_source('ws_flow_box_try');

	pg_assert_false(strpos($try, 'ws_approval_create(') !== false, 'never asks');
	pg_assert_false(strpos($try, 'ws_flow_approval_wait(') !== false, 'never waits');
}
