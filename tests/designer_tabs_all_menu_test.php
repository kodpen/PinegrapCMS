<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's "All pages" list after the page tab strip: its
 * rows are Bootstrap 6 menu items, the code that reads its open state and
 * closes it finds the menu's toggle, and a row opens the page menu on a
 * right-click or the context-menu key the way a tab of the strip does.
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
	$js = designer_tabs_all_menu_test_source('assets/js/style_designer.js');
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

function test_designer_tabs_all_rows_are_menu_items()
{
	$fill = designer_tabs_all_menu_test_js_function('_pgTabsAllFill');

	pg_assert_contains('class="menu-item sd-tabs-all-item', $fill, 'row is a v6 menu item');
	pg_assert_false(strpos($fill, 'dropdown-item') !== false, 'no Bootstrap 5 dropdown-item');
	// The badge reads on the dark menu, and an active row keeps it legible.
	pg_assert_contains('sd-tab-draft-badge sd-tabs-all-badge', $fill, 'draft badge has the list modifier');

	$css = designer_tabs_all_menu_test_source('assets/css/style_designer.css');
	pg_assert_contains('.sd-tabs-all-item.active .sd-tabs-all-badge', $css, 'badge on the active row');
	pg_assert_contains('.sd-tabs-all-item:focus-visible', $css, 'keyboard focus ring');
}

function test_designer_tabs_all_finds_its_menu_toggle()
{
	pg_assert_contains('[data-bs-toggle="menu"]', designer_tabs_all_menu_test_js_function('_pgTabsAllToggle'), 'the toggle is the v6 menu button');
	pg_assert_contains('pgUI.menu.isOpen(_pgTabsAllToggle())', designer_tabs_all_menu_test_js_function('_pgTabsAllSync'), 'open state read from it');

	$bind = designer_tabs_all_menu_test_js_function('_pgTabsBind');
	pg_assert_false(strpos($bind, 'data-bs-toggle="dropdown"') !== false, 'no dropdown toggle lookup in _pgTabsBind');
	pg_assert_false(strpos(designer_tabs_all_menu_test_js_function('_pgTabsAllSync'), 'data-bs-toggle="dropdown"') !== false, 'no dropdown toggle lookup in _pgTabsAllSync');
	pg_assert_contains('_pgTabsAllToggle()', $bind, 'a picked page closes the list');
}

function test_designer_tabs_all_rows_open_the_page_menu()
{
	$bind = designer_tabs_all_menu_test_js_function('_pgTabsBind');

	pg_assert_contains("all.addEventListener('contextmenu'", $bind, 'right-click on the list');
	pg_assert_contains("e.key === 'ContextMenu'", $bind, 'context-menu key on a row');
	pg_assert_contains("e.key === 'F10' && e.shiftKey", $bind, 'Shift+F10 on a row');

	$open = designer_tabs_all_menu_test_js_function('_pgTabsAllPageMenu');
	pg_assert_contains('.sd-tabs-all-item', $open, 'acts on a row');
	pg_assert_contains('it.disabled', $open, 'a locked row is skipped');
	pg_assert_contains('pgUI.menu.hide(', $open, 'the list closes first');
	pg_assert_contains('_pgTabsOpenMenu(null, key', $open, 'then the page menu opens');
}

// The page menu sits above the v6 menu layer and outside the list, so the
// list's own outside click and the page menu's outside test never meet.
function test_designer_tabs_page_menu_stacks_above_the_list()
{
	$css = designer_tabs_all_menu_test_source('assets/css/style_designer.css');

	pg_assert_true((bool) preg_match('/\.sd-tab-popmenu \{[^}]*z-index: 1060;/', $css), 'page menu above --bs-z-menu (1000)');
}
