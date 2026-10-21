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
