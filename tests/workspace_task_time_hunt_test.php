<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the timer guards of the workspace's task time
 * (includes/workspace/task_time.php: a Stop pressed on a task stops only
 * the timer running there, and a second timer started at the same moment
 * is stopped) and for the guarded tie of a new repeat to its task
 * (includes/workspace/recurrence.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/task_time.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/recurrence.php');

// A tab drawn before the timer moved to task 7 must not stop it from task 3.
function test_task_time_stop_only_on_the_task_pressed()
{
	$running = array('id' => 40, 'task_id' => 7, 'minutes' => 0, 'started_at' => 1700000000);

	pg_assert_true(ws_task_time_stops_here($running, 7));
	pg_assert_true(!ws_task_time_stops_here($running, 3), 'another task');
	pg_assert_true(ws_task_time_stops_here($running, 0), 'no task named: /timer stop');
	pg_assert_true(!ws_task_time_stops_here(null, 0), 'nothing running');
}

function test_task_time_extra_running_keeps_the_new_one()
{
	$rows = array(
		array('id' => 10, 'minutes' => 0, 'started_at' => 1700000000),
		array('id' => 11, 'minutes' => 0, 'started_at' => 1700000001),
		array('id' => 9, 'minutes' => 25, 'started_at' => 1699990000),
	);
	$extra = ws_task_time_extra_running($rows, 11);

	pg_assert_same(1, count($extra));
	pg_assert_same(10, (int) $extra[0]['id']);
	pg_assert_same(array(), ws_task_time_extra_running(array(), 11));
}

// The tie holds only while the task still has the series it was read with.
function test_recurrence_attach_where_guards_the_read_series()
{
	pg_assert_same("id = '5' AND COALESCE(recurrence_id, 0) = '0'", ws_recurrence_attach_where(5, null));
	pg_assert_same("id = '5' AND COALESCE(recurrence_id, 0) = '12'", ws_recurrence_attach_where('5', '12'));
}
