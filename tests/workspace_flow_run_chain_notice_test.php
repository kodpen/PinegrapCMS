<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one inbox line of a chain of flow runs
 * (includes/workspace/flow_run_chain_notice.php): which run is a chain's
 * root, which runs fold into its line, when the line is updated, written
 * anew or pushed, and what it says.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_chain_notice.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A function's source without its comments.
function ws_flow_run_chain_notice_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// A run as the table keeps it, started by another run's box when $parent.
function ws_flow_run_chain_notice_test_run($id, $parent = 0, $context = array())
{
	return array(
		'id'        => $id,
		'depth'     => $parent ? 1 : 0,
		'start_key' => $parent ? ws_flow_chain_key($parent, 'n2', 0) : 'manual:' . $id,
		'context'   => json_encode($context),
	);
}

// The run a chain start key names; any other key names none.
function test_workspace_flow_run_chain_notice_parent_from_start_key()
{
	pg_assert_same(41, ws_flow_chain_parent_run('flow:41-n2-0'));
	pg_assert_same(41, ws_flow_chain_parent_run('flow:41-node-with-dash-3'));
	pg_assert_same(0, ws_flow_chain_parent_run('manual:abc'));
	pg_assert_same(0, ws_flow_chain_parent_run('schedule:5'));
	pg_assert_same(0, ws_flow_chain_parent_run('event:12'));
	pg_assert_same(0, ws_flow_chain_parent_run(''));
}

// The root kept in the context answers without a look at the table.
function test_workspace_flow_run_chain_notice_root_from_context()
{
	$asked = array();
	$lookup = function ($id) use (&$asked) {
		$asked[] = $id;
		return null;
	};

	pg_assert_same(10, ws_flow_chain_root(ws_flow_run_chain_notice_test_run(17, 16, array('trigger' => 'chain', 'chain_root' => 10)), $lookup));
	pg_assert_same(array(), $asked);
}

// An older run without the root in its context walks its start keys up.
function test_workspace_flow_run_chain_notice_root_falls_back_to_start_key()
{
	$runs = array(
		10 => ws_flow_run_chain_notice_test_run(10),
		11 => ws_flow_run_chain_notice_test_run(11, 10, array('trigger' => 'chain')),
		12 => ws_flow_run_chain_notice_test_run(12, 11, array('trigger' => 'chain')),
	);
	$lookup = function ($id) use ($runs) {
		return $runs[$id] ?? null;
	};

	pg_assert_same(10, ws_flow_chain_root(ws_flow_run_chain_notice_test_run(13, 12, array('trigger' => 'chain')), $lookup));

	// A parent that kept the root answers for it.
	$runs[12] = ws_flow_run_chain_notice_test_run(12, 11, array('trigger' => 'chain', 'chain_root' => 10));
	$lookup = function ($id) use ($runs) {
		return $runs[$id] ?? null;
	};
	pg_assert_same(10, ws_flow_chain_root(ws_flow_run_chain_notice_test_run(13, 12, array('trigger' => 'chain')), $lookup));

	// A parent swept since: the furthest run still known is the root.
	pg_assert_same(11, ws_flow_chain_root(ws_flow_run_chain_notice_test_run(13, 12, array('trigger' => 'chain')), function ($id) {
		return ($id === 12) ? ws_flow_run_chain_notice_test_run(12, 11, array('trigger' => 'chain')) : null;
	}));
}

// Scheduled and event-started runs are roots of their own: never folded.
function test_workspace_flow_run_chain_notice_scheduled_and_event_runs_not_folded()
{
	$none = function () {
		return null;
	};

	foreach (array('schedule:5', 'event:9', 'manual:x') as $key) {
		$run = array('id' => 20, 'depth' => 0, 'start_key' => $key, 'context' => json_encode(array('trigger' => 'schedule')));
		pg_assert_same(20, ws_flow_chain_root($run, $none), $key);
		pg_assert_false(ws_flow_chain_folds(20, ws_flow_chain_root($run, $none), 3, 3), $key);
	}
}

// Only a run of a flow of the root's owner folds into the root's line.
function test_workspace_flow_run_chain_notice_other_owner_keeps_its_line()
{
	pg_assert_true(ws_flow_chain_folds(12, 10, 3, 3));
	pg_assert_false(ws_flow_chain_folds(12, 10, 4, 3), 'another owner');
	pg_assert_false(ws_flow_chain_folds(12, 10, 3, 0), 'a root whose flow is gone');
	pg_assert_false(ws_flow_chain_folds(10, 10, 3, 3), 'the root itself');
}

// A chain of eight runs, each ending while the line is unread: one line,
// pushed once, saying seven flows were started.
function test_workspace_flow_run_chain_notice_chain_of_eight_is_one_line()
{
	$line = null;
	$lines = 0;
	$pushes = 0;

	for ($i = 0; $i < 8; $i++) {
		$plan = ws_flow_chain_notice_plan($line, 'done');

		if ($plan['write'] === 'new') {
			$lines++;
			$line = array('failed' => false);
		}

		if ($plan['push']) {
			$pushes++;
		}

		pg_assert_same(($i === 0) ? 'root' : 'keep', $plan['point'], 'run ' . $i);
	}

	pg_assert_same(1, $lines);
	pg_assert_same(1, $pushes);
	pg_assert_same('Flow “Ping” and 7 flows it started: done.', ws_flow_chain_notice_text('Ping', 10, 7, null));
	pg_assert_same('Flow “Ping” and one flow it started: done.', ws_flow_chain_notice_text('Ping', 10, 1, null));
	pg_assert_same('Flow “Ping”, run 10, is done.', ws_flow_chain_notice_text('Ping', 10, 0, null));
}

// A failure in the middle turns the line, pushes once, and the line then
// opens the failed run; later runs keep it pointing there.
function test_workspace_flow_run_chain_notice_failure_turns_the_line()
{
	$plan = ws_flow_chain_notice_plan(array('failed' => false), 'failed');
	pg_assert_same(array('write' => 'update', 'push' => true, 'point' => 'this'), $plan);

	$plan = ws_flow_chain_notice_plan(array('failed' => true), 'done');
	pg_assert_same(array('write' => 'update', 'push' => false, 'point' => 'keep'), $plan);

	$plan = ws_flow_chain_notice_plan(array('failed' => true), 'failed');
	pg_assert_same(array('write' => 'update', 'push' => false, 'point' => 'keep'), $plan);

	// A chain whose first run to end failed opens with the failure.
	pg_assert_same(array('write' => 'new', 'push' => true, 'point' => 'this'), ws_flow_chain_notice_plan(null, 'failed'));

	$failure = array('run' => 14, 'flow' => 'Pong', 'box' => 'Send', 'text' => 'no channel');
	pg_assert_same('Flow “Ping” and 3 flows it started: run 14 of “Pong” failed at the box Send: no channel',
		ws_flow_chain_notice_text('Ping', 10, 3, $failure));
	pg_assert_same('Flow “Ping” and one flow it started: run 14 of “Pong” failed at the box Send: no channel',
		ws_flow_chain_notice_text('Ping', 10, 1, $failure));
	pg_assert_same('Flow “Ping”, run 10, failed at the box Send: no channel',
		ws_flow_chain_notice_text('Ping', 10, 0, array('run' => 10, 'flow' => 'Ping', 'box' => 'Send', 'text' => 'no channel')));

	// The failed run is what the line opens: its title and icon follow.
	$line = ws_inbox_flow_run_line(array('id' => 14, 'flow_id' => 5, 'status' => 'failed'), '/pg/');
	pg_assert_same('/pg/workspace_flows.php?flow=5&run=14', $line['url']);
	pg_assert_same('bi-exclamation-octagon', $line['icon']);
}

// A line already read is not touched: the next run writes a new one.
function test_workspace_flow_run_chain_notice_read_line_gets_a_new_one()
{
	pg_assert_same(array('write' => 'new', 'push' => true, 'point' => 'root'), ws_flow_chain_notice_plan(null, 'done'));
	pg_assert_contains('read_at = 0', ws_flow_run_chain_notice_test_source('ws_flow_chain_notice_line'));
	pg_assert_contains('read_at = 0', ws_flow_run_chain_notice_test_source('ws_inbox_send_again'));
	pg_assert_contains("created_at = '", ws_flow_run_chain_notice_test_source('ws_inbox_send_again'));
}

// The chained run keeps its root, and the end of a run goes through the
// chain's line.
function test_workspace_flow_run_chain_notice_wiring()
{
	$context = ws_flow_chain_context(array('id' => 2, 'name' => 'Pong', 'channel_id' => 0), array('id' => 'n1', 'kind' => 'manual'), array(), 'Ping', '', 10);
	pg_assert_same(10, $context['chain_root']);

	$context = ws_flow_chain_context(array('id' => 2, 'name' => 'Pong', 'channel_id' => 0), array('id' => 'n1', 'kind' => 'manual'), array('chain_root' => 99), 'Ping', '');
	pg_assert_false(isset($context['chain_root']), 'a root is never carried from the source context');

	pg_assert_contains('ws_flow_chain_root(', ws_flow_run_chain_notice_test_source('ws_flow_chain_start'));
	pg_assert_contains('ws_flow_run_tell_chain(', ws_flow_run_chain_notice_test_source('ws_flow_run_tell_owner'));
}
