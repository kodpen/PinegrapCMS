<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * tools/test.php gives every test the time limit the run started with: a
 * limit one test leaves behind, as pg_image_derivative() does with
 * @set_time_limit(120), never reaches the next one. Windows counts that
 * limit in wall-clock time, so a limit left behind ended the whole run with
 * "Maximum execution time of 120 seconds exceeded" two minutes later. The two
 * tests run in the order they are declared.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_runner_time_limit_a_test_leaves_one_behind()
{
	// A host may disable the function; then no test can leave a limit.
	if (!function_exists('set_time_limit')) {
		pg_assert_true(true, 'set_time_limit() is disabled; skipped');
		return;
	}

	pg_assert_true(set_time_limit(600), 'the limit is set');
	pg_assert_same('600', ini_get('max_execution_time'), 'the limit is in force');
}

function test_runner_time_limit_the_next_test_starts_with_the_runs_own()
{
	if (!function_exists('set_time_limit')) {
		pg_assert_true(true, 'set_time_limit() is disabled; skipped');
		return;
	}

	pg_assert_same((string) PG_TEST_TIME_LIMIT, ini_get('max_execution_time'), 'the limit of the test before is gone');
}
