<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping label Trendyol makes for its own carrier's parcel
 * (the common label: mp_trendyol_request_common_label(),
 * mp_trendyol_common_label() and their checks,
 * includes/api/outbound/connectors/trendyol.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers with saved bodies in the
 * shape of their documentation (createCommonLabel and getCommonLabel, read
 * October 2026), not recorded from a live account.
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

function trendyol_common_label_test_account($credentials = array())
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

function trendyol_common_label_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/' . $name);
}

function trendyol_common_label_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

/* ------------------------------------------------------------ the contract */

function test_marketplace_trendyol_common_label_connector_names_the_calls()
{
	foreach (array('request_label', 'read_label', 'label_carrier') as $capability) {
		pg_assert_true(mp_can('trendyol', $capability), $capability);
		pg_assert_false(mp_can('n11', $capability), 'n11: ' . $capability);
	}
}

function test_marketplace_trendyol_common_label_cargo_number_shape()
{
	foreach (array('7280027504111111', '72800', 'ABC1234567', str_repeat('7', 40)) as $number) {
		pg_assert_true(mp_trendyol_cargo_number_fits($number), $number);
	}

	foreach (array('', '7280', str_repeat('7', 41), '7280027504/111111', '7280027504 111111', '7280027504-111111',
		'../7280027504', "7280027504111111\n", 7280027504111111, array('7280027504111111')) as $number) {
		pg_assert_false(mp_trendyol_cargo_number_fits($number), var_export($number, true));
	}
}

function test_marketplace_trendyol_common_label_body_is_zpl_and_the_boxes()
{
	pg_assert_same('{"format":"ZPL","boxQuantity":2}',
		json_encode(mp_trendyol_common_label_body(2), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function test_marketplace_trendyol_common_label_carriers_it_labels()
{
	foreach (array('Trendyol Express Marketplace', 'Trendyol Express', 'TRENDYOL EXPRESS', 'TEX', 'Aras Kargo Marketplace',
		'ARAS KARGO', 'aras', '', '   ') as $name) {
		pg_assert_true(mp_trendyol_common_label_carrier($name), var_export($name, true));
	}

	foreach (array('Yurtiçi Kargo Marketplace', 'YURTİÇİ KARGO', 'MNG Kargo Marketplace', 'Sürat Kargo Marketplace',
		'PTT Kargo Marketplace', 'Horoz Lojistik', 'Kolay Gelsin Marketplace', 'DHL eCommerce Marketplace', 'Ceva MP',
		'Texas Freight') as $name) {
		pg_assert_false(mp_trendyol_common_label_carrier($name), $name);
	}

	pg_assert_true(mp_trendyol_common_label_carrier(array('Yurtiçi')), 'not a name: their answer decides');
}

/* -------------------------------------------------------------- the request */

function test_marketplace_trendyol_common_label_request_posts_the_boxes()
{
	$calls = array();

	$result = mp_trendyol_request_common_label(trendyol_common_label_test_account(), ' 7280027504111111 ', 2,
		trendyol_common_label_test_transport(200, '', $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_true($result['data']['requested']);

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/sellers/123456/common-label/7280027504111111', $calls[0]['url']);
	pg_assert_same('{"format":"ZPL","boxQuantity":2}', $calls[0]['options']['body']);
	pg_assert_true(in_array('Content-Type: application/json', $calls[0]['options']['headers'], true));
	pg_assert_true(in_array('User-Agent: 123456 - SelfIntegration', $calls[0]['options']['headers'], true));
}

function test_marketplace_trendyol_common_label_request_goes_to_stage_for_a_stage_account()
{
	$calls = array();

	mp_trendyol_request_common_label(trendyol_common_label_test_account(array('environment' => 'stage')), '7280027504111111', 1,
		trendyol_common_label_test_transport(200, '', $calls));

	pg_assert_same('https://stageapigw.trendyol.com/integration/sellers/123456/common-label/7280027504111111', $calls[0]['url']);
}

function test_marketplace_trendyol_common_label_request_refuses_before_the_call()
{
	$calls = array();

	foreach (array('', '72800/1', '7280027504111111?x=1', null) as $number) {
		$result = mp_trendyol_request_common_label(trendyol_common_label_test_account(), $number, 1,
			trendyol_common_label_test_transport(200, '', $calls));

		pg_assert_false($result['ok'], var_export($number, true));
	}

	foreach (array(0, -1, '2', 1.5) as $boxes) {
		$result = mp_trendyol_request_common_label(trendyol_common_label_test_account(), '7280027504111111', $boxes,
			trendyol_common_label_test_transport(200, '', $calls));

		pg_assert_false($result['ok'], var_export($boxes, true));
	}

	pg_assert_same(0, count($calls), 'nothing was sent');
}

function test_marketplace_trendyol_common_label_request_refusal_reads_their_title()
{
	$calls = array();

	$result = mp_trendyol_request_common_label(trendyol_common_label_test_account(), '7280027504111111', 1,
		trendyol_common_label_test_transport(400, trendyol_common_label_test_fixture('common-label-error-400.json'), $calls));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry'], 'a refusal is not tried again');
	pg_assert_same('İletmiş olduğunuz kargo takip numarası değerini kontrol ediniz', $result['error']);

	$result = mp_trendyol_request_common_label(trendyol_common_label_test_account(), '7280027504111111', 1,
		trendyol_common_label_test_transport(404, trendyol_common_label_test_fixture('common-label-error-404.json'), $calls));

	pg_assert_same('Kargo takip numarasına ait gönderi bulunamamıştır', $result['error']);
}

function test_marketplace_trendyol_common_label_request_outage_is_worth_repeating()
{
	$calls = array();

	pg_assert_true(mp_trendyol_request_common_label(trendyol_common_label_test_account(), '7280027504111111', 1,
		trendyol_common_label_test_transport(429, '', $calls))['retry'], 'rate limit');

	pg_assert_true(mp_trendyol_request_common_label(trendyol_common_label_test_account(), '7280027504111111', 1,
		trendyol_common_label_test_transport(503, '', $calls))['retry'], 'their outage');
}

/* ----------------------------------------------------------------- the read */

function test_marketplace_trendyol_common_label_read_one_box()
{
	$calls = array();

	$result = mp_trendyol_common_label(trendyol_common_label_test_account(), '7280027504111111',
		trendyol_common_label_test_transport(200, trendyol_common_label_test_fixture('common-label-one-box.json'), $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('zpl', $result['data']['format']);
	pg_assert_same(1, $result['data']['count']);
	pg_assert_same(0, strpos($result['data']['label'], '^XA^CI28'));
	pg_assert_contains('^FD7280027504111111^FS', $result['data']['label']);
	pg_assert_true(pg_label_document_valid($result['data']['label'], 'zpl'));

	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/sellers/123456/common-label/7280027504111111', $calls[0]['url']);
	pg_assert_false(isset($calls[0]['options']['body']), 'a read sends no body');
}

function test_marketplace_trendyol_common_label_read_every_box_in_one_file()
{
	$calls = array();

	$result = mp_trendyol_common_label(trendyol_common_label_test_account(), '7280027504111144',
		trendyol_common_label_test_transport(200, trendyol_common_label_test_fixture('common-label-two-boxes.json'), $calls));

	pg_assert_same(2, $result['data']['count']);
	pg_assert_same(2, substr_count($result['data']['label'], '^XA'), 'both labels');
	pg_assert_true(strpos($result['data']['label'], 'Koli 1/2') < strpos($result['data']['label'], 'Koli 2/2'), 'in their order');
	pg_assert_contains("^XZ\n^XA", $result['data']['label'], 'one after the other');
}

function test_marketplace_trendyol_common_label_read_not_made_yet_is_empty()
{
	$calls = array();

	$result = mp_trendyol_common_label(trendyol_common_label_test_account(), '7280027504111111',
		trendyol_common_label_test_transport(200, trendyol_common_label_test_fixture('common-label-empty.json'), $calls));

	pg_assert_true($result['ok'], 'not an error');
	pg_assert_same('', $result['data']['label']);
	pg_assert_same(0, $result['data']['count']);
}

function test_marketplace_trendyol_common_label_read_takes_only_the_parcel_label()
{
	$labels = mp_trendyol_common_label_documents(json_decode(trendyol_common_label_test_fixture('common-label-with-product-labels.json'), true));

	pg_assert_same(1, count($labels), 'the product labels are left out');
	pg_assert_contains('^FD1022069492243^FS', $labels[0]);

	pg_assert_same(array(), mp_trendyol_common_label_documents(array('data' => array(
		array('label' => 'https://cdn.example.com/label.pdf', 'format' => 'PDF'),
		array('label' => 'not a label', 'format' => 'ZPL'),
		array('label' => array('^XA^XZ')),
		'^XA^XZ',
	))), 'nothing that is not a ZPL label');

	pg_assert_same(array(), mp_trendyol_common_label_documents(null));
	pg_assert_same(array(), mp_trendyol_common_label_documents(array('data' => 'x')));
}

function test_marketplace_trendyol_common_label_read_refusal_reads_their_title()
{
	$calls = array();

	$result = mp_trendyol_common_label(trendyol_common_label_test_account(), '7280027504111111',
		trendyol_common_label_test_transport(400, trendyol_common_label_test_fixture('common-label-error-400.json'), $calls));

	pg_assert_false($result['ok']);
	pg_assert_same('İletmiş olduğunuz kargo takip numarası değerini kontrol ediniz', $result['error']);

	$calls = array();

	pg_assert_false(mp_trendyol_common_label(trendyol_common_label_test_account(), '7280/1',
		trendyol_common_label_test_transport(200, '{}', $calls))['ok']);
	pg_assert_same(0, count($calls), 'no call for a number that cannot be one');
}

/* ------------------------------------------------------- the error envelope */

function test_marketplace_trendyol_common_label_error_envelope_one_level_down()
{
	pg_assert_same('Kargo takip numarasına ait gönderi bulunamamıştır',
		mp_trendyol_error_message(json_decode(trendyol_common_label_test_fixture('common-label-error-404.json'), true)));

	pg_assert_same('BadRequestException', mp_trendyol_error_message(array('error' => array('exception' => 'BadRequestException'))),
		'the same reader one level down');

	// The other envelopes read as before.
	pg_assert_same('Kategori bulunamadı', mp_trendyol_error_message(json_decode(trendyol_common_label_test_fixture('error-400.json'), true)));
	pg_assert_same('', mp_trendyol_error_message(array('error' => 'Conflict', 'message' => 'x', 'status' => 409)),
		'the invoice service\'s envelope is still read by its own call');
	pg_assert_same('', mp_trendyol_error_message(array('data' => array(array('label' => '^XA^XZ', 'format' => 'ZPL')))),
		'a label is not an error');
}
