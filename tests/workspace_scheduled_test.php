<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the clock of the scheduled actions (includes/workspace/
 * scheduled.php): when a time rule falls next (ws_scheduled_next_run()) and
 * whether a moment is inside the hours of an "hours" condition
 * (ws_scheduled_hours_hold(), which ws_scheduled_rules_hold() asks).
 *
 * The times are read in a time zone each test sets and puts back, as a site
 * reads them in its own (init.php sets TIMEZONE); Europe/Istanbul has no
 * clock changes, Europe/Berlin changes on 29 March and 25 October 2026.
 *
 * A rule for working days asks ws_day_off(), which reads the company's
 * calendar from the database. It runs in a PHP process of its own here, with
 * a ws_day_off() of the test's: Monday to Friday worked, and the days off
 * the test names.
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

// A day that does not exist (29 February 2027) is not refused by the form's
// check: strtotime() moves it to 1 March, so a single run happens on 1 March
// and a yearly rule runs on 1 March every year. Pinned as it behaves.
function test_workspace_scheduled_next_run_day_that_does_not_exist()
{
	workspace_scheduled_test_in_zone('Europe/Istanbul', function () {
		pg_assert_same('2027-03-01 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2027-02-29', '09:00', 'none'), '2026-10-24 10:00:00'), 'once');
		pg_assert_same('2027-03-01 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2027-02-29', '09:00', 'yearly'), '2026-10-24 10:00:00'), 'yearly, first run');
		pg_assert_same('2028-03-01 09:00', workspace_scheduled_test_next(workspace_scheduled_test_rule('2027-02-29', '09:00', 'yearly'), '2027-03-01 10:00:00'), 'yearly, then 1 March, not 29 February 2028');

		// Wrong result: ws_scheduled_rule_input() takes 2027-02-29 (and
		// 2026-02-31) as a valid date, because strtotime() does not return
		// false for it; it should refuse it with "The date is not valid.".
		foreach (array('2027-02-29', '2026-02-31') as $date) {
			$checked = ws_scheduled_rule_input(array(), array('type' => 'at', 'date' => $date, 'time' => '09:00', 'repeat' => 'yearly'));
			pg_assert_true($checked['ok'], $date . ' is taken (it should not be)');
		}

		$refused = ws_scheduled_rule_input(array(), array('type' => 'at', 'date' => '2026-13-01', 'time' => '09:00', 'repeat' => 'none'));
		pg_assert_false($refused['ok'], 'a thirteenth month is refused');
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

// ws_scheduled_rules_hold() asks the helper with the clock of now: a window
// that starts this minute holds, one that ends this minute does not, and the
// same past midnight. Asked again when the minute turns during the check.
function test_workspace_scheduled_rules_hold_hours_now()
{
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
}
