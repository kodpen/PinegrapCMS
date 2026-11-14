<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent concluding a discussion when a person in it asks, that
 * need no database: who may ask, the one sentence the brief and the answer
 * tool carry, and the answer route's and the model's answer tool's reach.
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

function workspace_agent_conclude_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function workspace_agent_conclude_test_case()
{
	return array(
		'thread'  => array('channel_id' => 90, 'message_id' => 12, 'closed_at' => 0),
		'row'     => array('id' => 5, 'channel_id' => 90, 'message_id' => 300, 'requested_by' => 7),
		'message' => array('id' => 300, 'channel_id' => 90, 'sender_kind' => 'user', 'sender_id' => 7, 'via_client_id' => 0, 'deleted_at' => 0),
	);
}

// A person in the discussion asked: it may be concluded.
function test_workspace_agent_conclude_person_in_it()
{
	$case = workspace_agent_conclude_test_case();

	pg_assert_same('', ws_thread_agent_close_problem($case['thread'], $case['row'], $case['message'], true));
}

// A person who reads the channel but is not in the discussion.
function test_workspace_agent_conclude_person_not_in_it()
{
	$case = workspace_agent_conclude_test_case();

	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $case['message'], false) !== '');
}

// A guest's line never concludes, even if the guest were counted in.
function test_workspace_agent_conclude_guest()
{
	$case = workspace_agent_conclude_test_case();
	$case['message']['sender_kind'] = 'guest';

	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $case['message'], true) !== '');
}

// Another agent's message, an application's or one written through an OAuth
// connection is no person's own words.
function test_workspace_agent_conclude_another_agent()
{
	$case = workspace_agent_conclude_test_case();
	$app = $case['message'];
	$app['sender_kind'] = 'app';
	$app['sender_id'] = 3;
	$via = $case['message'];
	$via['via_client_id'] = 4;
	$mail = $case['message'];
	$mail['sender_kind'] = 'mail';

	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $app, true) !== '', 'an application');
	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $via, true) !== '', 'through a connection');
	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $mail, true) !== '', 'a mail');
}

// Already concluded: nothing more to do, and the reason says so.
function test_workspace_agent_conclude_already_concluded()
{
	$case = workspace_agent_conclude_test_case();
	$case['thread']['closed_at'] = 1700000000;

	pg_assert_same(lang('The discussion is already concluded.'), ws_thread_agent_close_problem($case['thread'], $case['row'], $case['message'], true));
}

// Not a discussion, no message, a message of another place, a deleted one,
// a message whose writer is not the request's asker.
function test_workspace_agent_conclude_other_places()
{
	$case = workspace_agent_conclude_test_case();
	$elsewhere = $case['message'];
	$elsewhere['channel_id'] = 91;
	$deleted = $case['message'];
	$deleted['deleted_at'] = 1700000000;
	$other = $case['message'];
	$other['sender_id'] = 8;

	pg_assert_true(ws_thread_agent_close_problem(null, $case['row'], $case['message'], true) !== '', 'a channel');
	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], null, true) !== '', 'a wake');
	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $elsewhere, true) !== '', 'another place');
	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $deleted, true) !== '', 'deleted');
	pg_assert_true(ws_thread_agent_close_problem($case['thread'], $case['row'], $other, true) !== '', 'another writer');
}

// One sentence, from one source: the app agent's brief and the model's
// answer tool both read it.
function test_workspace_agent_conclude_one_sentence()
{
	$sentence = ws_thread_agent_close_sentence();

	pg_assert_true(strpos($sentence, 'conclude') !== false);
	pg_assert_same(1, preg_match_all('/[.!?](\s|$)/', $sentence), 'one sentence');
	pg_assert_contains('ws_thread_agent_close_sentence()', workspace_agent_conclude_test_source('ws_agent_brief'));
	pg_assert_contains('ws_thread_agent_close_sentence()', workspace_agent_conclude_test_source('ws_ai_tools'));
}

// The answer route takes conclude, checks it before the dry run stops, and
// concludes after the answer (or no_answer) is written.
function test_workspace_agent_conclude_answer_route()
{
	$route = null;

	foreach (ws_agent_request_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.agent_requests.answer') {
			$route = $candidate;
		}
	}

	pg_assert_true(is_array($route));
	$names = array_map(function ($param) {
		return $param['name'];
	}, $route['params']);
	pg_assert_true(in_array('conclude', $names, true), 'conclude is a parameter');
	pg_assert_true(isset($route['returns']['conclude']), 'the answer says how concluding went');

	$source = workspace_agent_conclude_test_source('ws_api_agent_request_answer');
	pg_assert_contains('ws_thread_close_for_agent(', $source);

	// The check comes before both dry-run stops; the write after the update.
	$check = strpos($source, "ws_thread_close_for_agent(\$row, (string) \$agent['handle'], true)");
	$stop = strpos($source, 'api_dry_run_stop(');
	$write = strpos($source, "ws_thread_close_for_agent(\$row, (string) \$agent['handle'], false)");
	$update = strpos($source, "UPDATE ws_ai_requests SET status = 'answered'");
	pg_assert_true(($check !== false) && ($stop !== false) && ($check < $stop), 'checked before the dry run stops');
	pg_assert_true(($write !== false) && ($update !== false) && ($update < $write), 'concluded after the answer is written');
}

// The model's answer tool: conclude only where it may, and no_answer with
// conclude (no words, no emoji) closes the request too.
function test_workspace_agent_conclude_model_tool()
{
	pg_assert_same(false, ws_ai_answer_takes_conclude(array('agent' => 'claude', 'message_id' => 3, 'channel_id' => 90)), 'not a model assistant');
	pg_assert_same(false, ws_ai_answer_takes_conclude(array('agent' => 'model:2', 'message_id' => 0, 'channel_id' => 90)), 'no message asked');

	$source = workspace_agent_conclude_test_source('ws_ai_step');
	pg_assert_contains('ws_model_conclude_after(', $source);
	pg_assert_contains("\$conclude", $source);
}
