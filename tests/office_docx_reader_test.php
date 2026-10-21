<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Word preview's reader (includes/fn/office.php): table rows
 * and cells inside content controls (repeating sections, custom XML), and
 * a paragraph whose numbering is taken off with numId 0.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// office_test_package(): a package of the given parts in a zip on disk.
require_once(__DIR__ . '/office_test.php');

// The preview's HTML of a document with the given body, numbering and styles.
function office_docx_reader_test_html($body, $numbering = '', $styles = '')
{
	$w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="' . $w . '"><w:body>' . $body . '</w:body></w:document>',
	);

	if ($numbering !== '') {
		$parts['word/numbering.xml'] = '<?xml version="1.0"?><w:numbering xmlns:w="' . $w . '">' . $numbering . '</w:numbering>';
	}

	if ($styles !== '') {
		$parts['word/styles.xml'] = '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '">' . $styles . '</w:styles>';
	}

	$path = office_test_package($parts);
	$doc = pg_office_read($path, 'test.docx');
	unlink($path);

	pg_assert_true($doc['ok'], 'read: ' . ($doc['error'] ?? ''));

	return (string) ($doc['html'] ?? '');
}

// A paragraph with the given text in a table cell.
function office_docx_reader_test_cell($text)
{
	return '<w:tc><w:p><w:r><w:t>' . $text . '</w:t></w:r></w:p></w:tc>';
}

// A repeating section puts whole rows in content controls; a control or
// custom XML can also wrap a single cell.
function test_office_docx_reader_rows_in_content_controls()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$c = 'office_docx_reader_test_cell';
	$html = office_docx_reader_test_html(
		'<w:tbl><w:tblPr/><w:tblGrid><w:gridCol/><w:gridCol/></w:tblGrid>'
		. '<w:tr>' . $c('Ürün') . $c('Adet') . '</w:tr>'
		. '<w:sdt><w:sdtPr><w:alias w:val="Kalemler"/></w:sdtPr><w:sdtContent>'
		. '<w:sdt><w:sdtPr/><w:sdtContent><w:tr>' . $c('Kalem A') . $c('3') . '</w:tr></w:sdtContent></w:sdt>'
		. '<w:sdt><w:sdtPr/><w:sdtContent><w:tr>' . $c('Kalem B') . $c('5') . '</w:tr></w:sdtContent></w:sdt>'
		. '</w:sdtContent></w:sdt>'
		. '<w:customXml w:element="satir"><w:tr><w:sdt><w:sdtPr/><w:sdtContent>' . $c('Kalem C') . '</w:sdtContent></w:sdt>'
		. '<w:customXml w:element="adet">' . $c('7') . '</w:customXml></w:tr></w:customXml>'
		. '</w:tbl>'
	);

	pg_assert_same(
		'<table class="office-table"><tr><td><p>Ürün</p></td><td><p>Adet</p></td></tr><tr><td><p>Kalem A</p></td><td><p>3</p></td></tr>'
		. '<tr><td><p>Kalem B</p></td><td><p>5</p></td></tr><tr><td><p>Kalem C</p></td><td><p>7</p></td></tr></table>',
		$html,
		'rows in a repeating section, a row and cells in custom XML and a cell control'
	);
}

// numId 0 means "no numbering": it takes off the style's numbering too.
function test_office_docx_reader_numbering_taken_off()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$html = office_docx_reader_test_html(
		'<w:p><w:pPr><w:pStyle w:val="Heading1"/><w:numPr><w:numId w:val="0"/></w:numPr></w:pPr><w:r><w:t>Giriş</w:t></w:r></w:p>'
		. '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="0"/></w:numPr></w:pPr><w:r><w:t>Düz paragraf</w:t></w:r></w:p>'
		. '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Numaralı</w:t></w:r></w:p>'
		. '<w:p><w:pPr><w:pStyle w:val="NoList"/></w:pPr><w:r><w:t>Stil kaldırıyor</w:t></w:r></w:p>',
		'<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>',
		'<w:style w:type="paragraph" w:styleId="Heading1"><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr></w:style>'
		. '<w:style w:type="paragraph" w:styleId="NoList"><w:pPr><w:numPr><w:numId w:val="0"/></w:numPr></w:pPr></w:style>'
	);

	pg_assert_same(
		'<h1>Giriş</h1><p>Düz paragraf</p><ol class="office-list"><li class="office-lvl-0">Numaralı</li></ol><p>Stil kaldırıyor</p>',
		$html,
		'a heading and a paragraph without their numbering; the style\'s numbering where nothing takes it off'
	);
}
