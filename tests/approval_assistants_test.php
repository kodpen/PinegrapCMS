<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the assistants asked to approve (includes/workspace/
 * approval_agents.php): a model assistant and Pinegrap AI are named by their
 * request values, answer in the site's own run with the decide tool, and are
 * told so in their brief; the run's source answers an approval with decide
 * and never with words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in these files runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approval_agents.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/agent_queue.php');

// Pinegrap AI is kept as ai, Claude as claude.
function test_approval_assistants_clean_takes_pinegrap_ai()
{
	pg_assert_same(array('model:3', 'ai', 'agent:5', 'claude'), ws_approval_agents_clean(array('model:3', ' AI ', 'agent:5', 'ai', 'Claude', 'ai:1')));
}

// The value an agent answers under, Pinegrap AI's included.
function test_approval_assistants_value()
{
	pg_assert_same('ai', ws_approval_agent_value(array('kind' => 'ai', 'id' => 0)));
	pg_assert_same('model:4', ws_approval_agent_value(array('kind' => 'model', 'id' => 4)));
	pg_assert_same('agent:9', ws_approval_agent_value(array('kind' => 'app', 'id' => 9)));
	pg_assert_same('claude', ws_approval_agent_value(array('kind' => 'claude', 'id' => 1)), 'Claude answers as claude');
	pg_assert_same('', ws_approval_agent_value(array('kind' => 'mcp', 'id' => 1)), 'any other kind');
}

// A model assistant and Pinegrap AI are told to use the decide tool; an
// application agent the API route.
function test_approval_assistants_brief_names_the_tool()
{
	$approval = array('id' => 14, 'title' => 'Ship it', 'rule' => 'any', 'closes_at' => 0);
	$tool = implode("\n", ws_approval_agent_brief_lines($approval, true));
	$api = implode("\n", ws_approval_agent_brief_lines($approval));

	pg_assert_contains('decide tool', $tool);
	pg_assert_false(strpos($tool, 'POST /workspace/approvals') !== false, 'no API route for the site\'s run');
	pg_assert_contains('POST /workspace/approvals/14/decide', $api);
}

// The decide tool asks for a decision and a note.
function test_approval_assistants_decide_tool()
{
	$tool = ws_approval_agent_decide_tool();
	$parameters = $tool['function']['parameters'];

	pg_assert_same('decide', $tool['function']['name']);
	pg_assert_same(array('decision', 'note'), $parameters['required']);
	pg_assert_same(array('approve', 'reject'), $parameters['properties']->decision['enum']);
}

// Only the site's own runs answer in the loop.
function test_approval_assistants_run_agent_kinds()
{
	pg_assert_same(null, ws_approval_agent_run_agent(array('agent' => 'agent:5')), 'an application agent answers with its key');
	pg_assert_same(null, ws_approval_agent_run_agent(array('agent' => 'claude')), 'Claude\'s routine answers over the API');
	pg_assert_same('ai', ws_approval_agent_run_agent(array('agent' => 'ai'))['kind'] ?? '', 'Pinegrap AI');
}

// The run answers an approval with decide, keeps the approval in its state
// and never turns words into an answer for it (ai.php).
function test_approval_assistants_run_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');

	pg_assert_contains("\$state['approval'] = \$approval;", $source, 'ws_ai_start() keeps the approval');
	pg_assert_contains("return array(ws_approval_agent_decide_tool(), \$tools[1]);", $source, 'ws_ai_tools() offers decide and fail only');
	pg_assert_contains("if ((\$name === 'decide') && function_exists('ws_approval_agent_run_decide'))", $source, 'ws_ai_step() runs decide');
	pg_assert_contains("lang('The approval request was not answered with Approve or Reject.')", $source, 'words are no approval');
}

// Claude stands as claude with its handle; the card and the result name it @claude.
function test_approval_assistants_claude_row()
{
	$row = ws_approval_agent_claude_row();

	pg_assert_same('claude', $row['kind']);
	pg_assert_same('claude', $row['handle']);
	pg_assert_same('claude', ws_approval_agent_value($row));
	pg_assert_same('claude', ws_approval_agent_row('claude')['kind'] ?? '');
}

// A request to approve stays the routine's: the asker's own Claude session
// never answers it in their place (claude.php).
function test_approval_assistants_claude_request_stays_the_routines()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude.php');

	$now = 1700000010;
	$asked = array('id' => 4, 'status' => 'queued', 'message_id' => 70, 'note_id' => 0, 'task_id' => 0, 'page_id' => 0, 'claimed_device_id' => 0, 'created_at' => 1700000000);
	$approval = $asked + array('ai_state' => ws_approval_agent_request_state(array('id' => 8, 'title' => 'Ship it', 'rule' => 'any', 'closes_at' => 0)));

	pg_assert_same('person', ws_claude_request_holder($asked, false, true, $now)['who'], 'an ordinary request waits for the asker\'s session');
	pg_assert_same('routine', ws_claude_request_holder($approval, false, true, $now)['who'], 'a request to approve goes to the routine');
}
