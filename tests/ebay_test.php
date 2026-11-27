<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for eBay (includes/fn/ebay.php and the marketplace connector
 * includes/api/outbound/connectors/ebay.php): the two providers of connected
 * accounts, the account's remote selects, the choices read from the seller's
 * account, and the account deletion address (challenge, signature, notice).
 *
 * eBay is never called. The answers in tests/fixtures/ebay/ follow the
 * response shapes of eBay's public API documentation (November 2026) and
 * have not been recorded from a live account. The notice's signature was
 * made with a key generated for this test; its public half is the fixture
 * public-key.json, and no private key is kept.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/ebay.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');

function ebay_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/ebay/' . $name);
}

// The notice eBay's guide shows, as one line.
function ebay_test_notice()
{
	return '{"metadata":{"topic":"MARKETPLACE_ACCOUNT_DELETION","schemaVersion":"1.0","deprecated":false},"notification":{"notificationId":"49feeaeb-4982-42d9-a377-9645b8479411_33f7e043-fed8-442b-9d44-791923bd9a6d","eventDate":"2021-03-19T20:43:59.462Z","publishDate":"2021-03-19T20:43:59.679Z","publishAttemptCount":1,"data":{"username":"test_user","userId":"ma8vp1jySJC","eiasToken":"nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wJnY+gAZGEpwmdj6x9nY+seQ=="}}}';
}

// SHA-1 and SHA-256 ECDSA signatures of ebay_test_notice() by the key of
// public-key.json.
function ebay_test_signature($digest)
{
	return ($digest === 'SHA256')
		? 'MEUCIHgNyCgSW27YOcZbsx6uuTqBTxJNy3VZvKjov2pZiOwlAiEA8txETahXpbaG2P/gX4C8bbFe9Sdpp4NOYnaKOcqPmsw='
		: 'MEUCIAjuUtNik5m6j4wUoKU8MuSw4wu8AoZ+E61rE8OLJlzoAiEAylSfYG8+dqk8vZlAhSnlpcIcQN4ZXE/ga2+W9swuduw=';
}

function ebay_test_pem()
{
	$data = json_decode(ebay_test_fixture('public-key.json'), true);

	return pg_ebay_pem($data['key']);
}

// A call answering each path from $routes (path => array(status, fixture))
// and writing each path it was given into $calls; an unknown path is 404.
function ebay_test_call($routes, &$calls)
{
	return function ($path) use ($routes, &$calls) {
		$calls[] = $path;

		if (!isset($routes[$path])) {
			return array('ok' => false, 'status' => 404, 'data' => array(), 'error' => '');
		}

		$status = $routes[$path][0];

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status,
			'data' => json_decode(ebay_test_fixture($routes[$path][1]), true), 'error' => '');
	};
}

function ebay_test_routes($site = 'EBAY_US')
{
	return array(
		'/sell/account/v1/program/get_opted_in_programs'                   => array(200, 'opted-in-programs.json'),
		'/sell/inventory/v1/location?limit=100&offset=0'                   => array(200, 'locations.json'),
		'/sell/account/v1/fulfillment_policy?marketplace_id=' . $site      => array(200, 'fulfillment-policies.json'),
		'/sell/account/v1/payment_policy?marketplace_id=' . $site          => array(200, 'payment-policies.json'),
		'/sell/account/v1/return_policy?marketplace_id=' . $site           => array(200, 'return-policies.json'),
	);
}

/* ------------------------------------------------------------- providers */

function test_ebay_providers_keep_production_and_sandbox_apart()
{
	$production = pg_ebay_provider('production');
	$sandbox = pg_ebay_provider('sandbox');

	pg_assert_same('https://auth.ebay.com/oauth2/authorize', $production['authorize_url']);
	pg_assert_same('https://api.ebay.com/identity/v1/oauth2/token', $production['token_url']);
	pg_assert_same('https://auth.sandbox.ebay.com/oauth2/authorize', $sandbox['authorize_url']);
	pg_assert_same('https://api.sandbox.ebay.com/identity/v1/oauth2/token', $sandbox['token_url']);
	pg_assert_same('ebay', pg_ebay_provider_key('production'));
	pg_assert_same('ebay_sandbox', pg_ebay_provider_key('sandbox'));
	pg_assert_same('basic', $production['client_auth'], 'eBay takes the keyset as Basic authentication');
	pg_assert_same('runame', $production['redirect_option'], 'the RuName stands for the redirect address');
	pg_assert_false($production['identity'], 'no OpenID sign-in');

	// The deletion address is production's only: the sandbox gives keys
	// without it.
	pg_assert_true(isset($production['copy_values']['deletion'], $production['copy_values']['token']));
	pg_assert_same(array(), $sandbox['copy_values']);
}

function test_ebay_purpose_asks_for_the_three_sell_scopes()
{
	pg_assert_same(array(
		'https://api.ebay.com/oauth/api_scope/sell.inventory',
		'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
		'https://api.ebay.com/oauth/api_scope/sell.account',
	), pg_ebay_scopes());

	$purposes = pg_connect_purposes();

	pg_assert_true(isset($purposes['ebay']), 'the purpose is listed');
	pg_assert_same('site', $purposes['ebay']['owner']);
	pg_assert_same(pg_ebay_scopes(), $purposes['ebay']['scopes']['ebay']);
	pg_assert_same(pg_ebay_scopes(), $purposes['ebay']['scopes']['ebay_sandbox']);
}

function test_ebay_redirect_is_the_runame_the_application_names()
{
	$provider = pg_ebay_provider('production');

	pg_assert_same('Jane_Doe-JaneDoeS-PRD-1a2b3c', pg_connect_provider_redirect($provider, array('options' => array('runame' => ' Jane_Doe-JaneDoeS-PRD-1a2b3c '))));
	pg_assert_same('', pg_connect_provider_redirect($provider, array('options' => array())), 'no RuName, no redirect');
	pg_assert_same('', pg_connect_provider_redirect($provider, null));
}

function test_ebay_sites_have_a_currency_and_an_option()
{
	$sites = pg_ebay_sites();
	$options = pg_ebay_site_options();

	pg_assert_same(array_keys($sites), array_keys($options));
	pg_assert_same('ebay.co.uk · GBP', $options['EBAY_GB']);

	foreach ($sites as $id => $site) {
		pg_assert_true((bool) preg_match('/^EBAY_[A-Z]{2}$/', $id), $id . ': a marketplace id');
		pg_assert_true((bool) preg_match('/^[A-Z]{3}$/', $site['currency']), $id . ': an ISO currency');
	}
}

/* ------------------------------------------------------- the account form */

function test_ebay_marketplace_fields_are_the_site_and_four_remote_selects()
{
	$providers = mp_providers();
	$fields = $providers['ebay']['fields'];

	pg_assert_same(array('environment', 'site', 'location', 'fulfillment_policy', 'payment_policy', 'return_policy'), array_keys($fields));
	pg_assert_false(mp_provider_field_is_remote($fields['site']), 'the site is a select of its own');

	foreach (array('location', 'fulfillment_policy', 'payment_policy', 'return_policy') as $field) {
		pg_assert_true(mp_provider_field_is_remote($fields[$field]), $field . ' is read from the account');
	}

	pg_assert_true(mp_can('ebay', 'test_credentials'));
	pg_assert_false(mp_can('ebay', 'pull_orders'), 'nothing it does not do yet');
}

function test_ebay_remote_select_keeps_only_a_value_the_account_holds()
{
	$choices = array('location' => array('WH-SJ' => 'San Jose warehouse'));

	$credentials = mp_provider_credentials('ebay', array('site' => 'EBAY_DE', 'location' => 'WH-SJ', 'payment_policy' => 'forged'), $choices);

	pg_assert_same('EBAY_DE', $credentials['site']);
	pg_assert_same('WH-SJ', $credentials['location']);
	pg_assert_same('', $credentials['payment_policy'], 'a value from anywhere else is not kept');

	$credentials = mp_provider_credentials('ebay', array('site' => 'EBAY_XX'));

	pg_assert_same('EBAY_US', $credentials['site'], 'an unknown site falls back to the default');
	pg_assert_same('', $credentials['location'], 'nothing to choose from before a test');
}

function test_ebay_choices_are_cleaned_before_they_are_kept()
{
	$long = str_repeat('x', 150);
	$many = array();

	for ($i = 0; $i < 120; $i++) {
		$many['P' . $i] = 'Policy ' . $i;
	}

	$clean = mp_choices_clean('ebay', array(
		'location'       => array('WH-1' => '  Main  ', 'WH-2' => '', '' => 'no key', $long => 'too long', 'WH-3' => array('not text')),
		'payment_policy' => $many,
		'site'           => array('EBAY_US' => 'not remote'),
		'unknown'        => array('a' => 'b'),
	));

	pg_assert_same(array('location', 'payment_policy'), array_keys($clean));
	pg_assert_same(array('WH-1' => 'Main', 'WH-2' => 'WH-2'), $clean['location']);
	pg_assert_same(100, count($clean['payment_policy']));
	pg_assert_same(array(), mp_choices_clean('trendyol', array('environment' => array('x' => 'y'))));
}

function test_ebay_choices_merge_clears_a_stale_value_and_picks_a_sole_one()
{
	$credentials = array('environment' => 'production', 'site' => 'EBAY_US', 'location' => 'GONE', 'fulfillment_policy' => 'F2', 'payment_policy' => '');

	$merged = mp_account_choices_merge('ebay', $credentials, array(
		'location'           => array('WH-1' => 'Main', 'WH-2' => 'Second'),
		'fulfillment_policy' => array('F1' => 'Standard', 'F2' => 'Freight'),
		'payment_policy'     => array('P1' => 'Immediate'),
		'return_policy'      => array(),
	));

	pg_assert_same('', $merged['location'], 'a location the account no longer holds is cleared');
	pg_assert_same('F2', $merged['fulfillment_policy'], 'a chosen policy still there stays');
	pg_assert_same('P1', $merged['payment_policy'], 'the only policy is chosen');
	pg_assert_same('', $merged['return_policy']);
	pg_assert_same('EBAY_US', $merged['site'], 'the other fields are kept');
	pg_assert_same(array('WH-1' => 'Main', 'WH-2' => 'Second'), $merged['choices']['location']);
}

/* ------------------------------------------------------ the seller's choices */

function test_ebay_choices_read_from_the_recorded_answers()
{
	pg_assert_same(array('WH-SJ' => 'San Jose warehouse', 'WH-NY' => 'WH-NY'),
		pg_ebay_location_choices(json_decode(ebay_test_fixture('locations.json'), true)), 'a disabled location is left out');
	pg_assert_same(array('6196932000' => 'Standard shipping', '6196940000' => 'Freight'),
		pg_ebay_policy_choices(json_decode(ebay_test_fixture('fulfillment-policies.json'), true), 'fulfillment'));
	pg_assert_same(array('6196944000' => 'Immediate payment'),
		pg_ebay_policy_choices(json_decode(ebay_test_fixture('payment-policies.json'), true), 'payment'));
	pg_assert_same(array('6196948000' => '30 days, buyer pays'),
		pg_ebay_policy_choices(json_decode(ebay_test_fixture('return-policies.json'), true), 'return'));
	pg_assert_same(array(), pg_ebay_policy_choices(null, 'return'));
}

function test_ebay_test_credentials_answers_the_choices_of_the_site()
{
	$calls = array();
	$account = array('id' => 4, 'provider' => 'ebay', 'credentials' => array('environment' => 'sandbox', 'site' => 'EBAY_GB'));

	$result = mp_ebay_test_credentials($account, ebay_test_call(ebay_test_routes('EBAY_GB'), $calls));

	pg_assert_true($result['ok'], 'the test passes: ' . $result['error']);
	pg_assert_same(array('location', 'fulfillment_policy', 'payment_policy', 'return_policy'), array_keys($result['data']['choices']));
	pg_assert_same(array('6196944000' => 'Immediate payment'), $result['data']['choices']['payment_policy']);
	pg_assert_contains('/sell/account/v1/fulfillment_policy?marketplace_id=EBAY_GB', implode("\n", $calls), 'policies of the account\'s site');
}

function test_ebay_test_credentials_says_what_to_set_up_on_ebay()
{
	$calls = array();
	$routes = ebay_test_routes();
	$routes['/sell/account/v1/program/get_opted_in_programs'] = array(200, 'empty-return-policies.json');

	$result = mp_ebay_read_choices('EBAY_US', ebay_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('business policies', $result['error'], 'not opted in');
	pg_assert_same(1, count($calls), 'nothing else is read');

	$calls = array();
	$routes = ebay_test_routes('EBAY_DE');
	$routes['/sell/account/v1/return_policy?marketplace_id=EBAY_DE'] = array(200, 'empty-return-policies.json');

	$result = mp_ebay_read_choices('EBAY_DE', ebay_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('Return policy', $result['error']);
	pg_assert_contains('ebay.de', $result['error']);

	$calls = array();
	$routes = ebay_test_routes();
	$routes['/sell/inventory/v1/location?limit=100&offset=0'] = array(200, 'empty-return-policies.json');

	$result = mp_ebay_read_choices('EBAY_US', ebay_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('inventory location', $result['error']);
}

function test_ebay_test_credentials_passes_on_ebays_refusal()
{
	$calls = array();
	$routes = ebay_test_routes();
	$routes['/sell/account/v1/program/get_opted_in_programs'] = array(403, 'error-not-authorized.json');

	$result = mp_ebay_read_choices('EBAY_US', ebay_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_same('eBay: Access denied', $result['error']);
	pg_assert_false($result['retry'], 'a refusal is not tried again');
	pg_assert_true(mp_ebay_retry(array('status' => 503)), 'a fault on eBay\'s side is');
	pg_assert_true(mp_ebay_retry(array('status' => 429)));
	pg_assert_same('eBay: HTTP 500', mp_ebay_error_text(array('status' => 500, 'data' => array(), 'error' => '')));
}

/* ------------------------------------------------------ account deletion */

function test_ebay_challenge_is_the_hash_of_code_token_and_address()
{
	// The example of eBay's guide: SHA-256 of the three, in that order.
	pg_assert_same(hash('sha256', 'a8628072-3d33-45ee-9004-bee86830a22d' . 'token_123' . 'https://example.com/ebay_account_deletion.php'),
		pg_ebay_challenge_response('a8628072-3d33-45ee-9004-bee86830a22d', 'token_123', 'https://example.com/ebay_account_deletion.php'));
	pg_assert_same(64, strlen(pg_ebay_challenge_response('x', 'y', 'z')));
}

function test_ebay_verification_token_is_derived_from_the_encryption_key()
{
	$token = pg_ebay_deletion_token();

	if (defined('ENCRYPTION_KEY') && ((string) ENCRYPTION_KEY !== '')) {
		pg_assert_true((bool) preg_match('/^[0-9a-f]{64}$/', $token), 'a hexadecimal token');
		pg_assert_same($token, pg_ebay_deletion_token(), 'the same every time');
	} else {
		pg_assert_same('', $token, 'no key, no token');
	}
}

function test_ebay_signature_header_is_read()
{
	$header = base64_encode(json_encode(array('alg' => 'ecdsa', 'kid' => '99345e52-43fe-4ecf-8ae8-6a1fd0a8f2b2', 'signature' => ebay_test_signature('SHA1'), 'digest' => 'sha1')));
	$read = pg_ebay_signature_header($header);

	pg_assert_same('ECDSA', $read['alg']);
	pg_assert_same('99345e52-43fe-4ecf-8ae8-6a1fd0a8f2b2', $read['kid']);
	pg_assert_same('SHA1', $read['digest']);
	pg_assert_same(base64_decode(ebay_test_signature('SHA1')), $read['signature']);

	pg_assert_same(null, pg_ebay_signature_header(''));
	pg_assert_same(null, pg_ebay_signature_header('not base64 !'));
	pg_assert_same(null, pg_ebay_signature_header(base64_encode('{"kid":"../x","signature":"AAAA"}')), 'a key id that is not one');
	pg_assert_same(null, pg_ebay_signature_header(base64_encode('{"kid":"k1"}')), 'no signature');
}

function test_ebay_public_key_is_wrapped_for_openssl()
{
	$pem = ebay_test_pem();

	pg_assert_same(0, strpos($pem, "-----BEGIN PUBLIC KEY-----\n"));
	pg_assert_contains("\n-----END PUBLIC KEY-----\n", $pem);
	pg_assert_same('', pg_ebay_pem('-----BEGIN PUBLIC KEY-----not a key!-----END PUBLIC KEY-----'));
	pg_assert_same('', pg_ebay_pem(''));
}

function test_ebay_signature_is_checked_against_the_key()
{
	if (!function_exists('openssl_verify')) {
		pg_assert_true(true, 'openssl is not loaded');

		return;
	}

	$pem = ebay_test_pem();
	$body = ebay_test_notice();
	$sha1 = base64_decode(ebay_test_signature('SHA1'));

	pg_assert_true(pg_ebay_signature_valid($body, $sha1, $pem, 'SHA1'), 'the body as sent');
	pg_assert_true(pg_ebay_signature_valid($body, base64_decode(ebay_test_signature('SHA256')), $pem, 'SHA256'));

	// The same notice with spaces: eBay's libraries also check the JSON
	// written again without them.
	pg_assert_true(pg_ebay_signature_valid(json_encode(json_decode($body, true), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), $sha1, $pem, 'SHA1'), 'the body re-serialised');

	pg_assert_false(pg_ebay_signature_valid(str_replace('test_user', 'other_user', $body), $sha1, $pem, 'SHA1'), 'another body');
	pg_assert_false(pg_ebay_signature_valid($body, $sha1, $pem, 'SHA256'), 'another digest');
	pg_assert_false(pg_ebay_signature_valid($body, $sha1, $pem, 'MD5'), 'an unknown digest');
	pg_assert_false(pg_ebay_signature_valid($body, $sha1, '', 'SHA1'), 'no key');
}

function test_ebay_notice_payload_is_read()
{
	pg_assert_same(array(
		'notification_id' => '49feeaeb-4982-42d9-a377-9645b8479411_33f7e043-fed8-442b-9d44-791923bd9a6d',
		'username'        => 'test_user',
		'user_id'         => 'ma8vp1jySJC',
	), pg_ebay_deletion_payload(ebay_test_notice()));

	pg_assert_same(null, pg_ebay_deletion_payload(str_replace('MARKETPLACE_ACCOUNT_DELETION', 'ITEM_SOLD', ebay_test_notice())), 'another topic');
	pg_assert_same(null, pg_ebay_deletion_payload(str_replace('"username":"test_user"', '"username":""', ebay_test_notice())), 'no member');
	pg_assert_same(null, pg_ebay_deletion_payload('not json'));
}

function test_ebay_notice_becomes_an_erasure_request_naming_the_orders()
{
	$payload = pg_ebay_deletion_payload(ebay_test_notice());

	$request = pg_ebay_deletion_request($payload, array(
		array('id' => 31, 'order_number' => '1031', 'email' => ''),
		array('id' => 30, 'order_number' => '1030', 'email' => 'buyer@example.com'),
		array('id' => 12, 'order_number' => '', 'email' => 'old@example.com'),
	));

	pg_assert_same('erasure', $request['kind']);
	pg_assert_same('buyer@example.com', $request['email'], 'the address of the latest order that has one');
	pg_assert_same('test_user', $request['name']);
	pg_assert_contains('1031, 1030, #12', $request['message']);

	pg_assert_same(null, pg_ebay_deletion_request($payload, array()), 'not a buyer of this shop');
	pg_assert_same(null, pg_ebay_deletion_request($payload, array(array('id' => 5, 'order_number' => '5', 'email' => ''))), 'no address to answer');
}

function test_ebay_privacy_request_says_where_it_came_from()
{
	pg_assert_same(lang('From the buyer\'s latest order'), pg_privacy_request_source_text('marketplace', 'address'));
	pg_assert_contains('eBay', pg_privacy_request_source_text('marketplace', 'hint'));
	pg_assert_same(lang('Typed in by staff'), pg_privacy_request_source_text('staff', 'address'));
	pg_assert_same(lang('Proven by its link'), pg_privacy_request_source_text('form', 'address'));

	$labels = pg_privacy_request_labels();

	pg_assert_true(isset($labels['sources']['marketplace']), 'a source of its own');
}

function test_ebay_deletion_address_is_a_credential_endpoint_of_the_waf()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');

	pg_assert_contains("'sms_webhook.php', 'ebay_account_deletion.php');", $waf);
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/ebay_account_deletion.php'), 'the address is a file at the root');
}
