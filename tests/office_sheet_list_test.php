<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workbook's one list of changes (renderBook() in
 * assets/js/pg_office_view.js): typed cells and format ranges kept in the
 * order they were made, any number of ranges, each cell's look under the
 * ranges waiting, the save list sent, and the strings that refused a mix.
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
L.cell(list, 0, 'A1', true);
L.format(list, 0, [1, 0, 2, 1], 'bold', true);
L.format(list, 0, [1, 0, 2, 1], 'italic', true);
L.cell(list, 0, 'B2', true);
L.format(list, 1, [3, 2, 3, 2], 'fill', 'FFFF00');
L.format(list, 0, [1, 0, 2, 1], 'align', 'center');
out.kinds = list.map(function (step) { return step.range ? 'format' : 'cell'; });
out.merged = list[1].set;
out.count = L.count(list);
L.cell(list, 0, 'A1', true);
out.moved = list.map(function (step) { return step.cell || ''; });
L.cell(list, 0, 'B2', false);
out.dropped = L.count(list);
out.look = L.look({ b: 1, c: '2563EB' }, list, 0, 2, 1);
out.outside = L.look({ b: 1 }, list, 0, 3, 1);
out.other = L.look(null, list, 1, 3, 2);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('cell', 'format', 'cell', 'format', 'format'), $result['kinds'], 'cells and formats in the order they were made; a second part of the same range is the same change');
	pg_assert_same(array('bold' => true, 'italic' => true), $result['merged'], 'bold and italic of one range in one change');
	pg_assert_same(5, $result['count'], 'each cell and each range counts once');
	pg_assert_same(array('', 'B2', '', '', 'A1'), array_map(function ($cell) { return (string) $cell; }, $result['moved']), 'a cell typed again goes to the end');
	pg_assert_same(4, $result['dropped'], 'a cell given back its value leaves the list');
	pg_assert_same(array('b' => 1, 'c' => '2563EB', 'i' => 1, 'a' => 'center'), $result['look'], 'the ranges over a cell laid on its look in order');
	pg_assert_same(array('b' => 1), $result['outside'], 'a cell outside every range keeps its look');
	pg_assert_same(array('bg' => 'FFFF00'), $result['other'], 'a range of another sheet');
}

function test_office_sheet_list_edits()
{
	$result = office_sheet_list_test_node(<<<'JS'
var list = [];
L.cell(list, 0, 'A1', true);
L.format(list, 0, [1, 0, 2, 1], 'bold', true);
L.cell(list, 1, 'C3', true);
L.cell(list, 0, 'D4', true);
var values = [{ A1: 'one', B9: 'typed, not kept yet' }, { C3: '3' }];
console.log(JSON.stringify(L.edits(list, values, ['Liste', 'Two'], function (range) { return 'A1:B2'; })));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'A1', 'value' => 'one'),
		array('op' => 'format', 'sheet' => 0, 'name' => 'Liste', 'range' => 'A1:B2', 'set' => array('bold' => true)),
		array('sheet' => 1, 'name' => 'Two', 'cell' => 'C3', 'value' => '3'),
		array('sheet' => 0, 'name' => 'Liste', 'cell' => 'B9', 'value' => 'typed, not kept yet'),
	), $result, 'the list in order; a cell given back its value (D4) left out, the one being typed last');
}

function test_office_sheet_list_strings()
{
	$strings = pg_office_view_strings();
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');

	foreach (array('format_first', 'format_one', 'format_pending', 'changed') as $key) {
		pg_assert_false(isset($strings[$key]), $key . ' leaves the strings');
		pg_assert_false(strpos($source, 'strings.' . $key . ' ') !== false || strpos($source, 'strings.' . $key . ',') !== false || strpos($source, 'strings.' . $key . ')') !== false, $key . ' leaves the view');
	}

	pg_assert_same('{1} change(s) not saved.', $strings['unsaved'], 'the status line counts the changes');
}
