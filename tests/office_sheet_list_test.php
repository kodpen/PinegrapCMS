<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workbook's one list of changes (renderBook() in
 * assets/js/pg_office_view.js): typed cells and format ranges kept in the
 * order they were made with rows, columns and sheets, the workbook the list
 * leaves (rows, values, formats and locks moved, sheets added, renamed,
 * moved, deleted), the save list sent, and the strings that refused a mix.
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
function office_sheet_list_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsl');

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

function test_office_sheet_list_order()
{
	$result = office_sheet_list_test_node(<<<'JS'
var list = [];
var out = {};
L.cell(list, 0, 'A1', 'one');
L.format(list, 0, [1, 0, 2, 1], 'bold', true);
L.format(list, 0, [1, 0, 2, 1], 'italic', true);
L.cell(list, 0, 'B2', 'two');
L.format(list, 1, [3, 2, 3, 2], 'fill', 'FFFF00');
L.format(list, 0, [1, 0, 2, 1], 'align', 'center');
out.kinds = list.map(function (step) { return step.range ? 'format' : 'cell'; });
out.merged = list[1].set;
out.count = L.count(list);
L.cell(list, 0, 'A1', 'again');
out.moved = list.map(function (step) { return step.cell ? step.cell + '=' + step.value : ''; });
L.cell(list, 0, 'B2', null);
out.dropped = L.count(list);
out.look = L.look({ b: 1, c: '2563EB' }, [{ bold: true, italic: true }, { align: 'center' }]);
out.none = L.look({ b: 1 }, undefined);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('cell', 'format', 'cell', 'format', 'format'), $result['kinds'], 'cells and formats in the order they were made; a second part of the same range is the same change');
	pg_assert_same(array('bold' => true, 'italic' => true), $result['merged'], 'bold and italic of one range in one change');
	pg_assert_same(5, $result['count'], 'each cell and each range counts once');
	pg_assert_same(array('', 'B2=two', '', '', 'A1=again'), $result['moved'], 'a cell typed again goes to the end with its new value');
	pg_assert_same(4, $result['dropped'], 'a cell given back its value leaves the list');
	pg_assert_same(array('b' => 1, 'c' => '2563EB', 'i' => 1, 'a' => 'center'), $result['look'], 'the parts laid on a cell\'s look in order');
	pg_assert_same(array('b' => 1), $result['none'], 'a cell under no range keeps its look');
}

function test_office_sheet_list_moves_end_the_merge()
{
	$result = office_sheet_list_test_node(<<<'JS'
var list = [];
L.cell(list, 0, 'A2', 'before');
list.push({ op: 'insert', sheet: 0, axis: 'row', at: 1 });
L.cell(list, 0, 'A2', 'after');
L.cell(list, 0, 'A2', 'again');
L.cell(list, 0, 'A2', null);
console.log(JSON.stringify(list.map(function (step) { return step.cell ? step.cell + '=' + step.value : step.op; })));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('A2=before', 'insert'), $result, 'a cell typed after a row change is a change of its own: its address names another cell');
}

function test_office_sheet_list_book()
{
	$result = office_sheet_list_test_node(<<<'JS'
var sheets = [
	{ name: 'One', rows: [
		{ n: 1, cells: ['a1', 'b1', 'c1'], s: { 1: 4 }, show: { 2: '1.0' } },
		{ n: 2, cells: ['a2', 'b2'] },
		{ n: 3, cells: ['a3'] }
	], edit: { ok: true, locked: { C1: 'formula' }, formulas: { C1: 'A1+1' }, ranges: [[2, 0, 3, 1, 'merged']] } },
	{ name: 'Two', rows: [{ n: 1, cells: ['x'] }], edit: { ok: true } }
];
var list = [];
var out = {};
L.cell(list, 0, 'A2', 'typed');
L.format(list, 0, [3, 1, 3, 1], 'bold', true);
list.push({ op: 'insert', sheet: 0, axis: 'row', at: 2 });
L.cell(list, 0, 'B2', 'in the new row');
list.push({ op: 'delete', sheet: 0, axis: 'column', at: 0 });
list.push({ op: 'sheet_add', to: 'Three' });
L.cell(list, 2, 'C5', 'on the new sheet');
list.push({ op: 'sheet_rename', sheet: 1, to: 'Second' });
list.push({ op: 'sheet_move', sheet: 1, to: 0 });
var book = L.book(sheets, list);
out.rows = book.sheets[1].rows.map(function (row) { return row.n + ':' + row.cells.join(','); });
out.style = book.sheets[1].rows[0].s;
out.show = book.sheets[1].rows[0].show;
out.values = book.values[1];
out.sets = book.sets[1];
out.locked = book.sheets[1].edit.locked;
out.formulas = book.sheets[1].edit.formulas;
out.ranges = book.sheets[1].edit.ranges;
out.added = book.sheets[1].added;
out.wide = book.sheets[1].wide;
out.names = book.sheets.map(function (sheet) { return sheet.name + '#' + sheet.id + (sheet.changed ? '*' : ''); });
out.fresh = [book.sheets[2].rows.length, book.sheets[2].wide, book.sheets[2].edit.ok, book.values[2]];
out.untouched = [sheets[0].rows.length, sheets[0].rows[0].cells.length, sheets[0].name, sheets[1].name, sheets[0].edit.locked];
list.push({ op: 'sheet_delete', sheet: 0 });
out.after = L.book(sheets, list).sheets.map(function (sheet) { return sheet.name; });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('1:b1,c1', '2:', '3:b2', '4:'), $result['rows'], 'a row inserted before row 2 is drawn empty, the rows after move down, column A deleted');
	pg_assert_same(array(0 => 4), $result['style'], 'a cell\'s style moves with its column');
	pg_assert_same(array(1 => '1.0'), $result['show'], 'a cell\'s shown text moves with its column');
	pg_assert_same(array('A2' => 'in the new row'), $result['values'], 'a typed cell follows the rows and columns after it; one of a deleted column leaves');
	pg_assert_same(array('A4' => array(array('bold' => true))), $result['sets'], 'a format follows the rows and columns after it');
	pg_assert_same(array('B1' => 'formula'), $result['locked'], 'a locked cell moves');
	pg_assert_same(array('B1' => 'A1+1'), $result['formulas'], 'a formula text moves');
	pg_assert_same(array(array(3, 0, 4, 0, 'merged')), $result['ranges'], 'an area moves and narrows');
	pg_assert_same(array('row' => array(2 => true), 'column' => array()), $result['added'], 'the inserted row is marked');
	pg_assert_same(2, $result['wide'], 'the sheet is drawn one column narrower');
	pg_assert_same(array('Second#1*', 'One#0*', 'Three#new1*'), $result['names'], 'renamed, moved and added sheets in their places, each marked changed');
	pg_assert_same(array(30, 10, true, array('C5' => 'on the new sheet')), $result['fresh'], 'an added sheet is an empty grid typed into');
	pg_assert_same(array(3, 3, 'One', 'Two', array('C1' => 'formula')), $result['untouched'], 'the sheets the server sent are not changed');
	pg_assert_same(array('One', 'Three'), $result['after'], 'a deleted sheet leaves the tabs');
}

function test_office_sheet_list_edits()
{
	$result = office_sheet_list_test_node(<<<'JS'
var list = [];
L.cell(list, 0, 'A1', 'one');
L.format(list, 0, [1, 0, 2, 1], 'bold', true);
list.push({ op: 'insert', sheet: 0, axis: 'row', at: 3 });
list.push({ op: 'delete', sheet: 1, axis: 'column', at: 2 });
list.push({ op: 'sheet_rename', sheet: 0, to: 'Renamed' });
L.cell(list, 0, 'B3', 'typed');
list.push({ op: 'sheet_add', to: 'Three' });
list.push({ op: 'sheet_move', sheet: 2, to: 0 });
L.cell(list, 1, 'C3', '3');
list.push({ op: 'sheet_delete', sheet: 2 });
console.log(JSON.stringify(L.edits(list, ['Liste', 'Two'], function (range) { return 'A1:B2'; })));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A1', 'value' => 'one'),
		array('op' => 'format', 'sheet' => 0, 'name' => 'Liste', 'range' => 'A1:B2', 'set' => array('bold' => true)),
		array('sheet' => 0, 'name' => 'Liste', 'op' => 'insert', 'row' => 3),
		array('sheet' => 1, 'name' => 'Two', 'op' => 'delete', 'column' => 'C'),
		array('op' => 'sheet_rename', 'sheet' => 0, 'name' => 'Liste', 'to' => 'Renamed'),
		array('sheet' => 0, 'name' => 'Renamed', 'cell' => 'B3', 'value' => 'typed'),
		array('op' => 'sheet_add', 'to' => 'Three'),
		array('op' => 'sheet_move', 'sheet' => 2, 'name' => 'Three', 'to' => 0),
		array('sheet' => 1, 'name' => 'Renamed', 'cell' => 'C3', 'value' => '3'),
		array('op' => 'sheet_delete', 'sheet' => 2, 'name' => 'Two'),
	), $result, 'every change in order, each sheet named as the list leaves it at that point');
}

function test_office_sheet_list_strings()
{
	$strings = pg_office_view_strings();
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');

	foreach (array('format_first', 'format_one', 'format_pending', 'changed', 'shift_first', 'shift_undo', 'shift_row_before', 'shift_row_after', 'shift_row_delete',
		'shift_column_before', 'shift_column_after', 'shift_column_delete', 'shift_sheet', 'sheet_first', 'sheet_one', 'sheet_undo',
		'sheet_pending_add', 'sheet_pending_rename', 'sheet_pending_delete', 'sheet_pending_move') as $key) {
		pg_assert_false(isset($strings[$key]), $key . ' leaves the strings');
		pg_assert_false(strpos($source, 'strings.' . $key . ' ') !== false || strpos($source, 'strings.' . $key . ',') !== false || strpos($source, 'strings.' . $key . ')') !== false, $key . ' leaves the view');
	}

	pg_assert_same('{1} change(s) not saved.', $strings['unsaved'], 'the status line counts the changes');
}

function test_office_sheet_list_history()
{
	$result = office_sheet_list_test_node(<<<'JS'
var list = [];
var out = {};
var h = L.history(100);
out.empty = [h.canUndo(), h.canRedo()];
out.first = h.change(list, function () { L.cell(list, 0, 'A1', 'one'); });
h.change(list, function () { L.format(list, 0, [1, 0, 1, 0], 'bold', true); });
h.change(list, function () { L.format(list, 0, [1, 0, 1, 0], 'italic', true); });
out.length = list.length;
out.none = h.change(list, function () { L.cell(list, 0, 'B9', null); });
list = h.undo(list);
out.undone = list[1].set;
list = h.undo(list);
out.twice = list.length;
list = h.redo(list);
out.redone = list[1].set;
out.both = [h.canUndo(), h.canRedo()];
h.change(list, function () { list.push({ op: 'insert', sheet: 0, axis: 'row', at: 2 }); });
out.dropped = h.canRedo();
out.back = h.redo(list);
var all = [];
while (h.canUndo()) {
    list = h.undo(list);
    all.push(list.length);
}
out.all = all;
out.end = h.undo(list);
var c = L.history(5);
var kept = [];
c.change(kept, function () { L.format(kept, 0, [1, 0, 1, 0], 'bold', true); });
var undone = c.undo(kept);
kept[0].set.bold = false;
out.copy = c.redo(undone)[0].set;
var short = L.history(2);
var other = [];
['A1', 'A2', 'A3'].forEach(function (cell) { short.change(other, function () { L.cell(other, 0, cell, 'x'); }); });
other = short.undo(other);
other = short.undo(other);
out.limit = [other.length, short.canUndo()];
short.clear();
out.clear = [short.canUndo(), short.canRedo()];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(false, false), $result['empty'], 'nothing to undo or redo at the start');
	pg_assert_true($result['first'], 'a change that alters the list is kept');
	pg_assert_same(2, $result['length'], 'bold and italic of one range are one entry of the list');
	pg_assert_false($result['none'], 'a change that leaves the list as it was is not a step');
	pg_assert_same(array('bold' => true), $result['undone'], 'one press undoes one change: the italic laid on the range, not the range');
	pg_assert_same(1, $result['twice'], 'the next press undoes the bold, and the range leaves');
	pg_assert_same(array('bold' => true), $result['redone'], 'Redo gives back what the Undo took');
	pg_assert_same(array(true, true), $result['both'], 'both ways open in the middle');
	pg_assert_same(array('bold' => true), $result['copy'], 'a state is kept as a copy: the list changed after the press changes nothing kept');
	pg_assert_false($result['dropped'], 'a new change drops what could be redone');
	pg_assert_same(null, $result['back'], 'Redo gives nothing then');
	pg_assert_same(array(2, 1, 0), $result['all'], 'every change undone in turn, the last made first');
	pg_assert_same(null, $result['end'], 'Undo gives nothing past the first change');
	pg_assert_same(array(1, false), $result['limit'], 'only the last states of the limit are kept');
	pg_assert_same(array(false, false), $result['clear'], 'clear() empties both ways');
}

function test_office_sheet_list_history_controls()
{
	$strings = pg_office_view_strings();
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	$start = strpos($source, 'function renderBook(');
	$book = substr($source, $start, strpos($source, 'function paragraphTitle(') - $start);

	pg_assert_false(isset($strings['format_undo']), 'the quiet "Undo the format change" leaves the strings');
	pg_assert_false(strpos($source, 'strings.format_undo') !== false, 'and the view');
	pg_assert_false(strpos($book, 'cancelLast') !== false || strpos($book, 'cancelFormat') !== false, 'Escape no longer drops a waiting change');
	pg_assert_true(strpos($book, "['undo', 'bi-arrow-counterclockwise', strings.doc_undo], ['redo', 'bi-arrow-clockwise', strings.doc_redo]") !== false, 'the Undo and Redo pair on the sheet bar');
	pg_assert_true(strpos($book, 'historyKey(event)') !== false, 'Ctrl+Z, Ctrl+Y and Ctrl+Shift+Z in the grid');
	pg_assert_true(strpos($book, 'stepHistory.clear()') !== false, 'dropping every change empties the history');
}
