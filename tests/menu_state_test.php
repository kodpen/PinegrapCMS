<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the main menu's wide-screen state (includes/fn/output.php):
 * pg_menu_state() reading the cookie value, and the button in the bar that
 * walks the three states (pg_menu_mode_button()).
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

function test_menu_state_reads_the_three_states()
{
	pg_assert_same('expanded', pg_menu_state('expanded'), 'expanded');
	pg_assert_same('collapsed', pg_menu_state('collapsed'), 'collapsed');
	pg_assert_same('hidden', pg_menu_state('hidden'), 'hidden');
}

function test_menu_state_falls_back_to_the_rail()
{
	pg_assert_same('collapsed', pg_menu_state(''), 'no cookie');
	pg_assert_same('collapsed', pg_menu_state('Hidden'), 'case matters');
	pg_assert_same('collapsed', pg_menu_state('wide'), 'unknown value');
	pg_assert_same('collapsed', pg_menu_state(array('hidden')), 'not a string');
	pg_assert_same('collapsed', pg_menu_state(null), 'null');
}

function test_menu_mode_button_names_the_state_it_shows()
{
	$mini = pg_menu_mode_button('collapsed');
	$open = pg_menu_mode_button('expanded');
	$hidden = pg_menu_mode_button('hidden');

	pg_assert_contains('id="menu_mode_toggle"', $mini, 'the id the tour and Ctrl+D use');
	pg_assert_contains('aria-controls="menu"', $mini, 'controls the menu');
	pg_assert_contains('aria-expanded="true"', $mini, 'mini is on screen');
	pg_assert_contains('aria-expanded="true"', $open, 'open is on screen');
	pg_assert_contains('aria-expanded="false"', $hidden, 'hidden is not');
	pg_assert_contains('aria-label="' . h(lang('Menu view') . ': ' . lang('Mini')) . '"', $mini, 'names mini');
	pg_assert_contains('aria-label="' . h(lang('Menu view') . ': ' . lang('Expanded')) . '"', $open, 'names open');
	pg_assert_contains('aria-label="' . h(lang('Menu view') . ': ' . lang('Hidden')) . '"', $hidden, 'names hidden');
	pg_assert_contains('aria-label="' . h(lang('Menu view') . ': ' . lang('Mini')) . '"', pg_menu_mode_button('bogus'), 'an unknown state is the rail');
	pg_assert_contains('(Ctrl+D)', $mini, 'the shortcut in the hint');
}

function test_menu_mode_button_carries_every_picture_and_label()
{
	$html = pg_menu_mode_button('collapsed');

	foreach (array('pg-mt-collapsed', 'pg-mt-expanded', 'pg-mt-hidden', 'pg-mt-shut', 'pg-mt-open') as $picture) {
		pg_assert_same(1, substr_count($html, $picture), $picture . ' printed once');
	}

	foreach (array('view', 'collapsed', 'expanded', 'hidden', 'show', 'hide') as $label) {
		pg_assert_contains('data-label-' . $label . '="', $html, 'label ' . $label . ' for the script');
	}

	// The hidden menu is drawn as an empty side panel, like the shut drawer
	// on a phone: an empty square read as an unticked check box.
	pg_assert_contains('bi bi-layout-sidebar pg-mt-hidden', $html, 'hidden is the empty side panel');
	pg_assert_false(strpos($html, 'bi-square ') !== false, 'no empty square');

	pg_assert_false(strpos($html, 'data-pg-menu-mode') !== false, 'no menu of three any more');
	pg_assert_false(strpos($html, 'd-lg-none') !== false, 'on every width');
}

function test_menu_navigator_state_is_open_or_the_rail()
{
	pg_assert_same('expanded', pg_menu_navigator_state(''), 'no cookie: open');
	pg_assert_same('expanded', pg_menu_navigator_state('expanded'), 'open');
	pg_assert_same('collapsed', pg_menu_navigator_state('collapsed'), 'narrowed there');
	pg_assert_same('expanded', pg_menu_navigator_state('hidden'), 'never hidden');
	pg_assert_same('expanded', pg_menu_navigator_state(array('collapsed')), 'anything else: open');
}

function test_menu_header_draws_a_navigator_screen_from_its_own_cookie()
{
	$header = pg_test_function_source('output_header');

	// The menu is built before the flag is read, the flag before the button
	// and the classes are chosen; the menu is built once.
	$menu_at = strpos($header, '$output_menu = output_menu(');
	$flag_at = strpos($header, 'if (pg_menu_navigator()) {');
	$button_at = strpos($header, '$output_menu_mode_button = pg_menu_mode_button($menu_state)');
	$hidden_at = strpos($header, "\$menu_hidden_class = (\$menu_state === 'hidden')");
	pg_assert_true(($menu_at !== false) && ($flag_at !== false) && ($button_at !== false) && ($hidden_at !== false), 'all four found');
	pg_assert_true(($menu_at < $flag_at) && ($flag_at < $button_at) && ($flag_at < $hidden_at), 'the menu first, then the flag, then the button and the classes');
	pg_assert_same(1, substr_count($header, 'output_menu(array('), 'built once, not again in the sidebar');
	pg_assert_contains("if (pg_menu_navigator()) {\n            \$menu_state = pg_menu_navigator_state(isset(\$_COOKIE['softwaremenunav'])", $header, 'its own cookie');
	pg_assert_contains("\$menu_navigator_attribute = ' data-pg-menu-navigator';", $header, 'marked for the script');
	pg_assert_contains("\$menu_hidden_class . '\"' . \$menu_navigator_attribute . '>", $header, 'on the container');

	// Only a panel that opens by itself makes a navigator screen.
	$menu = pg_test_function_source('output_menu');
	pg_assert_contains("if (\$ws_side_screen !== '') {\n                pg_menu_navigator(true);", $menu, 'the workspace screens');
}

function test_menu_script_keeps_a_navigator_screen_open_or_narrowed()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	// Read before the init calls: syncMenuMode() paints the saved state there.
	$flag = strpos($js, "var MENU_NAVIGATOR = document.querySelector('.software-container[data-pg-menu-navigator]') !== null;");
	pg_assert_true(($flag !== false) && ($flag < strpos($js, 'initResizeCollapseHandler();')), 'the flag is read before the init calls');
	pg_assert_contains("return (getCookie('softwaremenunav') === 'collapsed') ? 'collapsed' : 'expanded';", $js, 'its saved state');
	pg_assert_contains("setCookie('softwaremenunav', state, 365);", $js, 'its own cookie');
	pg_assert_contains("return (shown === 'expanded') ? 'collapsed' : 'expanded';", $js, 'the button narrows and opens, never hides');
	pg_assert_same(1, substr_count($js, "setCookie('softwaremenustatus'"), 'one place writes the other screens\' state');
}
