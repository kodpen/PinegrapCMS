<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the resumable scheduled passes in includes/fn/cron.php: the tick
 * budget inside the process's time limit, the "due again" stamp, the lane a
 * tick hands out when both are due, and the completion markers the health
 * screens read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No time limit: the pass keeps its own budget.
function test_cron_tick_budget_without_limit()
{
	pg_assert_same(120, pg_cron_tick_budget(120, 1000.0, 0, 1050.0));
}

// A limit caps the budget, minus the margin for the closing writes.
function test_cron_tick_budget_capped_by_limit()
{
	// 300 s limit, 60 s gone, 15 s margin: 225 s left, the budget wins.
	pg_assert_same(120, pg_cron_tick_budget(120, 1000.0, 300, 1060.0), 'budget smaller');

	// 120 s limit, 30 s gone, 15 s margin: 75 s left.
	pg_assert_same(75, pg_cron_tick_budget(120, 1000.0, 120, 1030.0), 'limit smaller');

	// 30 s limit: the margin is a fifth of it, 6 s; 10 s gone leaves 14 s.
	pg_assert_same(14, pg_cron_tick_budget(120, 1000.0, 30, 1010.0), 'short limit');
}

// Time already spent: one second, never zero or less, so a pass still keeps
// its place and leaves.
function test_cron_tick_budget_never_below_one()
{
	pg_assert_same(1, pg_cron_tick_budget(120, 1000.0, 60, 1100.0));
	pg_assert_same(1, pg_cron_tick_budget(0, 1000.0, 0, 1000.0));
}

// The limit: JOB_TIME_LIMIT when set above zero, else max_execution_time.
function test_cron_time_limit_choice()
{
	pg_assert_same(600, pg_cron_time_limit_choice(600, '30'), 'setting');
	pg_assert_same(30, pg_cron_time_limit_choice(null, '30'), 'php limit');
	pg_assert_same(30, pg_cron_time_limit_choice(0, '30'), 'setting zero');
	pg_assert_same(0, pg_cron_time_limit_choice(null, '0'), 'no limit');
	pg_assert_same(0, pg_cron_time_limit_choice(null, false), 'unreadable');
}

// "Due again in N seconds" is a last run moved back so the interval ends then.
function test_cron_due_again_stamp()
{
	$now = 1000000;

	pg_assert_same($now - 86400 + 60, pg_cron_due_again_stamp($now, 86400, 60));

	// Never later than now: a wait longer than the interval is the interval.
	pg_assert_same($now, pg_cron_due_again_stamp($now, 300, 900));
	pg_assert_same($now - 300, pg_cron_due_again_stamp($now, 300, -5), 'negative is now');
}

// The stamp makes the job due when asked, and a job that waited longer still
// goes first in its lane.
function test_cron_due_again_job_waits_its_turn()
{
	$now = 1000000;

	$jobs = array(
		'a11y'   => array('dispatch' => true, 'interval' => 86400, 'lane' => 'heavy'),
		'backup' => array('dispatch' => true, 'interval' => 604800, 'lane' => 'heavy'),
	);

	$runs = array(
		'a11y'   => array('last_run_at' => pg_cron_due_again_stamp($now, 86400, 60), 'locked_until' => 0),
		'backup' => array('last_run_at' => $now - 700000, 'locked_until' => 0),
	);

	pg_assert_same(null, pg_cron_pick(array('a11y' => $jobs['a11y']), $runs, $now + 59)['heavy'], 'not yet');
	pg_assert_same('a11y', pg_cron_pick(array('a11y' => $jobs['a11y']), $runs, $now + 60)['heavy'], 'due');
	pg_assert_same('backup', pg_cron_pick($jobs, $runs, $now + 60)['heavy'], 'longer wait first');
}

// Both lanes due: the job due for longer goes first, so a heavy pass that
// asks to come back every minute does not keep the light jobs out.
function test_cron_lane_choice_by_due_time()
{
	$now = 1000000;

	$jobs = array(
		'mail'   => array('dispatch' => true, 'interval' => 300, 'lane' => 'light'),
		'a11y'   => array('dispatch' => true, 'interval' => 86400, 'lane' => 'heavy'),
	);

	$runs = array(
		'mail' => array('last_run_at' => $now - 400, 'locked_until' => 0),
		'a11y' => array('last_run_at' => pg_cron_due_again_stamp($now - 120, 86400, 60), 'locked_until' => 0),
	);

	$pick = pg_cron_pick($jobs, $runs, $now);

	// The mail job was due 100 s ago, the pass 60 s ago.
	pg_assert_same('mail', pg_cron_lane_choice($jobs, $runs, $pick));

	$runs['mail']['last_run_at'] = $now - 330;

	pg_assert_same('a11y', pg_cron_lane_choice($jobs, $runs, $pick), 'pass due longer');

	pg_assert_same('a11y', pg_cron_lane_choice($jobs, $runs, array('light' => null, 'heavy' => 'a11y')), 'one lane');
	pg_assert_same(null, pg_cron_lane_choice($jobs, $runs, array('light' => null, 'heavy' => null)), 'none');

	// A tie goes to the light lane.
	$runs['mail']['last_run_at'] = $now - 360;

	pg_assert_same('mail', pg_cron_lane_choice($jobs, $runs, $pick), 'tie');
}

// The completion marker: written beside the run of a dispatched job, never
// for the general job, an inline job or a name outside the catalogue.
function test_cron_done_marker()
{
	$jobs = array(
		'job'     => array('dispatch' => false, 'interval' => 60),
		'webhook' => array('dispatch' => true, 'inline' => true, 'interval' => 60),
		'a11y'    => array('dispatch' => true, 'interval' => 86400),
	);

	pg_assert_same('a11y_done', pg_cron_done_marker('a11y', $jobs));
	pg_assert_same('', pg_cron_done_marker('job', $jobs), 'host');
	pg_assert_same('', pg_cron_done_marker('webhook', $jobs), 'inline');
	pg_assert_same('', pg_cron_done_marker('seo_link_graph', $jobs), 'not catalogued');
}

// Health reads the completion marker when there is one, the run otherwise.
function test_cron_health_runs()
{
	$runs = array(
		'a11y'      => 990000,
		'a11y_done' => 800000,
		'index'     => 950000,
		'orphan_done' => 700000,
	);

	pg_assert_same(
		array('a11y' => 800000, 'a11y_done' => 800000, 'index' => 950000, 'orphan_done' => 700000, 'orphan' => 700000),
		pg_cron_health_runs($runs));

	pg_assert_same(null, pg_cron_health_runs(null), 'no table');
}
