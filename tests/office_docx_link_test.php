<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a link put on a stretch of a Word paragraph, changed or taken
 * off (includes/fn/office_docx_link.php): the addresses taken, the link the
 * editor's marks give a run, the hyperlink and its relationship written,
 * a link cut in two, a save read back with an untouched paragraph byte for
 * byte; and the editor's address and link functions in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_docx_link_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

function office_docx_link_test_r()
{
	return 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
}

// One paragraph parsed inside a document element.
function office_docx_link_test_paragraph($inner)
{
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadXML('<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="' . office_docx_link_test_w() . '" xmlns:r="' . office_docx_link_test_r() . '"><w:body><w:p>'
		. $inner . '</w:p></w:body></w:document>', LIBXML_NONET);

	return $document->getElementsByTagNameNS(office_docx_link_test_w(), 'p')->item(0);
}

// A paragraph as text, without the borrowed namespace declarations.
function office_docx_link_test_xml($paragraph)
{
	return str_replace(array(' xmlns:w="' . office_docx_link_test_w() . '"', ' xmlns:r="' . office_docx_link_test_r() . '"'), '',
		$paragraph->ownerDocument->saveXML($paragraph));
}

// Links a stretch of a paragraph (false takes it off) and gives back the
// paragraph as text and the relationships added.
function office_docx_link_test_apply($inner, $ranges, $rels = null)
{
	$paragraph = office_docx_link_test_paragraph($inner);
	$format = array();

	foreach ($ranges as $range) {
		$format[] = array('start' => $range[0], 'end' => $range[1], 'set' => array('link' => $range[2]));
	}

	$request = pg_office_docx_format_request($format);
	pg_assert_true($request !== null, 'the request is one');
	$links = ($rels === null) ? null : pg_office_docx_link_context($rels);
	$code = pg_office_docx_paragraph_format($paragraph, $request, $links);
	pg_assert_same('', $code, 'written');

	return array(office_docx_link_test_xml($paragraph), is_array($links) ? $links['added'] : array());
}

function test_office_docx_link_values()
{
	foreach (array('https://example.com', 'http://example.com:8080/a?b=c#d', 'HTTPS://Example.com/', 'https://user@example.com/x', 'mailto:ayse@example.com',
		'mailto:ayse@example.com?subject=Merhaba', 'tel:+905551112233', 'tel:+1-800-555-0100', '#Bolum_2', '#Başlık') as $good) {
		pg_assert_same($good, pg_office_docx_link_value($good), 'taken: ' . $good);
	}

	foreach (array('', 'example.com', 'javascript:alert(1)', 'data:text/html,x', 'file:///etc/passwd', 'ftp://example.com', 'https://', 'https:///path',
		'https://exa mple.com', "https://example.com/\n", 'mailto:', 'mailto:ayse', 'tel:', 'tel:+90 555', '#', '#1bolum', '#a-b', 'https://' . str_repeat('a', 2050)) as $bad) {
		pg_assert_same(null, pg_office_docx_link_value($bad), 'refused: ' . json_encode($bad));
	}

	pg_assert_same(null, pg_office_docx_link_value(array('https://example.com')), 'not a text');
	pg_assert_same(null, pg_office_docx_link_value("https://example.com/\xC2\xA0x"), 'a no-break space');
	pg_assert_same(null, pg_office_docx_format_request(array(array('start' => 0, 'end' => 2, 'set' => array('link' => 'javascript:x')))), 'a save asking for one refused');
	pg_assert_same(null, pg_office_docx_format_request(array(array('start' => 0, 'end' => 2, 'set' => array('link' => true)))), 'a link that is only true');
	pg_assert_same(array(array(0, 2, array('link' => false))), pg_office_docx_format_request(array(array('start' => 0, 'end' => 2, 'set' => array('link' => false)))), 'taken off');
}

function test_office_docx_link_marks()
{
	$rels = array(
		'rId5' => array('target' => 'https://example.com/', 'type' => 'hyperlink', 'external' => true),
		'rId6' => array('target' => 'file:///C:/x.doc', 'type' => 'hyperlink', 'external' => true),
	);
	$paragraph = office_docx_link_test_paragraph('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r>'
		. '<w:hyperlink r:id="rId5"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>site</w:t></w:r></w:hyperlink>'
		. '<w:r><w:t xml:space="preserve"> ve </w:t></w:r>'
		. '<w:hyperlink w:anchor="Ek_1"><w:r><w:t>ek</w:t></w:r></w:hyperlink>'
		. '<w:hyperlink r:id="rId6"><w:r><w:t>dosya</w:t></w:r></w:hyperlink>');

	pg_assert_same(array(
		array(5, 0),
		array(4, 0, array('l' => 'https://example.com/')),
		array(4, 0),
		array(2, 0, array('l' => '#Ek_1')),
		array(5, 0),
	), pg_office_docx_paragraph_marks(pg_office_docx_paragraph_edit($paragraph), $rels),
		'a run in a link carries its address; a bookmark\'s link its name; a file link none');

	pg_assert_same(array(array(20, 0)), pg_office_docx_paragraph_marks(pg_office_docx_paragraph_edit($paragraph)),
		'without relationships no link is read: the marks are as before');
}

function test_office_docx_link_writes()
{
	$rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId1" Type="' . office_docx_link_test_r() . '/styles" Target="styles.xml"/>'
		. '<Relationship Id="rId2" Type="' . office_docx_link_test_r() . '/settings" Target="settings.xml"/></Relationships>';

	list($xml, $added) = office_docx_link_test_apply('<w:r><w:t>Bir iki üç</w:t></w:r>', array(array(4, 7, 'https://example.com/')), $rels);
	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bir </w:t></w:r><w:hyperlink r:id="rId3" w:history="1"><w:r><w:t>iki</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> üç</w:t></w:r></w:p>',
		$xml, 'a word inside a run put in a link of its own, its run split out');
	pg_assert_same(array('rId3' => 'https://example.com/'), $added, 'a relationship with the next free id');

	list($xml, $added) = office_docx_link_test_apply('<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">Kalın </w:t></w:r><w:bookmarkStart w:id="0" w:name="x"/><w:r><w:t>düz</w:t></w:r>',
		array(array(0, 9, 'mailto:ayse@example.com'), array(5, 9, false)), $rels);
	pg_assert_same('<w:p><w:hyperlink r:id="rId3" w:history="1"><w:r><w:rPr><w:b/></w:rPr><w:t>Kalın</w:t></w:r></w:hyperlink><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve"> </w:t></w:r><w:bookmarkStart w:id="0" w:name="x"/><w:r><w:t>düz</w:t></w:r></w:p>',
		$xml, 'runs and a bookmark between them go into the link; taken off its end, the end and the bookmark leave it');
	pg_assert_same(array('rId3' => 'mailto:ayse@example.com'), $added, 'one address, one relationship');

	list($xml) = office_docx_link_test_apply('<w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId5" w:history="1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>uzun bağlantı</w:t></w:r></w:hyperlink>',
		array(array(10, 18, false)));
	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bkz. </w:t></w:r><w:hyperlink r:id="rId5" w:history="1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t xml:space="preserve">uzun </w:t></w:r></w:hyperlink><w:r><w:t>bağlantı</w:t></w:r></w:p>',
		$xml, 'taken off the end of a link: the start stays a link, the rest leaves it and its link style');

	list($xml) = office_docx_link_test_apply('<w:hyperlink r:id="rId5"><w:r><w:t>baş orta son</w:t></w:r></w:hyperlink>', array(array(4, 8, false)));
	pg_assert_same('<w:p><w:hyperlink r:id="rId5"><w:r><w:t xml:space="preserve">baş </w:t></w:r></w:hyperlink><w:r><w:t>orta</w:t></w:r><w:hyperlink r:id="rId5"><w:r><w:t xml:space="preserve"> son</w:t></w:r></w:hyperlink></w:p>',
		$xml, 'taken off the middle: the link is cut in two, both to the same address');

	list($xml, $added) = office_docx_link_test_apply('<w:hyperlink r:id="rId5"><w:r><w:t>eski</w:t></w:r></w:hyperlink>', array(array(0, 4, 'tel:+905551112233')), $rels);
	pg_assert_same('<w:p><w:hyperlink r:id="rId3" w:history="1"><w:r><w:t>eski</w:t></w:r></w:hyperlink></w:p>', $xml, 'a link changed: a new one in its place');
	pg_assert_same(array('rId3' => 'tel:+905551112233'), $added, 'the new address\'s relationship');

	list($xml, $added) = office_docx_link_test_apply('<w:r><w:t>Ek</w:t></w:r>', array(array(0, 2, '#Ek_1')), $rels);
	pg_assert_same('<w:p><w:hyperlink w:anchor="Ek_1"><w:r><w:t>Ek</w:t></w:r></w:hyperlink></w:p>', $xml, 'a bookmark\'s link has its anchor and no relationship');
	pg_assert_same(array(), $added, 'no relationship added');

	list($xml) = office_docx_link_test_apply('<w:r><w:t>Hiç</w:t></w:r>', array(array(0, 3, false)));
	pg_assert_same('<w:p><w:r><w:t>Hiç</w:t></w:r></w:p>', $xml, 'taken off text in no link: nothing changes');

	$paragraph = office_docx_link_test_paragraph('<w:r><w:t>kısa</w:t></w:r>');
	$links = null;
	pg_assert_same('format_range', pg_office_docx_paragraph_format($paragraph, pg_office_docx_format_request(array(array('start' => 2, 'end' => 9, 'set' => array('link' => 'https://example.com')))), $links),
		'a stretch past the text');
}

function test_office_docx_link_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$w = office_docx_link_test_w();
	$r = office_docx_link_test_r();
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId9" Type="' . $r . '/settings" Target="settings.xml"/>'
			. '<Relationship Id="rId4" Type="' . $r . '/hyperlink" Target="https://eski.example.com/" TargetMode="External"/></Relationships>',
		'word/settings.xml' => '<?xml version="1.0"?><w:settings xmlns:w="' . $w . '"/>',
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"/>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . $w . '" xmlns:r="' . $r . '"><w:body>'
			. '<w:p><w:r><w:t>Sitemiz burada</w:t></w:r></w:p>'
			. '<w:p><w:hyperlink r:id="rId4" w:history="1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:hyperlink></w:p>'
			. '<w:p><w:r><w:t xml:space="preserve">Eski </w:t></w:r><w:hyperlink r:id="rId4"><w:r><w:t>bağlantı</w:t></w:r></w:hyperlink></w:p>'
			. '<w:sectPr/></w:body></w:document>',
	);
	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_link_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	$directory = sys_get_temp_dir();
	$read = pg_office_read($path, 'mektup.docx', true);

	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);
	pg_assert_same(array(array(12, 0, array('l' => 'https://eski.example.com/'))), json_decode(json_encode($read['edit']['marks']), true)['1'], 'the link the editor starts from');
	pg_assert_contains('href="https://eski.example.com/"', $read['html'], 'the link drawn');

	$refused = pg_office_docx_save($path, array(array('p' => 0, 'format' => array(array('start' => 0, 'end' => 6, 'set' => array('link' => 'javascript:alert(1)'))))), $read['version'], $directory);
	pg_assert_same('none', $refused['code'], 'an address no link may have');

	$saved = pg_office_docx_save($path, array(
		array('p' => 0, 'format' => array(array('start' => 0, 'end' => 7, 'set' => array('link' => 'https://yeni.example.com/')))),
		array('p' => 2, 'format' => array(array('start' => 5, 'end' => 13, 'set' => array('link' => false)))),
		array('after' => 2, 'text' => 'Ara', 'format' => array(array('start' => 0, 'end' => 3, 'set' => array('link' => 'tel:+905551112233')))),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(3, $saved['changed'], 'two paragraphs changed, one added');

	$back = pg_office_read($path, 'mektup.docx', true);
	$marks = json_decode(json_encode($back['edit']['marks']), true);
	pg_assert_same(array(array(7, 0, array('l' => 'https://yeni.example.com/')), array(7, 0)), $marks['0'], 'the new link read back');
	pg_assert_same(array(array(13, 0)), $marks['2'], 'the link taken off');
	pg_assert_same(array(array(3, 0, array('l' => 'tel:+905551112233'))), $marks['3'], 'the added paragraph\'s link');

	$zip = new ZipArchive();
	$zip->open($path);
	$xml = (string) $zip->getFromName('word/document.xml');
	$rels = (string) $zip->getFromName('word/_rels/document.xml.rels');
	$zip->close();

	pg_assert_contains('<w:p><w:hyperlink r:id="rId4" w:history="1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:hyperlink></w:p>', $xml, 'an untouched paragraph byte for byte');
	pg_assert_contains('<Relationship Id="rId4" Type="' . $r . '/hyperlink" Target="https://eski.example.com/" TargetMode="External"/>', $rels, 'the old relationship kept');
	pg_assert_contains('Target="https://yeni.example.com/" TargetMode="External"', $rels, 'the new address\'s relationship');
	pg_assert_contains('Target="tel:+905551112233" TargetMode="External"', $rels, 'the added paragraph\'s relationship');
	pg_assert_contains('<w:sectPr/>', $xml, 'the section properties untouched');

	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}
