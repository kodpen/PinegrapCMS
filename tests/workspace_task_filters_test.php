<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Workspace task filters: the department selects on My
 * Tasks, the planning board and the Work Calendar are named, the My Tasks
 * scope buttons and the board's week buttons carry aria-pressed beside their
 * fill, a department pick moves the fill to My departments, another scope
 * clears the department, and the task count is announced after each load.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_task_filters_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// The body of one start*() function, up to the next top-level function.
function workspace_task_filters_body($name)
{
	$source = workspace_task_filters_source();
	$start = strpos($source, '    function ' . $name . '(root) {');

	pg_assert_true($start !== false, $name . ' found');

	$end = strpos($source, "\n    function ", $start + 10);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

function test_workspace_task_filters_department_selects_named()
{
	foreach (array('startTasks', 'startBoard', 'startCalendar') as $name) {
		$body = workspace_task_filters_body($name);

		pg_assert_contains("var dept = select(departmentOptions(t('all_departments')), 0);\n        dept.classList.add('w-auto');\n        dept.setAttribute('aria-label', t('department'));", $body);
	}
}

function test_workspace_task_filters_scope_buttons_pressed_and_in_step()
{
	$body = workspace_task_filters_body('startTasks');

	pg_assert_contains('function setScope(name) {', $body);
	pg_assert_contains("child.setAttribute('aria-pressed', on ? 'true' : 'false');", $body);
	pg_assert_contains("item.setAttribute('aria-pressed', state.scope === scope[0] ? 'true' : 'false');", $body);
	// A department pick shows My departments as the chosen scope.
	pg_assert_contains("setScope('department');", $body);
	// Any other scope drops the department the list would ignore.
	pg_assert_contains("if (scope[0] !== 'department') {\n                    state.department_id = 0;\n                    dept.value = '0';", $body);
}

function test_workspace_task_filters_week_buttons_pressed()
{
	$body = workspace_task_filters_body('startBoard');

	pg_assert_contains("item.setAttribute('aria-pressed', state.days === option[0] ? 'true' : 'false');", $body);
	pg_assert_contains("child.setAttribute('aria-pressed', 'false');", $body);
	pg_assert_contains("item.setAttribute('aria-pressed', 'true');", $body);
}

function test_workspace_task_filters_count_announced()
{
	$body = workspace_task_filters_body('startTasks');

	pg_assert_contains("var countLine = el('div', 'visually-hidden');", $body);
	pg_assert_contains("countLine.setAttribute('role', 'status');", $body);
	pg_assert_contains("countLine.textContent = data.tasks.length ? t('task_count', data.tasks.length) : t('nothing_found');", $body);
}
