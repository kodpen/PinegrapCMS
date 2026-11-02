<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checks of the delivery note and the return screens
 * (includes/erp/waybills.php, includes/erp/returns.php): a return is cut only
 * from an issued invoice, a note is tied only to an invoice of its own
 * account that has no note yet, a typed delivery place is kept, unit codes
 * read as names wherever the note is shown, and a refusal names the box it
 * is about.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/returns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');

// A return is cut from an issued invoice only: a draft has no number and no
// ledger row, a cancelled invoice and a return document are not returned.
function test_erp_return_refusal_takes_an_issued_invoice_only()
{
	pg_assert_same('', erp_return_refusal(array('doc_type' => 'invoice', 'status' => 'issued')), 'an issued invoice');
	pg_assert_same('', erp_return_refusal(array('doc_type' => 'invoice', 'status' => 'paid')), 'a paid invoice');
	pg_assert_same(lang('A draft is not a document yet; issue it first.'), erp_return_refusal(array('doc_type' => 'invoice', 'status' => 'draft')), 'a draft, in the API\'s words');
	pg_assert_same(lang('That invoice has been cancelled.'), erp_return_refusal(array('doc_type' => 'invoice', 'status' => 'cancelled')), 'a cancelled invoice');
	pg_assert_same(lang('Only an invoice can be returned.'), erp_return_refusal(array('doc_type' => 'return', 'status' => 'issued')), 'a return document');
}

function test_erp_return_screen_asks_before_the_form()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_return.php');
	$form = strpos($screen, 'if (!$_POST)');
	$asked = strpos($screen, 'erp_return_refusal($invoice)');

	pg_assert_true(($asked !== false) && ($asked < $form), 'the screen refuses before the form is drawn or read');

	$returns = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/returns.php');
	pg_assert_contains('erp_return_refusal($parent)', $returns, 'and the writer asks the same');
}

// The note's unit names do not depend on the invoice form being loaded.
function test_erp_waybill_unit_label_reads_every_code()
{
	pg_assert_same(lang('kilogram'), erp_waybill_unit_label('KGM'), 'KGM');
	pg_assert_same(lang('litre'), erp_waybill_unit_label('LTR'), 'LTR');
	pg_assert_same(lang('box'), erp_waybill_unit_label('BX'), 'BX');
	pg_assert_same('XYZ', erp_waybill_unit_label('XYZ'), 'an unknown code as it is');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');
	pg_assert_false(strpos($source, "function_exists('erp_invoice_form_unit_codes')") !== false, 'no fallback list of one unit');
}

// A note is tied to an invoice of its own account, sold, with no note yet.
function test_erp_waybill_invoice_refusal()
{
	$invoice = array('id' => 68, 'full_number' => 'PGF2026000000049', 'account_id' => 18, 'direction' => 'sales', 'doc_type' => 'invoice', 'status' => 'issued');

	pg_assert_same('', erp_waybill_invoice_refusal($invoice, 18, array()), 'the invoice\'s own account');
	pg_assert_contains('PGF2026000000049', erp_waybill_invoice_refusal($invoice, 46, array()), 'another account is refused, naming the invoice');
	pg_assert_contains('PGFS2026000000011', erp_waybill_invoice_refusal($invoice, 18, array(array('id' => 11, 'full_number' => 'PGFS2026000000011'))), 'a second note is refused, naming the first');
	pg_assert_true(erp_waybill_invoice_refusal(array('direction' => 'purchase') + $invoice, 18, array()) !== '', 'a purchase invoice is refused');
	pg_assert_true(erp_waybill_invoice_refusal(array('doc_type' => 'return') + $invoice, 18, array()) !== '', 'a return document is refused');
	pg_assert_same('', erp_waybill_invoice_refusal(array('account_id' => 0) + $invoice, 46, array()), 'an order invoice whose account opens with the note is not judged by account');
}

function test_erp_waybill_create_asks_the_invoice_refusal()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');
	$from = strpos($source, 'function erp_waybill_create(');
	$body = substr($source, $from, strpos($source, 'erp_tx_begin()', $from) - $from);

	pg_assert_contains('erp_waybill_invoice_refusal(', $body, 'the save checks the invoice before a number is taken');
}

// A typed delivery place under an empty recipient is kept.
function test_erp_waybill_ship_to_fill_keeps_what_was_typed()
{
	$account = array('title' => 'pg-test Cari', 'address' => 'Atatürk Cd. 1', 'district' => 'Kadıköy', 'city' => 'İstanbul', 'state' => '', 'country_code' => 'TR');

	$typed = erp_waybill_ship_to_fill(array('ship_to_title' => '', 'ship_to_address' => '', 'ship_to_city' => 'München', 'ship_to_state' => 'Bayern', 'ship_to_country' => 'DE'), $account);
	pg_assert_same('pg-test Cari', $typed['ship_to_title'], 'the recipient is the account');
	pg_assert_same('München', $typed['ship_to_city'], 'the typed city stays');
	pg_assert_same('Bayern', $typed['ship_to_state'], 'the typed state stays');
	pg_assert_same('DE', $typed['ship_to_country'], 'the typed country stays');
	pg_assert_same('', $typed['ship_to_address'], 'no street of the account under another city');

	$empty = erp_waybill_ship_to_fill(array('ship_to_title' => '', 'ship_to_address' => '', 'ship_to_country' => ''), $account);
	pg_assert_same('Atatürk Cd. 1', $empty['ship_to_address'], 'an empty place is the account\'s');
	pg_assert_same('İstanbul', $empty['ship_to_city'], 'with its city');
	pg_assert_same('TR', $empty['ship_to_country'], 'and its country');

	$named = erp_waybill_ship_to_fill(array('ship_to_title' => 'Depo', 'ship_to_city' => ''), $account);
	pg_assert_same('Depo', $named['ship_to_title'], 'a named recipient is left alone');
	pg_assert_same('', $named['ship_to_city'], 'with its place');
}

// A refusal names the box it is about.
function test_erp_waybill_refusals_name_their_field()
{
	$built = erp_waybill_lines_build(array(3 => array('description' => 'Saat', 'quantity' => '1'), 5 => array('description' => 'Kayış', 'quantity' => 'abc')));
	pg_assert_same('lines[5][quantity]', $built['field'] ?? '', 'the quantity of the posted row');

	$built = erp_waybill_lines_build(array(0 => array('description' => '', 'quantity' => '2')));
	pg_assert_same('lines[0][description]', $built['field'] ?? '', 'the description');

	$header = erp_waybill_header_build(array('account_id' => 0, 'issue_date' => '2026-11-02', 'ship_time' => '25:61'), false);
	pg_assert_same('ship_time', $header['field'] ?? '', 'the shipment time');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_erp_waybill.php');
	pg_assert_contains("\$result['field']", $screen, 'the screen marks it');
}
