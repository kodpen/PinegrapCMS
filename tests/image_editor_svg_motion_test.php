<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for motion in a saved SVG: the numbers the model samples
 * (M.svgMotion(), under Node) and the @keyframes the server writes from
 * them (pg_image_editor_svg_motion_css()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_render_test.php';

// A still layer is left out; a fade over the first second of two runs
// from 0 to 1 and then holds (repeats merged); a typed text shows the
// share of its letters; nothing moving gives null.
function test_image_editor_svg_motion_samples()
{
	$r = image_editor_render_node(
		'var fade = { type: "rect", x: 10, y: 20, w: 100, h: 50, anim: { in: { kind: "fade", ms: 1000, delay: 0, ease: "linear" } } };' . "\n"
		. 'var typed = { type: "text", text: "Hello", x: 0, y: 0, w: 50, h: 20, anim: { in: { kind: "type", ms: 1000, delay: 0, ease: "linear" } } };' . "\n"
		. 'var doc = { motion: { duration: 2000, loop: 3 } };' . "\n"
		. 'var m = M.svgMotion(doc, [{ type: "rect", x: 0, y: 0, w: 5, h: 5 }, fade, typed]);' . "\n"
		. 'console.log(JSON.stringify({ m: m, none: M.svgMotion(doc, [{ type: "rect", x: 0, y: 0, w: 5, h: 5 }]) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(null, $r['none'], 'nothing moves');

	$m = $r['m'];
	pg_assert_same(array(2000, 3, 2), array($m['duration'], $m['loop'], count($m['layers'])), 'duration, plays, two moving layers');

	$fade = $m['layers'][0];
	pg_assert_same(array(1, 60, 45), array($fade['n'], $fade['cx'], $fade['cy']), 'its place in the file and its centre');
	pg_assert_same(array(0, 0, 0, 0, 1, 0), $fade['frames'][0], 'hidden at the start');
	pg_assert_same(array(24, 0.48, 0, 0, 1, 0), $fade['frames'][12], 'half way through the fade');
	pg_assert_same(100, $fade['frames'][count($fade['frames']) - 1][0], 'to the end');
	pg_assert_true(count($fade['frames']) < 30, 'the still second merged');

	$typed = $m['layers'][1];
	$at = null;

	foreach ($typed['frames'] as $f) {
		if ($f[0] <= 24) {
			$at = $f;
		}
	}

	pg_assert_same(0.4, $at[1], 'two of five letters shown as a share');
}

// The server's style: scoped to the root, the plays, the reduced-motion
// guard, each frame's transform about the centre; anything out of range
// or not a number refuses the whole style.
function test_image_editor_svg_motion_css()
{
	$motion = array('duration' => 2000, 'loop' => 3, 'layers' => array(
		array('n' => 1, 'cx' => 60, 'cy' => 45, 'frames' => array(array(0, 0, 0, 0, 1, 0), array(50, 0.5, 10, -2.5, 1.2, 90), array(100, 1, 0, 0, 1, 0))),
	));
	$css = pg_image_editor_svg_motion_css($motion, 'logo');

	pg_assert_true(strpos($css, '@media (prefers-reduced-motion: no-preference){') === 0, 'inside the reduced-motion guard');
	pg_assert_true(strpos($css, '#logo .pgm-1{transform-box:view-box;transform-origin:0 0;animation:logo-pgm-1 2s linear 3 both}') !== false, 'scoped, plays');
	pg_assert_true(strpos($css, '50%{opacity:0.5;transform:translate(70px,42.5px) rotate(90deg) scale(1.2) translate(-60px,-45px)}') !== false, 'a frame about the centre');

	$forever = $motion;
	$forever['loop'] = 0;
	pg_assert_true(strpos(pg_image_editor_svg_motion_css($forever, 'logo'), 'linear infinite both') !== false, 'forever');

	$bad = array(
		'a string in a frame' => array(0, '0;}*{x', 0, 0, 1, 0),
		'opacity over 1'       => array(0, 2, 0, 0, 1, 0),
		'five numbers'         => array(0, 0, 0, 0, 1),
		'not a number'         => array(0, 0, NAN, 0, 1, 0),
	);

	foreach ($bad as $why => $frame) {
		$m = $motion;
		$m['layers'][0]['frames'][0] = $frame;
		pg_assert_same('', pg_image_editor_svg_motion_css($m, 'logo'), $why);
	}

	$m = $motion;
	$m['layers'][0]['frames'][1][0] = 0;
	pg_assert_same('', pg_image_editor_svg_motion_css($m, 'logo'), 'percent not rising');
	pg_assert_same('', pg_image_editor_svg_motion_css($motion, 'a"b'), 'root id');
	pg_assert_same('', pg_image_editor_svg_motion_css(array('duration' => 100, 'layers' => $motion['layers']), 'logo'), 'duration out of range');
}

// The style goes first into the sanitized root, which gets an id when it
// has none; the file is kept as it was without motion.
function test_image_editor_svg_motion_add()
{
	if (!class_exists('DOMDocument')) {
		return;
	}

	$svg = pg_svg_sanitize('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><g class="pgm-0"><rect width="5" height="5"/></g></svg>');
	pg_assert_true(strpos($svg, 'class="pgm-0"') !== false, 'the sanitizer keeps the group\'s class');

	$motion = array('duration' => 1000, 'loop' => 0, 'layers' => array(array('n' => 0, 'cx' => 2.5, 'cy' => 2.5, 'frames' => array(array(0, 0, 0, 0, 1, 0), array(100, 1, 0, 0, 1, 0)))));
	$out = pg_image_editor_svg_motion_add($svg, $motion);

	pg_assert_same(1, preg_match('/^<svg[^>]* id="(pgsvg-[0-9a-f]{10})"[^>]*><style>@media/', $out, $m), 'id and style first');
	pg_assert_true(strpos($out, '#' . $m[1] . ' .pgm-0{') !== false, 'scoped to that id');
	pg_assert_same($svg, pg_image_editor_svg_motion_add($svg, null), 'no motion');
	pg_assert_same($svg, pg_image_editor_svg_motion_add($svg, array('duration' => 'x')), 'refused motion');
}
