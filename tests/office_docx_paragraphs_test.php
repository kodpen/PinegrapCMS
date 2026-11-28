<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Word paragraphs added and deleted (includes/fn/office_docx_paragraphs.php):
 * what the preview marks, a save that adds paragraphs taking the kind of
 * the one they follow (a list item continues its list) and deletes others,
 * the written file read back by the product's reader with its untouched
 * paragraphs and parts byte for byte, and the refusals: a picture, a table
 * cell's last paragraph, a section break and the rest, each leaving the
 * file as it was.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A document package with a numbering part, written from its main part.
function office_docx_paragraphs_test_file($body)
{
	$w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
		'_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . 'officeDocument" Target="word/document.xml"/></Relationships>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="' . $rel . 'numbering" Target="numbering.xml"/></Relationships>',
		'word/numbering.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:numbering xmlns:w="' . $w . '">'
			. '<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl></w:abstractNum>'
			. '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:document xmlns:w="' . $w . '"><w:body>'
			. implode('', $body) . '<w:sectPr/></w:body></w:document>',
	);

	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_paragraphs_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return array($path, $parts);
}

function office_docx_paragraphs_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function office_docx_paragraphs_test_done($path)
{
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');
	@unlink($path);
}

// Paragraphs 0 to 10: a heading, two numbered items, plain text, a picture,
// a cell with two paragraphs, a section break, a bookmark that runs on
// into the next paragraph, the document's last paragraph.
function office_docx_paragraphs_test_body()
{
	return array(
		'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Başlık</w:t></w:r></w:p>',
		'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:rPr><w:b/></w:rPr></w:pPr><w:r><w:t>Bir</w:t></w:r></w:p>',
		'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>İki</w:t></w:r></w:p>',
		'<w:p><w:r><w:rPr><w:i/></w:rPr><w:t>Silinecek</w:t></w:r></w:p>',
		'<w:p><w:r><w:drawing/></w:r></w:p>',
		'<w:tbl><w:tr><w:tc><w:tcPr/><w:p><w:r><w:t>Hücre bir</w:t></w:r></w:p><w:p><w:r><w:t>Hücre son</w:t></w:r></w:p></w:tc></w:tr></w:tbl>',
		'<w:p><w:pPr><w:pageBreakBefore/><w:sectPr><w:pgSz w:w="11906" w:h="16838"/></w:sectPr></w:pPr><w:r><w:t>Bölüm sonu</w:t></w:r></w:p>',
		'<w:p><w:bookmarkStart w:id="7" w:name="yer"/><w:r><w:t>İşaret</w:t></w:r></w:p>',
		'<w:p><w:bookmarkStart w:id="8" w:name="tam"/><w:r><w:t>Tam işaret</w:t></w:r><w:bookmarkEnd w:id="8"/><w:bookmarkEnd w:id="7"/></w:p>',
		'<w:p><w:r><w:t>Son</w:t></w:r></w:p>',
	);
}

function test_office_docx_paragraphs_marks()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the read is not run');
		return;
	}

	list($path) = office_docx_paragraphs_test_file(office_docx_paragraphs_test_body());
	$read = pg_office_read($path, 'rapor.docx', true);

	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);
	pg_assert_same(array('4' => 'picture', '6' => 'cell', '7' => 'section', '8' => 'bookmark', '9' => 'bookmark', '10' => 'last'),
		json_decode(json_encode($read['edit']['keeps']), true), 'why each paragraph that is kept may not be deleted');
	pg_assert_same(array(), json_decode(json_encode($read['edit']['shut']), true), 'a paragraph can be added after each');
	pg_assert_contains('<p data-p="3"><em>Silinecek</em></p>', $read['html'], 'the drawing as it was');

	office_docx_paragraphs_test_done($path);
}

function test_office_docx_paragraphs_delete_refusal()
{
	$w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	$document = new DOMDocument();
	$document->loadXML('<w:document xmlns:w="' . $w . '"><w:body>'
		. '<w:p><w:r><w:t>a</w:t></w:r></w:p>'
		. '<w:p><w:r><w:fldSimple/></w:r></w:p>'
		. '<w:tbl><w:tr><w:tc><w:p/><w:tbl/><w:p/></w:tc><w:tc><w:p/><w:bookmarkEnd w:id="1"/></w:tc></w:tr></w:tbl>'
		. '<w:sdt><w:sdtContent><w:p/></w:sdtContent></w:sdt>'
		. '<w:p><w:permStart w:id="2"/><w:permEnd w:id="2"/></w:p>'
		. '<w:p/><w:sectPr/></w:body></w:document>');
	$paragraphs = pg_office_docx_paragraphs($document);

	pg_assert_same('', pg_office_docx_delete_refusal($paragraphs[0]), 'a paragraph of text');
	pg_assert_same('locked', pg_office_docx_delete_refusal($paragraphs[1]), 'a field');
	pg_assert_same('', pg_office_docx_delete_refusal($paragraphs[2]), 'a cell\'s paragraph before a table');
	pg_assert_same('cell', pg_office_docx_delete_refusal($paragraphs[3]), 'the paragraph after a table that ends a cell');
	pg_assert_same('cell', pg_office_docx_delete_refusal($paragraphs[4]), 'a cell\'s only paragraph, a marker after it');
	pg_assert_same('locked', pg_office_docx_delete_refusal($paragraphs[5]), 'a paragraph in a content control');
	pg_assert_same('', pg_office_docx_delete_refusal($paragraphs[6]), 'a permission range that starts and ends in it');
	pg_assert_same('last', pg_office_docx_delete_refusal($paragraphs[7]), 'the body\'s last paragraph');
	pg_assert_same(false, pg_office_docx_add_allowed($paragraphs[5]), 'nothing is added inside a content control');
	pg_assert_same(true, pg_office_docx_add_allowed($paragraphs[3]), 'a paragraph may follow a cell\'s last one');
}

function test_office_docx_paragraphs_save()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	list($path, $parts) = office_docx_paragraphs_test_file(office_docx_paragraphs_test_body());
	$read = pg_office_read($path, 'rapor.docx', true);
	$directory = sys_get_temp_dir();

	$saved = pg_office_docx_save($path, array(
		array('after' => 2, 'text' => 'Üç'),
		array('after' => 2, 'text' => 'Dört', 'format' => array(array('start' => 0, 'end' => 4, 'set' => array('italic' => true)))),
		array('after' => 1, 'text' => ''),
		array('after' => 7, 'text' => 'Yeni bölüm'),
		array('after' => 3, 'text' => 'Başlık değil', 'kind' => 'normal'),
		array('p' => 3, 'delete' => true),
		array('after' => 6, 'text' => 'Hücre yeni'),
		array('p' => 6, 'delete' => true),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(8, $saved['changed'], 'six paragraphs added, two deleted');

	$xml = office_docx_paragraphs_test_part($path, 'word/document.xml');
	$back = pg_office_read($path, 'rapor.docx', true);
	$kinds = json_decode(json_encode($back['edit']['kinds']), true);

	$order = array();

	foreach (pg_office_docx_paragraphs((function () use ($xml) { $d = new DOMDocument(); $d->loadXML($xml); return $d; })()) as $paragraph) {
		$order[] = $paragraph->textContent;
	}

	pg_assert_same(array('Başlık', 'Bir', '', 'İki', 'Üç', 'Dört', 'Başlık değil', '', 'Hücre bir', 'Hücre yeni', 'Bölüm sonu', 'Yeni bölüm', 'İşaret', 'Tam işaret', 'Son'),
		$order, 'added in their places, in the order asked after the same paragraph; the deleted ones gone');
	pg_assert_same('number', $kinds['4'], 'an item added after a numbered one continues its list');
	pg_assert_same('number', $kinds['5'], 'and the next one too');
	pg_assert_same('number', $kinds['2'], 'an empty item added in the list');
	pg_assert_same('normal', $kinds['6'], 'a kind asked for the added paragraph');
	pg_assert_contains('<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:rPr><w:b/></w:rPr></w:pPr></w:p>', $xml, 'an empty paragraph takes the properties and the mark of the one before');
	pg_assert_contains('<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Üç</w:t></w:r></w:p>', $xml, 'the new text in a run');
	pg_assert_contains('<w:r><w:rPr><w:i/><w:iCs/></w:rPr><w:t>Dört</w:t></w:r>', $xml, 'the added paragraph\'s format');
	pg_assert_contains('<w:p><w:r><w:t>Yeni bölüm</w:t></w:r></w:p>', $xml, 'neither the section break nor the page break before is copied');
	pg_assert_contains('<w:tc><w:tcPr/><w:p><w:r><w:t>Hücre bir</w:t></w:r></w:p><w:p><w:r><w:t>Hücre yeni</w:t></w:r></w:p></w:tc>', $xml, 'a cell\'s last paragraph deleted once another took its place');
	pg_assert_same(false, strpos($xml, 'Silinecek'), 'the deleted paragraph is gone');

	// Untouched paragraphs and parts byte for byte.
	$body = office_docx_paragraphs_test_body();

	foreach (array(0, 1, 2, 4, 6, 7, 8, 9) as $index) {
		pg_assert_contains($body[$index], $xml, 'paragraph ' . $index . ' as it was');
	}

	foreach (array('word/numbering.xml', 'word/_rels/document.xml.rels', '[Content_Types].xml', '_rels/.rels') as $name) {
		pg_assert_same($parts[$name], office_docx_paragraphs_test_part($path, $name), $name . ' untouched');
	}

	office_docx_paragraphs_test_done($path);
}

function test_office_docx_paragraphs_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	list($path) = office_docx_paragraphs_test_file(office_docx_paragraphs_test_body());
	$read = pg_office_read($path, 'rapor.docx', true);
	$before = file_get_contents($path);
	$directory = sys_get_temp_dir();

	$cases = array(
		array(array(array('p' => 4, 'delete' => true)), 'delete_picture', '5', 'a drawing, a text box'),
		array(array(array('p' => 6, 'delete' => true)), 'delete_cell', '7', 'last paragraph of a table cell'),
		array(array(array('p' => 5, 'delete' => true), array('p' => 6, 'delete' => true)), 'delete_cell', '7', 'last paragraph of a table cell'),
		array(array(array('p' => 7, 'delete' => true)), 'delete_section', '8', 'ends a section'),
		array(array(array('p' => 8, 'delete' => true)), 'delete_bookmark', '9', 'bookmark'),
		array(array(array('p' => 10, 'delete' => true)), 'delete_last', '11', 'last paragraph of the document'),
		array(array(array('p' => 3, 'text' => 'yeni'), array('p' => 4, 'delete' => true)), 'delete_picture', '5', 'a drawing, a text box'),
		array(array(array('after' => 40, 'text' => 'x')), 'missing', '41', 'not in the file'),
		array(array(array('after' => 'iki', 'text' => 'x')), 'none', '', ''),
		array(array(array('after' => 1, 'kind' => 'heading9')), 'none', '', ''),
	);

	foreach ($cases as $case) {
		$saved = pg_office_docx_save($path, $case[0], $read['version'], $directory);

		pg_assert_same(false, $saved['ok'], 'refused: ' . $case[1]);
		pg_assert_same($case[1], $saved['code'], 'the reason: ' . $case[1]);

		if ($case[2] !== '') {
			pg_assert_contains('Paragraph ' . $case[2] . ' ', $saved['error'], 'the message names the paragraph');
			pg_assert_contains($case[3], $saved['error'], 'the message says why');
		}

		pg_assert_same($before, file_get_contents($path), 'the file as it was: ' . $case[1]);
	}

	office_docx_paragraphs_test_done($path);

	// Nothing is added inside a content control.
	list($path) = office_docx_paragraphs_test_file(array('<w:sdt><w:sdtContent><w:p><w:r><w:t>Form</w:t></w:r></w:p></w:sdtContent></w:sdt>', '<w:p/>'));
	$read = pg_office_read($path, 'form.docx', true);
	$saved = pg_office_docx_save($path, array(array('after' => 0, 'text' => 'x')), $read['version'], $directory);

	pg_assert_same('add_locked', $saved['code'], 'no paragraph added in a content control');
	pg_assert_contains('content control', $saved['error'], 'the message says where');

	office_docx_paragraphs_test_done($path);
}

// The editor's source (assets/js/pg_office_view.js): a unit test cannot press
// the buttons, so these pin what they send and that the paragraph picker is
// not shadowed by the style choice (a var named select hoisted over the
// select() function broke a click on a paragraph).
function test_office_docx_paragraphs_editor_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	$start = strpos($source, '    function renderDocument(');
	$body = substr($source, $start, strpos($source, "\n    function renderCsv(", $start) - $start);

	pg_assert_true($start !== false, 'renderDocument() is found');
	pg_assert_same(0, preg_match('/\bvar select\b/', $body), 'no variable hides select()');
	pg_assert_contains("items.push({ p: position, delete: true });", $body, 'a deleted paragraph is sent by its position');
	pg_assert_contains("entry(key, { after: Number(node.getAttribute('data-after')) })", $body, 'an added one by the paragraph it follows');
	pg_assert_contains("strings['keep_' + keep]", $body, 'a kept paragraph says why');
}
