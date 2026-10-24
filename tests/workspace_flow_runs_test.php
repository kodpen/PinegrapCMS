<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the runner of the workspace's flows
 * (includes/workspace/flow_runs.php): the walk that says which boxes become
 * ready and which are skipped as the boxes before them settle (a straight
 * line, a fork, a join, an untaken branch, a failed box and the boxes
 * skipped behind it, a task outcome no arrow leaves on), how a run ends, how
 * a task's state settles its box, which event wakes which box, what a box
 * that stopped half way does, the limits on starting a run and the start key
 * a token becomes, the result kept for a box, the task text that links the
 * task to its run and its box, the channel a run keeps, who may read and
 * cancel a run; and, read from the source, the conditional writes that keep
 * two requests from running one box twice, the walk again of a run a request
 * left half way, a deleted flow's runs, the owner's notice of each run, and
 * the drivers that call the runner.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: the templates' assign rules and dates, the
// store's graph, the runner, and the drivers and the inbox read from their
// source.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

// A box of the graph.
function ws_flow_runs_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// A graph of boxes and arrows ([from, to, on]), kept in its shape.
function ws_flow_runs_test_graph($nodes, $edges)
{
	$out = array('nodes' => array(), 'edges' => array());

	foreach ($nodes as $id => $kind) {
		$out['nodes'][] = ws_flow_runs_test_node($id, $kind, ($kind === 'task') ? array('title' => ucfirst($id)) : array());
	}

	foreach ($edges as $edge) {
		$out['edges'][] = array('from' => $edge[0], 'to' => $edge[1], 'on' => $edge[2]);
	}

	return ws_flow_graph_normalize($out);
}

// Steps as the runner reads them: node id => status and outcome.
function ws_flow_runs_test_steps($states)
{
	$out = array();

	foreach ($states as $id => $state) {
		$parts = explode(':', $state);
		$out[$id] = array('status' => $parts[0], 'outcome' => $parts[1] ?? '');
	}

	return $out;
}

// The source of a function, comments taken out.
function ws_flow_runs_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The process of a new customer: started by hand, three tasks one after
// another, then the end.
function ws_flow_runs_test_line()
{
	return ws_flow_runs_test_graph(
		array('start' => 'manual', 'contract' => 'task', 'payment' => 'task', 'finish' => 'end'),
		array(array('start', 'contract', 'next'), array('contract', 'payment', 'done'), array('payment', 'finish', 'done'))
	);
}

// A straight line: each box becomes ready when the one before it is done,
// and not while it is still at work.
function test_workspace_flow_runs_walk_straight_line()
{
	$graph = ws_flow_runs_test_line();

	pg_assert_same(array('ready' => array('contract'), 'skipped' => array()), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next'))), 'the first task after the start');
	pg_assert_same(array('ready' => array(), 'skipped' => array()), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'waiting'))), 'nothing while the task is open');
	pg_assert_same(array('ready' => array('payment'), 'skipped' => array()), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'done:done'))), 'the next one once it is done');
	pg_assert_same(array('ready' => array('finish'), 'skipped' => array()), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'done:done', 'payment' => 'done:done'))), 'then the end');
	pg_assert_same(array('ready' => array(), 'skipped' => array()), ws_flow_walk($graph, array()), 'a start box is never made ready by the walk');
}

// A fork: two arrows on the same outcome make both boxes ready at once.
function test_workspace_flow_runs_walk_fork()
{
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'b' => 'task'),
		array(array('start', 'a', 'next'), array('start', 'b', 'next'))
	);

	pg_assert_same(array('ready' => array('a', 'b'), 'skipped' => array()), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next'))));
}

// A join waits for every arrow into it and fires once.
function test_workspace_flow_runs_walk_join()
{
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'b' => 'task', 'c' => 'task'),
		array(array('start', 'a', 'next'), array('start', 'b', 'next'), array('a', 'c', 'done'), array('b', 'c', 'done'))
	);

	pg_assert_same(array(), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:done', 'b' => 'running')))['ready'], 'one arrow in is not enough');
	pg_assert_same(array('c'), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:done', 'b' => 'done:done')))['ready'], 'both are');
	pg_assert_same(array(), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:done', 'b' => 'done:done', 'c' => 'ready')))['ready'], 'a box with a step is never written again');
}

// The branch an outcome does not take is skipped, box after box, so a join
// behind it fires once the taken branch arrives.
function test_workspace_flow_runs_walk_untaken_branch_skipped()
{
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'check' => 'task', 'ship' => 'task', 'refund' => 'task', 'apologise' => 'task', 'close' => 'task'),
		array(
			array('start', 'check', 'next'),
			array('check', 'ship', 'done'),
			array('check', 'refund', 'cancelled'),
			array('refund', 'apologise', 'done'),
			array('ship', 'close', 'done'),
			array('apologise', 'close', 'done'),
		)
	);

	$walk = ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'check' => 'done:done')));

	pg_assert_same(array('ship'), $walk['ready'], 'the taken branch');
	pg_assert_same(array('refund', 'apologise'), $walk['skipped'], 'the other branch, to its end');

	$walk = ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'check' => 'done:done', 'ship' => 'done:done', 'refund' => 'skipped', 'apologise' => 'skipped')));

	pg_assert_same(array('ready' => array('close'), 'skipped' => array()), $walk, 'the join fires on the branch taken');

	$walk = ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'check' => 'done:cancelled')));

	pg_assert_same(array('refund'), $walk['ready'], 'cancelled takes the other arrow');
	pg_assert_same(array('ship'), $walk['skipped']);
}

// A failed box takes no arrow, and what follows it is skipped; a join with
// a failed box before it is skipped too, even when its other arrow is taken.
function test_workspace_flow_runs_walk_failed_box_stops_what_follows()
{
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'b' => 'task', 'c' => 'task', 'finish' => 'end'),
		array(array('start', 'a', 'next'), array('start', 'b', 'next'), array('a', 'c', 'done'), array('b', 'c', 'done'), array('c', 'finish', 'done'))
	);

	$walk = ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'failed', 'b' => 'done:done')));

	pg_assert_same(array(), $walk['ready']);
	pg_assert_same(array('c', 'finish'), $walk['skipped']);
}

// A failure is carried through the boxes skipped behind it: a join two
// boxes behind a failed box is skipped too, even when its other arrow is
// taken, whether the box between them is skipped in the same walk or was
// stored skipped by an earlier one.
function test_workspace_flow_runs_walk_failure_carries_through_skipped_boxes()
{
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'x' => 'task', 'y' => 'task', 'w' => 'task', 'z' => 'task'),
		array(array('start', 'x', 'next'), array('x', 'y', 'done'), array('y', 'z', 'done'), array('start', 'w', 'next'), array('w', 'z', 'done'))
	);

	pg_assert_same(array(), ws_flow_graph_check($graph), 'a graph that may be published');

	$walk = ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'x' => 'failed', 'w' => 'waiting')));

	pg_assert_same(array('ready' => array(), 'skipped' => array('y')), $walk, 'the box behind the failure first');

	$walk = ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'x' => 'failed', 'y' => 'skipped', 'w' => 'done:done')));

	pg_assert_same(array('ready' => array(), 'skipped' => array('z')), $walk, 'then the join, its other arrow taken');

	$walk = ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'x' => 'failed', 'w' => 'done:done')));

	pg_assert_same(array(), $walk['ready'], 'in one walk too');
	pg_assert_same(array('y', 'z'), $walk['skipped']);

	// Two boxes in a row behind the failure, the join after them.
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'a2' => 'task', 'b' => 'task', 'c' => 'task', 'finish' => 'end'),
		array(array('start', 'a', 'next'), array('start', 'b', 'next'), array('a', 'a2', 'done'), array('a2', 'c', 'done'), array('b', 'c', 'done'), array('c', 'finish', 'done'))
	);

	$steps = ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'failed', 'a2' => 'skipped', 'b' => 'done:done'));

	pg_assert_same(array('ready' => array(), 'skipped' => array('c', 'finish')), ws_flow_walk($graph, $steps));

	$steps += ws_flow_runs_test_steps(array('c' => 'skipped', 'finish' => 'skipped'));

	pg_assert_same('failed', ws_flow_run_state($graph, $steps), 'the run fails');

	// A branch not taken is still no failure: the join behind it fires.
	$steps = ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:cancelled', 'a2' => 'skipped', 'b' => 'done:done'));

	pg_assert_same(array('ready' => array('c'), 'skipped' => array()), ws_flow_walk($graph, $steps));
}

// A task done whose box has an arrow on cancelled only: that arrow is not
// taken, and the branch ends there without a failure.
function test_workspace_flow_runs_walk_task_outcome_without_an_arrow()
{
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'chase' => 'task'),
		array(array('start', 'a', 'next'), array('a', 'chase', 'cancelled'))
	);

	$steps = ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:done'));

	pg_assert_same(array('ready' => array(), 'skipped' => array('chase')), ws_flow_walk($graph, $steps));
	pg_assert_false(ws_flow_outcome_routed($graph, 'a', 'done'), 'no arrow on done');
	pg_assert_true(ws_flow_outcome_routed($graph, 'a', 'cancelled'), 'one on cancelled');
	pg_assert_false(ws_flow_outcome_routed($graph, 'chase', 'done'), 'a box with no arrow out');

	$steps['chase'] = array('status' => 'skipped', 'outcome' => '');

	pg_assert_same('done', ws_flow_run_state($graph, $steps), 'the run is done: nothing failed');
}

// How a run stands: at work while a box is ready or running, waiting while
// boxes only wait, done when nothing is open and nothing failed, failed
// when a box failed.
function test_workspace_flow_runs_run_state()
{
	$graph = ws_flow_runs_test_line();

	pg_assert_same('running', ws_flow_run_state($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'ready'))));
	pg_assert_same('running', ws_flow_run_state($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'running'))));
	pg_assert_same('waiting', ws_flow_run_state($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'waiting'))));
	pg_assert_same('running', ws_flow_run_state($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'done:done'))), 'a box still to be written');
	pg_assert_same('done', ws_flow_run_state($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'done:done', 'payment' => 'done:done', 'finish' => 'done'))));
	pg_assert_same('failed', ws_flow_run_state($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'contract' => 'failed:cancelled', 'payment' => 'skipped', 'finish' => 'skipped'))));

	// A failure in one branch while another still waits: the run waits for it.
	$fork = ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'b' => 'task'),
		array(array('start', 'a', 'next'), array('start', 'b', 'next'))
	);

	pg_assert_same('waiting', ws_flow_run_state($fork, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'failed', 'b' => 'waiting'))));
	pg_assert_same('failed', ws_flow_run_state($fork, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'failed', 'b' => 'done:done'))));
}

// A task's state settles its box: done is done; cancelled, or a task that is
// gone, follows the cancelled arrow where the box has one and fails the box
// where it has none; an open task keeps the box waiting.
function test_workspace_flow_runs_task_settlement()
{
	pg_assert_same(array('status' => 'done', 'outcome' => 'done', 'reason' => 'done'), ws_flow_task_settlement('done', false));
	pg_assert_same(array('status' => 'done', 'outcome' => 'cancelled', 'reason' => 'cancelled'), ws_flow_task_settlement('cancelled', true));
	pg_assert_same(array('status' => 'failed', 'outcome' => 'cancelled', 'reason' => 'cancelled'), ws_flow_task_settlement('cancelled', false));
	pg_assert_same(array('status' => 'done', 'outcome' => 'cancelled', 'reason' => 'gone'), ws_flow_task_settlement(null, true));
	pg_assert_same(array('status' => 'failed', 'outcome' => 'cancelled', 'reason' => 'gone'), ws_flow_task_settlement(null, false));

	foreach (array('todo', 'doing', 'waiting') as $open) {
		pg_assert_same('', ws_flow_task_settlement($open, true)['status'], $open . ' keeps the box waiting');
	}
}

// The event a task box waits for is told apart by its task.
function test_workspace_flow_runs_wake_matching()
{
	pg_assert_same('task:12', ws_flow_wake_key('workspace.task.status_changed', array('id' => 12, 'status' => 'done')));
	pg_assert_same('task:12', ws_flow_wake_key('workspace.task.status_changed', array('id' => '12')));
	pg_assert_same('', ws_flow_wake_key('workspace.task.status_changed', array()), 'no task');
	pg_assert_same('', ws_flow_wake_key('workspace.task.completed', array('id' => 12)), 'a box waits for the status change only');
	pg_assert_same('', ws_flow_wake_key('order.created', array('id' => 12)));

	pg_assert_same(array('event' => 'workspace.task.status_changed', 'key' => 'task:7'), ws_flow_effect_wait('task:7'));
	pg_assert_same(null, ws_flow_effect_wait(''));
	pg_assert_same(null, ws_flow_effect_wait('task:0'));
	pg_assert_same(null, ws_flow_effect_wait('mail:3'), 'an effect nothing waits for');
	pg_assert_same(7, ws_flow_effect_task_id('task:7'));
	pg_assert_same(0, ws_flow_effect_task_id('approval:7'));
	pg_assert_same(0, ws_flow_effect_task_id(''));

	// The event a box waits for is one the workspace keeps (ws_event()).
	pg_assert_true(ws_events_is_own(ws_flow_effect_wait('task:7')['event']));
}

// A box that stopped half way: one whose effect is written waits for it
// again; one without is tried once more, then fails.
function test_workspace_flow_runs_stale_box()
{
	pg_assert_same('resume', ws_flow_stale_action(array('effect' => 'task:4', 'attempt' => 1)));
	pg_assert_same('resume', ws_flow_stale_action(array('effect' => 'task:4', 'attempt' => 5)));
	pg_assert_same('retry', ws_flow_stale_action(array('effect' => '', 'attempt' => 1)));
	pg_assert_same('fail', ws_flow_stale_action(array('effect' => '', 'attempt' => 2)));
	pg_assert_same('fail', ws_flow_stale_action(array('effect' => 'mail:4', 'attempt' => 2)), 'an effect nothing waits for is no reason to wait');
}

// A flow takes no more starts while it has too many runs open, or was
// started too often in the last hour.
function test_workspace_flow_runs_start_limits()
{
	pg_assert_same('', ws_flow_start_refusal(0, 0));
	pg_assert_same('', ws_flow_start_refusal(WS_FLOW_MAX_OPEN_RUNS - 1, WS_FLOW_STARTS_HOURLY - 1));
	pg_assert_same('open_runs', ws_flow_start_refusal(WS_FLOW_MAX_OPEN_RUNS, 0));
	pg_assert_same('hourly', ws_flow_start_refusal(0, WS_FLOW_STARTS_HOURLY));

	pg_assert_same(500, WS_FLOW_MAX_OPEN_RUNS);
	pg_assert_same(60, WS_FLOW_STARTS_HOURLY);
	pg_assert_same(200, WS_FLOW_MAX_BOX_RUNS);
	pg_assert_same(16384, WS_FLOW_RESULT_BYTES);
	pg_assert_same(30 * 86400, WS_FLOW_RUNS_KEEP);
	pg_assert_true(WS_FLOW_BUDGET_TICK < WS_FLOW_BUDGET_JOB, 'a job may run longer than a screen');
	pg_assert_same(3, WS_FLOW_BOXES_PER_RUN);

	// The start key fits its column with the longest token a client sends.
	pg_assert_true(ws_flow_start_token_valid('d3b07384-d9a0-4c9b-8f1e-1a2b3c4d5e6f'));
	pg_assert_true(ws_flow_start_token_valid(str_repeat('a', WS_FLOW_TOKEN_MAX)));
	pg_assert_false(ws_flow_start_token_valid(str_repeat('a', WS_FLOW_TOKEN_MAX + 1)), 'too long');
	pg_assert_false(ws_flow_start_token_valid(''), 'empty');
	pg_assert_false(ws_flow_start_token_valid('two words'), 'a space');
	pg_assert_false(ws_flow_start_token_valid(array('x')), 'not a string');
}

// The start key a token becomes: the same token always the same key, tokens
// that differ only in letter case two keys, although the column compares
// without regard to case; always within its VARCHAR(64).
function test_workspace_flow_runs_start_key()
{
	pg_assert_same(ws_flow_start_key('aB3x'), ws_flow_start_key('aB3x'));
	pg_assert_true(ws_flow_start_key('aB3x') !== ws_flow_start_key('Ab3X'), 'letter case counts');
	pg_assert_true(strtolower(ws_flow_start_key('aB3x')) !== strtolower(ws_flow_start_key('Ab3X')), 'also where the column ignores it');
	pg_assert_same(0, strpos(ws_flow_start_key('aB3x'), 'manual:'));
	pg_assert_true(strlen(ws_flow_start_key(str_repeat('Z', WS_FLOW_TOKEN_MAX))) <= 64, 'fits start_key');
	pg_assert_true(ws_flow_start_key('a') !== ws_flow_start_key('b'));
}

// What a box keeps of how it went: its text and, later, the values the
// boxes after it read; never more than WS_FLOW_RESULT_BYTES, always JSON.
function test_workspace_flow_runs_result()
{
	$packed = ws_flow_result_pack('task T-4 opened', array('task_id' => 4));

	pg_assert_same(array('text' => 'task T-4 opened', 'data' => array('task_id' => 4)), json_decode($packed, true));
	pg_assert_same('task T-4 opened', ws_flow_result_text($packed));
	pg_assert_same(array('text' => 'done'), json_decode(ws_flow_result_pack('done'), true), 'no data, no key');
	pg_assert_same('plain words', ws_flow_result_text('plain words'), 'a text kept before');
	pg_assert_same('', ws_flow_result_text(null));
	pg_assert_same('', ws_flow_result_text(''));

	$long = ws_flow_result_pack(str_repeat('ş', WS_FLOW_RESULT_BYTES), array('big' => str_repeat('x', WS_FLOW_RESULT_BYTES)));

	pg_assert_true(strlen($long) <= WS_FLOW_RESULT_BYTES, 'cut to the limit');
	pg_assert_true(is_array(json_decode($long, true)), 'still JSON');
	pg_assert_true(mb_check_encoding(ws_flow_result_text($long), 'UTF-8'), 'no character cut in half');
}

// The start box of a graph, and a box by its id.
function test_workspace_flow_runs_graph_lookups()
{
	$graph = ws_flow_runs_test_line();

	pg_assert_same('start', ws_flow_trigger_node($graph)['id']);
	pg_assert_same('payment', ws_flow_graph_node($graph, 'payment')['id']);
	pg_assert_same(null, ws_flow_graph_node($graph, 'nowhere'));
	pg_assert_same(null, ws_flow_trigger_node(ws_flow_graph_empty()));
}

// A task box's due date counts its days from the day the box became ready.
function test_workspace_flow_runs_due_date()
{
	$ready = mktime(15, 0, 0, 3, 30, 2026);

	pg_assert_same(null, ws_flow_due_date($ready, ''));
	pg_assert_same('2026-03-30', ws_flow_due_date($ready, 0));
	pg_assert_same('2026-04-02', ws_flow_due_date($ready, 3));
	pg_assert_same('2026-04-02', ws_flow_due_date($ready, '3'));
}

// The task a box opens says which run opened it, on its last line, which a
// long description never pushes out.
function test_workspace_flow_runs_task_description()
{
	pg_assert_same("Bring the papers.\n\nOpened by run 12.", ws_flow_task_description('Bring the papers.', 'Opened by run 12.'));
	pg_assert_same('Opened by run 12.', ws_flow_task_description('', 'Opened by run 12.'));

	$long = ws_flow_task_description(str_repeat('a', 8000), 'Opened by run 12.');

	pg_assert_true(mb_strlen($long) <= 8000, 'within what a task keeps');
	pg_assert_same('Opened by run 12.', mb_substr($long, -17), 'the line is kept');
}

// The line names the flow as the run started it, the run and the box, so a
// box tried again finds its own task and never a task of another box of
// the run; a name two boxes share is told apart by the box's id.
function test_workspace_flow_runs_task_marker()
{
	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array()),
			array('id' => 'legal', 'kind' => 'task', 'label' => 'Review', 'x' => 0, 'y' => 0, 'config' => array('title' => 'Review')),
			array('id' => 'finance', 'kind' => 'task', 'label' => 'Review', 'x' => 0, 'y' => 0, 'config' => array('title' => 'Review')),
			array('id' => 'contract', 'kind' => 'task', 'label' => 'Contract', 'x' => 0, 'y' => 0, 'config' => array('title' => 'Contract')),
			array('id' => 'pay', 'kind' => 'task', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array('title' => 'Pay')),
		),
		'edges' => array(),
	));

	pg_assert_same('Contract', ws_flow_box_unique_name($graph, ws_flow_graph_node($graph, 'contract')));
	pg_assert_same('pay', ws_flow_box_unique_name($graph, ws_flow_graph_node($graph, 'pay')), 'no label: its id');
	pg_assert_same('Review (legal)', ws_flow_box_unique_name($graph, ws_flow_graph_node($graph, 'legal')));
	pg_assert_same('Review (finance)', ws_flow_box_unique_name($graph, ws_flow_graph_node($graph, 'finance')));

	$legal = ws_flow_task_marker('New customer', 12, ws_flow_box_unique_name($graph, ws_flow_graph_node($graph, 'legal')));
	$finance = ws_flow_task_marker('New customer', 12, ws_flow_box_unique_name($graph, ws_flow_graph_node($graph, 'finance')));

	pg_assert_contains('New customer', $legal);
	pg_assert_contains('12', $legal);
	pg_assert_contains('Review (legal)', $legal);
	pg_assert_true($legal !== $finance, 'two boxes of one run, two lines');
	pg_assert_true($legal !== ws_flow_task_marker('New customer', 13, 'Review (legal)'), 'the same box of another run');

	// The name the run started with, kept in its context: renaming the flow
	// later changes nothing for the run.
	$run = array('context' => json_encode(ws_flow_run_context_new(array('name' => 'New customer', 'channel_id' => 7), array('id' => 'start'))));

	pg_assert_same('New customer', ws_flow_run_context($run)['flow_name']);
}

// A run keeps the home channel its flow had when it started: its tasks open
// there and its readers are that channel's, whatever the flow's home becomes
// later (a draft save may change it). A run that names none opens its tasks
// in no channel and is its flow owner's alone.
function test_workspace_flow_runs_channel_is_frozen()
{
	$context = ws_flow_run_context_new(array('name' => 'Hiring', 'channel_id' => 7), array('id' => 'start'));

	pg_assert_same('manual', $context['trigger']);
	pg_assert_same('start', $context['node']);
	pg_assert_same(7, $context['channel_id']);

	$run = array('context' => json_encode($context));

	pg_assert_same(7, ws_flow_run_channel_id($run));
	pg_assert_same(0, ws_flow_run_channel_id(array('context' => json_encode(ws_flow_run_context_new(array('name' => 'Mine', 'channel_id' => 0), array('id' => 'start'))))), 'a personal flow');
	pg_assert_same(0, ws_flow_run_channel_id(array('context' => '')), 'no context');
	pg_assert_same(0, ws_flow_run_channel_id(array('context' => 'not json')));
	pg_assert_same(0, ws_flow_run_channel_id(array('context' => json_encode(array('channel_id' => -3)))));

	// The task box and the run's readers go by the run's channel, never by
	// the flow's home as it is now.
	$task = ws_flow_runs_test_source('ws_flow_step_task');

	pg_assert_contains('ws_flow_run_channel_id($run)', $task);
	pg_assert_false(strpos($task, "\$flow['channel_id']") !== false, 'not the flow\'s home now');

	$access = ws_flow_runs_test_source('ws_flow_run_access');

	pg_assert_contains('ws_flow_run_channel_readable($viewer, $run)', $access);
	pg_assert_false(strpos($access, "\$flow['channel_id']") !== false);
	pg_assert_contains('ws_flow_run_channel_id($run)', ws_flow_runs_test_source('ws_flow_run_channel_readable'));
	pg_assert_contains('ws_flow_run_context_new($flow', ws_flow_runs_test_source('ws_flow_run_start'));
}

// Who may read a run and who may cancel it: the flow's editors; the people
// who may run the flow, or who started the run, while they read the channel
// the run was started in; and the starter of a run while they may run the
// flow, whatever the channel (a person the flow is shared with to run, who
// may not read its home, or a flow with no home at all). A view share reads
// no runs but the ones its holder started.
function test_workspace_flow_runs_access()
{
	pg_assert_same('edit', ws_flow_run_access_from('edit', false, false));
	pg_assert_same('read', ws_flow_run_access_from('run', false, true));
	pg_assert_same('', ws_flow_run_access_from('run', false, false), 'a reader of the flow\'s home now who does not read the channel the run was started in');
	pg_assert_same('read', ws_flow_run_access_from('run', true, false), 'the starter, while they may run the flow');
	pg_assert_same('read', ws_flow_run_access_from('', true, true), 'the starter of a run of a paused flow');
	pg_assert_same('', ws_flow_run_access_from('', true, false), 'a starter who no longer reads the run\'s channel');
	pg_assert_same('', ws_flow_run_access_from('', false, true));
	pg_assert_same('', ws_flow_run_access_from('view', false, true), 'a view share reads the graph, not the runs');
	pg_assert_same('read', ws_flow_run_access_from('view', true, true), 'but the runs its holder started, in a channel they read');
	pg_assert_same('', ws_flow_run_access_from('view', true, false));

	pg_assert_true(ws_flow_run_cancel_allowed('edit', false), 'the flow\'s editor');
	pg_assert_true(ws_flow_run_cancel_allowed('read', true), 'the run\'s starter');
	pg_assert_false(ws_flow_run_cancel_allowed('read', false), 'another reader');
	pg_assert_false(ws_flow_run_cancel_allowed('', true), 'nobody without access');
}

// The statuses of runs and boxes, each within its VARCHAR(16) column.
function test_workspace_flow_runs_statuses()
{
	pg_assert_same(array('running', 'waiting', 'done', 'failed', 'cancelled'), array_keys(ws_flow_run_statuses()));
	pg_assert_same(array('ready', 'running', 'waiting', 'done', 'failed', 'skipped'), array_keys(ws_flow_step_statuses()));

	foreach (array_merge(array_keys(ws_flow_run_statuses()), array_keys(ws_flow_step_statuses())) as $status) {
		pg_assert_true(strlen($status) <= 16, $status . ' fits its column');
	}

	pg_assert_true(ws_flow_step_open('waiting'));
	pg_assert_false(ws_flow_step_open('skipped'));
	pg_assert_true(ws_flow_step_settled('failed'));
	pg_assert_false(ws_flow_step_settled('ready'));
	pg_assert_true(ws_flow_run_open('waiting'));
	pg_assert_false(ws_flow_run_open('cancelled'));
}

// Two requests never run one box twice: a box is claimed, settled and a run
// finished with a conditional UPDATE whose affected rows are counted, and
// the owner is told only by the request that finished the run.
function test_workspace_flow_runs_writes_are_conditional()
{
	$claim = ws_flow_runs_test_source('ws_flow_step_claim');

	pg_assert_contains("status = 'running'", $claim);
	pg_assert_contains("AND status = 'ready'", $claim);
	pg_assert_contains('mysqli_affected_rows(', $claim);

	$settle = ws_flow_runs_test_source('ws_flow_step_settle');

	pg_assert_contains("AND status = '\" . e(\$from) . \"'", $settle);
	pg_assert_contains('mysqli_affected_rows(', $settle);

	$refresh = ws_flow_runs_test_source('ws_flow_run_refresh');

	pg_assert_contains('INSERT IGNORE INTO ws_flow_run_steps', $refresh, 'a box is written once per run and pass');
	pg_assert_contains("status IN ('running', 'waiting')", $refresh);
	pg_assert_true(strpos($refresh, 'mysqli_affected_rows(') < strpos($refresh, 'ws_flow_run_tell_owner('), 'the owner is told after the run was taken');

	// The task's id is kept in the same write that makes the box wait.
	$task = ws_flow_runs_test_source('ws_flow_step_task');

	pg_assert_true((bool) preg_match("/effect = '[^;]*status = 'waiting'[^;]*wait_key = '/s", $task), 'effect, waiting and the key in one UPDATE');
	pg_assert_true(strpos($task, 'ws_task_create(') < strpos($task, "effect = '"), 'right after the task is opened');

	// The box is carried out as the claim left it: on its second try it
	// knows it is one and looks for the task its first try opened.
	$advance = ws_flow_runs_test_source('ws_flow_advance');

	pg_assert_true(strpos($advance, 'ws_flow_step_claim($row)') < strpos($advance, "\$row['attempt'] = (int) \$row['attempt'] + 1;"), 'the attempt counted after the claim');
	pg_assert_true(strpos($advance, "\$row['attempt'] = (int) \$row['attempt'] + 1;") < strpos($advance, 'ws_flow_step_run($row)'), 'before the box runs');
	pg_assert_contains("((int) \$step['attempt'] > 1) ? ws_flow_step_task_find(", ws_flow_runs_test_source('ws_flow_step_task'));

	$cancel = ws_flow_runs_test_source('ws_flow_run_cancel');

	pg_assert_contains("status IN ('running', 'waiting')", $cancel);
	pg_assert_contains("status IN ('ready', 'running', 'waiting')", $cancel);
	pg_assert_false(strpos($cancel, 'ws_task_set_status(') !== false, 'the tasks a run opened stay as they are');
}

// The drivers: the scheduled run advances flows before it can return early,
// its gate sees boxes that are due, the queue of the workspace's events
// keeps the events a box waits for and wakes the box.
function test_workspace_flow_runs_drivers()
{
	$run = ws_flow_runs_test_source('ws_scheduled_run');

	pg_assert_contains('ws_flow_advance(', $run);
	pg_assert_true(strpos($run, 'ws_flow_advance(') < strpos($run, 'if (!ws_scheduled_ready())'), 'before the first early return');

	$due = ws_flow_runs_test_source('ws_scheduled_due');

	pg_assert_contains('ws_flow_due(', $due);
	pg_assert_true(strpos($due, 'ws_flow_due(') < strpos($due, 'if (!ws_scheduled_ready())'), 'before the first early return');

	pg_assert_contains('ws_flow_waits_awaited(', ws_flow_runs_test_source('ws_events_awaited'));
	pg_assert_contains('ws_flow_wake(', ws_flow_runs_test_source('ws_events_process'));

	// A task box that starts waiting drops the request's list of awaited
	// events, so a status change later in the same request is kept.
	pg_assert_contains('ws_events_awaited(true)', ws_flow_runs_test_source('ws_flow_step_task'));

	// Deleting a flow ends its open runs.
	pg_assert_contains('ws_flow_runs_cancel_for_flow(', ws_flow_runs_test_source('ws_flow_bin'));
}

// A request that dies half way never leaves a run nobody looks at again: a
// box is settled only after its run was marked running, so a run whose next
// boxes were never written reads running, untouched, and is walked again
// (ws_flow_runs_heal()), its start box written first when the run has none.
// The gate of the jobs sees such a run.
function test_workspace_flow_runs_half_way_runs_are_walked_again()
{
	$settle = ws_flow_runs_test_source('ws_flow_step_settle');

	pg_assert_contains('ws_flow_run_touch(', $settle);
	pg_assert_true(strpos($settle, 'ws_flow_run_touch(') < strpos($settle, 'UPDATE ws_flow_run_steps'), 'the run is marked before the box settles');

	$touch = ws_flow_runs_test_source('ws_flow_run_touch');

	pg_assert_contains("status = 'running'", $touch);
	pg_assert_contains("status IN ('running', 'waiting')", $touch, 'an ended run stays ended');

	$heal = ws_flow_runs_test_source('ws_flow_runs_heal');

	pg_assert_contains("status = 'running' AND updated_at < '\" . (\$now - WS_FLOW_LOCK) . \"'", $heal);
	pg_assert_contains('INSERT IGNORE INTO ws_flow_run_steps', $heal, 'the start box of a run that has none');
	pg_assert_contains('ws_flow_run_refresh(', $heal);

	$advance = ws_flow_runs_test_source('ws_flow_advance');

	pg_assert_contains('ws_flow_runs_heal(', $advance);
	pg_assert_true(strpos($advance, 'ws_flow_runs_heal(') < strpos($advance, 'ws_flow_step_claim('), 'before the claims');

	pg_assert_contains("FROM ws_flow_runs WHERE status = 'running' AND updated_at < '", ws_flow_runs_test_source('ws_flow_due'));
}

// A run of a flow deleted meanwhile opens nothing more: its box is let go
// and the run is cancelled with its open boxes. A start writes its run only
// while the flow is still live, and deleting a flow cancels every open run,
// however many.
function test_workspace_flow_runs_deleted_flow_stops()
{
	$step = ws_flow_runs_test_source('ws_flow_step_run');

	pg_assert_contains("(string) \$flow['status'] === 'binned'", $step);
	pg_assert_true(strpos($step, "'binned'") < strpos($step, 'ws_flow_step_task('), 'before a task is opened');
	pg_assert_contains('ws_flow_runs_cancel_ids(', $step);

	$start = ws_flow_runs_test_source('ws_flow_run_start');

	pg_assert_contains("FROM ws_flows WHERE id = '\" . \$flow_id . \"' AND status = 'live'", $start, 'the run is written only while the flow is live');

	$cancel = ws_flow_runs_test_source('ws_flow_runs_cancel_for_flow');

	pg_assert_false(strpos($cancel, 'LIMIT " . WS_FLOW_MAX_OPEN_RUNS') !== false, 'no cap on the runs cancelled');
	pg_assert_contains('ws_flow_runs_cancel_ids(', $cancel);

	// A waiting box of a run that ended is let go when it looks again.
	pg_assert_contains('ws_flow_run_open($run[\'status\'])', ws_flow_runs_test_source('ws_flow_step_recheck'));
}

// A retried start answers the run its token started even when the flow was
// paused, or its owner left, meanwhile: the token is looked up before those
// refusals.
function test_workspace_flow_runs_token_answers_before_refusals()
{
	$check = ws_flow_runs_test_source('ws_flow_run_start_check');

	pg_assert_true(strpos($check, 'start_key = ') < strpos($check, 'Only a published flow that is live can be run.'), 'before the live check');
	pg_assert_true(strpos($check, 'start_key = ') < strpos($check, 'ws_can_schedule($owner)'), 'before the owner check');
	pg_assert_true(strpos($check, 'ws_flow_access($viewer, $flow)') < strpos($check, 'start_key = '), 'after the access check');
	pg_assert_contains('ws_flow_start_key($token)', $check);
}

// A box tried again that finds the task its first try opened looks at it at
// once: the task may have been finished while the box was stopped, and the
// event that said so woke nothing.
function test_workspace_flow_runs_adopted_task_is_looked_at_at_once()
{
	$task = ws_flow_runs_test_source('ws_flow_step_task');

	pg_assert_contains('$adopted ? $now : $now + WS_FLOW_RECHECK', $task);

	$find = ws_flow_runs_test_source('ws_flow_step_task_find');

	pg_assert_contains('NOT EXISTS (SELECT 1 FROM ws_flow_run_steps', $find, 'never a task another box of the run holds');
	pg_assert_contains('ORDER BY id DESC', $find);
	pg_assert_contains("addcslashes(\$marker, '\\\\%_')", $find, 'a backslash in the line is no escape');
}

// Every run that ends is a line of its own in its owner's inbox: an unread
// line about another run is never written over.
function test_workspace_flow_runs_each_end_is_its_own_line()
{
	pg_assert_contains("'separate' => true", ws_flow_runs_test_source('ws_flow_run_tell_owner'));

	$notify = ws_flow_runs_test_source('ws_notify');

	pg_assert_contains("empty(\$data['separate'])", $notify);
	pg_assert_true(strpos($notify, "empty(\$data['separate'])") < strpos($notify, 'if ($existing > 0)'), 'the same line is looked for only without it');
}
