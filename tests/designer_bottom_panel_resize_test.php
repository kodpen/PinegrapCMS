<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for dragging the top edge of the designer's bottom panel: the drag
 * keeps the pointer while it crosses the canvas iframe and always ends,
 * writes the height once a frame, and the canvas frame's own bottom handle
 * no longer lies on the panel edge.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_bottom_panel_resize_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// One function of style_designer.js, from its declaration to its closing
// brace (at the indent given).
function designer_bottom_panel_resize_test_js_function($name, $indent = '    ')
{
	$js = designer_bottom_panel_resize_test_source('assets/js/style_designer.src.js');
	$start = strpos($js, "\n" . $indent . 'function ' . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n" . $indent . "}\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + strlen($indent) + 2);
}

// The bottom panel builder's vertical resize block.
function designer_bottom_panel_resize_test_vresize_block()
{
	$js = designer_bottom_panel_resize_test_source('assets/js/style_designer.src.js');
	$start = strpos($js, '// ── Vertical resize (snap-to-collapse) ──');
	$end = ($start === false) ? false : strpos($js, '// ── Grid column splitters', $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start);
}

function test_designer_bottom_panel_drag_helper_keeps_and_releases_the_pointer()
{
	$drag = designer_bottom_panel_resize_test_js_function('_sdBpDrag', '        ');

	pg_assert_contains('setPointerCapture(', $drag, 'pointer captured');
	pg_assert_contains("pointerEvents = 'none'", $drag, 'canvas iframe stops taking the pointer');
	pg_assert_contains("'pointerup'", $drag, 'ends on pointerup');
	pg_assert_contains("'pointercancel'", $drag, 'ends on pointercancel');
	pg_assert_contains("'lostpointercapture'", $drag, 'ends on a lost capture');
	pg_assert_contains('requestAnimationFrame(', $drag, 'one write per frame');
	pg_assert_contains('cancelAnimationFrame(', $drag, 'pending frame flushed at the end');
	pg_assert_contains('iframe.style.pointerEvents = prevPe', $drag, 'iframe restored');
	pg_assert_false(strpos($drag, "'mousemove'") !== false, 'no mousemove listener');
}

function test_designer_bottom_panel_edge_and_splitters_use_the_helper()
{
	$block = designer_bottom_panel_resize_test_vresize_block();
	pg_assert_contains("vResizeHandle.addEventListener('pointerdown'", $block, 'edge starts on pointerdown');
	pg_assert_contains('_sdBpDrag(', $block, 'edge drag through the helper');
	pg_assert_false(strpos($block, "'mousemove'") !== false, 'no mousemove on the edge');
	pg_assert_false(strpos($block, "'mousedown'") !== false, 'no mousedown on the edge');

	$cols = designer_bottom_panel_resize_test_js_function('_bindColResize', '        ');
	pg_assert_contains('_sdBpDrag(', $cols, 'column splitters through the helper');
	pg_assert_false(strpos($cols, "'mousemove'") !== false, 'no mousemove on the splitters');
}

function test_designer_canvas_bottom_handles_stay_off_the_panel_edge()
{
	$css = designer_bottom_panel_resize_test_source('assets/css/style_designer.css');

	pg_assert_true((bool) preg_match('/\.sd-resize-handle-bottom \{[^}]*bottom:\s*4px;/', $css), 'bottom handle sits inside the canvas, above the edge');
	pg_assert_true((bool) preg_match('/\.sd-resize-handle-corner \{[^}]*bottom:\s*4px;/', $css), 'corner handle sits above the edge');
	pg_assert_false((bool) preg_match('/\.sd-resize-handle-(bottom|corner) \{[^}]*bottom:\s*-6px/', $css), 'no handle hangs below the canvas');
	// The panel edge owns a strip either side of the line, above the canvas.
	pg_assert_true((bool) preg_match('/\.sd-bp-vresize \{[^}]*top:\s*-4px;[^}]*height:\s*9px;[^}]*z-index:\s*20;/', $css), 'panel edge strip straddles the line');
	pg_assert_false(strpos($css, '.sd-bp-vresize:hover { height: 6px; }') !== false, 'hover does not change the strip');
	pg_assert_contains('touch-action: none', $css, 'touch drags the strip, not the page');
}

function test_designer_bottom_panel_drag_has_no_transition()
{
	$css = designer_bottom_panel_resize_test_source('assets/css/style_designer.css');

	// After the later ".collapsed { transition: height .15s }", with more
	// weight, so the snap-to-collapse preview does not animate mid-drag.
	$late = strrpos($css, '.sd-panel-bottom.collapsed { transition: height .15s ease; }');
	$wins = strrpos($css, '.sd-panel-bottom.sd-resizing.collapsed');
	pg_assert_true($late !== false && $wins !== false && $wins > $late, 'sd-resizing wins over the collapsed transition');
}
