<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the chain depth a flow run's events carry: the site's events
 * (pg_event_record()) stamp the depth a running step stands at, as the
 * workspace's own events (ws_event()) do, and a flow's step box says its
 * run's depth while it does its work and gives the outer depth back after.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');

// A function's source without its comments.
function ws_event_depth_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// Outside a run the payload is left as it is; inside one it carries the
// depth the run's events start at.
function test_pg_event_payload_depth_stamps_the_run_depth()
{
	$before = ws_event_depth();

	ws_event_depth(-1);
	pg_assert_same(array('order_id' => 5), pg_event_payload_depth(array('order_id' => 5)), 'a payload outside a run changed');

	ws_event_depth(2);
	pg_assert_same(array('order_id' => 5, '_depth' => 3), pg_event_payload_depth(array('order_id' => 5)), 'a run\'s event lacks its depth');

	ws_event_depth(0);
	pg_assert_same(1, pg_event_payload_depth(array())['_depth'] ?? null, 'a run at the head of a chain does not stamp depth 1');

	ws_event_depth($before - 1);
}

// The INSERT writes the stamped payload.
function test_pg_event_record_writes_the_stamped_payload()
{
	$source = ws_event_depth_test_source('pg_event_record');

	pg_assert_true(strpos($source, 'json_encode(pg_event_payload_depth((array) $payload)') !== false, 'pg_event_record() does not stamp the depth');
}

// A step box says its run's depth right before it does its work and gives
// back the depth that stood before, so a step run nested keeps the outer one.
function test_ws_step_run_says_the_run_depth_around_its_work()
{
	$source = ws_event_depth_test_source('ws_step_run');

	$read = strpos($source, '$outer_depth = ws_event_depth();');
	$set = strpos($source, "ws_event_depth((int) (\$run['depth'] ?? 0));");
	$do = strpos($source, '$done = ws_scheduled_do(');
	$restore = strpos($source, 'ws_event_depth($outer_depth - 1);');

	pg_assert_true($read !== false, 'the outer depth is not read');
	pg_assert_true(($set !== false) && ($read < $set), 'the run depth is not set after reading the outer one');
	pg_assert_true(($do !== false) && ($set < $do), 'the run depth is not set before the step does its work');
	pg_assert_true(($restore !== false) && ($do < $restore), 'the outer depth is not given back after the work');
	pg_assert_true(strpos($source, 'ws_event_depth(-1)') === false, 'the step drops the outer depth');
}

// Giving back depth d - 1 leaves d standing: the round trip ws_step_run()
// relies on.
function test_ws_event_depth_restore_round_trip()
{
	$before = ws_event_depth();

	foreach (array(-1, 0, 3) as $outer) {
		ws_event_depth($outer);
		$kept = ws_event_depth();

		ws_event_depth(6);
		ws_event_depth($kept - 1);

		pg_assert_same($kept, ws_event_depth(), 'outer depth ' . $kept . ' not given back');
	}

	ws_event_depth($before - 1);
}
