<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a release bundle publish that died part-way (a fatal error, a
 * timeout, a lost connection; includes/fn/page_bundles.php): the publish
 * holds the bundle's named lock, and the general job's pass and the start
 * of every action on the bundle finish the dead run, each only when it gets
 * that lock without waiting. A claim with no written_state whose page is as
 * it was is given back, the rest is taken back newest first, and a page
 * that cannot be told or taken back leaves the bundle partly published. The
 * runner has no connection, so the planner is called with the answers the
 * database would give, the writes are counted callables, and the code's
 * shape is read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function pg_test_dead_items()
{
	return array(
		array('id' => 1, 'status' => 'written', 'live_page_id' => 0, 'written_state' => str_repeat('a', 40), 'written_at' => 100),
		array('id' => 2, 'status' => 'written', 'live_page_id' => 5, 'written_state' => str_repeat('b', 40), 'written_at' => 101),
		array('id' => 3, 'status' => 'written', 'live_page_id' => 6, 'written_state' => '', 'written_at' => 102),
		array('id' => 4, 'status' => 'waiting', 'live_page_id' => 7, 'written_state' => '', 'written_at' => 0),
	);
}

function test_page_bundle_dead_plan_open_bundle()
{
	$plan = pg_page_bundle_dead_plan('open', pg_test_dead_items(), array(3 => array('as_was' => true)));

	pg_assert_same(array(3), $plan['give_back'], 'the claim whose page is as it was is given back');
	pg_assert_same(array(2, 1), $plan['take_back'], 'the written pages are taken back, newest first');
	pg_assert_same(array(), $plan['unfinished'], 'nothing is left unknown');
	pg_assert_same(3, $plan['failed'], 'the claim is the page that failed');
}

function test_page_bundle_dead_plan_claim_not_as_it_was()
{
	$plan = pg_page_bundle_dead_plan('scheduled', pg_test_dead_items(), array(3 => array('as_was' => false)));

	pg_assert_same(array(), $plan['give_back'], 'a claim whose page changed is not given back');
	pg_assert_same(array(3), $plan['unfinished'], 'it is left as it stands');
	pg_assert_same(array(2, 1), $plan['take_back'], 'the written pages are still taken back');
}

function test_page_bundle_dead_plan_no_probe_counts_as_changed()
{
	$plan = pg_page_bundle_dead_plan('open', pg_test_dead_items(), array());

	pg_assert_same(array(3), $plan['unfinished'], 'a page that could not be read is never given back');
}

// In a partly published bundle the written pages are what stands: only a
// claim with no written_state is looked at.
function test_page_bundle_dead_plan_partial_only_claims()
{
	$plan = pg_page_bundle_dead_plan('partial', pg_test_dead_items(), array(3 => array('as_was' => true)));

	pg_assert_same(array(3), $plan['give_back'], 'the claim is given back');
	pg_assert_same(array(), $plan['take_back'], 'what stands is not taken back');
}

function test_page_bundle_dead_plan_closed_or_clean()
{
	foreach (array('published', 'reverted', 'unknown') as $status) {
		$plan = pg_page_bundle_dead_plan($status, pg_test_dead_items(), array(3 => array('as_was' => true)));
		pg_assert_false(pg_page_bundle_dead_plan_has_work($plan), $status . ' is never touched');
	}

	$waiting = array(array('id' => 4, 'status' => 'waiting', 'live_page_id' => 7, 'written_state' => '', 'written_at' => 0));
	pg_assert_false(pg_page_bundle_dead_plan_has_work(pg_page_bundle_dead_plan('open', $waiting, array())), 'a bundle with nothing written is not touched');
}

function test_page_bundle_dead_run_all_taken_back()
{
	$calls = array();
	$plan = pg_page_bundle_dead_plan('open', pg_test_dead_items(), array(3 => array('as_was' => true)));
	$run = pg_page_bundle_dead_run($plan,
		function ($id) use (&$calls) {
			$calls[] = 'give:' . $id;

			return true;
		},
		function ($id) use (&$calls) {
			$calls[] = 'undo:' . $id;

			return true;
		});

	pg_assert_same('taken_back', $run['outcome'], 'nothing of the dead run stands');
	pg_assert_same(array('give:3', 'undo:2', 'undo:1'), $calls, 'given back, then taken back newest first');
	pg_assert_same(array(), $run['stands'], 'nothing stands');
}

function test_page_bundle_dead_run_take_back_fails()
{
	$plan = pg_page_bundle_dead_plan('open', pg_test_dead_items(), array(3 => array('as_was' => true)));
	$tried = array();
	$run = pg_page_bundle_dead_run($plan,
		function ($id) {
			return true;
		},
		function ($id) use (&$tried) {
			$tried[] = $id;

			return $id !== 2;
		});

	pg_assert_same('partial', $run['outcome'], 'a take-back that fails leaves it partly published');
	pg_assert_same(array(2, 1), $tried, 'every page is still tried');
	pg_assert_same(array(2), $run['stands'], 'the page that stands is named');
}

function test_page_bundle_dead_run_unfinished_claim()
{
	$plan = pg_page_bundle_dead_plan('open', pg_test_dead_items(), array(3 => array('as_was' => false)));
	$run = pg_page_bundle_dead_run($plan,
		function ($id) {
			return true;
		},
		function ($id) {
			return true;
		});

	pg_assert_same('partial', $run['outcome'], 'a page neither as it was nor written leaves it partly published');
	pg_assert_same(array(3), $run['stands'], 'that page stands');
}

function test_page_bundle_dead_run_nothing_to_do()
{
	$called = false;
	$plan = pg_page_bundle_dead_plan('published', pg_test_dead_items(), array());
	$run = pg_page_bundle_dead_run($plan,
		function ($id) use (&$called) {
			$called = true;

			return true;
		},
		function ($id) use (&$called) {
			$called = true;

			return true;
		});

	pg_assert_same('', $run['outcome'], 'no outcome');
	pg_assert_false($called, 'nothing is written');
}

// B12: a replaced page whose write was claimed but never recorded is listed
// with its own reason, not as "published again since".
function test_page_bundle_take_back_plan_unfinished_reason()
{
	$items = array(
		array('id' => 3, 'live_page_id' => 6, 'revision_before' => 0, 'written_state' => '', 'draft_name' => 'about-copy', 'live_name' => 'about'),
	);
	$plan = pg_page_bundle_take_back_plan($items, array(3 => array('exists' => true, 'state' => str_repeat('c', 40), 'revision' => false)));

	pg_assert_same(array(), $plan['can'], 'it is not taken back');
	pg_assert_same('unfinished', $plan['cannot'][0]['reason'], 'its reason');
	pg_assert_true(pg_page_bundle_take_back_reason_text('unfinished') !== '', 'with a text');
}

function test_page_bundle_lock_name()
{
	$a = pg_page_bundle_lock_name(12);
	$b = pg_page_bundle_lock_name(13);

	pg_assert_true($a !== $b, 'one lock per bundle');
	pg_assert_true(strlen($a) <= 64, 'within the length MySQL allows a lock name');
	pg_assert_same($a, pg_page_bundle_lock_name('12'), 'the same name for the same bundle');
}

// The lock is taken without waiting: a busy bundle means a publish is running.
function test_page_bundle_lock_shape()
{
	pg_assert_contains('GET_LOCK(', pg_test_function_source('pg_page_bundle_lock'), 'a named lock');
	pg_assert_contains(', 0)', pg_test_function_source('pg_page_bundle_lock'), 'without waiting');
	pg_assert_contains('RELEASE_LOCK(', pg_test_function_source('pg_page_bundle_unlock'), 'given back');

	$with = pg_test_function_source('pg_page_bundle_with_lock');
	pg_assert_contains('pg_page_bundle_lock(', $with, 'takes the lock');
	pg_assert_contains('pg_page_bundle_finish_dead(', $with, 'finishes a dead publish first');
	pg_assert_contains('finally', $with, 'and always gives the lock back');

	foreach (array('pg_page_bundle_publish', 'pg_page_bundle_take_back', 'pg_page_bundle_remove_item', 'pg_page_bundle_delete') as $action) {
		pg_assert_contains('pg_page_bundle_with_lock(', pg_test_function_source($action), $action . ' runs under the bundle\'s lock');
	}

	pg_assert_contains('pg_page_bundle_job_finish_dead(', pg_test_function_source('pg_page_bundle_job_run'), 'the general job finishes dead publishes');

	$job = pg_test_function_source('pg_page_bundle_job_finish_dead');
	pg_assert_contains('pg_page_bundle_lock(', $job, 'only when the lock is free');
	pg_assert_contains('pg_page_bundle_finish_dead(', $job, 'by the same finish');
	pg_assert_contains('pg_page_bundle_unlock(', $job, 'and gives the lock back');
}

// A plain read of the screen writes nothing.
function test_page_bundle_reads_do_not_finish()
{
	foreach (array('pg_page_bundle_get', 'pg_page_bundle_items', 'pg_page_bundle_publish_check', 'pg_page_bundle_take_back_check') as $read) {
		$source = pg_test_function_source($read);
		pg_assert_true(strpos($source, 'pg_page_bundle_finish_dead(') === false, $read . ' does not finish');
		pg_assert_true(strpos($source, 'pg_page_bundle_lock(') === false, $read . ' takes no lock');
	}

	$finish = pg_test_function_source('pg_page_bundle_finish_dead');
	pg_assert_contains("AND status = 'written' AND written_state = ''", $finish, 'a claim is given back only while it is still an unrecorded claim');
	pg_assert_contains('pg_page_bundle_undo_item(', $finish, 'the rest by the failure\'s own undo');
	pg_assert_contains('pg_page_bundle_with_throwing_errors(', $finish, 'a failed query is a failed take-back');
}
