<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for lookup columns of workflow tables
 * (includes/workspace/wtable_lookup.php): the lookup type and its options,
 * a value worked out on read from the linked row and never stored, empty
 * for a table the reader does not read and for a row or column that is
 * gone, written by the linked column's type, refused as a formula operand,
 * a condition and a sort, given by the row boxes but not the row start
 * box, and where the screen, the API and the flows fill it.
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
function ws_wtable_lookup_test_column($id, $type, $options = array(), $name = '')
{
	return array('id' => $id, 'name' => ($name !== '') ? $name : 'C' . $id, 'type' => $type, 'options' => ws_wtable_column_options_check($type, $options)['value']);
}

// Orders: 1 Item (text), 2 Customer (row link to table 9), 3 Phone, 4 Credit,
// 5 Total, 6 VIP (lookups of the customer's columns 11, 12, 13, 14).
function ws_wtable_lookup_test_orders()
{
	return array(
		'1' => ws_wtable_lookup_test_column(1, 'text', array(), 'Item'),
		'2' => ws_wtable_lookup_test_column(2, 'row', array('table' => 9), 'Customer'),
		'3' => ws_wtable_lookup_test_column(3, 'lookup', array('link' => 2, 'column' => 11), 'Phone'),
		'4' => ws_wtable_lookup_test_column(4, 'lookup', array('link' => 2, 'column' => 12), 'Credit'),
		'5' => ws_wtable_lookup_test_column(5, 'lookup', array('link' => 2, 'column' => 13), 'Double credit'),
		'6' => ws_wtable_lookup_test_column(6, 'lookup', array('link' => 2, 'column' => 14), 'VIP'),
	);
}

// Customers (table 9): 11 Phone, 12 Credit (money), 13 a formula, 14 VIP.
function ws_wtable_lookup_test_customers()
{
	return array(
		'11' => ws_wtable_lookup_test_column(11, 'text', array(), 'Phone'),
		'12' => ws_wtable_lookup_test_column(12, 'money', array('currency' => 'EUR'), 'Credit'),
		'13' => ws_wtable_lookup_test_column(13, 'formula', array('expression' => 'Credit * 2'), 'Double credit'),
		'14' => ws_wtable_lookup_test_column(14, 'checkbox', array(), 'VIP'),
	);
}

// The type names a row link column and a column of the linked table, whole
// numbers from 1, and keeps no cell.
function test_workspace_wtable_lookup_options()
{
	pg_assert_true(isset(ws_wtable_column_types()['lookup']));
	pg_assert_false(ws_wtable_type_stores('lookup'), 'a lookup keeps no cell');
	pg_assert_false(ws_wtable_type_stores('formula'));
	pg_assert_true(ws_wtable_type_stores('row'));
	pg_assert_same(array('link' => 2, 'column' => 11), ws_wtable_column_options_check('lookup', array('link' => '2', 'column' => 11, 'other' => 1))['value']);

	foreach (array(null, 0, -1, 'x', 1.5, array(2)) as $bad) {
		pg_assert_same('lookup_link_invalid', ws_wtable_column_options_check('lookup', array('link' => $bad, 'column' => 11))['error']);
		pg_assert_same('lookup_column_invalid', ws_wtable_column_options_check('lookup', array('link' => 2, 'column' => $bad))['error']);
	}

	pg_assert_same('key_type', ws_wtable_column_options_check('lookup', array('link' => 2, 'column' => 11, 'key' => true))['error'], 'a lookup is no key');
	pg_assert_same('computed', ws_wtable_cell_check(ws_wtable_lookup_test_orders()['3'], '555')['error'], 'a write is refused as a formula\'s is');
	pg_assert_same(array(2, 11), ws_wtable_lookup_parts(ws_wtable_lookup_test_orders()['3']));
	pg_assert_same(array(0, 0), ws_wtable_lookup_parts(ws_wtable_lookup_test_orders()['2']));
}

// A lookup shows any column but a row link and another lookup: one step.
function test_workspace_wtable_lookup_shows()
{
	foreach (array('text', 'number', 'money', 'date', 'person', 'status', 'checkbox', 'link', 'list', 'formula') as $type) {
		pg_assert_true(ws_wtable_lookup_shows($type), $type);
	}

	pg_assert_false(ws_wtable_lookup_shows('row'));
	pg_assert_false(ws_wtable_lookup_shows('lookup'));
	pg_assert_false(ws_wtable_lookup_shows('nothing'));
}

// The store never keeps a lookup cell, even one sent by mistake.
function test_workspace_wtable_lookup_not_stored()
{
	$columns = ws_wtable_lookup_test_orders();

	pg_assert_same(array('1' => 'Cake', '2' => 40), ws_wtable_cells_merge($columns, array('1' => 'Cake', '2' => 40), array('3' => '555')));
}

// Values come from the linked row, as its column keeps them: money in
// minor units, a formula worked out, an unticked box empty.
function test_workspace_wtable_lookup_values()
{
	$columns = ws_wtable_lookup_test_orders();
	$rows = array(
		array('id' => 1, 'cells' => array('1' => 'Cake', '2' => 40)),
		array('id' => 2, 'cells' => array('1' => 'Bread', '2' => 41)),
		array('id' => 3, 'cells' => array('1' => 'Pie')),
		array('id' => 4, 'cells' => array('1' => 'Tart', '2' => 99)),
	);
	$linked = array(9 => array('columns' => ws_wtable_lookup_test_customers(), 'cells' => array(
		40 => array('11' => '555 01', '12' => 1250, '13' => 25.0, '14' => true),
		41 => array('11' => '555 02', '14' => false),
	)));

	$out = ws_wtable_lookup_values($columns, $rows, $linked);

	pg_assert_same(array('1' => 'Cake', '2' => 40, '3' => '555 01', '4' => 1250, '5' => 25.0, '6' => true), $out[0]['cells']);
	pg_assert_same(array('1' => 'Bread', '2' => 41, '3' => '555 02'), $out[1]['cells'], 'empty money and an unticked box stay empty');
	pg_assert_same(array('1' => 'Pie'), $out[2]['cells'], 'no link, no value');
	pg_assert_same(array('1' => 'Tart', '2' => 99), $out[3]['cells'], 'a linked row that is gone gives nothing');
	pg_assert_same(1, $out[0]['id']);
}

// A table the reader does not read is missing from $linked: the lookups
// stay empty, the row link keeps its id.
function test_workspace_wtable_lookup_unread_table()
{
	$rows = array(array('id' => 1, 'cells' => array('2' => 40, '3' => 'sent by mistake')));

	pg_assert_same(array('2' => 40), ws_wtable_lookup_values(ws_wtable_lookup_test_orders(), $rows, array())[0]['cells']);
}

// A deleted linked column, or a link column gone or no longer a row link,
// leaves the lookup empty.
function test_workspace_wtable_lookup_gone_columns()
{
	$rows = array(array('id' => 1, 'cells' => array('2' => 40)));
	$customers = ws_wtable_lookup_test_customers();
	unset($customers['11']);
	$linked = array(9 => array('columns' => $customers, 'cells' => array(40 => array('11' => '555 01', '12' => 1250))));

	$out = ws_wtable_lookup_values(ws_wtable_lookup_test_orders(), $rows, $linked);
	pg_assert_false(isset($out[0]['cells']['3']), 'the phone column is gone');
	pg_assert_same(1250, $out[0]['cells']['4']);

	$columns = ws_wtable_lookup_test_orders();
	unset($columns['2']);
	pg_assert_same(array(), ws_wtable_lookups($columns), 'no row link column, no lookup');
	pg_assert_same(array('2' => 40), ws_wtable_lookup_values($columns, $rows, $linked)[0]['cells']);

	$customers['15'] = ws_wtable_lookup_test_column(15, 'row', array('table' => 3));
	$columns = ws_wtable_lookup_test_orders();
	$columns['3']['options']['column'] = 15;
	$linked[9]['columns'] = $customers;
	$linked[9]['cells'][40]['15'] = 7;
	pg_assert_false(isset(ws_wtable_lookup_values($columns, $rows, $linked)[0]['cells']['3']), 'a row link is not shown');
}

// The screen and the download write a lookup as the linked column.
function test_workspace_wtable_lookup_text()
{
	$columns = ws_wtable_lookup_test_orders();
	$customers = ws_wtable_lookup_test_customers();
	$people = ws_wtable_people_with_lookups(array(), array('3' => $customers['11'], '4' => $customers['12'], '6' => $customers['14']));

	pg_assert_same('555 01', ws_wtable_screen_cell_text($columns['3'], '555 01', $people));
	pg_assert_true(strpos(ws_wtable_screen_cell_text($columns['4'], 1250, $people), 'EUR') !== false, 'money with its currency');
	pg_assert_same('✓', ws_wtable_screen_cell_text($columns['6'], true, $people));
	pg_assert_same('', ws_wtable_screen_cell_text($columns['5'], 25.0, $people), 'no target to the reader, no text');
	pg_assert_same(12.5, ws_wtable_export_cell($columns['4'], 1250, $people));
	pg_assert_same('', ws_wtable_export_cell($columns['5'], 25.0, $people));
	pg_assert_same(array(), ws_wtable_lookup_person_ids($people[WS_WTABLE_LOOKUPS_KEY], array(array('cells' => array('3' => 5)))));
	pg_assert_same(array(5), ws_wtable_lookup_person_ids(array('3' => ws_wtable_lookup_test_column(11, 'person')), array(array('cells' => array('3' => 5)))));
}

// The columns card names the link and the linked column.
function test_workspace_wtable_lookup_label()
{
	$columns = ws_wtable_lookup_test_orders();

	pg_assert_same('Customer › Phone', ws_wtable_lookup_label($columns['3'], $columns, ws_wtable_lookup_test_customers()));
	pg_assert_same('Customer › ' . lang('A table you do not read'), ws_wtable_lookup_label($columns['3'], $columns, null));
	pg_assert_same('Customer › ?', ws_wtable_lookup_label($columns['3'], $columns, array()));
}

// A formula, a condition and a sort do not read a lookup.
function test_workspace_wtable_lookup_not_read_by_formulas_or_conditions()
{
	$columns = ws_wtable_lookup_test_orders();
	$columns['7'] = ws_wtable_lookup_test_column(7, 'formula', array('expression' => 'Credit + 1'), 'Plus');

	pg_assert_same('ref', ws_wtable_formula_operand($columns['4'], null));
	pg_assert_same(array('7' => 'ref'), ws_wtable_formula_values($columns, array())['errors']);
	pg_assert_same(array(), ws_wtable_where_type_operators('lookup'));
	pg_assert_false(ws_wtable_total_type('lookup'));
	pg_assert_true(ws_wtable_query_refusal($columns, ws_wtable_query('', 'all', 3, 'asc')) !== '', 'the screen and the API do not sort by it');
	pg_assert_true(ws_step_where_refusal($columns, array('where' => '', 'sort' => 3), false) !== '', 'a box does not sort by it');
	pg_assert_same('', ws_step_where_refusal($columns, array('where' => '', 'sort' => 1), false));
}

// Change type refuses a lookup both ways.
function test_workspace_wtable_lookup_retype()
{
	pg_assert_same('type_not_changeable', ws_wtable_retype_input_check(ws_wtable_lookup_test_orders()['3'], array('type' => 'text'))['error']);
	pg_assert_same('type_unknown', ws_wtable_retype_input_check(ws_wtable_lookup_test_column(1, 'text'), array('type' => 'lookup'))['error']);
}

// The import never fills a lookup.
function test_workspace_wtable_lookup_import()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_import.php');
	pg_assert_true(strpos(pg_test_function_source('ws_wtable_import_match'), 'ws_wtable_type_stores') !== false);
}

// Read a row and Read rows give a lookup as cell_<id>; the row start box,
// whose event is built for no reader, does not.
function test_workspace_wtable_lookup_flow_outputs()
{
	$columns = array_values(ws_wtable_lookup_test_orders());
	$start = array_column(ws_flow_row_outputs($columns), 'key');
	$read = array_column(ws_flow_row_outputs($columns, true), 'key');

	pg_assert_false(in_array('cell_3', $start, true));
	pg_assert_true(in_array('cell_2', $start, true));
	pg_assert_true(in_array('cell_3', $read, true));
	pg_assert_true(in_array('cell_3', array_column(ws_step_outputs('row_get', array('columns' => $columns)), 'key')), 'Read a row gives it');
	pg_assert_same('1250', ws_step_row_get_data($columns, array('4' => 1250), 1)['cell_4']);
	pg_assert_same('555 01', ws_step_rows_get_items($columns, array(array('id' => 1, 'position' => 1, 'cells' => array('3' => '555 01'))))[0]['cell_3']);
}

// Every reader of rows fills the lookups, for whoever reads.
function test_workspace_wtable_lookup_filled_where_read()
{
	foreach (array('ws_step_row_get_do', 'ws_step_rows_get_do', 'ws_api_wtable_row_answer', 'ws_api_wtable_rows_list', 'ws_api_wtable_rows_query') as $function) {
		if (!function_exists($function) && (strpos($function, 'ws_api_') === 0)) {
			require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_wtables.php');
		}

		$source = pg_test_function_source($function);
		pg_assert_true((strpos($source, 'ws_wtable_lookup_fill') !== false) || (strpos($source, 'ws_api_wtable_rows_present') !== false), $function);
	}

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');
	pg_assert_same(4, substr_count($screen, 'ws_wtable_lookup_fill('), 'board, download, page and row form');
	pg_assert_same(3, substr_count($screen, 'ws_wtable_people_with_lookups('));

	$hints = pg_test_function_source('ws_flow_ai_catalogue_hints');
	pg_assert_true(substr_count($hints, 'a lookup column') >= 2, 'row_get and rows_get say a lookup is read');
}
