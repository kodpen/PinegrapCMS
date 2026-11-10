<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Claude is no channel agent (includes/workspace/agents.php,
 * channel_agents_panel.php, api_agents.php) and need no database: Claude is
 * asked where a channel's claude_access allows and no reader takes a
 * channel-agent row of kind claude, so none is offered, made, changed or
 * listed; taking an old one out still works.
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

function test_workspace_channel_agent_kind_problem()
{
	pg_assert_same('claude_not_channel_agent', ws_channel_agent_kind_problem('claude'));
	pg_assert_same('', ws_channel_agent_kind_problem('app'));
	pg_assert_same('', ws_channel_agent_kind_problem('model'));
	pg_assert_true(ws_channel_agent_error_text('claude_not_channel_agent') !== lang('Invalid request.'), 'the refusal has a sentence of its own');
}

function test_workspace_channel_agents_readers_leave_claude_out()
{
	$claude = ws_agent_shape(array('id' => 1, 'kind' => 'claude', 'handle' => 'claude', 'name' => 'Claude', 'status' => 'active'));
	$app = ws_agent_shape(array('id' => 2, 'kind' => 'app', 'handle' => 'ci', 'name' => 'CI', 'status' => 'active'));
	$rows = array(ws_channel_agent_shape(array('tier' => 1), $claude), ws_channel_agent_shape(array('tier' => 1), $app));

	pg_assert_same(array(2), array_map(function ($row) {
		return (int) $row['agent']['id'];
	}, ws_channel_agents_readable($rows)), 'a claude row is not read');
}

function test_workspace_channel_agents_api_refuses_claude()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_agents.php');
	$set = substr($source, strpos($source, 'function ws_api_channel_agents_set('));
	$set = substr($set, 0, strpos($set, 'function ws_api_channel_agents_remove('));
	$remove = substr($source, strpos($source, 'function ws_api_channel_agents_remove('));

	pg_assert_true(strpos($set, 'ws_channel_agent_kind_problem(') !== false, 'the add asks for the kind');
	pg_assert_true(strpos($set, 'ws_channel_agent_kind_problem(') < strpos($set, 'api_dry_run_stop('), 'a rehearsal is refused too');
	pg_assert_contains("api_fail(422, 'validation_failed'", substr($set, strpos($set, 'ws_channel_agent_kind_problem('), 200), 'with 422');
	pg_assert_true(strpos($remove, 'ws_channel_agent_kind_problem(') === false, 'an old claude row can still be taken out');

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_agents_panel.php');
	$action = substr($panel, strpos($panel, 'function ws_channel_agents_action('));
	pg_assert_true(strpos($action, 'ws_channel_agent_kind_problem(') > strpos($action, "'ws_channel_agents_remove'"), 'the panel refuses it after the removal');
	pg_assert_true(strpos($action, 'ws_channel_agent_kind_problem(') < strpos($action, 'ws_channel_agent_save('), 'and before the row is written');
}
