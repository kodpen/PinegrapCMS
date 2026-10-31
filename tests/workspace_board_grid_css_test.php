<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source check for the Plan board grid (workspace_board.php): the minimum
 * width of .ws-board in backend.src.css equals the grid's real minimum, the
 * person column minimum plus seven day column minimums from the column
 * template in workspace.js. A larger grid minimum makes the wrapper draw a
 * horizontal scrollbar and cut the Sunday column on 1280-1340 px windows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The person and day column minimums (rem) of the board's column template.
function workspace_board_grid_minimums()
{
	$js = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/workspace.js');
	if (!preg_match("/grid\.style\.gridTemplateColumns = '([^']*)' \+ data\.days\.length \+ '([^']*)'/", $js, $match)) {
		return null;
	}
	preg_match_all('/([0-9.]+)rem/', $match[1], $person);
	preg_match_all('/([0-9.]+)rem/', $match[2], $day);
	if (!$person[1] || !$day[1]) {
		return null;
	}

	return array((float) min($person[1]), (float) min($day[1]));
}

function test_workspace_board_min_width_matches_the_grid_minimum()
{
	$minimums = workspace_board_grid_minimums();
	pg_assert_true($minimums !== null, 'board column template found in workspace.js');

	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	pg_assert_true(preg_match('/\n\.ws-board \{[^}]*min-width:\s*([0-9.]+)rem;/', $css, $match) === 1, '.ws-board min-width found');

	pg_assert_same($minimums[0] + 7 * $minimums[1], (float) $match[1], '.ws-board min-width is the person minimum plus seven day minimums');
}

function test_workspace_board_seven_days_fit_a_1280_px_window()
{
	// A 1280 px window with the main menu open leaves the board about 1000 px.
	$minimums = workspace_board_grid_minimums();
	pg_assert_true(($minimums[0] + 7 * $minimums[1]) * 16 <= 1000, 'grid minimum fits 1000 px');
}
