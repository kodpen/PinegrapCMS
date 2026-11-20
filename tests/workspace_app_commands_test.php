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

	pg_assert_true(isset($routes['workspace.commands.register']), 'register route');
	$params = array();

	foreach ($routes['workspace.commands.register']['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_same('list', $params['args']['type'] ?? null, 'args is a list');
	pg_assert_true(!empty($params['args']['nullable']), 'null removes the definition');
	pg_assert_true(!isset($params['args']['max_items']), 'the ninth item is refused by the check, as too_many_args');
}

// A definition in the registry's own shape: suggest is the server's, flags
// default false, the label defaults to the name; every kind is accepted.
function test_workspace_app_command_args_check_keeps_the_registry_shape()
{
	$ok = ws_app_command_args_check(array(
		array('name' => 'who', 'kind' => 'person', 'label' => 'person'),
		array('name' => 'day', 'kind' => 'date', 'optional' => true, 'anywhere' => true, 'suggest' => 'tasks'),
	));

	pg_assert_same(array(
		array('name' => 'who', 'kind' => 'person', 'label' => 'person', 'optional' => false, 'suggest' => 'people', 'multiple' => false, 'anywhere' => false),
		array('name' => 'day', 'kind' => 'date', 'label' => 'day', 'optional' => true, 'suggest' => 'dates', 'multiple' => false, 'anywhere' => true),
	), $ok['args'], 'registry shape, suggest from the kind');

	foreach (ws_command_argument_kinds() as $kind) {
		pg_assert_true(isset(ws_app_command_args_check(array(array('name' => 'a', 'kind' => $kind)))['args']), 'kind ' . $kind);
	}

	pg_assert_same('', ws_app_command_args_check(array(array('name' => 'a', 'kind' => 'text')))['args'][0]['suggest'], 'text has no source');

	foreach (array(null, '', array()) as $none) {
		pg_assert_same(array('args' => array()), ws_app_command_args_check($none), 'no definition');
	}

	pg_assert_same('<person> [day]', ws_app_command_args_usage($ok['args']), 'usage from the labels');
}

// An unknown kind and a ninth argument are refused with their own codes;
// other bad items name their index.
function test_workspace_app_command_args_check_refuses()
{
	pg_assert_same(array('error' => 'invalid_kind', 'index' => 1), ws_app_command_args_check(array(array('name' => 'a', 'kind' => 'text'), array('name' => 'b', 'kind' => 'colour'))));

	$nine = array();

	for ($i = 0; $i < 9; $i++) {
		$nine[] = array('name' => 'a' . $i, 'kind' => 'text');
	}

	pg_assert_same('too_many_args', ws_app_command_args_check($nine)['error']);
	pg_assert_true(isset(ws_app_command_args_check(array_slice($nine, 0, 8))['args']), 'eight is the limit');

	$bad = array(
		array(array('name' => 'Who', 'kind' => 'text')),
		array(array('name' => '1a', 'kind' => 'text')),
		array(array('name' => 'abcdefghijklmnopqrstu', 'kind' => 'text')),
		array(array('name' => 'a', 'kind' => 'text'), array('name' => 'a', 'kind' => 'date')),
		array(array('name' => 'a', 'kind' => 'text', 'label' => str_repeat('x', 41))),
		array(array('name' => 'a', 'kind' => 'text', 'label' => "two\nlines")),
		array(array('name' => 'a', 'kind' => 'text', 'optional' => 'yes')),
		array('a'),
		array('x' => array('name' => 'a', 'kind' => 'text')),
		'text',
	);

	foreach ($bad as $index => $args) {
		pg_assert_same('invalid_args', ws_app_command_args_check($args)['error'] ?? null, 'bad item ' . $index);
	}
}

// A stored entry that cannot be resolved is no definition, never an error.
function test_workspace_app_command_args_read_unresolvable_is_none()
{
	pg_assert_same(array(), ws_app_command_args_read(null));
	pg_assert_same(array(), ws_app_command_args_read(''));
	pg_assert_same(array(), ws_app_command_args_read('{not json'));
	pg_assert_same(array(), ws_app_command_args_read('"text"'));
	pg_assert_same(array(), ws_app_command_args_read(json_encode(array(array('name' => 'a', 'kind' => 'colour')))), 'unknown kind');
	pg_assert_same('dates', ws_app_command_args_read(json_encode(array(array('name' => 'day', 'kind' => 'date'))))[0]['suggest']);

	$row = array('id' => 1, 'app_id' => 7, 'name' => 'rapor', 'description' => 'd', 'usage_text' => '', 'app_name' => 'A', 'args' => '[{"name":"a","kind":"nope"}]');
	pg_assert_same(array(), ws_app_command_entry($row)['args'], 'the entry takes free text');

	$row['args'] = json_encode(array(array('name' => 'day', 'kind' => 'date', 'label' => 'day')));
	$entry = ws_app_command_entry($row);
	pg_assert_same('date', $entry['args'][0]['kind'], 'a resolvable definition reaches the picker');
	pg_assert_same(array_keys(ws_api_command_argument_schema()), array_keys(ws_api_command_present($entry)['args'][0]));
}

// The words after the command read into values by argument name.
function test_workspace_app_command_values_by_name()
{
	$args = ws_app_command_args_check(array(
		array('name' => 'who', 'kind' => 'person'),
		array('name' => 'team', 'kind' => 'people', 'optional' => true),
		array('name' => 'order', 'kind' => 'record', 'optional' => true),
		array('name' => 'due', 'kind' => 'date', 'optional' => true),
		array('name' => 'at', 'kind' => 'time', 'optional' => true),
		array('name' => 'est', 'kind' => 'estimate', 'optional' => true),
		array('name' => 'price', 'kind' => 'amount', 'optional' => true),
		array('name' => 'topic', 'kind' => 'text', 'label' => 'topic'),
	))['args'];

	$read = ws_app_command_values($args, 'Quarter report <@user:5> <#order:12> 2025-x 25.12 14:30 ~2h 149,90 <@user:6> <@user:7>', '2026-11-20');

	pg_assert_same('', $read['missing']);
	pg_assert_same(array('who', 'team', 'order', 'due', 'at', 'est', 'price', 'topic'), array_keys($read['values']), 'every argument by name, in order');
	pg_assert_same(5, $read['values']['who']);
	pg_assert_same(array(6, 7), $read['values']['team'], 'people take the rest of the person tags');
	pg_assert_same(array('type' => 'order', 'id' => 12), $read['values']['order']);
	pg_assert_same('2026-12-25', $read['values']['due']);
	pg_assert_same('14:30', $read['values']['at']);
	pg_assert_same(120, $read['values']['est']);
	pg_assert_same(14990, $read['values']['price'], 'minor units');
	pg_assert_same('Quarter report 2025-x', $read['values']['topic'], 'the words left over');

	$read = ws_app_command_values($args, 'just a topic <@user:5>', '2026-11-20');
	pg_assert_same(null, $read['values']['team'], 'an optional argument not typed is null');
	pg_assert_same(null, $read['values']['due']);

	$read = ws_app_command_values($args, 'no person here', '2026-11-20');
	pg_assert_same('who', $read['missing'], 'a required argument not read is named');

	$two = ws_app_command_args_check(array(array('name' => 'env', 'kind' => 'text'), array('name' => 'tags', 'kind' => 'options', 'optional' => true)))['args'];
	pg_assert_same(array('env' => 'staging', 'tags' => array('fast', 'quiet')), ws_app_command_values($two, 'staging fast, quiet')['values'], 'text in turn, the last takes the rest');
}

// The event carries values only for a command with a definition; one
// without is told what it was told before.
function test_workspace_app_command_payload_values_only_with_a_definition()
{
	$channel = array('id' => 3, 'name' => 'general', 'kind' => 'public');
	$plain = ws_app_command_payload($channel, null, 7, 'report', 5, 'x', 'x');
	pg_assert_false(array_key_exists('values', $plain), 'no definition: no values key');

	$with = ws_app_command_payload($channel, null, 7, 'report', 5, 'x', 'x', 0, array('topic' => 'x'));
	pg_assert_same(array('topic' => 'x'), $with['values']);
	pg_assert_same(array_keys($plain), array_slice(array_keys($with), 0, count($plain)), 'the fields of before stay');
}

// The limit of twenty commands is unchanged by the definitions.
function test_workspace_app_command_limit_unchanged()
{
	pg_assert_same(20, WS_APP_COMMANDS_MAX);
	pg_assert_same(8, WS_APP_COMMAND_ARGS_MAX);
}
