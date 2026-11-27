<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workbook grid's clipboard (assets/js/pg_office_view.js):
 * tab-separated text read and written as a spreadsheet program does, the
 * limits a paste is held to, and many cells put into the list of changes
 * as one change.
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
function office_sheet_clipboard_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgclip');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar L = window.pgOfficeView.sheetList;\nvar C = window.pgOfficeView.sheetClip;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_office_sheet_clipboard_read()
{
	$result = office_sheet_clipboard_test_node(<<<'JS'
var out = {};
out.excel = C.rows('a\tb\r\nc\td\r\n');
out.plain = C.rows('a\tb\nc\td');
out.quoted = C.rows('"two\nlines"\t"say ""hi"""\r\nx\t\r\n');
out.loose = C.rows('"open\tb\nsix "inch"\tc');
out.one = C.rows('42');
out.empty = C.rows('');
out.blank = C.rows('\r\n');
out.gaps = C.rows('a\t\tc\n\nd');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('a', 'b'), array('c', 'd')), $result['excel'], "Excel's text: rows by CRLF, the last line break ends the block");
	pg_assert_same(array(array('a', 'b'), array('c', 'd')), $result['plain'], 'LF and no last line break read the same');
	pg_assert_same(array(array("two\nlines", 'say "hi"'), array('x', '')), $result['quoted'], 'a quoted value keeps its line break and doubled quotes; an empty last value stays');
	pg_assert_same(array(array('"open', 'b'), array('six "inch"', 'c')), $result['loose'], 'a quote that does not close a whole value is text');
	pg_assert_same(array(array('42')), $result['one'], 'one value is one cell');
	pg_assert_same(array(), $result['empty'], 'no text is no cell');
	pg_assert_same(array(array('')), $result['blank'], 'a lone line break is one empty cell');
	pg_assert_same(array(array('a', '', 'c'), array(''), array('d')), $result['gaps'], 'empty values and an empty row are kept');
}

function test_office_sheet_clipboard_write()
{
	$result = office_sheet_clipboard_test_node(<<<'JS'
var out = {};
var rows = [['a', 'two\nlines'], ['say "hi"', 'tab\there'], ['', '1.5']];
out.text = C.text(rows);
out.back = C.rows(C.text(rows));
out.one = C.text([['x']]);
out.lead = C.rows(C.text([['"quoted"', 'b']]));
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same("a\t\"two\nlines\"\r\n\"say \"\"hi\"\"\"\t\"tab\there\"\r\n\t1.5", $result['text'], 'values joined by tabs and CRLF; one holding a tab, a line break or a quote is quoted');
	pg_assert_same(array(array('a', "two\nlines"), array('say "hi"', "tab\there"), array('', '1.5')), $result['back'], 'the text read back is the same cells');
	pg_assert_same('x', $result['one'], 'one cell is its value alone');
	pg_assert_same(array(array('"quoted"', 'b')), $result['lead'], 'a value starting with a quote comes back as it was');
}

function test_office_sheet_clipboard_check()
{
	$result = office_sheet_clipboard_test_node(<<<'JS'
var out = {};
out.fits = C.check([['a', 'b'], ['c']], 2, 2);
out.rows = C.check([['a'], ['b'], ['c']], 2, 5);
out.columns = C.check([['a', 'b', 'c']], 9, 2);
out.ragged = C.check([['a'], ['b', 'c', 'd']], 9, 2);
var big = [];
for (var r = 0; r < 101; r++) { big.push(new Array(50).fill('x')); }
out.many = C.check(big, 200, 60);
out.long = C.check([['a', new Array(32769).join('x')]], 9, 9);
out.limit = C.check([[new Array(32768).join('x')]], 9, 9);
out.cells = C.cells(5001);
out.cellsOk = C.cells(5000);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same('', $result['fits'], 'a block within the rows and columns left is taken');
	pg_assert_same('room', $result['rows'], 'more rows than the grid has below is refused');
	pg_assert_same('room', $result['columns'], 'more columns than the grid has right is refused');
	pg_assert_same('room', $result['ragged'], 'the longest row decides the columns');
	pg_assert_same('many', $result['many'], 'over 5000 cells is refused');
	pg_assert_same('long', $result['long'], 'a value over 32767 characters is refused');
	pg_assert_same('', $result['limit'], '32767 characters is taken');
	pg_assert_same('many', $result['cells'], 'a fill or a clear over 5000 cells is refused');
	pg_assert_same('', $result['cellsOk'], '5000 cells are taken');
}

function test_office_sheet_clipboard_values()
{
	$result = office_sheet_clipboard_test_node(<<<'JS'
var sheets = [{ name: 'S', rows: [{ n: 1, cells: ['a', 'b'] }, { n: 2, cells: ['', 'x'] }], edit: { ok: true } }];
var out = {};
var list = [];
L.cell(list, 0, 'A1', 'typed');
L.format(list, 0, [1, 0, 1, 0], 'bold', true);
L.values(sheets, list, 0, [{ row: 1, column: 0, value: 'p' }, { row: 1, column: 1, value: 'b' }, { row: 2, column: 0, value: 'q' }]);
out.first = list.map(function (step) { return step.cell ? step.cell + '=' + step.value : 'format'; });
L.values(sheets, list, 0, [{ row: 1, column: 0, value: 'a' }, { row: 2, column: 1, value: '' }]);
out.back = list.map(function (step) { return step.cell ? step.cell + '=' + step.value : 'format'; });
var moved = [];
L.cell(moved, 0, 'B2', 'one');
moved.push({ op: 'insert', sheet: 0, axis: 'row', at: 1 });
L.values(sheets, moved, 0, [{ row: 3, column: 1, value: 'one' }, { row: 3, column: 0, value: 'new' }]);
out.moved = moved.map(function (step) { return step.cell ? step.cell + '=' + step.value : step.op; });
out.book = L.book(sheets, moved).values[0];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('format', 'A1=p', 'A2=q'), $result['first'], 'the cells go to the end in their order; a cell given the value it holds makes no entry');
	pg_assert_same(array('format', 'A2=q', 'B2='), $result['back'], 'a cell given back the file\'s value leaves the list');
	pg_assert_same(array('B2=one', 'insert', 'A3=new'), $result['moved'], 'past a row change a cell holding the value already keeps the earlier entry and makes none');
	pg_assert_same(array('B3' => 'one', 'A3' => 'new'), $result['book'], 'the workbook the list leaves holds every value');
}
