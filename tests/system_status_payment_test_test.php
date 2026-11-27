<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the System Status rule "a payment way in test on a live shop"
 * (pg_launch_journey_payment_test_ways() in includes/fn/launch_journey.php):
 * no row with the shop off or before going live, a pass when every way is
 * live, the ways in test named, and a mode that cannot be read is no warning.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey.php');

function system_status_payment_test_ways()
{
	return array(
		array('driver' => 'paytr', 'settings' => array('mode' => 'test')),
		array('driver' => 'stripe', 'settings' => array('publishable_key' => 'pk_live_a', 'secret_key' => 'sk_live_b')),
		array('driver' => 'PayPal Express Checkout', 'settings' => array('mode' => 'sandbox')),
	);
}

function test_system_status_payment_test_no_row_with_shop_off()
{
	pg_assert_same(null, pg_launch_journey_payment_test_ways(false, 1700000000, system_status_payment_test_ways(), 'live'), 'shop off: no row');
}

function test_system_status_payment_test_no_row_before_going_live()
{
	pg_assert_same(null, pg_launch_journey_payment_test_ways(true, 0, system_status_payment_test_ways(), 'live'), 'not gone live: no row, the card says it');
}

function test_system_status_payment_test_all_live_passes()
{
	$ways = array(
		array('driver' => 'paytr', 'settings' => array('mode' => 'live')),
		array('driver' => 'stripe', 'settings' => array('secret_key' => 'sk_live_b')),
		array('driver' => 'Authorize.Net', 'settings' => array()),
	);

	pg_assert_same(array(), pg_launch_journey_payment_test_ways(true, 1700000000, $ways, 'live'), 'every way live: the row passes');
	pg_assert_same(array(), pg_launch_journey_payment_test_ways(true, 1700000000, array(), 'live'), 'no online way: nothing in test');
}

function test_system_status_payment_test_names_each_way_in_test()
{
	pg_assert_same(
		array('paytr', 'PayPal Express Checkout'),
		pg_launch_journey_payment_test_ways(true, 1700000000, system_status_payment_test_ways(), 'live'),
		'the ways in test, in their order'
	);

	$ways = array(array('driver' => 'iyzico', 'settings' => array()));
	pg_assert_same(array('iyzico'), pg_launch_journey_payment_test_ways(true, 1700000000, $ways, 'test'), 'a way by the card gateway mode');
}

function test_system_status_payment_test_unknown_mode_is_no_warning()
{
	$ways = array(
		array('driver' => 'stripe', 'settings' => array('secret_key' => '')),
		array('driver' => 'paytr', 'settings' => array()),
		array('driver' => 'Authorize.Net', 'settings' => array()),
	);

	pg_assert_same(array(), pg_launch_journey_payment_test_ways(true, 1700000000, $ways, ''), 'a mode that cannot be read is not a warning');
}

function test_system_status_payment_test_agrees_with_the_card()
{
	foreach (array(system_status_payment_test_ways(), array(array('driver' => 'paytr', 'settings' => array('mode' => 'live')))) as $ways) {
		$card = pg_launch_journey_rule('payment_live', array('payments' => $ways, 'gateway_mode' => 'live'), 'shop');
		$row = pg_launch_journey_payment_test_ways(true, 1700000000, $ways, 'live');

		pg_assert_same(($card['reason'] === 'test_mode'), ($row !== array()), 'the row and the card read test mode alike');
	}
}
