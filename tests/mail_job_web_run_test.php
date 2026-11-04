<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for mail_job.php: a web request does no work and records no run.
 * pg_mail_job_alive() reads the 'mail_job' stamp, so a browser visit that
 * stamped it made the site queue e-mail for 15 minutes with no scheduled job
 * left to send it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_mail_job_web_run_does_no_work()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mail_job.php');

	$gate = strpos($source, 'if (!pg_cron_is_background_run()) {');
	$access = strpos($source, "validate_area_access(\$user, 'manager');");
	$redirect = strpos($source, "go(PATH . SOFTWARE_DIRECTORY . '/mail_queue.php');");
	$work = strpos($source, 'pg_mail_queue_run(');
	$stamp = strpos($source, "pg_cron_ran('mail_job');");

	pg_assert_true(($gate !== false) && ($access !== false), 'mail_job.php needs a signed-in manager for a web run');
	pg_assert_true(($redirect !== false) && ($access < $redirect), 'a web run is sent to the Mail queue screen');
	pg_assert_true(($work !== false) && ($stamp !== false) && ($redirect < $work) && ($redirect < $stamp), 'the redirect comes before any work or run stamp');
	pg_assert_true(substr_count($source, "pg_cron_ran('mail_job');") === 1, 'mail_job.php stamps its run in one place only');
}
