<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for overlapping search index runs: the closing sweep spares rows of a
 * run started later, and every start takes one lock of the same length.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Older than the run's start goes; the run's own rows and a later run's stay.
function test_search_index_sweep_deletes_only_older_rows()
{
	pg_assert_same("DELETE FROM search_items WHERE timestamp < '1000'", pg_search_index_sweep_sql(1000));
	pg_assert_same("DELETE FROM search_items WHERE timestamp < '0'", pg_search_index_sweep_sql('x'), 'not a number');
}

// The catalogue's lock wins, then JOB_DISPATCH_LOCK_SECONDS, then an hour;
// never under a minute.
function test_cron_lock_seconds()
{
	$jobs = array(
		'index' => array('lock' => 14400),
		'tiny'  => array('lock' => 5),
		'plain' => array(),
	);

	pg_assert_same(14400, pg_cron_lock_seconds($jobs, 'index', 900), 'catalogue');
	pg_assert_same(60, pg_cron_lock_seconds($jobs, 'tiny', 900), 'at least a minute');
	pg_assert_same(900, pg_cron_lock_seconds($jobs, 'plain', 900), 'setting');
	pg_assert_same(3600, pg_cron_lock_seconds($jobs, 'plain', null), 'default hour');
	pg_assert_same(60, pg_cron_lock_seconds($jobs, 'plain', 10), 'setting floor');
	pg_assert_same(3600, pg_cron_lock_seconds($jobs, 'missing', null), 'unknown job');
}

// A panel window request takes the job's catalogue lock; a command-line run,
// the whole pass in one process, holds the job for up to four hours.
function test_search_index_claim_seconds()
{
	pg_assert_same(null, pg_search_index_claim_seconds(true), 'panel window');
	pg_assert_same(14400, pg_search_index_claim_seconds(false), 'command line');
}

// A dispatched tick and a panel request work within a budget, so the job's
// own lock is minutes: a run killed outright (a host's job runner, a web
// server's timeout) no longer keeps the heavy lane for hours.
function test_search_index_catalogue_lock_is_minutes()
{
	$seconds = pg_cron_lock_seconds(pg_cron_jobs(), 'update_search_index', null);
	pg_assert_true(($seconds >= 60) && ($seconds <= 900), 'lock of ' . $seconds . ' s');
}

// The run claims with its own length, which pg_cron_claim() takes.
function test_search_index_claims_its_length()
{
	$claim = new ReflectionFunction('pg_cron_claim');
	pg_assert_same(2, $claim->getNumberOfParameters());

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');
	pg_assert_contains("pg_cron_claim('update_search_index', pg_search_index_claim_seconds(isset(\$_SERVER['HTTP_HOST'])))", $source);
}
