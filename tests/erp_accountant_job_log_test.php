<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the accountant pack job: a monthly pack that could not be
 * built, or was built but not sent, leaves one Site Log line naming the
 * period and the reason; a run that was off, already done or sent leaves none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_accountant_job_log_block()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/erp_accountant_job.php');
	$a = strpos($source, "if (defined('ERP_ENABLED') && ERP_ENABLED) {");
	$b = strpos($source, "pg_cron_ran('erp_accountant_job')");

	return (($a === false) || ($b === false)) ? '' : substr($source, $a, $b - $a);
}

function test_erp_accountant_job_logs_failed_run()
{
	$block = erp_accountant_job_log_block();
	pg_assert_true($block !== '', 'ERP branch found');

	pg_assert_true((bool) preg_match("/!in_array\(\\\$result\['reason'\], array\('off', 'done', 'sent'\), true\)/", $block), 'logs only a reason other than off, done or sent');
	pg_assert_contains('erp_accountant_last_month()', $block, 'names the period of the run');
	pg_assert_contains("'The accountant pack for {var:1} - {var:2} was built but not sent: {var:3}'", $block, 'built but not sent line');
	pg_assert_contains("'The accountant pack for {var:1} - {var:2} could not be built: {var:3}'", $block, 'could not be built line');
	pg_assert_contains("\$result['ran']", $block, 'the two lines follow whether the pack was built');
	pg_assert_same(1, substr_count($block, 'log_activity('), 'one log call');
	pg_assert_contains("'UNKNOWN'", $block, 'logged without a user, as the other jobs do');
}
