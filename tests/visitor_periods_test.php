<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visitors overview's period (includes/fn/visitor_periods.php):
 * the days of every preset and the period each is compared with, Monday
 * weeks, a daylight saving change, a custom range, the same-hour cut of a
 * period ending today, bad input and the links that keep a period.
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
 * Runs a check with the clock of a time zone, the test runner's put back.
 */
function visitor_periods_test_in_zone($zone, $check)
{
	$before = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		$check();
	} finally {
		date_default_timezone_set($before);
	}
}

function test_visitor_period_presets_cover_their_days()
{
	visitor_periods_test_in_zone('Europe/Istanbul', function () {

		// A Tuesday afternoon.
		$now = strtotime('2026-11-03 15:20:00');

		$expect = array(
			'today'      => array('2026-11-03', '2026-11-03', 1, 'hour', '2026-11-02', '2026-11-02'),
			'yesterday'  => array('2026-11-02', '2026-11-02', 1, 'hour', '2026-11-01', '2026-11-01'),
			'7d'         => array('2026-10-28', '2026-11-03', 7, 'day', '2026-10-21', '2026-10-27'),
			'30d'        => array('2026-10-05', '2026-11-03', 30, 'day', '2026-09-05', '2026-10-04'),
			'90d'        => array('2026-08-06', '2026-11-03', 90, 'week', '2026-05-08', '2026-08-05'),
			'month'      => array('2026-11-01', '2026-11-03', 3, 'day', '2026-10-01', '2026-10-03'),
			'last_month' => array('2026-10-01', '2026-10-31', 31, 'day', '2026-09-01', '2026-09-30'),
		);

		foreach ($expect as $key => $want) {
			$period = pg_visitor_period(array('p' => $key), $now);

			pg_assert_same($key, $period['key'], $key);
			pg_assert_same($want[0], $period['from'], $key . ' from');
			pg_assert_same($want[1], $period['to'], $key . ' to');
			pg_assert_same($want[2], $period['days'], $key . ' days');
			pg_assert_same($want[3], $period['grain'], $key . ' grain');
			pg_assert_same($want[4], $period['previous_from'], $key . ' previous from');
			pg_assert_same($want[5], $period['previous_to'], $key . ' previous to');
			pg_assert_same(strtotime($want[0] . ' 00:00:00'), $period['from_ts'], $key . ' starts at midnight');
			pg_assert_true($period['compare'], $key . ' compares by default');
		}

		$yesterday = pg_visitor_period(array('p' => 'yesterday'), $now);
		pg_assert_same(strtotime('2026-11-03 00:00:00'), $yesterday['to_ts'], 'a past day ends at its midnight');
		pg_assert_same(strtotime('2026-11-02 00:00:00'), $yesterday['previous_to_ts'], 'and is set against a whole day');
	});
}

function test_visitor_period_month_against_month_before()
{
	visitor_periods_test_in_zone('UTC', function () {

		// On 31 March this month runs 31 days; February has 28.
		$period = pg_visitor_period(array('p' => 'month'), strtotime('2026-03-31 10:00:00'));
		pg_assert_same('2026-03-01', $period['from']);
		pg_assert_same('2026-02-01', $period['previous_from']);
		pg_assert_same('2026-02-28', $period['previous_to'], 'cut to the end of the shorter month');

		// Last month in January is December of the year before.
		$period = pg_visitor_period(array('p' => 'last_month'), strtotime('2026-01-15 10:00:00'));
		pg_assert_same('2025-12-01', $period['from']);
		pg_assert_same('2025-12-31', $period['to']);
		pg_assert_same('2025-11-01', $period['previous_from']);
		pg_assert_same('2025-11-30', $period['previous_to']);
		pg_assert_same(30, pg_visitor_period_day_count($period['previous_from'], $period['previous_to']));
	});
}

function test_visitor_period_ending_today_cuts_the_earlier_one_at_the_same_hour()
{
	visitor_periods_test_in_zone('Europe/Istanbul', function () {

		$now = strtotime('2026-11-03 09:45:30');

		$today = pg_visitor_period(array('p' => 'today'), $now);
		pg_assert_same($now, $today['to_ts'], 'today ends now');
		pg_assert_same(strtotime('2026-11-02 00:00:00'), $today['previous_from_ts']);
		pg_assert_same(strtotime('2026-11-02 09:45:30'), $today['previous_to_ts'], 'yesterday up to the same time');

		$week = pg_visitor_period(array('p' => '7d'), $now);
		pg_assert_same(strtotime('2026-10-27 09:45:30'), $week['previous_to_ts'], 'the last day of the week before too');

		$custom = pg_visitor_period(array('p' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10'), $now);
		pg_assert_same(strtotime('2026-10-11 00:00:00'), $custom['to_ts'], 'a range in the past is whole days');
		pg_assert_same(strtotime('2026-10-01 00:00:00'), $custom['previous_to_ts']);
	});
}

function test_visitor_period_across_a_clock_change()
{
	visitor_periods_test_in_zone('Europe/Berlin', function () {

		// Clocks went back on 25 October 2026 at 03:00.
		$now = strtotime('2026-10-26 14:00:00');

		$period = pg_visitor_period(array('p' => '7d'), $now);
		pg_assert_same('2026-10-20', $period['from'], 'no day lost or doubled');
		pg_assert_same(7, $period['days']);
		pg_assert_same('2026-10-13', $period['previous_from']);
		pg_assert_same('2026-10-19', $period['previous_to']);

		$day = pg_visitor_period(array('p' => 'yesterday'), $now);
		pg_assert_same('2026-10-25', $day['from']);
		pg_assert_same(25 * 3600, $day['to_ts'] - $day['from_ts'], 'the long day has 25 hours');

		// Clocks went forward on 29 March 2026: today at 14:00 is set against
		// 14:00 the day before, read on the wall clock.
		$today = pg_visitor_period(array('p' => 'today'), strtotime('2026-03-30 14:00:00'));
		pg_assert_same('14:00', date('H:i', $today['previous_to_ts']));
		pg_assert_same('2026-03-29', date('Y-m-d', $today['previous_to_ts']));
	});
}

function test_visitor_period_custom_range()
{
	visitor_periods_test_in_zone('UTC', function () {

		$now = strtotime('2026-11-03 12:00:00');

		$period = pg_visitor_period(array('p' => 'custom', 'from' => '2026-09-01', 'to' => '2026-10-15', 'compare' => '0'), $now);
		pg_assert_same('custom', $period['key']);
		pg_assert_same(45, $period['days']);
		pg_assert_same('week', $period['grain']);
		pg_assert_same('2026-07-18', $period['previous_from']);
		pg_assert_same('2026-08-31', $period['previous_to']);
		pg_assert_false($period['compare'], 'compare=0 turns comparing off');

		$swapped = pg_visitor_period(array('p' => 'custom', 'from' => '2026-10-15', 'to' => '2026-09-01'), $now);
		pg_assert_same('2026-09-01', $swapped['from'], 'reversed dates are swapped');
		pg_assert_same('2026-10-15', $swapped['to']);

		$future = pg_visitor_period(array('p' => 'custom', 'from' => '2026-11-01', 'to' => '2027-01-01'), $now);
		pg_assert_same('2026-11-03', $future['to'], 'a day after today becomes today');
		pg_assert_same($now, $future['to_ts']);

		$one = pg_visitor_period(array('p' => 'custom', 'to' => '2026-10-15'), $now);
		pg_assert_same('2026-10-15', $one['from'], 'one end is a one-day range');
		pg_assert_same('hour', $one['grain']);

		$long = pg_visitor_period(array('p' => 'custom', 'from' => '2025-01-01', 'to' => '2026-10-31'), $now);
		pg_assert_same('month', $long['grain']);
	});
}

function test_visitor_period_bad_input_falls_back_to_seven_days()
{
	visitor_periods_test_in_zone('UTC', function () {

		$now = strtotime('2026-11-03 12:00:00');

		$bad = array(
			array(),
			array('p' => ''),
			array('p' => 'year'),
			array('p' => array('7d')),
			array('p' => 'custom'),
			array('p' => 'custom', 'from' => '2026-02-30', 'to' => 'tomorrow'),
			array('p' => 'custom', 'from' => array('2026-10-01'), 'to' => '03/11/2026'),
			array('p' => 'custom', 'from' => '1900-01-01', 'to' => ''),
		);

		foreach ($bad as $index => $request) {
			$period = pg_visitor_period($request, $now);
			pg_assert_same('7d', $period['key'], 'case ' . $index);
			pg_assert_same('2026-10-28', $period['from'], 'case ' . $index);
			pg_assert_same('2026-11-03', $period['to'], 'case ' . $index);
		}

		pg_assert_same('7d', pg_visitor_period('p=today', $now)['key'], 'not an array');
		pg_assert_false(pg_visitor_period(array('p' => 'custom', 'compare' => '0'), $now)['compare'], 'the fallback keeps compare');
		pg_assert_true(pg_visitor_period(array('compare' => 'no'), $now)['compare'], 'only 0 turns it off');
	});
}

function test_visitor_period_weeks_start_on_monday()
{
	visitor_periods_test_in_zone('UTC', function () {

		pg_assert_same('2026-11-02', pg_visitor_period_bucket('2026-11-02', 'week'), 'a Monday is its own week');
		pg_assert_same('2026-11-02', pg_visitor_period_bucket('2026-11-08', 'week'), 'Sunday ends the week');
		pg_assert_same('2026-12-28', pg_visitor_period_bucket('2027-01-01', 'week'), 'across the year');
		pg_assert_same('2026-11-01', pg_visitor_period_bucket('2026-11-17', 'month'));
		pg_assert_same('2026-11-17', pg_visitor_period_bucket('2026-11-17', 'day'));

		$period = pg_visitor_period(array('p' => 'custom', 'from' => '2026-10-01', 'to' => '2026-11-08'), strtotime('2026-11-20 12:00:00'));
		$buckets = pg_visitor_period_buckets($period);
		pg_assert_same('week', $period['grain']);
		pg_assert_same('2026-09-28', $buckets[0], 'the first week starts before the range');
		pg_assert_same('2026-11-02', end($buckets));
		pg_assert_same(6, count($buckets));

		$today = pg_visitor_period(array('p' => 'today'), strtotime('2026-11-20 12:00:00'));
		$hours = pg_visitor_period_buckets($today);
		pg_assert_same(24, count($hours));
		pg_assert_same('00', $hours[0]);
		pg_assert_same('23', $hours[23]);

		$days = pg_visitor_period_buckets(pg_visitor_period(array('p' => '7d'), strtotime('2026-11-20 12:00:00')));
		pg_assert_same(array('2026-11-14', '2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20'), $days);
	});
}

function test_visitor_period_query_keeps_the_period()
{
	visitor_periods_test_in_zone('UTC', function () {

		$now = strtotime('2026-11-03 12:00:00');

		pg_assert_same('p=30d', pg_visitor_period_query(pg_visitor_period(array('p' => '30d'), $now)));

		$custom = pg_visitor_period(array('p' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30', 'compare' => '0'), $now);
		pg_assert_same('p=custom&from=2026-09-01&to=2026-09-30&compare=0', pg_visitor_period_query($custom));
		pg_assert_same('p=custom&from=2026-09-01&to=2026-09-30', pg_visitor_period_query($custom, array('compare' => true)));
		pg_assert_same('p=today&compare=0', pg_visitor_period_query($custom, array('p' => 'today')));
	});
}
