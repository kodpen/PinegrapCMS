<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the EU VAT id checks (includes/fn/vat_check.php): the clean
 * function per member state, the VIES request, the mapping of VIES answers,
 * the reverse-charge decision and pg_vat_check() with a stubbed transport.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * One well-formed id per VIES prefix, as a customer might type it.
 */
function _vat_test_ids()
{
	return array(
		'AT' => 'ATU12345678',
		'BE' => 'BE 0123.456.789',
		'BG' => 'BG123456789',
		'CY' => 'CY12345678L',
		'CZ' => 'CZ12345678',
		'DE' => 'de 123 456 789',
		'DK' => 'DK12345678',
		'EE' => 'EE123456789',
		'EL' => 'EL123456789',
		'ES' => 'ESB12345678',
		'FI' => 'FI12345678',
		'FR' => 'FR 40 303265045',
		'HR' => 'HR12345678901',
		'HU' => 'HU12345678',
		'IE' => 'IE1234567WA',
		'IT' => 'IT12345678901',
		'LT' => 'LT123456789012',
		'LU' => 'LU12345678',
		'LV' => 'LV12345678901',
		'MT' => 'MT12345678',
		'NL' => 'NL123456789B01',
		'PL' => 'PL123-456-78-90',
		'PT' => 'PT123456789',
		'RO' => 'RO1234',
		'SE' => 'SE123456789001',
		'SI' => 'SI12345678',
		'SK' => 'SK1234567890',
		'XI' => 'XI123456789',
	);
}

function test_vat_id_clean_every_member_state()
{
	$ids = _vat_test_ids();
	pg_assert_same(array_keys(pg_vat_id_patterns()), array_keys($ids));

	foreach ($ids as $prefix => $typed) {
		$clean = pg_vat_id_clean($typed);
		pg_assert_same('', $clean['error'], $prefix);
		pg_assert_same($prefix, $clean['country'], $prefix);
		pg_assert_same($prefix . $clean['number'], $clean['id'], $prefix);
	}

	pg_assert_same('BE0123456789', pg_vat_id_clean('BE 0123.456.789')['id']);
	pg_assert_same('PL1234567890', pg_vat_id_clean('PL123-456-78-90')['id']);
}

function test_vat_id_clean_wrong_shapes_per_state()
{
	$wrong = array(
		'AT12345678',      // the U is part of the Austrian number
		'BE2123456789',    // a Belgian number starts with 0 or 1
		'DE12345678',      // eight digits
		'FR I0303265045',  // I is not used in the French key
		'NL123456789A01',
		'SE123456789012',  // a Swedish id ends in 01
		'RO0123',
		'IE12345678',
	);

	foreach ($wrong as $typed) {
		pg_assert_same('format', pg_vat_id_clean($typed)['error'], $typed);
	}
}

function test_vat_id_clean_prefix_from_country()
{
	$clean = pg_vat_id_clean('123456789', 'DE');
	pg_assert_same('', $clean['error']);
	pg_assert_same('DE123456789', $clean['id']);

	// Greece: ISO GR, VIES EL, either typed.
	pg_assert_same('EL123456789', pg_vat_id_clean('123456789', 'gr')['id']);
	pg_assert_same('EL123456789', pg_vat_id_clean('GR123456789')['id']);

	pg_assert_same('ATU12345678', pg_vat_id_clean('U12345678', 'AT')['id']);

	// A typed prefix wins over the billing country.
	pg_assert_same('DE', pg_vat_id_clean('DE123456789', 'FR')['country']);

	pg_assert_same('country', pg_vat_id_clean('123456789')['error']);
	pg_assert_same('country', pg_vat_id_clean('123456789', 'TR')['error']);
	pg_assert_same('country', pg_vat_id_clean('123456789', 'GB')['error']);
}

function test_vat_id_clean_empty_and_characters()
{
	pg_assert_same('empty', pg_vat_id_clean('  - . ')['error']);
	pg_assert_same('characters', pg_vat_id_clean('DE12345678#')['error']);
	pg_assert_same('characters', pg_vat_id_clean('DE1234567<b>')['error']);
}

function test_vat_eu_member()
{
	pg_assert_same(true, pg_vat_eu_member('DE'));
	pg_assert_same(true, pg_vat_eu_member('gr'));
	pg_assert_same(false, pg_vat_eu_member('EL'));
	pg_assert_same(false, pg_vat_eu_member('GB'));
	pg_assert_same(false, pg_vat_eu_member('TR'));
	pg_assert_same(false, pg_vat_eu_member(''));
}

function test_vat_check_request_shape()
{
	$request = pg_vat_check_request(pg_vat_id_clean('DE123456789'));
	pg_assert_same(PG_VAT_VIES_URL, $request['url']);
	pg_assert_same(array('countryCode' => 'DE', 'vatNumber' => '123456789'), json_decode($request['body'], true));
	pg_assert_same(true, in_array('Content-Type: application/json', $request['headers'], true));

	$request = pg_vat_check_request(pg_vat_id_clean('DE123456789'), 'NL123456789B01');
	pg_assert_same(array(
		'countryCode'              => 'DE',
		'vatNumber'                => '123456789',
		'requesterMemberStateCode' => 'NL',
		'requesterNumber'          => '123456789B01',
	), json_decode($request['body'], true));

	// A requester id that is not well formed is left out.
	$body = json_decode(pg_vat_check_request(pg_vat_id_clean('DE123456789'), '1234')['body'], true);
	pg_assert_same(false, isset($body['requesterNumber']));
}

function test_vat_check_map_valid()
{
	$result = pg_vat_check_map(array('ok' => true, 'status' => 200, 'body' => json_encode(array(
		'countryCode'       => 'DE',
		'vatNumber'         => '123456789',
		'requestDate'       => '2026-11-02T10:00:00.000Z',
		'valid'             => true,
		'requestIdentifier' => 'WAPIAAAAW1234567',
		'name'              => "Example  GmbH\n",
		'address'           => "Hauptstr. 1\n10115 Berlin",
		'userError'         => 'VALID',
	))), 1700000000);

	pg_assert_same(array(
		'status'     => 'valid',
		'name'       => 'Example GmbH',
		'address'    => 'Hauptstr. 1 10115 Berlin',
		'request_id' => 'WAPIAAAAW1234567',
		'checked_at' => 1700000000,
		'error'      => '',
	), $result);

	$hidden = pg_vat_check_map(array('ok' => true, 'status' => 200, 'body' => '{"valid":true,"name":"---","address":"---"}'), 1);
	pg_assert_same('', $hidden['name']);
	pg_assert_same('', $hidden['address']);
}

function test_vat_check_map_invalid_and_unavailable()
{
	$invalid = pg_vat_check_map(array('ok' => true, 'status' => 200, 'body' => '{"valid":false,"userError":"INVALID","name":"---"}'), 1);
	pg_assert_same('invalid', $invalid['status']);
	pg_assert_same('', $invalid['error']);

	$cases = array(
		'MS_UNAVAILABLE'      => array('ok' => true, 'status' => 200, 'body' => '{"valid":false,"userError":"MS_UNAVAILABLE"}'),
		'MS_MAX_CONCURRENT_REQ' => array('ok' => false, 'status' => 500, 'body' => '{"actionSucceed":false,"errorWrappers":[{"error":"MS_MAX_CONCURRENT_REQ"}]}'),
		'INVALID_INPUT'       => array('ok' => false, 'status' => 400, 'body' => '{"actionSucceed":false,"errorWrappers":[{"error":"INVALID_INPUT","message":"x"}]}'),
		'http_503'            => array('ok' => false, 'status' => 503, 'body' => '<html>down</html>'),
		'network'             => array('ok' => false, 'status' => 0, 'body' => '', 'errno' => 28),
		'answer'              => array('ok' => true, 'status' => 200, 'body' => 'not json'),
	);

	foreach ($cases as $code => $response) {
		$result = pg_vat_check_map($response, 1);
		pg_assert_same('unavailable', $result['status'], $code);
		pg_assert_same($code, $result['error'], $code);
	}

	pg_assert_same('unavailable', pg_vat_check_map(array(), 1)['status']);
}

function test_vat_exempt_decision()
{
	$valid_fr = array('status' => 'valid', 'country' => 'FR');
	$valid_el = array('status' => 'valid', 'country' => 'EL');

	pg_assert_same(array('exempt' => true, 'reason' => 'exempt'), pg_vat_exempt_decision($valid_fr, 'FR', 'DE'));
	pg_assert_same(true, pg_vat_exempt_decision($valid_el, 'GR', 'DE')['exempt']);

	$reasons = array(
		'no_check'      => array(array(), 'FR', 'DE'),
		'same_country'  => array(array('status' => 'valid', 'country' => 'DE'), 'DE', 'DE'),
		'not_eu'        => array($valid_fr, 'FR', 'TR'),
		'not_eu '       => array($valid_fr, 'CH', 'DE'),
		'other_country' => array($valid_fr, 'BE', 'DE'),
		'unavailable'   => array(array('status' => 'unavailable', 'country' => 'FR'), 'FR', 'DE'),
		'invalid'       => array(array('status' => 'invalid', 'country' => 'FR'), 'FR', 'DE'),
		'invalid '      => array(array('status' => 'malformed', 'country' => ''), 'FR', 'DE'),
	);

	foreach ($reasons as $reason => $args) {
		$decision = pg_vat_exempt_decision($args[0], $args[1], $args[2]);
		pg_assert_same(false, $decision['exempt'], $reason);
		pg_assert_same(trim($reason), $decision['reason'], $reason);
	}
}

function test_vat_check_malformed_makes_no_call()
{
	$called = false;
	$result = pg_vat_check('DE1234', array('fresh' => true, 'now' => 5, 'http' => function () use (&$called) {
		$called = true;
		return array();
	}));

	pg_assert_same(false, $called);
	pg_assert_same('malformed', $result['status']);
	pg_assert_same('format', $result['error']);
}

function test_vat_check_calls_maps_and_caches()
{
	// A number no real test uses, so a stale cache file of another run is the only thing it can meet.
	$typed = 'DE' . sprintf('%09d', mt_rand(100000000, 999999999));
	$clean = pg_vat_id_clean($typed);
	@unlink(pg_vat_cache_file($clean['id']));

	$calls = array();
	$http = function ($method, $url, $options) use (&$calls) {
		$calls[] = array($method, $url, json_decode($options['body'], true));
		return array('ok' => true, 'status' => 200, 'body' => '{"valid":true,"name":"A","address":"B","requestIdentifier":"R1"}');
	};

	$first = pg_vat_check($typed, array('now' => 1000, 'http' => $http));
	pg_assert_same('valid', $first['status']);
	pg_assert_same($clean['id'], $first['id']);
	pg_assert_same(false, $first['cached']);
	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0][0]);
	pg_assert_same(PG_VAT_VIES_URL, $calls[0][1]);

	// Within the day the kept answer comes back, with the first check's time.
	$second = pg_vat_check($typed, array('now' => 1000 + 3600, 'http' => $http));
	pg_assert_same(true, $second['cached']);
	pg_assert_same(1000, $second['checked_at']);
	pg_assert_same(1, count($calls));

	// After a day VIES is asked again.
	$third = pg_vat_check($typed, array('now' => 1000 + PG_VAT_CACHE_TTL, 'http' => $http));
	pg_assert_same(false, $third['cached']);
	pg_assert_same(2, count($calls));

	@unlink(pg_vat_cache_file($clean['id']));
}

function test_vat_check_unavailable_is_not_cached()
{
	$typed = 'NL' . sprintf('%09d', mt_rand(100000000, 999999999)) . 'B01';
	$clean = pg_vat_id_clean($typed);
	@unlink(pg_vat_cache_file($clean['id']));

	$count = 0;
	$http = function () use (&$count) {
		$count++;
		return array('ok' => false, 'status' => 0, 'body' => '', 'errno' => 7);
	};

	pg_assert_same('unavailable', pg_vat_check($typed, array('now' => 10, 'http' => $http))['status']);
	pg_assert_same('unavailable', pg_vat_check($typed, array('now' => 11, 'http' => $http))['status']);
	pg_assert_same(2, $count);
	pg_assert_same(false, is_file(pg_vat_cache_file($clean['id'])));
}
