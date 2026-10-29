<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assistant a discussion follows, that need no database: whom
 * a message leaves followed and whom it asks, what an open request of the
 * followed agent does with a new message, and where the column is added and
 * asked for.
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

function workspace_thread_follow_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The calls, not the comments that name them.
	return preg_replace('~^\s*(//|\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// Only the three kinds of key are kept.
function test_workspace_thread_follow_clean()
{
	pg_assert_same('claude', ws_thread_follow_clean(' Claude '));
	pg_assert_same('ai', ws_thread_follow_clean('ai'));
	pg_assert_same('mcp:12', ws_thread_follow_clean('mcp:12'));
	pg_assert_same('', ws_thread_follow_clean('mcp:0'), 'no server');
	pg_assert_same('', ws_thread_follow_clean('mcp:x'));
	pg_assert_same('', ws_thread_follow_clean('bot'));
	pg_assert_same('', ws_thread_follow_clean(''));
}

// A person's message that asks nobody and mentions nobody asks the followed
// agent; one that mentions only colleagues does not.
function test_workspace_thread_follow_asks_followed()
{
	pg_assert_same(array('follow' => 'claude', 'ask' => 'claude'), ws_thread_follow_decide('claude', array(), true, false), 'asks nobody');
	pg_assert_same(array('follow' => 'claude', 'ask' => ''), ws_thread_follow_decide('claude', array(), true, true), 'mentions only colleagues');
	pg_assert_same(array('follow' => '', 'ask' => ''), ws_thread_follow_decide('', array(), true, false), 'nobody followed');
	pg_assert_same(array('follow' => 'mcp:4', 'ask' => 'mcp:4'), ws_thread_follow_decide('mcp:4', array(), true, false), 'an MCP agent');
}

// The last agent asked by name is followed from then on, and is asked by its
// name only.
function test_workspace_thread_follow_last_asked()
{
	pg_assert_same(array('follow' => 'ai', 'ask' => ''), ws_thread_follow_decide('claude', array('ai'), true, false), 'another agent asked');
	pg_assert_same(array('follow' => 'ai', 'ask' => ''), ws_thread_follow_decide('', array('claude', 'ai'), true, false), 'the last of two');
	pg_assert_same(array('follow' => 'claude', 'ask' => ''), ws_thread_follow_decide('claude', array('claude'), true, true), 'the followed one by name');
	pg_assert_same(array('follow' => 'mcp:7', 'ask' => ''), ws_thread_follow_decide('ai', array('mcp:7'), true, false), 'an agent server');
}

// An application or a bot never asks, and changes nothing: two agents never
// answer each other.
function test_workspace_thread_follow_not_a_person()
{
	pg_assert_same(array('follow' => 'claude', 'ask' => ''), ws_thread_follow_decide('claude', array(), false, false));
	pg_assert_same(array('follow' => 'claude', 'ask' => ''), ws_thread_follow_decide('claude', array('ai'), false, false), 'a bot naming another agent');
}

// A mention is a person, a team or an application; a record tag is not.
function test_workspace_thread_follow_mentions()
{
	pg_assert_true(ws_thread_body_mentions('<@user:4> look at this'));
	pg_assert_true(ws_thread_body_mentions('<@dept:2> please'));
	pg_assert_false(ws_thread_body_mentions('what about <#order:31>?'));
	pg_assert_false(ws_thread_body_mentions('and the price?'));
}

// An open request that was sent, or that asked by name, takes the new
// message with it; queued ones that only followed give way to a new one.
function test_workspace_thread_follow_pending_plan()
{
	pg_assert_same(array('queue' => true, 'drop' => array()), ws_thread_follow_pending_plan(array()), 'nothing open');
	pg_assert_same(array('queue' => true, 'drop' => array(5)), ws_thread_follow_pending_plan(array(array('id' => 5, 'status' => 'queued', 'named' => false))), 'a queued follow request');
	pg_assert_same(array('queue' => false, 'drop' => array()), ws_thread_follow_pending_plan(array(array('id' => 5, 'status' => 'sent', 'named' => false))), 'a run took it');
	pg_assert_same(array('queue' => false, 'drop' => array()), ws_thread_follow_pending_plan(array(array('id' => 5, 'status' => 'queued', 'named' => true))), 'asked by name');
}

// The step adds the column with the idempotent helper, and every read and
// write asks for it by its exact name first.
function test_workspace_thread_follow_schema()
{
	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains("upgrade_2026_11_0_workspace_thread_follow(); // 11.0.113", $migration, 'called with its label');
	pg_assert_contains("install_add_column('ws_threads', 'follow_agent', \"VARCHAR(64) NOT NULL DEFAULT ''\")", $migration);
	pg_assert_contains("pg_schema_has('ws_threads', 'follow_agent')", workspace_thread_follow_test_source('ws_thread_follow_ready'));

	foreach (array('ws_thread_follow_agent', 'ws_thread_follow_set') as $function) {
		pg_assert_contains('ws_thread_follow_ready()', workspace_thread_follow_test_source($function), $function . ' asks first');
	}

	pg_assert_contains('ws_thread_follow_ready()', workspace_thread_follow_test_source('ws_message_ask_assistants'), 'the send asks first');
	pg_assert_contains('ws_thread_follow_ready()', workspace_thread_follow_test_source('ws_thread_start'), 'the start asks first');
}

// A scheduled message asks as the writing box does, so it follows too.
function test_workspace_thread_follow_scheduled_send()
{
	$run = workspace_thread_follow_test_source('ws_scheduled_message_run');

	pg_assert_contains('ws_message_ask_assistants(', $run);
	pg_assert_same(false, strpos($run, 'ws_claude_after_send('), 'no hook called by itself');
	pg_assert_contains('ws_thread_follow_ask(', workspace_thread_follow_test_source('ws_message_ask_assistants'));
}

// Claude's instructions and the brief say a followed discussion's request
// need not name the assistant.
function test_workspace_thread_follow_prompts()
{
	pg_assert_contains('In a discussion that follows you a request need not name you', ws_claude_routine_prompt('https://example.test/api/v1'));
	pg_assert_contains('ws_ai_followed_sentence()', workspace_thread_follow_test_source('ws_ai_briefing'));
	pg_assert_contains('Answer the latest messages', ws_ai_followed_sentence());
}
