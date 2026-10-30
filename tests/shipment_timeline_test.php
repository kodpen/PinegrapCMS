<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_shipment_timeline_steps() in includes/fn/shipping.php: the
 * order view timeline says Shipped and Delivered only when a parcel or a
 * shipped quantity records it, and keeps the checkout estimate otherwise,
 * even after the estimated day has passed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _shipment_timeline_address($ship_date, $delivery_date, $shipped_quantity = 0, $parcels = array())
{
	return array(
		'ship_date'        => $ship_date,
		'delivery_date'    => $delivery_date,
		'shipped_quantity' => $shipped_quantity,
		'parcels'          => $parcels,
	);
}

// A same-day ship estimate with nothing shipped stays an estimate.
function test_shipment_timeline_today_without_record_is_estimate()
{
	$today = date('Y-m-d');
	$steps = pg_shipment_timeline_steps(array(_shipment_timeline_address($today, $today)));
	pg_assert_same(strtotime($today), $steps['shipped']['ts']);
	pg_assert_true($steps['shipped']['estimate'], 'shipped estimate');
	pg_assert_same(strtotime($today), $steps['delivered']['ts']);
	pg_assert_true($steps['delivered']['estimate'], 'delivered estimate');
}

// Days long past, still without a record, stay estimates.
function test_shipment_timeline_past_dates_without_record_are_estimates()
{
	$steps = pg_shipment_timeline_steps(array(_shipment_timeline_address('2020-01-02', '2020-01-05')));
	pg_assert_same(strtotime('2020-01-02'), $steps['shipped']['ts']);
	pg_assert_true($steps['shipped']['estimate'], 'shipped estimate');
	pg_assert_same(strtotime('2020-01-05'), $steps['delivered']['ts']);
	pg_assert_true($steps['delivered']['estimate'], 'delivered estimate');
}

// A parcel's shipped_at is the shipped day; delivery stays an estimate.
function test_shipment_timeline_parcel_shipped_at()
{
	$at = mktime(14, 30, 0, 3, 4, 2026);
	$steps = pg_shipment_timeline_steps(array(_shipment_timeline_address('2026-03-02', '2026-03-09', 0, array(
		array('shipped_at' => $at, 'delivered_at' => 0),
	))));
	pg_assert_same($at, $steps['shipped']['ts']);
	pg_assert_false($steps['shipped']['estimate'], 'shipped is a record');
	pg_assert_same(strtotime('2026-03-09'), $steps['delivered']['ts']);
	pg_assert_true($steps['delivered']['estimate'], 'delivered estimate');
}

// An old parcel without shipped_at is shipped on the address's ship_date.
function test_shipment_timeline_old_parcel_uses_ship_date()
{
	$steps = pg_shipment_timeline_steps(array(_shipment_timeline_address('2024-05-06', '2024-05-08', 0, array(
		array('shipped_at' => 0, 'delivered_at' => 0),
	))));
	pg_assert_same(strtotime('2024-05-06'), $steps['shipped']['ts']);
	pg_assert_false($steps['shipped']['estimate'], 'shipped is a record');
}

// A shipped quantity without a parcel ("Mark Shipped") is a record too.
function test_shipment_timeline_shipped_quantity_without_parcel()
{
	$steps = pg_shipment_timeline_steps(array(_shipment_timeline_address('2024-05-06', '2024-05-08', 2)));
	pg_assert_same(strtotime('2024-05-06'), $steps['shipped']['ts']);
	pg_assert_false($steps['shipped']['estimate'], 'shipped is a record');
	pg_assert_true($steps['delivered']['estimate'], 'delivered estimate');
}

// The earliest delivered_at among the parcels is the delivered day.
function test_shipment_timeline_earliest_delivered_at()
{
	$late = mktime(10, 0, 0, 6, 12, 2026);
	$early = mktime(9, 0, 0, 6, 10, 2026);
	$steps = pg_shipment_timeline_steps(array(_shipment_timeline_address('2026-06-08', '2026-06-11', 1, array(
		array('shipped_at' => mktime(8, 0, 0, 6, 8, 2026), 'delivered_at' => $late),
		array('shipped_at' => mktime(8, 0, 0, 6, 8, 2026), 'delivered_at' => 0),
		array('shipped_at' => mktime(8, 0, 0, 6, 9, 2026), 'delivered_at' => $early),
	))));
	pg_assert_same($early, $steps['delivered']['ts']);
	pg_assert_false($steps['delivered']['estimate'], 'delivered is a record');
}

// Two addresses: the shipped one's real day wins over the other's earlier estimate.
function test_shipment_timeline_shipped_address_wins()
{
	$steps = pg_shipment_timeline_steps(array(
		_shipment_timeline_address('2026-02-01', '2026-02-03'),
		_shipment_timeline_address('2026-02-05', '2026-02-07', 1),
	));
	pg_assert_same(strtotime('2026-02-05'), $steps['shipped']['ts']);
	pg_assert_false($steps['shipped']['estimate'], 'shipped is a record');
	pg_assert_same(strtotime('2026-02-03'), $steps['delivered']['ts']);
	pg_assert_true($steps['delivered']['estimate'], 'delivered estimate');
}

// No dates and no record: no step at all.
function test_shipment_timeline_no_dates_no_steps()
{
	$steps = pg_shipment_timeline_steps(array(
		_shipment_timeline_address('', ''),
		_shipment_timeline_address('0000-00-00', '0000-00-00'),
	));
	pg_assert_same(0, $steps['shipped']['ts']);
	pg_assert_same(0, $steps['delivered']['ts']);
	$steps = pg_shipment_timeline_steps(array());
	pg_assert_same(0, $steps['shipped']['ts']);
}

// The order view labels the steps from the helper, not from today's date.
function test_shipment_timeline_order_view_uses_helper()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/widgets.php');
	pg_assert_contains('pg_shipment_timeline_steps(', $source);
	pg_assert_false(strpos($source, '($shipping_ts > $_today_ts)') !== false, 'no date comparison left');
	pg_assert_false(strpos($source, '($delivery_ts > $_today_ts)') !== false, 'no date comparison left');
}
