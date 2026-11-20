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
