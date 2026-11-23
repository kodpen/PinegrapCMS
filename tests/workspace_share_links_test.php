<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure rules of an agent channel's share links
 * (includes/workspace/share_links.php) and their API answer
 * (includes/workspace/api_share_links.php): the end a link takes, its state,
 * the item it may point at, who made it, the sweep's cut-off and the routes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in these files runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approval_guests.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/share_links.php');

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_share_links.php');

// A link open until a later time, of a message that carries file 9.
function share_links_test_link($over = array())
{
	return array_merge(array(
		'id' => 5, 'channel_id' => 3, 'message_id' => 40, 'kind' => 'file', 'item_id' => 9,
		'created_by_kind' => 'user', 'created_by_id' => 2, 'created_at' => 1000,
		'expires_at' => 5000, 'revoked_at' => 0, 'opened_at' => 0, 'opens' => 0,
	), $over);
}

function share_links_test_channel($over = array())
{
	return array_merge(array('id' => 3, 'kind' => 'public', 'archived_at' => 0, 'agent_channel' => 1), $over);
}

function share_links_test_message($over = array())
{
	return array_merge(array('id' => 40, 'channel_id' => 3, 'file_id' => 9, 'file_name' => 'plan.png', 'deleted_at' => 0), $over);
}

function test_share_link_days_default_to_seven_and_take_only_the_choices()
{
	pg_assert_same(array(1, 7, 30), ws_share_link_day_choices());
	pg_assert_same(7, ws_share_link_days_clean(null));
	pg_assert_same(7, ws_share_link_days_clean(''));
	pg_assert_same(1, ws_share_link_days_clean(1));
	pg_assert_same(30, ws_share_link_days_clean('30'));
	pg_assert_same(0, ws_share_link_days_clean(3));
	pg_assert_same(0, ws_share_link_days_clean(0));
	pg_assert_same(0, ws_share_link_days_clean('7 '));
	pg_assert_same(0, ws_share_link_days_clean(array(7)));
}

function test_share_link_expiry_is_never_without_an_end()
{
	pg_assert_same(1000 + 7 * 86400, ws_share_link_expiry(1000, 7));
	pg_assert_same(1000 + 86400, ws_share_link_expiry(1000, 0));
}

function test_share_link_token_has_the_approval_link_shape()
{
	pg_assert_true(ws_share_link_token_shape(str_repeat('aZ0_-', 8) . 'abc'));
	pg_assert_true(!ws_share_link_token_shape(str_repeat('a', 42)));
	pg_assert_true(!ws_share_link_token_shape(str_repeat('a', 42) . '=' ));
	pg_assert_true(!ws_share_link_token_shape(null));
	pg_assert_true(!ws_share_link_token_shape(array()));
}

function test_share_link_state_open_while_everything_stands()
{
	pg_assert_same('open', ws_share_link_state(share_links_test_link(), 2000, share_links_test_channel(), null, share_links_test_message(), false, true));
}

function test_share_link_state_revoked_before_expired_before_closed()
{
	$revoked = share_links_test_link(array('revoked_at' => 1500, 'expires_at' => 1200));

	pg_assert_same('revoked', ws_share_link_state($revoked, 2000, null, null, null, true, false));
	pg_assert_same('expired', ws_share_link_state(share_links_test_link(array('expires_at' => 2000)), 2000, null, null, null, true, false));
}

function test_share_link_state_closed_when_channel_message_or_item_no_longer_stands()
{
	$link = share_links_test_link();
	$channel = share_links_test_channel();
	$message = share_links_test_message();

	pg_assert_same('closed', ws_share_link_state($link, 2000, null, null, $message, false, true), 'channel gone');
	pg_assert_same('closed', ws_share_link_state($link, 2000, share_links_test_channel(array('archived_at' => 1)), null, $message, false, true), 'archived');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, null, false, true), 'message gone');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, share_links_test_message(array('deleted_at' => 1)), false, true), 'deleted');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, $message, true, true), 'before the clear floor');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, $message, false, false), 'item gone');
}

function test_share_link_state_of_a_discussion_follows_its_channel()
{
	$thread = share_links_test_channel(array('kind' => 'thread', 'agent_channel' => 0));

	pg_assert_same('open', ws_share_link_state(share_links_test_link(), 2000, $thread, share_links_test_channel(), share_links_test_message(), false, true));
	pg_assert_same('closed', ws_share_link_state(share_links_test_link(), 2000, $thread, share_links_test_channel(array('archived_at' => 9)), share_links_test_message(), false, true));
	pg_assert_same('closed', ws_share_link_state(share_links_test_link(), 2000, $thread, null, share_links_test_message(), false, true));
}

function test_share_link_item_is_the_file_the_message_carries()
{
	$message = share_links_test_message();

	pg_assert_true(ws_share_link_item_in($message, 'file', 9, true));
	pg_assert_true(!ws_share_link_item_in($message, 'file', 9, false), 'files row gone');
	pg_assert_true(!ws_share_link_item_in($message, 'file', 8, true), 'another file');
	pg_assert_true(!ws_share_link_item_in(share_links_test_message(array('file_id' => 0)), 'file', 0, true), 'no file');
	pg_assert_true(!ws_share_link_item_in($message, 'note', 9, true), 'kind not built yet');
	pg_assert_true(!ws_share_link_item_in(null, 'file', 9, true));
	pg_assert_same(array('file'), ws_share_link_kinds());
}

function test_share_link_maker_is_the_application_of_a_key_else_the_person()
{
	pg_assert_same(array('kind' => 'app', 'id' => 12), ws_share_link_maker(2, 12));
	pg_assert_same(array('kind' => 'user', 'id' => 2), ws_share_link_maker(2, 0));

	$link = share_links_test_link();

	pg_assert_true(ws_share_link_made_by($link, array('kind' => 'user', 'id' => 2)));
	pg_assert_true(!ws_share_link_made_by($link, array('kind' => 'app', 'id' => 2)), 'an application with the person\'s id');
	pg_assert_true(!ws_share_link_made_by(share_links_test_link(array('created_by_id' => 0)), array('kind' => 'user', 'id' => 0)));
}

function test_share_links_sweep_keeps_thirty_days_after_the_end()
{
	pg_assert_same(100 * 86400 - 30 * 86400, ws_share_links_sweep_before(100 * 86400));
}

function test_share_link_api_present_never_carries_the_address()
{
	$out = ws_api_share_link_present(share_links_test_link(array('revoked_at' => 0, 'opened_at' => 0)), 'open', 'plan.png');

	pg_assert_same(array_keys(ws_api_share_link_schema()), array_keys($out));
	pg_assert_true(!array_key_exists('url', $out) && !array_key_exists('token_hash', $out));
	pg_assert_same(null, $out['revoked_at']);
	pg_assert_same(array('kind' => 'user', 'id' => 2), $out['created_by']);
	pg_assert_same('plan.png', $out['name']);
}

function test_share_link_routes_redact_the_address_and_dry_run_their_writes()
{
	$routes = array();

	foreach (ws_share_link_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same(array('workspace.share_links.create', 'workspace.share_links.list', 'workspace.share_links.revoke'), array_keys($routes));
	pg_assert_same(array('url'), $routes['workspace.share_links.create']['idempotency_redact']);
	pg_assert_true(!empty($routes['workspace.share_links.create']['dry_run']));
	pg_assert_true(!empty($routes['workspace.share_links.revoke']['dry_run']));
	pg_assert_same('GET', $routes['workspace.share_links.list']['method']);

	// Every route is an MCP tool: an agent hands a link on without a card.
	foreach ($routes as $route) {
		pg_assert_true(!isset($route['mcp']) || ($route['mcp'] !== false), $route['id']);
	}
}
