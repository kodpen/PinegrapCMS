<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for taking a bank statement in as a workbook
 * (includes/erp/bank_import.php): erp_bank_sheet_value(), which turns a
 * number cell into the text a CSV would have carried,
 * erp_bank_rows_from_heading(), which drops the bank's title block, and
 * erp_bank_read_file() over a small .xlsx and .ods written here. The older
 * .xls goes through PHPExcel, which is checked on the sandbox rather than
 * here. A CSV statement's separator (erp_bank_read_csv()) and the amounts
 * erp_bank_amount() reads are checked too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bank_import.php');

// Whole numbers keep their digits; the rest is two decimals with a point.
function test_erp_bank_sheet_value_numbers()
{
	pg_assert_same('1250', erp_bank_sheet_value('1250'));
	pg_assert_same('-75', erp_bank_sheet_value('-75'));
	pg_assert_same('1234.56', erp_bank_sheet_value('1234.5599999999999'));
	pg_assert_same('-0.10', erp_bank_sheet_value('-0.1'));
	pg_assert_same('', erp_bank_sheet_value(''));
	pg_assert_same('n/a', erp_bank_sheet_value('n/a'));
}

// A date cell is a day count; it comes out as Y-m-d, with the time when it has one.
function test_erp_bank_sheet_value_dates()
{
	pg_assert_same('2026-09-23', erp_bank_sheet_value('46288', 'date'));
	pg_assert_same('2026-09-23 12:30', erp_bank_sheet_value('46288.520833333336', 'date'));
	pg_assert_same('1', erp_bank_sheet_value('1', 'b'));
	pg_assert_same('0', erp_bank_sheet_value('0', 'b'));
}

// The bank's title block goes; the heading over the first transaction stays.
function test_erp_bank_rows_from_heading_drops_the_title_block()
{
	$rows = array(
		array('Example Bank', ''),
		array('Account statement 01.09.2026 - 30.09.2026', ''),
		array('IBAN TR00 0000 0000 0000 0000 0000 00', ''),
		array('Date', 'Description', 'Amount', 'Balance'),
		array('23.09.2026', 'Invoice 1042', '1.250,00 TRY', '5.000,00'),
		array('24.09.2026', 'Rent', '-800,00', '4.200,00'),
	);

	$kept = erp_bank_rows_from_heading($rows);

	pg_assert_same(3, count($kept), 'rows');
	pg_assert_same('Date', $kept[0][0], 'heading first');
}

// A period written as two dates is not a transaction, nor is a reference number.
function test_erp_bank_rows_from_heading_needs_an_amount()
{
	$rows = array(
		array('Period', '01.09.2026', '30.09.2026'),
		array('Printed', '2026-10-01', 'Ref. TX-20260901-ABCDEF'),
		array('Tarih', 'Açıklama', 'Tutar'),
		array('2026-09-23', 'EFT', '1250.00'),
	);

	$kept = erp_bank_rows_from_heading($rows);

	pg_assert_same('Tarih', $kept[0][0], 'heading first');
	pg_assert_same(2, count($kept), 'rows');
}

// A file whose first row is a transaction, or that has none, is left as it is.
function test_erp_bank_rows_from_heading_leaves_others()
{
	$first = array(array('23.09.2026', 'EFT', '100,00'), array('24.09.2026', 'EFT', '200,00'));
	pg_assert_same($first, erp_bank_rows_from_heading($first), 'first row a transaction');

	$none = array(array('Date', 'Amount'), array('no', 'transactions'));
	pg_assert_same($none, erp_bank_rows_from_heading($none), 'none found');
}

// A small workbook as banks write them: a title, shared strings, a date
// format, inline text and an amount stored as a binary float.
function test_erp_bank_read_file_xlsx()
{
	if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
		return;
	}

	$dir = sys_get_temp_dir() . '/pg_bank_test_' . bin2hex(random_bytes(4));
	mkdir($dir);
	$path = $dir . '/statement.xlsx';

	$zip = new ZipArchive();
	pg_assert_true($zip->open($path, ZipArchive::CREATE) === true, 'zip written');

	$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
	$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
	$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Hareketler" sheetId="1" r:id="rId1"/></sheets></workbook>');
	$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
	$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts><cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="164"/><xf numFmtId="4"/></cellXfs></styleSheet>');
	$zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="5" uniqueCount="5"><si><t>Örnek Bankası hesap özeti</t></si><si><t>Tarih</t></si><si><t>Açıklama</t></si><si><t>Tutar</t></si><si><t>Kira</t></si></sst>');
	$zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
		. '<row r="1"><c r="A1" t="s"><v>0</v></c></row>'
		. '<row r="2"></row>'
		. '<row r="4"><c r="A4" t="s"><v>1</v></c><c r="B4" t="s"><v>2</v></c><c r="D4" t="s"><v>3</v></c></row>'
		. '<row r="5"><c r="A5" s="1"><v>46288</v></c><c r="B5" t="inlineStr"><is><r><t>Fatura </t></r><r><t>1042</t></r></is></c><c r="D5" s="2"><v>1250.0000000000002</v></c></row>'
		. '<row r="6"><c r="A6" s="1"><v>46289</v></c><c r="B6" t="s"><v>4</v></c><c r="D6" s="2"><v>-800</v></c></row>'
		. '</sheetData></worksheet>');
	$zip->close();

	$read = erp_bank_read_file($path, 'Hesap Hareketleri.XLSX');

	pg_assert_same('', $read['error'], 'read');
	pg_assert_same(array(
		array('Tarih', 'Açıklama', '', 'Tutar'),
		array('2026-09-23', 'Fatura 1042', '', '1250.00'),
		array('2026-09-24', 'Kira', '', '-800'),
	), $read['rows'], 'rows from the heading on, columns kept in place');

	pg_assert_same(125000, erp_bank_amount($read['rows'][1][3]), 'amount reads back');
	pg_assert_same('2026-09-23', erp_bank_date($read['rows'][1][0]), 'date reads back');

	file_put_contents($path, 'not a workbook');
	pg_assert_same(array(), erp_bank_read_file($path, 'statement.xlsx')['rows'], 'a broken file');
	pg_assert_true(erp_bank_read_file($path, 'statement.xlsx')['error'] !== '', 'says so');

	unlink($path);
	rmdir($dir);
}

// An .ods saved again from LibreOffice: the title block, a column left empty,
// dates as date cells, amounts as float and currency cells, the empty rows and
// columns LibreOffice writes as one repeated cell. Read the same in every time
// zone, as the .xlsx is.
function test_erp_bank_read_file_ods()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = sys_get_temp_dir() . '/pg_bank_test_' . bin2hex(random_bytes(4));
	mkdir($dir);
	$path = $dir . '/statement.ods';

	$cell = function ($text) {
		return '<table:table-cell office:value-type="string"><text:p>' . $text . '</text:p></table:table-cell>';
	};
	$empty = '<table:table-cell table:number-columns-repeated="1020"/>';

	$zip = new ZipArchive();
	pg_assert_true($zip->open($path, ZipArchive::CREATE) === true, 'zip written');

	$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
	$zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2"><manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/></manifest:manifest>');
	$zip->addFromString('meta.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:meta/></office:document-meta>');
	$zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2"><office:body><office:spreadsheet><table:table table:name="Hesap Hareketleri">'
		. '<table:table-row>' . $cell('Örnek Bankası hesap özeti') . $empty . '</table:table-row>'
		. '<table:table-row><table:table-cell table:number-columns-repeated="1024"/></table:table-row>'
		. '<table:table-row>' . $cell('Tarih') . $cell('Açıklama') . '<table:table-cell/>' . $cell('Tutar') . $empty . '</table:table-row>'
		. '<table:table-row>'
		. '<table:table-cell office:value-type="date" office:date-value="2026-09-23"><text:p>23.09.2026</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p><text:span>Fatura </text:span><text:span>1042</text:span></text:p></table:table-cell>'
		. '<table:table-cell/>'
		. '<table:table-cell office:value-type="currency" office:currency="TRY" office:value="1250"><text:p>1.250,00 ₺</text:p></table:table-cell>'
		. $empty . '</table:table-row>'
		. '<table:table-row>'
		. '<table:table-cell office:value-type="date" office:date-value="2026-09-24T14:30:00"><text:p>24.09.2026 14:30</text:p></table:table-cell>'
		. $cell('Kira') . '<table:table-cell/>'
		. '<table:table-cell office:value-type="float" office:value="-800.5"><text:p>-800,50</text:p></table:table-cell>'
		. $empty . '</table:table-row>'
		. '<table:table-row table:number-rows-repeated="1048570"><table:table-cell table:number-columns-repeated="1024"/></table:table-row>'
		. '</table:table></office:spreadsheet></office:body></office:document-content>');
	$zip->close();

	$zone = date_default_timezone_get();

	// PHPExcel predates PHP 8.2 and raises deprecations of its own while it
	// loads; they are not what this test is about.
	$reporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

	try {

		foreach (array('UTC', 'Europe/Istanbul', 'America/New_York') as $name) {
			date_default_timezone_set($name);

			$read = erp_bank_read_file($path, 'Hesap Hareketleri.ODS');

			pg_assert_same('', $read['error'], $name . ' read');
			pg_assert_same(array(
				array('Tarih', 'Açıklama', '', 'Tutar'),
				array('2026-09-23', 'Fatura 1042', '', '1250'),
				array('2026-09-24 14:30', 'Kira', '', '-800.50'),
			), $read['rows'], $name . ' rows from the heading on, columns kept in place');
		}

		pg_assert_same(125000, erp_bank_amount($read['rows'][1][3]), 'amount reads back');
		pg_assert_same(-80050, erp_bank_amount($read['rows'][2][3]), 'minus amount reads back');
		pg_assert_same('2026-09-24', erp_bank_date($read['rows'][2][0]), 'date with a time reads back');

		// A worksheet's destructor creates PHPExcel's calculation engine, which
		// raises the same deprecations; collected here rather than at shutdown.
		gc_collect_cycles();

		// Neither a zip nor an OpenDocument zip reaches the reader.
		file_put_contents($path, 'not a workbook');
		pg_assert_same(array(), erp_bank_read_file($path, 'statement.ods')['rows'], 'a broken file');
		pg_assert_true(erp_bank_read_file($path, 'statement.ods')['error'] !== '', 'says so');

		unlink($path);
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE);
		$zip->addFromString('mimetype', 'application/zip');
		$zip->close();
		pg_assert_same(array(), erp_bank_read_file($path, 'statement.ods')['rows'], 'a zip without a sheet');

	} finally {
		error_reporting($reporting);
		date_default_timezone_set($zone);
		@unlink($path);
		rmdir($dir);
	}
}

// A document that is not a workbook Pinegrap reads is refused by its name, not read as text.
function test_erp_bank_read_file_refuses_other_documents()
{
	foreach (array('statement.PDF', 'statement.numbers') as $name) {
		$read = erp_bank_read_file(__FILE__, $name);

		pg_assert_same(array(), $read['rows'], $name . ' rows');
		pg_assert_contains('.xlsx', $read['error'], $name . ' says what to save it as');
		pg_assert_contains('.ods', $read['error'], $name . ' names OpenDocument too');
	}
}

// Write a CSV statement to a temporary file and read it back as rows.
function erp_bank_test_read_csv($text)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_bank_csv_');
	file_put_contents($path, $text);

	try {
		return erp_bank_read_csv($path);
	} finally {
		unlink($path);
	}
}

// The separator is the one that splits the lines into the same number of
// columns, not the character seen most often: commas in the descriptions and
// decimal commas outnumber the semicolons of a Turkish bank's export.
function test_erp_bank_read_csv_separator_by_columns()
{
	$read = erp_bank_test_read_csv("Tarih;Açıklama;Tutar\n"
		. "01.10.2026;FAST GELEN, ABC LTD, FATURA 12, EKIM;1.250,00\n"
		. "02.10.2026;HAVALE, XYZ, ODEME;300,00\n");

	pg_assert_same('', $read['error'], 'read');
	pg_assert_same(array(
		array('Tarih', 'Açıklama', 'Tutar'),
		array('01.10.2026', 'FAST GELEN, ABC LTD, FATURA 12, EKIM', '1.250,00'),
		array('02.10.2026', 'HAVALE, XYZ, ODEME', '300,00'),
	), $read['rows'], 'three columns split on the semicolon');

	// Two decimal commas on every line split as evenly as the semicolons do;
	// the heading has none, so the semicolon still wins.
	$read = erp_bank_test_read_csv("Tarih;Açıklama;Tutar;Bakiye\n"
		. "01.10.2026;KIRA;-800,00;4.200,00\n"
		. "02.10.2026;EFT;1.250,00;5.450,00\n");

	pg_assert_same(4, count($read['rows'][1]), 'decimal commas do not decide');
	pg_assert_same('-800,00', $read['rows'][1][2], 'amount kept whole');
}

// A comma file with the amounts quoted, a tab file and a file opening on the
// bank's title block keep their own separators.
function test_erp_bank_read_csv_other_separators()
{
	$read = erp_bank_test_read_csv("Date,Description,Amount\n"
		. "2026-10-01,\"Rent; October\",\"-1,250.00\"\n"
		. "2026-10-02,Invoice 1042,300.00\n");

	pg_assert_same(array('2026-10-01', 'Rent; October', '-1,250.00'), $read['rows'][1], 'comma file, quoted cells');
	pg_assert_same(array('2026-10-02', 'Invoice 1042', '300.00'), $read['rows'][2], 'comma file');

	$read = erp_bank_test_read_csv("Date\tDescription\tAmount\n2026-10-01\tRent, October\t-1.250,00\n");

	pg_assert_same(array('2026-10-01', 'Rent, October', '-1.250,00'), $read['rows'][1], 'tab file');

	$read = erp_bank_test_read_csv("Örnek Bankası, Kadıköy Şubesi\n"
		. "Hesap özeti, 01.10.2026 - 31.10.2026, TRY\n"
		. "Tarih;Açıklama;Tutar\n"
		. "01.10.2026;EFT GELEN;1.250,00\n"
		. "02.10.2026;KIRA, EKIM;-800,00\n");

	pg_assert_same(array('02.10.2026', 'KIRA, EKIM', '-800,00'), $read['rows'][4], 'title block with commas');
}

// A minus after a currency sign or code still makes the amount go out.
function test_erp_bank_amount_minus_after_the_currency()
{
	pg_assert_same(-123456, erp_bank_amount('TL -1.234,56'), 'TL -');
	pg_assert_same(-123456, erp_bank_amount('₺-1.234,56'), 'lira sign');
	pg_assert_same(-123456, erp_bank_amount('TRY − 1.234,56'), 'minus sign');
	pg_assert_same(-123456, erp_bank_amount('-1.234,56 TL'), 'minus first');
	pg_assert_same(-123456, erp_bank_amount('1.234,56-'), 'trailing minus');
	pg_assert_same(-123456, erp_bank_amount('1,234.56 - USD'), 'trailing minus before the code');
	pg_assert_same(-123456, erp_bank_amount('(1.234,56)'), 'parentheses');
	pg_assert_same(-123456, erp_bank_amount('TL (1.234,56)'), 'parentheses after the code');
	pg_assert_same(123456, erp_bank_amount('₺1.234,56'), 'in');
	pg_assert_same(123456, erp_bank_amount('1,234.56 USD'), 'in, code after');
}

// A decimal separator with no digit in front of it is still the decimals.
function test_erp_bank_amount_leading_separator()
{
	pg_assert_same(50, erp_bank_amount('.50'), '.50');
	pg_assert_same(-50, erp_bank_amount('-.50'), '-.50');
	pg_assert_same(50, erp_bank_amount(',5'), ',5');
	pg_assert_same(50, erp_bank_amount('0,50'), '0,50');
	pg_assert_same(123400, erp_bank_amount('1.234'), 'thousands only');
	pg_assert_same(123400, erp_bank_amount('1,234'), 'thousands only, comma');
	pg_assert_same(1200, erp_bank_amount('12'), 'whole');
	pg_assert_same(null, erp_bank_amount('TL'), 'no number');
}

// A line's first occurrence keeps the hash lines were stored under before, so
// a statement taken in again still meets the lines already there.
function test_erp_bank_line_hash_first_occurrence_unchanged()
{
	pg_assert_same(sha1('7|2026-10-15|-850|' . mb_strtolower('EFT ÜCRETİ')), erp_bank_line_hash(7, '2026-10-15', -850, 'EFT ÜCRETİ'), 'default occurrence');
	pg_assert_same(erp_bank_line_hash(7, '2026-10-15', -850, 'EFT UCRETI'), erp_bank_line_hash(7, '2026-10-15', -850, 'eft ucreti', 1), 'letter case aside');
}

// The second real line of the same day, amount and words is a line of its own.
function test_erp_bank_line_hash_repeated_line()
{
	$first = erp_bank_line_hash(7, '2026-10-15', -850, 'EFT ÜCRETİ', 1);
	$second = erp_bank_line_hash(7, '2026-10-15', -850, 'EFT ÜCRETİ', 2);
	$third = erp_bank_line_hash(7, '2026-10-15', -850, 'EFT ÜCRETİ', 3);

	pg_assert_true($first !== $second, 'second differs from first');
	pg_assert_true($second !== $third, 'third differs from second');
	pg_assert_same($second, erp_bank_line_hash(7, '2026-10-15', -850, 'EFT ÜCRETİ', 2), 'the same file gives the same hashes again');
	pg_assert_true(erp_bank_line_hash(8, '2026-10-15', -850, 'EFT ÜCRETİ', 2) !== $second, 'another bank account');
}

// The statement's four lines, two of them the same fee: every line gets its
// own hash, and reading the file again gives the same four.
function test_erp_bank_line_hashes_count_repeats_per_file()
{
	$lines = array(
		array('2026-10-15', -100000, 'EFT GİDEN ABC LTD'),
		array('2026-10-15', -850, 'EFT ÜCRETİ'),
		array('2026-10-15', -200000, 'EFT GİDEN XYZ AŞ'),
		array('2026-10-15', -850, 'EFT ÜCRETİ'),
	);

	$hashes = function () use ($lines) {
		$seen = array();
		$out = array();

		foreach ($lines as $line) {
			$out[] = erp_bank_line_hash(7, $line[0], $line[1], $line[2], erp_bank_line_occurrence($seen, 7, $line[0], $line[1], $line[2]));
		}

		return $out;
	};

	$first = $hashes();

	pg_assert_same(4, count(array_unique($first)), 'four lines, four hashes');
	pg_assert_same(sha1('7|2026-10-15|-850|' . mb_strtolower('EFT ÜCRETİ')), $first[1], 'the first fee keeps the old hash');
	pg_assert_same($first, $hashes(), 'taken in again, the same hashes');
}

// The "one open invoice of this amount" a statement line suggests is an
// invoice, never a return document, in the line's currency, open by what is
// left once its returns are taken off.
function test_erp_bank_open_invoice_query_invoices_only()
{
	$sql = erp_bank_open_invoice_query('sales', 'TRY', 'TRY', 30000);

	pg_assert_contains("i.doc_type = 'invoice'", $sql, 'invoices only');
	pg_assert_contains("i.direction = 'sales'", $sql, 'money in closes a sale');
	pg_assert_contains("ret.parent_invoice_id = i.id", $sql, 'returns taken off');
	pg_assert_contains("COALESCE(ret.returned, 0)", $sql, 'by what they gave back');
	pg_assert_contains("r.doc_type = 'return'", $sql, 'returns only');
	pg_assert_contains("r.status <> 'cancelled'", $sql, 'a cancelled return gives nothing back');
	pg_assert_contains("= '30000'", $sql, 'the amount');
	pg_assert_contains("LIMIT 2", $sql, 'one is told from several');

	pg_assert_contains("i.direction = 'purchase'", erp_bank_open_invoice_query('purchase', 'TRY', 'TRY', 850), 'money out closes a purchase');
}

// The line's currency decides; a document written with no currency counts as
// the store's own.
function test_erp_bank_open_invoice_query_currency()
{
	$home = erp_bank_open_invoice_query('sales', 'try', 'TRY', 100);
	$foreign = erp_bank_open_invoice_query('sales', 'USD', 'TRY', 100);

	pg_assert_contains("(i.currency = 'TRY' OR i.currency = '')", $home, 'home currency');
	pg_assert_contains("i.currency = 'USD'", $foreign, 'foreign currency');
	pg_assert_true(strpos($foreign, "i.currency = ''") === false, 'no blank currency for a foreign line');
	pg_assert_contains("i.currency = 'USD'", erp_bank_open_invoice_query('sales', "US'D", 'TRY', 100), 'letters only');
}
