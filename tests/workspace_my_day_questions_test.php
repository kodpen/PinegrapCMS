<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent questions on GET /workspace/my-day
 * (ws_api_my_day_present(), includes/workspace/api_day.php): the questions
 * waiting for the person are listed five at most while questions_count
 * tells how many wait, each row carries the poll, its message and channel,
 * the question cut to 160 characters, the asker in a message sender's shape
 * and the asking time; with none waiting the list is empty and the count 0.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// A day with nothing in it but the questions given.
function workspace_my_day_questions_test_day($questions)
{
	return array(
		'date'             => '2026-11-17',
		'left_out'         => array(),
		'tasks_open'       => 0,
		'tasks_overdue'    => 0,
		'tasks_due_today'  => 0,
		'tasks_new'        => 0,
		'due'              => array(),
		'timer'            => null,
		'plan_items'       => array(),
		'approvals'        => array(),
		'proposed_changes' => array(),
		'read_receipts'    => array(),
		'written_to_me'    => array(),
		'questions'        => $questions,
		'questions_count'  => count($questions),
		'inbox_unread'     => 0,
	);
}

// A row as ws_agent_questions_for() gives it.
function workspace_my_day_questions_test_row($id, $question = 'Which supplier?', $asker = null)
{
	return array(
		'id'         => $id,
		'message_id' => 100 + $id,
		'question'   => $question,
		'asker'      => ($asker !== null) ? $asker : array('id' => 0, 'app_id' => 7, 'name' => 'Claude', 'app' => true),
		'channel'    => array('id' => 3, 'name' => 'purchasing', 'private' => true),
		'time'       => '5 min',
		'created_at' => 1795000000,
		'url'        => '/pinegrap/workspace.php?channel=3&message=' . (100 + $id),
	);
}

function test_workspace_my_day_questions_row_shape()
{
	$day = ws_api_my_day_present(workspace_my_day_questions_test_day(array(workspace_my_day_questions_test_row(9))));

	pg_assert_same(1, $day['questions_count'], 'one waiting');
	pg_assert_same(array('id', 'message_id', 'channel', 'question', 'asker', 'asked_at'), array_keys($day['questions'][0]), 'the row carries no url and no time label');
	pg_assert_same(9, $day['questions'][0]['id'], 'the poll id');
	pg_assert_same(109, $day['questions'][0]['message_id'], 'the message id');
	pg_assert_same(array('id' => 3, 'name' => 'purchasing', 'private' => true), $day['questions'][0]['channel'], 'the channel');
	pg_assert_same(array('user_id' => null, 'name' => 'Claude'), $day['questions'][0]['asker'], 'an application asks without a user id');
	pg_assert_same(api_time(1795000000), $day['questions'][0]['asked_at'], 'an API time');
}

function test_workspace_my_day_questions_person_asker_keeps_user_id()
{
	$row = workspace_my_day_questions_test_row(1, 'Ok?', array('id' => 12, 'name' => 'Ayşe', 'username' => 'ayse'));

	pg_assert_same(array('user_id' => 12, 'name' => 'Ayşe'), ws_api_my_day_question_present($row)['asker'], 'a team member asks with their user id');
}

function test_workspace_my_day_questions_question_cut_at_160()
{
	$long = str_repeat('ş', 200);
	$out = ws_api_my_day_question_present(workspace_my_day_questions_test_row(1, $long));

	pg_assert_same(160, mb_strlen($out['question']), 'cut to 160 characters');
	pg_assert_same('…', mb_substr($out['question'], -1), 'ends with an ellipsis');
	pg_assert_same('Short', ws_api_my_day_question_present(workspace_my_day_questions_test_row(1, 'Short'))['question'], 'a short question stays');
}

function test_workspace_my_day_questions_five_listed_all_counted()
{
	$rows = array();

	for ($i = 1; $i <= 8; $i++) {
		$rows[] = workspace_my_day_questions_test_row($i);
	}

	$day = ws_api_my_day_present(workspace_my_day_questions_test_day($rows));

	pg_assert_same(5, count($day['questions']), 'five listed');
	pg_assert_same(8, $day['questions_count'], 'all counted');
	pg_assert_same(array(1, 2, 3, 4, 5), array_column($day['questions'], 'id'), 'the first five, in the order given');
}

function test_workspace_my_day_questions_none_waiting()
{
	$day = ws_api_my_day_present(workspace_my_day_questions_test_day(array()));

	pg_assert_same(array(), $day['questions'], 'an empty list');
	pg_assert_same(0, $day['questions_count'], 'count 0');
}

function test_workspace_my_day_questions_declared_in_schema()
{
	$schema = ws_api_my_day_schema();
	$day = ws_api_my_day_present(workspace_my_day_questions_test_day(array(workspace_my_day_questions_test_row(1))));

	pg_assert_same(array_keys($schema), array_keys($day), 'the schema declares every key the route gives');
	pg_assert_same(array_keys($schema['questions'][0]), array_keys($day['questions'][0]), 'the question row declared');
	pg_assert_same('WorkspaceMessageSender?', $schema['questions'][0]['asker'], 'the asker in a message sender shape');
	pg_assert_same('integer', $schema['questions_count'], 'the count');
}

function test_workspace_my_day_questions_read_like_today()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_day.php');

	pg_assert_true(strpos($source, 'ws_agent_questions_for($viewer, 20)') !== false, 'twenty read and counted, as Today counts them');
	pg_assert_true(preg_match('/ws_agent_questions_for\(\$viewer, 20\) as \$question\) \{\s*if \(\$allowed\(\$question\[\'channel\'\]\[\'id\'\]\)\)/', $source) === 1, 'a channel Claude may not be asked in is left out, as for the other channel rows');
}
