<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel settings' agents section
 * (includes/workspace/channel_agents_panel.php) that need no database: the
 * row it draws, the agents it offers to add, and its texts.
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

function test_workspace_channel_agent_panel_row()
{
	$agent = ws_agent_shape(array('id' => '7', 'kind' => 'app', 'handle' => 'ci', 'name' => 'CI', 'status' => 'paused',
		'door_url' => 'https://hooks.example.com/x', 'door_secret_sealed' => 'a:b', 'key_sealed' => 'c:d'));
	$row = ws_channel_agent_panel_row(ws_channel_agent_shape(array('wake_rule' => 'prefix', 'wake_prefix' => '[ci]', 'tier' => '2', 'loop_guard' => '0'), $agent));

	pg_assert_same(array(
		'agent_id'    => 7,
		'kind'        => 'app',
		'handle'      => 'ci',
		'name'        => 'CI',
		'active'      => false,
		'wake_rule'   => 'prefix',
		'wake_prefix' => '[ci]',
		'tier'        => 2,
		'loop_guard'  => false,
	), $row, 'no door, address or secret reaches the screen');
}

function test_workspace_channel_agents_addable()
{
	$agents = array(
		ws_agent_shape(array('id' => 1, 'kind' => 'claude', 'handle' => 'claude', 'name' => 'Claude', 'status' => 'active', 'door_url' => 'https://example.com/fire')),
		ws_agent_shape(array('id' => 2, 'kind' => 'model', 'handle' => 'chatgpt', 'name' => 'ChatGPT', 'status' => 'active', 'key_sealed' => 'a:b')),
		ws_agent_shape(array('id' => 3, 'kind' => 'app', 'handle' => 'ci', 'name' => '', 'status' => 'paused')),
	);
	$out = ws_channel_agents_addable($agents, array('2'));

	pg_assert_same(array(3), array_column($out, 'agent_id'), 'the agents already in the channel and Claude, which is no channel agent, are left out');
	pg_assert_same(array('agent_id', 'kind', 'handle', 'name', 'active'), array_keys($out[0]), 'only what the list shows');
	pg_assert_same(false, $out[0]['active']);
	pg_assert_same(array(), ws_channel_agents_addable(array(), array()));
}

function test_workspace_channel_agents_actions_and_texts()
{
	pg_assert_same(array('ws_channel_agents_list', 'ws_channel_agents_set', 'ws_channel_agents_remove'), ws_channel_agents_actions());

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_channel_agents.js');
	$strings = ws_channel_agents_js_strings();

	preg_match_all("/t\\('(cha_[a-z0-9_]+)'\\)/", $source, $used);

	foreach (array_unique($used[1]) as $key) {
		pg_assert_true(isset($strings[$key]), 'the script\'s text ' . $key . ' is in ws_channel_agents_js_strings()');
	}

	foreach (ws_agent_wake_rules() as $rule) {
		pg_assert_true(isset($strings['cha_wake_' . $rule]), 'every wake rule has its label: ' . $rule);
	}
}
