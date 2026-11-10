<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for row links of workflow tables
 * (includes/workspace/wtable_links.php): the row column type and its table,
 * a cell as a row id, the text a linked row shows (its key, else its first
 * text, else #N), the text for a reader who does not read the linked
 * table, the ws_refs tag a link leaves, conditions on a link, the delete
 * confirmation counting the links, the import reading a link back, and
 * where the store, the screen and the API use them.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_import.php');

// A column as the store reads it.
function ws_wtable_links_test_column($id, $type, $options = array())
{
	return array('id' => $id, 'name' => 'C' . $id, 'type' => $type, 'options' => ws_wtable_column_options_check($type, $options)['value']);
}

// A function's source without its comment lines.
function ws_wtable_links_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The row type names its table, a whole number from 1, and keeps nothing else.
function test_workspace_wtable_links_options()
{
	pg_assert_true(isset(ws_wtable_column_types()['row']));
	pg_assert_true(ws_wtable_type_stores('row'));
	pg_assert_same(array('table' => 7), ws_wtable_column_options_check('row', array('table' => '7'))['value']);

	foreach (array(null, 0, -3, 'x', 1.5, array(7)) as $table) {
		pg_assert_same('row_table_invalid', ws_wtable_column_options_check('row', array('table' => $table))['error']);
	}

	pg_assert_same('row_table_invalid', ws_wtable_column_options_check('row', array())['error']);
	pg_assert_same('key_type', ws_wtable_column_options_check('row', array('table' => 7, 'key' => true))['error'], 'a link is no key');
}

// The table is fixed with the type: a change answers type_fixed, options
// sent without it keep it.
function test_workspace_wtable_links_table_fixed()
{
	$old = array('type' => 'row', 'options' => array('table' => 7));

	pg_assert_same('type_fixed', ws_wtable_column_input_check(array('options' => array('table' => 8)), $old)['error']);
	pg_assert_same(array('table' => 7), ws_wtable_column_input_check(array('options' => array()), $old)['sets']['options']);
	pg_assert_same(array('table' => 7), ws_wtable_column_input_check(array('options' => array('table' => '7')), $old)['sets']['options']);
	pg_assert_same(7, ws_wtable_link_table($old));
	pg_assert_same(0, ws_wtable_link_table(array('type' => 'number', 'options' => array('table' => 7))));
}

// A cell keeps a row id; anything else is not_row.
function test_workspace_wtable_links_cell()
{
	$column = ws_wtable_links_test_column(3, 'row', array('table' => 7));

	pg_assert_same(12, ws_wtable_cell_check($column, 12)['value']);
	pg_assert_same(12, ws_wtable_cell_check($column, '12')['value']);
	pg_assert_same(null, ws_wtable_cell_check($column, '')['value']);

	foreach (array(0, -1, 'abc', 1.5, true, array('id' => 3)) as $value) {
		pg_assert_same('not_row', ws_wtable_cell_check($column, $value)['error']);
	}
}

// The text of a linked row: the key, else the first filled text, else #N.
function test_workspace_wtable_links_label()
{
	$plain = array('1' => ws_wtable_links_test_column(1, 'number'), '2' => ws_wtable_links_test_column(2, 'text'), '3' => ws_wtable_links_test_column(3, 'text'));
	pg_assert_same('Ayşe Kaya', ws_wtable_link_label($plain, array('number' => 4, 'cells' => array('1' => 5, '2' => '  ', '3' => " Ayşe\n Kaya "))));
	pg_assert_same('#4', ws_wtable_link_label($plain, array('number' => 4, 'position' => 2, 'cells' => array('1' => 5))));
	pg_assert_same('#2', ws_wtable_link_label($plain, array('number' => 0, 'position' => 2, 'cells' => array())), 'before the numbers, the place');

	$keyed = $plain + array('4' => ws_wtable_links_test_column(4, 'text', array('key' => true)));
	pg_assert_same('C-104', ws_wtable_link_label($keyed, array('number' => 4, 'cells' => array('3' => 'Ayşe', '4' => 'C-104'))));
	pg_assert_same('Ayşe', ws_wtable_link_label($keyed, array('number' => 4, 'cells' => array('3' => 'Ayşe'))), 'an empty key falls back');
}

// The ids rows link to, by linked table; the text a cell shows from them,
// and the text of a table the reader does not read.
function test_workspace_wtable_links_ids_and_text()
{
	$columns = array('1' => ws_wtable_links_test_column(1, 'text'), '2' => ws_wtable_links_test_column(2, 'row', array('table' => 7)),
		'3' => ws_wtable_links_test_column(3, 'row', array('table' => 9)));
	$rows = array(array('cells' => array('1' => 'x', '2' => 5, '3' => 6)), array('cells' => array('2' => 5)), array('cells' => array('2' => '8', '3' => null)));

	pg_assert_same(array(7 => array(5 => true, 8 => true), 9 => array(6 => true)), ws_wtable_link_ids($columns, $rows));

	$people = ws_wtable_people_with_links(array(3 => array('name' => 'Can')), array(5 => 'Ayşe', 6 => ws_wtable_link_unread_text()));
	pg_assert_same('Ayşe', ws_wtable_screen_cell_text($columns['2'], 5, $people));
	pg_assert_same('A row of a table you do not read', ws_wtable_screen_cell_text($columns['3'], 6, $people));
	pg_assert_same('', ws_wtable_screen_cell_text($columns['2'], 8, $people), 'a row that is gone shows nothing');
	pg_assert_same('Can', ws_wtable_screen_cell_text(ws_wtable_links_test_column(4, 'person'), 3, $people), 'people stay readable');
	pg_assert_same('Ayşe', ws_wtable_export_cell($columns['2'], 5, $people), 'the download writes the text');
}

// A link leaves a ws_refs tag of type wtable_row, so the rows that link
// to a row are found by index; a deleted column's cell leaves none.
function test_workspace_wtable_links_ref_tokens()
{
	$columns = array('2' => ws_wtable_links_test_column(2, 'row', array('table' => 7)), '3' => ws_wtable_links_test_column(3, 'link'));

	pg_assert_same(array(array('type' => 'wtable_row', 'id' => 5), array('type' => 'contact', 'id' => 4)),
		ws_wtable_row_ref_tokens($columns, array('2' => 5, '3' => array('kind' => 'contact', 'id' => 4), '9' => 6)));
	pg_assert_same(array(), ws_wtable_row_ref_tokens($columns, array('2' => 0)));
}

// Conditions compare a link by the linked row's id.
function test_workspace_wtable_links_conditions()
{
	$column = ws_wtable_links_test_column(2, 'row', array('table' => 7));

	pg_assert_same(array('eq', 'neq', 'empty', 'not_empty'), ws_wtable_where_type_operators('row'));
	pg_assert_same(5, ws_wtable_where_cell($column, 5));
	pg_assert_same(null, ws_wtable_where_cell($column, null));
	pg_assert_same(5, ws_wtable_where_value($column, ' 5 '));
	pg_assert_same(null, ws_wtable_where_value($column, 'Ayşe'));
	pg_assert_same('not_whole', ws_wtable_where_value_fault($column, 'eq', 'Ayşe'));
}

// The delete confirmation counts the rows that lose their link.
function test_workspace_wtable_links_delete_confirm()
{
	$none = ws_wtable_screen_delete_confirm('row', 'Customers', 30);

	pg_assert_same($none, ws_wtable_screen_delete_confirm('row', 'Customers', 30, 0));
	pg_assert_contains('One row links to it', ws_wtable_screen_delete_confirm('row', 'Customers', 30, 1));
	pg_assert_contains('3 rows link to it', ws_wtable_screen_delete_confirm('row', 'Customers', 30, 3));
	pg_assert_contains('3 rows link to it', ws_wtable_screen_delete_confirm('row', 'Customers', 0, 3));
}

// The import reads a link by the linked row's text, case aside, or by #N;
// what matches no row stays as written, for the store to refuse.
function test_workspace_wtable_links_import()
{
	$column = ws_wtable_links_test_column(2, 'row', array('table' => 7));
	$people = array(WS_WTABLE_LINKS_KEY => array('2' => array('#1' => 41, ws_fold('Ayşe') => 41, '#2' => 42, ws_fold('C-7') => 42)));

	pg_assert_same(41, ws_wtable_import_cell($column, 'AYŞE', $people));
	pg_assert_same(42, ws_wtable_import_cell($column, '#2', $people));
	pg_assert_same(42, ws_wtable_import_cell($column, '2', $people));
	pg_assert_same(42, ws_wtable_import_cell($column, 'c-7', $people));
	pg_assert_same('Nobody', ws_wtable_import_cell($column, 'Nobody', $people));
	pg_assert_same('not_row', ws_wtable_cell_check($column, ws_wtable_import_cell($column, 'Nobody', $people))['error']);
	pg_assert_false(in_array('row', ws_wtable_import_types(), true), 'a new column is never a link');
}

// A type change takes no row link, from it or to it: a row id has no form
// in another type.
function test_workspace_wtable_links_type_change_refused()
{
	$link = ws_wtable_links_test_column(2, 'row', array('table' => 7));
	$text = ws_wtable_links_test_column(3, 'text');

	pg_assert_false(in_array('row', ws_wtable_retype_types(), true));
	pg_assert_same('type_not_changeable', ws_wtable_retype_input_check($link, array('type' => 'text'))['error']);
	pg_assert_same('type_unknown', ws_wtable_retype_input_check($text, array('type' => 'row', 'options' => array('table' => 7)))['error']);
}

// The store checks a link on write, clears the links to a deleted row and
// the screen, the API and the import use them.
function test_workspace_wtable_links_wired()
{
	pg_assert_contains('ws_wtable_link_cells_refusal($viewer, $columns, $cells[\'cells\'])', ws_wtable_links_test_source('ws_wtable_row_create_check'));
	pg_assert_contains('ws_wtable_link_cells_refusal($viewer, $columns, $cells[\'cells\'], ', ws_wtable_links_test_source('ws_wtable_row_update_check'));
	pg_assert_contains('ws_wtable_link_column_refusal($viewer, $check[\'sets\'])', ws_wtable_links_test_source('ws_wtable_column_create_check'));
	pg_assert_contains('ws_wtable_links_clear($table_id, (int) $row[\'id\'], (int) $viewer[\'id\'])', ws_wtable_links_test_source('ws_wtable_row_delete'));
	pg_assert_contains("'links_cleared'", ws_wtable_links_test_source('ws_api_wtable_rows_delete'));
	pg_assert_contains('ws_wtable_link_import_rows($viewer, $columns)', ws_wtable_links_test_source('ws_wtable_import_people'));
	pg_assert_contains('ws_wtable_import_people($viewer, $columns)', ws_wtable_links_test_source('ws_wtable_import_rows'));

	$clear = ws_wtable_links_test_source('ws_wtable_links_clear');
	pg_assert_contains("AND revision = '", $clear, 'a link is cleared on the row\'s revision');
	pg_assert_contains("ws_ref_source_write('wtable_row'", $clear);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');
	pg_assert_contains('ws_wtable_link_counts($id, ', $screen);
	pg_assert_contains("(\$column['type'] === 'row') && !array_key_exists(\$id, \$typed)", $screen, 'a link the form did not offer is kept');
	pg_assert_contains('ws_wtable_link_choices($viewer, $column)', $screen);
	pg_assert_contains('ws_wtable_link_texts($viewer, $columns, ', $screen);
}
