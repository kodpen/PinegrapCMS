<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the webhook events of a flow's runs
 * (includes/workspace/flow_run_events.php): which state announces which
 * end, the run and the box as the events carry them, beside the API's run
 * answer, the box a result comes from, what a waiting box waits for, the
 * words left out of a run outside a public channel and the channel every
 * body names; and, read from the source, that each move is announced once,
 * by the request whose conditional write made it: an end, a cancel, a box
 * that starts to wait, and nothing on a look that keeps it waiting, a box
 * that waits again after stopping half way, the runs of a deleted flow, a
 * retry made ready or a
 * box tried again by hand before its run ends again.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/groups.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The source of a function, comments taken out.
function ws_flow_run_events_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// A start box, a task, a change and the end, one after another.
function ws_flow_run_events_test_graph()
{
	$nodes = array();

	foreach (array('start' => 'manual', 'ask' => 'task', 'write' => 'change', 'finish' => 'end') as $id => $kind) {
		$nodes[] = array('id' => $id, 'kind' => $kind, 'label' => ($id === 'ask') ? 'Ask the customer' : '', 'x' => 0, 'y' => 0, 'config' => array());
	}

	return array('nodes' => $nodes, 'edges' => array(
		array('from' => 'start', 'to' => 'ask', 'on' => 'next'),
		array('from' => 'ask', 'to' => 'write', 'on' => 'done'),
		array('from' => 'write', 'to' => 'finish', 'on' => 'done'),
	));
}

// A box of a run as ws_flow_run_steps keeps it.
function ws_flow_run_events_test_step($id, $node, $status, $finished_at, $extra = array())
{
	return $extra + array(
		'id'          => $id,
		'run_id'      => 9,
		'node_id'     => $node,
		'status'      => $status,
		'outcome'     => ($status === 'done') ? 'done' : '',
		'effect'      => '',
		'wait_key'    => '',
		'attempt'     => 1,
		'finished_at' => $finished_at,
		'result'      => ws_flow_result_pack('Box ' . $node . ' went.'),
	);
}

// A run row.
function ws_flow_run_events_test_run($status = 'done', $channel_id = 12)
{
	return array(
		'id'          => '9',
		'flow_id'     => '3',
		'version_id'  => '21',
		'status'      => $status,
		'access'      => 'edit',
		'started_by'  => '5',
		'actor_id'    => '2',
		'boxes_run'   => '4',
		'started_at'  => 1767225600,
		'updated_at'  => 1767229200,
		'finished_at' => ($status === 'done' || $status === 'failed') ? 1767229200 : 0,
		'context'     => json_encode(array('channel_id' => $channel_id, 'flow_name' => 'New customer')),
	);
}

// A run that ended done or failed announces its own event; a cancelled or
// open run announces no end.
function test_workspace_flow_run_events_end_names()
{
	pg_assert_same('workspace.flow.run.finished', ws_flow_run_end_event('done'));
	pg_assert_same('workspace.flow.run.failed', ws_flow_run_end_event('failed'));

	foreach (array('cancelled', 'running', 'waiting', '') as $state) {
		pg_assert_same('', ws_flow_run_end_event($state), $state . ' announces no end');
	}
}

// The four events are in the catalogue, fit the queue's column, and the
// workspace does not record them: no flow starts or wakes on another run.
function test_workspace_flow_run_events_catalogue()
{
	$events = ws_webhook_events();

	foreach (array('finished', 'failed', 'cancelled', 'waiting') as $what) {
		$event = 'workspace.flow.run.' . $what;

		pg_assert_true(isset($events[$event]), $event . ' is listed');
		pg_assert_true(strlen($event) <= 50, $event . ' fits api_webhook_queue.event');
		pg_assert_false(in_array($event, pg_event_workspace_events(), true), $event . ' is not recorded for the workspace');
	}

	pg_assert_contains('a cancelled run is announced as workspace.flow.run.cancelled', $events['workspace.flow.run.finished']);
	pg_assert_contains('cancelled_by', $events['workspace.flow.run.cancelled']);
	pg_assert_contains('public channel', $events['workspace.flow.run.failed']);
	pg_assert_contains('Once per wait', $events['workspace.flow.run.waiting']);
}

// The run as an event carries it: the fields of the API's run answer, in
// its order, without the boxes, the graph and what depends on the reader.
function test_workspace_flow_run_events_run_fields_follow_the_api()
{
	$run = ws_flow_run_events_test_run();
	$fields = ws_flow_run_event_run($run, 4);
	$schema = array_keys(ws_api_flow_run_schema());

	pg_assert_same(array_values(array_diff($schema, array('access', 'can_cancel', 'steps', 'graph'))), array_keys($fields));

	$api = ws_api_flow_run_present(array('id' => 5), $run, 4);

	foreach ($fields as $key => $value) {
		pg_assert_same($api[$key], $value, $key . ' as the API answers it');
	}

	pg_assert_same(12, $fields['channel_id']);
	pg_assert_same(null, ws_flow_run_event_run(ws_flow_run_events_test_run('done', 0), 4)['channel_id'], 'no channel is null');
	pg_assert_same(null, ws_flow_run_event_run(ws_flow_run_events_test_run('running'), 4)['finished_at']);
}

// A box's values as a reader who may not change the flow reads them, as
// the API gives them, cut at the API's length.
function test_workspace_flow_run_events_outputs_follow_the_api()
{
	pg_assert_same(WS_API_FLOW_RESULT_CHARS, WS_FLOW_EVENT_TEXT_CHARS);

	$cases = array(
		array('task', ws_flow_result_pack('ok', array('task_id' => 7))),
		array('webhook', ws_flow_result_pack('ok', array('status' => 200, 'body' => str_repeat('a', 1500), 'none' => null, 'list' => array(1)))),
		array('change', ws_flow_result_pack('ok', array('record_id' => 4))),
		array('start_flow', ws_flow_result_pack('ok', array('run_id' => 8))),
		array('delay', 'plain text'),
	);

	foreach ($cases as $case) {
		pg_assert_same(ws_api_flow_step_outputs($case[0], $case[1], false), ws_flow_run_event_outputs($case[0], $case[1]), $case[0]);
	}

	pg_assert_same(array(), ws_flow_run_event_outputs('change', ws_flow_result_pack('ok', array('record_id' => 4))), 'a change box keeps its record back');
}

// Words go out only for a run of a public channel.
function test_workspace_flow_run_events_words_only_in_public()
{
	pg_assert_true(ws_flow_run_event_words(array('id' => 12, 'kind' => 'public')));

	foreach (array('private', 'guest', 'dm', '') as $kind) {
		pg_assert_false(ws_flow_run_event_words(array('id' => 12, 'kind' => $kind)), $kind . ' keeps its words');
	}

	pg_assert_false(ws_flow_run_event_words(null), 'a run of no channel keeps its words');
}

// A box as an event carries it: with the run's words its name, values and
// the sentence a reader without edit reads; without them its ids alone.
function test_workspace_flow_run_events_box()
{
	$graph = ws_flow_run_events_test_graph();
	$task = ws_flow_run_events_test_step(30, 'ask', 'done', 100, array('effect' => 'task:7', 'result' => ws_flow_result_pack('Task #7 is done.', array('task_id' => 7))));

	$open = ws_flow_run_event_box($task, $graph, true);

	pg_assert_same(array('node_id', 'kind', 'name', 'status', 'outcome', 'task_id', 'attempt', 'finished_at', 'outputs', 'result'), array_keys($open));
	pg_assert_same('Ask the customer', $open['name']);
	pg_assert_same('task', $open['kind']);
	pg_assert_same(7, $open['task_id']);
	pg_assert_same(array('task_id' => 7), (array) $open['outputs']);
	pg_assert_same('Task #7 is done.', $open['result']);

	$closed = ws_flow_run_event_box($task, $graph, false);

	pg_assert_same(null, $closed['name']);
	pg_assert_same(null, $closed['outputs']);
	pg_assert_same(null, $closed['result']);
	pg_assert_same(7, $closed['task_id'], 'ids stay');
	pg_assert_same('done', $closed['status']);

	$change = ws_flow_run_event_box(ws_flow_run_events_test_step(31, 'write', 'done', 110, array('result' => ws_flow_result_pack('Product 4 price set to 10.', array('record_id' => 4)))), $graph, true);

	pg_assert_same(ws_step_result_shown('change', 'done', 'Product 4 price set to 10.', false), $change['result'], 'as a reader without edit reads it');
	pg_assert_same(array(), (array) $change['outputs']);
	pg_assert_same('write', ws_flow_run_event_box(ws_flow_run_events_test_step(31, 'write', 'done', 110), $graph, true)['node_id']);
}

// A failed run's result is the box that failed first; a done run's, the box
// that finished last on done, an end box passed over, the start box only
// when nothing else ran.
function test_workspace_flow_run_events_result_step()
{
	$graph = ws_flow_run_events_test_graph();
	$rows = array(
		ws_flow_run_events_test_step(1, 'start', 'done', 100),
		ws_flow_run_events_test_step(2, 'ask', 'done', 200),
		ws_flow_run_events_test_step(3, 'write', 'done', 300),
		ws_flow_run_events_test_step(4, 'finish', 'done', 400),
	);

	pg_assert_same(3, ws_flow_run_result_step('done', $rows, $graph)['id'], 'the end box is passed over');
	pg_assert_same(1, ws_flow_run_result_step('done', array($rows[0], $rows[3]), $graph)['id'], 'only the start box ran');
	pg_assert_same(null, ws_flow_run_result_step('done', array(), $graph));

	// Two that finished in one second: the one written later.
	$tie = array(ws_flow_run_events_test_step(5, 'write', 'done', 300), ws_flow_run_events_test_step(6, 'ask', 'done', 300));
	pg_assert_same(6, ws_flow_run_result_step('done', $tie, $graph)['id']);

	$failed = array(
		ws_flow_run_events_test_step(1, 'start', 'done', 100),
		ws_flow_run_events_test_step(8, 'write', 'failed', 300),
		ws_flow_run_events_test_step(7, 'ask', 'failed', 300),
		ws_flow_run_events_test_step(9, 'finish', 'skipped', 300),
	);

	pg_assert_same(7, ws_flow_run_result_step('failed', $failed, $graph)['id'], 'the first failure, the row written first in a tie');
	pg_assert_same(8, ws_flow_run_result_step('failed', array($failed[1], ws_flow_run_events_test_step(10, 'ask', 'failed', 500)), $graph)['id'], 'the earlier failure');
	pg_assert_same(null, ws_flow_run_result_step('failed', array($rows[0]), $graph), 'a run failed with no box, as a run without its start box');
}

// What a waiting box waits for, from the key it waits on.
function test_workspace_flow_run_events_waiting_for()
{
	pg_assert_same(array('for' => 'task', 'task_id' => 7, 'approval_id' => null, 'until' => null), ws_flow_run_waiting_for(array('wait_key' => 'task:7')));
	pg_assert_same(array('for' => 'approval', 'task_id' => null, 'approval_id' => 11, 'until' => null), ws_flow_run_waiting_for(array('wait_key' => ws_flow_approval_key(11))));
	pg_assert_same(array('for' => 'delay', 'task_id' => null, 'approval_id' => null, 'until' => '2026-01-01T00:00:00Z'), ws_flow_run_waiting_for(array('wait_key' => ws_flow_delay_key(1767225600))));
	pg_assert_same(array('for' => 'retry', 'task_id' => null, 'approval_id' => null, 'until' => '2026-01-01T00:00:00Z'), ws_flow_run_waiting_for(array('wait_key' => ws_flow_retry_key(1767225600))));
	pg_assert_same('', ws_flow_run_waiting_for(array('wait_key' => ''))['for']);
}

// The body: the run's id and channel at the top, where a subscription
// narrowed to a group of channels reads it, the flow's name only with the
// run's words.
function test_workspace_flow_run_events_body()
{
	$run = ws_flow_run_events_test_run();
	$fields = ws_flow_run_event_run($run, 4);
	$body = ws_flow_run_event_body($fields, ws_flow_run_context($run), true, array('result' => null));

	pg_assert_same(array('id', 'channel_id', 'flow_id', 'flow_name', 'run', 'result'), array_keys($body));
	pg_assert_same(9, $body['id']);
	pg_assert_same(12, $body['channel_id']);
	pg_assert_same('New customer', $body['flow_name']);
	pg_assert_same($fields, $body['run']);
	pg_assert_same(12, ws_webhook_event_channel_id($body), 'a narrowed subscription finds the channel');

	pg_assert_same(null, ws_flow_run_event_body($fields, ws_flow_run_context($run), false, array())['flow_name'], 'no name outside a public channel');

	$none = ws_flow_run_event_body(ws_flow_run_event_run(ws_flow_run_events_test_run('done', 0), 4), array(), false, array());
	pg_assert_same(null, $none['channel_id']);
	pg_assert_same(0, ws_webhook_event_channel_id($none), 'a run of no channel is heard by whole-site subscriptions only');
}

// Finished and failed: announced by the request whose UPDATE ended the run,
// once, beside its owner's notice; the run read again must stand in that
// state, so a reopened run read in another state announces nothing.
function test_workspace_flow_run_events_end_is_announced_once()
{
	$refresh = ws_flow_run_events_test_source('ws_flow_run_refresh');
	$end = strpos($refresh, "SET status = '\" . e(\$state) . \"', finished_at");
	$counted = strpos($refresh, 'mysqli_affected_rows(db::$con) === 1', (int) $end);
	$told = strpos($refresh, 'ws_flow_run_tell_owner($run, $state);');
	$announced = strpos($refresh, "ws_flow_run_announce_end((int) \$run['id'], \$state);");

	pg_assert_true(($end !== false) && ($counted > $end) && ($told > $counted) && ($announced > $told), 'after the end UPDATE took the row');
	pg_assert_same(1, substr_count($refresh, 'ws_flow_run_announce_end('), 'one place');
	pg_assert_contains("AND status IN ('running', 'waiting')\");\n\n    if ((int) mysqli_affected_rows(db::\$con) === 1) {\n        ws_flow_run_tell_owner(\$run, \$state);\n        ws_flow_run_announce_end(", $refresh, 'only inside the counted branch');

	// The open branch returns before the end.
	$open = strpos($refresh, 'if (ws_flow_run_open($state)) {');
	pg_assert_true(($open !== false) && ($open < $end), 'a run still open returns first');

	$heal = ws_flow_run_events_test_source('ws_flow_runs_heal');
	pg_assert_contains("AND status = 'running'\");\n\n                if ((int) mysqli_affected_rows(db::\$con) === 1) {\n                    ws_flow_run_announce_end((int) \$run['id'], 'failed');", $heal, 'a run without its start box fails once');

	$announce = ws_flow_run_events_test_source('ws_flow_run_announce_end');
	pg_assert_contains("(string) \$read['run']['status'] !== (string) \$state", $announce, 'the run read again stands in the state announced');
	pg_assert_contains("pg_announce('workspace.flow.run.failed', ws_flow_run_event_body(", $announce);
	pg_assert_contains("pg_announce('workspace.flow.run.finished', ws_flow_run_event_body(", $announce);
}

// Cancelled: announced by the request whose UPDATE cancelled the run, once,
// after its open boxes were skipped, with who cancelled it; a request that
// lost the row returns first, and the run read again must stand cancelled.
function test_workspace_flow_run_events_cancel_is_announced_once()
{
	$cancel = ws_flow_run_events_test_source('ws_flow_run_cancel');
	$write = strpos($cancel, "SET status = 'cancelled'");
	$counted = strpos($cancel, 'if ((int) mysqli_affected_rows(db::$con) !== 1) {', (int) $write);
	$skipped = strpos($cancel, "SET status = 'skipped'", (int) $counted);
	$announced = strpos($cancel, "ws_flow_run_announce_cancelled((int) \$run['id'], (int) \$viewer['id']);");

	pg_assert_true(($write !== false) && ($counted > $write) && ($skipped > $counted) && ($announced > $skipped), 'after the counted UPDATE and the skipped boxes');
	pg_assert_same(1, substr_count($cancel, 'ws_flow_run_announce_cancelled('), 'one place');
	pg_assert_contains("return ws_flow_refusal(lang('This run has ended already.'), 409);", substr($cancel, (int) $counted, (int) ($skipped - $counted)), 'a lost row returns before the announce');

	$announce = ws_flow_run_events_test_source('ws_flow_run_announce_cancelled');
	pg_assert_contains("(string) \$read['run']['status'] !== 'cancelled'", $announce);
	pg_assert_contains("pg_announce('workspace.flow.run.cancelled', ws_flow_run_event_body(", $announce);
	pg_assert_contains("'cancelled_by' => (int) \$by", $announce);

	// The body is the other run events' body, cancelled_by last.
	$run = ws_flow_run_events_test_run('cancelled');
	$body = ws_flow_run_event_body(ws_flow_run_event_run($run, 4), ws_flow_run_context($run), true, array('cancelled_by' => 3));
	pg_assert_same(array('id', 'channel_id', 'flow_id', 'flow_name', 'run', 'cancelled_by'), array_keys($body));
}

// Waiting: announced by the request whose UPDATE put the box from running to
// waiting, once, after the run was walked, with the key that UPDATE wrote.
function test_workspace_flow_run_events_waiting_is_announced_once()
{
	$sites = array(
		'ws_flow_step_task'     => "ws_flow_run_announce_waiting((int) \$step['id'], 'task:' . \$task_id);",
		'ws_flow_delay_run'     => "ws_flow_run_announce_waiting((int) \$step['id'], \$key);",
		'ws_flow_retry_wait'    => "ws_flow_run_announce_waiting((int) \$step['id'], \$key);",
		'ws_flow_approval_wait' => "ws_flow_run_announce_waiting((int) \$step['id'], \$key);",
	);

	foreach ($sites as $function => $call) {
		$source = ws_flow_run_events_test_source($function);
		$write = strpos($source, "status = 'waiting'");
		$guard = strpos($source, "AND status = 'running'\");", (int) $write);
		$counted = strpos($source, 'mysqli_affected_rows(db::$con)', (int) $guard);
		$walked = strpos($source, 'ws_flow_run_refresh(', (int) $counted);
		$announced = strpos($source, $call);

		pg_assert_same(1, substr_count($source, 'ws_flow_run_announce_waiting('), $function . ': one place');
		pg_assert_true(($write !== false) && ($guard > $write) && ($counted > $guard) && ($walked > $counted) && ($announced > $walked), $function . ': after its own counted write and the walk');
	}

	// The keys passed are the keys written.
	pg_assert_contains("wait_key = 'task:\" . \$task_id . \"'", ws_flow_run_events_test_source('ws_flow_step_task'));

	foreach (array('ws_flow_delay_run', 'ws_flow_retry_wait', 'ws_flow_approval_wait') as $function) {
		pg_assert_contains("wait_key = '\" . e(\$key) . \"'", ws_flow_run_events_test_source($function), $function . ' waits on $key');
	}

	// A write that lost its row returns before the announce.
	pg_assert_contains("if ((int) mysqli_affected_rows(db::\$con) !== 1) {\n        return true;\n    }", ws_flow_run_events_test_source('ws_flow_retry_wait'));

	// Woken, let go or a run ended at that moment: nothing goes out.
	$announce = ws_flow_run_events_test_source('ws_flow_run_announce_waiting');
	pg_assert_contains("((string) \$step['status'] !== 'waiting')", $announce);
	pg_assert_contains("((string) \$step['wait_key'] !== (string) \$wait_key)", $announce);
	pg_assert_contains("!ws_flow_run_open(\$read['run']['status'])", $announce);
	pg_assert_contains("pg_announce('workspace.flow.run.waiting', ws_flow_run_event_body(", $announce);
}

// No other move announces: a look that keeps a box waiting, a box that
// stopped half way and waits again for what it began (or is made ready for
// its retry), a retry whose moment came, a wake, a cancel with a deleted
// flow, a box tried again or skipped by hand (its run announces
// when the walk ends it again), a start.
function test_workspace_flow_run_events_other_moves_announce_nothing()
{
	$quiet = array(
		'ws_flow_steps_stale', 'ws_flow_steps_recheck', 'ws_flow_step_recheck', 'ws_flow_delay_recheck',
		'ws_flow_retry_recheck', 'ws_flow_approval_recheck', 'ws_flow_wake', 'ws_flow_step_settle', 'ws_flow_run_touch',
		'ws_flow_runs_cancel_ids', 'ws_flow_runs_cancel_for_flow', 'ws_flow_step_mend',
		'ws_flow_run_start', 'ws_flow_step_claim', 'ws_flow_advance',
	);

	foreach ($quiet as $function) {
		$source = ws_flow_run_events_test_source($function);

		pg_assert_false(strpos($source, 'ws_flow_run_announce_') !== false, $function . ' announces nothing itself');
		pg_assert_false(strpos($source, "'workspace.flow.run.") !== false, $function . ' names no run event');
	}

	// Tried again by hand: the run is reopened and walked, and announces
	// through the walk when it ends again.
	$mend = ws_flow_run_events_test_source('ws_flow_step_mend');
	pg_assert_contains("SET status = 'running', finished_at = 0", $mend);
	pg_assert_contains('ws_flow_run_refresh((int) $run[\'id\']);', $mend);

	// Only the events file announces the run events, and only these
	// functions call it.
	$callers = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		$source = (string) file_get_contents($file);

		if (basename($file) !== 'flow_run_events.php') {
			pg_assert_same(0, preg_match("~pg_announce\\(\\s*'workspace\\.flow\\.run\\.~", $source), basename($file) . ' does not announce a run event');
		}

		if (preg_match_all('~(ws_flow_run_announce_(?:end|waiting|cancelled))\(~', $source, $found)) {
			$callers[basename($file)] = count($found[1]);
		}
	}

	pg_assert_same(array('flow_run_events.php' => 3, 'flow_runs.php' => 7), $callers, 'three definitions, seven calls');
}
