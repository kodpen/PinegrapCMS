<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Sends, the calendar view (includes/fn/messages_calendar.php):
 * the view read from the address, the days drawn, the steps, a flow's runs in
 * a period, the same-group-same-hour mark and the bucketing by day and hour.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_calendar.php';

function test_messages_calendar_filters()
{
	pg_assert_same(array('view' => 'week', 'date' => '2026-11-14'), pg_messages_calendar_filters(array(), '2026-11-14'), 'week of today by default');
	pg_assert_same(array('view' => 'month', 'date' => '2026-02-03'), pg_messages_calendar_filters(array('cal' => 'month', 'date' => '2026-02-03'), '2026-11-14'), 'month and a day');
	pg_assert_same('2026-11-14', pg_messages_calendar_filters(array('date' => '2026-02-30'), '2026-11-14')['date'], 'a date that does not exist');
	pg_assert_same('2026-11-14', pg_messages_calendar_filters(array('date' => array('x')), '2026-11-14')['date'], 'an array');
	pg_assert_same('week', pg_messages_calendar_filters(array('cal' => 'year'), '2026-11-14')['view'], 'an unknown view');
}

function test_messages_calendar_days()
{
	$week = pg_messages_calendar_days('week', '2026-11-14');

	pg_assert_same(7, count($week), 'a week has seven days');
	pg_assert_same('2026-11-09', $week[0]['date'], 'it starts on Monday');
	pg_assert_same('2026-11-15', $week[6]['date'], 'it ends on Sunday');
	pg_assert_true($week[5]['weekend'] && !$week[4]['weekend'], 'Saturday is a weekend, Friday is not');

	$month = pg_messages_calendar_days('month', '2026-11-14');

	pg_assert_same('2026-10-26', $month[0]['date'], 'the month starts on the Monday of its first week');
	pg_assert_same('2026-12-06', end($month)['date'], 'and ends on the Sunday of its last');
	pg_assert_same(0, count($month) % 7, 'whole weeks');
	pg_assert_same(30, count(array_filter($month, function ($day) {
		return $day['in_range'];
	})), 'November has thirty days in range');
}

function test_messages_calendar_step()
{
	pg_assert_same('2026-11-07', pg_messages_calendar_step('week', '2026-11-14', -1), 'a week back');
	pg_assert_same('2026-11-21', pg_messages_calendar_step('week', '2026-11-14', 1), 'a week on');
	pg_assert_same('2026-02-01', pg_messages_calendar_step('month', '2026-01-31', 1), 'a month on from the 31st is February, not March');
	pg_assert_same('2025-12-01', pg_messages_calendar_step('month', '2026-01-15', -1), 'a month back over the year');
}

function test_messages_calendar_flow_times()
{
	$hourly = function ($after) {
		return ((int) floor($after / 3600) + 1) * 3600;
	};

	pg_assert_same(array(7200, 10800), pg_messages_calendar_flow_times(7200, 3600, 10800, 100, $hourly), 'the kept run, then each next one inside the period');
	pg_assert_same(array(3600, 7200), pg_messages_calendar_flow_times(1, 3000, 7200, 100, $hourly), 'a kept run before the period starts from the period');
	pg_assert_same(array(3600, 7200), pg_messages_calendar_flow_times(3600, 0, 100000, 2, $hourly), 'the cap');
	pg_assert_same(array(3600), pg_messages_calendar_flow_times(3600, 0, 100000, 100, function () {
		return 0;
	}), 'a run that does not repeat');
	pg_assert_same(array(3600), pg_messages_calendar_flow_times(3600, 0, 100000, 100, function () {
		return 3600;
	}), 'a rule that does not move on stops');
	pg_assert_same(array(), pg_messages_calendar_flow_times(200000, 0, 100000, 100, $hourly), 'a run after the period');
}

function test_messages_calendar_clashes_mark_same_group_same_hour()
{
	$at = strtotime('2026-11-16 09:05:00');
	$entries = pg_messages_calendar_clashes(array(
		array('at' => $at, 'groups' => array(4 => 'Customers', 5 => 'Staff')),
		array('at' => $at + 50 * 60, 'groups' => array(4 => 'Customers')),
		array('at' => $at + 60 * 60, 'groups' => array(4 => 'Customers')),
		array('at' => $at, 'groups' => array(6 => 'Press')),
		array('at' => $at),
	));

	pg_assert_same(array('Customers'), $entries[0]['clash'], 'the shared group only');
	pg_assert_same(array('Customers'), $entries[1]['clash'], 'later in the same hour');
	pg_assert_same(array(), $entries[2]['clash'], 'the next hour is not the same hour');
	pg_assert_same(array(), $entries[3]['clash'], 'another group');
	pg_assert_same(array(), $entries[4]['clash'], 'a send without a group');
}

function test_messages_calendar_bucket_by_day_and_hour()
{
	$days = pg_messages_calendar_days('week', '2026-11-14');
	$buckets = pg_messages_calendar_bucket(array(
		array('at' => strtotime('2026-11-10 14:30:00'), 'title' => 'B'),
		array('at' => strtotime('2026-11-10 09:00:00'), 'title' => 'A'),
		array('at' => strtotime('2026-11-10 14:30:00'), 'title' => 'A'),
		array('at' => strtotime('2026-11-20 09:00:00'), 'title' => 'Out'),
	), $days);

	pg_assert_same(array('2026-11-10'), array_keys($buckets), 'only days with a send, none outside the week');
	pg_assert_same(array('09', '14'), array_map('strval', array_keys($buckets['2026-11-10'])), 'hours in order');
	pg_assert_same(array('A', 'B'), array_column($buckets['2026-11-10']['14'], 'title'), 'same time ordered by title');
}

function test_messages_calendar_wired_on_the_sends_tab()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/messages_sends.php');

	pg_assert_true(strpos($screen, 'pg_messages_calendar_body(') !== false, 'the Sends tab draws the calendar');
	pg_assert_true(strpos($screen, 'pg_messages_calendar_switch_html(false)') !== false, 'the list offers the switch');

	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/messages_calendar.php');

	pg_assert_true(preg_match('/\b(INSERT|UPDATE|DELETE)\b/', $module) === 0, 'the calendar writes nothing');
}
