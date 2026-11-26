<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Page Design Studio's page bar (includes/designer_screen.php): the menu
 * button's list picture, Undo and Redo as icon buttons between two lines,
 * the right end's order (presence, experiment chip, line, Undo, Redo, line,
 * Assistant, Preview, Publish), and Clicks as a row of the canvas bar's View
 * menu and a command palette row (assets/js/style_designer.src.js), keeping
 * the id #sd-clicks-btn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_frame_bar_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// The right end of the page bar, from its group to the closing of the bar.
function designer_frame_bar_actions()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	$start = strpos($screen, 'sd-toolbar-group sd-toolbar-actions');
	$end = ($start === false) ? false : strpos($screen, '<!-- 3-Panel Body -->', $start);

	return (($start === false) || ($end === false)) ? '' : substr($screen, $start, $end - $start);
}

function test_designer_bar_menu_button_is_a_list()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_true(preg_match('/<button[^>]*id="sd-left-toggle".*?<\/button>/', $screen, $m) === 1, 'the menu button is there');
	pg_assert_contains('bi bi-list pg-mt-shut', $m[0], 'shut: a list');
	pg_assert_contains('bi bi-x-lg pg-mt-open', $m[0], 'open: a cross');
	pg_assert_true(strpos($m[0], 'layout-sidebar') === false, 'no side panel picture');
}

function test_designer_bar_right_end_order()
{
	$bar = designer_frame_bar_actions();

	pg_assert_true($bar !== '', 'the right end is there');
	$at = array();
	foreach (array('sd-presence', 'sd-experiment-chip', 'sd-main-undo', 'sd-main-redo', 'sd-ai-btn', 'sd-preview-menu-btn', 'sd-ajax-save') as $id) {
		$at[$id] = strpos($bar, 'id="' . $id . '"');
		pg_assert_true($at[$id] !== false, $id . ' is in the right end');
	}
	pg_assert_true($at['sd-presence'] < $at['sd-experiment-chip'], 'presence, then the experiment chip');
	pg_assert_true($at['sd-experiment-chip'] < $at['sd-main-undo'], 'then Undo');
	pg_assert_true($at['sd-main-undo'] < $at['sd-main-redo'], 'then Redo');
	pg_assert_true($at['sd-main-redo'] < $at['sd-ai-btn'], 'then Assistant');
	pg_assert_true($at['sd-ai-btn'] < $at['sd-preview-menu-btn'], 'then Preview');
	pg_assert_true($at['sd-preview-menu-btn'] < $at['sd-ajax-save'], 'Publish last');

	// A line before Undo and one after Redo.
	$before = substr($bar, $at['sd-experiment-chip'], $at['sd-main-undo'] - $at['sd-experiment-chip']);
	$after = substr($bar, $at['sd-main-redo'], $at['sd-ai-btn'] - $at['sd-main-redo']);
	pg_assert_contains('class="sd-bar-line"', $before, 'a line before Undo');
	pg_assert_contains('class="sd-bar-line"', $after, 'a line after Redo');

	// Undo and Redo are icon buttons of their own size, named for a reader.
	pg_assert_true(preg_match('/<button[^>]*id="sd-main-undo"[^>]*>/', $bar, $u) === 1, 'Undo');
	pg_assert_contains('sd-icon-btn sd-history-btn', $u[0], 'Undo is an icon button');
	pg_assert_contains("aria-label=\"' . h(lang('Undo')) . '\"", $u[0], 'Undo is named');
	pg_assert_contains('disabled', $u[0], 'greyed until there is something to undo');
	pg_assert_true(preg_match('/<button[^>]*id="sd-main-redo"[^>]*>/', $bar, $r) === 1, 'Redo');
	pg_assert_contains('sd-icon-btn sd-history-btn', $r[0], 'Redo is an icon button');
	pg_assert_contains("aria-label=\"' . h(lang('Redo')) . '\"", $r[0], 'Redo is named');
	pg_assert_true(strpos($bar, 'sd-vb-btn') === false, 'no view bar button in the page bar');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	pg_assert_contains('.sd-history-btn { width: 28px; height: 28px;', $css, 'the 28 px icon button');
}

function test_designer_clicks_is_a_view_menu_row()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	// No button in the page bar any more.
	pg_assert_true(strpos($js, 'function _sdClicksPartInit(') === false, 'the bar button is no longer made');
	pg_assert_true(strpos($js, 'sd-clicks-btn-txt') === false, 'no bar label');

	// The row, in a group of its own before Side by side, for whoever gets the part.
	$canvas = designer_frame_bar_fn($js, 'createCanvasIframe');
	pg_assert_contains("(_sdClicksReady() ? '<div class=\"sd-vb-menu-line\" role=\"separator\"></div>'", $canvas, 'only for whoever gets the part');
	$row = strpos($canvas, 'id="sd-clicks-btn" role="menuitemcheckbox"');
	pg_assert_true($row !== false, 'the row keeps the id');
	pg_assert_true($row < strpos($canvas, 'id="sd-vb-side"'), 'before Side by side');
	pg_assert_true($row > strpos($canvas, 'id="sd-vb-guides"'), 'after Canvas guides');
	pg_assert_contains("esc(_sdT('Clicks on this page'))", $canvas, 'its label');
	pg_assert_contains("_sdClicksToggle(!_sdClicks.on);", $canvas, 'it switches the layer');

	$ready = designer_frame_bar_fn($js, '_sdClicksReady');
	pg_assert_contains("_design && _design.clicks && _sdPartUrls('clicks')", $ready, 'the part handed over with sdDesign.clicks');

	// On, it counts in the View button's badge.
	pg_assert_contains("'sd-clicks-btn'", designer_frame_bar_fn($js, '_sdCanvasBarSync'), 'counted in the badge');

	// The toggle marks the row as a checked menu item.
	$toggle = designer_frame_bar_fn($js, '_sdClicksToggle');
	pg_assert_contains("btn.setAttribute('aria-checked', _sdClicks.on ? 'true' : 'false');", $toggle, 'checked state');
	pg_assert_true(strpos($toggle, 'aria-pressed') === false, 'no pressed state on a menu row');

	// The command palette row.
	pg_assert_contains("id: 'act_clicks'", $js, 'a command palette row');
	pg_assert_contains("_sdClicks.on ? _sdT('Hide clicks on this page') : _sdT('Show clicks on this page')", $js, 'its label follows the state');
}
