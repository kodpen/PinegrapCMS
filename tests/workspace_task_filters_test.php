<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Workspace task filters: the department selects on My
 * Tasks, the planning board and the Work Calendar are named, the My Tasks
 * views and the board's week buttons carry aria-pressed, a department pick
 * moves the view to My departments, another view clears the department, the
 * task count is announced after each load; My Tasks' one-line bar keeps
 * every filter's meaning behind "Filter", and its empty screen.
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
function workspace_task_filters_body($name, $args = 'root')
{
	$source = workspace_task_filters_source();
	$start = strpos($source, '    function ' . $name . '(' . $args . ') {');

	pg_assert_true($start !== false, $name . ' found');

	$end = strpos($source, "\n    function ", $start + 10);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

function test_workspace_task_filters_department_selects_named()
{
	foreach (array('startBoard', 'startCalendar') as $name) {
		$body = workspace_task_filters_body($name);

		pg_assert_contains("var dept = select(departmentOptions(t('all_departments')), 0);\n        dept.classList.add('w-auto');\n        dept.setAttribute('aria-label', t('department'));", $body);
	}

	// My Tasks' select stands behind "Filter", named by its field's label
	// there (formParts.field()) and by aria-label when it stands in the bar.
	$body = workspace_task_filters_body('startTasks');

	pg_assert_contains("var dept = formParts.select(departmentOptions(t('all_departments')), '0', true);", $body);
	pg_assert_contains("{ label: t('department'), control: dept,", $body);

	$filter = workspace_task_filters_body('barFilter', 'options');

	pg_assert_contains("return field.row || formParts.field(field.label, field.control);", $filter);
	pg_assert_contains("field.control.setAttribute('aria-label', field.label);", $filter);
}

function test_workspace_task_filters_scope_buttons_pressed_and_in_step()
{
	$body = workspace_task_filters_body('startTasks');

	pg_assert_contains('function setScope(name) {', $body);
	pg_assert_contains("var views = barViews(t('tasks_views'), scopeList, state.scope, function (name) {", $body);
	pg_assert_contains('views.set(name);', $body);
	// A department pick shows My departments as the chosen scope.
	pg_assert_contains("setScope('department');", $body);
	// Any other scope drops the department the list would ignore.
	pg_assert_contains("if (name !== 'department') {\n                state.department_id = 0;\n                dept.value = '0';", $body);

	// The segment and the narrow window's select move together.
	$views = workspace_task_filters_body('barViews', 'label, items, value, onPick');

	pg_assert_contains("item.setAttribute('aria-pressed', (key === current) ? 'true' : 'false');", $views);
	pg_assert_contains('choose.value = current;', $views);
}

// WI5 keeps every filter's meaning: the same state keys and defaults go to
// ws_tasks, "Pressing, coming up" still sets priority hot and open tasks,
// agents=1 and urgent=1 in the address still work.
function test_workspace_task_filters_meaning_kept()
{
	$body = workspace_task_filters_body('startTasks');

	pg_assert_contains("var state = { scope: 'mine', status: 'open', search: '', department_id: 0, priority: '', soon: 0, agent_channels: (params().agents === '1') ? 'only' : '', made_by: '' };", $body);
	pg_assert_contains("state.soon = on ? 7 : 0;\n            state.priority = on ? 'hot' : '';\n            state.status = on ? 'open' : state.status;", $body);
	pg_assert_contains("if (query.urgent) {\n            setSoon(true);\n        }\n\n        load();", $body);
	pg_assert_contains("api('ws_tasks', state)", $body);
	// Clearing a field puts its default back.
	pg_assert_contains("clear: function () { state.status = 'open'; status.value = 'open'; }", $body);
	pg_assert_contains("clear: function () { state.department_id = 0; dept.value = '0'; }", $body);
	pg_assert_contains("clear: function () { setSoon(false); }", $body);
}

// The bar is drawn from look A: one fill, the quiet "Filter" with its
// count, the search field, no old button class.
function test_workspace_task_filters_bar_parts()
{
	$body = workspace_task_filters_body('startTasks');

	pg_assert_contains("el('nav', 'pg-toolbar ws-bar')", $body);
	pg_assert_contains("frame.appendChild(bar);", $body);
	pg_assert_same(1, substr_count($body, 'pg-button-fill'), 'one filled button');
	pg_assert_false(strpos($body, "'btn btn-") !== false, 'no old button class');
	pg_assert_contains("barSearch(t('search_tasks'),", $body);

	$filter = workspace_task_filters_body('barFilter', 'options');

	pg_assert_contains("lookButton('pg-button pg-button-quiet ws-bar-filter-button', '', 'bi-funnel')", $filter);
	pg_assert_contains("opener.appendChild(el('span', 'ws-bar-filter-label', t('bar_filter')));", $filter);
	pg_assert_contains("opener.setAttribute('aria-label', set.length ? t('bar_filter_count', set.length) : t('bar_filter'));", $filter);
	pg_assert_contains("remove.setAttribute('aria-label', remove.title);", $filter);
	pg_assert_contains("tags.hidden = hidden || !set.length || inline();", $filter);
	pg_assert_contains("return !!options.inline && (fields.length <= 3) && !barMatches(BAR_NARROW);", $filter);

	$search = workspace_task_filters_body('barSearch', 'placeholder, onSearch');

	pg_assert_contains("input.setAttribute('aria-label', placeholder);", $search);
	pg_assert_contains("icon('bi-search', '')", $search);
}

// An empty list: New task, or Clear when filters or the search narrowed it.
function test_workspace_task_filters_empty_screen()
{
	$body = workspace_task_filters_body('startTasks');

	pg_assert_contains("if (filter.isSet() || state.search) {\n                return barEmptyFound(t('tasks_found_none'),", $body);
	pg_assert_contains("title: t('tasks_none_title'), text: t('no_tasks'), action: { label: t('new_task')", $body);

	$empty = workspace_task_filters_body('emptyScreen', 'spec');

	pg_assert_contains("'pg-icon-tile ws-empty-screen-tile pg-tone-'", $empty);
	pg_assert_contains("'pg-title-section ws-empty-screen-title'", $empty);
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
