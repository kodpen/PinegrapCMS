<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the dispatch lane choice in includes/fn/cron.php
 * (pg_cron_pick()). pg_cron_jobs() calls lang(), so each test builds a small
 * catalogue of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A catalogue with two light jobs and two heavy ones, all daily.
function pg_test_cron_jobs()
{
	return array(
		'light_a' => array('dispatch' => true, 'interval' => 86400, 'lane' => 'light'),
		'light_b' => array('dispatch' => true, 'interval' => 86400, 'lane' => 'light'),
		'heavy_a' => array('dispatch' => true, 'interval' => 86400, 'lane' => 'heavy'),
		'heavy_b' => array('dispatch' => true, 'interval' => 86400, 'lane' => 'heavy'),
	);
}

// A single due light job is the light lane's choice; the heavy lane has none.
function test_cron_pick_single_due_light_job()
{
	$jobs = array(
		'light_a' => array('dispatch' => true, 'interval' => 300, 'lane' => 'light'),
	);

	$runs = array('light_a' => array('last_run_at' => 1000, 'locked_until' => 0));

	pg_assert_same(array('light' => 'light_a', 'heavy' => null), pg_cron_pick($jobs, $runs, 2000));
}

// A locked light job closes the light lane, and only that lane.
function test_cron_pick_locked_lane_hands_out_nothing()
{
	$now = 1000000;

	$runs = array(
		'light_a' => array('last_run_at' => 0, 'locked_until' => $now + 600),
		'light_b' => array('last_run_at' => 0, 'locked_until' => 0),
		'heavy_a' => array('last_run_at' => 0, 'locked_until' => 0),
	);

	$pick = pg_cron_pick(pg_test_cron_jobs(), $runs, $now);

	pg_assert_same(null, $pick['light'], 'light lane');
	pg_assert_same('heavy_a', $pick['heavy'], 'heavy lane');
}

// A lock that has expired no longer holds the lane, and the job itself is
// offered again.
function test_cron_pick_expired_lock_frees_lane()
{
	$now = 1000000;

	$runs = array(
		'heavy_a' => array('last_run_at' => 10, 'locked_until' => $now - 1),
		'heavy_b' => array('last_run_at' => 20, 'locked_until' => 0),
	);

	$pick = pg_cron_pick(pg_test_cron_jobs(), $runs, $now);

	pg_assert_same('heavy_a', $pick['heavy']);
}

// Two jobs that waited equally long: the one listed first wins.
function test_cron_pick_tie_keeps_catalogue_order()
{
	$runs = array(
		'light_b' => array('last_run_at' => 500, 'locked_until' => 0),
		'light_a' => array('last_run_at' => 500, 'locked_until' => 0),
	);

	$pick = pg_cron_pick(pg_test_cron_jobs(), $runs, 1000000);

	pg_assert_same('light_a', $pick['light'], 'tie');

	// The longer wait still beats the catalogue order.
	$runs['light_b']['last_run_at'] = 400;

	$pick = pg_cron_pick(pg_test_cron_jobs(), $runs, 1000000);

	pg_assert_same('light_b', $pick['light'], 'longer wait');
}

// A job whose interval has not passed since it last finished is not chosen.
function test_cron_pick_skips_job_not_yet_due()
{
	$now = 1000000;

	$jobs = array(
		'light_a' => array('dispatch' => true, 'interval' => 300, 'lane' => 'light'),
	);

	$runs = array('light_a' => array('last_run_at' => $now - 299, 'locked_until' => 0));

	pg_assert_same(null, pg_cron_pick($jobs, $runs, $now)['light'], 'one second early');

	$runs['light_a']['last_run_at'] = $now - 300;

	pg_assert_same('light_a', pg_cron_pick($jobs, $runs, $now)['light'], 'on the second');
}

// The general job (dispatch false) and an inline job are never chosen, and a
// stale lock on either does not close a lane.
function test_cron_pick_skips_host_and_inline_jobs()
{
	$now = 1000000;

	$jobs = array(
		'job'     => array('dispatch' => false, 'interval' => 60, 'lane' => 'light'),
		'webhook' => array('dispatch' => true, 'inline' => true, 'interval' => 60, 'lane' => 'light'),
		'light_a' => array('dispatch' => true, 'interval' => 300, 'lane' => 'light'),
	);

	$runs = array(
		'job'     => array('last_run_at' => 0, 'locked_until' => $now + 600),
		'webhook' => array('last_run_at' => 0, 'locked_until' => $now + 600),
		'light_a' => array('last_run_at' => 100, 'locked_until' => 0),
	);

	pg_assert_same(array('light' => 'light_a', 'heavy' => null), pg_cron_pick($jobs, $runs, $now));

	unset($jobs['light_a']);

	pg_assert_same(array('light' => null, 'heavy' => null), pg_cron_pick($jobs, $runs, $now), 'nothing left');
}

// An entry without a lane joins the light lane, and its lock closes it.
function test_cron_pick_missing_lane_is_light()
{
	$now = 1000000;

	$jobs = array(
		'unlaned' => array('dispatch' => true, 'interval' => 300),
		'light_a' => array('dispatch' => true, 'interval' => 300, 'lane' => 'light'),
	);

	pg_assert_same(
		array('light' => 'unlaned', 'heavy' => null),
		pg_cron_pick($jobs, array(), $now),
		'chosen in the light lane');

	$runs = array('unlaned' => array('last_run_at' => 0, 'locked_until' => $now + 60));

	pg_assert_same(
		array('light' => null, 'heavy' => null),
		pg_cron_pick($jobs, $runs, $now),
		'its lock closes the light lane');
}

// $allowed narrows who may be chosen, but a running job outside it still
// holds its lane.
function test_cron_pick_allowed_narrows_choice_not_locks()
{
	$now = 1000000;

	$runs = array(
		'heavy_a' => array('last_run_at' => 0, 'locked_until' => $now + 600),
	);

	$pick = pg_cron_pick(pg_test_cron_jobs(), $runs, $now, array('light_b', 'heavy_b'));

	pg_assert_same('light_b', $pick['light'], 'light lane');
	pg_assert_same(null, $pick['heavy'], 'heavy lane');
}

// A job with no run record at all has never run and is due.
function test_cron_pick_job_without_row_is_due()
{
	$jobs = array(
		'heavy_a' => array('dispatch' => true, 'interval' => 604800, 'lane' => 'heavy'),
	);

	pg_assert_same('heavy_a', pg_cron_pick($jobs, array(), 1000000)['heavy']);
}
