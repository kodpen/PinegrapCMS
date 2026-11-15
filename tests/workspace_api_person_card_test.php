<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's card in a channel in the external API and MCP
 * (api_person_card.php): the route and its tool, who is refused, and the
 * last seen given only to a manager of the channel.
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

// A card as ws_person_card() gives it, its last seen filled in.
function ws_api_person_card_test_card()
{
	return array(
		'id' => 7, 'name' => 'Ada Lovelace', 'username' => 'ada', 'role' => 2, 'title' => 'Engineer',
		'avatar' => '/files/ada.png', 'avatar_kind' => 'photo', 'presence' => 'away', 'initials' => 'AL',
		'role_label' => 'Manager',
		'departments' => array(
			array('id' => 3, 'name' => 'Design', 'color' => '#336699', 'lead' => true, 'leads' => array(array('id' => 9, 'name' => 'Grace'))),
		),
		'workday' => array('minutes' => 450, 'hours' => '7h 30m', 'days' => array(1, 2, 3, 4, 5)),
		'away' => array(array('start' => '2026-11-14', 'end' => '2026-11-16', 'now' => true, 'source' => 'plan', 'title' => '')),
		'clock' => array('zone' => 'Europe/London', 'time' => '09:15', 'other' => true),
		'seen' => 'Seen 5 minutes ago',
	);
}

function ws_api_person_card_test_route()
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.channels.members.card') {
			return $route;
		}
	}

	return null;
}

// The card is a read of the team, under workspace:read, beside the members.
function test_ws_api_person_card_route()
{
	$route = ws_api_person_card_test_route();

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('GET', $route['method'], 'a read');
	pg_assert_same('/workspace/channels/{id}/members/{user_id}', $route['path'], 'under the channel\'s members');
	pg_assert_same('workspace:read', $route['scope'], 'no new scope');
	pg_assert_same('WorkspacePersonCard', $route['returns'], 'the card');
	pg_assert_true(empty($route['dry_run']), 'nothing to rehearse');
	pg_assert_same('ws_api_person_card_schema', ws_openapi_objects()['WorkspacePersonCard'] ?? null, 'the component');
}

// The route is an MCP tool for a key with workspace:read.
function test_ws_api_person_card_is_an_mcp_tool()
{
	$route = ws_api_person_card_test_route();

	pg_assert_true(!isset($route['mcp']) || ($route['mcp'] !== false), 'not kept off MCP');

	$ids = array();

	foreach (pg_mcp_routes_for(ws_api_routes(), array('workspace:read')) as $one) {
		$ids[] = $one['id'];
	}

	pg_assert_true(in_array('workspace.channels.members.card', $ids, true), 'offered as a tool');
	pg_assert_same('workspace_channels_members_card', pg_mcp_tool_name('workspace.channels.members.card'), 'its tool name');
}

// A team reader of the channel gets the card the panel draws.
function test_ws_api_person_card_for_a_team_reader()
{
	$card = ws_api_person_card_present(ws_api_person_card_test_card(), false);

	pg_assert_same(array_keys(ws_api_person_card_schema()), array_keys($card), 'the declared keys, in order');
	pg_assert_same(7, $card['user_id'], 'the person');
	pg_assert_same('Manager', $card['role_label'], 'the role in words');
	pg_assert_same(array(array('user_id' => 9, 'name' => 'Grace')), $card['departments'][0]['leads'], 'the other leads');
	pg_assert_same(true, $card['departments'][0]['lead'], 'leads the department');
	pg_assert_same(array(1, 2, 3, 4, 5), $card['workday']['days'], 'the working days');
	pg_assert_same('plan', $card['away'][0]['source'], 'an away range');
	pg_assert_same('09:15', $card['clock']['time'], 'the local time');
	pg_assert_same(array_keys(ws_api_person_card_department_schema()), array_keys($card['departments'][0]), 'a department\'s keys');
	pg_assert_same(array_keys(ws_api_person_card_away_schema()), array_keys($card['away'][0]), 'an away range\'s keys');
	pg_assert_same(array_keys(ws_api_person_card_workday_schema()), array_keys($card['workday']), 'the working day\'s keys');
	pg_assert_same(array_keys(ws_api_person_card_clock_schema()), array_keys($card['clock']), 'the clock\'s keys');
}

// The last seen goes to a manager of the channel only, whatever the card holds.
function test_ws_api_person_card_seen_only_for_a_manager()
{
	pg_assert_same(null, ws_api_person_card_present(ws_api_person_card_test_card(), false)['seen'], 'a reader who does not manage gets none');
	pg_assert_same('Seen 5 minutes ago', ws_api_person_card_present(ws_api_person_card_test_card(), true)['seen'], 'a manager gets it');
	pg_assert_same(null, ws_api_person_card_present(array('seen' => '') + ws_api_person_card_test_card(), true)['seen'], 'never seen is null');

	// The panel's reading sends no last seen to one who does not manage.
	pg_assert_same(array(), ws_channel_member_seen(false, array(7), time()), 'ws_channel_member_seen() for a reader');

	// A user-role reader who does not own the channel does not manage it.
	$reader = array('member' => true, 'id' => 5, 'role' => 3);
	$channel = array('id' => 40, 'kind' => 'public', 'owner_user_id' => 6);

	pg_assert_same(false, ws_can_manage_channel($reader, $channel), 'a user-role reader');
	pg_assert_same(true, ws_can_manage_channel(array('id' => 6) + $reader, $channel), 'the channel\'s owner');
}

// The owner outside the workspace, a channel it may not read and a person
// not on the team (a guest, a former member, an application) are refused.
function test_ws_api_person_card_refusals()
{
	$outsider = array('member' => false, 'id' => 5, 'role' => 0);

	pg_assert_same(false, ws_can_read_channel($outsider, array('id' => 40, 'kind' => 'public', 'owner_user_id' => 6)), 'no channel for a non-member');
	pg_assert_same(null, ws_person_card(array('member' => true, 'id' => 5, 'role' => 0), array('id' => 40, 'kind' => 'public'), 0, time()), 'no card without a person');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_person_card.php');
	$handler = substr($source, strpos($source, 'function ws_api_channel_member_card('));
	$handler = substr($handler, 0, strpos($handler, "\n}\n"));

	pg_assert_true(strpos($handler, 'ws_api_viewer()') !== false, 'a non-member owner is refused 403 by ws_api_viewer()');
	pg_assert_true(strpos($handler, 'ws_api_viewer()') < strpos($handler, 'ws_api_channel_or_404('), 'the owner first, then the channel');
	pg_assert_true(strpos($handler, 'ws_api_channel_or_404(') < strpos($handler, 'ws_person_card('), 'the channel is read before the card');
	pg_assert_true(strpos($handler, 'ws_person_card(') < strpos($handler, 'api_fail_not_found('), 'no card (not on the team) is 404');
	pg_assert_true(strpos($handler, 'ws_can_manage_channel($viewer, $channel)') !== false, 'seen follows the manager check');
}
