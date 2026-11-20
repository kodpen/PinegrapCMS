<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ceilings on what an .ods makes of repeated rows, repeated
 * columns and merged ranges, as the product importer
 * (pg_products_import_to_csv(), import_products_f.php) and the bank
 * statement importer (erp_bank_read_file(), includes/erp/bank_import.php)
 * meet them through the sheet reader (includes/fn/sheet_read.php). An honest
 * file, laid out the way LibreOffice saves one, reads as written; a few
 * hundred bytes that ask for millions of cells are turned away before they
 * are made.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/bank_import.php');

// An .ods holding these table:table elements, in a folder of its own.
function ods_cell_ceiling_test_file($tables)
{
	$dir = sys_get_temp_dir() . '/pg_ods_ceiling_test_' . bin2hex(random_bytes(4));
	mkdir($dir);
	$path = $dir . '/sheet.ods';

	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::CREATE);
	$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
	$zip->addFromString('meta.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:meta/></office:document-meta>');
	$zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2"><office:body><office:spreadsheet>' . $tables . '</office:spreadsheet></office:body></office:document-content>');
	$zip->close();

	return $path;
}

function ods_cell_ceiling_test_clean($path)
{
	unlink($path);
	rmdir(dirname($path));
}

// A text cell, with any further attributes.
function ods_cell_ceiling_test_text($text, $attributes = '')
{
	return '<table:table-cell office:value-type="string"' . $attributes . '><text:p>' . $text . '</text:p></table:table-cell>';
}

// A number cell, with any further attributes.
function ods_cell_ceiling_test_number($value, $attributes = '')
{
	return '<table:table-cell office:value-type="float" office:value="' . $value . '"' . $attributes . '><text:p>' . $value . '</text:p></table:table-cell>';
}

// The product importer's CSV as rows, or its error.
function ods_cell_ceiling_test_products($path)
{
	$error = '';
	$csv = pg_products_import_to_csv($path, 'products.ods', $error);

	if ($csv === '') {
		return $error;
	}

	$rows = array();
	$handle = fopen($csv, 'r');

	while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
		$rows[] = $row;
	}

	fclose($handle);
	unlink($csv);

	return $rows;
}

// The bank statement importer's rows, or its error.
function ods_cell_ceiling_test_bank($path)
{
	$read = erp_bank_read_file($path, 'statement.ods');

	return ($read['error'] !== '') ? $read['error'] : $read['rows'];
}

// The sheet reader's first sheet: row number => column => value.
function ods_cell_ceiling_test_cells($path)
{
	$error = '';
	$cells = array();

	foreach (pg_sheet_rows($path, 'ods', $error) as $number => $row) {
		foreach ($row as $column => $cell) {
			if ($cell !== null) {
				$cells[$number][$column] = $cell['v'];
			}
		}
	}

	return ($error !== '') ? $error : $cells;
}

// A statement laid out the way LibreOffice saves one with a formatted heading
// row and column: every row filled out with an empty run to the sheet's last
// column, the rest of the sheet one empty row repeated to its last row, a
// merged title over the heading, two identical lines written as one row
// repeated twice and three equal amounts as one cell repeated three times.
// Both importers read it as its cells say.
function test_ods_cell_ceiling_honest_file_reads_as_written()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$t = 'ods_cell_ceiling_test_text';
	$n = 'ods_cell_ceiling_test_number';
	$fill = function ($used) {
		return '<table:table-cell table:style-name="ce1" table:number-columns-repeated="' . (16384 - $used) . '"/>';
	};

	$path = ods_cell_ceiling_test_file('<table:table table:name="Hareketler">'
		. '<table:table-column table:number-columns-repeated="16384"/>'
		. '<table:table-row>' . $t('Hesap Hareketleri', ' table:number-columns-spanned="5" table:number-rows-spanned="1"') . '<table:covered-table-cell table:number-columns-repeated="4"/>' . $fill(5) . '</table:table-row>'
		. '<table:table-row>' . $t('Tarih') . $t('Açıklama') . $t('Tutar') . $t('Masraf') . $t('İade') . $fill(5) . '</table:table-row>'
		. '<table:table-row>' . $t('2026-09-23') . $t('Kira') . $n('-800.5') . $n('0', ' table:number-columns-repeated="2"') . $fill(5) . '</table:table-row>'
		. '<table:table-row table:number-rows-repeated="2">' . $t('2026-09-24') . $t('Aidat') . $n('-100') . $n('0', ' table:number-columns-repeated="2"') . $fill(5) . '</table:table-row>'
		. '<table:table-row>' . $t('2026-09-25') . $t('Faiz') . $n('12', ' table:number-columns-repeated="3"') . $fill(5) . '</table:table-row>'
		. '<table:table-row table:number-rows-repeated="1048570"><table:table-cell table:number-columns-repeated="16384"/></table:table-row>'
		. '</table:table>');

	pg_assert_same(array(
		array('Hesap Hareketleri', '', '', '', ''),
		array('Tarih', 'Açıklama', 'Tutar', 'Masraf', 'İade'),
		array('2026-09-23', 'Kira', '-800.5', '0', '0'),
		array('2026-09-24', 'Aidat', '-100', '0', '0'),
		array('2026-09-24', 'Aidat', '-100', '0', '0'),
		array('2026-09-25', 'Faiz', '12', '12', '12'),
	), ods_cell_ceiling_test_products($path), 'product importer');

	pg_assert_same(array(
		array('Tarih', 'Açıklama', 'Tutar', 'Masraf', 'İade'),
		array('2026-09-23', 'Kira', '-800.50', '0', '0'),
		array('2026-09-24', 'Aidat', '-100', '0', '0'),
		array('2026-09-24', 'Aidat', '-100', '0', '0'),
		array('2026-09-25', 'Faiz', '12', '12', '12'),
	), ods_cell_ceiling_test_bank($path), 'bank statement importer, title block dropped');

	ods_cell_ceiling_test_clean($path);
}

// A cell after an empty run lands in the column the run ends at, across the
// two- and three-letter boundaries; empty rows are counted.
function test_ods_cell_ceiling_empty_run_steps_to_the_right_column()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$t = 'ods_cell_ceiling_test_text';

	$path = ods_cell_ceiling_test_file('<table:table table:name="S">'
		. '<table:table-row>' . $t('a') . '<table:table-cell table:number-columns-repeated="24"/>' . $t('z') . '<table:table-cell/>' . $t('ab') . '</table:table-row>'
		. '<table:table-row>' . $t('a') . '<table:table-cell table:number-columns-repeated="700"/>' . $t('zz') . '<table:table-cell/>' . $t('aab') . '</table:table-row>'
		. '<table:table-row table:number-rows-repeated="3"><table:table-cell table:number-columns-repeated="16384"/></table:table-row>'
		. '<table:table-row>' . $t('last') . '</table:table-row>'
		. '</table:table>');

	pg_assert_same(array(
		1 => array(0 => 'a', 25 => 'z', 27 => 'ab'),
		2 => array(0 => 'a', 701 => 'zz', 703 => 'aab'),
		6 => array(0 => 'last'),
	), ods_cell_ceiling_test_cells($path), 'Z, AB, ZZ and AAB; nothing made in the runs');

	ods_cell_ceiling_test_clean($path);
}

// An empty run of two billion columns is stepped over, not walked: the row
// before and after it reads at once.
function test_ods_cell_ceiling_long_empty_run_is_free()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$t = 'ods_cell_ceiling_test_text';

	$path = ods_cell_ceiling_test_file('<table:table table:name="S">'
		. '<table:table-row>' . $t('Tarih') . $t('Tutar') . '<table:table-cell table:number-columns-repeated="2000000000"/></table:table-row>'
		. '<table:table-row>' . $t('2026-09-23') . $t('-5') . '<table:table-cell table:number-columns-repeated="2000000000"/></table:table-row>'
		. '</table:table>');

	$started = microtime(true);
	$rows = ods_cell_ceiling_test_products($path);

	pg_assert_same(array(array('Tarih', 'Tutar'), array('2026-09-23', '-5')), $rows, 'read');
	pg_assert_true((microtime(true) - $started) < 5, 'at once');

	ods_cell_ceiling_test_clean($path);
}

// A merged range that covers nothing, runs backwards or reaches past column
// ZZZ makes no cells: the sheet reader takes a span as no more than the one
// value it stands on, and both importers read that value.
function test_ods_cell_ceiling_impossible_merge_reads_the_value()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$t = 'ods_cell_ceiling_test_text';

	foreach (array('table:number-columns-spanned="0"', 'table:number-columns-spanned="-3"', 'table:number-rows-spanned="-1"', 'table:number-columns-spanned="20000"') as $span) {
		$path = ods_cell_ceiling_test_file('<table:table table:name="S"><table:table-row>' . $t('m', ' ' . $span) . '</table:table-row></table:table>');

		pg_assert_same(array(array('m')), ods_cell_ceiling_test_products($path), $span . ', product import');
		pg_assert_same(array(array('m')), ods_cell_ceiling_test_bank($path), $span . ', bank statement');
		ods_cell_ceiling_test_clean($path);
	}
}

// A file of a few hundred bytes that asks for millions of cells -- a value
// repeated down and across, a merged range, the same on a second sheet, or a
// single value far down and far right -- is turned away by both importers as
// too large, before the cells are made.
function test_ods_cell_ceiling_importers_turn_away_expansion()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$t = 'ods_cell_ceiling_test_text';
	$heading = '<table:table-row>' . $t('Tarih') . $t('Tutar') . '</table:table-row>';
	$large = lang('The file is too large.');

	$cases = array(
		'repeated value' => '<table:table table:name="S">' . $heading . '<table:table-row table:number-rows-repeated="100000">' . $t('x', ' table:number-columns-repeated="1000"') . '</table:table-row></table:table>',
		'one past the cell ceiling' => '<table:table table:name="S">' . $heading . '<table:table-row table:number-rows-repeated="' . (PG_SHEET_ODS_MAX_CELLS - 1) . '">' . $t('x') . '</table:table-row></table:table>',
		'merged range' => '<table:table table:name="S">' . $heading . '<table:table-row>' . $t('x', ' table:number-columns-spanned="16000" table:number-rows-spanned="100000"') . '</table:table-row></table:table>',
		'second sheet' => '<table:table table:name="S">' . $heading . '</table:table><table:table table:name="T"><table:table-row table:number-rows-repeated="100000">' . $t('x', ' table:number-columns-repeated="1000"') . '</table:table-row></table:table>',
		'far corner' => '<table:table table:name="S">' . $heading . '<table:table-row table:number-rows-repeated="1048000"><table:table-cell table:number-columns-repeated="16000"/></table:table-row><table:table-row><table:table-cell table:number-columns-repeated="16000"/>' . $t('x') . '</table:table-row></table:table>',
		'used range one row past' => '<table:table table:name="S">' . $heading . '<table:table-row table:number-rows-repeated="' . (intdiv(PG_SHEET_ODS_MAX_RANGE, 1024) - 1) . '"><table:table-cell/></table:table-row><table:table-row><table:table-cell table:number-columns-repeated="1023"/>' . $t('x') . '</table:table-row></table:table>',
	);

	// The sheet reader, which both importers read through, makes no cells
	// of a merged range (its value stands in the first cell) and reads no
	// sheet but the first, so those two cost nothing and are read.
	$product_reads = array(
		'merged range' => array(array('Tarih', 'Tutar'), array('x', '')),
		'second sheet' => array(array('Tarih', 'Tutar')),
	);

	foreach ($cases as $label => $tables) {
		$path = ods_cell_ceiling_test_file($tables);
		clearstatcache();
		pg_assert_true(filesize($path) < 2048, $label . ': a small file');

		$started = microtime(true);
		pg_assert_same(isset($product_reads[$label]) ? $product_reads[$label] : $large, ods_cell_ceiling_test_products($path), $label . ': product importer');
		pg_assert_same(isset($product_reads[$label]) ? $product_reads[$label] : $large, ods_cell_ceiling_test_bank($path), $label . ': bank statement importer');
		pg_assert_true((microtime(true) - $started) < 5, $label . ': at once');

		ods_cell_ceiling_test_clean($path);
	}

	// The used range at its ceiling still reads: 1,024 rows by 1,024 columns.
	$path = ods_cell_ceiling_test_file('<table:table table:name="S">' . $heading . '<table:table-row table:number-rows-repeated="' . (intdiv(PG_SHEET_ODS_MAX_RANGE, 1024) - 2) . '"><table:table-cell/></table:table-row><table:table-row><table:table-cell table:number-columns-repeated="1023"/>' . $t('x') . '</table:table-row></table:table>');
	$rows = ods_cell_ceiling_test_products($path);
	pg_assert_true(is_array($rows) && (count($rows) === 2) && (count($rows[0]) === 1024) && ($rows[1][1023] === 'x'), 'used range at the ceiling');
	ods_cell_ceiling_test_clean($path);
}
