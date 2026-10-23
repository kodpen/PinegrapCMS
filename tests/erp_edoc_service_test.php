<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the e-document service
 * (includes/erp/edoc/service.php): when a new send would duplicate a record
 * the provider already holds, and when a send that stopped may go again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/service.php');

// A record the provider holds is never created a second time: a draft there,
// and one a later step failed on while its id is still on the row (the
// conversion job that failed at Paraşüt). Without an id nothing was written
// there, and a rejected document is answered with a new one.
function test_erp_edoc_invoice_held_refusal()
{
	pg_assert_contains('already at the provider as a draft', erp_edoc_invoice_held_refusal(array('edoc_status' => 'created', 'edoc_external_id' => '5001')));

	$held = erp_edoc_invoice_held_refusal(array('edoc_status' => 'error', 'edoc_external_id' => '5001'));
	pg_assert_contains('still holds the document (id 5001)', $held, 'a failed later step keeps the record');
	pg_assert_contains('Ask after its status', $held);
	pg_assert_contains('id 5001', erp_edoc_invoice_held_refusal(array('edoc_status' => 'error', 'edoc_external_id' => ' 5001 ')), 'the id trimmed');

	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'error', 'edoc_external_id' => '')), 'a send that failed before the provider wrote anything');
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'error')), 'an old row without the column');
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'none', 'edoc_external_id' => '')));
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'rejected', 'edoc_external_id' => '5001')), 'a rejected document is answered with a new one');
}

// A send whose request ended inside the driver call leaves the row at
// 'sending' with no provider id, and nothing else ever writes it again. Past
// fifteen minutes it is taken for a send that stopped; a fresh one is a send
// still in flight (a second click stops at the gate), and one with an id is
// asked after at the provider.
function test_erp_edoc_invoice_send_stalled()
{
	$now = 1790000000;
	$row = array('edoc_status' => 'sending', 'edoc_external_id' => '', 'updated_at' => (string) ($now - 901));

	pg_assert_true(erp_edoc_invoice_send_stalled($row, $now), 'a send that stopped more than fifteen minutes ago');
	pg_assert_true(erp_edoc_invoice_send_stalled(array('edoc_status' => 'sending', 'edoc_external_id' => ' ', 'updated_at' => $now - 3600), $now), 'a blank id is no id');
	pg_assert_false(erp_edoc_invoice_send_stalled(array('updated_at' => (string) ($now - 900)) + $row, $now), 'fifteen minutes to the second is still in flight');
	pg_assert_false(erp_edoc_invoice_send_stalled(array('updated_at' => (string) ($now - 5)) + $row, $now), 'a send in flight');
	pg_assert_false(erp_edoc_invoice_send_stalled(array('edoc_external_id' => '5001') + $row, $now), 'a row with an id is asked after');

	foreach (array('none', 'error', 'created', 'sent', 'accepted', 'rejected', 'cancelled') as $status) {
		pg_assert_false(erp_edoc_invoice_send_stalled(array('edoc_status' => $status) + $row, $now), $status);
	}

	pg_assert_true(erp_edoc_invoice_send_stalled($row), 'judged by the clock when no time is given');
}

// The doors that refuse a 'sending' row let a stalled one through: the send
// gate (screen, order card, bulk send) and the order card's waiting step,
// whose status question a row without an id can never answer. The invoice
// screen and the order card say the stopped send may have reached the
// provider.
function test_erp_edoc_stalled_send_is_offered_again()
{
	$service = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/service.php');
	$from = strpos($service, 'function erp_edoc_invoice_can_send(');
	$gate = substr($service, $from, strpos($service, "\n}\n", $from) - $from);
	$note = "The last send stopped before {var:1} answered, so a draft of the document may already be there.";

	pg_assert_true(strpos($gate, "array('queued', 'sending', 'sent', 'accepted'), true) && !erp_edoc_invoice_send_stalled(\$invoice)) {") !== false, 'the send gate');

	$flow = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/order_flow.php');
	pg_assert_true(strpos($flow, "elseif (in_array(\$status, array('queued', 'sending', 'sent'), true) && !erp_edoc_invoice_send_stalled(\$invoice)) {") !== false, 'the order card waits only for a send in flight');
	pg_assert_true(strpos($flow, "if (erp_edoc_invoice_send_stalled(\$invoice)) {\n                \$step['facts'][] = array('text' => lang(array('string' => '" . $note) !== false, 'the order card says why');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_invoice.php');
	pg_assert_true(strpos($screen, "(erp_edoc_invoice_send_stalled(\$invoice)\n                            ? '<div class=\"col-12 my-2\"><div class=\"form-text\">' . h(lang(array('string' => '" . $note) !== false, 'the invoice screen says why');
}

// A number whose check digits do not add up is refused before the provider is
// asked: it costs a request (İşbaşı counts its reads) and the answer, "not
// registered for e-Invoice, goes as an e-Archive", sends a mistyped number on
// as a valid buyer. The refusal comes before the provider is looked up.
function test_erp_edoc_taxpayer_lookup_refuses_wrong_check_digits()
{
	$vkn = erp_edoc_taxpayer_lookup('1234567891');
	pg_assert_false($vkn['success'], 'a VKN with a wrong check digit');
	pg_assert_contains('check digits', $vkn['error']);

	$tckn = erp_edoc_taxpayer_lookup('10000000147');
	pg_assert_false($tckn['success'], 'a TCKN with a wrong check digit');
	pg_assert_contains('check digits', $tckn['error']);

	pg_assert_contains('10 digits', erp_edoc_taxpayer_lookup('123')['error'], 'the length is still told first');

	pg_assert_true(erp_edoc_tax_number_valid('1234567808'), 'the test VKN passes the check');
	pg_assert_true(erp_edoc_tax_number_valid('10000000146'), 'the test TCKN passes the check');
	pg_assert_false(strpos(erp_edoc_taxpayer_lookup('1234567808')['error'], 'check digits') !== false, 'a valid number goes on');
}
