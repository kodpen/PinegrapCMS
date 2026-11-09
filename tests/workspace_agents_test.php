<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent registry's checks (includes/workspace/agents.php)
 * that need no database: an agent's handle and its door.
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

function test_workspace_agent_handle_problem()
{
	pg_assert_same('', ws_agent_handle_problem('koordinator-a', array('claude', 'ai'), array('erdal')));
	pg_assert_true(ws_agent_handle_problem('Koordinator', array(), array()) !== '', 'upper case is not the shape');
	pg_assert_true(ws_agent_handle_problem('1bot', array(), array()) !== '', 'starts with a letter');
	pg_assert_true(ws_agent_handle_problem('ko ordinator', array(), array()) !== '', 'no spaces');
	pg_assert_true(ws_agent_handle_problem('ci', array('CI'), array()) !== '', 'held by another agent, whatever its case');
	pg_assert_true(ws_agent_handle_problem('erdal', array(), array('Erdal')) !== '', 'a person\'s user name');
	pg_assert_true(ws_agent_handle_problem('all', array(), array()) !== '', 'a word the Workspace keeps');
}

function test_workspace_agent_door_problem()
{
	pg_assert_same('', ws_agent_door_problem('poll', ''));
	pg_assert_true(ws_agent_door_problem('poll', 'https://example.com/hook') !== '', 'a poll door has no address');
	pg_assert_same('', ws_agent_door_problem('push', 'https://api.anthropic.com/v1/claude_code/routines/trig_01abc/fire'), 'a routine fire address');
	pg_assert_same('', ws_agent_door_problem('push', ' https://hooks.example.com/pinegrap '), 'any https receiver');
	pg_assert_true(ws_agent_door_problem('push', 'http://hooks.example.com/pinegrap') !== '', 'https only');
	pg_assert_true(ws_agent_door_problem('push', '') !== '', 'a push door needs an address');
	pg_assert_true(ws_agent_door_problem('push', 'https://api.anthropic.com/integrations/v1/code/webhook-triggers/abc/fire') !== '', 'a session webhook nobody but Anthropic can sign');
	pg_assert_true(ws_agent_door_problem('ring', '') !== '', 'push or poll');
}

function test_workspace_channel_agent_check_defaults_and_fields()
{
	$new = ws_channel_agent_check(array(), null, false);
	pg_assert_true($new['ok']);
	pg_assert_same(array('wake_rule' => 'mention', 'wake_prefix' => '', 'tier' => 1, 'loop_guard' => 1), $new['values'], 'a new row: mention, tier 1, loop guard on');

	$prefix = ws_channel_agent_check(array('wake_rule' => 'prefix', 'wake_prefix' => ' [ci] '), null, false);
	pg_assert_same('[ci]', $prefix['values']['wake_prefix']);

	$kept = ws_channel_agent_check(array('loop_guard' => false), array('wake_rule' => 'prefix', 'wake_prefix' => '[ready]', 'tier' => 2, 'loop_guard' => 1), false);
	pg_assert_same(array('wake_rule' => 'prefix', 'wake_prefix' => '[ready]', 'tier' => 2, 'loop_guard' => 0), $kept['values'], 'fields left out keep the row\'s value');

	$dropped = ws_channel_agent_check(array('wake_rule' => 'all', 'wake_prefix' => '[ci]'), null, false);
	pg_assert_same('', $dropped['values']['wake_prefix'], 'only a prefix wake keeps a prefix');

	pg_assert_same('wake_rule_invalid', ws_channel_agent_check(array('wake_rule' => 'always'), null, false)['error']);
	pg_assert_same('wake_prefix_invalid', ws_channel_agent_check(array('wake_rule' => 'prefix'), null, false)['error']);
	pg_assert_same('wake_prefix_invalid', ws_channel_agent_check(array('wake_rule' => 'prefix', 'wake_prefix' => str_repeat('x', 33)), null, false)['error']);
}

function test_workspace_channel_agent_check_tier_is_the_managers()
{
	pg_assert_same('tier_forbidden', ws_channel_agent_check(array('tier' => 2), null, false)['error'], 'raising the tier');
	pg_assert_same('tier_forbidden', ws_channel_agent_check(array('tier' => 1), array('tier' => 3), false)['error'], 'lowering it too');
	pg_assert_true(ws_channel_agent_check(array('tier' => 2), array('tier' => 2), false)['ok'], 'the same tier sent back');
	pg_assert_same(3, ws_channel_agent_check(array('tier' => 3), null, true)['values']['tier'], 'the manager');
	pg_assert_same('tier_invalid', ws_channel_agent_check(array('tier' => 4), null, true)['error']);
	pg_assert_same('tier_invalid', ws_channel_agent_check(array('tier' => 0), null, true)['error']);
}

function test_workspace_agent_shape_hides_secrets()
{
	$agent = ws_agent_shape(array('id' => '3', 'kind' => 'claude', 'handle' => 'claude', 'door_kind' => 'push', 'door_secret_sealed' => 'abc:def', 'key_sealed' => null, 'wake_every' => '30'));
	pg_assert_same(3, $agent['id']);
	pg_assert_same(30, $agent['wake_every']);
	pg_assert_true($agent['door_secret_set']);
	pg_assert_true(!$agent['key_set']);
	pg_assert_true(!array_key_exists('door_secret_sealed', $agent) && !array_key_exists('key_sealed', $agent), 'no sealed value is carried');
}

function test_workspace_agent_brakes()
{
	pg_assert_same(0, ws_agent_chain_depth(array()));
	pg_assert_same(2, ws_agent_chain_depth(array(array('by_agent' => true), array('by_agent' => true), array('by_agent' => false), array('by_agent' => true))), 'a person ends the count');
	pg_assert_same('', ws_agent_brake(WS_AGENT_DEPTH_MAX - 1, WS_AGENT_HOURLY_MAX - 1));
	pg_assert_same('depth', ws_agent_brake(WS_AGENT_DEPTH_MAX, 0));
	pg_assert_same('hourly', ws_agent_brake(0, WS_AGENT_HOURLY_MAX));
}

function test_workspace_agent_routes()
{
	$routes = array();

	foreach (ws_agent_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same(array('workspace.channels.agents.list', 'workspace.channels.agents.set', 'workspace.channels.agents.remove'), array_keys($routes));
	pg_assert_same('workspace:read', $routes['workspace.channels.agents.list']['scope']);
	pg_assert_true(!empty($routes['workspace.channels.agents.set']['dry_run']) && !empty($routes['workspace.channels.agents.remove']['dry_run']), 'writes rehearse');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_agents.php');
	$set = substr($source, strpos($source, 'function ws_api_channel_agents_set('));
	pg_assert_true(strpos($set, 'ws_channel_agent_check(') < strpos($set, 'api_dry_run_stop('), 'the check runs before the rehearsal stops');
	pg_assert_true(strpos($set, 'api_dry_run_stop(') < strpos($set, 'ws_channel_agent_save('), 'nothing is written in a rehearsal');
}

function test_workspace_agent_save_check()
{
	$app = array('kind' => 'app', 'handle' => '@Coordinator-a', 'name' => ' Coordinator A ', 'app_id' => 4, 'door_kind' => 'push', 'door_url' => 'https://hooks.example.com/a', 'wake_every' => 30);
	$ok = ws_agent_save_check($app, null, array('claude'), array('erdal'), array(4, 5));
	pg_assert_true($ok['ok'], $ok['error']);
	pg_assert_same('coordinator-a', $ok['values']['handle'], 'kept lower case, without the @');
	pg_assert_same('Coordinator A', $ok['values']['name']);
	pg_assert_same('active', $ok['values']['status'], 'a new agent starts active');
	pg_assert_same('', $ok['values']['provider'], 'an app agent has no provider');

	pg_assert_same('app_id', ws_agent_save_check(array('app_id' => 0) + $app, null, array(), array(), array(4))['field'], 'an app agent needs its application');
	pg_assert_same('app_id', ws_agent_save_check(array('app_id' => 9) + $app, null, array(), array(), array(4))['field'], 'an application that is not there');
	pg_assert_same('door_url', ws_agent_save_check(array('door_url' => 'http://x.test/') + $app, null, array(), array(), array(4))['field']);
	pg_assert_same('handle', ws_agent_save_check(array('handle' => 'erdal') + $app, null, array(), array('erdal'), array(4))['field']);
	pg_assert_same('wake_every', ws_agent_save_check(array('wake_every' => 1441) + $app, null, array(), array(), array(4))['field']);
	pg_assert_same('kind', ws_agent_save_check(array('kind' => 'claude') + $app, null, array(), array(), array(4))['field'], 'a second Claude is not made by hand');

	$model = array('kind' => 'model', 'handle' => 'chatgpt', 'name' => 'ChatGPT', 'provider' => 'openai', 'model' => 'gpt-4.1-mini', 'door_kind' => 'push', 'door_url' => 'https://x.test/');
	$checked = ws_agent_save_check($model, null, array(), array(), array());
	pg_assert_true($checked['ok'], $checked['error']);
	pg_assert_same(array('poll', ''), array($checked['values']['door_kind'], $checked['values']['door_url']), 'a provider with a fixed address keeps none: the site is the door');
	pg_assert_same('model', ws_agent_save_check(array('model' => '') + $model, null, array(), array(), array())['field']);
	pg_assert_same('provider', ws_agent_save_check(array('provider' => 'nobody') + $model, null, array(), array(), array())['field'], 'only a provider of ws_model_providers()');

	$azure = ws_agent_save_check(array('provider' => 'azure', 'door_url' => 'https://res.openai.azure.com/openai/v1') + $model, null, array(), array(), array());
	pg_assert_true($azure['ok'], $azure['error']);
	pg_assert_same('https://res.openai.azure.com/openai/v1', $azure['values']['door_url'], 'Azure keeps the address given');
	pg_assert_same('door_url', ws_agent_save_check(array('provider' => 'azure', 'door_url' => '') + $model, null, array(), array(), array())['field'], 'Azure needs an address');
	pg_assert_same('door_url', ws_agent_save_check(array('provider' => 'azure', 'door_url' => 'http://res.example/v1') + $model, null, array(), array(), array())['field'], 'Azure only over https');
	pg_assert_true(ws_agent_save_check(array('provider' => 'custom', 'door_url' => 'http://localhost:11434/v1') + $model, null, array(), array(), array())['ok'], 'a custom server may be plain http');

	$claude = ws_agent_shape(array('id' => 1, 'kind' => 'claude', 'handle' => 'claude', 'name' => 'Claude', 'status' => 'active', 'door_kind' => 'push', 'door_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_1/fire'));
	$kept = ws_agent_save_check(array('kind' => 'app', 'handle' => 'other', 'name' => 'Claude', 'status' => 'paused', 'door_url' => ''), $claude, array('claude'), array(), array());
	pg_assert_true($kept['ok'], $kept['error']);
	pg_assert_same(array('claude', 'claude', 'push', $claude['door_url'], 'paused'), array($kept['values']['kind'], $kept['values']['handle'], $kept['values']['door_kind'], $kept['values']['door_url'], $kept['values']['status']), 'Claude keeps its kind, handle and door');
}
