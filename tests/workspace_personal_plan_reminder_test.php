<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the time a reminder line of a repeating personal plan names
 * (includes/workspace/personal_plans.php): the time whose reminder moment
 * the line was written for, whatever the lead.
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

function workspace_personal_plan_reminder_row($rule)
{
	return array_merge(array(
		'id'               => 7,
		'user_id'          => 3,
		'all_day'          => 0,
		'starts_at'        => strtotime('2026-12-01 10:00:00'),
		'ends_at'          => strtotime('2026-12-01 11:00:00'),
		'repeat_frequency' => 'daily',
		'repeat_interval'  => 1,
		'repeat_until'     => 0,
		'repeat_count'     => 0,
		'remind_minutes'   => 0,
	), $rule);
}

function workspace_personal_plan_reminder_day($plan, $written, $skips = array())
{
	$time = ws_personal_plan_reminded_time($plan, strtotime($written), $skips);

	return $time ? $time['day'] : '';
}

function test_workspace_personal_plan_reminder_daily_timed()
{
	$plan = workspace_personal_plan_reminder_row(array());
	pg_assert_same('2026-12-04', workspace_personal_plan_reminder_day($plan, '2026-12-04 10:00:30'), 'no lead: the time starting now');

	$plan['remind_minutes'] = 60;
	pg_assert_same('2026-12-04', workspace_personal_plan_reminder_day($plan, '2026-12-04 09:00:30'), 'an hour before: the time an hour later');

	$plan['remind_minutes'] = 1440;
	pg_assert_same('2026-12-05', workspace_personal_plan_reminder_day($plan, '2026-12-04 10:00:30'), 'a day before: tomorrow, not the time starting now');
	pg_assert_same('2026-12-05', workspace_personal_plan_reminder_day($plan, '2026-12-04 10:20:00'), 'a late run still names tomorrow');
	pg_assert_same('2026-12-06', workspace_personal_plan_reminder_day($plan, '2026-12-05 10:00:30', array('2026-12-05')), 'a skipped day does not change the answer for the next one');
	pg_assert_same('2026-12-04', workspace_personal_plan_reminder_day($plan, '2026-12-04 10:00:30', array('2026-12-05')), 'a skipped day is never named');
}

function test_workspace_personal_plan_reminder_daily_all_day()
{
	$plan = workspace_personal_plan_reminder_row(array(
		'all_day'   => 1,
		'starts_at' => strtotime('2026-12-01 00:00:00'),
		'ends_at'   => strtotime('2026-12-02 00:00:00') - 1,
	));
	pg_assert_same('2026-12-04', workspace_personal_plan_reminder_day($plan, '2026-12-04 09:00:30'), 'no lead: 09:00 of the day');

	$plan['remind_minutes'] = 60;
	pg_assert_same('2026-12-04', workspace_personal_plan_reminder_day($plan, '2026-12-04 08:00:30'), 'an hour before 09:00 of the day');

	$plan['remind_minutes'] = 1440;
	pg_assert_same('2026-12-05', workspace_personal_plan_reminder_day($plan, '2026-12-04 09:00:30'), 'a day before: tomorrow');
}

function test_workspace_personal_plan_reminder_weekly()
{
	$plan = workspace_personal_plan_reminder_row(array('repeat_frequency' => 'weekly'));
	pg_assert_same('2026-12-08', workspace_personal_plan_reminder_day($plan, '2026-12-08 10:00:30'), 'no lead');

	$plan['remind_minutes'] = 60;
	pg_assert_same('2026-12-08', workspace_personal_plan_reminder_day($plan, '2026-12-08 09:00:30'), 'an hour before');

	$plan['remind_minutes'] = 1440;
	pg_assert_same('2026-12-08', workspace_personal_plan_reminder_day($plan, '2026-12-07 10:00:30'), 'a day before');

	$all_day = workspace_personal_plan_reminder_row(array(
		'repeat_frequency' => 'weekly',
		'all_day'          => 1,
		'starts_at'        => strtotime('2026-12-01 00:00:00'),
		'ends_at'          => strtotime('2026-12-02 00:00:00') - 1,
		'remind_minutes'   => 1440,
	));
	pg_assert_same('2026-12-08', workspace_personal_plan_reminder_day($all_day, '2026-12-07 09:00:30'), 'all day, a day before');
	pg_assert_same('', workspace_personal_plan_reminder_day($all_day, '2026-11-29 09:00:30'), 'before the first reminder: nothing');
}
