<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mail queue screen's lists (includes/fn/mail_queue_list.php):
 * the search box's conditions, an e-mail's and a text message's row, the
 * long texts wrapping in an inner block, the delete question by status, the
 * columns and gate of both driver sources and the screen on the driver.
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

function mail_queue_list_test_search($columns, array $terms)
{
	return pg_list_search_where($terms, $columns['columns'], $columns['labels']);
}

function test_mail_queue_list_search()
{
	pg_assert_same(array('sql' => '', 'params' => array()), mail_queue_list_test_search(pg_mail_queue_list_search_columns(), array()));

	$search = mail_queue_list_test_search(pg_mail_queue_list_search_columns(), array('#1172'));
	pg_assert_contains('subject LIKE ?', $search['sql'], 'the subject is searched');
	pg_assert_contains('last_error LIKE ?', $search['sql'], 'the last error is searched');
	pg_assert_same('%#1172%', $search['params'][1]);

	$search = mail_queue_list_test_search(pg_mail_queue_list_search_columns(), array(lang('Failed')));
	pg_assert_contains('status IN (?)', $search['sql'], 'a status by its shown name');

	$search = mail_queue_list_test_search(pg_sms_queue_list_search_columns(), array('ref-9'));
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

	pg_assert_same(array_keys(pg_mail_queue_list_columns()), array_keys($built['cells']), 'one cell per column, in its order');
	pg_assert_same(7, $built['id']);
	pg_assert_contains('value="retry:7"', $html, 'a failed message can be retried');
	pg_assert_contains('value="delete:7"', $html);
	pg_assert_contains('&lt;x&gt;', $html, 'the error is escaped');
	pg_assert_contains(h(lang('The message will be deleted from the queue and not sent.')), $html);
	pg_assert_contains('<div class="text-break" style="white-space:normal;min-width:12rem;max-width:28rem;">', $built['cells']['last_error']['html'], 'the error wraps in an inner block');
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

	pg_assert_same(array_keys(pg_sms_queue_list_columns()), array_keys($built['cells']), 'one cell per column, in its order');
	pg_assert_contains(h(lang('Expired')), $html);
	pg_assert_false(strpos($html, 'retry:3') !== false, 'an expired message is not retried');
	pg_assert_contains('Your order &lt;b&gt;<div class="small fg-secondary">ORD-1</div>', $html, 'the body with its reference');
	pg_assert_contains('max-width:28rem;', $built['cells']['message']['html'], 'the body wraps in an inner block');

	// A queued row past its expiry is never sent (pg_sms_queue_run() marks it
	// failed without an attempt), so it reads Expired; a live one goes on the
	// next run.
	$row['expires_at'] = $now + 3600;
	$html = pg_list_row_html(pg_sms_queue_list_row($row, $now));
	pg_assert_contains(h(lang('On the next run')), $html, 'a live queued row');
	pg_assert_contains('retry:3', $html);
}

// Every kind the SMS layer queues has a label on the screen: a back-in-stock
// text is not shown under its raw key, and the cash-on-delivery code shares
// the otp kind with the sign-in code, so that label names neither alone.
function test_sms_queue_list_kinds_cover_the_layer()
{
	pg_assert_same(array(), array_values(array_diff(array_keys(pg_sms_kinds()), array_keys(pg_sms_queue_list_kinds()))), 'a label for every kind');

	$kinds = pg_sms_queue_list_kinds();
	pg_assert_same(lang('One-time code'), $kinds['otp']);

	$row = array(
		'id' => '4', 'created_at' => '1', 'send_after' => '0', 'expires_at' => '0', 'status' => 'sent',
		'attempts' => '1', 'last_error' => '', 'sent_at' => '2', 'kind' => 'stock', 'recipient' => '905551112233',
		'body' => 'Back', 'reference' => '', 'provider' => '', 'provider_id' => '',
	);
	pg_assert_contains(h(lang('Back in stock')), pg_sms_queue_list_row($row, 3)['cells']['kind']['html']);
}

// Both queues are lists of the list table driver: the screen prints the
// first rows, the next come from list_table_rows, so a message past the
// 100th can be found, retried and deleted.
function test_mail_queue_screen_on_the_driver()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mail_queue.php');

	pg_assert_contains('pg_list_table_html($mail_queue_list_source', $source);
	pg_assert_contains('pg_list_table_html($sms_queue_list_source', $source);
	pg_assert_contains('data-pg-list-search="mail_queue"', $source);
	pg_assert_contains('data-pg-list-search="mail_queue_sms"', $source);
	pg_assert_false(strpos($source, 'LIMIT 100') !== false, 'no fixed 100-row window');
	pg_assert_contains("is_string(\$_POST['mail_action'])", $source, 'a non-string mail_action is empty');
	pg_assert_contains('pg_mail_queue_run(1, 20, $mail_queue_id)', $source, 'a retry while the queue is inactive sends the message');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	pg_assert_false(strpos($actions, "'mail_queue_list_rows'") !== false, 'the old paged action is gone');

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
	pg_assert_same('pg_mail_queue_list_source', pg_list_table_definition('mail_queue')['source']);
	pg_assert_same('pg_sms_queue_list_source', pg_list_table_definition('mail_queue_sms')['source']);
}

function test_mail_queue_list_columns()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

	$columns = pg_list_table_columns(pg_mail_queue_list_columns());
	pg_assert_same(array('recipient', 'subject', 'status', 'kind', 'attempts', 'last_error', 'created', 'when', 'number', 'actions'), array_keys($columns));
	pg_assert_same(false, $columns['recipient']['hideable'], 'the recipient names the row');
	pg_assert_same('created_at', $columns['created']['sort']);
	pg_assert_contains('FIELD(mail_type', $columns['kind']['sort']);
	pg_assert_false($columns['when']['sortable'], 'next attempt or sent reads two columns');

	$columns = pg_list_table_columns(pg_sms_queue_list_columns());
	pg_assert_same(array('recipient', 'message', 'status', 'kind', 'attempts', 'last_error', 'provider', 'created', 'when', 'number', 'actions'), array_keys($columns));
	pg_assert_contains("FIELD(status, ", $columns['status']['sort']);
}

function test_mail_queue_list_sources_gate()
{
	pg_assert_same(null, pg_mail_queue_list_source(array('role' => 3), array()), 'a User does not open the queue');
	pg_assert_same(null, pg_sms_queue_list_source(array('role' => 3), array()));
	pg_assert_same(null, pg_mail_queue_list_source(array(), array()));
}
