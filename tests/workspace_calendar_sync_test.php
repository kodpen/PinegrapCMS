<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for writing a person's Workspace tasks to their own Google Calendar
 * (includes/workspace/calendar_sync.php): the event id Google accepts and
 * that stays the same for the same task, the event body for a whole-day and a
 * timed task, and the plan of writes and deletions against what was written
 * last time, within the per-run limit.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calendar_sync.php');

function ws_calsync_test_item($overrides = array())
{
	return array_merge(array(
		'uid'         => 'ws-task-42',
		'summary'     => 'Send the offer',
		'description' => "#42 · To do\n\nhttps://example.com/pinegrap/workspace_tasks.php?task=42",
		'url'         => 'https://example.com/pinegrap/workspace_tasks.php?task=42',
		'all_day'     => true,
		'start'       => '2026-11-05',
		'end'         => '2026-11-05',
		'rrule'       => '',
		'categories'  => array('Task'),
		'status'      => 'CONFIRMED',
		'transparent' => true,
		'stamp'       => 1700000000,
	), $overrides);
}

function test_workspace_calendar_sync_event_id()
{
	$id = ws_calsync_event_id('example.com', 'ws-task-42');

	pg_assert_true((bool) preg_match('/^[a-v0-9]{5,1024}$/', $id), 'only the base32hex letters Google accepts');
	pg_assert_same($id, ws_calsync_event_id('example.com', 'ws-task-42'), 'the same task gives the same id');
	pg_assert_false($id === ws_calsync_event_id('example.com', 'ws-task-43'), 'another task, another id');
	pg_assert_false($id === ws_calsync_event_id('other.example', 'ws-task-42'), 'another site, another id');
}

function test_workspace_calendar_sync_all_day_body()
{
	$body = ws_calsync_google_body(ws_calsync_test_item(), 'Europe/Istanbul');

	pg_assert_same(array('date' => '2026-11-05'), $body['start'], 'starts on the due day');
	pg_assert_same(array('date' => '2026-11-06'), $body['end'], 'Google ends a whole day on the next day');
	pg_assert_same('Send the offer', $body['summary'], 'the title');
	pg_assert_same('transparent', $body['transparency'], 'a task does not make the person busy');
	pg_assert_same('confirmed', $body['status'], 'a deleted event written again comes back');
	pg_assert_same('https://example.com/pinegrap/workspace_tasks.php?task=42', $body['source']['url'], 'a link back to the task');
	pg_assert_same(array('useDefault' => false), $body['reminders'], 'no extra reminders from the calendar');
	pg_assert_same('ws-task-42', $body['extendedProperties']['private']['pinegrap_uid'], 'the task is named for reading changes back');
}

function test_workspace_calendar_sync_timed_body()
{
	$moment = mktime(14, 30, 0, 11, 5, 2026);
	$body = ws_calsync_google_body(ws_calsync_test_item(array('all_day' => false, 'start' => $moment, 'end' => null)), 'Europe/Istanbul');

	pg_assert_same(date('c', $moment), $body['start']['dateTime'], 'the due time');
	pg_assert_same('Europe/Istanbul', $body['start']['timeZone'], 'in the site zone');
	pg_assert_same($body['start'], $body['end'], 'a deadline is a moment');

	$body = ws_calsync_google_body(ws_calsync_test_item(array('all_day' => false, 'start' => $moment, 'end' => $moment + 3600)), 'UTC');
	pg_assert_same(date('c', $moment + 3600), $body['end']['dateTime'], 'an end is kept');

	$body = ws_calsync_google_body(ws_calsync_test_item(array('end' => '2026-11-03')), 'UTC');
	pg_assert_same(array('date' => '2026-11-06'), $body['end'], 'an end before the start is the start day');

	$body = ws_calsync_google_body(ws_calsync_test_item(array('status' => 'TENTATIVE')), 'UTC');
	pg_assert_same('tentative', $body['status'], 'a coming copy of a repeating task is tentative');
}

function test_workspace_calendar_sync_plan()
{
	$a = ws_calsync_google_body(ws_calsync_test_item(), 'UTC');
	$b = ws_calsync_google_body(ws_calsync_test_item(array('uid' => 'ws-task-43', 'summary' => 'Call back')), 'UTC');
	$bodies = array('ida' => $a, 'idb' => $b);

	$plan = ws_calsync_plan($bodies, array(), 50);
	pg_assert_same(array('ida', 'idb'), array_keys($plan['put']), 'everything is written the first time');
	pg_assert_same(array(), $plan['delete'], 'nothing to delete');
	pg_assert_same(0, $plan['left'], 'nothing left');

	$written = array('ida' => ws_calsync_body_hash($a), 'idb' => ws_calsync_body_hash($b));
	$plan = ws_calsync_plan($bodies, $written, 50);
	pg_assert_same(array(), $plan['put'], 'nothing changed, nothing written');
	pg_assert_same(array(), $plan['delete'], 'nothing deleted');

	$b['summary'] = 'Call back today';
	$plan = ws_calsync_plan(array('idb' => $b), $written, 50);
	pg_assert_same(array('idb'), array_keys($plan['put']), 'a changed task is written again');
	pg_assert_same(array('ida'), $plan['delete'], 'a task no longer carried is deleted');

	$plan = ws_calsync_plan(array('idb' => $b), $written, 1);
	pg_assert_same(1, count($plan['put']) + count($plan['delete']), 'no more calls than the limit');
	pg_assert_same(1, $plan['left'], 'the rest waits for the next run');
	pg_assert_same(array('ida'), $plan['delete'], 'deletions go first');

	$plan = ws_calsync_plan(array('id' . str_repeat('x', 3) => $a), array('bad' => 'x', 7 => 'y'), 50);
	pg_assert_same(array('bad', '7'), array_map('strval', $plan['delete']), 'every id written before and not carried now is deleted');
}

function test_workspace_calendar_sync_graph_body()
{
	$body = ws_calsync_graph_body(ws_calsync_test_item(array('end' => '2026-11-06')));

	pg_assert_same(true, $body['isAllDay'], 'a whole-day task');
	pg_assert_same(array('dateTime' => '2026-11-05T00:00:00', 'timeZone' => 'UTC'), $body['start'], 'from midnight of the first day');
	pg_assert_same(array('dateTime' => '2026-11-07T00:00:00', 'timeZone' => 'UTC'), $body['end'], 'to midnight after the last day');
	pg_assert_same('Send the offer', $body['subject'], 'the title');
	pg_assert_same('text', $body['body']['contentType'], 'plain text, never HTML');
	pg_assert_contains('workspace_tasks.php?task=42', $body['body']['content'], 'the link is in the text');
	pg_assert_same('free', $body['showAs'], 'a task does not make the person busy');
	pg_assert_same(false, $body['isReminderOn'], 'no extra reminders from the calendar');

	$moment = 1793968200;
	$body = ws_calsync_graph_body(ws_calsync_test_item(array('all_day' => false, 'start' => $moment, 'end' => null)));
	pg_assert_same(false, $body['isAllDay'], 'a timed task');
	pg_assert_same(array('dateTime' => gmdate('Y-m-d\TH:i:s', $moment), 'timeZone' => 'UTC'), $body['start'], 'the due time in UTC');
	pg_assert_same($body['start'], $body['end'], 'a deadline is a moment');

	$body = ws_calsync_graph_body(ws_calsync_test_item(array('status' => 'TENTATIVE')));
	pg_assert_same('tentative', $body['showAs'], 'a coming copy of a repeating task is tentative');
}

function test_workspace_calendar_sync_state_shape()
{
	pg_assert_same(array(), ws_calsync_state_clean(array()), 'no calendar, writing is off');
	pg_assert_same(array(), ws_calsync_state_clean(array('calendar_id' => 'c', 'provider' => 'dropbox')), 'an unknown provider is off');

	$state = ws_calsync_state_clean(array('calendar_id' => 'c1'));
	pg_assert_same('google', $state['provider'], 'a state from before the provider field is Google');
	pg_assert_same(array(), $state['remote'], 'no remote ids');

	$state = ws_calsync_state_clean(array('calendar_id' => 'c2', 'provider' => 'microsoft', 'remote' => array('a' => 'AAMk1', 'b' => 7), 'written' => 'x'));
	pg_assert_same('microsoft', $state['provider'], 'Outlook kept');
	pg_assert_same(array('a' => 'AAMk1'), $state['remote'], 'only text ids kept');
	pg_assert_same(array(), $state['written'], 'a broken written list is empty');
}

function test_workspace_calendar_sync_google_when()
{
	pg_assert_same(array('date' => '2026-11-05', 'time' => ''), ws_calsync_google_when(array('date' => '2026-11-05'), 'Europe/Istanbul'), 'a whole day by its date');
	pg_assert_same(array('date' => '2026-11-05', 'time' => '14:30'), ws_calsync_google_when(array('dateTime' => '2026-11-05T11:30:00Z'), 'Europe/Istanbul'), 'a moment read in the site zone');
	pg_assert_same(array('date' => '2026-11-06', 'time' => '01:00'), ws_calsync_google_when(array('dateTime' => '2026-11-05T23:00:00+01:00', 'timeZone' => 'Europe/Berlin'), 'Europe/Istanbul'), 'another zone moves the day');
	pg_assert_same(null, ws_calsync_google_when(array('dateTime' => 'soon'), 'UTC'), 'an unreadable moment is no date');
	pg_assert_same(null, ws_calsync_google_when(null, 'UTC'), 'no start, no date');

	$moment = mktime(14, 30, 0, 11, 5, 2026);
	$body = ws_calsync_google_body(ws_calsync_test_item(array('all_day' => false, 'start' => $moment, 'end' => null)), date_default_timezone_get());
	pg_assert_same(array('date' => date('Y-m-d', $moment), 'time' => date('H:i', $moment)), ws_calsync_body_when('google', $body, date_default_timezone_get()), 'a written body reads back as written');
}

function test_workspace_calendar_sync_graph_when()
{
	pg_assert_same(array('date' => '2026-11-05', 'time' => ''), ws_calsync_graph_when(array('dateTime' => '2026-11-05T00:00:00.0000000', 'timeZone' => 'UTC'), true, 'Europe/Istanbul'), 'a whole day by its date, never shifted');
	pg_assert_same(array('date' => '2026-11-05', 'time' => '14:30'), ws_calsync_graph_when(array('dateTime' => '2026-11-05T11:30:00.0000000', 'timeZone' => 'UTC'), false, 'Europe/Istanbul'), 'UTC read in the site zone');
	pg_assert_same(null, ws_calsync_graph_when(array('dateTime' => '2026-11-05'), false, 'UTC'), 'no time part, no date');

	$body = ws_calsync_graph_body(ws_calsync_test_item());
	pg_assert_same(array('date' => '2026-11-05', 'time' => ''), ws_calsync_body_when('microsoft', $body, 'Europe/Istanbul'), 'a written whole day reads back as written');

	$moment = mktime(9, 15, 0, 11, 5, 2026);
	$body = ws_calsync_graph_body(ws_calsync_test_item(array('all_day' => false, 'start' => $moment, 'end' => null)));
	pg_assert_same(array('date' => date('Y-m-d', $moment), 'time' => '09:15'), ws_calsync_body_when('microsoft', $body, date_default_timezone_get()), 'a written moment reads back as written');
}

function test_workspace_calendar_sync_moves()
{
	$tz = 'Europe/Istanbul';
	$task = ws_calsync_event_id('example.com', 'ws-task-42');
	$copy = ws_calsync_event_id('example.com', 'ws-series-3-20261112');
	$changed = ws_calsync_event_id('example.com', 'ws-task-43');

	$bodies = array(
		$task    => ws_calsync_google_body(ws_calsync_test_item(), $tz),
		$copy    => ws_calsync_google_body(ws_calsync_test_item(array('uid' => 'ws-series-3-20261112', 'start' => '2026-11-12', 'end' => '2026-11-12')), $tz),
		$changed => ws_calsync_google_body(ws_calsync_test_item(array('uid' => 'ws-task-43')), $tz),
	);
	$written = array(
		$task    => ws_calsync_body_hash($bodies[$task]),
		$copy    => ws_calsync_body_hash($bodies[$copy]),
		$changed => 'an older body',
	);
	$tasks = array($task => 42, $changed => 43);
	$later = array('date' => '2026-11-07', 'time' => '');

	pg_assert_same(
		array($task => array('task_id' => 42, 'when' => $later)),
		ws_calsync_moves(array($task => $later, $copy => $later, $changed => $later), $bodies, $written, $tasks, 'google', $tz),
		'only a task whose event still held the last write moves; a copy and a task changed on the site do not'
	);
	pg_assert_same(array(), ws_calsync_moves(array($task => array('date' => '2026-11-05', 'time' => '')), $bodies, $written, $tasks, 'google', $tz), 'the site\'s own write read back, or a new title, is no move');
	pg_assert_same(array(), ws_calsync_moves(array($task => null), $bodies, $written, $tasks, 'google', $tz), 'a deleted event is no move');
	pg_assert_same(array(), ws_calsync_moves(array('pgunknown' => $later), $bodies, $written, $tasks, 'google', $tz), 'an event the site does not carry is no move');
}

function test_workspace_calendar_sync_move_fields()
{
	$task = array('due_date' => '2026-11-05', 'start_date' => null, 'due_time' => null);

	pg_assert_same(array('due_date' => '2026-11-07'), ws_calsync_move_fields($task, array('date' => '2026-11-07', 'time' => '')), 'another day');
	pg_assert_same(array('due_time' => '10:00'), ws_calsync_move_fields($task, array('date' => '2026-11-05', 'time' => '10:00')), 'a whole day given a time');
	pg_assert_same(array(), ws_calsync_move_fields($task, array('date' => '2026-11-05', 'time' => '')), 'nothing changed');

	$timed = array('due_date' => '2026-11-05', 'start_date' => '2026-11-01', 'due_time' => '14:30:00');
	pg_assert_same(
		array('due_date' => '2026-11-08', 'start_date' => '2026-11-04', 'due_time' => ''),
		ws_calsync_move_fields($timed, array('date' => '2026-11-08', 'time' => '')),
		'the start moves by as many days and a whole day clears the time'
	);
	pg_assert_same(array(), ws_calsync_move_fields($timed, array('date' => '2026-11-05', 'time' => '14:30')), 'the same moment');

	pg_assert_same(
		array('due_date' => '2026-11-03', 'start_date' => null),
		ws_calsync_move_fields(array('due_date' => null, 'start_date' => '2026-11-01'), array('date' => '2026-11-03', 'time' => '')),
		'a task with only a start date gets the day as its due date, as on the board; no due time field, no time'
	);
}
