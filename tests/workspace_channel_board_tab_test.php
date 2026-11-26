<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's one Board tab: the List view's person filter over
 * the channel's tasks (ws_tasks_for_person(), includes/workspace/tasks.php),
 * the same choice the board's "Filter" holds, and the channel screen's
 * sources that make Tasks and Board one tab while a room without the board
 * keeps its Tasks tab.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function workspace_channel_board_tab_tasks()
{
	return array(
		array('id' => 1, 'assignees' => array(array('id' => 4), array('id' => 9))),
		array('id' => 2, 'assignees' => array()),
		array('id' => 3, 'assignees' => array(array('id' => 9))),
		array('id' => 4),
	);
}

function workspace_channel_board_tab_ids($tasks)
{
	return array_map(function ($task) {
		return (int) $task['id'];
	}, $tasks);
}

function test_workspace_channel_board_tab_person_filter()
{
	$tasks = workspace_channel_board_tab_tasks();

	pg_assert_same(array(1, 2, 3, 4), workspace_channel_board_tab_ids(ws_tasks_for_person($tasks, 0)), 'no person: every task');
	pg_assert_same(array(1, 3), workspace_channel_board_tab_ids(ws_tasks_for_person($tasks, 9)), 'a person: the tasks they are on');
	pg_assert_same(array(1), workspace_channel_board_tab_ids(ws_tasks_for_person($tasks, 4)), 'one of two people on a task');
	pg_assert_same(array(2, 4), workspace_channel_board_tab_ids(ws_tasks_for_person($tasks, -1)), 'nobody: the tasks without a person');
	pg_assert_same(array(), workspace_channel_board_tab_ids(ws_tasks_for_person($tasks, 77)), 'a person on no task');
}

function workspace_channel_board_tab_js($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
}

function test_workspace_channel_board_tab_one_tab_where_the_board_is()
{
	$js = workspace_channel_board_tab_js('workspace.js');

	pg_assert_contains("var tabList = [['messages', t('tab_messages')]];", $js, 'the tab list starts with Messages');
	pg_assert_contains("tabList.push(['board', t('tab_board')]);", $js, 'the Board tab stands second where the board is');
	pg_assert_contains("tabList.push(['tasks', t('tab_tasks')]);", $js, 'a room without the board keeps its Tasks tab');
	pg_assert_contains("['board', counts.tasks, 'tab_tasks_count']", $js, 'the Board tab carries the open tasks\' count');
	pg_assert_contains("listInto: function (body, filters) { self.taskListInto(body, filters); }", $js, 'the List view is the Tasks pane\'s list');
}

function test_workspace_channel_board_tab_old_address_opens_the_list()
{
	$js = workspace_channel_board_tab_js('workspace.js');

	pg_assert_contains("if ((self.tab === 'tasks') && board) {", $js, 'the Tasks tab asked for opens the Board tab');
	pg_assert_contains("(query.view === 'tasks') || (query.view === 'list')", $js, 'the address of the Tasks tab and of the List view are read');
}

function test_workspace_channel_board_tab_shared_toolbar()
{
	$js = workspace_channel_board_tab_js('workspace_board_channel.js');

	pg_assert_contains("[['board', t('cb_view_board')], ['list', t('cb_view_list')]]", $js, 'the Segmented control switches Board and List');
	pg_assert_contains("VIEW_KEY = 'ws-board-view'", $js, 'the view chosen is remembered');
	pg_assert_contains("'pg-button pg-button-fill pg-button-sm ws-cb-new'", $js, 'one filled New task in the toolbar');
	pg_assert_contains('k.bar.filter(', $js, 'urgency, person and the cancelled ones are behind Filter');
	pg_assert_false(strpos($js, "'btn btn-sm ws-cb-chip") !== false, 'no btn btn-sm chip is drawn any more');
}

function test_workspace_channel_board_tab_nobody_column_takes_everybody_off()
{
	$js = workspace_channel_board_tab_js('workspace_board_channel.js');

	// "Drop here: nobody on it": a card with two people dropped from one
	// person's column on "Nobody yet" loses both, not only the column's one.
	pg_assert_contains("var people = (to.drop > 0) ? item.assignees.map(", $js, 'Nobody yet takes every person off the task');
}

function test_workspace_channel_board_tab_board_address_opens_the_board()
{
	$js = workspace_channel_board_tab_js('workspace.js');

	// ?view=board opens the Board view even when List is the view kept in
	// the browser, as ?view=list opens the List view.
	pg_assert_contains("self.boardView = (self.tab === 'board') ? 'board' : '';", $js, 'the board asked for in the address is the view opened');
}
