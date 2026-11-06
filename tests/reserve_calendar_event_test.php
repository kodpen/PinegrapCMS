<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the calendar check of reserve_calendar_event.php. An event linked
 * to no calendar built "WHERE () AND (page_id = ?)", the query failed and the
 * visitor saw "Query failed." instead of the not-allowed message. The calendar
 * condition is now built by pg_calendar_event_view_calendar_condition(), which
 * answers null for no calendar, and the endpoint then denies without a query.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_reserve_calendar_event_condition_without_calendar_is_null()
{
	pg_assert_same(null, pg_calendar_event_view_calendar_condition(array()), 'no calendar gives no condition');
	pg_assert_same(null, pg_calendar_event_view_calendar_condition(array('', '0')), 'empty ids give no condition');
}

function test_reserve_calendar_event_condition_lists_calendars()
{
	pg_assert_same(
		array('calendar_id IN (?)', array('7')),
		pg_calendar_event_view_calendar_condition(array(7)),
		'one calendar'
	);
	pg_assert_same(
		array('calendar_id IN (?, ?)', array('3', '9')),
		pg_calendar_event_view_calendar_condition(array('3', 9, '3')),
		'two calendars, duplicates dropped'
	);
}

function test_reserve_calendar_event_endpoint_uses_condition()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/reserve_calendar_event.php');

	pg_assert_true(strpos($source, 'pg_calendar_event_view_calendar_condition(') !== false, 'endpoint does not build the condition with the helper');
	pg_assert_false(strpos($source, '" . $where_calendars . "') !== false, 'endpoint still joins an empty calendar list into WHERE ()');
}

// The calendar event view screen asks the same question as the reserve
// handler: an event in no calendar built "WHERE  AND (page_id = …)" there,
// and the visitor got "Query failed." instead of "not allowed in this view".
function test_calendar_event_view_screen_uses_calendar_condition()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_calendar_event_view_screen_content.php');
	pg_assert_contains('pg_calendar_event_view_calendar_condition(', $source, 'the view screen builds the condition with the shared helper');
	pg_assert_false(strpos($source, '$where_calendar_id') !== false, 'the hand-built OR list is gone');
}

// A recurring event that keeps separate spots per occurrence took any posted
// recurrence_number: one past the last occurrence, or one removed from the
// calendar, had no remaining spots row, so it offered the initial spots and
// the visitor paid for a date that does not take place.
function test_reserve_calendar_event_occurrence_must_exist()
{
	pg_assert_true(pg_calendar_reserve_occurrence_ok('0', 1, 5, 1, array()), 'the first occurrence');
	pg_assert_true(pg_calendar_reserve_occurrence_ok('5', 1, 5, 1, array()), 'the last occurrence');
	pg_assert_true(pg_calendar_reserve_occurrence_ok('', 1, 5, 1, array()), 'no number is the first occurrence');
	pg_assert_false(pg_calendar_reserve_occurrence_ok('6', 1, 5, 1, array()), 'past the last occurrence');
	pg_assert_false(pg_calendar_reserve_occurrence_ok('999', 1, 5, 1, array()), 'far past the last occurrence');
	pg_assert_false(pg_calendar_reserve_occurrence_ok('3', 1, 5, 1, array('3')), 'a removed occurrence');
	pg_assert_false(pg_calendar_reserve_occurrence_ok('0', 1, 5, 1, array(0)), 'a removed first occurrence');
	pg_assert_false(pg_calendar_reserve_occurrence_ok('-1', 1, 5, 1, array()), 'a negative number');
	pg_assert_false(pg_calendar_reserve_occurrence_ok('2x', 1, 5, 1, array()), 'text');
	pg_assert_false(pg_calendar_reserve_occurrence_ok(array('1'), 1, 5, 1, array()), 'an array');
}

// Shared spots and single events book the event itself (number 0), whatever
// occurrence the visitor opened.
function test_reserve_calendar_event_occurrence_shared_spots()
{
	pg_assert_true(pg_calendar_reserve_occurrence_ok('9', 1, 5, 0, array('9')), 'shared spots book the event');
	pg_assert_true(pg_calendar_reserve_occurrence_ok('3', 0, 0, 1, array()), 'a single event');
	pg_assert_false(pg_calendar_reserve_occurrence_ok('x', 0, 0, 0, array()), 'text is refused anyway');
}

function test_reserve_calendar_event_endpoint_checks_occurrence()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/reserve_calendar_event.php');
	pg_assert_contains('pg_calendar_reserve_occurrence_ok(', $source, 'the endpoint checks the occurrence');
}
