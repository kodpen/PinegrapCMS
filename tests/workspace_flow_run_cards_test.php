<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for flows started from a channel's composer (flow_run_cards.php):
 * which flows the composer lists, the card a run leaves in the channel, the
 * channel the panel's run start passes on, and the run flag's shared script
 * (assets/js/workspace_flow_flag.js) and card timing under Node.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

// A flow the composer lists: live, published, started by hand, run or edit
// access, and an owner who may write in the channel; anything else is not.
function test_workspace_flow_run_cards_runnable_here()
{
	$row = array('status' => 'live', 'version_id' => 4, 'access' => 'run');

	pg_assert_true(ws_flow_runnable_here($row, 'manual', true), 'a live flow started by hand, run access');
	pg_assert_true(ws_flow_runnable_here(array('access' => 'edit') + $row, 'manual', true), 'edit access');
	pg_assert_false(ws_flow_runnable_here(array('access' => 'view') + $row, 'manual', true), 'view access');
	pg_assert_false(ws_flow_runnable_here(array('access' => '') + $row, 'manual', true), 'no access');
	pg_assert_false(ws_flow_runnable_here(array('status' => 'paused') + $row, 'manual', true), 'paused');
	pg_assert_false(ws_flow_runnable_here(array('status' => 'draft') + $row, 'manual', true), 'draft');
	pg_assert_false(ws_flow_runnable_here(array('version_id' => 0) + $row, 'manual', true), 'never published');

	foreach (array('time', 'event', 'join', '') as $start) {
		pg_assert_false(ws_flow_runnable_here($row, $start, true), 'started ' . ($start === '' ? 'by nothing' : 'by ' . $start));
	}

	pg_assert_false(ws_flow_runnable_here($row, 'manual', false), 'an owner who may not write in the channel');
}

// The card: the run without its boxes or graph, its flow, and Open the run
// only for a reader who reaches the flow.
function test_workspace_flow_run_cards_payload()
{
	$run = array('id' => 9, 'flow_id' => 3, 'status' => 'running', 'can_cancel' => true, 'result' => 'Waiting for Ada.',
		'started_at' => '2026-10-29T10:00:00Z', 'finished_at' => null, 'steps' => null, 'graph' => null);
	$card = ws_flow_run_card_payload($run, 'Daily report', 'run');

	pg_assert_same(array('run', 'flow_id', 'flow_name', 'can_open'), array_keys($card));
	pg_assert_same(3, $card['flow_id']);
	pg_assert_same('Daily report', $card['flow_name']);
	pg_assert_true($card['can_open'], 'a reader who runs the flow opens the run');
	pg_assert_false(array_key_exists('steps', $card['run']) || array_key_exists('graph', $card['run']), 'no boxes on the card');
	pg_assert_same('Waiting for Ada.', $card['run']['result']);
	pg_assert_true($card['run']['can_cancel']);
	pg_assert_false(ws_flow_run_card_payload($run, 'Daily report', '')['can_open'], 'a reader by the channel alone sees the flag only');
}

// The flow's name in the system line makes no tag, mark or link.
function test_workspace_flow_run_cards_name_is_plain()
{
	pg_assert_same('Daily report', ws_flow_run_card_name(' Daily report '));
	pg_assert_same('Ask @user:5 order:7 bold link(x)', ws_flow_run_card_name('Ask <@user:5> <#order:7> **bold** [link](x)'));
}

// The panel's run start passes the composer's channel on, and nothing for
// the canvas's start.
function test_workspace_flow_run_cards_run_fields()
{
	pg_assert_same(array('token' => 'canvas.ab'), ws_action_flow_run_fields(array('start_token' => 'canvas.ab')));
	pg_assert_same(array('token' => 'channel.ab', 'channel_id' => 12), ws_action_flow_run_fields(array('start_token' => 'channel.ab', 'channel_id' => '12')));
	pg_assert_same(array(), ws_action_flow_run_fields(array('channel_id' => 0)));
	pg_assert_same(array(), ws_action_flow_run_fields(array('channel_id' => 'x')));
}

// The bundle carries the flag before workspace.js, and the canvas leans on
// it instead of a copy of its own.
function test_workspace_flow_run_cards_flag_is_shared()
{
	$bundle = pg_js_bundles()['assets/js/workspace.min.js'];
	$flag = array_search('assets/js/workspace_flow_flag.js', $bundle, true);

	pg_assert_true($flag !== false, 'the flag is in the workspace bundle');
	pg_assert_true($flag < array_search('assets/js/workspace.js', $bundle, true), 'before workspace.js');

	$canvas = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	pg_assert_true(strpos($canvas, 'window.PGWsFlowFlag.runFlag(run, options)') !== false, 'the canvas draws the shared flag');
	pg_assert_false(strpos($canvas, 'FLAG_STATES') !== false, 'no second copy of the flag states');
}

// Runs a script under Node and answers what it printed as JSON; null when
// Node is not installed.
function workspace_flow_run_cards_test_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgwsflag');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// The shared flag's duration and fold, as the canvas had them.
function test_workspace_flow_run_cards_flag_helpers_node()
{
	$script = "'use strict';\nvar window = {};\n"
		. (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow_flag.js')
		. "\nvar F = window.PGWsFlowFlag;\n"
		. "function tr(key, a, b) { return key + ':' + a + (b === undefined ? '' : ',' + b); }\n"
		. "function run(secs) { return { started_at: '2026-10-29T10:00:00Z', finished_at: new Date(Date.parse('2026-10-29T10:00:00Z') + secs * 1000).toISOString() }; }\n"
		. "console.log(JSON.stringify([F.duration(run(45), tr), F.duration(run(720), tr), F.duration(run(7500), tr), F.duration(run(273600), tr),"
		. " F.duration({ started_at: '' }, tr), F.folds('short'), F.folds(new Array(62).join('x')), F.folds(new Array(61).join('x')), F.folds('a\\nb'), typeof F.runFlag]));\n";
	$answer = workspace_flow_run_cards_test_node($script);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('fl_flag_sec:45', 'fl_flag_min:12', 'fl_flag_hour:2,5', 'fl_flag_day:3,4', '', false, true, false, true, 'function'), $answer);
}

// The card reads an open run again every 4 s for a minute, every 15 s for
// ten more, then once a minute; only running and waiting are open.
function test_workspace_flow_run_cards_follow_timing_node()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$script = "'use strict';\n";

	foreach (array('flowRunOpen', 'flowRunWait') as $name) {
		$start = strpos($source, "\n    function " . $name . '(');
		pg_assert_true($start !== false, $name . ' is found');
		$end = strpos($source, "\n    }\n", (int) $start);
		$script .= substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
	}

	$script .= "console.log(JSON.stringify([flowRunWait(0), flowRunWait(14), flowRunWait(15), flowRunWait(54), flowRunWait(55),"
		. " flowRunOpen('running'), flowRunOpen('waiting'), flowRunOpen('done'), flowRunOpen('failed'), flowRunOpen('cancelled')]));\n";
	$answer = workspace_flow_run_cards_test_node($script);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(4000, 4000, 15000, 15000, 60000, true, true, false, false, false), $answer);
}
