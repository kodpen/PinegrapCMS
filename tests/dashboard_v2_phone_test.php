<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's phone layout as drawn (picture 20): the
 * edit bar as a band across the screen, the card tools' remove button on
 * the second line, Edit dashboard at the left, the switch resting in edit
 * mode. They read the sources, since the bar and tools are drawn by the
 * script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The phone block: the rules under a 38rem dashboard, read from the
// container query that holds them.
function pg_test_dashboard_v2_phone_block()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_dash.src.css');
	$start = strpos($css, '@container pgdash (max-width: 37.99rem)');
	pg_assert_true($start !== false, 'a phone container query');

	$end = strpos($css, '@supports not (container-type: inline-size)', $start);
	pg_assert_true($end !== false, 'its fallback follows it');

	return substr($css, $start, $end - $start);
}

// The edit bar spans #content's width (its 1rem padding taken back), with
// no corner and only the rule under it, as the drawn band.
function test_dashboard_v2_phone_edit_bar_band()
{
	$block = pg_test_dashboard_v2_phone_block();

	pg_assert_true((bool) preg_match('/\.pg-dash > \.pg-dash-bar \{[^}]*margin: -\.25rem -1rem 1rem;/', $block), 'the bar takes back the 1rem padding');
	pg_assert_true((bool) preg_match('/\.pg-dash > \.pg-dash-bar \{[^}]*border-width: 0 0 1px;/', $block), 'only the rule under it');
	pg_assert_true((bool) preg_match('/\.pg-dash > \.pg-dash-bar \{[^}]*border-radius: 0;/', $block), 'no corner');
}

// A card's remove button opens the tools' second line; Edit dashboard
// starts its own line at the left.
function test_dashboard_v2_phone_tools_and_switch_row()
{
	$block = pg_test_dashboard_v2_phone_block();

	pg_assert_true((bool) preg_match('/\.pg-dash-tools > \.pg-dash-tool-remove \{\s*margin-inline-start: 0;/', $block), 'remove at the line start');
	pg_assert_true((bool) preg_match('/\.pg-dash-switch-end \{\s*margin-inline-start: 0;/', $block), 'edit button at the left');
}

// Edit mode rests the new/old switch and gives it back on leaving: the
// switch would reload the page and drop the unsaved layout.
function test_dashboard_v2_phone_switch_rests_in_edit_mode()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_edit.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_dash.src.css');

	pg_assert_contains('document.getElementById("pg_dash_switch")', $js, 'the script finds the switch');
	pg_assert_contains('toggle.disabled = hidden;', $js, 'disabled with the edit button hidden, back with it');
	pg_assert_contains('.pg-dash-switch .switch:has(input:disabled)', $css, 'a resting switch is drawn dim');
}
