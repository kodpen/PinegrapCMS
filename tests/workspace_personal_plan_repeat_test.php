<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for repeating personal plans (includes/workspace/personal_plan_repeat.php):
 * the days of a series, its times in a span, where it ends, the rule the
 * drawer sends and the reminder of each time.
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

function workspace_personal_plan_repeat_row($rule)
{
	return array_merge(array(
		'id'               => 5,
		'user_id'          => 3,
		'all_day'          => 0,
		'starts_at'        => strtotime('2026-01-31 10:00:00'),
		'ends_at'          => strtotime('2026-01-31 11:00:00'),
		'repeat_frequency' => 'monthly',
		'repeat_interval'  => 1,
		'repeat_until'     => 0,
		'repeat_count'     => 0,
		'remind_minutes'   => 0,
	), $rule);
}

function test_workspace_personal_plan_repeat_days_keep_the_first_day()
{
	pg_assert_same('2026-02-28', ws_personal_plan_repeat_day('2026-01-31', 'monthly', 1, 1), 'a short month takes its last day');
	pg_assert_same('2026-03-31', ws_personal_plan_repeat_day('2026-01-31', 'monthly', 1, 2), 'and the next month is back on the 31st');
	pg_assert_same('2027-02-28', ws_personal_plan_repeat_day('2024-02-29', 'yearly', 1, 3), '29 February in a common year');
	pg_assert_same('2026-01-15', ws_personal_plan_repeat_day('2026-01-01', 'weekly', 2, 1), 'every two weeks');
	pg_assert_same('2027-01-01', ws_personal_plan_repeat_day('2026-12-30', 'daily', 1, 2), 'across the new year');
}

function test_workspace_personal_plan_times_in_a_span()
{
	$plan = workspace_personal_plan_repeat_row(array());
	$times = ws_personal_plan_times($plan, strtotime('2026-04-01'), strtotime('2026-07-01'));

	pg_assert_same(array('2026-04-30', '2026-05-31', '2026-06-30'), array_column($times, 'day'), 'the times touching the span');
	pg_assert_same('10:00', date('H:i', $times[0]['starts_at']), 'the time of day stays');

	$skipped = ws_personal_plan_times($plan, strtotime('2026-04-01'), strtotime('2026-07-01'), array('2026-05-31'));
	pg_assert_same(array('2026-04-30', '2026-06-30'), array_column($skipped, 'day'), 'a skipped day is left out');

	$single = workspace_personal_plan_repeat_row(array('repeat_frequency' => ''));
	pg_assert_same(1, count(ws_personal_plan_times($single, strtotime('2026-01-01'), strtotime('2026-12-31'))), 'a plan that does not repeat has one time');
}

function test_workspace_personal_plan_time_keeps_its_hour_across_a_clock_change()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	$plan = workspace_personal_plan_repeat_row(array(
		'starts_at'        => strtotime('2026-03-27 09:00:00'),
		'ends_at'          => strtotime('2026-03-27 10:00:00'),
		'repeat_frequency' => 'daily',
	));
	$time = ws_personal_plan_time($plan, 3);

	pg_assert_same('2026-03-30 09:00', date('Y-m-d H:i', $time['starts_at']), 'still 09:00 after the spring clock change');

	date_default_timezone_set($zone);
}

function test_workspace_personal_plan_repeat_until_from_a_day_or_a_count()
{
	$plan = workspace_personal_plan_repeat_row(array('repeat_frequency' => 'weekly', 'starts_at' => strtotime('2026-11-02 10:00:00'), 'ends_at' => strtotime('2026-11-02 11:00:00')));

	pg_assert_same(strtotime('2026-11-16 11:00:00'), ws_personal_plan_repeat_until($plan, 'count', '', 3), 'the end of the third time');
	pg_assert_same(strtotime('2026-11-16 11:00:00'), ws_personal_plan_repeat_until($plan, 'until', '2026-11-20', 0), 'the last time starting by the day');
	pg_assert_same(0, ws_personal_plan_repeat_until($plan, 'never', '', 0), 'no end');
	pg_assert_true(isset(ws_personal_plan_repeat_until($plan, 'until', '2026-10-01', 0)['error']), 'an end before the start is refused');

	$plan['repeat_until'] = ws_personal_plan_repeat_until($plan, 'count', '', 3);
	pg_assert_same(3, count(ws_personal_plan_times($plan, strtotime('2026-11-01'), strtotime('2027-01-01'))), 'the series stops there');
}

function test_workspace_personal_plan_repeat_clean()
{
	$clean = array('starts_at' => strtotime('2026-11-02 10:00:00'), 'ends_at' => strtotime('2026-11-02 11:00:00'));

	pg_assert_same('', ws_personal_plan_repeat_clean($clean, array())['repeat_frequency'], 'no rule sent: none');
	pg_assert_true(isset(ws_personal_plan_repeat_clean($clean, array('repeat' => 'hourly'))['error']), 'an unknown frequency');
	pg_assert_true(isset(ws_personal_plan_repeat_clean($clean, array('repeat' => 'daily', 'repeat_every' => '0'))['error']), 'every 0 days');
	pg_assert_true(isset(ws_personal_plan_repeat_clean($clean, array('repeat' => 'weekly', 'repeat_end' => 'count', 'repeat_count' => '1000'))['error']), 'too many times');

	$long = array('starts_at' => strtotime('2026-11-02 00:00:00'), 'ends_at' => strtotime('2026-11-05 00:00:00'));
	pg_assert_true(isset(ws_personal_plan_repeat_clean($long, array('repeat' => 'daily'))['error']), 'a three-day plan cannot repeat every day');

	$rule = ws_personal_plan_repeat_clean($clean, array('repeat' => 'weekly', 'repeat_every' => '2', 'repeat_end' => 'count', 'repeat_count' => '4'));
	pg_assert_same(array('repeat_frequency' => 'weekly', 'repeat_interval' => 2, 'repeat_until' => strtotime('2026-12-14 11:00:00'), 'repeat_count' => 4), $rule);

	$present = ws_personal_plan_repeat_present(array_merge($clean, $rule));
	pg_assert_same(array('repeat' => 'weekly', 'repeat_every' => 2, 'repeat_end' => 'count', 'repeat_until' => '2026-12-14', 'repeat_count' => '4'), $present, 'read back as the drawer sent it');
	pg_assert_same($rule, ws_personal_plan_repeat_clean($clean, $present), 'a write without a rule keeps the same one');
}

function test_workspace_personal_plan_cut_series_reads_back_its_last_day()
{
	$plan = workspace_personal_plan_repeat_row(array(
		'starts_at'        => strtotime('2026-12-01 10:00:00'),
		'ends_at'          => strtotime('2026-12-01 11:00:00'),
		'repeat_frequency' => 'daily',
	));
	$clean = array('starts_at' => $plan['starts_at'], 'ends_at' => $plan['ends_at']);
	// "This and the following" from 10 December, deleting or changing it: both cut there.
	$cut = array_merge($plan, array('repeat_until' => ws_personal_plan_cut_until($plan, ws_personal_plan_time($plan, 9)), 'repeat_count' => 0));
	$last = function ($plan) {
		$times = ws_personal_plan_times($plan, strtotime('2026-12-01'), strtotime('2027-01-01'));

		return end($times)['day'];
	};

	pg_assert_same('2026-12-09', $last($cut), 'the series ends the day before the cut');

	$present = ws_personal_plan_repeat_present($cut);
	pg_assert_same('until', $present['repeat_end'], 'it ends on a day');
	pg_assert_same('2026-12-09', $present['repeat_until'], 'the drawer shows the last day left');

	$saved = array_merge($cut, ws_personal_plan_repeat_clean($clean, $present));
	pg_assert_same('2026-12-09', $last($saved), 'a save of the whole series keeps the cut time out');
	pg_assert_same($cut['repeat_until'], $saved['repeat_until'], 'and keeps the same end');

	$night = array_merge($plan, array('starts_at' => strtotime('2026-12-01 23:00:00'), 'ends_at' => strtotime('2026-12-02 01:00:00')));
	$night['repeat_until'] = ws_personal_plan_cut_until($night, ws_personal_plan_time($night, 9));
	pg_assert_same('2026-12-09', ws_personal_plan_repeat_present($night)['repeat_until'], 'a time running past midnight reads its start day');
	pg_assert_same('2026-12-09', $last(array_merge($night, ws_personal_plan_repeat_clean(array('starts_at' => $night['starts_at'], 'ends_at' => $night['ends_at']), ws_personal_plan_repeat_present($night)))), 'and stays cut when saved');
}

function test_workspace_personal_plan_reminds_each_time()
{
	$plan = workspace_personal_plan_repeat_row(array(
		'starts_at'        => strtotime('2026-11-02 10:00:00'),
		'ends_at'          => strtotime('2026-11-02 11:00:00'),
		'repeat_frequency' => 'daily',
		'remind_minutes'   => 1440,
	));

	pg_assert_same(strtotime('2026-11-04 10:00:00'), ws_personal_plan_next_remind($plan, strtotime('2026-11-04 09:00:00')), 'a day before the next time');
	pg_assert_same('2026-11-05', ws_personal_plan_remind_day($plan, strtotime('2026-11-04 10:00:00')), 'that reminder is for the 5th');
	pg_assert_same(strtotime('2026-11-05 10:00:00'), ws_personal_plan_next_remind($plan, strtotime('2026-11-04 09:00:00'), array('2026-11-05')), 'a skipped time is passed over');

	$all_day = array_merge($plan, array('all_day' => 1, 'starts_at' => strtotime('2026-11-02 00:00:00'), 'ends_at' => strtotime('2026-11-03 00:00:00'), 'remind_minutes' => 0));
	pg_assert_same(strtotime('2026-11-04 09:00:00'), ws_personal_plan_next_remind($all_day, strtotime('2026-11-03 09:00:00')), 'an all-day plan reminds at 09:00');

	$ended = array_merge($plan, array('repeat_until' => strtotime('2026-11-03 11:00:00')));
	pg_assert_same(0, ws_personal_plan_next_remind($ended, strtotime('2026-11-03 12:00:00')), 'nothing left to remind of');
}

function test_workspace_personal_plan_repeat_wiring()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plans.php');
	pg_assert_contains("OR (repeat_frequency <> '' AND (repeat_until = 0 OR repeat_until > '", $module, 'a running series is read for any span');
	pg_assert_contains('ws_personal_plans_as_times($plans,', $module, 'and drawn as its times, rows the gate let through');
	pg_assert_contains("DELETE FROM ws_personal_plan_skips WHERE plan_id IN (", $module, 'skipped days go with their plan');

	pg_assert_contains("A repeating plan has no roadmap.", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plan_steps.php'), 'a roadmap does not repeat');
	pg_assert_contains('ws_personal_plans_between((int) $viewer[\'id\'], $span[0], $span[1], false)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_personal_plans.php'), 'the API lists a series once');
	pg_assert_contains("'ws_personal_plan_skips',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), 'get_tables()');
}
