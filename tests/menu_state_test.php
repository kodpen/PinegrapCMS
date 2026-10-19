<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the main menu's wide-screen state (includes/fn/output.php):
 * pg_menu_state() reading the cookie value, the button in the bar
 * (pg_menu_toggle_button()) and the three states at the foot of the menu
 * (pg_menu_mode_switch()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

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

function test_menu_toggle_button_says_whether_the_menu_is_on_screen()
{
	$shown = pg_menu_toggle_button('expanded');
	$hidden = pg_menu_toggle_button('hidden');

	pg_assert_contains('id="menu_drawer_toggle"', $shown, 'the id the tour and the drawer use');
	pg_assert_contains('aria-expanded="true"', $shown, 'visible menu');
	pg_assert_contains('aria-expanded="false"', $hidden, 'hidden menu');
	pg_assert_contains('aria-label="' . h(lang('Hide menu')) . '"', $shown, 'offers to hide');
	pg_assert_contains('aria-label="' . h(lang('Show menu')) . '"', $hidden, 'offers to show');
	pg_assert_contains('bi-layout-sidebar pg-mt-shut', $shown, 'empty panel picture');
	pg_assert_contains('bi-layout-sidebar-inset pg-mt-open', $shown, 'filled panel picture');
	pg_assert_false(strpos($shown, 'd-lg-none') !== false, 'on every width');
}

function test_menu_mode_switch_checks_the_current_state()
{
	$html = pg_menu_mode_switch('hidden');

	pg_assert_same(3, substr_count($html, 'data-pg-menu-mode="'), 'three states');
	pg_assert_same(1, substr_count($html, 'aria-checked="true"'), 'one is checked');
	pg_assert_contains('data-pg-menu-mode="hidden" aria-checked="true"', $html, 'the current one');
	pg_assert_contains('data-pg-menu-mode="expanded" aria-checked="false"', pg_menu_mode_switch('collapsed'), 'others unchecked');
	pg_assert_contains('data-pg-menu-mode="collapsed" aria-checked="true"', pg_menu_mode_switch('bogus'), 'an unknown state is the rail');
	pg_assert_contains('role="menu"', $html, 'a menu of radio items');
}
