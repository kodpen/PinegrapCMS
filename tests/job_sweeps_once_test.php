<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the general job (job.php) runs each daily sweep once per tick:
 * the page revisions block stood there twice, so a tick whose state file
 * could not be written swept and logged twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_job_daily_sweeps_run_once()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');

	foreach (array('pg_page_revisions_sweep()', 'pg_record_revisions_sweep()', 'pg_not_found_log_sweep()', 'pg_file_versions_sweep()', 'pg_visitor_cleanup_run(') as $call) {
		pg_assert_same(1, substr_count($source, $call), $call . ' runs once per tick');
	}
}
