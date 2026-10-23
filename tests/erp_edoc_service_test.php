<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the e-document service
 * (includes/erp/edoc/service.php): when a new send would duplicate a record
 * the provider already holds, and when the repair form's carrier is refused.
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

// The repair form posts both carrier keys even when it does not draw the
// carrier boxes, as empty strings. A buyer-only repair is not held back by the
// carrier already stored on the invoice; a carrier that was typed is checked
// as the pair it will be sent as, the empty box completed from the stored one.
function test_erp_edoc_fix_carrier_refusal_only_for_a_typed_carrier()
{
	$blank = array('tax_office' => 'Kadıköy', 'carrier_title' => '', 'carrier_vkn' => '');
	$mistyped = array('carrier_title' => 'Aras Kargo', 'carrier_vkn' => '1234567891');
	$courier = array('carrier_title' => 'Kurye', 'carrier_vkn' => '10000000146');

	pg_assert_same(null, erp_edoc_fix_carrier_refusal($blank, $mistyped), 'a stored carrier that fails the check digits does not block the buyer');
	pg_assert_same(null, erp_edoc_fix_carrier_refusal($blank, $courier), 'a stored TCKN with a one-word title does not block the buyer');
	pg_assert_same(null, erp_edoc_fix_carrier_refusal(array('tax_office' => 'Kadıköy'), $mistyped), 'no carrier keys at all');
	pg_assert_same(null, erp_edoc_fix_carrier_refusal(array('carrier_title' => ' ', 'carrier_vkn' => ' '), $mistyped), 'spaces are not a typed carrier');

	$vkn = erp_edoc_fix_carrier_refusal(array('carrier_title' => '', 'carrier_vkn' => '1234567891'), array('carrier_title' => 'Aras Kargo', 'carrier_vkn' => ''));
	pg_assert_same('carrier_vkn', $vkn['field'] ?? '', 'a typed VKN is still checked');
	pg_assert_contains('check digits', $vkn['error'] ?? '');
	pg_assert_false($vkn['success'] ?? true);

	$title = erp_edoc_fix_carrier_refusal(array('carrier_title' => 'Kurye', 'carrier_vkn' => ''), array('carrier_title' => '', 'carrier_vkn' => '10000000146'));
	pg_assert_same('carrier_title', $title['field'] ?? '', 'a typed title is paired with the stored TCKN');
	pg_assert_contains('first name and surname', $title['error'] ?? '');

	pg_assert_same('carrier_vkn', erp_edoc_fix_carrier_refusal(array('carrier_vkn' => erp_edoc_final_consumer_tckn()), array())['field'] ?? '', 'the final-consumer number is never the carrier');

	pg_assert_same(null, erp_edoc_fix_carrier_refusal(array('carrier_title' => 'Ali Veli', 'carrier_vkn' => ''), $courier), 'a person with the stored TCKN');
	pg_assert_same(null, erp_edoc_fix_carrier_refusal(array('carrier_title' => '', 'carrier_vkn' => '1234567808'), $mistyped), 'a corrected VKN with the stored title');
}
