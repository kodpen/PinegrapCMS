<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for who reads a channel and the versions of its conversation in the
 * external API (api_channel_access.php): make private or public, join,
 * clear and rename a version. Person only, no MCP tool, the panel's checks,
 * a dry run on each, and who comes in or leaves on a change of kind.
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

require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function ws_api_channel_access_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

// The body of one function of api_channel_access.php.
function ws_api_channel_access_test_source($function)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_channel_access.php');
	$body = substr($source, strpos($source, 'function ' . $function . '('));

	return substr($body, 0, strpos($body, "\n}\n"));
}

function ws_api_channel_access_test_ids()
{
	return array(
		'workspace.channels.kind'        => array('/workspace/channels/{id}/kind', 'ws_api_channel_kind'),
		'workspace.channels.join'        => array('/workspace/channels/{id}/join', 'ws_api_channel_join'),
		'workspace.channels.clear'       => array('/workspace/channels/{id}/clear', 'ws_api_channel_clear'),
		'workspace.channels.eras.rename' => array('/workspace/channels/{id}/eras/{number}', 'ws_api_channel_era_rename'),
	);
}

// Each route is a REST write with a dry run, under workspace:write, and no tool.
function test_ws_api_channel_access_routes_are_person_writes_off_mcp()
{
	$routes = ws_api_channel_access_test_routes();

	foreach (ws_api_channel_access_test_ids() as $id => $expected) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is a POST');
		pg_assert_same($expected[0], $routes[$id]['path'], $id . ' path');
		pg_assert_same($expected[1], $routes[$id]['handler'], $id . ' handler');
		pg_assert_same('workspace:write', $routes[$id]['scope'], $id . ' needs no new scope');
		pg_assert_same(true, $routes[$id]['dry_run'] ?? null, $id . ' has a dry run');
		pg_assert_same(false, $routes[$id]['mcp'] ?? null, $id . ' carries \'mcp\' => false');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	pg_assert_same(array(), array_keys(pg_mcp_routes_for(array_intersect_key($routes, ws_api_channel_access_test_ids()), array('workspace:write', 'workspace:read'))), 'no tool for any of them');
	pg_assert_same('ws_api_channel_kind_change_schema', ws_openapi_objects()['WorkspaceChannelKindChange'] ?? null, 'the kind change object');
	pg_assert_same('ws_api_channel_era_write_schema', ws_openapi_objects()['WorkspaceChannelEraWrite'] ?? null, 'the version object');
}

// An application key is refused on every route before anything is read or
// rehearsed, and each stops for a dry run after its last check and before
// the panel's writer.
function test_ws_api_channel_access_refuses_an_application_key_then_rehearses()
{
	$writers = array(
		'ws_api_channel_kind'       => 'ws_channel_make_',
		'ws_api_channel_join'       => 'ws_channel_join(',
		'ws_api_channel_clear'      => 'ws_channel_clear(',
		'ws_api_channel_era_rename' => 'ws_channel_era_rename(',
	);

	foreach ($writers as $handler => $writer) {
		$body = ws_api_channel_access_test_source($handler);
		$person = strpos($body, 'ws_api_person_only(');
		$stop = strpos($body, 'api_dry_run_stop(');
		$write = strpos($body, $writer);

		pg_assert_true($person !== false, $handler . ' is person only');
		pg_assert_true(($person < $stop) && ($stop < $write), $handler . ': the person, then the dry run, then the write');
		pg_assert_true(strpos($body, 'ws_api_channel_or_404(') < $person, $handler . ' reads the channel as the owner may');
		pg_assert_true((int) strrpos(substr($body, 0, $stop), 'api_fail') > 0, $handler . ' refuses before its stop');
	}

	pg_assert_true(strpos(ws_api_channel_access_test_source('ws_api_channel_kind'), 'ws_can_change_channel_kind(') < strpos(ws_api_channel_access_test_source('ws_api_channel_kind'), 'api_dry_run_stop('), 'kind: the panel\'s check before the stop');
	pg_assert_true(strpos(ws_api_channel_access_test_source('ws_api_channel_clear'), 'ws_channel_clear_span(') < strpos(ws_api_channel_access_test_source('ws_api_channel_clear'), 'api_dry_run_stop('), 'clear: nothing to clear is found before the stop');
	pg_assert_true(strpos(ws_api_channel_access_test_source('ws_api_channel_era_rename'), 'ws_can_clear_channel(') < strpos(ws_api_channel_access_test_source('ws_api_channel_era_rename'), 'api_dry_run_stop('), 'rename: the panel\'s check before the stop');
}

// Who may write is the panel's own rule: staff in the channel. A user-role
// member, somebody outside the workspace (a guest) and an archived channel
// are refused.
function test_ws_api_channel_access_uses_the_panel_checks()
{
	$channel = array('id' => 40, 'kind' => 'public', 'owner_user_id' => 6, 'archived_at' => 0);
	$staff = array('member' => true, 'id' => 5, 'role' => 2);

	pg_assert_false(ws_can_change_channel_kind(array('role' => 3) + $staff, $channel), 'a user-role member');
	pg_assert_false(ws_can_change_channel_kind(array('member' => false) + $staff, $channel), 'somebody outside the workspace');
	pg_assert_false(ws_can_change_channel_kind($staff, array('archived_at' => 1767225600) + $channel), 'an archived channel');
	pg_assert_false(ws_can_change_channel_kind($staff, array('kind' => 'thread') + $channel), 'a discussion');
	pg_assert_false(ws_can_change_channel_kind($staff, array('kind' => 'direct') + $channel), 'a direct message');

	foreach (array('ws_api_channel_clear', 'ws_api_channel_era_rename') as $handler) {
		pg_assert_contains('ws_can_clear_channel($viewer, $channel)', ws_api_channel_access_test_source($handler), $handler . ' asks the panel\'s check');
	}
}

// Join is refused on a private channel, as the panel's own join is.
function test_ws_api_channel_access_join_is_refused_on_a_private_channel()
{
	$viewer = array('member' => true, 'id' => 5, 'role' => 0);

	pg_assert_same(false, ws_channel_join($viewer, array('id' => 41, 'kind' => 'private', 'last_message_id' => 0))['ok'], 'the panel refuses a private channel');
	pg_assert_same(false, ws_channel_join(array('member' => false) + $viewer, array('id' => 40, 'kind' => 'public', 'last_message_id' => 0))['ok'], 'and somebody outside the workspace');

	$body = ws_api_channel_access_test_source('ws_api_channel_join');

	pg_assert_contains("(string) \$channel['kind'] !== 'public'", $body, 'the route refuses anything but a public channel');
	pg_assert_true(strpos($body, "!== 'public'") < strpos($body, 'api_dry_run_stop('), 'before its dry run');
}

// Making a channel private names the team members who stop reading it;
// making it public those who start. Its members read it either way.
function test_ws_api_channel_access_kind_names_who_comes_in_and_leaves()
{
	$team = array(9, 5, 6, 7, 8, 7);
	$members = array(5, 6);

	pg_assert_same(array('gaining' => array(), 'losing' => array(7, 8, 9)), ws_api_channel_kind_forecast('public', 'private', $team, $members), 'made private: the rest of the team leaves');
	pg_assert_same(array('gaining' => array(7, 8, 9), 'losing' => array()), ws_api_channel_kind_forecast('private', 'public', $team, $members), 'made public: the rest of the team comes in');
	pg_assert_same(array('gaining' => array(), 'losing' => array()), ws_api_channel_kind_forecast('public', 'public', $team, $members), 'no change');
	pg_assert_same(array('gaining' => array(), 'losing' => array()), ws_api_channel_kind_forecast('private', 'private', $team, $members), 'no change either way');

	pg_assert_same(array('channel_id', 'kind', 'changed', 'gaining', 'losing'), array_keys(ws_api_channel_kind_change_schema()), 'the answer');
	pg_assert_contains("api_dry_run_stop(\$answer['changed'] ? 'changed' : 'unchanged', 'workspace_channel', \$answer)", ws_api_channel_access_test_source('ws_api_channel_kind'), 'the dry run names them too');
}

// A version as clear and rename answer it.
function test_ws_api_channel_access_version_answer()
{
	$named = ws_api_channel_era_write_present(40, array('id' => 3, 'number' => 2, 'title' => 'Before the launch', 'message_count' => 12));
	$plain = ws_api_channel_era_write_present(40, array('id' => 4, 'number' => 3, 'title' => '', 'message_count' => 1));

	pg_assert_same(array_keys(ws_api_channel_era_write_schema()), array_keys($named), 'the declared keys');
	pg_assert_same('Before the launch', $named['label'], 'its name');
	pg_assert_same(12, $named['messages'], 'its messages');
	pg_assert_same('Version 3', $plain['label'], 'called by its number without one');
}
