<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's phone and keyboard pass: the edit bar keeps
 * its row on a .card, the grid is no live region, the solid Save buttons and
 * the size of the shortcut remove cross. They read the sources, since the
 * markup is drawn by the script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * One rule block of pg_dash.src.css, by its exact selector line.
 *
 * @param string $selector
 * @return string the declarations, or '' when the block is missing
 */
function pg_test_dashboard_v2_css_block($selector)
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_dash.src.css');

	if (!preg_match('/^\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m', $css, $match)) {
		return '';
	}

	return $match[1];
}

// The edit bar is drawn as "card pg-dash-bar"; Bootstrap's .card is a column,
// and under it the text block's flex basis made the bar some 18rem tall.
function test_dashboard_v2_edit_bar_is_a_row()
{
	$bar = pg_test_dashboard_v2_css_block('.pg-dash-bar');

	pg_assert_true($bar !== '', 'the bar rule exists');
	pg_assert_contains('flex-direction: row', $bar, 'the bar sets its own direction');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_edit.js');
	pg_assert_contains('el("section", "card pg-dash-bar")', $edit, 'the bar is still a .card');
}

// A live region around the grid read every drawn and refreshed card out loud.
function test_dashboard_v2_grid_is_no_live_region()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_grid.php');

	pg_assert_true(preg_match('/<div class="pg-dash-grid" id="pg_dash_grid"([^>]*)>/', $source, $match) === 1, 'the grid element is found');
	pg_assert_false(strpos($match[1], 'aria-live') !== false, 'no aria-live on the grid');
}

// A button with theme-primary and no btn-solid draws as plain text.
function test_dashboard_v2_primary_buttons_are_solid()
{
	foreach (glob(PG_FUNCTIONS_DIR . '/assets/js/pg_dash*.js') as $file) {
		if (substr($file, -7) === '.min.js') {
			continue;
		}

		$source = (string) file_get_contents($file);
		preg_match_all('/"btn ([^"]*)"/', $source, $matches);

		foreach ($matches[1] as $classes) {
			if (strpos($classes, 'theme-primary') === false) {
				continue;
			}

			pg_assert_true((strpos($classes, 'btn-solid') !== false) || (strpos($classes, 'btn-outline') !== false), basename($file) . ': "btn ' . $classes . '" has a variant');
		}
	}
}

// WCAG 2.5.8: a target of at least 24 by 24 px.
function test_dashboard_v2_remove_cross_target()
{
	$cross = pg_test_dashboard_v2_css_block('.pg-dash-action-remove');

	pg_assert_contains('min-width: 1.5rem', $cross, 'width');
	pg_assert_contains('min-height: 1.5rem', $cross, 'height');
}
