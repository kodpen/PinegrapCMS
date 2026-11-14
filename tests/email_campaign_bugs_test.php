<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the e-mail campaign checks: the fields a campaign is saved
 * with (subject, sender, reply-to, BCC, status, format, purpose), the
 * statuses the send window refuses, the delivery problem line and the
 * waiting text when no job retries, and the screen fixes they back (page
 * preview URL, read-only labels and heading, the save bar outside the row).
 * No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/campaign_content.php';

require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_send.php');

function test_email_campaign_field_errors_require_subject_and_sender()
{
	$errors = pg_email_campaign_field_errors(array('subject' => ' ', 'from_email_address' => '', 'reply_email_address' => 'abc'));

	pg_assert_true(isset($errors['subject']), 'a blank subject is refused');
	pg_assert_true(isset($errors['from_email_address']), 'a blank sender address is refused');
	pg_assert_true(isset($errors['reply_email_address']), 'a reply-to that is not an address is refused');
	pg_assert_false(isset($errors['bcc_email_address']), 'BCC is checked only when it is posted');

	$errors = pg_email_campaign_field_errors(array('subject' => 'News', 'from_email_address' => 'shop@example.com', 'reply_email_address' => ''));
	pg_assert_same(array(), $errors, 'a subject and a valid sender pass; reply-to may be blank');

	$errors = pg_email_campaign_field_errors(array('subject' => 'News', 'from_email_address' => 'shop', 'bcc_email_address' => 'x@'));
	pg_assert_true(isset($errors['from_email_address']), 'an invalid sender is refused');
	pg_assert_true(isset($errors['bcc_email_address']), 'an invalid BCC is refused');

	$errors = pg_email_campaign_field_errors(array('subject' => array('x'), 'from_email_address' => array('a@example.com')));
	pg_assert_true(isset($errors['subject']) && isset($errors['from_email_address']), 'arrays count as not given');
}

function test_email_campaign_allowed_values()
{
	pg_assert_same('ready', pg_email_campaign_allowed_value('status', 'ready'), 'ready');
	pg_assert_same('paused', pg_email_campaign_allowed_value('status', 'paused'), 'paused');
	pg_assert_same('cancelled', pg_email_campaign_allowed_value('status', 'cancelled'), 'cancelled');
	pg_assert_same(null, pg_email_campaign_allowed_value('status', 'complete'), 'complete is set by sending, never by the form');
	pg_assert_same('html', pg_email_campaign_allowed_value('format', 'html'), 'html');
	pg_assert_same('plain_text', pg_email_campaign_allowed_value('format', 'plain_text'), 'plain text');
	pg_assert_same(null, pg_email_campaign_allowed_value('format', 'pdf'), 'unknown format');
	pg_assert_same('commercial', pg_email_campaign_allowed_value('purpose', 'commercial'), 'commercial');
	pg_assert_same('transactional', pg_email_campaign_allowed_value('purpose', 'transactional'), 'transactional');
	pg_assert_same(null, pg_email_campaign_allowed_value('purpose', 'spam'), 'unknown purpose');
	pg_assert_same(null, pg_email_campaign_allowed_value('status', array('ready')), 'an array is not a value');
}

function test_email_campaign_send_refusal_by_status()
{
	pg_assert_same('', pg_email_campaign_send_refusal('ready'), 'a ready campaign is sent');
	pg_assert_same('', pg_email_campaign_send_refusal('complete'), 'a complete campaign shows its summary');
	pg_assert_true(pg_email_campaign_send_refusal('paused') !== '', 'a paused campaign is not sent');
	pg_assert_true(pg_email_campaign_send_refusal('cancelled') !== '', 'a cancelled campaign is not sent');
	pg_assert_true(pg_email_campaign_send_refusal('') !== '', 'an unknown status is not sent');
}

function test_email_campaign_summary_waiting_text_without_job()
{
	$with_job = pg_email_campaign_send_summary(1, 0, 0, 1, 0, true);
	pg_assert_contains('wait for another attempt', $with_job['message'], 'the job retries them');

	$without_job = pg_email_campaign_send_summary(1, 0, 0, 1, 0, false, '14:05');
	pg_assert_false(strpos($without_job['message'], 'wait for another attempt') !== false, 'nothing retries them by itself');
	pg_assert_contains('Send Campaign', $without_job['message'], 'the text says to press Send Campaign again');
	pg_assert_contains('14:05', $without_job['message'], 'the earliest time is named');
	pg_assert_false($without_job['complete'], 'the campaign stays open');
}

function test_email_campaign_problem_line()
{
	pg_assert_same('', pg_email_campaign_problem_line('', 0), 'no error, no line');

	$line = pg_email_campaign_problem_line('Invalid address: (From)', 3);
	pg_assert_contains('Invalid address: (From)', $line, 'the stored error is named');
	pg_assert_contains('3', $line, 'with how many recipients it stopped');
}

function test_email_campaign_screens_use_the_checks()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_email_campaign.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_email_campaign.php');
	$send = (string) file_get_contents(PG_FUNCTIONS_DIR . '/send_email_campaign.php');
	$delete = (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_email_campaigns.php');

	pg_assert_contains('pg_email_campaign_field_errors(', $add, 'create checks the fields');
	pg_assert_contains('pg_email_campaign_field_errors(', $edit, 'edit checks the fields');
	pg_assert_contains("pg_email_campaign_allowed_value('status'", $edit, 'edit accepts only form statuses');
	pg_assert_contains('pg_email_campaign_send_refusal(', $send, 'the send window refuses paused campaigns');
	pg_assert_contains("\$_POST['email_campaigns'] ?? array()", $delete, 'delete without a selection reads no missing key');

	// The preview frame is built from the picked page's name on the site
	// address, never from a label's markup.
	pg_assert_false(strpos($add, 'firstChild.nodeValue') !== false, 'the preview URL is not the option text');
	pg_assert_contains('pg_campaign_content_script()', $add, 'the content card\'s script drives the preview');
	pg_assert_contains('encodeURIComponent(name.value)', pg_campaign_content_script(), 'the picked name is encoded into the address');

	// The save bar and the form end follow the row, as on create.
	pg_assert_false((bool) preg_match('/\$output_buttons \. \'\s*\'\s*\. \$output_form_end \. \'\s*<\/div>\s*<\/div>/', $edit), 'the save bar does not sit inside the row');
}

function test_email_campaign_readonly_labels()
{
	$html = pg_email_campaign_subject_row('News', true);
	pg_assert_false(strpos($html, '<label') !== false, 'a read-only subject has no label for a missing field');
	pg_assert_contains('<div class="form-label">', $html, 'it keeps its heading');

	$html = pg_email_campaign_from_card(array('from_name' => 'A', 'from_email_address' => 'a@example.com', 'reply_email_address' => ''), true);
	pg_assert_false(strpos($html, '<label') !== false, 'read-only sender rows have no label for a missing field');

	$html = pg_email_campaign_subject_row('News');
	pg_assert_contains('required', $html, 'the subject is required in the browser too');
	$html = pg_email_campaign_from_card(array());
	pg_assert_contains('id="from_email_address" name="from_email_address" maxlength="100" required', $html, 'the sender address is required');
}
