<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's progress note on a request it holds
 * (includes/workspace/agent_shown.php, api_agent_requests.php): the route's
 * refusals, the 30-second brake, the note kept beside the request's other
 * state and taken out when the request closes, and the line under the
 * asking message.
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

function test_workspace_agent_progress_refusals()
{
	$now = 1700000000;
	$running = array('status' => 'running', 'claimed_at' => $now - 600, 'ai_state' => null);

	$ok = ws_agent_progress_check($running, "  reading\n the plans ", $now);
	pg_assert_true($ok['ok'], 'the holder may write a note');
	pg_assert_same('reading the plans', $ok['text'], 'spaces fold into one');

	foreach (array('queued', 'sent') as $status) {
		$said = ws_agent_progress_check(array('status' => $status) + $running, 'x', $now);
		pg_assert_same(array(false, 409, 'not_claimed'), array($said['ok'], $said['status'], $said['code']), $status . ': nobody holds it yet');
	}

	foreach (array('answered', 'failed', 'cancelled') as $status) {
		$said = ws_agent_progress_check(array('status' => $status) + $running, 'x', $now);
		pg_assert_same(array(false, 409, 'closed'), array($said['ok'], $said['status'], $said['code']), $status . ': a closed request takes no note');
	}

	$long = ws_agent_progress_check($running, str_repeat('ş', WS_AGENT_PROGRESS_MAX + 1), $now);
	pg_assert_same(array(false, 422, 'text'), array($long['ok'], $long['status'], $long['field']), 'over 200 characters');
	pg_assert_true(ws_agent_progress_check($running, str_repeat('ş', WS_AGENT_PROGRESS_MAX), $now)['ok'], '200 characters, counted as characters');

	$empty = ws_agent_progress_check($running, "  \n ", $now);
	pg_assert_same(array(false, 422), array($empty['ok'], $empty['status']), 'an empty note');
}

function test_workspace_agent_progress_brake()
{
	$now = 1700000000;
	$row = array('status' => 'running', 'claimed_at' => $now - 600, 'ai_state' => ws_agent_progress_state(null, 'reading', $now - 10));

	$said = ws_agent_progress_check($row, 'next', $now);
	pg_assert_same(array(false, 429, 'rate_limited'), array($said['ok'], $said['status'], $said['code']), 'a second note inside 30 seconds');
	pg_assert_same(20, $said['retry_after'], 'when the next note may come');

	$row['ai_state'] = ws_agent_progress_state(null, 'reading', $now - WS_AGENT_PROGRESS_GAP);
	pg_assert_true(ws_agent_progress_check($row, 'next', $now)['ok'], '30 seconds later');
}

// The note sits beside what else the request carries, and leaves it be.
function test_workspace_agent_progress_state()
{
	$now = 1700000000;
	$wake = json_encode(array('wake' => array('kind' => 'every', 'note' => 'look', 'due' => 5)));

	$noted = ws_agent_progress_state($wake, 'reading the plans', $now);
	pg_assert_same(array('text' => 'reading the plans', 'at' => $now), ws_agent_progress_of(array('ai_state' => $noted)));
	pg_assert_same('look', ws_agent_wake_of(array('message_id' => 0, 'agent' => 'agent:3', 'ai_state' => $noted))['note'], 'the wake stays');

	$cleared = ws_agent_progress_state($noted, '', $now);
	pg_assert_same(null, ws_agent_progress_of(array('ai_state' => $cleared)), 'cleared');
	pg_assert_same(json_decode($wake, true), json_decode($cleared, true), 'only the note goes');

	pg_assert_same(null, ws_agent_progress_state(ws_agent_progress_state(null, 'x', $now), '', $now), 'nothing left: no state, as before');
	pg_assert_same(null, ws_agent_progress_of(array('ai_state' => null)));
	pg_assert_same(null, ws_agent_progress_of(array('ai_state' => 'not json')));
}

// The line under the asking message: the note and the claim's time while
// the agent holds the request; the answer takes its place.
function test_workspace_agent_progress_line()
{
	$now = 1700000000;
	$claimed = $now - 3600;
	$row = array('status' => 'running', 'claimed_at' => $claimed, 'ai_state' => ws_agent_progress_state(null, 'waiting for CI', $now));

	$line = ws_agent_request_progress_line($row);
	pg_assert_same('waiting for CI', $line['note']);
	pg_assert_contains(date('H:i', $claimed), $line['since'], 'since the claim');

	$row['ai_state'] = null;
	$plain = ws_agent_request_progress_line($row);
	pg_assert_same('', $plain['note'], 'no note yet');
	pg_assert_contains(date('H:i', $claimed), $plain['since'], 'the claim time shows without a note too');

	foreach (array('queued', 'answered', 'failed', 'cancelled') as $status) {
		pg_assert_same(array('note' => '', 'since' => ''), ws_agent_request_progress_line(array('status' => $status, 'claimed_at' => $claimed, 'ai_state' => ws_agent_progress_state(null, 'x', $now))), $status);
	}
}

function test_workspace_agent_progress_route()
{
	$found = null;

	foreach (ws_agent_request_api_routes() as $route) {
		if ($route['id'] === 'workspace.agent_requests.progress') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'the route is listed');
	pg_assert_same(array('POST', '/workspace/agent-requests/{id}/progress', 'workspace:write', false, true), array($found['method'], $found['path'], $found['scope'], $found['mcp'], $found['dry_run']), 'off MCP, with a dry run');

	$text = array_values(array_filter($found['params'], function ($param) {
		return $param['name'] === 'text';
	}))[0];
	pg_assert_same(WS_AGENT_PROGRESS_MAX, $text['max_length']);

	$schema = ws_api_claude_request_schema();
	pg_assert_same(array('text' => 'string', 'at' => 'string?'), $schema['progress'], 'the request\'s read carries it');

	pg_assert_contains('/workspace/agent-requests/{id}/progress', implode("\n", ws_agent_instructions_request_lines()), 'the instructions name the route');
}

// Every close the agent makes takes the note out; the site's own closes
// already empty the whole state.
function test_workspace_agent_progress_wiring()
{
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_agent_requests.php');

	pg_assert_same(2, substr_count($api, 'ws_agent_progress_clear('), 'answer and fail clear the note');
	pg_assert_contains('ws_agent_request_progress_line($row)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agent_shown.php'), 'the state line carries it');
	pg_assert_contains('state.since', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), 'claudeStateLine() shows it');
}
