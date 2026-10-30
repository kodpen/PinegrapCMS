<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for marking an imported Trendyol package Invoiced with the operator's
 * invoice number (mp_trendyol_mark_invoiced() and its checks,
 * includes/api/outbound/connectors/trendyol.php) and for which rows of the
 * Imported orders card offer it (mp_invoiced_offer(),
 * includes/api/outbound/orders.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers with a status and a body in
 * the shape of their documentation (package status update, read October
 * 2026), not recorded from a live account.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');

function trendyol_invoiced_test_account($credentials = array())
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

function trendyol_invoiced_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_invoiced_test_package($package_id = '3330111111')
{
	return array(
		'remote_order_id'   => '10654411111',
		'remote_package_id' => $package_id,
		'remote_status'     => 'Picking',
		'lines'             => array(
			array('remote_line_id' => '56040534', 'quantity' => 2),
			array('remote_line_id' => '56040535', 'quantity' => 1),
		),
	);
}

/* ----------------------------------------------------------- the number */

function test_marketplace_trendyol_invoiced_number_accepts_invoice_numbers()
{
	pg_assert_same('', mp_trendyol_invoice_number_problem('EME2018000025208'));
	pg_assert_same('', mp_trendyol_invoice_number_problem('  ABC2026000000001  '), 'trimmed');
	pg_assert_same('', mp_trendyol_invoice_number_problem('A-123456'));
	pg_assert_same('', mp_trendyol_invoice_number_problem(str_repeat('9', 50)));
}

function test_marketplace_trendyol_invoiced_number_refuses_what_is_not_one()
{
	foreach (array('', '   ', '-123', 'ABC 2026', 'https://example.com/a.pdf', 'A/123', "A12\n3",
		str_repeat('9', 51), 'FATURA№1') as $number) {

		pg_assert_true(mp_trendyol_invoice_number_problem($number) !== '', var_export($number, true));
	}

	pg_assert_true(mp_trendyol_invoice_number_problem(array('EME2018000025208')) !== '', 'a posted list');
}

/* ------------------------------------------------------------- the body */

function test_marketplace_trendyol_invoiced_body_is_lines_params_and_status()
{
	$body = mp_trendyol_invoiced_body(trendyol_invoiced_test_package(), ' EME2018000025208 ');

	pg_assert_same(array(
		'lines'  => array(array('lineId' => 56040534, 'quantity' => 2), array('lineId' => 56040535, 'quantity' => 1)),
		'params' => array('invoiceNumber' => 'EME2018000025208'),
		'status' => 'Invoiced',
	), $body);

	pg_assert_same('{"lines":[{"lineId":56040534,"quantity":2},{"lineId":56040535,"quantity":1}],'
		. '"params":{"invoiceNumber":"EME2018000025208"},"status":"Invoiced"}', json_encode($body));
}

function test_marketplace_trendyol_invoiced_body_drops_ids_that_do_not_fit()
{
	$package = trendyol_invoiced_test_package();
	$package['lines'] = array(
		array('remote_line_id' => '0123', 'quantity' => 1),
		array('remote_line_id' => '1234567890123456789', 'quantity' => 1),
		array('remote_line_id' => '', 'quantity' => 1),
		array('remote_line_id' => '77', 'quantity' => 0),
	);

	pg_assert_same(array(array('lineId' => 77, 'quantity' => 1)), mp_trendyol_invoiced_body($package, 'X1')['lines']);
}

/* ------------------------------------------------------------- the call */

function test_marketplace_trendyol_invoiced_connector_names_the_call()
{
	pg_assert_true(mp_can('trendyol', 'mark_invoiced'));
	pg_assert_false(mp_can('n11', 'mark_invoiced'));
}

function test_marketplace_trendyol_invoiced_puts_the_status()
{
	$calls = array();

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), trendyol_invoiced_test_package(),
		'EME2018000025208', trendyol_invoiced_test_transport(200, '', $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_true($result['data']['invoiced']);

	pg_assert_same(1, count($calls));
	pg_assert_same('PUT', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/sellers/123456/shipment-packages/3330111111', $calls[0]['url']);
	pg_assert_contains('"params":{"invoiceNumber":"EME2018000025208"},"status":"Invoiced"', $calls[0]['options']['body']);
	pg_assert_true(in_array('User-Agent: 123456 - SelfIntegration', $calls[0]['options']['headers'], true));
}

function test_marketplace_trendyol_invoiced_goes_to_stage_for_a_stage_account()
{
	$calls = array();

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(array('environment' => 'stage')),
		trendyol_invoiced_test_package(), 'EME2018000025208', trendyol_invoiced_test_transport(200, '{}', $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same('https://stageapigw.trendyol.com/integration/order/sellers/123456/shipment-packages/3330111111', $calls[0]['url']);
}

function test_marketplace_trendyol_invoiced_refuses_before_the_call()
{
	foreach (array('', '0123', '1234567890123456789', 'abc', '12/34') as $package_id) {

		$calls = array();

		$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), trendyol_invoiced_test_package($package_id),
			'EME2018000025208', trendyol_invoiced_test_transport(200, '', $calls));

		pg_assert_false($result['ok'], $package_id);
		pg_assert_same(0, count($calls), $package_id);
	}

	$calls = array();

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), trendyol_invoiced_test_package(),
		'https://example.com/a.pdf', trendyol_invoiced_test_transport(200, '', $calls));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_same(0, count($calls), 'a link is not an invoice number');

	$package = trendyol_invoiced_test_package();
	$package['lines'] = array();

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), $package,
		'EME2018000025208', trendyol_invoiced_test_transport(200, '', $calls));

	pg_assert_false($result['ok']);
	pg_assert_same(0, count($calls), 'no lines, no call');
}

function test_marketplace_trendyol_invoiced_bad_request_shows_their_message()
{
	$calls = array();

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), trendyol_invoiced_test_package(),
		'EME2018000025208', trendyol_invoiced_test_transport(400,
			'{"error":"BAD_REQUEST","message":"Paket statüsü Invoiced olarak güncellenemez","status":400}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_same('Paket statüsü Invoiced olarak güncellenemez', $result['error']);

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), trendyol_invoiced_test_package(),
		'EME2018000025208', trendyol_invoiced_test_transport(400,
			'{"errors":[{"message":"lineId hatalı"}]}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('lineId hatalı', $result['error']);
}

function test_marketplace_trendyol_invoiced_outage_is_retryable()
{
	$calls = array();

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), trendyol_invoiced_test_package(),
		'EME2018000025208', trendyol_invoiced_test_transport(503, '{"message":"down"}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);

	$result = mp_trendyol_mark_invoiced(trendyol_invoiced_test_account(), trendyol_invoiced_test_package(),
		'EME2018000025208', trendyol_invoiced_test_transport(429, '{"message":"too.many.requests"}', $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
}

/* ------------------------------------------------------------ the offer */

function test_marketplace_invoiced_offer_rows()
{
	$row = array('status' => 'imported', 'order_id' => 12, 'remote_status' => 'Picking');

	pg_assert_true(mp_invoiced_offer($row, 'open'));
	pg_assert_true(mp_invoiced_offer(array_merge($row, array('remote_status' => 'Created')), 'open'));

	pg_assert_false(mp_invoiced_offer(array_merge($row, array('remote_status' => 'Invoiced')), 'open'), 'already invoiced');
	pg_assert_false(mp_invoiced_offer(array_merge($row, array('status' => 'failed')), 'open'), 'failed import');
	pg_assert_false(mp_invoiced_offer(array_merge($row, array('order_id' => 0)), 'open'), 'no order');
	pg_assert_false(mp_invoiced_offer($row, 'cancelled'));
	pg_assert_false(mp_invoiced_offer($row, 'shipped'));
	pg_assert_false(mp_invoiced_offer($row, 'delivered'));
}

function test_marketplace_settings_draws_the_invoiced_form_behind_the_capability()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	pg_assert_contains("\$can_mark_invoiced = \$can_orders && mp_can(\$selected['provider'], 'mark_invoiced');", $source);
	pg_assert_contains("(\$can_mark_invoiced && mp_invoiced_offer(\$row, mp_order_stage(\$selected, \$row)))", $source);
	pg_assert_contains("\$action === 'mark_invoiced'", $source);
	pg_assert_contains("!mp_invoiced_offer(\$map, mp_order_stage(\$account, \$map))", $source);
}
