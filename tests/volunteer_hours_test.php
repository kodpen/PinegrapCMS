<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of volunteer check-in and hours
 * (includes/fn/volunteer_hours.php, includes/fn/volunteer_hours_view.php):
 * the poster's signed code, what a scan does, "Did not come" after 30
 * minutes, which hours wait for approval, and hours typed outside a shift.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours_view.php');

function _volunteer_hours_test_shift()
{
	return array('start' => '2026-12-07 09:00:00', 'end' => '2026-12-07 13:00:00');
}

function test_volunteer_time_add()
{
	pg_assert_same('2026-12-07 08:00:00', pg_volunteer_time_add('2026-12-07 09:00:00', -60));
	pg_assert_same('2026-12-08 00:30:00', pg_volunteer_time_add('2026-12-07 23:30', 60), 'across midnight, seconds added');
	pg_assert_same('2026-03-29 03:30:00', pg_volunteer_time_add('2026-03-29 02:30:00', 60), 'no clock change moves a wall-clock time');
	pg_assert_same('', pg_volunteer_time_add('soon', 60));
}

function test_volunteer_poster_code_is_signed_per_place()
{
	$code = pg_volunteer_poster_code(5, 'Kitchen', 'key');

	pg_assert_same(20, strlen($code));
	pg_assert_true(pg_volunteer_poster_code_valid(5, 'Kitchen', $code, 'key'));
	pg_assert_false(pg_volunteer_poster_code_valid(5, 'Hall', $code, 'key'), 'another place');
	pg_assert_false(pg_volunteer_poster_code_valid(6, 'Kitchen', $code, 'key'), 'another programme');
	pg_assert_false(pg_volunteer_poster_code_valid(5, 'Kitchen', $code, 'other key'), 'another site');
	pg_assert_false(pg_volunteer_poster_code_valid(5, 'Kitchen', array($code), 'key'), 'an array is no code');
	pg_assert_false(pg_volunteer_poster_code_valid(5, 'Kitchen', $code, ''), 'no key, no poster');
}

function test_volunteer_scan_step()
{
	$shift = _volunteer_hours_test_shift();
	$signed = array('state' => 'signed', 'check_in' => null, 'check_out' => null);

	pg_assert_same('early', pg_volunteer_scan_step($signed, $shift, '2026-12-07 07:59:00'), 'more than an hour before the start');
	pg_assert_same('check_in', pg_volunteer_scan_step($signed, $shift, '2026-12-07 08:00:00'), 'an hour before the start');
	pg_assert_same('check_in', pg_volunteer_scan_step($signed, $shift, '2026-12-07 12:59:00'), 'late, still within the shift');
	pg_assert_same('over', pg_volunteer_scan_step($signed, $shift, '2026-12-07 13:00:00'));
	pg_assert_same('check_out', pg_volunteer_scan_step(array('check_in' => '2026-12-07 09:01:00') + $signed, $shift, '2026-12-07 17:00:00'), 'up to four hours after the end');
	pg_assert_same('done', pg_volunteer_scan_step(array('check_in' => '2026-12-07 09:01:00') + $signed, $shift, '2026-12-07 17:01:00'));
	pg_assert_same('done', pg_volunteer_scan_step(array('check_in' => '2026-12-07 09:01:00', 'check_out' => '2026-12-07 12:00:00') + $signed, $shift, '2026-12-07 12:30:00'), 'one button: no second check-out');
	pg_assert_same('none', pg_volunteer_scan_step(array('state' => 'waiting') + $signed, $shift, '2026-12-07 09:00:00'), 'the waitlist does not check in');
}

function test_volunteer_scan_pick_prefers_an_open_check_out()
{
	$morning = array('id' => 1, 'state' => 'signed', 'check_in' => '2026-12-07 09:00:00', 'check_out' => null, 'shift' => _volunteer_hours_test_shift());
	$afternoon = array('id' => 2, 'state' => 'signed', 'check_in' => null, 'check_out' => null, 'shift' => array('start' => '2026-12-07 13:00:00', 'end' => '2026-12-07 16:00:00'));

	$pick = pg_volunteer_scan_pick(array($afternoon, $morning), '2026-12-07 12:55:00');
	pg_assert_same(1, $pick['id']);
	pg_assert_same('check_out', $pick['step']);

	$pick = pg_volunteer_scan_pick(array($afternoon, array('check_out' => '2026-12-07 12:58:00') + $morning), '2026-12-07 12:59:00');
	pg_assert_same(2, $pick['id'], 'after the check-out the next shift checks in');
	pg_assert_same('check_in', $pick['step']);

	pg_assert_same(null, pg_volunteer_scan_pick(array(array('check_in' => null) + $morning), '2026-12-07 14:00:00'), 'an ended shift with no check-in');
}

function test_volunteer_no_show_due_after_thirty_minutes()
{
	$signed = array('state' => 'signed', 'check_in' => null, 'outcome' => '');

	pg_assert_false(pg_volunteer_no_show_due($signed, '2026-12-07 09:00:00', '2026-12-07 09:29:59'));
	pg_assert_true(pg_volunteer_no_show_due($signed, '2026-12-07 09:00:00', '2026-12-07 09:30:00'));
	pg_assert_false(pg_volunteer_no_show_due(array('check_in' => '2026-12-07 09:10:00') + $signed, '2026-12-07 09:00:00', '2026-12-07 10:00:00'), 'checked in');
	pg_assert_false(pg_volunteer_no_show_due(array('outcome' => 'no_show') + $signed, '2026-12-07 09:00:00', '2026-12-07 10:00:00'), 'marked once');
	pg_assert_false(pg_volunteer_no_show_due(array('state' => 'cancelled') + $signed, '2026-12-07 09:00:00', '2026-12-07 10:00:00'), 'a cancelled place');
}

function test_volunteer_hours_waiting()
{
	$shift = _volunteer_hours_test_shift();
	$came = array('state' => 'signed', 'check_in' => '2026-12-07 09:00:00', 'check_out' => null, 'approved_at' => 0);

	pg_assert_false(pg_volunteer_hours_waiting($came, $shift, '2026-12-07 12:00:00'), 'still on the shift');
	pg_assert_true(pg_volunteer_hours_waiting($came, $shift, '2026-12-07 13:00:00'), 'the shift ended');
	pg_assert_true(pg_volunteer_hours_waiting(array('check_out' => '2026-12-07 11:00:00') + $came, $shift, '2026-12-07 11:05:00'), 'checked out early');
	pg_assert_false(pg_volunteer_hours_waiting(array('check_in' => null) + $came, $shift, '2026-12-07 14:00:00'), 'never came');
	pg_assert_false(pg_volunteer_hours_waiting(array('approved_at' => 1700000000) + $came, $shift, '2026-12-07 14:00:00'), 'approved once');
	pg_assert_true(pg_volunteer_hours_waiting(array('state' => 'signed', 'minutes' => 90, 'approved_at' => 0), null, '2026-12-07 14:00:00'), 'outside a shift at once');
}

function test_volunteer_outside_input()
{
	$read = pg_volunteer_outside_input(array('day' => '2026-12-06', 'hours' => '2,5', 'note' => "  Sorted   donations\n"), '2026-12-07');
	pg_assert_same(array('worked_on' => '2026-12-06', 'minutes' => 150, 'note' => 'Sorted donations'), $read['row']);

	pg_assert_same('day', pg_volunteer_outside_input(array('day' => '2026-12-08', 'hours' => '2'), '2026-12-07')['error'], 'not after today');
	pg_assert_same('day', pg_volunteer_outside_input(array('day' => '2026-09-07', 'hours' => '2'), '2026-12-07')['error'], 'older than 90 days');
	pg_assert_same('2026-09-08', pg_volunteer_outside_input(array('day' => '2026-09-08', 'hours' => '2'), '2026-12-07')['row']['worked_on'], '90 days back still counts');
	pg_assert_same('day', pg_volunteer_outside_input(array('day' => '2026-02-30', 'hours' => '2'), '2026-12-07')['error']);
	pg_assert_same('hours', pg_volunteer_outside_input(array('day' => '2026-12-06', 'hours' => '0.1'), '2026-12-07')['error'], 'under a quarter');
	pg_assert_same('hours', pg_volunteer_outside_input(array('day' => '2026-12-06', 'hours' => '25'), '2026-12-07')['error'], 'over a day');
	pg_assert_same('hours', pg_volunteer_outside_input(array('day' => '2026-12-06', 'hours' => '-3'), '2026-12-07')['error']);
	pg_assert_same('hours', pg_volunteer_outside_input(array('day' => '2026-12-06', 'hours' => array('2')), '2026-12-07')['error'], 'an array is no number');
}

function test_volunteer_day_state()
{
	pg_assert_same('rose', pg_volunteer_day_state(array('outcome' => 'no_show', 'check_in' => null, 'check_out' => null))[1]);
	pg_assert_same('teal', pg_volunteer_day_state(array('outcome' => 'came', 'check_in' => '2026-12-07 09:00:00', 'check_out' => null))[1], 'came after "Did not come"');
	pg_assert_same('2026-12-07 12:00:00', pg_volunteer_day_state(array('outcome' => 'came', 'check_in' => '2026-12-07 09:00:00', 'check_out' => '2026-12-07 12:00:00'))[2]);
	pg_assert_same('amber', pg_volunteer_day_state(array('outcome' => '', 'check_in' => null, 'check_out' => null))[1]);
}

function test_volunteer_check_in_page_is_sensitive()
{
	pg_assert_contains("'volunteer_check_in.php'", pg_test_function_source('waf_sensitive_scripts'), 'the scan page on the sensitive allowance');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/volunteer_check_in.php');
	pg_assert_contains("header('Cache-Control: no-store, private');", $page);
	pg_assert_true(strpos($page, 'validate_token_field();') < strpos($page, 'pg_volunteer_check_write('), 'every write checks the form token first');
	pg_assert_true(strpos($page, 'pg_volunteer_poster_code_valid(') < strpos($page, 'pg_volunteer_scan_rows('), 'the poster is checked before any shift is read');
	pg_assert_contains("\$_SESSION['volunteer']['ids']", $page, 'only the volunteers this browser holds');
}

function test_volunteer_hours_answers_reach_the_page()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_volunteer_hours.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');

	pg_assert_true(pg_volunteer_hours_answer_text('hours_added')[1]);
	pg_assert_false(pg_volunteer_hours_answer_text('day')[1]);
	pg_assert_same(null, pg_volunteer_hours_answer_text('signed'), 'a shift action keeps its own text');
	pg_assert_contains("if (\$action === 'hours')", pg_test_function_source('pg_volunteer_self_act'), 'the page\'s posted hours reach the store');
}
