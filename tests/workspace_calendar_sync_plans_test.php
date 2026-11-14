<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for writing a person's own plans to their Google or Outlook calendar
 * (includes/workspace/calendar_sync.php): which plans are written (the
 * person's own, never one shared with them), what an event carries (no note,
 * customer or steps), the plans calendar kept apart from the tasks one, the
 * switch on with tasks off, and a deleted or switched-off plan leaving.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function ws_calsync_plans_test_plan($overrides = array())
{
	return array_merge(array(
		'id'          => 7,
		'user_id'     => 3,
		'title'       => 'Visit the supplier',
		'note'        => 'Bring the signed contract',
		'starts_at'   => mktime(10, 0, 0, 11, 20, 2026),
		'ends_at'     => mktime(11, 30, 0, 11, 20, 2026),
		'all_day'     => 0,
		'visibility'  => 'me',
		'contact_id'  => 55,
	), $overrides);
}

function ws_calsync_plans_test_items($plans, $user_id = 3)
{
	return ws_calsync_plan_items($plans, $user_id, array(3, 4), 'https://example.com/pinegrap/', mktime(0, 0, 0, 9, 1, 2026), mktime(0, 0, 0, 12, 31, 2027));
}

function test_workspace_calendar_sync_plans_which_are_written()
{
	$own = ws_calsync_plans_test_plan();
	$own_team = ws_calsync_plans_test_plan(array('id' => 8, 'visibility' => 'team'));
	$shared = ws_calsync_plans_test_plan(array('id' => 9, 'user_id' => 4, 'visibility' => 'team'));
	$shared_named = ws_calsync_plans_test_plan(array('id' => 10, 'user_id' => 4, 'visibility' => 'people'));
	$leaver = ws_calsync_plans_test_plan(array('id' => 11, 'user_id' => 5));

	$uids = array_column(ws_calsync_plans_test_items(array($own, $own_team, $shared, $shared_named, $leaver)), 'uid');

	pg_assert_same(array('ws-plan-7', 'ws-plan-8'), $uids, 'only the person\'s own plans, private or opened to the team; never one shared with them');
	pg_assert_same(array(), ws_calsync_plans_test_items(array($own), 6), 'a reader off the team gets nothing, not even a plan named theirs');
	pg_assert_same(array('ws-plan-9'), array_column(ws_calsync_plans_test_items(array($shared), 4), 'uid'), 'the owner of a shared plan has it in their own calendar');
	pg_assert_same(array(), ws_calsync_plans_test_items(array(ws_calsync_plans_test_plan(array('starts_at' => mktime(10, 0, 0, 1, 5, 2028), 'ends_at' => mktime(11, 0, 0, 1, 5, 2028))))), 'a plan past the window is not written');
}

function test_workspace_calendar_sync_plans_body()
{
	$items = ws_calsync_plans_test_items(array(ws_calsync_plans_test_plan()));
	$item = $items[0];

	pg_assert_same('Visit the supplier', $item['summary'], 'the title');
	pg_assert_same('https://example.com/pinegrap/workspace_my_calendar.php?plan=7', $item['url'], 'a link to the plan on My calendar');
	pg_assert_same($item['url'], $item['description'], 'the description is the link alone');
	pg_assert_same(mktime(10, 0, 0, 11, 20, 2026), $item['start'], 'starts at its time');
	pg_assert_same(mktime(11, 30, 0, 11, 20, 2026), $item['end'], 'ends at its time');

	foreach (array(ws_calsync_google_body($item, 'Europe/Istanbul'), ws_calsync_graph_body($item)) as $body) {
		$text = (string) json_encode($body);
		pg_assert_false(strpos($text, 'signed contract') !== false, 'no note in the event');
		pg_assert_false(strpos($text, '55') !== false, 'no customer in the event');
	}

	pg_assert_same(array('useDefault' => false), ws_calsync_google_body($item, 'UTC')['reminders'], 'the site keeps the reminder (Google)');
	pg_assert_false(ws_calsync_graph_body($item)['isReminderOn'], 'the site keeps the reminder (Outlook)');

	$day = ws_calsync_plans_test_items(array(ws_calsync_plans_test_plan(array(
		'all_day'   => 1,
		'starts_at' => mktime(0, 0, 0, 11, 20, 2026),
		'ends_at'   => mktime(0, 0, 0, 11, 22, 2026),
	))))[0];
	pg_assert_same(array('2026-11-20', '2026-11-21'), array($day['start'], $day['end']), 'a whole-day plan runs to its last day, its end being the midnight after');
	pg_assert_same(array('date' => '2026-11-22'), ws_calsync_google_body($day, 'UTC')['end'], 'Google ends it the day after');
}

function test_workspace_calendar_sync_plans_calendars_apart()
{
	$plan_id = ws_calsync_event_id('example.com', 'ws-plan-7');
	pg_assert_false($plan_id === ws_calsync_event_id('example.com', 'ws-task-7'), 'a plan and a task of the same number are two events');

	$state = ws_calsync_state_clean(array(
		'calendar_id'       => 'tasks-cal',
		'written'           => array('t1' => 'h1'),
		'remote'            => array('t1' => 'AAMk-t', $plan_id => 'AAMk-p'),
		'plans'             => true,
		'plans_calendar_id' => 'plans-cal',
		'plans_written'     => array($plan_id => 'h2'),
		'read_token'        => 'sync-1',
	));

	pg_assert_same('plans-cal', $state['plans_calendar_id'], 'the plans calendar is a key of its own');
	pg_assert_same(array('t1' => 'h1'), $state['written'], 'tasks written are kept apart from plans');

	// Stopping the tasks leaves the plans as they are.
	$left = ws_calsync_stop_tasks($state);
	pg_assert_false(ws_calsync_tasks_on($left), 'tasks off');
	pg_assert_true($left['plans'], 'plans still on');
	pg_assert_same('plans-cal', $left['plans_calendar_id'], 'the plans calendar stays');
	pg_assert_same(array($plan_id => 'AAMk-p'), $left['remote'], 'only the plans\' Outlook ids are kept');
	pg_assert_same('', $left['read_token'], 'nothing more is read back');

	pg_assert_same(array(), ws_calsync_stop_tasks(ws_calsync_state_clean(array('calendar_id' => 'tasks-cal'))), 'with no plans, stopping the tasks ends the entry');
}

function test_workspace_calendar_sync_plans_switch_alone()
{
	$state = ws_calsync_state_clean(array('plans' => true, 'provider' => 'microsoft'));

	pg_assert_true((bool) $state, 'the plans switch alone keeps writing on');
	pg_assert_false(ws_calsync_tasks_on($state), 'with tasks off');
	pg_assert_same('', $state['plans_calendar_id'], 'its calendar is made at the first run');
	pg_assert_same(array(), ws_calsync_state_clean(array('plans' => false)), 'off with nothing written: no entry');
	pg_assert_true(ws_calsync_tasks_on(ws_calsync_state_clean(array('calendar_id' => 'c1'))), 'an entry from before plans is tasks on, plans off');
	pg_assert_false(ws_calsync_state_clean(array('calendar_id' => 'c1'))['plans'], 'plans are off until switched on');
}

function test_workspace_calendar_sync_plans_leave_the_list()
{
	$a = ws_calsync_event_id('example.com', 'ws-plan-7');
	$b = ws_calsync_event_id('example.com', 'ws-plan-8');
	$items = ws_calsync_plans_test_items(array(ws_calsync_plans_test_plan(), ws_calsync_plans_test_plan(array('id' => 8))));
	$bodies = array();

	foreach ($items as $item) {
		$bodies[ws_calsync_event_id('example.com', $item['uid'])] = ws_calsync_google_body($item, 'UTC');
	}

	$written = array_map('ws_calsync_body_hash', $bodies);

	pg_assert_same(array('put' => array(), 'delete' => array(), 'left' => 0), ws_calsync_plan($bodies, $written, 60), 'unchanged plans cost no call');

	unset($bodies[$b]);
	pg_assert_same(array($b), ws_calsync_plan($bodies, $written, 60)['delete'], 'a deleted plan is deleted from the calendar');

	$switched_off = ws_calsync_plan_bodies(ws_calsync_state_clean(array('plans' => false, 'plans_calendar_id' => 'plans-cal')), array('id' => 3), '2026-09-01', '2027-12-31', 'UTC');
	pg_assert_same(array(), $switched_off, 'switched off, no plan is carried');
	pg_assert_same(array($a, $b), ws_calsync_plan($switched_off, $written, 60)['delete'], 'so every plan event is deleted at the next run');

	$changed = ws_calsync_plans_test_items(array(ws_calsync_plans_test_plan(array('title' => 'Visit the new supplier'))));
	pg_assert_same(array($a), array_keys(ws_calsync_plan(array($a => ws_calsync_google_body($changed[0], 'UTC')), $written, 60)['put']), 'a changed plan is written again');
}

function test_workspace_calendar_sync_plans_stay_out_of_the_subscription()
{
	$feed = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calendar_feed.php');
	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_ical.php');

	pg_assert_false(strpos($feed . $endpoint, 'ws-plan-') !== false, 'the outgoing iCal subscription never carries a plan');
	pg_assert_false(strpos($feed . $endpoint, 'ws_calsync_plan_items') !== false, 'nor reads the plans writer');
}

// A repeating plan is written one event a time, each under its day, inside
// the window; the writer reads the plan rows, not the calendar's times.
function test_workspace_calendar_sync_plans_repeat_one_event_a_time()
{
	$weekly = ws_calsync_plans_test_plan(array(
		'repeat_frequency' => 'weekly',
		'repeat_interval'  => 1,
		'repeat_until'     => mktime(11, 30, 0, 12, 4, 2026),
		'repeat_count'     => 3,
	));
	$items = ws_calsync_plan_items(array($weekly), 3, array(3, 4), 'https://example.com/pinegrap/', mktime(0, 0, 0, 11, 25, 2026), mktime(0, 0, 0, 12, 31, 2026));

	pg_assert_same(array('ws-plan-7-20261127', 'ws-plan-7-20261204'), array_column($items, 'uid'), 'the times in the window, each its own uid');
	pg_assert_same(mktime(10, 0, 0, 12, 4, 2026), $items[1]['start'], 'at the plan\'s hour');
	pg_assert_contains('ws_personal_plans_between((int) $viewer[\'id\'], $since_ts, $until_ts, false)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calendar_sync.php'), 'one row a plan, laid out here');
}
