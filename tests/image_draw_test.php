<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the server's drawing of an image editor document
 * (includes/fn/image_draw.php): colours and numbers of a document, the
 * frame of a layer, the SVG a layer is read as and what of it is drawn, the
 * text checks and layout, the outlines of drawn shapes, and every reason a
 * document is left to a browser. Drawing itself is tested only where
 * Imagick is loaded.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php');

/**
 * What a server that draws every part answers.
 *
 * @return array
 */
function image_draw_test_can()
{
	return array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png', 'jpeg', 'webp'), 'paused' => 0, 'engine' => 'test');
}

/**
 * A document of one layer on a 100 x 60 artboard.
 *
 * @param array $layer
 * @return array
 */
function image_draw_test_doc($layer)
{
	return array('artboard' => array('width' => 100, 'height' => 60, 'background' => '#ffffff'), 'layers' => array($layer));
}

/**
 * Whether two lists of numbers agree to a thousandth.
 *
 * @param float[] $expected
 * @param float[] $actual
 * @return bool
 */
function image_draw_test_near($expected, $actual)
{
	if (count($expected) !== count($actual)) {
		return false;
	}

	foreach (array_values($expected) as $i => $value) {
		if (abs($value - array_values($actual)[$i]) > 0.001) {
			return false;
		}
	}

	return true;
}

function test_image_draw_color()
{
	pg_assert_same(array(170, 187, 204), pg_image_draw_color('#abc'), 'three digits');
	pg_assert_same(array(255, 0, 16), pg_image_draw_color('#FF0010'), 'six digits, upper case');
	pg_assert_same(null, pg_image_draw_color('red'), 'a name');
	pg_assert_same(null, pg_image_draw_color('#abcd'), 'four digits');
	pg_assert_same(null, pg_image_draw_color('rgb(0,0,0)'), 'a function');
	pg_assert_same(null, pg_image_draw_color(255), 'a number');
}

function test_image_draw_number()
{
	pg_assert_same(5.0, pg_image_draw_number(array('x' => 5), 'x', 0, 10), 'inside');
	pg_assert_same(2.5, pg_image_draw_number(array(), 'x', 0, 10, 2.5), 'missing, default');
	pg_assert_same(null, pg_image_draw_number(array(), 'x', 0, 10), 'missing, no default');
	pg_assert_same(null, pg_image_draw_number(array('x' => 11), 'x', 0, 10), 'outside');
	pg_assert_same(null, pg_image_draw_number(array('x' => '5'), 'x', 0, 10), 'a string');
	pg_assert_same(null, pg_image_draw_number(array('x' => INF), 'x', -INF, INF), 'not finite');
}

// The editor's svgMatrix(): turned around the box's middle, mirrored inside
// it, and a view box drawn into the box.
function test_image_draw_matrix()
{
	$box = array('x' => 4, 'y' => 6, 'w' => 20, 'h' => 10, 'rot' => 0, 'mirror' => false);

	pg_assert_true(image_draw_test_near(array(1, 0, 0, 1, 4, 6), pg_image_draw_matrix($box)), 'upright');
	pg_assert_true(image_draw_test_near(array(0, 1, -1, 0, 19, 1), pg_image_draw_matrix(array('rot' => 90) + $box)), 'a quarter turn');
	pg_assert_true(image_draw_test_near(array(-1, 0, 0, 1, 24, 6), pg_image_draw_matrix(array('mirror' => true) + $box)), 'mirrored');
	pg_assert_true(image_draw_test_near(array(2, 0, 0, 0.5, 0, 5), pg_image_draw_matrix($box, array('x' => 2, 'y' => 2, 'w' => 10, 'h' => 20))), 'a view box');
}

function test_image_draw_bounds_and_region()
{
	$turned = array('x' => 0, 'y' => 0, 'w' => 20, 'h' => 10, 'rot' => 90);

	pg_assert_true(image_draw_test_near(array(5, -5, 15, 15), pg_image_draw_bounds($turned)), 'turned bounds');
	pg_assert_same(array(5, 0, 10, 15), pg_image_draw_region($turned, 100, 100), 'cut to the artboard');
	pg_assert_same(array(1, 2, 4, 3), pg_image_draw_region(array('x' => 1.5, 'y' => 2.2, 'w' => 3, 'h' => 2.6, 'rot' => 0), 100, 100), 'every pixel it touches');
	pg_assert_same(null, pg_image_draw_region(array('x' => 200, 'y' => 0, 'w' => 10, 'h' => 10, 'rot' => 0), 100, 100), 'outside');
}

function test_image_draw_text_plain()
{
	pg_assert_true(pg_image_draw_text_plain("Merhaba dünya, ışık\nŞEKİL €5 → ×"), 'Turkish, signs, two lines');
	pg_assert_true(pg_image_draw_text_plain('Привет Ελληνικά'), 'Cyrillic and Greek');
	pg_assert_false(pg_image_draw_text_plain('مرحبا'), 'right to left');
	pg_assert_false(pg_image_draw_text_plain('中文'), 'Han');
	pg_assert_false(pg_image_draw_text_plain("e\u{301}"), 'a combining mark');
	pg_assert_false(pg_image_draw_text_plain("\u{1F600}"), 'an emoji');
	pg_assert_false(pg_image_draw_text_plain("a\tb"), 'a tab');
	pg_assert_false(pg_image_draw_text_plain(5), 'no string');
}

// Only what the fixture set covers is drawn; the rest waits for a browser.
function test_image_draw_svg_plain()
{
	$wrap = function ($inner) {
		return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">' . $inner . '</svg>';
	};

	pg_assert_true(pg_image_draw_svg_plain($wrap('<defs><linearGradient id="a"><stop offset="0"/></linearGradient><clipPath id="c"><rect width="5" height="5"/></clipPath></defs>'
		. '<g clip-path="url(#c)"><path d="M0 0H5" fill="url(#a)"/><use xlink:href="#a"/></g><style>#x .y, g > rect{fill:#000}</style>'), 4000), 'shapes, gradient, clip, use, plain style');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<text>a</text>'), 4000), 'text');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<image href="data:image/png;base64,AA=="/>'), 4000), 'a picture');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<rect width="5" height="5" filter="url(#f)"/>'), 4000), 'a filter attribute');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<rect width="5" height="5" style="fill:red;mix-blend-mode:multiply"/>'), 4000), 'a blend mode in style');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<rect width="5" height="5" fill="var(--a)"/>'), 4000), 'a CSS function');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<style>@import url(a.css);</style>'), 4000), 'an at-rule');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<style>rect:hover{fill:red}</style>'), 4000), 'a pseudo-class');
	pg_assert_false(pg_image_draw_svg_plain($wrap('<style>rect{mask:url(#m)}</style>'), 4000), 'a mask in a rule');
	pg_assert_false(pg_image_draw_svg_plain($wrap(str_repeat('<g/>', 10)), 5), 'too many elements');
	pg_assert_false(pg_image_draw_svg_plain('<svg', 4000), 'not XML');
}

// The layer's own box as a clip and its matrix, and only markup the
// sanitizer passes.
function test_image_draw_svg_document()
{
	$layer = array('x' => 10, 'y' => 20, 'w' => 40, 'h' => 20, 'rot' => 0, 'mirror' => false, 'vb' => array('x' => 0, 'y' => 0, 'w' => 20, 'h' => 10),
		'markup' => '<script>alert(1)</script><rect width="5" height="5" onload="alert(1)" fill="#ff0000"/>');
	$region = pg_image_draw_region($layer, 100, 60);
	$svg = pg_svg_sanitize(pg_image_draw_svg_document($layer, $region, '', 'pgd'));

	pg_assert_same(array(10, 20, 40, 20), $region, 'the region');
	pg_assert_contains('viewBox="10 20 40 20"', $svg, 'the region is the view');
	pg_assert_contains('<clipPath id="pgdraw-box"><rect x="0" y="0" width="20" height="10"/></clipPath>', $svg, 'clipped to its own box');
	pg_assert_contains('transform="matrix(2 0 0 2 10 20)"', $svg, 'its matrix');
	pg_assert_contains('clip-path="url(#pgdraw-box)"', $svg, 'the clip survives the sanitizer');
	pg_assert_contains('<rect width="5" height="5" fill="#ff0000"/>', $svg, 'the shape survives');
	pg_assert_false(stripos($svg, 'script') !== false || stripos($svg, 'onload') !== false, 'script and handlers are gone');
	pg_assert_true(pg_image_draw_svg_plain($svg, 4000), 'plain');
}

function test_image_draw_font_file()
{
	pg_assert_contains('/assets/fonts/roboto/Roboto-Regular.ttf', pg_image_draw_font_file('g:Roboto', 400, false), 'bundled regular');
	pg_assert_contains('/assets/fonts/roboto/Roboto-Italic.ttf', pg_image_draw_font_file('g:Roboto', 400, true), 'bundled italic');
	pg_assert_same('', pg_image_draw_font_file('g:Roboto', 900, true), 'italic 900 is refused');
	pg_assert_same('', pg_image_draw_font_file('g:Roboto', 500, false), 'a weight the font link does not load');
	pg_assert_same('', pg_image_draw_font_file('sans', 400, false), 'a system font list');
	pg_assert_same('', pg_image_draw_font_file('g:No Such Family', 400, false), 'no file');
	pg_assert_same('', pg_image_draw_font_file(array('g:Roboto'), 400, false), 'no string');
}

function test_image_draw_font_read_and_fits()
{
	$path = pg_image_draw_font_file('g:Roboto', 400, false);
	$font = pg_image_draw_font_read($path);

	pg_assert_true(is_array($font), 'read');
	pg_assert_same(2048, $font['upem'], 'units per em');
	pg_assert_same(400, $font['weight'], 'weight class');
	pg_assert_false($font['italic'], 'upright');
	pg_assert_true(($font['ascent'] > 0) && ($font['descent'] > 0), 'ascent and descent');
	pg_assert_true(pg_image_draw_font_advance($font, 'W') > pg_image_draw_font_advance($font, 'l'), 'W is wider than l');
	pg_assert_same(pg_image_draw_font_advance($font, 'ş') > 0, true, 'a Turkish letter');
	pg_assert_same(null, pg_image_draw_font_advance($font, '中'), 'no glyph');

	pg_assert_true(pg_image_draw_font_fits($path, 400, false, "Şekil\nçizim"), 'fits');
	pg_assert_false(pg_image_draw_font_fits($path, 700, false, 'a'), 'another weight');
	pg_assert_false(pg_image_draw_font_fits($path, 400, true, 'a'), 'italic');
	pg_assert_false(pg_image_draw_font_fits($path, 400, false, 'a中'), 'a letter it lacks');
	pg_assert_same(null, pg_image_draw_font_read(__FILE__), 'not a font');
}

// The editor's textLayout(): line height, padding with a background, the
// baseline between ascent and descent, spacing after all but the last letter.
function test_image_draw_text_layout()
{
	$ten = function ($letter) {
		return 10;
	};
	$l = array('text' => "ab\nc", 'size' => 20, 'lineHeight' => 1.5, 'spacing' => 0.1, 'pad' => 0.5, 'align' => 'center', 'bg' => array(0, 0, 0));
	$layout = pg_image_draw_text_layout($l, $ten, 16, 4);

	pg_assert_same(42.0, (float) $layout['w'], 'width with padding');
	pg_assert_same(80.0, (float) $layout['h'], 'height with padding');
	pg_assert_same(31.0, (float) $layout['lines'][0]['base'], 'first baseline');
	pg_assert_same(61.0, (float) $layout['lines'][1]['base'], 'second baseline');
	pg_assert_same(22.0, (float) $layout['lines'][0]['width'], 'spacing between letters only');
	pg_assert_same(16.0, (float) $layout['lines'][1]['x'], 'centred');
	pg_assert_same(array(array('a', 0.0), array('b', 12.0)), $layout['lines'][0]['chars'], 'letters placed one by one');

	$plain = pg_image_draw_text_layout(array('bg' => array(), 'align' => 'right') + $l, $ten, 0, -1);

	pg_assert_same(22.0, (float) $plain['w'], 'no padding without a background');
	pg_assert_same(12.0, (float) $plain['lines'][1]['x'], 'right');
	pg_assert_same(21.0, (float) $plain['lines'][0]['base'], 'ascent and descent from the size without metrics');

	$empty = pg_image_draw_text_layout(array('text' => '') + $l, $ten, 16, 4);

	pg_assert_same(32.0, (float) $empty['w'], 'room for the caret');
}

// The editor's paths: a rectangle from its top edge clockwise, an ellipse
// from its left end down first, a line along the middle.
function test_image_draw_shape_points()
{
	$square = pg_image_draw_shape_points('rect', 10, 6);

	pg_assert_same(array(array(0.0, 0.0), array(10.0, 0.0), array(10.0, 6.0), array(0.0, 6.0), array(0.0, 0.0)), array_map(function ($p) {
		return array((float) $p[0], (float) $p[1]);
	}, $square), 'a square corner rectangle');

	$inset = pg_image_draw_shape_points('rect', 10, 6, 0, 1);

	pg_assert_true(image_draw_test_near(array(1, 1, 9, 1), array($inset[0][0], $inset[0][1], $inset[1][0], $inset[1][1])), 'inset');

	$round = pg_image_draw_shape_points('rect', 20, 10, 4);
	$last = end($round);

	pg_assert_true(image_draw_test_near(array(4, 0), $round[0]), 'rounded: starts after the corner');
	pg_assert_true(($round[1][0] > $round[0][0]) && (abs($round[1][1]) < 0.001), 'rounded: runs right first');
	pg_assert_true(image_draw_test_near($round[0], $last), 'rounded: closed');

	$ellipse = pg_image_draw_shape_points('ellipse', 20, 10);

	pg_assert_true(image_draw_test_near(array(0, 5), $ellipse[0]), 'ellipse: from its left end');
	pg_assert_true($ellipse[1][1] > 5, 'ellipse: down first');
	pg_assert_true(image_draw_test_near($ellipse[0], end($ellipse)), 'ellipse: closed');

	$line = pg_image_draw_shape_points('line', 10, 6);

	pg_assert_true(image_draw_test_near(array(0, 3, 10, 3), array($line[0][0], $line[0][1], $line[1][0], $line[1][1])), 'line');
	pg_assert_same(array(), pg_image_draw_shape_points('rect', 4, 4, 0, 3), 'inset past its middle');
	pg_assert_true(pg_image_draw_arc_steps(1000, 2 * M_PI) <= 720, 'pieces are limited');
}

function test_image_draw_dots()
{
	$dots = pg_image_draw_dots(array(array(0, 0), array(10, 0), array(10, 10)), 4);

	pg_assert_same(5, count($dots), 'one every period, around the corner');
	pg_assert_true(image_draw_test_near(array(0.005, 0), $dots[0]), 'the first at the start');
	pg_assert_true(image_draw_test_near(array(10, 2.005), $dots[3]), 'past the corner');
}

function test_image_draw_known()
{
	pg_assert_true(pg_image_draw_known(array('id' => 'a', 'type' => 'rect', 'color' => '#000'), array('color')), 'own fields');
	pg_assert_true(pg_image_draw_known(array('type' => 'rect', 'shadow' => null, 'warp' => array()), array()), 'empty fields of others');
	pg_assert_false(pg_image_draw_known(array('type' => 'rect', 'shadow' => array('blur' => 4)), array()), 'a shadow');
}

// Every document the server cannot draw as the editor does is refused with
// its reason, and waits for a browser.
function test_image_draw_plan_refusals()
{
	$can = image_draw_test_can();
	$rect = array('type' => 'rect', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10, 'color' => '#ff0000', 'fill' => 'solid');
	$text = array('type' => 'text', 'text' => 'Merhaba', 'font' => 'g:Roboto', 'weight' => 400, 'size' => 20, 'color' => '#000000', 'x' => 1, 'y' => 1, 'w' => 1, 'h' => 1);
	$svg = array('type' => 'svg', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'vb' => array('x' => 0, 'y' => 0, 'w' => 10, 'h' => 10), 'markup' => '<rect width="5" height="5"/>');
	$image = array('type' => 'image', 'src' => '/f/a.png', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10);
	$error = function ($doc, $can_now = null, $picture = null) use ($can) {
		return pg_image_draw_plan($doc, ($can_now === null) ? $can : $can_now, $picture)['error'];
	};

	pg_assert_same('board', $error(array('artboard' => array('width' => 3000, 'height' => 3000), 'layers' => array())), 'too many pixels');
	pg_assert_same('board', $error(array('artboard' => array('width' => 10.5, 'height' => 10), 'layers' => array())), 'a fraction');
	pg_assert_same('board', $error(array('artboard' => array('width' => 10, 'height' => 10, 'background' => 'url(x)'), 'layers' => array())), 'a background that is no colour');
	pg_assert_same('board', $error(null), 'no document');
	pg_assert_same('layers', $error(array('artboard' => array('width' => 10, 'height' => 10), 'layers' => array_fill(0, 151, $rect))), 'too many layers');
	pg_assert_same('layers', $error(array('artboard' => array('width' => 10, 'height' => 10), 'svgRootId' => 'a"b', 'layers' => array())), 'a root id that is no name');

	pg_assert_same('kind', $error(image_draw_test_doc(array('type' => 'brush') + $rect)), 'an unknown layer');
	pg_assert_same('kind', $error(image_draw_test_doc(array('shadow' => array('blur' => 4)) + $rect)), 'a shadow');
	pg_assert_same('kind', $error(image_draw_test_doc(array('x' => 'a') + $rect)), 'a position that is no number');
	pg_assert_same('kind', $error(image_draw_test_doc(array('color' => 'red') + $rect)), 'a colour by name');
	pg_assert_same('kind', $error(image_draw_test_doc(array('type' => 'glyph', 'set' => 'bi', 'code' => 1, 'color' => '#000000', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10))), 'no icon');
	pg_assert_same('kind', $error(image_draw_test_doc(array('type' => 'glyph', 'set' => 'fa', 'code' => 62087, 'color' => '#000000', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10))), 'another icon set');

	pg_assert_same('font', $error(image_draw_test_doc(array('font' => 'sans') + $text)), 'a system font');
	pg_assert_same('font', $error(image_draw_test_doc(array('weight' => 500) + $text)), 'a weight with no face');
	pg_assert_same('font', $error(image_draw_test_doc(array('text' => 'a → b') + $text)), 'a letter the font lacks');
	pg_assert_same('text', $error(image_draw_test_doc(array('text' => 'سلام') + $text)), 'a shaped script');
	pg_assert_same('text', $error(image_draw_test_doc(array('warp' => array('arc' => 30)) + $text)), 'a warp');
	pg_assert_same('text', $error(image_draw_test_doc($text), array('text' => false) + $can), 'text left to browsers');

	pg_assert_same('svg', $error(image_draw_test_doc($svg), array('svg' => false) + $can), 'svg left to browsers');
	pg_assert_same('svg', $error(image_draw_test_doc(array('markup' => '<text>a</text>') + $svg)), 'text in SVG');
	pg_assert_same('svg', $error(image_draw_test_doc(array('markup' => '<rect width="5" height="5" filter="url(#f)"/>') + $svg)), 'a filter in SVG');
	pg_assert_same('svg', $error(image_draw_test_doc(array('vb' => array('x' => 0, 'y' => 0, 'w' => 0, 'h' => 10)) + $svg)), 'an empty view box');

	pg_assert_same('picture', $error(image_draw_test_doc($image)), 'no files are answered for');
	pg_assert_same('picture', $error(image_draw_test_doc($image), null, function () {
		return '';
	}), 'a file the readers may not see');
	pg_assert_same('picture', $error(image_draw_test_doc($image), null, function () {
		return __FILE__;
	}), 'not a picture');
	pg_assert_same('picture', $error(image_draw_test_doc($image), array('pictures' => array('jpeg')) + $can, function () {
		return PG_FUNCTIONS_DIR . '/assets/images/icon_notice.png';
	}), 'a format this server does not read');
	pg_assert_same('picture', $error(image_draw_test_doc(array('crop' => array('x' => 1)) + $image), null, function () {
		return PG_FUNCTIONS_DIR . '/assets/images/icon_notice.png';
	}), 'a crop');

	$late = pg_image_draw_plan(image_draw_test_doc($rect), $can, null, microtime(true) - 1);

	pg_assert_same('time', $late['error'], 'past the budget');
}

function test_image_draw_plan_items()
{
	$doc = array('artboard' => array('width' => 100, 'height' => 60, 'background' => '#FFFFFF'), 'layers' => array(
		array('type' => 'rect', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10, 'color' => '#ff0000', 'fill' => 'soft', 'radius' => 2, 'dash' => 'dashed', 'stroke' => 2),
		array('type' => 'rect', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10, 'hidden' => true, 'shadow' => array('blur' => 4)),
		array('type' => 'ellipse', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10, 'opacity' => 0),
		array('type' => 'text', 'text' => 'Şekil', 'font' => 'g:Roboto', 'weight' => 700, 'size' => 20, 'color' => '#000000', 'x' => 1, 'y' => 1, 'w' => 1, 'h' => 1),
		array('type' => 'glyph', 'set' => 'bi', 'code' => 62087, 'color' => '#00ff00', 'x' => 30, 'y' => 10, 'w' => 40, 'h' => 40),
		array('type' => 'image', 'src' => '/f/a.png', 'fileId' => 4, 'fileName' => 'a.png', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10)));
	$asked = array();
	$plan = pg_image_draw_plan(json_decode(json_encode($doc)), image_draw_test_can(), function ($src) use (&$asked) {
		$asked[] = $src;

		return PG_FUNCTIONS_DIR . '/assets/images/icon_notice.png';
	});

	pg_assert_same('', $plan['error'], 'drawn, objects read as arrays');
	pg_assert_same('#ffffff', $plan['background'], 'the background');
	pg_assert_same(array('rect', 'text', 'glyph', 'image'), array_column($plan['items'], 'kind'), 'hidden and clear layers are left out');
	pg_assert_same(array('/f/a.png'), $asked, 'the picture is asked for by its address only');
	pg_assert_same('png', $plan['items'][3]['format'], 'its format from its bytes');
	pg_assert_contains('Roboto-Bold.ttf', $plan['items'][1]['file'], 'the face of its weight');
	pg_assert_same('Şekil', implode('', array_column($plan['items'][1]['layout']['lines'][0]['chars'], 0)), 'laid out letter by letter');

	$clear = pg_image_draw_plan(array('artboard' => array('width' => 10, 'height' => 10, 'background' => 'transparent'), 'layers' => array()), image_draw_test_can());

	pg_assert_same('', $clear['background'], 'a transparent artboard');
}

// The probe's own documents are ones the renderer takes: the sanitizer and
// the plain SVG check keep the whole fixture.
function test_image_draw_fixtures_plan()
{
	$fixtures = pg_image_draw_fixtures();

	foreach (array('shapes', 'text', 'icons', 'svg') as $name) {
		pg_assert_same('', pg_image_draw_plan($fixtures[$name]['doc'], image_draw_test_can())['error'], $name);
	}

	$picture = pg_image_draw_plan($fixtures['picture']['doc'], image_draw_test_can(), function () {
		return PG_FUNCTIONS_DIR . '/assets/images/icon_notice.png';
	});

	pg_assert_same('', $picture['error'], 'picture');

	$svg = pg_image_draw_plan($fixtures['svg']['doc'], image_draw_test_can());

	foreach (array('pgd-a', 'pgd-c', 'pgd-d', 'pgd-e', 'pgd-f', 'pgdraw-box') as $name) {
		pg_assert_contains($name, $svg['items'][0]['svg'], $name . ' is kept');
	}
}

// Where Imagick is loaded: a drawing is the artboard's size, and the probe
// answers every part.
function test_image_draw_paint_with_imagick()
{
	if (!class_exists('Imagick')) {
		pg_assert_true(true, 'Imagick is not loaded here');

		return;
	}

	$fixtures = pg_image_draw_fixtures();
	$png = pg_image_draw_paint(pg_image_draw_plan($fixtures['shapes']['doc'], image_draw_test_can()));
	$size = getimagesizefromstring($png);

	pg_assert_same(array(100, 60, IMAGETYPE_PNG), array($size[0], $size[1], $size[2]), 'the artboard\'s size');

	$probe = pg_image_draw_guarded('pg_image_draw_probe');

	pg_assert_same(array('engine', 'icons', 'pictures', 'shapes', 'svg', 'text'), call_user_func(function ($keys) {
		sort($keys);

		return $keys;
	}, array_keys($probe)), 'the probe\'s answer');
	pg_assert_true(!$probe['svg'] || in_array('RSVG', Imagick::queryFormats('RSVG'), true), 'SVG only through librsvg');
}
