<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The canvas guides at a canvas zoom other than 100 %: Measure, the layout
 * lines (style_designer_guides.js) and the spacing bands
 * (style_designer_bands.js) turn the canvas' rectangles and its CSS lengths
 * into the layer's pixels with two scales, read from the canvas' <html> box
 * (PGCanvasGuides.zoomScales(), PGCanvasBands.lengthScale()). Chromium
 * answers rectangles inside the zoomed canvas already zoomed, while the
 * computed margins and paddings stay in CSS pixels. Runs in Node; skipped
 * where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with the two geometry files loaded as G and C; its
// `out` comes back decoded. Null when Node cannot run.
function canvas_zoom_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_canvas_guides.js') . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_canvas_bands.js') . "\n"
		. "var G = window.PGCanvasGuides, C = window.PGCanvasBands, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgcz');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Chromium 158 at zoom 2 in a 1200 px frame: the <html> box answers 1200
// on screen for 600 CSS px, so a rectangle is already zoomed (k 1) while a
// CSS length still needs the zoom (2). A browser that answers the page's
// own pixels: 600 for 600, both scales the zoom. Fit multiplies both.
function test_canvas_zoom_guides_scales()
{
	$res = canvas_zoom_test_node('out = ['
		. ' G.zoomScales(2, 1, 1200, 600),'
		. ' G.zoomScales(2, 1, 600, 600),'
		. ' G.zoomScales(1, 1, 1200, 1200),'
		. ' G.zoomScales(1.5, 0.5, 1200, 800),'
		. ' G.zoomScales(2, 1, 0, 0),'
		. ' G.zoomScales(undefined, undefined, 1200, 1200) ];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('k' => 1, 'zoom' => 2, 'rect' => 2), $res[0], 'rectangles already zoomed: only the lengths take the zoom');
	pg_assert_same(array('k' => 2, 'zoom' => 2, 'rect' => 1), $res[1], 'rectangles in the page\'s pixels: both take the zoom');
	pg_assert_same(array('k' => 1, 'zoom' => 1, 'rect' => 1), $res[2], 'at 100 % nothing changes');
	pg_assert_same(array('k' => 0.5, 'zoom' => 0.75, 'rect' => 1.5), $res[3], 'Fit scales both');
	pg_assert_same(array('k' => 2, 'zoom' => 2, 'rect' => 1), $res[4], 'an <html> box of no width reads as the page\'s pixels');
	pg_assert_same(array('k' => 1, 'zoom' => 1, 'rect' => 1), $res[5], 'no zoom and no fit');
}

// The bands get the rectangles' scale from the core (_sdBandsGeo()); a
// margin or padding in CSS pixels takes the <html> box's ratio on top.
function test_canvas_zoom_bands_length_scale()
{
	$res = canvas_zoom_test_node('out = ['
		. ' C.lengthScale(1, 1200, 600),'
		. ' C.lengthScale(2, 600, 600),'
		. ' C.lengthScale(1, 1200, 1200),'
		. ' C.lengthScale(0.5, 1200, 800),'
		. ' C.lengthScale(1, 1200, 0),'
		. ' C.lengthScale(0, 1200, 600) ];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(2, 2, 1, 0.75, 1, 2), $res, 'CSS pixels to the layer\'s at every zoom, fit and fallback');
}

// The parts use the two scales: Measure and the lines through
// zoomScales(), the bands' widths, a drag's travel, the equal gaps and the
// columns' gutter through the length scale.
function test_canvas_zoom_parts_use_the_scales()
{
	$guides = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_guides.js');
	$bands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_bands.js');

	pg_assert_true(strpos($guides, 'G.zoomScales(') !== false, 'Measure reads its scales from zoomScales()');
	pg_assert_true(strpos($guides, 'docEl.clientWidth * cssZoom') === false, 'not from the viewport\'s width, which a zoom does not change');
	pg_assert_true(strpos($bands, 'C.lengthScale(') !== false, 'the bands read the length scale');
	pg_assert_true(strpos($bands, 'C.layout(b, read(\'margin\'), read(\'padding\'), g.z,') !== false, 'the bands are drawn with it');
	pg_assert_true(preg_match('/C\.growth\([^;]*\bk\)/', $bands) === 1 && strpos($bands, 'var k = S.g ? S.g.z : 1;') !== false, 'a drag travels with it');
	pg_assert_true(strpos($bands, 'S.g.k)') === false && strpos($bands, 'gutter * S.g.k') === false, 'no length is scaled as a rectangle');
}
