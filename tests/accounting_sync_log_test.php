<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/accounting_sync_log.php: a queued row as the Events
 * card shows it, the daily counts and the day all sends failed, the one line
 * of View Order and the ERP invoice, Try again's statement, the alert's
 * sentence, the connection that holds the queue, and the places outside the
 * module that reach it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync_log.php');

function accounting_sync_log_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Each status has its words and the tone of the panel's status colours.
function test_accounting_sync_log_states()
{
	pg_assert_same('due', pg_accounting_sync_log_state('waiting', 0)['state'], 'waiting, not tried yet');
	pg_assert_same('retrying', pg_accounting_sync_log_state('waiting', 2)['state'], 'waiting after a failed try');
	pg_assert_same('gray', pg_accounting_sync_log_state('waiting', 2)['tone'], 'a retry waits on nobody');
	pg_assert_same('teal', pg_accounting_sync_log_state('sent', 1)['tone'], 'sent is teal');
	pg_assert_same('rose', pg_accounting_sync_log_state('failed', 5)['tone'], 'failed is rose');
}

// A row: its kind, the record it links to, the reason only for a failure or
// a retry, and Try again only on a failed one.
function test_accounting_sync_log_item()
{
	$failed = pg_accounting_sync_log_item(array(
		'id' => 7, 'kind' => 'refund', 'local_table' => 'order_refunds', 'local_id' => 3, 'order_id' => 41, 'number' => '1042',
		'status' => 'failed', 'attempts' => 1, 'last_error' => 'Currency not in the books', 'updated_at' => 1763600000, 'sent_at' => 0,
	));

	pg_assert_same('Refund', $failed['kind_label']);
	pg_assert_same('view_order.php?id=41', $failed['link'], 'a refund links to its order');
	pg_assert_same('Order 1042', $failed['link_label']);
	pg_assert_same('Currency not in the books', $failed['reason']);
	pg_assert_true($failed['may_retry'], 'a failed send can be tried again');

	$sent = pg_accounting_sync_log_item(array(
		'id' => 8, 'kind' => 'sale', 'local_table' => 'erp_invoices', 'local_id' => 12, 'order_id' => 0, 'number' => 'A-2026-7',
		'status' => 'sent', 'attempts' => 2, 'last_error' => '', 'updated_at' => 1763600000, 'sent_at' => 1763600500,
	));

	pg_assert_same('edit_erp_invoice.php?id=12', $sent['link'], 'an ERP invoice links to itself');
	pg_assert_same('Invoice A-2026-7', $sent['link_label']);
	pg_assert_same(1763600500, $sent['at'], 'a sent row is dated when sent');
	pg_assert_same('', $sent['reason']);
	pg_assert_true(!$sent['may_retry'], 'a sent row has no Try again');

	$due = pg_accounting_sync_log_item(array('kind' => 'payment', 'local_table' => 'erp_cash_transactions', 'local_id' => 5,
		'status' => 'waiting', 'attempts' => 0, 'last_error' => 'Tax rate 20% is not matched'));

	pg_assert_same('', $due['reason'], 'a row that only waits tells no reason');
	pg_assert_same('', $due['link'], 'a receipt has no screen to link');
}

// Every day of the window, newest first; sent and failed counted apart.
function test_accounting_sync_daily_counts()
{
	$counts = pg_accounting_sync_daily_counts(array(
		array('day' => 20000, 'status' => 'sent', 'n' => 4),
		array('day' => 20000, 'status' => 'failed', 'n' => 1),
		array('day' => 19999, 'status' => 'failed', 'n' => 3),
		array('day' => 19990, 'status' => 'failed', 'n' => 9),
	), 20000, 3);

	pg_assert_same(3, count($counts));
	pg_assert_same(gmdate('Y-m-d', 20000 * 86400), $counts[0]['day']);
	pg_assert_same(4, $counts[0]['sent']);
	pg_assert_same(1, $counts[0]['failed']);
	pg_assert_same(3, $counts[1]['failed']);
	pg_assert_same(0, $counts[2]['failed'], 'a day outside the window is not counted');
	pg_assert_true(!pg_accounting_sync_failed_all($counts[0]), 'a day with a send passed');
	pg_assert_true(pg_accounting_sync_failed_all($counts[1]), 'a day all sends failed');
	pg_assert_true(!pg_accounting_sync_failed_all($counts[2]), 'a quiet day');
}

// View Order's and the ERP invoice's line.
function test_accounting_sync_line()
{
	pg_assert_same(null, pg_accounting_sync_line(null, 'xero'), 'no row, nothing to say');
	pg_assert_same('Not sent: the order has no ERP invoice.', pg_accounting_sync_line(null, 'xero', 'the order has no ERP invoice.')['label']);
	pg_assert_same('Sent to Xero', pg_accounting_sync_line(array('status' => 'sent'), 'xero')['label']);
	pg_assert_same('Waiting to be sent', pg_accounting_sync_line(array('status' => 'waiting', 'last_error' => 'x'), 'xero')['label']);

	$failed = pg_accounting_sync_line(array('status' => 'failed', 'last_error' => 'Currency not in the books'), 'xero');
	pg_assert_same('Not sent: Currency not in the books', $failed['label']);
	pg_assert_same('rose', $failed['tone']);
}

// Try again puts back only a failed send of the company booked into.
function test_accounting_sync_try_again_sql()
{
	$sql = pg_accounting_sync_try_again_sql();

	pg_assert_true(strpos($sql, "status = 'failed'") !== false, 'only a failed row');
	pg_assert_true(strpos($sql, 'service = ? AND company = ?') !== false, 'only the current company');
	pg_assert_true(strpos($sql, "kind IN ('sale', 'payment', 'refund')") !== false, 'never a pairing');
	pg_assert_true(strpos($sql, 'attempts = 0') !== false, 'its attempts cleared');
	pg_assert_same(5, substr_count($sql, '?'));
}

function test_accounting_sync_failed_sentence()
{
	$sentence = pg_accounting_sync_failed_sentence('xero', array('day' => '2026-11-26', 'failed' => 3), 'Currency not in the books');

	pg_assert_same('No send to Xero passed on 2026-11-26: 3 failed. Last reason: Currency not in the books', $sentence);
}

// A connection that must be connected again holds the queue.
function test_accounting_sync_connection_held()
{
	pg_assert_true(pg_accounting_sync_connection_held(array('status' => 'reauth')));
	pg_assert_true(!pg_accounting_sync_connection_held(array('status' => 'active')));
	pg_assert_true(!pg_accounting_sync_connection_held(null));
}

// The places outside the module that reach it.
function test_accounting_sync_log_wiring()
{
	$alerts = accounting_sync_log_test_source('includes/fn/ops_alerts.php');
	pg_assert_true(strpos($alerts, "'accounting_sync_failed' => array(") !== false, 'the alert code is in the catalogue');
	pg_assert_same('pg_ops_alerts_check_accounting_sync', pg_ops_alert_sources()['accounting_sync']);

	$connections = accounting_sync_log_test_source('connections.php');
	pg_assert_true(strpos($connections, 'pg_accounting_sync_log_card(true, $page_id)') !== false, 'the Events card on Xero\'s and QuickBooks\' pages');
	pg_assert_true(strpos($connections, "\$action === 'accounting_sync_again') && connections_accounting_may_manage(\$user)") !== false, 'Try again under the Accounting gate');

	pg_assert_true(strpos(accounting_sync_log_test_source('view_order.php'), "pg_accounting_sync_record_line('orders'") !== false, 'View Order\'s line');
	pg_assert_true(strpos(accounting_sync_log_test_source('edit_erp_invoice.php'), "pg_accounting_sync_record_line('erp_invoices'") !== false, 'the ERP invoice\'s line');
	pg_assert_true(strpos(accounting_sync_log_test_source('includes/fn/accounting_sync.php'), "(\$driver === '') || pg_accounting_sync_held()") !== false, 'the pass is held');
}
