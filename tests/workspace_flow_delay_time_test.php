<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the delay box's time of day (includes/workspace/flow_runs.php):
 * the first moment at or after the wait's end whose time of day in the
 * site's zone is the one set, across the day's turn, zones and clock
 * changes; "if the moment has passed" in until mode; boxes drawn before keep
 * their way.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

function test_workspace_flow_delay_time_clock()
{
	pg_assert_same('09:00', ws_flow_delay_clock('09:00'));
	pg_assert_same('09:05', ws_flow_delay_clock('9:05'), 'a single-digit hour gains its 0');
	pg_assert_same('23:59', ws_flow_delay_clock(' 23:59 '));
	pg_assert_same('', ws_flow_delay_clock('24:00'), 'no such hour');
	pg_assert_same('', ws_flow_delay_clock('12:60'), 'no such minute');
	pg_assert_same('', ws_flow_delay_clock('9am'));
	pg_assert_same('', ws_flow_delay_clock(array('09:00')));
}

// A box drawn before the setting keeps its way: no time, and go on.
function test_workspace_flow_delay_time_defaults()
{
	$config = ws_flow_delay_normalize(array('mode' => 'until', 'at' => '{{trigger.start_at}}', 'amount' => 1, 'unit' => 'days'));

	pg_assert_same('', $config['time']);
	pg_assert_same('go', $config['passed']);
	pg_assert_same('end', ws_flow_delay_normalize(array('passed' => 'end'))['passed']);
	pg_assert_same('go', ws_flow_delay_normalize(array('passed' => 'stop'))['passed'], 'an unknown choice goes on');
	pg_assert_same('', ws_flow_delay_normalize(array('time' => '25:00'))['time']);
}

function test_workspace_flow_delay_time_same_day_and_next()
{
	$zone = 'Europe/Istanbul';
	$at = function ($text) use ($zone) {
		return (new DateTime($text, new DateTimeZone($zone)))->getTimestamp();
	};

	pg_assert_same($at('2026-11-20 09:00'), ws_flow_delay_at_time($at('2026-11-20 07:15'), '09:00', $zone), 'later that day');
	pg_assert_same($at('2026-11-20 09:00'), ws_flow_delay_at_time($at('2026-11-20 09:00'), '09:00', $zone), 'the moment itself is at it');
	pg_assert_same($at('2026-11-21 09:00'), ws_flow_delay_at_time($at('2026-11-20 09:00:30'), '09:00', $zone), 'seconds past: the next day');
	pg_assert_same($at('2026-11-21 09:00'), ws_flow_delay_at_time($at('2026-11-20 18:40'), '09:00', $zone), 'passed that day: the next');
	pg_assert_same($at('2026-12-01 00:01'), ws_flow_delay_at_time($at('2026-11-30 23:59'), '00:01', $zone), 'over the month\'s turn');
	pg_assert_same($at('2027-01-01 08:00'), ws_flow_delay_at_time($at('2026-12-31 22:00'), '08:00', $zone), 'over the year\'s turn');
	pg_assert_same($at('2026-11-20 07:15'), ws_flow_delay_at_time($at('2026-11-20 07:15'), '', $zone), 'no time of day');
}

// The day is the zone's day, not UTC's: 01:00 UTC is already 04:00 in
// Istanbul, but still the evening before in New York.
function test_workspace_flow_delay_time_zones()
{
	$moment = gmmktime(1, 0, 0, 11, 20, 2026);

	pg_assert_same(gmmktime(6, 0, 0, 11, 20, 2026), ws_flow_delay_at_time($moment, '09:00', 'Europe/Istanbul'), 'Istanbul 09:00 is 06:00 UTC');
	pg_assert_same(gmmktime(14, 0, 0, 11, 20, 2026), ws_flow_delay_at_time($moment, '09:00', 'America/New_York'), 'New York\'s 09:00 is 14:00 UTC');
	pg_assert_same(gmmktime(1, 30, 0, 11, 20, 2026), ws_flow_delay_at_time($moment, '20:30', 'America/New_York'), 'New York\'s evening before');
	pg_assert_same(gmmktime(9, 0, 0, 11, 20, 2026), ws_flow_delay_at_time($moment, '09:00', 'UTC'));

	$saved = date_default_timezone_get();
	date_default_timezone_set('Europe/Istanbul');
	pg_assert_same(gmmktime(6, 0, 0, 11, 20, 2026), ws_flow_delay_at_time($moment, '09:00'), 'no zone: the site\'s');
	date_default_timezone_set($saved);
}

// A clock change: the day is one hour short or long, the time of day stays.
function test_workspace_flow_delay_time_clock_change()
{
	$zone = new DateTimeZone('Europe/Berlin');
	$at = function ($text) use ($zone) {
		return (new DateTime($text, $zone))->getTimestamp();
	};

	pg_assert_same($at('2026-03-29 09:00'), ws_flow_delay_at_time($at('2026-03-28 12:00'), '09:00', 'Europe/Berlin'), 'over the spring change');
	pg_assert_same($at('2026-10-25 09:00'), ws_flow_delay_at_time($at('2026-10-24 12:00'), '09:00', 'Europe/Berlin'), 'over the autumn change');
	pg_assert_same($at('2026-03-29 03:30'), ws_flow_delay_at_time($at('2026-03-29 00:30'), '02:30', 'Europe/Berlin'), 'a skipped time is read as the zone reads it');
}

// For a time: its length later, moved on to the time of day; 0 with a time
// waits for the next one.
function test_workspace_flow_delay_time_for_mode()
{
	$saved = date_default_timezone_get();
	date_default_timezone_set('UTC');
	$from = gmmktime(10, 0, 0, 11, 20, 2026);

	pg_assert_same(gmmktime(9, 0, 0, 11, 22, 2026), ws_flow_delay_until(array('amount' => 1, 'unit' => 'days', 'time' => '09:00'), $from), 'a day later, then the next 09:00');
	pg_assert_same(gmmktime(12, 0, 0, 11, 20, 2026), ws_flow_delay_until(array('amount' => 1, 'unit' => 'hours', 'time' => '12:00'), $from));
	pg_assert_same(gmmktime(9, 0, 0, 11, 21, 2026), ws_flow_delay_until(array('amount' => 0, 'unit' => 'days', 'time' => '09:00'), $from), '0: the next 09:00');
	pg_assert_same(gmmktime(10, 0, 0, 11, 21, 2026), ws_flow_delay_until(array('amount' => 1, 'unit' => 'days'), $from), 'no time of day: as before');

	pg_assert_same(array(), ws_flow_delay_problems(array('amount' => 0, 'time' => '09:00'), 'Wait'), 'a time of day alone is enough');
	pg_assert_same(1, count(ws_flow_delay_problems(array('amount' => 0), 'Wait')), 'nothing to wait');
	date_default_timezone_set($saved);
}

// Until a date: the date less the time before it, moved on to the time of
// day; whether the moment has passed.
function test_workspace_flow_delay_time_until_mode()
{
	$saved = date_default_timezone_get();
	date_default_timezone_set('UTC');
	$now = gmmktime(12, 0, 0, 11, 6, 2026);
	$until = function ($at, $amount, $time, $passed = '') use ($now) {
		return ws_flow_delay_until_date(array('mode' => 'until', 'at' => $at, 'amount' => $amount, 'unit' => 'days', 'time' => $time, 'passed' => $passed), $at, $now);
	};

	$date = $until('2026-11-20T15:00:00Z', 1, '09:00');
	pg_assert_same(gmmktime(9, 0, 0, 11, 20, 2026), $date['until'], 'a day before 15:00 is 15:00 the day before, then the next 09:00');
	pg_assert_same(false, $date['passed']);

	$date = $until('2026-11-20T08:00:00Z', 1, '09:00');
	pg_assert_same(gmmktime(9, 0, 0, 11, 19, 2026), $date['until'], 'the same day\'s 09:00');

	$date = $until('2026-11-06T09:00:00Z', 0, '');
	pg_assert_same($now, $date['until'], 'passed: at once');
	pg_assert_same(true, $date['passed']);

	$date = $until('2026-11-06T09:00:00Z', 0, '18:00');
	pg_assert_same(gmmktime(18, 0, 0, 11, 6, 2026), $date['until'], 'the time of day brings a passed date back ahead');
	pg_assert_same(false, $date['passed']);

	$date = $until('2026-11-06T11:00:00Z', 0, '11:00');
	pg_assert_same(true, $date['passed'], 'the moment is an hour ago');
	date_default_timezone_set($saved);
}
