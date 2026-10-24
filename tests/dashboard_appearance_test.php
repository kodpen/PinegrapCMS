<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the dashboard appearance helpers (includes/fn/dashboard.php): the
 * accepted card styles and backdrops, how "auto" is resolved, the widget
 * refresh time and who may change them; and that the Appearance save writes
 * the dashboard only behind that gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_dashboard_appearance_choices_start_with_the_defaults()
{
	$choices = pg_dashboard_appearance_choices();

	pg_assert_same(array('widget_theme', 'panel_backdrop'), array_keys($choices), 'the two columns');
	pg_assert_same('flat', $choices['widget_theme'][0], 'flat first');
	pg_assert_same('auto', $choices['panel_backdrop'][0], 'auto first');
	pg_assert_same(array('flat', 'neon', 'glass', 'aurora'), $choices['widget_theme'], 'card styles');
	pg_assert_same(array('auto', 'none', 'mesh', 'dusk', 'ember'), $choices['panel_backdrop'], 'backdrops');
}

function test_dashboard_appearance_resolves_auto_from_the_card_style()
{
	$expected = array('flat' => 'none', 'neon' => 'dusk', 'glass' => 'mesh', 'aurora' => 'mesh');

	foreach ($expected as $theme => $backdrop) {

		$appearance = pg_dashboard_appearance(array('widget_theme' => $theme, 'panel_backdrop' => 'auto'));

		pg_assert_same($theme, $appearance['widget_theme'], $theme . ': style kept');
		pg_assert_same('auto', $appearance['panel_backdrop'], $theme . ': the stored choice stays auto');
		pg_assert_same($backdrop, $appearance['backdrop'], $theme . ': drawn backdrop');
	}
}

function test_dashboard_appearance_keeps_a_chosen_backdrop()
{
	$appearance = pg_dashboard_appearance(array('widget_theme' => 'glass', 'panel_backdrop' => 'ember'));

	pg_assert_same('ember', $appearance['panel_backdrop'], 'stored');
	pg_assert_same('ember', $appearance['backdrop'], 'drawn');

	$appearance = pg_dashboard_appearance(array('widget_theme' => 'aurora', 'panel_backdrop' => 'none'));

	pg_assert_same('none', $appearance['backdrop'], 'none is a choice, not auto');
}

function test_dashboard_appearance_falls_back_on_missing_or_unknown_values()
{
	$flat = array('widget_theme' => 'flat', 'panel_backdrop' => 'auto', 'backdrop' => 'none');

	pg_assert_same($flat, pg_dashboard_appearance(null), 'no row');
	pg_assert_same($flat, pg_dashboard_appearance(array('order_widgets' => 'default')), 'columns not there yet');
	pg_assert_same($flat, pg_dashboard_appearance(array('widget_theme' => 'blur_one', 'panel_backdrop' => '')), 'old or edited values');
	pg_assert_same($flat, pg_dashboard_appearance(array('widget_theme' => 'Flat', 'panel_backdrop' => 'AUTO')), 'case counts');
}

function test_dashboard_refresh_seconds_bounds()
{
	pg_assert_same(60, pg_dashboard_refresh_seconds('60'), 'a plain number');
	pg_assert_same(10, pg_dashboard_refresh_seconds('10'), 'the minimum');
	pg_assert_same(10, pg_dashboard_refresh_seconds('1'), 'raised to the minimum');
	pg_assert_same(10, pg_dashboard_refresh_seconds('-5'), 'negative');
	pg_assert_same(86400, pg_dashboard_refresh_seconds('86400'), 'the maximum');
	pg_assert_same(86400, pg_dashboard_refresh_seconds('3000000'), 'beyond what a browser timer holds');
	pg_assert_same(86400, pg_dashboard_refresh_seconds('1e400'), 'infinite');
	pg_assert_same(16, pg_dashboard_refresh_seconds('15.5'), 'rounded');
	pg_assert_same(30, pg_dashboard_refresh_seconds(30), 'an integer');
}

function test_dashboard_refresh_seconds_keeps_the_fallback_for_a_non_number()
{
	pg_assert_same(60, pg_dashboard_refresh_seconds(''), 'empty');
	pg_assert_same(60, pg_dashboard_refresh_seconds(null), 'not sent');
	pg_assert_same(45, pg_dashboard_refresh_seconds('soon', 45), 'text keeps the given fallback');
	pg_assert_same(45, pg_dashboard_refresh_seconds(array('30'), 45), 'a list');
	pg_assert_same(45, pg_dashboard_refresh_seconds(true, 45), 'a boolean');
}

function test_dashboard_appearance_allowed_for_designers_and_administrators_only()
{
	pg_assert_true(pg_dashboard_appearance_allowed('0'), 'administrator');
	pg_assert_true(pg_dashboard_appearance_allowed(1), 'designer');
	pg_assert_false(pg_dashboard_appearance_allowed('2'), 'manager');
	pg_assert_false(pg_dashboard_appearance_allowed('3'), 'user');
	pg_assert_false(pg_dashboard_appearance_allowed(''), 'no role');
	pg_assert_false(pg_dashboard_appearance_allowed(null), 'not signed in');
}

// The Appearance category is saved by managers too. Everything the save
// writes to the dashboard row or the session's refresh time sits behind the
// role gate, so a manager's save cannot change or blank it, even when the
// request carries the fields.
function test_dashboard_settings_save_writes_behind_the_role_gate()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/appearance.save.php');

	$gate = strpos($source, 'pg_dashboard_appearance_allowed(');

	pg_assert_true($gate !== false, 'the save asks the gate');

	foreach (array('UPDATE dashboard', "['widget']['refresh']") as $write) {

		$at = strpos($source, $write);

		pg_assert_true($at !== false, $write . ' is written by the save');
		pg_assert_true(($gate !== false) && ($at !== false) && ($gate < $at), $write . ' comes after the gate');
	}

	$card = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/appearance.php');

	pg_assert_true(
		strpos($card, 'if ($dashboard_card_shown)') < strpos($card, 'id="pgset-dashboard"'),
		'the card is drawn behind the same gate');
}
