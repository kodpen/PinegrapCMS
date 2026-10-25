<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flows screen (workspace_flows.php, assets/js/workspace_flow.js):
 * its panel actions in api.php's table with the Workspace's gate, the texts
 * the canvas script reads (every key it uses handed over by
 * ws_flow_js_strings(), every English source in tr.json, which
 * tools/check_lang.php does not read for ws_js_template()), the graph and
 * list entries the screen is answered with, and the run view: its actions go
 * through the runner's own checks and writes and answer with the API's run
 * presenters, which the panel can load, and the states it draws are the
 * runner's. The shares of a flow go through the share writers' own checks
 * and answer with the API's share presenter; every fl_ text handed over is
 * read by a script. What the run view and the runs list show when a read
 * fails is run under Node, with the script's own functions (skipped without
 * Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: the panel table, the templates' assign rules
// the store reads, the store, the runner, the API's run presenters (behind
// the gate the API's own panel screens open), and ws_js_template() from the
// screen helpers.
if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

// The actions the canvas sends.
function ws_flows_canvas_test_actions()
{
	return array('ws_flow_list', 'ws_flow_get', 'ws_flow_create', 'ws_flow_save', 'ws_flow_publish', 'ws_flow_pause', 'ws_flow_bin',
		'ws_flow_run_start', 'ws_flow_runs_list', 'ws_flow_run_get', 'ws_flow_run_cancel',
		'ws_flow_shares', 'ws_flow_share_set', 'ws_flow_share_remove');
}

// Every action is a row of the Workspace family: its handler checks the
// session and the form token, the module decides who may do what.
function test_workspace_flows_canvas_actions_are_listed_with_the_workspace_gate()
{
	$actions = pg_panel_actions();

	foreach (ws_flows_canvas_test_actions() as $action) {
		pg_assert_true(isset($actions[$action]), $action . ' listed');

		if (!isset($actions[$action])) {
			continue;
		}

		pg_assert_same('workspace.php', $actions[$action]['file'], $action . ' file');
		pg_assert_same('pg_panel_ws', $actions[$action]['handler'], $action . ' handler');
		pg_assert_true($actions[$action]['exempt'] && $actions[$action]['token'] && $actions[$action]['write'], $action . ' exempt, token, write');
		pg_assert_same('exempt', pg_panel_gate($action), $action . ' gate');
	}
}

// Each action is answered by a case of ws_handle_action().
function test_workspace_flows_canvas_actions_have_a_case()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	foreach (ws_flows_canvas_test_actions() as $action) {
		pg_assert_contains("case '" . $action . "':", $source, $action);
	}
}

// The body of ws_flow_js_strings(), as written.
function ws_flows_canvas_test_strings_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
	$start = strpos($source, 'function ws_flow_js_strings()');
	$end = strpos($source, "\n}\n", (int) $start);

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

function test_workspace_flows_canvas_texts_are_in_tr_json()
{
	$body = ws_flows_canvas_test_strings_source();
	$keys = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr.json'), true);

	pg_assert_true($body !== '', 'ws_flow_js_strings() found');
	pg_assert_true(is_array($keys), 'tr.json read');

	preg_match_all("/(?:lang|ws_js_template)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $body, $matches);

	pg_assert_true(count($matches[1]) > 50, 'texts found');

	foreach ($matches[1] as $text) {
		$text = str_replace("\\'", "'", $text);
		pg_assert_true(is_array($keys) && array_key_exists($text, $keys), 'in tr.json: ' . $text);
	}
}

// Every fl_ key the scripts read is handed over, the keyboard rows built
// from their names included.
function test_workspace_flows_canvas_script_keys_are_handed_over()
{
	$strings = ws_flow_js_strings();
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	preg_match_all("/'(fl_[a-z_]+)'/", $script . $channels, $matches);

	$used = array_diff(array_unique($matches[1]), array('fl_key_'));

	foreach (array('tab', 'arrows', 'connect', 'delete', 'enter', 'zoom', 'pan', 'escape') as $key) {
		pg_assert_contains("'" . $key . "'", $script, 'keyboard row ' . $key);
		$used[] = 'fl_key_' . $key;
	}

	foreach ($used as $key) {
		pg_assert_true(isset($strings[$key]), 'handed over: ' . $key);
	}

	pg_assert_contains('{1}', $strings['fl_status_live'], 'the version number is a hole the script fills');
}

// A box's settings go out as an object, an end's empty ones too, so the
// script never meets a list where it reads keys.
function test_workspace_flows_canvas_graph_keeps_settings_objects()
{
	$graph = ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'manual_1', 'kind' => 'manual', 'x' => 0, 'y' => 0),
		array('id' => 'end_1', 'kind' => 'end', 'x' => 288, 'y' => 0),
	), 'edges' => array(array('from' => 'manual_1', 'to' => 'end_1'))));

	$json = json_encode(ws_flow_graph_present($graph));

	pg_assert_contains('"id":"end_1","kind":"end","label":"","x":288,"y":0,"config":{}', $json, 'end settings an object');
	pg_assert_contains('"config":{"subject":""}', $json, 'start settings');
	pg_assert_contains('"on":"next"', $json, 'the arrow leaves the start on next');
	pg_assert_contains('"notes":[]', $json, 'notes a list');
}

function test_workspace_flows_canvas_brief_is_typed()
{
	$flow = array('id' => '12', 'name' => 'Onboarding', 'status' => 'live', 'access' => 'run', 'owner_id' => '4', 'channel_id' => '9', 'draft_rev' => '3', 'version_id' => '30');
	$brief = ws_flow_brief(array('id' => 7), $flow, 2);

	pg_assert_same(array('id' => 12, 'name' => 'Onboarding', 'status' => 'live', 'access' => 'run', 'mine' => false, 'channel_id' => 9, 'draft_rev' => 3, 'version' => 2), $brief);
	pg_assert_true(ws_flow_brief(array('id' => 4), $flow, 0)['mine'], 'the owner\'s own');
}

// A save always names the draft it was made on: without draft_rev,
// ws_flow_save_check() would skip its "changed meanwhile" check and a stale
// screen would write over a draft saved elsewhere. Only the three fields a
// save may change are passed on.
function test_workspace_flows_canvas_save_fields_always_carry_draft_rev()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_same(array('draft_rev' => 0), ws_action_flow_fields(array()), 'nothing sent: draft_rev 0');
	pg_assert_same(array('draft_rev' => 0), ws_action_flow_fields(array('action' => 'ws_flow_save', 'token' => 'x', 'flow_id' => 7, 'status' => 'live')), 'other keys dropped');

	$graph = array('v' => 1, 'nodes' => array(), 'edges' => array(), 'notes' => array());

	pg_assert_same(
		array('draft_rev' => 5, 'name' => 'Onboarding', 'channel_id' => 3, 'graph' => $graph),
		ws_action_flow_fields(array('flow_id' => 7, 'draft_rev' => '5', 'name' => 'Onboarding', 'channel_id' => 3, 'graph' => $graph)),
		'name, channel and graph kept, draft_rev an integer'
	);
}

// A 409 from the store (a draft saved meanwhile elsewhere) reaches the screen
// as field changed_meanwhile, which its conflict banner listens for; any other
// refusal keeps the store's own field.
function test_workspace_flows_canvas_refusal_tells_a_conflict_apart()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	$conflict = ws_action_flow_refused(array('ok' => false, 'error' => 'Changed meanwhile.', 'status' => 409, 'field' => 'draft_rev'));
	$refused = ws_action_flow_refused(array('ok' => false, 'error' => 'Too large.', 'status' => 422, 'field' => 'graph'));

	pg_assert_same(array('status' => 'error', 'message' => 'Changed meanwhile.', 'field' => 'changed_meanwhile'), $conflict, '409');
	pg_assert_same(array('status' => 'error', 'message' => 'Too large.', 'field' => 'graph'), $refused, '422');
}

// A record's drawer draws no channel sidebar: the flows config tells it
// nothing, so the order, product and contact screens read nothing for it.
function test_workspace_flows_canvas_record_drawer_gets_no_flows_config()
{
	pg_assert_same(array('nav' => false), ws_flow_js_config(array('id' => 4, 'member' => true, 'role' => 3), 'record'));
}

// The body of one function of a file, as written.
function ws_flows_canvas_test_function_source($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');
	$end = ($start !== false) ? strpos($source, "\n}\n", $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The source of one case of ws_handle_action(), up to the next case.
function ws_flows_canvas_test_case_source($action)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($source, "case '" . $action . "':");
	$end = ($start !== false) ? strpos($source, "\n        case '", $start + 10) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The run actions decide nothing themselves: the runner's check-and-write
// functions refuse what they refuse (the API's run routes call the same),
// and the answer is the API's presenter.
function test_workspace_flows_canvas_run_actions_use_the_runner()
{
	$flow = "ws_flow_for(\$viewer, (int) (\$request['flow_id'] ?? 0), 'view')";
	$expected = array(
		'ws_flow_run_start'  => array($flow, 'ws_flow_run_start($viewer, $flow, ws_action_flow_run_fields($request))', 'ws_action_flow_run_answer('),
		'ws_flow_runs_list'  => array($flow, 'ws_flow_runs_list($viewer, $flow,', 'ws_action_flow_run_present('),
		'ws_flow_run_get'    => array('ws_action_flow_run_answer(', 'ws_scheduled_due()'),
		'ws_flow_run_cancel' => array('ws_flow_run_for($viewer,', 'ws_flow_run_cancel($viewer, $run)', 'ws_action_flow_run_answer('),
	);

	foreach ($expected as $action => $needles) {
		$case = ws_flows_canvas_test_case_source($action);

		pg_assert_true($case !== '', $action . ' case found');

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $case, $action . ': ' . $needle);
		}

		pg_assert_false(strpos($case, 'ws_flow_access(') !== false, $action . ' asks no access of its own');
	}

	pg_assert_contains('ws_api_flow_run_present(', ws_flows_canvas_test_function_source('includes/workspace/actions.php', 'ws_action_flow_run_present'), 'the API presenter answers');
	pg_assert_contains('ws_flow_run_for(', ws_flows_canvas_test_function_source('includes/workspace/actions.php', 'ws_action_flow_run_answer'), 'a run is read again through its access');
}

// A start passes on only the client's token, sent as start_token, and as it
// came: a token of the wrong shape meets ws_flow_run_start_check()'s refusal
// rather than being dropped, which would start a run without one. The
// request's `token` is the CSRF token api.php checks and never becomes the
// run's (64 hex characters, over the runner's 56, or one start key for every
// start of a session).
function test_workspace_flows_canvas_run_start_fields_carry_only_the_token()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	$csrf = str_repeat('ab', 32);

	pg_assert_same(array(), ws_action_flow_run_fields(array('flow_id' => 5, 'action' => 'ws_flow_run_start')), 'no token sent');
	pg_assert_same(array(), ws_action_flow_run_fields(array('flow_id' => 5, 'action' => 'ws_flow_run_start', 'token' => $csrf)), 'the CSRF token is not a run token');
	pg_assert_same(array('token' => 'canvas.0a1b'), ws_action_flow_run_fields(array('flow_id' => 5, 'token' => $csrf, 'start_token' => 'canvas.0a1b', 'status' => 'done')), 'the start token alone');
	pg_assert_same(array('token' => array('x')), ws_action_flow_run_fields(array('start_token' => array('x'))), 'a wrong shape passed on as it came');
	pg_assert_false(ws_flow_start_token_valid(ws_action_flow_run_fields(array('start_token' => array('x')))['token']), 'which the runner refuses');
	pg_assert_true(ws_flow_start_token_valid('canvas.' . str_repeat('0f', 12)), 'the canvas token shape is valid');
	pg_assert_false(ws_flow_start_token_valid($csrf), 'a CSRF token is not one');
}

// The canvas sends its start token under its own name, never as `token`,
// which workspace.js's api() fills with the CSRF token and a data key of the
// same name would overwrite.
function test_workspace_flows_canvas_run_start_sends_start_token()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$start = strpos($script, "K.api('ws_flow_run_start', {");
	$call = ($start !== false) ? substr($script, $start, (int) strpos($script, '}', $start) - $start) : '';

	pg_assert_true($call !== '', 'the start request found');
	pg_assert_contains('start_token:', $call, 'the start token under its own name');
	pg_assert_false((bool) preg_match('/[{,]\s*token\s*:/', $call), 'no data key named token');
}

// The times of a run as the API writes them (api_time()), from a function the
// panel has: 0 is no time.
function test_workspace_flows_canvas_run_times_are_iso_utc()
{
	pg_assert_same(null, ws_flow_time(0), 'none');
	pg_assert_same(null, ws_flow_time(''), 'empty');
	pg_assert_same('2026-10-14T17:46:40Z', ws_flow_time(1792000000), 'a moment');
	pg_assert_same('2026-10-14T17:46:40Z', ws_flow_time('1792000000'), 'as the row keeps it');

	foreach (array('ws_api_flow_run_present', 'ws_api_flow_run_step_present') as $presenter) {
		$body = ws_flows_canvas_test_function_source('includes/workspace/api_flows.php', $presenter);

		pg_assert_true($body !== '', $presenter . ' found');
		pg_assert_false(strpos($body, 'api_time(') !== false, $presenter . ' needs nothing only the API loads');
	}
}

// A run and its boxes as the canvas is answered: the API's shape, field for
// field, built without the database.
function test_workspace_flows_canvas_run_answer_is_the_api_shape()
{
	$graph = ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'manual_1', 'kind' => 'manual', 'label' => 'Return asked', 'x' => 0, 'y' => 0),
		array('id' => 'task_1', 'kind' => 'task', 'label' => 'Take it back', 'x' => 288, 'y' => 0, 'config' => array('title' => 'Take it back')),
		array('id' => 'end_1', 'kind' => 'end', 'x' => 576, 'y' => 0),
	), 'edges' => array(array('from' => 'manual_1', 'to' => 'task_1'), array('from' => 'task_1', 'to' => 'end_1', 'on' => 'done'))));
	$run = array('id' => '12', 'flow_id' => '7', 'version_id' => '30', 'status' => 'waiting', 'access' => 'read', 'started_by' => '4', 'actor_id' => '1',
		'context' => json_encode(array('trigger' => 'manual', 'node' => 'manual_1', 'channel_id' => 11, 'flow_name' => 'Returns')), 'boxes_run' => '1',
		'started_at' => '1792000000', 'updated_at' => '1792000060', 'finished_at' => '0');
	$steps = array(
		array('node_id' => 'manual_1', 'status' => 'done', 'outcome' => 'next', 'effect' => '', 'attempt' => '1', 'ready_at' => '1792000000', 'claimed_at' => '1792000000', 'finished_at' => '1792000000', 'result' => ws_flow_result_pack('Started by Ayşe.')),
		array('node_id' => 'task_1', 'status' => 'waiting', 'outcome' => '', 'effect' => 'task:41', 'attempt' => '1', 'ready_at' => '1792000000', 'claimed_at' => '1792000001', 'finished_at' => '0', 'result' => ws_flow_result_pack('task T-41 opened', array('task_id' => 41))),
	);

	$full = ws_api_flow_run_present(array('id' => 4), $run, 2, $steps, $graph);
	$brief = ws_api_flow_run_present(array('id' => 9), $run, 2);

	pg_assert_same(array_keys(ws_api_flow_run_schema()), array_keys($full), 'run fields');
	pg_assert_same(array_keys(ws_api_flow_run_step_schema()), array_keys($full['steps'][1]), 'box fields');
	pg_assert_same(array(12, 'waiting', 11, '2026-10-14T17:46:40Z', null), array($full['id'], $full['status'], $full['channel_id'], $full['started_at'], $full['finished_at']), 'typed, times in UTC');
	pg_assert_true($full['can_cancel'], 'its starter may cancel it');
	pg_assert_false($brief['can_cancel'], 'another reader may not');
	pg_assert_same(null, $brief['steps'], 'a listing carries no boxes');
	pg_assert_same(array('task_1', 'waiting', 41, 'task T-41 opened', '2026-10-14T17:46:41Z'), array($full['steps'][1]['node_id'], $full['steps'][1]['status'], $full['steps'][1]['task_id'], $full['steps'][1]['result'], $full['steps'][1]['started_at']), 'the waiting box and its task');
	pg_assert_contains('"config":{}', json_encode($full['graph']), 'the graph as the canvas reads it');
}

// The states the canvas draws for a run and its boxes are the runner's, and
// a box the run has not reached yet has its own word.
function test_workspace_flows_canvas_run_states_are_the_runners()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$read = function ($name) use ($script) {
		$start = strpos($script, 'var ' . $name . ' = {');
		$end = ($start !== false) ? strpos($script, '};', $start) : false;

		preg_match_all('/^\s+([a-z]+): \[/m', (($start !== false) && ($end !== false)) ? substr($script, $start, $end - $start) : '', $found);

		return $found[1];
	};

	pg_assert_same(array_keys(ws_flow_run_statuses()), $read('RUN_STATES'), 'run states');
	pg_assert_same(array_merge(array_keys(ws_flow_step_statuses()), array('none')), $read('STEP_STATES'), 'box states, and none');
}

// Every fl_ text ws_flow_js_strings() hands over is read by a script: by its
// literal name, or as a keyboard row built from its name.
function test_workspace_flows_canvas_every_text_is_read()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	preg_match_all("/'(fl_[a-z_]+)'\\s*=>/", ws_flows_canvas_test_strings_source(), $handed);

	pg_assert_true(count($handed[1]) > 50, 'keys found');
	pg_assert_same(array_keys(ws_flow_js_strings()), $handed[1], 'every key written once, in order');

	foreach ($handed[1] as $key) {
		$row = (strpos($key, 'fl_key_') === 0) && (strpos($script, "'" . substr($key, 7) . "'") !== false);

		pg_assert_true($row || (strpos($script . $channels, "'" . $key . "'") !== false), 'read by a script: ' . $key);
	}

	foreach (array('fl_share', 'fl_shared_with', 'fl_level_view', 'fl_level_run', 'fl_level_edit', 'fl_list_shared') as $key) {
		pg_assert_true(isset(ws_flow_js_strings()[$key]), 'handed over: ' . $key);
	}
}

// The share actions decide nothing themselves: the share writers' checks
// refuse what they refuse (the API's share routes call the same), and the
// list is the store's, in the API's shape.
function test_workspace_flows_canvas_share_actions_use_the_share_writers()
{
	$flow = "ws_flow_for(\$viewer, (int) (\$request['flow_id'] ?? 0), 'view')";
	$expected = array(
		'ws_flow_shares'       => array($flow, 'ws_action_flow_shares_answer('),
		'ws_flow_share_set'    => array($flow, 'ws_flow_share_set($viewer, $flow, ws_action_flow_share_fields($request))', 'ws_action_flow_shares_answer('),
		'ws_flow_share_remove' => array($flow, "ws_flow_share_remove(\$viewer, \$flow, (int) (\$request['share_id'] ?? 0))", 'ws_action_flow_shares_answer('),
	);

	foreach ($expected as $action => $needles) {
		$case = ws_flows_canvas_test_case_source($action);

		pg_assert_true($case !== '', $action . ' case found');

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $case, $action . ': ' . $needle);
		}

		pg_assert_false(strpos($case, 'ws_flow_access(') !== false, $action . ' asks no access of its own');
	}

	$answer = ws_flows_canvas_test_function_source('includes/workspace/actions.php', 'ws_action_flow_shares_answer');

	pg_assert_contains('ws_flow_shares_list($viewer, $flow)', $answer, 'the store lists them');
	pg_assert_contains('ws_api_flow_share_present', $answer, 'in the API\'s shape');
	pg_assert_contains('ws_flow_share_manager($viewer, $flow)', $answer, 'with whether the person manages them');
}

// Only what a share may carry is passed on: the kind, the id and the level.
function test_workspace_flows_canvas_share_fields()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_same(array('target_type' => '', 'target_id' => 0, 'level' => 'view'), ws_action_flow_share_fields(array()), 'nothing sent');
	pg_assert_same(
		array('target_type' => 'user', 'target_id' => 12, 'level' => 'edit'),
		ws_action_flow_share_fields(array('flow_id' => 4, 'token' => 'x', 'target_type' => 'user', 'target_id' => '12', 'level' => 'edit', 'owner_id' => 9)),
		'the three fields, the id an integer'
	);
}

// The canvas sends the share actions and draws a share's level with the
// level texts, and shows the dialog's controls to whoever manages the shares.
function test_workspace_flows_canvas_share_dialog_is_drawn_by_the_script()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	// The list is read with ws_flow_shares; every change goes through
	// shareRequest(), which sends it and draws the list from the answer.
	foreach (array("K.api('ws_flow_shares'", "shareRequest('ws_flow_share_set'", "shareRequest('ws_flow_share_remove'") as $call) {
		pg_assert_contains($call, $script);
	}

	$start = strpos($script, 'function shareRequest(');
	$request = ($start !== false) ? substr($script, $start, (int) strpos($script, "\n    }\n", $start) - $start) : '';

	pg_assert_contains('K.api(action, data)', $request, 'shareRequest() sends the action');
	pg_assert_contains('share.data = answer', $request, 'and keeps the list it answers');

	pg_assert_contains('data.manage', $script, 'the controls follow the answer');
	pg_assert_contains('flow.manage', $script, 'the menu item follows the flow');
}

// One top-level function of workspace_flow.js, as written: from its line to
// the closing brace at its own indent ('' when it is not there).
function ws_flows_canvas_test_script_function($script, $name)
{
	if (!preg_match('/^( *)function ' . preg_quote($name, '/') . '\(/m', $script, $found, PREG_OFFSET_CAPTURE)) {
		return '';
	}

	$close = "\n" . $found[1][0] . "}\n";
	$end = strpos($script, $close, $found[0][1]);

	return ($end !== false) ? substr($script, $found[0][1], $end - $found[0][1] + strlen($close)) : '';
}

// Stand-ins for what the canvas functions read of the page and of the
// Workspace kit: elements that keep their children, text and listeners,
// K.api answering from a queue, timers kept to be fired by hand, t() that
// gives the key with its values.
function ws_flows_canvas_test_stage()
{
	return <<<'JS'
var calls = [], answers = [], failed = [], announced = [], timers = [];
function FakeNode(tag, className, text) {
    var self = this;
    this.tag = tag;
    this.className = className || '';
    this.textContent = ((text === undefined) || (text === null)) ? '' : String(text);
    this.children = [];
    this.attrs = {};
    this.listeners = {};
    this.disabled = false;
    this.classList = {
        add: function (name) { self.mark(name, true); },
        remove: function (name) { self.mark(name, false); },
        toggle: function (name, on) { self.mark(name, (on === undefined) ? !self.classList.contains(name) : !!on); },
        contains: function (name) { return (' ' + self.className + ' ').indexOf(' ' + name + ' ') !== -1; }
    };
}
FakeNode.prototype.mark = function (name, on) {
    var list = this.className.split(/\s+/).filter(function (item) { return item && (item !== name); });
    if (on) { list.push(name); }
    this.className = list.join(' ');
};
FakeNode.prototype.appendChild = function (child) { this.children.push(child); return child; };
FakeNode.prototype.setAttribute = function (name, value) { this.attrs[name] = String(value); };
FakeNode.prototype.getAttribute = function (name) { return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null; };
FakeNode.prototype.addEventListener = function (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); };
FakeNode.prototype.click = function () { (this.listeners.click || []).forEach(function (fn) { fn({ button: 0 }); }); };
FakeNode.prototype.querySelector = function () { return null; };
FakeNode.prototype.querySelectorAll = function () { return []; };
FakeNode.prototype.contains = function () { return false; };
function textOf(node) { return [node.textContent].concat(node.children.map(textOf)).join(' ').replace(/\s+/g, ' ').trim(); }
function buttonsOf(node) { return node.children.reduce(function (all, child) { return all.concat(buttonsOf(child)); }, (node.tag === 'button') ? [node] : []); }
var el = function (tag, className, text) { return new FakeNode(tag, className, text); };
var t = function () { return Array.prototype.slice.call(arguments).join(' '); };
var K = {
    el: el,
    clear: function (node) { node.children = []; node.textContent = ''; return node; },
    icon: function (name) { return new FakeNode('i', 'bi ' + (name || '')); },
    button: function (className, label) { return new FakeNode('button', className, label); },
    fail: function (error) { failed.push(error && error.message); },
    toast: function () {},
    api: function (action) { calls.push(action); var next = answers.shift(); return next ? next() : new Promise(function () {}); }
};
var window = {
    setTimeout: function (fn, ms) { timers.push({ fn: fn, ms: ms, live: true }); return timers.length; },
    clearTimeout: function (id) { if (id && timers[id - 1]) { timers[id - 1].live = false; } }
};
var document = { visibilityState: 'visible', activeElement: null, body: new FakeNode('body') };
var ui = { root: new FakeNode('div'), runBox: new FakeNode('div', 'wsf-run-bar d-none'), side: new FakeNode('aside'), app: new FakeNode('div'), outlineBox: new FakeNode('div'), boxes: new FakeNode('div') };
var state = {};
function live() { return timers.filter(function (timer) { return timer.live; }).map(function (timer) { return timer.ms; }); }
function fire() { var timer = timers.filter(function (item) { return item.live; }).pop(); timer.live = false; timer.fn(); }
function settle() { return new Promise(function (resolve) { setImmediate(resolve); }); }
// A refusal read from the server's JSON answer, as workspace.js's api() throws it.
function refused(message) { var error = new Error(message); error.field = ''; error.code = ''; error.stale = false; return Promise.reject(error); }
function keepFocus(draw) { draw(); }
function announce(text) { announced.push(text); }
function runBadge(states, status) { return el('span', 'wsf-run-badge', status); }
function personName(id) { return 'person ' + id; }
function when(value) { return String(value); }
var RUN_STATES = {};
JS;
}

// Runs the functions named, cut from workspace_flow.js as written (its
// POLL_* timings with them), after the stand-ins and before $script, and
// returns what $script prints as JSON. null without Node.
function ws_flows_canvas_test_node(array $names, $script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));
	$body = ws_flows_canvas_test_stage() . "\n";

	preg_match_all('/^ *var POLL_[A-Z_]+ = [0-9.]+;$/m', $source, $timings);
	$body .= implode("\n", $timings[0]) . "\n";

	foreach ($names as $name) {
		$function = ws_flows_canvas_test_script_function($source, $name);

		pg_assert_true($function !== '', 'workspace_flow.js has ' . $name . '()');
		$body .= $function . "\n";
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsflow');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $body . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A look at an open run that the server refuses (the flow binned with its
// runs, the channel no longer read, the session ended: a JSON answer, which
// api() gives a code) is final: no further look, and the bar over the canvas
// says why and that the run is no longer followed. A look that got no answer
// (a network failure, a server error page without JSON) is tried again,
// later each time, with a word in the bar that goes once a look succeeds.
// Before, a refusal stopped the looks without a word, and the run kept
// showing as still going with its Cancel.
function test_workspace_flows_canvas_run_poll_failures_are_told()
{
	$result = ws_flows_canvas_test_node(
		array('runOpen', 'stopPoll', 'schedulePoll', 'pollRun', 'pollDone', 'pollResume', 'pollFailed', 'drawRunBar', 'runSide'),
		<<<'JS'
function applyRun(run) { state.run = run; return false; }
function drawRunViews() { drawRunBar(); K.clear(ui.side); runSide(); }
function closeRun() {}
function tick() {}
function fresh() {
    state = { run: { id: 12, flow_id: 7, status: 'running', version: 2, can_cancel: true, started_by: 4, started_at: 'a', finished_at: null, steps: [] }, poll: { timer: 0, delay: POLL_FIRST, busy: 0 }, outline: true, refocus: '' };
    timers = [];
    announced = [];
    K.clear(ui.runBox);
    K.clear(ui.side);
}
(async function () {
    var out = {};

    fresh();
    answers.push(function () { return refused('That run could not be found.'); });
    pollRun();
    await settle();
    out.refused = { looks: live(), bar: textOf(ui.runBox), side: textOf(ui.side), sideButtons: buttonsOf(ui.side).map(textOf), told: announced.length, cancel: state.run.can_cancel };
    schedulePoll(0);
    out.refused.again = live();

    fresh();
    answers.push(function () { return Promise.reject(new TypeError('Failed to fetch')); });
    pollRun();
    await settle();
    out.unreached = { looks: live(), bar: textOf(ui.runBox), told: announced.length };
    answers.push(function () { return Promise.reject(new Error('Something went wrong.')); });
    fire();
    await settle();
    out.unreached.second = live();
    out.unreached.toldOnce = announced.length;
    answers.push(function () { return Promise.resolve({ run: state.run, due: false }); });
    fire();
    await settle();
    out.unreached.healed = textOf(ui.runBox);
    out.unreached.next = live().length;

    console.log(JSON.stringify(out));
})();
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(is_array($result), 'the script printed its result');

	if (!is_array($result)) {
		return;
	}

	pg_assert_same(array(), $result['refused']['looks'], 'a refusal is not read again');
	pg_assert_contains('That run could not be found.', $result['refused']['bar'], 'the bar says why');
	pg_assert_contains('fl_run_lost', $result['refused']['bar'], 'and that the run is no longer followed');
	pg_assert_true($result['refused']['told'] > 0, 'screen readers are told');
	pg_assert_false($result['refused']['cancel'], 'a run that cannot be read offers no Cancel');
	pg_assert_false(in_array('fl_run_cancel', $result['refused']['sideButtons'], true), 'nor does its side panel');
	pg_assert_false(strpos($result['refused']['side'], 'fl_run_open') !== false, 'the side panel no longer says it is still going');
	pg_assert_contains('fl_run_lost', $result['refused']['side'], 'but that it is shown as last read');
	pg_assert_same(array(), $result['refused']['again'], 'a tab shown again or the screen\'s tick does not read it again');

	pg_assert_same(array(4000), $result['unreached']['looks'], 'no answer: read again later');
	pg_assert_contains('fl_run_unreached', $result['unreached']['bar'], 'the bar says it is tried again');
	pg_assert_same(1, $result['unreached']['told'], 'screen readers are told');
	pg_assert_same(array(8000), $result['unreached']['second'], 'a server error page waits longer still');
	pg_assert_same(1, $result['unreached']['toldOnce'], 'told once while it lasts');
	pg_assert_false(strpos($result['unreached']['healed'], 'fl_run_unreached') !== false, 'the word goes once a look succeeds');
	pg_assert_same(1, $result['unreached']['next'], 'and the run keeps being read');
}

// The runs list that could not be loaded says so, with the reason and a
// way to load it again, instead of "This flow has not run yet."
function test_workspace_flows_canvas_runs_list_failure_is_told()
{
	$result = ws_flows_canvas_test_node(
		array('loadRuns', 'runsSide'),
		<<<'JS'
function drawSettings() { K.clear(ui.side); runsSide(); }
function openRun() {}
state = { flow: { id: 7 }, panel: 'runs', run: null, selected: null, outline: false, opening: {}, refocus: '', runs: { flow: 7, items: [], next: null, loading: false } };
(async function () {
    var out = {};

    answers.push(function () { return refused('That flow could not be found.'); });
    loadRuns(false);
    await settle();
    drawSettings();
    out.failed = textOf(ui.side);
    var retry = buttonsOf(ui.side);
    out.buttons = retry.length;
    answers.push(function () { return Promise.resolve({ runs: [{ id: 31, flow_id: 7, status: 'done', version: 2, started_by: 4, started_at: 'a', finished_at: 'b' }], next: null }); });

    if (retry.length) {
        retry[0].click();
    }

    out.again = textOf(ui.side);
    await settle();
    drawSettings();
    out.loaded = textOf(ui.side);
    out.calls = calls.length;

    console.log(JSON.stringify(out));
})();
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(is_array($result), 'the script printed its result');

	if (!is_array($result)) {
		return;
	}

	pg_assert_false(strpos($result['failed'], 'fl_runs_none') !== false, 'a failed load is not "has not run yet"');
	pg_assert_contains('That flow could not be found.', $result['failed'], 'the reason');
	pg_assert_contains('fl_retry', $result['failed'], 'a way to load it again');
	pg_assert_same(1, $result['buttons'], 'one button');
	pg_assert_contains('loading', $result['again'], 'loading again shows at once');
	pg_assert_contains('fl_run_name 31', $result['loaded'], 'then the runs');
	pg_assert_same(2, $result['calls'], 'read twice');
}
