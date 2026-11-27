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
