<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the .xls preview and its copy as .xlsx (includes/fn/office.php,
 * includes/fn/xlsx_write.php): the kind by the name, the sheet reader's
 * fixtures read through the reader and shown as the .xlsx preview shows a
 * workbook (sheets in order, the hidden ones flagged, cells as text, gaps
 * kept), the files refused, the copy written by the shared writer and read
 * back by the product's readers cell for cell, the writer's sheet names and
 * failures, and the doors that offer the copy.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A fixture of the sheet reader (tests/fixtures/sheet_read/).
function office_xls_test_fixture($name)
{
	return dirname(__FILE__) . '/fixtures/sheet_read/' . $name;
}

// A copy of book.xls on disk with its sheet "Other" hidden: the visibility
// byte of its BOUNDSHEET record, four bytes before the name's length and
// flags (BIFF8: offset, visibility, type, length, flags, name).
function office_xls_test_hidden_book()
{
	$bytes = (string) file_get_contents(office_xls_test_fixture('book.xls'));
	$at = strpos($bytes, "\x05\x00Other");

	if ($at !== false) {
		$bytes[$at - 2] = "\x01";
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_office_xls_');
	file_put_contents($path, $bytes);

	return $path;
}

// Every row of a sheet as the sheet reader gives it.
function office_xls_test_rows($path, $kind, $sheet)
{
	$rows = array();
	$error = 'not run';

	foreach (pg_sheet_rows($path, $kind, $error, $sheet) as $number => $row) {
		$rows[$number] = $row;
	}

	return array($rows, $error);
}

// An .xls is a workbook only looked at: the preview knows it, the edits
// do not.
function test_office_xls_kind_by_extension()
{
	pg_assert_same('xls', pg_office_preview_kind('Rapor.XLS'), 'an .xls is previewed');
	pg_assert_same('', pg_office_kind('Rapor.xls'), 'an .xls is not offered for editing');
	pg_assert_same('Rapor.xlsx', pg_office_xls_copy_name('Rapor.XLS'), 'the copy is named with .xlsx');
	pg_assert_same('a.b.xlsx', pg_office_xls_copy_name('a.b.xls'), 'only the last extension is changed');
}

// LibreOffice's book.xls through the sheet reader: both sheets in order,
// cells as the .xlsx preview writes them, the sheet's own row numbers.
function test_office_xls_reads_a_workbook()
{
	$office = pg_office_read(office_xls_test_fixture('book.xls'), 'book.xls');

	pg_assert_true($office['ok'], 'read');
	pg_assert_same('xls', $office['kind'], 'kind');
	pg_assert_same(array('Products', 'Other'), array_column($office['sheets'], 'name'), 'sheets in order');
	pg_assert_same(array(false, false), array_column($office['sheets'], 'hidden'), 'none hidden');
	pg_assert_false($office['truncated'], 'nothing cut');

	$rows = array();

	foreach ($office['sheets'][0]['rows'] as $row) {
		$rows[$row['n']] = $row['cells'];
	}

	pg_assert_same(array(1, 2, 4, 5, 6, 7, 8, 9, 10, 11), array_keys($rows), 'empty rows left out, numbers kept');
	pg_assert_same(array('Çay ğüş', '12.5', 'TRUE', '2026-11-17', '2026-11-17 10:30', '3'), $rows[2], 'text, number, boolean, date, date and time');
	pg_assert_same(array('AB', 'TRUE', '12:30', '#DIV/0!', '  spaced  '), $rows[8], 'a formula result, a time, an error');
	pg_assert_same('0.0000001', $rows[7][3], 'a number without its exponent');
	pg_assert_same(array('', '', '', '', '', '', 'far'), $rows[6], 'a gap kept');
}

// A hidden sheet is flagged in the preview and stays hidden in the copy.
function test_office_xls_hidden_sheet()
{
	$path = office_xls_test_hidden_book();
	$target = $path . '.xlsx';

	$office = pg_office_read($path, 'book.xls');
	pg_assert_same(array(false, true), array_column($office['sheets'], 'hidden'), 'the second sheet is hidden');

	pg_assert_true(pg_office_xls_copy($path, $target)['ok'], 'copied');
	$copy = pg_office_read($target, 'book.xlsx');
	pg_assert_same(array(false, true), array_column($copy['sheets'], 'hidden'), 'hidden in the copy too');

	@unlink($path);
	@unlink($target);
}

// The copy holds every sheet's cells as the reader read them from the .xls:
// read back by the sheet reader cell for cell, and by the .xlsx preview as
// the .xls preview showed them.
function test_office_xls_copy_reads_back()
{
	foreach (array('book.xls', 'book_phpexcel.xls', 'mac1904.xls') as $fixture) {
		$source = office_xls_test_fixture($fixture);
		$target = tempnam(sys_get_temp_dir(), 'pg_office_xls_') . '.xlsx';

		pg_assert_same(array('ok' => true, 'error' => ''), pg_office_xls_copy($source, $target), $fixture . ' copied');

		foreach (array_keys(pg_sheet_xls_sheets($source)) as $sheet) {
			list($from, $from_error) = office_xls_test_rows($source, 'xls', $sheet);
			list($back, $back_error) = office_xls_test_rows($target, 'xlsx', $sheet);

			pg_assert_same('', $from_error . $back_error, $fixture . ' sheet ' . $sheet . ' read');
			pg_assert_true(count($from) > 0, $fixture . ' sheet ' . $sheet . ' has rows');
			pg_assert_same($from, $back, $fixture . ' sheet ' . $sheet . ' the same cells');
		}

		$shown = pg_office_read($source, $fixture);
		$copied = pg_office_read($target, 'copy.xlsx');

		foreach ($shown['sheets'] as $index => $sheet) {
			pg_assert_same($sheet['name'], $copied['sheets'][$index]['name'], $fixture . ' sheet name');
			pg_assert_same($sheet['rows'], $copied['sheets'][$index]['rows'], $fixture . ' shown alike');
		}

		@unlink($target);
	}
}

// What cannot be read is refused with the preview's words, and nothing is
// left behind by a refused copy.
function test_office_xls_refuses_what_it_cannot_read()
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_xls_');
	$target = $path . '.xlsx';

	file_put_contents($path, 'not a workbook at all');
	$office = pg_office_read($path, 'bad.xls');
	pg_assert_false($office['ok'], 'not an .xls');
	pg_assert_same(lang('The file could not be read.'), $office['error'], 'damaged');
	pg_assert_false(pg_office_xls_copy($path, $target)['ok'], 'no copy');
	pg_assert_false(file_exists($target), 'nothing written');

	file_put_contents($path, substr((string) file_get_contents(office_xls_test_fixture('book.xls')), 0, 3000));
	pg_assert_false(pg_office_read($path, 'cut.xls')['ok'], 'a cut file');

	$handle = fopen($path, 'wb');
	ftruncate($handle, PG_OFFICE_MAX_XLS + 1);
	fclose($handle);
	$office = pg_office_read($path, 'large.xls');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), $office['error'], 'past the size');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), pg_office_xls_copy($path, $target)['error'], 'no copy past the size');

	@unlink($path);
	@unlink($target);
}

// The shared writer's typed cells.
function test_office_xls_writer_cells()
{
	pg_assert_same('<c r="A1" t="inlineStr"><is><t xml:space="preserve">a &amp; b</t></is></c>', pg_xlsx_typed_cell('A1', array('t' => 's', 'v' => 'a & b')), 'text');
	pg_assert_same('<c r="B2"><v>1.0E-7</v></c>', pg_xlsx_typed_cell('B2', array('t' => 'n', 'v' => '1.0E-7')), 'a number as written');
	pg_assert_same('<c r="C3" t="b"><v>0</v></c>', pg_xlsx_typed_cell('C3', array('t' => 'b', 'v' => '0')), 'a boolean');
	pg_assert_same('<c r="D4" t="e"><v>#DIV/0!</v></c>', pg_xlsx_typed_cell('D4', array('t' => 'e', 'v' => '#DIV/0!')), 'an error');
	pg_assert_same('<c r="E5" s="1"><v>46343</v></c>', pg_xlsx_typed_cell('E5', array('t' => 'd', 'v' => '46343')), 'a date');
	pg_assert_same('<c r="F6" s="2"><v>46343.4375</v></c>', pg_xlsx_typed_cell('F6', array('t' => 'd', 'v' => '46343.4375')), 'a date and time');
	pg_assert_same('<c r="G7" s="4"><v>0.5</v></c>', pg_xlsx_typed_cell('G7', array('t' => 'd', 'v' => '0.5')), 'a time');
	pg_assert_same('<c r="G8" s="3"><v>0.50001</v></c>', pg_xlsx_typed_cell('G8', array('t' => 'd', 'v' => '0.50001')), 'a time with seconds');
	pg_assert_same('<c r="H8" t="inlineStr"><is><t xml:space="preserve">12,5</t></is></c>', pg_xlsx_typed_cell('H8', array('t' => 'n', 'v' => '12,5')), 'no number stays text');
	pg_assert_same('', pg_xlsx_typed_cell('I9', null), 'a gap');
	pg_assert_same('', pg_xlsx_typed_cell('J1', array('t' => 's', 'v' => '')), 'empty text');
}

// Sheet names Excel takes, kept apart; a workbook of hidden sheets shows
// its first; a sheet that cannot be read leaves no file.
function test_office_xls_writer_book()
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_xls_') . '.xlsx';
	$rows = function ($index) {
		yield 1 => array(array('t' => 's', 'v' => 'sheet ' . $index));
	};

	pg_assert_true(pg_xlsx_write_book(array(array('name' => 'Data', 'hidden' => true), array('name' => 'data', 'hidden' => true), array('name' => 'a/b', 'hidden' => false)), $rows, $path), 'written');
	$book = pg_office_read($path, 'book.xlsx');
	pg_assert_same(array('Data', 'data (2)', 'a b'), array_column($book['sheets'], 'name'), 'names apart and allowed');
	pg_assert_same(array(true, true, false), array_column($book['sheets'], 'hidden'), 'hidden kept');
	pg_assert_same(array('sheet 1'), $book['sheets'][1]['rows'][0]['cells'], 'each sheet its rows');

	pg_assert_true(pg_xlsx_write_book(array(array('name' => 'Only', 'hidden' => true)), $rows, $path), 'all hidden');
	pg_assert_same(array(false), array_column(pg_office_read($path, 'book.xlsx')['sheets'], 'hidden'), 'the first is shown');
	@unlink($path);

	$failing = function ($index) {
		yield 1 => array(array('t' => 's', 'v' => 'x'));
		throw new RuntimeException('cut');
	};

	pg_assert_false(pg_xlsx_write_book(array(array('name' => 'A')), $failing, $path), 'a failed sheet');
	pg_assert_false(file_exists($path), 'no file left');
}

// The doors: the preview says who may copy, the copy is its own action of
// the File Manager and of a channel, and the view draws the button in both.
function test_office_xls_doors_offer_the_copy()
{
	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	pg_assert_contains("office.kind === 'xls'", $view, 'the view draws an .xls');
	pg_assert_contains('options.saveCopy', $view, 'the copy button is the view\'s');

	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	pg_assert_contains("case 'explorer_office_copy':\n            pg_explorer_handle_office_copy(\$type, \$request, \$user);", $explorer, 'the File Manager copies');
	pg_assert_true(function_exists('pg_explorer_handle_office_copy') || (strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_files_media.php'), 'function pg_explorer_handle_office_copy(') !== false), 'its handler');

	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("'ods', 'xls']", $folders, 'Quick Look and the file window open it');
	pg_assert_contains("type: 'explorer_office_copy'", $folders, 'the file window asks for the copy');

	$workspace = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$case = substr($workspace, (int) strpos($workspace, "case 'ws_file_office_copy':"), 2000);
	pg_assert_contains('ws_can_post_channel($viewer, $channel)', $case, 'who may post copies');
	pg_assert_contains("'parent_id' => (int) \$message['id']", $case, 'a new message under the original');
	pg_assert_contains("api('ws_file_office_copy'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), 'a channel asks for the copy');
}
