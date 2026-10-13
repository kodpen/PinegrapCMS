<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of other services' MCP servers in the Workspace
 * (includes/workspace/mcp.php, mcp_ask.php): which tools may be turned on
 * with one connection for the whole site, which are on, the names the model
 * knows them by, aliases and the credential headers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_ask.php');

// A tool as the client keeps it.
function workspace_mcp_test_tool($name, $annotations = array())
{
	return pg_mcp_client_tool_normalise(array('name' => $name, 'description' => 'About ' . $name, 'annotations' => $annotations), '{"type":"object","properties":{}}');
}

// A server row with these tools and rules.
function workspace_mcp_test_server($tools, $rules)
{
	return array(
		'id'         => 1,
		'alias'      => 'docs',
		'status'     => 'active',
		'tools'      => json_encode(array_values($tools)),
		'tool_rules' => json_encode((object) $rules),
	);
}

function test_workspace_mcp_only_reading_tools_can_be_turned_on()
{
	pg_assert_same('read', ws_mcp_tool_can_run(workspace_mcp_test_tool('a', array('readOnlyHint' => true))));
	pg_assert_same('declare', ws_mcp_tool_can_run(workspace_mcp_test_tool('b')));
	pg_assert_same('', ws_mcp_tool_can_run(workspace_mcp_test_tool('c', array('readOnlyHint' => false))));
	pg_assert_same('', ws_mcp_tool_can_run(workspace_mcp_test_tool('d', array('destructiveHint' => true))));

	// A hint that only means something for a tool that writes says it writes.
	pg_assert_same('', ws_mcp_tool_can_run(workspace_mcp_test_tool('e', array('idempotentHint' => true))));

	// A hint that is not a boolean counts as not given.
	pg_assert_same('declare', ws_mcp_tool_can_run(workspace_mcp_test_tool('f', array('readOnlyHint' => 'yes'))));
}

function test_workspace_mcp_rules_count_only_for_tools_that_may_run()
{
	$server = workspace_mcp_test_server(array(
		workspace_mcp_test_tool('reads', array('readOnlyHint' => true)),
		workspace_mcp_test_tool('unsaid'),
		workspace_mcp_test_tool('declared'),
		workspace_mcp_test_tool('writes', array('readOnlyHint' => false)),
	), array(
		'reads'    => 'auto',
		'unsaid'   => 'auto',
		'declared' => 'auto_declared',
		'writes'   => 'auto_declared',
		'gone'     => 'auto',
		'odd'      => 'always',
	));

	pg_assert_same(array('reads', 'declared'), array_keys(ws_mcp_tools_on($server)));
}

function test_workspace_mcp_function_names_suit_the_chat_api()
{
	$tools = array();

	foreach (array('repos/search.issues', 'repos_search_issues', 'answer', 'fail', str_repeat('x', 100)) as $name) {
		$tools[] = workspace_mcp_test_tool($name, array('readOnlyHint' => true));
	}

	$rules = array_fill_keys(array_column($tools, 'name'), 'auto');
	$functions = ws_mcp_ask_functions(workspace_mcp_test_server($tools, $rules));

	foreach (array_keys($functions) as $function) {
		pg_assert_true((bool) preg_match('/^[A-Za-z0-9_-]{1,64}$/', $function), $function . ' is a valid function name');
		pg_assert_true(!in_array($function, array('answer', 'fail'), true), $function . ' is not answer or fail');
	}

	pg_assert_same(5, count($functions));
	pg_assert_same('answer', $functions['answer_2']['name']);
	pg_assert_same('fail', $functions['fail_2']['name']);
	pg_assert_same('repos/search.issues', $functions['repos_search_issues']['name']);
	pg_assert_same('repos_search_issues', $functions['repos_search_issues_2']['name']);
}

function test_workspace_mcp_model_is_offered_a_limited_number_of_tools()
{
	$tools = array();

	for ($i = 0; $i < WS_MCP_ASK_MAX_TOOLS + 5; $i++) {
		$tools[] = workspace_mcp_test_tool(sprintf('tool_%03d', $i), array('readOnlyHint' => true));
	}

	$functions = ws_mcp_ask_functions(workspace_mcp_test_server($tools, array_fill_keys(array_column($tools, 'name'), 'auto')));

	pg_assert_same(WS_MCP_ASK_MAX_TOOLS, count($functions));
	pg_assert_same('tool_000', array_keys($functions)[0]);
}

function test_workspace_mcp_alias_shapes()
{
	pg_assert_same('github', ws_mcp_alias_clean(' @GitHub '));
	pg_assert_same('', ws_mcp_alias_shape_problem('github'));
	pg_assert_same('', ws_mcp_alias_shape_problem('my-docs_2'));
	pg_assert_true(ws_mcp_alias_shape_problem('g') !== '', 'one letter is too short');
	pg_assert_true(ws_mcp_alias_shape_problem('2docs') !== '', 'a digit cannot start it');
	pg_assert_true(ws_mcp_alias_shape_problem('döküman') !== '', 'no accented letters');
	pg_assert_true(ws_mcp_alias_shape_problem('ai') !== '', 'ai is the assistant');
	pg_assert_true(ws_mcp_alias_shape_problem('herkes') !== '', 'herkes is everybody');
}

function test_workspace_mcp_credential_headers()
{
	pg_assert_same(array('Authorization: Bearer abc'), ws_mcp_auth_headers('bearer', array('token' => 'abc')));
	pg_assert_same(array('X-Api-Key: k1'), ws_mcp_auth_headers('header', array('name' => 'X-Api-Key', 'value' => 'k1')));
	pg_assert_same(array('Authorization: Basic ' . base64_encode('u:p')), ws_mcp_auth_headers('basic', array('user' => 'u', 'password' => 'p')));
	pg_assert_same(array(), ws_mcp_auth_headers('bearer', array()));
	pg_assert_same(array(), ws_mcp_auth_headers('none', array('token' => 'abc')));
}

function test_workspace_mcp_header_names()
{
	pg_assert_same('', ws_mcp_header_name_problem('X-Api-Key'));
	pg_assert_same('', ws_mcp_header_name_problem('Authorization'));
	pg_assert_true(ws_mcp_header_name_problem('Content-Type') !== '', 'the connection sets Content-Type');
	pg_assert_true(ws_mcp_header_name_problem('Mcp-Session-Id') !== '', 'the connection sets Mcp-*');
	pg_assert_true(ws_mcp_header_name_problem("X-Key\r\nHost") !== '', 'no line breaks');
}
