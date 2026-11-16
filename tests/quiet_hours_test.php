<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's quiet hours (includes/fn/quiet_hours.php): a range
 * over midnight, the days the clock changes, a start equal to the end, the
 * days off, and a value that is no setting.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/quiet_hours.php');

// A local moment in a zone, as a timestamp.
function quiet_hours_test_at($local, $zone)
{
	$date = new DateTime($local, new DateTimeZone($zone));

	return $date->getTimestamp();
}

function test_quiet_hours_range_over_midnight()
{
	$setting = '{"start":"22:00","end":"07:00","off_days":0}';
	$zone = 'Europe/Istanbul';

	pg_assert_same(8 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-11 23:00', $zone)), 'Wednesday 23:00 waits until 07:00');
	pg_assert_same(1800, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 06:30', $zone)));
	pg_assert_same(0, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 07:00', $zone)), 'the end is no longer quiet');
	pg_assert_same(0, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 12:00', $zone)));
	pg_assert_same(9 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 22:00', $zone)), 'the start is quiet');
}

function test_quiet_hours_range_within_a_day()
{
	$setting = array('start' => '12:00', 'end' => '13:30');

	pg_assert_same(3600, pg_quiet_hours_left($setting, 'UTC', 31, quiet_hours_test_at('2026-11-12 12:30', 'UTC')));
	pg_assert_same(0, pg_quiet_hours_left($setting, 'UTC', 31, quiet_hours_test_at('2026-11-12 14:00', 'UTC')));
}

function test_quiet_hours_keep_their_clock_times_when_the_clock_changes()
{
	$setting = '{"start":"22:00","end":"07:00"}';
	$zone = 'Europe/Berlin';

	// 29 March 2026: the clocks go forward an hour, so the night is 7 hours.
	pg_assert_same(7 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-03-28 23:00', $zone)));
	// 25 October 2026: the clocks go back an hour, so the night is 9 hours.
	pg_assert_same(9 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-10-24 23:00', $zone)));
}

function test_quiet_hours_equal_start_and_end_are_off()
{
	pg_assert_same(null, pg_quiet_hours_decode('{"start":"09:00","end":"09:00","off_days":1}'));
	pg_assert_same(0, pg_quiet_hours_left('{"start":"09:00","end":"09:00","off_days":1}', 'UTC', 31, quiet_hours_test_at('2026-11-14 09:00', 'UTC')), 'not even a day off is quiet');
}

function test_quiet_hours_days_off()
{
	$zone = 'Europe/Istanbul';
	$off = '{"start":"22:00","end":"07:00","off_days":1}';

	// 14 November 2026 is a Saturday; Monday to Friday are the working days.
	pg_assert_same(43 * 3600, pg_quiet_hours_left($off, $zone, 31, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'Saturday noon waits until Monday 07:00');
	pg_assert_same(56 * 3600, pg_quiet_hours_left($off, $zone, 31, quiet_hours_test_at('2026-11-13 23:00', $zone)), 'Friday night runs into the weekend');
	pg_assert_same(0, pg_quiet_hours_left('{"start":"22:00","end":"07:00","off_days":0}', $zone, 31, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'without off_days a Saturday noon is not quiet');
	pg_assert_same(0, pg_quiet_hours_left($off, $zone, 127, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'a person who works every day has no day off');
	pg_assert_same(0, pg_quiet_hours_left($off, $zone, 0, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'no working day known: none is off');
}

function test_quiet_hours_a_value_that_is_no_setting()
{
	foreach (array(null, '', 'nope', '{"start":"25:00","end":"07:00"}', '{"start":"22:00"}', '{"start":"7:00","end":"08:00"}') as $value) {
		pg_assert_same(null, pg_quiet_hours_decode($value), var_export($value, true));
		pg_assert_same(0, pg_quiet_hours_left($value, 'UTC', 31, 1763200000), var_export($value, true));
	}

	pg_assert_same(array('start' => '22:00', 'end' => '07:00', 'off_days' => 1), pg_quiet_hours_decode('{"start":"22:00","end":"07:00","off_days":"1"}'));
	pg_assert_same(8 * 3600, pg_quiet_hours_left('{"start":"22:00","end":"07:00"}', 'Not/AZone', 31, quiet_hours_test_at('2026-11-11 23:00', 'UTC')), 'an unknown zone counts as UTC');
}
