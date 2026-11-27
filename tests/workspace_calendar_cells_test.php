<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Work Calendar's cells (workspace_calendar.php,
 * assets/js/workspace_calendar_cells.js): a day shows three items and then
 * "+N more", which opens the day's list as look A rows in a card beside the
 * cell; the chip gives its title the cell's width; the side column folds
 * behind the bar's "Figures", kept per browser.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_calendar_cells_sources()
{
	return array(
		'cells'  => (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calendar_cells.js'),
		'js'     => (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'),
		'css'    => (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css'),
		'screen' => (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'),
	);
}

function test_workspace_calendar_cells_part_is_in_the_bundle_before_workspace_js()
{
	$files = pg_js_bundles()['assets/js/workspace.min.js'];
	$cells = array_search('assets/js/workspace_calendar_cells.js', $files, true);
	$main = array_search('assets/js/workspace.js', $files, true);

	pg_assert_true($cells !== false, 'the cells part is bundled');
	pg_assert_true($cells < $main, 'and loaded before workspace.js calls it');
}

function test_workspace_calendar_cells_three_a_day_then_more_card()
{
	$s = workspace_calendar_cells_sources();

	pg_assert_contains('var LIMIT = 3;', $s['cells'], 'a day shows three items');
	pg_assert_contains('var limit = cells ? cells.LIMIT : 4;', $s['js'], 'the Work Calendar takes the limit from the part');
	pg_assert_contains("cells.more(cellHelpers, opener, cell, day.label, dayItems(day)", $s['js'], '"+N more" opens the day\'s list beside its cell');
	pg_assert_contains("'pg-list-row ws-cal-more-row'", $s['cells'], 'each line is a look A list row');
	pg_assert_contains("card.setAttribute('role', 'dialog');", $s['cells'], 'the card is named as a dialog');
	pg_assert_contains("event.key === 'Escape'", $s['cells'], 'Escape closes the card');
	pg_assert_contains('event.stopPropagation();', $s['cells'], 'the button does not open the day drawer too');
	pg_assert_contains('.ws-cal-more-card {', $s['css'], 'the card has its rule');
}

function test_workspace_calendar_cells_chip_title_takes_the_width()
{
	$s = workspace_calendar_cells_sources();

	pg_assert_contains("el('div', 'ws-cal ws-cal-work card')", $s['js'], 'the Work Calendar\'s card is told apart from My calendar\'s');
	pg_assert_contains('container-type: inline-size;', $s['css'], 'a cell is the chip\'s container');
	pg_assert_true((bool) preg_match('~@container \(max-width: 15rem\) \{\s*\.ws-cal-work \.ws-cal-task \.ws-avatars \{\s*display: none;~', $s['css']), 'faces leave a narrow cell to the title');
}

function test_workspace_calendar_cells_figures_fold_is_kept_per_browser()
{
	$s = workspace_calendar_cells_sources();

	pg_assert_contains("var FIGURES_KEY = 'pg.ws.calendar.figures';", $s['cells'], 'the choice has its key');
	pg_assert_contains("var shown = stacked() ? false : (readFigures() !== 'hidden');", $s['cells'], 'wide it follows the kept choice, under the grid it starts folded');
	pg_assert_contains("mainCol.classList.toggle('xl:col-12', !shown);", $s['cells'], 'the grid takes the freed width');
	pg_assert_contains("bar.appendChild(cells.figures(cellHelpers, mainCol, sideCol));", $s['js'], 'the button stands in the bar');

	foreach (array('cal_figures', 'cal_figures_hide', 'cal_figures_show') as $key) {
		pg_assert_contains("'" . $key . "'", $s['screen'], $key . ' is a screen text');
	}
}
