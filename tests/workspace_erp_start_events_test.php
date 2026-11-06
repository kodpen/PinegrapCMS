<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP events a flow can start on: an invoice issued, paid in
 * full or first listed as overdue, its e-document answered, a receipt or
 * payment recorded, an incoming e-document received, a sales quote accepted
 * or rejected. Each is an ERP webhook (erp_webhook_events()), reaches the
 * Workspace (pg_event_workspace_events()), names its record
 * (ws_events_record_of()) and gives the start box values
 * (ws_flow_event_outputs()); the two new ones are announced where they
 * happen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');

function workspace_erp_start_events_list()
{
	return array(
		'erp.invoice.created'      => 'invoice',
		'erp.invoice.paid'         => 'invoice',
		'erp.invoice.overdue'      => 'invoice',
		'erp.invoice.edoc_changed' => 'invoice',
		'erp.receipt.created'      => 'receipt',
		'erp.edoc.received'        => 'edoc',
		'erp.quote.accepted'       => 'erp_quote',
		'erp.quote.rejected'       => 'erp_quote',
	);
}

// Webhooks the ERP announces, kept for the Workspace, with outputs, in the
// catalogue as written, each naming its record.
function test_workspace_erp_start_events_reach_the_workspace()
{
	$outputs = ws_flow_event_outputs();
	$catalog = pg_test_function_source('ws_events_catalog');
	$webhooks = erp_webhook_events();

	foreach (workspace_erp_start_events_list() as $event => $record) {
		pg_assert_true(isset($webhooks[$event]), $event . ' is an ERP webhook');
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' is kept for the Workspace');
		pg_assert_true(isset($outputs[$event]), $event . ' gives the start box values');
		pg_assert_contains("'" . $event . "'", $catalog, $event . ' in ws_events_catalog()');
		pg_assert_same(array($record, 7), ws_events_record_of($event, array('id' => 7)), $event . ' names its ' . $record);
	}

	pg_assert_false(in_array('erp.invoice.cancelled', pg_event_workspace_events(), true), 'the other ERP events stay webhooks only');
}

// What the start box offers: single values, no amount in minor units; an
// invoice's e-document status laid flat.
function test_workspace_erp_start_events_outputs()
{
	$outputs = ws_flow_event_outputs();
	$keys = function ($event) use ($outputs) {
		return array_column($outputs[$event], 'key');
	};
	$invoice = array('number', 'direction', 'doc_type', 'status', 'account_id', 'order_id', 'issue_date', 'due_date', 'currency', 'edoc_status');

	foreach (array('erp.invoice.created', 'erp.invoice.paid', 'erp.invoice.edoc_changed') as $event) {
		pg_assert_same($invoice, $keys($event), $event);
	}

	pg_assert_same(array_merge($invoice, array('days_overdue')), $keys('erp.invoice.overdue'));
	pg_assert_same(array('direction', 'date', 'currency', 'account_id', 'cash_account_id', 'payment_method', 'invoice_id', 'description'), $keys('erp.receipt.created'));
	pg_assert_same(array('gib_number', 'invoice_type', 'issue_date', 'supplier_title', 'supplier_tax_number', 'currency'), $keys('erp.edoc.received'));

	foreach (array('erp.quote.accepted', 'erp.quote.rejected') as $event) {
		pg_assert_same(array('number', 'status', 'account_id', 'issue_date', 'valid_until', 'currency'), $keys($event), $event);
	}

	$kept = pg_event_workspace_payload('erp.invoice.edoc_changed', array('id' => 7, 'edoc' => array('provider' => 'x', 'status' => 'accepted')));
	pg_assert_same('accepted', $kept['edoc_status']);
}

// The two new events, announced once where they happen: an overdue invoice
// after the digest's stamp, an inbox document only when it is added.
function test_workspace_erp_start_events_new_events_are_announced()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/notify.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/inbox.php');

	$check = pg_test_function_source('erp_overdue_check');
	$stamp = strpos($check, "SET overdue_notified_at = ");
	$told = strpos($check, "erp_event_invoice((int) \$row['id'], 'erp.invoice.overdue'");
	pg_assert_true(($stamp !== false) && ($told > $stamp), 'after the first-listing stamp');

	$keep = pg_test_function_source('erp_edoc_inbox_keep');
	$insert = strpos($keep, 'INSERT INTO erp_edoc_inbox');
	$received = strpos($keep, "erp_event('erp.edoc.received'");
	pg_assert_true(($insert !== false) && ($received > $insert), 'after the insert');
	pg_assert_same(1, substr_count($keep, "erp_event('erp.edoc.received'"), 'only for an added document');
}
