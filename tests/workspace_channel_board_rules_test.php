<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rules of a channel's board
 * (includes/workspace/channel_board_rules.php): the four ready rules, the
 * answer a rule asks for, the flow a rule is made into, which flows are the
 * board's rules (a task event of this channel), which ready rule a flow is,
 * and the list the drawer is drawn from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/tasks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_board.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_board_rules.php');

function ws_board_rules_test_flow($id, $graph, $extra = array())
{
	return $extra + array(
		'id'         => $id,
		'owner_id'   => 5,
		'channel_id' => 12,
		'name'       => 'Flow ' . $id,
		'status'     => 'live',
		'version_id' => $id * 10,
		'updated_at' => 1000 + $id,
		'access'     => 'edit',
		'graph'      => $graph,
	);
}

function ws_board_rules_test_event($event, $status = '', $channel = 12, $id = 'start')
{
	return ws_flow_graph_normalize(array('v' => 1, 'nodes' => array(
		array('id' => $id, 'kind' => 'event', 'label' => '', 'x' => 0, 'y' => 0,
			'config' => array('event' => $event, 'page_id' => 0, 'status' => $status, 'channel_id' => $channel)),
		array('id' => 'tell', 'kind' => 'post', 'label' => '', 'x' => 256, 'y' => 0, 'config' => array('channel_id' => $channel, 'body' => 'x')),
	), 'edges' => array(array('from' => $id, 'to' => 'tell', 'on' => 'next'))));
}

// Four ready rules in the drawer's order; two ask for an answer.
function test_workspace_channel_board_rules_templates()
{
	$templates = ws_board_rule_templates();

	pg_assert_same(array('done_post', 'overdue_remind', 'waiting_approval', 'done_next_task'), array_keys($templates));
	pg_assert_same(array('', '', 'person', 'text'), array_column($templates, 'asks'));
	pg_assert_same('When a task comes to “Waiting”', $templates['waiting_approval']['when']);

	foreach ($templates as $rule) {
		pg_assert_true(in_array($rule['event'], ws_board_rule_events(), true), 'a task event');
	}
}

function test_workspace_channel_board_rules_answer_check()
{
	pg_assert_same(array(3, 7), ws_board_rule_answer_check('waiting_approval', array('3', 7, 3, 0, 'x', true))['value']);
	pg_assert_false(ws_board_rule_answer_check('waiting_approval', array())['ok']);
	pg_assert_false(ws_board_rule_answer_check('waiting_approval', range(1, 21))['ok']);

	pg_assert_same('Send the invoice', ws_board_rule_answer_check('done_next_task', "  Send   the\ninvoice ")['value']);
	pg_assert_false(ws_board_rule_answer_check('done_next_task', '   ')['ok']);
	pg_assert_false(ws_board_rule_answer_check('done_next_task', str_repeat('a', 201))['ok']);
	pg_assert_false(ws_board_rule_answer_check('done_next_task', array('a'))['ok']);

	pg_assert_true(ws_board_rule_answer_check('done_post', null)['ok']);
	pg_assert_false(ws_board_rule_answer_check('nothing', null)['ok']);
}

// A rule's flow: an event start narrowed to the channel, one step, no
// problem left once answered, and it is read back as that rule.
function test_workspace_channel_board_rules_graph()
{
	pg_assert_same(null, ws_board_rule_graph('nothing', 12));

	$answers = array('done_post' => null, 'overdue_remind' => null, 'waiting_approval' => array(4), 'done_next_task' => 'Next');
	$kinds = array('done_post' => 'post', 'overdue_remind' => 'post', 'waiting_approval' => 'approval', 'done_next_task' => 'task_open');

	foreach ($answers as $key => $answer) {
		$graph = ws_board_rule_graph($key, 12, $answer);

		pg_assert_same(array('board_' . $key, 'step'), array_column($graph['nodes'], 'id'), $key);
		pg_assert_same(array('event', $kinds[$key]), array_column($graph['nodes'], 'kind'), $key);
		pg_assert_same(12, $graph['nodes'][0]['config']['channel_id'], $key);
		pg_assert_same(12, $graph['nodes'][1]['config']['channel_id'], $key);
		pg_assert_same(array(), ws_flow_graph_check($graph), $key . ' has no problem');

		$start = ws_board_rule_start($graph, 12, 0);
		pg_assert_same($key, ws_board_rule_key_of($start), $key . ' read back');
	}

	$approval = ws_board_rule_graph('waiting_approval', 12, array(4, 9));
	pg_assert_same(array(4, 9), $approval['nodes'][1]['config']['approvers']);
	pg_assert_same('waiting', $approval['nodes'][0]['config']['status']);
	pg_assert_same('Next', ws_board_rule_graph('done_next_task', 12, 'Next')['nodes'][1]['config']['title']);
}

// A task event narrowed to this channel, or to none in a flow at home here.
function test_workspace_channel_board_rules_start()
{
	pg_assert_same(array('id' => 'start', 'event' => 'workspace.task.overdue', 'status' => ''),
		ws_board_rule_start(ws_board_rules_test_event('workspace.task.overdue'), 12, 3));
	pg_assert_same(null, ws_board_rule_start(ws_board_rules_test_event('workspace.task.overdue', '', 13), 12, 12), 'another channel');
	pg_assert_same('start', ws_board_rule_start(ws_board_rules_test_event('workspace.task.completed', '', 0), 12, 12)['id'], 'any channel, home here');
	pg_assert_same(null, ws_board_rule_start(ws_board_rules_test_event('workspace.task.completed', '', 0), 12, 3), 'any channel, home elsewhere');
	pg_assert_same(null, ws_board_rule_start(ws_board_rules_test_event('order.created'), 12, 12), 'not a task event');
	pg_assert_same('', ws_board_rule_start(ws_board_rules_test_event('workspace.task.completed', 'waiting'), 12, 12)['status'], 'status only on a move');

	// A start box named as a rule whose event was changed is no longer it.
	$changed = ws_board_rule_start(ws_board_rules_test_event('workspace.task.overdue', '', 12, 'board_done_post'), 12, 12);
	pg_assert_same('', ws_board_rule_key_of($changed));
	pg_assert_same('', ws_board_rule_key_of(array('id' => 'board_nothing', 'event' => 'workspace.task.completed', 'status' => '')));

	pg_assert_same('done', ws_board_rule_column(array('event' => 'workspace.task.completed')));
	pg_assert_same('waiting', ws_board_rule_column(array('event' => 'workspace.task.status_changed', 'status' => 'waiting')));
	pg_assert_same('', ws_board_rule_column(array('event' => 'workspace.task.overdue')));
}

function test_workspace_channel_board_rules_list()
{
	$statuses = ws_task_statuses();
	$flows = array(
		// A paused copy of the done rule and the live one: the live one is it.
		ws_board_rules_test_flow(1, ws_board_rule_graph('done_post', 12), array('status' => 'paused', 'updated_at' => 5000)),
		ws_board_rules_test_flow(2, ws_board_rule_graph('done_post', 12)),
		// Somebody else's flow on a move to In progress.
		ws_board_rules_test_flow(3, ws_board_rules_test_event('workspace.task.status_changed', 'doing'), array('owner_id' => 8, 'access' => 'run')),
		// Not this board's.
		ws_board_rules_test_flow(4, ws_board_rules_test_event('workspace.task.overdue', '', 13)),
		ws_board_rules_test_flow(5, ws_board_rules_test_event('order.created')),
		ws_board_rules_test_flow(6, ws_board_rule_graph('overdue_remind', 12), array('status' => 'binned')),
	);

	$rules = ws_board_rules(12, $flows, $statuses);

	pg_assert_same(array('done_post', 'overdue_remind', 'waiting_approval', 'done_next_task'), array_column($rules['ready'], 'key'));
	pg_assert_same(2, $rules['ready'][0]['flow']['flow_id'], 'the live one');
	pg_assert_true($rules['ready'][0]['on']);
	pg_assert_same(null, $rules['ready'][1]['flow'], 'a binned flow is not the rule');
	pg_assert_same('person', $rules['ready'][2]['asks'], 'asks while not made');
	pg_assert_same('waiting', $rules['ready'][2]['column']);

	// The live ones first, then the latest changed.
	pg_assert_same(array(3, 1), array_column($rules['flows'], 'flow_id'), 'the other flow and the paused copy');
	pg_assert_false($rules['flows'][0]['edit']);
	pg_assert_same('When a task comes to “In progress”', $rules['flows'][0]['when']);
	pg_assert_same('doing', $rules['flows'][0]['column']);
	pg_assert_same(2, $rules['on']);

	// Made, a rule no longer asks.
	$made = ws_board_rules(12, array(ws_board_rules_test_flow(9, ws_board_rule_graph('done_next_task', 12, 'Next'), array('status' => 'paused'))), $statuses);
	pg_assert_same('', $made['ready'][3]['asks']);
	pg_assert_false($made['ready'][3]['on']);
	pg_assert_same(0, $made['on']);
}

// A column's rule lines: the rules that are on and tied to a status; an
// overdue rule has no column, a paused flow no line.
function test_workspace_channel_board_rules_lines()
{
	$rules = ws_board_rules(12, array(
		ws_board_rules_test_flow(1, ws_board_rule_graph('done_post', 12)),
		ws_board_rules_test_flow(2, ws_board_rule_graph('overdue_remind', 12)),
		ws_board_rules_test_flow(3, ws_board_rules_test_event('workspace.task.status_changed', 'doing'), array('name' => 'Tell the lead')),
		ws_board_rules_test_flow(4, ws_board_rules_test_event('workspace.task.status_changed', 'waiting'), array('status' => 'paused')),
	), ws_task_statuses());

	pg_assert_same(array(
		'done'  => array('A task that comes here: it is written in the channel'),
		'doing' => array('A task that comes here: “Tell the lead” runs'),
	), ws_board_rule_lines($rules));
	pg_assert_same(array(), ws_board_rule_lines(ws_board_rules(12, array(), ws_task_statuses())));
	pg_assert_same('A task that comes here: its approval is asked', ws_board_rule_line('waiting_approval', ''));
}

function test_workspace_channel_board_rules_week_start()
{
	pg_assert_same('2026-11-23', ws_board_rule_week_start('2026-11-25'), 'a Wednesday');
	pg_assert_same('2026-11-23', ws_board_rule_week_start('2026-11-23'), 'a Monday');
	pg_assert_same('2026-11-23', ws_board_rule_week_start('2026-11-29'), 'a Sunday');
}

// How often each rule ran this week: a made rule and another flow by their
// flow; a ready rule not made yet never ran.
function test_workspace_channel_board_rules_ran()
{
	$rules = ws_board_rules(12, array(
		ws_board_rules_test_flow(1, ws_board_rule_graph('done_post', 12)),
		ws_board_rules_test_flow(3, ws_board_rules_test_event('workspace.task.completed')),
	), ws_task_statuses());

	pg_assert_same(array(1, 3), ws_board_rule_flow_ids($rules));

	$ran = ws_board_rules_ran($rules, array(1 => 4, 7 => 9));

	pg_assert_same(array(4, 0, 0, 0), array_column($ran['ready'], 'ran'));
	pg_assert_same(array(0), array_column($ran['flows'], 'ran'));
}

// The drawer's last runs: newest first, cut to the limit; a task named only
// when it is the channel's; a run with no sentence says its status.
function test_workspace_channel_board_rules_run_lines()
{
	$runs = array(
		array('id' => 30, 'flow_id' => 1, 'status' => 'done', 'started_at' => 900, 'subject_type' => 'task', 'subject_id' => 5),
		array('id' => 29, 'flow_id' => 2, 'status' => 'running', 'started_at' => 800, 'subject_type' => 'task', 'subject_id' => 6),
		array('id' => 28, 'flow_id' => 1, 'status' => 'failed', 'started_at' => 700, 'subject_type' => 'contact', 'subject_id' => 5),
	);
	$lines = ws_board_rule_run_lines($runs, array(30 => 'Written in #design', 28 => 'The channel is gone'), array(5 => 'Campaign calendar'), array(1 => 'Board: done', 2 => 'Other'), 2);

	pg_assert_same(2, count($lines));
	pg_assert_same(array('id' => 30, 'flow_id' => 1, 'flow' => 'Board: done', 'at' => 900, 'task' => 'Campaign calendar', 'text' => 'Written in #design', 'status' => 'done'), $lines[0]);
	pg_assert_same('', $lines[1]['task'], 'not a task of this channel');
	pg_assert_same('Running', $lines[1]['text']);
	pg_assert_same('', ws_board_rule_run_lines(array($runs[2]), array(), array(5 => 'x'), array())[0]['task'], 'a contact is no task');
}

// The board's script: "Automations" only where the board's answer allows
// it, the rules read through their panel actions, no rule line on a
// workflow table's board, every word from the board's strings.
function test_workspace_channel_board_rules_script()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_board_channel.js');

	pg_assert_true(strpos($js, "automations(self, data.automations)") !== false, 'the gate comes with the board');
	pg_assert_true(strpos($js, "node.hidden = true;") !== false, 'hidden until allowed');
	pg_assert_true(strpos($js, "'ws_channel_board_rules'") !== false && strpos($js, "'ws_channel_board_rule_set'") !== false);
	pg_assert_true(strpos($js, "if (self.source || !self.rules") !== false, 'no rule line on a table board');

	preg_match_all("/t\\('(cb_auto[a-z_]*)'/", $js, $found);
	$strings = ws_channel_board_js_strings();

	pg_assert_true(count($found[1]) > 10);

	foreach (array_unique($found[1]) as $key) {
		pg_assert_true(isset($strings[$key]), 'a string for ' . $key);
	}
}
