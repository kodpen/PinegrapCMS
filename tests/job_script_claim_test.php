<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the job scripts which mail people take the job's lock when they
 * are started from their own crontab line or by hand, as the general job's
 * dispatcher does for a dispatched run. Each marks its work as done only
 * after the mail, so two runs side by side mailed the same people twice:
 * membership expiry warnings, overdue invoice reminders to customers and the
 * accountant's monthly pack.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Job name => the first statement of the job's work, which must come after
// the claim.
function _job_script_claim_jobs()
{
	return array(
		'membership_job'     => '$current_date = date(',
		'erp_overdue_job'    => 'erp_overdue_check(',
		'erp_accountant_job' => 'erp_accountant_monthly_run(',
	);
}

// Claimed unless dispatched, released at shutdown, a held lock ends the
// request, and the claim comes before any work.
function test_job_scripts_claim_the_job_lock()
{
	foreach (_job_script_claim_jobs() as $name => $work) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $name . '.php');

		pg_assert_same(1, preg_match("/if \(!defined\('PG_CRON_DISPATCH'\)\) \{\s*if \(!pg_cron_claim\('" . $name . "'\)\) \{.*?exit\(\);\s*\}\s*register_shutdown_function\('pg_cron_release', '" . $name . "'\);/s", $source), $name . ': claimed unless dispatched, released at shutdown');

		$claim = strpos($source, "pg_cron_claim('" . $name . "')");
		$run = strpos($source, $work);

		pg_assert_true(($claim !== false) && ($run !== false) && ($claim < $run), $name . ': the lock is taken before the work starts');
	}
}
