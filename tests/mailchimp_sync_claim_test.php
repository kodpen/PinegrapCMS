<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the MailChimp sync claims its running flag in one statement:
 * reading the flag and setting it apart let two overlapping general job
 * runs both start a sync and push the same orders twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_mailchimp_sync_claims_flag_atomically()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mailchimp.php');
	$start = strpos($source, 'function mailchimp_sync()');
	$end = strpos($source, 'function mailchimp_sync_products(');

	pg_assert_true(($start !== false) && ($end !== false) && ($end > $start), 'mailchimp_sync() found');

	$body = substr($source, $start, $end - $start);

	pg_assert_same(0, preg_match('/SELECT\s+mailchimp_sync_running/i', $body), 'the flag is not read apart from its claim');
	pg_assert_same(1, preg_match("/UPDATE config SET mailchimp_sync_running = '1' WHERE mailchimp_sync_running = '0'/", $body), 'the flag is claimed only while it is clear');
	pg_assert_true(strpos($body, 'mysqli_affected_rows(db::$con)') !== false, 'the claim is judged by the rows it changed');
}
