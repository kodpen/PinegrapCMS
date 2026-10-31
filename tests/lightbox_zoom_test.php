<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the panel's full-screen picture viewer (pg_lightbox.js)
 * measures zoom from the fitted size: a photo larger than the screen zooms
 * in steps from its fit instead of jumping to 1:1, a zoomed picture pans in
 * every direction by drag, and the arrow keys pan a zoomed picture instead
 * of changing it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function lightbox_zoom_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_lightbox.js');
}

function lightbox_zoom_function($js, $name)
{
	$start = strpos($js, '    function ' . $name . '(');
	pg_assert_true($start !== false, $name . '() exists');
	$end = strpos($js, "\n    }\n", $start);
	return substr($js, $start, $end - $start);
}

function test_lightbox_zoom_measured_from_fit()
{
	$js = lightbox_zoom_source();

	pg_assert_true(strpos($js, 'MIN_ZOOM') === false, 'no fixed 1:1 lower bound: the fitted size is the lower bound');
	pg_assert_contains('return scale > fitScale * 1.01;', lightbox_zoom_function($js, 'isZoomed'), 'zoomed means larger than the fit, not larger than 1:1');
	pg_assert_contains('clamp(newScale, fitScale, MAX_ZOOM)', lightbox_zoom_function($js, 'zoomAt'), 'wheel, buttons and keys zoom between the fit and the upper bound');
	pg_assert_contains('clamp(startScale * ratio, fitScale, MAX_ZOOM)', $js, 'a pinch zooms between the fit and the upper bound');
	pg_assert_contains("stage.classList.toggle('pgl-zoomed',   isZoomed());", $js, 'the zoomed class follows the fit baseline');
	pg_assert_contains('zoomAt(cx, cy, fitScale * DBLCLICK_ZOOM);', $js, 'double-click zooms relative to the fit');
	pg_assert_true(preg_match('/scale\s*[<>]=?\s*1\.0?1\b/', $js) === 0, 'no test compares the scale with 1:1');
}

function test_lightbox_zoom_drag_pans_when_zoomed()
{
	$js = lightbox_zoom_source();

	pg_assert_contains("if (isZoomed()) {\n                    // Pan when zoomed", $js, 'a drag over a zoomed picture pans it');
	pg_assert_contains('if (!isZoomed() && (Math.abs(totalDx) > SWIPE_THRESHOLD', $js, 'only a drag at the fitted size flips to another picture');
}

function test_lightbox_zoom_reset_while_loading()
{
	$show = lightbox_zoom_function(lightbox_zoom_source(), 'showImageAt');

	pg_assert_contains('scale = 1; fitScale = 1; tx = 0; ty = 0;', $show, 'a picture still loading is not zoomed, so quick arrow presses keep changing pictures');
}

function test_lightbox_zoom_arrow_keys_pan()
{
	$js = lightbox_zoom_source();
	$keys = lightbox_zoom_function($js, 'onKeydown');
	pg_assert_contains("case 'ArrowDown':  onArrowKey(e); break;", $keys, 'the four arrow keys go to onArrowKey()');
	foreach (array('ArrowLeft', 'ArrowRight', 'ArrowUp') as $key) {
		pg_assert_contains("case '" . $key . "':", $keys, $key . ' is handled');
	}

	$arrow = lightbox_zoom_function($js, 'onArrowKey');
	pg_assert_contains("if (isZoomed()) {", $arrow, 'the arrow keys check for zoom first');
	pg_assert_contains('panBy(horizontal ? step : 0, horizontal ? 0 : step);', $arrow, 'the arrow keys pan a zoomed picture');
	pg_assert_contains('e.shiftKey ? KEY_PAN_STEP_BIG : KEY_PAN_STEP', $arrow, 'Shift pans a larger step');
	pg_assert_contains("} else if (horizontal) {", $arrow, 'only Left/Right change the picture, and only at the fitted size');
	pg_assert_contains('navigate(back ? -1 : +1);', $arrow, 'Left goes back, Right goes forward');

	$pan = lightbox_zoom_function($js, 'panBy');
	pg_assert_contains('clampPan();', $pan, 'keyboard panning stays inside the picture');
	pg_assert_contains('applyTransform();', $pan, 'keyboard panning is drawn');
}
