<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Design Canvas's own mark (pg_design_canvas_icon(),
 * includes/fn/output.php): the artboard with its crop marks and the pen
 * curve, drawn as the main menu's icons are but sized for the place it is
 * shown, and the places that show it: the File Manager's action and file
 * pane, the editor's heading.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_design_canvas_icon_is_the_artboard_and_the_pen_curve()
{
	$svg = pg_design_canvas_icon();

	pg_assert_contains('<svg class="pg-mi pg-mi-canvas design-color" width="1.15em" height="1.15em" viewBox="0 0 24 24"', $svg, 'the design colour, no menu margin, its own size');
	pg_assert_contains('aria-hidden="true" focusable="false"', $svg, 'a mark beside a label, not read out');
	pg_assert_same(1, substr_count($svg, 'class="pg-mi-marks"'), 'the crop marks');
	pg_assert_same(8, substr_count(preg_replace('/^.*class="pg-mi-marks" d="([^"]*)".*$/s', '$1', $svg), 'M'), 'two marks at each of the four corners');
	pg_assert_contains('<g class="pg-mi-a pg-mi-pen" style="color: light-dark(#0284c7, #38bdf8)">', $svg, 'the pen in the menu\'s sky, both themes');
	pg_assert_same(2, substr_count($svg, '<rect fill="currentColor" stroke="none"'), 'the curve\'s two anchors');
	pg_assert_contains('light-dark(color-mix(in srgb, var(--design-color-highlight) 55%, transparent), color-mix(in srgb, var(--design-color) 70%, transparent))', $svg, 'the body tinted as the menu tints a design-coloured icon');
}

function test_design_canvas_icon_takes_the_place_classes_and_size()
{
	$svg = pg_design_canvas_icon('pgie-mark', '1.25rem');

	pg_assert_contains('class="pg-mi pg-mi-canvas pgie-mark design-color" width="1.25rem" height="1.25rem"', $svg, 'the place\'s class and size');
	pg_assert_contains('class="pg-mi pg-mi-canvas a&quot;b design-color"', pg_design_canvas_icon('a"b'), 'a class is escaped');
	pg_assert_contains('width="1em&quot;" height="1em&quot;"', pg_design_canvas_icon('', '1em"'), 'a size is escaped');
}

function test_design_canvas_icon_leaves_the_menu_rows_as_they_were()
{
	$svg = pg_menu_icon('tools', 'settings-color');

	pg_assert_contains('class="pg-mi pg-mi-tools me-3 settings-color"', $svg, 'a row keeps its margin');
	pg_assert_contains('width="1.3rem" height="1.3rem"', $svg, 'and its size');
	pg_assert_contains('class="pg-mi pg-mi-canvas me-3 design-color" width="1.3rem"', pg_menu_icon('canvas', 'design-color'), 'the drawing is a menu icon like the others');
}

function test_design_canvas_icon_on_the_file_managers_action_and_file_pane()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_contains('var CANVAS_MARK = <?php echo encode_json(pg_design_canvas_icon()); ?>;', $screen, 'the mark handed to the script');
	pg_assert_contains("html += menuItem(CANVAS_MARK, L.image_editor, 'image_editor', multi);", $screen, 'the context menu\'s action');
	pg_assert_contains("html += tool('image_editor', CANVAS_MARK, L.image_editor);", $screen, 'the file pane\'s button');
	pg_assert_contains('#explorer_menu .menu-item .pg-fm-mark { width: 1.35rem;', $screen, 'the mark takes a glyph\'s slot, labels in line');
	pg_assert_false(strpos($screen, "'bi-brush', L.image_editor") !== false, 'no brush glyph left on the action');
}

function test_design_canvas_icon_in_the_editors_heading()
{
	$config = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
	$editor = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor.js');

	pg_assert_contains("'mark'    => pg_design_canvas_icon('pgie-mark', '1.25rem'),", $config, 'the config carries the mark');
	pg_assert_contains('mark.innerHTML = config().mark;', $editor, 'the editor reads it');
	pg_assert_true(strpos($editor, 'mark,') < strpos($editor, "el('div', { class: 'pgie-file' }"), 'before the file\'s name in the heading');
}
