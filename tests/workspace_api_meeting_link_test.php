<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the caller's own meeting link in the external API and MCP
 * (api_meeting_link.php): the route, the person-only and booking staff
 * refusals, and the answer as the screen My meeting link shows it.
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

function ws_api_meeting_link_test_route()
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.meeting_link.get') {
			return $route;
		}
	}

	return null;
}

// Two services as pg_booking_link_screen_services() gives them.
function ws_api_meeting_link_test_services()
{
	return array(
		array('id' => '4', 'name' => 'Consultation', 'length_minutes' => '30', 'price' => '0', 'enabled' => '1', 'offered' => true, 'reason' => ''),
		array('id' => '9', 'name' => 'Session', 'length_minutes' => '60', 'price' => '125000', 'deposit' => '1', 'offered' => false, 'reason' => 'deposit'),
	);
}

// A read under workspace:read, with nothing to rehearse.
function test_ws_api_meeting_link_route()
{
	$route = ws_api_meeting_link_test_route();

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('GET', $route['method'], 'a read');
	pg_assert_same('/workspace/meeting-link', $route['path'], 'its path');
	pg_assert_same('workspace:read', $route['scope'], 'no new scope');
	pg_assert_same('WorkspaceMeetingLink', $route['returns'], 'the meeting link');
	pg_assert_true(empty($route['dry_run']), 'nothing to rehearse');
	pg_assert_same('ws_api_meeting_link_schema', ws_openapi_objects()['WorkspaceMeetingLink'] ?? null, 'the component');
	pg_assert_same('ws_api_meeting_link_service_schema', ws_openapi_objects()['WorkspaceMeetingLinkService'] ?? null, 'the service component');
}

// The person's own read: the handler refuses a key, and a caller who is no
// booking staff gets the screen's 404.
function test_ws_api_meeting_link_refusals()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_meeting_link.php');
	$start = strpos($source, 'function ws_api_meeting_link_get(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_true(strpos($body, 'ws_api_person_only(') !== false, 'a key is refused');
	pg_assert_true(strpos($body, 'pg_booking_link_screen_member(') !== false, 'the screen\'s own member reading');
	pg_assert_true(strpos($body, 'api_fail_not_found(') !== false, 'no booking staff is a 404');
	pg_assert_true(strpos($body, 'db_q(') === false && strpos($body, 'UPDATE') === false, 'read only');
}

// The answer carries the declared keys, a link and its short address.
function test_ws_api_meeting_link_present_bookable()
{
	$link = ws_api_meeting_link_present(true, 'https://example.com/pinegrap/book_with.php?c=3-ab', 'ada meet', 'https://example.com/', 'https://meet.example.com/ada', ws_api_meeting_link_test_services());

	pg_assert_same(array_keys(ws_api_meeting_link_schema()), array_keys($link), 'the declared keys, in order');
	pg_assert_same(true, $link['bookable'], 'bookable');
	pg_assert_same('https://example.com/pinegrap/book_with.php?c=3-ab', $link['link_url'], 'the link');
	pg_assert_same('https://example.com/' . encode_url_path('ada meet'), $link['short_url'], 'the short address as the screen builds it');
	pg_assert_same('https://meet.example.com/ada', $link['meeting_url'], 'the meeting address');
	pg_assert_same(array_keys(ws_api_meeting_link_service_schema()), array_keys($link['services'][0]), 'a service\'s keys');
	pg_assert_same(array('id' => 4, 'name' => 'Consultation', 'length_minutes' => 30, 'price' => 0, 'offered' => true, 'reason' => ''), $link['services'][0], 'a service on the link');
	pg_assert_same(125000, $link['services'][1]['price'], 'price in minor units');
	pg_assert_same(false, $link['services'][1]['offered'], 'a deposit service is not on the link');
	pg_assert_same('deposit', $link['services'][1]['reason'], 'and says why');
}

// No link, no short address: a member with no services, or a site with no key.
function test_ws_api_meeting_link_present_without_a_link()
{
	$none = ws_api_meeting_link_present(false, 'https://example.com/book_with.php?c=3-ab', 'ada', 'https://example.com/', '', array());

	pg_assert_same(false, $none['bookable'], 'not bookable');
	pg_assert_same(null, $none['link_url'], 'no link when not bookable');
	pg_assert_same(null, $none['short_url'], 'no short address beside no link');
	pg_assert_same('', $none['meeting_url'], 'empty for no meeting address');
	pg_assert_same(array(), $none['services'], 'no services');

	$no_key = ws_api_meeting_link_present(true, '', 'ada', 'https://example.com/', '', array());

	pg_assert_same(null, $no_key['link_url'], 'no key, no link');
	pg_assert_same(null, $no_key['short_url'], 'and no short address');

	$no_short = ws_api_meeting_link_present(true, 'https://example.com/book_with.php?c=3-ab', '', 'https://example.com/', '', array());

	pg_assert_same(null, $no_short['short_url'], 'no short link is null');
}
