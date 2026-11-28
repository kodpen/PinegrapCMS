<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a picture put into a Word document after a paragraph
 * (includes/fn/office_docx_picture.php): the entry checked (kind, size,
 * description), the size it is drawn at, the part, relationship and content
 * type written, a save read back by the product's reader with the rest of
 * the file byte for byte, the refusals leaving the file as it was, and a
 * paragraph holding a plain picture deleted where other drawings keep theirs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_docx_picture_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

// A grey PNG of the given size, built here so no image library is needed.
function office_docx_picture_test_png($width, $height)
{
	$chunk = function ($type, $data) {
		return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
	};

	$rows = str_repeat("\x00" . str_repeat("\x80", $width), $height);

	return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0))
		. $chunk('IDAT', gzcompress($rows, 9)) . $chunk('IEND', '');
}

function office_docx_picture_test_gif()
{
	return base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

// A 4 by 3 JPEG.
function office_docx_picture_test_jpeg()
{
	return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDABALDA4MChAODQ4SERATGCgaGBYWGDEjJR0oOjM9PDkzODdASFxOQERXRTc4UG1RV19iZ2hnPk1xeXBkeFxlZ2P/2wBDARESEhgVGC8aGi9jQjhCY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2P/wAARCAADAAQDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDAooorgPrT/9k=');
}

// A document package written from its body; its relationships and content
// types as given, or a document's usual ones.
function office_docx_picture_test_file($body, $rels = '', $types = '', $extra = array())
{
	$w = office_docx_picture_test_w();
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
	$parts = array(
		'[Content_Types].xml' => ($types !== '') ? $types : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
		'_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . 'officeDocument" Target="word/document.xml"/></Relationships>',
		'word/_rels/document.xml.rels' => ($rels !== '') ? $rels : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . 'styles" Target="styles.xml"/></Relationships>',
		'word/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:styles xmlns:w="' . $w . '"/>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:document xmlns:w="' . $w . '"><w:body>'
			. implode('', $body) . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:gutter="0"/></w:sectPr></w:body></w:document>',
	) + $extra;

	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_picture_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return array($path, $parts);
}

function office_docx_picture_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function office_docx_picture_test_done($path)
{
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');
	@unlink($path);
}

// A plain picture drawn inline, naming relationship rId9.
function office_docx_picture_test_drawing()
{
	return '<w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:extent cx="9525" cy="9525"/><wp:docPr id="5" name="Eski"/>'
		. '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
		. '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId9"/></pic:blipFill></pic:pic>'
		. '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
}

function test_office_docx_picture_request()
{
	$png = office_docx_picture_test_png(3, 2);
	$picture = pg_office_docx_picture_request(array('data' => base64_encode($png), 'name' => "  Logo\n bizim "));

	pg_assert_same(array('bytes' => $png, 'kind' => 'png', 'width' => 3, 'height' => 2, 'name' => 'Logo bizim'), $picture, 'a PNG with its size and description');
	pg_assert_same('jpeg', pg_office_docx_picture_request(array('data' => base64_encode(office_docx_picture_test_jpeg())))['kind'], 'a JPEG');
	pg_assert_same(array(4, 3), array_slice(array_values(pg_office_docx_picture_request(array('data' => base64_encode(office_docx_picture_test_jpeg())))), 2, 2), 'the JPEG\'s size');
	pg_assert_same('gif', pg_office_docx_picture_request(array('data' => chunk_split(base64_encode(office_docx_picture_test_gif()), 8)))['kind'], 'a GIF, its base64 in lines');

	pg_assert_same('picture_type', pg_office_docx_picture_request(array('data' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'))), 'an SVG');
	pg_assert_same('picture_type', pg_office_docx_picture_request(array('data' => base64_encode("BM\x00\x00"))), 'a BMP');
	pg_assert_same('picture_type', pg_office_docx_picture_request(array('data' => base64_encode("\x89PNG\r\n\x1a\nbroken"))), 'a broken PNG');
	pg_assert_same('picture_type', pg_office_docx_picture_request(array('data' => base64_encode('GIF89a' . substr($png, 6)))), 'a PNG calling itself a GIF');
	pg_assert_same('picture_type', pg_office_docx_picture_request(array('data' => '%%%')), 'not base64');
	pg_assert_same('picture_large', pg_office_docx_picture_request(array('data' => str_repeat('A', (int) (PG_OFFICE_MAX_IMAGE * 1.4)))), 'over 2 MB');
	pg_assert_same('none', pg_office_docx_picture_request(array('name' => 'x')), 'no data');
	pg_assert_same('none', pg_office_docx_picture_request('x'), 'not a picture');
	pg_assert_same('none', pg_office_docx_picture_request(array('data' => base64_encode($png), 'name' => array())), 'a description that is not a text');
}

function test_office_docx_picture_extent()
{
	pg_assert_same(array(300 * 9525, 200 * 9525), pg_office_docx_picture_extent(300, 200, 9026), 'its own size at 96 per inch');
	pg_assert_same(array(5731510, 2865755), pg_office_docx_picture_extent(2000, 1000, 9026), 'a wide picture made as wide as the text, keeping its shape');
}

function test_office_docx_picture_plain()
{
	$document = new DOMDocument();
	$document->loadXML('<w:body xmlns:w="' . office_docx_picture_test_w() . '">'
		. '<w:p>' . office_docx_picture_test_drawing() . '<w:r><w:t>Yazı</w:t></w:r></w:p>'
		. '<w:p><w:r><w:drawing><w:txbxContent><w:p/></w:txbxContent></w:drawing></w:r></w:p>'
		. '<w:p><w:r><w:pict/></w:r></w:p>'
		. '<w:p>' . office_docx_picture_test_drawing() . '<w:r><w:fldChar w:fldCharType="begin"/></w:r></w:p></w:body>');
	$paragraphs = iterator_to_array($document->getElementsByTagNameNS(office_docx_picture_test_w(), 'p'));
	$before = $document->saveXML();

	pg_assert_same(1, count(pg_office_docx_picture_drawings($paragraphs[0])), 'a plain picture beside text');
	pg_assert_same('', pg_office_docx_picture_lock($paragraphs[0], pg_office_docx_picture_drawings($paragraphs[0])), 'nothing else keeps it');
	pg_assert_same($before, $document->saveXML(), 'the document not touched');
	pg_assert_same(null, pg_office_docx_picture_drawings($paragraphs[1]), 'a text box');
	pg_assert_same(null, pg_office_docx_picture_drawings($paragraphs[3]), 'a legacy picture');
	pg_assert_same('field', pg_office_docx_picture_lock($paragraphs[4], pg_office_docx_picture_drawings($paragraphs[4])), 'a field beside a picture still keeps it');
}

function test_office_docx_picture_save()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$body = array(
		'<w:p><w:pPr><w:pStyle w:val="Heading1"/><w:jc w:val="center"/></w:pPr><w:r><w:t>Başlık</w:t></w:r></w:p>',
		'<w:tbl><w:tr><w:tc><w:tcPr><w:tcW w:w="3000" w:type="dxa"/></w:tcPr><w:p><w:r><w:t>Hücre</w:t></w:r></w:p></w:tc></w:tr></w:tbl>',
		'<w:p><w:r><w:t>Son</w:t></w:r></w:p>',
	);
	list($path, $parts) = office_docx_picture_test_file($body);
	$read = pg_office_read($path, 'resim.docx', true);
	$png = office_docx_picture_test_png(2000, 1000);
	$jpeg = office_docx_picture_test_jpeg();

	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);

	$saved = pg_office_docx_save($path, array(
		array('after' => 0, 'picture' => array('data' => base64_encode($png), 'name' => 'Geniş "çizim"')),
		array('after' => 0, 'text' => 'Resimden sonra'),
		array('after' => 1, 'picture' => array('data' => base64_encode($png))),
		array('after' => 2, 'picture' => array('data' => base64_encode($jpeg))),
	), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$xml = office_docx_picture_test_part($path, 'word/document.xml');
	$rels = office_docx_picture_test_part($path, 'word/_rels/document.xml.rels');
	$types = office_docx_picture_test_part($path, '[Content_Types].xml');

	pg_assert_same($png, office_docx_picture_test_part($path, 'word/media/image1.png'), 'the PNG\'s part');
	pg_assert_same($jpeg, office_docx_picture_test_part($path, 'word/media/image1.jpeg'), 'the JPEG\'s part');
	pg_assert_same(false, office_docx_picture_test_part($path, 'word/media/image2.png'), 'the same picture twice is one part');
	pg_assert_contains('<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>', $rels, 'the PNG\'s relationship');
	pg_assert_contains('<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.jpeg"/>', $rels, 'the JPEG\'s relationship');
	pg_assert_same(2, substr_count($rels, 'media/'), 'two relationships for three pictures');
	pg_assert_contains('<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/><Override PartName="/word/document.xml"', $types, 'content types for the new extensions, before the overrides');

	pg_assert_contains('<w:p xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing>', $xml, 'the picture\'s paragraph takes only the heading\'s alignment');
	pg_assert_contains('<wp:extent cx="5731510" cy="2865755"/>', $xml, 'a wide picture as wide as the text');
	pg_assert_contains('<wp:docPr id="1" name="Picture 1" descr="Geniş &quot;çizim&quot;"/>', $xml, 'its description');
	pg_assert_contains('<a:blip r:embed="rId2"/>', $xml, 'drawn from its relationship');
	pg_assert_contains('<wp:extent cx="38100" cy="28575"/>', $xml, 'a small picture at its own size');
	pg_assert_contains('<wp:docPr id="2" name="Picture 2"/>', $xml, 'the next drawing id');
	pg_assert_contains('<wp:docPr id="3" name="Picture 3"/>', $xml, 'the third drawing id');
	pg_assert_contains('<w:t>Hücre</w:t></w:r></w:p><w:p xmlns:wp=', $xml, 'a picture inside a cell');
	pg_assert_contains('<wp:extent cx="1767840" cy="883920"/>', $xml, 'in a cell, as wide as the cell less its margins');
	pg_assert_contains('</a:graphic></wp:inline></w:drawing></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Heading1"/><w:jc w:val="center"/></w:pPr><w:r><w:t>Resimden sonra</w:t></w:r></w:p>', $xml, 'a paragraph asked after the same one follows the picture');
	pg_assert_same(3, substr_count($xml, 'xmlns:wp='), 'the namespaces declared once a picture, on its paragraph');

	foreach ($body as $index => $block) {
		if ($index !== 1) {
			pg_assert_contains($block, $xml, 'block ' . $index . ' as it was');
		}
	}

	foreach (array('word/styles.xml', '_rels/.rels') as $name) {
		pg_assert_same($parts[$name], office_docx_picture_test_part($path, $name), $name . ' untouched');
	}

	// Read back by the product's reader: three pictures drawn.
	$back = pg_office_read($path, 'resim.docx', true);

	pg_assert_true($back['ok'], 'read back');
	pg_assert_same(3, substr_count($back['html'], '<img class="office-img" src="data:image/'), 'the pictures drawn');
	pg_assert_contains('alt="Geniş &quot;çizim&quot;"', $back['html'], 'with its description');
	pg_assert_same(0, $back['images_lost'], 'none lost');

	// A paragraph holding a new picture is deleted like any other.
	$keeps = json_decode(json_encode($back['edit']['keeps']), true);
	pg_assert_same(array('4' => 'cell', '6' => 'last'), $keeps, 'only a cell\'s and the document\'s last paragraphs are kept');

	$saved = pg_office_docx_save($path, array(array('p' => 1, 'delete' => true)), $back['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'a picture\'s paragraph deleted: ' . $saved['error']);
	pg_assert_same(2, substr_count(office_docx_picture_test_part($path, 'word/document.xml'), '<w:drawing>'), 'two pictures left');

	office_docx_picture_test_done($path);
}

function test_office_docx_picture_existing_and_strict()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	// A document holding image1.png and an upper-case PNG default.
	$types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
		. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
		. '<Default Extension="xml" ContentType="application/xml"/><Default Extension="PNG" ContentType="image/png"/>'
		. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
	$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>';
	$old = office_docx_picture_test_png(1, 1);
	list($path, $parts) = office_docx_picture_test_file(array('<w:p>' . office_docx_picture_test_drawing() . '</w:p>', '<w:p><w:r><w:t>Son</w:t></w:r></w:p>'), $rels, $types, array('word/media/image1.png' => $old));
	$read = pg_office_read($path, 'eski.docx', true);

	pg_assert_same(array('1' => 'last'), json_decode(json_encode($read['edit']['keeps']), true), 'a plain picture\'s paragraph may be deleted');

	$saved = pg_office_docx_save($path, array(array('after' => 1, 'picture' => array('data' => base64_encode(office_docx_picture_test_png(2, 2))))), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same($old, office_docx_picture_test_part($path, 'word/media/image1.png'), 'the old picture kept');
	pg_assert_same(office_docx_picture_test_png(2, 2), office_docx_picture_test_part($path, 'word/media/image2.png'), 'a name no entry has');
	pg_assert_same($types, office_docx_picture_test_part($path, '[Content_Types].xml'), 'the content types untouched: PNG has one');
	pg_assert_contains('Target="media/image2.png"', office_docx_picture_test_part($path, 'word/_rels/document.xml.rels'), 'its relationship');
	pg_assert_contains('<wp:docPr id="6" name="Picture 6"/>', office_docx_picture_test_part($path, 'word/document.xml'), 'an id after the document\'s');

	// The old picture's paragraph deleted, with what the file holds.
	$read = pg_office_read($path, 'eski.docx', true);
	$saved = pg_office_docx_save($path, array(array('p' => 0, 'delete' => true)), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'the old picture\'s paragraph deleted: ' . $saved['error']);
	pg_assert_same(false, strpos(office_docx_picture_test_part($path, 'word/document.xml'), 'rId9'), 'its drawing gone');

	office_docx_picture_test_done($path);

	// A strict document's picture in strict namespaces.
	list($path) = office_docx_picture_test_file(array('<w:p/>'));
	$zip = new ZipArchive();
	$zip->open($path);
	$zip->addFromString('word/document.xml', str_replace(office_docx_picture_test_w(), 'http://purl.oclc.org/ooxml/wordprocessingml/main', $zip->getFromName('word/document.xml')));
	$zip->close();
	$read = pg_office_read($path, 'strict.docx', true);
	$saved = pg_office_docx_save($path, array(array('after' => 0, 'picture' => array('data' => base64_encode(office_docx_picture_test_gif())))), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'strict saved: ' . $saved['error']);
	pg_assert_contains('xmlns:wp="http://purl.oclc.org/ooxml/drawingml/wordprocessingDrawing"', office_docx_picture_test_part($path, 'word/document.xml'), 'strict drawing');
	pg_assert_contains('Type="http://purl.oclc.org/ooxml/officeDocument/relationships/image" Target="media/image1.gif"', office_docx_picture_test_part($path, 'word/_rels/document.xml.rels'), 'strict relationship');
	pg_assert_contains('<Default Extension="gif" ContentType="image/gif"/>', office_docx_picture_test_part($path, '[Content_Types].xml'), 'gif content type');

	office_docx_picture_test_done($path);
}

function test_office_docx_picture_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	// The document's pictures take nearly 8 MB already.
	$big = str_repeat('x', PG_OFFICE_MAX_IMAGES - 20);
	list($path) = office_docx_picture_test_file(array('<w:p><w:r><w:t>Bir</w:t></w:r></w:p>', '<w:sdt><w:sdtContent><w:p><w:r><w:t>Form</w:t></w:r></w:p></w:sdtContent></w:sdt>', '<w:p/>'),
		'', '', array('word/media/image1.jpeg' => $big));
	$read = pg_office_read($path, 'dolu.docx', true);
	$before = file_get_contents($path);

	$cases = array(
		array(array(array('after' => 0, 'picture' => array('data' => base64_encode(office_docx_picture_test_gif())))), 'picture_room', '8 MB'),
		array(array(array('after' => 0, 'picture' => array('data' => base64_encode('GIF89a')))), 'picture_type', 'PNG, JPEG or GIF'),
		array(array(array('after' => 0, 'picture' => array('data' => str_repeat('A', 3 * 1024 * 1024)))), 'picture_large', '2 MB'),
		array(array(array('after' => 1, 'picture' => array('data' => base64_encode(office_docx_picture_test_gif())))), 'add_locked', 'content control'),
		array(array(array('after' => 9, 'picture' => array('data' => base64_encode(office_docx_picture_test_gif())))), 'missing', 'not in the file'),
	);

	foreach ($cases as $case) {
		$saved = pg_office_docx_save($path, $case[0], $read['version'], sys_get_temp_dir());

		pg_assert_same(false, $saved['ok'], 'refused: ' . $case[1]);
		pg_assert_same($case[1], $saved['code'], 'the reason: ' . $case[1]);
		pg_assert_contains($case[2], $saved['error'], 'the message: ' . $case[1]);
		pg_assert_same($before, file_get_contents($path), 'the file as it was: ' . $case[1]);
	}

	office_docx_picture_test_done($path);
}
