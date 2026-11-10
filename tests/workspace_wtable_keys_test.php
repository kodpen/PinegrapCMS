<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the key column of a workflow table
 * (includes/workspace/wtable_keys.php): which columns may be the key, a
 * cell as keys compare it, the rows that stop a column from becoming the
 * key, a row's key refused when empty or taken, the refusal naming rows,
 * and where the store, the boxes, the API and the import use it.
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

// A column as the store reads it.
function ws_wtable_keys_test_column($id, $type, $options = array())
{
	return array('id' => $id, 'name' => 'C' . $id, 'type' => $type, 'options' => ws_wtable_column_options_check($type, $options)['value']);
}

// A function's source without its comment lines.
function ws_wtable_keys_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// A text or number column may be the key; other types are refused, and
// the flag is kept only when set.
function test_workspace_wtable_keys_options()
{
	pg_assert_same(array('key' => true), ws_wtable_column_options_check('text', array('key' => true))['value']);
	pg_assert_same(array('decimals' => 0, 'key' => true), ws_wtable_column_options_check('number', array('key' => '1'))['value']);
	pg_assert_same(array(), ws_wtable_column_options_check('text', array('key' => false))['value']);
	pg_assert_same('key_type', ws_wtable_column_options_check('money', array('key' => true))['error']);
	pg_assert_same('key_type', ws_wtable_column_options_check('status', array('key' => true, 'choices' => array(array('key' => 'a', 'label' => 'A'))))['error']);

	$columns = array('1' => ws_wtable_keys_test_column(1, 'text'), '2' => ws_wtable_keys_test_column(2, 'number', array('key' => true)));
	pg_assert_same(2, ws_wtable_key_column($columns)['id']);
	pg_assert_same(null, ws_wtable_key_column(array('1' => ws_wtable_keys_test_column(1, 'text'))));
	pg_assert_true(ws_wtable_is_key($columns['2']));
	pg_assert_false(ws_wtable_is_key(array('type' => 'money', 'options' => array('key' => true))), 'a stored flag on another type is no key');

	// Options sent again without the key keep it; key false takes it away.
	$old = array('type' => 'text', 'options' => array('key' => true));
	pg_assert_same(array('key' => true), ws_wtable_column_input_check(array('options' => array()), $old)['sets']['options']);
	pg_assert_same(array(), ws_wtable_column_input_check(array('options' => array('key' => false)), $old)['sets']['options']);
}

// A text compares without case and space around it; a number by value.
function test_workspace_wtable_keys_value()
{
	$text = ws_wtable_keys_test_column(1, 'text', array('key' => true));
	$number = ws_wtable_keys_test_column(2, 'number', array('key' => true));

	pg_assert_same(ws_wtable_key_value($text, ' Şeker '), ws_wtable_key_value($text, 'şEKER'));
	pg_assert_same(ws_wtable_key_value($text, 'IRMAK'), ws_wtable_key_value($text, 'ırmak'));
	pg_assert_same(null, ws_wtable_key_value($text, '  '));
	pg_assert_same(null, ws_wtable_key_value($text, null));
	pg_assert_same('12', ws_wtable_key_value($number, 12));
	pg_assert_same('12', ws_wtable_key_value($number, 12.0));
	pg_assert_same('12.5', ws_wtable_key_value($number, 12.5));
	pg_assert_same('0', ws_wtable_key_value($number, 0), 'nought is a key');
	pg_assert_same(null, ws_wtable_key_value($number, null));
}

// The rows that stop a column from being the key, by the numbers shown,
// and the refusal that names at most twenty of them.
function test_workspace_wtable_keys_faults()
{
	$column = ws_wtable_keys_test_column(3, 'text', array('key' => true));
	$rows = array(
		array('label' => 1, 'cells' => array('3' => 'A-1')),
		array('label' => 2, 'cells' => array('3' => '')),
		array('label' => 4, 'cells' => array('3' => 'a-1')),
		array('label' => 5, 'cells' => array()),
		array('label' => 6, 'cells' => array('3' => 'B')),
	);

	pg_assert_same(array('empty' => array(2, 5), 'repeated' => array(1, 4)), ws_wtable_key_faults($column, $rows));
	pg_assert_same(null, ws_wtable_key_refusal(ws_wtable_key_faults($column, array($rows[0], $rows[4]))));

	$refusal = ws_wtable_key_refusal(ws_wtable_key_faults($column, $rows));
	pg_assert_same('key_not_unique', $refusal['error']);
	pg_assert_same(409, $refusal['status']);
	pg_assert_same(array(1, 2, 4, 5), $refusal['rows']);
	pg_assert_same(4, $refusal['rows_count']);
	pg_assert_same(2, $refusal['empty']);

	$many = array();

	for ($i = 1; $i <= 30; $i++) {
		$many[] = array('label' => $i, 'cells' => array());
	}

	$refusal = ws_wtable_key_refusal(ws_wtable_key_faults($column, $many));
	pg_assert_same(WS_WTABLE_KEY_ROWS_SHOWN, count($refusal['rows']));
	pg_assert_same(30, $refusal['rows_count']);
	pg_assert_same('The column cannot be the key while rows are empty in it or repeat a value. Rows: #1, #2 and 1 more.',
		ws_wtable_screen_refusal_text(array('error' => 'key_not_unique', 'rows' => array(1, 2), 'rows_count' => 3)));
}

// A row's key: empty refused, taken by another row refused, its own value
// kept when the row is written again.
function test_workspace_wtable_keys_clash()
{
	$column = ws_wtable_keys_test_column(3, 'number', array('key' => true));
	$rows = array(array('id' => 10, 'cells' => array('3' => 7)), array('id' => 11, 'cells' => array('3' => 8)));

	pg_assert_same('key_empty', ws_wtable_key_clash($column, $rows, null));
	pg_assert_same('duplicate_key', ws_wtable_key_clash($column, $rows, 7));
	pg_assert_same('duplicate_key', ws_wtable_key_clash($column, $rows, 7.0, 11));
	pg_assert_same('', ws_wtable_key_clash($column, $rows, 7, 10), 'the row keeps its own key');
	pg_assert_same('', ws_wtable_key_clash($column, $rows, 9));
	pg_assert_same('', ws_wtable_key_clash($column, array(), 0));
}

// Where the key is checked and used: the row checks, the restore, the
// column checks, the lock around the writes, the boxes, the API and the
// import.
function test_workspace_wtable_keys_wiring()
{
	pg_assert_contains('ws_wtable_key_cells_refusal(', ws_wtable_keys_test_source('ws_wtable_row_create_check'));
	pg_assert_contains('ws_wtable_key_cells_refusal(', ws_wtable_keys_test_source('ws_wtable_row_update_check'));
	pg_assert_contains('ws_wtable_key_cells_refusal(', ws_wtable_keys_test_source('ws_wtable_row_restore_check'));
	pg_assert_contains('ws_wtable_column_key_check(', ws_wtable_keys_test_source('ws_wtable_column_create_check'));
	pg_assert_contains('ws_wtable_column_key_check(', ws_wtable_keys_test_source('ws_wtable_column_update_check'));
	pg_assert_contains("'key_taken'", ws_wtable_keys_test_source('ws_wtable_column_key_check'));

	foreach (array('ws_wtable_row_create', 'ws_wtable_row_update') as $writer) {
		$source = ws_wtable_keys_test_source($writer);
		pg_assert_contains('ws_wtable_key_lock(', $source, $writer . ' takes the lock');
		pg_assert_contains('ws_wtable_key_unlock(', $source, $writer . ' gives it back');
		pg_assert_contains('finally', $source, $writer . ' gives it back on every path');
	}

	pg_assert_contains("GET_LOCK('pg_wtable_key_", ws_wtable_keys_test_source('ws_wtable_key_lock'));
	pg_assert_contains('ws_wtable_row_keyed(', ws_wtable_keys_test_source('ws_step_row_find'));
	pg_assert_contains('ws_wtable_row_keyed(', ws_wtable_keys_test_source('ws_api_wtable_rows_list'));
	pg_assert_contains('ws_wtable_row_update(', ws_wtable_keys_test_source('ws_wtable_import_rows'), 'a file row with a held key changes that row');

	pg_assert_same('', ws_step_row_refusal('key', 'ORD-12'));
	pg_assert_same('The box names no key.', ws_step_row_refusal('key', ' '));
	pg_assert_same(array(), ws_step_row_problem('row_get', array('by' => 'key', 'row' => 'a b c'), 'Read'));
	pg_assert_same('The table “Orders” has no row with the key “ORD-12”.', ws_step_row_missing_text('key', 'ORD-12', 'Orders'));
	pg_assert_same('2 rows were imported and 3 rows were changed by their key.', ws_wtable_import_report_text(array('added' => 2, 'updated' => 3)));
	pg_assert_same('2 rows were imported.', ws_wtable_import_report_text(array('added' => 2)));
}
