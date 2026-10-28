<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rows of workflow tables (includes/workspace/wtable_rows.php):
 * person and record-link cells kept closed, formula columns worked out on
 * read, a row as read, where a moved row lands, the revision a change names,
 * and the statements that keep row_count and the revision in step.
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

// A table's columns by id, each with its options checked.
function ws_wtable_rows_test_columns()
{
	$columns = array(
		'1' => array('Price', 'money', array('decimals' => 2)),
		'2' => array('Quantity', 'number', array()),
		'3' => array('Total', 'formula', array('expression' => 'Price * Quantity')),
		'4' => array('Owner', 'person', array()),
		'5' => array('Record', 'link', array()),
		'6' => array('Done', 'checkbox', array()),
		'7' => array('Note', 'text', array()),
	);

	$out = array();

	foreach ($columns as $id => $column) {
		$out[$id] = array('id' => (int) $id, 'name' => $column[0], 'type' => $column[1], 'options' => ws_wtable_column_options_check($column[1], $column[2])['value']);
	}

	return $out;
}

// The privacy export and erasure read person and record-link cells, so both
// are written like any other cell, and emptied too.
function test_workspace_wtable_rows_closed_cells()
{
	$columns = ws_wtable_rows_test_columns();

	pg_assert_same(array(), ws_wtable_row_closed_types(), 'no closed type');

	$check = ws_wtable_row_cells_input($columns, array('4' => 12, '1' => 500));
	pg_assert_true($check['ok'], 'a person is written');
	pg_assert_same(12, $check['cells']['4']);

	$check = ws_wtable_row_cells_input($columns, array('5' => array('kind' => 'contact', 'id' => 3)));
	pg_assert_true($check['ok'], 'a record link is written');
	pg_assert_same(array('kind' => 'contact', 'id' => 3), $check['cells']['5']);

	$check = ws_wtable_row_cells_input($columns, array('4' => null, '5' => '', '1' => 500));
	pg_assert_true($check['ok'], 'emptying is allowed');
	pg_assert_same(500, $check['cells']['1']);

	pg_assert_same(array('3' => 'computed'), ws_wtable_row_cells_input($columns, array('3' => 1))['errors'], 'the other checks still run');
	pg_assert_same(array('' => 'not_cells'), ws_wtable_row_cells_input($columns, 'x')['errors']);
}

// A formula names columns by name; money counts in whole units.
function test_workspace_wtable_rows_formula_values()
{
	$columns = ws_wtable_rows_test_columns();

	$out = ws_wtable_formula_values($columns, array('1' => 1250, '2' => 3));
	pg_assert_same(37.5, $out['values']['3']);
	pg_assert_same(array(), $out['errors']);

	$out = ws_wtable_formula_values($columns, array('1' => 1250));
	pg_assert_same(0.0, $out['values']['3'], 'an empty cell counts as nought');

	$columns['3']['options']['expression'] = 'Note + 1';
	$out = ws_wtable_formula_values($columns, array('7' => 'hello'));
	pg_assert_same(null, $out['values']['3']);
	pg_assert_same('value', $out['errors']['3'], 'text is no number');

	$columns['3']['options']['expression'] = 'Done * 2';
	pg_assert_same(2.0, ws_wtable_formula_values($columns, array('6' => true))['values']['3']);

	$columns['3']['options']['expression'] = 'Missing * 2';
	pg_assert_same('ref', ws_wtable_formula_values($columns, array())['errors']['3']);

	$columns['3']['options']['expression'] = 'A1 * 2';
	pg_assert_same('ref', ws_wtable_formula_values($columns, array())['errors']['3'], 'no sheet references in a row');

	$columns['3']['options']['expression'] = '0.1 + 0.2';
	pg_assert_same(0.3, ws_wtable_formula_values($columns, array())['values']['3']);
}

// One formula may use another; a loop is a cycle.
function test_workspace_wtable_rows_formula_chain_and_cycle()
{
	$columns = ws_wtable_rows_test_columns();
	$columns['8'] = array('id' => 8, 'name' => 'With tax', 'type' => 'formula', 'options' => array('expression' => 'Total * 1.2'));

	pg_assert_same(12.0, ws_wtable_formula_values($columns, array('1' => 500, '2' => 2))['values']['8']);

	$columns['3']['options']['expression'] = 'With tax + 1';
	$out = ws_wtable_formula_values($columns, array());
	pg_assert_same('cycle', $out['errors']['3']);
	pg_assert_same('cycle', $out['errors']['8']);
}

// A row as read: cells of deleted columns left out, formulas filled in.
function test_workspace_wtable_rows_row_out()
{
	$record = array('id' => '9', 'table_id' => '1', 'position' => '2', 'revision' => '4', 'cells' => '{"1":1000,"2":2,"99":"gone"}',
		'created_by' => '3', 'created_at' => '100', 'updated_by' => '5', 'updated_at' => '200');

	$row = ws_wtable_row_out($record, ws_wtable_rows_test_columns());

	pg_assert_same(9, $row['id']);
	pg_assert_same(4, $row['revision']);
	pg_assert_same(array('1' => 1000, '2' => 2, '3' => 20.0), $row['cells']);
	pg_assert_same(array(), $row['formula_errors']);
	pg_assert_same(5, $row['updated_by']);

	pg_assert_same(array(), ws_wtable_row_out(array('cells' => 'broken') + $record, array())['cells'], 'broken JSON reads as no cells');
}

// Where a moved row lands and which rows shift to make room.
function test_workspace_wtable_rows_move_plan()
{
	pg_assert_same(array('to' => 2, 'low' => 2, 'high' => 4, 'shift' => 1), ws_wtable_move_plan(5, 2, 6), 'up');
	pg_assert_same(array('to' => 5, 'low' => 3, 'high' => 5, 'shift' => -1), ws_wtable_move_plan(2, 5, 6), 'down');
	pg_assert_same(array('to' => 6, 'low' => 3, 'high' => 6, 'shift' => -1), ws_wtable_move_plan(2, 40, 6), 'past the end lands last');
	pg_assert_same(0, ws_wtable_move_plan(3, 3, 6)['shift']);
}

function test_workspace_wtable_rows_revision_given()
{
	pg_assert_same(4, ws_wtable_row_revision_given(array('revision' => '4')));
	pg_assert_same(0, ws_wtable_row_revision_given(array('revision' => 'x')));
	pg_assert_same(0, ws_wtable_row_revision_given(array('revision' => 0)));
	pg_assert_same(0, ws_wtable_row_revision_given(array()));
	pg_assert_same(0, ws_wtable_row_revision_given(null));
}

// The statements that must stay single: the limit checked by the count's own
// UPDATE, and a change written only on the revision it was made on.
function test_workspace_wtable_rows_guarded_writes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_rows.php');

	pg_assert_contains("row_count = row_count + 1", $source);
	pg_assert_contains("AND row_count < '\" . (int) ws_wtable_limits()['rows']", $source);
	pg_assert_contains("AND revision = '\" . \$revision . \"'\");", $source);
	pg_assert_same(1, substr_count((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php'), "require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_rows.php');"));
}
