<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the dashboard widget loader in includes/dashboard/widgets.php:
 * every widget id resolves to its own file and function, anything else is
 * refused before it reaches a file path, and the widgets that need no
 * database answer through the loader the way api.php relays them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

function pg_test_dashboard_widget_ids()
{
	$ids = array('clock');

	for ($id = 1; $id <= 26; $id++) {
		$ids[] = (string) $id;
	}

	return $ids;
}

// Each of the 27 ids has its file, and loading it defines its function.
function test_dashboard_every_widget_has_file_and_function()
{
	foreach (pg_test_dashboard_widget_ids() as $id) {
		$file = PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_' . $id . '.php';

		if (!pg_assert_true(is_file($file), 'file ' . $id)) {
			continue;
		}

		require_once($file);

		pg_assert_true(function_exists('pg_dashboard_widget_' . $id), 'function ' . $id);
	}
}

// Anything that is not a known id shape, or has no file, is the old default
// answer - including ids that try to leave the widgets folder.
function test_dashboard_invalid_ids_are_refused()
{
	$expected = array('status' => 'error', 'message' => 'Invalid widget id.');

	$ids = array(
		'empty'      => '',
		'zero'       => '0',
		'27'         => '27',
		'99'         => '99',
		'traversal'  => '../x',
		'nested'     => '1/../2',
		'newline'    => "1\n",
		'array'      => array(),
		'null'       => null,
		'true'       => true,
	);

	foreach ($ids as $label => $id) {
		pg_assert_same($expected, pg_dashboard_widget_run(array('widget_id' => $id), array('role' => 0)), $label);
	}

	pg_assert_same($expected, pg_dashboard_widget_run(array(), array('role' => 0)), 'missing widget_id');
}

// Widget 24 is a retired slot that answers an empty success without touching
// the database; it also shows an integer id is taken like its string form.
function test_dashboard_widget_24_answers_empty_success()
{
	foreach (array('string' => '24', 'int' => 24) as $label => $id) {
		$response = pg_dashboard_widget_run(array('widget_id' => $id), array('role' => 0));

		pg_assert_same('success', $response['status'], $label . ' status');
		pg_assert_same('', $response['data'], $label . ' data');
	}
}

// The clock answers the site time, formatted the way TIME_FORMAT says.
function test_dashboard_clock_answers_site_time()
{
	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}

	$had_session = isset($_SESSION);
	$previous = $had_session ? $_SESSION : null;
	$_SESSION['sessionusername'] = 'x';

	$response = pg_dashboard_widget_run(array('widget_id' => 'clock'), array('role' => 0));

	if ($had_session) {
		$_SESSION = $previous;
	} else {
		unset($_SESSION);
	}

	pg_assert_same('success', $response['status'], 'status');
	pg_assert_true((bool) preg_match('#^<time datetime="[^"]*">([01][0-9]|2[0-3]):[0-5][0-9]</time>$#', $response['data']), 'data: ' . $response['data']);
	pg_assert_true(substr($response['message'], -1) === 'x', 'message ends with the session name');
}

// Tile ink: near-black on a mid-tone brand colour, white on dark or unreadable
// input.
function test_dashboard_readable_ink_picks_the_legible_colour()
{
	pg_assert_same('#18181b', pg_readable_ink('#f59e0b'), 'amber');
	pg_assert_same('#fff', pg_readable_ink('#000'), 'black, short form');
	pg_assert_same('#fff', pg_readable_ink('zzz'), 'not a colour');
}

// Only the anchor tags go; the text and any other markup stay.
function test_dashboard_strip_anchor_tags_keeps_inner_markup()
{
	pg_assert_same('a <i>b</i>', pg_strip_anchor_tags('<a href="x">a <i>b</i></a>'));
}

// The aside span is rendered only when there is an aside.
function test_dashboard_row_heading_aside_is_optional()
{
	pg_assert_contains('pg-row-aside', pg_widget_row_heading('L', 'A'), 'with aside');
	pg_assert_false(strpos(pg_widget_row_heading('L'), 'pg-row-aside') !== false, 'without aside');
}
