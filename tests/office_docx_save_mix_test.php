<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Word document save that adds parts of more than one kind at
 * once (includes/fn/office_edit.php, pg_office_docx_save_parts()): the
 * numbering a first list adds beside a link or a picture keeps its
 * relationship and content type, every relationship id stays unique, and
 * an added part's relationship type is the standard one whatever
 * relationship the file lists first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_docx_save_mix_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

// A document as the product's New document writes it: styles only, no
// numbering part. $rels replaces the main part's relationships.
function office_docx_save_mix_test_file($paragraphs, $rels = '')
{
	$w = office_docx_save_mix_test_w();
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
			. '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
		'_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . 'officeDocument" Target="word/document.xml"/></Relationships>',
		'word/_rels/document.xml.rels' => ($rels !== '') ? $rels : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . 'styles" Target="styles.xml"/></Relationships>',
		'word/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:styles xmlns:w="' . $w . '">'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style></w:styles>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<w:document xmlns:w="' . $w . '"><w:body>'
			. implode('', $paragraphs) . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:gutter="0"/></w:sectPr></w:body></w:document>',
	);

	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_save_mix_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	return $path;
}

function office_docx_save_mix_test_part($path, $name)
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName($name);
	$zip->close();

	return $xml;
}

function office_docx_save_mix_test_done($path)
{
	@unlink(sys_get_temp_dir() . '/office_edit_' . sha1($path) . '.lock');
	@unlink($path);
}

// The main part's relationships as id => [type, target].
function office_docx_save_mix_test_rels($path)
{
	$dom = new DOMDocument();
	$dom->loadXML(office_docx_save_mix_test_part($path, 'word/_rels/document.xml.rels'));
	$out = array();

	foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
		pg_assert_false(isset($out[$relationship->getAttribute('Id')]), 'relationship id ' . $relationship->getAttribute('Id') . ' used once');
		$out[$relationship->getAttribute('Id')] = array($relationship->getAttribute('Type'), $relationship->getAttribute('Target'));
	}

	return $out;
}

// A grey 2 by 2 PNG.
function office_docx_save_mix_test_png()
{
	$chunk = function ($type, $data) {
		return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
	};

	return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 2, 2, 8, 0, 0, 0, 0))
		. $chunk('IDAT', gzcompress(str_repeat("\x00\x80\x80", 2), 9)) . $chunk('IEND', '');
}

function test_office_docx_save_mix_list_and_link()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$path = office_docx_save_mix_test_file(array('<w:p><w:r><w:t>Elma</w:t></w:r></w:p>', '<w:p><w:r><w:t>Bağlantı</w:t></w:r></w:p>'));
	$read = pg_office_read($path, 'yeni.docx', true);
	$saved = pg_office_docx_save($path, array(
		array('p' => 0, 'kind' => 'bullet'),
		array('p' => 1, 'format' => array(array('start' => 0, 'end' => 8, 'set' => array('link' => 'https://example.com/')))),
	), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$targets = array();

	foreach (office_docx_save_mix_test_rels($path) as $id => $relationship) {
		$targets[$relationship[1]] = $relationship[0];
	}

	pg_assert_same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering', $targets['numbering.xml'] ?? '', 'the numbering the list added is named in the relationships');
	pg_assert_same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink', $targets['https://example.com/'] ?? '', 'beside the link');
	pg_assert_contains('<Override PartName="/word/numbering.xml"', office_docx_save_mix_test_part($path, '[Content_Types].xml'), 'the numbering in the content types');

	$back = pg_office_read($path, 'yeni.docx', true);
	$kinds = json_decode(json_encode($back['edit']['kinds']), true);
	pg_assert_same('bullet', $kinds['0'] ?? '', 'the list read back');

	office_docx_save_mix_test_done($path);
}

function test_office_docx_save_mix_list_and_picture()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$path = office_docx_save_mix_test_file(array('<w:p><w:r><w:t>Elma</w:t></w:r></w:p>', '<w:p><w:r><w:t>Resim</w:t></w:r></w:p>'));
	$read = pg_office_read($path, 'yeni.docx', true);
	$saved = pg_office_docx_save($path, array(
		array('p' => 0, 'kind' => 'number'),
		array('after' => 1, 'picture' => array('data' => base64_encode(office_docx_save_mix_test_png()), 'name' => 'Gri')),
	), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$targets = array();

	foreach (office_docx_save_mix_test_rels($path) as $id => $relationship) {
		$targets[$relationship[1]] = $relationship[0];
	}

	pg_assert_true(isset($targets['numbering.xml']), 'the numbering named in the relationships');
	pg_assert_true(isset($targets['media/image1.png']), 'beside the picture');

	$types = office_docx_save_mix_test_part($path, '[Content_Types].xml');
	pg_assert_contains('<Override PartName="/word/numbering.xml"', $types, 'the numbering in the content types');
	pg_assert_contains('Extension="png"', $types, 'beside the picture');

	office_docx_save_mix_test_done($path);
}

function test_office_docx_save_mix_relationship_type()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	// Word 2010 lists its own extension parts among the standard ones.
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
	$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId2" Type="http://schemas.microsoft.com/office/2007/relationships/stylesWithEffects" Target="stylesWithEffects.xml"/>'
		. '<Relationship Id="rId1" Type="' . $rel . 'styles" Target="styles.xml"/></Relationships>';
	$path = office_docx_save_mix_test_file(array('<w:p><w:r><w:t>Elma</w:t></w:r></w:p>', '<w:p><w:r><w:t>Son</w:t></w:r></w:p>'), $rels);
	$read = pg_office_read($path, 'eski.docx', true);
	$saved = pg_office_docx_save($path, array(array('p' => 0, 'kind' => 'bullet')), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);

	$type = '';

	foreach (office_docx_save_mix_test_rels($path) as $relationship) {
		$type = ($relationship[1] === 'numbering.xml') ? $relationship[0] : $type;
	}

	pg_assert_same($rel . 'numbering', $type, 'the numbering named with the standard relationship type');

	office_docx_save_mix_test_done($path);
}
