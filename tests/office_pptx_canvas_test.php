<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design file mapper (includes/fn/office_pptx_canvas.php): a
 * slide of the design reader turned into an Artifact Canvas document; the
 * board, sizes and spacing by the census's sums, style words cut from a
 * family's name, the font lookup and its stand-ins, paragraphs broken where
 * their box ends, anchors and turns, pictures with their crops and room,
 * lines and arrows, shapes and their outlines, the report's numbers; and a
 * presentation the test writes read and mapped end to end.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/office_pptx_canvas.php');

// A design of the census's size: 1920 by 1080 pixels.
function office_pptx_canvas_test_design()
{
	return array('width' => 18288000, 'height' => 10287000);
}

// A slide of the elements given.
function office_pptx_canvas_test_slide($elements, $background = null, $left = array(), $simplified = 0)
{
	return array('n' => 1, 'hidden' => false, 'background' => $background, 'elements' => $elements, 'left' => $left, 'simplified' => $simplified);
}

// A placed element's common fields, in pixels turned into EMU.
function office_pptx_canvas_test_base($x, $y, $w, $h, $rot = 0, $flip_h = false, $flip_v = false)
{
	return array('name' => 'E', 'x' => $x * 9525, 'y' => $y * 9525, 'w' => $w * 9525, 'h' => $h * 9525, 'rot' => $rot, 'flip_h' => $flip_h, 'flip_v' => $flip_v);
}

// A text element of the paragraphs given (text => align), in a style.
function office_pptx_canvas_test_text($paragraphs, $style = array(), $extra = array(), $box = array(100, 200, 600, 100))
{
	$list = array();

	foreach ($paragraphs as $text => $align) {
		$list[] = array('text' => (string) $text, 'align' => $align, 'level' => 0, 'line' => $extra['line'] ?? null, 'before' => null, 'after' => null);
	}

	unset($extra['line']);

	return array('type' => 'text') + $extra + call_user_func_array('office_pptx_canvas_test_base', $box) + array(
		'paragraphs' => $list,
		'style'      => $style + array('font' => 'Inter', 'size' => 3600, 'bold' => false, 'italic' => false, 'underline' => false, 'color' => array('color' => '#112233', 'opacity' => 1.0), 'spacing' => 0),
		'simplified' => false,
		'inset'      => array('l' => 0, 't' => 0, 'r' => 0, 'b' => 0),
		'anchor'     => 't',
		'wrap'       => true,
		'autofit'    => 'shape',
	);
}

// A measure of 10 pixels a letter, so the breaks are plain to count.
function office_pptx_canvas_test_measure($text, $layer)
{
	return 10.0 * mb_strlen($text, 'UTF-8');
}

// The catalogue's families come out of the script the pickers ship.
function test_office_pptx_canvas_catalogue()
{
	$families = pg_pptx_canvas_catalogue();

	pg_assert_true(count($families) > 100, 'the catalogue read: ' . count($families));

	foreach (array('Inter', 'Roboto', 'Roboto Mono', 'Noto Serif', 'Caveat', 'Cormorant Garamond') as $family) {
		pg_assert_true(in_array($family, $families, true), $family . ' in the catalogue');
	}

	pg_assert_same(count($families), count(array_unique($families)), 'each family once');
	pg_assert_same(array('A', 'B C'), pg_pptx_canvas_catalogue_parse("var x = 'no'; var CATEGORIES = { sans: ['A', 'B C', 'A'] };\n var y = 'out';"), 'only the CATEGORIES block, each once');
	pg_assert_same(array(), pg_pptx_canvas_catalogue_parse('nothing'), 'no block: none');
}

// The style word is cut from the family's name into a weight and italic.
function test_office_pptx_canvas_font_name()
{
	pg_assert_same(array('family' => 'Aileron', 'weight' => 700, 'italic' => false), pg_pptx_canvas_font_name('Aileron Bold'), 'Bold');
	pg_assert_same(array('family' => 'Aileron', 'weight' => 0, 'italic' => true), pg_pptx_canvas_font_name('Aileron Italics'), 'Italics');
	pg_assert_same(array('family' => 'Cormorant Garamond', 'weight' => 0, 'italic' => true), pg_pptx_canvas_font_name('Cormorant Garamond Italics'), 'a family of two words');
	pg_assert_same(array('family' => 'Montserrat', 'weight' => 800, 'italic' => true), pg_pptx_canvas_font_name('Montserrat Extra Bold Italic'), 'Extra Bold read as one word, then Italic');
	pg_assert_same(array('family' => 'Poppins', 'weight' => 600, 'italic' => false), pg_pptx_canvas_font_name('Poppins-SemiBold'), 'a hyphen and SemiBold');
	pg_assert_same(array('family' => 'Open Sans', 'weight' => 300, 'italic' => false), pg_pptx_canvas_font_name('Open Sans Light'), 'Light');
	pg_assert_same(array('family' => 'Bold', 'weight' => 0, 'italic' => false), pg_pptx_canvas_font_name('Bold'), 'a family named only a style word keeps it');
	pg_assert_same(array('family' => 'Black Ops One', 'weight' => 0, 'italic' => false), pg_pptx_canvas_font_name('Black Ops One'), 'a style word at the start is the name');
}

// The font lookup: found by family whatever its case, bold raising the
// weight, a missing family replaced by a stand-in of its kind.
function test_office_pptx_canvas_font()
{
	$catalogue = array('Inter', 'Roboto', 'Roboto Mono', 'Noto Serif', 'Caveat', 'Cormorant Garamond');

	pg_assert_same(array('font' => 'g:Cormorant Garamond', 'weight' => 400, 'italic' => true, 'replaced' => ''), pg_pptx_canvas_font('cormorant garamond Italics', false, false, $catalogue), 'found whatever its case, italic from the name');
	pg_assert_same(array('font' => 'g:Inter', 'weight' => 700, 'italic' => false, 'replaced' => ''), pg_pptx_canvas_font('Inter', true, false, $catalogue), 'the run\'s bold is 700');
	pg_assert_same(array('font' => 'g:Inter', 'weight' => 900, 'italic' => true, 'replaced' => ''), pg_pptx_canvas_font('Inter Black', true, true, $catalogue), 'a heavier name stays over bold');
	pg_assert_same(array('font' => 'g:Inter', 'weight' => 300, 'italic' => false, 'replaced' => ''), pg_pptx_canvas_font('Inter Thin', false, false, $catalogue), 'a weight under 300 is 300');
	pg_assert_same(array('font' => 'g:Roboto', 'weight' => 700, 'italic' => false, 'replaced' => 'Aileron'), pg_pptx_canvas_font('Aileron Bold', false, false, $catalogue), 'a missing sans: Roboto, named');
	pg_assert_same('g:Noto Serif', pg_pptx_canvas_font('Libre Baskerville', false, false, $catalogue)['font'], 'a missing serif: Noto Serif');
	pg_assert_same('g:Roboto', pg_pptx_canvas_font('PT Sans Serif Thing', false, false, $catalogue)['font'], 'a sans serif stays sans');
	pg_assert_same('g:Roboto Mono', pg_pptx_canvas_font('Courier New', false, false, $catalogue)['font'], 'a missing monospace: Roboto Mono');
	pg_assert_same('g:Caveat', pg_pptx_canvas_font('Brush Script MT', false, false, $catalogue)['font'], 'a missing script: Caveat');
	pg_assert_same(array('font' => 'g:Roboto', 'weight' => 400, 'italic' => false, 'replaced' => ''), pg_pptx_canvas_font(null, false, false, $catalogue), 'no family written: Roboto, nothing named');
}

// A paragraph broken where it passes its box, between words.
function test_office_pptx_canvas_wrap()
{
	$measure = 'office_pptx_canvas_test_measure';

	pg_assert_same(array('one two', 'three'), pg_pptx_canvas_wrap('one two three', 80, array(), $measure), 'broken between words');
	pg_assert_same(array('one two three'), pg_pptx_canvas_wrap('one two three', 200, array(), $measure), 'a fitting line kept');
	pg_assert_same(array('a', 'extraordinarily', 'b'), pg_pptx_canvas_wrap('a extraordinarily b', 50, array(), $measure), 'a long word on a line of its own');
	pg_assert_same(array(''), pg_pptx_canvas_wrap('', 50, array(), $measure), 'an empty paragraph is an empty line');

	$layer = array('size' => 48, 'spacing' => 0, 'weight' => 400);
	$narrow = pg_pptx_canvas_measure('iiii', $layer);
	$wide = pg_pptx_canvas_measure('mmmm', $layer);
	pg_assert_true($narrow < $wide, 'narrow letters measure less than wide ones');
	pg_assert_true(pg_pptx_canvas_measure('abc', array('spacing' => 0.1) + $layer) > pg_pptx_canvas_measure('abc', $layer), 'letter spacing adds');
	pg_assert_true(pg_pptx_canvas_measure('abc', array('weight' => 700) + $layer) > pg_pptx_canvas_measure('abc', $layer), 'bold measures wider');
}

// A text layer: sizes by the census's sums, the font, its place.
function test_office_pptx_canvas_text()
{
	$catalogue = array('Inter', 'Roboto');
	$measure = 'office_pptx_canvas_test_measure';
	$element = office_pptx_canvas_test_text(array('Hello' => 'ctr', 'World' => 'ctr'), array('font' => 'Inter Bold', 'size' => 4800, 'spacing' => 240, 'color' => array('color' => '#ff0000', 'opacity' => 0.5)), array('line' => array('type' => 'pts', 'value' => 6000)));
	$out = pg_pptx_canvas_text($element, $catalogue, $measure);
	$layer = $out['layer'];

	pg_assert_same('text', $layer['type'], 'a text layer');
	pg_assert_same("Hello\nWorld", $layer['text'], 'a paragraph a line');
	pg_assert_same(64.0, $layer['size'], '48 pt is 64 px');
	pg_assert_same(1.25, $layer['lineHeight'], '60 pt line over 48 pt size');
	pg_assert_same(0.05, $layer['spacing'], '2.4 pt spacing over 48 pt is 0.05 em');
	pg_assert_same(array('g:Inter', 700, false), array($layer['font'], $layer['weight'], $layer['italic']), 'the family found, Bold cut into the weight');
	pg_assert_same('center', $layer['align'], 'centred');
	pg_assert_same(array('#ff0000', 0.5), array($layer['color'], $layer['opacity']), 'the colour, its alpha as the layer\'s opacity');
	pg_assert_same(array(100.0, 200.0, 600.0, 160.0, 0.0), array($layer['x'], $layer['y'], $layer['w'], $layer['h'], $layer['rot']), 'the box\'s place, two lines high');
	pg_assert_same('', $out['replaced'], 'nothing replaced');
	pg_assert_false($out['simplified'], 'nothing simplified');

	// A percent line spacing, insets, the middle anchor, a long paragraph.
	$element = office_pptx_canvas_test_text(array('aaaa bbbb cccc' => 'l'), array('size' => 1500), array('line' => array('type' => 'pct', 'value' => 1.5), 'anchor' => 'ctr', 'inset' => array('l' => 95250, 't' => 95250, 'r' => 95250, 'b' => 95250)), array(0, 0, 100, 200));
	$layer = pg_pptx_canvas_text($element, $catalogue, $measure)['layer'];
	pg_assert_same("aaaa\nbbbb\ncccc", $layer['text'], 'broken where the box\'s inner width (80 px) ends');
	pg_assert_same(1.8, $layer['lineHeight'], '150 % is 1.5 x 1.2');
	pg_assert_same(array(10.0, 46.0, 80.0, 108.0), array($layer['x'], $layer['y'], $layer['w'], $layer['h']), 'inside the insets, in the middle of the inner box (20 px x 1.8 x 3 lines)');
	pg_assert_same('left', $layer['align'], 'left');

	// A box that does not wrap keeps its paragraph whole; the bottom anchor.
	$element = office_pptx_canvas_test_text(array('aaaa bbbb cccc' => 'r'), array('size' => 1500), array('wrap' => false, 'anchor' => 'b'), array(0, 0, 50, 100));
	$layer = pg_pptx_canvas_text($element, $catalogue, $measure)['layer'];
	pg_assert_same('aaaa bbbb cccc', $layer['text'], 'not broken');
	pg_assert_same(array(76.0, 24.0, 'right'), array($layer['y'], $layer['h'], $layer['align']), 'at the foot of the box, right aligned');

	// A turned box: the text's middle turns with it about the box's middle.
	$element = office_pptx_canvas_test_text(array('ab' => 'l'), array('size' => 1500), array('anchor' => 't', 'line' => array('type' => 'pts', 'value' => 1500)), array(0, 0, 100, 100, 90));
	$layer = pg_pptx_canvas_text($element, $catalogue, $measure)['layer'];
	pg_assert_same(array(40.0, 40.0, 100.0, 20.0, 90.0), array($layer['x'], $layer['y'], $layer['w'], $layer['h'], $layer['rot']), 'the top line of a box turned a quarter lies at its right, turned');

	// Upside down, a missing family, runs that differ, alignments that differ.
	$element = office_pptx_canvas_test_text(array('One' => 'l', 'Two' => 'r'), array('font' => 'Aileron'), array('flip_v' => true, 'flip_h' => true), array(0, 0, 400, 100, 30));
	$out = pg_pptx_canvas_text($element, $catalogue, $measure);
	pg_assert_same(210.0, $out['layer']['rot'], 'flipped upside down: turned half round more');
	pg_assert_same(array('Aileron', 'g:Roboto'), array($out['replaced'], $out['layer']['font']), 'the missing family named and replaced');
	pg_assert_true($out['simplified'], 'paragraphs aligned differently are simplified');

	$element = office_pptx_canvas_test_text(array('x' => 'l'), array('underline' => true));
	pg_assert_true(pg_pptx_canvas_text($element, $catalogue, $measure)['simplified'], 'an underline the canvas lacks is simplified');

	$element = office_pptx_canvas_test_text(array('x' => 'l'), array('color' => null, 'size' => null));
	$layer = pg_pptx_canvas_text($element, $catalogue, $measure)['layer'];
	pg_assert_same(array('#000000', 1.0, 24.0), array($layer['color'], $layer['opacity'], $layer['size']), 'no colour: black; no size: 18 pt');
}

// A picture: a positive crop cut by the caller, the room of a negative
// one taken off the box, flips kept for the caller.
function test_office_pptx_canvas_picture()
{
	$element = array('type' => 'picture', 'part' => 'ppt/media/image1.jpeg', 'ext' => 'jpeg', 'crop' => array('l' => 0.1, 't' => 0.0, 'r' => 0.1, 'b' => 0.25), 'opacity' => 0.8, 'geometry' => 'rect')
		+ office_pptx_canvas_test_base(100, 100, 400, 300);
	$out = pg_pptx_canvas_picture($element);

	pg_assert_same(array('type' => 'image', 'src' => 'pptx:ppt/media/image1.jpeg', 'fileName' => 'image1.jpeg', 'x' => 100.0, 'y' => 100.0, 'w' => 400.0, 'h' => 300.0, 'rot' => 0.0, 'opacity' => 0.8), $out['layer'], 'a cut picture fills its box');
	pg_assert_same(array('part' => 'ppt/media/image1.jpeg', 'ext' => 'jpeg', 'crop' => array('l' => 0.1, 't' => 0.0, 'r' => 0.1, 'b' => 0.25), 'flip_h' => false, 'flip_v' => false), $out['picture'], 'the cut kept for the caller');

	// Room on the left: the picture fills four fifths of the box, at its right.
	$element['crop'] = array('l' => -0.25, 't' => 0.0, 'r' => 0.0, 'b' => 0.0);
	$out = pg_pptx_canvas_picture($element);
	pg_assert_same(array(180.0, 100.0, 320.0, 300.0), array($out['layer']['x'], $out['layer']['y'], $out['layer']['w'], $out['layer']['h']), 'the room taken off the box');
	pg_assert_same(array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0), $out['picture']['crop'], 'nothing to cut');

	// Flipped: the picture's left room shows on the box's right.
	$element['flip_h'] = true;
	$out = pg_pptx_canvas_picture($element);
	pg_assert_same(array(100.0, 320.0, true), array($out['layer']['x'], $out['layer']['w'], $out['picture']['flip_h']), 'mirrored room, the flip for the caller');

	// Turned half round: the room on the left lies at the right on the page.
	$element['flip_h'] = false;
	$element['rot'] = 180;
	$out = pg_pptx_canvas_picture($element);
	pg_assert_same(array(100.0, 180.0), array($out['layer']['x'], $out['layer']['rot']), 'the inner box turned about the outer one\'s middle');
}

// Lines: a plain line along its ends, an arrow with its head at the
// second end, ends swapped for a head at the first, dashes.
function test_office_pptx_canvas_line()
{
	$stroke = array('color' => '#333333', 'opacity' => 1.0, 'width' => 38100, 'dash' => '', 'head_start' => '', 'head_end' => '');
	$element = array('type' => 'line', 'x1' => 100 * 9525, 'y1' => 50 * 9525, 'x2' => 300 * 9525, 'y2' => 50 * 9525, 'stroke' => $stroke) + office_pptx_canvas_test_base(100, 50, 200, 0);
	$layer = pg_pptx_canvas_line($element);

	pg_assert_same(array('type' => 'line', 'x' => 100.0, 'y' => 48.0, 'w' => 200.0, 'h' => 4.0, 'rot' => 0.0, 'opacity' => 1.0, 'color' => '#333333', 'stroke' => 4.0), $layer, 'a 3 pt line is 4 px thick along its ends');

	$element['stroke']['head_start'] = 'triangle';
	$element['stroke']['dash'] = 'sysDot';
	$layer = pg_pptx_canvas_line($element);
	pg_assert_same(array('arrow', 'end', 'fill', 'dotted', 180.0), array($layer['type'], $layer['heads'], $layer['head'], $layer['dash'], $layer['rot']), 'a head at the first end: the ends swapped, pointing left');
	pg_assert_same(array(100.0, 200.0), array($layer['x'], $layer['w']), 'the same box along the line');
	pg_assert_true($layer['h'] > 4.0, 'an arrow\'s box is as thick as its head');

	$element['stroke']['head_end'] = 'oval';
	$element['stroke']['dash'] = 'lgDash';
	$layer = pg_pptx_canvas_line($element);
	pg_assert_same(array('both', 'dot', 'dashed', 0.0), array($layer['heads'], $layer['head'], $layer['dash'], $layer['rot']), 'heads at both ends; an oval is a dot');

	$element = array('type' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 0, 'y2' => 100 * 9525, 'stroke' => $stroke) + office_pptx_canvas_test_base(0, 0, 0, 100);
	$layer = pg_pptx_canvas_line($element);
	pg_assert_same(array(-50.0, 48.0, 100.0, 90.0), array($layer['x'], $layer['y'], $layer['w'], $layer['rot']), 'a line down is a box turned a quarter round');
}

// Shapes as drawn kinds with their fields; an outline of another colour
// as a second layer; flips.
function test_office_pptx_canvas_shape()
{
	$fill = array('color' => '#00ff00', 'opacity' => 1.0);
	$stroke = array('color' => '#0000ff', 'opacity' => 1.0, 'width' => 19050, 'dash' => 'dash', 'head_start' => '', 'head_end' => '');
	$shape = function ($geometry, $fill, $stroke, $adjust = array(), $box = array(10, 20, 200, 100)) {
		return array('type' => 'shape', 'geometry' => $geometry, 'adjust' => $adjust, 'fill' => $fill, 'stroke' => $stroke) + call_user_func_array('office_pptx_canvas_test_base', $box);
	};

	$out = pg_pptx_canvas_shape($shape('rect', $fill, null));
	pg_assert_same(array(array('type' => 'rect', 'x' => 10.0, 'y' => 20.0, 'w' => 200.0, 'h' => 100.0, 'rot' => 0.0, 'radius' => 0.0, 'opacity' => 1.0, 'color' => '#00ff00', 'fill' => 'solid', 'stroke' => 0.0)), $out['layers'], 'a filled rectangle');

	$out = pg_pptx_canvas_shape($shape('roundRect', $fill, $stroke));
	pg_assert_same(2, count($out['layers']), 'an outline of another colour is a layer of its own');
	pg_assert_same(array(16.67, 'solid', 0.0), array($out['layers'][0]['radius'], $out['layers'][0]['fill'], $out['layers'][0]['stroke']), 'the default corner (a sixth of the short side), filled');
	pg_assert_same(array('#0000ff', 'none', 2.0, 'dashed'), array($out['layers'][1]['color'], $out['layers'][1]['fill'], $out['layers'][1]['stroke'], $out['layers'][1]['dash']), 'the outline unfilled, 1.5 pt is 2 px, dashed');

	$out = pg_pptx_canvas_shape($shape('ellipse', $fill, array('color' => '#00ff00') + $fill + $stroke));
	pg_assert_same(1, count($out['layers']), 'an outline of the fill\'s colour stays on the one layer');
	pg_assert_same(array('ellipse', 2.0), array($out['layers'][0]['type'], $out['layers'][0]['stroke']), 'an ellipse with its outline');

	$out = pg_pptx_canvas_shape($shape('diamond', null, $stroke));
	pg_assert_same(array('polygon', 4, 'none'), array($out['layers'][0]['type'], $out['layers'][0]['sides'], $out['layers'][0]['fill']), 'a diamond is a four-sided polygon; an outline alone');

	$out = pg_pptx_canvas_shape($shape('hexagon', $fill, null));
	pg_assert_same(array(60.0, -30.0, 100.0, 200.0, 90.0, 6), array($out['layers'][0]['x'], $out['layers'][0]['y'], $out['layers'][0]['w'], $out['layers'][0]['h'], $out['layers'][0]['rot'], $out['layers'][0]['sides']), 'a hexagon on its flat side is turned a quarter round');

	$out = pg_pptx_canvas_shape($shape('star5', $fill, null));
	pg_assert_same(array('star', 5, 0.382), array($out['layers'][0]['type'], $out['layers'][0]['points'], $out['layers'][0]['inner']), 'a five-point star with its default inner radius');
	$out = pg_pptx_canvas_shape($shape('star8', $fill, null, array('adj' => 5000)));
	pg_assert_same(array(8, 0.2), array($out['layers'][0]['points'], $out['layers'][0]['inner']), 'an adjusted star, its inner radius at least 0.2');

	$out = pg_pptx_canvas_shape($shape('wedgeRoundRectCallout', $fill, null));
	pg_assert_same('bubble', $out['layers'][0]['type'], 'a callout is a bubble');

	$out = pg_pptx_canvas_shape($shape('rtTriangle', $fill, null));
	pg_assert_same(array('triangle', true), array($out['layers'][0]['type'], $out['simplified']), 'a right triangle drawn even, simplified');

	$out = pg_pptx_canvas_shape($shape('triangle', $fill, null, array(), array(0, 0, 100, 100, 10, true, false)));
	pg_assert_same(array(true, 10.0), array($out['layers'][0]['mirror'], $out['layers'][0]['rot']), 'flipped across: mirrored');
	$out = pg_pptx_canvas_shape($shape('triangle', $fill, null, array(), array(0, 0, 100, 100, 10, false, true)));
	pg_assert_same(array(true, 190.0), array($out['layers'][0]['mirror'], $out['layers'][0]['rot']), 'upside down: mirrored and turned half round');
	$out = pg_pptx_canvas_shape($shape('triangle', $fill, null, array(), array(0, 0, 100, 100, 0, true, true)));
	pg_assert_same(array(false, 180.0), array(isset($out['layers'][0]['mirror']), $out['layers'][0]['rot']), 'both flips: turned half round, not mirrored');
}

// A slide as a document: the board, the background, layers in paint
// order with their ids, the pictures for the caller, the report.
function test_office_pptx_canvas_slide()
{
	$catalogue = array('Inter', 'Roboto');
	$stroke = array('color' => '#333333', 'opacity' => 1.0, 'width' => 12700, 'dash' => '', 'head_start' => '', 'head_end' => '');
	$slide = office_pptx_canvas_test_slide(array(
		array('type' => 'shape', 'geometry' => 'rect', 'adjust' => array(), 'fill' => array('color' => '#eeeeee', 'opacity' => 1.0), 'stroke' => null) + office_pptx_canvas_test_base(0, 0, 100, 100),
		office_pptx_canvas_test_text(array('Title' => 'l'), array('font' => 'Aileron Bold')),
		office_pptx_canvas_test_text(array('Body' => 'l'), array('font' => 'Brush Script')) + array('simplified' => true),
		array('type' => 'picture', 'part' => 'ppt/media/image2.png', 'ext' => 'png', 'crop' => array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0), 'opacity' => 1.0, 'geometry' => 'ellipse') + office_pptx_canvas_test_base(0, 0, 50, 50),
		array('type' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 952500, 'y2' => 0, 'stroke' => $stroke) + office_pptx_canvas_test_base(0, 0, 100, 0),
	), array('type' => 'color', 'color' => '#faf7f0', 'opacity' => 1.0), array('chart' => 1, 'effect' => 2), 1);
	$slide['elements'][2]['simplified'] = true;
	$out = pg_pptx_canvas_slide(office_pptx_canvas_test_design(), $slide, array('fonts' => $catalogue, 'measure' => 'office_pptx_canvas_test_measure'));
	$doc = $out['doc'];

	pg_assert_same(array(1, null, array('width' => 1920, 'height' => 1080, 'background' => '#faf7f0')), array($doc['v'], $doc['source'], $doc['artboard']), 'a canvas of the slide\'s size and colour');
	pg_assert_same(array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null), $doc['geometry'], 'a new canvas\'s geometry');
	pg_assert_same(array(array(), 'none', null), array($doc['adjust'], $doc['filter'], $doc['frame']), 'no adjustments, filter or frame');
	pg_assert_same(array('l0', 'l1', 'l2', 'l3', 'l4'), array_column($doc['layers'], 'id'), 'ids in paint order');
	pg_assert_same(array('rect', 'text', 'text', 'image', 'line'), array_column($doc['layers'], 'type'), 'the kinds in paint order');
	pg_assert_same(array('l3'), array_keys($out['pictures']), 'the picture for the caller by its layer');
	pg_assert_same('ppt/media/image2.png', $out['pictures']['l3']['part'], 'named by its part');
	pg_assert_same(array(
		'texts'      => 2,
		'pictures'   => 1,
		'shapes'     => 2,
		'fonts'      => array('Aileron' => 'Roboto', 'Brush Script' => 'Caveat'),
		'left'       => array('chart' => 1, 'effect' => 2, 'mask' => 1),
		'simplified' => 1,
	), $out['report'], 'the report: what came in, the fonts replaced, what was left out (the reader\'s and the picture\'s mask)');

	// A picture background is a locked bottom layer over the whole board;
	// a background with none is white; the board's sides stay 16-8000.
	$slide = office_pptx_canvas_test_slide(array(), array('type' => 'picture', 'part' => 'ppt/media/bg.jpeg', 'ext' => 'jpeg', 'crop' => array('l' => 0.0, 't' => 0.1, 'r' => 0.0, 'b' => 0.1), 'opacity' => 1.0));
	$out = pg_pptx_canvas_slide(office_pptx_canvas_test_design(), $slide, array('fonts' => $catalogue));
	pg_assert_same('#ffffff', $out['doc']['artboard']['background'], 'white under a picture');
	pg_assert_same(array('l0', 'image', true, 0.0, 0.0, 1920.0, 1080.0), array($out['doc']['layers'][0]['id'], $out['doc']['layers'][0]['type'], $out['doc']['layers'][0]['locked'], $out['doc']['layers'][0]['x'], $out['doc']['layers'][0]['y'], $out['doc']['layers'][0]['w'], $out['doc']['layers'][0]['h']), 'the background picture locked over the board');
	pg_assert_same(0.1, $out['pictures']['l0']['crop']['t'], 'its cut for the caller');
	pg_assert_same(1, $out['report']['pictures'], 'counted as a picture');

	$out = pg_pptx_canvas_slide(array('width' => 100, 'height' => 999999999), office_pptx_canvas_test_slide(array()), array('fonts' => $catalogue));
	pg_assert_same(array(16, 8000, '#ffffff'), array($out['doc']['artboard']['width'], $out['doc']['artboard']['height'], $out['doc']['artboard']['background']), 'the board\'s sides kept to 16-8000, white with no background');
	pg_assert_true(pg_image_edit_validate($out['doc'])['error'] === '', 'the editor\'s validator takes the document');
}

// The reports of many pages added up.
function test_office_pptx_canvas_report()
{
	$one = array('texts' => 2, 'pictures' => 1, 'shapes' => 0, 'fonts' => array('Aileron' => 'Roboto'), 'left' => array('chart' => 1), 'simplified' => 1);
	$two = array('texts' => 3, 'pictures' => 0, 'shapes' => 4, 'fonts' => array('Aileron' => 'Roboto', 'Brush' => 'Caveat'), 'left' => array('chart' => 2, 'table' => 1), 'simplified' => 0);

	pg_assert_same(array('texts' => 5, 'pictures' => 1, 'shapes' => 4, 'fonts' => array('Aileron' => 'Roboto', 'Brush' => 'Caveat'), 'left' => array('chart' => 3, 'table' => 1), 'simplified' => 1), pg_pptx_canvas_report(array($one, $two)), 'added up, each font once');
	pg_assert_same(array('texts' => 0, 'pictures' => 0, 'shapes' => 0, 'fonts' => array(), 'left' => array(), 'simplified' => 0), pg_pptx_canvas_report(array()), 'none');
}

// A presentation the test writes, read and mapped: Canva's form of a text
// box, a picture in a scaling group and a line.
function test_office_pptx_canvas_end_to_end()
{
	if (!pg_office_ready()) {
		pg_assert_true(true, 'no zip extension here: the reader is not run');
		return;
	}

	$ns = ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
	$rels = function ($list) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

		foreach ($list as $id => $rel) {
			$xml .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $rel[0] . '" Target="' . $rel[1] . '"/>';
		}

		return $xml . '</Relationships>';
	};
	$text = '<p:sp><p:nvSpPr><p:cNvPr id="2" name="TextBox 1"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="952500" y="1905000"/><a:ext cx="9525000" cy="952500"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>'
		. '<p:txBody><a:bodyPr lIns="0" tIns="0" rIns="0" bIns="0" anchor="t"><a:spAutoFit/></a:bodyPr><a:lstStyle/>'
		. '<a:p><a:pPr algn="ctr"><a:lnSpc><a:spcPts val="7200"/></a:lnSpc></a:pPr><a:r><a:rPr lang="en-US" sz="6000" spc="300"><a:solidFill><a:srgbClr val="1A1A1A"/></a:solidFill><a:latin typeface="Cormorant Garamond Italics"/></a:rPr><a:t>Welcome home</a:t></a:r></a:p>'
		. '</p:txBody></p:sp>';
	$picture = '<p:grpSp><p:nvGrpSpPr><p:cNvPr id="3" name="Group"/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="9525000" y="0"/><a:ext cx="4762500" cy="4762500"/><a:chOff x="0" y="0"/><a:chExt cx="1000" cy="1000"/></a:xfrm></p:grpSpPr>'
		. '<p:sp><p:nvSpPr><p:cNvPr id="4" name="Picture"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="1000" cy="1000"/></a:xfrm>'
		. '<a:custGeom><a:pathLst><a:path w="1000" h="1000"><a:moveTo><a:pt x="0" y="0"/></a:moveTo><a:lnTo><a:pt x="1000" y="0"/></a:lnTo><a:lnTo><a:pt x="1000" y="1000"/></a:lnTo><a:lnTo><a:pt x="0" y="1000"/></a:lnTo><a:close/></a:path></a:pathLst></a:custGeom>'
		. '<a:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect l="0" t="-25000" r="0" b="-25000"/></a:stretch></a:blipFill></p:spPr></p:sp></p:grpSp>';
	$line = '<p:cxnSp><p:nvCxnSpPr><p:cNvPr id="5" name="Line"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr><p:spPr><a:xfrm><a:off x="952500" y="5715000"/><a:ext cx="9525000" cy="0"/></a:xfrm><a:prstGeom prst="line"><a:avLst/></a:prstGeom>'
		. '<a:ln w="19050"><a:solidFill><a:srgbClr val="C8A165"/></a:solidFill></a:ln></p:spPr></p:cxnSp>';
	$slide = '<?xml version="1.0" encoding="UTF-8"?><p:sld' . $ns . '><p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="FAF7F0"/></a:solidFill></p:bgPr></p:bg><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
		. $text . $picture . $line . '</p:spTree></p:cSld></p:sld>';
	$parts = array(
		'[Content_Types].xml'                => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
		'_rels/.rels'                        => $rels(array('rId1' => array('officeDocument', 'ppt/presentation.xml'))),
		'ppt/presentation.xml'               => '<?xml version="1.0" encoding="UTF-8"?><p:presentation' . $ns . '><p:sldIdLst><p:sldId id="256" r:id="rId10"/></p:sldIdLst><p:sldSz cx="18288000" cy="10287000"/></p:presentation>',
		'ppt/_rels/presentation.xml.rels'    => $rels(array('rId10' => array('slide', 'slides/slide1.xml'))),
		'ppt/slides/slide1.xml'              => $slide,
		'ppt/slides/_rels/slide1.xml.rels'   => $rels(array('rId2' => array('image', '../media/image1.png'))),
		'ppt/media/image1.png'               => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
	);
	$path = tempnam(sys_get_temp_dir(), 'pg_pptx_canvas_');
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::OVERWRITE);

	foreach ($parts as $name => $bytes) {
		$zip->addFromString($name, $bytes);
	}

	$zip->close();
	$read = pg_pptx_design_read($path);
	@unlink($path);

	pg_assert_true($read['ok'], 'read: ' . $read['error']);

	$out = pg_pptx_canvas_slide($read, $read['slides'][0], array('measure' => 'office_pptx_canvas_test_measure'));
	$layers = $out['doc']['layers'];

	pg_assert_same(array(1920, 1080, '#faf7f0'), array_values($out['doc']['artboard']), 'the board');
	pg_assert_same(array('text', 'image', 'line'), array_column($layers, 'type'), 'a text, a picture, a line');
	pg_assert_same(array('Welcome home', 'g:Cormorant Garamond', 400, true, 80.0, 1.2, 0.05, 'center', '#1a1a1a'),
		array($layers[0]['text'], $layers[0]['font'], $layers[0]['weight'], $layers[0]['italic'], $layers[0]['size'], $layers[0]['lineHeight'], $layers[0]['spacing'], $layers[0]['align'], $layers[0]['color']),
		'the text by the census\'s sums: 60 pt is 80 px, 72 pt lines, 3 pt spacing, the family found and its style word cut');
	pg_assert_same(array(100.0, 200.0, 1000.0, 96.0), array($layers[0]['x'], $layers[0]['y'], $layers[0]['w'], $layers[0]['h']), 'its place');
	pg_assert_same(array(1000.0, 0.0, 500.0, 500.0, 'pptx:ppt/media/image1.png'), array($layers[1]['x'], $layers[1]['y'], $layers[1]['w'], $layers[1]['h'], $layers[1]['src']), 'the picture fills its group\'s place');
	pg_assert_same(array('l' => 0.0, 't' => 0.1667, 'r' => 0.0, 'b' => 0.1667), $out['pictures']['l1']['crop'], 'Canva\'s fill rectangle as a cut for the caller');
	pg_assert_same(array(100.0, 1000.0, 2.0, '#c8a165'), array($layers[2]['x'], $layers[2]['w'], $layers[2]['stroke'], $layers[2]['color']), 'the line');
	pg_assert_same(array('texts' => 1, 'pictures' => 1, 'shapes' => 1, 'fonts' => array(), 'left' => array(), 'simplified' => 0), $out['report'], 'the report');
	pg_assert_true(pg_image_edit_validate($out['doc'])['error'] === '', 'the editor\'s validator takes the document');
}
