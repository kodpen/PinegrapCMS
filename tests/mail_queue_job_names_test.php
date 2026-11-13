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
	// The queues are worked from Communication › Sends; mail_queue.php is
	// its door.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/messages_sends.php');

	pg_assert_true($source !== '', 'messages_sends.php read');

	foreach (
		array(
			"lang('E-mail is handed to the scheduled job (the general job or mail_job.php).')",
			"lang('Neither the general job nor mail_job.php has run in the last 15 minutes, so e-mail is sent immediately.')",
			"lang('The message will be sent on the next run of the general job or mail_job.php.')",
			"lang('Last run of the mail queue')",
			"isset(\$sends_runs['mail_job'])",
		) as $needle
	) {
		pg_assert_true(strpos($source, $needle) !== false, $needle . ' present');
	}

	foreach (
		array(
			"lang('E-mail is handed to the general job.')",
			"lang('The general job has not run in the last 15 minutes, so e-mail is sent immediately.')",
			"\$sends_runs['job']",
		) as $needle
	) {
		pg_assert_same(false, strpos($source, $needle), $needle . ' gone');
	}

	// A text message's retry still names the general job alone.
	pg_assert_same(1, substr_count($source, "lang('The message will be sent on the next run of the general job.')"), 'SMS retry kept apart from the e-mail one');
}
