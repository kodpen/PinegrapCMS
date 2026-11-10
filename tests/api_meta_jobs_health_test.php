<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the scheduled jobs list in GET /meta (api_meta_jobs(),
 * includes/api/resources/meta.php): each job's last_run_at and stale come
 * from the job's real end (pg_cron_health_last_runs()), not from the
 * dispatcher's tick stamp. The pure merge, pg_cron_health_runs(), is pinned
 * in tests/cron_resume_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The list reads the health reader, never the plain rows the dispatcher
// stamps on every tick, finished or not.
function test_api_meta_jobs_reads_finished_runs()
{
	$source = pg_test_function_source('api_meta_jobs');

	pg_assert_true(strpos($source, 'pg_cron_health_last_runs(') !== false, 'health reader');
	pg_assert_true(strpos($source, 'pg_cron_last_runs()') === false, 'no plain rows');
}
