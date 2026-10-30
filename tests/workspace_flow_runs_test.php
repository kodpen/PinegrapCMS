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
	pg_assert_same(65535, WS_FLOW_RESULT_BYTES);
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

// A run started in a channel its starter names keeps that channel instead
// of the flow's home: a public or private channel the starter reads and the
// flow's owner may write in, else 403; the start check makes it, after the
// token's run is answered and the owner is known, so the dry run meets it.
function test_workspace_flow_runs_start_names_its_channel()
{
	$flow = array('name' => 'Hiring', 'channel_id' => 7);

	pg_assert_same(9, ws_flow_run_context_new($flow, array('id' => 'start'), 9)['channel_id'], 'the channel named');
	pg_assert_same(7, ws_flow_run_context_new($flow, array('id' => 'start'), null)['channel_id'], 'none named: the home');
	pg_assert_same(7, ws_flow_run_context_new($flow, array('id' => 'start'))['channel_id']);

	foreach (array('public', 'private') as $kind) {
		pg_assert_true(ws_flow_run_channel_allowed(array('kind' => $kind), true, true), $kind);
	}

	pg_assert_false(ws_flow_run_channel_allowed(array('kind' => 'public'), false, true), 'the starter does not read it');
	pg_assert_false(ws_flow_run_channel_allowed(array('kind' => 'public'), true, false), 'the owner may not write in it');
	pg_assert_false(ws_flow_run_channel_allowed(null, true, true), 'a channel that is gone');

	foreach (array('thread', 'guest', 'direct', '') as $kind) {
		pg_assert_false(ws_flow_run_channel_allowed(array('kind' => $kind), true, true), $kind . ' is no channel a run is read through');
	}

	$check = ws_flow_runs_test_source('ws_flow_run_channel_check');

	pg_assert_contains('ws_can_read_channel($viewer, $channel)', $check);
	pg_assert_contains('ws_can_post_channel($owner, $channel)', $check);
	pg_assert_contains("403, 'channel_id'", $check);

	$start = ws_flow_runs_test_source('ws_flow_run_start_check');
	$named = strpos($start, 'ws_flow_run_channel_check($viewer, $flow, $data[\'channel_id\'], $owner)');

	pg_assert_true(($named !== false) && ($named > strpos($start, 'ws_can_schedule($owner)')) && ($named > strpos($start, 'return $found($existing, $start_key);')), 'after the owner check and the token\'s run');
	pg_assert_contains("\$check['channel_id']", ws_flow_runs_test_source('ws_flow_run_start'), 'the run keeps the channel checked');
	pg_assert_contains("'channel_id'", ws_flow_runs_test_source('ws_api_flow_runs_start'), 'the route passes it on');
}

// Who may read a run and who may cancel it: the flow's editors; the people
// who may run the flow, or who started the run, while they read the channel
// the run was started in; and the starter of a run while they may run the
// flow, whatever the channel, when the channel does not decide their own run
// (a person share lets them run it, who may not read its home, or the run
// has no channel: a flow with no home). Running the flow through its home
// alone does not: a run started in a channel they were taken out of since
// stays out of their sight. A view share reads no runs but the ones its
// holder started.
function test_workspace_flow_runs_access()
{
	pg_assert_same('edit', ws_flow_run_access_from('edit', false, false));
	pg_assert_same('read', ws_flow_run_access_from('run', false, true));
	pg_assert_same('', ws_flow_run_access_from('run', false, false), 'a reader of the flow\'s home now who does not read the channel the run was started in');
	pg_assert_same('', ws_flow_run_access_from('run', true, false), 'the starter who runs the flow through its home, taken out of the run\'s channel since');
	pg_assert_same('read', ws_flow_run_access_from('run', true, false, true), 'the starter, while a person share lets them run the flow or the run has no channel');
	pg_assert_same('', ws_flow_run_access_from('run', false, false, true), 'somebody else\'s run');
	pg_assert_same('', ws_flow_run_access_from('view', true, false, true), 'a view share runs nothing');
	pg_assert_same('', ws_flow_run_access_from('', true, false, true), 'nor does a flow they no longer reach');
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

	// The channel leaves a starter's own run alone only for a run with no
	// channel or a person share that gives run; ws_flow_run_access() asks.
	$own = ws_flow_runs_test_source('ws_flow_run_own_reach');

	pg_assert_contains('ws_flow_run_channel_id($run) <= 0', $own, 'a run with no channel');
	pg_assert_contains("ws_flow_share_reach(\$viewer, \$flow)['person']", $own, 'a share made to the person');
	pg_assert_contains("ws_flow_access_rank('run')", $own, 'that lets them run the flow');
	pg_assert_contains('ws_flow_run_own_reach($viewer, $flow, $run)', ws_flow_runs_test_source('ws_flow_run_access'));
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
// The gate of the jobs sees such a run. A walk that read the boxes just
// before one of them ended may still write waiting over that mark; such a
// run has a box that ended at or after its last write, which the heal looks
// for among the waiting runs too.
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

	// The moment a walk writes is taken before it reads the boxes, so a box
	// that ends after that read always ended at or after the run's write.
	$refresh = ws_flow_runs_test_source('ws_flow_run_refresh');

	pg_assert_true(strpos($refresh, '$now = time()') < strpos($refresh, 'ws_flow_run_steps('), 'the moment before the read');

	// A waiting run untouched for WS_FLOW_LOCK with such a box is walked
	// again, and every run walked is marked looked at, waiting or running.
	pg_assert_true((bool) preg_match("/status = 'waiting'[^;]*EXISTS \\(SELECT 1 FROM ws_flow_run_steps[^;]*status IN \\('done', 'failed', 'skipped'\\)[^;]*finished_at >= ws_flow_runs\\.updated_at/s", $heal), 'a waiting run with a box ended since its last write');
	pg_assert_true((bool) preg_match("/UPDATE ws_flow_runs SET updated_at = [^;]*status IN \\('running', 'waiting'\\)/s", $heal), 'looked at, whichever it reads');
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

	pg_assert_contains("FROM ws_flows WHERE id = ? AND status = 'live'", $start, 'the run is written only while the flow is live');

	$cancel = ws_flow_runs_test_source('ws_flow_runs_cancel_for_flow');

	pg_assert_false(strpos($cancel, 'LIMIT " . WS_FLOW_MAX_OPEN_RUNS') !== false, 'no cap on the runs cancelled');
	pg_assert_contains('ws_flow_runs_cancel_ids(', $cancel);

	// A waiting box of a run that ended is let go when it looks again.
	pg_assert_contains('ws_flow_run_open($run[\'status\'])', ws_flow_runs_test_source('ws_flow_step_recheck'));
}

// A retried start answers the run its token started even when the flow was
// paused, or its owner left, meanwhile: the token is looked up before those
// refusals. A person who may no longer run the flow (it was paused, or their
// share was lowered to view) still gets the run back while they read it, as
// GET /workspace/flow-runs/{id} gives it to them; anybody else gets the 404
// of a flow they cannot see, or the 403 of a view share, never a refusal of
// the token's shape.
function test_workspace_flow_runs_token_answers_before_refusals()
{
	$check = ws_flow_runs_test_source('ws_flow_run_start_check');

	pg_assert_true(strpos($check, 'start_key = ') < strpos($check, 'Only a published flow that is live can be run.'), 'before the live check');
	pg_assert_true(strpos($check, 'start_key = ') < strpos($check, 'ws_flow_builder($owner)'), 'before the owner check');
	pg_assert_contains('ws_flow_start_key($token)', $check);

	pg_assert_contains('ws_flow_run_for_token(', $check, 'the run a token started, without the right to run the flow');
	pg_assert_true(strpos($check, 'ws_flow_run_for_token(') < strpos($check, 'That flow could not be found.'), 'before the 404');
	pg_assert_true(strpos($check, 'ws_flow_run_for_token(') < strpos($check, 'You can look at this flow but not run it.'), 'and before a view share is refused');
	pg_assert_true(strpos($check, 'ws_flow_access($viewer, $flow)') < strpos($check, 'The token of a run is'), 'the token\'s shape is judged only for a flow the person may run');

	// Only a run the person reads comes back: the same gate as reading it.
	$token = ws_flow_runs_test_source('ws_flow_run_for_token');

	pg_assert_contains('ws_flow_start_token_valid($token)', $token);
	pg_assert_contains('ws_flow_run_for($viewer', $token);
	pg_assert_same('read', ws_flow_run_access_from('', true, true), 'the starter of a run of a paused flow reads it');
	pg_assert_same('', ws_flow_run_access_from('', false, true), 'somebody else does not');
}

// A box tried again that finds the task its first try opened looks at it at
// once: the task may have been finished while the box was stopped, and the
// event that said so woke nothing.
function test_workspace_flow_runs_adopted_task_is_looked_at_at_once()
{
	$task = ws_flow_runs_test_source('ws_flow_step_task');

	pg_assert_contains('$adopted ? $now : ws_flow_timeout_next_look($now, $timeout_at)', $task);

	$find = ws_flow_runs_test_source('ws_flow_step_task_find');

	pg_assert_contains('NOT EXISTS (SELECT 1 FROM ws_flow_run_steps', $find, 'never a task another box of the run holds');
	pg_assert_contains('ORDER BY id DESC', $find);
	pg_assert_contains("addcslashes(\$marker, '\\\\%_')", $find, 'a backslash in the line is no escape');
}

// A run cancelled while its task box is on its way opens no task: the box
// looks at the run again right before it opens one. A task opened at the
// very moment of the cancel is still written on its box (skipped by the
// cancel, or failed as stopped half way), so the run shows the task it
// opened; a box made ready again for its retry is left alone, its retry
// finds that task itself.
function test_workspace_flow_runs_cancel_meets_a_task_box_at_work()
{
	$task = ws_flow_runs_test_source('ws_flow_step_task');
	$open = strpos($task, 'ws_flow_run_open(');

	pg_assert_true(($open !== false) && ($open < strpos($task, 'ws_task_create(')), 'the run is looked at before the task is opened');
	pg_assert_true((bool) preg_match("/SET effect = 'task:[^;]*AND effect = ''[^;]*AND status IN \\('skipped', 'failed'\\)/s", $task), 'the task written on a box that ended meanwhile');
	pg_assert_true(strpos($task, "status IN ('skipped', 'failed')") > strpos($task, "status = 'waiting'"), 'when the box could not be made to wait');
}

// The boxes of a run as the walk reads them: one entry per box, the row
// read last standing for it (a later pass of the box), its status and
// outcome kept as they are.
function test_workspace_flow_runs_step_states()
{
	pg_assert_same(array(), ws_flow_step_states(array()));
	pg_assert_same(array(), ws_flow_step_states(null));

	$rows = array(
		array('id' => 1, 'node_id' => 'a', 'status' => 'done', 'outcome' => 'done', 'pass' => 0),
		array('id' => 2, 'node_id' => 'a', 'status' => 'ready', 'outcome' => '', 'pass' => 1),
		array('id' => 3, 'node_id' => 'b', 'status' => 'waiting', 'outcome' => ''),
		array('id' => 4, 'node_id' => 7, 'status' => 'done', 'outcome' => 'next'),
	);

	pg_assert_same(array(
		'a' => array('status' => 'ready', 'outcome' => ''),
		'b' => array('status' => 'waiting', 'outcome' => ''),
		'7' => array('status' => 'done', 'outcome' => 'next'),
	), ws_flow_step_states($rows));

	// The order of the rows decides, as ws_flow_run_steps() reads them.
	pg_assert_same(array('status' => 'done', 'outcome' => 'done'), ws_flow_step_states(array($rows[1], $rows[0]))['a']);
}

// One call carries out at most WS_FLOW_BOXES_PER_RUN boxes of a run, also
// when one batch holds more of it (a fork), and WS_FLOW_ADVANCE_MAX in all;
// the next batch is read without the runs that had their share. A box
// another request took is passed over and counts for nothing, a batch that
// starts nothing ends the call, and nothing starts once the deadline has
// passed.
function test_workspace_flow_runs_advance_budgets()
{
	// The ready boxes, oldest first: five of run 1, two of run 2, four each
	// of runs 3 to 12. A batch is twenty, as the query reads them.
	$make = function ($counts) {
		$ready = array();
		$id = 0;

		foreach ($counts as $run => $count) {
			for ($i = 0; $i < $count; $i++) {
				$id++;
				$ready[$id] = array('id' => $id, 'run_id' => $run);
			}
		}

		return $ready;
	};
	$ready = $make(array(1 => 5, 2 => 2) + array_fill(3, 10, 4));
	$asked = array();
	$select = function ($full) use (&$ready, &$asked) {
		$asked[] = array_values($full);

		return array_slice(array_values(array_filter($ready, function ($row) use ($full) {
			return !isset($full[$row['run_id']]);
		})), 0, 20);
	};
	$claim = function ($row) use (&$ready) {
		if (!isset($ready[$row['id']])) {
			return false;
		}

		unset($ready[$row['id']]);

		return true;
	};
	$carried = array();
	$carry = function ($row) use (&$carried) {
		$carried[] = $row;
	};

	$ran = ws_flow_advance_boxes($select, $claim, $carry, microtime(true) + 60);
	$per_run = array_count_values(array_column($carried, 'run_id'));

	pg_assert_same(WS_FLOW_ADVANCE_MAX, $ran, 'no more than the call\'s share');
	pg_assert_same($ran, count($carried));
	pg_assert_same(WS_FLOW_BOXES_PER_RUN, max($per_run), 'no more than a run\'s share');
	pg_assert_same(WS_FLOW_BOXES_PER_RUN, $per_run[1], 'run 1 had its share from the first batch');
	pg_assert_true(isset($ready[4], $ready[5]), 'its other boxes were not taken: they wait for the next call');
	pg_assert_same(2, $per_run[2]);
	pg_assert_true(in_array(1, $asked[1], true), 'the next batch is read without the run that had its share');

	// A box another request took is passed over; the run's share is still
	// there for its other boxes.
	$ready = $make(array(1 => 4));
	$carried = array();
	$taken = function ($row) use (&$ready) {
		if ((int) $row['id'] === 1) {
			return false;
		}

		unset($ready[$row['id']]);

		return true;
	};
	$once = function ($full) use (&$ready) {
		return array_values($ready);
	};

	pg_assert_same(3, ws_flow_advance_boxes($once, $taken, $carry, microtime(true) + 60));
	pg_assert_same(array(2, 3, 4), array_column($carried, 'id'), 'the box the other request took is not carried out');

	// A batch that starts nothing ends the call; nothing starts after the
	// deadline, and nothing is even taken.
	$asked = array();
	$ready = $make(array(1 => 1));
	$never = function ($row) {
		return false;
	};

	pg_assert_same(0, ws_flow_advance_boxes($select, $never, $carry, microtime(true) + 60));
	pg_assert_same(1, count($asked), 'one batch, then the call ends');

	$claims = 0;
	$counted = function ($row) use (&$claims) {
		$claims++;

		return true;
	};

	pg_assert_same(0, ws_flow_advance_boxes($select, $counted, $carry, 0.0), 'a deadline passed');
	pg_assert_same(0, $claims);

	// ws_flow_advance() reads each batch without the runs that had their
	// share, takes each box with the conditional claim, and carries it out
	// as the claim left it.
	$advance = ws_flow_runs_test_source('ws_flow_advance');

	pg_assert_contains('ws_flow_advance_boxes(', $advance);
	pg_assert_contains('run_id NOT IN (', $advance);
	pg_assert_contains('ws_flow_step_claim($row)', $advance);
	pg_assert_contains('ws_flow_step_run($row)', $advance);
}

// Runs that ended more than WS_FLOW_RUNS_KEEP ago are swept with their boxes,
// never an open one; the boxes go first, so a sweep that stops half way
// leaves no box whose run is gone. The Workspace's scheduled job sweeps.
function test_workspace_flow_runs_sweep()
{
	$sweep = ws_flow_runs_test_source('ws_flow_runs_sweep');

	pg_assert_true((bool) preg_match('/FROM ws_flow_runs\s+WHERE status IN \(([^)]*)\)/', $sweep, $found), 'the sweep picks runs by their status');

	$listed = array_map(function ($status) {
		return trim(trim($status), "'");
	}, explode(',', $found[1]));
	$ended = array_values(array_filter(array_keys(ws_flow_run_statuses()), function ($status) {
		return !ws_flow_run_open($status);
	}));

	sort($listed);
	sort($ended);

	pg_assert_same($ended, $listed, 'every ended status and no open one');
	pg_assert_contains('time() - WS_FLOW_RUNS_KEEP', $sweep);

	$steps = strpos($sweep, 'DELETE FROM ws_flow_run_steps');
	$runs = strpos($sweep, 'DELETE FROM ws_flow_runs ');

	pg_assert_true(($steps !== false) && ($runs !== false) && ($steps < $runs), 'the boxes before their runs');
	pg_assert_contains('ws_flow_runs_sweep(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_recurring_job.php'));
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

// An arrow on always leaves a box however it ended, as a scheduled action's
// follow-up on always: every box with outcomes has it, a start box and the
// end do not.
function test_workspace_flow_runs_always_is_an_outcome_of_every_box()
{
	$kinds = ws_flow_kinds();

	pg_assert_same(array('done', 'cancelled', 'timeout', 'always'), ws_flow_arrow_outcomes($kinds['task']));
	pg_assert_same(array('next'), ws_flow_arrow_outcomes($kinds['manual']));
	pg_assert_same(array(), ws_flow_arrow_outcomes($kinds['end']));

	pg_assert_true(ws_flow_edge_takes('always', 'cancelled'));
	pg_assert_true(ws_flow_edge_takes('done', 'done'));
	pg_assert_false(ws_flow_edge_takes('done', 'cancelled'));
	pg_assert_false(ws_flow_edge_takes('always', ''), 'a box without an outcome took no arrow');

	$codes = function ($graph) {
		return array_map(function ($problem) {
			return $problem['code'] . ':' . $problem['node'];
		}, ws_flow_graph_check($graph));
	};

	pg_assert_same(array(), $codes(ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'stop' => 'end'),
		array(array('start', 'a', 'next'), array('a', 'stop', 'always'))
	)));
	pg_assert_same(array('bad_outcome:start'), $codes(ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task'),
		array(array('start', 'a', 'always'))
	)));
}

// The box after an arrow on always is ready whatever the box before ended
// on; a failure an arrow on always takes is routed, so the box is done.
function test_workspace_flow_runs_walk_always()
{
	$graph = ws_flow_runs_test_graph(
		array('start' => 'manual', 'a' => 'task', 'b' => 'task'),
		array(array('start', 'a', 'next'), array('a', 'b', 'always'))
	);

	pg_assert_same(array('b'), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:done')))['ready']);
	pg_assert_same(array('b'), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:cancelled')))['ready']);
	pg_assert_same(array('b'), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'done:timeout')))['ready']);
	pg_assert_same(array('b'), ws_flow_walk($graph, ws_flow_runs_test_steps(array('start' => 'done:next', 'a' => 'failed:')))['skipped'], 'a box that failed blocks what follows');

	pg_assert_true(ws_flow_outcome_routed($graph, 'a', 'cancelled'));
	pg_assert_true(ws_flow_outcome_routed($graph, 'a', 'timeout'));
	pg_assert_false(ws_flow_outcome_routed($graph, 'start', 'failed'));
}

// The result a run's flag shows: the first failure of a failed run, else the
// box that moved last, an open box latest of all; boxes without a sentence
// are passed over.
function test_workspace_flow_runs_result_pick()
{
	$line = function ($status, $text, $finished, $id) {
		return array('status' => $status, 'text' => $text, 'finished_at' => $finished, 'id' => $id);
	};

	pg_assert_same('', ws_flow_run_result_pick('done', array()), 'no boxes');
	pg_assert_same('', ws_flow_run_result_pick('done', array($line('done', '  ', 10, 1))), 'no sentence');
	pg_assert_same('Mail sent.', ws_flow_run_result_pick('done', array(
		$line('done', 'Task opened.', 10, 1),
		$line('done', 'Mail sent.', 20, 2),
		$line('skipped', '', 30, 3),
	)), 'the last box with a sentence');
	pg_assert_same('Second.', ws_flow_run_result_pick('done', array($line('done', 'First.', 20, 1), $line('done', 'Second.', 20, 2))), 'a tie goes to the later box');
	pg_assert_same('Waiting for Ada.', ws_flow_run_result_pick('waiting', array(
		$line('waiting', 'Waiting for Ada.', 0, 1),
		$line('done', 'Mail sent.', 20, 2),
	)), 'an open box is the latest');
	pg_assert_same('Address refused.', ws_flow_run_result_pick('failed', array(
		$line('done', 'Task opened.', 10, 1),
		$line('failed', 'Address refused.', 20, 2),
		$line('failed', 'Timed out.', 30, 3),
		$line('done', 'Later.', 40, 4),
	)), 'the first failure of a failed run');
	pg_assert_same('Later.', ws_flow_run_result_pick('done', array($line('failed', 'Routed failure.', 20, 2), $line('done', 'Later.', 40, 4))), 'a routed failure of a run done is just a box');
	pg_assert_same(WS_FLOW_RUN_RESULT_CHARS, mb_strlen(ws_flow_run_result_pick('done', array($line('done', str_repeat('ş', 1500), 10, 1)))), 'cut in characters');
}

// A run about a customer keeps one start key per flow and contact while it
// is open: the key names the last ended run of that contact, so a second
// start meets the open run's key, and once that run ends the next start
// gets a key of its own. It never meets a key a token makes.
function test_workspace_flow_runs_contact_start_key()
{
	pg_assert_same('contact:5:0', ws_flow_contact_start_key(5, 0));
	pg_assert_same('contact:5:31', ws_flow_contact_start_key(5, 31));
	pg_assert_true(ws_flow_contact_start_key(5, 0) !== ws_flow_contact_start_key(6, 0), 'one key per contact');
	pg_assert_true(ws_flow_contact_start_key(5, 0) !== ws_flow_contact_start_key(5, 12), 'a run ended since: a new key');
	pg_assert_true(strpos(ws_flow_contact_start_key(5, 0), 'manual:') !== 0, 'never a token\'s key');
	pg_assert_true(strlen(ws_flow_contact_start_key(4294967295, 4294967295)) <= 64, 'fits start_key');
}

// A second Run for a customer with an open run answers that run while the
// starter may read it; a run they may not read is refused, never shown and
// never doubled; no open run starts one.
function test_workspace_flow_runs_contact_open_run_answered()
{
	pg_assert_same('start', ws_flow_run_subject_answer(0, false));
	pg_assert_same('answer', ws_flow_run_subject_answer(41, true));
	pg_assert_same('busy', ws_flow_run_subject_answer(41, false));

	// Read from the source: the open run is looked for once the contact is
	// known to be readable, and before the limits refuse a start.
	$check = ws_flow_runs_test_source('ws_flow_run_start_check');
	$contact = strpos($check, 'ws_flow_run_contact_check(');
	$open = strpos($check, 'ws_flow_run_subject_answer(');
	$limits = strpos($check, 'ws_flow_start_refusal(');

	pg_assert_true(($contact !== false) && ($open !== false) && ($contact < $open), 'the read right first');
	pg_assert_true($open < $limits, 'the open run before the limits');

	// The run row carries its subject.
	$start = ws_flow_runs_test_source('ws_flow_run_start');

	pg_assert_contains("subject_type, subject_id", $start);
	pg_assert_contains("\$check['subject']", $start);
}

// The tasks of a run about a customer carry the customer's name after their
// title, within the length a task title keeps; the name is never cut away.
function test_workspace_flow_runs_task_title_with_customer()
{
	pg_assert_same('Send the contract', ws_flow_task_title_customer('Send the contract', ''));
	pg_assert_same('Send the contract – Ayşe Kaya', ws_flow_task_title_customer('Send the contract', 'Ayşe Kaya'));

	$long = ws_flow_task_title_customer(str_repeat('a', 200), 'Ayşe Kaya');

	pg_assert_true(mb_strlen($long) <= 200, 'within a task title');
	pg_assert_same(' – Ayşe Kaya', mb_substr($long, -12), 'the name is kept');

	// The name the run started with, kept in its context, as the flow's.
	$context = ws_flow_run_context_new(array('name' => 'Onboarding', 'channel_id' => 7), array('id' => 'start'), null, array('type' => 'contact', 'id' => 5, 'name' => 'Ayşe Kaya'));

	pg_assert_same('Ayşe Kaya', $context['subject_name']);
	pg_assert_true(!isset(ws_flow_run_context_new(array('name' => 'Onboarding'), array('id' => 'start'))['subject_name']), 'no customer: no name');
	pg_assert_contains('ws_flow_task_title_customer(', ws_flow_runs_test_source('ws_flow_step_task'));
}

// Only a contact the starter may read is a run's customer: staff with the
// contacts right read every contact, the user role those in its contact
// groups, nobody without the contacts right.
function test_workspace_flow_runs_contact_read_right()
{
	$staff = array('id' => 2, 'role' => 1, 'contacts' => true);
	$member = array('id' => 9, 'role' => 3, 'contacts' => true);
	$none = array('id' => 9, 'role' => 3, 'contacts' => false);

	pg_assert_true(ws_flow_run_contact_readable($staff, array(5), 5));
	pg_assert_true(ws_flow_run_contact_readable($member, array(5, 8), 8));
	pg_assert_true(!ws_flow_run_contact_readable($member, array(5), 8), 'outside their contact groups');
	pg_assert_true(!ws_flow_run_contact_readable($none, array(5), 5), 'no contacts right');
	pg_assert_true(!ws_flow_run_contact_readable($staff, array(5), 0), 'no contact');

	pg_assert_contains('ws_viewer_contacts_open(', ws_flow_runs_test_source('ws_flow_run_contact_check'));
}
