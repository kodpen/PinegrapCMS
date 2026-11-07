<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the answer route of Claude's requests and the reply on
 * workspace.messages.create (includes/workspace/api_claude.php,
 * api_resources.php) that need no database: what "no answer needed" takes,
 * where it is refused, and the route declarations.
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

function test_workspace_claude_answer_needs_words_without_no_answer()
{
	pg_assert_same('', ws_api_claude_answer_input_refusal('channel', array('text' => 'Done.'))['field']);
	pg_assert_same('text', ws_api_claude_answer_input_refusal('channel', array('text' => '  '))['field'], 'blank words');
	pg_assert_same('text', ws_api_claude_answer_input_refusal('note', array())['field'], 'no words at all');
	pg_assert_same('text', ws_api_claude_answer_input_refusal('channel', array('text' => '', 'no_answer' => false))['field'], 'no_answer false is an ordinary answer');
}

function test_workspace_claude_answer_no_answer_closes_channel_and_summary_only()
{
	pg_assert_same('', ws_api_claude_answer_input_refusal('channel', array('no_answer' => true))['field']);
	pg_assert_same('', ws_api_claude_answer_input_refusal('summary', array('no_answer' => true, 'text' => ' '))['field'], 'blank words are no words');

	foreach (array('editor', 'note', 'task') as $origin) {
		pg_assert_same('no_answer', ws_api_claude_answer_input_refusal($origin, array('no_answer' => true))['field'], $origin . ' waits for words');
	}
}

function test_workspace_claude_answer_no_answer_takes_nothing_else()
{
	pg_assert_same('text', ws_api_claude_answer_input_refusal('channel', array('no_answer' => true, 'text' => 'Thanks'))['field']);
	pg_assert_same('tasks', ws_api_claude_answer_input_refusal('channel', array('no_answer' => true, 'tasks' => array(array('title' => 'x'))))['field']);
	pg_assert_same('changes', ws_api_claude_answer_input_refusal('channel', array('no_answer' => true, 'changes' => array(array('type' => 'product'))))['field']);
	pg_assert_same('', ws_api_claude_answer_input_refusal('channel', array('no_answer' => true, 'tasks' => array(), 'changes' => array()))['field'], 'empty lists are nothing sent');
}

function test_workspace_claude_answer_route_declarations()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$params = function ($id) use ($routes) {
		$out = array();

		foreach ($routes[$id]['params'] as $param) {
			$out[$param['name']] = $param;
		}

		return $out;
	};

	$answer = $params('workspace.claude.requests.answer');
	pg_assert_same('bool', $answer['no_answer']['type']);
	pg_assert_true(empty($answer['text']['required']), 'text may be left out with no_answer');

	$create = $params('workspace.messages.create');
	pg_assert_same('int', $create['parent_id']['type']);
	pg_assert_same(1, $create['parent_id']['min']);
	pg_assert_true(empty($create['parent_id']['required']), 'a message of its own by default');
}
