<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the spreadsheet import door (includes/fn/sheet_import.php) on a big
 * sheet, on workbooks left waiting for their sheet question, and on a file
 * whose name and content disagree. The workbooks are built in the test.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A one-sheet .xlsx of $count rows below a heading, six text cells each.
 */
function sheet_import_doors_test_big_xlsx($count)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_doors') . '.xlsx';
	$rows_of = function () use ($count) {
		yield 1 => array(
			array('t' => 's', 'v' => 'email_address'), array('t' => 's', 'v' => 'first_name'), array('t' => 's', 'v' => 'last_name'),
			array('t' => 's', 'v' => 'company'), array('t' => 's', 'v' => 'city'), array('t' => 's', 'v' => 'notes'),
		);

		for ($row = 2; $row <= $count + 1; $row++) {
			yield $row => array(
				array('t' => 's', 'v' => 'person' . $row . '@example.com'), array('t' => 's', 'v' => 'First' . $row), array('t' => 's', 'v' => 'Last' . $row),
				array('t' => 's', 'v' => 'Company ' . $row), array('t' => 's', 'v' => 'City ' . ($row % 81)), array('t' => 's', 'v' => 'A note on row ' . $row),
			);
		}
	};

	pg_assert_true(pg_xlsx_write_book(array(array('name' => 'Contacts')), $rows_of, $path), 'the big workbook is written');

	return $path;
}

function test_sheet_import_doors_big_sheet_is_written_without_holding_it()
{
	if (!function_exists('memory_reset_peak_usage')) {
		pg_assert_true(true, 'the peak cannot be reset before PHP 8.2');
		return;
	}

	$path = sheet_import_doors_test_big_xlsx(40000);
	$target = tempnam(sys_get_temp_dir(), 'pg_doors_csv');

	gc_collect_cycles();
	$before = memory_get_usage();
	memory_reset_peak_usage();
	$made = pg_sheet_import_csv($path, 'xlsx', 0, $target, $error);
	$grown = memory_get_peak_usage() - $before;

	$lines = 0;
	$handle = fopen($target, 'rb');

	while (fgets($handle) !== false) {
		$lines++;
	}

	fclose($handle);
	@unlink($path);
	@unlink($target);

	pg_assert_true($made, 'the sheet is written (' . $error . ')');
	pg_assert_same(40001, $lines, 'every row and the heading');
	pg_assert_true($grown < 4 * 1024 * 1024, 'the rows are not held in memory while the CSV is written (' . round($grown / 1048576, 1) . ' MB)');
}

function test_sheet_import_doors_unanswered_workbooks_are_swept_by_age()
{
	$directory = sys_get_temp_dir() . '/pg_doors_sweep_' . bin2hex(random_bytes(4));
	mkdir($directory);
	$old = $directory . '/sheet_import_' . str_repeat('a', 32) . '.bin';
	$fresh = $directory . '/sheet_import_' . str_repeat('b', 32) . '.bin';
	$other = $directory . '/list_export.xlsx';

	foreach (array($old, $fresh, $other) as $file) {
		file_put_contents($file, 'x');
	}

	touch($old, time() - PG_SHEET_IMPORT_MAX_AGE - 60);
	touch($other, time() - PG_SHEET_IMPORT_MAX_AGE - 60);

	pg_assert_same(1, pg_sheet_import_sweep($directory, time()), 'one workbook was past the hour');
	pg_assert_false(is_file($old), 'the workbook nobody came back for is removed');
	pg_assert_true(is_file($fresh), 'a workbook still waiting stays');
	pg_assert_true(is_file($other), 'another file in the folder stays');

	@unlink($fresh);
	@unlink($other);
	@rmdir($directory);
}

function test_sheet_import_doors_content_decides_the_reader()
{
	$copy = function ($fixture, $name) {
		$path = sys_get_temp_dir() . '/pg_doors_' . bin2hex(random_bytes(4)) . '_' . $name;
		copy(dirname(PG_FUNCTIONS_DIR) . '/tests/fixtures/sheet_import/' . $fixture, $path);

		return $path;
	};

	$text = sys_get_temp_dir() . '/pg_doors_' . bin2hex(random_bytes(4)) . '_report.xls';
	file_put_contents($text, "email_address\tfirst_name\nali@example.com\tAli\n");

	$xlsx_as_csv = $copy('rows.xlsx', 'rows.csv');
	$ods = $copy('rows.ods', 'rows.ods');
	$xls = $copy('rows.xls', 'rows.xls');
	$csv = $copy('rows.csv', 'rows.csv');

	pg_assert_same('', pg_sheet_import_content_kind($text, 'report.xls'), 'a tab text saved as .xls goes on as a CSV file');
	pg_assert_same('xlsx', pg_sheet_import_content_kind($xlsx_as_csv, 'rows.csv'), 'a workbook named .csv is read as the workbook');
	pg_assert_same('ods', pg_sheet_import_content_kind($ods, 'rows.ods'), 'an .ods file');
	pg_assert_same('ods', pg_sheet_import_content_kind($ods, 'rows.xlsx'), 'an .ods file named .xlsx');
	pg_assert_same('xls', pg_sheet_import_content_kind($xls, 'rows.xls'), 'an .xls file');
	pg_assert_same('', pg_sheet_import_content_kind($csv, 'rows.csv'), 'a CSV file');

	foreach (array($text, $xlsx_as_csv, $ods, $xls, $csv) as $file) {
		@unlink($file);
	}
}
