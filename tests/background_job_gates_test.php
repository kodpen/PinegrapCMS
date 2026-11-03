<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the gate of the e-mail campaign, membership and SEO score jobs:
 * a web request must come from a signed-in manager before the job does any
 * work; the scheduler (CLI or the job.php dispatcher) runs it without a
 * session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_background_job_gates_before_work()
{
	// Script => the first statement that does the job's work.
	$jobs = array(
		'email_campaign_job.php' => 'email_campaign_job_enabled()',
		'membership_job.php' => 'pg_audit_as_job(true)',
		'seo_score_job.php' => 'pg_seo_schema_ready()',
	);

	foreach ($jobs as $script => $first_work) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $script);

		$gate = strpos($source, 'if (!pg_cron_is_background_run()) {');
		$user = strpos($source, '$user = validate_user();');
		$access = strpos($source, "validate_area_access(\$user, 'manager');");
		$work = strpos($source, $first_work);

		pg_assert_true($gate !== false, $script . ' checks for a background run');
		pg_assert_true(($user !== false) && ($access !== false), $script . ' needs a signed-in manager for a web run');
		pg_assert_true(($work !== false) && ($gate < $user) && ($user < $access) && ($access < $work), $script . ': the gate comes before any work');
	}
}
