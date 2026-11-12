<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the registry's agents show (includes/workspace/agent_shown.php)
 * that need no database: the request value read back, the line under a
 * message, the @ picker's entries, the agent's tag, its own messages, and
 * that no task is handed to an agent.
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

function test_workspace_agent_request_parse()
{
	pg_assert_same(array('kind' => 'app', 'id' => 4), ws_agent_request_parse('agent:4'));
	pg_assert_same(null, ws_agent_request_parse('app:4'), 'app:<n> is an application\'s proposal');
	pg_assert_same(array('kind' => 'model', 'id' => 12), ws_agent_request_parse('model:12'));
	pg_assert_same(null, ws_agent_request_parse('claude'));
	pg_assert_same(null, ws_agent_request_parse('mcp:3'), 'an MCP server is not a registry agent');
	pg_assert_same(null, ws_agent_request_parse('agent:0'));
	pg_assert_same(null, ws_agent_request_parse('agent:4x'));
}

function test_workspace_agent_request_label()
{
	$now = 1700000000;

	pg_assert_same('bi-hourglass-split', ws_agent_request_label('queued', '@mimar', '', 0, $now)['icon']);
	pg_assert_contains('@mimar', ws_agent_request_label('queued', '@mimar', '', 0, $now)['label']);
	pg_assert_contains(date('H:i', $now + 120), ws_agent_request_label('queued', '@mimar', '', $now + 120, $now)['label'], 'a held door says the next try');
	pg_assert_same(ws_agent_request_label('queued', '@mimar', '', 0, $now)['label'], ws_agent_request_label('queued', '@mimar', '', $now - 5, $now)['label'], 'a hold gone by is plain waiting');
	pg_assert_same('bi-robot', ws_agent_request_label('running', '@mimar', '', 0, $now)['icon']);
	pg_assert_same('bi-robot', ws_agent_request_label('sent', '@mimar', '', 0, $now)['icon']);
	pg_assert_same('bi-check2-circle', ws_agent_request_label('answered', '@mimar', '', 0, $now)['icon']);
	pg_assert_contains('timeout', ws_agent_request_label('failed', '@mimar', 'timeout', 0, $now)['label'], 'a failure carries its reason');
	pg_assert_same('bi-x-circle', ws_agent_request_label('cancelled', '@mimar', '', 0, $now)['icon']);
}

// A request closed without text keeps the eye and says so; the tick and
// "answered" only with a reply.
function test_workspace_agent_request_label_no_answer()
{
	$now = 1700000000;
	$seen = ws_agent_request_label('answered', '@mimar', '', 0, $now, false);

	pg_assert_same('bi-eye', $seen['icon']);
	pg_assert_contains('@mimar', $seen['label']);
	pg_assert_true($seen['label'] !== ws_agent_request_label('answered', '@mimar', '', 0, $now)['label'], 'not "answered"');
	pg_assert_same('bi-check2-circle', ws_agent_request_label('answered', '@mimar', '', 0, $now, true)['icon']);
	pg_assert_same('bi-robot', ws_agent_request_label('running', '@mimar', '', 0, $now, false)['icon'], 'only a closed request reads as seen');
}

function test_workspace_agent_request_replied()
{
	pg_assert_true(ws_agent_request_replied(array('reply_message_id' => 12)));
	pg_assert_true(ws_agent_request_replied(array('reply_message_id' => 0, 'note_reply' => 'Done.')), 'an answer written in a note');
	pg_assert_false(ws_agent_request_replied(array('reply_message_id' => 0, 'note_reply' => '')));
	pg_assert_false(ws_agent_request_replied(array('reply_message_id' => '0')));
	pg_assert_true(ws_agent_request_no_answer(array('status' => 'answered', 'reply_message_id' => 0)));
	pg_assert_false(ws_agent_request_no_answer(array('status' => 'answered', 'reply_message_id' => 5)));
	pg_assert_false(ws_agent_request_no_answer(array('status' => 'running', 'reply_message_id' => 0)), 'still open');
}

// The route closing a request without text leaves the eye and adds no tick.
function test_workspace_agent_answer_route_keeps_eye()
{
	$reflection = new ReflectionFunction('ws_api_agent_request_answer');
	$source = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	$tick = strpos($source, "'✅', true");

	pg_assert_true($tick !== false);
	pg_assert_true(strpos(substr($source, 0, $tick), 'if ($reply_id > 0)') !== false, 'the tick only with a reply');
	pg_assert_contains('ws_agent_request_label((string) $row[\'status\'], $handle, (string) $row[\'error\'], $next_try, time(), ws_agent_request_replied($row))', (function () {
		$r = new ReflectionFunction('ws_agent_request_state');

		return implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
	})());
}

function test_workspace_agent_picker_entries()
{
	$entries = ws_agent_picker_entries(array(
		array('handle' => 'zeta', 'name' => 'Zeta', 'app_id' => 9),
		array('handle' => 'mimar', 'name' => 'ADNAN Mimar', 'app_id' => 7),
		array('handle' => 'twin', 'name' => 'Same app', 'app_id' => 7),
		array('handle' => 'noapp', 'name' => 'No app', 'app_id' => 0),
	));

	pg_assert_same(2, count($entries), 'one entry per application, none without one');
	pg_assert_same('@mimar', $entries[0]['label'], 'sorted by handle');
	pg_assert_same('<@app:7>', $entries[0]['token'], 'the tag wakes it under any rule');
	pg_assert_same('@mimar', $entries[0]['shown']);
	pg_assert_same('ADNAN Mimar', $entries[0]['meta']);
	pg_assert_same(array(), ws_agent_picker_entries(array()));
}

function test_workspace_agent_tag()
{
	$agents = array(7 => array('handle' => 'mimar', 'name' => 'ADNAN Mimar'));

	$tag = ws_app_tag(7, array('id' => 7, 'name' => 'Architect key'), 9, 5, array(), $agents);
	pg_assert_same('@mimar', $tag['label'], 'an agent\'s application reads as the agent');
	pg_assert_same('ADNAN Mimar', $tag['meta']);
	pg_assert_same('@mimar', ws_app_tag(7, null, 9, 5, array(), $agents)['label'], 'also once the application row is not read');
	pg_assert_same('@Claude', ws_app_tag(9, null, 9, 5, array(), array(9 => array('handle' => 'x', 'name' => '')))['label'], 'Claude\'s application stays Claude');
	pg_assert_same('@architect-key', ws_app_tag(7, array('id' => 7, 'name' => 'Architect key'), 9, 5)['label'], 'no agent: the application\'s own handle');
}

function test_workspace_message_is_own()
{
	$person = array('sender_kind' => 'user', 'sender_id' => 3);
	$app = array('sender_kind' => 'app', 'sender_id' => 7);

	pg_assert_true(ws_message_is_own($person, 3));
	pg_assert_false(ws_message_is_own($person, 4));
	pg_assert_false(ws_message_is_own($app, 7), 'a person never owns an application\'s message');
	pg_assert_true(ws_message_is_own($app, 3, 7), 'the agent\'s application its own');
	pg_assert_false(ws_message_is_own($app, 3, 8), 'another application\'s');
	pg_assert_false(ws_message_is_own($person, 3, 7), 'an agent never its owner\'s words');
}

function test_workspace_agent_takes_no_tasks()
{
	pg_assert_true(ws_assistant_takes_tasks(array('key' => 'claude')));
	pg_assert_false(ws_assistant_takes_tasks(array('key' => 'app:4', 'delegate' => false)));
	pg_assert_false(ws_assistant_takes_tasks(null));
}

function test_workspace_agent_shown_wiring()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/workspace/';
	$api = (string) file_get_contents($dir . 'api_resources.php');

	pg_assert_true(substr_count($api, 'ws_api_agent_own_app()') >= 2, 'the edit and the delete routes let an agent\'s key at its own messages');
	pg_assert_contains('ws_agent_request_state($row)', (string) file_get_contents($dir . 'claude.php'), 'the line under a message names the agent');
	pg_assert_contains('ws_agent_assistants()', (string) file_get_contents($dir . 'delegation.php'));
	pg_assert_contains('ws_agent_picker_entries(', (string) file_get_contents($dir . 'app_handles.php'));
	pg_assert_contains('ws_agent_tags()', (string) file_get_contents($dir . 'refs.php'));
}
