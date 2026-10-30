<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the MCP box of the workspace's flows with a tool that asks
 * before each call (includes/workspace/steps.php, flow_runs.php): the box
 * asks its owner to approve the call in a channel, waits on that request
 * as the approval box does, and calls the tool with the owner's own account
 * once it is approved; a refusal, an expiry or a request taken back settles
 * it as the approval box is settled. Read from the source: the call is made
 * once, with the arguments that were approved.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

// A function's source without its comments.
function ws_flow_mcp_asks_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// A server people sign in to, with a tool that reads, one that asks before
// each call and one that is off.
function ws_flow_mcp_asks_test_server($auth_kind = 'oauth')
{
	return array(
		'id'         => 7,
		'alias'      => 'docs',
		'auth_kind'  => $auth_kind,
		'status'     => 'active',
		'tools'      => json_encode(array(
			array('name' => 'search', 'hints' => array('readOnlyHint' => true)),
			array('name' => 'create_page', 'hints' => array('readOnlyHint' => false)),
			array('name' => 'delete_page', 'hints' => array('destructiveHint' => true)),
		)),
		'tool_rules' => json_encode(array('search' => 'auto', 'create_page' => 'approve')),
	);
}

// The box leaves on done or failed as before, and on how the request ended
// when its call asks first: rejected, expired or withdrawn.
function test_workspace_flow_mcp_asks_outcomes()
{
	pg_assert_same(array('done', 'failed', 'rejected', 'expired', 'withdrawn'), ws_flow_kinds()['mcp']['outcomes']);

	$graph = ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual'),
			array('id' => 'make', 'kind' => 'mcp', 'config' => array('server_id' => 7, 'tool' => 'create_page', 'channel_id' => 3)),
			array('id' => 'said_no', 'kind' => 'post', 'config' => array('channel_id' => 3, 'body' => 'Not made')),
		),
		'edges' => array(
			array('from' => 'start', 'to' => 'make'),
			array('from' => 'make', 'to' => 'said_no', 'on' => 'rejected'),
		),
	));

	pg_assert_same('rejected', $graph['edges'][1]['on'], 'an arrow may leave on a refusal');
	pg_assert_same(array(), ws_flow_graph_check($graph));

	// Refused, the branch ends; nothing decided and no arrow on it, the box
	// fails: as the approval box.
	$steps = array('start' => array('status' => 'done', 'outcome' => 'next'), 'make' => array('status' => 'done', 'outcome' => 'rejected'));

	pg_assert_same(array('said_no'), ws_flow_walk($graph, $steps)['ready']);
}

// Its settings gain the channel the owner is asked in and a deadline in
// hours, kept as the approval box keeps them.
function test_workspace_flow_mcp_asks_config()
{
	$config = ws_step_config_normalize('mcp', array('server_id' => 7, 'tool' => 'create_page', 'channel_id' => '3', 'closes_in' => '48'));

	pg_assert_same(array('server_id' => 7, 'tool' => 'create_page', 'arguments' => '', 'channel_id' => 3, 'closes_in' => 48), $config);
	pg_assert_same('', ws_step_config_normalize('mcp', array('closes_in' => 'soon'))['closes_in']);
	pg_assert_same(1, ws_step_config_normalize('mcp', array('closes_in' => 0))['closes_in'], 'at least an hour');
	pg_assert_same(WS_STEP_APPROVAL_CLOSES_MAX, ws_step_config_normalize('mcp', array('closes_in' => 10 ** 6))['closes_in']);
	pg_assert_same(0, ws_step_config_normalize('mcp', array('channel_id' => -4))['channel_id']);

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');

	$settings = array_column(array_column(ws_flow_kind_catalogue(), null, 'kind')['mcp']['settings'], null, 'key');

	pg_assert_same(array('integer', false), array($settings['channel_id']['type'], $settings['channel_id']['blank']));
	pg_assert_same(array('integer', true), array($settings['closes_in']['type'], $settings['closes_in']['blank']));
}

// A tool that asks first may be run by a box now; one that is off may not.
function test_workspace_flow_mcp_asks_tool_rule()
{
	$server = ws_flow_mcp_asks_test_server();

	pg_assert_same('', ws_step_mcp_tool_problem($server, 'search'));
	pg_assert_same('', ws_step_mcp_tool_problem($server, 'create_page'), 'a tool that asks first');
	pg_assert_contains('is not turned on', ws_step_mcp_tool_problem($server, 'delete_page'));

	pg_assert_true(ws_step_mcp_tool_asks($server, 'create_page'));
	pg_assert_false(ws_step_mcp_tool_asks($server, 'search'), 'a tool that reads runs at once');
	pg_assert_false(ws_step_mcp_tool_asks($server, 'delete_page'), 'a tool that is off asks nothing');

	// Only where people sign in with their own accounts may a tool ask first.
	pg_assert_contains('is not turned on', ws_step_mcp_tool_problem(ws_flow_mcp_asks_test_server('bearer'), 'create_page'));
	pg_assert_false(ws_step_mcp_tool_asks(ws_flow_mcp_asks_test_server('bearer'), 'create_page'));
}

// The effect of a box whose call was approved: never a wait, and a box that
// stopped before the call is tried again once, as a box that never began.
function test_workspace_flow_mcp_asks_approved_key()
{
	pg_assert_same('approved:12', ws_flow_approved_key(12));
	pg_assert_same(12, ws_flow_effect_approved_id('approved:12'));
	pg_assert_same(0, ws_flow_effect_approved_id('approval:12'));
	pg_assert_same(0, ws_flow_effect_approved_id('approved:0'));
	pg_assert_same(0, ws_flow_effect_approval_id('approved:12'));
	pg_assert_true(strlen(ws_flow_approved_key(4294967295)) <= 64, 'fits the effect column');

	pg_assert_same(null, ws_flow_effect_wait('approved:12'));
	pg_assert_same('retry', ws_flow_stale_action(array('effect' => 'approved:12', 'attempt' => 1)));
	pg_assert_same('fail', ws_flow_stale_action(array('effect' => 'approved:12', 'attempt' => 2)));
	pg_assert_same('fail', ws_flow_stale_action(array('effect' => WS_STEP_BEGAN . 'mcp', 'attempt' => 1)), 'a call that may have been made is never made again');
}

// What the request says: the server and the tool in its title, the flow,
// who approves it and who started the run, and the arguments as JSON.
function test_workspace_flow_mcp_asks_card()
{
	pg_assert_same('Run the tool create_page of @docs', ws_step_mcp_ask_title('create_page', 'docs'));

	$text = ws_step_mcp_ask_text('{"title":"Plan","tags":["a"],"meta":{}}', 'Weekly', 'Ayşe', '', 3500);

	pg_assert_contains('Weekly', $text);
	pg_assert_contains('Ayşe', $text);
	pg_assert_contains("```\n{\n    \"title\": \"Plan\",", $text, 'the arguments as JSON in a block');
	pg_assert_contains('"meta": {}', $text, 'an empty object stays one');
	pg_assert_true(substr($text, -4) === "\n```", 'the block is closed');
	pg_assert_false(strpos($text, 'started by') !== false, 'the owner started it');
	pg_assert_contains('Bora', ws_step_mcp_ask_text('{}', 'Weekly', 'Ayşe', 'Bora', 3500), 'a colleague started it');
	pg_assert_contains("```\n{}\n```", ws_step_mcp_ask_text('', 'Weekly', 'Ayşe', '', 3500), 'no arguments');

	// A tag in the arguments mentions nobody; a fence in them ends no block.
	$tagged = ws_step_mcp_ask_text('{"q":"<@user:5> <#order:9> ```x```"}', 'Weekly', 'Ayşe', '', 3500);

	pg_assert_same(array(), ws_tokens($tagged), 'no tag');
	pg_assert_same(2, substr_count($tagged, '```'), 'one block');

	// Too long for the message: the start of them, cut at a character, and
	// how long they are.
	$json = json_encode(array('body' => str_repeat('ğ', 9000)), JSON_UNESCAPED_UNICODE);
	$cut = ws_step_mcp_ask_text($json, 'Weekly', 'Ayşe', '', 1000);

	pg_assert_true(mb_strlen($cut) <= 1000, 'within the room');
	pg_assert_true(mb_check_encoding($cut, 'UTF-8'));
	pg_assert_contains((string) mb_strlen($json), $cut, 'the length named');
	pg_assert_true(substr($cut, -4) === "\n```", 'the block is still closed');
}

// The arguments a box asked with stay beside its waiting result, where no
// reader's values and no later box read them; the call reads them back.
function test_workspace_flow_mcp_asks_kept_arguments()
{
	$stored = ws_flow_result_pack('asked for approval in #ops', array('result' => null, 'duration_ms' => null, 'call' => array('arguments' => '{"title":"Plan"}')));

	pg_assert_same('{"title":"Plan"}', ws_step_mcp_asked_arguments($stored));
	pg_assert_same(null, ws_step_mcp_asked_arguments(ws_flow_result_pack('done', array('result' => 'x'))));
	pg_assert_same(null, ws_step_mcp_asked_arguments(''));
	pg_assert_same(null, ws_step_mcp_asked_arguments(ws_flow_result_pack('x', array('call' => array('arguments' => '[1]')))), 'only an object');

	pg_assert_same(array('result' => null, 'duration_ms' => null), ws_api_flow_step_outputs('mcp', $stored, true), 'no output of the box');
	pg_assert_false(isset(ws_step_node_values(array(array('node_id' => 'make', 'status' => 'done', 'outcome' => 'done', 'result' => $stored)))['make']['call']), 'no value for a later box');
}

// Trying the box says it asks first, and where.
function test_workspace_flow_mcp_asks_try_words()
{
	pg_assert_contains('#ops', ws_step_mcp_text(array('tool' => 'create_page', 'alias' => 'docs', 'asks' => true, 'channel_name' => 'ops')));
	pg_assert_same(ws_step_mcp_text(array('tool' => 'search', 'alias' => 'docs')), lang(array('string' => 'Runs the tool {var:1} of @{var:2} and keeps its answer.', 'vars' => array('search', 'docs'))));
}

// Read from the source: the check asks for the owner's own account and a
// channel they may be asked in; the box asks instead of calling, waits as
// the approval box waits and the owner is told.
function test_workspace_flow_mcp_asks_asks_and_waits()
{
	$check = ws_flow_mcp_asks_test_source('ws_step_mcp_check');

	pg_assert_contains('ws_step_mcp_tool_asks($server, $tool)', $check);
	pg_assert_contains("ws_mcp_oauth_account(\$server, (int) \$viewer['id'], true)", $check, 'never the site\'s account');
	pg_assert_contains('ws_approval_input($viewer, $channel', $check);
	pg_assert_contains("array((int) \$viewer['id'])", $check, 'the owner alone approves');

	$run = ws_flow_mcp_asks_test_source('ws_step_run');

	pg_assert_contains('ws_step_mcp_ask($owner, $action', $run);
	pg_assert_contains('ws_flow_approval_wait(', $run);
	pg_assert_true(strpos($run, 'ws_step_mcp_ask(') < strpos($run, 'ws_flow_approval_wait('), 'waits once the request is out');
	pg_assert_true(strpos($run, 'WS_STEP_BEGAN') < strpos($run, 'ws_step_mcp_ask('), 'never asked twice');
	pg_assert_contains("in_array(\$kind, array('approval', 'mcp', 'change'), true)", $run, 'the deadline counts from when the box runs');

	$ask = ws_flow_mcp_asks_test_source('ws_step_mcp_ask');

	pg_assert_contains('ws_approval_create($owner, $channel', $ask);
	pg_assert_contains("'approvers' => array((int) \$owner['id'])", $ask);
	pg_assert_contains("ws_notify((int) \$owner['id'], 'approval_requested'", $ask, 'the owner is told of their own request');
	pg_assert_contains("'call'", $ask, 'the arguments are kept');

	pg_assert_contains("\$row['actor_id'] === (int) \$viewer['id']", ws_flow_mcp_asks_test_source('ws_approval_inbox_describe'));
}

// Read from the source: approved, the box is ready again from waiting only;
// its claim makes the call once, with the arguments approved and the
// owner's own account; anything else settles it as the approval box.
function test_workspace_flow_mcp_asks_approved_call()
{
	$recheck = ws_flow_mcp_asks_test_source('ws_flow_approval_recheck');

	pg_assert_contains("'mcp'", $recheck);
	pg_assert_true(strpos($recheck, 'ws_flow_approved_ready(') < strpos($recheck, 'ws_flow_approval_settlement('), 'approved, it calls rather than settles');

	$ready = ws_flow_mcp_asks_test_source('ws_flow_approved_ready');

	pg_assert_true((bool) preg_match("/status = 'ready'[^;]*effect = '[^;]*AND status = 'waiting' AND wait_key = '/s", $ready), 'ready again from waiting on that request, in one write');
	pg_assert_contains('mysqli_affected_rows(db::$con)', $ready);
	pg_assert_contains('ws_flow_run_refresh(', $ready);

	$run = ws_flow_mcp_asks_test_source('ws_step_run');

	pg_assert_true(strpos($run, 'ws_flow_effect_approved_id(') < strpos($run, 'WS_STEP_BEGAN'), 'an approved box goes to its call before anything is begun again');

	$call = ws_flow_mcp_asks_test_source('ws_step_mcp_approved_call');

	pg_assert_true((bool) preg_match("/effect = '\" \. e\(WS_STEP_BEGAN \. 'mcp'\) \. \"'[^;]*AND status = 'running' AND effect = '/s", $call), 'begun from the approved effect, conditionally');
	pg_assert_true(strpos($call, 'WS_STEP_BEGAN') < strpos($call, 'ws_step_mcp_do('), 'the mark before the call');
	pg_assert_contains('ws_step_mcp_asked_arguments(', $call);
	pg_assert_false(strpos($call, 'ws_step_fill_action(') !== false, 'never filled again');
	pg_assert_contains("'asks' => true", $call, 'with the owner\'s own account');
	pg_assert_contains('ws_step_mcp_check($owner', $call);

	pg_assert_contains('!empty($action[\'asks\'])', ws_flow_mcp_asks_test_source('ws_step_mcp_do'));
	pg_assert_contains('ws_mcp_connection($server, WS_MCP_SCREEN_TIMEOUT, (int) $user_id, $person_only)', ws_flow_mcp_asks_test_source('ws_mcp_tool_call'));
}
