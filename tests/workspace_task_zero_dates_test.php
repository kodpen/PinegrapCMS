<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a task's start and due dates stored as 0000-00-00 (no date):
 * moving the due date on the board or with /postpone, and the check that a
 * task does not start after it is due (includes/workspace/tasks.php).
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

function test_workspace_task_move_dates_keep_the_length_of_a_dated_task()
{
	$fields = ws_task_move_dates(array('start_date' => '2026-11-02', 'due_date' => '2026-11-05'), '2026-11-10');

	pg_assert_same(array('due_date' => '2026-11-10', 'start_date' => '2026-11-07'), $fields);
}

function test_workspace_task_move_dates_read_a_zero_date_as_no_date()
{
	pg_assert_same(array('due_date' => '2026-11-10'), ws_task_move_dates(array('start_date' => '0000-00-00', 'due_date' => '0000-00-00'), '2026-11-10'), 'neither date set');
	pg_assert_same(array('due_date' => '2026-11-10'), ws_task_move_dates(array('start_date' => '0000-00-00', 'due_date' => '2026-11-05'), '2026-11-10'), 'no start to move');
	pg_assert_same(array('due_date' => '2026-11-10'), ws_task_move_dates(array('start_date' => null, 'due_date' => null), '2026-11-10'), 'NULL dates');
	pg_assert_same(array('due_date' => '2026-11-10', 'start_date' => null), ws_task_move_dates(array('start_date' => '2026-11-02', 'due_date' => '0000-00-00'), '2026-11-10'), 'a start without a due date is cleared, as with NULL');
}

function test_workspace_task_starts_after_due_ignores_a_zero_date()
{
	pg_assert_true(ws_task_starts_after_due('2026-11-10', '2026-11-05'));
	pg_assert_true(!ws_task_starts_after_due('2026-11-05', '2026-11-05'), 'same day');
	pg_assert_true(!ws_task_starts_after_due('2026-11-10', '0000-00-00'), 'no due date');
	pg_assert_true(!ws_task_starts_after_due('0000-00-00', '2026-11-05'), 'no start date');
	pg_assert_true(!ws_task_starts_after_due(null, '2026-11-05'), 'NULL start');
}

function test_workspace_task_date_value_reads_zero_as_empty()
{
	pg_assert_same('', ws_task_date_value('0000-00-00'));
	pg_assert_same('', ws_task_date_value(null));
	pg_assert_same('', ws_task_date_value(''));
	pg_assert_same('2026-11-05', ws_task_date_value('2026-11-05'));
}
