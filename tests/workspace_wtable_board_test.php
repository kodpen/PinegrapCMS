<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the board view of workflow tables
 * (includes/workspace/wtable_board.php): which column a board groups by,
 * rows grouped by their status in the choices' order, "No status" for the
 * rest, the cards a column shows, and the row change a moved card writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_rows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_board.php');

// A table's columns by id, each with its options checked.
function ws_wtable_board_test_columns()
{
	$choices = array(
		array('key' => 'todo', 'label' => 'To do', 'color' => '#888888'),
		array('key' => 'doing', 'label' => 'Doing'),
		array('key' => 'done', 'label' => 'Done'),
	);
	$columns = array(
		'1' => array('Price', 'money', array('decimals' => 2, 'currency' => 'EUR')),
		'2' => array('Title', 'text', array()),
		'3' => array('Stage', 'status', array('choices' => $choices)),
		'4' => array('Note', 'text', array()),
		'5' => array('Priority', 'status', array('choices' => array(array('key' => 'high', 'label' => 'High')))),
	);
	$out = array();

	foreach ($columns as $id => $column) {
		$out[$id] = array('id' => (int) $id, 'name' => $column[0], 'type' => $column[1], 'options' => ws_wtable_column_options_check($column[1], $column[2])['value']);
	}

	return $out;
}

function ws_wtable_board_test_row($id, $position, $cells)
{
	return array('id' => $id, 'position' => $position, 'revision' => 3, 'cells' => $cells, 'formula_errors' => array());
}

function test_workspace_wtable_board_column_chosen()
{
	$columns = ws_wtable_board_test_columns();

	pg_assert_same(array(3 => 'Stage', 5 => 'Priority'), ws_wtable_board_status_columns($columns));
	pg_assert_same(5, ws_wtable_board_column_id($columns, '5'), 'the asked status column');
	pg_assert_same(3, ws_wtable_board_column_id($columns, '2'), 'a text column is no board: the first status column');
	pg_assert_same(3, ws_wtable_board_column_id($columns, array('x')), 'nonsense: the first');
	pg_assert_same(0, ws_wtable_board_column_id(array('2' => $columns['2']), 3), 'no status column');
}

function test_workspace_wtable_board_groups_by_status()
{
	$columns = ws_wtable_board_test_columns();
	$rows = array(
		ws_wtable_board_test_row(10, 1, array('2' => 'Call', '3' => 'doing', '1' => 1250)),
		ws_wtable_board_test_row(11, 2, array('3' => 'todo')),
		ws_wtable_board_test_row(12, 3, array('2' => 'Old', '3' => 'gone')),
		ws_wtable_board_test_row(13, 4, array('2' => 'Write', '3' => 'doing')),
	);

	$board = ws_wtable_board($columns, $rows, 3, array(), true);

	pg_assert_same(3, $board['column_id']);
	pg_assert_same(array('todo', 'doing', 'done', ''), array_column($board['columns'], 'drop'), 'the choices in order, then no status');
	pg_assert_same(array('To do', 'Doing', 'Done', 'No status'), array_column($board['columns'], 'title'));
	pg_assert_same('#888888', $board['columns'][0]['color']);
	pg_assert_same(array(10, 13), array_column($board['columns'][1]['items'], 'id'), 'in row order');
	pg_assert_same(array(12), array_column($board['columns'][3]['items'], 'id'), 'a choice taken away: no status');
	pg_assert_same(array(), $board['columns'][2]['items']);

	$card = $board['columns'][1]['items'][0];
	pg_assert_same('row', $card['kind']);
	pg_assert_same('Call', $card['title'], 'the first text cell');
	pg_assert_same('#1', $card['number']);
	pg_assert_same(3, $card['revision']);
	pg_assert_true($card['can_edit']);
	pg_assert_same(array(array('label' => 'Price', 'text' => '12.50 EUR')), $card['lines'], 'the other filled cells, not the status');
	pg_assert_same('Row 2', $board['columns'][0]['items'][0]['title'], 'no text cell');

	$board = ws_wtable_board($columns, array($rows[1]), 3);
	pg_assert_same(3, count($board['columns']), 'no row without a status: no such column');
	pg_assert_false($board['columns'][0]['items'][0]['can_edit']);
}

function test_workspace_wtable_board_more()
{
	$columns = ws_wtable_board_test_columns();
	$rows = array();

	for ($i = 1; $i <= WS_WTABLE_BOARD_CARDS + 5; $i++) {
		$rows[] = ws_wtable_board_test_row($i, $i, array('3' => 'done'));
	}

	$board = ws_wtable_board($columns, $rows, 3);
	pg_assert_same(WS_WTABLE_BOARD_CARDS, count($board['columns'][2]['items']));
	pg_assert_same(5, $board['columns'][2]['more']);

	$board = ws_wtable_board($columns, $rows, 3, array(), false, true);
	pg_assert_same(WS_WTABLE_BOARD_CARDS + 5, count($board['columns'][2]['items']), 'all asked for');
	pg_assert_same(0, $board['columns'][2]['more']);
}

function test_workspace_wtable_board_move_input()
{
	$columns = ws_wtable_board_test_columns();

	$input = ws_wtable_board_move_input(3, 'done', '4');
	pg_assert_same(array('cells' => array('3' => 'done'), 'revision' => 4), $input);

	$check = ws_wtable_row_cells_input($columns, $input['cells']);
	pg_assert_true($check['ok'], 'a choice of the column is written');

	$input = ws_wtable_board_move_input(3, '', 4);
	pg_assert_same(array('3' => null), $input['cells'], 'no status empties the cell');
	pg_assert_same(array('2' => 'Call'), ws_wtable_cells_merge($columns, array('2' => 'Call', '3' => 'doing'), $input['cells']), 'the other cells stay');

	pg_assert_same(0, ws_wtable_board_move_input(3, 'done', array())['revision'], 'no revision: refused by the store');
	pg_assert_false(ws_wtable_row_cells_input($columns, ws_wtable_board_move_input(3, 'nope', 4)['cells'])['ok'], 'not a choice: refused');
}
