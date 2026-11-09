<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the buyer's VKN/TCKN the order screen's Paraşüt invoice is
 * issued under (includes/fn/parasut.php): a Turkish buyer's number goes as
 * its digits, so a spaced or TR-prefixed number of a registered buyer is
 * looked up as typed by GİB and gets an e-Invoice, and a number that is not
 * a valid VKN/TCKN stops the invoice instead of reading as "not registered".
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

// A Turkish buyer's number is its digits: spaces, dashes, dots and a TR
// prefix typed at checkout are dropped.
function test_parasut_order_identity_number_keeps_a_turkish_buyers_digits()
{
	pg_assert_same('3130557669', parasut_order_identity_number(' 313 055 76 69 ', 'TR'));
	pg_assert_same('3130557669', parasut_order_identity_number('TR3130557669', 'TR'));
	pg_assert_same('10000000146', parasut_order_identity_number('100-000-001.46', 'TR'));
	pg_assert_same('', parasut_order_identity_number('  ', 'TR'));
}

// A buyer abroad keeps the number as typed: a foreign VAT id is not a VKN.
function test_parasut_order_identity_number_keeps_a_foreign_number_as_typed()
{
	pg_assert_same('DE 123456789', parasut_order_identity_number(' DE 123456789 ', 'DE'));
}

// A Turkish number that is not a VKN or TCKN with matching check digits is
// refused; an empty one, a valid one and the final consumer's are not.
function test_parasut_order_identity_refusal()
{
	pg_assert_same('', parasut_order_identity_refusal('', 'TR'));
	pg_assert_same('', parasut_order_identity_refusal('3130557669', 'TR'));
	pg_assert_same('', parasut_order_identity_refusal('10000000146', 'TR'));
	pg_assert_same('', parasut_order_identity_refusal('11111111111', 'TR'));
	pg_assert_same('', parasut_order_identity_refusal('DE 123456789', 'DE'));

	foreach (array('3130557668', '123456789', '123456789012', '10000000147') as $number) {
		pg_assert_true(parasut_order_identity_refusal($number, 'TR') !== '', $number);
	}
}

// The contact card carries the digits, and a spaced company VKN is no TCKN.
function test_parasut_order_contact_attributes_take_the_digits()
{
	$order = array('billing_company' => 'Örnek Yazılım A.Ş.', 'billing_country' => 'DE');
	$attributes = parasut_order_contact_attributes($order, parasut_order_identity_number('TR 313 055 76 69', 'TR'), 'DE');

	pg_assert_same('3130557669', $attributes['tax_number']);
	pg_assert_same('company', $attributes['contact_type']);
}

// The invoice reads the number through the normalizer and asks the refusal
// before the e-document kind is looked up.
function test_parasut_create_invoice_refuses_a_malformed_number_before_the_lookup()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');

	$identity = strpos($source, 'function _parasut_order_identity(');
	$body = substr($source, $identity, strpos($source, "\n}\n", $identity) - $identity);
	pg_assert_contains('parasut_order_identity_number(', $body);

	$refusal = strpos($source, 'parasut_order_identity_refusal($identity');
	$lookup = strpos($source, '_parasut_resolve_edoc_kind($identity, $token)');
	pg_assert_true(($refusal !== false) && ($lookup !== false) && ($refusal < $lookup), 'refusal before lookup');
}
