<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the words an application agent reads when it finds its way with
 * the API alone: the brief's tool line, the answer route's refusals and
 * description, and the refusal a paused agent meets on its own routes.
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

// The agent's own routes are not MCP tools: the brief says so.
function test_workspace_agent_words_brief_http_only()
{
	$tools = implode("\n", ws_agent_brief_tool_lines('https://site.example/mcp.php', array('workspace:write'), 12));

	pg_assert_contains('https://site.example/mcp.php', $tools);
	pg_assert_true(strpos($tools, 'every API route is a tool there') === false, 'no longer claims every route is a tool');
	pg_assert_contains('HTTP only', $tools);
	pg_assert_contains('/workspace/agent-requests/12/fail', $tools);
}

// A 403 answer names fail as the way to close; the no-channel 422 names the
// messages route.
function test_workspace_agent_words_answer_refusals()
{
	$forbidden = ws_agent_answer_refusal_text('forbidden', 12);
	pg_assert_contains('no longer allowed in that channel', $forbidden);
	pg_assert_contains('POST /workspace/agent-requests/12/fail', $forbidden);

	$nowhere = ws_agent_answer_refusal_text('no_channel', 12);
	pg_assert_contains('POST /workspace/channels/{id}/messages', $nowhere);
	pg_assert_contains('no_answer', $nowhere);
}

// The answer route's description names where the other things go.
function test_workspace_agent_words_answer_description()
{
	$row = null;

	foreach (ws_agent_request_api_routes() as $one) {
		if ($one['id'] === 'workspace.agent_requests.answer') {
			$row = $one;
		}
	}

	pg_assert_true(is_array($row), 'the answer route is declared');
	pg_assert_contains('/workspace/channels/{id}/polls', $row['description']);
	pg_assert_contains('/workspace/approvals', $row['description']);
	pg_assert_contains('/workspace/changes', $row['description']);
	pg_assert_contains('/workspace/agent-requests/{id}/fail', $row['description']);
}

// A paused agent learns why it is refused and where it is turned back on.
function test_workspace_agent_words_paused_caller()
{
	$paused = ws_agent_caller_refusal_text('mira');
	pg_assert_contains('@mira', $paused);
	pg_assert_contains('paused on the Agents card (Site Settings)', $paused);

	pg_assert_same('Only an active application agent, with its own key, may use this endpoint.', ws_agent_caller_refusal_text(''));
}
