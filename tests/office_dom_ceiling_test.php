<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how much of an office package the Word and Excel readers build
 * as a tree (includes/fn/office.php, includes/fn/office_edit.php). A part
 * read whole into a DOM declares no more than PG_OFFICE_MAX_DOM, unpacks to
 * exactly what it declares, and a larger main document part is read from its
 * beginning and drawn cut; the rows and strings of a workbook, streamed, are
 * never expanded into a tree, and carry the values SimpleXML and readString()
 * give for them. The save path refuses what it cannot check instead of
 * passing it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A package of the parts given, in a zip on disk.
function office_dom_test_package($parts)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_dom_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

// Sets the size the archive declares for an entry, in its local header (name
// length at 26, name at 30, size at 22) and its central directory entry (28,
// 46, 24), after the zip was written.
function office_dom_test_declare($path, $entry, $size)
{
	$bytes = file_get_contents($path);

	foreach (array(array("PK\x03\x04", 26, 30, 22), array("PK\x01\x02", 28, 46, 24)) as $header) {
		list($signature, $length_at, $name_at, $size_at) = $header;
		$position = 0;

		while (($position = strpos($bytes, $signature, $position)) !== false) {
			$length = unpack('v', substr($bytes, $position + $length_at, 2));

			if (substr($bytes, $position + $name_at, $length[1]) === $entry) {
				$bytes = substr_replace($bytes, pack('V', $size), $position + $size_at, 4);
			}

			$position += 4;
		}
	}

	file_put_contents($path, $bytes);
}

// Whether this libzip hands over a byte past an entry's declared size when
// asked for it; one that stops on its own leaves no overrun to refuse.
function office_dom_test_reads_past($path, $entry)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$stat = $zip->statName($entry);
	$data = $zip->getFromIndex($stat['index'], $stat['size'] + 1);
	$zip->close();

	return is_string($data) && (strlen($data) > $stat['size']);
}

function office_dom_test_types()
{
	return '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>';
}

// The parts of a document around the body given.
function office_dom_test_docx_parts($body)
{
	$w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

	return array(
		'[Content_Types].xml' => office_dom_test_types(),
		'word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId9" Type="' . $rel . '/settings" Target="settings.xml"/></Relationships>',
		'word/settings.xml' => '<?xml version="1.0"?><w:settings xmlns:w="' . $w . '"/>',
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"/>',
		'word/numbering.xml' => '<?xml version="1.0"?><w:numbering xmlns:w="' . $w . '"/>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . $w . '" xmlns:r="' . $rel . '"><w:body>' . $body . '<w:sectPr/></w:body></w:document>',
	);
}

// The parts of a one-sheet workbook around the rows given; the sheet has a
// table over D1:E3.
function office_dom_test_xlsx_parts($rows, $strings = '')
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$pns = 'http://schemas.openxmlformats.org/package/2006/relationships';
	$parts = array(
		'[Content_Types].xml' => office_dom_test_types(),
		'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Liste" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
		'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="' . $ns . '"><cellXfs count="1"><xf numFmtId="0"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheetData>' . $rows . '</sheetData><tableParts count="1"><tablePart r:id="rId1"/></tableParts></worksheet>',
		'xl/worksheets/_rels/sheet1.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="' . $pns . '"><Relationship Id="rId1" Type="' . $rns . '/table" Target="../tables/table1.xml"/></Relationships>',
		'xl/tables/table1.xml' => '<?xml version="1.0"?><table xmlns="' . $ns . '" id="1" name="T" displayName="T" ref="D1:E3"><tableColumns count="2"><tableColumn id="1" name="Ad"/><tableColumn id="2" name="Fiyat"/></tableColumns></table>',
	);

	if ($strings !== '') {
		$parts['xl/sharedStrings.xml'] = '<?xml version="1.0"?><sst xmlns="' . $ns . '">' . $strings . '</sst>';
	}

	return $parts;
}

// A part read whole: a DOM when it is there, within the ceiling and exactly
// its declared size; otherwise null and the reason.
function test_office_dom_part_reads()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$path = office_dom_test_package(array(
		'a.xml' => '<a><b/></a>',
		'empty.xml' => '',
		'bad.xml' => '<a><b></a>',
		'big.xml' => '<a/>',
		'short.xml' => '<a>' . str_repeat('x', 5000) . '</a>',
	));
	office_dom_test_declare($path, 'big.xml', PG_OFFICE_MAX_DOM + 1);
	office_dom_test_declare($path, 'short.xml', 1000);
	$past = office_dom_test_reads_past($path, 'short.xml');

	$zip = new ZipArchive();
	$zip->open($path);

	$dom = pg_office_xml($zip, 'a.xml', $status);
	pg_assert_true($dom instanceof DOMDocument, 'an honest part is a DOM');
	pg_assert_same('', $status, 'read whole');
	$dom = pg_office_xml($zip, 'a.xml', $status, true);
	pg_assert_true(($dom instanceof DOMDocument) && ($status === ''), 'a part within the ceiling is read whole even when its beginning would do');

	pg_assert_same(null, pg_office_xml($zip, 'none.xml', $status), 'no such part');
	pg_assert_same('missing', $status, 'missing');
	pg_assert_same(null, pg_office_xml($zip, 'empty.xml', $status), 'an empty part');
	pg_assert_same('missing', $status, 'is as good as none');
	pg_assert_same(null, pg_office_xml($zip, 'bad.xml', $status), 'not XML');
	pg_assert_same('xml', $status, 'xml');
	pg_assert_same(null, pg_office_xml($zip, 'big.xml', $status), 'a part declaring more than the ceiling is not read');
	pg_assert_same('large', $status, 'large');

	if ($past) {
		pg_assert_same(null, pg_office_xml($zip, 'short.xml', $status), 'a part inflating past what it declares');
		pg_assert_same('damaged', $status, 'damaged');
	}

	$zip->close();
	unlink($path);

	pg_assert_true(PG_OFFICE_EDIT_MAX_DOCUMENT <= PG_OFFICE_MAX_DOM, 'a document that may be edited is always read whole');
}

// A main document part past the ceiling is read from its beginning: the
// preview, already cut at PG_OFFICE_MAX_HTML, comes out as it does for the
// same document read whole. It is not offered for editing.
function test_office_dom_large_document_shown_cut()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$first = '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Yıllık rapor</w:t></w:r></w:p>';
	$paragraph = '<w:p w:rsidR="00A1B2C3"><w:pPr><w:jc w:val="both"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">Satış </w:t></w:r><w:r><w:t>ve stok hareketleri, şubeler ayrı ayrı.</w:t></w:r></w:p>';
	$table = '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Şube</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Tutar</w:t></w:r></w:p></w:tc></w:tr></w:tbl>';
	$unit = str_repeat($paragraph, 9) . $table;

	$small = office_dom_test_package(office_dom_test_docx_parts($first . str_repeat($unit, (int) ceil((2 * 1024 * 1024) / strlen($unit)))));
	$large = office_dom_test_package(office_dom_test_docx_parts($first . str_repeat($unit, (int) ceil((PG_OFFICE_MAX_DOM + 1024 * 1024) / strlen($unit)))));

	$zip = new ZipArchive();
	$zip->open($large);
	pg_assert_true((int) $zip->statName('word/document.xml')['size'] > PG_OFFICE_MAX_DOM, 'the large document part is past the ceiling');
	$zip->close();

	$whole = pg_office_read($small, 'rapor.docx');
	$cut = pg_office_read($large, 'rapor.docx');

	pg_assert_true($whole['ok'] && $whole['truncated'], 'the document read whole is long enough to be cut by the preview');
	pg_assert_true($cut['ok'], 'the large one is shown: ' . $cut['error']);
	pg_assert_true($cut['truncated'], 'and says it is cut');
	pg_assert_same($whole['html'], $cut['html'], 'drawn as the same document read whole');
	pg_assert_contains('<h1>Yıllık rapor</h1>', $cut['html'], 'from its beginning');

	$edit = pg_office_read($large, 'rapor.docx', true);
	pg_assert_true($edit['ok'], 'shown when read for editing too');
	pg_assert_same(array('ok' => false, 'why' => 'large'), $edit['edit'], 'but not offered for editing');

	unlink($large);

	// A main part that declares more than the ceiling but holds less than
	// the beginning that is read is damaged.
	office_dom_test_declare($small, 'word/document.xml', PG_OFFICE_MAX_DOM * 4);
	$damaged = pg_office_read($small, 'rapor.docx');
	pg_assert_false($damaged['ok'], 'a main part shorter than it declares is not shown');
	pg_assert_same(lang('The file could not be read.'), $damaged['error'], 'it could not be read');
	unlink($small);
}

// The other parts read whole (lists, styles, relationships, the workbook):
// one past the ceiling makes the file too large to show, a damaged one makes
// it unreadable, rather than the file being drawn without it.
function test_office_dom_other_parts_refused()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$large = lang('This file is too large to show here. Download it to open it.');
	$unreadable = lang('The file could not be read.');
	$body = '<w:p><w:r><w:t>Metin</w:t></w:r></w:p>';

	$path = office_dom_test_package(office_dom_test_docx_parts($body));
	$read = pg_office_read($path, 'x.docx', true);
	pg_assert_true($read['ok'] && $read['edit']['ok'], 'the honest document is shown and offered');
	unlink($path);

	foreach (array('word/numbering.xml', 'word/styles.xml', 'word/_rels/document.xml.rels') as $part) {
		$path = office_dom_test_package(office_dom_test_docx_parts($body));
		office_dom_test_declare($path, $part, PG_OFFICE_MAX_DOM + 1);
		$read = pg_office_read($path, 'x.docx');
		pg_assert_false($read['ok'], $part . ' past the ceiling: not shown');
		pg_assert_same($large, $read['error'], $part . ': too large to show');
		unlink($path);
	}

	$path = office_dom_test_package(office_dom_test_docx_parts($body));

	if (office_dom_test_reads_past($path, 'word/styles.xml')) {
		office_dom_test_declare($path, 'word/styles.xml', 10);
		$read = pg_office_read($path, 'x.docx');
		pg_assert_same(array(false, $unreadable), array($read['ok'], $read['error']), 'a damaged style part: the file could not be read');
	}

	unlink($path);

	// The settings say whether the document is protected; when they cannot
	// be read, it is not offered for editing.
	$path = office_dom_test_package(office_dom_test_docx_parts($body));
	office_dom_test_declare($path, 'word/settings.xml', PG_OFFICE_MAX_DOM + 1);
	$read = pg_office_read($path, 'x.docx', true);
	pg_assert_true($read['ok'], 'the document with settings past the ceiling is shown');
	pg_assert_same(array('ok' => false, 'why' => 'large'), $read['edit'], 'not offered for editing');
	$saved = pg_office_docx_save($path, array(array('p' => 0, 'text' => 'Yeni')), $read['version'], sys_get_temp_dir());
	pg_assert_same('large', $saved['code'], 'nor saved');
	unlink($path);

	$rows = '<row r="1"><c r="A1" t="inlineStr"><is><t>a</t></is></c></row>';

	foreach (array('xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml') as $part) {
		$path = office_dom_test_package(office_dom_test_xlsx_parts($rows));
		office_dom_test_declare($path, $part, PG_OFFICE_MAX_DOM + 1);
		$read = pg_office_read($path, 'x.xlsx');
		pg_assert_false($read['ok'], $part . ' past the ceiling: not shown');
		pg_assert_same($large, $read['error'], $part . ': too large to show');
		unlink($path);
	}

	$path = office_dom_test_package(office_dom_test_xlsx_parts($rows));

	if (office_dom_test_reads_past($path, 'xl/styles.xml')) {
		office_dom_test_declare($path, 'xl/styles.xml', 10);
		$read = pg_office_read($path, 'x.xlsx');
		pg_assert_same(array(false, $unreadable), array($read['ok'], $read['error']), 'a damaged style part: the workbook could not be read');
	}

	unlink($path);

	// A table part says which cells are its header; when it cannot be read,
	// the sheet is shown but not offered, and not saved.
	$path = office_dom_test_package(office_dom_test_xlsx_parts($rows));
	office_dom_test_declare($path, 'xl/tables/table1.xml', PG_OFFICE_MAX_DOM + 1);
	$read = pg_office_read($path, 'x.xlsx', true);
	pg_assert_true($read['ok'], 'the workbook with a table part past the ceiling is shown');
	pg_assert_same(array('ok' => false, 'why' => 'large'), array_intersect_key($read['sheets'][0]['edit'], array('ok' => 1, 'why' => 1)), 'its sheet is not offered');
	$saved = pg_office_xlsx_save($path, array(array('sheet' => 0, 'cell' => 'A1', 'value' => 'b')), $read['version'], sys_get_temp_dir());
	pg_assert_same('large', $saved['code'], 'nor saved');
	unlink($path);

	$path = office_dom_test_package(office_dom_test_xlsx_parts($rows));

	if (office_dom_test_reads_past($path, 'xl/tables/table1.xml')) {
		office_dom_test_declare($path, 'xl/tables/table1.xml', 10);
		$read = pg_office_read($path, 'x.xlsx', true);
		pg_assert_same(array('ok' => false, 'why' => 'unusual'), array_intersect_key($read['sheets'][0]['edit'], array('ok' => 1, 'why' => 1)), 'a damaged table part: the sheet is not offered');
	}

	unlink($path);
}

// The text of an element read node by node is what XMLReader::readString()
// gives for it, and the reader is left at the element's end.
function test_office_dom_reader_text_matches_read_string()
{
	$snippets = array(
		'<si><t>düz</t></si>',
		"<si><r><t xml:space=\"preserve\">Ka </t></r>\n  <r><rPr><b/></rPr><t>lem</t></r><rPh sb=\"0\" eb=\"1\"><t>カ</t></rPh></si>",
		'<si/>',
		'<si></si>',
		'<si><t><![CDATA[a<b]]></t></si>',
		'<si><t xml:space="preserve">  </t></si>',
		'<si>&amp;<t>&lt;&#x130;</t>son</si>',
	);

	foreach ($snippets as $snippet) {
		$xml = '<sst>' . $snippet . '<si><t>sonraki</t></si></sst>';

		$reference = new XMLReader();
		$reference->XML($xml);

		while ($reference->read() && ($reference->name !== 'si')) {
		}

		$expected = $reference->readString();
		$reference->close();

		$reader = new XMLReader();
		$reader->XML($xml);

		while ($reader->read() && ($reader->name !== 'si')) {
		}

		$text = pg_office_reader_text($reader, 1000000, $over);
		pg_assert_same($expected, $text, 'as readString(): ' . $snippet);
		pg_assert_false($over, 'within the limit: ' . $snippet);

		while ($reader->read() && ($reader->nodeType !== XMLReader::ELEMENT)) {
		}

		pg_assert_same(array('si', 'sonraki'), array($reader->name, $reader->readString()), 'the next element comes next: ' . $snippet);
		$reader->close();
	}

	$reader = new XMLReader();
	$reader->XML('<sst><si><r><t>abc</t></r><r><t>def</t></r></si><si><t>sonraki</t></si></sst>');

	while ($reader->read() && ($reader->name !== 'si')) {
	}

	pg_assert_same('abcd', pg_office_reader_text($reader, 4, $over), 'cut at the limit');
	pg_assert_true($over, 'and says so');
	$reader->read();
	pg_assert_same('si', $reader->name, 'the element read to its end all the same');
	$reader->close();
}

// Rows and strings of a workbook read node by node carry what SimpleXML gives
// for a row built as a tree: shared and inline strings, runs, the first
// value, formulas waiting, only the row's own cells, the column cut.
function test_office_dom_rows_streamed()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$wide = '';

	for ($column = 0; $column < PG_OFFICE_MAX_COLUMNS + 10; $column++) {
		$wide .= '<c r="' . pg_office_xlsx_column_name($column) . '4"><v>' . $column . '</v></c>';
	}

	$rows = '<row r="1" spans="1:6"><c r="A1" t="inlineStr"><is><t>düz &amp; net</t></is></c>'
		. '<c r="B1" t="inlineStr"><is><r><t>Ka</t></r><r><rPr><b/></rPr><t>lem</t></r></is></c>'
		. '<c r="C1" t="inlineStr"><is><t></t><r><t>yalnız</t></r><r><t>koşu</t></r></is></c>'
		. '<c r="D1"><v>1</v><v>2</v></c>'
		. '<c r="E1"><f>A1</f></c>'
		. '<c r="F1"/>'
		. '<c r="G1" t="s"><v>1</v></c></row>'
		. '<row r="2"/>'
		. '<row r="3"><c r="A3"><v>3</v></c><extLst><ext><c r="B3"><v>99</v></c></ext></extLst><c r="C3" t="b"><v>0</v></c></row>'
		. '<row r="4">' . $wide . '</row>'
		. '<row r="5"><c r="A5" t="s"><v>0</v></c></row>';
	$strings = "<si><t>ilk</t></si><si><r><t xml:space=\"preserve\">a </t></r>\n<r><t>b</t></r></si>";

	$path = office_dom_test_package(office_dom_test_xlsx_parts($rows, $strings));
	$book = pg_office_read($path, 'x.xlsx');
	unlink($path);

	pg_assert_true($book['ok'], 'read: ' . $book['error']);
	$read = $book['sheets'][0]['rows'];

	pg_assert_same(array(1, 2, 3, 4, 5), array_column($read, 'n'), 'every row, by its own number');
	pg_assert_same(array('düz & net', 'Kalem', 'yalnızkoşu', '1', '', '', "a \nb"), $read[0]['cells'], 'inline text, runs, runs behind an empty text, the first value, a formula, an empty cell, a shared string with the space between its runs');
	pg_assert_same(array(4), $read[0]['calc'], 'the formula without a result waits');
	pg_assert_same(array(), $read[1]['cells'], 'an empty row');
	pg_assert_same(array('3', '', 'FALSE'), $read[2]['cells'], 'only the row\'s own cells, not one inside something else');
	pg_assert_same(PG_OFFICE_MAX_COLUMNS, count($read[3]['cells']), 'a wide row cut at the column limit');
	pg_assert_same((string) (PG_OFFICE_MAX_COLUMNS - 1), $read[3]['cells'][PG_OFFICE_MAX_COLUMNS - 1], 'up to its last column');
	pg_assert_same(array('ilk'), $read[4]['cells'], 'the row after the cut one is read');
	pg_assert_true($book['sheets'][0]['truncated'], 'the sheet says it is cut');
}

// The save path: the workbook part and a changed row go into a DOM only
// within the ceiling.
function test_office_dom_save_parts_within_ceiling()
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$filler = '<definedNames>' . str_repeat('<definedName name="x">A1</definedName>', (int) ceil(PG_OFFICE_MAX_DOM / 38)) . '</definedNames>';
	pg_assert_same(null, pg_office_xlsx_patch_workbook('<workbook xmlns="' . $ns . '"><sheets/>' . $filler . '</workbook>'), 'a workbook part past the ceiling is not changed');

	$cell = '<c r="B1"><v>1</v></c>';
	$row = '<row r="1"><c r="A1"><v>1</v></c>' . str_repeat($cell, (int) ceil(PG_OFFICE_MAX_DOM / strlen($cell))) . '</row>';
	$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '"><sheetData>' . $row . '<row r="2"><c r="A2"><v>2</v></c></row></sheetData></worksheet>';
	$context = array('styles' => array('date' => array(), 'text' => array(), 'open' => array()), 'date1904' => false, 'protected' => false);

	$patched = pg_office_xlsx_patch_sheet($sheet, array(1 => array(0 => '5')), $context);
	pg_assert_same(array(false, 'large'), array($patched['ok'], $patched['error']), 'a changed row past the ceiling is not built as a tree');

	$patched = pg_office_xlsx_patch_sheet($sheet, array(2 => array(0 => '5')), $context);
	pg_assert_true($patched['ok'], 'another row of the same sheet is changed');

	if (!pg_office_ready()) {
		return;
	}

	$parts = office_dom_test_xlsx_parts('');
	$parts['xl/worksheets/sheet1.xml'] = $sheet;
	$path = office_dom_test_package($parts);
	$saved = pg_office_xlsx_save($path, array(array('sheet' => 0, 'cell' => 'A1', 'value' => '5')), pg_office_version($path), sys_get_temp_dir());
	pg_assert_same('large', $saved['code'], 'a save of that row is refused as too large');
	unlink($path);
}

// A formula's text for the tooltip, read node by node, is cut to its label
// length, and the scan goes on after it.
function test_office_dom_scan_formula_label()
{
	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$formula = str_repeat('A1+', 150) . 'B1';
	$reader = new XMLReader();
	$reader->XML('<worksheet xmlns="' . $ns . '"><sheetData><row r="1"><c r="A1"><f>' . $formula . '</f><v>3</v></c><c r="B1"><f>SUM(A1)</f></c></row></sheetData><mergeCells><mergeCell ref="C1:D1"/></mergeCells></worksheet>');
	$scan = pg_office_xlsx_scan($reader, 1, array());
	$reader->close();

	pg_assert_same(mb_substr($formula, 0, PG_OFFICE_EDIT_FORMULA_LABEL, 'UTF-8'), $scan['formulas']['A1'], 'a long formula cut to the label');
	pg_assert_same('SUM(A1)', $scan['formulas']['B1'], 'the next cell\'s formula');
	pg_assert_same(array('A1' => 'formula', 'B1' => 'formula'), $scan['locked'], 'both locked');
	pg_assert_same(1, count($scan['merges']), 'and what follows the cells is read');
}
