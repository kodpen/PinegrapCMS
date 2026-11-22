<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for bold, italic and underline on stretches of a Word paragraph
 * (includes/fn/office_docx_format.php): the request, the look the editor
 * starts from, runs split where a stretch starts or ends inside them and
 * joined where they end up looking the same, the other properties kept in
 * their order, a save with text and format read back, and its refusals.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_docx_format_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

// One paragraph parsed inside a document element; $props is its pPr.
function office_docx_format_test_paragraph($inner, $props = '')
{
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadXML('<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="' . office_docx_format_test_w() . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p>'
		. $props . $inner . '</w:p></w:body></w:document>', LIBXML_NONET);

	return $document->getElementsByTagNameNS(office_docx_format_test_w(), 'p')->item(0);
}

// Formats a paragraph and gives it back as text, without the borrowed
// namespace declaration, or the refusal's code.
function office_docx_format_test_apply($inner, $format, $props = '')
{
	$paragraph = office_docx_format_test_paragraph($inner, $props);
	$code = pg_office_docx_paragraph_format($paragraph, pg_office_docx_format_request($format));

	if ($code !== '') {
		return $code;
	}

	return str_replace(' xmlns:w="' . office_docx_format_test_w() . '"', '', $paragraph->ownerDocument->saveXML($paragraph));
}

function test_office_docx_format_request()
{
	pg_assert_same(array(array(2, 5, array('bold' => true))), pg_office_docx_format_request(array(array('start' => 2, 'end' => 5, 'set' => array('bold' => true)))), 'one stretch');
	pg_assert_same(array(array(0, 1, array('italic' => false, 'underline' => true))), pg_office_docx_format_request(array(array('start' => '0', 'end' => '1', 'set' => array('italic' => false, 'underline' => true)))), 'numbers as text, two properties');
	pg_assert_same(null, pg_office_docx_format_request(array()), 'nothing');
	pg_assert_same(null, pg_office_docx_format_request('bold'), 'not a list');
	pg_assert_same(null, pg_office_docx_format_request(array(array('start' => 3, 'end' => 3, 'set' => array('bold' => true)))), 'an empty stretch');
	pg_assert_same(null, pg_office_docx_format_request(array(array('start' => -1, 'end' => 3, 'set' => array('bold' => true)))), 'a negative start');
	pg_assert_same(null, pg_office_docx_format_request(array(array('start' => 0, 'end' => 3, 'set' => array('strike' => true)))), 'a property not offered');
	pg_assert_same(null, pg_office_docx_format_request(array(array('start' => 0, 'end' => 3, 'set' => array('bold' => 1)))), 'not a true or false');
	pg_assert_same(null, pg_office_docx_format_request(array(array('start' => 0, 'end' => 3, 'set' => array()))), 'nothing to set');
	pg_assert_same(null, pg_office_docx_format_request(array_fill(0, PG_OFFICE_DOCX_FORMAT_MAX_RANGES + 1, array('start' => 0, 'end' => 1, 'set' => array('bold' => true)))), 'too many stretches');
}

function test_office_docx_format_marks()
{
	$paragraph = office_docx_format_test_paragraph('<w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/><w:i/></w:rPr><w:t>Ayşe</w:t></w:r>'
		. '<w:r><w:rPr><w:b w:val="0"/><w:u w:val="single"/></w:rPr><w:t>,</w:t></w:r><w:r><w:rPr><w:u w:val="none"/></w:rPr><w:t xml:space="preserve"> merhaba</w:t><w:tab/></w:r><w:r><w:t>.</w:t></w:r>');

	pg_assert_same(array(array(6, 0), array(4, 3), array(1, 4), array(10, 0)), pg_office_docx_paragraph_marks(pg_office_docx_paragraph_edit($paragraph)),
		'stretches by look, a property written off not counted, neighbours of one look together');

	pg_assert_same(array(), pg_office_docx_paragraph_marks(pg_office_docx_paragraph_edit(office_docx_format_test_paragraph(''))), 'an empty paragraph');
}

function test_office_docx_format_splits_and_joins()
{
	$bold = array('bold' => true);

	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>Ayşe</w:t></w:r><w:r><w:t xml:space="preserve"> Hanım</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:t>Sayın Ayşe Hanım</w:t></w:r>', array(array('start' => 6, 'end' => 10, 'set' => $bold))),
		'a word inside one run: the run split in three, the middle bold');

	pg_assert_same('<w:p><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>Sayın Ayşe</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>Ayşe</w:t></w:r>', array(array('start' => 0, 'end' => 6, 'set' => $bold))),
		'made the same as its neighbour, the two runs are one again');

	pg_assert_same('<w:p><w:r><w:t>Sayın Ayşe</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:t xml:space="preserve">Sayın </w:t></w:r><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>Ayşe</w:t></w:r>', array(array('start' => 6, 'end' => 10, 'set' => array('bold' => false)))),
		'bold taken off where no style gives it: the properties go, and the runs join');

	pg_assert_same('<w:p><w:r><w:t>Bir</w:t></w:r><w:r><w:t>İki</w:t></w:r><w:r><w:rPr><w:i/><w:iCs/></w:rPr><w:t>Üç</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:t>Bir</w:t></w:r><w:r><w:t>İki</w:t></w:r><w:r><w:t>Üç</w:t></w:r>', array(array('start' => 6, 'end' => 8, 'set' => array('italic' => true)))),
		'runs the change did not reach are not joined');

	pg_assert_same('<w:p><w:r w:rsidR="00A1"><w:rPr><w:rFonts w:ascii="Arial"/><w:b/><w:bCs/><w:color w:val="FF0000"/><w:sz w:val="28"/><w:u w:val="single"/><w:lang w:val="tr-TR"/></w:rPr><w:t>Kırmızı</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r w:rsidR="00A1"><w:rPr><w:rFonts w:ascii="Arial"/><w:color w:val="FF0000"/><w:sz w:val="28"/><w:lang w:val="tr-TR"/></w:rPr><w:t>Kırmızı</w:t></w:r>',
			array(array('start' => 0, 'end' => 7, 'set' => array('bold' => true, 'underline' => true)))),
		'other properties and attributes kept, the new ones in their order');

	pg_assert_same('<w:p><w:r><w:rPr><w:u w:val="double"/></w:rPr><w:t>Çift</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:rPr><w:u w:val="double"/></w:rPr><w:t>Çift</w:t></w:r>', array(array('start' => 0, 'end' => 4, 'set' => array('underline' => true)))),
		'an underline already on keeps its kind');

	pg_assert_same('<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:rPr><w:b w:val="0"/><w:bCs w:val="0"/></w:rPr><w:t>Başlık</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:t>Başlık</w:t></w:r>', array(array('start' => 0, 'end' => 6, 'set' => array('bold' => false))), '<w:pPr><w:pStyle w:val="Heading1"/></w:pPr>'),
		'taken off in a styled paragraph: written off, so the style does not give it back');

	pg_assert_same('<w:p><w:r><w:t>Kalem</w:t><w:tab/></w:r><w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>5</w:t><w:br/><w:t>Sil</w:t></w:r><w:r><w:t>gi</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:t>Kalem</w:t><w:tab/><w:t>5</w:t><w:br/><w:t>Silgi</w:t></w:r>', array(array('start' => 6, 'end' => 11, 'set' => array('underline' => true)))),
		'a run with a tab and a line break split at the right places');

	pg_assert_same('<w:p><w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>bağ</w:t></w:r><w:r><w:rPr><w:rStyle w:val="Hyperlink"/><w:b/><w:bCs/></w:rPr><w:t>lantı</w:t></w:r></w:hyperlink><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t xml:space="preserve"> burada</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:hyperlink r:id="rId1"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>bağlantı</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> burada</w:t></w:r>',
			array(array('start' => 3, 'end' => 15, 'set' => $bold))),
		'a stretch across a link: the link\'s runs stay in the link');

	pg_assert_same('<w:p><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t>Bir</w:t></w:r><w:r><w:t xml:space="preserve"> iki </w:t></w:r><w:r><w:rPr><w:i/><w:iCs/></w:rPr><w:t>üç</w:t></w:r></w:p>',
		office_docx_format_test_apply('<w:r><w:t>Bir iki üç</w:t></w:r>', array(
			array('start' => 0, 'end' => 3, 'set' => $bold),
			array('start' => 8, 'end' => 10, 'set' => array('italic' => true)),
		)), 'two stretches in one change');

	pg_assert_same('format_range', office_docx_format_test_apply('<w:r><w:t>Kısa</w:t></w:r>', array(array('start' => 2, 'end' => 9, 'set' => $bold))), 'a stretch past the text');
	pg_assert_same('locked', office_docx_format_test_apply('<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:t>x</w:t></w:r>', array(array('start' => 0, 'end' => 1, 'set' => $bold))), 'a paragraph with a field');
}

function test_office_docx_format_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$w = office_docx_format_test_w();
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId9" Type="' . $rel . '/settings" Target="settings.xml"/></Relationships>',
		'word/settings.xml' => '<?xml version="1.0"?><w:settings xmlns:w="' . $w . '"/>',
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"/>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . $w . '"><w:body>'
			. '<w:p><w:r><w:t>Sayın Ayşe Hanım</w:t></w:r></w:p>'
			. '<w:p><w:r><w:rPr><w:i/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:p>'
			. '<w:p><w:r><w:t>Eski metin</w:t></w:r></w:p>'
			. '<w:sectPr/></w:body></w:document>',
	);
	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_format_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	$directory = sys_get_temp_dir();
	$read = pg_office_read($path, 'mektup.docx', true);

	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);
	pg_assert_same(array('0' => array(array(16, 0)), '1' => array(array(12, 2)), '2' => array(array(10, 0))), (array) $read['edit']['marks'], 'the looks the editor starts from');

	$refused = pg_office_docx_save($path, array(array('p' => 0, 'format' => array(array('start' => 6, 'end' => 99, 'set' => array('bold' => true))))), $read['version'], $directory);
	pg_assert_same('format_range', $refused['code'], 'a stretch past the text is refused');

	$refused = pg_office_docx_save($path, array(array('p' => 0, 'format' => 'bold')), $read['version'], $directory);
	pg_assert_same('none', $refused['code'], 'a format that is not one');

	$saved = pg_office_docx_save($path, array(
		array('p' => 0, 'format' => array(array('start' => 6, 'end' => 10, 'set' => array('bold' => true)))),
		array('p' => 2, 'text' => 'Yeni metin', 'format' => array(array('start' => 0, 'end' => 4, 'set' => array('underline' => true)))),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(2, $saved['changed'], 'two paragraphs');

	$back = pg_office_read($path, 'mektup.docx', true);
	pg_assert_contains('<p data-p="0">Sayın <strong>Ayşe</strong> Hanım</p>', $back['html'], 'the text left as it was, the word bold');
	pg_assert_contains('<p data-p="2"><u>Yeni</u> metin</p>', $back['html'], 'the new text, its stretch counted in it');
	pg_assert_same(array(array(6, 0), array(4, 1), array(6, 0)), $back['edit']['marks']->{'0'}, 'read back as three stretches');

	$zip = new ZipArchive();
	$zip->open($path);
	$xml = (string) $zip->getFromName('word/document.xml');
	$zip->close();

	pg_assert_contains('<w:p><w:r><w:rPr><w:i/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:p>', $xml, 'an untouched paragraph byte for byte');
	pg_assert_contains('<w:sectPr/>', $xml, 'the section properties untouched');

	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}

// Runs a script after the view in Node and answers what it printed as JSON;
// null when Node cannot run.
function office_docx_format_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgdf');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar M = window.pgOfficeView.docMarks;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_office_docx_format_editor_marks()
{
	$result = office_docx_format_test_node(<<<'JS'
var out = {};
out.expand = M.expandMarks([[2, 0], [3, 1]], 'abcde');
out.astral = M.expandMarks([[1, 1], [1, 0]], '😀x');
out.short = M.expandMarks([[1, 2]], 'abc');
out.typed = M.carryMarks([0, 0, 1, 1, 0], 'abcde', 'abcXYde');
out.atStart = M.carryMarks([1, 1, 0], 'abc', 'Zabc');
out.removed = M.carryMarks([0, 1, 1, 0], 'abcd', 'ad');
out.ranges = M.formatRanges([0, 0, 1, 1, 0, 0], [0, 1, 1, 0, 0, 4]);
out.none = M.formatRanges([1, 2], [1, 2]);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(0, 0, 1, 1, 1), $result['expand'], 'stretches as one look a character');
	pg_assert_same(array(1, 1, 0), $result['astral'], 'a character outside the basic plane takes two units');
	pg_assert_same(array(2, 0, 0), $result['short'], 'what the stretches leave out is plain');
	pg_assert_same(array(0, 0, 1, 1, 1, 1, 0), $result['typed'], 'typed text takes the look of the character before it');
	pg_assert_same(array(1, 1, 1, 0), $result['atStart'], 'at the start, of the character after it');
	pg_assert_same(array(0, 0), $result['removed'], 'removed characters take their look with them');
	pg_assert_same(array(
		array('start' => 1, 'end' => 2, 'set' => array('bold' => true)),
		array('start' => 3, 'end' => 4, 'set' => array('bold' => false)),
		array('start' => 5, 'end' => 6, 'set' => array('underline' => true)),
	), $result['ranges'], 'only the stretches that differ, each with what it asks for');
	pg_assert_same(array(), $result['none'], 'nothing when the looks are the same');
}
