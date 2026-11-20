<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests whether a list's Export to Excel fits in memory and in a sheet
 * (includes/fn/list_excel.php): the CSV is measured, the memory the workbook
 * takes is estimated from it, and an export past either limit is sent as the
 * CSV it was.
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
 * A CSV stream of a heading and $count copies of $line.
 */
function list_excel_size_test_csv($heading, $line, $count)
{
	$handle = fopen('php://temp', 'w+b');
	fwrite($handle, "\xEF\xBB\xBF" . $heading . "\n");

	for ($row = 0; $row < $count; $row++) {
		fwrite($handle, str_replace('#', (string) $row, $line) . "\n");
	}

	rewind($handle);

	return $handle;
}

function test_list_excel_size_counts_rows_and_cells()
{
	$handle = list_excel_size_test_csv('"a";"b";"c"', '"1";"x;y";"#"', 4);
	$size = pg_list_excel_size($handle);

	pg_assert_same(4, $size['rows'], 'the rows below the heading');
	pg_assert_same(15, $size['cells'], 'every cell, the heading too, a separator inside quotes not counted');
	pg_assert_same(0, ftell($handle), 'the stream is put back at its start');
	fclose($handle);
}

function test_list_excel_size_estimate_covers_the_build()
{
	if (!function_exists('memory_reset_peak_usage')) {
		pg_assert_true(true, 'the peak cannot be reset before PHP 8.2');
		return;
	}

	$shapes = array(
		'many short cells' => array('a,b,c,d,e,f,g,h,i,j', '1,2,x,y,,,,,z,w', array('a' => 'number')),
		'long text cells'  => array('"n","m","d","a","b"', '"#","12.50","2026-11-19 10:00:00","Some longer text on row #","person#@example.com"', array('n' => 'number', 'm' => 'money', 'd' => 'date')),
	);

	foreach ($shapes as $label => $shape) {
		$handle = list_excel_size_test_csv($shape[0], $shape[1], 20000);
		$size = pg_list_excel_size($handle);
		$path = tempnam(sys_get_temp_dir(), 'pg_list_size');

		gc_collect_cycles();
		$before = memory_get_usage();
		memory_reset_peak_usage();
		$table = pg_list_excel_table($handle, $shape[2], 'month_day');
		pg_xlsx_write_plain($table['headings'], $table['rows'], $path, 'Sheet');
		unset($table);
		$grown = memory_get_peak_usage() - $before;

		fclose($handle);
		@unlink($path);

		pg_assert_true(pg_list_excel_need($size) >= $grown, $label . ': the estimate (' . pg_list_excel_need($size) . ') covers the build (' . $grown . ')');
	}
}

function test_list_excel_size_too_big_goes_as_csv()
{
	$size = array('rows' => 1000, 'cells' => 10000, 'bytes' => 100000);
	$need = pg_list_excel_need($size);

	pg_assert_true(pg_list_excel_fits($size, $need + 2000, 1000), 'room left for the workbook');
	pg_assert_false(pg_list_excel_fits($size, $need + 500, 1000), 'not enough memory left: the CSV is sent');
	pg_assert_true(pg_list_excel_fits($size, 0, 1000), 'no memory limit');
	pg_assert_true(pg_list_excel_fits(array('rows' => 1048575, 'cells' => 1048576, 'bytes' => 2097152), 0, 0), 'the heading and 1,048,575 rows fill a sheet');
	pg_assert_false(pg_list_excel_fits(array('rows' => 1048576, 'cells' => 1048577, 'bytes' => 2097154), 0, 0), 'a row past the sheet\'s last: the CSV is sent');
}

function test_list_excel_text_excel_can_open()
{
	$handle = list_excel_size_test_csv('"name","notes"', '"Ay' . "\xC5" . '","' . str_repeat('a', 40000) . '"', 1);
	$table = pg_list_excel_table($handle, array(), 'month_day');
	fclose($handle);

	pg_assert_true(preg_match('//u', $table['rows'][0][0]) === 1, 'a value cut inside a letter becomes valid UTF-8');
	pg_assert_same(0, strpos($table['rows'][0][0], 'Ay'), 'the readable part stays');
	pg_assert_same(32767, mb_strlen($table['rows'][0][1]), 'a text longer than an Excel cell holds is cut to 32,767 characters');
	pg_assert_true(preg_match('//u', pg_list_excel_text(str_repeat('ğ', 40000))) === 1, 'the cut falls between letters');
}
