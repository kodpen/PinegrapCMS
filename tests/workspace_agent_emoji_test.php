<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the emoji an agent leaves on the message that asked it, that need
 * no database: which emoji and which message it may go on, the brief that
 * offers it, the answer route and the model assistant's answer tool.
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

function workspace_agent_emoji_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// One emoji, on a message somebody else wrote.
function test_workspace_agent_emoji_problem()
{
	$asked = array('id' => 40, 'sender_kind' => 'user', 'sender_id' => 7);

	pg_assert_same('', ws_agent_emoji_problem('🎉', $asked, 7), 'a person\'s message, whatever their id');
	pg_assert_same('', ws_agent_emoji_problem(' 👍 ', $asked, 7), 'trimmed');
	pg_assert_same('', ws_agent_emoji_problem('❤️', array('id' => 41, 'sender_kind' => 'app', 'sender_id' => 3), 7), 'another application\'s message');
	pg_assert_true(ws_agent_emoji_problem('ok', $asked, 7) !== '', 'not an emoji');
	pg_assert_true(ws_agent_emoji_problem('🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉', $asked, 7) !== '', 'more than one');
	pg_assert_true(ws_agent_emoji_problem('🎉', array('id' => 42, 'sender_kind' => 'app', 'sender_id' => 7), 7) !== '', 'its own message');
	pg_assert_true(ws_agent_emoji_problem('🎉', null, 7) !== '', 'a wake asked by no message');
	pg_assert_true(ws_agent_emoji_problem('🎉', array('id' => 0, 'sender_kind' => 'user', 'sender_id' => 1), 7) !== '');
}

// The brief offers it beside the text and no_answer.
function test_workspace_agent_emoji_brief()
{
	$tools = implode("\n", ws_agent_brief_tool_lines('https://site.example/mcp.php', array('workspace:write'), 12));

	pg_assert_contains('emoji', $tools);
	pg_assert_contains('/workspace/agent-requests/12/answer', $tools);

	$guard = implode("\n", ws_agent_loop_guard_lines());

	pg_assert_contains('one fitting emoji', $guard);
	pg_assert_contains('never on your own message', $guard);
}

// The route takes an optional emoji, checked before the dry run, and leaves
// it as the application's reaction.
function test_workspace_agent_emoji_answer_route()
{
	$route = null;

	foreach (ws_agent_request_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.agent_requests.answer') {
			$route = $candidate;
		}
	}

	pg_assert_true(is_array($route));
	pg_assert_contains('emoji', implode(',', array_column($route['params'], 'name')));

	$source = workspace_agent_emoji_test_source('ws_api_agent_request_answer');

	pg_assert_contains('ws_agent_emoji_problem(', $source);
	pg_assert_contains('ws_reaction_check(', $source);
	pg_assert_true(strpos($source, 'ws_agent_emoji_problem(') < strpos($source, 'api_dry_run_stop('), 'refused before the dry run');
	pg_assert_contains('ws_reaction_set(', $source);
}

// A model assistant's answer tool takes the emoji where a channel message
// asked it, and nowhere else.
function test_workspace_agent_emoji_model_answer()
{
	$base = array('agent' => 'model:3', 'message_id' => 40, 'note_id' => 0, 'task_id' => 0, 'page_id' => 0, 'mcp_server_id' => 0);

	pg_assert_true(ws_ai_answer_takes_emoji($base));
	pg_assert_false(ws_ai_answer_takes_emoji(array('agent' => 'ai') + $base), 'Pinegrap AI');
	pg_assert_false(ws_ai_answer_takes_emoji(array('message_id' => 0) + $base), 'no asking message');
	pg_assert_false(ws_ai_answer_takes_emoji(array('note_id' => 5) + $base), 'asked in a note');
	pg_assert_false(ws_ai_answer_takes_emoji(array('task_id' => 5) + $base), 'asked on a task');
	pg_assert_false(ws_ai_answer_takes_emoji(array('mcp_server_id' => 2) + $base), 'an MCP server\'s alias');

	$step = workspace_agent_emoji_test_source('ws_ai_step');

	pg_assert_contains("\$call['args']['emoji']", $step);
	pg_assert_contains('ws_model_close_with_emoji(', $step, 'the emoji alone closes the request');
}
