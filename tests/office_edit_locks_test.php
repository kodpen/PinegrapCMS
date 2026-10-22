<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which cells of a protected sheet the grid offers and the save
 * accepts (includes/fn/office_edit.php, assets/js/pg_office_view.js). The
 * grid's rule is copied here from the script (how wide the sheet is drawn,
 * openOf()) and run over every drawn cell of a few form templates, read
 * through the preview's own reader, against pg_office_xlsx_patch_sheet() on
 * the same sheet: a cell is drawn open exactly when the save takes it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_edit_locks_test_ns()
{
	return 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
}

// A protected sheet part around the rows given, with what comes before them.
function office_edit_locks_test_sheet($rows, $before = '')
{
	return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<worksheet xmlns="' . office_edit_locks_test_ns() . '">'
		. $before . '<sheetData>' . $rows . '</sheetData><sheetProtection sheet="1" objects="1"/></worksheet>';
}

// The cell styles: style 0 (Normal) and style 1, each locked unless said.
function office_edit_locks_test_styles($normal_open, $one_open)
{
	$xf = function ($open) {
		return $open ? '<xf numFmtId="0"><protection locked="0"/></xf>' : '<xf numFmtId="0"/>';
	};

	return '<?xml version="1.0"?><styleSheet xmlns="' . office_edit_locks_test_ns() . '"><cellXfs count="2">' . $xf($normal_open) . $xf($one_open) . '</cellXfs></styleSheet>';
}

// A workbook of the one sheet "Form", in a zip on disk.
function office_edit_locks_test_package($sheet, $styles)
{
	$ns = office_edit_locks_test_ns();
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Form" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
		'xl/styles.xml' => $styles,
		'xl/worksheets/sheet1.xml' => $sheet,
	);

	$path = tempnam(sys_get_temp_dir(), 'pg_office_locks_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

// How many columns sheetTable() draws for a sheet offered for editing.
function office_edit_locks_test_width($sheet)
{
	$width = 0;

	foreach ($sheet['rows'] as $row) {
		$width = max($width, count($row['cells']));
	}

	if (!empty($sheet['edit']['ok']) && !empty($sheet['edit']['protected'])) {
		foreach ($sheet['edit']['columns'] as $range) {
			$width = max($width, min($range[1] + 1, 200));
		}
	}

	return $width;
}

// openOf() of pg_office_view.js.
function office_edit_locks_test_open($edit, $address, $row, $column)
{
	if (isset($edit['open'][$address])) {
		return true;
	}

	if (isset($edit['closed'][$address])) {
		return false;
	}

	if (array_key_exists($row, $edit['rows'])) {
		return (bool) $edit['rows'][$row];
	}

	foreach ($edit['columns'] as $range) {
		if (($column >= $range[0]) && ($column <= $range[1])) {
			return true;
		}
	}

	foreach (isset($edit['closed_columns']) ? $edit['closed_columns'] : array() as $range) {
		if (($column >= $range[0]) && ($column <= $range[1])) {
			return false;
		}
	}

	return !empty($edit['other']);
}

/**
 * Reads the workbook as the file window does and, for every drawn cell,
 * compares what the grid offers with what the save does with a value typed
 * into it. Returns the sheet as read and the cells drawn open.
 */
function office_edit_locks_test_mirror($label, $sheet_xml, $styles_xml)
{
	$path = office_edit_locks_test_package($sheet_xml, $styles_xml);
	$book = pg_office_read($path, 'form.xlsx', true);
	$zip = new ZipArchive();
	$zip->open($path);
	$styles = pg_office_xlsx_style_kinds($zip);
	$zip->close();
	@unlink($path);

	pg_assert_true($book['ok'] && $book['sheets'][0]['edit']['ok'], $label . ': read for editing');

	$sheet = $book['sheets'][0];
	$edit = $sheet['edit'];
	$width = office_edit_locks_test_width($sheet);
	$context = array('styles' => $styles, 'date1904' => false, 'protected' => true);
	$drawn_open = array();

	foreach ($sheet['rows'] as $row) {
		for ($column = 0; $column < $width; $column++) {
			$address = pg_office_xlsx_column_name($column) . $row['n'];
			$grid = !isset($edit['locked'][$address]) && office_edit_locks_test_open($edit, $address, (int) $row['n'], $column);
			$saved = pg_office_xlsx_patch_sheet($sheet_xml, array((int) $row['n'] => array($column => 'x')), $context);

			pg_assert_same($saved['ok'], $grid, $label . ': ' . $address . ' is drawn ' . ($grid ? 'open' : 'locked') . ', the save ' . ($saved['ok'] ? 'takes it' : 'refuses it (' . $saved['error'] . ')'));

			if ($grid) {
				$drawn_open[] = $address;
			}
		}
	}

	return array('sheet' => $sheet, 'width' => $width, 'open' => $drawn_open);
}

function test_office_edit_locks_column_without_any_cell_is_drawn()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the workbook is not read');
		return;
	}

	// Labels in A; column B unlocked by its style and empty, so not in the
	// file at all, not even in row 1.
	$sheet = office_edit_locks_test_sheet(
		'<row r="1"><c r="A1" t="inlineStr"><is><t>Name</t></is></c></row>'
		. '<row r="2"><c r="A2" t="inlineStr"><is><t>Phone</t></is></c></row>'
		. '<row r="3"><c r="A3" t="inlineStr"><is><t>City</t></is></c></row>',
		'<cols><col min="1" max="1" width="20" customWidth="1"/><col min="2" max="2" width="30" customWidth="1" style="1"/></cols>');
	$result = office_edit_locks_test_mirror('form unlocked by column', $sheet, office_edit_locks_test_styles(false, true));

	pg_assert_same(array(array(1, 1)), $result['sheet']['edit']['columns'], 'column B is sent as open');
	pg_assert_same(false, $result['sheet']['edit']['other'], 'the default style is locked');
	pg_assert_same(2, $result['width'], 'the grid reaches column B');
	pg_assert_same(array('B1', 'B2', 'B3'), $result['open'], 'every B cell is drawn open, the labels are not');
}

function test_office_edit_locks_unlocked_default_style()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the workbook is not read');
		return;
	}

	// Normal unlocked, style 1 locked: the cells in the file with style 1
	// stay locked, the cells not in the file are open, except in column D,
	// whose style is the locked one.
	$sheet = office_edit_locks_test_sheet(
		'<row r="1"><c r="A1" s="1"><v>1</v></c><c r="C1" s="1"><v>2</v></c><c r="D1"><v>3</v></c><c r="E1" s="1"/></row>'
		. '<row r="2"><c r="A2" s="1"><v>4</v></c></row>',
		'<cols><col min="4" max="4" style="1"/></cols>');
	$result = office_edit_locks_test_mirror('default style unlocked', $sheet, office_edit_locks_test_styles(true, false));
	$edit = $result['sheet']['edit'];

	pg_assert_same(true, $edit['other'], 'a new cell with no row or column style is open');
	pg_assert_same(array(array(3, 3)), $edit['closed_columns'], 'column D, whose style locks a new cell');
	pg_assert_same(array(), $edit['columns'], 'no column opened by its style');
	pg_assert_same(array('A1' => true, 'C1' => true, 'E1' => true, 'A2' => true), $edit['closed'], 'cells in the file that keep their own locked style');
	pg_assert_same(5, $result['width'], 'the default style does not widen the grid');
	pg_assert_same(array('B1', 'D1', 'B2', 'C2', 'E2'), $result['open'], 'the open cells');
}

function test_office_edit_locks_second_cols_block()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the workbook is not read');
		return;
	}

	// Two <cols> lists; the second unlocks column B, and C is unlocked by
	// one list and locked again by a later one (the last one counts).
	$sheet = office_edit_locks_test_sheet(
		'<row r="1"><c r="A1" t="inlineStr"><is><t>Name</t></is></c></row>'
		. '<row r="2"><c r="A2" t="inlineStr"><is><t>Phone</t></is></c></row>',
		'<cols><col min="1" max="1" width="20" customWidth="1"/><col min="3" max="3" style="1"/></cols>'
		. '<cols><col min="2" max="2" style="1"/><col min="3" max="3" width="9"/></cols>');
	$result = office_edit_locks_test_mirror('two column lists', $sheet, office_edit_locks_test_styles(false, true));

	pg_assert_same(array(array(1, 1)), $result['sheet']['edit']['columns'], 'only column B is open');
	pg_assert_same(array('B1', 'B2'), $result['open'], 'the B cells are drawn open and saved');
}

function test_office_edit_locks_column_styles_every_list()
{
	$xml = office_edit_locks_test_sheet('', '<cols><col min="2" max="3" style="4"/></cols><cols><col min="3" max="3"/><col min="5" max="5" style="2"/></cols>');

	pg_assert_same(array(1 => 4, 4 => 2), pg_office_xlsx_column_styles($xml, ''), 'every list is read, the last one for a column counts');
	pg_assert_same(array(array(1, 1)), pg_office_xlsx_column_ranges(array(1 => 4, 4 => 2), array(4 => true), true), 'the open columns as ranges');
	pg_assert_same(array(array(4, 4)), pg_office_xlsx_column_ranges(array(1 => 4, 4 => 2), array(4 => true), false), 'the locked columns as ranges');
	pg_assert_same(array(array(0, 2), array(5, 5)), pg_office_xlsx_column_ranges(array(0 => 1, 1 => 1, 2 => 1, 5 => 1, 6 => 2), array(1 => true), true), 'next columns join one range');
}
