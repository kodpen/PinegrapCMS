<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's own calendar address shown as a read-only layer on
 * their Work Calendar (includes/workspace/personal_ical.php): only the
 * owner's rows are read for a person while the site-wide public holiday rows
 * keep their own filter, the sealed address round trip, the address check
 * (https and webcal only, no host inside the server's network), and the
 * events of a sample calendar landing in the person's layer and nowhere else.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/workdays.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/personal_ical.php');

// A personal calendar as Google and Outlook send it: a one-off meeting in UTC,
// a weekly meeting in a named zone with one week left out and one moved, an
// all-day trip over three days, and a cancelled event.
function ws_personal_ical_test_sample()
{
	return implode("\r\n", array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'BEGIN:VEVENT',
		'UID:one@example.com',
		'DTSTART:20261103T090000Z',
		'DTEND:20261103T100000Z',
		'SUMMARY:Dentist',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:weekly@example.com',
		'DTSTART;TZID=Europe/Istanbul:20261102T140000',
		'DTEND;TZID=Europe/Istanbul:20261102T150000',
		'RRULE:FREQ=WEEKLY;BYDAY=MO;COUNT=4',
		'EXDATE;TZID=Europe/Istanbul:20261109T140000',
		'SUMMARY:Choir',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:weekly@example.com',
		'RECURRENCE-ID;TZID=Europe/Istanbul:20261116T140000',
		'DTSTART;TZID=Europe/Istanbul:20261117T160000',
		'DTEND;TZID=Europe/Istanbul:20261117T170000',
		'SUMMARY:Choir (moved)',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:trip@example.com',
		'DTSTART;VALUE=DATE:20261105',
		'DTEND;VALUE=DATE:20261108',
		'SUMMARY:Trip to',
		'  Izmir',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:gone@example.com',
		'DTSTART:20261104T090000Z',
		'DTEND:20261104T100000Z',
		'STATUS:CANCELLED',
		'SUMMARY:Cancelled',
		'END:VEVENT',
		'END:VCALENDAR',
	));
}

function ws_personal_ical_test_events()
{
	$previous = date_default_timezone_get();
	date_default_timezone_set('Europe/Istanbul');

	$events = ws_personal_ical_events(ws_personal_ical_test_sample(), strtotime('2026-10-01 00:00:00'), strtotime('2027-01-01 00:00:00'));

	date_default_timezone_set($previous);

	return $events;
}

// A person reads only their own rows; the site-wide screens and counts keep reading the rows of nobody.
function test_workspace_personal_ical_owner_rows_only()
{
	pg_assert_same("user_id = '7'", ws_holiday_feed_owner_where(7), 'a person reads their own rows');
	pg_assert_same("user_id = '0'", ws_holiday_feed_owner_where(0), 'the site-wide screen reads the rows of nobody');
	pg_assert_same("user_id = '0'", ws_holiday_feed_owner_where(-3), 'a negative id never widens the filter');
	pg_assert_same("user_id = '12'", ws_holiday_feed_owner_where('12 OR 1=1'), 'the id is a number, never SQL');
}

// The address and the layer's days go in sealed and come back whole; another key opens nothing.
function test_workspace_personal_ical_seal_round_trip()
{
	$payload = array('url' => 'https://calendar.google.com/calendar/ical/me%40gmail.com/private-abc123/basic.ics', 'on' => true, 'events' => array());
	$sealed = ws_personal_ical_seal_with($payload, 'site-key-one');

	pg_assert_true($sealed !== '', 'something is sealed');
	pg_assert_false(strpos($sealed, 'private-abc123') !== false, 'the secret part is not in the stored text');
	pg_assert_false(strpos($sealed, 'google') !== false, 'not even the host is in the stored text');
	pg_assert_same($payload, ws_personal_ical_unseal_with($sealed, 'site-key-one'), 'read back whole');
	pg_assert_same(array(), ws_personal_ical_unseal_with($sealed, 'site-key-two'), 'another key opens nothing');
	pg_assert_same(array(), ws_personal_ical_unseal_with('https://plain.example.com/a.ics', 'site-key-one'), 'a plain address is not a sealed one');
	pg_assert_same('', ws_personal_ical_seal_with($payload, ''), 'no key, nothing sealed');
}

// Only https and webcal addresses, never a host inside the server's own network.
function test_workspace_personal_ical_address_check()
{
	$ok = ws_personal_ical_address('webcal://p52-caldav.icloud.com/published/2/abc');
	pg_assert_true($ok['ok'], 'webcal is accepted');
	pg_assert_same('https://p52-caldav.icloud.com/published/2/abc', $ok['url'], 'webcal is called as https');

	pg_assert_true(ws_personal_ical_address(' https://outlook.office365.com/owa/calendar/x/reachcalendar.ics ')['ok'], 'https is accepted, trimmed');

	foreach (array(
		'http://calendar.example.com/a.ics'  => 'plain http',
		'ftp://calendar.example.com/a.ics'   => 'another scheme',
		'file:///etc/passwd'                 => 'a file',
		'javascript:alert(1)'                => 'script',
		'https://127.0.0.1/a.ics'            => 'loopback',
		'https://10.0.0.5/a.ics'             => 'private range',
		'https://192.168.1.10/a.ics'         => 'home network',
		'https://169.254.169.254/latest'     => 'cloud metadata',
		'https://[::1]/a.ics'                => 'IPv6 loopback',
		'https://localhost/a.ics'            => 'localhost',
		'https://intranet.local/a.ics'       => 'a .local name',
		'https://user:pass@example.com/a'    => 'credentials in the address',
		''                                   => 'empty',
		'https://' . str_repeat('a', 2100)   => 'too long',
	) as $url => $why) {
		pg_assert_false(ws_personal_ical_address($url)['ok'], $why . ' is refused');
	}
}

// Timed and all-day events, a weekly rule with a week left out and a week moved, a cancelled event dropped.
function test_workspace_personal_ical_sample_events()
{
	pg_assert_same(array(
		'2026-11-02 14:00 Choir',
		'2026-11-03 12:00 Dentist',
		'2026-11-05 00:00 Trip to Izmir (day)',
		'2026-11-17 16:00 Choir (moved)',
		'2026-11-23 14:00 Choir',
	), ws_personal_ical_test_lines(ws_personal_ical_test_events()), 'the layer of the sample');
}

// Lines read in Istanbul time, whatever zone the runner has.
function ws_personal_ical_test_lines($events)
{
	$previous = date_default_timezone_get();
	date_default_timezone_set('Europe/Istanbul');
	$lines = array();

	foreach ($events as $event) {
		$lines[] = date('Y-m-d H:i', $event['starts_at']) . ' ' . $event['title'] . ($event['all_day'] ? ' (day)' : '');
	}

	date_default_timezone_set($previous);

	return $lines;
}

// The sample lands in the person's layer, read-only, and never counts as a day off.
function test_workspace_personal_ical_lands_in_layer_only()
{
	$previous = date_default_timezone_get();
	date_default_timezone_set('Europe/Istanbul');

	$days = array();

	foreach (array('2026-11-05', '2026-11-06', '2026-11-07', '2026-11-08') as $date) {
		$days[$date] = array('date' => $date, 'events' => array());
	}

	$events = ws_personal_ical_events(ws_personal_ical_test_sample(), strtotime('2026-10-01 00:00:00'), strtotime('2027-01-01 00:00:00'));
	$days = ws_personal_ical_place($days, $events, 'My Google calendar');

	pg_assert_same(1, count($days['2026-11-05']['events']), 'the trip is on its first day');
	pg_assert_same(1, count($days['2026-11-07']['events']), 'and on its last');
	pg_assert_same(0, count($days['2026-11-08']['events']), 'not on the day after (DTEND is exclusive)');

	$item = $days['2026-11-05']['events'][0];
	pg_assert_same(0, $item['id'], 'no plan item behind it');
	pg_assert_same('My Google calendar', $item['layer'], 'it names its layer');
	pg_assert_true($item['readonly'], 'read-only');
	pg_assert_same('', $item['time'], 'all day');
	pg_assert_false($item['kind'] === 'holiday' || $item['kind'] === 'leave', 'never a day off');

	// The public holiday reader takes nothing from a personal calendar's timed events.
	pg_assert_same(array('trip@example.com'), array_column(ws_ical_events(ws_personal_ical_test_sample(), false), 'uid'), 'the holiday reader keeps all-day events only');

	date_default_timezone_set($previous);
}

// The stored layer stays within the column, keeping the nearest events first.
function test_workspace_personal_ical_payload_fits()
{
	$events = array();

	for ($i = 0; $i < 2000; $i++) {
		$events[] = array('title' => str_repeat('x', 120), 'starts_at' => 1790000000 + $i * 3600, 'ends_at' => 1790000000 + $i * 3600 + 1800, 'all_day' => false);
	}

	$kept = ws_personal_ical_trim($events);

	pg_assert_true(count($kept) < 2000, 'some are left out');
	pg_assert_true(strlen(json_encode($kept)) <= WS_PERSONAL_ICAL_PAYLOAD_MAX, 'what is kept fits');
	pg_assert_same(1790000000, $kept[0]['starts_at'], 'the first is kept');
}
