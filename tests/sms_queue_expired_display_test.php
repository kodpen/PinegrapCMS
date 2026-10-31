<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pin for the mail queue screen's SMS tab: a queued row already past
 * its expiry is never sent (pg_sms_queue_run() marks it failed without an
 * attempt), so its When column reads 'Expired' instead of 'On the next run'.
 * The e-mail tab has no expiry and keeps 'On the next run'.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_sms_queue_expired_row_reads_expired()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mail_queue.php');

	pg_assert_true($source !== '', 'mail_queue.php read');

	$start = strpos($source, "} elseif (\$sms_queue_row['status'] === 'queued') {");
	pg_assert_true($start !== false, 'SMS queued branch present');

	$end = strpos($source, "\$sms_queue_when = '';", (int) $start);
	pg_assert_true($end !== false, 'SMS queued branch ends');

	$branch = substr($source, (int) $start, (int) $end - (int) $start);
	$expired = strpos($branch, "if (\$sms_queue_expired) {");
	$label = strpos($branch, "lang('Expired')");
	$next_run = strpos($branch, "lang('On the next run')");

	pg_assert_true($expired !== false, 'queued branch checks the expiry');
	pg_assert_true($label !== false, 'expired queued row reads Expired');
	pg_assert_true($next_run !== false, 'live queued row keeps On the next run');
	pg_assert_true((int) $expired < (int) $next_run, 'expiry checked before the next-run text');
}
