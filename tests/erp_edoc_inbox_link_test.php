<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for marking an incoming e-invoice as taken in by a purchase invoice
 * that already exists (erp_edoc_inbox_link(), includes/erp/edoc/inbox.php):
 * a document a purchase invoice already carries is not linked to a second
 * one, so one ETTN never stands on two purchase invoices, and the state is
 * read under the inbox row's lock.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/inbox.php');

function erp_edoc_inbox_link_test_row($status, $invoice_id, $invoice_status)
{
	return array('id' => 7, 'gib_uuid' => 'A0B1C2D3-E4F5-4A6B-8C7D-9E0F1A2B3C4D', 'gib_number' => 'TED2026000000001',
		'status' => $status, 'invoice_id' => $invoice_id, 'invoice_status' => $invoice_status);
}

function erp_edoc_inbox_link_test_invoice($id, $gib_uuid = '', $status = 'issued')
{
	return array('id' => $id, 'account_id' => 3, 'status' => $status, 'gib_uuid' => $gib_uuid);
}

function test_erp_edoc_inbox_link_refuses_a_document_already_drafted_elsewhere()
{
	$row = erp_edoc_inbox_link_test_row('imported', 41, 'draft');

	pg_assert_true(erp_edoc_inbox_link_refusal($row, erp_edoc_inbox_link_test_invoice(52)) !== '',
		'a document whose draft stands is not linked to another purchase invoice');
}

function test_erp_edoc_inbox_link_refuses_a_document_already_taken_in_elsewhere()
{
	$row = erp_edoc_inbox_link_test_row('imported', 41, 'issued');

	pg_assert_true(erp_edoc_inbox_link_refusal($row, erp_edoc_inbox_link_test_invoice(52)) !== '',
		'a document whose purchase invoice stands is not linked to another one');
}

function test_erp_edoc_inbox_link_allows_a_waiting_document()
{
	pg_assert_same('', erp_edoc_inbox_link_refusal(erp_edoc_inbox_link_test_row('new', 0, null), erp_edoc_inbox_link_test_invoice(52)),
		'a new document is linked');
	pg_assert_same('', erp_edoc_inbox_link_refusal(erp_edoc_inbox_link_test_row('imported', 41, 'cancelled'), erp_edoc_inbox_link_test_invoice(52)),
		'a document whose invoice was cancelled waits again and is linked');
	pg_assert_same('', erp_edoc_inbox_link_refusal(erp_edoc_inbox_link_test_row('imported', 41, ''), erp_edoc_inbox_link_test_invoice(52)),
		'a document whose invoice was deleted waits again and is linked');
	pg_assert_same('', erp_edoc_inbox_link_refusal(erp_edoc_inbox_link_test_row('imported', 52, 'issued'),
		erp_edoc_inbox_link_test_invoice(52, 'a0b1c2d3-e4f5-4a6b-8c7d-9e0f1a2b3c4d')),
		'linking again to the invoice that carries it is no refusal');
}

function test_erp_edoc_inbox_link_refuses_an_invoice_of_another_document()
{
	pg_assert_true(erp_edoc_inbox_link_refusal(erp_edoc_inbox_link_test_row('new', 0, null),
		erp_edoc_inbox_link_test_invoice(52, 'FFFFFFFF-E4F5-4A6B-8C7D-9E0F1A2B3C4D')) !== '',
		'an invoice carrying another ETTN is refused');
	pg_assert_true(erp_edoc_inbox_link_refusal(erp_edoc_inbox_link_test_row('new', 0, null),
		erp_edoc_inbox_link_test_invoice(52, '', 'cancelled')) !== '',
		'a cancelled invoice is refused');
	pg_assert_true(erp_edoc_inbox_link_refusal(erp_edoc_inbox_link_test_row('new', 0, null), null) !== '',
		'a missing invoice is refused');
}

function test_erp_edoc_inbox_link_reads_the_state_under_the_row_lock()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/inbox.php');
	$start = strpos($source, 'function erp_edoc_inbox_link(');
	$end = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, $end - $start);

	$begin = strpos($body, 'erp_tx_begin()');
	$lock = strpos($body, 'FOR UPDATE');
	$read = strpos($body, 'erp_edoc_inbox_row($id)');
	$check = strpos($body, 'erp_edoc_inbox_link_refusal(');

	pg_assert_true(($begin !== false) && ($lock !== false) && ($begin < $lock), 'the row is locked inside a transaction');
	pg_assert_true(($read !== false) && ($lock < $read), 'the row is read after the lock');
	pg_assert_true(($check !== false) && ($read < $check), 'the refusal is asked of the locked row');
}
