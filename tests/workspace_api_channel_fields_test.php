<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's settings over the API (includes/workspace/api.php,
 * api_resources.php): the records watch on the channel update, the summary
 * route, the Claude, Pinegrap AI and voice-transcript switches (the person's
 * own, no MCP tool), and the fields the channel object carries for them.
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

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_channel_fields_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

function ws_channel_fields_test_source($handler)
{
	$function = new ReflectionFunction($handler);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The records watch is a field of the channel update, beside the others.
function test_ws_channel_fields_watch_on_update()
{
	$routes = ws_channel_fields_test_routes();
	$names = array_column($routes['workspace.channels.update']['params'], 'name');

	pg_assert_true(in_array('watch', $names, true), 'watch is a field of the channel update');

	$source = ws_channel_fields_test_source($routes['workspace.channels.update']['handler']);
	pg_assert_contains('ws_watch_channel_state(', $source, 'refused where the channel cannot watch records');
	pg_assert_true(strpos($source, "'watch'") < strpos($source, 'api_dry_run_stop('), 'checked before the dry run stops');
}

// The summary: any writer of the channel, a tool, with a dry run.
function test_ws_channel_fields_summary_route()
{
	$routes = ws_channel_fields_test_routes();
	pg_assert_true(isset($routes['workspace.channels.summary']), 'the summary route is declared');

	$route = $routes['workspace.channels.summary'];
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/channels/{id}/summary', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_true(!empty($route['dry_run']), 'it rehearses');
	pg_assert_true(pg_mcp_route_is_offered($route), 'it is a tool');

	$source = ws_channel_fields_test_source($route['handler']);
	pg_assert_contains('ws_can_post_channel(', $source, 'the panel\'s own check');
	pg_assert_true(strpos($source, 'api_dry_run_stop(') < strpos($source, 'ws_channel_set_summary('), 'the dry run stops above the write');
}

// The three switches: the person's own, never an application key, no tool.
function test_ws_channel_fields_switches_are_the_persons()
{
	$routes = ws_channel_fields_test_routes();
	pg_assert_true(isset($routes['workspace.channels.switches']), 'the switches route is declared');

	$route = $routes['workspace.channels.switches'];
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/channels/{id}/switches', $route['path']);
	pg_assert_true(!empty($route['dry_run']), 'it rehearses');
	pg_assert_same(false, $route['mcp'] ?? null, 'carries \'mcp\' => false');
	pg_assert_false(pg_mcp_route_is_offered($route), 'no MCP tool sets them');
	pg_assert_same(array('id', 'claude', 'ai', 'voice'), array_column($route['params'], 'name'));

	$source = ws_channel_fields_test_source($route['handler']);
	$gate = strpos($source, 'ws_api_person_only(');
	pg_assert_true($gate !== false, 'an application key is refused');
	pg_assert_true($gate < strpos($source, 'api_dry_run_stop('), 'refused before a dry run too');
	pg_assert_contains('ws_can_manage_channel(', $source, 'the panel\'s own check');

	foreach (array('ws_claude_channel_set(', 'ws_ai_channel_set(', 'ws_voice_channel_set(') as $writer) {
		pg_assert_true(strpos($source, 'api_dry_run_stop(') < strpos($source, $writer), $writer . ' runs after the dry run stops');
	}

	pg_assert_true(!isset(array_flip(array_column($routes['workspace.channels.update']['params'], 'name'))['claude']), 'the switches are not fields of the update, a tool');
}

// An archived channel is read, never switched: the route refuses it before
// the dry run, and the three setters the panel's channel menu calls too.
function test_ws_channel_fields_switches_refuse_archived()
{
	$routes = ws_channel_fields_test_routes();
	$source = ws_channel_fields_test_source($routes['workspace.channels.switches']['handler']);
	$gate = strpos($source, "archived_at']");
	pg_assert_true($gate !== false, 'the route checks the archive');
	pg_assert_true($gate < strpos($source, 'api_dry_run_stop('), 'refused before a dry run too');

	$viewer = array('id' => 1, 'member' => true, 'role' => 0);
	$channel = array('id' => 1, 'kind' => 'public', 'archived_at' => 1700000000, 'claude_access' => 2, 'ai_access' => 2, 'voice_access' => 2);
	$archived = lang('This channel is archived. It can be read but not written in.');

	foreach (array('ws_claude_channel_set', 'ws_ai_channel_set', 'ws_voice_channel_set') as $setter) {
		pg_assert_true(function_exists($setter), $setter . ' is loaded');
		$result = $setter($viewer, $channel, true);
		pg_assert_false($result['ok'], $setter . ' refuses an archived channel');
		pg_assert_same($archived, $result['error'], $setter . ' says why');
	}
}

// A discussion follows its channel for Claude and Pinegrap AI: it offers no
// switch of its own, so the object carries null (the route answers 422 on the
// field) and the two setters refuse it, never writing a line that is not true.
function test_ws_channel_fields_discussion_offers_no_assistant_switch()
{
	$thread = ws_api_channel_settings_present(array(
		'claude' => array('ready' => true, 'allowed' => true, 'available' => true),
		'ai'     => array('ready' => true, 'allowed' => true, 'available' => true),
		'voice'  => null, 'watch' => null, 'shared' => false, 'thread' => true,
		'summary' => '', 'summary_updated_at' => 0,
	));
	pg_assert_same(null, $thread['claude_allowed'], 'a discussion offers no Claude switch');
	pg_assert_same(null, $thread['ai_allowed'], 'a discussion offers no Pinegrap AI switch');

	$source = ws_channel_fields_test_source('ws_api_channel_settings_states');
	pg_assert_contains("'thread'", $source, 'the states say whether the channel is a discussion');

	$viewer = array('id' => 1, 'member' => true, 'role' => 0);
	$channel = array('id' => 2, 'kind' => 'thread', 'archived_at' => 0, 'claude_access' => 0, 'ai_access' => 0);
	$follows = lang('A discussion follows its channel: switch it in the channel.');

	foreach (array('ws_claude_channel_set', 'ws_ai_channel_set') as $setter) {
		$result = $setter($viewer, $channel, false);
		pg_assert_false($result['ok'], $setter . ' refuses a discussion');
		pg_assert_same($follows, $result['error'], $setter . ' says why');
	}
}

// The channel object: the switches, the watch and the summary as the panel
// shows them to the reader, null in a list.
function test_ws_channel_fields_settings_present()
{
	$schema = ws_api_channel_schema();

	foreach (array('watch' => 'boolean?', 'claude_allowed' => 'boolean?', 'ai_allowed' => 'boolean?', 'voice_allowed' => 'boolean?', 'summary' => 'string?', 'summary_updated_at' => 'string?') as $field => $type) {
		pg_assert_same($type, $schema[$field] ?? null, $field . ' is on the channel object');
	}

	$full = ws_api_channel_settings_present(array(
		'claude'             => array('ready' => true, 'allowed' => false, 'available' => false),
		'ai'                 => array('ready' => false, 'allowed' => true, 'available' => false),
		'voice'              => array('allowed' => true, 'provider' => 'x'),
		'watch'              => false,
		'shared'             => false,
		'summary'            => 'The plan',
		'summary_updated_at' => 0,
	));
	pg_assert_same(false, $full['claude_allowed']);
	pg_assert_same(true, $full['ai_allowed']);
	pg_assert_same(true, $full['voice_allowed']);
	pg_assert_same(false, $full['watch']);
	pg_assert_same('The plan', $full['summary']);
	pg_assert_same(null, $full['summary_updated_at'], 'never written');

	$shared = ws_api_channel_settings_present(array(
		'claude' => array('allowed' => true), 'ai' => array('allowed' => true), 'voice' => null,
		'watch' => null, 'shared' => true, 'summary' => '', 'summary_updated_at' => 0,
	));
	pg_assert_same(null, $shared['claude_allowed'], 'a channel shared with guests offers no Claude switch');
	pg_assert_same(null, $shared['ai_allowed']);
	pg_assert_same(null, $shared['voice_allowed'], 'none where it cannot be switched');
	pg_assert_same(null, $shared['watch']);

	$listed = ws_api_channel_present(array(
		'id' => 1, 'name' => 'ops', 'kind' => 'public', 'topic' => '', 'joined' => true,
		'contact_id' => 0, 'department_id' => 0, 'archived' => false, 'unread' => 0, 'last_message_at' => 0, 'icon' => 'bi-hash',
	));
	pg_assert_same(null, $listed['summary'], 'a list carries none');
	pg_assert_same(null, $listed['claude_allowed']);
}
