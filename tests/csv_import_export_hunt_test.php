<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's CSV imports and exports: a Windows-1254 file (Excel's Turkish
 * "CSV (Comma delimited)") read as UTF-8 by pg_csv_open(), the formula guard
 * on the products and campaign profiles exports and its removal on their
 * imports, and the time limit of the importers that write a row at a time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/export_products_f.php');
require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');

// The rows fgetcsv() reads from $bytes through pg_csv_open().
function csv_import_export_hunt_test_rows($bytes, $separator = ',')
{
	$path = tempnam(sys_get_temp_dir(), 'pgcsv');
	file_put_contents($path, $bytes);
	$handle = pg_csv_open($path);
	$rows = array();

	while (($row = fgetcsv($handle, 100000, $separator, '"', '\\')) !== false) {
		$rows[] = $row;
	}

	fclose($handle);
	unlink($path);

	return $rows;
}

function csv_import_export_hunt_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_csv_open_reads_a_windows_1254_file_as_utf8()
{
	$rows = csv_import_export_hunt_test_rows("first_name;last_name;city\nAy\xFEe;G\xFCral;\xDDstanbul\n\xDE\xFCkr\xFC;\xC7a\xF0lar;I\xF0d\xFDr\n", ';');

	pg_assert_same(array('first_name', 'last_name', 'city'), $rows[0], 'heading');
	pg_assert_same(array('Ayşe', 'Güral', 'İstanbul'), $rows[1], 'first row');
	pg_assert_same(array('Şükrü', 'Çağlar', 'Iğdır'), $rows[2], 'second row');
}

function test_csv_open_leaves_utf8_lines_as_they_are()
{
	// A UTF-8 file stays byte for byte, with or without its mark.
	$utf8 = "name,city\nAyşe,İstanbul\n";
	pg_assert_same(array(array('name', 'city'), array('Ayşe', 'İstanbul')), csv_import_export_hunt_test_rows($utf8), 'UTF-8');
	pg_assert_same(array(array('name', 'city'), array('Ayşe', 'İstanbul')), csv_import_export_hunt_test_rows("\xEF\xBB\xBF" . $utf8), 'UTF-8 with mark');

	// One damaged line is read as Windows-1254; the UTF-8 lines beside it
	// are not turned into mojibake.
	$rows = csv_import_export_hunt_test_rows("name,city\nAyşe,İstanbul\nG\xFCral,\xC7orum\n");
	pg_assert_same(array('Ayşe', 'İstanbul'), $rows[1], 'UTF-8 line');
	pg_assert_same(array('Güral', 'Çorum'), $rows[2], 'Windows-1254 line');
}

function test_csv_open_converts_a_windows_1254_file_with_cr_breaks()
{
	$rows = csv_import_export_hunt_test_rows("name\rG\xFCral\r\xDE\xFCkr\xFC\r");

	pg_assert_same(array(array('name'), array('Güral'), array('Şükrü')), $rows, 'CR file');
}

function test_products_export_csv_line_guards_text_cells_only()
{
	$line = pg_products_export_csv_line(array('=HYPERLINK("x")', '-12.50', '+90 532', '@brand', 'plain'), array(true, false, true, true, true));

	pg_assert_same('"\'=HYPERLINK(""x"")","-12.50","\'+90 532","\'@brand","plain"' . "\n", $line, 'guarded line');
}

function test_products_export_table_marks_text_columns_for_the_guard()
{
	$source = csv_import_export_hunt_test_source('export_products_f.php');

	pg_assert_true(strpos($source, "'guard' => \$guard") !== false, 'the table names its guarded columns');
	pg_assert_true(strpos($source, 'pg_products_export_csv_line($row, $guard)') !== false, 'the CSV writes rows through the guarded line');
	// The workbook writes every cell as an inline string, which never runs.
	pg_assert_same(false, strpos(substr($source, strpos($source, 'function pg_products_export_sheet')), 'pg_csv_formula_guard'), 'the workbook is not guarded');
}

function test_products_import_takes_the_guard_off()
{
	pg_assert_same(array('=1+1', '-5', "'plain", '+90 532'), pg_products_import_unguard_row(array("'=1+1", "'-5", "'plain", "'+90 532")), 'unguarded');
	pg_assert_same(array(), pg_products_import_unguard_row(array()), 'empty row');

	$source = csv_import_export_hunt_test_source('import_products.php');
	pg_assert_true(strpos($source, '$row = pg_products_import_unguard_row($row);') !== false, 'the rows loop unguards');
}

function test_campaign_profiles_export_guards_and_import_unguards()
{
	$export = csv_import_export_hunt_test_source('view_email_campaign_profiles.php');

	foreach (array("['name']", '$action_item', "['subject']", "['body']", '$page_name', "['from_name']", "['from_email_address']", "['reply_email_address']", "['bcc_email_address']", "['created_username']", "['last_modified_username']") as $cell) {
		pg_assert_true(strpos($export, 'csv_cell(' . (($cell[0] === '$') ? $cell : '$email_campaign_profile' . $cell) . ')') !== false, $cell . ' is guarded');
	}

	pg_assert_same(false, strpos($export, 'escape_csv($email_campaign_profile'), 'no unguarded text cell');

	$import = csv_import_export_hunt_test_source('import_email_campaign_profiles.php');
	pg_assert_true(strpos($import, "\$row = array_map('pg_csv_formula_unguard', \$row);") !== false, 'the import unguards');
}

function test_row_by_row_importers_raise_the_time_limit()
{
	foreach (array('import_contacts.php', 'import_submitted_forms.php', 'import_email_campaign_profiles.php', 'import_key_codes.php', 'import_shipments.php') as $file) {
		pg_assert_true(strpos(csv_import_export_hunt_test_source($file), "ini_set('max_execution_time', '9999');") !== false, $file . ' raises the time limit');
	}
}
