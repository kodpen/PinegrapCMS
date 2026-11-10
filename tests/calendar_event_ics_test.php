<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_calendar_event_ics() in includes/fn/calendar.php: the .ics
 * file a calendar event view page answers with ?icalendar=true.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function calendar_event_ics_test_event($fields = array())
{
	return array_merge(array(
		'id' => '7',
		'created_timestamp' => '1767225600',
		'all_day' => '0',
		'start_date_and_time' => '2026-11-09 10:00:00',
		'end_date_and_time' => '2026-11-09 12:00:00',
		'location' => 'Hall A',
		'name' => 'Open day',
		'short_description' => 'Tour',
	), $fields);
}

function test_calendar_event_ics_escapes_text_values()
{
	$ics = pg_calendar_event_ics(calendar_event_ics_test_event(array(
		'name' => 'Tea, cake; talk',
		'location' => "Main St 5, Floor 2",
		'short_description' => "First line\nATTACH:http://example.com/x\r\nThird \\ line",
	)), 'example.com', 'Events', '');

	pg_assert_true(strpos($ics, "\r\nSUMMARY:Tea\\, cake\; talk\r\n") !== false, 'summary escaped');
	pg_assert_true(strpos($ics, "\r\nLOCATION:Main St 5\\, Floor 2\r\n") !== false, 'location escaped');
	pg_assert_true(strpos($ics, "\r\nDESCRIPTION:First line\\nATTACH:http://example.com/x\\nThird \\\\ line\r\n") !== false, 'description on one line');
	pg_assert_same(0, preg_match('/\r\nATTACH:/', $ics), 'a line break in the description starts no property');
}

function test_calendar_event_ics_uses_crlf_and_folds_long_lines()
{
	$ics = pg_calendar_event_ics(calendar_event_ics_test_event(array(
		'short_description' => str_repeat('ğ', 60),
	)), 'example.com', 'Events', '');

	pg_assert_true(strpos($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n") === 0, 'starts with the calendar');
	pg_assert_same("END:VCALENDAR\r\n", substr($ics, -15), 'ends with CRLF');
	pg_assert_same(0, preg_match("/(?<!\r)\n/", $ics), 'no bare LF');

	foreach (explode("\r\n", $ics) as $line) {
		pg_assert_true(strlen($line) <= 75, 'line within 75 octets: ' . $line);
		pg_assert_true(preg_match('//u', $line) === 1, 'no UTF-8 character cut');
	}
}

function test_calendar_event_ics_keeps_timed_event_times()
{
	$ics = pg_calendar_event_ics(calendar_event_ics_test_event(), 'example.com', 'Events', '');

	pg_assert_true(strpos($ics, "\r\nDTSTART:20261109T100000\r\n") !== false, 'start');
	pg_assert_true(strpos($ics, "\r\nDTEND:20261109T120000\r\n") !== false, 'end');
	pg_assert_true(strpos($ics, "\r\nUID:71767225600\r\n") !== false, 'uid kept');
}

function test_calendar_event_ics_writes_all_day_event_as_dates()
{
	$ics = pg_calendar_event_ics(calendar_event_ics_test_event(array(
		'all_day' => '1',
		'start_date_and_time' => '2026-11-09 00:00:00',
		'end_date_and_time' => '2026-11-10 00:00:00',
	)), 'example.com', 'Events', '');

	// DTEND of a date is the day after the last day.
	pg_assert_true(strpos($ics, "\r\nDTSTART;VALUE=DATE:20261109\r\n") !== false, 'start date');
	pg_assert_true(strpos($ics, "\r\nDTEND;VALUE=DATE:20261111\r\n") !== false, 'end date exclusive');
}

function test_calendar_event_ics_keeps_the_recurrence_rule()
{
	$ics = pg_calendar_event_ics(calendar_event_ics_test_event(), 'example.com', 'Events', 'FREQ=WEEKLY;COUNT=4');

	pg_assert_true(strpos($ics, "\r\nRRULE:FREQ=WEEKLY;COUNT=4\r\nEND:VEVENT\r\n") !== false, 'rrule');
}

// DTSTAMP is a UTC time (RFC 5545 3.8.7.2): the start read in the site's
// zone, written with Z, while DTSTART and DTEND keep the local times.
function test_calendar_event_ics_stamp_is_utc()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	$summer = pg_calendar_event_ics(calendar_event_ics_test_event(array(
		'start_date_and_time' => '2026-07-15 10:00:00',
		'end_date_and_time' => '2026-07-15 12:00:00',
	)), 'example.com', 'Events', '');
	$winter = pg_calendar_event_ics(calendar_event_ics_test_event(), 'example.com', 'Events', '');

	date_default_timezone_set($zone);

	pg_assert_true(strpos($summer, "\r\nDTSTAMP:20260715T080000Z\r\n") !== false, 'summer stamp in UTC');
	pg_assert_true(strpos($summer, "\r\nDTSTART:20260715T100000\r\n") !== false, 'start stays local');
	pg_assert_true(strpos($winter, "\r\nDTSTAMP:20261109T090000Z\r\n") !== false, 'winter stamp in UTC');
}
