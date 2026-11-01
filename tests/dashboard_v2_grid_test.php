<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's card frame and data (includes/dashboard/v2/
 * run.php, page_grid.php, cards/visitors.php, cards/engagement.php): the
 * request clean-up, the drawn layout, the change figure and the series.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_grid.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/visitors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/engagement.php');

// Unknown ids, repeats and non-arrays are dropped; period and compare are
// held to 0-2, and a card without a period takes 0.
function test_dashboard_v2_cards_asked_cleans_the_request()
{
	pg_assert_same(array(), pg_dashboard_v2_cards_asked('visitors'));

	$asked = pg_dashboard_v2_cards_asked(array(
		array('id' => 'visitors', 'per' => 9, 'cmp' => -3),
		array('id' => 'visitors', 'per' => 1),
		array('id' => 'nope'),
		'engagement',
		array('id' => 'engagement', 'per' => 2, 'cmp' => 1),
		array('id' => 'gsearch'),
	));

	pg_assert_same(array(
		'visitors'   => array('per' => 2, 'cmp' => 0),
		'engagement' => array('per' => 0, 'cmp' => 1),
		'gsearch'    => array('per' => 1, 'cmp' => 0),
	), $asked);
}

// A card that is not built, or behind a gate the person does not pass,
// answers denied without loading its file.
function test_dashboard_v2_card_run_denies()
{
	pg_assert_same('denied', pg_dashboard_v2_card_run('goals', array('id' => 1, 'role' => 0), 0, 0)['status'], 'not built');
	pg_assert_same('denied', pg_dashboard_v2_card_run('engagement', array('id' => 1, 'role' => 3, 'manage_visitors' => 1), 0, 0)['status'], 'gate');
	pg_assert_same('denied', pg_dashboard_v2_card_run('nope', array('id' => 1, 'role' => 0), 0, 0)['status'], 'unknown');
}

// Built cards have their file and function, and are in the timing lists
// only by known ids.
function test_dashboard_v2_built_cards_have_functions()
{
	$cards = pg_dashboard_v2_cards();

	foreach ($cards as $id => $card) {
		if ($card['built']) {
			pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/' . $id . '.php'), $id . ' file');
			require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/' . $id . '.php');
			pg_assert_true(function_exists('pg_dashboard_v2_card_' . $id), $id . ' function');
		}
	}

	foreach (pg_dashboard_v2_card_timing() as $list) {
		foreach ($list as $id) {
			pg_assert_true(isset($cards[$id]), 'timing ' . $id);
		}
	}
}

function test_dashboard_v2_change()
{
	pg_assert_same(null, pg_dashboard_v2_change(5, 0));
	pg_assert_same(array(50, 'good'), array_slice(array_values(pg_dashboard_v2_change(150, 100)), 0, 2));
	pg_assert_same(array(-25, 'bad'), array_slice(array_values(pg_dashboard_v2_change(75, 100)), 0, 2));
	pg_assert_same(0, pg_dashboard_v2_change(100, 100)['pct']);
}

// The stored order is kept; unknown, hidden and repeated cards are skipped;
// bad widths and periods take the card's own; nothing stored draws every
// visible card.
function test_dashboard_v2_grid_layout()
{
	$all = pg_dashboard_v2_cards();
	$visible = array('visitors' => $all['visitors'], 'engagement' => $all['engagement']);

	pg_assert_same(array(
		array('id' => 'visitors', 'w' => 'M', 'per' => 0, 'cmp' => 0, 'badge' => 1),
		array('id' => 'engagement', 'w' => 'S', 'per' => null, 'cmp' => 0, 'badge' => 1),
	), pg_dashboard_v2_grid_layout(null, $visible), 'nothing stored');

	pg_assert_same(array(
		array('id' => 'engagement', 'w' => 'L', 'per' => null, 'cmp' => 0, 'badge' => 0),
		array('id' => 'visitors', 'w' => 'M', 'per' => 2, 'cmp' => 1, 'badge' => 1),
	), pg_dashboard_v2_grid_layout(array(
		array('id' => 'firewall', 'w' => 'S'),
		array('id' => 'engagement', 'w' => 'L', 'per' => 1, 'badge' => 0),
		array('id' => 'visitors', 'w' => 'XL', 'per' => 2, 'cmp' => 1),
		array('id' => 'visitors', 'w' => 'S'),
		'junk',
	), $visible), 'stored');
}

// Today is one date read by the hour; 7 and 30 days step back a day at a
// time; the comparison is the period before or the same dates a year ago.
function test_dashboard_v2_visitor_dates()
{
	$now = mktime(15, 0, 0, 3, 10, 2026);

	$today = pg_dashboard_v2_visitor_dates(0, 1, $now);
	pg_assert_same(array('2026-03-10'), $today['dates']);
	pg_assert_same(array('2026-03-09'), $today['compare']);
	pg_assert_true($today['hourly']);

	$week = pg_dashboard_v2_visitor_dates(1, 2, $now);
	pg_assert_same('2026-03-04', $week['dates'][0]);
	pg_assert_same('2026-03-10', $week['dates'][6]);
	pg_assert_same('2026-02-25', $week['before'][0]);
	pg_assert_same('2025-03-04', $week['compare'][0]);
	pg_assert_false($week['hourly']);

	pg_assert_same(30, count(pg_dashboard_v2_visitor_dates(2, 0, $now)['dates']));
	pg_assert_same(array(), pg_dashboard_v2_visitor_dates(2, 0, $now)['compare']);
}

function test_dashboard_v2_visitor_series()
{
	$stats = array(
		'2026-03-09' => array(1 => array('visitors' => 2, 'page_views' => 5), 20 => array('visitors' => 9, 'page_views' => 9)),
		'2026-03-10' => array(0 => array('visitors' => 3, 'page_views' => 4), 2 => array('visitors' => 1, 'page_views' => 1)),
	);

	pg_assert_same(array('visits' => array(0, 2, 0), 'views' => 5),
		pg_dashboard_v2_visitor_series($stats, array('2026-03-09'), true, 2), 'hourly up to an hour');

	pg_assert_same(array('visits' => array(11, 4, 0), 'views' => 19),
		pg_dashboard_v2_visitor_series($stats, array('2026-03-09', '2026-03-10', '2026-03-11'), false), 'daily');
}

function test_dashboard_v2_minute_bars()
{
	pg_assert_same(array(2, 0, 5), pg_dashboard_v2_minute_bars(array(
		array('minute' => 0, 'visits' => 2), array('minute' => 2, 'visits' => 5), array('minute' => 3, 'visits' => 9), array('minute' => -1, 'visits' => 1),
	), 3));
}
