<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Design Canvas's own icon: its drawing in pg_menu_icon()
 * (includes/fn/output.php), pg_design_canvas_icon() (includes/fn/
 * image_editor.php) and the places that show it: the File Manager, the
 * editor's top bar and Workspace's New canvas.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_design_canvas_icon_is_the_artboard_with_the_pen_curve()
{
	$svg = pg_design_canvas_icon();

	pg_assert_contains('class="pg-mi pg-mi-design_canvas design-color pg-dc-icon"', $svg, 'no margin class, the design colour and the outside-menu class');
	pg_assert_contains('<g class="pg-mi-a pg-mi-pen">', $svg, 'the pen curve is the second colour');
	pg_assert_contains('M8.3 15.7C9 11 15 13 15.7 8.3', $svg, 'the pen curve');
	pg_assert_same(4, substr_count($svg, 'width="2" height="2"'), 'two anchors, each a tint and an outline');
	pg_assert_contains('aria-hidden="true"', $svg, 'decoration beside its name');
}

function test_design_canvas_icon_takes_a_margin_class()
{
	pg_assert_contains('class="pg-mi pg-mi-design_canvas me-1 design-color', pg_design_canvas_icon('me-1'), 'the margin given');
}

function test_menu_icons_keep_their_menu_margin()
{
	pg_assert_contains('class="pg-mi pg-mi-notes me-3 notes-color"', pg_menu_icon('notes', 'notes-color'), 'the menu rows keep me-3');
}

function test_design_canvas_icon_reaches_its_places()
{
	$config = pg_image_editor_config();

	pg_assert_same(pg_design_canvas_icon(), $config['brandIcon'], 'the editor config carries it');

	$editor = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor.js');
	pg_assert_contains("brand.innerHTML = config().brandIcon;", $editor, 'the top bar shows it');

	$folders = file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("'image_editor_icon' => pg_design_canvas_icon(),", $folders, 'the File Manager gets it');
	pg_assert_contains("menuItem(L.image_editor_icon, L.image_editor, 'image_editor', multi)", $folders, 'in the context menu');
	pg_assert_contains("tool('image_editor', L.image_editor_icon, L.image_editor)", $folders, 'and among the file tools');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');
	pg_assert_contains("'canvas_icon'  => pg_design_canvas_icon(),", $screen, 'Workspace gets it');

	$workspace = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_same(2, substr_count($workspace, "'pg-design-canvas', "), 'both New canvas entries use it');
	pg_assert_false(strpos($workspace, "'bi-easel', t('canvas_new')") !== false, 'no easel glyph left on New canvas');
}
