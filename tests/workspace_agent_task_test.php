<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a task handed to a registry agent (includes/workspace/delegation.php,
 * agent_queue.php, api_agent_requests.php) that need no database: which keys
 * are agents', when the task's channel refuses one, the task in the agent's
 * brief, what an answer on a task takes, that an agent's key hands no task
 * over, and that Stop closes the agent's run as it does Claude's.
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

/**
 * A function's source without its line comments.
 *
 * @param string $function
 * @return string
 */
function agent_task_source_of($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

function test_workspace_agent_task_keys()
{
	pg_assert_true(ws_assistant_is_agent('agent:4'));
	pg_assert_true(ws_assistant_is_agent('model:12'));
	pg_assert_false(ws_assistant_is_agent('claude'));
	pg_assert_false(ws_assistant_is_agent('ai'));
	pg_assert_false(ws_assistant_is_agent('mcp:3'), 'an MCP server\'s alias keeps its own checks');
	pg_assert_false(ws_assistant_is_agent('app:4'), 'app:<n> is an application\'s proposal');
	pg_assert_false(ws_assistant_is_agent('agent:0'));
	pg_assert_false(ws_assistant_is_agent('agent:4x'));
}

// Allowed only for a task of a channel whose agent row allows the agent.
function test_workspace_agent_task_refusal()
{
	pg_assert_same('', ws_agent_task_refusal(true, true));
	pg_assert_same('not_allowed', ws_agent_task_refusal(true, false), 'a channel that does not allow the agent');
	pg_assert_same('no_channel', ws_agent_task_refusal(false, false), 'a task in no channel');
	pg_assert_same('no_channel', ws_agent_task_refusal(false, true));
}

// The agent's checks replace the drawer's refusal; a closed task and a run
// under way are refused before them, as for every assistant.
function test_workspace_agent_task_checks_wiring()
{
	$problem = agent_task_source_of('ws_assistant_task_problem');
	pg_assert_true(strpos($problem, 'ws_agent_task_problem(') < strpos($problem, 'ws_assistant_takes_tasks('), 'an agent key is checked by its channel, not refused as off the drawer');

	$check = agent_task_source_of('ws_task_delegate_check');
	pg_assert_contains('ws_task_is_open(', $check, 'a closed task is refused');
	pg_assert_false(ws_task_is_open('done'));
	pg_assert_contains('ws_can_edit_task(', $check, 'only a person who may change the task hands it over');

	$run = agent_task_source_of('ws_task_delegation_run_check');
	pg_assert_contains('ws_agent_brake(', $run, 'the agents\' hourly brake holds');
	pg_assert_contains('ws_task_delegation_open_run(', $run, 'one run at a time on a task');
}

// A key an assistant or an agent works through hands no task over.
function test_workspace_agent_task_key_refused()
{
	foreach (array('ws_api_tasks_delegate', 'ws_api_tasks_delegate_stop') as $route) {
		$source = agent_task_source_of($route);
		$guard = strpos($source, 'ws_api_is_assistant()');

		pg_assert_true($guard !== false, $route . ' refuses an assistant\'s key');
		pg_assert_true($guard < strpos($source, 'api_dry_run_stop('), $route . ' refuses it before the dry run');
	}

	pg_assert_contains("'app_id'", agent_task_source_of('ws_agent_assistants'), 'an agent\'s application counts as an assistant\'s');
}

function test_workspace_agent_task_brief_lines()
{
	$read = array(
		'number'      => 'T-12',
		'title'       => 'Check the stock',
		'description' => 'Count the blue mugs',
		'status'      => 'todo',
		'priority'    => 'high',
		'due_date'    => '2026-11-20',
		'assignees'   => array('Ayşe <@user:3>'),
		'notes'       => array('2026-11-14 10:00 · Ayşe: started'),
	);
	$earlier = array(array('agent' => '@mimar', 'asked_by' => 'Ayşe', 'instruction' => 'first look', 'answer' => 'done', 'error' => ''));
	$text = implode("\n", ws_agent_brief_task_lines($read, true, $earlier, 12, 345));

	pg_assert_contains('T-12 was handed to you', $text);
	pg_assert_contains('Check the stock', $text);
	pg_assert_contains('Count the blue mugs', $text);
	pg_assert_contains('due 2026-11-20', $text);
	pg_assert_contains('Ayşe <@user:3>', $text);
	pg_assert_contains('started', $text, 'the task\'s notes');
	pg_assert_contains('first look', $text, 'the runs before this one');
	pg_assert_contains('GET /workspace/tasks/12', $text);
	pg_assert_contains('/workspace/agent-requests/345/answer', $text);
	pg_assert_contains('not as a message', $text, 'the answer is the run\'s note on the task');
	pg_assert_contains('<<<', $text, 'the task\'s words sit in a data block');

	$asked = implode("\n", ws_agent_brief_task_lines($read, false, array(), 12, 345));
	pg_assert_contains('asked on the task T-12', $asked);
	pg_assert_false(strpos($asked, 'handed to you') !== false, 'a run that was not handed over says so');
}

function test_workspace_agent_task_brief_wiring()
{
	$brief = agent_task_source_of('ws_agent_brief');

	pg_assert_contains('ws_task_delegation_context(', $brief);
	pg_assert_contains('ws_agent_brief_task_lines(', $brief);
	pg_assert_contains("note_text", $brief, 'the instruction is the request');
}

function test_workspace_agent_task_answer_refusal()
{
	pg_assert_same('', ws_agent_task_answer_refusal(array('text' => 'Counted: 14'))['field']);
	pg_assert_same('text', ws_agent_task_answer_refusal(array('text' => '  '))['field']);
	pg_assert_same('no_answer', ws_agent_task_answer_refusal(array('no_answer' => true))['field'], 'a run on a task is answered, as Claude\'s');
	pg_assert_same('emoji', ws_agent_task_answer_refusal(array('text' => 'ok', 'emoji' => '👍'))['field'], 'no message to leave an emoji on');
	pg_assert_same('conclude', ws_agent_task_answer_refusal(array('text' => 'ok', 'conclude' => true))['field']);
	pg_assert_same('offer_conclude', ws_agent_task_answer_refusal(array('text' => 'ok', 'offer_conclude' => true))['field']);
}

// The answer and the failure write the run's note and tell the asker, as
// Claude's do.
function test_workspace_agent_task_answer_and_fail_wiring()
{
	$answer = agent_task_source_of('ws_api_agent_request_answer');
	pg_assert_true(strpos($answer, 'ws_api_agent_task_answer(') < strpos($answer, 'ws_api_agent_request_place('), 'a run on a task is answered before any channel place is looked for');

	$task = agent_task_source_of('ws_api_agent_task_answer');
	pg_assert_contains('ws_task_delegation_answer(', $task);
	pg_assert_contains('ws_agent_task_problem(', $task, 'refused once the channel no longer allows the agent');
	pg_assert_true(strpos($task, 'api_dry_run_stop(') < strpos($task, 'ws_task_delegation_answer('));

	pg_assert_contains("ws_task_delegation_told(\$row, 'failed')", agent_task_source_of('ws_api_agent_request_fail'));
}

// Stop cancels the run under way on the task whoever's it is, the agent's
// included; the agent's next claim or answer then meets 409 closed.
function test_workspace_agent_task_stop()
{
	$stop = agent_task_source_of('ws_task_delegation_stop');

	pg_assert_contains("status = 'cancelled'", $stop);
	pg_assert_contains("WHERE task_id = '\" . (int) \$task['id'] . \"' AND status IN ('queued', 'sent', 'running')", $stop, 'every open run on the task, not one assistant\'s');
	pg_assert_contains("'closed'", agent_task_source_of('ws_api_agent_request_answer'));
	pg_assert_contains("'closed'", agent_task_source_of('ws_api_agent_request_claim'));
	pg_assert_same('', ws_task_delegation_stop_problem(true, false, false), 'the person who asked stops the run');
}
