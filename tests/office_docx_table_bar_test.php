<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document bar's table controls (renderDocument() in
 * assets/js/pg_office_view.js): the save entries a table of the file sends
 * for the rows and columns put in or deleted on the page, the size grid's
 * rows, and the parts of the source a unit test cannot press.
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
function office_docx_table_bar_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgtb');

	if ($file === false) {
		return null;
	}

	$helpers = <<<'JS'
var B = window.pgOfficeView.docTable;
// A row of the file (f) or a new one (n), hidden when deleted (h); its cells
// the same way, each new cell holding its text as one paragraph.
function cell(spec, anchor) {
    return { fresh: spec.charAt(0) === 'n', hidden: spec.indexOf('h') > 0, anchor: anchor, items: spec.charAt(0) === 'n' ? (spec.slice(1).replace('h', '') ? [{ text: spec.slice(1).replace('h', '') }] : []) : null };
}
function row(spec, cells, anchor) {
    return { fresh: spec.charAt(0) === 'n', hidden: spec === 'fh', anchor: anchor, cells: cells };
}
JS;

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\n" . $helpers . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_office_docx_table_bar_entries()
{
	$result = office_docx_table_bar_test_node(<<<'JS'
var out = {};
// A 2x2 table of the file: paragraphs 1, 2 / 3, 4.
function plain() {
    return [row('f', [cell('f', 1), cell('f', 2)], 1), row('f', [cell('f', 3), cell('f', 4)], 3)];
}
out.none = B.tableEntries({ hidden: false, rows: plain() });
out.whole = B.tableEntries({ hidden: true, rows: plain() });

// A row put in after the first, typed in; two after the second.
var rows = plain();
rows.splice(1, 0, row('n', [cell('na'), cell('n')]));
rows.push(row('n', [cell('nb'), cell('nc')]), row('n', [cell('n'), cell('n')]));
out.rows = B.tableEntries({ hidden: false, rows: rows });

// A row put in above the first.
rows = plain();
rows.unshift(row('n', [cell('n'), cell('nx')]));
out.above = B.tableEntries({ hidden: false, rows: rows });

// A column put in after the first and one before it; the new row (put in
// last) holds a cell in each.
rows = [
    row('f', [cell('n'), cell('f', 1), cell('np'), cell('f', 2)], 1),
    row('f', [cell('nq'), cell('f', 3), cell('n'), cell('f', 4)], 3),
    row('n', [cell('n1'), cell('n2'), cell('n3'), cell('n4')])
];
out.columns = B.tableEntries({ hidden: false, rows: rows });

// The second row deleted and the first column; a new row keeps the hidden
// cell of the deleted column, sent empty.
rows = [
    row('f', [cell('fh', 1), cell('f', 2)], 1),
    row('fh', [cell('fh', 3), cell('f', 4)], 3),
    row('n', [cell('nh'), cell('nz')])
];
out.deletes = B.tableEntries({ hidden: false, rows: rows });

out.grid = [B.pickRows(0), B.pickRows(3), B.pickRows(7), B.pickRows(8), B.pickRows(12), B.pickRows(19), B.pickRows(25)];
out.limits = [B.limits.rows, B.limits.columns, B.limits.widest];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(), $result['none'], 'a table as it was sends nothing');
	pg_assert_same(array(array('cell' => 1, 'table' => 'delete')), $result['whole'], 'a table deleted is named by a paragraph of its first cell');

	pg_assert_same(array(
		array('cell' => 1, 'table' => 'row_after', 'cells' => array(array(array('text' => 'a')), array())),
		array('cell' => 3, 'table' => 'row_after', 'cells' => array(array(array('text' => 'b')), array(array('text' => 'c')))),
		array('cell' => 3, 'table' => 'row_after'),
	), $result['rows'], 'rows after a row of the file go after it in order, each with its cells by column; an empty row sends no cells');

	pg_assert_same(array(
		array('cell' => 1, 'table' => 'row_before', 'cells' => array(array(), array(array('text' => 'x')))),
	), $result['above'], 'a row above the first goes before it');

	pg_assert_same(array(
		array('cell' => 1, 'table' => 'column_before', 'cells' => array(array(), array(array('text' => 'q')))),
		array('cell' => 1, 'table' => 'column_after', 'cells' => array(array(array('text' => 'p')), array())),
		array('cell' => 3, 'table' => 'row_after', 'cells' => array(array(array('text' => '1')), array(array('text' => '2')), array(array('text' => '3')), array(array('text' => '4')))),
	), $result['columns'], 'columns go first, their cells by the rows of the file; a later row has a cell in each column');

	pg_assert_same(array(
		array('cell' => 3, 'table' => 'row_after', 'cells' => array(array(), array(array('text' => 'z')))),
		array('cell' => 3, 'table' => 'row_delete'),
		array('cell' => 1, 'table' => 'column_delete'),
	), $result['deletes'], 'a row put in after a deleted one is put in before it goes; what goes is listed last');

	pg_assert_same(array(8, 8, 8, 9, 13, 20, 20), $result['grid'], 'the size grid shows 8 rows, one more than the row reached, at most 20');
	pg_assert_same(array(20, 10, 63), $result['limits'], 'the new table limits are the writer\'s');
}

function test_office_docx_table_bar_limits_match_the_writer()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/office_docx_table.php';

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');

	pg_assert_true(strpos($source, 'var DOC_TABLE_LIMITS = { rows: ' . PG_OFFICE_DOCX_TABLE_MAX_ROWS . ', columns: ' . PG_OFFICE_DOCX_TABLE_MAX_COLUMNS . ', widest: ' . PG_OFFICE_DOCX_TABLE_WIDEST . ' };') !== false, 'the editor\'s table limits are the writer\'s');
}

function test_office_docx_table_bar_source()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');

	foreach (array('doc_table', 'doc_table_size', 'doc_table_row', 'doc_table_column', 'doc_table_row_delete', 'doc_table_column_delete', 'doc_table_delete') as $key) {
		pg_assert_true(strpos($source, 'strings.' . $key) !== false, 'the bar uses ' . $key);
	}

	pg_assert_true(strpos($source, "'bi bi-table'") !== false || strpos($source, "'bi-table'") !== false, 'the Table button shows the table glyph');
	pg_assert_true(preg_match('/var\s+select\s*=/', $source) === 0, 'no var hides renderDocument()\'s select()');

	$php = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/office.php');

	foreach (array('doc_table', 'doc_table_size', 'doc_table_row', 'doc_table_column', 'doc_table_row_delete', 'doc_table_column_delete', 'doc_table_delete') as $key) {
		pg_assert_true(strpos($php, "'" . $key . "'") !== false, 'the strings hold ' . $key);
	}
}
