<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's Pages window after the page tab strip (it took
 * the place of the "All pages" list): a row opens the page menu on a
 * right-click or the context-menu key the way a tab of the strip does, and
 * the page menu stacks above the strip's menus.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_tabs_all_menu_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_tabs_all_menu_test_js_function($name)
{
	$js = designer_tabs_all_menu_test_source('assets/js/style_designer.src.js');
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

function test_designer_tabs_pages_window_rows_open_the_page_menu()
{
	$part = designer_tabs_all_menu_test_source('assets/js/style_designer_pages.js');

	pg_assert_contains("win.addEventListener('contextmenu'", $part, 'right-click on a row');
	pg_assert_contains("e.key === 'ContextMenu' || (e.key === 'F10' && e.shiftKey)", $part, 'the context-menu key or Shift+F10 on the active row');
	pg_assert_contains("row.classList.contains('sd-pw-locked')", $part, 'a locked row is skipped');

	$host = designer_tabs_all_menu_test_js_function('_pgPagesHost');
	pg_assert_contains('_pgTabsOpenMenu(null, key', $host, 'the window closes, then the page menu opens');
}

// The page menu sits above the v6 menu layer and outside the window, so the
// window's own outside click and the page menu's outside test never meet.
function test_designer_tabs_page_menu_stacks_above_the_list()
{
	$css = designer_tabs_all_menu_test_source('assets/css/style_designer.css');

	pg_assert_true((bool) preg_match('/\.sd-tab-popmenu \{[^}]*z-index: 1060;/', $css), 'page menu above --bs-z-menu (1000)');
}
