<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests: the grid of one Data table's screen
 * (includes/workspace/wtable_screen_grid.php): the width and alignment a
 * column's type gives its cells, the one-line cell with its whole text in
 * the title, the rows a long text takes in the row drawer, and the drawer
 * itself.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen_grid.php');

// Numbers, money, a plain date and a checkbox are narrow; text is wide;
// figures stand right; a date with its time takes a middle width.
function test_ws_wtable_grid_shape()
{
	pg_assert_same(array('width' => 'narrow', 'end' => true), ws_wtable_grid_shape('number'));
	pg_assert_same(array('width' => 'narrow', 'end' => true), ws_wtable_grid_shape('money'));
	pg_assert_same(array('width' => 'medium', 'end' => true), ws_wtable_grid_shape('formula'));
	pg_assert_same(array('width' => 'narrow', 'end' => false), ws_wtable_grid_shape('date'));
	pg_assert_same(array('width' => 'medium', 'end' => false), ws_wtable_grid_shape('date', array('time' => 1)));
	pg_assert_same(array('width' => 'narrow', 'end' => false), ws_wtable_grid_shape('checkbox'));
	pg_assert_same(array('width' => 'wide', 'end' => false), ws_wtable_grid_shape('text'));
	pg_assert_same(array('width' => 'wide', 'end' => false), ws_wtable_grid_shape('list'));

	foreach (array('status', 'person', 'row', 'link', '') as $type) {
		pg_assert_same(array('width' => 'medium', 'end' => false), ws_wtable_grid_shape($type), $type);
	}
}

// A cell is one line inside its width class; its whole text is the title,
// escaped; no title when there is no text.
function test_ws_wtable_grid_cell_html()
{
	pg_assert_same('<td class="ws-wt-c ws-wt-c-wide"><div class="ws-wt-cell" title="a &lt;b&gt;&#10;two">a &lt;b&gt; two</div></td>',
		ws_wtable_grid_cell_html(ws_wtable_grid_shape('text'), 'a &lt;b&gt; two', "a <b>\ntwo"));
	pg_assert_same('<td class="ws-wt-c ws-wt-c-narrow text-end"><div class="ws-wt-cell">12</div></td>',
		ws_wtable_grid_cell_html(ws_wtable_grid_shape('number'), '12', ''));
}

// The row drawer shows a long text whole: a line each, a long line
// wrapped at the drawer's width, two at least, a cap far above a note.
function test_ws_wtable_grid_text_rows()
{
	pg_assert_same(2, ws_wtable_grid_text_rows(''));
	pg_assert_same(2, ws_wtable_grid_text_rows('one'));
	pg_assert_same(3, ws_wtable_grid_text_rows("one\ntwo\r\nthree"));
	pg_assert_same(3, ws_wtable_grid_text_rows(str_repeat('a', 112) . 'b'));
	pg_assert_same(2, ws_wtable_grid_text_rows(str_repeat('ğ', 56)), 'letters, not bytes');
	pg_assert_same(30, ws_wtable_grid_text_rows(str_repeat("line\n", 80)));
}

// The row drawer: the post goes through the screen's form, Delete at the
// left of an existing row, Save or Add at the right; no Cancel.
function test_ws_wtable_grid_row_drawer()
{
	$new = ws_wtable_grid_row_drawer(array('action' => 'workspace_wtables.php', 'title' => 'New row', 'icon' => 'bi-plus-lg', 'form' => '<input name="x">', 'submit' => 'Add', 'delete' => ''));
	pg_assert_contains('<dialog class="drawer drawer-end ws-wt-drawer" id="ws-wt-row-form" aria-labelledby="ws-wt-row-form-title" data-ws-wt-new="1">', $new);
	pg_assert_contains('<form method="post" action="workspace_wtables.php" class="d-contents disable_shortcut">', $new);
	pg_assert_contains('<input name="x">', $new);
	pg_assert_contains('<button type="submit" class="pg-button pg-button-fill pg-button-sm">Add</button>', $new);
	pg_assert_false(strpos($new, 'pg-button-danger') !== false);
	pg_assert_false((bool) preg_match('/btn btn-|Cancel/', $new));

	$edit = ws_wtable_grid_row_drawer(array('action' => 'workspace_wtables.php', 'title' => 'Row 4', 'icon' => 'bi-pencil', 'form' => '', 'submit' => 'Save', 'delete' => '<button class="pg-button pg-button-danger">Delete</button>'));
	pg_assert_false(strpos($edit, 'data-ws-wt-new') !== false);
	pg_assert_true(strpos($edit, 'pg-button-danger') < strpos($edit, 'type="submit"'), 'Delete at the left');
}

// A text, number, money, date, checkbox or status cell is changed in the
// grid; the others, and a text of more than one line, open the drawer.
function test_ws_wtable_grid_cell_editor()
{
	pg_assert_same('text', ws_wtable_grid_cell_editor(array('type' => 'text'), 'one line'));
	pg_assert_same('text', ws_wtable_grid_cell_editor(array('type' => 'text'), null));
	pg_assert_same('', ws_wtable_grid_cell_editor(array('type' => 'text'), "two\nlines"), 'one line would lose the break');
	pg_assert_same('number', ws_wtable_grid_cell_editor(array('type' => 'number')));
	pg_assert_same('money', ws_wtable_grid_cell_editor(array('type' => 'money')));
	pg_assert_same('date', ws_wtable_grid_cell_editor(array('type' => 'date', 'options' => array())));
	pg_assert_same('datetime', ws_wtable_grid_cell_editor(array('type' => 'date', 'options' => array('time' => true))));
	pg_assert_same('checkbox', ws_wtable_grid_cell_editor(array('type' => 'checkbox')));
	pg_assert_same('status', ws_wtable_grid_cell_editor(array('type' => 'status')));

	foreach (array('formula', 'lookup', 'rollup', 'person', 'link', 'row', 'list', '') as $type) {
		pg_assert_same('', ws_wtable_grid_cell_editor(array('type' => $type)), $type);
	}
}

// The grid's change is the one cell, read as the row drawer reads its
// field, on the row's revision; a column the grid does not change has none.
function test_ws_wtable_grid_cell_change()
{
	pg_assert_same(array('cells' => array('7' => '2026-11-20 14:30'), 'revision' => 3),
		ws_wtable_grid_cell_change(7, array('type' => 'date', 'options' => array('time' => true)), '2026-11-20T14:30', '3'));
	pg_assert_same(array('cells' => array('7' => '2026-11-20 14:30'), 'revision' => 3),
		ws_wtable_grid_cell_change('7', array('type' => 'date', 'options' => array('time' => true)), '2026-11-20T14:30', 3));
	pg_assert_same(array('cells' => array('2' => true), 'revision' => 1), ws_wtable_grid_cell_change(2, array('type' => 'checkbox'), '1', 1));
	pg_assert_same(array('cells' => array('2' => false), 'revision' => 1), ws_wtable_grid_cell_change(2, array('type' => 'checkbox'), '0', 1));
	pg_assert_same(array('cells' => array('4' => null), 'revision' => 5), ws_wtable_grid_cell_change(4, array('type' => 'text'), '  ', 5), 'emptied');
	pg_assert_same(array('cells' => array('4' => 'ab'), 'revision' => 5), ws_wtable_grid_cell_change(4, array('type' => 'text'), ' ab ', 5));
	pg_assert_same(array('cells' => array('5' => 's2'), 'revision' => 2), ws_wtable_grid_cell_change(5, array('type' => 'status'), 's2', 2));
	pg_assert_same(array('cells' => array('6' => 'abc'), 'revision' => 2), ws_wtable_grid_cell_change(6, array('type' => 'number'), 'abc', 2), 'unread: the store refuses it');
	pg_assert_same(array('cells' => array('6' => null), 'revision' => 0), ws_wtable_grid_cell_change(6, array('type' => 'text'), array('x'), array()), 'no scalar');
	pg_assert_same(null, ws_wtable_grid_cell_change(8, array('type' => 'formula'), '1', 1));
	pg_assert_same(null, ws_wtable_grid_cell_change(8, array('type' => 'person'), '12', 1));
	pg_assert_same(null, ws_wtable_grid_cell_change(8, array('type' => 'list'), 'a', 1));

	// Money to minor units, rounded, as the drawer reads it.
	$money = array('type' => 'money', 'options' => array('decimals' => 2));
	pg_assert_same(ws_wtable_screen_cell_from_form($money, '12.345'), ws_wtable_grid_cell_change(9, $money, '12.345', 1)['cells']['9']);
}

// A refused cell is told in the store's words: its own code, else the
// refusal as a whole.
function test_ws_wtable_grid_cell_refusal_text()
{
	pg_assert_same(ws_wtable_screen_error_text('not_a_number'), ws_wtable_grid_cell_refusal_text(array('error' => 'cells_invalid', 'errors' => array('6' => 'not_a_number')), 6));
	pg_assert_same(ws_wtable_screen_error_text('changed_meanwhile'), ws_wtable_grid_cell_refusal_text(array('error' => 'changed_meanwhile'), 6));
	pg_assert_same(ws_wtable_screen_error_text('cells_invalid'), ws_wtable_grid_cell_refusal_text(array('error' => 'cells_invalid', 'errors' => array('9' => 'not_a_number')), 6), 'another cell');
}

// A writer's cells carry their column, and the cells the grid changes
// their field and opening value; a reader's carry nothing to edit.
function test_ws_wtable_grid_row_cells()
{
	$columns = array(
		'1' => array('type' => 'text', 'name' => 'Name', 'options' => array()),
		'2' => array('type' => 'status', 'name' => 'Stage', 'options' => array('choices' => array(array('key' => 's1', 'label' => 'Open', 'color' => '#123456')))),
		'3' => array('type' => 'formula', 'name' => 'Sum', 'options' => array()),
		'4' => array('type' => 'date', 'name' => 'Due', 'options' => array('time' => true)),
	);
	$shapes = array();

	foreach ($columns as $id => $column) {
		$shapes[$id] = ws_wtable_grid_shape($column['type'], $column['options']);
	}

	$row = array('cells' => array('1' => 'a "b"', '2' => 's1', '3' => null, '4' => '2026-11-20 14:30'), 'formula_errors' => array('3' => 'div_zero'));
	$cells = ws_wtable_grid_row_cells($columns, $shapes, $row, array(), true);

	pg_assert_same(array(1, 2, 3, 4), array_keys($cells));
	pg_assert_contains(' data-ws-wt-col="1" tabindex="-1" data-ws-wt-edit="text" data-ws-wt-value="a &quot;b&quot;">', $cells['1']);
	pg_assert_contains('data-ws-wt-edit="status" data-ws-wt-value="s1"', $cells['2']);
	pg_assert_contains('style="background: #123456; color: #fff;">Open</span>', $cells['2']);
	pg_assert_contains(' data-ws-wt-col="3" tabindex="-1">', $cells['3']);
	pg_assert_false(strpos($cells['3'], 'data-ws-wt-edit') !== false, 'a formula opens the drawer');
	pg_assert_contains('#DIV_ZERO', $cells['3']);
	pg_assert_contains('data-ws-wt-edit="datetime" data-ws-wt-value="2026-11-20T14:30"', $cells['4']);

	$read = ws_wtable_grid_row_cells($columns, $shapes, $row, array(), false);
	pg_assert_false(strpos(implode('', $read), 'data-ws-wt-') !== false, 'a reader changes nothing');
	pg_assert_same('<td class="ws-wt-c ws-wt-c-wide"><div class="ws-wt-cell" title="a &quot;b&quot;">a &quot;b&quot;</div></td>', $read['1']);
}

// The script's settings: the columns the grid changes, a money column's
// currency and a status column's choices, nothing of the others.
function test_ws_wtable_grid_script_config()
{
	$config = ws_wtable_grid_script_config('workspace_wtables.php', 4, 'tok', array(
		'1' => array('type' => 'money', 'name' => 'Price', 'options' => array('currency' => 'EUR')),
		'2' => array('type' => 'status', 'name' => 'Stage', 'options' => array('choices' => array(array('key' => 's1', 'label' => 'Open', 'color' => 'red')))),
		'3' => array('type' => 'person', 'name' => 'Owner', 'options' => array()),
	));

	pg_assert_same(4, $config['table']);
	pg_assert_same('tok', $config['token']);
	pg_assert_same(array(
		'1' => array('name' => 'Price', 'currency' => 'EUR'),
		'2' => array('name' => 'Stage', 'choices' => array(array('key' => 's1', 'label' => 'Open'))),
	), $config['columns']);
	pg_assert_same(array('none', 'saving', 'saved', 'error', 'keys'), array_keys($config['texts']));
}
