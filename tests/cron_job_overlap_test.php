<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the general job's abandoned-order pass and the dispatched light jobs
 * against overlapping runs and the process time limit. Two ticks of the
 * general job side by side both read the same incomplete orders and each
 * made an order_abandoned campaign, so the customer was mailed twice. A
 * light job working its own fixed budget past the time limit a host runner
 * kills job.php at kept its lock, and the whole light lane, for an hour.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The pass claims its lock before it reads the orders, gives it back after
// the campaigns are made, and a tick that finds it held makes none.
function test_job_abandoned_orders_pass_claims_its_lock()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	$claim = strpos($source, "pg_cron_claim(PG_ORDER_ABANDONED_LOCK, ");
	$read = strpos($source, "(orders.status = 'incomplete')");
	$make = strpos($source, "'action' => 'order_abandoned',");
	$release = strpos($source, "pg_cron_release(PG_ORDER_ABANDONED_LOCK);");

	pg_assert_true($claim !== false, 'the pass takes its lock');
	pg_assert_true(($claim !== false) && ($read !== false) && ($claim < $read), 'the lock is taken before the orders are read');
	pg_assert_true(($make !== false) && ($release !== false) && ($make < $release), 'the lock is given back after the campaigns are made');
	pg_assert_same(1, preg_match("/register_shutdown_function\('pg_cron_release', PG_ORDER_ABANDONED_LOCK\);/", $source), 'a run that dies in the pass gives the lock back');
	pg_assert_same(1, preg_match("/define\('PG_ORDER_ABANDONED_LOCK', '[a-z_]+'\);/", $source), 'the lock has its own name in cron_runs');
}

// The lock name is not a catalogue job, so it never takes a turn in the
// dispatcher's rotation or shows as a job on the health screens.
function test_job_abandoned_orders_lock_is_no_catalogue_job()
{
	preg_match("/define\('PG_ORDER_ABANDONED_LOCK', '([a-z_]+)'\);/", (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php'), $match);

	pg_assert_true(isset($match[1]) && !isset(pg_cron_jobs()[$match[1]]), 'not a catalogue name');
	pg_assert_same('', pg_cron_done_marker(isset($match[1]) ? $match[1] : '', pg_cron_jobs()), 'no completion marker');
}

// Dispatched light jobs => their own budget, which must be capped by what is
// left of the general job's time limit.
function _cron_job_time_left_jobs()
{
	return array(
		'shipment_tracking_job' => 'pg_shipment_tracking_run(pg_cron_time_left(PG_SHIPMENT_TRACKING_SECONDS))',
		'api_sync_job'          => 'mp_sync_run(pg_cron_time_left(25))',
		'translation_job'       => 'pg_tr_jobs_run_due(pg_cron_time_left(40))',
	);
}

function test_dispatched_light_jobs_keep_inside_the_time_limit()
{
	foreach (_cron_job_time_left_jobs() as $name => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $name . '.php');

		pg_assert_true(strpos($source, $call) !== false, $name . ': its budget is capped by the time left');
	}
}

// A job run alone from the command line has no limit: the budget is its own.
function test_cron_time_left_keeps_the_budget_without_a_limit()
{
	pg_assert_same(90, pg_cron_tick_budget(90, 1000.0, 0, 1010.0), 'no limit');
	pg_assert_same(37, pg_cron_tick_budget(90, 1000.0, 60, 1011.0), 'a 60 s runner limit, 11 s gone, 12 s margin');
}
