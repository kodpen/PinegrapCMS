<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the automatic backup takes the job's lock before it starts and
 * reads the last backup time after taking it. The once-a-day check read the
 * time loaded when the request began, and the time is written only when the
 * archive is done, so a crontab run, a dispatched tick or a manager's request
 * during a long backup each started another full backup of the same week.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _auto_backup_claim_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/auto_backup.php');
}

// Every start that the dispatcher did not claim for takes the job's lock in
// cron_runs and frees it at the end; a held lock ends the request.
function test_auto_backup_claims_the_job_lock()
{
	$source = _auto_backup_claim_source();

	pg_assert_same(1, preg_match("/if \(!defined\('PG_CRON_DISPATCH'\)\) \{\s*if \(!pg_cron_claim\('auto_backup'\)\) \{\s*exit\(\);\s*\}\s*register_shutdown_function\('pg_cron_release', 'auto_backup'\);/", $source), 'claimed unless dispatched, released at shutdown');

	$claim = strpos($source, "pg_cron_claim('auto_backup')");
	$run = strpos($source, "\n        software_auto_backup();");

	pg_assert_true(($claim !== false) && ($run !== false) && ($claim < $run), 'the lock is taken before the backup starts');
}

// The once-a-day check reads the time from the table after the lock is held,
// not the value loaded when the request began.
function test_auto_backup_reads_last_time_after_claim()
{
	$source = _auto_backup_claim_source();

	$claim = strpos($source, "pg_cron_claim('auto_backup')");
	$read = strpos($source, 'db_value("SELECT last_software_auto_backup FROM config")');

	pg_assert_true(($claim !== false) && ($read !== false) && ($claim < $read), 'the last time is read after the claim');
	pg_assert_same(0, preg_match('/=\s*LAST_SOFTWARE_AUTO_BACKUP\s*;/', $source), 'the time loaded at start is not used for the check');
}
