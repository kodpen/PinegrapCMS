<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Artifact Canvas's design file action
 * (includes/fn/image_editor_design.php) and Import's page as a group
 * (assets/js/pg_image_editor_design.js, run under Node with the model): the
 * pages asked for, a package told by its bytes, a picture's type and cut, the
 * pixels cut and flipped, a presentation the test writes read into pages with
 * their pictures, the document pointed at the files, and a page fitted to the
 * board as one group.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor_design.php');

// Runs $script with the model as M and the design part as D; null without Node.
function image_editor_design_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiedesign');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var D = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_design.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A PNG of w x h whose left half is red and right half blue; '' without GD.
function image_editor_design_png($w, $h)
{
	if (!function_exists('imagecreatetruecolor')) {
		return '';
	}

	$image = imagecreatetruecolor($w, $h);
	$red = imagecolorallocate($image, 255, 0, 0);
	$blue = imagecolorallocate($image, 0, 0, 255);
	imagefilledrectangle($image, 0, 0, (int) ($w / 2) - 1, $h - 1, $red);
	imagefilledrectangle($image, (int) ($w / 2), 0, $w - 1, $h - 1, $blue);
	ob_start();
	imagepng($image);
	pg_image_destroy($image);

	return (string) ob_get_clean();
}

// The colour at x, y of a picture's bytes as 'r,g,b'.
function image_editor_design_pixel($bytes, $x, $y)
{
	$image = imagecreatefromstring($bytes);
	$c = imagecolorsforindex($image, imagecolorat($image, $x, $y));
	pg_image_destroy($image);

	return $c['red'] . ',' . $c['green'] . ',' . $c['blue'];
}

// A presentation of two slides: the first a text and a picture cut on its
// sides, the second a colour board with a rectangle. Its path, or '' without
// the zip extension.
function image_editor_design_package($picture)
{
	if (!pg_office_ready()) {
		return '';
	}

	$ns = ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
	$rels = function ($list) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

		foreach ($list as $id => $rel) {
			$xml .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $rel[0] . '" Target="' . $rel[1] . '"/>';
		}

		return $xml . '</Relationships>';
	};
	$tree = '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>';
	$text = '<p:sp><p:nvSpPr><p:cNvPr id="2" name="TextBox 1"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="952500" y="952500"/><a:ext cx="9525000" cy="952500"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>'
		. '<p:txBody><a:bodyPr lIns="0" tIns="0" rIns="0" bIns="0"/><a:lstStyle/><a:p><a:r><a:rPr lang="en-US" sz="3000"><a:latin typeface="Roboto"/></a:rPr><a:t>Open house</a:t></a:r></a:p></p:txBody></p:sp>';
	$pic = '<p:pic><p:nvPicPr><p:cNvPr id="3" name="Picture"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr><p:blipFill><a:blip r:embed="rId2"/><a:srcRect l="50000"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>'
		. '<p:spPr><a:xfrm><a:off x="9525000" y="0"/><a:ext cx="952500" cy="952500"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>';
	$rect = '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Box"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="952500" cy="952500"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="00FF00"/></a:solidFill></p:spPr></p:sp>';
	$parts = array(
		'[Content_Types].xml'              => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'_rels/.rels'                      => $rels(array('rId1' => array('officeDocument', 'ppt/presentation.xml'))),
		'ppt/presentation.xml'             => '<?xml version="1.0" encoding="UTF-8"?><p:presentation' . $ns . '><p:sldIdLst><p:sldId id="256" r:id="rId10"/><p:sldId id="257" r:id="rId11"/></p:sldIdLst><p:sldSz cx="18288000" cy="10287000"/></p:presentation>',
		'ppt/_rels/presentation.xml.rels'  => $rels(array('rId10' => array('slide', 'slides/slide1.xml'), 'rId11' => array('slide', 'slides/slide2.xml'))),
		'ppt/slides/slide1.xml'            => '<?xml version="1.0" encoding="UTF-8"?><p:sld' . $ns . '><p:cSld><p:spTree>' . $tree . $text . $pic . '</p:spTree></p:cSld></p:sld>',
		'ppt/slides/_rels/slide1.xml.rels' => $rels(array('rId2' => array('image', '../media/image1.png'))),
		'ppt/slides/slide2.xml'            => '<?xml version="1.0" encoding="UTF-8"?><p:sld' . $ns . '><p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="102030"/></a:solidFill></p:bgPr></p:bg><p:spTree>' . $tree . $rect . '</p:spTree></p:cSld></p:sld>',
		'ppt/media/image1.png'             => $picture,
	);
	$path = tempnam(sys_get_temp_dir(), 'pg_iedesign_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $bytes) {
		$zip->addFromString($name, $bytes);
	}

	$zip->close();

	return $path;
}

// The pages asked for: none is a read, else whole numbers of the file's
// pages, sorted once each, no more than the most an open takes.
function test_image_editor_design_pages()
{
	pg_assert_same(null, pg_image_editor_design_pages(null, 3), 'no pages: the file is read');
	pg_assert_same(array(1, 3), pg_image_editor_design_pages(array(3, 1, 3), 3), 'sorted, once each');
	pg_assert_false(pg_image_editor_design_pages(array(0), 3), 'no page 0');
	pg_assert_false(pg_image_editor_design_pages(array(4), 3), 'no page past the last');
	pg_assert_false(pg_image_editor_design_pages(array('2'), 3), 'a number, not a string');
	pg_assert_false(pg_image_editor_design_pages(array(), 3), 'an empty list');
	pg_assert_false(pg_image_editor_design_pages('1', 3), 'not a list');
	pg_assert_false(pg_image_editor_design_pages(range(1, PG_IMAGE_EDITOR_DESIGN_MAX_PAGES + 1), 500), 'no more than an open takes');
	pg_assert_same(range(1, PG_IMAGE_EDITOR_DESIGN_MAX_PAGES), pg_image_editor_design_pages(range(1, PG_IMAGE_EDITOR_DESIGN_MAX_PAGES), 500), 'as many as it takes');
}

// A package is told by its bytes, and the upload in parts knows the kind.
function test_image_editor_design_format()
{
	pg_assert_same('pptx', pg_image_editor_design_format("PK\x03\x04rest"), 'a ZIP package');
	pg_assert_same('', pg_image_editor_design_format('%PDF-1.7'), 'a PDF is not one');
	pg_assert_same('', pg_image_editor_design_format(''), 'nothing is not one');
	pg_assert_same('design_file', pg_image_editor_upload_kinds()['design'] ?? '', 'a design file may come in parts');
	pg_assert_same('pptx', pg_image_editor_upload_format('design', "PK\x03\x04rest"), 'its first part is told the same way');
	pg_assert_same('', pg_image_editor_upload_format('design', "\x89PNG\r\n\x1a\n"), 'a picture is not a design file');
}

// A picture's type by its bytes, never its name.
function test_image_editor_design_picture_type()
{
	pg_assert_same('png', pg_image_editor_design_picture_type("\x89PNG\r\n\x1a\nxxxx"), 'PNG');
	pg_assert_same('jpg', pg_image_editor_design_picture_type("\xff\xd8\xff\xe0xxxx"), 'JPEG');
	pg_assert_same('gif', pg_image_editor_design_picture_type('GIF89axxxx'), 'GIF');
	pg_assert_same('webp', pg_image_editor_design_picture_type('RIFF1234WEBPVP8 '), 'WebP');
	pg_assert_same('', pg_image_editor_design_picture_type('<svg xmlns="http://www.w3.org/2000/svg"/>'), 'an SVG is left out');
	pg_assert_same('', pg_image_editor_design_picture_type("\x01\x00\x00\x00emf"), 'an EMF is left out');
}

// The pixels a crop keeps, in whole pixels, at least one each way.
function test_image_editor_design_cut_box()
{
	pg_assert_same(array(20, 25, 160, 50), pg_image_editor_design_cut_box(200, 100, array('l' => 0.1, 't' => 0.25, 'r' => 0.1, 'b' => 0.25)), 'the sides cut');
	pg_assert_same(array(0, 0, 200, 100), pg_image_editor_design_cut_box(200, 100, array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0)), 'nothing cut');
	pg_assert_same(array(140, 0, 1, 100), pg_image_editor_design_cut_box(200, 100, array('l' => 0.7, 't' => 0.0, 'r' => 0.6, 'b' => 0.0)), 'cut past the middle: one pixel left');
	pg_assert_false(pg_image_editor_design_redrawn('png', array('crop' => array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0), 'flip_h' => false, 'flip_v' => false)), 'a PNG as it is');
	pg_assert_true(pg_image_editor_design_redrawn('gif', array('crop' => array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0), 'flip_h' => false, 'flip_v' => false)), 'a GIF becomes a PNG');
	pg_assert_true(pg_image_editor_design_redrawn('jpg', array('crop' => array('l' => 0.0, 't' => 0.1, 'r' => 0.0, 'b' => 0.0), 'flip_h' => false, 'flip_v' => false)), 'a cut');
	pg_assert_true(pg_image_editor_design_redrawn('jpg', array('crop' => array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0), 'flip_h' => false, 'flip_v' => true)), 'a flip');
}

// The cut and the flips drawn into the pixels; a JPEG stays one.
function test_image_editor_design_redraw()
{
	$png = image_editor_design_png(4, 2);

	if ($png === '') {
		pg_assert_true(true, 'no GD here');
		return;
	}

	$none = array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0);
	$cut = pg_image_editor_design_redraw($png, 'png', array('crop' => array('l' => 0.5) + $none, 'flip_h' => false, 'flip_v' => false));
	pg_assert_same('png', $cut[1], 'a PNG');
	pg_assert_same(array(2, 2), array_slice(getimagesizefromstring($cut[0]), 0, 2), 'the left half cut');
	pg_assert_same('0,0,255', image_editor_design_pixel($cut[0], 0, 0), 'blue is left');

	$flipped = pg_image_editor_design_redraw($png, 'png', array('crop' => $none, 'flip_h' => true, 'flip_v' => false));
	pg_assert_same('0,0,255', image_editor_design_pixel($flipped[0], 0, 0), 'mirrored: blue first');

	$image = imagecreatefromstring($png);
	ob_start();
	imagejpeg($image, null, 95);
	$jpeg = (string) ob_get_clean();
	pg_image_destroy($image);
	pg_assert_same('jpg', pg_image_editor_design_redraw($jpeg, 'jpg', array('crop' => array('t' => 0.5) + $none, 'flip_h' => false, 'flip_v' => false))[1], 'a JPEG stays a JPEG');
	pg_assert_same(null, pg_image_editor_design_redraw('not a picture', 'png', array('crop' => $none, 'flip_h' => true, 'flip_v' => false)), 'bytes no picture: null');
}

// The document's picture layers pointed at their files; one whose picture
// could not be kept is left out.
function test_image_editor_design_point()
{
	$doc = array('layers' => array(
		array('id' => 'l0', 'type' => 'image', 'src' => 'pptx:ppt/media/a.png', 'fileName' => 'a.png'),
		array('id' => 'l1', 'type' => 'text', 'text' => 'Hi'),
		array('id' => 'l2', 'type' => 'image', 'src' => 'pptx:ppt/media/b.emf', 'fileName' => 'b.emf'),
	));
	$lost = pg_image_editor_design_point($doc, array('l0' => array('id' => 7, 'url' => '/a.png')));

	pg_assert_same(1, $lost, 'one picture lost');
	pg_assert_same(array('l0', 'l1'), array_column($doc['layers'], 'id'), 'its layer left out');
	pg_assert_same(array('/a.png', 7), array($doc['layers'][0]['src'], $doc['layers'][0]['fileId']), 'the kept one names its file');
}

// A written presentation read: the file's pages, a page's document and its
// pictures; a page's picture taken out of the package, cut.
function test_image_editor_design_read_package()
{
	$png = image_editor_design_png(4, 2);
	$path = image_editor_design_package(($png !== '') ? $png : base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

	if ($path === '') {
		pg_assert_true(true, 'no zip extension here');
		return;
	}

	$read = pg_image_editor_design_read($path, null);
	pg_assert_same('', $read['error'], 'read');
	pg_assert_same(array(2, 1920, 1080), array($read['count'], $read['width'], $read['height']), 'two pages of Canva\'s size');
	pg_assert_same(array(1, 2), array_column($read['pages'], 'n'), 'both pages');
	pg_assert_same(array('text', 'image'), array_column($read['pages'][0]['doc']['layers'], 'type'), 'page 1: a text and a picture');
	pg_assert_same('#102030', $read['pages'][1]['doc']['artboard']['background'], 'page 2\'s board colour');
	pg_assert_same(array('texts' => 1, 'pictures' => 1, 'shapes' => 1), array_intersect_key($read['report'], array('texts' => 1, 'pictures' => 1, 'shapes' => 1)), 'the file\'s report');

	$two = pg_image_editor_design_read($path, array(2));
	pg_assert_same(array(2), array_column($two['pages'], 'n'), 'only the page asked for');

	$one = pg_image_editor_design_read($path, array(1));
	$picture = $one['pages'][0]['pictures']['l1'];
	pg_assert_same('ppt/media/image1.png', $picture['part'], 'the picture\'s part');

	if ($png !== '') {
		$zip = pg_office_open($path, $error);
		$taken = pg_image_editor_design_picture_bytes($zip, $picture);
		$zip->close();
		pg_assert_same('png', $taken[1], 'taken as a PNG');
		pg_assert_same(array(2, 2), array_slice(getimagesizefromstring($taken[0]), 0, 2), 'with the file\'s cut');
	}

	@unlink($path);

	$bad = tempnam(sys_get_temp_dir(), 'pg_iedesign_');
	file_put_contents($bad, 'not a package');
	pg_assert_true(pg_image_editor_design_read($bad, null)['error'] !== '', 'not a package: a sentence');
	@unlink($bad);
}

// A page joins the canvas as one group fitted to its board: centred,
// proportions kept, letters scaled, new ids, the page's colour a rectangle
// under it, a locked background unlocked.
function test_image_editor_design_place()
{
	$r = image_editor_design_node(
		'var page = { artboard: { width: 1920, height: 1080, background: "#102030" }, layers: ['
		. '{ id: "l0", type: "image", src: "/b.png", x: 0, y: 0, w: 1920, h: 1080, rot: 0, opacity: 1, locked: true },'
		. '{ id: "l1", type: "text", text: "Hi", size: 40, x: 960, y: 540, w: 200, h: 48, rot: 0, opacity: 1 }] };' . "\n"
		. 'var doc = { artboard: { width: 960, height: 960, background: "#ffffff" }, layers: [{ id: "l0", type: "rect", x: 0, y: 0, w: 10, h: 10, rot: 0, grp: ["g1", "g2"] }] };' . "\n"
		. 'var white = { artboard: { width: 100, height: 50, background: "#ffffff" }, layers: [{ id: "l0", type: "rect", x: 0, y: 0, w: 100, h: 50, rot: 0 }] };' . "\n"
		. 'var out = D.designPlace(M, doc, page, { x: 0, y: 0, w: 960, h: 960 });' . "\n"
		. 'console.log(JSON.stringify({ fit: D.designFit({ w: 1920, h: 1080 }, { x: 0, y: 0, w: 960, h: 960 }), out: out,'
		. ' white: D.designPlace(M, doc, white, { x: 0, y: 0, w: 960, h: 960 }).layers.length }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'no Node here');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same(array('x' => 0, 'y' => 210, 'w' => 960, 'h' => 540), $r['fit'], 'contained and centred');

	$layers = $r['out']['layers'];
	pg_assert_same('g3', $r['out']['group'], 'a group id the document does not use');
	pg_assert_same(array('rect', 'image', 'text'), array_column($layers, 'type'), 'the page\'s colour under its layers');
	pg_assert_same(array('l1', 'l2', 'l3'), array_column($layers, 'id'), 'new ids');
	pg_assert_same(array(array('g3'), array('g3'), array('g3')), array_column($layers, 'grp'), 'one group');
	pg_assert_same(array('#102030', 0, 210, 960, 540), array($layers[0]['color'], $layers[0]['x'], $layers[0]['y'], $layers[0]['w'], $layers[0]['h']), 'the colour covers the page\'s place');
	pg_assert_false(!empty($layers[1]['locked']), 'the background is unlocked: the group moves whole');
	pg_assert_same(array(0, 210, 960, 540), array($layers[1]['x'], $layers[1]['y'], $layers[1]['w'], $layers[1]['h']), 'the background fills the place');
	pg_assert_same(array(480, 480, 100, 24, 20), array($layers[2]['x'], $layers[2]['y'], $layers[2]['w'], $layers[2]['h'], $layers[2]['size']), 'the text at half: its letters too');
	pg_assert_same(1, $r['white'], 'a white page adds no rectangle');
}

// The chooser's pure parts: the pages ticked at first, a page's name, the
// amber mark, a page drawn small without its pictures, many pages as the
// scenes of one canvas, the report line's numbers and the cap on scenes.
function test_image_editor_design_chooser()
{
	$r = image_editor_design_node(
		'var pages = [{ n: 1, report: { left: [], simplified: 0 } }, { n: 3, report: { left: { chart: 1 }, simplified: 0 } },'
		. ' { n: 4, report: { left: [], simplified: 2 } }];' . "\n"
		. 'var thumb = D.designThumbDoc(M, { artboard: { width: 100, height: 50, background: "#ffffff" }, layers: ['
		. '{ id: "l0", type: "image", src: "pptx:ppt/media/image1.png", x: 1, y: 2, w: 30, h: 20, rot: 15, opacity: 1 },'
		. '{ id: "l1", type: "image", src: "/files/a.png", x: 0, y: 0, w: 10, h: 10, rot: 0, opacity: 1 }] });' . "\n"
		. 'var docs = [{ n: 1, doc: { artboard: { width: 1920, height: 1080, background: "#ff0000" }, layers: [{ id: "l0", type: "rect", x: 0, y: 0, w: 5, h: 5, rot: 0 }] } },'
		. ' { n: 2, doc: { artboard: { width: 1920, height: 1080, background: "#00ff00" }, layers: [{ id: "l0", type: "text", text: "B", size: 9, x: 0, y: 0, w: 5, h: 5, rot: 0 }] } }];' . "\n"
		. 'var scenes = D.designScenes(M, docs);' . "\n"
		. 'console.log(JSON.stringify({'
		. ' all: D.designTicks(pages, "new"), one: D.designTicks(pages, "import"), none: D.designTicks([], "new"),'
		. ' names: [D.designName("Brand deck.pptx", 2), D.designName("Brand deck.PPTX", 0), D.designName("", 3), D.designName("a/b\\\\Talk.pptx", 1)],'
		. ' partial: pages.map(D.designPartial),'
		. ' thumb: thumb.layers,'
		. ' scenes: { count: M.sceneCount(scenes), open: scenes.scene, bg: scenes.artboard.background, layers: scenes.layers.map(function (l) { return l.type; }),'
		. '  closed: { bg: scenes.scenes[1].background, layers: scenes.scenes[1].layers.map(function (l) { return l.type; }) } },'
		. ' one_scene: M.sceneCount(D.designScenes(M, [docs[0]])),'
		. ' report: D.designReport({ texts: 4, pictures: 2, shapes: 1, fonts: { "Aileron": "Roboto", "Canva Sans": "Roboto" }, left: { chart: 2, table: 1 }, simplified: 1 }),'
		. ' empty: D.designReport({ texts: 0, pictures: 0, shapes: 0, fonts: [], left: [], simplified: 0 }),'
		. ' cap: [D.designRoom(3, false, 50), D.designRoom(51, false, 50), D.designRoom(51, true, 50), D.designRoom(0, false, 50)]'
		. ' }));'
	);

	if ($r === null) {
		pg_assert_true(true, 'no Node here');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same(array(1, 3, 4), $r['all'], 'from the New image dialog every page is ticked');
	pg_assert_same(array(1), $r['one'], 'from Import one page, the first');
	pg_assert_same(array(), $r['none'], 'no page, nothing ticked');
	pg_assert_same(array('Brand deck 2', 'Brand deck', '', 'Talk 1'), $r['names'], 'named by the file and the page\'s number');
	pg_assert_same(array(false, true, true), $r['partial'], 'a page with a part left out or simplified carries the amber mark');
	pg_assert_same(array('rect', 'image'), array_column($r['thumb'], 'type'), 'a picture still in the package is a plain box in the tile');
	pg_assert_same(array(1, 2, 30, 20, 15), array($r['thumb'][0]['x'], $r['thumb'][0]['y'], $r['thumb'][0]['w'], $r['thumb'][0]['h'], $r['thumb'][0]['rot']), 'in the picture\'s place');
	pg_assert_same(2, $r['scenes']['count'], 'a scene a page');
	pg_assert_same(array('s1', '#ff0000', array('rect')), array($r['scenes']['open'], $r['scenes']['bg'], $r['scenes']['layers']), 'the first page open');
	pg_assert_same(array('#00ff00', array('text')), array($r['scenes']['closed']['bg'], $r['scenes']['closed']['layers']), 'the second page a closed scene with its board colour');
	pg_assert_same(0, $r['one_scene'], 'one page is a canvas without scenes');
	pg_assert_same(array('texts' => 4, 'pictures' => 2, 'fonts' => array(array('Aileron', 'Roboto'), array('Canva Sans', 'Roboto')),
		'left' => array(array('chart', 2), array('table', 1)), 'leftCount' => 3, 'simplified' => 1), $r['report'], 'the report line\'s numbers');
	pg_assert_same(array('texts' => 0, 'pictures' => 0, 'fonts' => array(), 'left' => array(), 'leftCount' => 0, 'simplified' => 0), $r['empty'], 'PHP\'s empty arrays read as none');
	pg_assert_same(array('', '', 'scenes', 'none'), $r['cap'], 'at most 50 scenes in one canvas; nothing ticked opens nothing');
}
