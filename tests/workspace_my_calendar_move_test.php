<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an item moved on My calendar by a drag or "Move to…"
 * (includes/workspace/personal_plans.php, tasks.php): where a plan's days and times go
 * when it is moved by whole days and, on the week's hours, to another hour
 * (ws_personal_plan_moved()), and a task's due time moved to another hour
 * (ws_task_moved_time()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_my_calendar_move_test_line($moved)
{
	return $moved['start'] . ' ' . $moved['start_time'] . ' - ' . $moved['end'] . ' ' . $moved['end_time'];
}

function test_workspace_my_calendar_move_shifts_a_timed_plan_by_days()
{
	$plan = array('start' => '2026-11-27', 'end' => '2026-11-27', 'start_time' => '09:30', 'end_time' => '11:00', 'all_day' => false);

	pg_assert_same('2026-11-30 09:30 - 2026-11-30 11:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 3, -1)), 'three days on, the times kept');
	pg_assert_same('2026-11-25 09:30 - 2026-11-25 11:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, -2, -1)), 'two days back');
	pg_assert_same('2026-12-01 09:30 - 2026-12-01 11:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 4, -1)), 'into the next month');
}

function test_workspace_my_calendar_move_takes_a_timed_plan_to_an_hour()
{
	$plan = array('start' => '2026-11-27', 'end' => '2026-11-27', 'start_time' => '09:30', 'end_time' => '11:00', 'all_day' => false);

	pg_assert_same('2026-11-28 14:30 - 2026-11-28 16:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 1, 14)), 'its minutes and its length kept');
	pg_assert_same('2026-11-27 23:30 - 2026-11-28 01:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 0, 23)), 'past midnight it ends the next day');
	pg_assert_same('2026-11-27 00:30 - 2026-11-27 02:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 0, 0)), 'the first hour');
}

function test_workspace_my_calendar_move_keeps_a_plan_over_midnight_whole()
{
	$plan = array('start' => '2026-11-27', 'end' => '2026-11-28', 'start_time' => '22:00', 'end_time' => '02:00', 'all_day' => false);

	pg_assert_same('2026-11-29 22:00 - 2026-11-30 02:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 2, -1)), 'by days');
	pg_assert_same('2026-11-27 08:00 - 2026-11-27 12:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 0, 8)), 'to the morning: four hours on one day');

	$to_midnight = array('start' => '2026-11-27', 'end' => '2026-11-28', 'start_time' => '23:00', 'end_time' => '00:00', 'all_day' => false);

	pg_assert_same('2026-11-27 22:00 - 2026-11-27 23:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($to_midnight, 0, 22)), 'a plan up to midnight');
	pg_assert_same('2026-11-28 23:00 - 2026-11-29 00:00', workspace_my_calendar_move_test_line(ws_personal_plan_moved($to_midnight, 1, -1)), 'and a day on');
}

function test_workspace_my_calendar_move_shifts_an_all_day_plan_by_days_alone()
{
	$plan = array('start' => '2026-11-27', 'end' => '2026-11-29', 'start_time' => '', 'end_time' => '', 'all_day' => true);

	pg_assert_same('2026-12-04  - 2026-12-06 ', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 7, -1)), 'a week on, its three days kept');
	pg_assert_same('2026-12-04  - 2026-12-06 ', workspace_my_calendar_move_test_line(ws_personal_plan_moved($plan, 7, 10)), 'an hour is no hour of a whole-day plan');
}

function test_workspace_my_calendar_move_refuses_what_is_no_plan()
{
	pg_assert_same(null, ws_personal_plan_moved(array('start' => '', 'end' => '', 'start_time' => '', 'end_time' => '', 'all_day' => true), 1, -1), 'no first day');
	pg_assert_same(null, ws_personal_plan_moved(array('start' => '2026-11-27', 'end' => '2026-11-27', 'start_time' => 'x', 'end_time' => '', 'all_day' => false), 1, -1), 'a timed plan without a time');
	pg_assert_same(null, ws_personal_plan_moved(array('start' => '2026-11-27', 'end' => '2026-11-27', 'start_time' => '09:00', 'end_time' => '10:00', 'all_day' => false), 1, 24), 'no 24th hour');
}

function test_workspace_my_calendar_move_takes_a_task_due_time_to_an_hour()
{
	pg_assert_same('14:30', ws_task_moved_time('09:30', 14), 'the minutes kept');
	pg_assert_same('00:00', ws_task_moved_time('17:00:00', 0), 'a stored time with seconds');
	pg_assert_same('', ws_task_moved_time('', 14), 'a task without a due time stays without one');
	pg_assert_same('', ws_task_moved_time('09:30', 24), 'no 24th hour');
	pg_assert_same('', ws_task_moved_time('09:30', -1), 'no hour: nothing to change');
}
