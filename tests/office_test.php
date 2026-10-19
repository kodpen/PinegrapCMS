<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Word and Excel readers (includes/fn/office.php): the pure
 * helpers (kinds, column letters, day counts, numbers, style names, part
 * names), and the two readers over small packages the test writes itself,
 * so a worksheet's strings, dates, booleans and gaps and a document's
 * headings, runs, lists, tables, links and pictures come out as the
 * preview draws them, and what the file wrote never passes through as
 * markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_office_kind_by_extension()
{
	pg_assert_same('docx', pg_office_kind('Teklif.DOCX'), 'a document, whatever the case');
	pg_assert_same('docx', pg_office_kind('form.dotm'), 'a macro-enabled template reads the same');
	pg_assert_same('xlsx', pg_office_kind('liste.xlsx'), 'a workbook');
	pg_assert_same('xlsx', pg_office_kind('liste.xlsm'), 'a macro-enabled workbook');
	pg_assert_same('', pg_office_kind('eski.doc'), 'the binary formats are not read');
	pg_assert_same('', pg_office_kind('sunum.pptx'), 'nor a presentation');
	pg_assert_same('', pg_office_kind('noext'), 'nor nothing');
}

function test_office_pure_helpers()
{
	pg_assert_same(0, pg_office_xlsx_column('A1'), 'A');
	pg_assert_same(25, pg_office_xlsx_column('Z9'), 'Z');
	pg_assert_same(26, pg_office_xlsx_column('AA1'), 'AA');
	pg_assert_same(702, pg_office_xlsx_column('AAA1'), 'AAA');
	pg_assert_same('2026-10-19', pg_office_xlsx_date(46314), 'a whole day count');
	pg_assert_same('2026-10-19 14:30', pg_office_xlsx_date(46314.604166667), 'with a time of day');
	pg_assert_same('10', pg_office_xlsx_number('10'), 'a whole number as it is');
	pg_assert_same('0.0000001', pg_office_xlsx_number('1E-7'), 'an exponent written out');
	pg_assert_same('125.5', pg_office_xlsx_number('125.5'), 'a fraction as it is');
	pg_assert_same('abc', pg_office_xlsx_number('abc'), 'not a number: untouched');
	pg_assert_same('h1', pg_office_docx_block_tag('Heading1'), 'a heading by its level');
	pg_assert_same('h3', pg_office_docx_block_tag('Balk3'), 'a Turkish Word heading style id');
	pg_assert_same('h1', pg_office_docx_block_tag('Title'), 'the title');
	pg_assert_same('blockquote', pg_office_docx_block_tag('Quote'), 'a quote');
	pg_assert_same('p', pg_office_docx_block_tag('Normal'), 'anything else is a paragraph');
	pg_assert_same('xl/worksheets/sheet1.xml', pg_office_part_name('xl/', 'worksheets/sheet1.xml'), 'relative to the folder');
	pg_assert_same('xl/worksheets/sheet1.xml', pg_office_part_name('xl/', '/xl/worksheets/sheet1.xml'), 'absolute');
	pg_assert_same('word/media/image1.png', pg_office_part_name('word/', '../word/media/image1.png'), 'a step up folded');
	pg_assert_same('etc/passwd', pg_office_part_name('word/', '../../../etc/passwd'), 'never above the package root');
}

// A small package: the parts given, in a zip on disk.
function office_test_package($parts)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

function test_office_xlsx_reader()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	$rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$path = office_test_package(array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="' . $ns . '" xmlns:r="' . $rns . '"><sheets><sheet name="Ürünler" sheetId="1" r:id="rId1"/><sheet name="Gizli" sheetId="2" state="hidden" r:id="rId2"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
		'xl/sharedStrings.xml' => '<?xml version="1.0"?><sst xmlns="' . $ns . '"><si><t>Ad</t></si><si><r><t>Ka</t></r><r><t>lem</t></r></si></sst>',
		'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="' . $ns . '"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd.mm.yyyy"/></numFmts><cellXfs count="3"><xf numFmtId="0"/><xf numFmtId="164"/><xf numFmtId="4"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet3.xml' => '<?xml version="1.0"?><worksheet xmlns="' . $ns . '"><sheetData>'
			. '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="inlineStr"><is><t>Fiyat &amp; vergi</t></is></c></row>'
			. '<row r="2"><c r="A2" t="s"><v>1</v></c><c r="B2" s="1"><v>46314</v></c><c r="C2" s="2"><v>125.5</v></c><c r="D2" t="b"><v>1</v></c><c r="E2"><f>B2*2</f></c></row>'
			. '<row r="5"><c r="B5" t="str"><v>&lt;b&gt;x&lt;/b&gt;</v></c></row>'
			. '</sheetData></worksheet>',
		'xl/worksheets/sheet1.xml' => '<?xml version="1.0"?><worksheet xmlns="' . $ns . '"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>saklı</t></is></c></row></sheetData></worksheet>',
	));

	$book = pg_office_read($path, 'liste.xlsx');
	unlink($path);

	pg_assert_true($book['ok'], 'read: ' . $book['error']);
	pg_assert_same('xlsx', $book['kind'], 'the kind');
	pg_assert_same(2, count($book['sheets']), 'both sheets, in the workbook\'s order');
	pg_assert_same('Ürünler', $book['sheets'][0]['name'], 'the first sheet by its name, not its file name');
	pg_assert_false($book['sheets'][0]['hidden'], 'shown');
	pg_assert_true($book['sheets'][1]['hidden'], 'the hidden one says so');
	pg_assert_same(array('saklı'), $book['sheets'][1]['rows'][0]['cells'], 'and is read all the same');

	$rows = $book['sheets'][0]['rows'];

	pg_assert_same(3, count($rows), 'three rows');
	pg_assert_same(array(1, 2, 5), array($rows[0]['n'], $rows[1]['n'], $rows[2]['n']), 'with the sheet\'s own row numbers, gaps kept');
	pg_assert_same(array('Ad', '', 'Fiyat & vergi'), $rows[0]['cells'], 'a shared string, a gap, an inline string');
	pg_assert_same(array('Kalem', '2026-10-19', '125.5', 'TRUE', ''), $rows[1]['cells'], 'runs joined, a custom date style, a number, a boolean, a formula without a cached value');
	pg_assert_same(array('', '<b>x</b>'), $rows[2]['cells'], 'a formula string result, as text');
	pg_assert_false($book['truncated'], 'nothing cut');
}

function test_office_docx_reader()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	$r = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
	$path = office_test_package(array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/x" TargetMode="External"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>'
			. '</Relationships>',
		'word/media/image1.png' => $png,
		'word/numbering.xml' => '<?xml version="1.0"?><w:numbering xmlns:w="' . $w . '"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num></w:numbering>',
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"><w:style w:type="paragraph" w:styleId="ListBullet"><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr></w:style></w:styles>',
		'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="' . $w . '" xmlns:r="' . $r . '"><w:body>'
			. '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Teklif: Ekim</w:t></w:r></w:p>'
			. '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Ayşe</w:t></w:r><w:r><w:rPr><w:i/><w:b w:val="0"/></w:rPr><w:t>, &lt;b&gt;x&lt;/b&gt;</w:t></w:r><w:r><w:tab/><w:t>son</w:t></w:r></w:p>'
			. '<w:p><w:pPr><w:pStyle w:val="ListBullet"/></w:pPr><w:r><w:t>Kalem</w:t></w:r></w:p>'
			. '<w:p><w:pPr><w:pStyle w:val="ListBullet"/></w:pPr><w:r><w:t>Defter</w:t></w:r></w:p>'
			. '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>Birinci</w:t></w:r></w:p>'
			. '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>BC</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
			. '<w:p><w:hyperlink r:id="rId1"><w:r><w:t>site</w:t></w:r></w:hyperlink><w:hyperlink r:id="rId3"><w:r><w:t>evil</w:t></w:r></w:hyperlink></w:p>'
			. '<w:p><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:docPr id="1" name="p" descr="bir resim"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:blip r:embed="rId2"/></a:graphic></wp:inline></w:drawing></w:r></w:p>'
			. '<w:p><w:r><w:del><w:r><w:delText>silindi</w:delText></w:r></w:del><w:br w:type="page"/><w:t>son sayfa</w:t></w:r></w:p>'
			. '</w:body></w:document>',
	));

	$doc = pg_office_read($path, 'teklif.docx');
	unlink($path);

	pg_assert_true($doc['ok'], 'read: ' . $doc['error']);

	$html = $doc['html'];

	pg_assert_contains('<h1>Teklif: Ekim</h1>', $html, 'a heading');
	pg_assert_contains('<p style="text-align:center">Sayın <strong>Ayşe</strong><em>, &lt;b&gt;x&lt;/b&gt;</em><span class="office-tabchar"> </span>son</p>', $html, 'runs with their bold and italic, text escaped, a tab, alignment');
	pg_assert_contains('<ul class="office-list"><li class="office-lvl-0">Kalem</li><li class="office-lvl-0">Defter</li></ul><ol class="office-list"><li class="office-lvl-0">Birinci</li></ol>', $html, 'a bullet list from the style, then a numbered one from the paragraph');
	pg_assert_contains('<table class="office-table"><tr><td><p>A</p></td><td colspan="2"><p>BC</p></td></tr></table>', $html, 'a table with a span');
	pg_assert_contains('<a href="https://example.test/x" target="_blank" rel="noopener noreferrer">site</a>evil', $html, 'a web link kept, a javascript: one reduced to its words');
	pg_assert_contains('<img class="office-img" src="data:image/png;base64,', $html, 'a picture inlined');
	pg_assert_contains('alt="bir resim"', $html, 'with its description');
	pg_assert_false(strpos($html, 'silindi') !== false, 'deleted text left out');
	pg_assert_contains('<hr class="office-page">son sayfa', $html, 'a page break');
	pg_assert_same(0, $doc['images_lost'], 'every picture shown');
	pg_assert_false($doc['truncated'], 'nothing cut');
}

function test_office_refuses_what_is_not_a_package()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_office_test_');
	file_put_contents($path, 'not a zip at all');
	$read = pg_office_read($path, 'x.docx');
	unlink($path);

	pg_assert_false($read['ok'], 'refused');
	pg_assert_true($read['error'] !== '', 'with a reason');

	$plain = office_test_package(array('hello.txt' => 'hi'));
	$read = pg_office_read($plain, 'x.xlsx');
	unlink($plain);

	pg_assert_false($read['ok'], 'a zip without [Content_Types].xml is not a package');
}
