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

function ws_gcal_test_item($overrides = array())
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
	$id = ws_gcal_event_id('example.com', 'ws-task-42');

	pg_assert_true((bool) preg_match('/^[a-v0-9]{5,1024}$/', $id), 'only the base32hex letters Google accepts');
	pg_assert_same($id, ws_gcal_event_id('example.com', 'ws-task-42'), 'the same task gives the same id');
	pg_assert_false($id === ws_gcal_event_id('example.com', 'ws-task-43'), 'another task, another id');
	pg_assert_false($id === ws_gcal_event_id('other.example', 'ws-task-42'), 'another site, another id');
}

function test_workspace_calendar_sync_all_day_body()
{
	$body = ws_gcal_event_body(ws_gcal_test_item(), 'Europe/Istanbul');

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
	$body = ws_gcal_event_body(ws_gcal_test_item(array('all_day' => false, 'start' => $moment, 'end' => null)), 'Europe/Istanbul');

	pg_assert_same(date('c', $moment), $body['start']['dateTime'], 'the due time');
	pg_assert_same('Europe/Istanbul', $body['start']['timeZone'], 'in the site zone');
	pg_assert_same($body['start'], $body['end'], 'a deadline is a moment');

	$body = ws_gcal_event_body(ws_gcal_test_item(array('all_day' => false, 'start' => $moment, 'end' => $moment + 3600)), 'UTC');
	pg_assert_same(date('c', $moment + 3600), $body['end']['dateTime'], 'an end is kept');

	$body = ws_gcal_event_body(ws_gcal_test_item(array('end' => '2026-11-03')), 'UTC');
	pg_assert_same(array('date' => '2026-11-06'), $body['end'], 'an end before the start is the start day');

	$body = ws_gcal_event_body(ws_gcal_test_item(array('status' => 'TENTATIVE')), 'UTC');
	pg_assert_same('tentative', $body['status'], 'a coming copy of a repeating task is tentative');
}

function test_workspace_calendar_sync_plan()
{
	$a = ws_gcal_event_body(ws_gcal_test_item(), 'UTC');
	$b = ws_gcal_event_body(ws_gcal_test_item(array('uid' => 'ws-task-43', 'summary' => 'Call back')), 'UTC');
	$bodies = array('ida' => $a, 'idb' => $b);

	$plan = ws_gcal_plan($bodies, array(), 50);
	pg_assert_same(array('ida', 'idb'), array_keys($plan['put']), 'everything is written the first time');
	pg_assert_same(array(), $plan['delete'], 'nothing to delete');
	pg_assert_same(0, $plan['left'], 'nothing left');

	$written = array('ida' => ws_gcal_body_hash($a), 'idb' => ws_gcal_body_hash($b));
	$plan = ws_gcal_plan($bodies, $written, 50);
	pg_assert_same(array(), $plan['put'], 'nothing changed, nothing written');
	pg_assert_same(array(), $plan['delete'], 'nothing deleted');

	$b['summary'] = 'Call back today';
	$plan = ws_gcal_plan(array('idb' => $b), $written, 50);
	pg_assert_same(array('idb'), array_keys($plan['put']), 'a changed task is written again');
	pg_assert_same(array('ida'), $plan['delete'], 'a task no longer carried is deleted');

	$plan = ws_gcal_plan(array('idb' => $b), $written, 1);
	pg_assert_same(1, count($plan['put']) + count($plan['delete']), 'no more calls than the limit');
	pg_assert_same(1, $plan['left'], 'the rest waits for the next run');
	pg_assert_same(array('ida'), $plan['delete'], 'deletions go first');

	$plan = ws_gcal_plan(array('id' . str_repeat('x', 3) => $a), array('bad' => 'x', 7 => 'y'), 50);
	pg_assert_same(array('bad', '7'), array_map('strval', $plan['delete']), 'every id written before and not carried now is deleted');
}
