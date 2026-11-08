<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's drawn look that a unit test can reach. They
 * read the sources, since the markup is drawn by the script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_dashboard_v2_look_css()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_dash.src.css');
}

// The greeting's since chips and the activity filters share .pg-dash-chip;
// unscoped, the filter rules drew the greeting's chips as grey pills. They
// sit under a grid card, which the greeting is not.
function test_dashboard_v2_activity_pills_stay_in_cards()
{
	$css = pg_test_dashboard_v2_look_css();

	pg_assert_contains('.pg-dash-cardbox .pg-dash-chip[aria-pressed="true"]', $css, 'the pressed pill is scoped');
	pg_assert_same(1, preg_match_all('/^\s*\.pg-dash-chip\s*\{/m', $css), 'one unscoped .pg-dash-chip rule, the greeting\'s');
	pg_assert_false((bool) preg_match('/^\s*\.pg-dash-chip\[aria-pressed/m', $css), 'no unscoped pressed rule');

	$greeting = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_greeting.php');
	pg_assert_false(strpos($greeting, 'pg-dash-cardbox') !== false, 'the greeting is no grid card');
}

// A rule filling every path with none would hide the area under the line,
// whose gradient comes from its fill attribute.
function test_dashboard_v2_line_area_keeps_its_fill()
{
	$css = pg_test_dashboard_v2_look_css();

	pg_assert_contains('.pg-dash-line path:not(.pg-dash-line-area)', $css);
	pg_assert_false((bool) preg_match('/^\s*\.pg-dash-line path\s*\{/m', $css), 'no rule for every path');
}

// Two charts on one page with the same gradient id would share one.
function test_dashboard_v2_line_gradient_ids_are_unique()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash.js');

	pg_assert_contains('"pg_dash_line_" + (++lineCount)', $js);
	pg_assert_contains('fill: "url(#" + id + "_area)"', $js);
	pg_assert_contains('stroke: "url(#" + id + "_stroke)"', $js);
}

// A var() naming a property nothing defines falls back to nothing: the
// figure box drew white on white and the operations desk's lanes lost their
// corners. Every variable the stylesheet reads is defined by the panel,
// Bootstrap, the stylesheet itself or the dashboard's scripts.
function test_dashboard_v2_stylesheet_variables_are_defined()
{
	$css = pg_test_dashboard_v2_look_css();
	preg_match_all('/var\((--[a-z0-9-]+)/', $css, $used);

	$defined = '';
	foreach (array('/assets/css/backend.src.css', '/assets/lib/bootstrap-6/css/bootstrap.css', '/assets/css/pg_dash.src.css') as $file) {
		$defined .= (string) file_get_contents(PG_FUNCTIONS_DIR . $file);
	}
	preg_match_all('/(--[a-z0-9-]+)\s*:/', $defined, $names);
	$names = array_flip($names[1]);

	foreach (glob(PG_FUNCTIONS_DIR . '/assets/js/pg_dash*.js') as $file) {
		if (substr($file, -7) !== '.min.js') {
			preg_match_all('/setProperty\("(--[a-z0-9-]+)"/', (string) file_get_contents($file), $set);
			$names += array_flip($set[1]);
		}
	}

	$missing = array_values(array_unique(array_filter($used[1], function ($name) use ($names) {
		return !isset($names[$name]);
	})));

	pg_assert_same(array(), $missing, 'variables read but never defined');
}

// The figure box is appended to <body>, outside .pg-dash, so the palette's
// tokens do not reach it: it names its colours for both themes.
function test_dashboard_v2_chart_tip_names_its_colours()
{
	$css = pg_test_dashboard_v2_look_css();

	pg_assert_true((bool) preg_match('/^\s*\.pg-dash-tip \{[^}]*background: #[0-9a-f]{6};[^}]*color: #[0-9a-f]{6};/m', $css), 'light page');
	pg_assert_true((bool) preg_match('/html\[data-bs-theme="dark"\] \.pg-dash-tip \{[^}]*background: #[0-9a-f]{6};[^}]*color: #[0-9a-f]{6};/', $css), 'dark page');
	pg_assert_false((bool) preg_match('/\.pg-dash-tip \{[^}]*var\(--pgd-/', $css), 'no palette token in the figure box');
}

// A 2560 px screen gave three cards of 760 px, and five columns stretched the
// greeting and the wide cards across it. The parts stop at 118rem, centred,
// and the columns follow the dashboard's own width (the rail may be open);
// the window decides only where container queries are missing.
function test_dashboard_v2_grid_follows_the_dashboard_width()
{
	$css = pg_test_dashboard_v2_look_css();

	pg_assert_contains("  .pg-dash {\n    container: pgdash / inline-size;\n  }", $css, 'the dashboard is the query container');
	pg_assert_contains("  .pg-dash > * {\n    max-width: 118rem;\n    margin-inline: auto;\n  }", $css, 'its parts stop at 118rem, centred');
	foreach (array(2 => '38rem', 3 => '58rem', 4 => '94rem') as $columns => $width) {
		pg_assert_true((bool) preg_match('/@container pgdash \(min-width: ' . preg_quote($width, '/') . '\) \{\s*\.pg-dash-grid \{\s*grid-template-columns: repeat\(' . $columns . ', /', $css), $columns . ' columns from ' . $width);
	}
	pg_assert_true((bool) preg_match('/@container pgdash \(min-width: 38rem\) \{[^@]*\[data-width="M"\] \{\s*grid-column: span 2;/', $css), 'M spans two only beside a second column');

	$fallback = '/  @supports not \(container-type: inline-size\) \{.*?\n  \}\n/s';
	pg_assert_same(1, preg_match($fallback, $css), 'one fallback for browsers without container queries');
	pg_assert_false((bool) preg_match('/@media \([^)]*width[^)]*\) \{\s*\.pg-dash-(grid|tiles|top)\b/', preg_replace($fallback, '', $css)), 'no window-width rule for the grid, tiles or top outside it');
}

// The sales map's figure box puts a region's figures and best cities on
// lines of their own ("\n" in the text it is given).
function test_dashboard_v2_figure_box_keeps_line_breaks()
{
	pg_assert_true((bool) preg_match('/  \.pg-dash-tip \{[^}]*white-space: pre-line;/', pg_test_dashboard_v2_look_css()));
}

// Edit mode as drawn: the side panel wears the look's card face, its option
// segments keep their own width instead of stretching across the panel, and
// a hidden System Status shrinks to one line beside the greeting.
function test_dashboard_v2_edit_panel_look()
{
	$css = pg_test_dashboard_v2_look_css();
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_panel.js');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_edit.js');

	pg_assert_contains('el("aside", "card pg-dash-card pg-dash-side")', $panel, 'the panel is a dashboard card');
	pg_assert_true(preg_match('/\.pg-dash-side \.pg-dash-segment \{[^}]*align-self: flex-start;/', $css) === 1, 'panel segments are not stretched');
	pg_assert_true(preg_match('/\.pg-dash-status\[data-off\] \{[^}]*align-self: start;/', $css) === 1, 'a hidden status is one line');
	pg_assert_contains('S.status_hidden', $edit, 'the hidden status says so');
	pg_assert_contains('"pg-dash-btn pg-dash-btn-main", S.save', $edit, 'Save is the drawn main button');
}

// The new dashboard draws the drawings' backdrops on its own root, so the
// old dashboard's washes stay as they are; lights off drops the backdrop with
// the shadows, and compact narrows the grid but not the top row.
function test_dashboard_v2_backdrops_and_lights()
{
	$css = pg_test_dashboard_v2_look_css();

	foreach (array('mesh', 'dusk', 'ember') as $name) {
		pg_assert_contains('html[data-bs-theme="dark"] #content.pg-dash[data-panel-backdrop="' . $name . '"]', $css, $name . ' has a dark drawing');
	}
	pg_assert_contains('linear-gradient(180deg, #1b1440 0%, #0d1024 38%, #070b12 100%)', $css, 'dusk is the drawn vertical wash');
	pg_assert_true(preg_match('/#content\.pg-dash\.pg-dash-calm\[data-panel-backdrop\] \{[^}]*background-image: none;/', $css) === 1, 'lights off drops the backdrop');
	pg_assert_false((bool) preg_match('/\.pg-dash-compact \.pg-dash-top\b/', $css), 'compact keeps the top row\'s gaps');
}
