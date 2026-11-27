<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for My Tasks' Board layout (ws_tasks_status_board(),
 * includes/workspace/channel_board.php): a column a status, which columns
 * stand for which status filter, the order of the cards, and the card a
 * task brief makes (ws_channel_board_task_card()).
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

function ws_my_tasks_board_test_brief($id, $status, $priority = 'normal', $due = null, $extra = array())
{
	return $extra + array(
		'id'           => $id,
		'number'       => 'T-' . $id,
		'title'        => 'Task ' . $id,
		'status'       => $status,
		'status_label' => $status,
		'priority'     => $priority,
		'open'         => ws_task_is_open($status),
		'due_date'     => $due,
		'due_label'    => (string) $due,
		'overdue'      => false,
		'assignees'    => array(),
		'progress'     => null,
		'notes_count'  => 0,
		'recurring'    => false,
		'can_edit'     => true,
	);
}

function ws_my_tasks_board_test_keys($board)
{
	return array_map(function ($column) { return $column['key']; }, $board['columns']);
}

function ws_my_tasks_board_test_ids($column)
{
	return array_map(function ($card) { return $card['id']; }, $column['items']);
}

function test_workspace_my_tasks_board_leaves_cancelled_out_of_the_open_tasks()
{
	$board = ws_tasks_status_board(array(), 'open', '2026-11-25');

	pg_assert_same('status', $board['group_by'], 'grouped by status');
	pg_assert_same(array('todo', 'doing', 'waiting', 'done'), ws_my_tasks_board_test_keys($board), 'open tasks: no Cancelled column');
	pg_assert_same(false, $board['can_post'], 'no New task in a column');
}

function test_workspace_my_tasks_board_shows_cancelled_with_closed_or_all_tasks()
{
	foreach (array('closed', 'all') as $status) {
		pg_assert_same(array('todo', 'doing', 'waiting', 'done', 'cancelled'), ws_my_tasks_board_test_keys(ws_tasks_status_board(array(), $status, '2026-11-25')), $status);
	}
}

function test_workspace_my_tasks_board_makes_every_column_a_drop_place_of_its_status()
{
	foreach (ws_tasks_status_board(array(), 'all', '2026-11-25')['columns'] as $column) {
		pg_assert_same($column['key'], $column['drop'], $column['key'] . ' drop');
		pg_assert_same(true, $column['dot'], $column['key'] . ' dot');
		pg_assert_same(null, $column['add'], $column['key'] . ' add');
		pg_assert_same(0, $column['more'], $column['key'] . ' more');
	}
}

function test_workspace_my_tasks_board_puts_each_task_in_its_status_column()
{
	$board = ws_tasks_status_board(array(
		ws_my_tasks_board_test_brief(1, 'todo'),
		ws_my_tasks_board_test_brief(2, 'doing'),
		ws_my_tasks_board_test_brief(3, 'waiting'),
		ws_my_tasks_board_test_brief(4, 'todo'),
		ws_my_tasks_board_test_brief(5, 'cancelled'),
	), 'open', '2026-11-25');
	$columns = array_combine(ws_my_tasks_board_test_keys($board), $board['columns']);

	pg_assert_same(array(4, 1), ws_my_tasks_board_test_ids($columns['todo']), 'to do, newer first');
	pg_assert_same(array(2), ws_my_tasks_board_test_ids($columns['doing']), 'in progress');
	pg_assert_same(array(3), ws_my_tasks_board_test_ids($columns['waiting']), 'waiting');
	pg_assert_same(array(), ws_my_tasks_board_test_ids($columns['done']), 'done empty');
	pg_assert_same(false, isset($columns['cancelled']), 'a cancelled task has no column with open tasks');
}

function test_workspace_my_tasks_board_orders_cards_by_priority_then_due_date()
{
	$board = ws_tasks_status_board(array(
		ws_my_tasks_board_test_brief(1, 'todo', 'normal', null),
		ws_my_tasks_board_test_brief(2, 'todo', 'normal', '2026-11-30'),
		ws_my_tasks_board_test_brief(3, 'todo', 'urgent', null),
		ws_my_tasks_board_test_brief(4, 'todo', 'normal', '2026-11-26'),
		ws_my_tasks_board_test_brief(5, 'todo', 'high', '2026-12-10'),
	), 'open', '2026-11-25');

	pg_assert_same(array(3, 5, 4, 2, 1), ws_my_tasks_board_test_ids($board['columns'][0]), 'urgent, high, then sooner due, no date last');
}

function test_workspace_my_tasks_board_card_reads_the_due_chip_on_the_day_given()
{
	$card = ws_tasks_status_board(array(ws_my_tasks_board_test_brief(7, 'todo', 'high', '2026-11-22')), 'open', '2026-11-25')['columns'][0]['items'][0];

	pg_assert_same('task', $card['kind'], 'a task card');
	pg_assert_same('T-7', $card['number'], 'number');
	pg_assert_same('late', $card['due_tone'], 'late on the reader\'s day');
	pg_assert_same(3, $card['due_late'], 'three days late');
	pg_assert_same(ws_task_priorities()['high'], $card['priority_label'], 'priority label');
	pg_assert_same(true, $card['can_edit'], 'can_edit kept');
	pg_assert_same(false, $card['reminder'], 'no reminder read here');
}

function test_workspace_my_tasks_board_card_keeps_a_task_the_reader_may_not_change_still()
{
	$card = ws_channel_board_task_card(ws_my_tasks_board_test_brief(8, 'doing', 'normal', null, array('can_edit' => false)), '2026-11-25', ws_task_priorities());

	pg_assert_same(false, $card['can_edit'], 'not movable');
	pg_assert_same('', $card['due_tone'], 'no due date, no tone');
}
