<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design file reader (includes/fn/office_pptx_design.php): a
 * presentation the test writes read as slides of placed elements: the slide
 * size, backgrounds (own, inherited, theme colours, a picture, a gradient),
 * text boxes with their paragraphs and first run's style, pictures with
 * their crops (Canva's filled shape in a scaling group, a plain picture),
 * groups resolved with their turns and flips, lines and shapes, what is left
 * out and counted, the limits and the refusals.
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
function office_pptx_design_test_ns()
{
	return ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
		. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
		. ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
}

// A relationships part: id => [type word, target].
function office_pptx_design_test_rels($rels)
{
	$xml = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

	foreach ($rels as $id => $rel) {
		$xml .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $rel[0] . '" Target="' . $rel[1] . '"/>';
	}

	return $xml . '</Relationships>';
}

// A place: a:xfrm with an offset and a size, a turn and flips when given.
function office_pptx_design_test_xfrm($x, $y, $w, $h, $extra = '')
{
	return '<a:xfrm' . $extra . '><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm>';
}

// A shape: its properties (spPr inside) and an optional text body.
function office_pptx_design_test_sp($sp_pr, $body = '', $name = 'Shape', $nv = '')
{
	return '<p:sp><p:nvSpPr><p:cNvPr id="2" name="' . $name . '"/><p:cNvSpPr/><p:nvPr>' . $nv . '</p:nvPr></p:nvSpPr>'
		. '<p:spPr>' . $sp_pr . '</p:spPr>' . (($body !== '') ? '<p:txBody>' . $body . '</p:txBody>' : '') . '</p:sp>';
}

// A group of shapes with its own place and its children's frame.
function office_pptx_design_test_group($shapes, $x, $y, $w, $h, $cx, $cy, $cw, $ch, $extra = '')
{
	return '<p:grpSp><p:nvGrpSpPr><p:cNvPr id="3" name="Group"/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr>'
		. '<a:xfrm' . $extra . '><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/>'
		. '<a:chOff x="' . $cx . '" y="' . $cy . '"/><a:chExt cx="' . $cw . '" cy="' . $ch . '"/></a:xfrm></p:grpSpPr>'
		. $shapes . '</p:grpSp>';
}

// A slide of the shapes given, with an optional background.
function office_pptx_design_test_slide($shapes, $background = '', $root = 'p:sld', $extra = '')
{
	return '<?xml version="1.0" encoding="UTF-8"?><' . $root . office_pptx_design_test_ns() . $extra . '><p:cSld>' . $background . '<p:spTree>'
		. '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
		. $shapes . '</p:spTree></p:cSld></' . $root . '>';
}

// A theme with dark 1 black, light 1 white, accent 1 blue, and its fonts.
function office_pptx_design_test_theme()
{
	return '<?xml version="1.0" encoding="UTF-8"?><a:theme' . office_pptx_design_test_ns() . ' name="T"><a:themeElements>'
		. '<a:clrScheme name="C"><a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>'
		. '<a:dk2><a:srgbClr val="1F2937"/></a:dk2><a:lt2><a:srgbClr val="F3F4F6"/></a:lt2><a:accent1><a:srgbClr val="2563EB"/></a:accent1></a:clrScheme>'
		. '<a:fontScheme name="F"><a:majorFont><a:latin typeface="Playfair Display"/></a:majorFont><a:minorFont><a:latin typeface="Inter"/></a:minorFont></a:fontScheme>'
		. '</a:themeElements></a:theme>';
}

// A presentation on disk: slides (part name => xml, in the order listed),
// each slide's relationships, other parts, and the slide size when given.
function office_pptx_design_test_file($slides, $slide_rels = array(), $parts = array(), $size = '<p:sldSz cx="18288000" cy="10287000"/>')
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
		'_rels/.rels'                     => office_pptx_design_test_rels(array('rId1' => array('officeDocument', 'ppt/presentation.xml'))),
		'ppt/presentation.xml'            => '<?xml version="1.0" encoding="UTF-8"?><p:presentation' . office_pptx_design_test_ns() . '><p:sldIdLst>' . $list . '</p:sldIdLst>' . $size . '</p:presentation>',
		'ppt/_rels/presentation.xml.rels' => office_pptx_design_test_rels($rels),
	) + $slides;

	foreach ($slide_rels as $name => $slide_rel) {
		$all[pg_office_pptx_rels_part($name)] = office_pptx_design_test_rels($slide_rel);
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_pptx_design_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($all + $parts as $name => $bytes) {
		$zip->addFromString($name, $bytes);
	}

	$zip->close();

	return $path;
}

// Reads a presentation of one slide and returns the read and its slide.
function office_pptx_design_test_one($shapes, $rels = array(), $parts = array(), $background = '', $limits = array())
{
	$path = office_pptx_design_test_file(array('ppt/slides/slide1.xml' => office_pptx_design_test_slide($shapes, $background)), $rels ? array('ppt/slides/slide1.xml' => $rels) : array(), $parts);
	$read = pg_pptx_design_read($path, $limits);
	@unlink($path);

	return $read;
}

// A one-pixel PNG.
function office_pptx_design_test_png()
{
	return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

// The slide size, the order the presentation gives, hidden slides, and the
// size PowerPoint assumes when none is written.
function test_office_pptx_design_slides_and_size()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$path = office_pptx_design_test_file(array(
		'ppt/slides/slide2.xml' => office_pptx_design_test_slide('', '', 'p:sld', ' show="0"'),
		'ppt/slides/slide1.xml' => office_pptx_design_test_slide(''),
	));
	$read = pg_pptx_design_read($path);
	@unlink($path);

	pg_assert_true($read['ok'], 'read: ' . $read['error']);
	pg_assert_same(18288000, $read['width'], 'width in EMU');
	pg_assert_same(10287000, $read['height'], 'height in EMU');
	pg_assert_same(array(1, 2), array_column($read['slides'], 'n'), 'numbered in the presentation\'s order');
	pg_assert_same(array(true, false), array_column($read['slides'], 'hidden'), 'the hidden slide flagged');
	pg_assert_same(null, $read['slides'][0]['background'], 'no background written');
	pg_assert_same(array(), $read['slides'][0]['elements'], 'no elements');
	pg_assert_same(array(), $read['left'], 'nothing left out');
	pg_assert_same(0, $read['simplified'], 'nothing simplified');
	pg_assert_false($read['truncated'], 'nothing cut');

	$path = office_pptx_design_test_file(array('ppt/slides/slide1.xml' => office_pptx_design_test_slide('')), array(), array(), '');
	$read = pg_pptx_design_read($path);
	@unlink($path);

	pg_assert_same(array(9144000, 6858000), array($read['width'], $read['height']), 'no size written: PowerPoint\'s 4:3');
}

// Backgrounds: a slide's own colour, a theme colour inherited from the
// master through the colour map, a picture, a gradient's first stop.
function test_office_pptx_design_backgrounds()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$read = office_pptx_design_test_one('', array(), array(), '<p:bg><p:bgPr><a:solidFill><a:srgbClr val="FAF7F0"><a:alpha val="50000"/></a:srgbClr></a:solidFill><a:effectLst/></p:bgPr></p:bg>');
	pg_assert_same(array('type' => 'color', 'color' => '#faf7f0', 'opacity' => 0.5), $read['slides'][0]['background'], 'the slide\'s own colour with its alpha');

	// No background on the slide or the layout: the master's, bg2 mapped to lt2.
	$path = office_pptx_design_test_file(
		array('ppt/slides/slide1.xml' => office_pptx_design_test_slide('')),
		array('ppt/slides/slide1.xml' => array('rId1' => array('slideLayout', '../slideLayouts/slideLayout1.xml'))),
		array(
			'ppt/slideLayouts/slideLayout1.xml'             => office_pptx_design_test_slide('', '', 'p:sldLayout'),
			'ppt/slideLayouts/_rels/slideLayout1.xml.rels'  => office_pptx_design_test_rels(array('rId1' => array('slideMaster', '../slideMasters/slideMaster1.xml'))),
			'ppt/slideMasters/slideMaster1.xml'             => str_replace('</p:sldMaster>', '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1"/></p:sldMaster>', office_pptx_design_test_slide('', '<p:bg><p:bgRef idx="1001"><a:schemeClr val="bg2"><a:lumMod val="50000"/></a:schemeClr></p:bgRef></p:bg>', 'p:sldMaster')),
			'ppt/slideMasters/_rels/slideMaster1.xml.rels'  => office_pptx_design_test_rels(array('rId1' => array('theme', '../theme/theme1.xml'))),
			'ppt/theme/theme1.xml'                          => office_pptx_design_test_theme(),
		)
	);
	$read = pg_pptx_design_read($path);
	@unlink($path);

	pg_assert_true($read['ok'], 'read: ' . $read['error']);
	pg_assert_same(array('type' => 'color', 'color' => '#69748c', 'opacity' => 1.0), $read['slides'][0]['background'], 'the master\'s theme colour, darkened by its luminance');

	$read = office_pptx_design_test_one(
		'',
		array('rId4' => array('image', '../media/image1.png')),
		array('ppt/media/image1.png' => office_pptx_design_test_png()),
		'<p:bg><p:bgPr><a:blipFill><a:blip r:embed="rId4"/><a:srcRect l="10000" r="20000"/><a:stretch><a:fillRect/></a:stretch></a:blipFill></p:bgPr></p:bg>'
	);
	pg_assert_same(
		array('type' => 'picture', 'part' => 'ppt/media/image1.png', 'ext' => 'png', 'crop' => array('l' => 0.1, 't' => 0.0, 'r' => 0.2, 'b' => 0.0), 'opacity' => 1.0),
		$read['slides'][0]['background'],
		'a picture background, part and crop'
	);

	$read = office_pptx_design_test_one('', array(), array(), '<p:bg><p:bgPr><a:gradFill><a:gsLst><a:gs pos="0"><a:srgbClr val="112233"/></a:gs><a:gs pos="100000"><a:srgbClr val="FFFFFF"/></a:gs></a:gsLst></a:gradFill></p:bgPr></p:bg>');
	pg_assert_same('#112233', $read['slides'][0]['background']['color'], 'a gradient: its first colour');
	pg_assert_same(array('gradient' => 1), $read['slides'][0]['left'], 'the gradient counted as left out');
	pg_assert_same(array('gradient' => 1), $read['left'], 'and in the file\'s total');
}

// A text box: place, insets, anchor, paragraphs with alignment and spacing,
// line breaks, the first run's style; runs of differing style simplified.
function test_office_pptx_design_text()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$run = '<a:rPr lang="en-US" sz="4800" b="1" spc="120"><a:solidFill><a:srgbClr val="1A1A1A"/></a:solidFill><a:latin typeface="Aileron Bold"/></a:rPr>';
	$body = '<a:bodyPr lIns="0" tIns="0" rIns="0" bIns="0" anchor="t"><a:spAutoFit/></a:bodyPr><a:lstStyle/>'
		. '<a:p><a:pPr algn="ctr"><a:lnSpc><a:spcPts val="5760"/></a:lnSpc></a:pPr><a:r>' . $run . '<a:t>Grow your</a:t></a:r><a:br>' . $run . '</a:br><a:r>' . $run . '<a:t>business</a:t></a:r></a:p>'
		. '<a:p><a:pPr algn="r"><a:lnSpc><a:spcPct val="150000"/></a:lnSpc><a:spcBef><a:spcPts val="600"/></a:spcBef></a:pPr><a:r>' . $run . '<a:t>today</a:t></a:r></a:p>'
		. '<a:p><a:endParaRPr lang="en-US"/></a:p>';
	$read = office_pptx_design_test_one(office_pptx_design_test_sp(office_pptx_design_test_xfrm(952500, 476250, 9525000, 1905000) . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/>', $body, 'TextBox 1'));
	pg_assert_true($read['ok'], 'read: ' . $read['error']);
	$elements = $read['slides'][0]['elements'];
	pg_assert_same(1, count($elements), 'one text layer, no shape under it');
	$text = $elements[0];

	pg_assert_same('text', $text['type'], 'a text');
	pg_assert_same('TextBox 1', $text['name'], 'its name');
	pg_assert_same(array(952500, 476250, 9525000, 1905000), array($text['x'], $text['y'], $text['w'], $text['h']), 'its place');
	pg_assert_same(array(0.0, false, false), array($text['rot'], $text['flip_h'], $text['flip_v']), 'upright');
	pg_assert_same(array('l' => 0, 't' => 0, 'r' => 0, 'b' => 0), $text['inset'], 'no insets');
	pg_assert_same(array('t', true, 'shape'), array($text['anchor'], $text['wrap'], $text['autofit']), 'top, wrapped, the box grows');
	pg_assert_same(array("Grow your\nbusiness", 'today', ''), array_column($text['paragraphs'], 'text'), 'a paragraph each, a break a new line, the empty one kept');
	pg_assert_same(array('ctr', 'r', 'l'), array_column($text['paragraphs'], 'align'), 'alignment, left when none');
	pg_assert_same(array('type' => 'pts', 'value' => 5760), $text['paragraphs'][0]['line'], 'line spacing in hundredths of a point');
	pg_assert_same(array('type' => 'pct', 'value' => 1.5), $text['paragraphs'][1]['line'], 'line spacing as a share');
	pg_assert_same(array('type' => 'pts', 'value' => 600), $text['paragraphs'][1]['before'], 'space before');
	pg_assert_same(null, $text['paragraphs'][2]['line'], 'none written');
	pg_assert_same(
		array('font' => 'Aileron Bold', 'size' => 4800, 'bold' => true, 'italic' => false, 'underline' => false, 'color' => array('color' => '#1a1a1a', 'opacity' => 1.0), 'spacing' => 120),
		$text['style'],
		'the first run\'s style'
	);
	pg_assert_false($text['simplified'], 'every run alike');
	pg_assert_same(0, $read['simplified'], 'nothing simplified');

	// A second run of another size; a theme font; the default insets.
	$body = '<a:bodyPr/><a:p><a:r><a:rPr sz="2400" i="1" u="sng"><a:latin typeface="+mn-lt"/></a:rPr><a:t>Small </a:t></a:r><a:r><a:rPr sz="3600"/><a:t>Large</a:t></a:r></a:p>';
	$path = office_pptx_design_test_file(
		array('ppt/slides/slide1.xml' => office_pptx_design_test_slide(office_pptx_design_test_sp(office_pptx_design_test_xfrm(0, 0, 100, 100), $body))),
		array('ppt/slides/slide1.xml' => array('rId1' => array('slideLayout', '../slideLayouts/slideLayout1.xml'))),
		array(
			'ppt/slideLayouts/slideLayout1.xml'            => office_pptx_design_test_slide('', '', 'p:sldLayout'),
			'ppt/slideLayouts/_rels/slideLayout1.xml.rels' => office_pptx_design_test_rels(array('rId1' => array('slideMaster', '../slideMasters/slideMaster1.xml'))),
			'ppt/slideMasters/slideMaster1.xml'            => office_pptx_design_test_slide('', '', 'p:sldMaster'),
			'ppt/slideMasters/_rels/slideMaster1.xml.rels' => office_pptx_design_test_rels(array('rId1' => array('theme', '../theme/theme1.xml'))),
			'ppt/theme/theme1.xml'                         => office_pptx_design_test_theme(),
		)
	);
	$read = pg_pptx_design_read($path);
	@unlink($path);
	$text = $read['slides'][0]['elements'][0];

	pg_assert_same('Small Large', $text['paragraphs'][0]['text'], 'the runs\' text joined');
	pg_assert_same(array('Inter', 2400, false, true, true, null, 0), array_values($text['style']), 'the first run: the theme\'s minor font, italic, underlined, no colour written');
	pg_assert_same(array('l' => 91440, 't' => 45720, 'r' => 91440, 'b' => 45720), $text['inset'], 'PowerPoint\'s default insets');
	pg_assert_same(array('t', true, 'none'), array($text['anchor'], $text['wrap'], $text['autofit']), 'defaults');
	pg_assert_true($text['simplified'], 'runs of differing style: simplified');
	pg_assert_same(1, $read['slides'][0]['simplified'], 'counted on the slide');
	pg_assert_same(1, $read['simplified'], 'and in the file');

	// A filled shape with text: the shape, then the text in the same place.
	$read = office_pptx_design_test_one(office_pptx_design_test_sp(office_pptx_design_test_xfrm(10, 20, 30, 40) . '<a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 25000"/></a:avLst></a:prstGeom><a:solidFill><a:srgbClr val="FF0000"/></a:solidFill>', '<a:bodyPr anchor="ctr" wrap="none"/><a:p><a:r><a:t>Button</a:t></a:r></a:p>'));
	$elements = $read['slides'][0]['elements'];
	pg_assert_same(array('shape', 'text'), array_column($elements, 'type'), 'the shape under its text');
	pg_assert_same(array(10, 20, 30, 40), array($elements[1]['x'], $elements[1]['y'], $elements[1]['w'], $elements[1]['h']), 'the text in the shape\'s place');
	pg_assert_same(array('ctr', false), array($elements[1]['anchor'], $elements[1]['wrap']), 'centred, not wrapped');
	pg_assert_same(array('roundRect', array('adj' => 25000)), array($elements[0]['geometry'], $elements[0]['adjust']), 'the shape\'s kind and its adjustment');

	// A shape whose text is only spaces is no text.
	$read = office_pptx_design_test_one(office_pptx_design_test_sp(office_pptx_design_test_xfrm(0, 0, 1, 1), '<a:bodyPr/><a:p><a:r><a:t>   </a:t></a:r></a:p>'));
	pg_assert_same(array(), $read['slides'][0]['elements'], 'an empty box: nothing');
}

// Pictures: Canva's filled rectangle alone in a scaling group, its crop
// from the fill rectangle; a plain picture with its source rectangle.
function test_office_pptx_design_pictures()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$canva = office_pptx_design_test_group(
		office_pptx_design_test_sp(
			office_pptx_design_test_xfrm(0, 0, 2000, 1000)
			. '<a:custGeom><a:avLst/><a:gdLst/><a:ahLst/><a:cxnLst/><a:rect l="l" t="t" r="r" b="b"/><a:pathLst><a:path w="2000" h="1000"><a:moveTo><a:pt x="0" y="0"/></a:moveTo><a:lnTo><a:pt x="2000" y="0"/></a:lnTo><a:lnTo><a:pt x="2000" y="1000"/></a:lnTo><a:lnTo><a:pt x="0" y="1000"/></a:lnTo><a:close/></a:path></a:pathLst></a:custGeom>'
			. '<a:blipFill><a:blip r:embed="rId2"><a:alphaModFix amt="80000"/></a:blip><a:srcRect/><a:stretch><a:fillRect l="-25000" t="0" r="-25000" b="0"/></a:stretch></a:blipFill>',
			'',
			'Picture 1'
		),
		100, 200, 1000, 500, 0, 0, 2000, 1000
	);
	$plain = '<p:pic><p:nvPicPr><p:cNvPr id="5" name="Photo"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>'
		. '<p:blipFill><a:blip r:embed="rId3"/><a:srcRect l="10000" t="5000" r="0" b="15000"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>'
		. '<p:spPr>' . office_pptx_design_test_xfrm(5000, 6000, 700, 800, ' flipH="1"') . '<a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom></p:spPr></p:pic>';
	$missing = '<p:pic><p:nvPicPr><p:cNvPr id="6" name="Gone"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr><p:blipFill><a:blip r:embed="rId9"/></p:blipFill><p:spPr>' . office_pptx_design_test_xfrm(0, 0, 1, 1) . '</p:spPr></p:pic>';

	$read = office_pptx_design_test_one(
		$canva . $plain . $missing,
		array('rId2' => array('image', '../media/image1.jpeg'), 'rId3' => array('image', '../media/image2.png'), 'rId9' => array('image', '../media/none.png')),
		array('ppt/media/image1.jpeg' => 'jpeg bytes', 'ppt/media/image2.png' => office_pptx_design_test_png())
	);
	pg_assert_true($read['ok'], 'read: ' . $read['error']);
	$elements = $read['slides'][0]['elements'];
	pg_assert_same(array('picture', 'picture'), array_column($elements, 'type'), 'two pictures, the missing one left out');
	pg_assert_same(array('picture' => 1), $read['slides'][0]['left'], 'the missing picture counted');

	$first = $elements[0];
	pg_assert_same('Picture 1', $first['name'], 'the shape\'s name');
	pg_assert_same(array(100, 200, 1000, 500), array($first['x'], $first['y'], $first['w'], $first['h']), 'scaled to its place by the group');
	pg_assert_same(array('ppt/media/image1.jpeg', 'jpeg'), array($first['part'], $first['ext']), 'its part');
	pg_assert_same(array('l' => 0.1667, 't' => 0.0, 'r' => 0.1667, 'b' => 0.0), $first['crop'], 'the fill rectangle as a crop of the picture');
	pg_assert_same(array('rect', 0.8), array($first['geometry'], $first['opacity']), 'a rectangle, its opacity');

	$second = $elements[1];
	pg_assert_same(array(5000, 6000, 700, 800, true), array($second['x'], $second['y'], $second['w'], $second['h'], $second['flip_h']), 'its place, flipped');
	pg_assert_same(array('l' => 0.1, 't' => 0.05, 'r' => 0.0, 'b' => 0.15), $second['crop'], 'the source rectangle as the crop');
	pg_assert_same(array('ellipse', 1.0), array($second['geometry'], $second['opacity']), 'cut to an ellipse');
}

// Groups turned and flipped: the child's centre moves with the group, its
// turn and flips add up; elements in paint order.
function test_office_pptx_design_groups()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$child = office_pptx_design_test_sp(office_pptx_design_test_xfrm(0, 0, 200, 200, ' rot="600000"') . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="000000"/></a:solidFill>', '', 'Box');
	$turned = office_pptx_design_test_group($child, 0, 0, 1000, 1000, 0, 0, 1000, 1000, ' rot="5400000"');
	$flipped = office_pptx_design_test_group($child, 0, 0, 1000, 1000, 0, 0, 1000, 1000, ' flipH="1"');
	$nested = office_pptx_design_test_group(office_pptx_design_test_group($child, 0, 0, 100, 100, 0, 0, 200, 200), 1000, 1000, 50, 50, 0, 0, 100, 100);

	$read = office_pptx_design_test_one($turned . $flipped . $nested);
	$elements = $read['slides'][0]['elements'];
	pg_assert_same(3, count($elements), 'three boxes, in paint order');
	pg_assert_same(array(800, 0, 200, 200, 100.0, false), array($elements[0]['x'], $elements[0]['y'], $elements[0]['w'], $elements[0]['h'], $elements[0]['rot'], $elements[0]['flip_h']), 'a quarter turn moves it to the top right and adds the turn');
	pg_assert_same(array(800, 0, 350.0, true), array($elements[1]['x'], $elements[1]['y'], $elements[1]['rot'], $elements[1]['flip_h']), 'a mirror moves it right, flips it and turns it back');
	pg_assert_same(array(1000, 1000, 50, 50), array($elements[2]['x'], $elements[2]['y'], $elements[2]['w'], $elements[2]['h']), 'nested scales multiply');
}

// Lines and shapes: ends from the box and its flips, the stroke, dashes and
// heads, fills from the theme's style, what the canvas cannot draw counted.
function test_office_pptx_design_lines_and_shapes()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$line = office_pptx_design_test_sp(office_pptx_design_test_xfrm(100, 100, 400, 300, ' flipV="1"') . '<a:prstGeom prst="line"><a:avLst/></a:prstGeom><a:ln w="25400"><a:solidFill><a:srgbClr val="333333"/></a:solidFill><a:prstDash val="dash"/><a:tailEnd type="triangle"/></a:ln>', '', 'Line 1');
	$connector = '<p:cxnSp><p:nvCxnSpPr><p:cNvPr id="7" name="Connector"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr><p:spPr>' . office_pptx_design_test_xfrm(0, 50, 100, 0) . '<a:prstGeom prst="straightConnector1"><a:avLst/></a:prstGeom></p:spPr>'
		. '<p:style><a:lnRef idx="1"><a:srgbClr val="00FF00"/></a:lnRef><a:fillRef idx="0"><a:srgbClr val="00FF00"/></a:fillRef><a:effectRef idx="0"><a:srgbClr val="00FF00"/></a:effectRef><a:fontRef idx="minor"/></p:style></p:cxnSp>';
	$ellipse = office_pptx_design_test_sp(office_pptx_design_test_xfrm(0, 0, 50, 50) . '<a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom><a:ln w="12700"><a:solidFill><a:srgbClr val="0000FF"/></a:solidFill></a:ln><a:effectLst><a:outerShdw blurRad="40000"/></a:effectLst>', '', 'Oval')
		. '';
	$styled = '<p:sp><p:nvSpPr><p:cNvPr id="8" name="Styled"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr>' . office_pptx_design_test_xfrm(0, 0, 10, 10) . '<a:prstGeom prst="star5"><a:avLst/></a:prstGeom></p:spPr>'
		. '<p:style><a:lnRef idx="0"><a:srgbClr val="000000"/></a:lnRef><a:fillRef idx="1"><a:srgbClr val="ABCDEF"/></a:fillRef><a:effectRef idx="0"><a:srgbClr val="000000"/></a:effectRef><a:fontRef idx="minor"/></p:style></p:sp>';
	$cloud = office_pptx_design_test_sp(office_pptx_design_test_xfrm(0, 0, 10, 10) . '<a:prstGeom prst="cloud"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="000000"/></a:solidFill>');
	$curve = office_pptx_design_test_sp(office_pptx_design_test_xfrm(0, 0, 10, 10) . '<a:custGeom><a:pathLst><a:path w="10" h="10"><a:moveTo><a:pt x="0" y="0"/></a:moveTo><a:cubicBezTo><a:pt x="1" y="1"/><a:pt x="2" y="2"/><a:pt x="3" y="3"/></a:cubicBezTo></a:path></a:pathLst></a:custGeom><a:solidFill><a:srgbClr val="000000"/></a:solidFill>');

	$read = office_pptx_design_test_one($line . $connector . $ellipse . $styled . $cloud . $curve);
	pg_assert_true($read['ok'], 'read: ' . $read['error']);
	$elements = $read['slides'][0]['elements'];
	pg_assert_same(array('line', 'line', 'shape', 'shape'), array_column($elements, 'type'), 'two lines and two shapes; the cloud and the curve left out');

	$first = $elements[0];
	pg_assert_same(array(100, 400, 500, 100), array($first['x1'], $first['y1'], $first['x2'], $first['y2']), 'a line flipped upright runs from the bottom left up');
	pg_assert_same(array('color' => '#333333', 'opacity' => 1.0, 'width' => 25400, 'dash' => 'dash', 'head_start' => '', 'head_end' => 'triangle'), $first['stroke'], 'its stroke');
	pg_assert_same(array(0, 50, 100, 50), array($elements[1]['x1'], $elements[1]['y1'], $elements[1]['x2'], $elements[1]['y2']), 'a connector');
	pg_assert_same('#00ff00', $elements[1]['stroke']['color'], 'its colour from the style');
	pg_assert_same(12700, $elements[1]['stroke']['width'], 'the default width');

	pg_assert_same(array('ellipse', null), array($elements[2]['geometry'], $elements[2]['fill']), 'an ellipse with no fill');
	pg_assert_same('#0000ff', $elements[2]['stroke']['color'], 'its outline');
	pg_assert_same(array('star5', array('color' => '#abcdef', 'opacity' => 1.0), null), array($elements[3]['geometry'], $elements[3]['fill'], $elements[3]['stroke']), 'a star filled from the style, no outline');
	pg_assert_same(array('effect' => 1, 'shape' => 2), $read['slides'][0]['left'], 'the shadow and the two shapes counted');
}

// What the canvas cannot hold is left out and counted by kind.
function test_office_pptx_design_left_out()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$frame = function ($uri) {
		return '<p:graphicFrame><p:nvGraphicFramePr><p:cNvPr id="4" name="Frame"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>'
			. '<p:xfrm><a:off x="0" y="0"/><a:ext cx="10" cy="10"/></p:xfrm><a:graphic><a:graphicData uri="' . $uri . '"/></a:graphic></p:graphicFrame>';
	};
	$video = '<p:pic><p:nvPicPr><p:cNvPr id="9" name="Video"/><p:cNvPicPr/><p:nvPr><a:videoFile r:link="rId2"/></p:nvPr></p:nvPicPr><p:blipFill><a:blip r:embed="rId3"/></p:blipFill><p:spPr>' . office_pptx_design_test_xfrm(0, 0, 1, 1) . '</p:spPr></p:pic>';

	$read = office_pptx_design_test_one(
		$frame('http://schemas.openxmlformats.org/drawingml/2006/table') . $frame('http://schemas.openxmlformats.org/drawingml/2006/chart')
		. $frame('http://schemas.openxmlformats.org/drawingml/2006/diagram') . $frame('http://schemas.openxmlformats.org/presentationml/2006/ole') . $video,
		array('rId3' => array('image', '../media/image1.png')),
		array('ppt/media/image1.png' => office_pptx_design_test_png())
	);
	pg_assert_same(array(), $read['slides'][0]['elements'], 'nothing brought');
	pg_assert_same(array('chart' => 1, 'diagram' => 1, 'media' => 1, 'object' => 1, 'table' => 1), $read['left'], 'each counted by its kind');
}

// A placeholder that writes no place takes its layout's.
function test_office_pptx_design_placeholder_place()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$layout_shape = office_pptx_design_test_sp(office_pptx_design_test_xfrm(111, 222, 333, 444), '<a:bodyPr/><a:p><a:r><a:t>Click to add title</a:t></a:r></a:p>', 'Title', '<p:ph type="title"/>');
	$slide_shape = office_pptx_design_test_sp('', '<a:bodyPr/><a:p><a:r><a:t>Hello</a:t></a:r></a:p>', 'Title 1', '<p:ph type="title"/>');
	$path = office_pptx_design_test_file(
		array('ppt/slides/slide1.xml' => office_pptx_design_test_slide($slide_shape)),
		array('ppt/slides/slide1.xml' => array('rId1' => array('slideLayout', '../slideLayouts/slideLayout1.xml'))),
		array('ppt/slideLayouts/slideLayout1.xml' => office_pptx_design_test_slide($layout_shape, '', 'p:sldLayout'))
	);
	$read = pg_pptx_design_read($path);
	@unlink($path);
	$elements = $read['slides'][0]['elements'];

	pg_assert_same(1, count($elements), 'only the slide\'s own text, never the layout\'s prompt');
	pg_assert_same(array('Hello', 111, 222, 333, 444), array($elements[0]['paragraphs'][0]['text'], $elements[0]['x'], $elements[0]['y'], $elements[0]['w'], $elements[0]['h']), 'the layout\'s place');
}

// The limits: elements a slide, then the list cut; refusals with reasons.
function test_office_pptx_design_limits_and_refusals()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$box = office_pptx_design_test_sp(office_pptx_design_test_xfrm(0, 0, 1, 1) . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="000000"/></a:solidFill>');
	$read = office_pptx_design_test_one(str_repeat($box, 5), array(), array(), '', array('elements' => 3));
	pg_assert_same(3, count($read['slides'][0]['elements']), 'three elements kept');
	pg_assert_true($read['truncated'], 'the rest cut, said');

	$path = tempnam(sys_get_temp_dir(), 'pg_pptx_design_');
	file_put_contents($path, 'not a zip at all');
	$read = pg_pptx_design_read($path);
	pg_assert_false($read['ok'], 'not a zip');
	pg_assert_same(lang('The file could not be read.'), $read['error'], 'could not be read');

	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);
	$zip->addFromString('[Content_Types].xml', '<Types/>');
	$zip->addFromString('word/document.xml', '<w:document/>');
	$zip->close();
	$read = pg_pptx_design_read($path);
	@unlink($path);
	pg_assert_false($read['ok'], 'a zip with no presentation');
	pg_assert_same(lang('The file could not be read.'), $read['error'], 'could not be read either');
}
