<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/accounting_sync.php: the row an ERP event writes,
 * the refunds the scan queues, the key that keeps a retried call one call,
 * a row's columns after its driver's answer (sent, not ready, slowed down,
 * tried again, failed), a driver that sends nothing, the pace, the
 * statements, and the places outside the module that call it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// webhooks.php (api_webhook_backoff()) opens behind the request's gate.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync.php');

function accounting_sync_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A driver that sends nothing: it keeps what it was handed and gives the
// answer it was told to.
function accounting_sync_test_driver(array $row, array $target)
{
	$GLOBALS['accounting_sync_test_handed'][] = array($row, $target);

	return $GLOBALS['accounting_sync_test_answer'];
}

function accounting_sync_test_row(array $more = array())
{
	return $more + array('id' => 7, 'service' => 'xero', 'company' => 'b3f1c2d4-0000-4000-8000-000000000001', 'kind' => 'sale',
		'local_table' => 'orders', 'local_id' => 42, 'status' => 'waiting', 'attempts' => 0, 'next_try_at' => 1000);
}

// The kinds and the table's ENUM agree.
function test_accounting_sync_kinds()
{
	pg_assert_same(array('customer', 'item', 'tax', 'sale', 'payment', 'refund'), pg_accounting_sync_kinds(), 'six kinds');
	pg_assert_contains("ENUM('customer','item','tax','sale','payment','refund')",
		accounting_sync_test_source('includes/migrations/2026.11.0.php'), 'as the table lists them');
}

// The target: connected with an organisation chosen, or nothing.
function test_accounting_sync_xero_target()
{
	$state = array('tenant_id' => 'b3f1c2d4-0000-4000-8000-000000000001');

	pg_assert_same(array('service' => 'xero', 'company' => $state['tenant_id']), pg_accounting_sync_xero_target($state, true), 'connected, organisation chosen');
	pg_assert_same(null, pg_accounting_sync_xero_target($state, false), 'no connection');
	pg_assert_same(null, pg_accounting_sync_xero_target(array('tenant_id' => ''), true), 'no organisation');
}

// An ERP event's row.
function test_accounting_sync_erp_event_row()
{
	$invoice = array('id' => 5, 'direction' => 'sales', 'doc_type' => 'invoice', 'status' => 'issued');

	pg_assert_same(array('kind' => 'sale', 'local_table' => 'erp_invoices', 'local_id' => 5, 'again' => false, 'invoice_id' => 0),
		pg_accounting_sync_erp_event_row('erp.invoice.created', $invoice), 'a sales invoice is a sale');
	$return = pg_accounting_sync_erp_event_row('erp.invoice.created', array('doc_type' => 'return', 'parent_invoice_id' => 4) + $invoice);
	pg_assert_same('refund', $return['kind'], 'a return invoice is a refund');
	pg_assert_same(4, $return['invoice_id'], 'of the invoice it returns');
	pg_assert_same(null, pg_accounting_sync_erp_event_row('erp.invoice.created', array('doc_type' => 'proforma') + $invoice), 'a proforma writes nothing');
	pg_assert_same(null, pg_accounting_sync_erp_event_row('erp.invoice.created', array('direction' => 'purchase') + $invoice), 'a purchase invoice writes nothing');
	pg_assert_same(null, pg_accounting_sync_erp_event_row('erp.invoice.created', array('status' => 'draft') + $invoice), 'a draft writes nothing');
	pg_assert_same(null, pg_accounting_sync_erp_event_row('erp.invoice.created', array('id' => 0) + $invoice), 'no id');

	$cancelled = pg_accounting_sync_erp_event_row('erp.invoice.cancelled', array('status' => 'cancelled') + $invoice);
	pg_assert_true($cancelled['again'], 'a cancellation puts the row back');
	pg_assert_same('sale', $cancelled['kind'], 'of the sale');

	pg_assert_same(array('kind' => 'payment', 'local_table' => 'erp_cash_transactions', 'local_id' => 9, 'again' => false, 'invoice_id' => 5),
		pg_accounting_sync_erp_event_row('erp.receipt.created', array('id' => 9, 'invoice_id' => 5)), 'a receipt for an invoice is a payment');
	pg_assert_same(null, pg_accounting_sync_erp_event_row('erp.receipt.created', array('id' => 9, 'invoice_id' => 0)), 'a receipt for no invoice writes nothing');
	pg_assert_true(pg_accounting_sync_erp_event_row('erp.receipt.cancelled', array('id' => 9))['again'], 'a receipt cancelled puts its row back');
	pg_assert_same(null, pg_accounting_sync_erp_event_row('erp.account.created', array('id' => 3)), 'other events write nothing');
}

// The refund scan: an order the service has a sale for, more than nothing.
function test_accounting_sync_refunds_to_queue()
{
	$refunds = array(
		array('id' => 1, 'order_id' => 10, 'amount_cents' => 500),
		array('id' => 2, 'order_id' => 11, 'amount_cents' => 500),
		array('id' => 3, 'order_id' => 10, 'amount_cents' => 0),
		array('id' => 4, 'order_id' => 10, 'amount_cents' => 1),
	);

	pg_assert_same(array(1, 4), pg_accounting_sync_refunds_to_queue($refunds, array('10')), 'sold orders, more than nothing');
	pg_assert_same(array(), pg_accounting_sync_refunds_to_queue($refunds, array()), 'no sale, no refund');
}

// The key: 50 characters, the same on every try, apart per operation, row
// and site.
function test_accounting_sync_key()
{
	$row = accounting_sync_test_row();
	$key = pg_accounting_sync_key_for('secret', $row, 'create');

	pg_assert_same(50, strlen($key), 'QuickBooks\' 50 characters');
	pg_assert_same(1, preg_match('/^pg-[0-9a-f]{47}$/', $key), 'plain characters');
	pg_assert_same($key, pg_accounting_sync_key_for('secret', array('attempts' => 4, 'id' => 99) + $row, 'create'), 'the attempt and the row id do not change it');
	pg_assert_true($key !== pg_accounting_sync_key_for('secret', $row, 'void'), 'another operation');
	pg_assert_true($key !== pg_accounting_sync_key_for('secret', array('local_id' => 43) + $row, 'create'), 'another record');
	pg_assert_true($key !== pg_accounting_sync_key_for('other', $row, 'create'), 'another site');
}

function test_accounting_sync_tax_local_id()
{
	pg_assert_same(20000, pg_accounting_sync_tax_local_id('20.000'), '20%');
	pg_assert_same(8875, pg_accounting_sync_tax_local_id(8.875), '8.875%');
	pg_assert_same(0, pg_accounting_sync_tax_local_id(-1), 'never below zero');
}

// A row after its driver's answer.
function test_accounting_sync_after_attempt()
{
	$row = accounting_sync_test_row(array('attempts' => 1));

	$sent = pg_accounting_sync_after_attempt($row, array('ok' => true, 'remote_id' => 'INV-1', 'sync_token' => '3'), 5000);
	pg_assert_same('sent', $sent['status'], 'sent');
	pg_assert_same('INV-1', $sent['remote_id'], 'the remote id kept');
	pg_assert_same('3', $sent['sync_token'], 'the sync token kept');
	pg_assert_same(2, $sent['attempts'], 'an attempt counted');
	pg_assert_same(5000, $sent['sent_at'], 'when');
	pg_assert_false(isset(pg_accounting_sync_after_attempt($row, array('ok' => true), 5000)['remote_id']), 'a remote id not given is left as it was');

	$wait = pg_accounting_sync_after_attempt($row, array('ok' => false, 'wait' => 900, 'error' => 'The sale is not sent yet.'), 5000);
	pg_assert_same(array('updated_at' => 5000, 'status' => 'waiting', 'next_try_at' => 5900, 'last_error' => 'The sale is not sent yet.'), $wait, 'not ready: no attempt counted');
	pg_assert_same(5000 + 86400, pg_accounting_sync_after_attempt($row, array('wait' => 999999), 5000)['next_try_at'], 'a day at most');

	$slow = pg_accounting_sync_after_attempt($row, array('ok' => false, 'status' => 429, 'retry_after' => 10), 5000);
	pg_assert_same(5060, $slow['next_try_at'], 'a 429 waits a minute at least');
	pg_assert_false(isset($slow['attempts']), 'a 429 counts no attempt');
	pg_assert_same(5300, pg_accounting_sync_after_attempt($row, array('status' => 429, 'retry_after' => 300), 5000)['next_try_at'], 'its Retry-After');

	$again = pg_accounting_sync_after_attempt($row, array('ok' => false, 'status' => 503, 'error' => 'Down'), 5000);
	pg_assert_same('waiting', $again['status'], 'a 503 is tried again');
	pg_assert_same(5000 + api_webhook_backoff(2), $again['next_try_at'], 'after the backoff');
	pg_assert_same(2, $again['attempts'], 'counted');

	pg_assert_same('failed', pg_accounting_sync_after_attempt($row, array('ok' => false, 'status' => 400, 'error' => 'Bad'), 5000)['status'], 'a refusal fails at once');
	pg_assert_same('failed', pg_accounting_sync_after_attempt($row, array('ok' => false, 'status' => 500, 'final' => true), 5000)['status'], 'a final answer fails');
	pg_assert_same('failed', pg_accounting_sync_after_attempt(array('attempts' => api_webhook_max_attempts() - 1), array('status' => 0), 5000)['status'], 'the last attempt fails');
	pg_assert_same('No answer.', pg_accounting_sync_after_attempt($row, array('status' => 0), 5000)['last_error'], 'an error always says something');
	pg_assert_same(500, strlen(pg_accounting_sync_after_attempt($row, array('status' => 400, 'error' => str_repeat('x', 900)), 5000)['last_error']), 'the error is cut to its column');
}

function test_accounting_sync_holds()
{
	pg_assert_true(pg_accounting_sync_holds(array('ok' => false, 'status' => 0)), 'no answer');
	pg_assert_true(pg_accounting_sync_holds(array('ok' => false, 'status' => 401)), 'the token refused');
	pg_assert_true(pg_accounting_sync_holds(array('ok' => false, 'status' => 429)), 'slow down');
	pg_assert_false(pg_accounting_sync_holds(array('ok' => false, 'status' => 400)), 'one record refused');
	pg_assert_false(pg_accounting_sync_holds(array('ok' => false, 'status' => 0, 'wait' => 60)), 'a row not ready');
	pg_assert_false(pg_accounting_sync_holds(array('ok' => true, 'status' => 200)), 'sent');
}

// A driver that sends nothing: it is handed the row and the target, and its
// answer becomes the row's columns.
function test_accounting_sync_null_driver()
{
	$row = accounting_sync_test_row();
	$target = array('service' => 'xero', 'company' => $row['company']);
	$GLOBALS['accounting_sync_test_handed'] = array();
	$GLOBALS['accounting_sync_test_answer'] = array('ok' => true, 'status' => 200, 'remote_id' => 'abc');

	$attempt = pg_accounting_sync_attempt($row, 'accounting_sync_test_driver', $target, 6000);
	pg_assert_same(array(array($row, $target)), $GLOBALS['accounting_sync_test_handed'], 'handed the row and the target');
	pg_assert_same('sent', $attempt['values']['status'], 'sent');
	pg_assert_same('abc', $attempt['values']['remote_id'], 'paired');

	$GLOBALS['accounting_sync_test_answer'] = 'nonsense';
	$attempt = pg_accounting_sync_attempt($row, 'accounting_sync_test_driver', $target, 6000);
	pg_assert_same(0, $attempt['outcome']['status'], 'an answer that is no array is no answer');
	pg_assert_same('waiting', $attempt['values']['status'], 'tried again');
	pg_assert_true(pg_accounting_sync_holds($attempt['outcome']), 'and the pass stops');

	pg_assert_same('', pg_accounting_sync_driver('no_such_service'), 'a service without a driver: its rows wait');
	pg_assert_same('', pg_accounting_sync_driver('../xero'), 'no path in a service name');
}

function test_accounting_sync_pace_and_statements()
{
	pg_assert_same(10, pg_accounting_sync_pace('xero'), 'Xero');
	pg_assert_same(40, pg_accounting_sync_pace('quickbooks'), 'QuickBooks Online');
	pg_assert_same(10, pg_accounting_sync_pace('other'), 'another');

	pg_assert_same(8, substr_count(pg_accounting_sync_insert_sql(), '?'), 'insert: eight values');
	pg_assert_contains('INSERT IGNORE', pg_accounting_sync_insert_sql(), 'a record keeps one row');
	pg_assert_same(7, substr_count(pg_accounting_sync_again_sql(), '?'), 'again: seven values');
	pg_assert_false(strpos(pg_accounting_sync_again_sql(), 'INSERT') !== false, 'again writes no row');
	pg_assert_same(3, substr_count(pg_accounting_sync_due_sql(10), '?'), 'due: three values');
	pg_assert_contains('LIMIT 10', pg_accounting_sync_due_sql(10), 'the pace');
	pg_assert_contains('LIMIT 1', pg_accounting_sync_due_sql(-5), 'one at least');
	pg_assert_same(14, substr_count(pg_accounting_sync_pair_sql(), '?'), 'pair: fourteen values');
}

// The places outside the module, the step and the table list.
function test_accounting_sync_wiring()
{
	pg_assert_contains('pg_accounting_sync_order_paid($order_id);', accounting_sync_test_source('includes/fn/ecommerce.php'), 'Payment Received');
	pg_assert_contains('pg_accounting_sync_order_paid($order_id);', accounting_sync_test_source('includes/fn/order_completion.php'), 'a checkout that took the money');
	pg_assert_contains('pg_accounting_sync_erp_event(', accounting_sync_test_source('includes/erp/events.php'), 'the ERP events');
	pg_assert_contains('pg_accounting_sync_queue_refunds();', accounting_sync_test_source('job.php'), 'the refund scan');
	pg_assert_contains('pg_accounting_sync_dispatch(', accounting_sync_test_source('job.php'), 'the general job');

	$migration = accounting_sync_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_accounting_sync();', $migration, 'the step is called');
	pg_assert_contains("install_create_table('accounting_sync'", $migration, 'created with the helper');
	pg_assert_contains('UNIQUE KEY uniq_pair (service, company, kind, local_table, local_id)', $migration, 'the unique key');
	pg_assert_contains("'accounting_sync',", accounting_sync_test_source('install/index.php'), 'in get_tables()');
}
