<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a drawer-only screen (output_header()'s menu_drawer, Page Design
 * Studio): the main menu printed as the phone's drawer at every width, opened
 * by the screen's own button instead of the bar's (pg_menu_shell_parts(),
 * backend.src.js, includes/designer_screen.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function test_menu_drawer_prints_the_sidebar_without_the_bar_button()
{
	$parts = pg_menu_shell_parts(array('hide_menu' => true, 'menu_drawer' => true));
	pg_assert_true($parts['sidebar'], 'the menu is printed');
	pg_assert_false($parts['button'], 'no bar button');
	pg_assert_true($parts['drawer_only'], 'the drawer-only class');

	$alone = pg_menu_shell_parts(array('menu_drawer' => true));
	pg_assert_true($alone['sidebar'] && !$alone['button'] && $alone['drawer_only'], 'menu_drawer on its own too');
}

function test_menu_drawer_leaves_other_screens_as_they_were()
{
	$plain = pg_menu_shell_parts(array());
	pg_assert_true($plain['sidebar'], 'a screen draws the menu');
	pg_assert_true($plain['button'], 'and the bar button');
	pg_assert_false($plain['drawer_only'], 'no drawer-only class');
	pg_assert_same($plain, pg_menu_shell_parts('not an array'), 'no properties is a plain screen');
	pg_assert_same($plain, pg_menu_shell_parts(array('hide_menu' => false, 'menu_drawer' => false)), 'both off');

	$hidden = pg_menu_shell_parts(array('hide_menu' => true));
	pg_assert_false($hidden['sidebar'], 'hide_menu alone prints no menu');
	pg_assert_false($hidden['button'], 'nor the bar button');
	pg_assert_false($hidden['drawer_only'], 'nor the class');
}

function test_menu_header_draws_the_drawer_only_container()
{
	$header = pg_test_function_source('output_header');

	pg_assert_contains('$menu_parts = pg_menu_shell_parts($properties);', $header, 'the parts are asked once');
	pg_assert_contains("\$menu_drawer_class = ' menu-drawer-only';", $header, 'the class');
	pg_assert_contains("\$menu_hidden_class . \$menu_drawer_class . '\"'", $header, 'on the container');

	// The bar button and the wide-screen state only where the menu is not a drawer.
	$drawer_at = strpos($header, "if (\$menu_parts['drawer_only']) {");
	$button_at = strpos($header, '$output_menu_mode_button = pg_menu_mode_button($menu_state)');
	pg_assert_true(($drawer_at !== false) && ($button_at !== false) && ($drawer_at < $button_at), 'the button in the other branch');

	// The section rail and the search field still read hide_menu alone.
	pg_assert_contains("!empty(\$properties['section_nav']) && \$toolbar != true && empty(\$properties['hide_menu'])", $header, 'the section rail');
	pg_assert_contains("if (empty(\$properties['hide_menu'])) {\n        \$output_header_search", $header, 'the search field');
}

function test_designer_toolbar_button_opens_the_main_menu()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_contains("'menu_drawer'  => true,", $screen, 'the designer asks for the drawer');
	pg_assert_true(preg_match('/<button[^>]*id="sd-left-toggle"[^>]*>/', $screen, $m) === 1, 'the button is there');
	pg_assert_contains('data-pg-menu-drawer-toggle', $m[0], 'marked as the drawer opener');
	pg_assert_contains('aria-controls="menu"', $m[0], 'controls the menu');
	pg_assert_contains("data-label-show=\"' . h(lang('Show menu')) . '\"", $m[0], 'the phone label to show');
	pg_assert_contains("data-label-hide=\"' . h(lang('Hide menu')) . '\"", $m[0], 'and to hide');
	pg_assert_contains('bi bi-list pg-mt-shut', $screen, 'shut picture');
	pg_assert_contains('bi bi-x-lg pg-mt-open', $screen, 'open picture');
	pg_assert_false(strpos($screen, 'left panel') !== false, 'no left panel labels');

	// First in the toolbar, before the page group the component editor hides.
	pg_assert_true(strpos($screen, 'id="sd-left-toggle"') < strpos($screen, 'sd-toolbar-pages"'), 'outside the page group');
}

function test_menu_script_opens_the_drawer_at_every_width_on_such_a_screen()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	$flag = strpos($js, "var MENU_DRAWER_ONLY = document.querySelector('.software-container.menu-drawer-only') !== null;");
	pg_assert_true(($flag !== false) && ($flag < strpos($js, 'initMenuToggle();')), 'read before the init calls');
	pg_assert_contains('return MENU_DRAWER_ONLY || (window.innerWidth < MENU_DRAWER_BREAKPOINT);', $js, 'drawer mode whatever the width');
	pg_assert_contains("\$(document).on('click', '[data-pg-menu-drawer-toggle]', function (e) {", $js, 'the screen\'s opener');
	pg_assert_contains('var toggle = menuDrawerToggle();', $js, 'Escape gives the focus back to the opener');
	pg_assert_contains('next.closest(MENU_TOGGLES)', $js, 'focus moving to an opener does not shut it');
	pg_assert_contains("e.target.closest('[data-pg-menu-drawer-toggle]')", $js, 'a click on the opener in the content is its own');
	pg_assert_same(1, substr_count($js, "setCookie('softwaremenustatus'"), 'the drawer writes no state');
}

// The bar over a site page on a phone (#11187): a site page wider than the
// screen widened the frame past the breakpoint, and the menu came up open
// from the desktop cookie. The device's screen decides there.
function test_site_bar_on_a_phone_keeps_the_menu_a_drawer()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	$call = strpos($js, "    pgSiteBarPhoneDrawer();\n    var MENU_DRAWER_ONLY");
	pg_assert_true($call !== false, 'marked before the drawer-only flag is read');
	pg_assert_contains("document.body.classList.contains('toolbar')", $js, 'only in the site bar frame');
	pg_assert_contains('(window.screen.width < MENU_DRAWER_BREAKPOINT)', $js, 'by the screen, not the frame');
	pg_assert_contains("container.classList.add('menu-drawer-only');", $js, 'the drawer at every frame width');

	pg_assert_contains('.software-container.menu-drawer-only #header #menu_mode_toggle > .bi.pg-mt-collapsed,', $css, 'no wide-screen picture on the bar button');
	pg_assert_contains('.software-container.menu-drawer-only:not(.drawer-open) #header #menu_mode_toggle > .pg-mt-shut,', $css, 'the shut drawer picture');
}

function test_designer_left_panel_hide_is_gone()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	foreach (array('sd-hide-left', '_sdLeftSetHidden', '_sdLeftHideInit', 'act_left_panel', 'Hide the left panel', 'Show the left panel') as $gone) {
		pg_assert_false(strpos($js, $gone) !== false, $gone . ' gone from the script');
	}

	pg_assert_contains("localStorage.removeItem('pinegrap_designer_hide_left')", $js, 'the old key is removed at load');
	pg_assert_false(strpos($css, 'sd-hide-left') !== false, 'the style rule is gone');
}

// Page Design Studio draws no top bar: its tools stand at the head of the
// main menu's drawer, Go to Site and the account at its foot; every other
// screen keeps its bar.
function test_menu_drawer_takes_the_top_bar_in()
{
	$drawer = pg_menu_shell_parts(array('hide_menu' => true, 'menu_drawer' => true));
	pg_assert_true($drawer['bar_in_drawer'], 'the drawer-only screen moves its bar into the drawer');

	foreach (array(array(), array('hide_menu' => true), array('menu_drawer' => false)) as $properties) {
		pg_assert_false(pg_menu_shell_parts($properties)['bar_in_drawer'], 'any other screen keeps its bar');
	}
}

function test_menu_drawer_bar_markup_order()
{
	$bar = pg_menu_drawer_bar('<nav id="menu">ROWS</nav>', array(
		'logo' => 'LOGO',
		'home' => '/pg/welcome.php',
		'parent_target' => '',
		'tools' => array('SEARCH', 'NOTE', 'HELP', 'SETTINGS', 'BELL'),
		'site' => 'SITE',
		'account' => '<li>ACCOUNT</li>',
	));

	pg_assert_contains('<div id="header" class="pg-drawer-bar">', $bar, 'the drawer holds the bar\'s id, so its controls keep their rules');
	pg_assert_contains('href="/pg/welcome.php"', $bar, 'the logo leads to the panel\'s home');
	pg_assert_true(preg_match('/<button[^>]*data-pg-menu-drawer-close[^>]*>/', $bar, $close) === 1, 'a close button in the head');
	pg_assert_contains('aria-controls="menu"', $close[0], 'it controls the menu');

	$order = array('LOGO', 'data-pg-menu-drawer-close', 'SEARCH', 'NOTE', 'HELP', 'SETTINGS', 'BELL', 'ROWS', 'SITE', 'ACCOUNT');
	$last = -1;
	foreach ($order as $part) {
		$at = strpos($bar, $part);
		pg_assert_true(($at !== false) && ($at > $last), $part . ' in its place');
		$last = $at;
	}

	pg_assert_contains('<ul class="navbar-nav pg-db-account"><li>ACCOUNT</li></ul>', $bar, 'the account item in its list');
}

function test_menu_header_prints_no_top_bar_on_the_drawer_screen()
{
	$header = pg_test_function_source('output_header');

	pg_assert_contains("\$bar_in_drawer = (\$menu_parts['bar_in_drawer'] && (\$toolbar != true) && (\$output_menu_nav !== ''));", $header, 'only with a drawer and outside the site bar');
	pg_assert_contains("\$menu_drawer_class .= ' pg-bar-in-drawer';", $header, 'the container says so');
	pg_assert_contains("\$output_navbar = '';", $header, 'no top bar there');
	pg_assert_contains('$output_navbar . \'', $header, 'the bar is printed through the variable');
	pg_assert_contains('pg_menu_drawer_bar($output_menu_nav, array(', $header, 'the bar\'s controls go into the drawer');
	pg_assert_same(1, substr_count($header, 'id="notification_toggle"'), 'one bell');
	pg_assert_same(1, substr_count($header, 'id="EnableSearch"'), 'one search button');
	pg_assert_same(1, substr_count($header, 'id="help_button"'), 'one help button');
}

function test_menu_drawer_bar_script()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains("var MENU_BAR_IN_DRAWER = document.querySelector('.software-container.pg-bar-in-drawer') !== null;", $js, 'the flag');
	pg_assert_contains("\$(document).on('click', '[data-pg-menu-drawer-close]', function (e) {", $js, 'the head\'s close button');
	pg_assert_contains("if (MENU_BAR_IN_DRAWER && next.closest('dialog[open]')) {", $js, 'a window a tool opened keeps the drawer under it');
	pg_assert_contains("if (!MENU_BAR_IN_DRAWER && isMenuDrawerOpen()) {", $js, 'a menu of a tool keeps the drawer open');
	pg_assert_contains("\$('[data-pg-menu-drawer-toggle]').addClass('pg-menu-unread');", $js, 'an unread notification marks the opener');
	pg_assert_contains("\$('[data-pg-menu-drawer-toggle]').removeClass('pg-menu-unread');", $js, 'and the mark goes when all is read');
}

function test_menu_drawer_bar_styles()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains(".software-container.pg-bar-in-drawer {\n  --pg-navbar-h: 0px;\n  grid-template-rows: 0 minmax(0, 1fr);\n}", $css, 'no row for the bar');
	pg_assert_true((bool) preg_match('/\[data-pg-menu-drawer-toggle\]\.pg-menu-unread::after \{[^}]*width: 7px;[^}]*height: 7px;/', $css), 'the 7 px dot');
	pg_assert_contains('.pg-bar-in-drawer #header .pg-quick-note-nav,', $css, 'the quick note stays in the drawer on a phone');
	pg_assert_contains('.pg-bar-in-drawer #header .pg-usermenu {', $css, 'the account menu opens over the drawer');
	pg_assert_contains('.pg-bar-in-drawer .sd-wrapper {', $css, 'the editor takes the bar\'s height');
}
