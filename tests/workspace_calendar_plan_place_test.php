<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for placing plan items on the Work Calendar's days
 * (ws_calendar_plan_place(), includes/workspace/calendar.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calendar.php');

function workspace_calendar_plan_place_test_days()
{
	$days = array();

	foreach (array('2026-11-09', '2026-11-10', '2026-11-11') as $date) {
		$days[$date] = array('date' => $date, 'events' => array());
	}

	return $days;
}

function workspace_calendar_plan_place_test_event($starts, $ends, $all_day)
{
	return array(
		'id' => 5, 'kind' => 'meeting', 'title' => 'Review', 'scope' => 'people', 'people' => array(3),
		'starts_at' => strtotime($starts), 'ends_at' => strtotime($ends), 'all_day' => $all_day ? 1 : 0,
	);
}

function workspace_calendar_plan_place_test_dates($days)
{
	$dates = array();

	foreach ($days as $date => $day) {
		foreach ($day['events'] as $event) {
			$dates[] = $date . ' ' . $event['time'];
		}
	}

	return $dates;
}

function test_workspace_calendar_plan_place_midnight_end()
{
	$days = ws_calendar_plan_place(workspace_calendar_plan_place_test_days(), workspace_calendar_plan_place_test_event('2026-11-09 22:00:00', '2026-11-10 00:00:00', false));

	pg_assert_same(array('2026-11-09 22:00'), workspace_calendar_plan_place_test_dates($days), 'a meeting until midnight stays on its own day');
}

function test_workspace_calendar_plan_place_overnight()
{
	$days = ws_calendar_plan_place(workspace_calendar_plan_place_test_days(), workspace_calendar_plan_place_test_event('2026-11-09 22:00:00', '2026-11-10 01:00:00', false));

	pg_assert_same(array('2026-11-09 22:00', '2026-11-10 00:00'), workspace_calendar_plan_place_test_dates($days), 'a meeting past midnight is on both days');
}

function test_workspace_calendar_plan_place_all_day()
{
	$days = ws_calendar_plan_place(workspace_calendar_plan_place_test_days(), workspace_calendar_plan_place_test_event('2026-11-09 00:00:00', '2026-11-10 23:59:59', true));

	pg_assert_same(array('2026-11-09 ', '2026-11-10 '), workspace_calendar_plan_place_test_dates($days), 'an all-day item covers its days and no more');
}
