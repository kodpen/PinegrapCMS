<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the e-mail campaign send helpers (includes/fn/email_campaign_send.php)
 * shared by Send now (send_email_campaign.php) and the campaign job: the
 * mail properties carry the campaign's purpose (a commercial campaign gets
 * the one-click unsubscribe headers on both paths), a refused delivery is
 * recorded as an attempt rather than as sent, and the finish counts only
 * accepted deliveries. Saving a campaign with the job off reads no missing
 * start_time. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_send.php');

function email_campaign_send_test_campaign($purpose)
{
	return array(
		'from_name'           => 'Shop',
		'from_email_address'  => 'shop@example.com',
		'reply_email_address' => 'reply@example.com',
		'bcc_email_address'   => '',
		'format'              => 'html',
		'purpose'             => $purpose,
	);
}

function test_email_campaign_properties_carry_purpose()
{
	$properties = pg_email_campaign_mail_properties(email_campaign_send_test_campaign('commercial'), 'a@example.com', 'A', 'Hi', '<p>Hi</p>');

	pg_assert_same('campaign', $properties['type'], 'a campaign mail is typed campaign');
	pg_assert_same('commercial', $properties['purpose'], 'the purpose reaches email(), which adds List-Unsubscribe for commercial');
	pg_assert_same('a@example.com', $properties['to'], 'to');
	pg_assert_same('reply@example.com', $properties['reply_to'], 'reply-to comes from the campaign');
	pg_assert_same('html', $properties['format'], 'format');
	pg_assert_false(array_key_exists('notify_sender', $properties), 'the caller decides about the sender notice');

	$properties = pg_email_campaign_mail_properties(email_campaign_send_test_campaign('transactional'), 'a@example.com', 'A', 'Hi', 'Hi');
	pg_assert_same('transactional', $properties['purpose'], 'a transactional purpose is kept as it is');
}

function test_email_campaign_both_paths_use_the_shared_properties()
{
	$send_now = (string) file_get_contents(PG_FUNCTIONS_DIR . '/send_email_campaign.php');
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/email_campaign_job.php');

	pg_assert_contains('pg_email_campaign_mail_properties(', $send_now, 'Send now builds the mail through the shared helper');
	pg_assert_contains('pg_email_campaign_mail_properties(', $job, 'the job builds the mail through the shared helper');
	pg_assert_contains('pg_email_campaign_record_failure(', $send_now, 'Send now records a refused delivery');
	pg_assert_contains('pg_email_campaign_record_failure(', $job, 'the job records a refused delivery the same way');
	pg_assert_false((bool) preg_match('/\n\s*email\(array\(/', $send_now), 'Send now no longer calls email() with an inline array and ignores the answer');
}

function test_email_campaign_failure_first_attempt_waits()
{
	$failure = pg_email_campaign_failure(0, 'SMTP connect() failed');

	pg_assert_same(1, $failure['attempts'], 'the first refusal is attempt 1');
	pg_assert_false($failure['given_up'], 'the first refusal is not given up');
	pg_assert_same(pg_mail_backoff(1), $failure['retry_in'], 'it waits the mail backoff');
	pg_assert_same('SMTP connect() failed', $failure['last_error'], 'the reason is kept');
}

function test_email_campaign_failure_last_attempt_gives_up()
{
	$failure = pg_email_campaign_failure(pg_mail_max_attempts() - 1, str_repeat('x', 700));

	pg_assert_same(pg_mail_max_attempts(), $failure['attempts'], 'the last attempt is counted');
	pg_assert_true($failure['given_up'], 'the last refusal gives the recipient up');
	pg_assert_same(0, $failure['retry_in'], 'no further attempt');
	pg_assert_true(strlen($failure['last_error']) <= 500, 'the reason is clipped to the column');
}

function test_email_campaign_summary_all_delivered()
{
	$summary = pg_email_campaign_send_summary(2, 2, 0, 0);

	pg_assert_same(100, $summary['percent'], 'all delivered is 100%');
	pg_assert_true($summary['all_sent'], 'all sent');
	pg_assert_true($summary['complete'], 'the campaign is complete');
}

function test_email_campaign_summary_all_failed_is_not_complete()
{
	$summary = pg_email_campaign_send_summary(2, 0, 0, 2);

	pg_assert_same(0, $summary['percent'], 'nothing delivered is 0%');
	pg_assert_false($summary['all_sent'], 'not all sent');
	pg_assert_false($summary['complete'], 'recipients waiting for another attempt keep the campaign open');
	pg_assert_false(strpos($summary['message'], 'all recipients') !== false, 'the finish does not claim every recipient got it');
	pg_assert_contains('2', $summary['message'], 'the finish names how many could not be delivered');
}

function test_email_campaign_summary_never_rounds_up_to_100()
{
	$summary = pg_email_campaign_send_summary(200, 199, 1, 0);

	pg_assert_same(99, $summary['percent'], 'one given-up recipient of 200 is not shown as 100%');
	pg_assert_false($summary['all_sent'], 'not all sent');
	pg_assert_true($summary['complete'], 'given-up recipients let the campaign finish, as in the job');
}

function test_email_campaign_save_reads_start_time_only_when_posted()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_email_campaign.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_email_campaign.php');

	pg_assert_false(strpos($add, "prepare_form_data_for_input(\$_POST['start_time'],") !== false, 'create reads no missing start_time');
	pg_assert_true((bool) preg_match('/if \(isset\(\$_POST\[\'start_time\'\]\)[^{]*\{\s*\$sql_start_time = /', $edit), 'edit reads start_time only when it is posted');
	pg_assert_contains("isset(\$_POST['start_time'])", $edit, 'edit keeps the stored start time when the field is not posted');
}
