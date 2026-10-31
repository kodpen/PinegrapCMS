<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks on shipworks.php updateshipment: the tracking number goes
 * through the shipment write path (pg_shipment_record(), source 'shipworks')
 * instead of a direct INSERT, and order.shipped is announced only when that
 * call reports a new row, so a number ShipWorks sends again stays one row and
 * one event. The handler runs on a request and a database, so its shape is
 * checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_shipworks_update_source()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/shipworks.php');
	$start = strpos($src, "case 'updateshipment':");

	pg_assert_true($start !== false, 'the updateshipment action is there');

	$end = strpos($src, 'default:', (int) $start);

	return (string) substr($src, (int) $start, ($end === false) ? null : $end - $start);
}

// ShipWorks is a known writer, so its rows keep their own source.
function test_shipworks_is_a_shipment_source()
{
	pg_assert_true(in_array('shipworks', pg_shipment_sources(), true));
	pg_assert_true(strlen('shipworks') <= 16, 'fits shipping_tracking_numbers.source');
}

// The number goes through the write path, never a direct insert.
function test_shipworks_records_through_the_write_path()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/shipworks.php');
	$update = pg_test_shipworks_update_source();

	pg_assert_true(strpos($update, 'pg_shipment_record(') !== false, 'calls pg_shipment_record()');
	pg_assert_true((bool) preg_match("/'source'\s*=>\s*'shipworks'/", $update), 'with source shipworks');
	pg_assert_true(stripos($src, 'INSERT INTO shipping_tracking_numbers') === false, 'no direct insert');
}

// order.shipped is announced once, when the row is new; nothing is mailed.
function test_shipworks_announces_only_a_new_parcel()
{
	$update = pg_test_shipworks_update_source();

	pg_assert_true((bool) preg_match("/if\s*\(\s*!?\s*\\\$recorded\['created'\]\s*\)/", $update), 'guarded by created');

	$guard = strpos($update, "\$recorded['created']");
	$event = strpos($update, "api_webhook_enqueue('order.shipped'");

	pg_assert_true($event !== false, 'announces order.shipped');
	pg_assert_true($guard !== false && $event > $guard, 'after the created guard');
	pg_assert_true(strpos($update, 'pg_shipment_api_parcels(') !== false, 'with the parcel as the API shows it');

	foreach (array('send_email', 'pg_sms_', 'erp_', 'order_review_request', 'update_order(') as $call) {
		pg_assert_true(strpos($update, $call) === false, 'no ' . $call);
	}
}
