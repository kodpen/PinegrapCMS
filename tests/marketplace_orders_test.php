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
