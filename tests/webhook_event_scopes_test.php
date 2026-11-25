<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the read scope of each webhook event's data
 * (api_webhook_event_scopes(), includes/api/outbound/webhooks.php): every
 * event of the catalogue has its scope, the scope is a real one, and an
 * application lacking it is neither subscribed nor sent the event.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows_alone.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_mail_alone.php');

// Every module's events and scopes, whether or not the module is on here.
function webhook_event_scopes_test_events()
{
	return api_webhook_core_events() + erp_webhook_events() + ws_webhook_events();
}

function webhook_event_scopes_test_map()
{
	return api_webhook_core_event_scopes() + erp_webhook_event_scopes() + ws_webhook_event_scopes();
}

function webhook_event_scopes_test_known_scopes()
{
	$groups = array();

	foreach (array('api_scope_groups', 'erp_scope_groups', 'ws_scope_groups') as $declare) {
		$groups = array_merge(array_values($groups), array_values(call_user_func($declare)));
	}

	$scopes = array();

	foreach ($groups as $group) {
		foreach (array('read', 'write') as $half) {
			if (isset($group[$half]) && ($group[$half] !== '')) {
				$scopes[] = $group[$half];
			}
		}
	}

	return $scopes;
}

// A new event without its scope would be sent to every subscriber whatever
// it may read: the catalogue and the map hold the same names.
function test_webhook_event_scopes_cover_the_catalogue()
{
	$events = webhook_event_scopes_test_events();
	$map = webhook_event_scopes_test_map();

	foreach (array_keys($events) as $event) {
		pg_assert_true(isset($map[$event]), $event . ' has a read scope in the map');
	}

	foreach (array_keys($map) as $event) {
		pg_assert_true(isset($events[$event]), $event . ' of the map is in the catalogue');
	}
}

// Each mapped scope is one an application can be given.
function test_webhook_event_scopes_are_real_scopes()
{
	$known = webhook_event_scopes_test_known_scopes();

	foreach (webhook_event_scopes_test_map() as $event => $scope) {
		pg_assert_true(in_array($scope, $known, true), $event . ': ' . $scope . ' is a scope');
	}
}

// The flows and the incoming mail merged alone map their own events.
function test_webhook_event_scopes_of_the_alone_modules()
{
	pg_assert_same(array_keys(ws_flows_alone_webhook_events()), array_keys(ws_flows_alone_webhook_event_scopes()));
	pg_assert_same(array_keys(ws_mail_alone_webhook_events()), array_keys(ws_mail_alone_webhook_event_scopes()));
}

// The data an event carries is read under the scope GET answers it with.
function test_webhook_event_scopes_follow_the_reads()
{
	$map = webhook_event_scopes_test_map();

	pg_assert_same('orders:read', $map['order.created']);
	pg_assert_same('customers:read', $map['customer.created']);
	pg_assert_same('products:read', $map['inventory.changed']);
	pg_assert_same('gift_cards:read', $map['credit.issued']);
	pg_assert_same('erp_cash:read', $map['erp.receipt.created']);
	pg_assert_same('erp:read', $map['erp.invoice.created']);
	pg_assert_same('tasks:read', $map['workspace.task.created']);
	pg_assert_same('workspace:read', $map['workspace.message.created']);
}

// A write gives its read; nothing else stands in for a scope.
function test_webhook_scope_held()
{
	pg_assert_true(api_webhook_scope_held(array('orders:read'), 'orders:read'));
	pg_assert_true(api_webhook_scope_held(array('orders:write'), 'orders:read'), 'write gives read');
	pg_assert_true(api_webhook_scope_held(array(), ''), 'no data scope');
	pg_assert_true(api_webhook_scope_held(array('workspace:commands'), 'workspace:commands'));
	pg_assert_false(api_webhook_scope_held(array('webhooks:manage', 'meta:read'), 'orders:read'));
	pg_assert_false(api_webhook_scope_held(array('orders:read'), 'orders:write'), 'read never gives write');
	pg_assert_false(api_webhook_scope_held(array('workspace:write'), 'tasks:read'), 'another group');
	pg_assert_false(api_webhook_scope_held(null, 'orders:read'), 'no scopes stored');
}

// What a subscription is not sent: each scope lacking, with its events.
function test_webhook_hook_blocked()
{
	$map = array('order.created' => 'orders:read', 'order.shipped' => 'orders:read', 'customer.created' => 'customers:read');

	pg_assert_same(array(), api_webhook_hook_blocked(array('order.created'), array('orders:write'), $map));
	pg_assert_same(
		array('orders:read' => array('order.created', 'order.shipped'), 'customers:read' => array('customer.created')),
		api_webhook_hook_blocked(array('order.created', 'customer.created', 'order.shipped'), array('webhooks:manage'), $map)
	);
	pg_assert_same(
		array('customers:read' => array('customer.created')),
		api_webhook_hook_blocked(array('order.created', 'customer.created'), array('orders:read'), $map)
	);
}

// Queueing asks the scope of every subscription, and the first skip tells
// the bell once per subscription.
function test_webhook_enqueue_skips_an_application_without_the_scope()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
	$start = strpos($source, 'function api_webhook_enqueue(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 10) - $start);

	pg_assert_contains('api_apps.scopes AS app_scopes', $body, 'the application\'s scopes are read with the subscription');
	pg_assert_contains('api_webhook_scope_held(', $body, 'each subscription is asked');
	pg_assert_contains('api_webhook_scope_skip_note(', $body, 'a skip is told');
	pg_assert_true(strpos($body, 'api_webhook_scope_skip_note(') < strpos($body, 'INSERT INTO api_webhook_queue'), 'before anything is queued');

	$start = strpos($source, 'function api_webhook_scope_skip_note(');
	$note = substr($source, $start, strpos($source, "\nfunction ", $start + 10) - $start);

	pg_assert_contains("action = 'webhook_scope' AND form_id = ", $note, 'once per subscription');
}
