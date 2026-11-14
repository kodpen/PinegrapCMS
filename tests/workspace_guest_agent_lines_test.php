<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agents' lines on the guest page (includes/workspace/guests.php):
 * which senders a guest reads, the WHERE part that lists them, and that
 * nothing a guest does - a tag, an answer to an agent's message, an emoji,
 * a vote, a tick - makes a request for an agent or an assistant.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in this file runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/guests.php');

function test_ws_guest_line_sender_reads_people_guests_and_agents()
{
	$agents = array(7, 9);

	pg_assert_same('person', ws_guest_line_sender(array('sender_kind' => 'user', 'sender_id' => 3, 'kind' => 'message'), $agents));
	pg_assert_same('guest', ws_guest_line_sender(array('sender_kind' => 'guest', 'sender_id' => 2, 'kind' => 'message'), $agents));
	pg_assert_same('agent', ws_guest_line_sender(array('sender_kind' => 'app', 'sender_id' => 7, 'kind' => 'message'), $agents));
	pg_assert_same('agent', ws_guest_line_sender(array('sender_kind' => 'app', 'sender_id' => '9', 'kind' => 'decision'), $agents));
}

function test_ws_guest_line_sender_draws_an_mcp_answer_as_the_agent()
{
	pg_assert_same('agent', ws_guest_line_sender(array('sender_kind' => 'user', 'sender_id' => 3, 'kind' => 'message'), array(), true));
}

function test_ws_guest_line_sender_leaves_out_other_applications_and_system_lines()
{
	// CI and integrations are applications that are not agents.
	pg_assert_same('', ws_guest_line_sender(array('sender_kind' => 'app', 'sender_id' => 4, 'kind' => 'message'), array(7)));
	pg_assert_same('', ws_guest_line_sender(array('sender_kind' => 'system', 'sender_id' => 0, 'kind' => 'system'), array(7)));
	pg_assert_same('', ws_guest_line_sender(array('sender_kind' => 'mail', 'sender_id' => 1, 'kind' => 'message'), array(7)));
	pg_assert_same('', ws_guest_line_sender(array('sender_kind' => 'user', 'sender_id' => 3, 'kind' => 'system'), array(7)));
	// An agent's task card stays the team's.
	pg_assert_same('', ws_guest_line_sender(array('sender_kind' => 'app', 'sender_id' => 7, 'kind' => 'task'), array(7)));
}

function test_ws_guest_lines_sql_without_agents_takes_people_and_guests()
{
	pg_assert_same("sender_kind IN ('user', 'guest')", ws_guest_lines_sql(array()));
}

function test_ws_guest_lines_sql_adds_the_agents_applications_as_numbers()
{
	pg_assert_same("(m.sender_kind IN ('user', 'guest') OR (m.sender_kind = 'app' AND m.kind <> 'task' AND m.sender_id IN (7,9)))",
		ws_guest_lines_sql(array(7, '9', "9' OR '1", 0, -2), 'm.'));
}

/**
 * The source of one function of guests.php.
 *
 * @param string $name
 * @return string
 */
function ws_guest_agent_lines_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

/**
 * Asserts a guest's action calls nothing that asks an agent or an
 * assistant: no wake, no request row, no knock.
 *
 * @param string $name
 */
function ws_guest_agent_lines_test_asks_nobody($name)
{
	$source = ws_guest_agent_lines_test_source($name);

	foreach (array('ws_agent_after_send', 'ws_agent_after_app_send', 'ws_claude_after_send', 'ws_message_ask', 'ws_message_asked',
		'ws_ai_request', 'ws_ai_ask', 'ws_mcp_agent_ask', 'ws_mcp_ask', 'ws_thread_follow_ask', 'ws_agent_knock', 'ws_agent_wake',
		'ws_question_ring', 'INSERT INTO ws_ai_requests') as $asking) {
		pg_assert_same(false, stripos($source, $asking) !== false, $name . ' calls ' . $asking);
	}
}

function test_a_guest_tag_is_taken_out_and_asks_nobody()
{
	pg_assert_same('please look', ws_guest_clean_text('<@app:7> please look'));
	pg_assert_same('hi', ws_guest_clean_text('<@user:3>hi'));
	ws_guest_agent_lines_test_asks_nobody('ws_guest_send');
}

function test_a_guest_answer_to_an_agent_message_asks_nobody()
{
	// The "replies to its messages" rule is read only where a message of
	// the team is sent (ws_agent_after_send()); a guest's answer is written
	// by ws_guest_send() alone.
	$source = ws_guest_agent_lines_test_source('ws_guest_send');
	pg_assert_same(true, strpos($source, 'parent_id') !== false);
	pg_assert_same(false, strpos($source, 'ws_message_send(') !== false);
	ws_guest_agent_lines_test_asks_nobody('ws_guest_send');
}

function test_a_guest_emoji_asks_nobody()
{
	ws_guest_agent_lines_test_asks_nobody('ws_guest_react');
}

function test_a_guest_vote_asks_nobody()
{
	ws_guest_agent_lines_test_asks_nobody('ws_guest_vote');
}

function test_a_guest_tick_asks_nobody()
{
	ws_guest_agent_lines_test_asks_nobody('ws_guest_check');
}
