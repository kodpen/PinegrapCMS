<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a text colour, a highlight and a font size on stretches of a
 * Word paragraph and a paragraph's alignment
 * (includes/fn/office_docx_format.php): the request, the look the editor
 * starts from, the run properties written and taken off, runs split and
 * joined as for bold, a save read back with an untouched paragraph byte
 * for byte.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_docx_look_test_w()
{
	return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
}

// One paragraph parsed inside a document element; $props is its pPr.
function office_docx_look_test_paragraph($inner, $props = '')
{
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadXML('<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="' . office_docx_look_test_w() . '"><w:body><w:p>'
		. $props . $inner . '</w:p></w:body></w:document>', LIBXML_NONET);

	return $document->getElementsByTagNameNS(office_docx_look_test_w(), 'p')->item(0);
}

// A paragraph as text, without the borrowed namespace declaration.
function office_docx_look_test_xml($paragraph)
{
	return str_replace(' xmlns:w="' . office_docx_look_test_w() . '"', '', $paragraph->ownerDocument->saveXML($paragraph));
}

// Formats a paragraph and gives it back as text, or the refusal's code.
function office_docx_look_test_apply($inner, $format, $props = '')
{
	$paragraph = office_docx_look_test_paragraph($inner, $props);
	$request = pg_office_docx_format_request($format);
	pg_assert_true($request !== null, 'the request is one');
	$code = pg_office_docx_paragraph_format($paragraph, $request);

	return ($code !== '') ? $code : office_docx_look_test_xml($paragraph);
}

// Aligns a paragraph and gives it back as text.
function office_docx_look_test_align($inner, $align, $props = '')
{
	$paragraph = office_docx_look_test_paragraph($inner, $props);
	pg_office_docx_paragraph_align_set($paragraph, $align);

	return office_docx_look_test_xml($paragraph);
}

function test_office_docx_look_request()
{
	$one = function ($set) {
		return pg_office_docx_format_request(array(array('start' => 0, 'end' => 2, 'set' => $set)));
	};

	pg_assert_same(array(array(0, 2, array('color' => 'C00000'))), $one(array('color' => 'c00000')), 'a colour, written in capitals');
	pg_assert_same(array(array(0, 2, array('color' => false, 'highlight' => false, 'size' => false))), $one(array('color' => false, 'highlight' => false, 'size' => false)), 'each taken off');
	pg_assert_same(array(array(0, 2, array('highlight' => 'darkYellow'))), $one(array('highlight' => 'darkyellow')), 'a highlight by Word\'s own name');
	pg_assert_same(array(array(0, 2, array('size' => 12))), $one(array('size' => 12)), 'a size in points');
	pg_assert_same(array(array(0, 2, array('size' => 10.5))), $one(array('size' => 10.5)), 'a half point');
	pg_assert_same(array(array(0, 2, array('size' => 14))), $one(array('size' => '14')), 'a size as text');
	pg_assert_same(null, $one(array('color' => '#C00000')), 'a colour with a hash');
	pg_assert_same(null, $one(array('color' => 'red')), 'a colour by name');
	pg_assert_same(null, $one(array('color' => true)), 'a colour that is only true');
	pg_assert_same(null, $one(array('highlight' => 'orange')), 'a highlight Word does not name');
	pg_assert_same(null, $one(array('highlight' => 'none')), 'none is taken off with false');
	pg_assert_same(null, $one(array('size' => 7)), 'a size under 8');
	pg_assert_same(null, $one(array('size' => 73)), 'a size over 72');
	pg_assert_same(null, $one(array('size' => 10.25)), 'a size between half points');
	pg_assert_same(null, $one(array('size' => true)), 'a size that is only true');

	pg_assert_same('center', pg_office_docx_align_request('center'), 'an alignment');
	pg_assert_same('justify', pg_office_docx_align_request('justify'), 'justified');
	pg_assert_same(null, pg_office_docx_align_request('left'), 'left is start');
	pg_assert_same(null, pg_office_docx_align_request(array('center')), 'not a word');
}

function test_office_docx_look_marks()
{
	$paragraph = office_docx_look_test_paragraph('<w:r><w:t xml:space="preserve">Düz </w:t></w:r>'
		. '<w:r><w:rPr><w:b/><w:color w:val="c00000"/><w:sz w:val="28"/><w:highlight w:val="yellow"/></w:rPr><w:t>renkli</w:t></w:r>'
		. '<w:r><w:rPr><w:b/><w:color w:val="C00000"/><w:sz w:val="28"/><w:highlight w:val="yellow"/></w:rPr><w:t xml:space="preserve"> yine</w:t></w:r>'
		. '<w:r><w:rPr><w:color w:val="auto"/><w:sz w:val="21"/><w:highlight w:val="none"/></w:rPr><w:t>yarım</w:t></w:r>'
		. '<w:r><w:rPr><w:color w:val="FF0000" w:themeColor="accent2"/><w:highlight w:val="orange"/></w:rPr><w:t>son</w:t></w:r>');

	pg_assert_same(array(
		array(4, 0),
		array(11, 1, array('c' => 'C00000', 'h' => 'yellow', 's' => 14)),
		array(5, 0, array('s' => 10.5)),
		array(3, 0, array('c' => 'FF0000')),
	), pg_office_docx_paragraph_marks(pg_office_docx_paragraph_edit($paragraph)),
		'a look beside the bits only when the run has one; auto, none and names Word does not have are not counted; equal looks together');
}

function test_office_docx_look_writes()
{
	pg_assert_same('<w:p><w:r><w:t xml:space="preserve">Bir </w:t></w:r><w:r><w:rPr><w:color w:val="C00000"/></w:rPr><w:t>iki</w:t></w:r><w:r><w:t xml:space="preserve"> üç</w:t></w:r></w:p>',
		office_docx_look_test_apply('<w:r><w:t>Bir iki üç</w:t></w:r>', array(array('start' => 4, 'end' => 7, 'set' => array('color' => 'C00000')))),
		'a colour on a word inside a run: split in three');

	pg_assert_same('<w:p><w:r><w:rPr><w:rFonts w:ascii="Arial"/><w:b/><w:color w:val="00B050"/><w:sz w:val="48"/><w:szCs w:val="48"/><w:highlight w:val="cyan"/><w:u w:val="single"/><w:lang w:val="tr-TR"/></w:rPr><w:t>Hepsi</w:t></w:r></w:p>',
		office_docx_look_test_apply('<w:r><w:rPr><w:rFonts w:ascii="Arial"/><w:b/><w:color w:val="FF0000" w:themeColor="accent2" w:themeShade="BF"/><w:sz w:val="20"/><w:u w:val="single"/><w:lang w:val="tr-TR"/></w:rPr><w:t>Hepsi</w:t></w:r>',
			array(array('start' => 0, 'end' => 5, 'set' => array('color' => '00B050', 'highlight' => 'cyan', 'size' => 24)))),
		'colour replaced with its theme left off, size and its complex-script twin, highlight, each in its place, the rest kept');

	pg_assert_same('<w:p><w:r><w:t>Sade</w:t></w:r></w:p>',
		office_docx_look_test_apply('<w:r><w:rPr><w:color w:val="C00000"/><w:sz w:val="28"/><w:szCs w:val="28"/><w:highlight w:val="yellow"/></w:rPr><w:t>Sade</w:t></w:r>',
			array(array('start' => 0, 'end' => 4, 'set' => array('color' => false, 'highlight' => false, 'size' => false)))),
		'taken off where no style gives them: the properties go');

	pg_assert_same('<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:rPr><w:color w:val="auto"/><w:highlight w:val="none"/></w:rPr><w:t>Başlık</w:t></w:r></w:p>',
		office_docx_look_test_apply('<w:r><w:rPr><w:color w:val="C00000"/><w:sz w:val="28"/></w:rPr><w:t>Başlık</w:t></w:r>',
			array(array('start' => 0, 'end' => 6, 'set' => array('color' => false, 'highlight' => false, 'size' => false))), '<w:pPr><w:pStyle w:val="Heading1"/></w:pPr>'),
		'in a styled paragraph colour and highlight are written off; the size goes, the style\'s is the one meant');

	pg_assert_same('<w:p><w:r><w:rPr><w:sz w:val="21"/><w:szCs w:val="21"/></w:rPr><w:t>Bir iki</w:t></w:r></w:p>',
		office_docx_look_test_apply('<w:r><w:rPr><w:sz w:val="21"/><w:szCs w:val="21"/></w:rPr><w:t xml:space="preserve">Bir </w:t></w:r><w:r><w:t>iki</w:t></w:r>',
			array(array('start' => 4, 'end' => 7, 'set' => array('size' => 10.5)))),
		'made the size of its neighbour, the two runs are one again');

	pg_assert_same('<w:p><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>Sarı</w:t></w:r></w:p>',
		office_docx_look_test_apply('<w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>Sarı</w:t></w:r>', array(array('start' => 0, 'end' => 4, 'set' => array('highlight' => 'yellow')))),
		'the same highlight again: nothing changes');
}

function test_office_docx_look_align()
{
	pg_assert_same('<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Orta</w:t></w:r></w:p>',
		office_docx_look_test_align('<w:r><w:t>Orta</w:t></w:r>', 'center'), 'properties made for the alignment');

	pg_assert_same('<w:p><w:pPr><w:pStyle w:val="Body"/><w:spacing w:after="120"/><w:jc w:val="right"/><w:rPr><w:b/></w:rPr></w:pPr><w:r><w:t>Son</w:t></w:r></w:p>',
		office_docx_look_test_align('<w:r><w:t>Son</w:t></w:r>', 'end', '<w:pPr><w:pStyle w:val="Body"/><w:spacing w:after="120"/><w:rPr><w:b/></w:rPr></w:pPr>'),
		'end written as right, in its place among the other properties');

	pg_assert_same('<w:p><w:pPr><w:jc w:val="both"/></w:pPr><w:r><w:t>İki yana</w:t></w:r></w:p>',
		office_docx_look_test_align('<w:r><w:t>İki yana</w:t></w:r>', 'justify', '<w:pPr><w:jc w:val="center"/></w:pPr>'), 'justified replaces centred');

	// A paragraph without a style takes the default paragraph style
	// (Normal), which may centre or justify it: start is written.
	pg_assert_same('<w:p><w:pPr><w:jc w:val="left"/></w:pPr><w:r><w:t>Baş</w:t></w:r></w:p>',
		office_docx_look_test_align('<w:r><w:t>Baş</w:t></w:r>', 'start', '<w:pPr><w:jc w:val="center"/></w:pPr>'), 'start replaces centred, as the default style may align it otherwise');

	pg_assert_same('<w:p><w:r><w:t>Düz</w:t></w:r></w:p>',
		office_docx_look_test_align('<w:r><w:t>Düz</w:t></w:r>', 'start'), 'start on a paragraph with no alignment and no style writes nothing');

	pg_assert_same('<w:p><w:pPr><w:pStyle w:val="Title"/><w:jc w:val="left"/></w:pPr><w:r><w:t>Başlık</w:t></w:r></w:p>',
		office_docx_look_test_align('<w:r><w:t>Başlık</w:t></w:r>', 'start', '<w:pPr><w:pStyle w:val="Title"/></w:pPr>'), 'start in a styled paragraph is written, as the style may centre it');

	$kept = '<w:p><w:pPr><w:jc w:val="end"/></w:pPr><w:r><w:t>Aynı</w:t></w:r></w:p>';
	pg_assert_same($kept, office_docx_look_test_align('<w:r><w:t>Aynı</w:t></w:r>', 'end', '<w:pPr><w:jc w:val="end"/></w:pPr>'), 'end written the newer way is left as it is');

	pg_assert_same('start', pg_office_docx_paragraph_align(office_docx_look_test_paragraph('<w:r><w:t>x</w:t></w:r>')), 'no alignment is start');
	pg_assert_same('end', pg_office_docx_paragraph_align(office_docx_look_test_paragraph('', '<w:pPr><w:jc w:val="right"/></w:pPr>')), 'right is end');
	pg_assert_same('justify', pg_office_docx_paragraph_align(office_docx_look_test_paragraph('', '<w:pPr><w:jc w:val="both"/></w:pPr>')), 'both is justified');
	pg_assert_same('start', pg_office_docx_paragraph_align(office_docx_look_test_paragraph('', '<w:pPr><w:jc w:val="distribute"/></w:pPr>')), 'one the bar does not offer is start');
}

function test_office_docx_look_save_and_read_back()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the save is not run');
		return;
	}

	$w = office_docx_look_test_w();
	$rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	$parts = array(
		'[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'word/_rels/document.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId9" Type="' . $rel . '/settings" Target="settings.xml"/></Relationships>',
		'word/settings.xml' => '<?xml version="1.0"?><w:settings xmlns:w="' . $w . '"/>',
		'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="' . $w . '"/>',
		'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . $w . '"><w:body>'
			. '<w:p><w:r><w:t>Sayın Ayşe Hanım</w:t></w:r></w:p>'
			. '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:color w:val="0070C0"/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:p>'
			. '<w:p><w:r><w:t>Üçüncü</w:t></w:r></w:p>'
			. '<w:sectPr/></w:body></w:document>',
	);
	$path = tempnam(sys_get_temp_dir(), 'pg_office_docx_look_test_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $xml) {
		$zip->addFromString($name, $xml);
	}

	$zip->close();

	$directory = sys_get_temp_dir();
	$read = pg_office_read($path, 'mektup.docx', true);

	pg_assert_true($read['ok'], 'read for editing: ' . $read['error']);
	pg_assert_same(array(array(12, 0, array('c' => '0070C0'))), json_decode(json_encode($read['edit']['marks']), true)['1'], 'the colour the editor starts from');
	pg_assert_same(array('1' => 'center'), json_decode(json_encode($read['edit']['aligns']), true), 'only the paragraphs not aligned at the start');

	$refused = pg_office_docx_save($path, array(array('p' => 0, 'align' => 'left')), $read['version'], $directory);
	pg_assert_same('none', $refused['code'], 'an alignment the bar does not offer');

	$saved = pg_office_docx_save($path, array(
		array('p' => 0, 'format' => array(array('start' => 6, 'end' => 10, 'set' => array('color' => 'C00000', 'highlight' => 'yellow', 'size' => 16)))),
		array('p' => 2, 'align' => 'justify'),
		array('after' => 2, 'text' => 'Yeni', 'align' => 'end'),
	), $read['version'], $directory);

	pg_assert_true($saved['ok'], 'saved: ' . $saved['error']);
	pg_assert_same(3, $saved['changed'], 'two paragraphs changed, one added');

	$back = pg_office_read($path, 'mektup.docx', true);
	$marks = json_decode(json_encode($back['edit']['marks']), true);
	pg_assert_same(array(array(6, 0), array(4, 0, array('c' => 'C00000', 'h' => 'yellow', 's' => 16)), array(6, 0)), $marks['0'], 'the word read back with its look');
	pg_assert_same(array('1' => 'center', '2' => 'justify', '3' => 'end'), json_decode(json_encode($back['edit']['aligns']), true), 'the alignments read back');

	$zip = new ZipArchive();
	$zip->open($path);
	$xml = (string) $zip->getFromName('word/document.xml');
	$zip->close();

	pg_assert_contains('<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:color w:val="0070C0"/></w:rPr><w:t>Dokunulmayan</w:t></w:r></w:p>', $xml, 'an untouched paragraph byte for byte');
	pg_assert_contains('<w:rPr><w:color w:val="C00000"/><w:sz w:val="32"/><w:szCs w:val="32"/><w:highlight w:val="yellow"/></w:rPr><w:t>Ayşe</w:t>', $xml, 'the word\'s properties');
	pg_assert_contains('<w:sectPr/>', $xml, 'the section properties untouched');

	@unlink($directory . '/office_edit_' . sha1($path) . '.lock');
	unlink($path);
}
