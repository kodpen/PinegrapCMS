<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the asker's own session over OAuth (includes/workspace/claude.php,
 * api_claude.php, agent_queue.php) that need no database: who may take a
 * request to Claude, the depth brake on a message a connection wrote, and
 * the routes a connection may call.
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

function workspace_claude_sessions_test_row($extra = array())
{
	return array_merge(array(
		'id'                => 7,
		'status'            => 'queued',
		'channel_id'        => 3,
		'message_id'        => 40,
		'requested_by'      => 12,
		'created_at'        => 1700000000,
		'claimed_device_id' => 0,
	), $extra);
}

function test_workspace_claude_holder_waits_for_the_online_asker()
{
	$row = workspace_claude_sessions_test_row();
	$now = 1700000000 + 30;

	pg_assert_same(array('who' => 'person', 'device' => 0), ws_claude_request_holder($row, false, true, $now), 'the asker is online: their session takes it first');
	pg_assert_same(array('who' => 'routine', 'device' => 0), ws_claude_request_holder($row, false, false, $now), 'nobody online: the routine as before');
	pg_assert_same(array('who' => 'routine', 'device' => 0), ws_claude_request_holder($row, false, true, 1700000000 + WS_CLAUDE_SESSION_WAIT), 'unclaimed after two minutes: the routine');
	pg_assert_same(120, WS_CLAUDE_SESSION_WAIT);
}

function test_workspace_claude_holder_of_a_claimed_request()
{
	$row = workspace_claude_sessions_test_row(array('status' => 'running', 'claimed_device_id' => 55));

	pg_assert_same(array('who' => 'person', 'device' => 55), ws_claude_request_holder($row, false, false, 1700009999), 'the session that claimed it keeps it');
	pg_assert_same(array('who' => 'routine', 'device' => 0), ws_claude_request_holder(workspace_claude_sessions_test_row(array('status' => 'running')), false, true, 1700000001), 'the routine claimed it');
}

function test_workspace_claude_holder_never_a_session_for_a_connections_message()
{
	// A message a connection wrote asks what the application's message asks
	// today: the site's routine, never the asker's own session, which would
	// be asking itself.
	$row = workspace_claude_sessions_test_row();

	pg_assert_same(array('who' => 'routine', 'device' => 0), ws_claude_request_holder($row, true, true, 1700000001));
}

function test_workspace_claude_holder_only_for_a_message()
{
	// The daily summary, a note, a task run and the Visual Page Editor go to
	// the routine as before.
	foreach (array(array('message_id' => 0), array('note_id' => 5), array('task_id' => 9), array('page_id' => 2)) as $extra) {
		pg_assert_same('routine', ws_claude_request_holder(workspace_claude_sessions_test_row($extra), false, true, 1700000001)['who'], json_encode($extra));
	}
}

function test_workspace_claude_session_may_take()
{
	$held = array('who' => 'person', 'device' => 0);
	$row = workspace_claude_sessions_test_row();

	pg_assert_true(ws_claude_caller_may($held, $row, 12, 55), 'the asker\'s connection');
	pg_assert_true(!ws_claude_caller_may($held, $row, 13, 56), 'somebody else\'s connection never');
	pg_assert_true(!ws_claude_caller_may($held, $row, 12, 0), 'the routine waits');
	pg_assert_true(ws_claude_caller_may(array('who' => 'person', 'device' => 55), $row, 12, 55), 'the claiming session');
	pg_assert_true(!ws_claude_caller_may(array('who' => 'person', 'device' => 55), $row, 12, 56), 'another session of the asker');
	pg_assert_true(ws_claude_caller_may(array('who' => 'routine', 'device' => 0), $row, 0, 0), 'the routine');
	pg_assert_true(!ws_claude_caller_may(array('who' => 'routine', 'device' => 0), $row, 12, 55), 'handed to the routine: the session no longer');
}

function test_workspace_agent_chain_counts_a_connections_message()
{
	pg_assert_true(ws_agent_chain_entry(array('id' => 1, 'sender_kind' => 'user', 'sender_id' => 12, 'via_client_id' => 4), array(9))['by_agent'], 'written through a connection: an agent\'s message');
	pg_assert_true(!ws_agent_chain_entry(array('id' => 1, 'sender_kind' => 'user', 'sender_id' => 12, 'via_client_id' => 0), array(9))['by_agent'], 'a person\'s own message ends the count');
	pg_assert_true(!ws_agent_chain_entry(array('id' => 1, 'sender_kind' => 'user', 'sender_id' => 12), array(9))['by_agent'], 'no column yet: a person\'s');
	pg_assert_true(ws_agent_chain_entry(array('id' => 1, 'sender_kind' => 'app', 'sender_id' => 9), array(9))['by_agent'], 'an agent\'s application as before');

	$chain = array(
		ws_agent_chain_entry(array('id' => 3, 'sender_kind' => 'app', 'sender_id' => 9), array(9)),
		ws_agent_chain_entry(array('id' => 2, 'sender_kind' => 'user', 'sender_id' => 12, 'via_client_id' => 4), array(9)),
		ws_agent_chain_entry(array('id' => 1, 'sender_kind' => 'user', 'sender_id' => 12, 'via_client_id' => 0), array(9)),
	);

	pg_assert_same(2, ws_agent_chain_depth($chain));
}

function test_workspace_claude_routes_a_connection_may_call()
{
	$open = ws_api_claude_session_routes();

	foreach (array('workspace.claude.requests.list', 'workspace.claude.requests.brief', 'workspace.claude.requests.claim', 'workspace.claude.requests.answer', 'workspace.claude.requests.fail', 'workspace.claude.change_types', 'workspace.claude.record') as $id) {
		pg_assert_true(in_array($id, $open, true), $id);
	}

	pg_assert_true(!in_array('workspace.claude.instructions', $open, true), 'the routine\'s instructions stay its own');
}

function test_workspace_claude_sessions_wiring()
{
	$source = function ($name) {
		$reflection = new ReflectionFunction($name);
		$lines = file($reflection->getFileName());

		return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	};

	pg_assert_contains('ws_claude_routine_ids(', $source('ws_claude_dispatch'), 'a request held for the asker starts no run');
	pg_assert_contains('ws_claude_routine_ids(', $source('ws_claude_fire'), 'a held request is not marked sent');
	pg_assert_contains('claimed_device_id', $source('ws_api_claude_claim'), 'the claiming connection is kept');
	pg_assert_contains('via_client_id', $source('ws_agent_chain'));
}

function test_workspace_api_message_writer_of_a_connection()
{
	// A connection's message is its owner's, with the client it came through;
	// a team devices session is the person's; a key writes as its application.
	pg_assert_same(array('app_id' => 0, 'via_client_id' => 4), ws_api_message_writer_of(array('id' => 9, 'oauth_client_id' => 4), 31, true));
	pg_assert_same(array('app_id' => 0, 'via_client_id' => 0), ws_api_message_writer_of(array('id' => 9, 'oauth_client_id' => 0), 31, true));
	pg_assert_same(array('app_id' => 31, 'via_client_id' => 0), ws_api_message_writer_of(null, 31, true));
	pg_assert_same(array('app_id' => 31, 'via_client_id' => 0), ws_api_message_writer_of(array('id' => 9, 'oauth_client_id' => 4), 31, false), 'before the step: the application, as before');
}

function test_workspace_api_message_via()
{
	pg_assert_same(array('client_id' => 4, 'name' => 'Claude Code'), ws_api_message_via(array('via_client_id' => 4), array(4 => 'Claude Code')));
	pg_assert_same(null, ws_api_message_via(array('via_client_id' => 0), array()));
	pg_assert_same(null, ws_api_message_via(array(), array()), 'no column yet');
	pg_assert_same(array('client_id' => 5, 'name' => ''), ws_api_message_via(array('via_client_id' => 5), array()), 'a client deleted since');
	pg_assert_same('WorkspaceMessageVia?', ws_api_message_schema()['via']);
	pg_assert_same(array('client_id' => 'integer', 'name' => 'string'), ws_api_message_via_schema());
}

function test_workspace_connection_message_wakes_what_the_application_message_woke()
{
	// Before the step a connection wrote as the application, and an
	// application's message asked no assistant; written as the person with
	// via, it still asks none.
	pg_assert_true(!ws_api_message_asks_assistants(31, 'message'), 'the application\'s message');
	pg_assert_true(!ws_api_message_asks_assistants(0, 'message', 4), 'the same message written through the connection');
	pg_assert_true(ws_api_message_asks_assistants(0, 'message'), 'the person\'s own device asks as the panel does');
	pg_assert_true(!ws_api_message_asks_assistants(0, 'note'));
}

function test_workspace_connection_message_wiring()
{
	$source = function ($name) {
		$reflection = new ReflectionFunction($name);
		$lines = file($reflection->getFileName());

		return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	};

	pg_assert_contains('via_client_id', $source('ws_message_send'), 'the client is stored on the message');
	pg_assert_contains("'via'", $source('ws_message_payload'), 'the screen gets the client\'s name');
	pg_assert_contains("'via'", $source('ws_api_message_present'));

	foreach (array('ws_api_messages_create', 'ws_api_messages_forward', 'ws_api_files_create', 'ws_api_messages_voice', 'ws_api_claude_answer', 'ws_api_claude_fail') as $name) {
		pg_assert_contains('ws_api_message_writer()', $source($name), $name);
	}

	// A message written through a connection decides a discussion's
	// following as an application's does.
	pg_assert_contains('ws_message_by_person(', $source('ws_message_ask_assistants'));
}

function test_workspace_message_by_person()
{
	pg_assert_true(ws_message_by_person(array('sender_kind' => 'user', 'via_client_id' => 0)));
	pg_assert_true(ws_message_by_person(array('sender_kind' => 'user')));
	pg_assert_true(!ws_message_by_person(array('sender_kind' => 'user', 'via_client_id' => 4)));
	pg_assert_true(!ws_message_by_person(array('sender_kind' => 'app', 'via_client_id' => 0)));
	pg_assert_true(!ws_message_by_person(null));
}
