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
