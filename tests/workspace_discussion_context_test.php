<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what an assistant reads about a discussion, that need no
 * database: the context of a discussion names the channel it was started in
 * and the message it talks over, the endpoint declares it, and Claude's
 * routine prompt says how to read it.
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

// The presenter's time helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

function workspace_discussion_context_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The calls, not the comments that name them.
	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// The channel the discussion was started in and the message it talks over,
// with ids as numbers and no task as null.
function test_workspace_discussion_context_thread()
{
	$parent = array('id' => '9', 'name' => 'products', 'kind' => 'private', 'summary' => 'not part of it');
	$about = array('id' => '470', 'kind' => 'message', 'sender_kind' => 'app', 'sender_id' => '3', 'task_id' => '0', 'created_at' => '1792580000', 'body' => '<@user:4> the list is ready');

	pg_assert_same(array(
		'channel' => array('id' => 9, 'name' => 'products', 'kind' => 'private'),
		'message' => array(
			'id'          => 470,
			'kind'        => 'message',
			'sender_kind' => 'app',
			'author'      => 'Claude',
			'text'        => '@Ayşe the list is ready',
			'task_id'     => null,
			'created_at'  => api_time(1792580000),
		),
	), ws_api_context_thread_present($parent, $about, 'Claude', '@Ayşe the list is ready'));

	$card = ws_api_context_thread_present($parent, array('kind' => 'task', 'task_id' => '31') + $about, 'Ayşe', '');

	pg_assert_same(31, $card['message']['task_id'], 'a task card names its task');
	pg_assert_same(array('channel' => array('id' => 9, 'name' => 'products', 'kind' => 'private'), 'message' => null), ws_api_context_thread_present($parent, null, '', ''), 'the message was deleted');
}

// The endpoint fills thread for a discussion alone, reads the message's tags
// with the rest, and declares it; the objects are in the catalogue.
function test_workspace_discussion_context_endpoint()
{
	$context = workspace_discussion_context_test_source('ws_api_channel_context');

	pg_assert_contains('ws_channel_is_thread($channel) ? ws_thread_parent_channel($channel) : null', $context, 'only a discussion has one');
	pg_assert_contains("((int) \$about['deleted_at'] === 0)", $context, 'a deleted message is left out');
	pg_assert_true(strpos($context, '$about ? array($about) : array()') < strpos($context, 'ws_refs_resolve($viewer, $tokens)'), 'its tags are resolved with the others');
	pg_assert_contains("'thread'     => \$thread", $context, 'answered');

	$schema = ws_api_channel_context_schema();
	$objects = ws_openapi_objects();

	pg_assert_same('WorkspaceContextThread?', $schema['thread'], 'declared, may be null');
	pg_assert_same('ws_api_context_thread_schema', $objects['WorkspaceContextThread'] ?? '', 'the thread object');
	pg_assert_same('ws_api_context_message_schema', $objects['WorkspaceContextMessage'] ?? '', 'the message object');
	pg_assert_same('WorkspaceContextMessage?', ws_api_context_thread_schema()['message'], 'the message may be null');
	pg_assert_same(array_keys(ws_api_context_message_schema()), array_keys(ws_api_context_thread_present(array('id' => 1, 'name' => 'a', 'kind' => 'public'), array('id' => 2, 'kind' => 'message', 'sender_kind' => 'user', 'task_id' => 0, 'created_at' => 1), 'A', 'b')['message']), 'the declaration matches the presenter');
}

// The routine is told what a discussion is about, through its brief. Read
// from the source: the prompt puts the site's address in, which the test
// runner does not have.
function test_workspace_discussion_context_routine_prompt()
{
	$prompt = workspace_discussion_context_test_source('ws_claude_routine_prompt');

	$briefing = workspace_discussion_context_test_source('ws_ai_briefing');

	pg_assert_contains('a discussion with the message it talks over', $prompt, 'the brief says what a discussion is about');
	pg_assert_contains('This is a discussion about message', $briefing, 'the brief carries the message the discussion talks over');
}
