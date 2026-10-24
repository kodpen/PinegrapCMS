<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for marketplace order import (includes/api/outbound/orders.php): the
 * parts that decide what happens to a package without touching the database.
 * Writing the order itself is checked on a sandbox.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The outbound files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');

function test_marketplace_orders_stage_follows_the_provider()
{
	$n11 = array('id' => 1, 'provider' => 'n11');

	pg_assert_same('cancelled', mp_order_stage($n11, array('remote_status' => 'Cancelled')));
	pg_assert_same('cancelled', mp_order_stage($n11, array('remote_status' => 'UnSupplied')));
	pg_assert_same('shipped', mp_order_stage($n11, array('remote_status' => 'Shipped')));
	pg_assert_same('delivered', mp_order_stage($n11, array('remote_status' => 'Delivered')));
	pg_assert_same('open', mp_order_stage($n11, array('remote_status' => 'Created')));
	pg_assert_same('open', mp_order_stage($n11, array('remote_status' => 'Picking')));
	pg_assert_same('open', mp_order_stage($n11, array()), 'a package without a status is open');
}

function test_marketplace_orders_stage_is_open_for_a_provider_that_cannot_say()
{
	pg_assert_same('open', mp_order_stage(array('id' => 3, 'provider' => 'nope'), array('remote_status' => 'Cancelled')));
}

// A package split into new ones (UnPacked) gives its stock back like a
// cancelled one: its goods are now in the new packages, which take it again.
function test_marketplace_orders_stage_follows_trendyol()
{
	$trendyol = array('id' => 7, 'provider' => 'trendyol');

	pg_assert_same('cancelled', mp_order_stage($trendyol, array('remote_status' => 'Cancelled')));
	pg_assert_same('cancelled', mp_order_stage($trendyol, array('remote_status' => 'UnSupplied')));
	pg_assert_same('cancelled', mp_order_stage($trendyol, array('remote_status' => 'UnPacked')));
	pg_assert_same('shipped', mp_order_stage($trendyol, array('remote_status' => 'Shipped')));
	pg_assert_same('shipped', mp_order_stage($trendyol, array('remote_status' => 'AtCollectionPoint')));
	pg_assert_same('shipped', mp_order_stage($trendyol, array('remote_status' => 'UnDelivered')));
	pg_assert_same('shipped', mp_order_stage($trendyol, array('remote_status' => 'Returned')));
	pg_assert_same('delivered', mp_order_stage($trendyol, array('remote_status' => 'Delivered')));
	pg_assert_same('open', mp_order_stage($trendyol, array('remote_status' => 'Created')));
	pg_assert_same('open', mp_order_stage($trendyol, array('remote_status' => 'Picking')));
	pg_assert_same('open', mp_order_stage($trendyol, array('remote_status' => 'Invoiced')));
	pg_assert_same('open', mp_order_stage($trendyol, array()), 'a package without a status is open');
}

// Auto-accept sends Picking only for a package still Created: one already
// Picking or Invoiced (accepted by hand in their panel) is refused when it is
// sent Picking again, and the refusal would fill the map row's error.
function test_marketplace_orders_only_a_created_package_is_accepted()
{
	foreach (array('n11', 'trendyol') as $provider) {
		$account = array('id' => 1, 'provider' => $provider);

		pg_assert_true(mp_sync_approvable($account, array('remote_status' => 'Created')), $provider . ' Created');
		pg_assert_false(mp_sync_approvable($account, array('remote_status' => 'Picking')), $provider . ' Picking');
		pg_assert_false(mp_sync_approvable($account, array('remote_status' => 'Shipped')), $provider . ' Shipped');
		pg_assert_false(mp_sync_approvable($account, array('remote_status' => 'Cancelled')), $provider . ' Cancelled');
		pg_assert_false(mp_sync_approvable($account, array('remote_status' => 'created')), $provider . ' the word exactly');
		pg_assert_false(mp_sync_approvable($account, array()), $provider . ' no status');
	}

	pg_assert_false(mp_sync_approvable(array('id' => 1, 'provider' => 'trendyol'), array('remote_status' => 'Invoiced')));
}

// Marketplaces hand out a relay address per order; matching a customer on one
// would file every order from that marketplace under one contact.
function test_marketplace_orders_relay_addresses_are_not_personal()
{
	pg_assert_false(mp_order_email_is_personal('pf+k3j9x2@trendyolmail.com'), 'Trendyol buyer relay');
	pg_assert_false(mp_order_email_is_personal('PF+K3J9X2@TrendyolMail.com'), 'the domain in any case');
	pg_assert_false(mp_order_email_is_personal('buyer@mail.n11.com'));
	pg_assert_false(mp_order_email_is_personal('buyer@trendyol.com'));
	pg_assert_false(mp_order_email_is_personal('no-at-sign'));
	pg_assert_true(mp_order_email_is_personal('ayse@example.com'));
	pg_assert_true(mp_order_email_is_personal('ayse@nottrendyolmail.com'), 'only the domain itself or its subdomains');
}

// The moment a feed counts, rounded down to the millisecond: the next request
// starts there and must not pass over an entry later in the same second.
function test_marketplace_orders_feed_moment_rounds_down()
{
	pg_assert_same(1792690000123, mp_feed_moment(1792690000123.9), 'milliseconds');
	pg_assert_same(1792690000123, mp_feed_moment('1792690000123'), 'milliseconds as a string');
	pg_assert_same(1792690000000, mp_feed_moment(1792690000), 'seconds');
	pg_assert_same(strtotime('2026-10-24 10:00:00') * 1000, mp_feed_moment('2026-10-24 10:00:00'), 'a date');
	pg_assert_same(0, mp_feed_moment(null));
	pg_assert_same(0, mp_feed_moment('not a date'));
}

function test_marketplace_orders_feed_next_page_starts_again_at_the_last_moment()
{
	pg_assert_same(array(1499, 0), mp_feed_next_page(1000, 0, 1100, 1500), 'just before the last moment, page 0');
	pg_assert_same(array(1499, 0), mp_feed_next_page(1000, 3, 1000, 1500), 'also after pages of one moment');
	pg_assert_same(array(1000, 4), mp_feed_next_page(1000, 3, 1200, 1200), 'a page of one moment: the next page');
	pg_assert_same(array(1000, 1), mp_feed_next_page(1000, 0, 0, 0), 'entries without a moment: the next page');
	pg_assert_same(array(1000, 1), mp_feed_next_page(1000, 0, 1000, 1001), 'a start that would not move: the next page');
}

function test_marketplace_orders_feed_hands_a_package_over_once_per_moment()
{
	$seen = array();

	pg_assert_true(mp_feed_first_time($seen, '42', 1000));
	pg_assert_false(mp_feed_first_time($seen, '42', 1000), 'read again at the same moment');
	pg_assert_true(mp_feed_first_time($seen, '42', 2000), 'changed while the pages were walked');
	pg_assert_true(mp_feed_first_time($seen, '43', 1000));
}

// A split comes back as the old package cancelled and a new one: the
// cancellation gives the stock back before the new order takes it.
function test_marketplace_orders_pass_applies_cancellations_first()
{
	$account = array('id' => 7, 'provider' => 'trendyol');
	$packages = array(
		array('remote_package_id' => 'A', 'remote_status' => 'Created'),
		array('remote_package_id' => 'B', 'remote_status' => 'UnPacked'),
		array('remote_package_id' => 'C', 'remote_status' => 'Shipped'),
		array('remote_package_id' => 'D', 'remote_status' => 'Cancelled'),
		array('remote_package_id' => 'E', 'remote_status' => 'Created'),
	);

	$ids = array();

	foreach (mp_sync_cancellations_first($account, $packages) as $package) {
		$ids[] = $package['remote_package_id'];
	}

	pg_assert_same(array('B', 'D', 'A', 'C', 'E'), $ids, 'cancelled first, the rest in the order read');
	pg_assert_same(array(), mp_sync_cancellations_first($account, array()));

	// A package that changed while the pages were walked is in the pass twice:
	// only its newest copy is kept, so the older one cannot write its status back.
	$twice = array(
		array('remote_package_id' => 'A', 'remote_status' => 'Created'),
		array('remote_package_id' => 'B', 'remote_status' => 'Created'),
		array('remote_package_id' => 'A', 'remote_status' => 'Cancelled'),
		array('remote_package_id' => 'C', 'remote_status' => 'Created'),
		array('remote_package_id' => 'C', 'remote_status' => 'Shipped'),
	);

	$kept = array();

	foreach (mp_sync_cancellations_first($account, $twice) as $package) {
		$kept[] = $package['remote_package_id'] . ':' . $package['remote_status'];
	}

	pg_assert_same(array('A:Cancelled', 'B:Created', 'C:Shipped'), $kept, 'each package once, in its newest copy');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');
	pg_assert_contains('foreach (mp_sync_cancellations_first($account, $packages) as $package)', $source,
		'the pass imports in that order');
}

// The marketplace tells its own buyer; this shop does not mail a cancelled
// marketplace order's relay address.
function test_marketplace_orders_cancellation_mails_only_the_shops_own_customers()
{
	pg_assert_false(pg_order_cancellation_mails_customer(array('type' => 'marketplace')));
	pg_assert_true(pg_order_cancellation_mails_customer(array('type' => 'online')));
	pg_assert_true(pg_order_cancellation_mails_customer(array('type' => '')));
	pg_assert_true(pg_order_cancellation_mails_customer(array()));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	pg_assert_contains('if ($email_enabled && pg_order_cancellation_mails_customer($order)) {', $source);
	pg_assert_contains("(pg_schema_has('orders', 'type') ? 'type' : \"'' AS type\")", $source,
		'the cancellation reads the order type');
}
