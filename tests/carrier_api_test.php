<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the carriers' REST API helpers in includes/fn/carrier_api.php:
 * the token request and its cache, error lines, and the UPS Rating request
 * and answer. No carrier is called.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function carrier_api_test_app($environment = 'production')
{
	return array(
		'client_id'     => 'id-123',
		'client_secret' => 'secret:456',
		'secret_stored' => true,
		'environment'   => $environment,
	);
}

function carrier_api_test_shipper()
{
	return array(
		'name'      => 'Pinegrap Shop',
		'address_1' => '100 Main St',
		'city'      => 'Atlanta',
		'state'     => 'GA',
		'zip_code'  => '30301',
	);
}

// UPS takes the client credentials as Basic auth, at its test host when the
// application is set to the test environment.
function test_carrier_api_ups_token_request_uses_basic_auth()
{
	$request = pg_carrier_api_token_request(pg_carrier_api_definition('ups'), carrier_api_test_app('test'));

	pg_assert_same('https://wwwcie.ups.com/security/v1/oauth/token', $request['url']);
	pg_assert_contains('Authorization: Basic ' . base64_encode('id-123:secret:456'), $request['headers']);
	pg_assert_same('grant_type=client_credentials', $request['body']);
}

function test_carrier_api_ups_production_host()
{
	$request = pg_carrier_api_token_request(pg_carrier_api_definition('ups'), carrier_api_test_app());

	pg_assert_same('https://onlinetools.ups.com/security/v1/oauth/token', $request['url']);
}

// A carrier that wants the credentials in the form gets them there, and no
// Authorization header.
function test_carrier_api_form_token_request()
{
	$definition = array(
		'hosts'      => array('production' => 'https://api.example.test', 'test' => 'https://sandbox.example.test'),
		'token_path' => '/oauth/token',
		'token_auth' => 'form',
	);

	$request = pg_carrier_api_token_request($definition, carrier_api_test_app());

	pg_assert_same('https://api.example.test/oauth/token', $request['url']);
	pg_assert_same('grant_type=client_credentials&client_id=id-123&client_secret=secret%3A456', $request['body']);

	foreach ($request['headers'] as $header) {
		pg_assert_false(stripos($header, 'Authorization:') === 0, $header);
	}
}

// UPS sends the lifetime as a string.
function test_carrier_api_token_parse_reads_string_lifetime()
{
	$parsed = pg_carrier_api_token_parse(array('access_token' => 'abc', 'expires_in' => '14399'), 1000);

	pg_assert_same(array('token' => 'abc', 'expires' => 15399), $parsed);
}

function test_carrier_api_token_parse_without_token_is_null()
{
	pg_assert_same(null, pg_carrier_api_token_parse(array('response' => array('errors' => array())), 1000));
	pg_assert_same(null, pg_carrier_api_token_parse(null, 1000));
}

// A missing lifetime is taken as a minute, not as forever.
function test_carrier_api_token_parse_short_lifetime_floor()
{
	$parsed = pg_carrier_api_token_parse(array('access_token' => 'abc'), 1000);

	pg_assert_same(1060, $parsed['expires']);
}

function test_carrier_api_cached_token_is_used_while_valid()
{
	$fingerprint = pg_carrier_api_fingerprint(carrier_api_test_app());
	$cache = array('token' => 'cached', 'expires' => 2000, 'fingerprint' => $fingerprint);

	pg_assert_same('cached', pg_carrier_api_cached_token($cache, $fingerprint, 1000));
}

// Two minutes before it expires a token is not handed out any more.
function test_carrier_api_cached_token_near_expiry_is_skipped()
{
	$fingerprint = pg_carrier_api_fingerprint(carrier_api_test_app());
	$cache = array('token' => 'cached', 'expires' => 1100, 'fingerprint' => $fingerprint);

	pg_assert_same('', pg_carrier_api_cached_token($cache, $fingerprint, 1000));
}

// A token fetched with other credentials or for the other environment is not
// offered.
function test_carrier_api_cached_token_of_other_credentials_is_skipped()
{
	$cache = array('token' => 'cached', 'expires' => 5000, 'fingerprint' => pg_carrier_api_fingerprint(carrier_api_test_app('test')));

	pg_assert_same('', pg_carrier_api_cached_token($cache, pg_carrier_api_fingerprint(carrier_api_test_app()), 1000));
	pg_assert_same('', pg_carrier_api_cached_token(array(), 'x', 1000));
}

function test_carrier_api_error_text_shapes()
{
	pg_assert_same(
		'250002: Invalid Authentication Information.',
		pg_carrier_api_error_text(array('response' => array('errors' => array(array('code' => '250002', 'message' => 'Invalid Authentication Information.')))), 401));

	pg_assert_same(
		'NOT.AUTHORIZED.ERROR: Bad token.; SERVICE.UNAVAILABLE.ERROR: Later.',
		pg_carrier_api_error_text(array('errors' => array(
			array('code' => 'NOT.AUTHORIZED.ERROR', 'message' => 'Bad token.'),
			array('code' => 'SERVICE.UNAVAILABLE.ERROR', 'message' => 'Later.'))), 401));

	pg_assert_same(
		'invalid_client: Client authentication failed',
		pg_carrier_api_error_text(array('error' => 'invalid_client', 'error_description' => 'Client authentication failed'), 401));

	pg_assert_same('HTTP 502', pg_carrier_api_error_text(null, 502));
}

function test_ups_measure_trims_to_six_characters()
{
	pg_assert_same('12', pg_ups_measure('12.000'));
	pg_assert_same('0.5', pg_ups_measure('0.50'));
	pg_assert_same('123.46', pg_ups_measure('123.4567'));
}

// Every service is asked for, with the shipper's address and the recipient's
// ZIP code and state.
function test_ups_rate_request_shape()
{
	$request = pg_ups_rate_request(
		carrier_api_test_shipper(),
		array('state' => 'NY', 'zip_code' => '10001'),
		array('length' => '10.00', 'width' => '8', 'height' => '4', 'weight' => '2.500'),
		'');

	$shipment = $request['RateRequest']['Shipment'];

	pg_assert_same('Shop', $request['RateRequest']['Request']['RequestOption']);
	pg_assert_same(array('100 Main St'), $shipment['Shipper']['Address']['AddressLine']);
	pg_assert_same('GA', $shipment['Shipper']['Address']['StateProvinceCode']);
	pg_assert_same('30301', $shipment['Shipper']['Address']['PostalCode']);
	pg_assert_same(array('PostalCode' => '10001', 'StateProvinceCode' => 'NY', 'CountryCode' => 'US'), $shipment['ShipTo']['Address']);
	pg_assert_same('10', $shipment['Package'][0]['Dimensions']['Length']);
	pg_assert_same('2.5', $shipment['Package'][0]['PackageWeight']['Weight']);
	pg_assert_same('LBS', $shipment['Package'][0]['PackageWeight']['UnitOfMeasurement']['Code']);
	pg_assert_false(isset($shipment['Shipper']['ShipperNumber']), 'no shipper number');
	pg_assert_false(isset($shipment['ShipmentRatingOptions']), 'no negotiated rates');
}

// An account number asks for the negotiated rates.
function test_ups_rate_request_with_account_asks_negotiated_rates()
{
	$request = pg_ups_rate_request(
		carrier_api_test_shipper(),
		array('state' => 'NY', 'zip_code' => '10001'),
		array('length' => 10, 'width' => 8, 'height' => 4, 'weight' => 2),
		'A1B2C3');

	$shipment = $request['RateRequest']['Shipment'];

	pg_assert_same('A1B2C3', $shipment['Shipper']['ShipperNumber']);
	pg_assert_same(array('NegotiatedRatesIndicator' => 'Y'), $shipment['ShipmentRatingOptions']);
}

// A package without dimensions is rated by weight alone.
function test_ups_rate_request_without_dimensions()
{
	$request = pg_ups_rate_request(
		carrier_api_test_shipper(),
		array('state' => '', 'zip_code' => '10001'),
		array('length' => '0', 'width' => '0', 'height' => '0', 'weight' => '1'),
		'');

	$shipment = $request['RateRequest']['Shipment'];

	pg_assert_false(isset($shipment['Package'][0]['Dimensions']), 'no dimensions');
	pg_assert_false(isset($shipment['ShipTo']['Address']['StateProvinceCode']), 'no state');
}

function test_ups_rate_parse_reads_services_in_cents()
{
	$data = array('RateResponse' => array('RatedShipment' => array(
		array('Service' => array('Code' => '03'), 'TotalCharges' => array('MonetaryValue' => '23.10')),
		array('Service' => array('Code' => '01'), 'TotalCharges' => array('MonetaryValue' => '88.45')),
		// Not a service the shipping methods offer.
		array('Service' => array('Code' => '65'), 'TotalCharges' => array('MonetaryValue' => '40.00')),
	)));

	pg_assert_same(array('ups_ground' => 2310, 'ups_next_day_air' => 8845), pg_ups_rate_parse($data, false));
}

// With an account the negotiated rate wins; a service without one keeps the
// published rate.
function test_ups_rate_parse_prefers_negotiated_rate()
{
	$data = array('RateResponse' => array('RatedShipment' => array(
		array(
			'Service'               => array('Code' => '03'),
			'TotalCharges'          => array('MonetaryValue' => '23.10'),
			'NegotiatedRateCharges' => array('TotalCharge' => array('MonetaryValue' => '19.75')),
		),
		array('Service' => array('Code' => '02'), 'TotalCharges' => array('MonetaryValue' => '45.00')),
	)));

	pg_assert_same(array('ups_ground' => 1975, 'ups_2nd_day_air' => 4500), pg_ups_rate_parse($data, true));
	pg_assert_same(array('ups_ground' => 2310, 'ups_2nd_day_air' => 4500), pg_ups_rate_parse($data, false));
}

// Before v2409 a lone service came as an object rather than a list.
function test_ups_rate_parse_single_object()
{
	$data = array('RateResponse' => array('RatedShipment' => array(
		'Service'      => array('Code' => '12'),
		'TotalCharges' => array('MonetaryValue' => '30.00'),
	)));

	pg_assert_same(array('ups_3_day_select' => 3000), pg_ups_rate_parse($data, false));
	pg_assert_same(array(), pg_ups_rate_parse(array('response' => array('errors' => array())), false));
}

// FedEx takes the credentials in the form, at its sandbox host in the test
// environment.
function test_carrier_api_fedex_token_request_uses_form()
{
	$request = pg_carrier_api_token_request(pg_carrier_api_definition('fedex'), carrier_api_test_app('test'));

	pg_assert_same('https://apis-sandbox.fedex.com/oauth/token', $request['url']);
	pg_assert_same('grant_type=client_credentials&client_id=id-123&client_secret=secret%3A456', $request['body']);
}

function test_fedex_transit_days_words()
{
	pg_assert_same(1, pg_fedex_transit_days('ONE_DAY'));
	pg_assert_same(4, pg_fedex_transit_days('FOUR_DAYS'));
	pg_assert_same(20, pg_fedex_transit_days('TWENTY_DAYS'));
	pg_assert_same(0, pg_fedex_transit_days('UNKNOWN'));
	pg_assert_same(0, pg_fedex_transit_days(''));
}

// Every service is asked for, with the account, the ship day and the
// heaviest item's weight; a missing weight is sent as one pound.
function test_fedex_rate_request_shape()
{
	$request = pg_fedex_rate_request(
		array('zip_code' => '30301'),
		array('zip_code' => '10001', 'country' => 'USA'),
		'2026-10-14',
		0,
		'740561073');

	pg_assert_same(array('value' => '740561073'), $request['accountNumber']);
	pg_assert_true($request['rateRequestControlParameters']['returnTransitTimes'], 'transit times');
	pg_assert_same('2026-10-14', $request['requestedShipment']['shipDateStamp']);
	pg_assert_same(array('postalCode' => '10001', 'countryCode' => 'US'), $request['requestedShipment']['recipient']['address']);
	pg_assert_same('30301', $request['requestedShipment']['shipper']['address']['postalCode']);
	pg_assert_same(1, $request['requestedShipment']['requestedPackageLineItems'][0]['weight']['value']);
	pg_assert_false(isset($request['requestedShipment']['serviceType']), 'no service type');
}

// The committed date is read first, then the operational one; FedEx Ground's
// transit time comes along for the method's own weekend rules.
function test_fedex_delivery_parse_reads_dates_and_transit()
{
	$data = array('output' => array('rateReplyDetails' => array(
		array(
			'serviceType' => 'PRIORITY_OVERNIGHT',
			'commit'      => array('dateDetail' => array('dayOfWeek' => 'Thu', 'dayFormat' => '2026-10-15T10:30:00')),
		),
		array(
			'serviceType'       => 'FEDEX_2_DAY',
			'operationalDetail' => array('deliveryDate' => '2026-10-16T23:59:00'),
		),
		array(
			'serviceType'       => 'FEDEX_GROUND',
			'commit'            => array(
				'dateDetail'  => array('dayFormat' => '2026-10-20T23:59:00'),
				'transitDays' => array('minimumTransitTime' => 'FOUR_DAYS'),
			),
		),
		// Not a service the shipping methods offer.
		array(
			'serviceType' => 'SMART_POST',
			'commit'      => array('dateDetail' => array('dayFormat' => '2026-10-22T23:59:00')),
		),
	)));

	pg_assert_same(array(
		'fedex_priority_overnight' => array('date' => '2026-10-15', 'transit_days' => 0),
		'fedex_2_day'              => array('date' => '2026-10-16', 'transit_days' => 0),
		'fedex_ground'             => array('date' => '2026-10-20', 'transit_days' => 4),
	), pg_fedex_delivery_parse($data));
}

function test_fedex_delivery_parse_transit_from_operational_detail()
{
	$data = array('output' => array('rateReplyDetails' => array(
		array(
			'serviceType'       => 'FEDEX_GROUND',
			'operationalDetail' => array('transitTime' => 'THREE_DAYS'),
		),
	)));

	pg_assert_same(array('fedex_ground' => array('date' => '', 'transit_days' => 3)), pg_fedex_delivery_parse($data));
	pg_assert_same(array(), pg_fedex_delivery_parse(array('errors' => array())));
}
