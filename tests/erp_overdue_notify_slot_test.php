<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the period start of the overdue reminder digest
 * (erp_overdue_calendar_slot() in includes/erp/notify.php): the slot stays
 * at the local hour on the calendar day across a daylight saving change, so a
 * digest sent at that hour is not sent a second time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/notify.php');

// Runs $check under Europe/Berlin (clocks go back on 2026-10-25 and forward
// on 2026-03-29) and puts the zone back.
function erp_overdue_notify_slot_test_in_berlin($check)
{
	$saved = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	try {
		$check();
	} finally {
		date_default_timezone_set($saved);
	}
}

// Daily, early on the day the clocks went back: the period is yesterday at
// 09:00, not 10:00, so the digest sent yesterday at 09:30 still counts.
function test_erp_overdue_slot_daily_before_hour_on_autumn_change()
{
	erp_overdue_notify_slot_test_in_berlin(function () {
		$slot = erp_overdue_calendar_slot(strtotime('2026-10-25 05:00:00'), 9, false);

		pg_assert_same('2026-10-24 09:00', date('Y-m-d H:i', $slot), 'yesterday at the hour');
		pg_assert_true(strtotime('2026-10-24 09:30:00') >= $slot, 'the digest sent at 09:30 counts as sent');
	});
}

// Daily, early on the day after the clocks went forward.
function test_erp_overdue_slot_daily_before_hour_after_spring_change()
{
	erp_overdue_notify_slot_test_in_berlin(function () {
		$slot = erp_overdue_calendar_slot(strtotime('2026-03-30 05:00:00'), 9, false);

		pg_assert_same('2026-03-29 09:00', date('Y-m-d H:i', $slot), 'yesterday at the hour');
	});
}

// Weekly, on the Sunday the clocks went back: this week's Monday at 09:00.
function test_erp_overdue_slot_weekly_on_autumn_sunday()
{
	erp_overdue_notify_slot_test_in_berlin(function () {
		$slot = erp_overdue_calendar_slot(strtotime('2026-10-25 12:00:00'), 9, true);

		pg_assert_same('2026-10-19 09:00', date('Y-m-d H:i', $slot), 'Monday at the hour');
	});
}

// Weekly, on Monday before the hour, a week after the clocks went back: the
// Monday before at 09:00.
function test_erp_overdue_slot_weekly_before_hour_after_autumn_change()
{
	erp_overdue_notify_slot_test_in_berlin(function () {
		$slot = erp_overdue_calendar_slot(strtotime('2026-10-26 05:00:00'), 9, true);

		pg_assert_same('2026-10-19 09:00', date('Y-m-d H:i', $slot), 'last Monday at the hour');
	});
}

// Weekly, on the Sunday the clocks went forward.
function test_erp_overdue_slot_weekly_on_spring_sunday()
{
	erp_overdue_notify_slot_test_in_berlin(function () {
		$slot = erp_overdue_calendar_slot(strtotime('2026-03-29 12:00:00'), 9, true);

		pg_assert_same('2026-03-23 09:00', date('Y-m-d H:i', $slot), 'Monday at the hour');
	});
}

// An ordinary week: past the hour the slot is today's (daily) or Monday's
// (weekly); the job's hour 0 is midnight.
function test_erp_overdue_slot_ordinary_days()
{
	erp_overdue_notify_slot_test_in_berlin(function () {
		$now = strtotime('2026-11-05 10:00:00');

		pg_assert_same('2026-11-05 09:00', date('Y-m-d H:i', erp_overdue_calendar_slot($now, 9, false)), 'today at the hour');
		pg_assert_same('2026-11-02 09:00', date('Y-m-d H:i', erp_overdue_calendar_slot($now, 9, true)), 'Monday at the hour');
		pg_assert_same('2026-11-05 00:00', date('Y-m-d H:i', erp_overdue_calendar_slot($now, 0, false)), 'midnight for the job');
		pg_assert_same('2026-11-01 09:00', date('Y-m-d H:i', erp_overdue_calendar_slot(strtotime('2026-11-02 08:00:00'), 9, false)), 'yesterday across a month');
	});
}
