<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address request POST /orders/{id}/shipment hands to
 * update_order() (api_orders_ship_recipient() in
 * includes/api/resources/orders.php): tracking_numbers travels only when the
 * caller sent it, so a call that only reports a date keeps the address's
 * numbers; both dates always travel; notify decides the shipped flag.
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

// orders.php pulls in the outbound webhook module, whose gate asks for
// init.php. Behind the gate both files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');

// A ship_tos row as api_orders_ship() reads it.
function api_orders_ship_test_address()
{
	return array(
		'id' => '371', 'ship_date' => '2026-10-20', 'delivery_date' => '0000-00-00', 'shipping_method_code' => '',
	);
}

function test_api_orders_ship_date_only_call_leaves_tracking_numbers_out()
{
	$request = api_orders_ship_recipient(api_orders_ship_test_address(),
		array('delivery_date' => mktime(12, 0, 0, 10, 21, 2026)), array());

	pg_assert_false(array_key_exists('tracking_numbers', $request),
		'a call without tracking_numbers must not ask update_order() to replace the list');
	pg_assert_same(371, $request['id']);
	pg_assert_same('2026-10-21', $request['delivery_date']);
	pg_assert_same('2026-10-20', $request['ship_date'], 'the date not sent keeps its stored value');
	pg_assert_false($request['shipped']);
	pg_assert_same(array(), $request['items']);
}

function test_api_orders_ship_ship_date_only_call_leaves_tracking_numbers_out()
{
	$request = api_orders_ship_recipient(api_orders_ship_test_address(),
		array('ship_date' => mktime(12, 0, 0, 10, 22, 2026)), array());

	pg_assert_false(array_key_exists('tracking_numbers', $request));
	pg_assert_same('2026-10-22', $request['ship_date']);
	pg_assert_same('0000-00-00', $request['delivery_date']);
}

function test_api_orders_ship_empty_list_sent_still_removes_the_numbers()
{
	$request = api_orders_ship_recipient(api_orders_ship_test_address(),
		array('tracking_numbers' => array()), array());

	pg_assert_true(array_key_exists('tracking_numbers', $request),
		'an empty list sent on purpose is the documented way to remove the numbers');
	pg_assert_same(array(), $request['tracking_numbers']);
}

function test_api_orders_ship_sent_list_is_passed_with_notify()
{
	$request = api_orders_ship_recipient(api_orders_ship_test_address(),
		array('tracking_numbers' => array('1234567890123', '9876543210987'), 'notify' => true),
		array('1234567890123', '9876543210987'));

	pg_assert_same(array('1234567890123', '9876543210987'), $request['tracking_numbers']);
	pg_assert_true($request['shipped']);
}

function test_api_orders_ship_known_carrier_goes_on_each_number()
{
	$request = api_orders_ship_recipient(api_orders_ship_test_address(),
		array('tracking_numbers' => array('1234567890123'), 'carrier' => 'Aras Kargo'),
		array('1234567890123'));

	pg_assert_same(array(array('number' => '1234567890123', 'carrier' => 'aras')), $request['tracking_numbers']);

	$free = api_orders_ship_recipient(api_orders_ship_test_address(),
		array('tracking_numbers' => array('1234567890123'), 'carrier' => 'Our own van'),
		array('1234567890123'));

	pg_assert_same(array('1234567890123'), $free['tracking_numbers'], 'free text names no carrier for the parcel');
}
