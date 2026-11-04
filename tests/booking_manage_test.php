<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a customer may do with their own booking
 * (includes/fn/booking_manage.php): the cancel and move window of the
 * policy, the account's upcoming and past lists, and the months the move
 * calendar may show.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_booking_manage_rights_window()
{
	$now = 1800000000;
	$policy = array('cancel_hours' => 24, 'reschedule' => true);
	$booking = array('status' => 'confirmed', 'start_at' => $now + (25 * 3600));

	$rights = pg_booking_manage_rights($booking, $policy, $now);
	pg_assert_same(true, $rights['cancel']);
	pg_assert_same(true, $rights['reschedule']);
	pg_assert_same($now + 3600, $rights['until']);

	pg_assert_same(true, pg_booking_manage_rights($booking, $policy, $now + 3600)['cancel'], 'the last moment still counts');
	pg_assert_same(false, pg_booking_manage_rights($booking, $policy, $now + 3601)['cancel'], 'inside the window: no cancel');
	pg_assert_same(false, pg_booking_manage_rights($booking, $policy, $now + 3601)['reschedule'], 'inside the window: no move');
}

function test_booking_manage_rights_policy_and_status()
{
	$now = 1800000000;
	$booking = array('status' => 'requested', 'start_at' => $now + 7200);

	$rights = pg_booking_manage_rights($booking, array('cancel_hours' => 0, 'reschedule' => false), $now);
	pg_assert_same(true, $rights['cancel'], 'no window: until the start');
	pg_assert_same(false, $rights['reschedule'], 'moves switched off');

	pg_assert_same(false, pg_booking_manage_rights($booking, array('cancel_hours' => 0, 'reschedule' => true), $now + 7200)['cancel'], 'a started booking');

	foreach (array('held', 'cancelled', 'no_show', 'done') as $status) {
		$rights = pg_booking_manage_rights(array('status' => $status, 'start_at' => $now + 86400 * 5), array('cancel_hours' => 1, 'reschedule' => true), $now);
		pg_assert_same(false, $rights['cancel'], $status . ' cannot be cancelled');
		pg_assert_same(false, $rights['reschedule'], $status . ' cannot be moved');
	}
}

function test_booking_customer_split()
{
	$now = 1800000000;
	$rows = array(
		array('id' => 1, 'status' => 'confirmed', 'start_at' => $now + 7200, 'end_at' => $now + 9000),
		array('id' => 2, 'status' => 'requested', 'start_at' => $now + 3600, 'end_at' => $now + 5400),
		array('id' => 3, 'status' => 'confirmed', 'start_at' => $now - 600, 'end_at' => $now + 600),
		array('id' => 4, 'status' => 'done', 'start_at' => $now - 86400, 'end_at' => $now - 84600),
		array('id' => 5, 'status' => 'cancelled', 'start_at' => $now + 86400, 'end_at' => $now + 88200),
		array('id' => 6, 'status' => 'held', 'start_at' => $now + 600, 'end_at' => $now + 2400),
		array('id' => 7, 'status' => 'confirmed', 'start_at' => $now - 7200, 'end_at' => $now - 5400),
	);

	$split = pg_booking_customer_split($rows, $now);
	$ids = function ($list) {
		return array_map(function ($row) {
			return $row['id'];
		}, $list);
	};

	pg_assert_same(array(3, 2, 1), $ids($split['upcoming']), 'soonest first; one under way is still upcoming');
	pg_assert_same(array(5, 7, 4), $ids($split['past']), 'latest first; cancelled ones are past; holds are left out');
}

function test_booking_manage_months()
{
	// 2026-11-20 12:00 in Istanbul (UTC+3).
	$now = (new DateTime('2026-11-20 12:00:00', new DateTimeZone('Europe/Istanbul')))->getTimestamp();

	pg_assert_same(array('2026-11', '2026-12', '2027-01'), pg_booking_manage_months($now, 60, 'Europe/Istanbul'));
	pg_assert_same(array('2026-11'), pg_booking_manage_months($now, 5, 'Europe/Istanbul'));
	$end = (new DateTime('2026-11-30 12:00:00', new DateTimeZone('Europe/Istanbul')))->getTimestamp();
	pg_assert_same(array('2026-11', '2026-12'), pg_booking_manage_months($end, 1, 'Europe/Istanbul'), 'a horizon over the month end');
	pg_assert_same(25, count(pg_booking_manage_months($now, 730, 'Europe/Istanbul')), 'two years: 25 months at most');
}

function test_booking_without_range()
{
	$ranges = array(array(100, 200), array(300, 400), array(300, 400));

	pg_assert_same(array(array(100, 200), array(300, 400)), pg_booking_without_range($ranges, 300, 400), 'only the first equal range goes');
	pg_assert_same($ranges, pg_booking_without_range($ranges, 300, 401), 'nothing equal: unchanged');
	pg_assert_same(array(), pg_booking_without_range(array(), 1, 2));
}
