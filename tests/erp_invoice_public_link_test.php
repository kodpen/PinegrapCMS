<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the public address of an invoice PDF
 * (includes/erp/invoice_public_link.php, erp_invoice.php) and the push of a
 * marketplace order's invoice (includes/api/outbound/invoice_push.php, the
 * n11 invoice link call) that need no database: the token helpers and the
 * refusals, the migration step, the firewall's list, the push plan, the n11
 * envelope and answers from saved fixtures, the card's filled-in forms.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_public_link.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/invoice_push.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/n11.php');
require_once(PG_FUNCTIONS_DIR . '/waf.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function erp_invoice_public_link_test_invoice($token, array $over = array())
{
	return array_merge(array(
		'id' => 5, 'doc_type' => 'invoice', 'status' => 'issued',
		'public_token_hash' => erp_invoice_public_token_hash($token),
	), $over);
}

function erp_invoice_public_link_test_row(array $over = array())
{
	return array_merge(array(
		'id' => 3, 'status' => 'imported', 'order_id' => 12, 'invoice_sent' => 0,
		'remote_status' => 'Picking', 'remote_order_id' => '10654411111',
	), $over);
}

function erp_invoice_public_link_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_erp_invoice_public_token_is_64_hex_and_new_each_time()
{
	$one = erp_invoice_public_token_new();
	$two = erp_invoice_public_token_new();

	pg_assert_true(preg_match('/^[0-9a-f]{64}$/D', $one) === 1, '32 random bytes as hex');
	pg_assert_true($one !== $two, 'a new token each time');
	pg_assert_same(hash('sha256', $one), erp_invoice_public_token_hash($one), 'only the SHA-256 is kept');
}

function test_erp_invoice_public_token_clean_takes_the_pdf_suffix_off()
{
	$token = str_repeat('ab', 32);

	pg_assert_same($token, erp_invoice_public_token_clean($token . '.pdf'), 'the address ends in .pdf');
	pg_assert_same($token, erp_invoice_public_token_clean(strtoupper($token)), 'case');
	pg_assert_same('', erp_invoice_public_token_clean(substr($token, 1)), 'too short');
	pg_assert_same('', erp_invoice_public_token_clean($token . 'x'), 'not hex');
	pg_assert_same('', erp_invoice_public_token_clean(array($token)), 'an array');
	pg_assert_same('', erp_invoice_public_token_clean(''), 'empty');
}

function test_erp_invoice_public_refusal_serves_only_the_right_token_of_a_live_invoice()
{
	$token = erp_invoice_public_token_new();
	$invoice = erp_invoice_public_link_test_invoice($token);

	pg_assert_same('', erp_invoice_public_refusal($invoice, $token . '.pdf'), 'issued, right token');
	pg_assert_same('', erp_invoice_public_refusal(array_merge($invoice, array('status' => 'paid')), $token), 'paid');
	pg_assert_same('token', erp_invoice_public_refusal($invoice, erp_invoice_public_token_new()), 'another token');
	pg_assert_same('token', erp_invoice_public_refusal($invoice, ''), 'no token');
	pg_assert_same('none', erp_invoice_public_refusal(array_merge($invoice, array('public_token_hash' => '')), $token), 'empty hash: no address, or taken back');
	pg_assert_same('none', erp_invoice_public_refusal(array_merge($invoice, array('public_token_hash' => '')), ''), 'empty hash never matches an empty token');
	pg_assert_same('cancelled', erp_invoice_public_refusal(array_merge($invoice, array('status' => 'cancelled')), $token), 'cancelled');
	pg_assert_same('draft', erp_invoice_public_refusal(array_merge($invoice, array('status' => 'draft')), $token), 'draft');
	pg_assert_same('kind', erp_invoice_public_refusal(array_merge($invoice, array('doc_type' => 'return')), $token), 'a return');
	pg_assert_same('missing', erp_invoice_public_refusal(null, $token), 'deleted');
}

function test_erp_invoice_public_link_compares_with_hash_equals_and_never_a_site_secret()
{
	$source = erp_invoice_public_link_test_source('includes/erp/invoice_public_link.php');

	pg_assert_contains('hash_equals(', pg_test_function_source('erp_invoice_public_refusal'), 'constant-time compare');
	pg_assert_contains('random_bytes(32)', pg_test_function_source('erp_invoice_public_token_new'), 'random token');
	pg_assert_false(strpos($source, 'hash_hmac') !== false, 'no HMAC of a site secret');
	pg_assert_contains("public_token_hash = ''", pg_test_function_source('erp_invoice_public_link_revoke'), 'revoked by clearing');
	pg_assert_contains("status NOT IN ('draft', 'cancelled')", pg_test_function_source('erp_invoice_public_link_issue'), 'only an issued invoice');
	pg_assert_contains("'.pdf'", pg_test_function_source('erp_invoice_public_url'), 'the address names a PDF');
}

function test_erp_invoice_public_link_migration_step()
{
	$entry = erp_invoice_public_link_test_source('includes/migrations/2026.11.0.php');
	$start = strpos($entry, 'function upgrade_2026_11_0_erp_invoice_public_link()');
	$step = ($start === false) ? '' : substr($entry, $start, strpos($entry, "\n}\n", $start) - $start);

	pg_assert_contains("install_add_column('erp_invoices', 'public_token_hash', \"CHAR(64) NOT NULL DEFAULT ''\")", $step, 'the column');
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no bare ALTER');
	pg_assert_contains('upgrade_2026_11_0_erp_invoice_public_link(); // 11.0.65', $entry, 'called with its label');
}

function test_erp_invoice_public_page_is_a_sensitive_script_and_answers_404()
{
	$page = erp_invoice_public_link_test_source('erp_invoice.php');

	pg_assert_true(in_array('erp_invoice.php', waf_sensitive_scripts(), true), 'rate limited as a token page');
	pg_assert_contains('X-Robots-Tag: noindex', $page, 'noindex');
	pg_assert_contains('http_response_code(404)', $page, '404');
	pg_assert_contains('erp_invoice_public_for_request(', $page, 'read by id and token');
	pg_assert_contains('erp_archive_invoice(', $page, 'the archived PDF');
	pg_assert_contains('erp_archive_send(', $page, 'streamed');
}

function test_mp_invoice_push_plan_runs_only_with_the_switch_on()
{
	$can = array('send_invoice_link' => true, 'mark_invoiced' => true);
	$row = erp_invoice_public_link_test_row();

	pg_assert_same(array('link' => true, 'invoiced' => true), mp_invoice_push_plan(true, $row, 'open', $can, 'GIB2026000000001'), 'both');
	pg_assert_same(array('link' => false, 'invoiced' => false), mp_invoice_push_plan(false, $row, 'open', $can, 'GIB2026000000001'), 'switch off: nothing');
	pg_assert_same(array('link' => false, 'invoiced' => false), mp_invoice_push_plan(true, $row, 'open', $can, ''), 'no invoice');
	pg_assert_same(array('link' => false, 'invoiced' => false), mp_invoice_push_plan(true, $row, 'cancelled', $can, 'GIB2026000000001'), 'cancelled package');
	pg_assert_same(array('link' => false, 'invoiced' => false), mp_invoice_push_plan(true, erp_invoice_public_link_test_row(array('status' => 'failed')), 'open', $can, 'X'), 'not imported');
}

function test_mp_invoice_push_plan_steps()
{
	$can = array('send_invoice_link' => true, 'mark_invoiced' => true);

	pg_assert_same(array('link' => false, 'invoiced' => true),
		mp_invoice_push_plan(true, erp_invoice_public_link_test_row(array('invoice_sent' => 1700000000)), 'open', $can, 'N'), 'link sent already');
	pg_assert_same(array('link' => true, 'invoiced' => false),
		mp_invoice_push_plan(true, erp_invoice_public_link_test_row(array('remote_status' => 'Created')), 'open', $can, 'N'), 'not accepted: no Invoiced, never an accept');
	pg_assert_same(array('link' => true, 'invoiced' => false),
		mp_invoice_push_plan(true, erp_invoice_public_link_test_row(array('remote_status' => 'Invoiced')), 'open', $can, 'N'), 'Invoiced already');
	pg_assert_same(array('link' => true, 'invoiced' => false),
		mp_invoice_push_plan(true, erp_invoice_public_link_test_row(), 'shipped', $can, 'N'), 'shipped: link still goes');
	pg_assert_same(array('link' => true, 'invoiced' => false),
		mp_invoice_push_plan(true, erp_invoice_public_link_test_row(), 'open', array('send_invoice_link' => true), 'N'), 'n11: link only');
}

function test_mp_invoice_push_pending_marks_the_row_for_retry()
{
	$row = erp_invoice_public_link_test_row();
	$invoice = array('id' => 5, 'full_number' => 'GIB2026000000001');

	pg_assert_true(mp_invoice_push_pending(true, $row, $invoice, true), 'invoiced, link not gone');
	pg_assert_false(mp_invoice_push_pending(false, $row, $invoice, true), 'switch off');
	pg_assert_false(mp_invoice_push_pending(true, $row, null, true), 'no invoice');
	pg_assert_false(mp_invoice_push_pending(true, erp_invoice_public_link_test_row(array('invoice_sent' => 1)), $invoice, true), 'sent');
}

function test_mp_invoice_push_is_called_after_the_automatic_invoice_and_records_failures()
{
	$file = erp_invoice_public_link_test_source('includes/fn/ecommerce.php');
	$start = strpos($file, 'function pg_erp_auto_invoice_run(');
	$run = ($start === false) ? '' : substr($file, $start, strpos($file, "\n}\n", $start) - $start);
	$push = pg_test_function_source('mp_invoice_push_order');
	$link = pg_test_function_source('mp_invoice_push_link');

	pg_assert_contains("=== 'marketplace'", $run, 'only a marketplace order');
	pg_assert_contains('mp_invoice_push_order(', $run, 'after the invoice');
	pg_assert_contains('mp_invoice_push_switch_on()', $push, 'behind the switch');
	pg_assert_contains('log_activity(', $link, 'a failure is logged');
	pg_assert_false(strpos($push . $link, 'last_error') !== false, 'never last_error');
	pg_assert_contains('ERP_AUTO_INVOICE_MARKETPLACE', pg_test_function_source('mp_invoice_push_switch_on'), 'the switch');
}

function test_mp_n11_invoice_link_problem()
{
	pg_assert_same('', mp_n11_invoice_link_problem('https://shop.example/pinegrap/erp_invoice.php?i=5&t=' . str_repeat('a', 64) . '.pdf'), 'our address');
	pg_assert_true(mp_n11_invoice_link_problem('http://shop.example/a.pdf') !== '', 'not https');
	pg_assert_true(mp_n11_invoice_link_problem('https://shop.example/invoice') !== '', 'not named as a file');
	pg_assert_true(mp_n11_invoice_link_problem('https://shop.example/' . str_repeat('a', 2048) . '.pdf') !== '', 'too long');
	pg_assert_true(mp_n11_invoice_link_problem('') !== '', 'empty');
}

function test_mp_n11_invoice_number_fits()
{
	pg_assert_true(mp_n11_invoice_number_fits('N112026000000001'), 'their example');
	pg_assert_false(mp_n11_invoice_number_fits('INV-2026-1'), 'a free number');
	pg_assert_false(mp_n11_invoice_number_fits('ABC2019000000001'), 'year before 2020');
}

function test_mp_n11_invoice_link_envelope()
{
	$account = array('credentials' => array('app_key' => 'k&1', 'app_secret' => 's<2'));

	$plain = mp_n11_invoice_link_envelope($account, '202665912318', 'https://a.example/x.pdf?a=1&b=2', 'INV-1', '2026-10-30');
	pg_assert_contains('<sch:SaveLinkSellerInvoiceRequest>', $plain, 'the operation');
	pg_assert_contains('<appKey>k&amp;1</appKey>', $plain, 'escaped key');
	pg_assert_contains('<url>https://a.example/x.pdf?a=1&amp;b=2</url>', $plain, 'escaped url');
	pg_assert_contains('<orderNumber>202665912318</orderNumber>', $plain, 'order number');
	pg_assert_false(strpos($plain, 'invoiceNumber') !== false, 'a number of another shape is left out');

	$full = mp_n11_invoice_link_envelope($account, '1', 'https://a.example/x.pdf', 'N112026000000001', '2026-10-30');
	pg_assert_contains('<invoiceNumber>N112026000000001</invoiceNumber>', $full, 'their shape goes');
	pg_assert_contains('<invoiceDateTime>1793307600000</invoiceDateTime>', $full, 'start of the day in GMT+3, milliseconds');
}

function test_mp_n11_send_invoice_link_reads_their_answers()
{
	$account = array('credentials' => array('app_key' => 'k', 'app_secret' => 's'));
	$package = array('remote_order_id' => '202665912318', 'invoice_number' => 'N112026000000001', 'invoice_date' => '2026-10-30');
	$url = 'https://shop.example/erp_invoice.php?i=5&t=' . str_repeat('a', 64) . '.pdf';
	$calls = array();
	$answer = function ($status, $body) use (&$calls) {
		return function ($method, $address, $options) use ($status, $body, &$calls) {
			$calls[] = array($method, $address, $options);

			return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body, 'error' => '', 'duration_ms' => 1);
		};
	};

	$success = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body><ns3:SaveLinkSellerInvoiceResponse xmlns:ns3="http://www.n11.com/ws/schemas"><result><status>success</status></result><url>x</url><orderNumber>202665912318</orderNumber></ns3:SaveLinkSellerInvoiceResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>';
	$result = mp_n11_send_invoice_link($account, $package, $url, $answer(200, $success));
	pg_assert_true($result['ok'], 'success');
	pg_assert_same('POST', $calls[0][0], 'POST');
	pg_assert_same('https://api.n11.com/ws/sellerInvoiceService/', $calls[0][1], 'the WSDL address');
	pg_assert_contains('Content-Type: text/xml; charset=utf-8', implode("\n", $calls[0][2]['headers']), 'SOAP 1.1');

	$failure = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body><ns3:SaveLinkSellerInvoiceResponse xmlns:ns3="http://www.n11.com/ws/schemas"><result><status>failure</status><errorCode>x</errorCode><errorMessage>Sipariş bulunamadı</errorMessage></result></ns3:SaveLinkSellerInvoiceResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>';
	$result = mp_n11_send_invoice_link($account, $package, $url, $answer(200, $failure));
	pg_assert_false($result['ok'], 'failure');
	pg_assert_same('Sipariş bulunamadı', $result['error'], 'their message');

	$fault = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultcode>soap:Server</faultcode><faultstring>Authentication failed</faultstring></soap:Fault></soap:Body></soap:Envelope>';
	$result = mp_n11_send_invoice_link($account, $package, $url, $answer(500, $fault));
	pg_assert_false($result['ok'], 'fault');
	pg_assert_same('Authentication failed', $result['error'], 'fault string');

	$count = count($calls);
	$result = mp_n11_send_invoice_link($account, $package, 'http://shop.example/a.pdf', $answer(200, $success));
	pg_assert_false($result['ok'], 'refused before the call');
	pg_assert_same($count, count($calls), 'no call');

	pg_assert_same('mp_n11_send_invoice_link', mp_n11_connector()['send_invoice_link'], 'the connector offers it');
}

function test_mp_settings_invoice_forms_come_filled_in()
{
	$screen = erp_invoice_public_link_test_source('marketplace_settings.php');
	$cut = function ($name) use ($screen) {
		$start = strpos($screen, 'function ' . $name . '(');

		return ($start === false) ? '' : substr($screen, $start, strpos($screen, "\n}\n", $start) - $start);
	};
	$form = $cut('mp_settings_invoice_form');
	$invoiced = $cut('mp_settings_invoiced_form');

	pg_assert_contains('name="use_public" value="1"', $form, 'the public address, ticked');
	pg_assert_contains('checked', $form, 'ticked by default');
	pg_assert_contains("\$invoice['full_number']", $invoiced, 'the invoice number filled in');
	pg_assert_contains('mp_invoice_push_link($account, $package, $invoice, false)', $screen, 'the send makes the address');
	pg_assert_contains('mp_invoice_push_pending(', $screen, 'the row is marked');
}
