<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the line that adds a task above the groups of My Tasks' Mine
 * view (assets/js/workspace_my_tasks.js, addLine(); wired in startTasks()
 * of assets/js/workspace.js): it saves through the New task drawer's own
 * action, gives the task to the reader, opens the drawer on Tab, says a
 * refusal under the field in the server's words, and stands only where the
 * new task shows up.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a function of workspace_my_tasks.js, or ''.
function workspace_task_add_line_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_my_tasks.js');

	if (!preg_match('/\n    function ' . preg_quote($name, '/') . '\([^)]*\) \{\n(.*?)\n    \}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

// The body of startTasks() in workspace.js, or ''.
function workspace_task_add_line_test_start_tasks()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n    function startTasks\(root\) \{\n(.*?)\n    \}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_task_add_line_saves_through_the_new_task_action()
{
	$body = workspace_task_add_line_test_function('addLine');

	pg_assert_true($body !== '', 'addLine() is found');
	preg_match_all("/h\\.api\\('([a-z_]+)'/", $body, $calls);
	pg_assert_same(array('ws_task_save'), $calls[1], 'the one action called is the one New task saves with');
	pg_assert_contains("assignees: [h.boot().me.id]", $body, 'the task is given to the reader');
	pg_assert_contains('opts.onAdded(result.task_id)', $body, 'the list is loaded again with the new task');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_my_tasks.js');

	pg_assert_contains('window.PGWsMyTasks = { list: list, addLine: addLine };', $source);
}

function test_workspace_task_add_line_keys()
{
	$body = workspace_task_add_line_test_function('addLine');

	pg_assert_contains("event.key === 'Enter'", $body);
	pg_assert_contains('event.isComposing', $body, 'Enter while composing a word is left to the input method');
	pg_assert_contains("(event.key === 'Tab') && title && !event.shiftKey", $body, 'Tab opens the drawer only from a typed title; Shift+Tab moves on');
	pg_assert_contains('opts.openDrawer(title', $body);
	pg_assert_contains('if (result.needs_confirm) {', $body, 'a clash the server asks about goes to the drawer, which asks');
}

function test_workspace_task_add_line_says_a_refusal_in_the_server_words()
{
	$body = workspace_task_add_line_test_function('addLine');

	pg_assert_contains("refuse((error && error.message) ? error.message : h.t('error_generic'))", $body);
	pg_assert_contains("'pg-field-help pg-field-error'", $body, 'the error line of the Design examples');
	pg_assert_contains("input.setAttribute('aria-invalid', 'true')", $body);
	pg_assert_contains("note.setAttribute('aria-live', 'polite')", $body);
	pg_assert_true(strpos($body, 'h.fail(') === false, 'a refusal is not a toast');
}

function test_workspace_task_add_line_stands_above_the_list_in_mine_alone()
{
	$body = workspace_task_add_line_test_start_tasks();

	pg_assert_true($body !== '', 'startTasks() is found');
	pg_assert_contains('list.insertBefore(addLine.node, listBody);', $body, 'outside the list body, so a reload keeps the field');
	pg_assert_contains("addLine.shown((state.scope === 'mine') && (state.status === 'open') && !state.search && !filter.isSet() && (state.agent_channels !== 'only'));", $body);
	pg_assert_contains('taskDrawer.open(0, { title: title }', $body, 'Tab fills the New task drawer with the title');
	pg_assert_contains('drawMyTasks(data.tasks, fresh);', $body, 'the group holding a new task is drawn open');
}

function test_workspace_task_add_line_texts_are_given_to_the_screen()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('mt_add', 'mt_add_hint', 'mt_add_label') as $key) {
		pg_assert_true((bool) preg_match("/'" . $key . "'\\s+=> lang\\('/", $source), $key . ' is in ws_js_strings()');
	}
}
