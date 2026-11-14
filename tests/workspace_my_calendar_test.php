<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for My calendar (includes/workspace/my_calendar.php,
 * workspace_my_calendar.php): the days each view covers, how a task is laid,
 * the order of a day's events, the gate and that the reader is always the
 * person, and its ways in from the E-mails toolbar and the Workspace menu.
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

function test_workspace_my_calendar_month_is_whole_weeks_monday_first()
{
	$range = ws_my_calendar_range('month', '2026-11-14', '2026-11-14');

	pg_assert_same('2026-10-26', $range['from'], 'the Monday before the 1st');
	pg_assert_same('2026-12-06', $range['to'], 'the Sunday after the 30th');
	pg_assert_same('2026-11-01', $range['anchor'], 'a month is anchored on its first day');
	pg_assert_same('2026-10-01', $range['prev'], 'previous month');
	pg_assert_same('2026-12-01', $range['next'], 'next month');
	pg_assert_same('2026-11', $range['month']);
}

function test_workspace_my_calendar_week_runs_monday_to_sunday()
{
	$range = ws_my_calendar_range('week', '2026-11-14', '2026-11-14');

	pg_assert_same('2026-11-09', $range['from']);
	pg_assert_same('2026-11-15', $range['to']);
	pg_assert_same('2026-11-02', $range['prev']);
	pg_assert_same('2026-11-16', $range['next']);

	$sunday = ws_my_calendar_range('week', '2026-11-15', '2026-11-14');
	pg_assert_same('2026-11-09', $sunday['from'], 'a Sunday belongs to the week before it');
}

function test_workspace_my_calendar_list_runs_from_its_day()
{
	$range = ws_my_calendar_range('list', '2026-11-14', '2026-11-01');

	pg_assert_same('2026-11-14', $range['from']);
	pg_assert_same('2026-12-13', $range['to'], WS_MY_CALENDAR_LIST_DAYS . ' days');
	pg_assert_same('2026-10-15', $range['prev']);
	pg_assert_same('2026-12-14', $range['next']);
}

function test_workspace_my_calendar_takes_today_for_a_bad_day_and_month_for_a_bad_view()
{
	foreach (array('', '2026-02-30', 'x', '2026-11-14 OR 1', array('2026-11-01')) as $anchor) {
		pg_assert_same('2026-11-09', ws_my_calendar_range('week', $anchor, '2026-11-12')['from'], 'anchor ' . json_encode($anchor));
	}

	pg_assert_same('month', ws_my_calendar_range('year', '2026-11-14', '2026-11-14')['view']);
	pg_assert_same('month', ws_my_calendar_view(array('list')));
}

function test_workspace_my_calendar_task_days_follow_the_work_calendar()
{
	$today = '2026-11-14';
	$open = array('status' => 'doing', 'start_date' => '2026-11-02', 'due_date' => '2026-11-05', 'completed_at' => 0, 'created_at' => strtotime('2026-10-20 10:00'));

	pg_assert_same(array('2026-11-02', '2026-11-14'), ws_my_calendar_task_days($open, $today), 'an open late task runs on to today');

	$done = array('status' => 'done', 'start_date' => '2026-11-02', 'due_date' => '2026-11-05', 'completed_at' => strtotime('2026-11-07 09:00'), 'created_at' => 0);
	pg_assert_same(array('2026-11-02', '2026-11-07'), ws_my_calendar_task_days($done, $today), 'a closed task ends on the day it was closed');

	$undated = array('status' => 'todo', 'start_date' => null, 'due_date' => null, 'completed_at' => 0, 'created_at' => strtotime('2026-11-14 08:00'));
	pg_assert_same(array('2026-11-14', '2026-11-14'), ws_my_calendar_task_days($undated, $today), 'an undated task sits on its creation day');

	$coming = array('status' => 'todo', 'start_date' => '', 'due_date' => '2026-11-20', 'completed_at' => 0, 'created_at' => 0);
	pg_assert_same(array('2026-11-20', '2026-11-20'), ws_my_calendar_task_days($coming, $today));
}

function test_workspace_my_calendar_task_item_marks_late_and_keeps_its_time()
{
	$item = ws_my_calendar_task_item(array('id' => 7, 'title' => 'Call', 'status' => 'todo', 'priority' => 'high', 'due_date' => '2026-11-10', 'due_time' => '09:30:00'), array('3', 4), '2026-11-14');

	pg_assert_true($item['overdue']);
	pg_assert_same('09:30', $item['time']);
	pg_assert_same(array(3, 4), $item['people']);
	pg_assert_false(ws_my_calendar_task_item(array('id' => 7, 'title' => 'Call', 'status' => 'done', 'priority' => 'low', 'due_date' => '2026-11-10'), array(), '2026-11-14')['overdue'], 'a closed task is never late');
}

function test_workspace_my_calendar_day_events_whole_day_first_then_by_time()
{
	$events = ws_my_calendar_sort_events(array(
		array('title' => 'b', 'time' => '14:00'),
		array('title' => 'a', 'time' => '09:00'),
		array('title' => 'leave', 'time' => ''),
		array('title' => 'c', 'time' => '09:00'),
	));

	pg_assert_same(array('leave', 'a', 'c', 'b'), array_column($events, 'title'));
}

function test_workspace_my_calendar_label_names_the_range()
{
	pg_assert_contains('2026', ws_my_calendar_label(ws_my_calendar_range('month', '2026-11-14', '2026-11-14')));
	pg_assert_true(strpos(ws_my_calendar_label(ws_my_calendar_range('week', '2026-11-14', '2026-11-14')), '9 – 15 ') === 0, 'one month: one name');
	pg_assert_true(strpos(ws_my_calendar_label(ws_my_calendar_range('week', '2026-12-01', '2026-11-14')), '30 ') === 0, 'two months: both named');
}

function test_workspace_my_calendar_screen_is_for_team_members_and_reads_only_the_reader()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/workspace_my_calendar.php');
	pg_assert_contains('$viewer = ws_screen_gate($user);', $screen, 'a guest is no member and is refused');

	$actions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$case = substr($actions, strpos($actions, "case 'ws_my_calendar':"), 300);
	pg_assert_contains("ws_my_calendar(\$viewer, \$request['view'] ?? '', \$request['date'] ?? '')", $case);
	pg_assert_false(strpos($case, 'person_id') !== false, 'no person is taken from the request');

	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/my_calendar.php');
	pg_assert_false(strpos($module, 'ws_messages') !== false || strpos($module, 'ws_channels') !== false, 'no conversations of the team');
}

function test_workspace_my_calendar_ways_in()
{
	pg_assert_contains('href="workspace_my_calendar.php"', ws_mail_list_my_calendar_html(), 'the E-mails toolbar');
	pg_assert_contains('ws_mail_list_my_calendar_html($calendar)', file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_list.php'));

	$groups = ws_nav_groups(array('role' => 3, 'settings' => false), array('base' => '/pg/'));
	$urls = array_column($groups['tools'], 'url', 'key');
	pg_assert_same('/pg/workspace_my_calendar.php', $urls['my_calendar'] ?? '', 'the Workspace menu');
	pg_assert_same('my_calendar', ws_side_screen('/pg/workspace_my_calendar.php'));
	pg_assert_contains("'assets/js/workspace_my_calendar.js'", file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php'), 'bundled');
}
