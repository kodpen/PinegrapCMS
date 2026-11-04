<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for bookings on a staff member's Workspace calendar: the calendar's
 * shape of booking rows (includes/fn/booking_agenda.php), their items in the
 * calendar written to Google or Outlook and the refused move read back
 * (includes/workspace/calendar_sync.php), and their place on the Work
 * Calendar's days (includes/workspace/calendar.php).
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calendar_sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calendar.php');

function workspace_bookings_calendar_test_items()
{
	return pg_booking_calendar_item_rows(array(
		array('id' => '7', 'name' => ' Ayşe Yılmaz ', 'start_at' => '1793440800', 'end_at' => '1793442600', 'service_name' => 'Haircut'),
		array('id' => '8', 'name' => '', 'start_at' => '1793444400', 'end_at' => '1793440000', 'service_name' => null),
	));
}

function test_workspace_bookings_calendar_item_rows()
{
	$items = workspace_bookings_calendar_test_items();

	pg_assert_same(array(
		'id'        => 7,
		'starts_at' => 1793440800,
		'ends_at'   => 1793442600,
		'title'     => 'Haircut · Ayşe Yılmaz',
		'service'   => 'Haircut',
		'customer'  => 'Ayşe Yılmaz',
		'url'       => 'edit_booking.php?id=7',
	), $items[0], 'a booking is its service and customer, opened on the agenda');
	pg_assert_same(lang('Booking'), $items[1]['title'], 'a booking with neither is called a booking');
	pg_assert_same(1793444400, $items[1]['ends_at'], 'an end before the start is the start');
}

function test_workspace_bookings_calendar_sync_items()
{
	$items = ws_calsync_booking_feed_items(workspace_bookings_calendar_test_items(), 'https://example.com/pinegrap/');
	$body = ws_calsync_google_body($items[0], 'Europe/Istanbul');
	$graph = ws_calsync_graph_body($items[0]);

	pg_assert_same('ws-booking-7', $items[0]['uid'], 'a booking has its own uid');
	pg_assert_same('https://example.com/pinegrap/edit_booking.php?id=7', $items[0]['url'], 'the link opens the booking on the agenda');
	pg_assert_true(strpos($items[0]['description'], 'Ayşe Yılmaz') !== false, 'the customer is in the description');
	pg_assert_same(date('c', 1793442600), $body['end']['dateTime'], 'a booking is timed and ends when the booking ends');
	pg_assert_same('opaque', $body['transparency'], 'a booking is busy in Google');
	pg_assert_same('busy', $graph['showAs'], 'a booking is busy in Outlook');
}

function test_workspace_bookings_calendar_sync_move_refused()
{
	$tz = 'Europe/Istanbul';
	$items = ws_calsync_booking_feed_items(workspace_bookings_calendar_test_items(), 'https://example.com/pinegrap/');
	$id = ws_calsync_event_id('example.com', 'ws-booking-7');
	$bodies = array($id => ws_calsync_google_body($items[0], $tz));
	$written = array($id => ws_calsync_body_hash($bodies[$id]));
	$later = array('date' => '2026-11-10', 'time' => '15:00');

	pg_assert_same(array(), ws_calsync_moves(array($id => $later), $bodies, $written, array(), 'google', $tz), 'a booking is never a task move');
	pg_assert_same(
		array($id => array('task_id' => 7, 'when' => $later)),
		ws_calsync_moves(array($id => $later), $bodies, $written, array($id => 7), 'google', $tz),
		'a booking moved in the calendar is found, to be refused'
	);
}

function test_workspace_bookings_calendar_place()
{
	$events = ws_booking_calendar_events(workspace_bookings_calendar_test_items());
	$day = date('Y-m-d', 1793440800);
	$next = date('Y-m-d', strtotime($day . ' 12:00:00 +1 day'));
	$days = ws_booking_calendar_place(array($day => array('events' => array()), $next => array('events' => array())), array($events[0]));

	pg_assert_same('booking', $events[0]['kind'], 'a booking is drawn as kind booking');
	pg_assert_same(1, count($days[$day]['events']), 'the booking is on its day');
	pg_assert_same(array(), $days[$next]['events'], 'and not on the next');
	pg_assert_same(date('H:i', 1793440800), $days[$day]['events'][0]['time'], 'at its time');
	pg_assert_same('edit_booking.php?id=7', $days[$day]['events'][0]['url'], 'opened on the agenda');
	pg_assert_true($days[$day]['events'][0]['readonly'], 'and read-only on the calendar');
}
