<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure rules of volunteer shifts
 * (includes/fn/volunteer_shifts.php): who fits a role on a day, the waitlist
 * pass, the cancel cut-off, the unfilled call's pick, the show-up rate, hours
 * sums, document notices and the signed link's token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _volunteer_test_volunteer($overrides = array())
{
	return $overrides + array(
		'id'        => 5,
		'state'     => 'active',
		'skills'    => array('first_aid', 'driving'),
		'trainings' => array(12),
		'documents' => array(
			array('kind' => 'criminal_record', 'valid_until' => '2026-12-31'),
		),
		// 2026-12-07 is a Monday (1), 2026-12-12 a Saturday (6).
		'hours'     => array(1 => array(array('08:00', '18:00')), 6 => array(array('09:00', '13:00'))),
		'shifts'    => array(),
	);
}

function _volunteer_test_role($overrides = array())
{
	return $overrides + array(
		'key'       => 'driver',
		'count'     => 2,
		'skills'    => array('driving'),
		'trainings' => array(12),
		'documents' => array('criminal_record'),
	);
}

function _volunteer_test_shift($overrides = array())
{
	return $overrides + array(
		'id'    => 100,
		'start' => '2026-12-07 09:00:00',
		'end'   => '2026-12-07 13:00:00',
	);
}

function test_volunteer_fits_a_role_and_says_why_not()
{
	$role = _volunteer_test_role();
	$shift = _volunteer_test_shift();

	pg_assert_same(array(), pg_volunteer_fit_reasons(_volunteer_test_volunteer(), $role, $shift), 'everything in place');
	pg_assert_true(pg_volunteer_fits(_volunteer_test_volunteer(), $role, $shift));

	pg_assert_same(array('state'), pg_volunteer_fit_reasons(_volunteer_test_volunteer(array('state' => 'paused')), $role, $shift));
	pg_assert_same(array('skill'), pg_volunteer_fit_reasons(_volunteer_test_volunteer(array('skills' => array('first_aid'))), $role, $shift));
	pg_assert_same(array('training'), pg_volunteer_fit_reasons(_volunteer_test_volunteer(array('trainings' => array('13'))), $role, $shift));
	pg_assert_same(array('document'), pg_volunteer_fit_reasons(_volunteer_test_volunteer(array('documents' => array())), $role, $shift));

	// Saturday 09:00-13:00 is free; 12:00-14:00 runs past the free hours.
	pg_assert_true(pg_volunteer_fits(_volunteer_test_volunteer(), $role, _volunteer_test_shift(array('start' => '2026-12-12 09:00', 'end' => '2026-12-12 13:00'))));
	pg_assert_same(array('hours'), pg_volunteer_fit_reasons(_volunteer_test_volunteer(), $role, _volunteer_test_shift(array('start' => '2026-12-12 12:00', 'end' => '2026-12-12 14:00'))));
	// Tuesday is not a free day.
	pg_assert_same(array('hours'), pg_volunteer_fit_reasons(_volunteer_test_volunteer(), $role, _volunteer_test_shift(array('start' => '2026-12-08 09:00', 'end' => '2026-12-08 10:00'))));
	// No free hours given: no limit.
	pg_assert_true(pg_volunteer_fits(_volunteer_test_volunteer(array('hours' => array())), $role, _volunteer_test_shift(array('start' => '2026-12-08 22:00', 'end' => '2026-12-09 02:00'))));

	// Several reasons together, in a fixed order.
	pg_assert_same(array('state', 'skill', 'document'), pg_volunteer_fit_reasons(
		_volunteer_test_volunteer(array('state' => 'left', 'skills' => array(), 'documents' => array())),
		$role,
		$shift
	));
}

function test_volunteer_overlapping_shifts()
{
	$a = _volunteer_test_shift();

	pg_assert_true(pg_volunteer_shifts_overlap($a, array('start' => '2026-12-07 12:00', 'end' => '2026-12-07 15:00')), 'overlaps its last hour');
	pg_assert_true(pg_volunteer_shifts_overlap($a, array('start' => '2026-12-07 10:00', 'end' => '2026-12-07 11:00')), 'inside it');
	pg_assert_false(pg_volunteer_shifts_overlap($a, array('start' => '2026-12-07 13:00', 'end' => '2026-12-07 15:00')), 'starts when it ends');
	pg_assert_false(pg_volunteer_shifts_overlap($a, array('start' => '2026-12-07 07:00', 'end' => '2026-12-07 09:00')), 'ends when it starts');

	$busy = _volunteer_test_volunteer(array('shifts' => array(array('id' => 101, 'start' => '2026-12-07 12:30', 'end' => '2026-12-07 16:00'))));
	pg_assert_same(array('busy'), pg_volunteer_fit_reasons($busy, _volunteer_test_role(), $a));

	// The shift itself in the volunteer's list is not another shift.
	$own = _volunteer_test_volunteer(array('shifts' => array(array('id' => 100, 'start' => '2026-12-07 09:00', 'end' => '2026-12-07 13:00'))));
	pg_assert_same(array(), pg_volunteer_fit_reasons($own, _volunteer_test_role(), $a));
}

function test_volunteer_document_ending_the_day_before()
{
	$role = _volunteer_test_role();
	$shift = _volunteer_test_shift();

	$day_before = _volunteer_test_volunteer(array('documents' => array(array('kind' => 'criminal_record', 'valid_until' => '2026-12-06'))));
	pg_assert_same(array('document'), pg_volunteer_fit_reasons($day_before, $role, $shift), 'ended the day before');

	$same_day = _volunteer_test_volunteer(array('documents' => array(array('kind' => 'criminal_record', 'valid_until' => '2026-12-07'))));
	pg_assert_same(array(), pg_volunteer_fit_reasons($same_day, $role, $shift), 'valid through the shift day');

	// A shift running past midnight needs the document on its last day too.
	$night = _volunteer_test_shift(array('start' => '2026-12-07 17:00', 'end' => '2026-12-08 01:00'));
	pg_assert_false(pg_volunteer_document_valid(array('kind' => 'x', 'valid_until' => '2026-12-07'), $night));
	pg_assert_true(pg_volunteer_document_valid(array('kind' => 'x', 'valid_until' => '2026-12-08'), $night));

	// An old expired copy beside a new valid one: the valid one counts.
	$renewed = _volunteer_test_volunteer(array('documents' => array(
		array('kind' => 'criminal_record', 'valid_until' => '2025-01-01'),
		array('kind' => 'criminal_record', 'valid_until' => '2027-06-30'),
	)));
	pg_assert_same(array(), pg_volunteer_fit_reasons($renewed, $role, $shift));

	pg_assert_false(pg_volunteer_document_valid(array('kind' => 'x', 'valid_until' => 'soon'), $shift), 'no readable date');
}

function test_volunteer_cancel_23_and_25_hours_before()
{
	$start = '2026-12-07 09:00:00';

	pg_assert_true(pg_volunteer_can_cancel($start, '2026-12-06 08:00:00', 24), '25 hours before');
	pg_assert_false(pg_volunteer_can_cancel($start, '2026-12-06 10:00:00', 24), '23 hours before');
	pg_assert_true(pg_volunteer_can_cancel($start, '2026-12-06 09:00:00', 24), 'exactly at the cut-off');
	pg_assert_false(pg_volunteer_can_cancel($start, '2026-12-07 10:00:00', 0), 'the shift has started');
	pg_assert_true(pg_volunteer_can_cancel($start, '2026-12-07 08:59:00', 0), 'no cut-off');
	pg_assert_true(pg_volunteer_can_cancel($start, '2026-12-06 08:00:00', null), 'the default is 24 hours');
	pg_assert_false(pg_volunteer_can_cancel($start, '2026-12-06 10:00:00', null));
}

function test_volunteer_waitlist_two_waiting()
{
	$signups = array(
		array('id' => 1, 'state' => 'signed', 'created' => '2026-12-01 10:00:00'),
		array('id' => 2, 'state' => 'cancelled', 'created' => '2026-12-01 10:05:00'),
		array('id' => 4, 'state' => 'waiting', 'created' => '2026-12-02 09:00:00'),
		array('id' => 3, 'state' => 'waiting', 'created' => '2026-12-01 11:00:00'),
	);

	pg_assert_same(array(3), pg_volunteer_waitlist_pass(2, $signups), 'one freed place: the first waiting only');
	pg_assert_same(array(3, 4), pg_volunteer_waitlist_pass(3, $signups), 'two free places: both, in turn');
	pg_assert_same(array(), pg_volunteer_waitlist_pass(1, $signups), 'no free place');

	// One who no longer fits is passed over.
	$signups[3]['fits'] = false;
	pg_assert_same(array(4), pg_volunteer_waitlist_pass(2, $signups));

	// Equal times go by id.
	$tie = array(
		array('id' => 9, 'state' => 'waiting', 'created' => '2026-12-01 10:00:00'),
		array('id' => 8, 'state' => 'waiting', 'created' => '2026-12-01 10:00:00'),
	);
	pg_assert_same(array(8), pg_volunteer_waitlist_pass(1, $tie));
}

function test_volunteer_call_pick()
{
	$role = _volunteer_test_role(array('count' => 3));
	$shift = _volunteer_test_shift();
	$now = '2026-12-05 10:00:00';

	$volunteers = array(
		_volunteer_test_volunteer(array('id' => 1)),
		_volunteer_test_volunteer(array('id' => 2, 'calls' => false)),
		_volunteer_test_volunteer(array('id' => 3, 'called' => true)),
		_volunteer_test_volunteer(array('id' => 4, 'skills' => array())),
		_volunteer_test_volunteer(array('id' => 5, 'shifts' => array(array('id' => 200, 'start' => '2026-12-07 08:00', 'end' => '2026-12-07 10:00')))),
		_volunteer_test_volunteer(array('id' => 6)),
		_volunteer_test_volunteer(array('id' => 7)),
	);

	// 7 is signed up already: one of the three places is filled.
	pg_assert_same(array(1, 6), pg_volunteer_call_pick($shift, $role, 1, array(7), $volunteers, $now, 3));

	pg_assert_same(array(), pg_volunteer_call_pick($shift, $role, 3, array(), $volunteers, $now, 3), 'full: no call');
	pg_assert_same(array(), pg_volunteer_call_pick($shift, $role, 0, array(), $volunteers, '2026-12-03 10:00:00', 3), 'too early');
	pg_assert_same(array(), pg_volunteer_call_pick($shift, $role, 0, array(), $volunteers, '2026-12-07 09:00:00', 3), 'started');

	pg_assert_true(pg_volunteer_call_due($shift, '2026-12-04 09:00:00', 3), 'exactly three days before');
	pg_assert_false(pg_volunteer_call_due($shift, '2026-12-04 08:59:00', 3));
	pg_assert_true(pg_volunteer_call_due($shift, '2026-12-04 09:00:00', null), 'the default is 3 days');
}

function test_volunteer_show_up_rate()
{
	pg_assert_same(null, pg_volunteer_show_up_rate(array()), 'nothing to count');

	$rows = array(
		array('start' => '2026-11-01 09:00', 'outcome' => 'came'),
		array('start' => '2026-11-02 09:00', 'outcome' => 'no_show'),
		array('start' => '2026-11-03 09:00', 'outcome' => 'came'),
		array('start' => '2026-11-04 09:00', 'outcome' => 'cancelled'),
	);
	pg_assert_same(67, pg_volunteer_show_up_rate($rows), 'two of three, cancels not counted');

	// Only the last 10: an old no-show drops out.
	$rows = array(array('start' => '2026-01-01 09:00', 'outcome' => 'no_show'));

	for ($day = 10; $day < 20; $day++) {
		$rows[] = array('start' => '2026-11-' . $day . ' 09:00', 'outcome' => 'came');
	}

	pg_assert_same(100, pg_volunteer_show_up_rate($rows));
}

function test_volunteer_hours_sums()
{
	pg_assert_same(240, pg_volunteer_signup_minutes(array(), _volunteer_test_shift()), 'the shift length');
	pg_assert_same(225, pg_volunteer_signup_minutes(array('check_in' => '2026-12-07 09:10:00', 'check_out' => '2026-12-07 12:55:00'), _volunteer_test_shift()), 'checked in and out');
	pg_assert_same(240, pg_volunteer_signup_minutes(array('check_in' => '2026-12-07 09:10:00'), _volunteer_test_shift()), 'never checked out');
	pg_assert_same(90, pg_volunteer_signup_minutes(array('minutes' => 90), null), 'hours outside a shift');

	$rows = array(
		array('volunteer_id' => 1, 'programme_id' => 1, 'role' => 'driver', 'day' => '2026-11-03', 'minutes' => 240, 'approved' => true),
		array('volunteer_id' => 1, 'programme_id' => 1, 'role' => 'cook', 'day' => '2026-11-20', 'minutes' => 90, 'approved' => true),
		array('volunteer_id' => 2, 'programme_id' => 1, 'role' => 'driver', 'day' => '2026-11-21', 'minutes' => 60, 'approved' => true),
		array('volunteer_id' => 2, 'programme_id' => 1, 'role' => 'driver', 'day' => '2026-11-22', 'minutes' => 600, 'approved' => false),
		array('volunteer_id' => 1, 'programme_id' => 2, 'role' => 'driver', 'day' => '2026-12-01', 'minutes' => 30, 'approved' => true),
	);

	pg_assert_same(420, pg_volunteer_hours_total($rows), 'approved only');
	pg_assert_same(390, pg_volunteer_hours_total($rows, '2026-11-01', '2026-11-30'), 'a period, both ends counted');
	pg_assert_same(array('driver' => 330, 'cook' => 90), pg_volunteer_hours_by($rows, 'role'));
	pg_assert_same(array(1 => 360, 2 => 60), pg_volunteer_hours_by($rows, 'volunteer_id'));
	pg_assert_same(array(1 => 330), pg_volunteer_hours_by($rows, 'volunteer_id', '2026-11-01', '2026-11-20'));
}

function test_volunteer_document_notice_days()
{
	pg_assert_same(0, pg_volunteer_document_notice('2026-12-31', '2026-11-30'), '31 days left: nothing yet');
	pg_assert_same(30, pg_volunteer_document_notice('2026-12-31', '2026-12-01'), '30 days left');
	pg_assert_same(30, pg_volunteer_document_notice('2026-12-31', '2026-12-20'), 'a missed day still sends it');
	pg_assert_same(0, pg_volunteer_document_notice('2026-12-31', '2026-12-20', array(30)), 'sent once');
	pg_assert_same(7, pg_volunteer_document_notice('2026-12-31', '2026-12-24', array(30)), '7 days left');
	pg_assert_same(7, pg_volunteer_document_notice('2026-12-31', '2026-12-31', array()), 'the last day: the nearer notice only');
	pg_assert_same(0, pg_volunteer_document_notice('2026-12-31', '2026-12-31', array(7)));
	pg_assert_same(0, pg_volunteer_document_notice('2026-12-31', '2027-01-01'), 'expired');
	pg_assert_same(0, pg_volunteer_document_notice('', '2026-12-01'), 'no date');
	pg_assert_same(31, pg_volunteer_days_between('2026-11-30', '2026-12-31'));
}

function test_volunteer_link_token()
{
	$key = str_repeat('k', 32);
	$token = pg_volunteer_link_token(5, 1764000000, $key);

	pg_assert_true(preg_match('/^[0-9a-f]{64}$/', $token) === 1, '64 hex');
	pg_assert_same($token, pg_volunteer_link_token(5, 1764000000, $key), 'drawn again, the same');
	pg_assert_true($token !== pg_volunteer_link_token(5, 1764000001, $key), 'a new issue, a new token');
	pg_assert_true($token !== pg_volunteer_link_token(6, 1764000000, $key), 'another volunteer');
	pg_assert_true($token !== pg_booking_manage_token(5, 1764000000, $key), 'bound to its feature');
	pg_assert_same('', pg_volunteer_link_token(5, 1764000000, ''), 'no key, no token');
	pg_assert_same('', pg_volunteer_link_token(0, 1764000000, $key));

	$stored = pg_volunteer_link_token_hash($token);
	pg_assert_true(pg_volunteer_link_token_valid($token, $stored));
	pg_assert_false(pg_volunteer_link_token_valid(strtoupper($token), $stored));
	pg_assert_false(pg_volunteer_link_token_valid($token, ''), 'withdrawn');
	pg_assert_false(pg_volunteer_link_token_valid(array(), $stored));
}
