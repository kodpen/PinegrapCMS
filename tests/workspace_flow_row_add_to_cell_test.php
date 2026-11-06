<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for adding to a cell in the change-row box of the workspace's flows
 * (includes/workspace/steps.php): a number or money cell written as "+3"
 * adds 3 to what the row holds when the box writes it, on the row's
 * revision, so two runs changing one row never lose an addition. Which
 * cells add, the check of the amount added, the sums laid over the row and
 * what the store is handed.
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
require_once(__DIR__ . '/helpers/source_reader.php');

// The columns of a stock table, as ws_wtable_columns() gives them.
function workspace_flow_row_add_to_cell_test_columns()
{
	return array(
		'3' => array('id' => 3, 'name' => 'Item', 'type' => 'text', 'options' => array()),
		'4' => array('id' => 4, 'name' => 'Value', 'type' => 'money', 'options' => array('decimals' => 2, 'currency' => '')),
		'8' => array('id' => 8, 'name' => 'Stock', 'type' => 'number', 'options' => array('decimals' => 0)),
		'9' => array('id' => 9, 'name' => 'Weight', 'type' => 'number', 'options' => array('decimals' => 2)),
	);
}

// Only a number or money cell whose value starts with "+" adds; a text
// cell keeps "+3" as its text, and a value that only fills in to "+3"
// does not add.
function test_workspace_flow_row_add_to_cell_which_cells()
{
	$columns = workspace_flow_row_add_to_cell_test_columns();

	pg_assert_same(array('4', '8'), ws_step_row_adds($columns, array('3' => '+3', '4' => '+1250', '8' => ' +{{node.answer.answer_number}}', '9' => '2')));
	pg_assert_same(array(), ws_step_row_adds($columns, array('8' => '{{node.answer.text}}', '9' => 5, '4' => -100)), 'a value or number alone sets');
	pg_assert_same(array(), ws_step_row_adds($columns, array('7' => '+3')), 'a column not in the table');
}

// The amount added is checked as the column checks a value: money in minor
// units, a number as a number, and it may be below zero.
function test_workspace_flow_row_add_to_cell_amount_checked()
{
	$columns = workspace_flow_row_add_to_cell_test_columns();

	pg_assert_same(array('ok' => true, 'value' => 3, 'error' => ''), ws_step_row_addend($columns['8'], '+3'));
	pg_assert_same(array('ok' => true, 'value' => -2, 'error' => ''), ws_step_row_addend($columns['8'], '+ -2'), 'a value below zero takes away');
	pg_assert_same(-250, ws_step_row_addend($columns['4'], '+-250')['value']);
	pg_assert_same('not_minor_units', ws_step_row_addend($columns['4'], '+12.50')['error']);
	pg_assert_same('not_number', ws_step_row_addend($columns['8'], '+many')['error']);
	pg_assert_same('not_number', ws_step_row_addend($columns['8'], '+')['error'], 'nothing to add');
}

// At publish a literal addition is checked by its amount; one read from
// the run waits until it is filled in; only the change-row box adds.
function test_workspace_flow_row_add_to_cell_refusal()
{
	$columns = workspace_flow_row_add_to_cell_test_columns();

	pg_assert_same('', ws_step_row_cells_refusal($columns, array('8' => '+3', '4' => '+1250', '9' => '+{{node.answer.answer_number}}'), 'row_set'));
	pg_assert_contains('“Stock” does not take that value', ws_step_row_cells_refusal($columns, array('8' => '+many'), 'row_set'));
	pg_assert_contains('minor units', ws_step_row_cells_refusal($columns, array('4' => '+12.50'), 'row_set'));
	pg_assert_contains('“Stock”', ws_step_row_cells_refusal($columns, array('8' => '+3'), 'row_add'), 'a new row has nothing to add to');
	pg_assert_same('', ws_step_row_cells_refusal($columns, array('3' => '+3'), 'row_add'), 'a text cell keeps its plus');
}

// The sums are laid over the cells the row holds now: an empty cell counts
// as 0, a number keeps the column's decimals, the other cells are written
// as given.
function test_workspace_flow_row_add_to_cell_sums()
{
	$columns = workspace_flow_row_add_to_cell_test_columns();
	$added = ws_step_row_cells_added($columns, array('3' => 'Flour', '8' => '+3', '4' => '+-250', '9' => '+0.25'), array('8' => 10, '4' => 1000, '9' => 1.5), array('8', '4', '9'));

	pg_assert_same(array('ok' => true, 'cells' => array('3' => 'Flour', '8' => 13, '4' => 750, '9' => 1.75), 'column' => '', 'error' => ''), $added);
	pg_assert_same(7, ws_step_row_cells_added($columns, array('8' => '+7'), array(), array('8'))['cells']['8'], 'an empty cell counts as 0');
	pg_assert_same('+3', ws_step_row_cells_added($columns, array('8' => '+3'), array('8' => 10), array())['cells']['8'], 'a cell not marked as adding is handed on as it is');

	$bad = ws_step_row_cells_added($columns, array('8' => '+x'), array('8' => 10), array('8'));

	pg_assert_same(array(false, '8', 'not_number'), array($bad['ok'], $bad['column'], $bad['error']), 'a value filled in that is no number');
}

// The check marks the cells that add from the settings as written, the run
// adds them to the row it reads on each try and hands the sums to the store
// on that row's revision.
function test_workspace_flow_row_add_to_cell_runs_on_the_revision()
{
	$check = pg_test_function_source('ws_step_row_check');

	pg_assert_contains("'adds'", $check, 'the cells that add are known before the run fills them');
	pg_assert_contains('ws_step_row_cells_refusal($columns, $cells, $kind)', $check);

	$do = pg_test_function_source('ws_step_row_do');

	pg_assert_contains('ws_step_row_cells_added($columns, $cells, ws_wtable_cells_decode($row[\'cells\']), ', $do, 'added to the row read on this try');
	pg_assert_contains("ws_wtable_row_update(\$owner, \$table, \$row, array('cells' => \$added['cells'], 'revision' => (int) \$row['revision']))", $do, 'written on that revision');
	pg_assert_contains('WS_STEP_ROW_TRIES', $do, 'read again while somebody else wrote in between');
}
