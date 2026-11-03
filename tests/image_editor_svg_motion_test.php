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
