<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marketplace.order.* webhook events
 * (includes/api/outbound/orders.php, sync.php, webhooks.php): the catalogue
 * lines, the payload, and that each event is announced only where it happens
 * now: an order written for a package, a cancellation applied here, a
 * shipment the shop reported with its own carrier.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function marketplace_order_events_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	return substr($source, $start, strpos($source, "\n}\n", $start) - $start);
}

function test_marketplace_order_events_catalogue()
{
	$events = api_webhook_events();

	foreach (array('marketplace.order.imported', 'marketplace.order.cancelled', 'marketplace.order.shipped') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is in the catalogue');
	}

	pg_assert_contains('not announced', $events['marketplace.order.imported'], 'a package cancelled before its import is named');
	pg_assert_contains('order.shipped', $events['marketplace.order.shipped'], 'the marketplace carrier case is named');
}

function test_marketplace_order_events_payload()
{
	$account = array('id' => '4', 'provider' => 'trendyol', 'credentials' => array('api_key' => 'secret'));
	$map = array(
		'id'                => '77',
		'order_id'          => '9001',
		'remote_order_id'   => '10650123',
		'remote_package_id' => '3290123456',
		'remote_status'     => 'Created',
	);

	pg_assert_same(array(
		'id'                => 9001,
		'order_number'      => '100245',
		'account_id'        => 4,
		'provider'          => 'trendyol',
		'remote_order_id'   => '10650123',
		'remote_package_id' => '3290123456',
		'remote_status'     => 'Created',
	), mp_order_event_payload($account, $map, '100245'));

	$shipped = mp_order_event_payload($account, $map, '100245', 'Shipped', array('tracking_url' => 'https://t.example/1', 'id' => 5));

	pg_assert_same('Shipped', $shipped['remote_status'], 'the status the event is about');
	pg_assert_same('https://t.example/1', $shipped['tracking_url']);
	pg_assert_same(9001, $shipped['id'], 'an extra never overwrites a base field');
	pg_assert_true(!array_key_exists('credentials', $shipped), 'nothing of the account but its id and provider');
}

function test_marketplace_order_events_imported_only_with_an_order()
{
	$claimed = marketplace_order_events_test_body('orders.php', 'mp_import_claimed');
	$announce = strpos($claimed, "mp_order_announce('marketplace.order.imported'");

	pg_assert_true($announce !== false, 'the import announces');
	pg_assert_true($announce > strpos($claimed, "SET order_id = '"), 'after the map row holds the order');
	pg_assert_true($announce < strpos($claimed, 'mp_order_apply_status('), 'before the shipped and delivered updates of the same pass');
	pg_assert_true($announce > strpos($claimed, "=== 'cancelled'"), 'not for a package cancelled before its import');
}

function test_marketplace_order_events_cancelled_only_when_applied_now()
{
	$cancel = marketplace_order_events_test_body('orders.php', 'mp_order_cancel');
	$announce = strpos($cancel, "mp_order_announce('marketplace.order.cancelled'");

	pg_assert_true($announce !== false, 'the cancellation announces');
	pg_assert_true($announce > strpos($cancel, "=== 'shipped'"), 'not for an order that had shipped');
	pg_assert_true($announce > strpos($cancel, "!== 'success'"), 'only after the cancellation went through');
	pg_assert_true($announce > strpos($cancel, 'mp_order_return_stock('), 'once the stock is back');
}

function test_marketplace_order_events_shipped_only_by_the_own_carrier_report()
{
	$ship = marketplace_order_events_test_body('sync.php', 'mp_sync_ship_pending');
	$announce = strpos($ship, "mp_order_announce('marketplace.order.shipped'");

	pg_assert_true($announce !== false, 'the own-carrier report announces');
	pg_assert_true($announce > strpos($ship, "if (\$shipped['ok'])"), 'only on a report the marketplace took');
	pg_assert_true($announce > strpos($ship, "SET remote_status = 'Shipped'"), 'after the map row says so');
}
