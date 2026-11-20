<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The number boxes of Site Settings › E-mail sending (includes/fn/mail_settings.php):
 * a port from 1 to 65535 and the campaign job's Max. of 1 or more, or blank
 * for the default. email_campaign_job.php puts Max. straight into its LIMIT,
 * where 0 sends nothing and a sign or a letter fails the query on every run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php';

function test_mail_settings_numbers_max_is_one_or_more()
{
	pg_assert_same('', pg_mail_settings_number_problem('EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS', ''), 'blank keeps the default of 25');
	pg_assert_same('', pg_mail_settings_number_problem('EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS', '1'), '1');
	pg_assert_same('', pg_mail_settings_number_problem('EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS', '500'), '500');

	foreach (array('0', '-5', '10.5', '1e3', 'abc', '25 ') as $value) {
		pg_assert_true(pg_mail_settings_number_problem('EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS', $value) !== '', 'Max. ' . $value . ' is refused');
	}
}

function test_mail_settings_numbers_port_is_a_port()
{
	foreach (array('SYSTEM_SMTP_PORT', 'CAMPAIGN_SMTP_PORT') as $key) {
		pg_assert_same('', pg_mail_settings_number_problem($key, ''), $key . ': blank is 587');
		pg_assert_same('', pg_mail_settings_number_problem($key, '465'), $key . ': 465');
		pg_assert_same('', pg_mail_settings_number_problem($key, '65535'), $key . ': 65535');

		foreach (array('0', '-25', '65536', '58.7', 'smtp') as $value) {
			pg_assert_true(pg_mail_settings_number_problem($key, $value) !== '', $key . ': ' . $value . ' is refused');
		}
	}

	pg_assert_same('', pg_mail_settings_number_problem('SYSTEM_SMTP_HOSTNAME', 'smtp.example.com'), 'a text box is not a number box');
}

// The save asks before anything is written, the sending service included.
function test_mail_settings_numbers_checked_before_the_save_writes()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');
	$from = strpos($source, 'function pg_mail_settings_save(');
	$body = substr($source, $from);
	$check = strpos($body, 'pg_mail_settings_number_problem(');

	pg_assert_true($check !== false, 'the save checks the number boxes');
	pg_assert_true($check < strpos($body, 'pg_mail_transport_settings_save('), 'before the sending service is saved');
}

// The campaign boxes are folded away while the job is off.
function test_mail_settings_numbers_campaign_boxes_only_while_the_job_is_on()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');
	$body = substr($source, strpos($source, 'function pg_mail_settings_save('));

	pg_assert_contains("\$job_on = (isset(\$post['email_campaign_job']) && (\$post['email_campaign_job'] === 'true'));", $body, 'the job switch is read');
	pg_assert_contains("if (!\$job_on && (\$key !== 'SYSTEM_SMTP_PORT')) {", $body, 'only the system port is checked while the job is off');
}
