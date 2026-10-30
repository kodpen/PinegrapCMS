<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for long texts the panel shows in full: the mail queue's
 * e-mail and SMS "Last error" cells and the SMS message body print the whole
 * value in a wrapping cell (no 80-character cut, no hover-only title), and
 * the bell notification title wraps instead of ending in an ellipsis.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_mail_queue_full_text_cells()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mail_queue.php');

	pg_assert_true($source !== '', 'mail_queue.php read');
	pg_assert_same(0, preg_match('/truncate\(\$(sms_queue_row\[\'body\'\]|sms_queue_error|mail_queue_error)/', $source), 'no truncated body or error');
	pg_assert_same(0, preg_match('/title="\'\s*\.\s*h\(\$(sms_queue_row\[\'body\'\]|sms_queue_error|mail_queue_error)\)/', $source), 'no hover-only title on body or error cells');

	foreach (array("h(\$sms_queue_row['body'])", 'h($sms_queue_error)', 'h($mail_queue_error)') as $value) {
		pg_assert_true(
			preg_match('/<td class="[^"]*text-wrap text-break[^"]*" style="max-width: 28rem;">\'\s*\.\s*' . preg_quote($value, '/') . '/', $source) === 1,
			$value . ' printed in full in a wrapping cell'
		);
	}

	pg_assert_true(strpos($source, '<div class="small fg-secondary">\' . h($sms_queue_reference)') !== false, 'SMS reference line kept under the body');
}

function test_bell_notification_title_wraps()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_same(false, strpos($source, '<small class="notification-title" title='), 'bell title has no hover-only title attribute');
	pg_assert_true(strpos($source, '<small class="notification-title text-wrap text-break w-100">') !== false, 'bell title wraps');
}
