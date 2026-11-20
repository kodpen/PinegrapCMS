<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel lists' Export to Excel (includes/fn/list_excel.php):
 * the CSV read back, each column's type, the panel's dates, the file name,
 * the action's address, and the shared writer's money and seconds
 * (includes/fn/xlsx_write.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A CSV text as a stream at its start.
function list_excel_test_stream($text)
{
	$handle = fopen('php://memory', 'w+b');
	fwrite($handle, $text);
	rewind($handle);

	return $handle;
}

function test_list_excel_dates_read_the_panel_forms()
{
	pg_assert_same('2026-11-19', pg_list_excel_date('2026-11-19', 'month_day'), 'an ISO date');
	pg_assert_same('2026-11-19 17:05:09', pg_list_excel_date('2026-11-19 17:05:09', 'month_day'), 'an ISO date and time');
	pg_assert_same('2026-11-19 17:05:00', pg_list_excel_date('2026-11-19 17:05', 'month_day'), 'minutes only');
	pg_assert_same('2026-04-03 17:05:09', pg_list_excel_date('4/3/2026 5:05:09 PM +03', 'month_day'), 'month first, twelve hours');
	pg_assert_same('2026-03-04 17:05:09', pg_list_excel_date('4/3/2026 17:05:09 +03', 'day_month'), 'day first, 24 hours');
	pg_assert_same('2026-04-03 00:15:00', pg_list_excel_date('4/3/2026 12:15:00 AM', 'month_day'), 'midnight on a twelve-hour clock');
	pg_assert_same('2026-11-19', pg_list_excel_date('19/11/2026', 'day_month'), 'a site date');
	pg_assert_same(null, pg_list_excel_date('19/11/2026', 'month_day'), 'no 19th month');
	pg_assert_same(null, pg_list_excel_date('2026-02-30', 'month_day'), 'no such day');
	pg_assert_same(null, pg_list_excel_date('Immediate', 'month_day'), 'a word');
	pg_assert_same(null, pg_list_excel_date('4/3/2026 13:05:09 PM', 'month_day'), 'no 13 PM');
}

function test_list_excel_cells_are_typed_by_their_column()
{
	pg_assert_same(array('money' => 12.5), pg_list_excel_cell('12.50', 'money', ',', 'month_day'), 'money');
	pg_assert_same(array('money' => -3.0), pg_list_excel_cell('-3', 'money', ',', 'month_day'), 'whole money');
	pg_assert_same(array('money' => 1234.56), pg_list_excel_cell('1234,56', 'money', ';', 'month_day'), 'a decimal comma in a semicolon file');
	pg_assert_same('1234,56', pg_list_excel_cell('1234,56', 'money', ',', 'month_day'), 'a comma is no decimal in a comma file');
	pg_assert_same(42, pg_list_excel_cell('42', 'number', ',', 'month_day'), 'a whole number');
	pg_assert_same(2.5, pg_list_excel_cell('2.5', 'number', ',', 'month_day'), 'a decimal number');
	pg_assert_same('n/a', pg_list_excel_cell('n/a', 'number', ',', 'month_day'), 'not a number stays text');
	pg_assert_same(array('date' => '2026-11-19'), pg_list_excel_cell('2026-11-19', 'date', ',', 'month_day'), 'a date');
	pg_assert_same('Immediate', pg_list_excel_cell('Immediate', 'date', ',', 'month_day'), 'not a date stays text');
	pg_assert_same('00123', pg_list_excel_cell('00123', 'text', ',', 'month_day'), 'a postal code keeps its zeros');
	pg_assert_same('=1+1', pg_list_excel_cell("'=1+1", 'text', ',', 'month_day'), 'the formula guard is dropped: a text cell is never a formula');
	pg_assert_same('', pg_list_excel_cell('', 'money', ',', 'month_day'), 'an empty cell');
}

function test_list_excel_table_reads_the_csv_back()
{
	$csv = "\xEF\xBB\xBF" . '"n","name","total","when"' . "\n"
		. '"1","Ayşe ""Kaya""","12.50","2026-11-19 10:00:00"' . "\n"
		. '"2","two' . "\n" . 'lines","x","soon"' . "\n\n";
	$table = pg_list_excel_table(list_excel_test_stream($csv), array('total' => 'money', 3 => 'date', 'n' => 'number'), 'month_day');

	pg_assert_same(array('n', 'name', 'total', 'when'), $table['headings'], 'the heading without the byte order mark');
	pg_assert_same(array(1, 'Ayşe "Kaya"', array('money' => 12.5), array('date' => '2026-11-19 10:00:00')), $table['rows'][0], 'typed by heading and by index');
	pg_assert_same(array(2, "two\nlines", 'x', 'soon'), $table['rows'][1], 'a quoted line break; cells that are not of their type stay text');
	pg_assert_same(2, count($table['rows']), 'a blank line is no row');

	$semicolon = pg_list_excel_table(list_excel_test_stream("Account;Total\nAcme, Inc.;1250,50\n"), array(1 => 'money'), 'month_day');

	pg_assert_same(array(array('Acme, Inc.', array('money' => 1250.5))), $semicolon['rows'], 'a semicolon file with a decimal comma');
}

function test_list_excel_delimiter_is_the_most_used_outside_quotes()
{
	pg_assert_same(',', pg_list_excel_delimiter('"a;b","c"'), 'a semicolon inside quotes does not count');
	pg_assert_same(';', pg_list_excel_delimiter('a;b;c'), 'semicolons');
	pg_assert_same("\t", pg_list_excel_delimiter("a\tb\tc"), 'tabs');
	pg_assert_same(',', pg_list_excel_delimiter('single'), 'one column');
}

function test_list_excel_names_and_addresses()
{
	pg_assert_same('Content-Disposition: attachment; filename="orders.xlsx"', pg_list_excel_disposition('Content-Disposition: attachment; filename="orders.csv"'), 'a quoted name');
	pg_assert_same("Content-Disposition: attachment; filename=\"x.xlsx\"; filename*=UTF-8''%C3%BCr%C3%BCn.xlsx", pg_list_excel_disposition("Content-Disposition: attachment; filename=\"x.csv\"; filename*=UTF-8''%C3%BCr%C3%BCn.csv"), 'the UTF-8 name too');
	pg_assert_same('orders', pg_list_excel_file_name('Content-Disposition: attachment; filename="orders.csv"', 'export.csv'), 'the sheet title from the file name');
	pg_assert_same('view_short_links.php?export=csv&excel=1', pg_list_excel_url('view_short_links.php?export=csv'), 'excel=1 after a query');
	pg_assert_same('export.php?excel=1', pg_list_excel_url('export.php'), 'excel=1 as the query');

	$action = pg_list_excel_action('a.php?csv=1');
	pg_assert_same('a.php?csv=1&excel=1', $action['url'], 'the toolbar action');
	pg_assert_true(strpos(pg_list_excel_button('menu-item'), 'bi-file-earmark-excel me-3') !== false, 'a menu item spaces its icon');
	pg_assert_true(strpos(pg_list_excel_button(), 'name="excel" value="1"') !== false, 'the form button names the Excel form');
}

function test_list_excel_asked_only_by_excel_one()
{
	$get = $_GET;
	$_GET = array('excel' => '1');
	pg_assert_true(pg_list_excel_asked(), 'excel=1');
	$_GET = array('excel' => array('1'));
	pg_assert_true(!pg_list_excel_asked(), 'an array is not asking');
	$_GET = array();
	pg_assert_true(!pg_list_excel_asked(), 'nothing asked');
	pg_assert_true(!pg_list_excel_capture(), 'no capture when not asked');
	pg_assert_true(!pg_list_excel_capturing(), 'nothing held back');
	$_GET = $get;
}

function test_list_excel_writer_money_and_seconds()
{
	pg_assert_same(46345.7119097222, pg_xlsx_date_serial('2026-11-19 17:05:09'), 'a time with seconds');
	pg_assert_same(46345.5, pg_xlsx_date_serial('2026-11-19 12:00:00'), 'no seconds, the same serial as before');
	pg_assert_same('<row r="2"><c r="A2" s="4"><v>1250.5</v></c><c r="B2" s="3"><v>46345.5</v></c></row>',
		pg_xlsx_sheet_rows(array(array(array('money' => 1250.5), array('date' => '2026-11-19 12:00:00'))), 2), 'money and a date with seconds');

	if (!class_exists('ZipArchive')) {
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgle');
	pg_assert_true(pg_xlsx_write_plain(array('Total'), array(array(array('money' => 3))), $path, 'Orders'), 'written');

	$zip = new ZipArchive();
	$zip->open($path);
	$styles = (string) $zip->getFromName('xl/styles.xml');
	$sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
	$zip->close();
	@unlink($path);

	pg_assert_true(strpos($styles, '<cellXfs count="5">') !== false && strpos($styles, '<xf numFmtId="4"') !== false, 'a money format');
	pg_assert_true(strpos($sheet, 'state="frozen"') !== false && strpos($sheet, '<c r="A1" s="1"') !== false, 'a frozen, bold heading');
}
