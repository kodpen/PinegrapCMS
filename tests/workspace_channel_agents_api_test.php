<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel-agent reads of the external API and MCP
 * (api_agents.php): the registered agents a channel can still take beside
 * GET /workspace/channels/{id}/agents, and the agent card of one agent
 * of a channel on GET /workspace/channels/{id}/agents/{agent_id}.
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
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function workspace_channel_agents_api_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_workspace_channel_agents_api_addable_beside_the_list()
{
	$route = workspace_channel_agents_api_test_route('workspace.channels.agents.list');

	pg_assert_true(is_array($route), 'the list route');
	pg_assert_same('WorkspaceAddableAgent[]', $route['returns']['addable'], 'addable is declared beside data');

	$agents = array(
		ws_agent_shape(array('id' => 1, 'kind' => 'claude', 'handle' => 'claude', 'name' => 'Claude', 'status' => 'active')),
		ws_agent_shape(array('id' => 2, 'kind' => 'app', 'handle' => 'ci', 'name' => 'CI', 'status' => 'active', 'door_url' => 'https://example.com/fire', 'key_sealed' => 'a:b')),
		ws_agent_shape(array('id' => 3, 'kind' => 'app', 'handle' => 'mimar', 'name' => 'Mimar', 'status' => 'off', 'door_url' => 'https://example.com/door')),
	);
	$out = ws_api_addable_agents_present(ws_channel_agents_addable($agents, array(2)));

	pg_assert_same(array(array('id' => 3, 'handle' => 'mimar', 'name' => 'Mimar', 'status' => 'off')), $out, 'the panel\'s Add list, id, handle, name and status only; Claude and an agent already in are left out');
	pg_assert_same(array_keys(ws_api_addable_agent_schema()), array_keys($out[0]), 'every field declared, nothing more');
	pg_assert_same(array(), ws_api_addable_agents_present(array()));
}

function test_workspace_channel_agents_api_card_route()
{
	$route = workspace_channel_agents_api_test_route('workspace.channels.agents.card');

	pg_assert_true(is_array($route), 'the card route');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/workspace/channels/{id}/agents/{agent_id}', $route['path']);
	pg_assert_same('workspace:read', $route['scope'], 'every reader of the channel, as the panel\'s card');
	pg_assert_true(!isset($route['mcp']) || ($route['mcp'] !== false), 'an MCP tool');
	pg_assert_same('workspace_channels_agents_card', pg_mcp_tool_name($route['id']));
	pg_assert_true(isset(ws_openapi_objects()['WorkspaceAgentCard']), 'the card schema is registered');
	pg_assert_true(isset(ws_openapi_objects()['WorkspaceAgentCardMessage']), 'the last message schema is registered');
}

function test_workspace_channel_agents_api_card_present()
{
	$now = 1700000000;
	$card = array(
		'id'           => 7,
		'app_id'       => 12,
		'handle'       => 'mimar',
		'name'         => 'Mimar',
		'kind'         => 'app',
		'avatar'       => '',
		'role'         => 'Architect',
		'seen'         => 'Seen 1 min ago',
		'seen_state'   => 'on',
		'model'        => 'model-x',
		'windows'      => array(array('name' => 'weekly', 'label' => 'Weekly', 'percent' => 92, 'resets_at' => $now + 3600, 'resets' => 'resets 14.11 23:13', 'high' => true)),
		'reported'     => 'As the agent reported it 1 min ago; the site cannot check it.',
		'stale'        => false,
		'today'        => array('answered' => 4, 'failed' => 1),
		'last_message' => array('id' => 90, 'when' => '2 min ago'),
		'channels'     => array(array('id' => 25, 'name' => 'agents')),
		'tier'         => 2,
		'wake'         => 'wakes when mentioned',
		'role_key'     => 'architect',
		'state'        => 'idle',
		'state_words'  => 'Ready',
		'waiting'      => 0,
		'usage_high'   => true,
	);
	$out = ws_api_agent_card_present(25, $card);

	pg_assert_same(25, $out['channel_id']);
	pg_assert_same(api_time($now + 3600), $out['windows'][0]['resets_at'], 'a reset time as an API time');
	pg_assert_same(92, $out['windows'][0]['percent']);
	pg_assert_same(array('id' => 90, 'when' => '2 min ago'), $out['last_message']);
	pg_assert_same(array_keys(ws_api_agent_card_schema()), array_keys(array_intersect_key(ws_api_agent_card_schema(), $out)), 'every declared field is given');

	foreach (array_keys($out) as $field) {
		pg_assert_true(array_key_exists($field, ws_api_agent_card_schema()), 'declared: ' . $field);
	}

	pg_assert_same(array_keys($card), array_values(array_diff(array_keys($card), array_keys(array_diff_key($card, ws_api_agent_card_schema())))), 'no field of the panel\'s card is dropped');

	$card['last_message'] = null;
	$card['windows'] = array();

	$plain = ws_api_agent_card_present(25, $card);

	pg_assert_same(null, $plain['last_message']);
	pg_assert_same(array(), $plain['windows']);
}

function workspace_channel_agents_api_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());
	$body = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

	return (string) preg_replace('~^\s*//.*$~m', '', $body);
}

function test_workspace_channel_agents_api_wake_route()
{
	$route = workspace_channel_agents_api_test_route('workspace.channels.agents.wake');

	pg_assert_true(is_array($route), 'the channel wake route');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/channels/{id}/agents/{agent_id}/wake', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_true(!empty($route['dry_run']), 'a write rehearses');
	pg_assert_true(isset($route['mcp']) && ($route['mcp'] === false), 'a person\'s write: no MCP tool');
	pg_assert_same(array('id', 'agent_id', 'every', 'at', 'off', 'note'), array_column($route['params'], 'name'), 'the same input as the agent\'s own agent-wake');

	foreach (array('channel_id', 'agent_id', 'message_id', 'handle', 'every', 'next_at', 'note') as $field) {
		pg_assert_true(array_key_exists($field, $route['returns']), 'declared: ' . $field);
	}

	// The agent keeps its own route.
	$own = workspace_channel_agents_api_test_route('workspace.agent_wake.set');

	pg_assert_true(is_array($own) && ($own['mcp'] === false), 'the agent\'s own agent-wake stays');
}

// The person's gate comes first; the panel's check and its writer and
// system line are the ones /wake uses, and the rehearsal stops after every
// check, just above the write.
function test_workspace_channel_agents_api_wake_handler_order()
{
	$source = workspace_channel_agents_api_test_source('ws_api_channel_agent_wake');
	$gate = strpos($source, 'ws_api_person_only(');
	$check = strpos($source, 'ws_agent_wake_problem(');
	$parse = strpos($source, 'ws_api_agent_wake_parsed(');
	$stop = strpos($source, 'api_dry_run_stop(');
	$save = strpos($source, 'ws_agent_wake_save(');
	$line = strpos($source, 'ws_agent_wake_system_line(');

	pg_assert_true($gate !== false && $check !== false && $parse !== false && $stop !== false && $save !== false && $line !== false, 'gate, check, input, rehearsal, writer and line');
	pg_assert_true(($gate < strpos($source, 'ws_api_channel_or_404(')) && ($gate < $check), 'an application key is refused before anything is read');
	pg_assert_true(($check < $stop) && ($parse < $stop), 'the rehearsal stops after every check');
	pg_assert_true(($stop < $save) && ($save < $line), 'then the write and its line');

	pg_assert_contains('ws_agent_wake_system_line(', workspace_channel_agents_api_test_source('ws_command_wake'), '/wake leaves the same line');
	pg_assert_contains('ws_api_agent_wake_parsed(', workspace_channel_agents_api_test_source('ws_api_agent_wake_set'), 'the agent\'s own route reads its input the same way');
}
