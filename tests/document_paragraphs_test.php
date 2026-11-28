<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a document turned into the paragraphs the designer's document
 * reader takes (includes/fn/document_paragraphs.php): a .docx read through
 * the product's own Word reader, with its headings, lists, quotes, tables,
 * bold leads and pictures taken out; a .md or .txt read as text; the
 * limits and how much was read.
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
// document_reader_test_node(): runs the designer's reader in Node.
require_once(__DIR__ . '/document_reader_test.php');

if (is_file(PG_FUNCTIONS_DIR . '/includes/fn/document_paragraphs.php')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/document_paragraphs.php');
}

// The module is loaded by the places that need it, never by functions.php.
function document_paragraphs_test_loaded()
{
	pg_assert_true(function_exists('pg_document_paragraphs'), 'includes/fn/document_paragraphs.php is there');

	return function_exists('pg_document_paragraphs');
}

// A one-pixel PNG.
function document_paragraphs_test_png()
{
	return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
}

// A document with the given body (and styles) on disk; its path.
function document_paragraphs_test_docx($body, $styles = '', $extra = array())
{
	$w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="' . $w . '"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
			. ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
			. ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><w:body>' . $body . '</w:body></w:document>',
		'word/numbering.xml' => '<?xml version="1.0"?><w:numbering xmlns:w="' . $w . '">'
			. '<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>'
			. '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>'
			. '<Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/chart.emf"/>'
			. '</Relationships>',
		'word/media/image1.png' => document_paragraphs_test_png(),
		'word/media/chart.emf' => 'not a picture a browser shows',
	);

	if ($styles !== '') {
		$parts['word/styles.xml'] = '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '">' . $styles . '</w:styles>';
	}

	return office_test_package(array_merge($parts, $extra));
}

// A paragraph of the given style id and runs.
function document_paragraphs_test_p($style, $runs, $props = '')
{
	return '<w:p><w:pPr>' . (($style !== '') ? '<w:pStyle w:val="' . $style . '"/>' : '') . $props . '</w:pPr>' . $runs . '</w:p>';
}

// A run of text, bold when asked.
function document_paragraphs_test_r($text, $bold = false)
{
	return '<w:r>' . ($bold ? '<w:rPr><w:b/></w:rPr>' : '') . '<w:t xml:space="preserve">' . $text . '</w:t></w:r>';
}

// A run holding a picture of the given relationship and description.
function document_paragraphs_test_picture($rel, $alt)
{
	return '<w:r><w:drawing><wp:inline><wp:docPr id="1" name="Picture 1" descr="' . $alt . '"/>'
		. '<a:graphic><a:graphicData><a:blip r:embed="' . $rel . '"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
}

function test_document_paragraphs_kind_by_extension()
{
	if (!document_paragraphs_test_loaded()) {
		return;
	}

	pg_assert_same('docx', pg_document_kind('Teklif.DOCX'), 'a Word document');
	pg_assert_same('docx', pg_document_kind('a.dotx'), 'a Word template');
	pg_assert_same('text', pg_document_kind('notes.md'), 'Markdown');
	pg_assert_same('text', pg_document_kind('notes.markdown'), 'Markdown, long form');
	pg_assert_same('text', pg_document_kind('notes.txt'), 'plain text');
	pg_assert_same('', pg_document_kind('old.doc'), 'the binary Word format is not read');
	pg_assert_same('', pg_document_kind('sheet.xlsx'), 'a workbook is not a document');
}

// Headings by their built-in name, the title, a bold lead, a list item, a
// quote, a table, a picture; empty paragraphs and deleted text left out.
function test_document_paragraphs_docx_styles()
{
	if (!document_paragraphs_test_loaded() || !pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$p = 'document_paragraphs_test_p';
	$r = 'document_paragraphs_test_r';
	$cell = function ($text) use ($p, $r) {
		return '<w:tc>' . $p('', $r($text)) . '</w:tc>';
	};
	$styles = '<w:style w:type="paragraph" w:styleId="Balk2"><w:name w:val="heading 2"/></w:style>'
		. '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/></w:style>';
	$path = document_paragraphs_test_docx(
		$p('Title', $r('Kafe Lale'))
		. $p('', $r('Mahallenin '), '') . $p('', '')
		. $p('Balk2', $r('Hizmetler'))
		. $p('', $r('Kahve:', true) . $r(' Taze çekilmiş.'))
		. $p('', $r('Pasta') , '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>')
		. $p('Quote', $r('Harika bir yer.') . '<w:del><w:r><w:delText>silindi</w:delText></w:r></w:del>')
		. '<w:sdt><w:sdtContent>' . $p('', $r('Kontrol içinde')) . '</w:sdtContent></w:sdt>'
		. '<w:tbl><w:tr>' . $cell('Ürün') . $cell('Fiyat') . '</w:tr><w:tr>' . $cell('Latte') . $cell('90 TL') . '</w:tr></w:tbl>'
		. $p('', document_paragraphs_test_picture('rId5', 'Vitrin'))
		. $p('', document_paragraphs_test_picture('rId6', 'Grafik'))
		. '<w:sectPr/>',
		$styles
	);
	$doc = pg_document_paragraphs($path, 'kafe.docx');
	unlink($path);

	pg_assert_true($doc['ok'], 'read: ' . $doc['error']);
	pg_assert_same('', $doc['text'], 'a document gives paragraphs, not text');
	pg_assert_same(array(
		array('text' => 'Kafe Lale', 'style' => 'heading', 'level' => 1),
		array('text' => 'Mahallenin', 'style' => 'paragraph'),
		array('text' => 'Hizmetler', 'style' => 'heading', 'level' => 2),
		array('text' => 'Kahve: Taze çekilmiş.', 'style' => 'paragraph', 'lead' => 'Kahve:'),
		array('text' => 'Pasta', 'style' => 'item'),
		array('text' => 'Harika bir yer.', 'style' => 'quote'),
		array('text' => 'Kontrol içinde', 'style' => 'paragraph'),
		array('text' => '', 'style' => 'table', 'rows' => array(array('Ürün', 'Fiyat'), array('Latte', '90 TL'))),
		array('text' => '', 'style' => 'picture', 'src' => 'picture:0', 'alt' => 'Vitrin', 'picture' => 0),
	), $doc['paragraphs'], 'the paragraphs with their styles');
	pg_assert_same(array(array('part' => 'word/media/image1.png', 'name' => 'image1.png', 'mime' => 'image/png', 'size' => strlen(document_paragraphs_test_png()), 'alt' => 'Vitrin')), $doc['pictures'], 'the picture taken out');
	pg_assert_same(1, $doc['pictures_lost'], 'a picture of a kind a browser does not show is counted, not listed');
	pg_assert_same('', $doc['cut'], 'nothing left unread');
	pg_assert_same(100, $doc['read']['percent'], 'the whole document read');
	pg_assert_same(9, $doc['read']['paragraphs'], 'paragraphs read');
}

// A picture's bytes come out of the package only from its media, and only
// when they are a picture.
function test_document_paragraphs_picture_bytes()
{
	if (!document_paragraphs_test_loaded() || !pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$path = document_paragraphs_test_docx('<w:p/>', '', array('word/document2.png' => 'x'));
	$picture = pg_document_picture($path, 'word/media/image1.png');

	pg_assert_same(array('bytes' => document_paragraphs_test_png(), 'mime' => 'image/png', 'name' => 'image1.png'), $picture, 'the picture taken out');
	pg_assert_same(null, pg_document_picture($path, 'word/media/chart.emf'), 'not a picture kind');
	pg_assert_same(null, pg_document_picture($path, 'word/media/../document.xml'), 'no way out of the media');
	pg_assert_same(null, pg_document_picture($path, 'word/document2.png'), 'only the media folder');
	pg_assert_same(null, pg_document_picture($path, 'word/media/none.png'), 'missing');
	unlink($path);
}

// Past the reader's paragraph limit the rest is left, and the answer says
// how much was read.
function test_document_paragraphs_docx_limit()
{
	if (!document_paragraphs_test_loaded() || !pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$body = str_repeat(document_paragraphs_test_p('', document_paragraphs_test_r('Satır')), PG_DOCUMENT_MAX_PARAGRAPHS * 2);
	$path = document_paragraphs_test_docx($body);
	$doc = pg_document_paragraphs($path, 'long.docx');
	unlink($path);

	pg_assert_true($doc['ok'], 'read');
	pg_assert_same(PG_DOCUMENT_MAX_PARAGRAPHS, count($doc['paragraphs']), 'the limit held');
	pg_assert_same('paragraphs', $doc['cut'], 'why the rest was left');
	pg_assert_same(PG_DOCUMENT_MAX_PARAGRAPHS, $doc['read']['paragraphs'], 'paragraphs read');
	pg_assert_same(50, $doc['read']['percent'], 'half of the document read');
}

// A document held whole in one content control (a template's usual shape)
// says how much of it was read, not "100%", when the limit cut it.
function test_document_paragraphs_docx_limit_in_a_control()
{
	if (!document_paragraphs_test_loaded() || !pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$body = '<w:sdt><w:sdtContent>' . str_repeat(document_paragraphs_test_p('', document_paragraphs_test_r('Satır')), PG_DOCUMENT_MAX_PARAGRAPHS * 2) . '</w:sdtContent></w:sdt>';
	$path = document_paragraphs_test_docx($body);
	$doc = pg_document_paragraphs($path, 'template.docx');
	unlink($path);

	pg_assert_same('paragraphs', $doc['cut'], 'the limit cut it');
	pg_assert_same(50, $doc['read']['percent'], 'half of the document read');
}

// The limit met at a table: the table is left out whole and the share is
// counted up to it.
function test_document_paragraphs_docx_limit_at_a_table()
{
	if (!document_paragraphs_test_loaded() || !pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$long = str_repeat('Uzun bir satır. ', 25000);
	$cell = '<w:tc>' . document_paragraphs_test_p('', document_paragraphs_test_r($long)) . '</w:tc>';
	$body = document_paragraphs_test_p('', document_paragraphs_test_r($long))
		. '<w:tbl><w:tr>' . $cell . '</w:tr></w:tbl>'
		. document_paragraphs_test_p('', document_paragraphs_test_r('Son'));
	$path = document_paragraphs_test_docx($body);
	$doc = pg_document_paragraphs($path, 'table.docx');
	unlink($path);

	pg_assert_true($doc['ok'], 'read');
	pg_assert_same('paragraphs', $doc['cut'], 'the character limit met at the table');
	pg_assert_same(1, count($doc['paragraphs']), 'the table left out whole');
	pg_assert_same(33, $doc['read']['percent'], 'one of three blocks read');
}

// A .md or .txt comes back as UTF-8 text, which the browser's reader turns
// into paragraphs as it does a paste; past the size limit at a line's end.
function test_document_paragraphs_text_files()
{
	if (!document_paragraphs_test_loaded()) {
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_docpar_');
	file_put_contents($path, "\xEF\xBB\xBF# Başlık\n\nMetin.\n");
	$doc = pg_document_paragraphs($path, 'a.md');

	pg_assert_true($doc['ok'], 'read');
	pg_assert_same("# Başlık\n\nMetin.\n", $doc['text'], 'UTF-8, the byte order mark left out');
	pg_assert_same(array(), $doc['paragraphs'], 'the browser makes the paragraphs');
	pg_assert_same(100, $doc['read']['percent'], 'all of it');

	file_put_contents($path, "Ba\xFEl\xFDk\n");
	$doc = pg_document_paragraphs($path, 'a.txt');
	pg_assert_same("Başlık\n", $doc['text'], 'Windows-1254 converted');

	file_put_contents($path, str_repeat(str_repeat('a', 99) . "\n", (int) (PG_DOCUMENT_MAX_TEXT / 100) * 2));
	$doc = pg_document_paragraphs($path, 'a.txt');
	pg_assert_same('size', $doc['cut'], 'cut by size');
	pg_assert_same("\n", substr($doc['text'], -1), 'cut at a line end');
	pg_assert_true(strlen($doc['text']) <= PG_DOCUMENT_MAX_TEXT, 'within the limit');
	pg_assert_same(50, $doc['read']['percent'], 'half of it read');
	unlink($path);
}

function test_document_paragraphs_refusals()
{
	if (!document_paragraphs_test_loaded()) {
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_docpar_');
	file_put_contents($path, 'not a zip');
	$doc = pg_document_paragraphs($path, 'a.docx');
	pg_assert_false($doc['ok'], 'not a package');
	pg_assert_true($doc['error'] !== '', 'says why');

	$doc = pg_document_paragraphs($path, 'a.pdf');
	pg_assert_false($doc['ok'], 'not a kind read here');
	unlink($path);
}

// What the document gives is what the designer's reader takes: a heading,
// its lead, cards led by bold words.
function test_document_paragraphs_feed_the_reader()
{
	if (!document_paragraphs_test_loaded() || !pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$p = 'document_paragraphs_test_p';
	$r = 'document_paragraphs_test_r';
	$styles = '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/></w:style>'
		. '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>';
	$path = document_paragraphs_test_docx(
		$p('Heading1', $r('Kafe Lale')) . $p('', $r('Mahallenin kahvecisi.'))
		. $p('Heading2', $r('Neler var'))
		. $p('', $r('Kahve', true) . $r(' — taze çekilmiş.'))
		. $p('', $r('Pasta', true) . $r(' — her sabah.'))
		. $p('', $r('Çay', true) . $r(' — demlik.')),
		$styles
	);
	$doc = pg_document_paragraphs($path, 'kafe.docx');
	unlink($path);

	$out = document_reader_test_node('out = R.read(' . json_encode($doc['paragraphs'], JSON_UNESCAPED_UNICODE) . ').sections.map(function (s) { return [s.kind, s.heading, s.items.length]; });');

	if ($out === null) {
		pg_assert_true(true, 'no Node here: the reader is not run');
		return;
	}

	pg_assert_same(array(array('intro', 'Kafe Lale', 1), array('cards', 'Neler var', 3)), $out, 'an intro and three cards');
}
