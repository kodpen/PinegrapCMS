<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agents' request queue (includes/workspace/agent_queue.php)
 * that need no database: the agent value of a request, the wake rules, the
 * push door's body and signature, the routes, and that a message's ask and
 * the queue's carry-on reach the registry's agents.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_agent_request_value()
{
	pg_assert_same('agent:4', ws_agent_request_value(array('kind' => 'app', 'id' => 4)));
	pg_assert_same('model:9', ws_agent_request_value(array('kind' => 'model', 'id' => '9')));
	pg_assert_same('', ws_agent_request_value(array('kind' => 'claude', 'id' => 1)), 'Claude keeps its own queue');
	pg_assert_same('', ws_agent_request_value(array('kind' => 'app', 'id' => 0)));
	pg_assert_same(9, ws_model_agent_id_of(ws_agent_request_value(array('kind' => 'model', 'id' => 9))), 'the model loop reads it back');
}

function test_workspace_agent_request_value_apart_from_proposals()
{
	// Agent 5 and application 5: the agent's requests and the application's
	// proposals never share a value, so neither's readers take the other's.
	$request = ws_agent_request_value(array('kind' => 'app', 'id' => 5, 'app_id' => 5));
	$proposal = ws_proposal_agent_key(0, 5);

	pg_assert_same('agent:5', $request);
	pg_assert_same('app:5', $proposal);
	pg_assert_true($request !== $proposal);
	pg_assert_same(array('kind' => 'app', 'id' => 5), ws_agent_request_parse($request), 'the request names agent 5');
	pg_assert_same(null, ws_agent_request_parse($proposal), 'the proposal names no registry agent');
	pg_assert_same(array('kind' => 'app', 'id' => 5), ws_proposal_agent_parse($proposal), 'the proposal names application 5');
	pg_assert_same(null, ws_proposal_agent_parse($request), 'the request is no proposal');
	pg_assert_true(strlen($request) <= 16, 'it fits ws_ai_requests.agent');
	pg_assert_same(null, ws_agent_wake_of(array('agent' => $proposal, 'message_id' => 0, 'ai_state' => ws_agent_wake_state('at', '', 1))));
}

function test_workspace_agent_queue_expired_where()
{
	$where = ws_agent_queue_expired_where(1700000000);
	pg_assert_contains("agent LIKE 'agent:%'", $where, 'only app agents: the others have their own watchdogs');
	pg_assert_contains("status IN ('queued', 'sent')", $where, 'never one claimed');
	pg_assert_contains("created_at < '" . (1700000000 - WS_AI_QUEUE_TTL) . "'", $where, 'a day old');
}

function test_workspace_agent_wake_matches()
{
	$row = array('handle' => 'ci', 'app_id' => 7, 'wake_rule' => 'mention', 'wake_prefix' => '');

	pg_assert_true(ws_agent_wake_matches($row, '@ci run it', false));
	pg_assert_true(ws_agent_wake_matches($row, 'please, @CI.', false), 'any case, punctuation after');
	pg_assert_true(ws_agent_wake_matches($row, 'ask <@app:7> now', false), 'its application tag');
	pg_assert_true(!ws_agent_wake_matches($row, '@circle', false), 'a longer handle');
	pg_assert_true(!ws_agent_wake_matches($row, 'mail me@ci.example', false), 'an address');
	pg_assert_true(!ws_agent_wake_matches($row, 'ci is red', false), 'no @');
	pg_assert_true(!ws_agent_wake_matches($row, 'thanks', true), 'mention only: a reply does not wake it');

	pg_assert_true(ws_agent_wake_matches(array('wake_rule' => 'all') + $row, 'anything', false));
	pg_assert_true(!ws_agent_wake_matches(array('wake_rule' => 'all') + $row, '  ', false), 'an empty message wakes nobody');

	$prefix = array('wake_rule' => 'prefix', 'wake_prefix' => '[ci]') + $row;
	pg_assert_true(ws_agent_wake_matches($prefix, ' [CI] build 12 red', false));
	pg_assert_true(!ws_agent_wake_matches($prefix, 'build [ci] red', false), 'only at the start');
	pg_assert_true(!ws_agent_wake_matches(array('wake_prefix' => '') + $prefix, 'x', false), 'no prefix, no wake');

	$replies = array('wake_rule' => 'replies') + $row;
	pg_assert_true(ws_agent_wake_matches($replies, 'ok, go on', true));
	pg_assert_true(!ws_agent_wake_matches($replies, 'ok, go on', false));
}

function test_workspace_agent_knock_body_and_headers()
{
	$body = ws_agent_knock_body('https://site.example/integration.php', 'ci', 12);
	pg_assert_same(array('event' => 'workspace.agent_request', 'site' => 'https://site.example/integration.php', 'agent' => 'ci', 'request_id' => 12), json_decode($body, true));
	pg_assert_true(strpos($body, 'text') === false, 'no words in a knock');

	$headers = ws_agent_knock_headers($body, 's3cret', 1700000000);
	pg_assert_true(in_array('Authorization: Bearer s3cret', $headers, true));
	pg_assert_true(in_array('X-Pinegrap-Timestamp: 1700000000', $headers, true));
	pg_assert_true(in_array('X-Pinegrap-Signature: sha256=' . hash_hmac('sha256', '1700000000.' . $body, 's3cret'), $headers, true));

	foreach (ws_agent_knock_headers($body, '', 1) as $header) {
		pg_assert_true((stripos($header, 'Authorization') === false) && (stripos($header, 'Signature') === false), 'no secret, nothing signed');
	}
}

function test_workspace_agent_request_routes()
{
	$ids = array();

	foreach (ws_agent_request_api_routes() as $route) {
		$ids[$route['id']] = $route;
		pg_assert_true(empty($route['mcp']), $route['id'] . ': the key used as itself, never over MCP');

		if ($route['method'] === 'POST') {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' rehearses');
		}
	}

	pg_assert_same(array('workspace.agent_requests.list', 'workspace.agent_requests.claim', 'workspace.agent_requests.answer', 'workspace.agent_requests.fail',
		'workspace.agent_requests.brief', 'workspace.agent_wake.get', 'workspace.agent_wake.set'), array_keys($ids));

	$routes = array_column(ws_api_routes(), 'id');
	pg_assert_true(in_array('workspace.agent_requests.answer', $routes, true), 'merged into the module routes');
}

// A function's source without its comment lines.
function workspace_agent_queue_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

function test_workspace_agent_queue_wiring()
{
	pg_assert_true(strpos(workspace_agent_queue_test_source('ws_message_ask_assistants'), 'ws_agent_after_send(') !== false, 'a message asks the registry agents');
	pg_assert_true(strpos(workspace_agent_queue_test_source('ws_api_assistants_carry_on'), "\$asked['models']") !== false, 'a model agent is carried on as Pinegrap AI is');
	pg_assert_true(strpos(workspace_agent_queue_test_source('ws_ai_kick'), 'ws_agent_knocks_retry(') !== false, 'failed knocks are tried again');

	$after = workspace_agent_queue_test_source('ws_agent_after_send');
	pg_assert_true(strpos($after, 'ws_agent_brake(') < strpos($after, 'INSERT INTO ws_ai_requests'), 'the brakes come before the request');

	$answer = workspace_agent_queue_test_source('ws_api_agent_request_answer');
	pg_assert_true(strpos($answer, 'api_dry_run_stop(') < strpos($answer, 'ws_message_send($'), 'the answer is rehearsed before it is written');
}
