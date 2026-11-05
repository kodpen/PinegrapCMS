<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for email_campaign_job.php: a scheduled run has nobody signed in, so
 * its activity log lines must not read $_SESSION['sessionusername'] without
 * a fallback (a PHP warning on every run that sent or skipped a recipient).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_email_campaign_job_log_user_has_fallback()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/email_campaign_job.php');

	pg_assert_true(preg_match_all('/log_activity\([^;]*\$_SESSION\[\'sessionusername\'\]\)\s*;/', $source) === 0, 'no log line passes the session user unguarded');
	pg_assert_true(strpos($source, '$email_campaign_job_log_user') !== false, 'the job names its log user once');
}
