<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the legacy Paraşüt e-invoice inbox (view_parasut_inbox.php and
 * includes/fn/parasut.php): the totals its lists and detail show for a
 * sales invoice or purchase bill, the VKN/TCKN lookup that refuses a
 * malformed or mistyped number before any request, and, read from the
 * source, that the screen prints both.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The check digits of the lookup are the ERP's (erp_edoc_tax_number_valid()).
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');

/**
 * A lookup request that records every path it is asked for and answers
 * what it is given.
 *
 * @param array $calls   Receives each path asked for
 * @param array $answer  The _parasut_request() answer
 * @return callable
 */
function parasut_inbox_test_request(&$calls, $answer)
{
	return function ($path) use (&$calls, $answer) {
		$calls[] = $path;

		return $answer;
	};
}

// net_total is Paraşüt's "Genel Toplam", VAT included; the figure before VAT
// is before_taxes_total. A plain sale: 900.00 + 180.00 VAT = 1080.00.
function test_parasut_inbox_totals_plain_sale()
{
	pg_assert_same(
		array('before_vat' => 900.0, 'vat' => 180.0, 'withheld' => 0.0, 'total' => 1080.0),
		parasut_inbox_totals(array('gross_total' => '900.0', 'before_taxes_total' => '900.0', 'total_vat' => '180.0', 'net_total' => '1080.0')));
}

// With withholding net_total is the amount payable: 79.95 + 15.99 VAT - 6.40
// withheld = 89.54 (the live tevkifat draft).
function test_parasut_inbox_totals_withholding()
{
	pg_assert_same(
		array('before_vat' => 79.95, 'vat' => 15.99, 'withheld' => 6.4, 'total' => 89.54),
		parasut_inbox_totals(array('gross_total' => '79.95', 'before_taxes_total' => '79.95', 'total_vat' => '15.99', 'total_vat_withholding' => '6.4', 'net_total' => '89.54')));
}

// A discounted sale as Paraşüt answered it (demo company): 2 x 100.00 less
// 10 % at 20 % VAT, 1 x 50.00 at 10 % VAT, 20.00 off the invoice. gross_total
// is the subtotal before the line and invoice discounts, before_taxes_total
// what is left after them. A record without before_taxes_total gets the same
// figure from the subtotal less both discounts.
function test_parasut_inbox_totals_discounted_sale()
{
	$attributes = array('gross_total' => '250.0', 'total_discount' => '20.0', 'total_invoice_discount' => '20.0',
		'before_taxes_total' => '210.0', 'total_vat' => '37.44', 'net_total' => '247.44');

	pg_assert_same(array('before_vat' => 210.0, 'vat' => 37.44, 'withheld' => 0.0, 'total' => 247.44), parasut_inbox_totals($attributes));

	unset($attributes['before_taxes_total']);
	pg_assert_same(210.0, parasut_inbox_totals($attributes)['before_vat'], 'without before_taxes_total');

	$attributes['gross_total'] = '100.1';
	$attributes['total_discount'] = '0.2';
	$attributes['total_invoice_discount'] = '0.1';
	pg_assert_same(99.8, parasut_inbox_totals($attributes)['before_vat'], 'rounded to the kurus');
}

// A record without totals shows zeros, never a warning.
function test_parasut_inbox_totals_empty_record()
{
	pg_assert_same(array('before_vat' => 0.0, 'vat' => 0.0, 'withheld' => 0.0, 'total' => 0.0), parasut_inbox_totals(array()));
	pg_assert_same(array('before_vat' => 0.0, 'vat' => 0.0, 'withheld' => 0.0, 'total' => 0.0), parasut_inbox_totals(null));
}

// Paraşüt answers a filter on any malformed number with 200 and an empty
// list, the answer of a valid number that is not registered, so a number
// that is not ten or eleven digits, or fails the check digits, is refused
// before any request.
function test_parasut_check_einvoice_address_refuses_a_bad_number_without_asking()
{
	$calls = array();
	$request = parasut_inbox_test_request($calls, array('http_code' => 200, 'body' => array('data' => array())));

	foreach (array('313055766' => 'A VKN has 10 digits and a TCKN 11.', 'abc' => 'A VKN has 10 digits and a TCKN 11.',
			'31305576690' => 'The check digits of this number do not match; it is probably mistyped. GİB refuses a document that carries it.',
			'3130557668' => 'The check digits of this number do not match; it is probably mistyped. GİB refuses a document that carries it.') as $typed => $error) {
		$result = parasut_check_einvoice_address($typed, $request);

		pg_assert_false($result['success'], $typed);
		pg_assert_true($result['refused'], $typed);
		pg_assert_same(lang($error), $result['error'], $typed);
		pg_assert_same(null, $result['data'], $typed);
	}

	pg_assert_same(array(), $calls, 'no request');
}

// The number is asked with its digits only, as erp_edoc_taxpayer_lookup()
// reads it: a space or a dash typed inside is no reason to answer "not
// registered".
function test_parasut_check_einvoice_address_asks_the_digits()
{
	$calls = array();
	$inbox = array('id' => '79245', 'type' => 'e_invoice_inboxes', 'attributes' => array('vkn' => '3130557669', 'e_invoice_address' => 'urn:mail:efaturapk@example.com'));
	$request = parasut_inbox_test_request($calls, array('http_code' => 200, 'body' => array('data' => array($inbox))));

	$result = parasut_check_einvoice_address(' 313 055-7669 ', $request);

	pg_assert_same(array('/e_invoice_inboxes?filter[vkn]=3130557669'), $calls);
	pg_assert_true($result['success']);
	pg_assert_false($result['refused']);
	pg_assert_same('3130557669', $result['number']);
	pg_assert_same(array($inbox), $result['data']);

	// A valid number Paraşüt lists no mailbox for: registered nowhere.
	$calls = array();
	$result = parasut_check_einvoice_address('10000000146', parasut_inbox_test_request($calls, array('http_code' => 200, 'body' => array('data' => array()))));

	pg_assert_same(array('/e_invoice_inboxes?filter[vkn]=10000000146'), $calls);
	pg_assert_true($result['success']);
	pg_assert_same(array(), $result['data']);

	// The final consumer's eleven ones are asked, as the ERP's lookup asks them.
	$calls = array();
	parasut_check_einvoice_address('11111111111', parasut_inbox_test_request($calls, array('http_code' => 200, 'body' => array('data' => array()))));
	pg_assert_same(array('/e_invoice_inboxes?filter[vkn]=11111111111'), $calls);
}

// A refusal by Paraşüt is an API error, not a refused number.
function test_parasut_check_einvoice_address_reports_an_api_error()
{
	$calls = array();
	$result = parasut_check_einvoice_address('3130557669', parasut_inbox_test_request($calls,
		array('http_code' => 401, 'body' => array('errors' => array(array('title' => 'Unauthorized', 'detail' => 'Token expired'))))));

	pg_assert_false($result['success']);
	pg_assert_false($result['refused']);
	pg_assert_same('Unauthorized: Token expired', $result['error']);
	pg_assert_same(1, count($calls));
}

// The screen prints the totals of parasut_inbox_totals() under keys that say
// whether VAT is in them, and a refused number as the field's error.
function test_parasut_inbox_screen_prints_totals_and_refusal()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_parasut_inbox.php');

	pg_assert_same(2, substr_count($source, 'parasut_inbox_totals('), 'both lists');
	pg_assert_false(strpos($source, "lang('Net')"), "lang('Net')");
	pg_assert_false(strpos($source, "lang('Gross')"), "lang('Gross')");
	pg_assert_false(strpos($source, "['gross_total']"), 'gross_total read directly');
	pg_assert_same(3, substr_count($source, "lang('Total before VAT')"), 'two headers and the detail');
	pg_assert_same(3, substr_count($source, "lang('Grand total (incl. VAT)')"), 'two headers and the detail');
	pg_assert_true(strpos($source, "\$vkn_result['refused']") !== false, 'a refused number');
}
