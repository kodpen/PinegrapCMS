<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's personal cards (includes/dashboard/v2/
 * personal.php and cards/goals.php, pins.php, notes.php, perf.php): what a
 * card may store and how a goal turns into progress.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/personal.php');

function test_dashboard_v2_goals_clean()
{
	$metrics = array('visits', 'forms');
	$goals = pg_dashboard_v2_goals_clean(array(
		array('name' => "  October\tvisits ", 'metric' => 'visits', 'target' => 1500, 'span' => 'month'),
		array('name' => 'Forms', 'metric' => 'forms', 'target' => '20', 'span' => 'week'),
		array('name' => 'Orders', 'metric' => 'orders', 'target' => 5, 'span' => 'week'),
		array('name' => '', 'metric' => 'visits', 'target' => 5),
		array('name' => 'Zero', 'metric' => 'visits', 'target' => 0),
		array('name' => 'Half', 'metric' => 'visits', 'target' => 2.5),
		array('name' => 'Odd span', 'metric' => 'visits', 'target' => 3, 'span' => 'year'),
		array('name' => 'Fourth', 'metric' => 'visits', 'target' => 3),
	), $metrics);

	pg_assert_same(3, count($goals), 'at most three, the bad ones dropped');
	pg_assert_same(array('name' => 'October visits', 'metric' => 'visits', 'target' => 1500, 'span' => 'month'), $goals[0]);
	pg_assert_same(20, $goals[1]['target'], 'a target typed as text');
	pg_assert_same('week', $goals[1]['span']);
	pg_assert_same('Odd span', $goals[2]['name'], 'a metric the person may not count is dropped');
	pg_assert_same('month', $goals[2]['span'], 'an unknown span is a month');
	pg_assert_same(array(), pg_dashboard_v2_goals_clean('x', $metrics));
	pg_assert_same(60, mb_strlen(pg_dashboard_v2_goals_clean(array(array('name' => str_repeat('ğ', 90), 'metric' => 'forms', 'target' => 1)), $metrics)[0]['name']));
}

function test_dashboard_v2_pins_clean()
{
	$pins = pg_dashboard_v2_pins_clean(array(
		array('t' => 'page', 'id' => 12, 'l' => 'Home'),
		array('t' => 'page', 'id' => '12', 'l' => 'Home again'),
		array('t' => 'screen', 'u' => 'view_orders.php?filter=open', 'l' => 'Open orders', 'i' => 'bi-receipt'),
		array('t' => 'screen', 'u' => 'https://evil.example/x.php', 'l' => 'Away'),
		array('t' => 'screen', 'u' => '//evil.example/x.php', 'l' => 'Away'),
		array('t' => 'screen', 'u' => '../config.php', 'l' => 'Up'),
		array('t' => 'screen', 'u' => 'view_pages.php"onclick=x', 'l' => 'Quote'),
		array('t' => 'screen', 'u' => 'view_pages.php', 'l' => 'Pages', 'i' => 'bi-x" onmouseover="y'),
		array('t' => 'file', 'id' => 3, 'l' => 'Unknown type'),
		array('t' => 'product', 'id' => -1, 'l' => 'Bad id'),
		array('t' => 'product', 'id' => 4, 'l' => ''),
	));

	pg_assert_same(3, count($pins), 'repeats, foreign addresses and unknown types dropped');
	pg_assert_same(array('t' => 'page', 'id' => 12, 'l' => 'Home'), $pins[0]);
	pg_assert_same('view_orders.php?filter=open', $pins[1]['u']);
	pg_assert_same('bi-window', $pins[2]['i'], 'a bad icon takes the default');

	pg_assert_same(array('url' => 'edit_page.php?id=12', 'icon' => 'bi-file-earmark-text'), pg_dashboard_v2_pin_target($pins[0]));
	pg_assert_same(array('url' => 'view_orders.php?filter=open', 'icon' => 'bi-receipt'), pg_dashboard_v2_pin_target($pins[1]));

	$many = array();
	for ($i = 1; $i <= 12; $i++) {
		$many[] = array('t' => 'product', 'id' => $i, 'l' => 'P' . $i);
	}
	pg_assert_same(PG_DASHBOARD_V2_MAX_PINS, count(pg_dashboard_v2_pins_clean($many)));
}

function test_dashboard_v2_notes_clean()
{
	pg_assert_same("one\ntwo\n\tthree", pg_dashboard_v2_notes_clean("one\r\ntwo\r\tthree\x07"));
	pg_assert_same('', pg_dashboard_v2_notes_clean(array('x')));
	pg_assert_same(2000, mb_strlen(pg_dashboard_v2_notes_clean(str_repeat('ş', 2500))));
}

function test_dashboard_v2_goal_span()
{
	// Thursday 2026-10-15 12:00 in the runner's time zone.
	$now = mktime(12, 0, 0, 10, 15, 2026);
	$week = pg_dashboard_v2_goal_span('week', $now);
	pg_assert_same('2026-10-12 00:00', date('Y-m-d H:i', $week['start']), 'Monday');
	pg_assert_same('2026-10-19 00:00', date('Y-m-d H:i', $week['end']));
	$month = pg_dashboard_v2_goal_span('month', $now);
	pg_assert_same('2026-10-01 00:00', date('Y-m-d H:i', $month['start']));
	pg_assert_same('2026-11-01 00:00', date('Y-m-d H:i', $month['end']));
}

// Measured against the share of the span gone by.
function test_dashboard_v2_goal_progress()
{
	pg_assert_same(array('pct' => 40, 'ring' => 0.4, 'state' => 'ahead'), pg_dashboard_v2_goal_progress(600, 1500, 0.1));
	pg_assert_same('behind', pg_dashboard_v2_goal_progress(600, 1500, 0.9)['state']);
	$over = pg_dashboard_v2_goal_progress(1800, 1500, 0.5);
	pg_assert_same(120, $over['pct'], 'past the target');
	pg_assert_same(1, (int) $over['ring'], 'the ring stops full');
	pg_assert_same('done', $over['state']);
	pg_assert_same(500, pg_dashboard_v2_goal_progress(5, 0, 0)['pct'], 'a zero target counts as one');
}
