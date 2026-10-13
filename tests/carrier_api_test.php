<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the carriers' REST API helpers in includes/fn/carrier_api.php:
 * the token request and its cache, error lines, the UPS Rating request and
 * answer, the FedEx transit times, and the USPS prices, delivery dates and
 * address checks. No carrier is called.
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

// USPS takes the credentials in the form, at its test host in the test
// environment.
function test_carrier_api_usps_token_request_uses_form()
{
	$request = pg_carrier_api_token_request(pg_carrier_api_definition('usps'), carrier_api_test_app('test'));

	pg_assert_same('https://apis-tem.usps.com/oauth2/v3/token', $request['url']);
	pg_assert_same('grant_type=client_credentials&client_id=id-123&client_secret=secret%3A456', $request['body']);

	$request = pg_carrier_api_token_request(pg_carrier_api_definition('usps'), carrier_api_test_app());

	pg_assert_same('https://apis.usps.com/oauth2/v3/token', $request['url']);
}

// A GET carries no content type; a POST with a body does.
function test_carrier_api_request_headers()
{
	pg_assert_same(
		array('Accept: application/json', 'Authorization: Bearer t0k'),
		pg_carrier_api_request_headers('t0k', false));

	pg_assert_same(
		array('Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer t0k', 'X-locale: en_US'),
		pg_carrier_api_request_headers('t0k', true, array('X-locale: en_US')));
}

// USPS names the refused fields under the error; a detail repeating the
// message is written once.
function test_carrier_api_error_text_usps_details()
{
	pg_assert_same(
		'400: OAS Validation Failed; 400: Invalid ZIP Code.',
		pg_carrier_api_error_text(array('apiVersion' => 'v3', 'error' => array(
			'code'    => '400',
			'message' => 'OAS Validation Failed',
			'errors'  => array(array('status' => '400', 'code' => '400', 'title' => 'Invalid value', 'detail' => 'Invalid ZIP Code.')),
		)), 400));

	pg_assert_same(
		'010005: Address Not Found.',
		pg_carrier_api_error_text(array('error' => array(
			'code'    => '010005',
			'message' => 'Address Not Found.',
			'errors'  => array(array('code' => '010005', 'title' => 'Address Not Found.')),
		)), 400));
}

function test_usps_mail_classes()
{
	pg_assert_same('PRIORITY_MAIL', pg_usps_mail_class('usps_priority'));
	pg_assert_same('PRIORITY_MAIL_EXPRESS', pg_usps_mail_class('usps_express'));
	pg_assert_same('USPS_GROUND_ADVANTAGE', pg_usps_mail_class('usps_ground'));
	pg_assert_same('', pg_usps_mail_class('ups_ground'));
}

function test_usps_machinable_limits()
{
	pg_assert_true(pg_usps_machinable(array('length' => 10, 'width' => 8, 'height' => 4, 'weight' => 2)));
	pg_assert_false(pg_usps_machinable(array('length' => 40, 'width' => 8, 'height' => 4, 'weight' => 2)), 'too long');
	pg_assert_false(pg_usps_machinable(array('length' => 10, 'width' => 8, 'height' => 4, 'weight' => 0.25)), 'too light');
	pg_assert_false(pg_usps_machinable(array('length' => 0, 'width' => 0, 'height' => 0, 'weight' => 2)), 'no dimensions');
}

// Retail prices for the five-digit ZIP codes, the package's measures and the
// mail classes asked for.
function test_usps_rate_request_shape()
{
	$request = pg_usps_rate_request(
		'30301-1234',
		'10001',
		array('length' => '10.00', 'width' => '8', 'height' => '4', 'weight' => '2.500'),
		array('PRIORITY_MAIL', 'USPS_GROUND_ADVANTAGE'),
		'2026-10-14');

	pg_assert_same(array(
		'originZIPCode'      => '30301',
		'destinationZIPCode' => '10001',
		'weight'             => 2.5,
		'length'             => 10.0,
		'width'              => 8.0,
		'height'             => 4.0,
		'mailClasses'        => array('PRIORITY_MAIL', 'USPS_GROUND_ADVANTAGE'),
		'priceType'          => 'RETAIL',
		'mailingDate'        => '2026-10-14',
		'itemValue'          => 0,
	), $request);
}

function carrier_api_test_usps_option($mail_class, $total, $rate)
{
	return array(
		'totalBasePrice' => $total,
		'totalPrice'     => $total,
		'rates'          => array($rate + array('mailClass' => $mail_class, 'price' => $total)),
	);
}

// Flat Rate, Cubic and destination entry prices are left out; of the
// single-piece prices the package's processing category is taken.
function test_usps_rate_parse_keeps_single_piece_prices()
{
	$data = array('rateOptions' => array(
		carrier_api_test_usps_option('PRIORITY_MAIL', 10.40, array('rateIndicator' => 'FS', 'description' => 'Priority Mail Small Flat Rate Box')),
		carrier_api_test_usps_option('PRIORITY_MAIL', 12.85, array('rateIndicator' => 'SP', 'description' => 'Priority Mail Machinable Single-piece', 'processingCategory' => 'MACHINABLE')),
		carrier_api_test_usps_option('PRIORITY_MAIL', 9.20, array('rateIndicator' => 'CP', 'description' => 'Priority Mail Cubic Soft Pack')),
		carrier_api_test_usps_option('USPS_GROUND_ADVANTAGE', 7.45, array('rateIndicator' => 'SP', 'processingCategory' => 'MACHINABLE')),
		carrier_api_test_usps_option('USPS_GROUND_ADVANTAGE', 11.30, array('rateIndicator' => 'SP', 'processingCategory' => 'NONSTANDARD')),
		carrier_api_test_usps_option('USPS_GROUND_ADVANTAGE', 5.10, array('rateIndicator' => 'SP', 'processingCategory' => 'MACHINABLE', 'destinationEntryFacilityType' => 'DESTINATION_DELIVERY_UNIT')),
		carrier_api_test_usps_option('PRIORITY_MAIL_EXPRESS', 39.10, array('rateIndicator' => 'PA', 'processingCategory' => 'MACHINABLE', 'destinationEntryFacilityType' => 'NONE')),
		// Not a service the shipping methods offer.
		carrier_api_test_usps_option('MEDIA_MAIL', 4.13, array('rateIndicator' => 'SP')),
	));

	pg_assert_same(array(
		'usps_priority' => 1285,
		'usps_ground'   => 745,
		'usps_express'  => 3910,
	), pg_usps_rate_parse($data, true));

	pg_assert_same(1130, pg_usps_rate_parse($data, false)['usps_ground'], 'nonstandard package');
}

// Without a rate indicator the description decides; with several prices left
// the highest is taken, and the total with fees is preferred over the base.
function test_usps_rate_parse_without_indicator()
{
	$data = array('rateOptions' => array(
		array(
			'totalBasePrice' => 8.00,
			'totalPrice'     => 8.75,
			'rates'          => array(array('mailClass' => 'USPS_GROUND_ADVANTAGE', 'description' => 'USPS Ground Advantage Machinable Single-piece', 'price' => 8.00)),
		),
		array(
			'totalBasePrice' => 7.10,
			'rates'          => array(array('mailClass' => 'USPS_GROUND_ADVANTAGE', 'description' => 'USPS Ground Advantage Machinable Dimensional Rectangular', 'price' => 7.10)),
		),
		array(
			'totalBasePrice' => 6.00,
			'rates'          => array(array('mailClass' => 'PRIORITY_MAIL', 'productName' => 'Priority Mail Flat Rate Envelope', 'price' => 6.00)),
		),
	));

	pg_assert_same(array('usps_ground' => 875), pg_usps_rate_parse($data, true));
	pg_assert_same(array(), pg_usps_rate_parse(array('error' => array('code' => '400')), true));
	pg_assert_same(array(), pg_usps_rate_parse(null, true));
}

// A price named by a rate indicator outranks one without, whatever the price.
function test_usps_rate_parse_indicator_outranks_description()
{
	$data = array('rateOptions' => array(
		carrier_api_test_usps_option('PRIORITY_MAIL', 30.00, array('description' => 'Priority Mail Something New')),
		carrier_api_test_usps_option('PRIORITY_MAIL', 12.00, array('rateIndicator' => 'SP')),
	));

	pg_assert_same(array('usps_priority' => 1200), pg_usps_rate_parse($data, true));
}

function test_usps_delivery_path()
{
	pg_assert_same(
		'/service-standards/v3/estimates?originZIPCode=30301&destinationZIPCode=10001&acceptanceDate=2026-10-14&mailClass=PRIORITY_MAIL&destinationType=STREET',
		pg_usps_delivery_path('30301-1234', '10001-0001', '2026-10-14', 'PRIORITY_MAIL'));
}

function test_usps_delivery_parse()
{
	$data = array(
		array(
			'mailClass'       => 'PRIORITY_MAIL',
			'serviceStandard' => '2',
			'delivery'        => array('scheduledDeliveryDateTime' => '2026-10-16T18:00:00Z'),
		),
	);

	pg_assert_same('2026-10-16', pg_usps_delivery_parse($data, 'PRIORITY_MAIL'));
	pg_assert_same('', pg_usps_delivery_parse($data, 'USPS_GROUND_ADVANTAGE'), 'other class');

	// A lone estimate, with the date at its top.
	pg_assert_same('2026-10-19', pg_usps_delivery_parse(array('scheduledDeliveryDateTime' => '2026-10-19T00:00:00'), 'USPS_GROUND_ADVANTAGE'));
	pg_assert_same('', pg_usps_delivery_parse(array(array('mailClass' => 'PRIORITY_MAIL')), 'PRIORITY_MAIL'));
	pg_assert_same('', pg_usps_delivery_parse(null, 'PRIORITY_MAIL'));
}

function test_usps_address_path()
{
	pg_assert_same(
		'/addresses/v3/address?streetAddress=3120%20M%20St%20NW&secondaryAddress=Apt%202&city=Washington&state=DC&ZIPCode=20007',
		pg_usps_address_path(array(
			'address_1' => ' 3120 M St NW ',
			'address_2' => 'Apt 2',
			'city'      => 'Washington',
			'state'     => 'dc',
			'zip_code'  => '20007-3704')));

	pg_assert_same(
		'/addresses/v3/address?streetAddress=1%20Main%20St&state=GA&ZIPCode=30301',
		pg_usps_address_path(array('address_1' => '1 Main St', 'address_2' => '', 'city' => '', 'state' => 'GA', 'zip_code' => '30301')));
}

function carrier_api_test_usps_address($dpv, $corrections = array())
{
	return array(
		'firm'           => '',
		'address'        => array(
			'streetAddress'    => '3120 M ST NW',
			'secondaryAddress' => '',
			'city'             => 'WASHINGTON',
			'state'            => 'DC',
			'ZIPCode'          => '20007',
			'ZIPPlus4'         => '3704',
		),
		'additionalInfo' => array('DPVConfirmation' => $dpv),
		'corrections'    => $corrections,
		'matches'        => array(array('code' => '31', 'text' => 'Single Response - exact match')),
	);
}

// A confirmed address comes back standardized, with the ZIP+4.
function test_usps_address_parse_verified()
{
	pg_assert_same(array(
		'result'  => 'verified',
		'address' => array(
			'address_1' => '3120 M ST NW',
			'address_2' => '',
			'city'      => 'WASHINGTON',
			'state'     => 'DC',
			'zip_code'  => '20007-3704',
		),
	), pg_usps_address_parse(200, carrier_api_test_usps_address('Y')));
}

// The building was found but the unit was not: the address is filled in and
// the customer is asked for the apartment or suite.
function test_usps_address_parse_missing_secondary()
{
	pg_assert_same('secondary', pg_usps_address_parse(200, carrier_api_test_usps_address('D'))['result']);
	pg_assert_same('secondary', pg_usps_address_parse(200, carrier_api_test_usps_address('S'))['result']);
	pg_assert_same('secondary', pg_usps_address_parse(200, carrier_api_test_usps_address('Y', array(array('code' => '32', 'text' => 'Default address'))))['result']);
	pg_assert_same('20007-3704', pg_usps_address_parse(200, carrier_api_test_usps_address('D'))['address']['zip_code']);
}

function test_usps_address_parse_not_found()
{
	pg_assert_same(array('result' => 'not_found', 'address' => null), pg_usps_address_parse(200, carrier_api_test_usps_address('N')));
	pg_assert_same('not_found', pg_usps_address_parse(200, carrier_api_test_usps_address('', array(array('code' => '22', 'text' => 'Multiple addresses were found'))))['result']);
	pg_assert_same('not_found', pg_usps_address_parse(400, array('error' => array('code' => '400', 'message' => 'Address Not Found.')))['result']);
	pg_assert_same('not_found', pg_usps_address_parse(404, array('error' => array('code' => '404', 'message' => 'Resource not found')))['result']);
}

// A field USPS names as wrong is the one marked.
function test_usps_address_parse_invalid_fields()
{
	pg_assert_same('city', pg_usps_address_parse(400, array('error' => array('code' => '400', 'message' => 'Invalid City.')))['result']);
	pg_assert_same('state', pg_usps_address_parse(400, array('error' => array('code' => '400', 'message' => 'OAS Validation Failed', 'errors' => array(array('detail' => 'Invalid State Code.')))))['result']);
	pg_assert_same('zip_code', pg_usps_address_parse(400, array('error' => array('code' => '400', 'message' => 'Invalid Zip Code.')))['result']);
}

// No answer, a refused token, a server fault or a blank address says nothing
// about the address, so the customer is not told it is wrong.
function test_usps_address_parse_errors()
{
	pg_assert_same('error', pg_usps_address_parse(0, null)['result']);
	pg_assert_same('error', pg_usps_address_parse(401, array('error' => array('code' => '401', 'message' => 'Address not found because the token expired')))['result']);
	pg_assert_same('error', pg_usps_address_parse(503, null)['result']);
	pg_assert_same('error', pg_usps_address_parse(200, array('address' => array('streetAddress' => '')))['result']);
	pg_assert_same('error', pg_usps_address_parse(400, array('error' => array('code' => '400', 'message' => 'Something else')))['result']);
}
