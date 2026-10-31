<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Workspace rows drawn as divs with role=button: the
 * Work Calendar day drawer's task and plan-item rows, My time's rows and the
 * inbox's Join shortcut take focus and answer Enter and Space, and the
 * planning board prints its decimal hours in the panel language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_keyboard_rows_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The one helper every role=button row goes through: focus, the role, and
// Enter or Space on the row itself (a control inside keeps its own keys).
function test_workspace_keyboard_rows_helper_answers_enter_and_space()
{
	$source = workspace_keyboard_rows_source('assets/js/workspace.js');

	pg_assert_contains('function pressKeys(node, action) {', $source);
	pg_assert_contains("event.target === node && (event.key === 'Enter' || event.key === ' ')", $source);
	pg_assert_contains('pressKeys: pressKeys,', $source);
}

// No row answers Enter alone any more.
function test_workspace_keyboard_rows_no_enter_only_keydown()
{
	$source = workspace_keyboard_rows_source('assets/js/workspace.js');

	pg_assert_false((bool) preg_match("/if \\(event\\.key === 'Enter'\\) \\{\\s*(openTask|openDay|openNote)\\(/", $source), 'Enter-only row keydown');
	pg_assert_contains('pressKeys(row, openTask);', $source);
	pg_assert_contains('pressKeys(cell, function () { openDay(day); });', $source);
	pg_assert_contains('pressKeys(item, openThis);', $source);
}

// The day drawer's rows are the only way to a plan item from the calendar.
function test_workspace_keyboard_rows_calendar_drawer_rows()
{
	$source = workspace_keyboard_rows_source('assets/js/workspace.js');

	pg_assert_contains('pressKeys(row, openDayTask);', $source);
	pg_assert_contains('pressKeys(row, openPlanItem);', $source);
}

function test_workspace_keyboard_rows_my_time_rows()
{
	$source = workspace_keyboard_rows_source('assets/js/workspace_task_time.js');

	pg_assert_contains('h.pressKeys(running, openRunning);', $source);
	pg_assert_contains('h.pressKeys(row, openItem);', $source);
	pg_assert_false(strpos($source, "row.setAttribute('role', 'button');") !== false, 'role set without its keys');
}

// The Join shortcut sits inside the row's link: its keys must not reach it.
function test_workspace_keyboard_rows_inbox_join()
{
	$source = workspace_keyboard_rows_source('assets/js/workspace.js');

	pg_assert_contains('join.tabIndex = 0;', $source);
	pg_assert_contains("join.addEventListener('keydown', function (pressed) {", $source);
}

function test_workspace_keyboard_rows_board_hours_in_panel_language()
{
	$source = workspace_keyboard_rows_source('assets/js/workspace.js');

	pg_assert_contains("t('day_capacity', hoursText(row.capacity.day_minutes))", $source);
	pg_assert_contains("t('cell_title', hoursText(cell.minutes), hoursText(cell.capacity))", $source);
	pg_assert_contains('toLocaleString(CFG.locale || undefined, { maximumFractionDigits: 1 })', $source);
}
