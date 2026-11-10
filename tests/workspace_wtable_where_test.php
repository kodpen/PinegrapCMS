<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the conditions over the rows of a workflow table
 * (includes/workspace/wtable_where.php): the comparisons each column type
 * takes, a list of conditions read from JSON, a value checked against its
 * column, each comparison by the column's type (money in minor units, a
 * date as ISO text, a status by its key, a checkbox as 1 or 0), conditions
 * joined by all or any, rows sorted by a column, and the sum, average,
 * smallest and largest value of the number columns over the rows.
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

// A table's columns by id, each with its options checked.
function ws_wtable_where_test_columns()
{
	$columns = array(
		'1' => array('Name', 'text', array()),
		'2' => array('Stock', 'number', array()),
		'3' => array('Price', 'money', array('decimals' => 2)),
		'4' => array('Due', 'date', array()),
		'5' => array('State', 'status', array('choices' => array(array('key' => 'open', 'label' => 'Open'), array('key' => 'done', 'label' => 'Done')))),
		'6' => array('Paid', 'checkbox', array()),
		'7' => array('Total', 'formula', array('expression' => 'Price * Stock')),
		'8' => array('Owner', 'person', array()),
		'9' => array('Order', 'link', array()),
		'10' => array('Weight', 'number', array('decimals' => 2)),
		'11' => array('Met', 'date', array('time' => true)),
	);
	$out = array();

	foreach ($columns as $id => $column) {
		$out[$id] = array('id' => (int) $id, 'name' => $column[0], 'type' => $column[1], 'options' => ws_wtable_column_options_check($column[1], $column[2])['value']);
	}

	return $out;
}

// Rows as the store reads them (ws_wtable_row_out()), formulas worked out.
function ws_wtable_where_test_rows()
{
	$columns = ws_wtable_where_test_columns();
	$cells = array(
		1 => array('1' => 'Un', '2' => 4, '3' => 1250, '4' => '2026-11-09', '5' => 'open', '6' => true, '8' => 12, '9' => array('kind' => 'order', 'id' => 70), '10' => 1.25, '11' => '2026-11-09 10:30'),
		2 => array('1' => 'Şeker', '2' => 0, '3' => 990, '4' => '2026-11-12', '5' => 'done', '10' => 2.5),
		3 => array('1' => 'ırmak suyu', '2' => 12, '3' => 300, '5' => 'open', '6' => true, '8' => 4),
		4 => array('1' => '', '3' => 1999),
	);
	$rows = array();

	foreach ($cells as $id => $row) {
		$rows[] = ws_wtable_row_out(array('id' => 10 + $id, 'position' => $id, 'revision' => 1, 'cells' => json_encode((object) $row),
			'created_by' => 1, 'created_at' => 0, 'updated_by' => 1, 'updated_at' => 0), $columns);
	}

	return $rows;
}

// The ids of the rows that meet conditions.
function ws_wtable_where_test_ids($conditions, $join = 'all')
{
	return array_column(ws_wtable_rows_where(ws_wtable_where_test_columns(), ws_wtable_where_test_rows(), $conditions, $join), 'id');
}

// One condition, as a list holds it.
function ws_wtable_where_test_one($column, $op, $value = '')
{
	return array('column' => $column, 'op' => $op, 'value' => $value);
}

// The comparisons, and the ones each column type takes: sizes for numbers,
// money, formulas and dates; words for text; a status, a person and a link
// by what they hold; a checkbox ticked or not.
function test_workspace_wtable_where_operators()
{
	pg_assert_same(array('eq', 'neq', 'lt', 'gt', 'lte', 'gte', 'contains', 'starts', 'empty', 'not_empty'), array_keys(ws_wtable_where_operators()));
	pg_assert_same(array('all', 'any'), ws_wtable_where_joins());

	pg_assert_same(array('eq', 'neq', 'contains', 'starts', 'empty', 'not_empty'), ws_wtable_where_type_operators('text'));

	foreach (array('number', 'money', 'formula', 'date') as $type) {
		pg_assert_same(array('eq', 'neq', 'lt', 'gt', 'lte', 'gte', 'empty', 'not_empty'), ws_wtable_where_type_operators($type), $type);
	}

	foreach (array('status', 'person', 'link') as $type) {
		pg_assert_same(array('eq', 'neq', 'empty', 'not_empty'), ws_wtable_where_type_operators($type), $type);
	}

	pg_assert_same(array('eq', 'neq'), ws_wtable_where_type_operators('checkbox'));
	pg_assert_same(array(), ws_wtable_where_type_operators('nothing'));

	pg_assert_true(ws_wtable_where_takes_value('eq'));
	pg_assert_true(ws_wtable_where_takes_value('starts'));
	pg_assert_false(ws_wtable_where_takes_value('empty'));
	pg_assert_false(ws_wtable_where_takes_value('not_empty'));

	pg_assert_true(ws_wtable_total_type('number'));
	pg_assert_true(ws_wtable_total_type('money'));
	pg_assert_true(ws_wtable_total_type('formula'));
	pg_assert_false(ws_wtable_total_type('text'));
	pg_assert_false(ws_wtable_total_type('checkbox'));
}

// A list of conditions, given as a list or as its JSON text: each its
// column, comparison and value as text; anything else is no list.
function test_workspace_wtable_where_list()
{
	pg_assert_same(array(), ws_wtable_where_list(''));
	pg_assert_same(array(), ws_wtable_where_list('[]'));
	pg_assert_same(array(), ws_wtable_where_list(array()));
	pg_assert_same(array(
		array('column' => 2, 'op' => 'lt', 'value' => '5'),
		array('column' => 1, 'op' => 'eq', 'value' => 'Un'),
		array('column' => 6, 'op' => 'eq', 'value' => '1'),
		array('column' => 10, 'op' => 'gte', 'value' => '1.25'),
	), ws_wtable_where_list('[{"column":"2","op":"lt","value":5},{"column":1,"op":"eq","value":"Un"},{"column":6,"op":"eq","value":true},{"column":10,"op":"gte","value":1.25}]'));
	pg_assert_same(array(array('column' => 3, 'op' => 'empty', 'value' => '')), ws_wtable_where_list(array(array('column' => 3, 'op' => 'empty'))));

	// A part it cannot read stays in its place, so the editor names it.
	pg_assert_same(array(array('column' => 0, 'op' => '', 'value' => '')), ws_wtable_where_list('[{"column":"x","op":"like","value":[1]}]'));

	foreach (array('{', '{"column":1}', '"eq"', '[1]', '[[1]]', '[{"column":1},"x"]', 12) as $text) {
		pg_assert_same(null, ws_wtable_where_list($text), json_encode($text) . ' is no list of conditions');
	}
}

// A typed value is checked against its column: numbers, money in minor
// units, a date as the table writes it, a status by its key, a checkbox
// 1 or 0, a person and a record by their numbers; a text takes anything.
function test_workspace_wtable_where_value_fault()
{
	$columns = ws_wtable_where_test_columns();
	$fault = function ($id, $value, $op = 'eq') use ($columns) {
		return ws_wtable_where_value_fault($columns[$id], $op, $value);
	};

	pg_assert_same('', $fault('1', 'anything at all'));
	pg_assert_same('', $fault('2', '-3'));
	pg_assert_same('', $fault('2', '12.5'));
	pg_assert_same('not_number', $fault('2', '12,5'));
	pg_assert_same('not_number', $fault('2', 'five'));
	pg_assert_same('', $fault('3', '1250'));
	pg_assert_same('not_minor_units', $fault('3', '12.50'));
	pg_assert_same('not_number', $fault('7', 'x'));
	pg_assert_same('', $fault('7', '0.5'));
	pg_assert_same('', $fault('4', '2026-11-09'));
	pg_assert_same('not_date', $fault('4', '09.11.2026'));
	pg_assert_same('not_date', $fault('4', '2026-02-30'));
	pg_assert_same('', $fault('11', '2026-11-09 10:30'));
	pg_assert_same('', $fault('11', '2026-11-09'), 'a day alone is compared by the day');
	pg_assert_same('not_date', $fault('11', '2026-11-09 25:00'));
	pg_assert_same('', $fault('5', 'done'));
	pg_assert_same('not_status', $fault('5', 'Done'), 'a status by its key, not its label');
	pg_assert_same('', $fault('6', '1'));
	pg_assert_same('', $fault('6', '0'));
	pg_assert_same('not_checkbox', $fault('6', 'yes'));
	pg_assert_same('', $fault('8', '12'));
	pg_assert_same('not_whole', $fault('8', 'Ayşe'));
	pg_assert_same('not_whole', $fault('9', '0'));
	pg_assert_same('', $fault('9', '70'));

	pg_assert_same('empty_value', $fault('1', '  '), 'a comparison that takes a value needs one');
	pg_assert_same('', $fault('1', '', 'empty'));
	pg_assert_same('', $fault('2', 'x', 'not_empty'), 'empty takes no value');
	pg_assert_same('operator', $fault('1', '5', 'lt'), 'a text is no size');
	pg_assert_same('operator', $fault('6', '', 'empty'), 'a checkbox is ticked or not');
	pg_assert_same('operator', $fault('2', '5', 'like'));
}

// Text without case, the Turkish capitals and the dotted or dotless i
// alike; contains and starts with as typed.
function test_workspace_wtable_where_text()
{
	$one = 'ws_wtable_where_test_one';

	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(1, 'eq', 'un'))));
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(1, 'eq', ' UN '))), 'a value is trimmed');
	pg_assert_same(array(12), ws_wtable_where_test_ids(array($one(1, 'eq', 'ŞEKER'))));
	pg_assert_same(array(13), ws_wtable_where_test_ids(array($one(1, 'starts', 'Irmak'))), 'I and ı are one letter');
	pg_assert_same(array(13), ws_wtable_where_test_ids(array($one(1, 'contains', 'SUY'))));
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(1, 'contains', ''))), 'contains nothing holds for no row');
	pg_assert_same(array(12, 13, 14), ws_wtable_where_test_ids(array($one(1, 'neq', 'un'))), 'differs keeps the empty cell');
	pg_assert_same(array(14), ws_wtable_where_test_ids(array($one(1, 'empty'))));
	pg_assert_same(array(11, 12, 13), ws_wtable_where_test_ids(array($one(1, 'not_empty'))));
	pg_assert_same(array(14), ws_wtable_where_test_ids(array($one(1, 'eq', ''))), 'equal to nothing is empty');
}

// Numbers, money in minor units and formulas by size; an empty cell is no
// size, and nought is a value.
function test_workspace_wtable_where_numbers()
{
	$one = 'ws_wtable_where_test_one';

	pg_assert_same(array(11, 12), ws_wtable_where_test_ids(array($one(2, 'lt', '5'))));
	pg_assert_same(array(11, 12), ws_wtable_where_test_ids(array($one(2, 'lte', '4'))));
	pg_assert_same(array(13), ws_wtable_where_test_ids(array($one(2, 'gt', '4'))));
	pg_assert_same(array(11, 13), ws_wtable_where_test_ids(array($one(2, 'gte', '4'))));
	pg_assert_same(array(12), ws_wtable_where_test_ids(array($one(2, 'eq', '0'))), 'nought is a value');
	pg_assert_same(array(11, 13, 14), ws_wtable_where_test_ids(array($one(2, 'neq', '0'))));
	pg_assert_same(array(14), ws_wtable_where_test_ids(array($one(2, 'empty'))));
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(2, 'lt', 'five'))), 'a value that is no number holds for no row');
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(2, 'neq', 'five'))));

	pg_assert_same(array(11, 14), ws_wtable_where_test_ids(array($one(3, 'gte', '1250'))), 'money in minor units');
	pg_assert_same(array(13), ws_wtable_where_test_ids(array($one(3, 'eq', '300'))));
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(10, 'eq', '1.25'))), 'decimals');
	pg_assert_same(array(12), ws_wtable_where_test_ids(array($one(10, 'gt', '2'))));

	// Price * Stock in whole units: 50, 0, 36, and 0 for the row without a stock.
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(7, 'gt', '40'))));
	pg_assert_same(array(12, 14), ws_wtable_where_test_ids(array($one(7, 'eq', '0'))));
}

// Dates as ISO text, compared at the coarser of the two: a day alone takes
// the whole day of a cell that keeps a time.
function test_workspace_wtable_where_dates()
{
	$one = 'ws_wtable_where_test_one';

	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(4, 'eq', '2026-11-09'))));
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(4, 'lt', '2026-11-12'))));
	pg_assert_same(array(11, 12), ws_wtable_where_test_ids(array($one(4, 'lte', '2026-11-12'))));
	pg_assert_same(array(12), ws_wtable_where_test_ids(array($one(4, 'gt', '2026-11-09'))));
	pg_assert_same(array(13, 14), ws_wtable_where_test_ids(array($one(4, 'empty'))));
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(11, 'eq', '2026-11-09'))), 'the day of a time');
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(11, 'gte', '2026-11-09 10:30'))));
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(11, 'gt', '2026-11-09 10:30'))));
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(4, 'eq', 'yesterday'))));
}

// A status by its key, a checkbox ticked (1) or not (0, also an empty
// value, as a read row gives an unticked box), a person and a record by
// their numbers.
function test_workspace_wtable_where_choices()
{
	$one = 'ws_wtable_where_test_one';

	pg_assert_same(array(11, 13), ws_wtable_where_test_ids(array($one(5, 'eq', 'open'))));
	pg_assert_same(array(12, 14), ws_wtable_where_test_ids(array($one(5, 'neq', 'open'))));
	pg_assert_same(array(14), ws_wtable_where_test_ids(array($one(5, 'empty'))));
	pg_assert_same(array(11, 13), ws_wtable_where_test_ids(array($one(6, 'eq', '1'))));
	pg_assert_same(array(12, 14), ws_wtable_where_test_ids(array($one(6, 'eq', '0'))));
	pg_assert_same(array(12, 14), ws_wtable_where_test_ids(array($one(6, 'eq', ''))), 'an unticked box read from a row');
	pg_assert_same(array(11, 13), ws_wtable_where_test_ids(array($one(6, 'neq', '0'))));
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(6, 'eq', 'maybe'))));
	pg_assert_same(array(13), ws_wtable_where_test_ids(array($one(8, 'eq', '4'))));
	pg_assert_same(array(12, 14), ws_wtable_where_test_ids(array($one(8, 'empty'))));
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(9, 'eq', '70'))));
	pg_assert_same(array(11), ws_wtable_where_test_ids(array($one(9, 'not_empty'))));
}

// All or any of the conditions; none keeps every row; a column the table
// lacks or a comparison its type does not take holds for no row.
function test_workspace_wtable_where_joined()
{
	$one = 'ws_wtable_where_test_one';
	$both = array($one(5, 'eq', 'open'), $one(2, 'lt', '10'));

	pg_assert_same(array(11, 12, 13, 14), ws_wtable_where_test_ids(array()));
	pg_assert_same(array(11), ws_wtable_where_test_ids($both));
	pg_assert_same(array(11, 12, 13), ws_wtable_where_test_ids($both, 'any'));
	pg_assert_same(array(11), ws_wtable_where_test_ids($both, 'nearly'), 'an unknown join is all');
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(99, 'empty'))), 'a column the table lacks');
	pg_assert_same(array(11, 13), ws_wtable_where_test_ids(array($one(99, 'empty'), $one(5, 'eq', 'open')), 'any'));
	pg_assert_same(array(), ws_wtable_where_test_ids(array($one(1, 'lt', 'b'))), 'a text is no size');

	$rows = ws_wtable_rows_where(ws_wtable_where_test_columns(), ws_wtable_where_test_rows(), $both);
	pg_assert_same(1, $rows[0]['position'], 'the rows as read');
	pg_assert_same('Un', $rows[0]['cells']['1']);
}

// Rows sorted by a column, its empty cells last either way; rows alike keep
// the table's order; no column is the table's order, the last row first
// when descending.
function test_workspace_wtable_where_sorted()
{
	$columns = ws_wtable_where_test_columns();
	$rows = ws_wtable_where_test_rows();
	$sorted = function ($column, $dir = 'asc') use ($columns, $rows) {
		return array_column(ws_wtable_rows_sorted($columns, $rows, $column, $dir), 'id');
	};

	pg_assert_same(array('asc', 'desc'), ws_wtable_sort_directions());
	pg_assert_same(array(11, 12, 13, 14), $sorted(0));
	pg_assert_same(array(14, 13, 12, 11), $sorted(0, 'desc'), 'the latest rows first');
	pg_assert_same(array(11, 12, 13, 14), $sorted(0, 'sideways'), 'an unknown direction is ascending');
	pg_assert_same(array(12, 11, 13, 14), $sorted(2));
	pg_assert_same(array(13, 11, 12, 14), $sorted(2, 'desc'), 'the empty stock last');
	pg_assert_same(array(13, 12, 11, 14), $sorted(3));
	pg_assert_same(array(14, 11, 12, 13), $sorted(3, 'desc'));
	pg_assert_same(array(13, 12, 11, 14), $sorted(1), 'text without case, the empty name last');
	pg_assert_same(array(11, 12, 13, 14), $sorted(4));
	pg_assert_same(array(12, 11, 13, 14), $sorted(4, 'desc'));
	pg_assert_same(array(11, 13, 12, 14), $sorted(5), 'a status in the order of its choices');
	pg_assert_same(array(12, 14, 11, 13), $sorted(6), 'unticked first');
	pg_assert_same(array(12, 14, 13, 11), $sorted(7), 'the formula, rows alike in the table\'s order');
	pg_assert_same(array(11, 12, 13, 14), $sorted(99), 'a column the table lacks');
}

// The sum, the average, the smallest and the largest value of each number,
// money and formula column over the rows; the average of money in whole
// minor units, rounded; an empty cell is left out, nothing sums to nought.
function test_workspace_wtable_where_totals()
{
	$columns = ws_wtable_where_test_columns();
	$totals = ws_wtable_rows_totals($columns, ws_wtable_where_test_rows());

	pg_assert_same(array('2', '3', '7', '10'), array_map('strval', array_keys($totals)), 'the number, money and formula columns');
	pg_assert_same(array('sum' => 16, 'avg' => 5.3333333333, 'min' => 0, 'max' => 12, 'count' => 3), $totals['2']);
	pg_assert_same(array('sum' => 4539, 'avg' => 1135, 'min' => 300, 'max' => 1999, 'count' => 4), $totals['3'], '4539 / 4 = 1134.75, rounded');
	pg_assert_same(array('sum' => 86.0, 'avg' => 21.5, 'min' => 0.0, 'max' => 50.0, 'count' => 4), $totals['7']);
	pg_assert_same(array('sum' => 3.75, 'avg' => 1.875, 'min' => 1.25, 'max' => 2.5, 'count' => 2), $totals['10']);

	$none = ws_wtable_rows_totals($columns, array());
	pg_assert_same(array('sum' => 0, 'avg' => null, 'min' => null, 'max' => null, 'count' => 0), $none['3'], 'no rows: nought, the rest none');

	$rounded = ws_wtable_rows_totals(array('3' => $columns['3']), array(array('cells' => array('3' => 1)), array('cells' => array('3' => 2))));
	pg_assert_same(2, $rounded['3']['avg'], '1.5 minor units round to 2');
}
