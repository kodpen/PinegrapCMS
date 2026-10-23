<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure helpers of the Paraşüt client (includes/fn/parasut.php):
 * the authorization page address, the owner stamp of the sealed token, the
 * reading of the /v4/me answer and the words of a refusal.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The authorization page names the client, the out-of-band redirect and the
// code response type; without a client id there is nothing to open.
function test_parasut_authorize_url_names_client_and_oob_redirect()
{
	$url = parasut_authorize_url('Lk-abc_123');

	pg_assert_same('https://api.parasut.com/oauth/authorize?', substr($url, 0, 40));
	pg_assert_contains('client_id=Lk-abc_123', $url);
	pg_assert_contains('redirect_uri=urn%3Aietf%3Awg%3Aoauth%3A2.0%3Aoob', $url);
	pg_assert_contains('response_type=code', $url);
	pg_assert_same('', parasut_authorize_url(''));
	pg_assert_same('', parasut_authorize_url('   '));
}

// The owner stamp follows the three values it is given; a change in any one
// of them is another owner, so a token kept for one account is never
// offered to another.
function test_parasut_token_owner_changes_with_company_client_and_user()
{
	$owner = _parasut_token_owner('123', 'client-a', 'a@example.com');

	pg_assert_same(32, strlen($owner));
	pg_assert_same($owner, _parasut_token_owner('123', 'client-a', 'a@example.com'));
	pg_assert_true($owner !== _parasut_token_owner('124', 'client-a', 'a@example.com'));
	pg_assert_true($owner !== _parasut_token_owner('123', 'client-b', 'a@example.com'));
	pg_assert_true($owner !== _parasut_token_owner('123', 'client-a', ''));
}

// /v4/me with the companies included: the user's e-mail and name, every
// company the relationship lists, named from the included records.
function test_parasut_me_parse_reads_user_and_companies()
{
	$body = array(
		'data' => array(
			'id' => '55',
			'type' => 'users',
			'attributes' => array('name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com', 'is_confirmed' => true),
			'relationships' => array(
				'companies' => array('data' => array(
					array('id' => '1001', 'type' => 'companies'),
					array('id' => '1002', 'type' => 'companies'),
				)),
				'profile' => array('data' => array('id' => '7', 'type' => 'profiles')),
			),
		),
		'included' => array(
			array('id' => '1001', 'type' => 'companies', 'attributes' => array('name' => 'Demo Şirket A.Ş.')),
			array('id' => '7', 'type' => 'profiles', 'attributes' => array()),
		),
	);

	$me = _parasut_me_parse($body);

	pg_assert_same('ayse@example.com', $me['email']);
	pg_assert_same('Ayşe Yılmaz', $me['name']);
	pg_assert_same(array('1001' => 'Demo Şirket A.Ş.', '1002' => ''), $me['companies']);
}

// An answer with no relationships or included records still reads, with no
// companies and empty strings - never a notice.
function test_parasut_me_parse_tolerates_a_bare_answer()
{
	$me = _parasut_me_parse(array('data' => array('id' => '1', 'type' => 'users')));

	pg_assert_same('', $me['email']);
	pg_assert_same('', $me['name']);
	pg_assert_same(array(), $me['companies']);

	$me = _parasut_me_parse(null);

	pg_assert_same(array(), $me['companies']);
}

// Ten requests fit in any ten seconds; the eleventh waits until the oldest
// of the last ten has left the window.
function test_parasut_throttle_wait_keeps_ten_in_ten_seconds()
{
	pg_assert_same(0.0, parasut_throttle_wait(array(), 100.0, 10, 10.0));
	pg_assert_same(0.0, parasut_throttle_wait(array_fill(0, 9, 99.0), 100.0, 10, 10.0));

	$stamps = array(91.0, 92.0, 93.0, 94.0, 95.0, 96.0, 97.0, 98.0, 99.0, 99.5);
	pg_assert_same(1.0, parasut_throttle_wait($stamps, 100.0, 10, 10.0));

	// Requests older than the window do not count.
	pg_assert_same(0.0, parasut_throttle_wait(array(80.0, 85.0, 89.0), 100.0, 3, 10.0));
	pg_assert_same(3.5, parasut_throttle_wait(array(93.5, 95.0, 99.0), 100.0, 3, 10.0));
}

// The card's status line: not connected, or how, as whom, which company and
// since when.
function test_parasut_connection_line()
{
	$none = parasut_connection_line(array('connected' => false));
	pg_assert_same('secondary', $none['tone']);

	$line = parasut_connection_line(array(
		'connected' => true, 'grant' => 'code', 'email' => 'a@example.com',
		'company_name' => 'Deneme Ltd.', 'connected_at' => mktime(17, 39, 0, 10, 19, 2026),
	));
	pg_assert_same('success', $line['tone']);
	pg_assert_contains('a@example.com · Deneme Ltd. · 19.10.2026 17:39', $line['text']);

	$bare = parasut_connection_line(array('connected' => true, 'grant' => 'password', 'email' => '', 'company_name' => '', 'connected_at' => 0));
	pg_assert_same(false, strpos($bare['text'], ' · '));
}

// An empty company box keeps the saved number (the page drawn before Connect
// holds an empty box); another number replaces it.
function test_parasut_company_id_for_save()
{
	pg_assert_same('830533', parasut_company_id_for_save('', '830533'));
	pg_assert_same('830533', parasut_company_id_for_save('  ', '830533'));
	pg_assert_same('830533', parasut_company_id_for_save(null, '830533'));
	pg_assert_same('830533', parasut_company_id_for_save(array('x'), '830533'));
	pg_assert_same('115', parasut_company_id_for_save(' 115 ', '830533'));
	pg_assert_same('', parasut_company_id_for_save('', ''));
}

// The order screen's own Paraşüt buttons give way only when the ERP is on
// and an e-document provider takes its invoices to GİB.
function test_parasut_order_documents_in_erp()
{
	pg_assert_true(_parasut_order_documents_in_erp(true, true));
	pg_assert_false(_parasut_order_documents_in_erp(true, false));
	pg_assert_false(_parasut_order_documents_in_erp(false, true));
	pg_assert_false(_parasut_order_documents_in_erp(false, false));
}

// A refusal reads in Paraşüt's own words: the title before the detail, once
// when Paraşüt repeats the detail as the title (its e-Archive answer).
function test_parasut_extract_error_says_a_repeated_title_once()
{
	$said = 'Bu firmaya ait aktif bir e-Arşiv entegrasyonu bulunmamaktadır.';

	pg_assert_same($said, _parasut_extract_error(array('http_code' => 422, 'body' => array('errors' => array(array('source' => array('pointer' => '/data/attributes/base'), 'title' => $said, 'detail' => $said))))));
	pg_assert_same('Record was not found: SalesInvoice-1', _parasut_extract_error(array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found', 'detail' => 'SalesInvoice-1'))))));
	pg_assert_same('Müşteri/tedarikçi ismi doldurulmalı | Eksik', _parasut_extract_error(array('http_code' => 422, 'body' => array('errors' => array(array('detail' => 'Müşteri/tedarikçi ismi doldurulmalı'), array('title' => 'Eksik'))))));
	pg_assert_same('Geçersiz', _parasut_extract_error(array('http_code' => 422, 'body' => array('errors' => array('Geçersiz')))));
	pg_assert_same('HTTP 500', _parasut_extract_error(array('http_code' => 500, 'body' => null)));
}

// E-Shipment needs the buyer's VKN/TCKN. Under custom_field_1/2 it is the
// order's field, the number the shipment's buyer card is opened with, and the
// refusal names that field as the order screen labels it; under "do not use"
// (the default) and tax_number it is the linked contact's tax_number.
function test_parasut_shipment_identity_refusal_reads_the_configured_field()
{
	$contact_missing = lang('VKN/TCKN is missing from the linked contact. Please update the contact record before creating a document.');
	$order = array('custom_field_1' => '10000000146', 'custom_field_2' => ' 1234567890 ');
	$empty = array('custom_field_1' => '  ', 'custom_field_2' => '');

	pg_assert_same('', _parasut_shipment_identity_refusal('do not use', $empty, '1234567890'));
	pg_assert_same('', _parasut_shipment_identity_refusal('tax_number', $empty, '1234567890'));
	pg_assert_same($contact_missing, _parasut_shipment_identity_refusal('do not use', $order, ''));
	pg_assert_same($contact_missing, _parasut_shipment_identity_refusal('tax_number', $order, ' '));

	pg_assert_same('', _parasut_shipment_identity_refusal('custom_field_1', $order, ''));
	pg_assert_same('', _parasut_shipment_identity_refusal('custom_field_2', $order, ''));

	$custom_1 = _parasut_shipment_identity_refusal('custom_field_1', $empty, '1234567890');
	pg_assert_contains(lang('Custom Field') . ' #1', $custom_1);
	pg_assert_false(strpos($custom_1, 'custom_field_1') !== false);
	pg_assert_false(strpos($custom_1, $contact_missing) !== false);
	pg_assert_contains(lang('Custom Field') . ' #2', _parasut_shipment_identity_refusal('custom_field_2', array(), '1234567890'));
}

// The order screen's E-Shipment check gives the helper the order row and the
// linked contact's tax_number, so each setting is judged by its own field.
function test_parasut_view_order_shipment_check_passes_the_order_and_the_contact_number()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');
	$start = strpos($source, "if (isset(\$_POST['parasut_action']))");
	$end = strpos($source, "if (\$parasut_action === 'invoice')", (int) $start);
	pg_assert_true(($start !== false) && ($end !== false));
	$handler = substr($source, (int) $start, (int) $end - (int) $start);

	pg_assert_contains('_parasut_shipment_identity_refusal(', $handler);
	pg_assert_contains('$parasut_order,', $handler);
	pg_assert_contains('SELECT tax_number FROM contacts', $handler);
}
