<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Every column the Contacts and Products importers read is written by the
 * matching export, so an exported record comes back whole when the file is
 * imported as new records.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function export_import_columns_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The body of a top-level function, from its declaration to the closing brace
// at the start of a line.
function export_import_columns_function_body($source, $name)
{
	if (!preg_match('/^function ' . preg_quote($name, '/') . '\(.*?^\}/ms', $source, $match)) {
		return '';
	}

	return $match[0];
}

// The table columns convert_column_name() maps a file heading to.
function export_import_columns_importer_columns($file)
{
	$body = export_import_columns_function_body(export_import_columns_source($file), 'convert_column_name');
	$columns = array();

	// return('first_name'); and return 'custom_field_1';
	preg_match_all("/return\s*\(?\s*'([a-z0-9_]+)'/", $body, $matches);
	$columns = array_merge($columns, $matches[1]);

	// return pg_..._ready() ? 'compare_at_price' : false;
	preg_match_all("/\?\s*'([a-z0-9_]+)'\s*:/", $body, $matches);
	$columns = array_merge($columns, $matches[1]);

	// case 'hs_code': case 'origin_country': return ... ? $column_name : false;
	if (preg_match_all("/((?:case\s+'[a-z0-9_]+':\s*)+)return[^;]*\\\$column_name/", $body, $groups)) {
		foreach ($groups[1] as $group) {
			preg_match_all("/case\s+'([a-z0-9_]+)'/", $group, $matches);
			$columns = array_merge($columns, $matches[1]);
		}
	}

	return array_values(array_unique($columns));
}

function test_export_import_columns_products_export_writes_every_imported_column()
{
	$body = export_import_columns_function_body(export_import_columns_source('export_products_f.php'), 'pg_products_export_columns');
	preg_match_all("/'column'\s*=>\s*'([a-z0-9_]+)'/", $body, $matches);
	$exported = $matches[1];

	$imported = export_import_columns_importer_columns('import_products.php');
	pg_assert_true(count($imported) > 50, 'the importer column map was read');
	pg_assert_true(in_array('minimum_quantity', $imported, true), 'the importer reads minimum_quantity');

	foreach ($imported as $column) {
		pg_assert_true(in_array($column, $exported, true), 'the products export writes ' . $column);
	}
}

function test_export_import_columns_contacts_export_writes_every_imported_column()
{
	$source = export_import_columns_source('view_contacts.php');

	$headings = array();

	// The heading line: its literals and the affiliate headings it appends.
	if (preg_match("/print 'first_name,.*?\"\\\\n\";/", $source, $match)) {
		preg_match_all("/'([a-z0-9_,]+)'/", $match[0], $literals);
		foreach ($literals[1] as $literal) {
			$headings = array_merge($headings, explode(',', trim($literal, ',')));
		}
	}

	if (preg_match("/output_affiliate_headings = '(,[a-z0-9_,]+)'/", $source, $match)) {
		$headings = array_merge($headings, explode(',', trim($match[1], ',')));
	}

	// The export branch alone, so a column read elsewhere on the screen does not count.
	$start = strpos($source, "== 'Export Contacts'");
	$end = strpos($source, "== 'Delete Contacts'");
	pg_assert_true($start !== false && $end > $start, 'the export branch was found');
	$export = substr($source, (int) $start, (int) $end - (int) $start);

	$imported = export_import_columns_importer_columns('import_contacts.php');
	pg_assert_true(count($imported) > 30, 'the importer column map was read');

	foreach ($imported as $column) {
		pg_assert_true(in_array($column, $headings, true), 'the contacts export header has ' . $column);

		// The row prints each heading's value, and the SELECT reads it.
		pg_assert_true(strpos($export, "\$row['" . $column . "']") !== false, 'the contacts export row writes ' . $column);
		pg_assert_true(preg_match('/contacts\.' . $column . '\b/', $export) === 1, 'the contacts export reads ' . $column);
	}
}
