<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a workbook's column widths, row heights and frozen top row in
 * the grid's list of changes (assets/js/pg_office_view.js): the entries
 * kept, the workbook they leave, the save list sent, the units drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs a Node script after the view's source; its JSON output, or null when
// Node cannot run.
function office_sheet_sizes_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsz');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar L = window.pgOfficeView.sheetList;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_office_sheet_sizes_list()
{
	$result = office_sheet_sizes_test_node(<<<'JS'
var list = [];
var out = {};
L.cell(list, 0, 'A1', 'one');
L.size(list, 0, 'width', 2, 12.5);
L.size(list, 0, 'width', 2, 20);
L.size(list, 0, 'height', 3, 30);
L.size(list, 0, 'width', 2, 8);
L.size(list, 1, 'freeze', 0, true);
L.cell(list, 0, 'A1', 'again');
out.list = list.map(function (step) { return step.op ? step.op + ':' + step.sheet + ':' + step.at + '=' + step.value : step.cell; });
out.typed = list.filter(function (step) { return step.cell; }).length;
out.count = L.count(list);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('width:0:2=20', 'height:0:3=30', 'width:0:2=8', 'freeze:1:0=true', 'A1'), $result['list'], 'a size set again at once is one change; any other is its own; a cell typed again moves to the end');
	pg_assert_same(1, $result['typed'], 'a size moves no cell, so a cell typed again past it is still one entry');
	pg_assert_same(5, $result['count'], 'each entry counts one change');
}

function test_office_sheet_sizes_book()
{
	$result = office_sheet_sizes_test_node(<<<'JS'
var sheets = [
	{ name: 'One', rows: [
		{ n: 1, cells: ['a1', 'b1'], h: 20 },
		{ n: 2, cells: ['a2'] },
		{ n: 4, cells: ['a4'], h: 33 }
	], sizes: { widths: [10, 10, 30], width: 8.43, height: 15, frozen: 0 }, edit: { ok: true } },
	{ name: 'Two', rows: [{ n: 1, cells: ['x'] }], edit: { ok: true } }
];
var out = {};
var list = [];
L.size(list, 0, 'width', 3, 15);
L.size(list, 0, 'height', 2, 40);
L.size(list, 0, 'freeze', 0, true);
L.size(list, 1, 'width', 0, 5);
var book = L.book(sheets, list);
out.widths = book.sheets[0].sizes.widths;
out.heights = book.sheets[0].rows.map(function (row) { return row.h === undefined ? null : row.h; });
out.frozen = book.sheets[0].sizes.frozen;
out.defaults = [book.sheets[0].sizes.width, book.sheets[0].sizes.height];
out.other = [book.sheets[1].sizes.widths, book.sheets[1].sizes.frozen, book.sheets[1].changed];
out.untouched = [sheets[0].sizes.widths, sheets[0].rows[1].h === undefined, sheets[0].sizes.frozen, sheets[1].sizes === undefined];
list.push({ op: 'insert', sheet: 0, axis: 'column', at: 1 });
list.push({ op: 'insert', sheet: 0, axis: 'column', at: 3 });
list.push({ op: 'delete', sheet: 0, axis: 'column', at: 0 });
list.push({ op: 'insert', sheet: 0, axis: 'row', at: 2 });
book = L.book(sheets, list);
out.shifted = book.sheets[0].sizes.widths;
out.rows = book.sheets[0].rows.map(function (row) { return row.n + ':' + (row.h === undefined ? '' : row.h); });
L.size(list, 0, 'width', 0, null);
L.size(list, 0, 'freeze', 0, false);
book = L.book(sheets, list);
out.off = [book.sheets[0].sizes.widths, book.sheets[0].sizes.frozen];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('0' => 10, '1' => 10, '2' => 30, '3' => 15), $result['widths'], 'a column\'s width is laid on the widths the file has');
	pg_assert_same(array(20, 40, 33), $result['heights'], 'a row\'s height is laid on its row');
	pg_assert_same(1, $result['frozen'], 'Freeze top row freezes one row');
	pg_assert_same(array(8.43, 15), $result['defaults'], 'the sheet\'s default sizes stay');
	pg_assert_same(array(array('0' => 5), 0, true), $result['other'], 'a sheet sent without sizes gets them, marked changed');
	pg_assert_same(array(array(10, 10, 30), true, 0, true), $result['untouched'], 'the sheets the server sent are not changed');
	pg_assert_same(array('0' => 10, '1' => 10, '3' => 30, '4' => 15), $result['shifted'], 'widths move with their columns; a column inserted inside a run of one width takes it, one beside it does not');
	pg_assert_same(array('1:20', '2:', '3:40', '5:33'), $result['rows'], 'heights move with their rows; an inserted row has none');
	pg_assert_same(array(array('1' => 10, '3' => 30, '4' => 15), 0), $result['off'], 'a width taken off and the frozen row turned off');
}

function test_office_sheet_sizes_edits()
{
	$result = office_sheet_sizes_test_node(<<<'JS'
var list = [];
L.size(list, 0, 'width', 27, 12.5);
L.size(list, 0, 'height', 4, 30);
list.push({ op: 'sheet_rename', sheet: 0, to: 'Renamed' });
L.size(list, 0, 'freeze', 0, true);
L.size(list, 1, 'width', 0, null);
console.log(JSON.stringify(L.edits(list, ['Liste', 'Two'], function () { return 'A1'; })));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('op' => 'width', 'sheet' => 0, 'name' => 'Liste', 'column' => 'AB', 'width' => 12.5),
		array('op' => 'height', 'sheet' => 0, 'name' => 'Liste', 'row' => 4, 'height' => 30),
		array('op' => 'sheet_rename', 'sheet' => 0, 'name' => 'Liste', 'to' => 'Renamed'),
		array('op' => 'freeze', 'sheet' => 0, 'name' => 'Renamed', 'on' => true),
		array('op' => 'width', 'sheet' => 1, 'name' => 'Two', 'column' => 'A', 'width' => null),
	), $result, 'each size sent as office_size.php reads it, its sheet named as the list leaves it');
}

function test_office_sheet_sizes_units()
{
	$result = office_sheet_sizes_test_node(<<<'JS'
var U = window.pgOfficeView.sheetSizes;
console.log(JSON.stringify({
	px: [U.widthPx(8.43), U.widthPx(1), U.heightPx(15), U.heightPx(409)],
	back: [U.widthOf(64), U.widthOf(12), U.widthOf(1), U.widthOf(100000), U.heightOf(20), U.heightOf(1), U.heightOf(100000)],
	round: [U.widthOf(U.widthPx(20)), U.heightOf(U.heightPx(30))]
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(64, 12, 20, 545), $result['px'], 'characters and points drawn as a spreadsheet draws them at 100 %');
	pg_assert_same(array(8.43, 1, 0.5, 255, 15, 1, 409), $result['back'], 'a drawn size read back in the file\'s units, inside the limits office_size.php takes');
	pg_assert_same(array(20, 30), $result['round'], 'a whole size drawn and read back is the same');
}

function test_office_sheet_sizes_strings()
{
	$strings = pg_office_view_strings();
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');

	foreach (array('size_column', 'size_row', 'size_column_label', 'size_row_label', 'size_apply', 'size_bad', 'freeze_on', 'freeze_off') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key . ' is among the view\'s strings');
		pg_assert_true(strpos($source, 'strings.' . $key) !== false || strpos($source, "'" . $key . "'") !== false, $key . ' is used by the view');
	}

	pg_assert_true(strpos($source, '!sizeOps[step.op]') !== false, 'a size is no change that brings the recalculation note');
}
