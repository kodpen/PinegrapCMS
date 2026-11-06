<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the days off read from a holiday calendar address
 * (ws_ical_events() in includes/workspace/workdays.php): a moved occurrence
 * of a yearly day shares the day's UID and must not replace it, and a
 * cancelled day is no day off.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/workdays.php');

// A company calendar as Google and Outlook export it: a yearly day off with
// one year moved (same UID, RECURRENCE-ID), and a one-off day that was
// cancelled.
function ws_holiday_feed_test_sample()
{
	return implode("\r\n", array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'BEGIN:VEVENT',
		'UID:founding@example.com',
		'DTSTART;VALUE=DATE:20260512',
		'DTEND;VALUE=DATE:20260513',
		'RRULE:FREQ=YEARLY',
		'SUMMARY:Founding day',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:founding@example.com',
		'RECURRENCE-ID;VALUE=DATE:20270512',
		'DTSTART;VALUE=DATE:20270514',
		'DTEND;VALUE=DATE:20270515',
		'SUMMARY:Founding day',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:picnic@example.com',
		'DTSTART;VALUE=DATE:20261120',
		'DTEND;VALUE=DATE:20261121',
		'STATUS:CANCELLED',
		'SUMMARY:Company picnic',
		'END:VEVENT',
		'END:VCALENDAR',
	));
}

// The sync keys the rows by uid: every event must have its own, and the yearly day must stay yearly.
function test_workspace_holiday_feed_moved_occurrence_keeps_the_yearly_day()
{
	$by_uid = array();

	foreach (ws_ical_events(ws_holiday_feed_test_sample(), false) as $event) {
		$by_uid[$event['uid']] = $event;
	}

	pg_assert_true(isset($by_uid['founding@example.com']), 'the yearly day is read');
	pg_assert_true($by_uid['founding@example.com']['yearly'], 'the moved year does not replace the yearly day');
	pg_assert_same('2026-05-12', $by_uid['founding@example.com']['start'], 'the yearly day keeps its own date');
	pg_assert_true(isset($by_uid['founding@example.com|20270512']), 'the moved year has a uid of its own');
	pg_assert_same('2027-05-14', $by_uid['founding@example.com|20270512']['start'], 'the moved year is on its new day');
	pg_assert_false($by_uid['founding@example.com|20270512']['yearly'], 'the moved year is a one-off');
}

function test_workspace_holiday_feed_cancelled_day_is_no_day_off()
{
	foreach (ws_ical_events(ws_holiday_feed_test_sample(), false) as $event) {
		pg_assert_false($event['uid'] === 'picnic@example.com', 'a cancelled day is left out');
	}
}
