<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mail queue screen's lists (includes/fn/mail_queue_list.php):
 * the search box's conditions, an e-mail's and a text message's row, the
 * long texts wrapping in an inner block, the delete question by status, and
 * the screen handing its rows to the paged actions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $mail_queue_list_test_name => $mail_queue_list_test_value) {
	if (!defined($mail_queue_list_test_name)) {
		define($mail_queue_list_test_name, $mail_queue_list_test_value);
	}
}

function test_mail_queue_list_search()
{
	pg_assert_same(array('sql' => '', 'params' => array()), pg_mail_queue_list_search(array()));

	$search = pg_mail_queue_list_search(array('#1172'));
	pg_assert_contains('subject LIKE ?', $search['sql'], 'the subject is searched');
	pg_assert_contains('last_error LIKE ?', $search['sql'], 'the last error is searched');
	pg_assert_same('%#1172%', $search['params'][1]);

	$search = pg_mail_queue_list_search(array(lang('Failed')));
	pg_assert_contains('status IN (?)', $search['sql'], 'a status by its shown name');

	$search = pg_sms_queue_list_search(array('ref-9'));
	pg_assert_contains('body LIKE ?', $search['sql']);
	pg_assert_contains('reference LIKE ?', $search['sql']);
}

function test_mail_queue_list_row()
{
	$now = 1800000000;
	$row = array(
		'id' => '7', 'created_at' => $now - 3600, 'send_after' => '0', 'status' => 'failed', 'attempts' => '1',
		'last_error' => str_repeat('Could not connect to the SMTP host <x> ', 4), 'sent_at' => '0',
		'mail_type' => 'system', 'recipient' => 'a@example.com', 'subject' => 'Order #1172',
	);

	$built = pg_mail_queue_list_row($row, $now);
	$html = pg_list_row_html($built);

	pg_assert_same(10, count($built['cells']), 'one cell per heading');
	pg_assert_contains('value="retry:7"', $html, 'a failed message can be retried');
	pg_assert_contains('value="delete:7"', $html);
	pg_assert_contains('&lt;x&gt;', $html, 'the error is escaped');
	pg_assert_contains(h(lang('The message will be deleted from the queue and not sent.')), $html);
	pg_assert_contains('<div class="text-break" style="white-space:normal;min-width:12rem;max-width:28rem;">', $built['cells'][7]['html'], 'the error wraps in an inner block');
	pg_assert_false(strpos($html, 'max-width: 28rem') !== false, 'no max-width on the cell itself');

	$row['status'] = 'sent';
	$row['sent_at'] = $now - 60;
	$html = pg_list_row_html(pg_mail_queue_list_row($row, $now));
	pg_assert_false(strpos($html, 'retry:7') !== false, 'a sent message is not retried');
	pg_assert_contains(h(lang('The message will be deleted from the queue.')) . '"', $html, 'a sent message is not "not sent"');

	$row['status'] = 'suppressed';
	pg_assert_contains(h(lang('Blocked')), pg_list_row_html(pg_mail_queue_list_row($row, $now)));
}

function test_sms_queue_list_row()
{
	$now = 1800000000;
	$row = array(
		'id' => '3', 'created_at' => $now - 60, 'send_after' => '0', 'expires_at' => $now - 1, 'status' => 'queued',
		'attempts' => '0', 'last_error' => '', 'sent_at' => '0', 'kind' => 'order', 'recipient' => '905551112233',
		'body' => 'Your order <b>', 'reference' => 'ORD-1', 'provider' => '', 'provider_id' => '',
	);

	$built = pg_sms_queue_list_row($row, $now);
	$html = pg_list_row_html($built);

	pg_assert_same(11, count($built['cells']), 'one cell per heading');
	pg_assert_contains(h(lang('Expired')), $html);
	pg_assert_false(strpos($html, 'retry:3') !== false, 'an expired message is not retried');
	pg_assert_contains('Your order &lt;b&gt;<div class="small fg-secondary">ORD-1</div>', $html, 'the body with its reference');
	pg_assert_contains('max-width:28rem;', $built['cells'][5]['html'], 'the body wraps in an inner block');

	// A queued row past its expiry is never sent (pg_sms_queue_run() marks it
	// failed without an attempt), so it reads Expired; a live one goes on the
	// next run.
	$row['expires_at'] = $now + 3600;
	$html = pg_list_row_html(pg_sms_queue_list_row($row, $now));
	pg_assert_contains(h(lang('On the next run')), $html, 'a live queued row');
	pg_assert_contains('retry:3', $html);
}

// The screen prints no rows: both tables are paged by their actions, so a
// message past the 100th can be found, retried and deleted.
function test_mail_queue_screen_paged()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mail_queue.php');

	pg_assert_contains('data-pg-list-action="mail_queue_list_rows"', $source);
	pg_assert_contains('data-pg-list-action="sms_queue_list_rows"', $source);
	pg_assert_false(strpos($source, 'LIMIT 100') !== false, 'no fixed 100-row window');
	pg_assert_contains("is_string(\$_POST['mail_action'])", $source, 'a non-string mail_action is empty');
	pg_assert_contains('pg_mail_queue_run(1, 20, $mail_queue_id)', $source, 'a retry while the queue is inactive sends the message');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	pg_assert_contains("'mail_queue_list_rows' => array('file' => 'lists.php'", $actions);
	pg_assert_contains("'sms_queue_list_rows' => array('file' => 'lists.php'", $actions);
}
