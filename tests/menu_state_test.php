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

	pg_assert_false(strpos($html, 'data-pg-menu-mode') !== false, 'no menu of three any more');
	pg_assert_false(strpos($html, 'd-lg-none') !== false, 'on every width');
}
