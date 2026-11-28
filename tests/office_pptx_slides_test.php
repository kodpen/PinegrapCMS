<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a presentation's Slides tab on the server
 * (includes/fn/office_pptx_slides.php): the line on what the drawing leaves
 * out, a presentation the test writes read as slides beside its text list,
 * one slide's pictures as data: addresses, and the refusals.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/office_pptx_slides.php');

// A presentation of two slides: the first a title and a 2 x 2 PNG shown
// twice (once cut), the second a chart left out. Its path, or '' without the
// zip extension.
function office_pptx_slides_package()
{
	if (!pg_office_ready()) {
		return '';
	}

	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
	$ns = ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
	$rels = function ($list) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

		foreach ($list as $id => $rel) {
			$xml .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $rel[0] . '" Target="' . $rel[1] . '"/>';
		}

		return $xml . '</Relationships>';
	};
	$tree = '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>';
	$title = '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:spPr><a:xfrm><a:off x="952500" y="952500"/><a:ext cx="9525000" cy="952500"/></a:xfrm></p:spPr>'
		. '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="en-US" sz="3000"><a:latin typeface="Roboto"/></a:rPr><a:t>Open house</a:t></a:r></a:p></p:txBody></p:sp>';
	$pic = function ($id, $crop) {
		return '<p:pic><p:nvPicPr><p:cNvPr id="' . $id . '" name="Picture"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr><p:blipFill><a:blip r:embed="rId2"/>' . $crop . '<a:stretch><a:fillRect/></a:stretch></p:blipFill>'
			. '<p:spPr><a:xfrm><a:off x="9525000" y="0"/><a:ext cx="952500" cy="952500"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>';
	};
	$chart = '<p:graphicFrame><p:nvGraphicFramePr><p:cNvPr id="4" name="Chart"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr><p:xfrm><a:off x="0" y="0"/><a:ext cx="952500" cy="952500"/></p:xfrm>'
		. '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" r:id="rId3"/></a:graphicData></a:graphic></p:graphicFrame>';
	$parts = array(
		'[Content_Types].xml'              => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'_rels/.rels'                      => $rels(array('rId1' => array('officeDocument', 'ppt/presentation.xml'))),
		'ppt/presentation.xml'             => '<?xml version="1.0" encoding="UTF-8"?><p:presentation' . $ns . '><p:sldIdLst><p:sldId id="256" r:id="rId10"/><p:sldId id="257" r:id="rId11"/></p:sldIdLst><p:sldSz cx="18288000" cy="10287000"/></p:presentation>',
		'ppt/_rels/presentation.xml.rels'  => $rels(array('rId10' => array('slide', 'slides/slide1.xml'), 'rId11' => array('slide', 'slides/slide2.xml'))),
		'ppt/slides/slide1.xml'            => '<?xml version="1.0" encoding="UTF-8"?><p:sld' . $ns . '><p:cSld><p:spTree>' . $tree . $title . $pic(3, '') . $pic(5, '<a:srcRect l="50000"/>') . '</p:spTree></p:cSld></p:sld>',
		'ppt/slides/_rels/slide1.xml.rels' => $rels(array('rId2' => array('image', '../media/image1.png'))),
		'ppt/slides/slide2.xml'            => '<?xml version="1.0" encoding="UTF-8"?><p:sld' . $ns . ' show="0"><p:cSld><p:spTree>' . $tree . $chart . '</p:spTree></p:cSld></p:sld>',
		'ppt/media/image1.png'             => $png,
	);
	$path = tempnam(sys_get_temp_dir(), 'pg_pptxslides_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $bytes) {
		$zip->addFromString($name, $bytes);
	}

	$zip->close();

	return $path;
}

// The quiet line: kinds not drawn with their counts, other kinds summed,
// fonts replaced, simplified elements; nothing counted, no line.
function test_office_pptx_left_line()
{
	pg_assert_same('', pg_office_pptx_left_line(array('left' => array(), 'fonts' => array(), 'simplified' => 0)), 'nothing left out: no line');
	pg_assert_same(
		'Shown approximately. Not drawn: Charts (2), Tables (1), Other elements (4). 3 font(s) replaced. 1 element(s) simplified.',
		pg_office_pptx_left_line(array('left' => array('chart' => 2, 'shape' => 3, 'table' => 1, 'zzz' => 1), 'fonts' => array('Aileron' => 'Roboto', 'Canva Sans' => 'Roboto', 'Calibri' => 'Roboto'), 'simplified' => 1)),
		'kinds, fonts and simplified'
	);
	pg_assert_same('Shown approximately. 1 font(s) replaced.', pg_office_pptx_left_line(array('left' => array('chart' => 0), 'fonts' => array('A' => 'Roboto'))), 'a zero count is not told');
}

// The preview's read of a presentation carries its slides as documents,
// pictures still named by their parts; one slide's pictures come as data:
// addresses, a part shown twice with the same cut read once.
function test_office_pptx_slides_read()
{
	$path = office_pptx_slides_package();

	if ($path === '') {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$office = pg_office_read($path, 'deck.pptx');
	pg_assert_true($office['ok'], 'the text list is read');
	pg_assert_same(2, count($office['slides']), 'two slides in the text list');

	$design = $office['design'];
	pg_assert_same(array(1920, 1080, 2), array($design['width'], $design['height'], $design['count']), 'the board\'s size and the count');
	pg_assert_same(array(1, 2), array_column($design['pages'], 'n'), 'both slides');
	pg_assert_same(array(false, true), array_column($design['pages'], 'hidden'), 'the second slide is hidden');
	pg_assert_false(isset($design['pages'][0]['pictures']), 'the cut instructions stay on the server');

	$images = array_values(array_filter($design['pages'][0]['doc']['layers'], function ($l) { return $l['type'] === 'image'; }));
	pg_assert_same(2, count($images), 'two picture layers');
	pg_assert_same('pptx:ppt/media/image1.png', $images[0]['src'], 'named by its part');
	pg_assert_contains('Charts (1)', $design['left'], 'the chart is told');

	$pictures = pg_office_pptx_slide_pictures($path, 1);
	pg_assert_true($pictures['ok'], 'slide 1\'s pictures');
	pg_assert_same(0, $pictures['lost'], 'none lost');
	pg_assert_same(array($images[0]['id'], $images[1]['id']), array_keys($pictures['pictures']), 'one address a layer');
	pg_assert_same(0, strpos($pictures['pictures'][$images[0]['id']], 'data:image/png;base64,'), 'a PNG data: address');

	$none = pg_office_pptx_slide_pictures($path, '2');
	pg_assert_same(array(true, array()), array($none['ok'], $none['pictures']), 'a slide without pictures');
	pg_assert_false(pg_office_pptx_slide_pictures($path, 3)['ok'], 'no slide 3');
	pg_assert_false(pg_office_pptx_slide_pictures($path, 0)['ok'], 'no slide 0');
	pg_assert_false(pg_office_pptx_slide_pictures($path, '1; x')['ok'], 'a number only');
	pg_assert_false(pg_office_pptx_slide_pictures($path, array(1))['ok'], 'not a list');

	@unlink($path);

	$bad = tempnam(sys_get_temp_dir(), 'pg_pptxslides_');
	file_put_contents($bad, 'not a package');
	pg_assert_same(null, pg_office_pptx_slides_drawn($bad), 'not a package: no slides');
	pg_assert_false(pg_office_pptx_slide_pictures($bad, 1)['ok'], 'not a package: no pictures');
	@unlink($bad);
}

// Runs a script after the view in Node with its Slides helpers as S and
// answers what it printed as JSON; null when Node cannot run.
function office_pptx_slides_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgslides');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar S = window.pgOfficeView.slides;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The Slides tab's own logic: pictures only from the door's data:
// addresses, the size kept in ratio and never above the slide's own, the
// keys, the name a screen reader hears.
function test_office_pptx_slides_view_helpers()
{
	$result = office_pptx_slides_node(<<<'JS'
var doc = { artboard: { width: 1920, height: 1080 }, layers: [
	{ id: 'l0', type: 'image', src: 'pptx:ppt/media/a.png' },
	{ id: 'l1', type: 'text', text: 'Hi' },
	{ id: 'l2', type: 'image', src: 'pptx:ppt/media/b.png' },
	{ id: 'l3', type: 'image', src: 'pptx:ppt/media/c.png' }
] };
var drawn = S.doc(doc, { l0: 'data:image/png;base64,AAAA', l2: 'https://example.com/x.png', l3: 'data:text/html;base64,AAAA' });
console.log(JSON.stringify({
	layers: drawn.layers.map(function (l) { return l.id; }),
	src: drawn.layers[0].src,
	kept: doc.layers[0].src,
	none: S.doc(doc, null).layers.length,
	thumb: S.fit(1920, 1080, 148, 0),
	wide: S.fit(1920, 1080, 4000, 3000),
	tall: S.fit(1920, 1080, 1000, 300),
	keys: [S.key('ArrowRight', 0, 3), S.key('ArrowLeft', 0, 3), S.key('ArrowDown', 2, 3), S.key('Home', 2, 3), S.key('End', 0, 3), S.key('a', 1, 3)],
	name: S.name({ slide_of: 'Slide {1} of {2}' }, 3, 24, 'Plans'),
	bare: S.name({ slide_of: 'Slide {1} of {2}' }, 3, 24, '')
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('l0', 'l1'), $result['layers'], 'a picture without a data: image address is left out');
	pg_assert_same('data:image/png;base64,AAAA', $result['src'], 'the door\'s address is drawn');
	pg_assert_same('pptx:ppt/media/a.png', $result['kept'], 'the server\'s document is not changed');
	pg_assert_same(1, $result['none'], 'no pictures: the text alone');
	pg_assert_same(array('w' => 148, 'h' => 83), $result['thumb'], 'a thumb 148 px wide in the slide\'s ratio');
	pg_assert_same(array('w' => 1920, 'h' => 1080), $result['wide'], 'never larger than its own size');
	pg_assert_same(array('w' => 533, 'h' => 300), $result['tall'], 'fitted to the height');
	pg_assert_same(array(1, 0, 2, 0, 2, -1), $result['keys'], 'arrows, Home and End; other keys are not the slides\'');
	pg_assert_same('Slide 3 of 24: Plans', $result['name'], 'the name with the title');
	pg_assert_same('Slide 3 of 24', $result['bare'], 'without a title');
}
