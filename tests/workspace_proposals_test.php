<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record changes an application proposes
 * (includes/workspace/proposals.php) that need no database: the agent a
 * proposal is kept under for each kind of caller, how that agent is read
 * back, the SQL that tells proposals from the assistants' requests, the
 * agent the API lists a change under, the read scopes the kinds a body
 * names are asked with, and how an application's name is cleaned before it
 * goes into a message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// An OAuth connection is kept under its client, since every client writes
// through the one OAuth application; a key under its application; anything
// else under none.
function test_ws_proposal_agent_key()
{
	pg_assert_same('oauth:7', ws_proposal_agent_key(7, 3), 'the client wins over the shared application');
	pg_assert_same('app:3', ws_proposal_agent_key(0, 3));
	pg_assert_same('', ws_proposal_agent_key(0, 0));
	pg_assert_same('', ws_proposal_agent_key(-1, -2), 'no negative ids');

	// The longest id still fits ws_ai_requests.agent, VARCHAR(16).
	pg_assert_true(strlen(ws_proposal_agent_key(4294967295, 0)) <= 16, 'oauth:<INT UNSIGNED> fits the column');
}

// Who calls decides the agent: a key by its application, an OAuth
// connection by its client, a team devices session by nobody (the person
// changes the record themselves).
function test_ws_proposal_caller_key()
{
	pg_assert_same('app:12', ws_proposal_caller_key(null, 12), 'an application key');
	pg_assert_same('oauth:5', ws_proposal_caller_key(array('id' => 40, 'oauth_client_id' => 5), 9), 'an OAuth connection');
	pg_assert_same('', ws_proposal_caller_key(array('id' => 41, 'oauth_client_id' => 0), 9), 'a team devices session');
	pg_assert_same('', ws_proposal_caller_key(array('id' => 42), 9), 'a device row before the OAuth column');
	pg_assert_same('', ws_proposal_caller_key(null, 0));
}

// Only the two proposal shapes read back as an application; the assistants'
// agents and anything malformed do not.
function test_ws_proposal_agent_parse()
{
	pg_assert_same(array('kind' => 'oauth', 'id' => 5), ws_proposal_agent_parse('oauth:5'));
	pg_assert_same(array('kind' => 'app', 'id' => 12), ws_proposal_agent_parse('app:12'));

	foreach (array('claude', 'ai', 'mcp', 'mcp:3', '', 'app:', 'app:0', 'oauth:05', 'oauth:x', 'app:1 ', 'APP:1', 'oauth:12345678901') as $agent) {
		pg_assert_same(null, ws_proposal_agent_parse($agent), var_export($agent, true) . ' is no proposal');
	}

	foreach (array(array(7, 0), array(0, 3)) as $ids) {
		$key = ws_proposal_agent_key($ids[0], $ids[1]);

		pg_assert_true(ws_proposal_agent_parse($key) !== null, $key . ' reads back');
	}
}

// The SQL names both shapes, with the table's alias when there is one, and
// can be turned round to keep the proposals out.
function test_ws_proposal_where()
{
	pg_assert_same("(agent LIKE 'oauth:%' OR agent LIKE 'app:%')", ws_proposal_where());
	pg_assert_same("(r.agent LIKE 'oauth:%' OR r.agent LIKE 'app:%')", ws_proposal_where('r'));
	pg_assert_same("NOT (agent LIKE 'oauth:%' OR agent LIKE 'app:%')", ws_proposal_where('', false));

	// No assistant's agent matches the pattern, so none of the queues the
	// assistants read can be told apart wrongly.
	foreach (array('claude', 'ai', 'mcp') as $agent) {
		pg_assert_false((strpos($agent, 'oauth:') === 0) || (strpos($agent, 'app:') === 0), $agent);
	}
}

// The API lists a change under Pinegrap AI, an MCP agent, the application
// that proposed it, or Claude: never under Claude for an application's.
function test_ws_change_agent_key()
{
	pg_assert_same('ai', ws_change_agent_key('ai'));
	pg_assert_same('mcp', ws_change_agent_key('mcp'));
	pg_assert_same('oauth:5', ws_change_agent_key('oauth:5'));
	pg_assert_same('app:12', ws_change_agent_key('app:12'));
	pg_assert_same('claude', ws_change_agent_key('claude'));
	pg_assert_same('claude', ws_change_agent_key(''), 'a row from before the column');
	pg_assert_same('claude', ws_change_agent_key('oauth:'), 'a malformed agent');
}

// The kinds a body names are read with the scope the API reads them with
// elsewhere, once each; a change that is not an object or names no kind is
// left to ws_changes_input() to refuse.
function test_ws_proposal_scopes()
{
	$types = array(
		'product' => array('tag' => 'product'),
		'stock'   => array('tag' => 'product'),
		'order'   => array('tag' => 'order'),
		'contact' => array('tag' => 'contact'),
		'task'    => array('tag' => 'task'),
		'note'    => array('tag' => ''),
	);

	pg_assert_same(array('products:read', 'orders:read'), ws_proposal_scopes(array(
		array('type' => 'product', 'id' => 1),
		array('type' => 'stock', 'id' => 1),
		array('type' => 'order', 'id' => 9),
	), $types), 'one scope per kind read');

	pg_assert_same(array('customers:read', 'tasks:read', 'workspace:read'), ws_proposal_scopes(array(
		array('type' => 'contact', 'action' => 'create'),
		array('type' => 'task', 'id' => 3),
		array('type' => 'note', 'action' => 'create'),
	), $types), 'a kind with no tag is the Workspace\'s own');

	pg_assert_same(array(), ws_proposal_scopes(array('product', array('type' => 'nothing'), array('id' => 4), null), $types), 'nothing named, nothing asked');
	pg_assert_same(array(), ws_proposal_scopes('product', $types), 'not a list');
	pg_assert_same(array(), ws_proposal_scopes(null, $types));
}

// An application's name goes into the words of a decision, and an OAuth
// client names itself: no tag, no line break survives, and it stays short.
function test_ws_proposal_name_clean()
{
	pg_assert_same('ChatGPT', ws_proposal_name_clean('ChatGPT'));
	pg_assert_same('Cursor Agent', ws_proposal_name_clean("  Cursor\n\tAgent  "));
	pg_assert_same('@user:1 x', ws_proposal_name_clean('<@user:1> x'), 'a mention tag is no tag any more');
	pg_assert_same('#order:5', ws_proposal_name_clean('<#order:5>'));
	pg_assert_same('', ws_proposal_name_clean('<>'), 'nothing left: the caller names it');
	pg_assert_same(100, mb_strlen(ws_proposal_name_clean(str_repeat('ş', 150))), 'the column\'s length');
	pg_assert_false(strpos(ws_proposal_name_clean('a<b>c'), '<') !== false);
}

// A proposal is capped per application and person, and never carries more
// cards than an assistant's answer.
function test_ws_proposal_limits()
{
	pg_assert_true(WS_PROPOSALS_PER_HOUR > 0, 'there is a limit');
	pg_assert_same(10, WS_CHANGES_PER_ANSWER, 'the answer\'s own limit is the one reused');
}
