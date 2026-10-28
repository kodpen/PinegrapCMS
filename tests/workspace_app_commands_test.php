<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the / commands an application registers
 * (includes/workspace/app_commands.php): which names it may take, how its
 * description and usage line are checked and kept, which applications'
 * commands are offered, how a typed one is found, what
 * workspace.app.command carries and whom it reaches, and the routes and
 * scope that register them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/app_commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

// A name is kept in Turkish lower case without its slash, the texts on one
// line, so the picker and the parser find it as typed.
function test_workspace_app_command_check_keeps_the_clean_shape()
{
	$ok = ws_app_command_check(array('name' => '/Özetle', 'description' => "  Sums up\nthe channel ", 'usage' => '[days]'));
	pg_assert_same(array('name' => 'özetle', 'description' => 'Sums up the channel', 'usage' => '[days]'), $ok, 'clean shape');

	$ok = ws_app_command_check(array('name' => 'Işık', 'description' => 'Light'));
	pg_assert_same('ışık', $ok['name'], 'Turkish dotless I');
	pg_assert_same('', $ok['usage'], 'usage may be left out');
}

// Only one word of letters reads as a command in the composer
// (ws_command_attempt()), so other shapes are refused.
function test_workspace_app_command_check_refuses_names_that_are_no_command()
{
	foreach (array('', 'a', 'sum-up', 'sum up', 'report2', 'abcdefghijklmnopqrstu', '//x') as $name) {
		pg_assert_same('name_invalid', ws_app_command_check(array('name' => $name, 'description' => 'x'))['error'], 'name ' . $name);
	}

	pg_assert_same('name_invalid', ws_app_command_check(array('name' => 5, 'description' => 'x'))['error'], 'not a string');
	pg_assert_same('name_invalid', ws_app_command_check(null)['error'], 'no input');
}

// A built-in name or an assistant's word means one thing, whichever case or
// slash it comes with.
function test_workspace_app_command_check_refuses_built_in_names()
{
	foreach (array('task', 'GÖREV', '/help', 'ürün', 'urun', 'Toplantı', 'claude', 'AI') as $name) {
		pg_assert_same('name_taken', ws_app_command_check(array('name' => $name, 'description' => 'x'))['error'], 'name ' . $name);
	}
}

function test_workspace_app_command_check_bounds_the_texts()
{
	pg_assert_same('description_required', ws_app_command_check(array('name' => 'report', 'description' => " \n "))['error'], 'blank description');
	pg_assert_same('description_required', ws_app_command_check(array('name' => 'report'))['error'], 'no description');
	pg_assert_same('description_too_long', ws_app_command_check(array('name' => 'report', 'description' => str_repeat('ş', 201)))['error'], 'long description');
	pg_assert_same('Report', ws_app_command_check(array('name' => 'report', 'description' => 'Report', 'usage' => array('x')))['description'], 'usage not a string is empty');
	pg_assert_same('usage_too_long', ws_app_command_check(array('name' => 'report', 'description' => 'x', 'usage' => str_repeat('a', 201)))['error'], 'long usage');
	pg_assert_same(str_repeat('ş', 200), ws_app_command_check(array('name' => 'report', 'description' => str_repeat('ş', 200)))['description'], 'two hundred letters fit');
}

// Only a working key granted workspace:commands offers its commands: one
// disabled, revoked or expired, or holding only the channel scopes, is
// answered as unknown in the channel.
function test_workspace_app_command_app_ok()
{
	$app = array('status' => 'active', 'expires_at' => 0, 'scopes' => '["workspace:commands","workspace:read"]');

	pg_assert_true(ws_app_command_app_ok($app), 'active with the scope');
	pg_assert_false(ws_app_command_app_ok(array('status' => 'disabled') + $app), 'disabled');
	pg_assert_false(ws_app_command_app_ok(array('status' => 'revoked') + $app), 'revoked');
	pg_assert_false(ws_app_command_app_ok(array('expires_at' => 100) + $app, 200), 'expired');
	pg_assert_true(ws_app_command_app_ok(array('expires_at' => 300) + $app, 200), 'not yet expired');
	pg_assert_false(ws_app_command_app_ok(array('scopes' => '["workspace:read","workspace:write"]') + $app), 'without workspace:commands');
	pg_assert_false(ws_app_command_app_ok(array('scopes' => 'not json') + $app), 'unreadable scopes');
}

// A typed word finds its command with the Turkish letters folded, as the
// table's collation compares names; anything else finds none.
function test_workspace_app_command_find_and_entry()
{
	$rows = array(
		array('id' => 1, 'app_id' => 7, 'name' => 'özetle', 'description' => 'Sums up', 'usage_text' => '[days]', 'app_name' => 'Notes bot'),
		array('id' => 2, 'app_id' => 8, 'name' => 'report', 'description' => 'Reports', 'usage_text' => '', 'app_name' => 'Reports'),
	);

	pg_assert_same(1, ws_app_command_find('özetle', $rows)['id'], 'as registered');
	pg_assert_same(1, ws_app_command_find('OZETLE', $rows)['id'], 'capitals, letters folded');
	pg_assert_same(2, ws_app_command_find('Report', $rows)['id']);
	pg_assert_same(null, ws_app_command_find('reports', $rows), 'no prefix match');
	pg_assert_same(null, ws_app_command_find('', $rows));

	$entry = ws_app_command_entry($rows[0]);
	pg_assert_same('app:özetle', $entry['key']);
	pg_assert_same(array('/özetle'), $entry['names']);
	pg_assert_same('/özetle [days]', $entry['usage']);
	pg_assert_same('/özetle · Notes bot', $entry['label']);
	pg_assert_same(array(), $entry['args'], 'free text after the name');
	pg_assert_same(false, $entry['live']);
	pg_assert_same(7, $entry['app_id']);
	pg_assert_same('/report', ws_app_command_entry($rows[1])['usage'], 'no usage: the name alone');

	$present = ws_api_command_present($entry);
	pg_assert_same(array_keys(ws_api_command_schema()), array_keys($present), 'an app command answers the same fields');
	pg_assert_same(7, $present['app_id']);
	pg_assert_same('Notes bot', $present['app']);
}

// The built-in registry, read without a person, names no application and
// touches no table.
function test_workspace_app_command_built_ins_name_no_app()
{
	foreach (ws_commands(null) as $entry) {
		pg_assert_same(0, $entry['app_id'], $entry['key']);
		pg_assert_same(null, ws_api_command_present($entry)['app'], $entry['key']);
	}
}

// What the application is told, and that only its own subscriptions hear it.
function test_workspace_app_command_payload_and_audience()
{
	$channel = array('id' => 40, 'name' => 'Discussion', 'kind' => 'thread');
	$payload = ws_app_command_payload($channel, array('parent_channel_id' => 9, 'message_id' => 470), 7, 'özetle', 5, 'last week', 'last week', 480);

	pg_assert_same(array('command', 'app_id', 'channel_id', 'channel', 'channel_kind', 'thread', 'parent_id', 'user_id', 'text', 'body'), array_keys($payload));
	pg_assert_same('/özetle', $payload['command']);
	pg_assert_same(40, $payload['channel_id'], 'answered where it was typed');
	pg_assert_same(array('channel_id' => 9, 'message_id' => 470), $payload['thread']);
	pg_assert_same(5, $payload['user_id']);
	pg_assert_same(480, $payload['parent_id']);
	pg_assert_same(null, ws_app_command_payload(array('id' => 3, 'name' => 'x', 'kind' => 'public'), null, 7, 'x', 5, '', '')['thread'], 'no discussion');

	pg_assert_same(7, ws_webhook_event_app_id('workspace.app.command', $payload), 'only the registering application');
	pg_assert_same(-1, ws_webhook_event_app_id('workspace.app.command', array('channel_id' => 3)), 'none named: nobody');
	pg_assert_same(7, api_webhook_event_app_id('workspace.app.command', $payload), 'the queue asks the workspace');
	pg_assert_same(9, ws_webhook_event_channel_id($payload), 'a narrowed subscription matches the channel the discussion is about');

	$events = ws_webhook_events();
	pg_assert_true(isset($events['workspace.app.command']), 'in the catalogue');
	pg_assert_contains('POST /workspace/channels/{channel_id}/messages', (string) ($events['workspace.app.command'] ?? ''), 'says how to answer');
	pg_assert_true(strlen('workspace.app.command') <= 50, 'fits api_webhook_queue.event');
}

// The scope is a write of its own, inside the owner ceiling, and the routes
// rehearse, carry no MCP tool and have a POST twin for the delete.
function test_workspace_app_command_scope_and_routes()
{
	$groups = ws_scope_groups();
	pg_assert_same('workspace:commands', $groups['workspace_commands']['write']);
	pg_assert_same('', $groups['workspace_commands']['read']);

	$routes = array();

	foreach (ws_api_routes() as $item) {
		$routes[$item['id']] = $item;
	}

	foreach (array('workspace.commands.register' => array('POST', '/workspace/commands'), 'workspace.commands.delete' => array('DELETE', '/workspace/commands/{name}'), 'workspace.commands.delete_post' => array('POST', '/workspace/commands/{name}/delete')) as $id => $where) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same($where, array($routes[$id]['method'], $routes[$id]['path']), $id);
		pg_assert_same('workspace:commands', $routes[$id]['scope'], $id);
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	$objects = ws_openapi_objects();
	pg_assert_same('ws_api_app_command_schema', $objects['WorkspaceAppCommand'] ?? null);
	pg_assert_same(array_keys(ws_api_app_command_schema()), array_keys(ws_api_app_command_present('x', 'd', '', 7)));
}
