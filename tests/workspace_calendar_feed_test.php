<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workspace calendar subscription
 * (includes/workspace/calendar_feed.php): escaping and folding of iCalendar
 * lines, whole-day and timed events, the yearly rule, the calendar around
 * them, and the checks that need no database - the kinds, the token's shape
 * and the name of the application that read the address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calendar_feed.php');

// One event with the fields every test overrides a few of.
function ws_calendar_feed_test_item($overrides = array())
{
	return array_merge(array(
		'uid'         => 'ws-task-7',
		'summary'     => 'Teklif hazırla',
		'description' => '',
		'url'         => '',
		'all_day'     => true,
		'start'       => '2026-10-13',
		'end'         => '2026-10-13',
		'rrule'       => '',
		'categories'  => array(),
		'status'      => 'CONFIRMED',
		'transparent' => true,
		'stamp'       => 1791892800,
	), $overrides);
}

// Backslash, semicolon, comma and line breaks escaped; control characters dropped, broken UTF-8 replaced.
function test_workspace_calendar_feed_text_escapes()
{
	pg_assert_same('a\\\\b\\;c\\,d\\ne', ws_ics_text("a\\b;c,d\ne"), 'the four escapes');
	pg_assert_same('one\\ntwo\\nthree', ws_ics_text("one\r\ntwo\rthree"), 'CRLF and CR read as one break');
	pg_assert_same("tab\tkept", ws_ics_text("tab\tkept\x07"), 'bell dropped, tab kept');
	pg_assert_same('Şükrü: ödeme', ws_ics_text('Şükrü: ödeme'), 'colon and UTF-8 untouched');
	pg_assert_true(mb_check_encoding(ws_ics_text("ok\xC3"), 'UTF-8'), 'broken UTF-8 made whole');
}

// At most 75 octets a line, a space before every continuation, never inside a character.
function test_workspace_calendar_feed_fold_keeps_characters_whole()
{
	pg_assert_same('SUMMARY:short', ws_ics_fold('SUMMARY:short'), 'short line untouched');

	$line = 'DESCRIPTION:' . str_repeat('ğ', 60);
	$folded = ws_ics_fold($line);
	$parts = explode("\r\n", $folded);

	pg_assert_true(count($parts) > 1, 'long line folded');

	foreach ($parts as $index => $part) {
		pg_assert_true(strlen($part) <= 75, 'part ' . $index . ' within 75 octets');
		pg_assert_true(mb_check_encoding($part, 'UTF-8'), 'part ' . $index . ' is whole UTF-8');

		if ($index > 0) {
			pg_assert_same(' ', substr($part, 0, 1), 'continuation ' . $index . ' starts with a space');
		}
	}

	$unfolded = '';

	foreach ($parts as $index => $part) {
		$unfolded .= ($index > 0) ? substr($part, 1) : $part;
	}

	pg_assert_same($line, $unfolded, 'unfolding gives the line back');
}

// A whole-day event ends the day after its last day.
function test_workspace_calendar_feed_all_day_event()
{
	$lines = ws_ics_event(ws_calendar_feed_test_item(array('start' => '2026-10-13', 'end' => '2026-10-15')), 'example.com');

	pg_assert_same('BEGIN:VEVENT', $lines[0], 'opens');
	pg_assert_same('END:VEVENT', $lines[count($lines) - 1], 'closes');
	pg_assert_contains('UID:ws-task-7@example.com', $lines, 'uid with the site');
	pg_assert_contains('DTSTART;VALUE=DATE:20261013', $lines, 'first day');
	pg_assert_contains('DTEND;VALUE=DATE:20261016', $lines, 'day after the last');
	pg_assert_contains('TRANSP:TRANSPARENT', $lines, 'takes no time');
	pg_assert_contains('DTSTAMP:20261013T120000Z', $lines, 'stamp from the record');

	$single = ws_ics_event(ws_calendar_feed_test_item(array('end' => null)), 'example.com');
	pg_assert_contains('DTEND;VALUE=DATE:20261014', $single, 'one day without an end');

	$month_end = ws_ics_event(ws_calendar_feed_test_item(array('start' => '2026-12-31', 'end' => '2026-12-31')), 'example.com');
	pg_assert_contains('DTEND;VALUE=DATE:20270101', $month_end, 'across the year');
}

// A timed event in UTC; one with no end is a moment and carries no DTEND.
function test_workspace_calendar_feed_timed_event()
{
	$meeting = ws_ics_event(ws_calendar_feed_test_item(array(
		'all_day'     => false,
		'start'       => 1791900000,
		'end'         => 1791903600,
		'transparent' => false,
	)), 'example.com');

	pg_assert_contains('DTSTART:' . gmdate('Ymd\THis\Z', 1791900000), $meeting, 'start in UTC');
	pg_assert_contains('DTEND:' . gmdate('Ymd\THis\Z', 1791903600), $meeting, 'end in UTC');
	pg_assert_contains('TRANSP:OPAQUE', $meeting, 'takes the time');

	$deadline = ws_ics_event(ws_calendar_feed_test_item(array('all_day' => false, 'start' => 1791900000, 'end' => null)), 'example.com');
	$has_end = false;

	foreach ($deadline as $line) {
		$has_end = $has_end || (strpos($line, 'DTEND') === 0);
	}

	pg_assert_false($has_end, 'a deadline has no end');

	$stampless = ws_ics_event(ws_calendar_feed_test_item(array('stamp' => 0)), 'example.com');
	pg_assert_contains('DTSTAMP:20000101T000000Z', $stampless, 'a fixed stamp when the record has none');
}

// Summary, description, categories and the yearly rule as written.
function test_workspace_calendar_feed_event_fields()
{
	$lines = ws_ics_event(ws_calendar_feed_test_item(array(
		'summary'     => 'Bayram, 1. gün',
		'description' => "Tatil\n\nhttps://example.com/x",
		'url'         => "https://example.com/pinegrap/workspace_tasks.php?task=7\r\n",
		'categories'  => array('Tatil', 'A,B'),
		'rrule'       => 'FREQ=YEARLY',
		'status'      => 'TENTATIVE',
	)), 'example.com');

	pg_assert_contains('SUMMARY:Bayram\\, 1. gün', $lines, 'summary escaped');
	pg_assert_contains('DESCRIPTION:Tatil\\n\\nhttps://example.com/x', $lines, 'description escaped');
	pg_assert_contains('URL:https://example.com/pinegrap/workspace_tasks.php?task=7', $lines, 'url without the line break');
	pg_assert_contains('CATEGORIES:Tatil,A\\,B', $lines, 'categories, each escaped');
	pg_assert_contains('RRULE:FREQ=YEARLY', $lines, 'yearly rule');
	pg_assert_contains('STATUS:TENTATIVE', $lines, 'status');
}

// The calendar: CRLF line ends, its own properties, every event inside it.
function test_workspace_calendar_feed_calendar()
{
	$body = ws_ics_calendar(array(
		ws_calendar_feed_test_item(),
		ws_calendar_feed_test_item(array('uid' => 'ws-event-3')),
	), array(
		'host'     => 'example.com',
		'name'     => 'Ayşe · Çalışma Alanı (example.com)',
		'about'    => 'Görevler; plan',
		'timezone' => 'Europe/Istanbul',
	));

	pg_assert_same("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", substr($body, 0, 30), 'opens with the version');
	pg_assert_same("END:VCALENDAR\r\n", substr($body, -15), 'closes with CRLF');
	pg_assert_same(0, preg_match("/(?<!\r)\n/", $body), 'no bare LF');
	pg_assert_contains("\r\nMETHOD:PUBLISH\r\n", $body, 'published');
	pg_assert_contains("\r\nX-WR-CALNAME:Ayşe · Çalışma Alanı (example.com)\r\n", $body, 'name');
	pg_assert_contains("\r\nX-WR-CALDESC:Görevler\\; plan\r\n", $body, 'description escaped');
	pg_assert_contains("\r\nX-WR-TIMEZONE:Europe/Istanbul\r\n", $body, 'time zone hint');
	pg_assert_contains("\r\nREFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n", $body, 'refresh hint');
	pg_assert_same(2, substr_count($body, "BEGIN:VEVENT\r\n"), 'two events');
	pg_assert_contains('UID:ws-event-3@example.com', $body, 'second event');

	$empty = ws_ics_calendar(array(), array('host' => 'example.com'));
	pg_assert_same(0, substr_count($empty, 'BEGIN:VEVENT'), 'an empty calendar is still a calendar');
	pg_assert_contains("END:VCALENDAR\r\n", $empty, 'and closes');
}

// Only the known kinds, in the screen's order, from a list or the stored string.
function test_workspace_calendar_feed_kinds()
{
	pg_assert_same(array('tasks', 'plan', 'holidays', 'approvals'), array_keys(ws_calendar_feed_kinds()), 'the kinds');
	pg_assert_same(array('tasks', 'approvals'), ws_calendar_feed_clean_kinds(array('approvals', 'x', 'tasks')), 'unknown dropped, order kept');
	pg_assert_same(array('plan', 'holidays'), ws_calendar_feed_clean_kinds('holidays,plan'), 'stored string');
	pg_assert_same(array(), ws_calendar_feed_clean_kinds(''), 'nothing');
	pg_assert_same(array(), ws_calendar_feed_clean_kinds(array(array('tasks'))), 'no nested arrays');
}

// A token is 43 characters of base64url; anything else is not looked up.
function test_workspace_calendar_feed_token_shape()
{
	pg_assert_true(ws_calendar_feed_token_shape(str_repeat('a', 21) . '-_' . str_repeat('Z9', 10)), 'base64url, 43');
	pg_assert_true(ws_calendar_feed_token_shape(pg_short_link_new_token()), 'a fresh token');
	pg_assert_false(ws_calendar_feed_token_shape(str_repeat('a', 42)), 'too short');
	pg_assert_false(ws_calendar_feed_token_shape(str_repeat('a', 44)), 'too long');
	pg_assert_false(ws_calendar_feed_token_shape(str_repeat('a', 42) . '='), 'padding');
	pg_assert_false(ws_calendar_feed_token_shape(str_repeat('a', 42) . "\n"), 'line break');
	pg_assert_false(ws_calendar_feed_token_shape(array('x')), 'not a string');
}

// The application that read the address, by its user agent.
function test_workspace_calendar_feed_reader_name()
{
	pg_assert_same('Google Calendar', ws_calendar_feed_reader_name('Google-Calendar-Importer'), 'Google');
	pg_assert_same('Apple Calendar', ws_calendar_feed_reader_name('iOS/18.0 (22A3354) dataaccessd/1.0'), 'iPhone');
	pg_assert_same('Apple Calendar', ws_calendar_feed_reader_name('macOS/15.0 (24A335) CalendarAgent/1000'), 'Mac');
	pg_assert_same('Microsoft Outlook', ws_calendar_feed_reader_name('Microsoft Office/16.0 (Windows NT 10.0; Microsoft Outlook 16.0.17928; Pro)'), 'Outlook');
	pg_assert_same('', ws_calendar_feed_reader_name('curl/8.5.0'), 'unknown');
	pg_assert_same('', ws_calendar_feed_reader_name(''), 'none');
}
