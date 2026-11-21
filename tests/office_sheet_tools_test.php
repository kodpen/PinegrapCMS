<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The workbook view's sheet tools (assets/js/pg_office_view.js,
 * window.pgOfficeView.sheetTools) run in Node: the header row, sorting by a
 * column (numbers, text, empty cells, either way), filtering by value, the
 * column's value list, finding in the workbook and the CSV of the rows
 * shown. Skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the script after the view in Node and answers what it printed as
// JSON; null when Node cannot run.
function office_sheet_tools_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgot');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar T = window.pgOfficeView.sheetTools;\n"
		. "var rows = [\n"
		. "  { n: 1, cells: ['Name', 'Amount', 'Day'] },\n"
		. "  { n: 2, cells: ['pear', '10', '2026-03-01'], show: { 1: '10,00' } },\n"
		. "  { n: 3, cells: ['Apple', '9.5', ''] },\n"
		. "  { n: 5, cells: ['banana', '', '2026-01-15'] },\n"
		. "  { n: 6, cells: ['apple', '100', '2025-12-31'] },\n"
		. "  { n: 7, cells: ['cherry', 'n/a'] }\n"
		. "];\n"
		. "var C = new Intl.Collator('en', { numeric: true, sensitivity: 'base' }).compare;\n"
		. "function names(order) { return order.map(function (place) { return rows[place].cells[0]; }); }\n"
		. $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_office_sheet_tools_sort()
{
	$result = office_sheet_tools_node(<<<'JS'
var out = {};
out.header = T.headerRow(rows, null);
out.noHeader = T.headerRow([{ n: 1, cells: ['x', '5'] }, { n: 2, cells: ['y', '6'] }], null);
out.single = T.headerRow([{ n: 1, cells: ['x'] }], null);
out.none = T.arrange(rows, { sort: null, filters: {} }, null, C);
out.byName = names(T.arrange(rows, { sort: { column: 0, down: false }, filters: {} }, null, C));
out.byAmount = names(T.arrange(rows, { sort: { column: 1, down: false }, filters: {} }, null, C));
out.byAmountDown = names(T.arrange(rows, { sort: { column: 1, down: true }, filters: {} }, null, C));
out.byDay = names(T.arrange(rows, { sort: { column: 2, down: false }, filters: {} }, null, C));
out.changed = names(T.arrange(rows, { sort: { column: 1, down: false }, filters: {} }, { B7: '1' }, C));
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true($result['header'], 'a first row of text over more rows is the header');
	pg_assert_true(!$result['noHeader'], 'a first row holding a number is data');
	pg_assert_true(!$result['single'], 'a sheet of one row has no header');
	pg_assert_same(array(0, 1, 2, 3, 4, 5), $result['none'], 'unsorted and unfiltered rows keep the file\'s order');
	pg_assert_same(array('Name', 'Apple', 'apple', 'banana', 'cherry', 'pear'), $result['byName'], 'text sorts without case, equal values in the file\'s order, the header first');
	pg_assert_same(array('Name', 'Apple', 'pear', 'apple', 'cherry', 'banana'), $result['byAmount'], 'numbers by size before text, an empty cell last');
	pg_assert_same(array('Name', 'cherry', 'apple', 'pear', 'Apple', 'banana'), $result['byAmountDown'], 'Z to A turns all but the empty cells around');
	pg_assert_same(array('Name', 'apple', 'banana', 'pear', 'Apple', 'cherry'), $result['byDay'], 'dates sort as days');
	pg_assert_same(array('Name', 'cherry', 'Apple', 'pear', 'apple', 'banana'), $result['changed'], 'a value typed into a cell sorts as typed');
}

function test_office_sheet_tools_filter_and_values()
{
	$result = office_sheet_tools_node(<<<'JS'
var out = {};
out.values = T.columnValues(rows, 1, null, C);
out.dayValues = T.columnValues(rows, 2, null, C).texts;
out.filtered = names(T.arrange(rows, { sort: null, filters: { 1: { '': true, 'n/a': true } } }, null, C));
out.shownText = names(T.arrange(rows, { sort: null, filters: { 1: { '10,00': true } } }, null, C));
out.both = names(T.arrange(rows, { sort: { column: 0, down: true }, filters: { 2: { '': true } } }, null, C));
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('texts' => array('9.5', '10,00', '100', 'n/a', ''), 'more' => false), $result['values'], 'a column\'s shown texts once each, in sort order, the header left out');
	pg_assert_same(array('2025-12-31', '2026-01-15', '2026-03-01', ''), $result['dayValues']);
	pg_assert_same(array('Name', 'pear', 'Apple', 'apple'), $result['filtered'], 'a hidden value hides its rows; the header stays');
	pg_assert_same(array('Name', 'Apple', 'banana', 'apple', 'cherry'), $result['shownText'], 'a filter takes the text the cell shows');
	pg_assert_same(array('Name', 'pear', 'banana', 'apple'), $result['both'], 'a filter and a sort together: a row without the cell shows it empty');
}

function test_office_sheet_tools_find()
{
	$result = office_sheet_tools_node(<<<'JS'
var sheets = [{ rows: rows }, { rows: [{ n: 4, cells: ['', 'APPLE pie'] }] }];
var out = {};
out.all = T.findCells(sheets, 'apple', [[0, 1, 2, 3, 4, 5], [0]], null);
out.sorted = T.findCells(sheets, 'apple', [[4, 2], [0]], null);
out.shown = T.findCells(sheets, '10,0', [[0, 1, 2, 3, 4, 5], [0]], null);
out.changed = T.findCells(sheets, 'kiwi', [[0, 1, 2, 3, 4, 5], [0]], [{ C7: 'Kiwi' }, {}]);
out.empty = T.findCells(sheets, '', [[0], [0]], null);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('sheet' => 0, 'row' => 3, 'column' => 0),
		array('sheet' => 0, 'row' => 6, 'column' => 0),
		array('sheet' => 1, 'row' => 4, 'column' => 1),
	), $result['all'], 'every sheet, whatever the case');
	pg_assert_same(array(6, 3, 4), array_column($result['sorted'], 'row'), 'in the order the rows are shown; a row filtered out is not found');
	pg_assert_same(array(array('sheet' => 0, 'row' => 2, 'column' => 1)), $result['shown'], 'the text a cell shows is searched');
	pg_assert_same(array(array('sheet' => 0, 'row' => 7, 'column' => 2)), $result['changed'], 'a value typed in is found');
	pg_assert_same(array(), $result['empty']);
}

function test_office_sheet_tools_csv()
{
	$result = office_sheet_tools_node(<<<'JS'
var out = {};
out.text = T.csvText(rows, [0, 1, 3], null);
out.changed = T.csvText([{ n: 1, cells: ['a'] }, { n: 2, cells: [] }], [0, 1], { C2: 'x, "y"' });
out.fields = ['=SUM(A1)', '+1+A1', '-5', '-12,50', '-₺1.234', '@cmd', '-', 'a\nb', 'plain', '+90 532 000'].map(T.csvField);
out.names = [T.csvName('Sales: 2026/Q1'), T.csvName(''), T.csvName('..')];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same("\xEF\xBB\xBFName,Amount,Day\r\npear,\"10,00\",2026-03-01\r\nbanana,,2026-01-15\r\n", $result['text'], 'the rows shown, cells as shown, a byte order mark, CRLF');
	pg_assert_same("\xEF\xBB\xBFa,,\r\n,,\"x, \"\"y\"\"\"\r\n", $result['changed'], 'a cell typed in beyond the row\'s cells widens the sheet');
	pg_assert_same(array("'=SUM(A1)", "'+1+A1", '-5', '"-12,50"', '-₺1.234', "'@cmd", "'-", "\"a\nb\"", 'plain', '+90 532 000'), $result['fields'], 'a text read as a formula is kept as text; a number is not');
	pg_assert_same(array('Sales_ 2026_Q1.csv', 'sheet.csv', 'sheet.csv'), $result['names']);
}
