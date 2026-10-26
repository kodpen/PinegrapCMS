<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for sending the link of an imported Trendyol package's invoice
 * (mp_trendyol_send_invoice_link() and its checks,
 * includes/api/outbound/connectors/trendyol.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers with a status and a body in
 * the shape of their documentation (seller invoice links, read October 2026),
 * not recorded from a live account.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function trendyol_invoice_test_account($credentials = array())
{
	return array(
		'id'          => 7,
		'provider'    => 'trendyol',
		'credentials' => array_merge(array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
		), $credentials),
	);
}

function trendyol_invoice_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_invoice_test_package($package_id = '3330111111')
{
	return array('remote_order_id' => '10654411111', 'remote_package_id' => $package_id, 'remote_status' => 'Picking');
}

/* --------------------------------------------------------------- the link */

function test_marketplace_trendyol_invoice_link_accepts_a_document_address()
{
	pg_assert_same('', mp_trendyol_invoice_link_problem('https://earsiv.example.com/fatura/ABC2026000000001.pdf'));
	pg_assert_same('', mp_trendyol_invoice_link_problem('http://example.com/f?id=12&k=x'));
	pg_assert_same('', mp_trendyol_invoice_link_problem('  https://example.com  '), 'trimmed');
	pg_assert_same('', mp_trendyol_invoice_link_problem('HTTPS://EXAMPLE.COM/A.PDF'));
}

function test_marketplace_trendyol_invoice_link_refuses_what_is_not_one()
{
	foreach (array('', '   ', 'ABC2026000000001', 'example.com/a.pdf', 'ftp://example.com/a.pdf',
		'javascript:alert(1)', 'https://', 'https:///a.pdf', 'https://exa mple.com/a.pdf',
		'https://example.com/a b.pdf', "https://example.com/a.pdf\nx", 'https://user@example.com/a.pdf',
		'https://example.com/' . str_repeat('a', 1000)) as $url) {

		pg_assert_true(mp_trendyol_invoice_link_problem($url) !== '', var_export($url, true));
	}

	pg_assert_true(mp_trendyol_invoice_link_problem(array('https://example.com')) !== '', 'a posted list');
}

function test_marketplace_trendyol_invoice_link_body_is_the_link_and_a_numeric_package_id()
{
	$body = mp_trendyol_invoice_link_body('3330111111', ' https://example.com/a.pdf ');

	pg_assert_same(array('invoiceLink' => 'https://example.com/a.pdf', 'shipmentPackageId' => 3330111111), $body);
	pg_assert_same('{"invoiceLink":"https://example.com/a.pdf","shipmentPackageId":3330111111}',
		json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/* ----------------------------------------------------------------- the call */

function test_marketplace_trendyol_invoice_connector_names_the_call()
{
	pg_assert_true(mp_can('trendyol', 'send_invoice_link'));
	pg_assert_false(mp_can('n11', 'send_invoice_link'));
}

function test_marketplace_trendyol_invoice_posts_the_link()
{
	$calls = array();

	$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(), trendyol_invoice_test_package(),
		'https://example.com/a.pdf', trendyol_invoice_test_transport(201, '', $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_true($result['data']['sent']);

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/sellers/123456/seller-invoice-links', $calls[0]['url']);
	pg_assert_same('{"invoiceLink":"https://example.com/a.pdf","shipmentPackageId":3330111111}', $calls[0]['options']['body']);
	pg_assert_true(in_array('Content-Type: application/json', $calls[0]['options']['headers'], true));
	pg_assert_true(in_array('User-Agent: 123456 - SelfIntegration', $calls[0]['options']['headers'], true));
}

function test_marketplace_trendyol_invoice_goes_to_stage_for_a_stage_account()
{
	$calls = array();

	$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(array('environment' => 'stage')),
		trendyol_invoice_test_package(), 'https://example.com/a.pdf', trendyol_invoice_test_transport(201, '{}', $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('https://stageapigw.trendyol.com/integration/sellers/123456/seller-invoice-links', $calls[0]['url']);
}

function test_marketplace_trendyol_invoice_refuses_before_the_call()
{
	foreach (array('', '0123', '1234567890123456789', 'abc', '12/34') as $package_id) {

		$calls = array();

		$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(), trendyol_invoice_test_package($package_id),
			'https://example.com/a.pdf', trendyol_invoice_test_transport(201, '', $calls));

		pg_assert_false($result['ok'], $package_id);
		pg_assert_same(0, count($calls), $package_id);
	}

	$calls = array();

	$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(), trendyol_invoice_test_package(),
		'ABC2026000000001', trendyol_invoice_test_transport(201, '', $calls));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_same(0, count($calls), 'an invoice number is not a link');
}

function test_marketplace_trendyol_invoice_conflict_is_named()
{
	$calls = array();

	$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(), trendyol_invoice_test_package(),
		'https://example.com/a.pdf', trendyol_invoice_test_transport(409,
			'{"error":"CONFLICT","message":"Gönderilen ShipmentPackageId\'ye ait bir fatura zaten beslenmiş","status":409}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_true(strpos($result['error'], 'already holds an invoice') !== false, $result['error']);
}

function test_marketplace_trendyol_invoice_bad_request_shows_their_message()
{
	$calls = array();

	$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(), trendyol_invoice_test_package(),
		'https://example.com/a.pdf', trendyol_invoice_test_transport(400,
			'{"error":"BAD_REQUEST","message":"invoiceLink geçersiz","status":400}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_same('invoiceLink geçersiz', $result['error']);
}

function test_marketplace_trendyol_invoice_outage_is_retryable()
{
	$calls = array();

	$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(), trendyol_invoice_test_package(),
		'https://example.com/a.pdf', trendyol_invoice_test_transport(503, '{"message":"down"}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry'], 'a 5xx keeps the shared reader\'s answer');

	$result = mp_trendyol_send_invoice_link(trendyol_invoice_test_account(), trendyol_invoice_test_package(),
		'https://example.com/a.pdf', trendyol_invoice_test_transport(429, '', $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
}
