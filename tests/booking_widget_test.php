<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking widget's pure helpers (includes/fn/widgets_booking.php):
 * the staff of a service, the days of a month with a free time, the times of
 * a day over several staff members ("anyone" picks the freest), and the busy
 * ranges of Workspace calendar events.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function booking_widget_test_at($local)
{
	$moment = new DateTime($local, new DateTimeZone('Europe/Istanbul'));

	return $moment->getTimestamp();
}

function booking_widget_test_staff($id, $days = array(1, 2, 3, 4, 5))
{
	$hours = array();

	foreach ($days as $day) {
		$hours[(string) $day] = array(array('09:00', '12:00'));
	}

	return array('id' => $id, 'user_id' => 100 + $id, 'name' => 'Staff ' . $id, 'service_ids' => '[1,2]', 'enabled' => 1, 'hours' => $hours, 'exceptions' => array());
}

function test_booking_widget_service_ids()
{
	pg_assert_same(array(1, 2), pg_booking_service_ids('[1,"2",2,0,-3,"x"]'));
	pg_assert_same(array(), pg_booking_service_ids('not json'));
	pg_assert_same(array(4), pg_booking_service_ids(array(4)));
}

function test_booking_widget_staff_for()
{
	$staff = array(
		array('id' => 1, 'service_ids' => '[1]', 'enabled' => 1),
		array('id' => 2, 'service_ids' => '[2]', 'enabled' => 1),
		array('id' => 3, 'service_ids' => '[1,2]', 'enabled' => 0),
		array('id' => 4, 'service_ids' => '[2,1]', 'enabled' => 1),
	);

	$ids = array();
	foreach (pg_booking_staff_for($staff, 1) as $row) {
		$ids[] = $row['id'];
	}

	pg_assert_same(array(1, 4), $ids, 'a switched-off staff member is left out');

	$map = pg_booking_widget_staff_map(array(array('id' => 2)), array(array('id' => 2, 'name' => 'Ayşe', 'service_ids' => '[2]', 'enabled' => 1)));
	pg_assert_same(array('2' => array(array('id' => 2, 'name' => 'Ayşe'))), $map);
}

function test_booking_widget_month()
{
	pg_assert_true(pg_booking_month_valid('2026-11'));
	pg_assert_false(pg_booking_month_valid('2026-13'));
	pg_assert_false(pg_booking_month_valid('2026-1'));
	pg_assert_false(pg_booking_month_valid(array('2026-11')));
	pg_assert_same(28, count(pg_booking_month_dates('2026-02')));
	pg_assert_same('2026-02-28', pg_booking_month_dates('2026-02')[27]);
	pg_assert_same(array(), pg_booking_month_dates('x'));
}

function test_booking_widget_month_days()
{
	$service = array('length_minutes' => 60, 'buffer_before' => 0, 'buffer_after' => 0);
	$policy = array('zone' => 'Europe/Istanbul', 'lead_minutes' => 60, 'horizon_days' => 10);
	$now = booking_widget_test_at('2026-11-03 10:30');

	// Monday to Friday, 09:00-12:00; at 10:30 with an hour's lead the
	// earliest start is 11:30, so today has nothing left.
	$days = pg_booking_month_days($service, array(booking_widget_test_staff(1)), '2026-11', array(), $now, $policy);

	pg_assert_same('2026-11-04', $days[0], 'today has no time left after the lead time');
	pg_assert_false(in_array('2026-11-07', $days, true), 'no hours on Saturday');
	pg_assert_same('2026-11-13', end($days), 'the horizon ends the month early');

	// A day fully busy for the only staff member is not offered.
	$busy = array(1 => array(array(booking_widget_test_at('2026-11-04 09:00'), booking_widget_test_at('2026-11-04 12:00'))));
	$days = pg_booking_month_days($service, array(booking_widget_test_staff(1)), '2026-11', $busy, $now, $policy);
	pg_assert_same('2026-11-05', $days[0]);

	// Another staff member free that day brings it back.
	$days = pg_booking_month_days($service, array(booking_widget_test_staff(1), booking_widget_test_staff(2)), '2026-11', $busy, $now, $policy);
	pg_assert_same('2026-11-04', $days[0]);

	pg_assert_same(array(), pg_booking_month_days($service, array(booking_widget_test_staff(1)), '2026-10', array(), $now, $policy), 'a past month');
}

function test_booking_widget_day_times_freest()
{
	$service = array('length_minutes' => 60, 'buffer_before' => 0, 'buffer_after' => 0);
	$policy = array('zone' => 'Europe/Istanbul');
	$now = booking_widget_test_at('2026-11-01 08:00');

	// Staff 1 has 10:00 taken; staff 2 is free all morning.
	$busy = array(1 => array(array(booking_widget_test_at('2026-11-04 10:00'), booking_widget_test_at('2026-11-04 11:00'))));
	$times = pg_booking_day_times($service, array(booking_widget_test_staff(1), booking_widget_test_staff(2)), '2026-11-04', $busy, $now, $policy);

	pg_assert_same(3, count($times), 'each start once');
	pg_assert_same(booking_widget_test_at('2026-11-04 09:00'), $times[0]['start']);
	pg_assert_same(2, $times[0]['staff_id'], 'the freer staff member takes a shared start');
	pg_assert_same(2, $times[1]['staff_id'], 'only staff 2 is free at 10:00');

	// Equal load: the lowest id.
	$times = pg_booking_day_times($service, array(booking_widget_test_staff(3), booking_widget_test_staff(2)), '2026-11-04', array(), $now, $policy);
	pg_assert_same(2, $times[0]['staff_id']);

	pg_assert_same(array(), pg_booking_day_times($service, array(booking_widget_test_staff(1)), '2026-11-07', array(), $now, $policy), 'a day off');
}

function test_booking_widget_ws_busy()
{
	$staff = array(array('id' => 1, 'user_id' => 10), array('id' => 2, 'user_id' => 20));
	$events = array(
		array('scope' => 'people', 'department_id' => 0, 'people' => array(10), 'starts_at' => 100, 'ends_at' => 200, 'all_day' => 0),
		array('scope' => 'department', 'department_id' => 7, 'people' => array(), 'starts_at' => 300, 'ends_at' => 399, 'all_day' => 1),
		array('scope' => 'company', 'department_id' => 0, 'people' => array(), 'starts_at' => 500, 'ends_at' => 600, 'all_day' => 0),
	);

	pg_assert_same(
		array(1 => array(array(100, 200), array(500, 600)), 2 => array(array(300, 400), array(500, 600))),
		pg_booking_ws_busy($events, $staff, array(20 => array(7))),
		'named people, the department and the company; an all-day event ends a second later');
}
