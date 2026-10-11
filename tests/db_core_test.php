<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the query counter of the db helpers in includes/fn/core.php
 * (pg_db_run(), pg_db_stats()). pg_db_run() itself needs a connection and is
 * checked in the sandbox; without one it ends the request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The counter is an array of exactly count and ms, typed int and float.
function test_db_core_stats_shape()
{
	$stats = pg_db_stats();

	pg_assert_same(array('count', 'ms'), array_keys($stats));
	pg_assert_true(is_int($stats['count']), 'count is an int');
	pg_assert_true(is_float($stats['ms']), 'ms is a float');
}

// Nothing has gone through pg_db_run() in a run without a database.
function test_db_core_stats_start_at_zero()
{
	$stats = pg_db_stats();

	pg_assert_same(0, $stats['count']);
	pg_assert_same(0.0, $stats['ms']);
}

// The answer is a copy: changing it does not change the counter.
function test_db_core_stats_returns_a_copy()
{
	$stats = pg_db_stats();
	$stats['count'] = 99;

	pg_assert_same(0, pg_db_stats()['count']);
}
