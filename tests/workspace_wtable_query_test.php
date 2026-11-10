<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a query over the rows of a workflow table
 * (includes/workspace/wtable_query.php): a query read and checked against
 * the table's columns, the rows it keeps in its order and their pages, the
 * screen's query bar read from the address and written back into links,
 * a typed value as a condition compares it, and a total as the totals line
 * shows it.
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
function ws_wtable_query_test_columns()
{
	$columns = array(
		'1' => array('Name', 'text', array()),
		'2' => array('Stock', 'number', array()),
		'3' => array('Price', 'money', array('decimals' => 2)),
		'4' => array('Due', 'date', array()),
		'5' => array('State', 'status', array('choices' => array(array('key' => 'open', 'label' => 'Open'), array('key' => 'done', 'label' => 'Done')))),
		'6' => array('Paid', 'checkbox', array()),
		'8' => array('Owner', 'person', array()),
		'9' => array('Order', 'link', array()),
		'11' => array('Met', 'date', array('time' => true)),
	);
	$out = array();

	foreach ($columns as $id => $column) {
		$out[$id] = array('id' => (int) $id, 'name' => $column[0], 'type' => $column[1], 'options' => ws_wtable_column_options_check($column[1], $column[2])['value']);
	}

	return $out;
}

// Rows as the store reads them (ws_wtable_row_out()).
function ws_wtable_query_test_rows()
{
	$columns = ws_wtable_query_test_columns();
	$cells = array(
		1 => array('1' => 'Un', '2' => 4, '3' => 1250, '4' => '2026-11-09', '5' => 'open', '6' => true, '8' => 12),
		2 => array('1' => 'Şeker', '2' => 0, '3' => 990, '4' => '2026-11-12', '5' => 'done'),
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

// Runs $test with the site writing decimals with a dot, then puts the
// site's own way back.
function ws_wtable_query_test_dot($test)
{
	$own = ws_calc_decimal_comma();
	ws_calc_decimal_comma(false);

	try {
		$test();
	} finally {
		ws_calc_decimal_comma($own);
	}
}

// A query is read with defaults for what it lacks or does not know.
function test_workspace_wtable_query_read()
{
	pg_assert_same(array('where' => array(), 'join' => 'all', 'sort' => 0, 'dir' => 'asc'), ws_wtable_query(''));
	pg_assert_same(array('where' => null, 'join' => 'all', 'sort' => 0, 'dir' => 'asc'), ws_wtable_query('{"a":1}', 'some', 'x', 'up'));

	$query = ws_wtable_query('[{"column":2,"op":"gt","value":3}]', 'any', '5', 'desc');
	pg_assert_same(array(array('column' => 2, 'op' => 'gt', 'value' => '3')), $query['where']);
	pg_assert_same('any', $query['join']);
	pg_assert_same(5, $query['sort']);
	pg_assert_same('desc', $query['dir']);

	pg_assert_false(ws_wtable_query_active(ws_wtable_query('')));
	pg_assert_true(ws_wtable_query_active(ws_wtable_query('', 'all', 0, 'desc')));
	pg_assert_true(ws_wtable_query_active(ws_wtable_query('', 'all', 3)));
	pg_assert_true(ws_wtable_query_active($query));
}

// A query is refused, condition by condition, when it does not fit the
// table: values in the engine's form (money in minor units).
function test_workspace_wtable_query_refusal()
{
	$columns = ws_wtable_query_test_columns();

	pg_assert_same('', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":3,"op":"gte","value":"1000"}]', 'all', 1)));
	pg_assert_same('', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":4,"op":"empty"}]')));
	pg_assert_true(ws_wtable_query_refusal($columns, ws_wtable_query('[1]')) !== '');

	$many = array_fill(0, WS_WTABLE_WHERE_MAX + 1, array('column' => 1, 'op' => 'empty'));
	pg_assert_contains((string) WS_WTABLE_WHERE_MAX, ws_wtable_query_refusal($columns, ws_wtable_query($many)));

	pg_assert_contains('Condition 1', ws_wtable_query_refusal($columns, ws_wtable_query('[{"op":"eq","value":"1"}]')));
	pg_assert_contains('Condition 2', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":1,"op":"empty"},{"column":1,"op":"less"}]')));
	pg_assert_contains('no longer has', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":99,"op":"eq","value":"1"}]')));
	pg_assert_contains('Stock', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":2,"op":"contains","value":"1"}]')));
	pg_assert_contains('12.50', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":3,"op":"eq","value":"12.50"}]')));
	pg_assert_contains('two', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":2,"op":"eq","value":"two"}]')));
	pg_assert_contains('value', ws_wtable_query_refusal($columns, ws_wtable_query('[{"column":2,"op":"eq","value":""}]')));
	pg_assert_contains('sorted', ws_wtable_query_refusal($columns, ws_wtable_query('', 'all', 99)));
}

// The rows a query keeps, sorted, then paged by an offset.
function test_workspace_wtable_query_rows_and_pages()
{
	$columns = ws_wtable_query_test_columns();
	$rows = ws_wtable_query_test_rows();

	pg_assert_same(array(11, 12, 13, 14), array_column(ws_wtable_query_rows($columns, $rows, ws_wtable_query('')), 'id'));
	pg_assert_same(array(14, 13, 12, 11), array_column(ws_wtable_query_rows($columns, $rows, ws_wtable_query('', 'all', 0, 'desc')), 'id'));
	pg_assert_same(array(14, 11, 12, 13), array_column(ws_wtable_query_rows($columns, $rows, ws_wtable_query('', 'all', 3, 'desc')), 'id'));
	pg_assert_same(array(11, 14), array_column(ws_wtable_query_rows($columns, $rows, ws_wtable_query('[{"column":3,"op":"gte","value":"1000"}]', 'all', 3)), 'id'));
	pg_assert_same(array(12, 13), array_column(ws_wtable_query_rows($columns, $rows, ws_wtable_query('[{"column":5,"op":"eq","value":"done"},{"column":2,"op":"gt","value":"10"}]', 'any')), 'id'));

	$page = ws_wtable_query_page($rows, 0, 3);
	pg_assert_same(array(11, 12, 13), array_column($page['rows'], 'id'));
	pg_assert_same(3, $page['next']);

	$page = ws_wtable_query_page($rows, 3, 3);
	pg_assert_same(array(14), array_column($page['rows'], 'id'));
	pg_assert_same(null, $page['next']);

	pg_assert_same(null, ws_wtable_query_page($rows, 1, 3)['next']);
	pg_assert_same(array(), ws_wtable_query_page($rows, 10, 3)['rows']);
}

// The bar's address: lines without a column left out, unknown words to the
// defaults, and back into the parameters of a link, defaults left out.
function test_workspace_wtable_query_screen_address()
{
	$get = array(
		'q'    => array(
			array('c' => '3', 'o' => 'gte', 'v' => '10.00'),
			array('c' => '', 'o' => 'eq', 'v' => ''),
			array('c' => '5', 'o' => 'nope', 'v' => 'Open'),
			'x',
		),
		'join' => 'any',
		'sort' => '2',
		'dir'  => 'desc',
	);
	$screen = ws_wtable_query_from_get($get);

	pg_assert_same(array(
		array('column' => 3, 'op' => 'gte', 'value' => '10.00'),
		array('column' => 5, 'op' => '', 'value' => 'Open'),
	), $screen['typed']);
	pg_assert_same('any', $screen['join']);
	pg_assert_same(2, $screen['sort']);
	pg_assert_same('desc', $screen['dir']);

	pg_assert_same(array(
		'q'    => array(array('c' => '3', 'o' => 'gte', 'v' => '10.00'), array('c' => '5', 'o' => '', 'v' => 'Open')),
		'join' => 'any',
		'sort' => '2',
		'dir'  => 'desc',
	), ws_wtable_query_params($screen));

	pg_assert_same(array(), ws_wtable_query_params(ws_wtable_query_from_get(array('join' => 'any', 'sort' => 'x'))));

	$lines = array();

	for ($i = 0; $i < WS_WTABLE_WHERE_MAX + 3; $i++) {
		$lines[] = array('c' => '1', 'o' => 'empty');
	}

	pg_assert_same(WS_WTABLE_WHERE_MAX, count(ws_wtable_query_from_get(array('q' => $lines))['typed']));
}

// A value typed into the bar as the condition compares it.
function test_workspace_wtable_query_screen_value()
{
	ws_wtable_query_test_dot(function () {
		$columns = ws_wtable_query_test_columns();
		$people = array(12 => array('name' => 'Ayşe Kaya'), 4 => array('name' => 'Can Er'));

		pg_assert_same('1250', ws_wtable_query_screen_value($columns['3'], '12.50'));
		pg_assert_same('125000', ws_wtable_query_screen_value($columns['3'], '1,250'));
		pg_assert_same('1001', ws_wtable_query_screen_value($columns['3'], '10.005'));
		pg_assert_same('abc', ws_wtable_query_screen_value($columns['3'], 'abc'));
		pg_assert_same('2.5', ws_wtable_query_screen_value($columns['2'], '2.50'));
		pg_assert_same('', ws_wtable_query_screen_value($columns['2'], '  '));

		pg_assert_same('2026-11-09', ws_wtable_query_screen_value($columns['4'], '2026-11-09'));
		pg_assert_same('2026-11-09', ws_wtable_query_screen_value($columns['4'], '09.11.2026', array(), 'day_month'));
		pg_assert_same('2026-11-09', ws_wtable_query_screen_value($columns['4'], '11/09/2026', array(), 'month_day'));
		pg_assert_same('2026-11-09 09:30', ws_wtable_query_screen_value($columns['11'], '2026-11-09T09:30'));
		pg_assert_same('2026-11-09 09:30', ws_wtable_query_screen_value($columns['11'], '9.11.2026 9:30', array(), 'day_month'));

		pg_assert_same('done', ws_wtable_query_screen_value($columns['5'], 'DONE'));
		pg_assert_same('open', ws_wtable_query_screen_value($columns['5'], 'open'));
		pg_assert_same('1', ws_wtable_query_screen_value($columns['6'], 'yes'));
		pg_assert_same('0', ws_wtable_query_screen_value($columns['6'], 'No'));
		pg_assert_same('12', ws_wtable_query_screen_value($columns['8'], 'ayşe kaya', $people));
		pg_assert_same('7', ws_wtable_query_screen_value($columns['8'], '7', $people));
		pg_assert_same('70', ws_wtable_query_screen_value($columns['9'], 'order:70'));
		pg_assert_same('Şeker', ws_wtable_query_screen_value($columns['1'], ' Şeker '));

		// What it reads, the engine takes; money in minor units, rounded.
		$query = ws_wtable_query(array(array('column' => 3, 'op' => 'lt', 'value' => ws_wtable_query_screen_value($columns['3'], '10'))));
		pg_assert_same('', ws_wtable_query_refusal($columns, $query));
		pg_assert_same(array(12, 13), array_column(ws_wtable_query_rows($columns, ws_wtable_query_test_rows(), $query), 'id'));
	});
}

// A total as the line shows it: money in its decimals, numbers as figures.
function test_workspace_wtable_query_total_text()
{
	ws_wtable_query_test_dot(function () {
		$columns = ws_wtable_query_test_columns();
		$totals = ws_wtable_rows_totals($columns, ws_wtable_query_test_rows());

		pg_assert_same('45.39', ws_wtable_query_total_text($columns['3'], $totals['3']['sum']));
		pg_assert_same('11.35', ws_wtable_query_total_text($columns['3'], $totals['3']['avg']));
		pg_assert_same('16', ws_wtable_query_total_text($columns['2'], $totals['2']['sum']));
		pg_assert_same('5.3333333333', ws_wtable_query_total_text($columns['2'], $totals['2']['avg']));
		pg_assert_same('', ws_wtable_query_total_text($columns['2'], null));
	});
}
