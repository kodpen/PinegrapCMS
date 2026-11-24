<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the volunteer shift board
 * (includes/fn/volunteer_board.php): the week's Monday, the Add shift form
 * read and cleaned, the weekly series, "Copy last week", a role's colour,
 * the days and places of the board and who is told when a shift goes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');

function _volunteer_board_test_roles()
{
	return array('r1' => array('name' => 'Driver'), 'r2' => array('name' => 'Packer'));
}

function test_volunteer_week_monday()
{
	// 2026-12-07 is a Monday.
	pg_assert_same('2026-12-07', pg_volunteer_week_monday('2026-12-07'));
	pg_assert_same('2026-12-07', pg_volunteer_week_monday('2026-12-13'), 'a Sunday belongs to the week before it');
	pg_assert_same('2026-12-28', pg_volunteer_week_monday('2027-01-01'), 'across the year');
	pg_assert_same(null, pg_volunteer_week_monday('2026-02-30'));
	pg_assert_same(null, pg_volunteer_week_monday('soon'));
	pg_assert_same('2026-12-14', pg_volunteer_add_days('2026-12-07', 7));
	pg_assert_same('2026-03-30', pg_volunteer_add_days('2026-03-23', 7), 'no clock change moves a wall-clock day');
}

function test_volunteer_shift_input_reads_the_add_shift_form()
{
	$read = pg_volunteer_shift_input(array(
		'place'    => '  Warehouse  ',
		'date'     => '2026-12-07',
		'start_at' => '09:00',
		'end_at'   => '13:30',
		'count'    => array('r1' => '2', 'r2' => '0', 'r9' => '4'),
		'note'     => ' Bring gloves ',
		'repeat'   => '1',
		'weeks'    => '40',
	), _volunteer_board_test_roles());

	pg_assert_same(array(), $read['errors']);
	pg_assert_same(array('place' => 'Warehouse', 'start' => '2026-12-07 09:00:00', 'end' => '2026-12-07 13:30:00', 'roles' => array('r1' => 2), 'note' => 'Bring gloves'), $read['shift'], 'a role of another programme and a zero go');
	pg_assert_same(PG_VOLUNTEER_SERIES_WEEKS, $read['weeks'], 'held to its top');
	pg_assert_same(1, pg_volunteer_shift_input(array('date' => '2026-12-07', 'start_at' => '09:00', 'end_at' => '10:00', 'count' => array('r1' => '1'), 'weeks' => '5'), _volunteer_board_test_roles())['weeks'], 'without Repeat weekly one shift');
}

function test_volunteer_shift_input_ends_past_midnight_and_refuses_gaps()
{
	$night = pg_volunteer_shift_input(array('place' => 'Shelter', 'date' => '2026-12-31', 'start_at' => '22:00', 'end_at' => '06:00', 'count' => array('r2' => '1')), _volunteer_board_test_roles());

	pg_assert_same('2027-01-01 06:00:00', $night['shift']['end'], 'an end before the start is the next day');
	pg_assert_same(array(), $night['errors']);

	$bad = pg_volunteer_shift_input(array('place' => '', 'date' => '2026-13-01', 'start_at' => '25:00', 'end_at' => '', 'count' => array('r1' => '0')), _volunteer_board_test_roles());

	pg_assert_same(3, count($bad['errors']), 'the place, the date and times, the places of the roles');
	pg_assert_same(1, count(pg_volunteer_shift_input(array('place' => 'A', 'date' => '2026-12-07', 'start_at' => '09:00', 'end_at' => '09:00', 'count' => array('r1' => '1')), _volunteer_board_test_roles())['errors']), 'a shift of no length');
	pg_assert_same(99, pg_volunteer_shift_input(array('count' => array('r1' => '500')), _volunteer_board_test_roles())['shift']['roles']['r1']);
}

function test_volunteer_series_spans_a_week_apart()
{
	pg_assert_same(array(
		array('2026-12-31 22:00:00', '2027-01-01 06:00:00'),
		array('2027-01-07 22:00:00', '2027-01-08 06:00:00'),
		array('2027-01-14 22:00:00', '2027-01-15 06:00:00'),
	), pg_volunteer_series_spans('2026-12-31 22:00:00', '2027-01-01 06:00:00', 3));
	pg_assert_same(1, count(pg_volunteer_series_spans('2026-12-07 09:00:00', '2026-12-07 10:00:00', 0)));
}

function test_volunteer_copy_last_week_skips_what_is_there()
{
	$last = array(
		array('id' => 1, 'place' => 'Warehouse', 'start' => '2026-11-30 09:00:00', 'end' => '2026-11-30 13:00:00', 'roles' => array('r1' => 2), 'note' => 'Gloves', 'series_id' => 1),
		array('id' => 2, 'place' => 'Shop', 'start' => '2026-12-06 22:00:00', 'end' => '2026-12-07 02:00:00', 'roles' => array('r2' => 1), 'note' => '', 'series_id' => 0),
	);
	$this_week = array(
		array('id' => 9, 'place' => 'Warehouse', 'start' => '2026-12-07 09:00:00', 'end' => '2026-12-07 13:00:00', 'roles' => array('r1' => 3)),
	);

	pg_assert_same(array(
		array('place' => 'Shop', 'start' => '2026-12-13 22:00:00', 'end' => '2026-12-14 02:00:00', 'roles' => array('r2' => 1), 'note' => ''),
	), pg_volunteer_week_copy($last, $this_week), 'the same place and times this week is not copied again');
	pg_assert_same(2, count(pg_volunteer_week_copy($last, array())));
}

function test_volunteer_role_tone()
{
	$shift = array('start' => '2026-12-07 09:00:00', 'end' => '2026-12-07 13:00:00');

	pg_assert_same('full', pg_volunteer_role_tone(3, 3, $shift, '2026-12-06 09:00:00'));
	pg_assert_same('full', pg_volunteer_role_tone(4, 3, $shift, '2026-12-06 09:00:00'));
	pg_assert_same('short', pg_volunteer_role_tone(1, 3, $shift, '2026-12-01 09:00:00'), 'short, a week away');
	pg_assert_same('near', pg_volunteer_role_tone(1, 3, $shift, '2026-12-05 09:00:00'), 'short and within the call days');
	pg_assert_same('short', pg_volunteer_role_tone(1, 3, $shift, '2026-12-05 09:00:00', 1), 'the programme\'s own call days');
	pg_assert_same('short', pg_volunteer_role_tone(1, 3, $shift, '2026-12-07 10:00:00'), 'once started it is no longer near');
}

function test_volunteer_board_days_and_places()
{
	$shifts = array(
		array('id' => 3, 'place' => 'Shop', 'start' => '2026-12-08 14:00:00'),
		array('id' => 1, 'place' => 'Warehouse', 'start' => '2026-12-08 09:00:00'),
		array('id' => 2, 'place' => 'Shop', 'start' => '2026-12-08 09:00:00'),
		array('id' => 4, 'place' => 'Shop', 'start' => '2026-12-13 23:00:00'),
		array('id' => 5, 'place' => 'Shop', 'start' => '2026-12-14 09:00:00'),
	);
	$days = pg_volunteer_board_days($shifts, '2026-12-07');

	pg_assert_same(array('2026-12-07', '2026-12-08', '2026-12-09', '2026-12-10', '2026-12-11', '2026-12-12', '2026-12-13'), array_keys($days));
	pg_assert_same(array(2, 1, 3), array_column($days['2026-12-08'], 'id'), 'by start, then place');
	pg_assert_same(array(4), array_column($days['2026-12-13'], 'id'), 'next week\'s shift stays out');
	pg_assert_same(array(2, 3), array_column(pg_volunteer_board_days($shifts, '2026-12-07', 'Shop')['2026-12-08'], 'id'));
	pg_assert_same(array('Shop', 'Warehouse'), pg_volunteer_board_places($shifts));
}

function test_volunteer_shift_people_told()
{
	pg_assert_same(array(4, 6), pg_volunteer_shift_people_told(array(
		array('volunteer_id' => 4, 'state' => 'signed'),
		array('volunteer_id' => 5, 'state' => 'cancelled'),
		array('volunteer_id' => 6, 'state' => 'waiting'),
		array('volunteer_id' => 4, 'state' => 'signed'),
	)));
}

function test_volunteer_shift_call_skips_a_removed_role()
{
	$programme = array('roles' => array('r1' => array('name' => 'Driver', 'skills' => array('Driving'))), 'call_days' => 3);
	$shift = array('id' => 7, 'start' => '2026-12-03 09:00:00', 'end' => '2026-12-03 12:00:00', 'roles' => array('r1' => 1, 'r9' => 2), 'filled' => array(), 'signups' => array());
	$person = function ($id, array $skills) {
		return array('id' => $id, 'state' => 'active', 'skills' => $skills, 'trainings' => array(), 'documents' => array(), 'hours' => array(), 'shifts' => array(), 'calls' => true, 'called' => false);
	};
	$people = array(1 => $person(1, array('Driving')), 2 => $person(2, array()));

	pg_assert_same(array(1), pg_volunteer_shift_call_ids($programme, $shift, $people, '2026-12-01 10:00:00'), 'a role the programme no longer has calls nobody: nobody could take its place');
}
