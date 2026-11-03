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
