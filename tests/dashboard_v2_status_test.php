<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the System Status card of the new dashboard
 * (includes/dashboard/v2/page_status.php) and the gauge and check chips it
 * shares with dashboard widget 2 (includes/fn/health_gauge.php), plus the
 * page's look (card style, backdrop, density, lights). The fixtures under
 * tests/fixtures/health_gauge/ were written by widget 2's own code before
 * the gauge and chips moved out of it, so widget 2's output stays the same.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_status.php');

function dashboard_v2_status_fixture($name)
{
	return (string) file_get_contents(__DIR__ . '/fixtures/health_gauge/' . $name);
}

function dashboard_v2_status_checks()
{
	return json_decode(dashboard_v2_status_fixture('checks.json'), true);
}

function test_dashboard_v2_status_gauge_matches_widget_2()
{
	foreach (array(0, 44, 100) as $score) {
		pg_assert_same(dashboard_v2_status_fixture('gauge_' . $score . '.html'), pg_health_gauge($score), 'score ' . $score);
	}
}

function test_dashboard_v2_status_chips_match_widget_2()
{
	foreach (array(0, 2) as $role) {
		pg_assert_same(dashboard_v2_status_fixture('chips_role_' . $role . '.html'), pg_system_status_chips(dashboard_v2_status_checks(), $role), 'role ' . $role);
	}
}

function test_dashboard_v2_status_widget_2_calls_the_shared_functions()
{
	$widget = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_2.php');

	pg_assert_contains('pg_health_gauge($health_score)', $widget);
	pg_assert_contains("pg_system_status_chips(\$reading_checks, \$user['role'])", $widget);
	pg_assert_false(strpos($widget, '<linearGradient') !== false, 'the gauge markup left widget 2');
}

function test_dashboard_v2_status_card_hidden_below_manager()
{
	$status = array('score' => 44, 'checks' => dashboard_v2_status_checks());

	pg_assert_same('', pg_dashboard_v2_status(array('role' => 3), $status));
}

function test_dashboard_v2_status_card_draws_gauge_chips_and_details()
{
	$status = array('score' => 44, 'checks' => dashboard_v2_status_checks());
	$card = pg_dashboard_v2_status(array('role' => 2), $status);

	pg_assert_contains('id="pg_dash_status"', $card);
	pg_assert_contains('aria-labelledby="pg_dash_status_title"', $card);
	pg_assert_contains(pg_health_gauge(44), $card, 'the same gauge as widget 2');
	pg_assert_contains(pg_system_status_chips(dashboard_v2_status_checks(), 2), $card, 'every check as a chip, jobs included');
	pg_assert_contains('href="backups.php"', $card, 'a job chip links where its row does');
	pg_assert_contains('data-bs-target="#changelog_modal"', $card, 'Details opens what widget 2\'s header opens');
	pg_assert_contains('id="changelog_modal"', $card, 'the dialog comes with the card');
}

// Widget 2's order: failed, warning, the priority checks, then the rest.
function test_dashboard_v2_status_order()
{
	$keys = array_column(pg_system_status_order(dashboard_v2_status_checks()), 'key');

	pg_assert_same(array('Directory File Integrity', 'SSL', 'Backup', 'Firewall', 'PHP Version', 'Event subscriptions'), $keys);
}

/**
 * A calm check for the folding tests.
 *
 * @param string $name
 * @param string $state
 * @param array  $detail
 * @return array
 */
function dashboard_v2_status_extra_check($name, $state = 'ok', $detail = array())
{
	return array('key' => $name, 'icon' => 'bi-check', 'state' => $state, 'title' => $name, 'label' => $name, 'message' => $name . '.', 'value' => 'OK', 'generic' => true, 'href' => '', 'priority' => false, 'job' => false, 'detail' => $detail);
}

// Past eight chips the rest fold behind "+n more"; a folded panel gets its
// own ids, so no id repeats on the page.
function test_dashboard_v2_status_card_folds_chips_past_eight()
{
	$checks = dashboard_v2_status_checks();
	$checks[] = dashboard_v2_status_extra_check('One');
	$checks[] = dashboard_v2_status_extra_check('Two');
	$checks[] = dashboard_v2_status_extra_check('Three');
	$checks[] = dashboard_v2_status_extra_check('Four', 'info', array(array('label' => 'Row', 'state' => 'ok', 'when' => '')));

	$ordered = pg_system_status_order($checks);
	$chips = pg_dashboard_v2_status_chips($checks, 2);

	pg_assert_true(strpos($chips, pg_system_status_chips(array_slice($ordered, 0, 8), 2)) === 0, 'the first eight are drawn as widget 2 draws them');
	pg_assert_contains('<details class="pg-dash-status-more"><summary>+2 more</summary>', $chips, 'two folded, no tone for calm checks');
	pg_assert_contains('<div class="pg-dash-status-more-list">' . pg_system_status_chips(array_slice($ordered, 8), 2, 'system_status_more_') . '</div></details>', $chips, 'the folded two in their own row box');
	pg_assert_contains('id="system_status_more_2"', $chips, 'a folded panel has its own id');

	preg_match_all('/\sid="([^"]+)"/', $chips, $ids);
	pg_assert_same(count($ids[1]), count(array_unique($ids[1])), 'no id twice');

	$six = pg_dashboard_v2_status_chips(dashboard_v2_status_checks(), 2);
	pg_assert_same(pg_system_status_chips(dashboard_v2_status_checks(), 2), $six, 'six checks: nothing folds');
}

// A folded problem tints the "+n more" chip.
function test_dashboard_v2_status_card_folded_tone()
{
	$failing = array();
	for ($i = 1; $i <= 9; $i++) {
		$failing[] = dashboard_v2_status_extra_check('Fail ' . $i, 'fail');
	}

	pg_assert_contains('<summary data-tone="bad">+1 more</summary>', pg_dashboard_v2_status_chips($failing, 2));

	$failing[8]['state'] = 'warn';
	$failing[] = dashboard_v2_status_extra_check('Calm');
	pg_assert_contains('<summary data-tone="warn">+2 more</summary>', pg_dashboard_v2_status_chips($failing, 2), 'a warning without a failure');
}

function test_dashboard_v2_status_look_falls_back_to_the_site()
{
	$look = pg_dashboard_v2_look('', array('widget_theme' => 'neon', 'panel_backdrop' => 'auto'));

	pg_assert_same(array('style' => 'neon', 'backdrop' => 'auto', 'drawn' => 'dusk', 'dense' => false, 'calm' => false), $look);
}

function test_dashboard_v2_status_look_reads_the_person()
{
	$look = pg_dashboard_v2_look('{"style":"glass","backdrop":"ember","dense":1,"calm":1}', array('widget_theme' => 'neon', 'panel_backdrop' => 'none'));

	pg_assert_same(array('style' => 'glass', 'backdrop' => 'ember', 'drawn' => 'ember', 'dense' => true, 'calm' => true), $look);
}

function test_dashboard_v2_status_look_drops_unknown_values()
{
	$look = pg_dashboard_v2_look('{"style":"x\"><script>","backdrop":"auto"}', array('widget_theme' => 'aurora', 'panel_backdrop' => 'none'));

	pg_assert_same('aurora', $look['style'], 'an unknown style takes the site\'s');
	pg_assert_same('mesh', $look['drawn'], 'auto follows the style');

	$broken = pg_dashboard_v2_look('{not json', null);

	pg_assert_same('flat', $broken['style']);
	pg_assert_same('none', $broken['drawn']);
}

function test_dashboard_v2_status_look_attributes()
{
	$attributes = pg_dashboard_v2_look_attributes(array('style' => 'glass', 'backdrop' => 'auto', 'drawn' => 'mesh', 'dense' => true, 'calm' => true));

	pg_assert_same(' class="container-fluid pg-dash pg-dash-compact pg-dash-calm" data-widget-theme="glass" data-panel-backdrop="mesh"', $attributes);

	$plain = pg_dashboard_v2_look_attributes(array('style' => 'flat', 'backdrop' => 'none', 'drawn' => 'none', 'dense' => false, 'calm' => false));

	pg_assert_same(' class="container-fluid pg-dash" data-widget-theme="flat" data-panel-backdrop="none"', $plain);
}

// Lights off stops the gauge's twinkle and breathing as an off-screen dial
// does; compact narrows the cards.
function test_dashboard_v2_status_calm_and_compact_rules()
{
	$css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_dash.src.css'));

	pg_assert_true((bool) preg_match('/\.pg-dash-calm \.pg-health-cells > rect,\s*\.pg-dash-calm \.pg-health-layer\s*\{\s*animation-play-state:\s*paused;/', $css), 'lights off pauses the gauge');
	pg_assert_contains('.pg-dash-compact .pg-dash-card', $css);

	foreach (array('neon', 'glass', 'aurora') as $style) {
		pg_assert_contains('[data-widget-theme="' . $style . '"] .pg-dash-card', $css, $style);
	}
}

// welcome.php hands the parsed sections on to the brief, so the dialog
// function returns them beside its markup.
function test_dashboard_v2_status_changelog_dialog_returns_sections()
{
	$dialog = pg_changelog_dialog();

	pg_assert_true(is_array($dialog['sections']), 'sections');
	pg_assert_same(pg_changelog_sections(), $dialog['sections']);
	pg_assert_contains("\$changelog_sections = \$changelog_dialog['sections'];", (string) file_get_contents(PG_FUNCTIONS_DIR . '/welcome.php'));
}
