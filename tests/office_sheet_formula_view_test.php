<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workbook grid's list of changes around the file's simple
 * formulas (assets/js/pg_office_view.js): a cell holding a formula counts as
 * holding the formula, not its result, and a row or column moved locks the
 * file's formulas, whose texts the save moves.
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
function office_sheet_formula_view_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgsfv');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar L = window.pgOfficeView.sheetList;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	$result = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($result), 'the Node script answers JSON: ' . implode("\n", array_slice($out, 0, 8)));

	return $result;
}

function test_office_sheet_formula_cell_holds_its_formula()
{
	$result = office_sheet_formula_view_test_node(<<<'JS'
var sheets = [{ name: 'S', rows: [{ n: 1, cells: ['2', '4', ''] }], edit: { ok: true, locked: { D1: 'formula' }, formulas: { B1: 'A1*2', C1: 'A1/0', D1: 'IF(A1,1,2)' } } }];
var out = {};
out.same = L.values(sheets, [], 0, [{ row: 1, column: 1, value: '4' }]);
out.emptied = L.values(sheets, [], 0, [{ row: 1, column: 2, value: '' }]);
out.formula = L.values(sheets, [], 0, [{ row: 1, column: 1, value: '=A1*2' }]);
out.plain = L.values(sheets, [], 0, [{ row: 1, column: 0, value: '2' }]);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('sheet' => 0, 'cell' => 'B1', 'value' => '4')), $result['same'], "a value equal to the formula's result replaces the formula");
	pg_assert_same(array(array('sheet' => 0, 'cell' => 'C1', 'value' => '')), $result['emptied'], 'a formula with no result stored is emptied');
	pg_assert_same(array(), $result['formula'], 'the same formula written again is no change');
	pg_assert_same(array(), $result['plain'], 'a value cell holding the value is no change');
}

function test_office_sheet_formula_locked_after_a_move()
{
	$result = office_sheet_formula_view_test_node(<<<'JS'
var sheets = [{ name: 'S', rows: [{ n: 1, cells: ['2', '4'] }, { n: 2, cells: ['3', '6'] }], edit: { ok: true, locked: {}, formulas: { B1: 'A1*2', B2: 'A2*2' } } }];
var before = L.book(sheets, []).sheets[0].edit;
var after = L.book(sheets, [{ op: 'insert', sheet: 0, axis: 'row', at: 2 }]).sheets[0].edit;
console.log(JSON.stringify({ before: [before.locked, !!before.shifted], after: [after.locked, !!after.shifted, after.formulas] }));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array(), false), $result['before'], 'open before any move');
	pg_assert_same(array(array('B1' => 'formula', 'B3' => 'formula'), true, array('B1' => 'A1*2', 'B3' => 'A2*2')), $result['after'], 'locked where they moved, marked moved');
}
