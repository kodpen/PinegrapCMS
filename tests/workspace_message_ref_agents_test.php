<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agents' side of message references
 * (ws_message_ref_agent_rule() in includes/workspace/message_refs.php): every
 * agent's instructions tell it to name the picture, file or earlier message a
 * question or an answer is about with its <#message:ID> tag.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The rule names the token and what it is for.
function test_ws_message_ref_agent_rule_names_the_token()
{
	$rule = ws_message_ref_agent_rule();

	pg_assert_contains('<#message:ID>', $rule, 'the token');
	pg_assert_contains('picture', $rule, 'a picture is named');
}

// An application agent's instructions carry the rule.
function test_ws_message_ref_agent_rule_in_the_agent_instructions()
{
	$agent = array('id' => 3, 'name' => 'Helper', 'handle' => 'helper', 'role' => 'assistant', 'door' => 'poll', 'door_url' => '', 'wake_rule' => 'mention');
	$text = ws_agent_instructions_text($agent, array(), 'https://example.com/pinegrap/api/v1');

	pg_assert_contains(ws_message_ref_agent_rule(), $text, 'agent instructions');
}

// Claude's routine text carries the rule.
function test_ws_message_ref_agent_rule_in_the_claude_routine()
{
	$text = preg_replace('/\s+/', ' ', ws_claude_routine_text('https://example.com/pinegrap/api/v1', ''));

	pg_assert_contains(ws_message_ref_agent_rule(), $text, 'Claude routine');
}

// Pinegrap AI's prompt and its answer tool name the rule and the token.
function test_ws_message_ref_agent_rule_in_pinegrap_ai()
{
	pg_assert_contains('ws_message_ref_agent_rule()', pg_test_function_source('ws_ai_system_prompt'), 'Pinegrap AI prompt');
	pg_assert_contains('<#message:', pg_test_function_source('ws_ai_tools'), 'answer tool');
}

// The poll API's question says how to point at a message.
function test_ws_message_ref_in_the_poll_question()
{
	pg_assert_contains('earlier message points at it with <#message:N>', pg_test_function_source('ws_api_routes'), 'the question param');
}
