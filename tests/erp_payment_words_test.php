<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the words of an ERP payment (money paid out to a supplier): the
 * receipt screen (erp_receipt.php), the record form (add_erp_receipt.php),
 * the allocation refusals (includes/erp/settlement.php) and the audit trail
 * (includes/erp/audit.php) say "payment" for a payment and keep "receipt"
 * for money taken in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_payment_words_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_erp_payment_words_on_the_receipt_screen()
{
	$source = erp_payment_words_test_source('erp_receipt.php');

	foreach (array(
		"\$is_collection ? 'erp receipt (#{var:1}) was cancelled' : 'erp payment (#{var:1}) was cancelled'",
		"\$is_collection ? 'The receipt has been cancelled and {var:1} invoice(s) reopened.' : 'The payment has been cancelled and {var:1} invoice(s) reopened.'",
		"\$is_collection ? 'erp allocation was removed from receipt (#{var:1})' : 'erp allocation was removed from payment (#{var:1})'",
		"\$is_collection ? 'erp receipt (#{var:1}) was allocated to {var:2} invoice(s)' : 'erp payment (#{var:1}) was allocated to {var:2} invoice(s)'",
		"lang('This payment has not been allocated to any invoice.')",
	) as $needle) {
		pg_assert_contains($needle, $source, 'receipt screen');
	}

	pg_assert_false(strpos($source, "lang('Collection')") !== false, 'receipt screen: no Collection key');
}

function test_erp_payment_words_on_the_record_form()
{
	$source = erp_payment_words_test_source('add_erp_receipt.php');

	pg_assert_contains("lang('The invoice\\'s currency; the payment is entered in it.')", $source, 'record form: currency hint');
	pg_assert_contains("lang('rate of the payment date')", $source, 'record form: rate placeholder');
	pg_assert_contains("'{var:1} per unit; leave empty for the recorded rate of the payment date. Ignored for the base currency.'", $source, 'record form: rate hint');
}

function test_erp_payment_words_in_the_allocation_refusals()
{
	$source = erp_payment_words_test_source('includes/erp/settlement.php');

	pg_assert_contains("\$is_payment = ((string) \$txn['doc_type'] === 'payment');", $source, 'settlement: the kind');
	pg_assert_contains("lang('That payment has been cancelled.')", $source, 'settlement: cancelled');
	pg_assert_contains("'Invoice {var:1} is in {var:2}; record the payment in that currency.'", $source, 'settlement: currency');
	pg_assert_contains("lang('That amount is more than is unallocated on this payment.')", $source, 'settlement: unallocated');
}

function test_erp_payment_words_in_the_audit_trail()
{
	if (!function_exists('erp_audit_event_text')) {
		require_once(PG_FUNCTIONS_DIR . '/includes/erp/audit.php');
	}

	$text = function ($event, $direction) {
		return erp_audit_event_text(array('event' => $event, 'detail' => json_encode(array('direction' => $direction))));
	};

	pg_assert_same(lang('Payment cancelled'), $text('erp.receipt.cancelled', 'payment'), 'a cancelled payment');
	pg_assert_same(lang('Collection cancelled'), $text('erp.receipt.cancelled', 'collection'), 'a cancelled collection');
	pg_assert_same(lang('Receipt cancelled'), erp_audit_event_text(array('event' => 'erp.receipt.cancelled', 'detail' => '')), 'no direction');
	pg_assert_same(lang('Payment recorded'), $text('erp.receipt.created', 'payment'), 'a recorded payment');
}
