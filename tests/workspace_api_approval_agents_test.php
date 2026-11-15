<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for agents asked to approve as the external API takes and shows them
 * (includes/workspace/api_resources.php): the agents a create call names,
 * by handle or request value, and an agent asked as WorkspaceApproval lists
 * it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

function ws_api_approval_agents_test_options()
{
	return array(
		array('value' => 'agent:4', 'handle' => 'Builder', 'name' => 'Builder'),
		array('value' => 'agent:9', 'handle' => 'reviewer', 'name' => 'Reviewer'),
	);
}

// A handle, with or without @ and in any case, or a request value names an agent.
function test_workspace_api_approval_agent_values_resolve()
{
	$got = ws_api_approval_agent_values(array('@builder', 'agent:9', ' REVIEWER ', 'Builder'), ws_api_approval_agents_test_options());

	pg_assert_same(array('values' => array('agent:4', 'agent:9'), 'unknown' => ''), $got, 'each agent once, in the order given');
	pg_assert_same(array('values' => array(), 'unknown' => ''), ws_api_approval_agent_values(array(), ws_api_approval_agents_test_options()));
	pg_assert_same(array('values' => array(), 'unknown' => ''), ws_api_approval_agent_values(array('', '  '), ws_api_approval_agents_test_options()), 'blank entries are left out');
}

// An agent the channel does not offer is named back, never dropped.
function test_workspace_api_approval_agent_values_unknown()
{
	pg_assert_same(array('values' => array('agent:4'), 'unknown' => 'stranger'),
		ws_api_approval_agent_values(array('builder', '@stranger', 'agent:12'), ws_api_approval_agents_test_options()));
	pg_assert_same('agent:12', ws_api_approval_agent_values(array('agent:12'), ws_api_approval_agents_test_options())['unknown'], 'a paused or other channel\'s agent');
	pg_assert_same('claude', ws_api_approval_agent_values(array('claude'), array())['unknown'], 'no agent offered');
}

// One agent asked, with its answer.
function test_workspace_api_approval_agent_present()
{
	$row = array('agent' => 'agent:4', 'request_id' => '31', 'decision' => 'approved', 'note' => 'Looks right', 'decided_at' => '1762900000');

	pg_assert_same(array(
		'agent'      => 'agent:4',
		'handle'     => 'builder',
		'name'       => 'Builder',
		'decision'   => 'approved',
		'note'       => 'Looks right',
		'decided_at' => api_time(1762900000),
	), ws_api_approval_agent_present($row, array('handle' => 'builder', 'name' => 'Builder')));

	$gone = ws_api_approval_agent_present(array('agent' => 'agent:7', 'decision' => 'pending', 'note' => '', 'decided_at' => '0'), null);

	pg_assert_same(array('agent' => 'agent:7', 'handle' => null, 'name' => 'agent:7', 'decision' => 'pending', 'note' => null, 'decided_at' => null), $gone, 'a removed agent keeps its value');
}

// WorkspaceApproval declares the agents with the fields the presenter gives.
function test_workspace_api_approval_schema_has_agents()
{
	$schema = ws_api_approval_schema();

	pg_assert_true(isset($schema['agents']), 'agents declared');
	pg_assert_same(array_keys(ws_api_approval_agent_present(array('agent' => 'agent:7', 'decision' => 'pending', 'note' => '', 'decided_at' => 0), null)),
		array_keys($schema['agents'][0]));
}

// The decide route stays off MCP: the agent answers with its own key over REST.
function test_workspace_api_approval_decide_stays_off_mcp()
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.approvals.decide') {
			pg_assert_same(false, $route['mcp']);

			return;
		}
	}

	pg_assert_true(false, 'workspace.approvals.decide is declared');
}
