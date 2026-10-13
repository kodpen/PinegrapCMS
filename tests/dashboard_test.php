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

	for ($id = 1; $id <= 30; $id++) {
		$ids[] = (string) $id;
	}

	return $ids;
}

// Each of the 31 ids has its file, and loading it defines its function.
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
		'31'         => '31',
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

// The clock answers the site time, formatted the way TIME_FORMAT says, with
// what the dashboard needs to keep its own clock right: the server time, the
// site offset from UTC and the date line.
function test_dashboard_clock_answers_site_time()
{
	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}

	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$before = time();

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
	pg_assert_true(($response['epoch'] >= $before) && ($response['epoch'] <= time()), 'epoch');
	pg_assert_same((int) date('Z', $response['epoch']), $response['offset'], 'offset');
	pg_assert_true((bool) preg_match('#^<time datetime="\d{4}-\d{2}-\d{2}">#', $response['date']), 'date: ' . $response['date']);
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

// The batch answers each id the way a single request would, keyed by the id as
// a string: duplicates once, an integer id like its string form, an unknown id
// with the single request's refusal, and anything that is not an id skipped.
function test_dashboard_batch_answers_each_id_once()
{
	$response = pg_dashboard_widgets_run_many(array('widget_ids' => array('24', 24, '99', array(), null, '24')), array('role' => 0));

	pg_assert_same('success', $response['status'], 'status');
	pg_assert_same(array('24', '99'), array_map('strval', array_keys($response['widgets'])), 'ids');
	pg_assert_same('', $response['widgets']['24']['data'], 'retired slot');
	pg_assert_same('Invalid widget id.', $response['widgets']['99']['message'], 'unknown id');
}

// No list, or not a list, is an empty batch rather than an error.
function test_dashboard_batch_without_ids_is_empty()
{
	pg_assert_same(array(), pg_dashboard_widgets_run_many(array(), array('role' => 0))['widgets'], 'missing');
	pg_assert_same(array(), pg_dashboard_widgets_run_many(array('widget_ids' => '24'), array('role' => 0))['widgets'], 'string');
}

// A batch is capped at forty ids, however many are asked for.
function test_dashboard_batch_is_capped()
{
	$ids = array();

	for ($id = 100; $id < 160; $id++) {
		$ids[] = (string) $id;
	}

	pg_assert_same(40, count(pg_dashboard_widgets_run_many(array('widget_ids' => $ids), array('role' => 0))['widgets']));
}

// Row data attributes: well-formed keys are written and their values escaped;
// anything else is dropped rather than written into the markup.
function test_dashboard_row_data_attributes()
{
	$row = pg_widget_row(array('name' => 'N', 'href' => 'x.php', 'data' => array('kind' => 'a"b', 'Bad Key' => 'x', 'on-click' => 'y')));

	pg_assert_contains(' data-kind="a&quot;b"', $row, 'escaped value');
	pg_assert_contains(' data-on-click="y"', $row, 'dashed key');
	pg_assert_false(strpos($row, 'Bad Key') !== false, 'malformed key dropped');
	pg_assert_contains(' data-kind=', pg_widget_row(array('name' => 'N', 'data' => array('kind' => 'k'))), 'div row');
}

// The bars keep the last seven days, scale to the busiest, and draw an empty
// day as a stub marked zero.
function test_dashboard_bars_scale_to_the_busiest_day()
{
	$bars = pg_widget_bars(array(9, 0, 1, 2, 0, 0, 4, 8));

	pg_assert_same(7, substr_count($bars, '<i '), 'seven days');
	pg_assert_contains('<i style="height:100%"></i>', $bars, 'busiest day fills the box');
	pg_assert_contains('<i style="height:8%" class="is-zero"></i>', $bars, 'empty day');
	pg_assert_contains('<i style="height:50%"></i>', $bars, 'half of the busiest');
	pg_assert_same(7, substr_count(pg_widget_bars(array(0, 0, 0, 0, 0, 0, 0)), 'is-zero'), 'a quiet week');
}

// A filter button carries its key, its colour on the button itself, and the
// count, bars and second line only when given.
function test_dashboard_filter_button()
{
	$button = pg_widget_filter(array('key' => 'form', 'label' => 'Forms <b>', 'icon' => 'bi-x', 'color' => '#f59e0b', 'count' => '3', 'count_class' => 'is-urgent', 'bars' => array(1, 2)));

	pg_assert_contains('data-pg-filter="form"', $button, 'key');
	pg_assert_contains('aria-pressed="false"', $button, 'starts unpressed');
	pg_assert_contains('class="pg-filter" data-pg-filter="form" aria-pressed="false" style="--pg-k:#f59e0b;--pg-k-ink:#18181b"', $button, 'colour on the button');
	pg_assert_contains('Forms &lt;b&gt;', $button, 'label escaped');
	pg_assert_contains('<span class="pg-filter-count is-urgent">3</span>', $button, 'count');
	pg_assert_contains('pg-bars', $button, 'bars');

	$plain = pg_widget_filter(array('key' => 'all', 'label' => 'All'));

	pg_assert_false(strpos($plain, 'pg-filter-count') !== false, 'no count');
	pg_assert_false(strpos($plain, 'pg-bars') !== false, 'no bars');
	pg_assert_false(strpos($plain, 'pg-filter-sub') !== false, 'no second line');
	pg_assert_false(strpos($plain, 'style=') !== false, 'no colour');
}

// A lane card opens on the first urgent lane with something in it, then on
// the first lane with anything, then on the first lane; an empty lane wears
// the green check, and each lane carries its own body.
function test_dashboard_lanes_pick_the_lane_that_opens()
{
	$lane = function ($count, $urgent = false) {
		return array('label' => 'L', 'icon' => 'bi-x', 'color' => '#6366f1', 'count' => $count, 'urgent' => $urgent, 'body' => '<p>b' . $count . '</p>');
	};

	$pick = function ($html) {
		return preg_match('/data-pg-default="([^"]*)"/', $html, $match) ? $match[1] : null;
	};

	pg_assert_same('late', $pick(pg_widget_lanes('t', 'T', array('open' => $lane(4), 'late' => $lane(2, true)))), 'urgent first');
	pg_assert_same('open', $pick(pg_widget_lanes('t', 'T', array('late' => $lane(0, true), 'today' => $lane(0), 'open' => $lane(3)))), 'then any');
	pg_assert_same('late', $pick(pg_widget_lanes('t', 'T', array('late' => $lane(0, true), 'open' => $lane(0)))), 'then the first');
	pg_assert_same('open', $pick(pg_widget_lanes('t', 'T', array('late' => $lane(2, true), 'open' => $lane(3)), 'open')), 'caller decides');

	$html = pg_widget_lanes('ws-tasks', 'My <Tasks>', array('late' => $lane(0, true), 'open' => $lane(3)));

	pg_assert_contains('data-pg-filterable="ws-tasks"', $html, 'name');
	pg_assert_contains('aria-label="My &lt;Tasks&gt;"', $html, 'label escaped');
	pg_assert_contains('<div class="pg-ops-lane d-none" data-kind="open"><p>b3</p></div>', $html, 'lane body');
	pg_assert_contains('bi-check2-circle', $html, 'empty lane checked');
	pg_assert_contains('<span class="pg-filter-count is-clear">0</span>', $html, 'empty lane count');
}

// The workspace cards answer nothing when the module is off, which is how
// the unit runner sees an installation: no WORKSPACE_ENABLED.
function test_dashboard_workspace_cards_need_the_module()
{
	$denied = array('status' => 'error', 'message' => 'Access denied');

	foreach (array('27', '28', '29', '30') as $id) {
		pg_assert_same($denied, pg_dashboard_widget_run(array('widget_id' => $id), array('id' => 1, 'role' => 0)), 'widget ' . $id);
	}

	pg_assert_same(null, pg_dashboard_workspace_rights(array('id' => 1, 'role' => 0)), 'no rights');
}
