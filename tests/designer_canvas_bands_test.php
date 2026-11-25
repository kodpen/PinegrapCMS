<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The spacing bands of the designer's canvas guides
 * (assets/js/pg_canvas_bands.js, window.PGCanvasBands): the bands and
 * handles around a frame, a handle's growth, the arrow keys' steps, Shift
 * and Alt, the value label's place and the canvas width's screen size; and
 * the part that draws them (style_designer_bands.js), loaded on first use.
 * Runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGCanvasBands loaded as C; its `out`
// comes back decoded. Null when Node cannot run.
function canvas_bands_test_node($code)
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
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_canvas_bands.js') . "\n"
		. "var C = window.PGCanvasBands, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgcb');

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

// The drawing's card: a 200 x 120 frame at 120, 44, bottom margin 24,
// padding 16 on every side, at 100 %.
function test_canvas_bands_layout_draws_the_drawing_card()
{
	$res = canvas_bands_test_node('out = C.layout({ left: 120, top: 44, right: 320, bottom: 164 },'
		. ' { top: 0, right: 0, bottom: 24, left: 0 }, { top: 16, right: 16, bottom: 16, left: 16 }, 1, false);');

	if ($res === null) {
		return;
	}

	$outer = array();
	foreach ($res['outer'] as $o) {
		$outer[$o['side']] = $o;
	}
	$inner = array();
	foreach ($res['inner'] as $o) {
		$inner[$o['side']] = $o;
	}

	pg_assert_same(array('t', 'e', 'b', 's'), array_keys($outer), 'four outer sides in order');
	pg_assert_same(array('left' => 120, 'top' => 164, 'right' => 320, 'bottom' => 188), $outer['b']['band'], 'the bottom margin band under the frame');
	pg_assert_same(array('kind' => 'm', 'side' => 'b', 'x' => 220, 'y' => 188, 'axis' => 'x', 'faint' => false), $outer['b']['handle'], 'its handle on the band\'s outer edge');
	pg_assert_true($outer['t']['handle']['faint'], 'no top margin: a faint handle');
	pg_assert_same(44, $outer['t']['handle']['y'], 'on the frame\'s own edge');
	pg_assert_same(320, $outer['e']['handle']['x'], 'the end handle on the right edge');
	pg_assert_same('y', $outer['s']['handle']['axis'], 'a side handle stands upright');
	pg_assert_same(104, $outer['s']['handle']['y'], 'at the middle of the content height');

	pg_assert_same(array('left' => 120, 'top' => 44, 'right' => 320, 'bottom' => 60), $inner['t']['band'], 'the top padding band inside');
	pg_assert_same(60, $inner['t']['handle']['y'], 'its handle on the inner edge');
	pg_assert_same(148, $inner['b']['handle']['y'], 'the bottom one too');
	pg_assert_same(array('left' => 120, 'top' => 60, 'right' => 136, 'bottom' => 148), $inner['s']['band'], 'side bands between the top and bottom bands');
	pg_assert_same(136, $inner['s']['handle']['x'], 'the start handle on its inner edge');
	pg_assert_same(304, $inner['e']['handle']['x'], 'the end handle on its inner edge');
}

// Zoom draws the bands larger; a side with no padding gets a faint handle
// just inside the frame, apart from the faint outer one; RTL swaps start and
// end; a negative margin draws no band.
function test_canvas_bands_layout_zoom_zero_and_rtl()
{
	$res = canvas_bands_test_node('var b = { left: 100, top: 100, right: 300, bottom: 200 };'
		. ' var z = C.layout(b, { top: 8, right: 0, bottom: 0, left: -16 }, {}, 2, false);'
		. ' var r = C.layout(b, { top: 0, right: 0, bottom: 0, left: 24 }, {}, 1, true);'
		. ' out = { zt: z.outer[0].band, zs: z.outer[3], pt: z.inner[0].handle, rs: r.outer[3], re: r.outer[1] };');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('left' => 100, 'top' => 84, 'right' => 300, 'bottom' => 100), $res['zt'], '8 px at 200 % is 16 screen px');
	pg_assert_same(100, $res['zs']['band']['left'], 'a negative margin draws no band');
	pg_assert_true($res['zs']['handle']['faint'], 'and a faint handle');
	pg_assert_true($res['pt']['faint'], 'no padding: faint');
	pg_assert_same(106, $res['pt']['y'], 'the faint inner handle sits one handle inside');
	pg_assert_same('right', $res['rs']['edge'], 'RTL: the start is the right edge');
	pg_assert_true($res['rs']['handle']['faint'], 'the right edge has no margin');
	pg_assert_same('left', $res['re']['edge'], 'RTL: the end is the left edge');
	pg_assert_same(76, $res['re']['handle']['x'], 'the left margin is the end\'s');
}

// An outer handle grows outwards, an inner one inwards, in page pixels.
function test_canvas_bands_growth_by_edge_and_kind()
{
	$res = canvas_bands_test_node('out = ['
		. ' C.growth("m", "bottom", 0, 24, 1),'
		. ' C.growth("m", "top", 0, 24, 1),'
		. ' C.growth("m", "left", -10, 0, 1),'
		. ' C.growth("m", "right", 10, 0, 2),'
		. ' C.growth("p", "top", 0, 8, 1),'
		. ' C.growth("p", "right", 8, 0, 1),'
		. ' C.growth("p", "bottom", 0, 0, 1)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(24, -24, 10, 5, 8, -8, 0), $res, 'growth per edge, kind and zoom');
}

// Arrows step one scale step and stop at 0 and 5; an unknown value starts
// at 0. Shift adds the opposite side, Alt all four.
function test_canvas_bands_keys_and_reach()
{
	$res = canvas_bands_test_node('out = ['
		. ' C.keyStep("3", 1), C.keyStep("3", -1), C.keyStep("5", 1), C.keyStep("0", -1), C.keyStep("auto", 1), C.keyStep("", -1),'
		. ' C.reach("t", false, false), C.reach("b", true, false), C.reach("s", true, false), C.reach("e", true, true)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('4', '2', '5', '0', '1', '0', 'one', 'tb', 'se', 'all'), $res, 'key steps and reach');
}

// The value label goes below right of the pointer and flips at the layer's
// right or bottom edge.
function test_canvas_bands_label_flips_at_the_edge()
{
	$res = canvas_bands_test_node('out = ['
		. ' C.labelAt(100, 100, 60, 20, 400, 300),'
		. ' C.labelAt(380, 100, 60, 20, 400, 300),'
		. ' C.labelAt(100, 290, 60, 20, 400, 300),'
		. ' C.labelAt(5, 5, 60, 20, 50, 20)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('x' => 112, 'y' => 112), $res[0], 'below right');
	pg_assert_same(array('x' => 308, 'y' => 112), $res[1], 'flipped left at the right edge');
	pg_assert_same(array('x' => 112, 'y' => 258), $res[2], 'flipped up at the bottom edge');
	pg_assert_same(array('x' => 0, 'y' => 0), $res[3], 'kept inside a small layer');
}

// The canvas width names Bootstrap's screen size, as the bar's width text.
function test_canvas_bands_size_for_width()
{
	$res = canvas_bands_test_node('out = [375, 575, 576, 768, 991, 992, 1200, 1399, 1400, 1920].map(C.sizeFor);');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('xs', 'xs', 'sm', 'md', 'md', 'lg', 'xl', 'xl', 'xxl', 'xxl'), $res, 'sizes by width');
}

// The scale is Bootstrap 5.3's spacers; the nearest step takes the smaller
// on a tie.
function test_canvas_bands_scale_and_nearest_step()
{
	$res = canvas_bands_test_node('var sc = C.scale(16); out = [sc, C.scale(0)[5], C.nearestStep(48, sc), C.nearestStep(6, sc), C.nearestStep(-5, sc), C.nearestStep(100, sc)];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(0, 4, 8, 16, 24, 48), $res[0], 'the six steps at 16 px');
	pg_assert_same(48, $res[1], 'no root size reads as 16');
	pg_assert_same(array('step' => '5', 'px' => 48), $res[2], 'exact step');
	pg_assert_same('1', $res[3]['step'], 'a tie takes the smaller');
	pg_assert_same('0', $res[4]['step'], 'below 0 is 0');
	pg_assert_same('5', $res[5]['step'], 'above the scale is its top');
}

// The bands are a part of their own: the modules first, the part last; the
// core only asks it to draw or hide, handing it a host.
function test_canvas_bands_are_a_part()
{
	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_same('assets/js/style_designer_bands.min.js', isset($parts['bands']) ? $parts['bands'] : null, 'bands is a part');
	$files = $bundles['assets/js/style_designer_bands.min.js'];
	pg_assert_true(in_array('assets/js/pg_canvas_bands.js', $files, true), 'the band module is in it');
	pg_assert_false(in_array('assets/js/pg_spacing_box.js', $files, true), 'what the Spacing box writes comes with its own part');
	pg_assert_false(in_array('assets/js/pg_canvas_guides.js', $files, true), 'the guides\' geometry belongs to the Measure part');
	pg_assert_same('assets/js/style_designer_bands.js', end($files), 'the part last');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'bands'", $screen, 'the screen hands the part over');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdPartCall('bands', 'draw', [_sdBandsHost(node)])", $core, 'the core draws through the part');
	pg_assert_contains("_sdPartLoad('spacing').then(", $core, 'after the Spacing box\'s part, which brings PGSpacingBox');
	pg_assert_contains("_sdPartApis.bands.hide()", $core, 'and hides it once loaded');
}
