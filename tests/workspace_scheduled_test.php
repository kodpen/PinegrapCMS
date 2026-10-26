<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the clock of the scheduled actions (includes/workspace/
 * scheduled.php): when a time rule falls next (ws_scheduled_next_run()) and
 * whether a moment is inside the hours of an "hours" condition
 * (ws_scheduled_hours_hold(), which ws_scheduled_rules_hold() asks). And for
 * the counts of one channel's tasks, which need their creator to read that
 * channel (ws_scheduled_metric_value(), the summary and its save path).
 *
 * The times are read in a time zone each test sets and puts back, as a site
 * reads them in its own (init.php sets TIMEZONE); Europe/Istanbul has no
 * clock changes, Europe/Berlin changes on 29 March and 25 October 2026.
 *
 * A rule for working days asks ws_day_off(), which reads the company's
 * calendar from the database. It runs in a PHP process of its own here, with
 * a ws_day_off() of the test's: Monday to Friday worked, and the days off
 * the test names. The counts run in one too, with stand-ins for the channels
 * and the right to read them; the summary's save path asks the database
 * before its counts, so its check is read from its source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module files hold functions and constants only; loading them runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// Runs a callback with the PHP time zone set, then puts the zone back.
function workspace_scheduled_test_in_zone($zone, $callback)
{
	$before = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		return $callback();
	} finally {
		date_default_timezone_set($before);
	}
}

// The next run of a time rule after a moment, both as the zone writes them;
// 0 for none. With $offset the UTC offset is written too (clock changes).
function workspace_scheduled_test_next($rule, $after, $offset = false)
{
	$next = ws_scheduled_next_run($rule + array('type' => 'at'), strtotime($after));

	return ($next > 0) ? date($offset ? 'Y-m-d H:i P' : 'Y-m-d H:i', $next) : 0;
}

// A time rule.
function workspace_scheduled_test_rule($date, $time, $repeat, $every = 1, $more = array())
{
	return array('type' => 'at', 'date' => $date, 'time' => $time, 'repeat' => $repeat, 'every' => $every) + $more;
}

// A monthly rule on the 31st falls on the last day of a shorter month and
// goes back to the 31st after it; the 30th the same way. Every two months
// keeps its rhythm, also when the run comes long after the first day.
function test_workspace_scheduled_next_run_month_end()
{
	workspace_scheduled_test_in_zone('Europe/Istanbul', function () {
		$rule = workspace_scheduled_test_rule('2026-01-31', '09:00', 'monthly');

		pg_assert_same('2026-02-28 09:00', workspace_scheduled_test_next($rule, '2026-01-31 09:00:00'), 'the 31st into February');
		pg_assert_same('2026-03-31 09:00', workspace_scheduled_test_next($rule, '2026-02-28 09:00:00'), 'back to the 31st in March');
		pg_assert_same('2026-04-30 09:00', workspace_scheduled_test_next($rule, '2026-03-31 09:00:00'), 'the 31st into a 30-day month');
		pg_assert_same('2026-04-30 09:00', workspace_scheduled_test_next($rule, '2026-04-15 09:00:00'), 'from the middle of a 30-day month');
		pg_assert_same('2026-08-31 09:00', workspace_scheduled_test_next($rule, '2026-08-15 09:00:00'), 'months later (the cursor starts near the moment)');
		pg_assert_same('2028-02-29 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2028-01-31', '09:00', 'monthly'), '2028-01-31 10:00:00'), 'the 31st into February of a leap year');

		$thirtieth = workspace_scheduled_test_rule('2026-01-30', '09:00', 'monthly');
		pg_assert_same('2026-02-28 09:00', workspace_scheduled_test_next($thirtieth, '2026-01-30 10:00:00'), 'the 30th into February');
		pg_assert_same('2026-03-30 09:00', workspace_scheduled_test_next($thirtieth, '2026-02-28 10:00:00'), 'back to the 30th');

		$two = workspace_scheduled_test_rule('2026-01-31', '09:00', 'monthly', 2);
		pg_assert_same('2026-03-31 09:00', workspace_scheduled_test_next($two, '2026-01-31 10:00:00'), 'every two months');
		pg_assert_same('2026-11-30 09:00', workspace_scheduled_test_next($two, '2026-10-01 10:00:00'), 'every two months, long after: January, March ... November');
	});
}

// A yearly rule on 29 February falls on the 28th in the years without one
// and on the 29th again in a leap year; a rule on the 28th stays on the 28th
// in a leap year. A monthly rule from 29 February keeps the 29th.
function test_workspace_scheduled_next_run_29_february()
{
	workspace_scheduled_test_in_zone('Europe/Istanbul', function () {
		$rule = workspace_scheduled_test_rule('2024-02-29', '09:00', 'yearly');

		pg_assert_same('2025-02-28 09:00', workspace_scheduled_test_next($rule, '2024-02-29 10:00:00'), 'into a year without 29 February');
		pg_assert_same('2026-02-28 09:00', workspace_scheduled_test_next($rule, '2025-02-28 10:00:00'), 'the next year without one');
		pg_assert_same('2027-02-28 09:00', workspace_scheduled_test_next($rule, '2026-10-24 10:00:00'), 'years later (the cursor starts near the moment)');
		pg_assert_same('2028-02-29 09:00', workspace_scheduled_test_next($rule, '2027-03-01 10:00:00'), 'back to the 29th in a leap year');
		pg_assert_same('2028-02-29 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2024-02-29', '09:00', 'yearly', 4), '2024-02-29 10:00:00'), 'every four years');
		pg_assert_same('2028-02-28 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-02-28', '09:00', 'yearly'), '2027-02-28 10:00:00'), 'the 28th stays the 28th in a leap year');
		pg_assert_same('2028-03-29 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2028-02-29', '09:00', 'monthly'), '2028-02-29 10:00:00'), 'monthly from 29 February');
	});
}

// A day that does not exist (29 February 2027, 31 February) is refused by the
// form's check with the message it gives any other bad date, for the first
// day and for the last day (until) alike; strtotime() alone would move it
// into March. A row stored before the check keeps the old reading: its run
// falls on 1 March.
function test_workspace_scheduled_rule_input_day_that_does_not_exist()
{
	foreach (array('2027-02-29', '2026-02-31', '2026-04-31', '2026-13-01', '2026-00-10', '2026-1-01') as $date) {
		$checked = ws_scheduled_rule_input(array(), array('type' => 'at', 'date' => $date, 'time' => '09:00', 'repeat' => 'yearly'));
		pg_assert_false($checked['ok'], $date . ' is refused');
		pg_assert_same('The date is not valid.', $checked['error'], $date . ' gets the bad date message');
	}

	foreach (array('2028-02-29', '2026-02-28', '2026-12-31') as $date) {
		$checked = ws_scheduled_rule_input(array(), array('type' => 'at', 'date' => $date, 'time' => '09:00', 'repeat' => 'none'));
		pg_assert_true($checked['ok'], $date . ' is taken');
		pg_assert_same($date, $checked['rule']['date'] ?? null, $date . ' is kept as written');
	}

	$until = ws_scheduled_rule_input(array(), array('type' => 'at', 'date' => '2026-02-20', 'time' => '09:00', 'repeat' => 'daily', 'until' => '2026-02-31'));
	pg_assert_false($until['ok'], 'a last day of 2026-02-31 is refused');
	pg_assert_same('The last day is not valid.', $until['error'], 'with the bad last day message');

	$until = ws_scheduled_rule_input(array(), array('type' => 'at', 'date' => '2028-02-20', 'time' => '09:00', 'repeat' => 'daily', 'until' => '2028-02-29'));
	pg_assert_true($until['ok'], 'a last day of 29 February in a leap year is taken');
	pg_assert_same('2028-02-29', $until['rule']['until'] ?? null, 'and kept as written');
}

function test_workspace_scheduled_next_run_stored_day_that_does_not_exist()
{
	workspace_scheduled_test_in_zone('Europe/Istanbul', function () {
		pg_assert_same('2027-03-01 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2027-02-29', '09:00', 'none'), '2026-10-24 10:00:00'), 'once');
		pg_assert_same('2027-03-01 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2027-02-29', '09:00', 'yearly'), '2026-10-24 10:00:00'), 'yearly, first run');
		pg_assert_same('2028-03-01 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2027-02-29', '09:00', 'yearly'), '2027-03-01 10:00:00'), 'yearly, then 1 March, not 29 February 2028');
	});
}

// A weekly rule on chosen days runs on each of them, every so many weeks
// counted from the week of its first day (Monday to Sunday); the days of the
// first week before the first day are not run. Without days it keeps the
// weekday of its first day.
function test_workspace_scheduled_next_run_every_n_weeks_on_days()
{
	workspace_scheduled_test_in_zone('Europe/Istanbul', function () {
		// Wednesday 21 October 2026, every other week on Monday and Wednesday.
		$rule = workspace_scheduled_test_rule('2026-10-21', '09:00', 'weekly', 2, array('days' => array(1, 3)));

		pg_assert_same('2026-10-21 09:00', workspace_scheduled_test_next($rule, '2026-10-19 08:00:00'), 'the Monday of the first week is before the first day');
		pg_assert_same('2026-10-21 09:00', workspace_scheduled_test_next($rule, '2026-10-20 10:00:00'), 'the first day itself');
		pg_assert_same('2026-11-02 09:00', workspace_scheduled_test_next($rule, '2026-10-21 10:00:00'), 'the Monday two weeks on');
		pg_assert_same('2026-11-04 09:00', workspace_scheduled_test_next($rule, '2026-11-02 10:00:00'), 'the Wednesday of that week');
		pg_assert_same('2026-11-16 09:00', workspace_scheduled_test_next($rule, '2026-11-04 10:00:00'), 'the week between is left out');
		pg_assert_same('2027-03-22 09:00', workspace_scheduled_test_next($rule, '2027-03-10 10:00:00'), 'months later (the probe starts a week before the moment)');

		pg_assert_same('2026-11-11 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-21', '09:00', 'weekly', 3), '2026-10-21 10:00:00'), 'every three weeks on the weekday of the first day');
		pg_assert_same('2026-10-28 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-21', '09:00', 'weekly', 1, array('days' => array(3))), '2026-10-21 09:00:00'), 'a run at the very moment is not the next one');
	});
}

// Nothing falls after the rule's last day; the last day itself counts to its
// end. Steps in minutes are at least WS_SCHEDULED_MIN_MINUTES long.
function test_workspace_scheduled_next_run_until()
{
	workspace_scheduled_test_in_zone('Europe/Istanbul', function () {
		$friday = workspace_scheduled_test_rule('2026-10-21', '09:00', 'weekly', 1, array('days' => array(5), 'until' => '2026-11-06'));
		pg_assert_same('2026-11-06 09:00', workspace_scheduled_test_next($friday, '2026-10-30 10:00:00'), 'weekly: the last day');
		pg_assert_same(0, workspace_scheduled_test_next($friday, '2026-11-06 10:00:00'), 'weekly: nothing after it');

		$daily = workspace_scheduled_test_rule('2026-10-20', '09:00', 'daily', 1, array('until' => '2026-10-24'));
		pg_assert_same('2026-10-24 09:00', workspace_scheduled_test_next($daily, '2026-10-23 10:00:00'), 'daily: the last day');
		pg_assert_same(0, workspace_scheduled_test_next($daily, '2026-10-24 10:00:00'), 'daily: nothing after it');

		$monthly = workspace_scheduled_test_rule('2026-01-31', '09:00', 'monthly', 1, array('until' => '2026-04-29'));
		pg_assert_same(0, workspace_scheduled_test_next($monthly, '2026-03-31 10:00:00'), 'monthly: 30 April is after the last day');

		pg_assert_same(0, workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-20', '23:30', 'hourly', 1, array('until' => '2026-10-20')), '2026-10-20 23:30:00'), 'hourly: the next hour is on the day after');
		pg_assert_same('2026-10-20 09:05', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-20', '09:00', 'minutes', 2), '2026-10-20 09:01:00'), 'every 2 minutes is every ' . WS_SCHEDULED_MIN_MINUTES);
		pg_assert_same('2026-10-20 10:30', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-20', '08:30', 'hourly', 2), '2026-10-20 09:00:00'), 'every two hours from the first time');

		pg_assert_same('2026-10-20 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-20', '09:00', 'none'), '2026-10-19 09:00:00'), 'once, still to come');
		pg_assert_same(0, workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-20', '09:00', 'none'), '2026-10-20 09:00:00'), 'once, gone by');
	});
}

// The clock changes (Europe/Berlin): a rule counted in days keeps its clock
// across them; a clock that does not exist on the day the clocks go forward
// moves an hour on, and one that comes twice when they go back falls on the
// second (winter time). A rule counted in hours is counted in seconds, so
// "every 24 hours" moves an hour with the clock.
function test_workspace_scheduled_next_run_clock_changes()
{
	workspace_scheduled_test_in_zone('Europe/Berlin', function () {
		pg_assert_same('2026-03-29 09:00 +02:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-03-27', '09:00', 'daily'), '2026-03-28 10:00:00', true), 'daily keeps 09:00 into summer time');
		pg_assert_same('2026-03-30 09:00 +02:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-01-01', '09:00', 'daily'), '2026-03-29 10:00:00', true), 'daily from long before, across the change');
		pg_assert_same('2026-03-30 09:00 +02:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-03-02', '09:00', 'weekly', 1, array('days' => array(1))), '2026-03-29 10:00:00', true), 'weekly keeps 09:00');
		pg_assert_same('2026-10-26 09:00 +01:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-24', '09:00', 'daily'), '2026-10-25 10:00:00', true), 'daily keeps 09:00 into winter time');

		$gap = workspace_scheduled_test_rule('2026-03-27', '02:30', 'daily');
		pg_assert_same('2026-03-29 03:30 +02:00', workspace_scheduled_test_next($gap, '2026-03-28 03:00:00', true), '02:30 does not exist on 29 March: 03:30');
		pg_assert_same('2026-03-30 02:30 +02:00', workspace_scheduled_test_next($gap, '2026-03-29 04:00:00', true), 'back to 02:30 the day after');
		pg_assert_same('2026-03-29 03:30 +02:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-03-29', '02:30', 'none'), '2026-03-28 03:00:00', true), 'once at a clock that does not exist');

		pg_assert_same('2026-10-25 02:30 +01:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-24', '02:30', 'daily'), '2026-10-24 03:00:00', true), '02:30 comes twice on 25 October: the second');

		pg_assert_same('2026-03-29 10:00 +02:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-03-28', '09:00', 'hourly', 24), '2026-03-28 10:00:00', true), 'every 24 hours: 10:00 in summer time');
		pg_assert_same('2026-10-25 08:00 +01:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2026-10-24', '09:00', 'hourly', 24), '2026-10-24 10:00:00', true), 'every 24 hours: 08:00 in winter time');
	});
}

/**
 * Runs time rules in a PHP process of its own, with a ws_day_off() that
 * works Monday to Friday and is off on the given days, in Europe/Istanbul.
 *
 * @param array $cases    key => [rule, after]
 * @param array $days_off date => name
 * @return array|null next (key => 'Y-m-d H:i' or 0), asked (the dates
 *                    ws_day_off() was asked about); null where PHP cannot
 *                    be started
 */
function workspace_scheduled_test_next_alone(array $cases, array $days_off)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgnextrun');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. '$days_off = ' . var_export($days_off, true) . ";\n"
		. "\$asked = array();\n"
		. "function ws_day_off(\$date, \$department_id = 0)\n"
		. "{\n"
		. "    global \$days_off, \$asked;\n"
		. "    \$asked[] = \$date;\n"
		. "    if (isset(\$days_off[\$date])) {\n"
		. "        return \$days_off[\$date];\n"
		. "    }\n"
		. "    return ((int) date('N', strtotime(\$date . ' 12:00:00')) >= 6) ? 'Weekend' : '';\n"
		. "}\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php', true) . ";\n"
		. "date_default_timezone_set('Europe/Istanbul');\n"
		. "\$next = array();\n"
		. 'foreach (' . var_export($cases, true) . " as \$key => \$case) {\n"
		. "    \$time = ws_scheduled_next_run(\$case[0], strtotime(\$case[1]));\n"
		. "    \$next[\$key] = (\$time > 0) ? date('Y-m-d H:i', \$time) : 0;\n"
		. "}\n"
		. "echo json_encode(array('next' => \$next, 'asked' => array_values(array_unique(\$asked))));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['next'], $result['asked'])) {
		throw new RuntimeException('The run of the time rules did not answer in JSON: ' . implode("\n", $out));
	}

	return $result;
}

// A rule for working days steps over the weekend and over the company's days
// off, both through ws_day_off(); a first day that is off moves to the next
// working day. A daily rule does not ask.
function test_workspace_scheduled_next_run_workdays_skip_days_off()
{
	$cases = array(
		'friday to monday'  => array(workspace_scheduled_test_rule('2026-10-23', '09:00', 'workdays'), '2026-10-23 10:00:00'),
		'over a day off'    => array(workspace_scheduled_test_rule('2026-10-23', '09:00', 'workdays'), '2026-10-28 10:00:00'),
		'first day off'     => array(workspace_scheduled_test_rule('2026-10-24', '09:00', 'workdays'), '2026-10-20 10:00:00'),
		'long after'        => array(workspace_scheduled_test_rule('2026-01-01', '09:00', 'workdays'), '2026-10-28 10:00:00'),
		'until on a sunday' => array(workspace_scheduled_test_rule('2026-10-23', '09:00', 'workdays', 1, array('until' => '2026-10-25')), '2026-10-23 10:00:00'),
	);

	$result = workspace_scheduled_test_next_alone($cases, array('2026-10-29' => 'Republic Day'));

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		'friday to monday'  => '2026-10-26 09:00',
		'over a day off'    => '2026-10-30 09:00',
		'first day off'     => '2026-10-26 09:00',
		'long after'        => '2026-10-30 09:00',
		'until on a sunday' => 0,
	), $result['next']);

	foreach (array('2026-10-24', '2026-10-25', '2026-10-29') as $date) {
		pg_assert_contains($date, $result['asked'], 'ws_day_off() was asked about ' . $date);
	}

	$daily = workspace_scheduled_test_next_alone(array('daily' => array(workspace_scheduled_test_rule('2026-10-23', '09:00', 'daily'), '2026-10-28 10:00:00')), array('2026-10-29' => 'Republic Day'));
	pg_assert_same(array('daily' => '2026-10-29 09:00'), $daily['next'], 'a daily rule runs on a day off');
	pg_assert_same(array(), $daily['asked'], 'a daily rule does not ask ws_day_off()');
}

// The hours check as ws_scheduled_rules_hold() wrote it before
// ws_scheduled_hours_hold(): the clock of the day as date('H:i') gives it,
// against the rule's from and to.
function workspace_scheduled_test_hours_before($from, $to, $clock)
{
	return ($from < $to)
		? (($clock >= $from) && ($clock < $to))
		: (($clock >= $from) || ($clock < $to));
}

// The helper answers as the old check did, for every minute of the day, for
// windows within the day, past midnight, a minute long, all but a minute and
// one whose start is its end (refused by the form, all day here).
function test_workspace_scheduled_hours_hold_matches_the_old_check()
{
	$windows = array(
		array('09:00', '18:00'), array('18:00', '09:00'), array('22:00', '06:00'), array('06:00', '22:00'),
		array('00:00', '12:00'), array('12:00', '00:00'), array('00:00', '23:59'), array('23:59', '00:00'),
		array('00:00', '00:01'), array('00:01', '00:00'), array('12:00', '12:00'), array('13:37', '13:38'),
	);

	foreach ($windows as $window) {
		$differ = array();

		for ($minute = 0; $minute < 1440; $minute++) {
			$clock = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);

			if (ws_scheduled_hours_hold($window[0], $window[1], $minute) !== workspace_scheduled_test_hours_before($window[0], $window[1], $clock)) {
				$differ[] = $clock;
			}
		}

		pg_assert_same(array(), $differ, $window[0] . ' - ' . $window[1]);
	}
}

// The start is in, the end is out; past midnight the same; minutes outside
// the day are taken round the clock.
function test_workspace_scheduled_hours_hold_edges()
{
	$at = function ($clock) {
		list($hours, $minutes) = array_map('intval', explode(':', $clock));

		return $hours * 60 + $minutes;
	};

	pg_assert_true(ws_scheduled_hours_hold('09:00', '18:00', $at('09:00')), 'exactly the start');
	pg_assert_true(ws_scheduled_hours_hold('09:00', '18:00', $at('17:59')), 'the last minute');
	pg_assert_false(ws_scheduled_hours_hold('09:00', '18:00', $at('18:00')), 'exactly the end');
	pg_assert_false(ws_scheduled_hours_hold('09:00', '18:00', $at('08:59')), 'before the start');

	pg_assert_true(ws_scheduled_hours_hold('22:00', '06:00', $at('22:00')), 'past midnight: exactly the start');
	pg_assert_false(ws_scheduled_hours_hold('22:00', '06:00', $at('21:59')), 'past midnight: before the start');
	pg_assert_true(ws_scheduled_hours_hold('22:00', '06:00', $at('23:59')), 'past midnight: before midnight');
	pg_assert_true(ws_scheduled_hours_hold('22:00', '06:00', $at('00:00')), 'past midnight: midnight');
	pg_assert_true(ws_scheduled_hours_hold('22:00', '06:00', $at('05:59')), 'past midnight: the last minute');
	pg_assert_false(ws_scheduled_hours_hold('22:00', '06:00', $at('06:00')), 'past midnight: exactly the end');
	pg_assert_false(ws_scheduled_hours_hold('22:00', '06:00', $at('12:00')), 'past midnight: midday');

	pg_assert_true(ws_scheduled_hours_hold('12:00', '12:00', $at('03:00')), 'a start equal to the end holds all day');
	pg_assert_true(ws_scheduled_hours_hold('00:00', '06:00', 1440), '1440 is midnight again');
	pg_assert_true(ws_scheduled_hours_hold('22:00', '06:00', -1), '-1 is 23:59');
	pg_assert_false(ws_scheduled_hours_hold('00:00', '23:59', 1439), '23:59 is the end of 00:00 - 23:59');
}

// ws_scheduled_rules_hold() asks the helper with the clock of now, in the
// site's zone: a window that starts this minute holds, one that ends this
// minute does not, and the same past midnight. Asia/Kolkata is five and a
// half hours from UTC, so a clock read in UTC is wrong in its hour and its
// minute. Asked again when the minute turns during the check.
function test_workspace_scheduled_rules_hold_hours_now()
{
	workspace_scheduled_test_in_zone('Asia/Kolkata', function () {
		$clock = function ($minute) {
			$minute = (($minute % 1440) + 1440) % 1440;

			return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
		};

		for ($attempt = 0; $attempt < 3; $attempt++) {
			$before = time();
			$minute = (int) date('G', $before) * 60 + (int) date('i', $before);
			$answers = array();

			foreach (array(
				'starts now'                 => array($clock($minute), $clock($minute + 1)),
				'ends now'                   => array($clock($minute - 1), $clock($minute)),
				'past midnight, starts now'  => array($clock($minute), $clock($minute - 1)),
				'past midnight, ends now'    => array($clock($minute + 1), $clock($minute)),
				'all day but the next hour'  => array($clock($minute + 60), $clock($minute + 1)),
			) as $name => $window) {
				$answers[$name] = ws_scheduled_rules_hold(array(), array(array('type' => 'hours', 'from' => $window[0], 'to' => $window[1])));
			}

			$after = time();

			if (date('H:i', $before) === date('H:i', $after)) {
				break;
			}
		}

		pg_assert_true($answers['starts now']['ok'], 'a window that starts this minute');
		pg_assert_false($answers['ends now']['ok'], 'a window that ends this minute');
		pg_assert_true($answers['past midnight, starts now']['ok'], 'past midnight, starting this minute');
		pg_assert_false($answers['past midnight, ends now']['ok'], 'past midnight, ending this minute');
		pg_assert_true($answers['all day but the next hour']['ok'], 'all day but the next hour');
		pg_assert_contains($clock($minute - 1) . ' – ' . $clock($minute), $answers['ends now']['reason'], 'the reason names the hours');
	});
}

/**
 * Runs the counts in a PHP process of its own: functions.php, then stand-ins
 * for what scheduled.php asks of the other Workspace files (a channel, the
 * right to read it, the time rows, a message sent), then scheduled.php.
 * Channels 7 (#general) and 8 (#secret) are there and 9 is gone; the
 * creator reads 7 only and holds no shop right. Without a connection a query
 * throws (pg_error_throws()), answered here as array('database' => the
 * message): the count went on to its query.
 *
 * @return array|null values (key:channel => answer), values_minutes_asked
 *                    (the channels the time rows were read for), hold (a
 *                    count condition), fill (placeholders), summary, sent
 *                    (the messages it wrote); null where PHP cannot be
 *                    started
 */
function workspace_scheduled_test_counts_alone()
{
	static $result = null;

	if ($result !== null) {
		return $result;
	}

	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcounts');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
<?php
require FUNCTIONS_FILE;

if (!class_exists('db')) {
	class db
	{
		public static $con;
	}
}

pg_error_throws(true);
define('ECOMMERCE', true);
define('WS_MESSAGE_MAX', 4000);

$minutes_asked = array();
$sent = array();

function ws_channel($channel_id)
{
	$channels = array(
		7 => array('id' => 7, 'name' => 'general', 'kind' => 'public', 'archived_at' => 0),
		8 => array('id' => 8, 'name' => 'secret', 'kind' => 'private', 'archived_at' => 0),
	);

	return $channels[(int) $channel_id] ?? null;
}

function ws_can_read_channel($rights, $channel)
{
	return is_array($channel) && in_array((int) $channel['id'], $rights['reads'], true);
}

function ws_can_post_channel($rights, $channel)
{
	return ws_can_read_channel($rights, $channel);
}

function ws_task_time_ready()
{
	return true;
}

function ws_task_time_week_minutes($channel_id = 0)
{
	$GLOBALS['minutes_asked'][] = (int) $channel_id;

	return 90;
}

function ws_minutes_label($minutes)
{
	return (int) $minutes . ' min';
}

function ws_message_send($viewer, $channel, $body, $options = array())
{
	$GLOBALS['sent'][] = array('channel_id' => (int) $channel['id'], 'body' => $body);

	return array('ok' => true, 'error' => '', 'message_id' => 31);
}

require SCHEDULED_FILE;
date_default_timezone_set('Europe/Istanbul');

$viewer = array('id' => 5, 'role' => 2, 'member' => true, 'ecommerce' => false, 'reads' => array(7));

$run = function ($callback) {
	try {
		return $callback();
	} catch (RuntimeException $e) {
		return array('database' => $e->getMessage());
	}
};

$out = array('values' => array(), 'hold' => array());

foreach (array('tasks_open', 'tasks_overdue', 'tasks_due_today', 'tasks_done_today', 'time_logged_week') as $key) {
	foreach (array(7, 8, 9, 0) as $param) {
		$out['values'][$key . ':' . $param] = $run(function () use ($viewer, $key, $param) {
			$count = ws_scheduled_metric_value($viewer, $key, $param);

			return array('ok' => $count['ok'], 'text' => $count['text'], 'error' => $count['error']);
		});
	}
}

$out['values_minutes_asked'] = $minutes_asked;

foreach (array('read' => 7, 'not read' => 8) as $name => $param) {
	$out['hold'][$name] = $run(function () use ($viewer, $param) {
		return ws_scheduled_rules_hold($viewer, array(array('type' => 'metric', 'metric' => 'time_logged_week', 'param' => $param, 'op' => 'gt', 'value' => 0)), array('last_run_at' => 0));
	});
}

$out['fill'] = $run(function () use ($viewer) {
	return ws_scheduled_fill('{{count:tasks_open:8}} {{count:time_logged_week:9}} {{count:time_logged_week:7}}', array('viewer' => $viewer, 'row' => array()));
});

$out['summary'] = $run(function () use ($viewer) {
	return ws_scheduled_summary($viewer, array('name' => 'Counts', 'last_run_at' => 0), array(
		'title'      => 'Counts',
		'channel_id' => 7,
		'to'         => array(),
		'metrics'    => array(
			array('key' => 'tasks_open', 'param' => 8),
			array('key' => 'time_logged_week', 'param' => 7),
			array('key' => 'tasks_overdue', 'param' => 9),
			array('key' => 'stock_low', 'param' => 5),
		),
	), array('viewer' => $viewer, 'row' => array()));
});

$out['sent'] = $sent;

echo json_encode($out);
PHP;

	file_put_contents($file, strtr($script, array(
		'FUNCTIONS_FILE' => var_export(PG_FUNCTIONS_DIR . '/functions.php', true),
		'SCHEDULED_FILE' => var_export(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php', true),
	)));

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['values'], $result['hold'], $result['summary'], $result['sent'])) {
		throw new RuntimeException('The run of the counts did not answer in JSON: ' . implode("\n", $out));
	}

	return $result;
}

// A count of one channel's tasks needs its creator to read that channel,
// asked at every count: one they do not read, or one that is gone, is
// refused before anything is counted, for each count of a channel. The
// channel they read, or every channel (0), goes on to be counted.
function test_workspace_scheduled_count_of_a_channel_needs_the_right_to_read_it()
{
	$result = workspace_scheduled_test_counts_alone();

	if ($result === null) {
		return;
	}

	$refused = array('ok' => false, 'text' => '', 'error' => 'You may not see this count.');

	foreach (array('tasks_open', 'tasks_overdue', 'tasks_due_today', 'tasks_done_today', 'time_logged_week') as $key) {
		$counted = ($key === 'time_logged_week') ? array('ok' => true, 'text' => '90 min', 'error' => '') : array('database' => 'No database connection.');

		pg_assert_same($refused, $result['values'][$key . ':8'] ?? null, $key . ' of a channel the creator does not read');
		pg_assert_same($refused, $result['values'][$key . ':9'] ?? null, $key . ' of a channel that is gone');
		pg_assert_same($counted, $result['values'][$key . ':7'] ?? null, $key . ' of the channel the creator reads is counted');
		pg_assert_same($counted, $result['values'][$key . ':0'] ?? null, $key . ' of every channel is counted');
	}

	pg_assert_same(array(7, 0), $result['values_minutes_asked'], 'the time rows are read for the channel the creator reads and for all, never for the others');
}

// A count condition at a run, the {{count:...}} placeholders and a summary
// ask the same: a refused count stops the condition, is a "?" in a text and
// a "—" in the summary, whose line then leaves the channel's name out (a
// threshold is still given).
function test_workspace_scheduled_count_of_a_channel_refused_in_conditions_texts_and_summaries()
{
	$result = workspace_scheduled_test_counts_alone();

	if ($result === null) {
		return;
	}

	pg_assert_same(array('ok' => true, 'reason' => ''), $result['hold']['read'] ?? null, 'a count condition on the channel the creator reads holds');
	pg_assert_same(array('ok' => false, 'reason' => 'You may not see this count.'), $result['hold']['not read'] ?? null, 'one on a channel they do not read stops the run');
	pg_assert_same('? ? 90 min', $result['fill'], 'the placeholders of a channel not read and of one that is gone');

	pg_assert_same(array('ok' => true, 'text' => 'summary sent to #general', 'message_id' => 31), $result['summary'], 'the summary is sent');
	pg_assert_same(1, count($result['sent']), 'as one message');

	$body = (string) ($result['sent'][0]['body'] ?? '');

	pg_assert_same(array(
		'• Open tasks: **—**',
		'• Time spent on tasks this week (#general): **90 min**',
		'• Overdue tasks: **—**',
		'• Products low in stock (≤ 5): **—**',
	), array_slice(explode("\n", $body), 1), 'a refused count of a channel is drawn without the channel');
	pg_assert_false(strpos($body, 'secret') !== false, 'the channel the creator does not read is not named');
}

// The code of a loaded function without its comments and with its runs of
// whitespace made one space, so a check of what it calls is misled neither
// by a comment nor by the indentation.
function workspace_scheduled_test_code($function)
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return preg_replace('/\s+/', ' ', $code);
}

// A summary's counts are checked as they are saved, as a count condition's
// are: a count of a channel the creator does not read, or of one that is
// gone, is refused with the condition's sentence. ws_scheduled_action_input()
// asks ws_scheduled_chains_ready() (a query) before it, so the check is read
// from its code: in the loop over the counts, before a count is kept.
function test_workspace_scheduled_report_input_refuses_a_channel_not_read()
{
	$code = workspace_scheduled_test_code('ws_scheduled_action_input');
	$start = strpos($code, "case 'report':");
	$end = ($start === false) ? false : strpos($code, "case 'trigger':", $start);
	$report = (($start !== false) && ($end !== false)) ? substr($code, $start, $end - $start) : '';

	pg_assert_true($report !== '', 'ws_scheduled_action_input() has a report case before its trigger case');

	$last = -1;

	foreach (array(
		'the loop over the counts'   => "foreach ((array) (\$action['metrics'] ?? array()) as \$item) {",
		'a count of a channel'       => "if ((\$metrics[\$key]['scope'] === 'channel') && (\$param > 0)) {",
		'looks the channel up'       => '$channel = ws_channel($param);',
		'asks whether it is read'    => 'if (!$channel || !ws_can_read_channel($viewer, $channel)) {',
		'and refuses it'             => "return \$fail(lang('That channel could not be found.'));",
		'before the count is kept'   => "\$list[] = array('key' => \$key, 'param' => \$param);",
	) as $name => $needle) {
		$at = strpos($report, $needle);

		pg_assert_true(($at !== false) && ($at > $last), 'the report case ' . $name);
		$last = ($at !== false) ? $at : $last;
	}
}
