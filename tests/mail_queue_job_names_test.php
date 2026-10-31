<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the mail queue screen's e-mail texts: the queue is worked
 * by the general job or by mail_job.php alone (pg_mail_job_alive() reads
 * both cron_runs rows), so the e-mail tab names both and reads its last run
 * from the 'mail_job' row both scripts write. The SMS tab keeps naming only
 * the general job, which is the only one that sends text messages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_mail_queue_email_texts_name_both_jobs()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mail_queue.php');

	pg_assert_true($source !== '', 'mail_queue.php read');

	foreach (
		array(
			"lang('E-mail is handed to the scheduled job (the general job or mail_job.php).')",
			"lang('Neither the general job nor mail_job.php has run in the last 15 minutes, so e-mail is sent immediately.')",
			"lang('The message will be sent on the next run of the general job or mail_job.php.')",
			"lang('E-mail waiting for the general job or mail_job.php, sent in the last week and given up on in the last month.')",
			"lang('Last run of the mail queue')",
			"isset(\$mail_queue_runs['mail_job'])",
		) as $needle
	) {
		pg_assert_true(strpos($source, $needle) !== false, $needle . ' present');
	}

	foreach (
		array(
			"lang('E-mail is handed to the general job.')",
			"lang('The general job has not run in the last 15 minutes, so e-mail is sent immediately.')",
			"lang('E-mail waiting for the general job, sent in the last week and given up on in the last month.')",
			"\$mail_queue_runs['job']",
		) as $needle
	) {
		pg_assert_same(false, strpos($source, $needle), $needle . ' gone');
	}

	// The SMS retry and the SMS last-run line stay on the general job.
	pg_assert_same(1, substr_count($source, "lang('The message will be sent on the next run of the general job.')"), 'SMS retry kept, e-mail retry moved');
	pg_assert_same(1, substr_count($source, "lang('Last run of the general job')"), 'SMS last-run label kept');
	pg_assert_true(strpos($source, "isset(\$sms_queue_runs['job'])") !== false, 'SMS last run still from the job row');
}
