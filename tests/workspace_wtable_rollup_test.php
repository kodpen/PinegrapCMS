<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for rollup columns of workflow tables
 * (includes/workspace/wtable_rollup.php): the rollup type and its options,
 * a count, sum, average, smallest or largest value worked out on read over
 * the rows of another table that link to the row, with conditions, never
 * stored, empty for a table the reader does not read, money kept in minor
 * units, two links from one table kept apart, the text it shows, and
 * where the screen, the API, the flows and the canvas use or refuse it.
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
function ws_wtable_rollup_test_column($id, $type, $options = array(), $name = '')
{
	return array('id' => $id, 'name' => ($name !== '') ? $name : 'C' . $id, 'type' => $type, 'options' => ws_wtable_column_options_check($type, $options)['value']);
}

// Orders (table 20): 21 Customer and 25 Seller (row links to table 9),
// 22 Amount (money), 23 Status, 24 Quantity, 26 a formula.
function ws_wtable_rollup_test_orders()
{
	return array(
		'21' => ws_wtable_rollup_test_column(21, 'row', array('table' => 9), 'Customer'),
		'22' => ws_wtable_rollup_test_column(22, 'money', array('currency' => 'EUR'), 'Amount'),
		'23' => ws_wtable_rollup_test_column(23, 'status', array('choices' => array(array('key' => 'paid', 'label' => 'Paid'), array('key' => 'open', 'label' => 'Open'))), 'Status'),
		'24' => ws_wtable_rollup_test_column(24, 'number', array(), 'Quantity'),
		'25' => ws_wtable_rollup_test_column(25, 'row', array('table' => 9), 'Seller'),
		'26' => ws_wtable_rollup_test_column(26, 'formula', array('expression' => 'Quantity * 2'), 'Double'),
	);
}

// The orders' rows, formulas worked out.
function ws_wtable_rollup_test_order_rows()
{
	return array(
		array('id' => 1, 'cells' => array('21' => 40, '22' => 1000, '23' => 'paid', '24' => 2.0, '26' => 4.0)),
		array('id' => 2, 'cells' => array('21' => 40, '22' => 251, '23' => 'open', '24' => 1.0, '26' => 2.0, '25' => 41)),
		array('id' => 3, 'cells' => array('21' => 40, '23' => 'paid', '25' => 41)),
		array('id' => 4, 'cells' => array('21' => 41, '22' => 500, '23' => 'paid', '24' => 5.0, '26' => 10.0)),
	);
}

// Customers (table 9): 1 Name, then rollups over the orders.
function ws_wtable_rollup_test_customers()
{
	$rollup = function ($id, $op, $column, $name, $more = array()) {
		return ws_wtable_rollup_test_column($id, 'rollup', array('table' => 20, 'link' => 21, 'op' => $op, 'column' => $column) + $more, $name);
	};

	return array(
		'1' => ws_wtable_rollup_test_column(1, 'text', array(), 'Name'),
		'2' => $rollup(2, 'count', 0, 'Orders'),
		'3' => $rollup(3, 'sum', 22, 'Spent'),
		'4' => $rollup(4, 'avg', 22, 'Average order'),
		'5' => $rollup(5, 'min', 22, 'Smallest order'),
		'6' => $rollup(6, 'count', 0, 'Paid orders', array('where' => array(array('column' => 23, 'op' => 'eq', 'value' => 'paid')))),
		'7' => $rollup(7, 'max', 26, 'Largest double'),
		'8' => ws_wtable_rollup_test_column(8, 'rollup', array('table' => 20, 'link' => 25, 'op' => 'count'), 'Sold'),
	);
}

// The type names the other table, its link, an operation, a column (none
// for a count) and conditions, and keeps no cell.
function test_workspace_wtable_rollup_options()
{
	pg_assert_true(isset(ws_wtable_column_types()['rollup']));
	pg_assert_false(ws_wtable_type_stores('rollup'));
	pg_assert_same(array('table' => 20, 'link' => 21, 'op' => 'sum', 'column' => 22, 'where' => array(), 'match' => 'all'),
		ws_wtable_column_options_check('rollup', array('table' => '20', 'link' => 21, 'op' => 'sum', 'column' => 22, 'other' => 1))['value']);
	pg_assert_same(0, ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => 21, 'op' => 'count', 'column' => 22))['value']['column'], 'a count counts rows');
	pg_assert_same('any', ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => 21, 'op' => 'count', 'where' => '[{"column":23,"op":"eq","value":"paid"}]', 'match' => 'any'))['value']['match']);

	foreach (array(null, 0, -1, 'x', 1.5) as $bad) {
		pg_assert_same('rollup_table_invalid', ws_wtable_column_options_check('rollup', array('table' => $bad, 'link' => 21, 'op' => 'count'))['error']);
		pg_assert_same('rollup_link_invalid', ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => $bad, 'op' => 'count'))['error']);
		pg_assert_same('rollup_column_invalid', ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => 21, 'op' => 'sum', 'column' => $bad))['error']);
	}

	pg_assert_same('rollup_op_invalid', ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => 21, 'op' => 'median', 'column' => 22))['error']);
	pg_assert_same('rollup_where_invalid', ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => 21, 'op' => 'count', 'where' => '{not json'))['error']);
	pg_assert_same('rollup_where_invalid', ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => 21, 'op' => 'count', 'where' => array_fill(0, WS_WTABLE_WHERE_MAX + 1, array('column' => 23, 'op' => 'empty'))))['error']);
	pg_assert_same('key_type', ws_wtable_column_options_check('rollup', array('table' => 20, 'link' => 21, 'op' => 'count', 'key' => true))['error']);
	pg_assert_same('computed', ws_wtable_cell_check(ws_wtable_rollup_test_customers()['3'], 5)['error']);
	pg_assert_same(array('1' => 'Ann'), ws_wtable_cells_merge(ws_wtable_rollup_test_customers(), array('1' => 'Ann'), array('3' => 5)), 'never stored');
}

// The values over the rows that link to each row: a count, money kept in
// minor units with its average rounded, a formula's largest value, a
// count under conditions, and a second link of the same table apart.
function test_workspace_wtable_rollup_values()
{
	$rows = array(array('id' => 40, 'cells' => array('1' => 'Ann', '3' => 'sent by mistake')), array('id' => 41, 'cells' => array('1' => 'Bob')), array('id' => 42, 'cells' => array('1' => 'Cem')));
	$others = array(20 => array('columns' => ws_wtable_rollup_test_orders(), 'rows' => ws_wtable_rollup_test_order_rows()));
	$out = ws_wtable_rollup_values(ws_wtable_rollup_test_customers(), $rows, $others);

	pg_assert_same(array('1' => 'Ann', '2' => 3, '3' => 1251, '4' => 626, '5' => 251, '6' => 2, '7' => 4.0, '8' => 0), $out[0]['cells'], 'Ann: three orders, 1250.5 rounds to 1251 / 2 = 626');
	pg_assert_same(array('1' => 'Bob', '2' => 1, '3' => 500, '4' => 500, '5' => 500, '6' => 1, '7' => 10.0, '8' => 2), $out[1]['cells'], 'Bob sold two orders');
	pg_assert_same(array('1' => 'Cem', '2' => 0, '6' => 0, '8' => 0), $out[2]['cells'], 'no order: counts 0, the rest empty');
	pg_assert_true(is_int($out[0]['cells']['3']) && is_int($out[0]['cells']['4']), 'money stays whole minor units');
}

// A table the reader does not read is missing from $others: every rollup
// over it stays empty, a count too.
function test_workspace_wtable_rollup_unread_table()
{
	$rows = array(array('id' => 40, 'cells' => array('1' => 'Ann', '2' => 9)));

	pg_assert_same(array('1' => 'Ann'), ws_wtable_rollup_values(ws_wtable_rollup_test_customers(), $rows, array())[0]['cells']);
}

// A link column or a totalled column gone leaves the rollup empty.
function test_workspace_wtable_rollup_gone_columns()
{
	$rows = array(array('id' => 40, 'cells' => array()));
	$orders = ws_wtable_rollup_test_orders();
	unset($orders['22']);
	$out = ws_wtable_rollup_values(ws_wtable_rollup_test_customers(), $rows, array(20 => array('columns' => $orders, 'rows' => ws_wtable_rollup_test_order_rows())));

	pg_assert_false(isset($out[0]['cells']['3']), 'the amount is gone');
	pg_assert_same(3, $out[0]['cells']['2']);

	unset($orders['21']);
	$out = ws_wtable_rollup_values(ws_wtable_rollup_test_customers(), $rows, array(20 => array('columns' => $orders, 'rows' => ws_wtable_rollup_test_order_rows())));
	pg_assert_false(isset($out[0]['cells']['2']), 'the link is gone');
	pg_assert_same(0, $out[0]['cells']['8'], 'the other link still counts');
}

// A count and a number total show as numbers, a money total as money.
function test_workspace_wtable_rollup_text()
{
	$columns = ws_wtable_rollup_test_customers();
	$orders = ws_wtable_rollup_test_orders();
	$rollups = ws_wtable_rollups($columns);
	$people = ws_wtable_people_with_lookups(array(), array(
		'2' => ws_wtable_rollup_display($rollups['2'], null),
		'3' => ws_wtable_rollup_display($rollups['3'], $orders['22']),
		'7' => ws_wtable_rollup_display($rollups['7'], $orders['26']),
	));

	pg_assert_same('3', ws_wtable_screen_cell_text($columns['2'], 3, $people));
	pg_assert_true(strpos(ws_wtable_screen_cell_text($columns['3'], 1251, $people), 'EUR') !== false);
	pg_assert_same(12.51, ws_wtable_export_cell($columns['3'], 1251, $people));
	pg_assert_same('4', ws_wtable_screen_cell_text($columns['7'], 4.0, $people));
	pg_assert_same('', ws_wtable_screen_cell_text($columns['4'], 626, $people), 'no display to the reader, no text');
	pg_assert_same(null, ws_wtable_rollup_display($rollups['3'], $orders['23']), 'a status is no total');
}

// The columns card names the operation over the other table.
function test_workspace_wtable_rollup_label()
{
	$columns = ws_wtable_rollup_test_customers();
	$orders = ws_wtable_rollup_test_orders();

	pg_assert_same(lang(array('string' => 'Sum of {var:1}', 'vars' => 'Orders (Customer) › Amount')), ws_wtable_rollup_label($columns['3'], 'Orders', $orders));
	pg_assert_same(lang(array('string' => 'Count of {var:1}', 'vars' => 'Orders (Customer)')) . ' · ' . lang('with conditions'), ws_wtable_rollup_label($columns['6'], 'Orders', $orders));
	pg_assert_same(lang('A table you do not read'), ws_wtable_rollup_label($columns['3'], '', null));
}

// A formula, a condition, the totals line and a sort do not read a rollup;
// change type refuses it both ways.
function test_workspace_wtable_rollup_not_read()
{
	$columns = ws_wtable_rollup_test_customers();
	$columns['9'] = ws_wtable_rollup_test_column(9, 'formula', array('expression' => 'Spent + 1'), 'Plus');

	pg_assert_same('ref', ws_wtable_formula_operand($columns['3'], 5));
	pg_assert_same(array('9' => 'ref'), ws_wtable_formula_values($columns, array())['errors']);
	pg_assert_same(array(), ws_wtable_where_type_operators('rollup'));
	pg_assert_false(ws_wtable_total_type('rollup'), 'a rollup does not total a rollup');
	pg_assert_true(ws_wtable_joined_type('rollup') && ws_wtable_joined_type('lookup') && !ws_wtable_joined_type('formula'));
	pg_assert_true(ws_wtable_query_refusal($columns, ws_wtable_query('', 'all', 3, 'asc')) !== '');
	pg_assert_true(ws_step_where_refusal($columns, array('where' => '', 'sort' => 3), false) !== '');
	pg_assert_same('type_not_changeable', ws_wtable_retype_input_check($columns['3'], array('type' => 'number'))['error']);
	pg_assert_same('type_unknown', ws_wtable_retype_input_check(ws_wtable_rollup_test_column(1, 'number'), array('type' => 'rollup'))['error']);
}

// Read a row and Read rows give it as a number; the row start box does not.
function test_workspace_wtable_rollup_flow_outputs()
{
	$columns = array_values(ws_wtable_rollup_test_customers());
	$read = ws_flow_row_outputs($columns, true);

	pg_assert_false(in_array('cell_3', array_column(ws_flow_row_outputs($columns), 'key'), true));
	pg_assert_same('number', $read[array_search('cell_3', array_column($read, 'key'), true)]['type']);
	pg_assert_same('1251', ws_step_row_get_data($columns, array('3' => 1251), 40)['cell_3']);
	pg_assert_same('0', ws_step_row_get_data($columns, array('2' => 0), 40)['cell_2'], 'a count of nought is 0');
}

// Every reader fills the rollups beside the lookups; the store checks a
// new one; the canvas leaves joined columns out of writes, conditions and
// sorts.
function test_workspace_wtable_rollup_wired()
{
	pg_assert_true(strpos(pg_test_function_source('ws_wtable_joined_fill'), 'ws_wtable_rollup_fill') !== false);
	pg_assert_true(strpos(pg_test_function_source('ws_wtable_joined_targets'), 'ws_wtable_rollup_targets') !== false);
	pg_assert_true(strpos(pg_test_function_source('ws_wtable_column_create_check'), 'ws_wtable_rollup_column_refusal') !== false);
	pg_assert_true(strpos(pg_test_function_source('ws_wtable_column_update_check'), 'ws_wtable_rollup_column_refusal') !== false);

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	pg_assert_true(strpos($js, "['formula', 'lookup', 'rollup'].indexOf(column.type) === -1") !== false, 'Add/Change a row leaves them out');
	pg_assert_same(3, substr_count($js, 'rowQueryColumns('), 'defined, conditions and sort');

	$hints = pg_test_function_source('ws_flow_ai_catalogue_hints');
	pg_assert_true(substr_count($hints, 'rollup column') >= 2, 'row_get and rows_get say a rollup is read');
}
