<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the week's hour grid on My calendar
 * (includes/workspace/my_calendar.php): which items stand on the hours and
 * which in the strip above them, where each stands, how items that overlap
 * share the day's width, and where an event stops on its day
 * (ws_calendar_until()).
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

// start-end column/columns, one line an item, in the order given.
function workspace_my_calendar_hours_test_lines($places)
{
	return array_values(array_map(function ($place) {
		return $place['start'] . '-' . $place['end'] . ' ' . $place['column'] . '/' . $place['columns'];
	}, $places));
}

function test_workspace_my_calendar_hours_minutes_read_a_time()
{
	pg_assert_same(0, ws_my_calendar_minutes('00:00'));
	pg_assert_same(570, ws_my_calendar_minutes('09:30'));
	pg_assert_same(1440, ws_my_calendar_minutes('24:00'), 'the end of the day');

	foreach (array('', '9:30', '24:01', '25:00', '12:60', null, array('09:00')) as $bad) {
		pg_assert_same(-1, ws_my_calendar_minutes($bad), 'no time: ' . json_encode($bad));
	}
}

function test_workspace_my_calendar_hours_apart_take_the_whole_width()
{
	pg_assert_same(array('540-600 0/1', '600-660 0/1'), workspace_my_calendar_hours_test_lines(ws_my_calendar_hours_place(array(
		array('start' => 540, 'end' => 600),
		array('start' => 600, 'end' => 660),
	))), 'one ending as the next starts does not overlap');
}

function test_workspace_my_calendar_hours_overlapping_share_the_width()
{
	pg_assert_same(array('540-660 0/2', '570-600 1/2', '600-630 1/2', '720-780 0/1'), workspace_my_calendar_hours_test_lines(ws_my_calendar_hours_place(array(
		array('start' => 540, 'end' => 660),
		array('start' => 570, 'end' => 600),
		array('start' => 600, 'end' => 630),
		array('start' => 720, 'end' => 780),
	))), 'the second column is taken again once it is free; a later item stands alone');

	pg_assert_same(array('600-660 1/3', '540-720 0/3', '630-690 2/3'), workspace_my_calendar_hours_test_lines(ws_my_calendar_hours_place(array(
		array('start' => 600, 'end' => 660),
		array('start' => 540, 'end' => 720),
		array('start' => 630, 'end' => 690),
	))), 'laid by start whatever order they come in; three at once are three columns');

	pg_assert_same(array('540-600 1/2', '540-660 0/2'), workspace_my_calendar_hours_test_lines(ws_my_calendar_hours_place(array(
		array('start' => 540, 'end' => 600),
		array('start' => 540, 'end' => 660),
	))), 'of two starting together the longer stands first');
}

function test_workspace_my_calendar_hours_short_items_are_drawn_as_the_shortest()
{
	pg_assert_same(array('600-630 0/2', '615-645 1/2'), workspace_my_calendar_hours_test_lines(ws_my_calendar_hours_place(array(
		array('start' => 600, 'end' => 600),
		array('start' => 615, 'end' => 620),
	))), 'a moment and a short item take ' . WS_MY_CALENDAR_HOUR_SHORTEST . ' minutes and overlap as drawn');

	pg_assert_same(array('1410-1440 0/1'), workspace_my_calendar_hours_test_lines(ws_my_calendar_hours_place(array(
		array('start' => 1430, 'end' => 1430),
	))), 'never past midnight');
}

function test_workspace_my_calendar_hours_day_puts_timed_items_on_the_grid()
{
	$day = array(
		'date'   => '2026-11-18',
		'events' => array(
			array('title' => 'Leave', 'time' => '', 'until' => ''),
			array('title' => 'Meeting', 'time' => '10:00', 'until' => '11:30'),
			array('title' => 'Middle of a trip', 'time' => '00:00', 'until' => '24:00'),
			array('title' => 'Late call', 'time' => '22:00', 'until' => '24:00'),
			array('title' => 'Old item', 'time' => '09:00'),
		),
		'tasks'  => array(
			array('title' => 'No time', 'time' => '', 'due_date' => '2026-11-18'),
			array('title' => 'Due at 11', 'time' => '11:00', 'due_date' => '2026-11-18'),
			array('title' => 'Copy', 'time' => '', 'due_date' => '2026-11-18', 'upcoming' => true),
			array('title' => 'Early', 'time' => '00:10', 'due_date' => '2026-11-18'),
		),
	);

	$lines = array_map(function ($place) {
		return $place['type'] . ' ' . $place['index'] . ' ' . $place['start'] . '-' . $place['end'] . ' ' . $place['column'] . '/' . $place['columns'];
	}, ws_my_calendar_day_hours($day));

	pg_assert_same(array(
		'event 1 600-690 0/2',
		'event 3 1320-1440 0/1',
		'event 4 540-570 0/1',
		'task 1 630-660 1/2',
		'task 3 0-30 0/1',
	), $lines, 'whole-day events, a day filled by a long event and tasks without a time stay in the strip; a task is a mark ending at its time');
}

function test_workspace_my_calendar_hours_task_due_another_day_stays_in_the_strip()
{
	pg_assert_same(array(), ws_my_calendar_day_hours(array(
		'date'   => '2026-11-18',
		'events' => array(),
		'tasks'  => array(array('title' => 'Due later', 'time' => '11:00', 'due_date' => '2026-11-20')),
	)), 'only on its due day');
}

function test_workspace_my_calendar_hours_until_is_where_an_event_stops_on_its_day()
{
	$day_end = strtotime('2026-11-19 00:00:00');

	pg_assert_same('11:30', ws_calendar_until(array('starts_at' => strtotime('2026-11-18 10:00'), 'ends_at' => strtotime('2026-11-18 11:30'), 'all_day' => 0), $day_end));
	pg_assert_same('24:00', ws_calendar_until(array('starts_at' => strtotime('2026-11-18 22:00'), 'ends_at' => strtotime('2026-11-19 01:00'), 'all_day' => 0), $day_end), 'runs on past the day');
	pg_assert_same('24:00', ws_calendar_until(array('starts_at' => strtotime('2026-11-18 22:00'), 'ends_at' => $day_end, 'all_day' => 0), $day_end), 'until midnight');
	pg_assert_same('10:00', ws_calendar_until(array('starts_at' => strtotime('2026-11-18 10:00'), 'ends_at' => strtotime('2026-11-18 09:00'), 'all_day' => 0), $day_end), 'an end before the start is the start');
	pg_assert_same('', ws_calendar_until(array('starts_at' => strtotime('2026-11-18 00:00'), 'ends_at' => $day_end, 'all_day' => 1), $day_end), 'whole day');

	$days = ws_calendar_plan_place(array('2026-11-18' => array('events' => array())), array(
		'id' => 4, 'kind' => 'meeting', 'title' => 'Review', 'all_day' => 0, 'scope' => 'company', 'people' => array(),
		'starts_at' => strtotime('2026-11-18 14:00'), 'ends_at' => strtotime('2026-11-18 15:15'),
	));
	pg_assert_same('15:15', $days['2026-11-18']['events'][0]['until'], 'a plan item carries its end');
}
