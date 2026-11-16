<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for whether a claimed task reminder is still sent
 * (ws_task_reminder_still_worth_sending() in includes/workspace/reminders.php):
 * a reminder at the due time, or one the job reaches a little after it, goes;
 * a finished task or one long past its due time is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/tasks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/reminders.php');

// An open task due on a day at a time.
function ws_task_reminder_send_test_task($status = 'todo')
{
	return array('id' => 5, 'status' => $status, 'due_date' => '2026-11-20', 'due_time' => '14:30:00', 'remind_minutes' => 0);
}

// "At the due time": the reminder moment is the due moment, and it goes.
function test_ws_task_reminder_at_the_due_time_is_sent()
{
	$task = ws_task_reminder_send_test_task();
	$due = ws_task_due_at($task);

	pg_assert_true(ws_task_remind_target($task) === $due, 'the reminder moment is the due moment');
	pg_assert_true(ws_task_reminder_still_worth_sending($task, $due), 'sent at the due time');
}

// A reminder the job reaches a minute after the due time still goes.
function test_ws_task_reminder_reached_a_minute_late_is_sent()
{
	$task = ws_task_reminder_send_test_task();
	$task['remind_minutes'] = 5;

	pg_assert_true(ws_task_reminder_still_worth_sending($task, ws_task_due_at($task) + 60), 'sent a minute late');
}

// Long past the due time the reminder is old news and is skipped.
function test_ws_task_reminder_long_past_the_due_time_is_skipped()
{
	$task = ws_task_reminder_send_test_task();
	$due = ws_task_due_at($task);

	pg_assert_true(ws_task_reminder_still_worth_sending($task, $due + WS_TASK_REMIND_LATE), 'sent at the end of the grace');
	pg_assert_true(!ws_task_reminder_still_worth_sending($task, $due + WS_TASK_REMIND_LATE + 1), 'skipped after the grace');
}

// A finished task, or one without a due date, is reminded of nothing.
function test_ws_task_reminder_of_a_finished_or_undated_task_is_skipped()
{
	$task = ws_task_reminder_send_test_task('done');
	pg_assert_true(!ws_task_reminder_still_worth_sending($task, ws_task_due_at($task) - 600), 'a finished task is skipped');

	$task = ws_task_reminder_send_test_task();
	$task['due_date'] = '';
	pg_assert_true(!ws_task_reminder_still_worth_sending($task, time()), 'an undated task is skipped');
	pg_assert_true(!ws_task_reminder_still_worth_sending(null, time()), 'a missing task is skipped');
}
