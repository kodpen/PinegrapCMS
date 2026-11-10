<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Run now on the canvas of a flow that starts at a time
 * (ws_flow_run_now(), includes/workspace/flow_anchors.php): who may run it,
 * which flows it is offered on and refused for (a paused, draft or never
 * published flow, every start but a time, an anchor that ran out), and,
 * read from the source, that it starts the flow through its anchor by hand
 * with the client's token as the run's start key, so a second click finds
 * the run the first one started; and that the canvas asks first, sends the
 * token under its own name and shows Run now in place of Run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A function's source without its comments.
function ws_flow_run_now_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The source of one case of ws_handle_action(), up to the next case.
function ws_flow_run_now_test_case($action)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($source, "case '" . $action . "':");
	$end = ($start !== false) ? strpos($source, "\n        case '", $start + 10) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The canvas script's function of that name, up to its closing brace.
function ws_flow_run_now_test_script_function($name)
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$start = strpos($script, '    function ' . $name . '(');
	$end = ($start !== false) ? strpos($script, "\n    }\n", $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($script, $start, $end - $start) : '';
}

// Only whoever may change the flow: a run share, a view share, a home
// channel's reader and nobody are refused.
function test_workspace_flow_run_now_rights()
{
	$live = array('status' => 'live', 'version_id' => 30);

	pg_assert_same('', ws_flow_run_now_refusal('edit', $live, 'schedule', 'active'), 'an editor');
	pg_assert_same('access', ws_flow_run_now_refusal('run', $live, 'schedule', 'active'), 'run access');
	pg_assert_same('access', ws_flow_run_now_refusal('view', $live, 'schedule', 'active'), 'view access');
	pg_assert_same('access', ws_flow_run_now_refusal('', $live, 'schedule', 'active'), 'no access');
}

// A paused flow, a draft, one never published and one in the bin are
// refused; the flow's state is asked before its start.
function test_workspace_flow_run_now_refuses_a_flow_not_live()
{
	pg_assert_same('not_live', ws_flow_run_now_refusal('edit', array('status' => 'paused', 'version_id' => 30), 'schedule', 'paused'), 'paused');
	pg_assert_same('not_live', ws_flow_run_now_refusal('edit', array('status' => 'draft', 'version_id' => 0), '', ''), 'draft');
	pg_assert_same('not_live', ws_flow_run_now_refusal('edit', array('status' => 'live', 'version_id' => 0), 'schedule', 'active'), 'never published');
	pg_assert_same('not_live', ws_flow_run_now_refusal('edit', array('status' => 'binned', 'version_id' => 30), 'schedule', 'cancelled'), 'binned');
}

// Time only (owner): every other start box, and a flow without one, gets no
// Run now; a flow started by hand has Run.
function test_workspace_flow_run_now_time_starts_only()
{
	$live = array('status' => 'live', 'version_id' => 30);

	foreach (array('event', 'join', 'row', 'mention', 'web', 'no_order', 'manual', '') as $kind) {
		pg_assert_same('not_time', ws_flow_run_now_refusal('edit', $live, $kind, 'active'), 'started ' . (($kind === '') ? 'by nothing' : 'by ' . $kind));
	}

	foreach (ws_flow_start_names() as $kind => $start) {
		pg_assert_same(($kind === 'schedule'), $start === 'time', 'the start column says time for ' . $kind);
	}
}

// The anchor must still run: active or failed; a flow that starts once and
// ran (done), a paused or cancelled anchor and none at all are over.
function test_workspace_flow_run_now_anchor_still_runs()
{
	$live = array('status' => 'live', 'version_id' => 30);

	pg_assert_same('', ws_flow_run_now_refusal('edit', $live, 'schedule', 'active'), 'active');
	pg_assert_same('', ws_flow_run_now_refusal('edit', $live, 'schedule', 'failed'), 'failed');

	foreach (array('done', 'paused', 'cancelled', '') as $status) {
		pg_assert_same('over', ws_flow_run_now_refusal('edit', $live, 'schedule', $status), 'anchor ' . (($status === '') ? 'missing' : $status));
	}
}

// Offered on the canvas only where the refusal would say yes; any other
// flow is answered without a read.
function test_workspace_flow_run_now_offered_without_a_read()
{
	$flow = array('id' => 12, 'status' => 'live', 'version_id' => 30, 'access' => 'edit');

	pg_assert_false(ws_flow_run_now_offered($flow, 'event'), 'an event start');
	pg_assert_false(ws_flow_run_now_offered($flow, 'manual'), 'by hand');
	pg_assert_false(ws_flow_run_now_offered(array('access' => 'run') + $flow, 'time'), 'run access');
	pg_assert_false(ws_flow_run_now_offered(array('status' => 'paused') + $flow, 'time'), 'paused');

	$offered = ws_flow_run_now_test_source('ws_flow_run_now_offered');

	pg_assert_contains('ws_flow_run_now_refusal(', $offered, 'the same rule as the route');
	pg_assert_contains("'run_now'  => function_exists('ws_flow_run_now_offered') && ws_flow_run_now_offered(\$flow, \$start)", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php'), 'the canvas detail says it');
}

// A panel action of the Workspace family, its case handing over only the
// start token, answered as Run is.
function test_workspace_flow_run_now_route()
{
	$actions = pg_panel_actions();

	pg_assert_true(isset($actions['ws_flow_run_now']), 'listed');
	pg_assert_same('pg_panel_ws', $actions['ws_flow_run_now']['handler'] ?? '', 'handler');
	pg_assert_true(!empty($actions['ws_flow_run_now']['token']) && !empty($actions['ws_flow_run_now']['write']), 'token and write');
	pg_assert_same('exempt', pg_panel_gate('ws_flow_run_now'), 'the module decides');

	$case = ws_flow_run_now_test_case('ws_flow_run_now');

	pg_assert_contains("ws_flow_for(\$viewer, (int) (\$request['flow_id'] ?? 0), 'view')", $case, 'a flow the person reaches');
	pg_assert_contains("ws_flow_run_now(\$viewer, \$flow, ws_action_flow_run_fields(array_intersect_key(\$request, array('start_token' => true))))", $case, 'the token alone');
	pg_assert_contains('ws_action_flow_run_answer($viewer, $result[\'run_id\'])', $case, 'the run as Run answers it');
	pg_assert_false(strpos($case, 'ws_scheduled_execute(') !== false, 'the case runs nothing itself');
}

// Rights before anything runs: a token's own run first (it may have been
// started before a share was lowered), then the refusal, then the anchor by
// hand; nothing past the refusal starts a flow of another kind.
function test_workspace_flow_run_now_checks_before_it_runs()
{
	$body = ws_flow_run_now_test_source('ws_flow_run_now');
	$token = strpos($body, 'ws_flow_run_for_token($viewer, $flow_id, $token)');
	$refusal = strpos($body, 'ws_flow_run_now_refusal($access, $flow,');
	$run = strpos($body, "ws_scheduled_execute(\$anchor['id'], true, array('start_key' => ws_flow_start_key(\$token)))");

	pg_assert_true($token !== false && $refusal !== false && $run !== false, 'all three found');
	pg_assert_true(($token < $refusal) && ($refusal < $run), 'token run, refusal, then the anchor');
	pg_assert_contains('!ws_flow_start_token_valid($token)', $body, 'a token of the wrong shape refused');
	pg_assert_contains("422, 'token'", $body, 'with its field');
	pg_assert_contains('ws_flow_access($viewer, $flow)', $body, 'the access asked here');
}

// A second click (or a request sent again after its answer was lost) with
// the same token finds the run the first one started: the token's key is
// the run's start key, which ws_flow_anchor_start() uses in place of the
// anchor run's, and the unique (flow_id, start_key) answers it.
function test_workspace_flow_run_now_second_click_finds_the_run()
{
	$body = ws_flow_run_now_test_source('ws_flow_run_now');

	pg_assert_same(2, substr_count($body, 'ws_flow_run_for_token($viewer, $flow_id, $token)'), 'asked before and after the start');
	pg_assert_contains('return $answer($retried[\'id\'], false);', $body, 'the first run answered, not started again');
	pg_assert_contains('return $answer($run[\'id\'], $ran !== \'\');', $body, 'a run another request started is answered too');
	pg_assert_contains("(string) \$fill['start_key']", ws_flow_run_now_test_source('ws_flow_anchor_start'), 'the anchor start takes the key');
	pg_assert_contains("'start_key' => (string) (\$context['start_key'] ?? '')", ws_flow_run_now_test_source('ws_scheduled_execute'), 'handed on by the anchor');
	pg_assert_same(ws_flow_start_key('canvas.0a1b'), ws_flow_start_key('canvas.0a1b'), 'one token, one key');
	pg_assert_true(ws_flow_start_key('canvas.0a1b') !== ws_flow_start_key('canvas.0A1B'), 'tokens apart by case stay apart');
}

// By hand, as a converted action's Run now: the claim lets an active or a
// failed anchor run at once, and the start box says so.
function test_workspace_flow_run_now_is_by_hand()
{
	pg_assert_contains("\$claim = \" AND status IN ('active', 'paused', 'failed')\";", ws_flow_run_now_test_source('ws_scheduled_execute'), 'claimed whatever its time');
	pg_assert_same(lang('Started by hand.'), ws_flow_anchor_start_said('schedule', array('by_hand' => true)), 'the start box');
	pg_assert_same(lang(array('string' => 'Started on its own: {var:1}.', 'vars' => ws_flow_anchor_kinds()['schedule']['label'])), ws_flow_anchor_start_said('schedule', array()), 'on its time');
}

// The canvas offers Run now in place of Run on a flow that starts at a time,
// only where the server says run_now; any other flow keeps Run as it was.
function test_workspace_flow_run_now_canvas_button()
{
	$runnable = ws_flow_run_now_test_script_function('runnable');
	$button = ws_flow_run_now_test_script_function('runButton');

	pg_assert_contains("state.flow.start === 'time'", ws_flow_run_now_test_script_function('timeStarted'), 'a time start, as the start column reads it');
	pg_assert_contains('timeStarted() ? !!flow.run_now : ((flow.access === \'edit\') || (flow.access === \'run\'))', $runnable, 'the server decides Run now');
	pg_assert_contains("t(now ? 'fl_run_now' : 'fl_run')", $button, 'its own name');
	pg_assert_contains("t(now ? 'fl_run_now_help' : 'fl_run_help')", $button, 'its own help');

	$strings = ws_flow_js_strings();

	foreach (array('fl_run_now', 'fl_run_now_help', 'fl_run_now_ask') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' handed over');
	}
}

// Asked first, as Run is; the start token goes under its own name, never as
// the CSRF token's `token`, and no customer is asked of a time start.
function test_workspace_flow_run_now_canvas_asks_and_sends_the_token()
{
	$start = ws_flow_run_now_test_script_function('startRun');
	$ask = strpos($start, "K.ask(t(now ? 'fl_run_now_ask' : 'fl_run_ask', flow.name, flow.version)");
	$send = strpos($start, "K.api('ws_flow_run_now', {");

	pg_assert_true(($ask !== false) && ($send !== false) && ($ask < $send), 'asked before it is sent');

	$call = ($send !== false) ? substr($start, $send, (int) strpos($start, '}', $send) - $send) : '';

	pg_assert_contains('start_token: state.startToken.value', $call, 'the start token under its own name');
	pg_assert_false((bool) preg_match('/[{,]\s*token\s*:/', $call), 'no data key named token');
	pg_assert_contains('(FL.run_customer && !now) ? customerPicker() : null', $start, 'no customer for a time start');
	pg_assert_contains('flow.run_now = !!data.run_now;', $start, 'the answer says whether Run now stays');
	pg_assert_contains('if (!state.startToken || (state.startToken.flow !== flow.id))', $start, 'one token kept until answered');
}
