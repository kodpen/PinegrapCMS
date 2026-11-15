<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's card facts in a channel (person_card.php): the
 * working day, the away days from the plan and the mail away range, the
 * departments with their leads, the person's local time, and who is sent
 * the last seen.
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

function test_workspace_person_card_workday()
{
	$day = ws_person_card_workday(450, 31);

	pg_assert_same(450, $day['minutes'], 'the day in minutes');
	pg_assert_same(array(1, 2, 3, 4, 5), $day['days'], 'Monday to Friday, ISO numbers');
	pg_assert_same(ws_minutes_label(450), $day['hours'], 'the hours in the planning words');

	pg_assert_same(array(1, 3, 6, 7), ws_person_card_workday(480, 1 + 4 + 32 + 64)['days'], 'any days of the week');
	pg_assert_same(array(), ws_person_card_workday(0, 0)['days'], 'no day');
}

function test_workspace_person_card_away()
{
	$at = function ($day, $time = '00:00:00') {
		return (int) strtotime($day . ' ' . $time);
	};
	$events = array(
		array('id' => 1, 'kind' => 'leave', 'scope' => 'people', 'department_id' => 0, 'people' => array(7), 'title' => 'Holiday at sea',
			'starts_at' => $at('2026-11-10'), 'ends_at' => $at('2026-11-12', '23:59:59')),
		array('id' => 2, 'kind' => 'leave', 'scope' => 'people', 'department_id' => 0, 'people' => array(7, 8), 'title' => '',
			'starts_at' => $at('2026-11-14'), 'ends_at' => $at('2026-11-16', '23:59:59')),
		array('id' => 3, 'kind' => 'leave', 'scope' => 'people', 'department_id' => 0, 'people' => array(8), 'title' => 'Not theirs',
			'starts_at' => $at('2026-11-20'), 'ends_at' => $at('2026-11-21', '23:59:59')),
		array('id' => 4, 'kind' => 'meeting', 'scope' => 'people', 'department_id' => 0, 'people' => array(7), 'title' => 'A meeting',
			'starts_at' => $at('2026-11-18', '10:00:00'), 'ends_at' => $at('2026-11-18', '11:00:00')),
		array('id' => 5, 'kind' => 'leave', 'scope' => 'department', 'department_id' => 3, 'people' => array(), 'title' => 'Team day off',
			'starts_at' => $at('2026-11-25'), 'ends_at' => $at('2026-11-25', '23:59:59')),
	);
	$mail = array('start' => '2026-12-01', 'end' => '2026-12-03', 'text' => 'Private auto reply', 'stand_in' => 8, 'plan_id' => 0);

	$away = ws_person_card_away($events, 7, array(3), '2026-11-15', $mail);

	pg_assert_same(array(
		array('start' => '2026-11-14', 'end' => '2026-11-16', 'now' => true, 'source' => 'plan', 'title' => ''),
		array('start' => '2026-11-25', 'end' => '2026-11-25', 'now' => false, 'source' => 'plan', 'title' => 'Team day off'),
		array('start' => '2026-12-01', 'end' => '2026-12-03', 'now' => false, 'source' => 'mail', 'title' => ''),
	), $away, 'leaves from today on that apply to the person, and the mail range without its text');

	pg_assert_same(array(), ws_person_card_away($events, 9, array(), '2026-11-15', null), 'nobody else\'s leave');
	pg_assert_same(1, count(ws_person_card_away($events, 7, array(3), '2026-11-15', $mail, 1)), 'at most as many as asked');
	pg_assert_same(array(), ws_person_card_away(array(), 7, array(), '2026-12-04', $mail), 'a mail range that has ended');
}

function test_workspace_person_card_departments()
{
	$departments = array(
		3 => array('id' => 3, 'name' => 'Design', 'color' => 'violet', 'channel_id' => 0, 'archived' => false, 'members' => array(7, 8), 'leads' => array(8)),
		4 => array('id' => 4, 'name' => 'Sales', 'color' => 'teal', 'channel_id' => 0, 'archived' => false, 'members' => array(7), 'leads' => array(7)),
		5 => array('id' => 5, 'name' => 'Support', 'color' => 'rose', 'channel_id' => 0, 'archived' => false, 'members' => array(8), 'leads' => array()),
	);
	$names = array(8 => 'Ayşe Yılmaz');

	pg_assert_same(array(
		array('id' => 4, 'name' => 'Sales', 'color' => 'teal', 'lead' => true, 'leads' => array()),
		array('id' => 3, 'name' => 'Design', 'color' => 'violet', 'lead' => false, 'leads' => array(array('id' => 8, 'name' => 'Ayşe Yılmaz'))),
	), ws_person_card_departments(7, array(4, 3), $departments, $names), 'primary first, the person\'s own lead mark, the other leads by name');

	pg_assert_same(array(), ws_person_card_departments(7, array(9), $departments, $names), 'an archived or unknown department is left out');
}

function test_workspace_person_card_clock()
{
	$now = 1763190000; // 2025-11-15 07:00:00 UTC

	pg_assert_same(array('zone' => 'Europe/Istanbul', 'time' => '10:00', 'other' => true), ws_person_card_clock('Europe/Istanbul', 'UTC', $now), 'the person\'s zone');
	pg_assert_same(array('zone' => 'UTC', 'time' => '07:00', 'other' => false), ws_person_card_clock('', 'UTC', $now), 'no zone of their own: the site\'s');
	pg_assert_same(array('zone' => 'UTC', 'time' => '07:00', 'other' => false), ws_person_card_clock('Mars/Base', 'UTC', $now), 'an unknown zone: the site\'s');
}

function test_workspace_person_card_action_listed()
{
	pg_assert_same(array('ws_person_card'), ws_person_card_actions());

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/workspace.php');
	pg_assert_true(strpos($panel, 'ws_person_card_actions()') !== false, 'the panel dispatches the action');
}
