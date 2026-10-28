<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the panel's JavaScript copies of its breakpoints match the
 * widths its CSS uses (lg 1024, xl 1280, 2xl 1536): the file manager's list
 * columns (view_folders.php) and the shell tour's narrow() (includes/fn/tour.php)
 * against the menu drawer in assets/js/backend.src.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_js_breakpoints_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Each column class is hidden below its own width; the script's colgroup
// must switch at the same widths.
function test_panel_js_breakpoints_file_manager_columns()
{
	$source = panel_js_breakpoints_source('view_folders.php');

	pg_assert_true((bool) preg_match('/var LIST_BREAKPOINTS = \{ md: (\d+), lg: (\d+), xl: (\d+), xxl: (\d+) \};/', $source, $js), 'LIST_BREAKPOINTS');

	$hidden_below = array();
	preg_match_all('/@media \(min-width: (\d+)px\) and \(max-width: (\d+)\.98px\) \{\s*([^}]*)\}/', $source, $blocks, PREG_SET_ORDER);
	foreach ($blocks as $block) {
		preg_match_all('/\.pg-at-(lg|xl|xxl)\b/', $block[3], $classes);
		foreach ($classes[1] as $class) {
			$hidden_below[$class] = max($hidden_below[$class] ?? 0, (int) $block[2] + 1);
		}
	}

	pg_assert_same(768, (int) $js[1], 'md');
	pg_assert_same($hidden_below['lg'] ?? null, (int) $js[2], 'lg');
	pg_assert_same($hidden_below['xl'] ?? null, (int) $js[3], 'xl');
	pg_assert_same($hidden_below['xxl'] ?? null, (int) $js[4], 'xxl');
	pg_assert_same(1024, (int) $js[2], 'lg is the panel width');
	pg_assert_true(strpos($source, '992px') === false, 'view_folders.php: 992px');
}

// The tour tells the drawer from the column by the width the menu itself uses.
function test_panel_js_breakpoints_tour_narrow()
{
	pg_assert_true((bool) preg_match('/var MENU_DRAWER_BREAKPOINT = (\d+);/', panel_js_breakpoints_source('assets/js/backend.src.js'), $drawer), 'MENU_DRAWER_BREAKPOINT');
	$width = (int) $drawer[1];

	$source = panel_js_breakpoints_source('includes/fn/tour.php');
	pg_assert_contains('matchMedia("(max-width: ' . ($width - 1) . '.98px)")', $source);
	pg_assert_contains('(window.innerWidth < ' . $width . ')', $source);
}
