<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the empty fields of a buyer card Paraşüt already keeps
 * (includes/erp/edoc/parasut.php, erp_edoc_parasut_contact_fill()): when the
 * ERP's driver (erp_edoc_parasut_contact_for()) or the order screen's
 * buttons (parasut_order_contact_for(), includes/fn/parasut.php) take a
 * card, the fields it holds empty are filled with the buyer's in one
 * contact update; a field it holds is never overwritten.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');

/**
 * A request stand-in in the driver's shape (erp_edoc_parasut_request()):
 * GET contacts/{id} answers $linked, a GET with a page lists $cards, PUT
 * answers $put (a 200 with the card it was sent by default), POST opens card
 * 7999. Every call is written into $calls as [method, path, body].
 *
 * @param array      $cards
 * @param array      $calls
 * @param array|null $put     The answer to a PUT; null for a 200
 * @param array|null $linked  data of the linked card; null for a 404
 * @return callable
 */
function parasut_contact_fill_test_request($cards, &$calls, $put = null, $linked = null)
{
	return function ($method, $path, $body = null, $context = array()) use ($cards, &$calls, $put, $linked) {
		$calls[] = array($method, $path, $body);
		$answer = array('http_code' => 200, 'body' => null, 'raw' => '{}', 'error' => '', 'duration_ms' => 1, 'login_error' => '');

		if ($method === 'PUT') {
			return ($put !== null) ? array_merge($answer, $put) : array_merge($answer, array('body' => array('data' => $body['data'])));
		}

		if ($method === 'POST') {
			return array_merge($answer, array('http_code' => 201, 'body' => array('data' => array('id' => '7999', 'type' => 'contacts'))));
		}

		if (strpos($path, 'page[number]=') === false) {
			return ($linked !== null)
				? array_merge($answer, array('body' => array('data' => $linked)))
				: array_merge($answer, array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found')))));
		}

		return array_merge($answer, array('body' => array(
			'data' => $cards,
			'meta' => array('current_page' => 1, 'total_count' => count($cards), 'total_pages' => 1),
		)));
	};
}

// The buyer of the driver's tests: a company with every field filled.
function parasut_contact_fill_test_party($overrides = array())
{
	return array_merge(array(
		'title' => 'Deneme Ticaret A.Ş.',
		'is_person' => false,
		'tax_number' => '1234567808',
		'tax_office' => 'Konak',
		'email' => 'muhasebe@deneme.com.tr',
		'phone' => '0232 000 00 00',
		'address' => 'Cumhuriyet Bulvarı 1',
		'district' => 'Konak',
		'city' => 'İzmir',
		'postcode' => '35210',
		'country_code' => 'TR',
	), $overrides);
}

// Only a field the card holds empty (missing, null or blank) and the buyer
// has is filled; a field the card holds stays, even when the buyer's value
// differs; the card's name, number and kind are never touched.
function test_parasut_contact_fill_takes_only_the_empty_fields()
{
	$wanted = erp_edoc_parasut_contact_attributes(parasut_contact_fill_test_party(), '1234567808');
	$card = array(
		'name' => 'Başka Unvan A.Ş.', 'contact_type' => 'person', 'account_type' => 'supplier', 'tax_number' => '1234567808',
		'email' => 'eski@deneme.com.tr', 'phone' => null, 'tax_office' => '  ', 'address' => 'Eski Adres 5', 'city' => '',
		'archived' => false,
	);

	pg_assert_same(array(
		'phone' => '0232 000 00 00',
		'tax_office' => 'Konak',
		'district' => 'Konak',
		'city' => 'İzmir',
		'postal_code' => '35210',
	), erp_edoc_parasut_contact_fill($card, $wanted));

	// A card that holds every field the buyer has: nothing to fill.
	pg_assert_same(array(), erp_edoc_parasut_contact_fill($wanted, $wanted), 'the card opened for this buyer');
	pg_assert_same(array(), erp_edoc_parasut_contact_fill(array_merge($wanted, array('email' => 'baska@example.com', 'city' => 'Ankara')), $wanted), 'filled fields that differ');

	// A field the buyer does not have is not filled either.
	pg_assert_same(array('address' => 'Test Sokak 1'), erp_edoc_parasut_contact_fill(array('name' => 'Ayşe Demir', 'email' => 'ayse@example.com'),
		array('name' => 'Ayşe Demir', 'email' => 'ayse@example.com', 'phone' => '', 'tax_number' => '10000000146', 'address' => 'Test Sokak 1')));
	pg_assert_same(array(), erp_edoc_parasut_contact_fill(null, array('name' => 'Ayşe Demir', 'tax_number' => '10000000146')), 'nothing to fill with');
	pg_assert_same(array('email' => 'ayse@example.com'), erp_edoc_parasut_contact_fill('not a card', array('email' => ' ayse@example.com ')), 'trimmed');
}

// The card goes back whole, every attribute it lets be written as it was
// read, with only the empty fields filled; nothing is sent when none is.
function test_parasut_contact_fill_send_writes_the_card_back_whole()
{
	$wanted = erp_edoc_parasut_contact_attributes(parasut_contact_fill_test_party(), '1234567808');
	$card = array(
		'name' => 'Başka Unvan A.Ş.', 'short_name' => 'Başka', 'contact_type' => 'company', 'account_type' => 'customer',
		'tax_number' => '1234567808', 'email' => 'eski@deneme.com.tr', 'phone' => null, 'iban' => 'TR000000000000000000000000',
		'tax_office' => 'Konak', 'address' => 'Eski Adres 5', 'district' => 'Konak', 'city' => 'İzmir', 'postal_code' => '35210',
		'archived' => false, 'balance' => '120.0', 'created_at' => '2026-01-02T10:00:00.000+03:00',
	);
	$calls = array();
	$request = function ($method, $path, $body) use (&$calls) {
		$calls[] = array($method, $path, $body);

		return array('http_code' => 200, 'body' => array('data' => $body['data']), 'raw' => '{}');
	};

	$put = erp_edoc_parasut_contact_fill_send('6001', $card, $wanted, $request);

	pg_assert_same(200, $put['http_code']);
	pg_assert_same(1, count($calls));
	pg_assert_same('PUT', $calls[0][0]);
	pg_assert_same('/contacts/6001', $calls[0][1]);
	pg_assert_same('6001', $calls[0][2]['data']['id']);
	pg_assert_same('contacts', $calls[0][2]['data']['type']);

	$sent = $calls[0][2]['data']['attributes'];

	pg_assert_same('0232 000 00 00', $sent['phone'], 'the empty field filled');
	pg_assert_same('Başka Unvan A.Ş.', $sent['name'], 'the name as it was');
	pg_assert_same('eski@deneme.com.tr', $sent['email'], 'a filled e-mail as it was');
	pg_assert_same('Eski Adres 5', $sent['address'], 'a filled address as it was');
	pg_assert_same('Başka', $sent['short_name'], 'the rest of the card as it was');
	pg_assert_same('TR000000000000000000000000', $sent['iban']);
	pg_assert_same(false, $sent['archived']);
	pg_assert_false(array_key_exists('balance', $sent), 'no balance');
	pg_assert_false(array_key_exists('created_at', $sent), 'no date');

	// Nothing to fill: no request.
	$calls = array();
	pg_assert_same(null, erp_edoc_parasut_contact_fill_send('6001', array_merge($card, array('phone' => '0212 111 11 11')), $wanted, $request));
	pg_assert_same(array(), $calls);
}

// A login that failed, a 401 after the renewed token and a 403 stop the
// send: many of them can get the API client switched off. Any other answer
// leaves the card as it is and the document goes on it.
function test_parasut_contact_fill_stops_only_on_a_refused_user()
{
	pg_assert_true(erp_edoc_parasut_contact_fill_stops(array('http_code' => 0, 'login_error' => 'Oturum açılamadı')));
	pg_assert_true(erp_edoc_parasut_contact_fill_stops(array('http_code' => 401, 'body' => null)));
	pg_assert_true(erp_edoc_parasut_contact_fill_stops(array('http_code' => 403, 'body' => null)));

	pg_assert_false(erp_edoc_parasut_contact_fill_stops(null), 'nothing sent');
	pg_assert_false(erp_edoc_parasut_contact_fill_stops(array('http_code' => 200, 'body' => array())));
	pg_assert_false(erp_edoc_parasut_contact_fill_stops(array('http_code' => 422, 'body' => array('errors' => array(array('title' => 'Geçersiz'))))));
	pg_assert_false(erp_edoc_parasut_contact_fill_stops(array('http_code' => 500, 'body' => null)));
	pg_assert_false(erp_edoc_parasut_contact_fill_stops(array('http_code' => 0, 'body' => null, 'curl_error' => 'Operation timed out')));
}

// The driver fills the card it found under the number, and the card its
// account is linked to, before it hands the card over.
function test_parasut_contact_fill_driver_fills_a_taken_card()
{
	$party = parasut_contact_fill_test_party();
	$company = array('id' => '6001', 'type' => 'contacts', 'attributes' => array('name' => 'Başka Unvan A.Ş.', 'tax_number' => '1234567808', 'archived' => false));
	$calls = array();

	pg_assert_same(array('id' => '6001', 'error' => ''), erp_edoc_parasut_contact_for($party, '1234567808', array(), parasut_contact_fill_test_request(array($company), $calls)));
	pg_assert_same(array('GET', 'PUT'), array_column($calls, 0));
	pg_assert_same('/contacts/6001', $calls[1][1]);
	pg_assert_same('Başka Unvan A.Ş.', $calls[1][2]['data']['attributes']['name'], 'the name as it was');
	pg_assert_same('Cumhuriyet Bulvarı 1', $calls[1][2]['data']['attributes']['address']);
	pg_assert_same('35210', $calls[1][2]['data']['attributes']['postal_code']);

	// The linked card is read once and filled from that read.
	$linked = array('id' => '6100', 'type' => 'contacts', 'attributes' => array('name' => 'Deneme Ticaret A.Ş.', 'tax_number' => '1234567808', 'archived' => false, 'email' => 'eski@deneme.com.tr'));
	$calls = array();

	pg_assert_same(array('id' => '6100', 'error' => ''), erp_edoc_parasut_contact_for(array_merge($party, array('provider_code' => '6100')), '1234567808', array(), parasut_contact_fill_test_request(array(), $calls, null, $linked)));
	pg_assert_same(array('GET', 'PUT'), array_column($calls, 0));
	pg_assert_same('/contacts/6100', $calls[1][1]);
	pg_assert_same('eski@deneme.com.tr', $calls[1][2]['data']['attributes']['email'], 'a filled e-mail as it was');
	pg_assert_same('Konak', $calls[1][2]['data']['attributes']['tax_office']);

	// A card that holds everything: nothing is sent.
	$full = array('id' => '6001', 'type' => 'contacts', 'attributes' => erp_edoc_parasut_contact_attributes($party, '1234567808') + array('archived' => false));
	$calls = array();

	pg_assert_same(array('id' => '6001', 'error' => ''), erp_edoc_parasut_contact_for($party, '1234567808', array(), parasut_contact_fill_test_request(array($full), $calls)));
	pg_assert_same(array('GET'), array_column($calls, 0));
}

// The driver: a refused user stops the send in Paraşüt's words; any other
// refusal of the fill hands the card over as it is.
function test_parasut_contact_fill_driver_refusals()
{
	$party = parasut_contact_fill_test_party();
	$company = array('id' => '6001', 'type' => 'contacts', 'attributes' => array('name' => 'Başka Unvan A.Ş.', 'tax_number' => '1234567808', 'archived' => false));
	$forbidden = array('http_code' => 403, 'body' => array('errors' => array(array('title' => 'Forbidden', 'detail' => 'Bu işlem için yetkiniz yok'))));
	$calls = array();
	$contact = erp_edoc_parasut_contact_for($party, '1234567808', array(), parasut_contact_fill_test_request(array($company), $calls, $forbidden));

	pg_assert_same('', $contact['id']);
	pg_assert_same(lang(array('string' => 'Paraşüt would not fill the empty fields of the buyer card: {var:1}', 'vars' => array('Forbidden: Bu işlem için yetkiniz yok'))), $contact['error']);
	pg_assert_same(array('GET', 'PUT'), array_column($calls, 0), 'nothing after the 403');

	$refused = array('http_code' => 422, 'body' => array('errors' => array(array('title' => 'Geçersiz', 'detail' => 'Posta kodu geçersiz'))));
	$calls = array();

	pg_assert_same(array('id' => '6001', 'error' => ''), erp_edoc_parasut_contact_for($party, '1234567808', array(), parasut_contact_fill_test_request(array($company), $calls, $refused)));
	pg_assert_same(array('GET', 'PUT'), array_column($calls, 0));
}

// The order screen's buttons fill the card they take the same way, with the
// attributes a new card for the order is opened with.
function test_parasut_contact_fill_legacy_fills_a_taken_card()
{
	$attributes = array('account_type' => 'customer', 'contact_type' => 'person', 'name' => 'Ayşe Demir',
		'email' => 'ayse@example.com', 'phone' => '0532 000 00 00', 'tax_number' => '10000000146', 'address' => 'Test Sokak 1', 'city' => 'Ankara');
	$card = array('id' => '7001', 'type' => 'contacts', 'attributes' => array('name' => 'Ayşe Demir', 'email' => 'ayse@example.com',
		'contact_type' => 'person', 'account_type' => 'customer', 'tax_number' => '10000000146', 'address' => 'Eski Sokak 2', 'archived' => false));
	$calls = array();
	$request = function ($method, $path, $body) use (&$calls, $card) {
		$calls[] = array($method, $path, $body);

		return ($method === 'PUT')
			? array('http_code' => 200, 'body' => array('data' => $body['data']), 'raw' => '{}')
			: array('http_code' => 200, 'body' => array('data' => array($card), 'meta' => array('total_pages' => 1)), 'raw' => '{}');
	};

	pg_assert_same(array('id' => '7001', 'reused' => true, 'error' => ''), parasut_order_contact_for('10000000146', $attributes, $request));
	pg_assert_same(array('GET', 'PUT'), array_column($calls, 0));
	pg_assert_same('/contacts/7001', $calls[1][1]);

	$sent = $calls[1][2]['data']['attributes'];

	pg_assert_same('0532 000 00 00', $sent['phone']);
	pg_assert_same('Ankara', $sent['city']);
	pg_assert_same('Eski Sokak 2', $sent['address'], 'a filled address as it was');

	// A refused user stops the button in Paraşüt's words; another refusal
	// leaves the card as it is.
	foreach (array(401 => true, 403 => true, 422 => false, 500 => false) as $code => $stops) {
		$calls = array();
		$answer = array('http_code' => $code, 'body' => array('errors' => array(array('title' => 'Hata ' . $code))), 'raw' => '{}');
		$refusing = function ($method, $path, $body) use (&$calls, $card, $answer) {
			$calls[] = array($method, $path, $body);

			return ($method === 'PUT') ? $answer : array('http_code' => 200, 'body' => array('data' => array($card), 'meta' => array('total_pages' => 1)), 'raw' => '{}');
		};
		$contact = parasut_order_contact_for('10000000146', $attributes, $refusing);

		pg_assert_same(array('GET', 'PUT'), array_column($calls, 0), 'HTTP ' . $code);
		pg_assert_same($stops
			? array('id' => '', 'reused' => false, 'error' => lang(array('string' => 'Paraşüt would not fill the empty fields of the buyer card: {var:1}', 'vars' => array('Hata ' . $code))))
			: array('id' => '7001', 'reused' => true, 'error' => ''), $contact, 'HTTP ' . $code);
	}
}
