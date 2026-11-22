<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the staff agenda's pure parts (includes/fn/booking_agenda.php):
 * the view and its period (day, week from the site's first day, list), the
 * steps of the period navigator across a clock change, the bookings laid
 * out by day in the site's zone, the status chips, who sees which staff
 * member, the cancel reason kept in the notes and the edit form's checks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

// Unix time of a wall-clock moment in a zone.
function booking_agenda_test_at($local, $zone)
{
	$moment = new DateTime($local, new DateTimeZone($zone));

	return $moment->getTimestamp();
}

function test_booking_agenda_view_falls_back_to_week()
{
	pg_assert_same('day', pg_booking_agenda_view('day'));
	pg_assert_same('list', pg_booking_agenda_view('list'));
	pg_assert_same('week', pg_booking_agenda_view('month'));
	pg_assert_same('week', pg_booking_agenda_view(array('day')));
	pg_assert_same('week', pg_booking_agenda_view(null));
}

function test_booking_agenda_day_period()
{
	$zone = 'Europe/Istanbul';
	$now = booking_agenda_test_at('2026-11-04 15:00', $zone);
	$period = pg_booking_agenda_period('day', '2026-11-10', $now, 1, $zone);

	pg_assert_same('2026-11-10', $period['date']);
	pg_assert_same(array('2026-11-10'), $period['days']);
	pg_assert_same(booking_agenda_test_at('2026-11-10 00:00', $zone), $period['start']);
	pg_assert_same(booking_agenda_test_at('2026-11-11 00:00', $zone), $period['end']);
	pg_assert_same('2026-11-09', $period['previous']);
	pg_assert_same('2026-11-11', $period['next']);
	pg_assert_same('2026-11-04', $period['today']);
}

function test_booking_agenda_bad_date_is_today()
{
	$zone = 'Europe/Istanbul';
	$now = booking_agenda_test_at('2026-11-04 01:30', $zone);

	foreach (array('', '2026-02-30', 'tomorrow', array('2026-11-10')) as $bad) {
		$period = pg_booking_agenda_period('day', $bad, $now, 1, $zone);
		pg_assert_same('2026-11-04', $period['date']);
	}
}

function test_booking_agenda_week_starts_on_the_first_day()
{
	$zone = 'Europe/Istanbul';
	$now = booking_agenda_test_at('2026-11-04 12:00', $zone);

	// Wednesday 4 November 2026: a Monday week and a Sunday week.
	$monday = pg_booking_agenda_period('week', '2026-11-04', $now, 1, $zone);
	pg_assert_same('2026-11-02', $monday['days'][0]);
	pg_assert_same('2026-11-08', $monday['days'][6]);
	pg_assert_same('2026-10-26', $monday['previous']);
	pg_assert_same('2026-11-09', $monday['next']);

	$sunday = pg_booking_agenda_period('week', '2026-11-04', $now, 0, $zone);
	pg_assert_same('2026-11-01', $sunday['days'][0]);
	pg_assert_same('2026-11-07', $sunday['days'][6]);
	pg_assert_same(booking_agenda_test_at('2026-11-08 00:00', $zone), $sunday['end']);
}

function test_booking_agenda_week_across_a_clock_change()
{
	$zone = 'Europe/Berlin';
	$now = booking_agenda_test_at('2026-10-20 12:00', $zone);

	// 25 October 2026 is the autumn change in Berlin: the week is seven whole
	// days of wall clock, one hour longer in real time.
	$week = pg_booking_agenda_period('week', '2026-10-22', $now, 1, $zone);

	pg_assert_same(7, count($week['days']));
	pg_assert_same('2026-10-25', $week['days'][6]);
	pg_assert_same(booking_agenda_test_at('2026-10-26 00:00', $zone), $week['end']);
	pg_assert_same((7 * 86400) + 3600, $week['end'] - $week['start']);
}

function test_booking_agenda_list_period_is_a_month_from_the_date()
{
	$zone = 'Europe/Istanbul';
	$now = booking_agenda_test_at('2026-11-04 12:00', $zone);
	$period = pg_booking_agenda_period('list', '2026-11-04', $now, 1, $zone);

	pg_assert_same('2026-11-01', $period['days'][0]);
	pg_assert_same('2026-11-30', $period['days'][count($period['days']) - 1]);
	pg_assert_same('2026-10-01', $period['previous']);
	pg_assert_same('2026-12-01', $period['next']);
}

function test_booking_agenda_by_day_in_the_site_zone()
{
	$zone = 'Europe/Istanbul';
	$days = array('2026-11-02', '2026-11-03');

	$rows = array(
		array('id' => 3, 'start_at' => booking_agenda_test_at('2026-11-03 10:00', $zone)),
		array('id' => 1, 'start_at' => booking_agenda_test_at('2026-11-02 23:30', $zone)),
		array('id' => 2, 'start_at' => booking_agenda_test_at('2026-11-03 09:00', $zone)),
		array('id' => 4, 'start_at' => booking_agenda_test_at('2026-11-05 09:00', $zone)),
	);

	$by_day = pg_booking_agenda_by_day($rows, $days, $zone);

	pg_assert_same(array('2026-11-02', '2026-11-03'), array_keys($by_day));
	pg_assert_same(array(1), array_column($by_day['2026-11-02'], 'id'));
	pg_assert_same(array(2, 3), array_column($by_day['2026-11-03'], 'id'));
}

function test_booking_agenda_status_chips()
{
	// No choice: every status a person made, cancelled and holds left out.
	pg_assert_same(array('requested', 'confirmed', 'no_show', 'done'), pg_booking_agenda_status_filter(null));
	pg_assert_same(array('requested', 'confirmed', 'no_show', 'done'), pg_booking_agenda_status_filter(array()));
	pg_assert_same(array('confirmed', 'cancelled'), pg_booking_agenda_status_filter(array('cancelled', 'confirmed', 'bogus', 'confirmed')));
	pg_assert_same(array('requested'), pg_booking_agenda_status_filter('requested'));
	pg_assert_same(array('held'), pg_booking_agenda_status_filter(array('held')));
}

function test_booking_agenda_scope_by_role()
{
	// Managers and up see every staff member; a staff member of role 3
	// sees their own bookings; anyone else sees none.
	pg_assert_same(null, pg_booking_agenda_scope(0, 0));
	pg_assert_same(null, pg_booking_agenda_scope(2, 7));
	pg_assert_same(7, pg_booking_agenda_scope(3, 7));
	pg_assert_same(false, pg_booking_agenda_scope(3, 0));
	pg_assert_same(false, pg_booking_agenda_scope(4, 7));
}

function test_booking_agenda_staff_filter_keeps_to_the_scope()
{
	pg_assert_same(0, pg_booking_agenda_staff_filter('', null, array(4, 7)));
	pg_assert_same(7, pg_booking_agenda_staff_filter('7', null, array(4, 7)));
	pg_assert_same(0, pg_booking_agenda_staff_filter('9', null, array(4, 7)));
	pg_assert_same(7, pg_booking_agenda_staff_filter('', 7, array(4, 7)));
	pg_assert_same(7, pg_booking_agenda_staff_filter('4', 7, array(4, 7)));
}

function test_booking_agenda_cancel_reason_goes_into_the_notes()
{
	pg_assert_same('Cancelled: Ill', pg_booking_cancel_notes('', ' Ill ', 'Cancelled'));
	pg_assert_same("Bring the report\n\nCancelled: Ill", pg_booking_cancel_notes("Bring the report\n", 'Ill', 'Cancelled'));
	pg_assert_same('Bring the report', pg_booking_cancel_notes('Bring the report', '  ', 'Cancelled'));
}

function test_booking_agenda_check_new_booking()
{
	$staff = array(4 => array('service_ids' => array(1, 2)), 7 => array('service_ids' => array(2)));
	$services = array(1 => array('length_minutes' => 30), 2 => array('length_minutes' => 60));

	$good = array('service_id' => 2, 'staff_id' => 7, 'start_at' => 1800000000, 'name' => 'Ayşe', 'email' => 'ayse@example.com', 'phone' => '');
	pg_assert_same(array(), pg_booking_agenda_check($good, $services, $staff));

	// A service the staff member does not give, no name, a bad address.
	$bad = array('service_id' => 1, 'staff_id' => 7, 'start_at' => 0, 'name' => ' ', 'email' => 'ayse@', 'phone' => '');
	$errors = pg_booking_agenda_check($bad, $services, $staff);
	pg_assert_same(array('service_id', 'start_at', 'name', 'email'), array_keys($errors));

	// No way to reach the customer at all.
	$unreachable = array('service_id' => 2, 'staff_id' => 4, 'start_at' => 1800000000, 'name' => 'Ayşe', 'email' => '', 'phone' => '');
	pg_assert_same(array('email'), array_keys(pg_booking_agenda_check($unreachable, $services, $staff)));

	// A phone alone is enough.
	$phone = array('service_id' => 2, 'staff_id' => 4, 'start_at' => 1800000000, 'name' => 'Ayşe', 'email' => '', 'phone' => '+90 555 000 00 00');
	pg_assert_same(array(), pg_booking_agenda_check($phone, $services, $staff));

	// An unknown staff member.
	$nobody = array('service_id' => 2, 'staff_id' => 9, 'start_at' => 1800000000, 'name' => 'Ayşe', 'email' => 'a@example.com', 'phone' => '');
	pg_assert_same(array('staff_id'), array_keys(pg_booking_agenda_check($nobody, $services, $staff)));
}

// The contact rule a change to a booking shares with a new one.
function test_booking_agenda_contact_error()
{
	pg_assert_same('', pg_booking_agenda_contact_error('ayse@example.com', ''));
	pg_assert_same('', pg_booking_agenda_contact_error('', '+90 555 000 00 00'), 'a phone alone is enough');
	pg_assert_same('', pg_booking_agenda_contact_error(' ayse@example.com ', '555'));
	pg_assert_same(lang('Enter an e-mail address or a phone number.'), pg_booking_agenda_contact_error(' ', ' '), 'neither');
	pg_assert_same(lang('Enter a valid e-mail address.'), pg_booking_agenda_contact_error('ayse@', '555'), 'a bad address with a phone');

	pg_assert_contains('pg_booking_agenda_contact_error($email, $phone)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_booking.php'), 'the edit screen applies it');
}

function test_booking_agenda_source_label()
{
	pg_assert_same(lang('Online booking'), pg_booking_agenda_source_label('site'));
	pg_assert_same(lang('Panel'), pg_booking_agenda_source_label('panel'));
	pg_assert_same(lang('API'), pg_booking_agenda_source_label('api'));
	pg_assert_same('import', pg_booking_agenda_source_label('import'), 'an unknown value as stored');
}

function test_booking_agenda_status_moves_offered()
{
	pg_assert_same(array('confirmed', 'cancelled'), pg_booking_agenda_moves('requested'));
	pg_assert_same(array('cancelled', 'no_show', 'done'), pg_booking_agenda_moves('confirmed'));
	pg_assert_same(array(), pg_booking_agenda_moves('done'));
	pg_assert_same(array(), pg_booking_agenda_moves('cancelled'));
}

function test_booking_agenda_expired_hold_only_cancels()
{
	$now = 1800000000;

	pg_assert_same(array('confirmed', 'cancelled'), pg_booking_agenda_moves_now(array('status' => 'held', 'hold_until' => $now + 60), $now));
	pg_assert_same(array('cancelled'), pg_booking_agenda_moves_now(array('status' => 'held', 'hold_until' => $now - 60), $now));
	pg_assert_same(array('cancelled', 'no_show', 'done'), pg_booking_agenda_moves_now(array('status' => 'confirmed', 'hold_until' => 0), $now));
}

function test_booking_agenda_audit_fields_leave_the_customer_out()
{
	$fields = pg_booking_agenda_audit_fields(array('id' => 5, 'service_id' => '2', 'staff_id' => '4', 'start_at' => '100', 'end_at' => '200',
		'status' => 'confirmed', 'source' => 'panel', 'name' => 'Ayşe', 'email' => 'a@example.com', 'phone' => '555', 'notes' => 'Çay'));

	pg_assert_same(array('service_id' => 2, 'staff_id' => 4, 'start_at' => 100, 'end_at' => 200, 'status' => 'confirmed', 'source' => 'panel', 'notes_length' => 3), $fields);
	pg_assert_same(array(), pg_booking_agenda_audit_fields(array()));
}

// A scope reaches every booking (null), the staff member's own (an id) or
// none (false).
function test_booking_agenda_scope_allows()
{
	pg_assert_true(pg_booking_agenda_scope_allows(null, 7), 'roles 0-2: any staff member');
	pg_assert_true(pg_booking_agenda_scope_allows(null, 0), 'roles 0-2: a booking without a staff member');
	pg_assert_true(pg_booking_agenda_scope_allows(7, 7), 'the staff member\'s own');
	pg_assert_false(pg_booking_agenda_scope_allows(7, 8), 'another staff member\'s');
	pg_assert_false(pg_booking_agenda_scope_allows(false, 7), 'no agenda');
	pg_assert_false(pg_booking_agenda_scope_allows(0, 0), 'a zero scope reaches nothing');
}

// The free times of a staff-made booking: an all-day event takes its last
// second too, and a holiday read from a calendar and left out under
// Workspace settings ("Do not count", ws_events.skipped) takes no time.
function test_booking_agenda_ws_ranges_leave_out_skipped_days()
{
	pg_assert_same(
		array(array(100, 200), array(500, 600)),
		pg_booking_agenda_ws_ranges(array(
			array('starts_at' => 100, 'ends_at' => 199, 'all_day' => 1, 'skipped' => 0),
			array('starts_at' => '300', 'ends_at' => '399', 'all_day' => '1', 'skipped' => '1'),
			array('starts_at' => 500, 'ends_at' => 600, 'all_day' => 0),
		)),
		'a day left out takes no time');

	$reflection = new ReflectionFunction('pg_booking_agenda_ws_busy');
	$source = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	pg_assert_contains("pg_schema_has('ws_events', 'skipped')", $source, 'the agenda reads ws_events.skipped where it exists');
}

// A Workspace holiday marked "Every year" closes its day in the later years
// too, as the Workspace calendar shows it; a one-off holiday does not.
function test_booking_agenda_ws_busy_holds_yearly_holidays()
{
	$zone = 'Europe/Istanbul';
	$yearly = array('starts_at' => booking_agenda_test_at('2026-01-01 00:00:00', $zone),
		'ends_at' => booking_agenda_test_at('2026-01-01 23:59:59', $zone), 'all_day' => 1, 'skipped' => 0, 'yearly' => 1);
	$once = array_merge($yearly, array('yearly' => 0));
	$from = booking_agenda_test_at('2027-01-01 00:00:00', $zone);
	$until = booking_agenda_test_at('2027-01-02 00:00:00', $zone);

	pg_assert_same(
		array(array($from, $until)),
		pg_booking_agenda_ws_ranges(pg_booking_ws_yearly(array($yearly, $once), $from, $until, new DateTimeZone($zone))),
		'1 January 2027 is taken by the yearly holiday only');

	$reflection = new ReflectionFunction('pg_booking_agenda_ws_busy');
	$source = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	pg_assert_contains('pg_booking_ws_yearly(', $source, 'the agenda adds the later years of yearly holidays');
	pg_assert_contains("pg_schema_has('ws_events', 'yearly')", $source, 'the agenda reads ws_events.yearly where it exists');
}

// Bookings › New booking offers only the starts its save would take: a
// booked row keeps its service's buffers, and the larger of the two sides
// counts, in either order (#10805).
function test_booking_agenda_free_slots_keep_the_booked_buffers()
{
	$zone = 'Europe/Istanbul';
	$now = booking_agenda_test_at('2026-11-01 12:00', $zone);
	$nine = booking_agenda_test_at('2026-11-02 09:00', $zone);
	$staff = array('hours' => array('1' => array(array('09:00', '17:00'))));
	$times = function (array $slots) use ($zone) {
		$out = array();
		foreach ($slots as $start) {
			$out[] = (new DateTime('@' . $start))->setTimezone(new DateTimeZone($zone))->format('H:i');
		}
		return $out;
	};

	// A (60 minutes, 30 after) booked first at 09:00, then B (30 minutes) looked for.
	$a_row = array('start_at' => $nine, 'end_at' => $nine + 3600, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => '0', 'buffer_after' => '30');
	$b = array('length_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 0);
	$free = $times(pg_booking_agenda_free_from_rows($b, $staff, '2026-11-02', array($a_row), array(), $now, $zone));
	pg_assert_false(in_array('10:00', $free, true), 'B at 10:00 is within A\'s buffer after');
	pg_assert_true(in_array('10:30', $free, true), 'B at 10:30 is offered');

	// B booked first at 10:00, then C (60 minutes, 40 before) looked for.
	$b_row = array('start_at' => $nine + 3600, 'end_at' => $nine + 5400, 'status' => 'confirmed', 'hold_until' => 0, 'buffer_before' => '20', 'buffer_after' => '0');
	$c = array('length_minutes' => 60, 'buffer_before' => 40, 'buffer_after' => 0);
	$free = $times(pg_booking_agenda_free_from_rows($c, $staff, '2026-11-02', array($b_row), array(), $now, $zone));
	pg_assert_false(in_array('09:00', $free, true), 'C at 09:00 ends inside B\'s buffer before');
	pg_assert_false(in_array('11:00', $free, true), 'C at 11:00 is within its own 40 minutes before');
	pg_assert_true(in_array('12:00', $free, true), 'C at 12:00 is offered');

	// The larger buffer, not the sum: B before 20, a 30-minute service with 30 after.
	$d = array('length_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 30);
	$free = $times(pg_booking_agenda_free_from_rows($d, $staff, '2026-11-02', array($b_row), array(), $now, $zone));
	pg_assert_true(in_array('09:00', $free, true), 'D at 09:00 ends 30 minutes before B: 30, not 50');

	// The day's query reads each booking with its service's buffers.
	$reflection = new ReflectionFunction('pg_booking_agenda_free_slots');
	$source = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
	pg_assert_contains('COALESCE(booking_services.buffer_before, 0) AS buffer_before', $source, 'the bookings carry their buffer before');
	pg_assert_contains('COALESCE(booking_services.buffer_after, 0) AS buffer_after', $source, 'the bookings carry their buffer after');
	pg_assert_contains('LEFT JOIN booking_services ON booking_services.id = bookings.service_id', $source, 'joined to their service');
}

function test_booking_agenda_list_columns_and_row()
{
	$columns = pg_booking_agenda_list_columns();
	pg_assert_same(array('time', 'status', 'customer', 'contact', 'service', 'staff'), array_keys($columns));
	pg_assert_same('link', $columns['time']['kind']);

	$row = pg_booking_agenda_list_row(array(
		'id' => '12', 'staff_id' => '3', 'name' => 'A<b', 'email' => 'a@example.com', 'phone' => '',
		'start_at' => '1700000000', 'status' => 'confirmed', 'service_name' => null,
	), array(3 => array('id' => 3, 'name' => 'Ece')), '<i>d</i>');

	pg_assert_same(12, $row['id']);
	pg_assert_same(array('time', 'status', 'customer', 'contact', 'service', 'staff'), array_keys($row['cells']), 'cells keyed by column');
	pg_assert_contains('href="edit_booking.php?id=12"', $row['cells']['time']['html']);
	pg_assert_contains('data-pg-open', $row['cells']['time']['html'], 'the time opens the booking');
	pg_assert_same('1700000000', $row['cells']['time']['attributes']['data-pg-sort-value'], 'sorted by its Unix time');
	pg_assert_contains('<i>d</i>', $row['cells']['status']['html'], 'deposit badge beside the status');
	pg_assert_same('A&lt;b', $row['cells']['customer']['html']);
	pg_assert_same('a@example.com', $row['cells']['contact']['html'], 'no line break without a phone');
	pg_assert_same('Ece', $row['cells']['staff']['html']);
}

// Any id other than 0 is an existing booking to load: a negative one ends on
// "Record not found." instead of the existing-booking form with an empty row
// (0 alone is a new booking).
function test_booking_agenda_edit_loads_any_non_zero_id()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_booking.php');

	pg_assert_contains('if ($booking_id !== 0) {' . "\n" . '    $booking = db_item("SELECT * FROM bookings', $source, 'a negative id is looked up too');
	pg_assert_false(strpos($source, '$booking_id > 0') !== false, 'no branch reads only the ids above zero');
}
