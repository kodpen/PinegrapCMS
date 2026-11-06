<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a failed run as a flow's start (workspace.flow.run.failed):
 * the event is one of the workspace's own (kept by ws_event()), listed in
 * the start box's catalogue with its outputs, names the run's channel, is
 * kept with the flow, the box and its sentence as a reader without edit
 * reads them, at the failed run's chain depth, and never starts the flow
 * that failed.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_events.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');

// The source of a function, comments taken out.
function ws_flow_run_failed_start_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// One of the workspace's own events, never recorded from pg_announce(), in
// the catalogue's literal lines, about the run's channel.
function test_workspace_flow_run_failed_start_is_an_own_event()
{
	$event = 'workspace.flow.run.failed';

	pg_assert_true(ws_events_is_own($event));
	pg_assert_false(in_array($event, pg_event_workspace_events(), true), 'kept by ws_event(), not pg_event_record()');
	pg_assert_true(ws_events_checks_creator($event), 'checked against what the creator reads');
	pg_assert_contains("\$catalog['workspace.flow.run.failed'] = array(", file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php'));
	pg_assert_same(array('channel', 12), ws_events_record_of($event, array('id' => 9, 'flow_id' => 3, 'channel_id' => 12)));
	pg_assert_same(array('', 0), ws_events_record_of($event, array('id' => 9, 'flow_id' => 3, 'channel_id' => 0)), 'a run of no channel names no record');

	$keys = array_column(ws_flow_event_outputs()[$event] ?? array(), 'key');

	pg_assert_same(array('flow_id', 'flow_name', 'run_id', 'box', 'sentence', 'channel_id'), $keys);
}

// What ws_event() keeps: the run, its flow, its channel, the box that
// failed first and its sentence as a reader without edit reads it.
function test_workspace_flow_run_failed_start_payload()
{
	$run = array('id' => 9, 'flow_id' => 3, 'channel_id' => 12);
	$box = array('node_id' => 'write', 'kind' => 'change', 'name' => 'Set the price', 'result' => 'It failed.');

	pg_assert_same(array(
		'id'         => 9,
		'run_id'     => 9,
		'flow_id'    => 3,
		'flow_name'  => 'New customer',
		'channel_id' => 12,
		'node_id'    => 'write',
		'box'        => 'Set the price',
		'sentence'   => 'It failed.',
	), ws_flow_run_failed_payload($run, array('flow_name' => 'New customer'), $box));

	$none = ws_flow_run_failed_payload(array('id' => 9, 'flow_id' => 3, 'channel_id' => null), array(), null);

	pg_assert_same(0, $none['channel_id']);
	pg_assert_same('', $none['flow_name']);
	pg_assert_same('', $none['node_id']);
	pg_assert_same('', $none['box']);
	pg_assert_same('', $none['sentence']);
}

// A flow never starts on its own failure; another flow's does.
function test_workspace_flow_run_failed_start_never_its_own()
{
	$payload = array('id' => 9, 'flow_id' => 3);

	pg_assert_true(ws_events_own_failure('workspace.flow.run.failed', $payload, 3), 'the flow that failed');
	pg_assert_false(ws_events_own_failure('workspace.flow.run.failed', $payload, 4), 'another flow');
	pg_assert_false(ws_events_own_failure('workspace.flow.run.failed', $payload, 0), 'a scheduled action of no flow');
	pg_assert_false(ws_events_own_failure('workspace.task.completed', $payload, 3), 'another event');

	$start = ws_flow_run_failed_start_test_source('ws_events_start');
	pg_assert_contains("ws_events_own_failure(\$event, \$payload, \$item['flow_id'])", $start);
}

// Kept beside the webhook, once, at the depth of the run that failed, so a
// ring of flows starting on one another's failures stops at the chain's
// limit; the creator check asks for the run, as the run's own reader.
function test_workspace_flow_run_failed_start_is_kept_once()
{
	$announce = ws_flow_run_failed_start_test_source('ws_flow_run_announce_end');
	$hook = strpos($announce, "pg_announce('workspace.flow.run.failed'");
	$kept = strpos($announce, 'ws_flow_run_keep_failed(');

	pg_assert_true(($hook !== false) && ($kept > $hook), 'beside the webhook, after it');
	pg_assert_same(1, substr_count($announce, 'ws_flow_run_keep_failed('));

	$keep = ws_flow_run_failed_start_test_source('ws_flow_run_keep_failed');
	pg_assert_contains("ws_event_depth((int) (\$run['depth'] ?? 0));", $keep);
	pg_assert_contains("ws_event('workspace.flow.run.failed', ", $keep);
	pg_assert_contains('ws_event_depth($outer - 1);', $keep);

	$sees = ws_flow_run_failed_start_test_source('ws_events_creator_sees');
	pg_assert_contains('ws_flow_run_access($viewer, ', $sees);
}
