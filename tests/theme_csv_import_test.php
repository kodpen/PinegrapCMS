<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the system theme CSV import of Create Theme (add_theme_file.php):
 * the heading and the rows are checked before the theme's file record is
 * written, the rule columns are quoted (`row` is a reserved word in MySQL
 * 8), and a rule insert that still fails takes the record back out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/theme_file_form.php');

function test_theme_csv_import_columns_of_the_export_heading()
{
	pg_assert_same(
		'`area`, `row`, `col`, `module`, `region_type`, `region_name`, `property`, `value`',
		pg_theme_csv_import_columns(array('area', 'row', 'col', 'module', 'region_type', 'region_name', 'property', 'value')),
		'the heading the export writes');

	pg_assert_same(
		'`value`, `property`, `area`, `row`, `col`, `module`, `region_type`, `region_name`',
		pg_theme_csv_import_columns(array('Value', ' property ', 'AREA', 'row', 'col', 'module', 'region_type', 'region_name')),
		'any order and letter case, spaces around a name ignored');
}

function test_theme_csv_import_columns_refused()
{
	pg_assert_same(null, pg_theme_csv_import_columns(array()), 'no heading');
	pg_assert_same(null, pg_theme_csv_import_columns(array('area', 'row', 'col', 'module', 'region_type', 'region_name', 'property')), 'a column missing');
	pg_assert_same(null, pg_theme_csv_import_columns(array('area', 'row', 'col', 'module', 'region_type', 'region_name', 'property', 'value', 'area')), 'a column twice');
	pg_assert_same(null, pg_theme_csv_import_columns(array('area', 'row', 'col', 'module', 'region_type', 'region_name', 'property', 'my_value')), 'a name that only contains a column name');
	pg_assert_same(null, pg_theme_csv_import_columns(array('area', 'row', 'col', 'module', 'region_type', 'region_name', 'property', 'value) VALUES (1')), 'SQL in a heading');
	pg_assert_same(null, pg_theme_csv_import_columns(array('area', 'row', 'col', 'module', 'region_type', 'region_name', 'property', null)), 'an empty cell');
}

function test_theme_csv_import_checks_before_the_record()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_theme_file.php');

	$columns = strpos($source, '$rule_columns = pg_theme_csv_import_columns($columns);');
	$empty = strpos($source, 'if (!$rule_rows) {');
	$record = strpos($source, 'INSERT INTO files', (int) strpos($source, "'system_theme_csv_file'"));
	$rules = strpos($source, '(file_id, " . $rule_columns . ")');

	pg_assert_true($columns !== false, 'the heading is read by the helper');
	pg_assert_true($empty !== false, 'a file without rows is refused');
	pg_assert_true(($columns !== false) && ($record !== false) && ($columns < $record), 'the heading is checked before the record is written');
	pg_assert_true(($empty !== false) && ($record !== false) && ($empty < $record), 'the rows are read before the record is written');
	pg_assert_true($rules !== false, 'the rules are inserted under the quoted columns');
	pg_assert_false(strpos($source, '$data_list') !== false, 'no column list built from the raw heading');
	pg_assert_contains("db_q(\"DELETE FROM files WHERE id = ?\", array((string) \$theme_id));\n                output_error(lang('The file is not a valid System Theme CSV file')", $source, 'a failed rule insert takes the record back out');
}
