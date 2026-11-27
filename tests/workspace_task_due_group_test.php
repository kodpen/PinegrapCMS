<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the date group a task stands under on My Tasks (overdue, today,
 * this week, later, no date), worked out from its due date and the reader's
 * day (includes/workspace/tasks.php).
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

function test_workspace_task_due_group_reads_a_missing_or_zero_date_as_none()
{
	pg_assert_same('none', ws_task_due_group(null, '2026-11-25', 1), 'NULL');
	pg_assert_same('none', ws_task_due_group('', '2026-11-25', 1), 'empty');
	pg_assert_same('none', ws_task_due_group('0000-00-00', '2026-11-25', 1), 'zero date');
	pg_assert_same('none', ws_task_due_group('next week', '2026-11-25', 1), 'not a date');
}

function test_workspace_task_due_group_puts_a_past_date_in_overdue_and_the_day_in_today()
{
	pg_assert_same('overdue', ws_task_due_group('2026-11-24', '2026-11-25', 1), 'yesterday');
	pg_assert_same('overdue', ws_task_due_group('2025-01-01', '2026-11-25', 1), 'last year');
	pg_assert_same('today', ws_task_due_group('2026-11-25', '2026-11-25', 1));
}

function test_workspace_task_due_group_ends_this_week_on_sunday_when_monday_starts_it()
{
	// 2026-11-25 is a Wednesday; the week runs Monday 23 to Sunday 29.
	pg_assert_same('week', ws_task_due_group('2026-11-26', '2026-11-25', 1), 'Thursday');
	pg_assert_same('week', ws_task_due_group('2026-11-29', '2026-11-25', 1), 'Sunday, the last day');
	pg_assert_same('later', ws_task_due_group('2026-11-30', '2026-11-25', 1), 'the Monday after');
}

function test_workspace_task_due_group_ends_this_week_on_saturday_when_sunday_starts_it()
{
	pg_assert_same('week', ws_task_due_group('2026-11-28', '2026-11-25', 0), 'Saturday, the last day');
	pg_assert_same('later', ws_task_due_group('2026-11-29', '2026-11-25', 0), 'the Sunday after');
}

function test_workspace_task_due_group_on_the_last_day_of_the_week_leaves_this_week_empty()
{
	// Sunday 29 November is the last day of a week that Monday starts:
	// tomorrow is already the next week.
	pg_assert_same('today', ws_task_due_group('2026-11-29', '2026-11-29', 1));
	pg_assert_same('later', ws_task_due_group('2026-11-30', '2026-11-29', 1));

	// Sunday is the first day of a week that Sunday starts: the week runs on
	// to Saturday 5 December.
	pg_assert_same('week', ws_task_due_group('2026-12-05', '2026-11-29', 0));
	pg_assert_same('later', ws_task_due_group('2026-12-06', '2026-11-29', 0));
}

function test_workspace_task_due_group_carries_the_week_over_the_year_end()
{
	// Tuesday 29 December 2026: the week ends on Sunday 3 January 2027.
	pg_assert_same('week', ws_task_due_group('2027-01-03', '2026-12-29', 1));
	pg_assert_same('later', ws_task_due_group('2027-01-04', '2026-12-29', 1));
	pg_assert_same('overdue', ws_task_due_group('2026-12-28', '2026-12-29', 1));
}

function test_workspace_task_due_group_ignores_a_bad_first_day()
{
	// Any first day outside 0..6 is read as Monday's.
	pg_assert_same('week', ws_task_due_group('2026-11-29', '2026-11-25', 9));
	pg_assert_same('later', ws_task_due_group('2026-11-30', '2026-11-25', -1));
}

function test_workspace_task_day_in_zone_reads_the_day_where_the_reader_is()
{
	// 2026-11-25 22:30 UTC is already 26 November in Istanbul (UTC+3) and
	// still 25 November in New York.
	$moment = gmmktime(22, 30, 0, 11, 25, 2026);

	pg_assert_same('2026-11-26', ws_task_day_in_zone($moment, 'Europe/Istanbul'));
	pg_assert_same('2026-11-25', ws_task_day_in_zone($moment, 'America/New_York'));
	pg_assert_same('2026-11-25', ws_task_day_in_zone($moment, 'UTC'));
}

function test_workspace_task_due_label_reads_the_day_it_is_given()
{
	$task = array('due_date' => '2026-11-26', 'status' => 'todo');

	pg_assert_same(lang('Today'), ws_task_due_label($task, '2026-11-26'));
	pg_assert_same(lang('Tomorrow'), ws_task_due_label($task, '2026-11-25'), 'the day before');
	pg_assert_same(lang(array('string' => '{var:1} (overdue)', 'vars' => ws_day_label('2026-11-26'))), ws_task_due_label($task, '2026-11-27'), 'the day after');
	pg_assert_same(lang('Tomorrow'), ws_task_due_label(array('due_date' => '2027-01-01', 'status' => 'todo'), '2026-12-31'), 'over the year end');
}
