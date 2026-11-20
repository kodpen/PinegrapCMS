<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application agent's own routes as MCP tools: they are tools
 * only for the key of an active application agent used as itself. An OAuth
 * connection, a team devices session and a key with no active agent never
 * see them, on the full list or the compact one, and the agent's feedback
 * read stays off MCP.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// The agent's own routes that are tools for its own key, by tool name.
function mcp_agent_tools_test_names()
{
	return array(
		'workspace_agent_instructions_get',
		'workspace_agent_requests_list',
		'workspace_agent_requests_claim',
		'workspace_agent_requests_brief',
		'workspace_agent_requests_answer',
		'workspace_agent_requests_fail',
		'workspace_agent_requests_progress',
		'workspace_agent_report_set',
		'workspace_agent_wake_get',
		'workspace_agent_wake_set',
	);
}

function mcp_agent_tools_test_scopes()
{
	return array('workspace:read', 'workspace:write');
}

// The agent tools in a list of routes by tool name.
function mcp_agent_tools_test_present($routes)
{
	return array_values(array_intersect(mcp_agent_tools_test_names(), array_keys($routes)));
}

// Exactly the listed routes carry 'mcp' => 'agent'.
function test_mcp_agent_tools_marked_routes()
{
	$marked = array();

	foreach (ws_api_routes() as $route) {
		if (isset($route['mcp']) && ($route['mcp'] === 'agent')) {
			$marked[] = pg_mcp_tool_name($route['id']);
		}
	}

	$expected = mcp_agent_tools_test_names();
	sort($expected);
	sort($marked);

	pg_assert_same($expected, $marked, 'the agent\'s own routes, and only they');
}

// Who gets the agent tools: only an active app agent's row with no device.
function test_mcp_agent_tools_callers()
{
	$agent = array('id' => 7, 'kind' => 'app', 'status' => 'active', 'app_id' => 13, 'handle' => 'mimar');
	$oauth = array('id' => 4, 'app_id' => 13, 'oauth_client_id' => 'cl_abc', 'user_id' => 2);
	$team = array('id' => 5, 'app_id' => 13, 'oauth_client_id' => '', 'user_id' => 2);

	pg_assert_true(pg_mcp_agent_routes_open(null, $agent), 'the agent\'s own key');
	pg_assert_false(pg_mcp_agent_routes_open($oauth, $agent), 'an OAuth connection of the same application');
	pg_assert_false(pg_mcp_agent_routes_open($team, $agent), 'a team devices session');
	pg_assert_false(pg_mcp_agent_routes_open(null, null), 'a key with no active agent');
	pg_assert_false(pg_mcp_agent_routes_open(null, array('status' => 'paused') + $agent), 'a paused agent\'s key');
	pg_assert_false(pg_mcp_agent_routes_open(null, array('kind' => 'model') + $agent), 'a model agent');
}

// The three callers that never see the tools get a list without them; the
// agent's key gets all of them.
function test_mcp_agent_tools_listed_only_for_the_agent()
{
	$schema = ws_api_routes();
	$scopes = mcp_agent_tools_test_scopes();

	pg_assert_same(array(), mcp_agent_tools_test_present(pg_mcp_routes_for($schema, $scopes)), 'the default caller');
	pg_assert_same(array(), mcp_agent_tools_test_present(pg_mcp_routes_for($schema, $scopes, false)), 'an OAuth connection, a device session or a key with no agent');
	pg_assert_same(mcp_agent_tools_test_names(), mcp_agent_tools_test_present(pg_mcp_routes_for($schema, $scopes, true)), 'the agent\'s own key');

	$routes = pg_mcp_routes_for($schema, $scopes, true);
	pg_assert_false(isset($routes['workspace_agent_feedback_get']), 'the feedback read stays off MCP');
	pg_assert_false(isset($routes['workspace_claude_requests_list']), 'Claude\'s queue stays off MCP');

	$read_only = pg_mcp_routes_for($schema, array('workspace:read'), true);
	pg_assert_false(isset($read_only['workspace_agent_requests_claim']), 'the scope still decides');
	pg_assert_true(isset($read_only['workspace_agent_requests_list']), 'a read the scope covers');
}

// The compact list finds and describes them for the agent only.
function test_mcp_agent_tools_compact()
{
	$schema = ws_api_routes();
	$scopes = mcp_agent_tools_test_scopes();

	$person = pg_mcp_compact_describe(pg_mcp_routes_for($schema, $scopes, false), array('workspace_agent_requests_claim'));
	pg_assert_same(array('workspace_agent_requests_claim'), $person['unknown'], 'unknown to a person');

	$agent = pg_mcp_compact_describe(pg_mcp_routes_for($schema, $scopes, true), array('workspace_agent_requests_claim'));
	pg_assert_same(array(), $agent['unknown'], 'described to the agent');
}

// A route marked for the agent is never offered when nobody says the caller
// is one, so no other list (resources, prompts) picks it up by accident.
function test_mcp_agent_tools_offer_check()
{
	$route = array('id' => 'workspace.agent_requests.list', 'scope' => 'workspace:read', 'mcp' => 'agent');

	pg_assert_false(pg_mcp_route_is_offered($route), 'no caller named');
	pg_assert_false(pg_mcp_route_is_offered($route, false), 'not the agent');
	pg_assert_true(pg_mcp_route_is_offered($route, true), 'the agent');
	pg_assert_false(pg_mcp_route_is_offered(array('mcp' => false) + $route, true), 'mcp false stays off for the agent too');
}
