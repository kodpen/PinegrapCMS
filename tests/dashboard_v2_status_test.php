<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the System Status card of the new dashboard
 * (includes/dashboard/v2/page_status.php) and the gauge and check chips it
 * shares with dashboard widget 2 (includes/fn/health_gauge.php), plus the
 * page's look (card style, backdrop, density, lights). The fixtures under
 * tests/fixtures/health_gauge/ were written by widget 2's own code before
 * the gauge and chips moved out of it, so widget 2's output stays the same;
 * the card draws the same gauge in the dashboard's own palette.
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

function test_dashboard_v2_status_card_draws_gauge_chips_and_changes()
{
	$status = array('score' => 44, 'checks' => dashboard_v2_status_checks());
	$card = pg_dashboard_v2_status(array('role' => 2), $status);

	pg_assert_contains('id="pg_dash_status"', $card);
	pg_assert_contains('aria-labelledby="pg_dash_status_title"', $card);
	pg_assert_contains(pg_health_gauge(44, 'dash'), $card, 'widget 2\'s gauge in the dashboard\'s palette');
	pg_assert_contains(pg_dashboard_v2_status_chips(dashboard_v2_status_checks(), 2), $card, 'every check as a chip, jobs included');
	pg_assert_contains('href="backups.php"', $card, 'a job chip links where its row does');
	pg_assert_contains('data-bs-target="#changelog_modal"', $card, 'the button opens what widget 2\'s header opens');
	pg_assert_contains('title="Release Notes">Changes</button>', $card, 'the button is named for what the dialog lists');
	pg_assert_contains('id="changelog_modal"', $card, 'the dialog comes with the card');
}

// The dashboard's palette changes the colours handed to the stylesheet and
// nothing else: the arc, the squares and the readout are widget 2's.
function test_dashboard_v2_status_dash_palette_changes_only_the_colours()
{
	$strip = function ($gauge) {
		return preg_replace('/<div class="pg-health" style="[^"]*">/', '<div class="pg-health">', $gauge, 1);
	};

	foreach (array(0, 44, 72, 100) as $score) {
		$classic = pg_health_gauge($score);
		$dash = pg_health_gauge($score, 'dash');

		pg_assert_true($classic !== $dash, 'score ' . $score . ' is coloured differently');
		pg_assert_same($strip($classic), $strip($dash), 'score ' . $score . ' is drawn the same');
	}

	pg_assert_same(pg_health_gauge(44), pg_health_gauge(44, 'classic'), 'classic is the default');
	pg_assert_same(pg_health_gauge_band(44), pg_health_gauge_band(44, 'no such palette'), 'an unknown palette is the classic one');
}

// Five bands, cut under 25, 50, 80 and 90, in both palettes; every band has
// four stops for each theme and an ink for each.
function test_dashboard_v2_status_gauge_bands()
{
	foreach (array('classic', 'dash') as $palette) {

		$seen = array();

		foreach (array(array(0, 24), array(25, 49), array(50, 79), array(80, 89), array(90, 100)) as $band) {

			$low = pg_health_gauge_band($band[0], $palette);

			pg_assert_same($low, pg_health_gauge_band($band[1], $palette), $palette . ' ' . $band[0] . '-' . $band[1] . ' is one band');
			pg_assert_same(4, count($low['stops']), $palette . ' dark stops');
			pg_assert_same(4, count($low['stops_lt']), $palette . ' light stops');

			foreach (array_merge($low['stops'], $low['stops_lt']) as $stop) {
				pg_assert_true((bool) preg_match('/^#[0-9a-f]{6}$/', $stop), $palette . ' stop ' . $stop);
			}

			foreach (array($low['ink'], $low['ink_text']) as $ink) {
				pg_assert_true((bool) preg_match('/^\d{1,3}, \d{1,3}, \d{1,3}$/', $ink), $palette . ' ink ' . $ink);
			}

			$seen[] = implode(',', $low['stops']);
		}

		pg_assert_same(5, count(array_unique($seen)), $palette . ': no two bands share their stops');
	}

	// The dashboard's middle band is its own sweep: cyan into indigo into
	// fuchsia, the colours of its charts.
	$middle = pg_health_gauge_band(72, 'dash');
	pg_assert_same(array('#22d3ee', '#818cf8', '#c084fc', '#e879f9'), $middle['stops']);
	pg_assert_same(array('#0891b2', '#4f46e5', '#9333ea', '#c026d3'), $middle['stops_lt']);
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

// Only the failed checks are always drawn; every other check folds behind
// one chip. A folded panel gets its own ids, so no id repeats on the page.
function test_dashboard_v2_status_card_keeps_only_failed_checks_in_sight()
{
	$checks = dashboard_v2_status_checks();
	$checks[] = dashboard_v2_status_extra_check('Broken', 'fail', array(array('label' => 'Row', 'state' => 'fail', 'when' => '')));
	$checks[] = dashboard_v2_status_extra_check('Four', 'info', array(array('label' => 'Row', 'state' => 'ok', 'when' => '')));

	$ordered = pg_system_status_order($checks);
	$chips = pg_dashboard_v2_status_chips($checks, 2);

	pg_assert_same(array('fail', 'fail'), array_column(array_slice($ordered, 0, 2), 'state'), 'the fixture and the extra check fail');
	pg_assert_true(strpos($chips, pg_system_status_chips(array_slice($ordered, 0, 2), 2) . '<details') === 0, 'the two failed checks, as widget 2 draws them, then the fold');
	pg_assert_contains('<details class="pg-dash-status-more"><summary data-tone="warn">+6 more · 2 warnings</summary>', $chips, 'the rest folded, the warnings among them counted');
	pg_assert_contains('<div class="pg-dash-status-more-list">' . pg_system_status_chips(array_slice($ordered, 2), 2, 'system_status_more_') . '</div></details>', $chips, 'the folded six in their own row box');
	pg_assert_contains('id="system_status_detail_2"', $chips, 'a failed check\'s panel');
	pg_assert_contains('id="system_status_more_6"', $chips, 'a folded panel has its own id');

	preg_match_all('/\sid="([^"]+)"/', $chips, $ids);
	pg_assert_same(count($ids[1]), count(array_unique($ids[1])), 'no id twice');
}

// With nothing failed the chip stands alone and counts the checks; it is
// tinted, and says why, only when a warning is among them.
function test_dashboard_v2_status_card_fold_chip_wording()
{
	$calm = array(dashboard_v2_status_extra_check('One'), dashboard_v2_status_extra_check('Two', 'info'), dashboard_v2_status_extra_check('Three'));

	$chips = pg_dashboard_v2_status_chips($calm, 2);
	pg_assert_true(strpos($chips, '<details class="pg-dash-status-more"><summary>3 checks</summary>') === 0, 'nothing failed: the chip alone, no tone');
	pg_assert_false(strpos($chips, 'data-tone') !== false, 'no warning, no tint');

	$calm[] = dashboard_v2_status_extra_check('Careful', 'warn');
	pg_assert_contains('<summary data-tone="warn">4 checks · 1 warning</summary>', pg_dashboard_v2_status_chips($calm, 2), 'a warning tints the chip and is counted in words');

	$calm[] = dashboard_v2_status_extra_check('Broken', 'fail');
	pg_assert_contains('<summary data-tone="warn">+4 more · 1 warning</summary>', pg_dashboard_v2_status_chips($calm, 2), 'after a failed chip it reads "+n more"');

	$failing = array(dashboard_v2_status_extra_check('Fail 1', 'fail'), dashboard_v2_status_extra_check('Fail 2', 'fail'));
	pg_assert_same(pg_system_status_chips($failing, 2), pg_dashboard_v2_status_chips($failing, 2), 'everything failed: nothing folds');
	pg_assert_same('', pg_dashboard_v2_status_chips(array(), 2), 'no checks, no chip');
	pg_assert_contains('<summary>1 check</summary>', pg_dashboard_v2_status_chips(array(dashboard_v2_status_extra_check('One')), 2), 'one check is counted in the singular');
	pg_assert_contains('<summary data-tone="warn">2 checks · 2 warnings</summary>', pg_dashboard_v2_status_chips(array(dashboard_v2_status_extra_check('A', 'warn'), dashboard_v2_status_extra_check('B', 'warn')), 2), 'several warnings in the plural');
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

// The new dashboard's stylesheet with its comments removed, so a word in a
// note never counts as a rule.
function dashboard_v2_status_css()
{
	return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_dash.src.css'));
}

// Lights off drops the gauge's bloom and glow and leaves its twinkle
// running; compact narrows the cards.
function test_dashboard_v2_status_calm_and_compact_rules()
{
	$css = dashboard_v2_status_css();

	pg_assert_true((bool) preg_match('/\.pg-dash-calm \.pg-health-layer-bloom,\s*\.pg-dash-calm \.pg-health-layer-glow\s*\{\s*display:\s*none;/', $css), 'lights off drops the light around the bar');
	pg_assert_false((bool) preg_match('/\.pg-dash-calm[^{}]*\{[^}]*animation-play-state/', $css), 'lights off does not hold the twinkle');
	pg_assert_contains('.pg-dash-compact .pg-dash-card', $css);

	foreach (array('neon', 'glass', 'aurora') as $style) {
		pg_assert_contains('[data-widget-theme="' . $style . '"] .pg-dash-card', $css, $style);
	}
}

// The gauge is lit the way its card is: Flat without bloom or glow, Neon
// and Glass at their own strengths, each strength breathing only where
// motion is welcome.
function test_dashboard_v2_status_gauge_follows_the_card_style()
{
	$css = dashboard_v2_status_css();

	pg_assert_true((bool) preg_match('/\.pg-dash\[data-widget-theme="flat"\] \.pg-health-layer-bloom,\s*\.pg-dash\[data-widget-theme="flat"\] \.pg-health-layer-glow\s*\{\s*display:\s*none;/', $css), 'Flat draws no bloom and no glow');

	foreach (array('neon', 'glass') as $style) {
		foreach (array('glow', 'bloom') as $layer) {
			pg_assert_true((bool) preg_match('/html\[data-bs-theme="dark"\] \.pg-dash\[data-widget-theme="' . $style . '"\] \.pg-health-layer-' . $layer . '\s*\{\s*opacity:/', $css), $style . ' sets its ' . $layer);
			pg_assert_contains('@keyframes pg-dash-gauge-' . $layer . '-' . $style . ' {', $css);
		}
	}

	pg_assert_true((bool) preg_match('/\.pg-dash\[data-widget-theme="glass"\] \.pg-health-track\s*\{[^}]*stroke:/', $css), 'Glass draws its own groove');

	// Every infinite animation of the gauge stays inside the block a
	// reduced-motion visitor does not get.
	$outside = $css;
	$offset = 0;

	while (($pos = strpos($css, '@media (prefers-reduced-motion: no-preference)', $offset)) !== false) {

		$start = strpos($css, '{', $pos);
		$depth = 0;

		for ($i = $start; $i < strlen($css); $i++) {
			if ($css[$i] === '{') {
				$depth++;
			} elseif ($css[$i] === '}' && --$depth === 0) {
				break;
			}
		}

		$outside = str_replace(substr($css, $start, $i - $start + 1), '', $outside);
		$offset = $pos + 1;
	}

	pg_assert_false((bool) preg_match('/animation-name:\s*pg-dash-gauge-/', $outside), 'no gauge breath outside the no-preference block');
	pg_assert_contains('animation-name: pg-dash-gauge-glow-neon;', $css);
}

// The score is written in the arc's own sweep, with a plain colour where
// the system paints its own.
function test_dashboard_v2_status_score_takes_the_sweep()
{
	$css = dashboard_v2_status_css();

	pg_assert_true((bool) preg_match('/\.pg-dash \.pg-dash-status \.pg-health-score\s*\{[^}]*background:\s*linear-gradient\(90deg, var\(--pg-health-s1\), var\(--pg-health-s2\), var\(--pg-health-s3\), var\(--pg-health-s4\)\);[^}]*background-clip:\s*text;[^}]*color:\s*transparent;/', $css), 'the four stops across the digits');
	pg_assert_true((bool) preg_match('/@media \(forced-colors: active\)\s*\{\s*\.pg-dash \.pg-dash-status \.pg-health-score\s*\{[^}]*color:\s*CanvasText;/', $css), 'forced colours get a plain number');
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
