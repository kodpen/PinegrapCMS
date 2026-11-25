<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's Style guide sheet (the part 'styleguide'): one row of the
 * Preview menu and of the command palette, no bar button, loaded on first
 * use for designers only, and the server's style_guide read kept out of the
 * content roles' list and in the page cache's clean list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_style_guide_sheet_is_a_designer_part()
{
	$files = pg_designer_part_files();
	pg_assert_true(isset($files['styleguide']), 'the part is described');
	pg_assert_same('designer', $files['styleguide']['when'], 'handed to designers only');
	pg_assert_same(array('assets/js/style_designer_styleguide.js'), $files['styleguide']['sources'], 'one source');
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_styleguide.js');
	pg_assert_true(strpos($part, "StyleDesigner.definePart('styleguide'") !== false, 'the source defines the part');
	pg_assert_true(strpos($part, 'return { open: open, close: close };') !== false, 'the part answers open and close');
}

function test_style_guide_sheet_is_the_last_row_of_the_preview_menu()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$share = strpos($js, 'id="sd-vb-share"');
	$row = strpos($js, 'id="sd-vb-styleguide"');
	pg_assert_true($share !== false && $row !== false && $row > $share, 'the row comes after Share the prototype');
	$line = substr($js, $share, $row - $share);
	pg_assert_true(strpos($line, '_sdStyleGuideReady()') !== false, 'the row is drawn only when the sheet can open');
	pg_assert_true(strpos($line, 'class="sd-vb-sep" role="separator"') !== false, 'under a line');
	pg_assert_true(strpos($js, "{ id: 'act_style_guide', label: _sdT('Style guide')") !== false, 'a row of the command palette');
	pg_assert_same(1, substr_count($js, "_sdPartCall('styleguide', 'open'"), 'one way in, the stub');
	pg_assert_false((bool) preg_match('/id="sd-[a-z-]*style-?guide[a-z-]*-btn"/i', $js), 'no bar button');
}

function test_style_guide_sheet_read_is_for_designers_and_clean()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	$from = strpos($panel, '$content_ok = array(');
	$to = strpos($panel, 'pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE', (int) $from);
	pg_assert_true($from !== false && $to !== false, 'the content roles\' list');
	pg_assert_false(strpos(substr($panel, $from, $to - $from), 'style_guide') !== false, 'content roles do not read it');
	pg_assert_true(strpos($panel, "case 'style_guide':") !== false, 'the designer endpoint answers it');
	$subs = pg_page_cache_panel_sub_actions();
	pg_assert_true(in_array('style_guide', $subs['designer']['clean'], true), 'it changes no stored page');
}
