<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking model and slot engine (2026.11.0, 11.0.157;
 * includes/fn/bookings.php): the step that creates the three tables, the
 * weekly hours and dated exceptions, the free slots of a day (DST change
 * days, buffers, lead time, horizon, holidays), the statuses and their
 * moves, an expired hold, the duplicate of a slot and the manage token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one top-level function, up to the next one.
function booking_slots_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// Unix time of a wall-clock moment in a zone.
function booking_slots_test_at($local, $zone)
{
	$moment = new DateTime($local, new DateTimeZone($zone));

	return $moment->getTimestamp();
}

// The slot starts as local "H:i" texts, to read a test at a glance.
function booking_slots_test_times(array $slots, $zone)
{
	$times = array();

	foreach ($slots as $start) {
		$moment = new DateTime('@' . $start);
		$moment->setTimezone(new DateTimeZone($zone));
		$times[] = $moment->format('H:i');
	}

	return $times;
}

function booking_slots_test_service($length, $before = 0, $after = 0)
{
	return array('length_minutes' => $length, 'buffer_before' => $before, 'buffer_after' => $after);
}

function test_booking_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$entry = booking_slots_test_body($source, 'upgrade_to_2026_11_0');
	$step = booking_slots_test_body($source, 'upgrade_2026_11_0_bookings');

	pg_assert_true(preg_match('/upgrade_2026_11_0_bookings\(\);\s+\/\/ 11\.0\.157\b/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.157\b/', $entry), 'the label is used once');

	foreach (array('booking_services', 'booking_staff', 'bookings') as $table) {
		pg_assert_contains("install_create_table('" . $table . "', \"CREATE TABLE " . $table . ' (', $step);
	}

	pg_assert_contains('UNIQUE KEY idx_staff_slot (staff_id, slot_at)', $step);
	pg_assert_contains('slot_at       INT UNSIGNED NULL DEFAULT NULL,', $step);
	pg_assert_false(strpos($step, 'idx_staff_start') !== false, 'the key is on slot_at, not start_at');
	pg_assert_contains('UNIQUE KEY idx_user (user_id)', $step);
	pg_assert_contains("status        ENUM('held','requested','confirmed','cancelled','no_show','done') NOT NULL DEFAULT 'held'", $step);
	pg_assert_contains('install_note(', $step);
	pg_assert_false(strpos($step, 'db(') !== false, 'no raw statement');

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	foreach (array('booking_services', 'booking_staff', 'bookings') as $table) {
		pg_assert_contains("\t\t'" . $table . "',\n", $install, 'in get_tables(): ' . $table);
	}
}

function test_booking_ready_probe()
{
	pg_assert_false(pg_booking_ready(), 'no database in the test run');
}

function test_booking_hours_clean()
{
	pg_assert_same(array(), pg_booking_hours_clean(''));
	pg_assert_same(array(), pg_booking_hours_clean('not json'));
	pg_assert_same(array(), pg_booking_hours_clean(null));

	// Sorted, overlaps and touching ranges merged, bad ranges and days dropped.
	pg_assert_same(
		array('1' => array(array('09:00', '13:00'), array('14:00', '18:00')), '7' => array(array('10:00', '24:00'))),
		pg_booking_hours_clean(array(
			'1' => array(array('14:00', '18:00'), array('09:00', '12:00'), array('11:30', '13:00')),
			'3' => array(array('12:00', '09:00'), array('25:00', '26:00'), 'x'),
			'7' => array(array('10:00', '24:00')),
			'8' => array(array('09:00', '17:00')),
		)));

	pg_assert_same(array('2' => array(array('09:00', '17:00'))), pg_booking_hours_clean('{"2":[["09:00","12:00"],["12:00","17:00"]]}'), 'JSON, touching');
	pg_assert_same(array('5' => array(array('08:05', '09:30'))), pg_booking_hours_clean(array(5 => array(array('8:05', '9:30')))), 'one-digit hour');
}

function test_booking_exceptions_clean()
{
	pg_assert_same(
		array('2026-12-24' => array(array('09:00', '12:00')), '2026-12-25' => array()),
		pg_booking_exceptions_clean(array(
			'2026-12-25' => array(),
			'2026-12-24' => array(array('09:00', '12:00')),
			'2026-02-30' => array(),
			'24.12.2026' => array(),
		)), 'sorted; an empty day is a day off; impossible dates dropped');
	pg_assert_same(array(), pg_booking_exceptions_clean('[]'));
}

function test_booking_slots_plain_day()
{
	$zone = 'Europe/Istanbul';
	$staff = array('hours' => array('1' => array(array('09:00', '12:00'))), 'exceptions' => array());
	$now = booking_slots_test_at('2026-11-01 08:00', $zone);
	$policy = array('zone' => $zone);

	pg_assert_same(array('09:00', '10:00', '11:00'),
		booking_slots_test_times(pg_booking_slots(booking_slots_test_service(60), $staff, '2026-11-02', array(), $now, $policy), $zone));
	pg_assert_same(array('09:00', '09:30', '10:00', '10:30', '11:00'),
		booking_slots_test_times(pg_booking_slots(booking_slots_test_service(60), $staff, '2026-11-02', array(), $now, $policy + array('step_minutes' => 30)), $zone), 'a 30 minute step');
	pg_assert_same(array(), pg_booking_slots(booking_slots_test_service(60), $staff, '2026-11-03', array(), $now, $policy), 'no hours on Tuesday');
	pg_assert_same(array(), pg_booking_slots(booking_slots_test_service(0), $staff, '2026-11-02', array(), $now, $policy), 'no length');
	pg_assert_same(array(), pg_booking_slots(booking_slots_test_service(60), $staff, '2026-11-31', array(), $now, $policy), 'no such day');
}

// The spring change: 02:00-03:00 does not exist, so six wall-clock hours hold
// five real ones. The autumn change: 02:00-03:00 comes twice, seven real hours.
function test_booking_slots_dst_change_days()
{
	$zone = 'Europe/Berlin';
	$staff = array('hours' => array('7' => array(array('00:00', '06:00'))));
	$service = booking_slots_test_service(60);
	$policy = array('zone' => $zone);

	$spring = pg_booking_slots($service, $staff, '2026-03-29', array(), booking_slots_test_at('2026-03-01 00:00', $zone), $policy);
	pg_assert_same(array('00:00', '01:00', '03:00', '04:00', '05:00'), booking_slots_test_times($spring, $zone), 'spring forward');

	$autumn = pg_booking_slots($service, $staff, '2026-10-25', array(), booking_slots_test_at('2026-10-01 00:00', $zone), $policy);
	pg_assert_same(array('00:00', '01:00', '02:00', '02:00', '03:00', '04:00', '05:00'), booking_slots_test_times($autumn, $zone), 'fall back');
	pg_assert_same(3600, $autumn[3] - $autumn[2], 'the two 02:00 are an hour apart');

	// A slot is a length of real time: 01:30 to 04:00 on the spring day is an
	// hour and a half, so the 01:30 slot ends at 03:30 and no second one fits.
	$staff_late = array('hours' => array('7' => array(array('01:30', '04:00'))));
	pg_assert_same(array('01:30'),
		booking_slots_test_times(pg_booking_slots($service, $staff_late, '2026-03-29', array(), booking_slots_test_at('2026-03-01 00:00', $zone), $policy), $zone));
}

// Where the spring change skips midnight (Chile, 6 September 2026), "24:00"
// of that day is still the next day's midnight, not an hour after it: the
// day must not offer a slot that starts once the hours have ended.
function test_booking_slots_day_end_on_a_skipped_midnight()
{
	$zone = 'America/Santiago';

	pg_assert_same(booking_slots_test_at('2026-09-07 00:00', $zone), pg_booking_wall_time('2026-09-06', '24:00', new DateTimeZone($zone)), 'the end of the day the clock skipped midnight');
	pg_assert_same(booking_slots_test_at('2026-09-06 01:00', $zone), pg_booking_wall_time('2026-09-05', '24:00', new DateTimeZone($zone)), 'the end of the day before');

	$staff = array('hours' => array('7' => array(array('22:00', '24:00'))));
	$slots = pg_booking_slots(booking_slots_test_service(60), $staff, '2026-09-06', array(), booking_slots_test_at('2026-09-01 00:00', $zone), array('zone' => $zone));

	pg_assert_same(array('22:00', '23:00'), booking_slots_test_times($slots, $zone), 'no slot past the day\'s end');
}

function test_booking_slots_buffers()
{
	$zone = 'UTC';
	$staff = array('hours' => array('1' => array(array('09:00', '13:00'))));
	$now = booking_slots_test_at('2026-11-01 00:00', $zone);
	$policy = array('zone' => $zone, 'step_minutes' => 30);
	$busy = array(array(booking_slots_test_at('2026-11-02 11:00', $zone), booking_slots_test_at('2026-11-02 11:30', $zone)));

	pg_assert_same(array('09:00', '09:30', '10:00', '11:30', '12:00'),
		booking_slots_test_times(pg_booking_slots(booking_slots_test_service(60), $staff, '2026-11-02', $busy, $now, $policy), $zone), 'no buffers');

	// 15 minutes after: a 10:00 start would run into 11:00 with its buffer.
	pg_assert_same(array('09:00', '09:30', '11:30', '12:00'),
		booking_slots_test_times(pg_booking_slots(booking_slots_test_service(60, 0, 15), $staff, '2026-11-02', $busy, $now, $policy), $zone), 'buffer after');

	// 15 minutes before: 11:30 sits right after the busy range.
	pg_assert_same(array('09:00', '09:30', '10:00', '12:00'),
		booking_slots_test_times(pg_booking_slots(booking_slots_test_service(60, 15, 0), $staff, '2026-11-02', $busy, $now, $policy), $zone), 'buffer before');

	// Buffers keep apart from other bookings, not from the edge of the hours.
	pg_assert_same(array('09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00'),
		booking_slots_test_times(pg_booking_slots(booking_slots_test_service(60, 15, 15), $staff, '2026-11-02', array(), $now, $policy), $zone), 'free day');
}

function test_booking_slots_lead_horizon_holiday_exception()
{
	$zone = 'Europe/Istanbul';
	$staff = array(
		'hours'      => array('1' => array(array('09:00', '12:00')), '4' => array(array('09:00', '12:00'))),
		'exceptions' => array('2026-11-05' => array(), '2026-11-09' => array(array('14:00', '16:00'))),
	);
	$service = booking_slots_test_service(60);
	$now = booking_slots_test_at('2026-11-02 08:30', $zone);

	pg_assert_same(array('11:00'),
		booking_slots_test_times(pg_booking_slots($service, $staff, '2026-11-02', array(), $now, array('zone' => $zone, 'lead_minutes' => 120)), $zone), 'lead time');
	pg_assert_same(array(), pg_booking_slots($service, $staff, '2026-10-26', array(), $now, array('zone' => $zone)), 'a past day');
	pg_assert_same(array(), pg_booking_slots($service, $staff, '2026-11-05', array(), $now, array('zone' => $zone)), 'a day off');
	pg_assert_same(array('14:00', '15:00'),
		booking_slots_test_times(pg_booking_slots($service, $staff, '2026-11-09', array(), $now, array('zone' => $zone)), $zone), 'other hours that day');
	pg_assert_same(array(), pg_booking_slots($service, $staff, '2026-11-16', array(), $now, array('zone' => $zone, 'horizon_days' => 7)), 'beyond the horizon');
	pg_assert_same(3, count(pg_booking_slots($service, $staff, '2026-11-16', array(), $now, array('zone' => $zone, 'horizon_days' => 30))), 'within the horizon');
	pg_assert_same(array(), pg_booking_slots($service, $staff, '2026-11-16', array(), $now, array('zone' => $zone, 'holidays' => array('2026-11-16'))), 'a holiday');
	pg_assert_same(2, count(pg_booking_slots($service, $staff, '2026-11-09', array(), $now, array('zone' => $zone, 'holidays' => array('2026-11-09')))), 'hours set for the day open a holiday');
}

function test_booking_busy_from_rows()
{
	$now = 1000000;
	$rows = array(
		array('start_at' => 2000000, 'end_at' => 2003600, 'status' => 'confirmed', 'hold_until' => 0),
		array('start_at' => 2010000, 'end_at' => 2013600, 'status' => 'held', 'hold_until' => $now + 60),
		array('start_at' => 2020000, 'end_at' => 2023600, 'status' => 'held', 'hold_until' => $now - 1),
		array('start_at' => 2030000, 'end_at' => 2033600, 'status' => 'cancelled', 'hold_until' => 0),
		array('start_at' => 2040000, 'end_at' => 2043600, 'status' => 'requested', 'hold_until' => 0),
	);

	pg_assert_same(array(array(2000000, 2003600), array(2010000, 2013600), array(2040000, 2043600)), pg_booking_busy_from_rows($rows, $now),
		'an expired hold and a cancelled booking take no time');
}

function test_booking_status_moves()
{
	pg_assert_same(array('held', 'requested', 'confirmed', 'cancelled', 'no_show', 'done'), pg_booking_statuses());

	pg_assert_true(pg_booking_can_move('held', 'confirmed'));
	pg_assert_true(pg_booking_can_move('held', 'requested'));
	pg_assert_true(pg_booking_can_move('requested', 'confirmed'));
	pg_assert_true(pg_booking_can_move('confirmed', 'done'));
	pg_assert_true(pg_booking_can_move('confirmed', 'no_show'));
	pg_assert_true(pg_booking_can_move('confirmed', 'cancelled'));

	pg_assert_false(pg_booking_can_move('cancelled', 'confirmed'), 'a cancelled booking stays cancelled');
	pg_assert_false(pg_booking_can_move('done', 'cancelled'));
	pg_assert_false(pg_booking_can_move('requested', 'held'), 'nothing goes back to a hold');
	pg_assert_false(pg_booking_can_move('confirmed', 'confirmed'));
	pg_assert_false(pg_booking_can_move('confirmed', 'unknown'));
}

// The slot of a row that holds it: a live one takes it, an expired hold or a
// cancelled booking gives it up.
function test_booking_slot_holder_expired_hold()
{
	$now = 1000000;

	pg_assert_same('free', pg_booking_slot_holder(null, $now), 'no row');
	pg_assert_same('expired', pg_booking_slot_holder(array('status' => 'held', 'hold_until' => $now - 1), $now));
	pg_assert_same('taken', pg_booking_slot_holder(array('status' => 'held', 'hold_until' => $now), $now), 'the last second of the hold');
	pg_assert_same('taken', pg_booking_slot_holder(array('status' => 'confirmed', 'hold_until' => 0), $now));
	pg_assert_same('cancelled', pg_booking_slot_holder(array('status' => 'cancelled', 'hold_until' => 0), $now));
}

// A second row for one staff member and start is refused by the unique key:
// the answer is "just taken", never a second row.
function test_booking_insert_answer_duplicate()
{
	pg_assert_same('ok', pg_booking_insert_answer(true, 0));
	pg_assert_same('taken', pg_booking_insert_answer(false, 1062), 'duplicate key');
	pg_assert_same('error', pg_booking_insert_answer(false, 1146));

	$hold = booking_slots_test_body((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/bookings.php'), 'pg_booking_insert');
	pg_assert_contains('INSERT INTO bookings', $hold);
	pg_assert_contains('start_at, end_at, slot_at,', $hold, 'a live row carries its slot');
	pg_assert_false(stripos($hold, 'ON DUPLICATE KEY') !== false, 'a duplicate is never written over');
	pg_assert_false(stripos($hold, 'INSERT IGNORE') !== false);
}

function test_booking_overlaps()
{
	$service = booking_slots_test_service(60, 10, 10);

	pg_assert_true(pg_booking_overlaps(10000, $service, array(array(13600, 14000))), 'within the after buffer');
	pg_assert_true(pg_booking_overlaps(10000, $service, array(array(9000, 9500))), 'within the before buffer');
	pg_assert_false(pg_booking_overlaps(10000, $service, array(array(14200, 15000))), 'after the buffer');
	pg_assert_false(pg_booking_overlaps(10000, $service, array(array(8000, 9400))), 'ends where the buffer starts');
}

// Buffers do not add up between two bookings: the gap is the larger of the
// earlier one's buffer after and the later one's buffer before, whichever was
// booked first (#10805).
function test_booking_overlaps_larger_buffer_of_two_bookings()
{
	$now = 1000000;
	$nine = 2000000;
	$a = booking_slots_test_service(60, 0, 30);
	$b = booking_slots_test_service(30);

	// A at 09:00 booked first, then B looked for.
	$a_booked = pg_booking_busy_from_rows(array(
		array('start_at' => $nine, 'end_at' => $nine + 3600, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => 0, 'buffer_after' => 30),
	), $now);
	pg_assert_same(array(array($nine, $nine + 3600, 0, 1800)), $a_booked, 'a booked row carries its buffers in seconds');
	pg_assert_true(pg_booking_overlaps($nine + 3600, $b, $a_booked), 'A booked first: B at 10:00 is within its buffer after');
	pg_assert_false(pg_booking_overlaps($nine + 5400, $b, $a_booked), 'A booked first: B at 10:30 is free');

	// B at 10:00 booked first, then A looked for: the same answer.
	$b_booked = pg_booking_busy_from_rows(array(
		array('start_at' => $nine + 3600, 'end_at' => $nine + 5400, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => '0', 'buffer_after' => '0'),
	), $now);
	pg_assert_true(pg_booking_overlaps($nine, $a, $b_booked), 'B booked first: A at 09:00 is refused');
	$b_later = pg_booking_busy_from_rows(array(
		array('start_at' => $nine + 5400, 'end_at' => $nine + 7200, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => 0, 'buffer_after' => 0),
	), $now);
	pg_assert_false(pg_booking_overlaps($nine, $a, $b_later), 'B booked first at 10:30: A at 09:00 is free');

	// The larger buffer counts, not the sum: A after 30, C before 20.
	$c = booking_slots_test_service(30, 20, 0);
	pg_assert_true(pg_booking_overlaps($nine + 3600 + 1200, $c, $a_booked), 'C at 10:20: within A\'s 30 minutes');
	pg_assert_false(pg_booking_overlaps($nine + 5400, $c, $a_booked), 'C at 10:30: 30 minutes, not 50');
	$c_booked = pg_booking_busy_from_rows(array(
		array('start_at' => $nine + 5400, 'end_at' => $nine + 7200, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => 20, 'buffer_after' => 0),
	), $now);
	pg_assert_false(pg_booking_overlaps($nine, $a, $c_booked), 'C booked first at 10:30: A at 09:00 is free');
	$c_close = pg_booking_busy_from_rows(array(
		array('start_at' => $nine + 4800, 'end_at' => $nine + 6600, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => 40, 'buffer_after' => 0),
	), $now);
	pg_assert_true(pg_booking_overlaps($nine + 1800, $b, $c_close), 'a booked row\'s buffer before keeps the new slot away');
	pg_assert_false(pg_booking_overlaps($nine + 6600, $b, $c_close), 'and after it, its buffer after of 0 counts');

	// The day's slots give the same answer as the check.
	$zone = 'Europe/Istanbul';
	$staff = array('hours' => array('1' => array(array('09:00', '17:00'))));
	$monday = (new DateTime('2026-11-02 09:00:00', new DateTimeZone($zone)))->getTimestamp();
	$busy = pg_booking_busy_from_rows(array(
		array('start_at' => $monday, 'end_at' => $monday + 3600, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => 0, 'buffer_after' => 30),
	), $now);
	$times = booking_slots_test_times(pg_booking_slots($b, $staff, '2026-11-02', $busy, $now, array('zone' => $zone, 'step_minutes' => 30)), $zone);
	pg_assert_false(in_array('10:00', $times, true), 'the widget does not offer B at 10:00');
	pg_assert_true(in_array('10:30', $times, true), 'B at 10:30 is offered');
}

// Busy time that is not a booking (a Workspace event, a holiday) carries no
// buffers: only the new service's buffers count (#10805).
function test_booking_overlaps_non_booking_busy_keeps_the_new_buffers()
{
	$service = booking_slots_test_service(60, 0, 15);

	pg_assert_false(pg_booking_overlaps(10000, booking_slots_test_service(60), array(array(13600, 15000))), 'an event right after the slot');
	pg_assert_true(pg_booking_overlaps(10000, $service, array(array(14000, 15000))), 'within the new service\'s buffer after');
	pg_assert_false(pg_booking_overlaps(10000, $service, array(array(14500, 15000))), 'past it');
	pg_assert_false(pg_booking_overlaps(10000, booking_slots_test_service(60), array(array(5000, 10000))), 'an event ending where the slot starts');
}

// The widget, the hold and the manage page's move read a booked row with its
// service's buffers.
function test_booking_paths_read_the_booked_buffers()
{
	$bookings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/bookings.php');
	pg_assert_contains('pg_booking_rows_near(', booking_slots_test_body($bookings, 'pg_booking_insert'));
	pg_assert_contains('booking_services.buffer_after', booking_slots_test_body($bookings, 'pg_booking_rows_near'));
	pg_assert_contains('GREATEST(', booking_slots_test_body($bookings, 'pg_booking_rows_near'), 'the window widens by the larger buffer');

	$manage = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/booking_manage.php');
	pg_assert_contains('pg_booking_rows_near(', booking_slots_test_body($manage, 'pg_booking_manage_move'));

	$widget = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_booking.php');
	pg_assert_contains('booking_services.buffer_after', booking_slots_test_body($widget, 'pg_booking_busy_between'));

}

function test_booking_manage_token()
{
	$key = 'k';
	$token = pg_booking_manage_token(12, 1800000000, $key);

	pg_assert_same(64, strlen($token));
	pg_assert_same($token, pg_booking_manage_token(12, 1800000000, $key), 'drawn again from the booking');
	pg_assert_false($token === pg_booking_manage_token(13, 1800000000, $key), 'another booking');
	pg_assert_false($token === pg_booking_manage_token(12, 1800003600, $key), 'moved: a new link');
	pg_assert_same('', pg_booking_manage_token(12, 1800000000, ''), 'no key');
	pg_assert_same('', pg_booking_manage_token(0, 1800000000, $key));

	$stored = hash('sha256', $token);
	pg_assert_true(pg_booking_manage_token_valid($token, $stored, 1800000000, 1799999999));
	pg_assert_true(pg_booking_manage_token_valid($token, $stored, 1800000000, 1800000000), 'until the end');
	pg_assert_false(pg_booking_manage_token_valid($token, $stored, 1800000000, 1800000001), 'expired with the booking');
	pg_assert_false(pg_booking_manage_token_valid(strtoupper($token), $stored, 1800000000, 1), 'exact');
	pg_assert_false(pg_booking_manage_token_valid('', hash('sha256', ''), 1800000000, 1));
	pg_assert_false(pg_booking_manage_token_valid($token, '', 1800000000, 1));
}
