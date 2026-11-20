<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the .pptx preview (includes/fn/office.php): the kind by the
 * name, a presentation the test writes read as a list of slides (titles,
 * text and pictures in reading order, groups and tables, speaker notes,
 * hidden slides, the order the presentation gives), the picture limits of
 * the document preview, and the files refused with their reason.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The namespaces a PowerPoint part declares.
function office_pptx_test_ns()
{
	return ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
		. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
		. ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"'
		. ' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';
}

// A relationships part: id => [type word, target].
function office_pptx_test_rels($rels)
{
	$xml = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

	foreach ($rels as $id => $rel) {
		$xml .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $rel[0] . '" Target="' . $rel[1] . '"/>';
	}

	return $xml . '</Relationships>';
}

// A shape holding paragraphs, a placeholder of a type when given; each
// paragraph a string, or [text, level].
function office_pptx_test_shape($paragraphs, $placeholder = null)
{
	$ph = ($placeholder === null) ? '' : '<p:nvPr><p:ph' . (($placeholder !== '') ? ' type="' . $placeholder . '"' : '') . '/></p:nvPr>';
	$xml = '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Shape"/><p:cNvSpPr/>' . $ph . '</p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/>';

	foreach ($paragraphs as $paragraph) {
		$paragraph = (array) $paragraph;
		$level = isset($paragraph[1]) ? '<a:pPr lvl="' . (int) $paragraph[1] . '"/>' : '';
		$runs = array();

		foreach (explode("\n", $paragraph[0]) as $piece) {
			$runs[] = '<a:r><a:rPr lang="tr-TR"/><a:t>' . htmlspecialchars($piece, ENT_XML1) . '</a:t></a:r>';
		}

		$xml .= '<a:p>' . $level . implode('<a:br/>', $runs) . '</a:p>';
	}

	return $xml . '</p:txBody></p:sp>';
}

// A picture shape of a relationship.
function office_pptx_test_picture($rel_id, $alt)
{
	return '<p:pic><p:nvPicPr><p:cNvPr id="5" name="Picture" descr="' . $alt . '"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>'
		. '<p:blipFill><a:blip r:embed="' . $rel_id . '"/></p:blipFill><p:spPr/></p:pic>';
}

// A slide of the shapes given.
function office_pptx_test_slide($shapes, $hidden = false)
{
	return '<?xml version="1.0" encoding="UTF-8"?><p:sld' . office_pptx_test_ns() . ($hidden ? ' show="0"' : '') . '><p:cSld><p:spTree>'
		. '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
		. $shapes . '</p:spTree></p:cSld></p:sld>';
}

// A notes page: the slide image placeholder, then the body of the notes.
function office_pptx_test_notes($paragraphs)
{
	return '<?xml version="1.0" encoding="UTF-8"?><p:notes' . office_pptx_test_ns() . '><p:cSld><p:spTree>'
		. '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
		. office_pptx_test_shape(array('not a note'), 'sldImg') . office_pptx_test_shape($paragraphs, 'body')
		. '</p:spTree></p:cSld></p:notes>';
}

// A presentation on disk: the slides given (part name => xml, in the order
// the presentation lists them, which may differ from their names), each
// slide's relationships, and any other parts.
function office_pptx_test_file($slides, $slide_rels = array(), $parts = array())
{
	$list = '';
	$rels = array();
	$number = 256;

	foreach (array_keys($slides) as $index => $name) {
		$id = 'rId' . ($index + 10);
		$list .= '<p:sldId id="' . ($number++) . '" r:id="' . $id . '"/>';
		$rels[$id] = array('slide', substr($name, strlen('ppt/')));
	}

	$all = array(
		'[Content_Types].xml'             => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'_rels/.rels'                     => office_pptx_test_rels(array('rId1' => array('officeDocument', 'ppt/presentation.xml'))),
		'ppt/presentation.xml'            => '<?xml version="1.0" encoding="UTF-8"?><p:presentation' . office_pptx_test_ns() . '><p:sldIdLst>' . $list . '</p:sldIdLst></p:presentation>',
		'ppt/_rels/presentation.xml.rels' => office_pptx_test_rels($rels),
	) + $slides;

	foreach ($slide_rels as $name => $slide_rel) {
		$all[pg_office_pptx_rels_part($name)] = office_pptx_test_rels($slide_rel);
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_office_pptx_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($all + $parts as $name => $bytes) {
		$zip->addFromString($name, $bytes);
	}

	$zip->close();

	return $path;
}

// A one-pixel PNG.
function office_pptx_test_png()
{
	return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

// A presentation is only looked at: the preview knows it, the edits do not.
function test_office_pptx_kind_by_extension()
{
	pg_assert_same('pptx', pg_office_preview_kind('Sunum.PPTX'), 'a .pptx, whatever the case');
	pg_assert_same('pptx', pg_office_preview_kind('show.ppsx'), 'a slide show');
	pg_assert_same('pptx', pg_office_preview_kind('kalip.potx'), 'a template');
	pg_assert_same('', pg_office_preview_kind('eski.ppt'), 'the binary form has no preview');
	pg_assert_same('', pg_office_kind('Sunum.pptx'), 'the kind the edits ask for leaves it out: view only');
	pg_assert_same('ppt/slides/_rels/slide1.xml.rels', pg_office_pptx_rels_part('ppt/slides/slide1.xml'), 'a slide\'s relationships part');

	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("'pptx', 'pptm', 'ppsx', 'potx']", $folders, 'Quick Look and the file window open it');
}

// Slides in the presentation's order with their titles, text in the order
// of the shape tree (groups in place, a table's rows, a line break kept),
// levels, a picture in its place, notes, a hidden slide.
function test_office_pptx_reads_slides()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$first = office_pptx_test_slide(
		office_pptx_test_shape(array('Body first'), '')
		. office_pptx_test_shape(array("\xC3\x87eyrek sonu\xC3\xA7lar\xC4\xB1"), 'title')
		. '<p:grpSp><p:nvGrpSpPr><p:cNvPr id="3" name="Group"/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
		. office_pptx_test_shape(array('In a group', array('Indented', 2)))
		. '</p:grpSp>'
		. office_pptx_test_picture('rId2', 'Chart of sales')
		. office_pptx_test_shape(array("Line one\nLine two", '   '))
		. '<p:graphicFrame><p:nvGraphicFramePr><p:cNvPr id="4" name="Table"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr><p:xfrm/>'
		. '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl>'
		. '<a:tr h="1"><a:tc><a:txBody><a:bodyPr/><a:p><a:r><a:t>Q1</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:bodyPr/><a:p><a:r><a:t>12</a:t></a:r></a:p></a:txBody></a:tc></a:tr>'
		. '</a:tbl></a:graphicData></a:graphic></p:graphicFrame>'
	);
	$second = office_pptx_test_slide(office_pptx_test_shape(array('Opening'), 'ctrTitle') . office_pptx_test_shape(array('Subtitle'), 'subTitle'), true);

	// The presentation lists slide2.xml first.
	$path = office_pptx_test_file(
		array('ppt/slides/slide2.xml' => $second, 'ppt/slides/slide1.xml' => $first),
		array(
			'ppt/slides/slide1.xml' => array('rId2' => array('image', '../media/image1.png'), 'rId3' => array('notesSlide', '../notesSlides/notesSlide1.xml')),
		),
		array(
			'ppt/media/image1.png'              => office_pptx_test_png(),
			'ppt/notesSlides/notesSlide1.xml'   => office_pptx_test_notes(array('Say hello', 'Then the numbers')),
		)
	);

	$office = pg_office_read($path, 'Sunum.pptx', true);
	@unlink($path);

	pg_assert_true($office['ok'], 'read: ' . $office['error']);
	pg_assert_same('pptx', $office['kind'], 'kind');
	pg_assert_false($office['truncated'], 'nothing cut');
	pg_assert_same(0, $office['images_lost'], 'no picture lost');
	pg_assert_false(isset($office['edit']) || isset($office['version']), 'nothing offered for editing, even when asked');
	pg_assert_same(array(1, 2), array_column($office['slides'], 'n'), 'numbered in order');
	pg_assert_same(array('Opening', "\xC3\x87eyrek sonu\xC3\xA7lar\xC4\xB1"), array_column($office['slides'], 'title'), 'the presentation\'s order, a centred title too');
	pg_assert_same(array(true, false), array_column($office['slides'], 'hidden'), 'the hidden slide flagged');
	pg_assert_same(array(array('type' => 'text', 'text' => 'Subtitle', 'level' => 0)), $office['slides'][0]['items'], 'the subtitle as text');
	pg_assert_same(array(), $office['slides'][0]['notes'], 'no notes');

	$items = $office['slides'][1]['items'];

	pg_assert_same(array('text', 'text', 'text', 'picture', 'text', 'row'), array_column($items, 'type'), 'shapes in the tree\'s order, the title taken out, an empty paragraph left');
	pg_assert_same(array('Body first', 'In a group', 'Indented'), array_column(array_slice($items, 0, 3), 'text'), 'text before the title, a group in place');
	pg_assert_same(2, $items[2]['level'], 'a paragraph\'s level');
	pg_assert_same('Chart of sales', $items[3]['alt'], 'the picture\'s description');
	pg_assert_same(0, strpos($items[3]['src'], 'data:image/png;base64,'), 'the picture inlined');
	pg_assert_same("Line one\nLine two", $items[4]['text'], 'a line break kept');
	pg_assert_same("Q1\t12", $items[5]['text'], 'a table row, cells apart by a tab');
	pg_assert_same(array('Say hello', 'Then the numbers'), $office['slides'][1]['notes'], 'the notes body, not the slide image placeholder');
}

// The document preview's picture limits: one past 2 MB, or the ones past
// 8 MB together, or a part a browser cannot show, is lost and counted.
function test_office_pptx_picture_limits()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$path = office_pptx_test_file(
		array('ppt/slides/slide1.xml' => office_pptx_test_slide(office_pptx_test_picture('rId1', 'big') . office_pptx_test_picture('rId2', 'small') . office_pptx_test_picture('rId3', 'vector') . office_pptx_test_picture('rId9', 'none'))),
		array('ppt/slides/slide1.xml' => array('rId1' => array('image', '../media/big.png'), 'rId2' => array('image', '../media/small.png'), 'rId3' => array('image', '../media/shape.emf'))),
		array(
			'ppt/media/big.png'   => office_pptx_test_png() . str_repeat("\0", PG_OFFICE_MAX_IMAGE),
			'ppt/media/small.png' => office_pptx_test_png(),
			'ppt/media/shape.emf' => 'emf',
		)
	);

	$office = pg_office_read($path, 'p.pptx');
	@unlink($path);

	pg_assert_true($office['ok'], 'read');
	pg_assert_same(array('lost', 'picture', 'lost'), array_column($office['slides'][0]['items'], 'type'), 'the large and the unknown kind lost, a missing relationship left out');
	pg_assert_same(2, $office['images_lost'], 'counted for the note');
}

// Past the slide limit the list is cut and says so.
function test_office_pptx_slide_limit()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$slides = array();

	for ($index = 1; $index <= PG_OFFICE_MAX_SLIDES + 1; $index++) {
		$slides['ppt/slides/slide' . $index . '.xml'] = office_pptx_test_slide(office_pptx_test_shape(array('S' . $index), 'title'));
	}

	$path = office_pptx_test_file($slides);
	$office = pg_office_read($path, 'p.pptx');
	@unlink($path);

	pg_assert_true($office['ok'], 'read');
	pg_assert_same(PG_OFFICE_MAX_SLIDES, count($office['slides']), 'the first slides');
	pg_assert_same('S' . PG_OFFICE_MAX_SLIDES, $office['slides'][PG_OFFICE_MAX_SLIDES - 1]['title'], 'in order');
	pg_assert_true($office['truncated'], 'cut');
}

// A file that is no presentation, or whose parts cannot be read whole, is
// refused with its reason.
function test_office_pptx_refused()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_office_pptx_');
	file_put_contents($path, 'not a zip at all');
	$office = pg_office_read($path, 'bozuk.pptx');
	pg_assert_false($office['ok'], 'not a zip');
	pg_assert_same(lang('The file could not be read.'), $office['error'], 'not a zip: could not be read');

	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);
	$zip->addFromString('[Content_Types].xml', '<Types/>');
	$zip->addFromString('word/document.xml', '<w:document/>');
	$zip->close();
	$office = pg_office_read($path, 'yanlis.pptx');
	@unlink($path);
	pg_assert_false($office['ok'], 'a package with no presentation');
	pg_assert_same(lang('The file could not be read.'), $office['error'], 'no presentation: could not be read');

	$path = office_pptx_test_file(array('ppt/slides/slide1.xml' => office_pptx_test_slide('<!--' . str_repeat('x', PG_OFFICE_MAX_DOM) . '-->')));
	$office = pg_office_read($path, 'buyuk.pptx');
	@unlink($path);
	pg_assert_false($office['ok'], 'a slide past the DOM ceiling');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), $office['error'], 'too large');

	$path = office_pptx_test_file(
		array('ppt/slides/slide1.xml' => office_pptx_test_slide(office_pptx_test_shape(array('x'), 'title'))),
		array('ppt/slides/slide1.xml' => array('rId1' => array('notesSlide', '../notesSlides/notesSlide1.xml'))),
		array('ppt/notesSlides/notesSlide1.xml' => '<?xml version="1.0"?><p:notes' . office_pptx_test_ns() . '><!--' . str_repeat('x', PG_OFFICE_MAX_DOM) . '--></p:notes>')
	);
	$office = pg_office_read($path, 'notlar.pptx');
	@unlink($path);
	pg_assert_false($office['ok'], 'a notes page past the DOM ceiling');
	pg_assert_same(lang('This file is too large to show here. Download it to open it.'), $office['error'], 'too large too');
}
