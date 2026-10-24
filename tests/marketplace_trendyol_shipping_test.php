<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for shipping a Trendyol package with the shop's own carrier: the
 * account's choice (a select field of the provider), the alternative delivery
 * call of the connector (includes/api/outbound/connectors/trendyol.php), and
 * which recorded parcel's tracking link the job sends
 * (mp_sync_shipment_link(), includes/api/outbound/sync.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers with a status and a body in
 * the shape of their documentation (October 2026), not recorded from a live
 * account.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The outbound files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function trendyol_shipping_test_account($credentials = array())
{
	return array(
		'id'          => 7,
		'provider'    => 'trendyol',
		'credentials' => array_merge(array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
			'delivery'    => 'own',
		), $credentials),
	);
}

// A transport that answers every call with $status and $body and writes each
// call it was given into $calls.
function trendyol_shipping_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_shipping_test_package($package_id = '3330111111')
{
	return array(
		'remote_order_id'   => '10654411111',
		'remote_package_id' => $package_id,
		'remote_status'     => 'Picking',
		'lines'             => array(array('remote_line_id' => '4765111111', 'quantity' => 3)),
	);
}

// A parcel row in the shape pg_shipment_rows() answers with.
function trendyol_shipping_test_row($overrides = array())
{
	return array_merge(array(
		'id'             => 1,
		'number'         => '123456789012',
		'carrier'        => '',
		'tracking_url'   => '',
		'status'         => 'created',
		'status_at'      => 0,
		'shipped_at'     => 0,
		'delivered_at'   => 0,
		'source'         => 'panel',
		'format_warning' => 0,
	), $overrides);
}

/* ---------------------------------------------------- the account's choice */

function test_marketplace_trendyol_shipping_choice_is_a_select_that_defaults_to_trendyol()
{
	$providers = mp_providers();

	$field = $providers['trendyol']['fields']['delivery'];

	pg_assert_true(mp_provider_field_is_select($field));
	pg_assert_same(array('trendyol', 'own'), array_keys($field['options']));
	pg_assert_same('trendyol', $field['default']);

	pg_assert_same('own', mp_provider_credentials('trendyol', array('delivery' => 'own'))['delivery']);
	pg_assert_same('trendyol', mp_provider_credentials('trendyol', array('delivery' => 'someone-else'))['delivery'],
		'an unknown answer is the default');
	pg_assert_false(isset($providers['n11']['fields']['delivery']), 'n11 ships with its own carriers only');
}

function test_marketplace_trendyol_shipping_account_ships_itself_only_when_it_says_so()
{
	pg_assert_true(mp_account_ships_itself(trendyol_shipping_test_account()));
	pg_assert_false(mp_account_ships_itself(trendyol_shipping_test_account(array('delivery' => 'trendyol'))));
	pg_assert_false(mp_account_ships_itself(array('provider' => 'trendyol', 'credentials' => array())),
		'an account saved before the choice existed ships with Trendyol');
	pg_assert_false(mp_account_ships_itself(array('provider' => 'trendyol')));
	pg_assert_false(mp_account_ships_itself(trendyol_shipping_test_account(array('delivery' => 'OWN'))), 'the stored word exactly');
}

/* ----------------------------------------------------------- the call */

function test_marketplace_trendyol_shipping_connector_names_the_call()
{
	pg_assert_true(mp_can('trendyol', 'ship_package'));
	pg_assert_false(mp_can('n11', 'ship_package'));
}

function test_marketplace_trendyol_shipping_puts_the_tracking_link()
{
	$calls = array();
	$link = 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=123456789012';

	$result = mp_trendyol_ship_package(trendyol_shipping_test_account(), trendyol_shipping_test_package(), $link,
		trendyol_shipping_test_transport(200, '', $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_true($result['data']['shipped']);

	pg_assert_same(1, count($calls));
	pg_assert_same('PUT', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/sellers/123456/shipment-packages/3330111111/alternative-delivery',
		$calls[0]['url']);
	pg_assert_same('{"isPhoneNumber":false,"trackingInfo":"' . $link . '","params":{}}', $calls[0]['options']['body'],
		'a link, not a phone number; params is an object');
	pg_assert_contains('Content-Type: application/json', $calls[0]['options']['headers']);
}

function test_marketplace_trendyol_shipping_refuses_before_any_call()
{
	$calls = array();
	$transport = trendyol_shipping_test_transport(200, '', $calls);
	$link = 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code=1';

	foreach (array('', 'abc', '../3330111111', '1234567890123456789') as $package_id) {
		$result = mp_trendyol_ship_package(trendyol_shipping_test_account(), trendyol_shipping_test_package($package_id), $link, $transport);

		pg_assert_false($result['ok'], 'package ' . $package_id);
		pg_assert_contains('no package number', $result['error']);
	}

	foreach (array('', '123456789012', 'ftp://example.com/x', 'javascript:alert(1)', 'https://') as $tracking) {
		$result = mp_trendyol_ship_package(trendyol_shipping_test_account(), trendyol_shipping_test_package(), $tracking, $transport);

		pg_assert_false($result['ok'], 'tracking ' . $tracking);
		pg_assert_contains('http://', $result['error']);
	}

	pg_assert_same(0, count($calls), 'nothing was sent');
}

function test_marketplace_trendyol_shipping_passes_their_answer_on()
{
	$calls = array();
	$link = 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code=1';

	$refused = mp_trendyol_ship_package(trendyol_shipping_test_account(), trendyol_shipping_test_package(), $link,
		trendyol_shipping_test_transport(400, '{"errors":[{"message":"Paket statüsü uygun değil"}]}', $calls));

	pg_assert_false($refused['ok']);
	pg_assert_false($refused['retry'], 'a refusal is not asked again');
	pg_assert_same('Paket statüsü uygun değil', $refused['error']);

	$down = mp_trendyol_ship_package(trendyol_shipping_test_account(), trendyol_shipping_test_package(), $link,
		trendyol_shipping_test_transport(503, '<html></html>', $calls));

	pg_assert_false($down['ok']);
	pg_assert_true($down['retry'], 'their outage is asked again on the next pass');
}

/* ----------------------------------------------------- which parcel */

// Trendyol's own cargo number arrives with the package and is recorded with
// source marketplace; sending it back as the seller's carrier would be wrong.
function test_marketplace_trendyol_shipping_link_never_sends_their_own_number_back()
{
	$theirs = trendyol_shipping_test_row(array('number' => '7280027504111111', 'source' => 'marketplace',
		'tracking_url' => 'https://tracking.trendyol.com/?id=1'));

	pg_assert_same(null, mp_sync_shipment_link(array($theirs), 'Trendyol Express', 'yurtici'));

	$ours = trendyol_shipping_test_row(array('id' => 2, 'number' => '123456789012', 'source' => 'panel'));

	pg_assert_same(array('number' => '123456789012',
		'url' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=123456789012'),
		mp_sync_shipment_link(array($theirs, $ours), 'Trendyol Express', 'yurtici'), 'the shop\'s parcel, linked by the site default');
}

function test_marketplace_trendyol_shipping_link_follows_the_order_screen()
{
	// A row that holds its carrier's own page sends that page.
	$own_page = trendyol_shipping_test_row(array('source' => 'carrier', 'tracking_url' => 'https://track.example-carrier.com/p/ABC'));

	pg_assert_same('https://track.example-carrier.com/p/ABC', mp_sync_shipment_link(array($own_page), '', '')['url']);

	// A carrier chosen on the row wins over the method.
	$named = trendyol_shipping_test_row(array('number' => '9988776655', 'carrier' => 'mng'));

	pg_assert_same('https://kargotakip.mngkargo.com.tr/?takipNo=9988776655',
		mp_sync_shipment_link(array($named), 'Aras Kargo', '')['url']);

	// With no carrier on the row and none in the method code, the site's
	// default carrier names it.
	$unnamed = trendyol_shipping_test_row(array('number' => '9988776655', 'source' => 'api'));

	pg_assert_same('https://kargotakip.araskargo.com.tr/mainpage.aspx?code=9988776655',
		mp_sync_shipment_link(array($unnamed), 'Trendyol Express', 'aras')['url']);
}

// On an order imported before the account shipped itself, the method code is
// the carrier name the marketplace gave, and their own parcel on the address
// carries the same carrier. A link built from it, or from a row carrier that
// only repeats it, would send their carrier's page with the shop's number.
function test_marketplace_trendyol_shipping_link_never_takes_their_carrier()
{
	$theirs = trendyol_shipping_test_row(array('id' => 9, 'number' => '7280027504111111', 'source' => 'marketplace',
		'carrier' => 'yurtici', 'tracking_url' => 'https://tracking.trendyol.com/?id=1'));

	$by_method = trendyol_shipping_test_row(array('number' => '9988776655', 'source' => 'api'));

	pg_assert_same(null, mp_sync_shipment_link(array($theirs, $by_method), 'Yurtiçi Kargo Marketplace', ''),
		'the method code names their carrier');
	pg_assert_same(null, mp_sync_shipment_link(array($theirs, $by_method), 'Yurtiçi Kargo Marketplace', 'aras'),
		'the default does not override what the order screen shows');

	$inherited = trendyol_shipping_test_row(array('number' => '9988776655', 'carrier' => 'yurtici'));

	pg_assert_same(null, mp_sync_shipment_link(array($theirs, $inherited), 'Yurtiçi Kargo Marketplace', ''),
		'the row carrier only repeats theirs');

	$chosen = trendyol_shipping_test_row(array('number' => '9988776655', 'carrier' => 'surat'));

	pg_assert_true(mp_sync_shipment_link(array($theirs, $chosen), 'Yurtiçi Kargo Marketplace', '') !== null,
		'a carrier chosen on the row that is not theirs');

	$own_page = trendyol_shipping_test_row(array('number' => '9988776655', 'carrier' => 'yurtici',
		'tracking_url' => 'https://track.example-carrier.com/p/9988776655'));

	pg_assert_same('https://track.example-carrier.com/p/9988776655',
		mp_sync_shipment_link(array($theirs, $own_page), 'Yurtiçi Kargo Marketplace', '')['url'], 'the row\'s own page');
}

// On an order imported for an account that ships itself the method code stays
// empty, so a carrier there, or on the row, is the one the shop named - also
// when it is the same company the marketplace contracts.
function test_marketplace_trendyol_shipping_link_takes_the_carrier_the_shop_named()
{
	// POST /orders/{id}/shipment with "carrier": "Aras Kargo" writes the method
	// code, and the row stores the carrier it names.
	$by_api = trendyol_shipping_test_row(array('number' => '9988776655', 'carrier' => 'aras', 'source' => 'api'));

	pg_assert_same('https://kargotakip.araskargo.com.tr/mainpage.aspx?code=9988776655',
		mp_sync_shipment_link(array($by_api), 'Aras Kargo', '')['url']);

	// A row recorded before the method code was written takes it from there.
	$before = trendyol_shipping_test_row(array('number' => '9988776655', 'source' => 'panel'));

	pg_assert_same('https://kargotakip.araskargo.com.tr/mainpage.aspx?code=9988776655',
		mp_sync_shipment_link(array($before), 'Aras Kargo', '')['url']);

	// Their parcel says Aras too, but the method code is not theirs.
	$theirs = trendyol_shipping_test_row(array('id' => 9, 'number' => '7280027504111111', 'source' => 'marketplace',
		'carrier' => 'aras'));

	pg_assert_same('https://kargotakip.araskargo.com.tr/mainpage.aspx?code=9988776655',
		mp_sync_shipment_link(array($theirs, $by_api), '', '')['url']);
}

// The number's shape reads a plain Turkish number as UPS (11 digits) or FedEx
// (12 digits); a link nobody named is not sent.
function test_marketplace_trendyol_shipping_link_never_comes_from_the_numbers_shape()
{
	foreach (array('12345678901', '123456789012') as $number) {

		$row = trendyol_shipping_test_row(array('number' => $number));

		pg_assert_true(pg_shipment_view($row, 'Trendyol Express', '')['url'] !== '', 'the order screen guesses a link');
		pg_assert_same(null, mp_sync_shipment_link(array($row), 'Trendyol Express', ''), $number);
		pg_assert_same(null, mp_sync_shipment_link(array($row), '', ''), $number . ' with no method code');

	}
}

// The job step: a package the acceptance pass took already is not sent Picking
// again, a Created one is accepted only when both calls fit, and only packages
// whose order has a parcel the shop recorded are read.
function test_marketplace_trendyol_shipping_job_reads_and_calls_what_it_should()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');

	$start = strpos($source, 'function mp_sync_ship_pending($account) {');
	$end = strpos($source, 'function mp_sync_shipment_link(');

	pg_assert_true(($start !== false) && ($end !== false) && ($start < $end));

	$step = substr($source, $start, $end - $start);

	pg_assert_contains('remote_status, approved, lines_json', $step);
	pg_assert_contains("if ((\$package['remote_status'] === 'Created') && empty(\$row['approved'])) {", $step);
	pg_assert_contains("if (\$calls < 2) {", $step);
	pg_assert_contains("AND (shipping_tracking_numbers.source <> 'marketplace')", $step);
	pg_assert_contains("\$named = (\$default !== '') ? '' : \"", $step, 'without a default, a shape-only parcel is not read');
	pg_assert_contains("OR (ship_tos.shipping_method_code <> ''))", $step);

	$orders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');

	pg_assert_contains('mp_order_apply_tracking($order_id, $package, $account);', $orders);
	pg_assert_contains("if ((\$carrier !== '') && \$ship_to_id && !(is_array(\$account) && mp_account_ships_itself(\$account))) {",
		$orders, 'an account that ships itself keeps their carrier out of the method code');
}

function test_marketplace_trendyol_shipping_link_is_null_without_a_linked_parcel()
{
	pg_assert_same(null, mp_sync_shipment_link(array(), 'Aras Kargo', ''), 'not shipped yet');

	pg_assert_same(null, mp_sync_shipment_link(array(trendyol_shipping_test_row(array('number' => '  '))), 'Aras Kargo', ''),
		'an empty number');

	pg_assert_same(null, mp_sync_shipment_link(array(trendyol_shipping_test_row(array('number' => '55443322'))), 'Standart Teslimat', ''),
		'a carrier nobody can tell and no default');

	$first_unknown = trendyol_shipping_test_row(array('number' => '55443322', 'source' => 'csv'));
	$second_known = trendyol_shipping_test_row(array('id' => 2, 'number' => 'K1', 'tracking_url' => 'https://track.example.com/K1'));

	pg_assert_same(array('number' => 'K1', 'url' => 'https://track.example.com/K1'),
		mp_sync_shipment_link(array($first_unknown, $second_known), 'Standart Teslimat', ''), 'the first parcel that has a link');
}
