<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's text layers: the one line layout the stage
 * and the saved overlay share (assets/js/pg_image_editor_model.js, run under
 * Node), the font list and the text labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_text_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgietext');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Every character 10 px wide: a measure the tests can count on.
function image_editor_text_measure()
{
	return 'var measure = function (font, s) { return Array.from(s).length * 10; };' . "\n";
}

function test_image_editor_text_layout_lines_and_alignment()
{
	$result = image_editor_text_node(image_editor_text_measure() . '
		var base = { text: "abcd\nab", size: 20, lineHeight: 1.5, align: "left" };
		var out = {};
		["left", "center", "right"].forEach(function (a) {
			out[a] = M.textLayout(Object.assign({}, base, { align: a }), measure);
		});
		console.log(JSON.stringify(out));');

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the layout runs: ' . (isset($result['error']) ? $result['error'] : ''));
	pg_assert_same(40, $result['left']['w'], 'the widest line sets the width');
	pg_assert_same(60, $result['left']['h'], 'two lines of 1.5 times the size');
	pg_assert_same(array(0, 0), array($result['left']['lines'][0]['x'], $result['left']['lines'][1]['x']), 'left: both lines start at the left');
	pg_assert_same(10, $result['center']['lines'][1]['x'], 'centre: the short line sits in the middle');
	pg_assert_same(20, $result['right']['lines'][1]['x'], 'right: the short line ends at the right');
	// 30 px a line, the 0.8/0.2 ascent and descent of 20 px: 5 px above.
	pg_assert_same(array(21, 51), array($result['left']['lines'][0]['base'], $result['left']['lines'][1]['base']), 'baselines in the middle of each line');
}

function test_image_editor_text_layout_spacing_and_background()
{
	$result = image_editor_text_node(image_editor_text_measure() . '
		var spaced = M.textLayout({ text: "abc", size: 10, spacing: 0.5 }, measure);
		var boxed = M.textLayout({ text: "ab", size: 10, bg: "#000", pad: 0.5, lineHeight: 1 }, measure, function () { return { ascent: 7, descent: 3 }; });
		var empty = M.textLayout({ text: "", size: 10 }, measure);
		console.log(JSON.stringify({ spaced: spaced, boxed: boxed, empty: empty }));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('a', 0), array('b', 15), array('c', 30)), $result['spaced']['lines'][0]['chars'], 'each character placed after the spacing');
	pg_assert_same(40, $result['spaced']['w'], 'no spacing after the last character');
	pg_assert_same(array(30, 20, 5), array($result['boxed']['w'], $result['boxed']['h'], $result['boxed']['pad']), 'a background adds its padding round the text');
	pg_assert_same(array(5, 12), array($result['boxed']['lines'][0]['x'], $result['boxed']['lines'][0]['base']), 'the text sits inside the padding on the font\'s baseline');
	pg_assert_same(6, $result['empty']['w'], 'an empty text keeps room for the caret');
}

function test_image_editor_text_fit_keeps_the_aligned_side()
{
	$result = image_editor_text_node('
		var layout = { w: 60, h: 30 };
		console.log(JSON.stringify(["left", "center", "right"].map(function (a) {
			return M.textFit({ x: 100, y: 50, w: 40, h: 30, align: a }, layout);
		})));');

	if ($result === null) {
		return;
	}

	pg_assert_same(100, $result[0]['x'], 'left text grows to the right');
	pg_assert_same(90, $result[1]['x'], 'centred text grows both ways');
	pg_assert_same(80, $result[2]['x'], 'right text grows to the left');
	pg_assert_same(array(50, 60, 30), array($result[1]['y'], $result[1]['w'], $result[1]['h']), 'the top stays, the box takes the layout');
}

function test_image_editor_text_resize_scales_the_letters()
{
	$result = image_editor_text_node('
		var start = { x: 0, y: 0, w: 100, h: 40, size: 20, rot: 0 };
		var turned = { x: 0, y: 0, w: 100, h: 40, size: 20, rot: 90 };
		console.log(JSON.stringify({
			corner: M.textResize(start, { x: 0, y: 0, w: 200, h: 80 }, "se"),
			side: M.textResize(start, { x: -50, y: 0, w: 150, h: 40 }, "w"),
			turned: M.textResize(turned, { x: 0, y: 0, w: 200, h: 80 }, "se"),
			tiny: M.textResize(start, { x: 0, y: 0, w: 1, h: 0.1 }, "se")
		}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 200, 'h' => 80, 'size' => 40), $result['corner'], 'a corner doubles the letters and keeps the opposite corner');
	pg_assert_same(array('x' => -50, 'y' => -10, 'w' => 150, 'h' => 60, 'size' => 30), $result['side'], 'a side scales by the width, the right side and the middle stay');
	// Turned 90 degrees, the frame's top left corner stays at (70, -30):
	// the middle moves from (50, 20) to (30, 70).
	pg_assert_same(array('x' => -70, 'y' => 30, 'w' => 200, 'h' => 80, 'size' => 40), $result['turned'], 'a turned text keeps the corner of its own frame');
	pg_assert_same(2, $result['tiny']['size'], 'the letters keep a smallest size');
}

function test_image_editor_text_fonts_and_effects()
{
	$result = image_editor_text_node('
		console.log(JSON.stringify({
			site: M.textFamily("g:Open Sans"),
			bad: M.textFamily("g:Evil\"; } body { x"),
			unknown: M.textFamily("nope") === M.TEXT_FONTS.sans,
			font: M.textFont({ size: 12.345, weight: 700, italic: true, font: "serif" }),
			fx: M.textEffects({ size: 50, bg: "#fff", radius: 0.2, outline: { color: "#000", width: 0.04 }, shadow: { color: "rgba(0,0,0,.5)", blur: 0.1, dx: 0.02, dy: 0.04 } }),
			none: M.textEffects({ size: 50, bg: "", radius: 0.2, outline: { color: "#000", width: 0 } })
		}));');

	if ($result === null) {
		return;
	}

	pg_assert_true(strpos($result['site'], '"Open Sans", ') === 0, 'a site font comes first, quoted');
	pg_assert_true(strpos($result['bad'], '"Evil body x", ') === 0, 'quotes and braces are dropped from a site font\'s family: ' . $result['bad']);
	pg_assert_true($result['unknown'], 'an unknown font is the plain sans');
	pg_assert_same(0, strpos($result['font'], 'italic 700 12.35px Georgia'), 'the canvas font string');
	pg_assert_same(array('color' => '#000', 'width' => 2), $result['fx']['outline'], 'the outline width in px');
	pg_assert_same(array('color' => 'rgba(0,0,0,.5)', 'blur' => 5, 'dx' => 1, 'dy' => 2), $result['fx']['shadow'], 'the shadow in px');
	pg_assert_same(10, $result['fx']['radius'], 'the corner radius in px');
	pg_assert_same(array(null, null, 0), array($result['none']['outline'], $result['none']['shadow'], $result['none']['radius']), 'no background, no radius; a zero outline is none');
}

function test_image_editor_text_site_families()
{
	$json = json_encode(array(
		array('family' => 'Open Sans', 'weights' => array('400', '700')),
		array('family' => 'Lobster', 'enabled' => false),
		array('family' => 'Bad"; } x {', 'weights' => array('400')),
		array('family' => ''),
		'not a font'));

	pg_assert_same(array('Open Sans', 'Bad x'), pg_image_editor_text_families($json), 'enabled families, cleaned for quoting');
	pg_assert_same(array(), pg_image_editor_text_families("https://fonts.googleapis.com/css2?family=Roboto\n"), 'the old one-URL-a-line form names none');
	pg_assert_same(array(), pg_image_editor_text_families(''), 'nothing set');
}

function test_image_editor_text_labels_cover_the_tool()
{
	$strings = pg_image_editor_strings_text();
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_text.js');

	preg_match_all("/t\\('(text[A-Za-z0-9]+|layer_text|hintText)'\\)|\\['[^']*', '(text[A-Za-z0-9]+)'\\]/", $source, $m);

	foreach (array_filter(array_merge($m[1], $m[2])) as $key) {
		pg_assert_true(isset($strings[$key]), 'the label ' . $key . ' is in pg_image_editor_strings_text()');
	}

	foreach (array(300, 400, 600, 700, 900) as $weight) {
		pg_assert_true(isset($strings['textWeight' . $weight]), 'weight ' . $weight . ' has a label');
	}
}
