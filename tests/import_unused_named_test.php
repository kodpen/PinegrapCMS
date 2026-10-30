<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Import Products, Import Submitted Forms and Import Campaign Profiles read a
 * ';' or tab file with the separator of its header line and name what they
 * did not use: unknown headings, rows of existing products left alone,
 * file-upload cells that do not resolve to a file. The loops need a
 * database, so the screens are checked for the shared CSV helpers of
 * includes/fn/core.php (tests/csv_import_feedback_test.php covers those).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function import_unused_named_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Every fgetcsv() of the three screens reads with the separator of the
// header line, not a fixed comma.
function test_import_unused_named_screens_read_the_header_separator()
{
	foreach (array('import_products.php', 'import_submitted_forms.php', 'import_email_campaign_profiles.php') as $file) {
		$source = import_unused_named_test_source($file);

		pg_assert_true(strpos($source, 'pg_csv_read_separator($handle)') !== false, $file . ' reads the separator');
		pg_assert_same(0, preg_match('/fgetcsv\([^;]*?,\s*[\'"],[\'"]/', $source), $file . ' has no fgetcsv() with a fixed comma');
		pg_assert_true(preg_match_all('/fgetcsv\(\$handle, 100000, \$separator,/', $source) >= 2, $file . ' reads header and rows with $separator');
	}
}

// A converted spreadsheet is written with commas by the converter, so only
// an uploaded .csv file has its separator read.
function test_import_unused_named_products_spreadsheet_keeps_comma()
{
	$source = import_unused_named_test_source('import_products.php');
	$separator = strpos($source, '$separator = ($import_temp_path != \'\') ? \',\' : pg_csv_read_separator($handle);');

	pg_assert_true($separator !== false, 'separator read only for a .csv upload');
}

// Unknown headings are named on the screen the import returns to.
function test_import_unused_named_unknown_headings_listed()
{
	$expect = array(
		'import_products.php' => '$liveform_view_products->add_warning(',
		'import_submitted_forms.php' => '$liveform->add_warning(',
		'import_email_campaign_profiles.php' => '$liveform_view_email_campaign_profiles->add_warning(',
	);

	foreach ($expect as $file => $warning) {
		$source = import_unused_named_test_source($file);

		pg_assert_true(strpos($source, 'pg_csv_headings_text($unknown_headings)') !== false, $file . ' names unknown headings');
		pg_assert_true(strpos($source, $warning) !== false, $file . ' warns on the result screen');
		pg_assert_true(strpos($source, "array_map('h', pg_csv_problem_list(") !== false || $file === 'import_email_campaign_profiles.php', $file . ' lists skipped lines escaped');
	}
}

// With Update Existing Products off, a row of an existing product is named
// and counted, not dropped without a word.
function test_import_unused_named_products_existing_rows_named()
{
	$source = import_unused_named_test_source('import_products.php');
	$update = strpos($source, "if (\$update_existing_products == '1') {");
	$left = strpos($source, '$rows_left_alone[] = array($row_number,');

	pg_assert_true(($update !== false) && ($left !== false) && ($update < $left), 'existing product row recorded after the update branch');
	pg_assert_true(strpos($source, 'pg_csv_problem_list($rows_left_alone)') !== false, 'existing product rows listed');
}

// A file-upload cell that names no file, or a file in a folder the user
// cannot edit, is named with its line.
function test_import_unused_named_submitted_forms_file_cells_named()
{
	$source = import_unused_named_test_source('import_submitted_forms.php');

	pg_assert_true(strpos($source, '$file_problems[] = array($line,') !== false, 'unresolved file cell recorded');
	pg_assert_true(strpos($source, 'pg_csv_problem_list($file_problems)') !== false, 'unresolved file cells listed');
	pg_assert_true(strpos($source, '$next_line += pg_csv_row_lines($row);') !== false, 'rows numbered by file line');
}
