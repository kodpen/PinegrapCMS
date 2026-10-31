<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace Today page's task card and the day states of the
 * plan grid: the tasks due today are never pushed out by overdue ones, a
 * row tells how many more there are, the priority badge stays outside the
 * shortened title, the two-column grid fits a phone; a day off with work on
 * it is told as a day off, never as a percentage.
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

function workspace_today_plan_test_task($id, $due)
{
	return array('id' => $id, 'title' => 'Task ' . $id, 'due_date' => $due);
}

function test_workspace_today_due_keeps_the_tasks_due_today()
{
	$today = '2026-10-31';
	$rows = array();

	foreach (array(1 => '2026-10-01', 2 => '2026-10-02', 3 => '2026-10-03', 4 => '2026-10-04', 5 => '2026-10-05', 6 => '2026-10-30', 7 => $today, 8 => $today) as $id => $due) {
		$rows[] = workspace_today_plan_test_task($id, $due);
	}

	$ids = array_map(function ($task) { return $task['id']; }, ws_home_due_pick($rows, $today, 6));

	pg_assert_same(array(1, 2, 3, 4, 7, 8), $ids, 'today first kept, the earliest overdue fill the rest, by due date');
}

function test_workspace_today_due_with_few_tasks_keeps_them_all()
{
	$rows = array(workspace_today_plan_test_task(1, '2026-10-30'), workspace_today_plan_test_task(2, '2026-10-31'), workspace_today_plan_test_task(3, null));

	$ids = array_map(function ($task) { return $task['id']; }, ws_home_due_pick($rows, '2026-10-31', 6));

	pg_assert_same(array(1, 2), $ids, 'undated tasks never listed');
}

function test_workspace_today_due_more_today_than_room()
{
	$rows = array();

	for ($id = 1; $id <= 8; $id++) {
		$rows[] = workspace_today_plan_test_task($id, '2026-10-31');
	}

	pg_assert_same(6, count(ws_home_due_pick($rows, '2026-10-31', 6)), 'at most the room');
}

function test_workspace_today_card_tells_the_rest_and_keeps_the_badge_apart()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_true(preg_match('/\n        homeDue: function \([^)]*\) \{\n(.*?)\n        \},\n/s', $source, $match) === 1, 'homeDue found');
	pg_assert_contains("t('home_tasks_more'", $match[1], 'a row for the tasks not shown');
	pg_assert_false(strpos($match[1], 'titleLine.appendChild(el(\'span\', \'ws-home-priority') !== false, 'badge not inside the shortened title');
	pg_assert_contains('ws-home-task-head', $match[1], 'title and badge share a line');
}

function test_workspace_today_two_column_grid_fits_a_phone()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_true(preg_match('/\.ws-home-grid-2 \{\s*grid-template-columns: repeat\(auto-fit, minmax\(min\(22rem, 100%\), 1fr\)\);/', $css) === 1, 'one column never wider than the page');
}

function test_workspace_plan_day_state()
{
	$cell = function ($extra) {
		return $extra + array('workday' => true, 'leave' => false, 'holiday' => false, 'capacity' => 480, 'minutes' => 0, 'load' => 0);
	};

	pg_assert_same('away', ws_plan_day_state($cell(array('leave' => true, 'capacity' => 0, 'minutes' => 60, 'load' => 999))), 'away');
	pg_assert_same('holiday', ws_plan_day_state($cell(array('holiday' => true, 'capacity' => 0))), 'holiday');
	pg_assert_same('off', ws_plan_day_state($cell(array('workday' => false, 'capacity' => 0, 'minutes' => 60, 'load' => 999))), 'work on a day off');
	pg_assert_same('off', ws_plan_day_state($cell(array('capacity' => 0, 'minutes' => 30, 'load' => 999))), 'work on a day of no hours');
	pg_assert_same('over', ws_plan_day_state($cell(array('minutes' => 600, 'load' => 125))), 'over');
	pg_assert_same('full', ws_plan_day_state($cell(array('minutes' => 400, 'load' => 83))), 'full');
	pg_assert_same('ok', ws_plan_day_state($cell(array('minutes' => 60, 'load' => 13))), 'ok');
}

function test_workspace_plan_texts_never_print_the_marker()
{
	foreach (array('includes/workspace/commands.php', 'includes/workspace/schedule.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('ws_plan_day_state(', $source, $file . ' reads the day state');
	}
}
