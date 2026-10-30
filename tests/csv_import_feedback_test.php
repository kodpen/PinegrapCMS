<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The shared pieces of the contacts and users CSV imports: the separator read
 * from the header line, the file lines a row takes, and the list of lines that
 * could not be used, capped at 20.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The separator pg_csv_read_separator() finds in $bytes, and the first row
// fgetcsv() then reads with it.
function csv_import_feedback_test_header($bytes)
{
	$path = tempnam(sys_get_temp_dir(), 'pgcsv');
	file_put_contents($path, $bytes);
	$handle = pg_csv_open($path);
	$separator = pg_csv_read_separator($handle);
	$row = fgetcsv($handle, 100000, $separator, '"', '\\');
	fclose($handle);
	unlink($path);

	return array($separator, $row);
}

function test_csv_import_feedback_separator_from_header_line()
{
	pg_assert_same(',', pg_csv_separator("email_address,first_name\n"), 'comma');
	pg_assert_same(';', pg_csv_separator("Ad;Soyad;E-posta\r\n"), 'semicolon');
	pg_assert_same("\t", pg_csv_separator("email\tfirst name\n"), 'tab');
	pg_assert_same(',', pg_csv_separator("email\n"), 'one column');
	pg_assert_same(';', pg_csv_separator("\"Last, First\";email;city\n"), 'most wins');
	pg_assert_same(',', pg_csv_separator(''), 'empty');
}

function test_csv_import_feedback_read_separator_keeps_first_row()
{
	pg_assert_same(array(';', array('Ad', 'Soyad', 'E-posta')), csv_import_feedback_test_header("Ad;Soyad;E-posta\nAli;Kaya;ali@x.com\n"), 'semicolon');
	pg_assert_same(array(';', array('email_address', 'first_name')), csv_import_feedback_test_header("\xEF\xBB\xBF\"email_address\";\"first_name\"\nali@x.com;Ali\n"), 'byte order mark and quotes');
	pg_assert_same(array(',', array('email', 'name')), csv_import_feedback_test_header("email,name\rali@x.com,Ali\r"), 'CR-only file');
	pg_assert_same(array(',', false), csv_import_feedback_test_header(''), 'empty file');
}

function test_csv_import_feedback_row_lines()
{
	pg_assert_same(1, pg_csv_row_lines(array('Ali', 'ali@x.com')), 'plain row');
	pg_assert_same(1, pg_csv_row_lines(array(null)), 'blank line');
	pg_assert_same(3, pg_csv_row_lines(array("one\ntwo\r\nthree", 'x')), 'quoted line breaks');
}

function test_csv_import_feedback_blank_row()
{
	pg_assert_same(true, pg_csv_row_blank(array(null)), 'blank line');
	pg_assert_same(true, pg_csv_row_blank(array('', ' ', "\t")), 'separators only');
	pg_assert_same(false, pg_csv_row_blank(array('', 'x')), 'one value');
	pg_assert_same(false, pg_csv_row_blank(array('0')), 'zero is a value');
}

function test_csv_import_feedback_problem_list_capped()
{
	pg_assert_same(array(), pg_csv_problem_list(array()), 'none');

	$problems = array();

	for ($line = 2; $line <= 24; $line++) {
		$problems[] = array($line, 'No e-mail address.');
	}

	$list = pg_csv_problem_list($problems);

	pg_assert_same(21, count($list), 'twenty and a last line');
	pg_assert_same('Line 2: No e-mail address.', $list[0], 'first');
	pg_assert_same('Line 21: No e-mail address.', $list[19], 'twentieth');
	pg_assert_same('and 3 more line(s) that could not be used.', $list[20], 'the rest counted');

	pg_assert_same(array('Line 7: x'), pg_csv_problem_list(array(array(7, 'x'))), 'one');
	pg_assert_same(2, count(pg_csv_problem_list(array_slice($problems, 0, 3), 1)), 'own limit');
}

function test_csv_import_feedback_headings_text()
{
	pg_assert_same('Ad, Soyad, E-posta', pg_csv_headings_text(array('Ad', ' Soyad ', 'E-posta', '')), 'trimmed, blanks left out');
	pg_assert_same('Ad;Soyad', pg_csv_headings_text(array('Ad;Soyad')), 'one cell');
	pg_assert_same('', pg_csv_headings_text(array('', null)), 'none');
}
