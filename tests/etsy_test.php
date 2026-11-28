<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Etsy (includes/fn/etsy.php and the marketplace connector
 * includes/api/outbound/connectors/etsy.php): the provider of connected
 * accounts and its x-api-key header, the account's remote selects, and the
 * choices read from the seller's shop.
 *
 * Etsy is never called. The answers in tests/fixtures/etsy/ follow the
 * response shapes of Etsy's Open API v3 documentation (November 2026) and
 * have not been recorded from a live shop.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/etsy.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/etsy.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connect.php');

// A call answering each "METHOD path" from $routes (=> array(status,
// fixture)) and writing each one it was given into $calls; an unknown one
// is 404.
function etsy_test_call($routes, &$calls)
{
	return function ($method, $path, $options = array()) use ($routes, &$calls) {
		$key = $method . ' ' . $path;
		$calls[] = $key;

		if (!isset($routes[$key])) {
			return array('ok' => false, 'status' => 404, 'data' => array(), 'error' => '');
		}

		$status = $routes[$key][0];
		$data = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/etsy/' . $routes[$key][1]), true);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'data' => $data, 'error' => '');
	};
}

function etsy_test_routes()
{
	return array(
		'GET /users/me'                                                      => array(200, 'me.json'),
		'GET /shops/87654321'                                                => array(200, 'shop.json'),
		'GET /shops/87654321/shipping-profiles'                              => array(200, 'shipping-profiles.json'),
		'GET /shops/87654321/readiness-state-definitions?limit=100&offset=0' => array(200, 'readiness-states.json'),
	);
}

/* --------------------------------------------------------------- provider */

function test_etsy_provider_signs_in_with_pkce_and_no_secret()
{
	$provider = pg_etsy_provider();

	pg_assert_same('https://www.etsy.com/oauth/connect', $provider['authorize_url']);
	pg_assert_same('https://api.etsy.com/v3/public/oauth/token', $provider['token_url']);
	pg_assert_same('none', $provider['client_auth'], 'the token request takes the keystring alone');
	pg_assert_false($provider['identity'], 'the shop is read by the connector, not by an OpenID sign-in');
	pg_assert_same('etsy', $provider['connect_purpose']);
	pg_assert_same(3600, $provider['default_ttl']);

	$url = pg_connect_authorize_url($provider, array('client_id' => 'abc123keystring'), array(
		'redirect_uri' => 'https://shop.example/connect.php',
		'scopes'       => pg_etsy_scopes(),
		'state'        => 'st',
		'challenge'    => 'ch',
	));

	pg_assert_contains('code_challenge_method=S256', $url);
	pg_assert_contains('scope=listings_r%20listings_w%20shops_r%20transactions_r%20transactions_w', $url);
}

function test_etsy_api_key_joins_keystring_and_shared_secret()
{
	pg_assert_same(array('x-api-key: abc123:s3cret'), pg_etsy_app_headers(array('client_id' => ' abc123 ', 'client_secret' => 's3cret')));
	pg_assert_same(array(), pg_etsy_app_headers(array('client_id' => 'abc123', 'client_secret' => '')), 'no secret, no header');
	pg_assert_same(array(), pg_etsy_app_headers(null));

	// The connect layer adds them to a token request of this provider only.
	pg_assert_same(array('x-api-key: abc123:s3cret'), pg_connect_app_headers(pg_etsy_provider(), array('client_id' => 'abc123', 'client_secret' => 's3cret')));
	pg_assert_same(array(), pg_connect_app_headers(array('label' => 'Other'), array('client_id' => 'abc123', 'client_secret' => 's3cret')));
	pg_assert_same(array(), pg_connect_app_headers(array('app_headers' => 'pg_no_such_function'), array()));
}

function test_etsy_purpose_asks_for_the_scopes_of_its_steps()
{
	$purposes = pg_connect_purposes();

	pg_assert_true(isset($purposes['etsy']), 'the purpose is listed');
	pg_assert_same('site', $purposes['etsy']['owner']);
	pg_assert_same(pg_etsy_scopes(), $purposes['etsy']['scopes']['etsy']);
}

/* ------------------------------------------------------- the account form */

function test_etsy_marketplace_fields_are_five_remote_selects()
{
	$providers = mp_providers();
	$fields = $providers['etsy']['fields'];

	pg_assert_same(array('shop', 'shipping_profile', 'readiness_state', 'who_made', 'when_made'), array_keys($fields));

	foreach ($fields as $field => $meta) {
		pg_assert_true(mp_provider_field_is_remote($meta), $field . ' is read from the shop');
	}

	pg_assert_true(mp_can('etsy', 'test_credentials'));
	pg_assert_true(mp_can('etsy', 'push_stock_price'), 'stock and price go through the queue');
	pg_assert_true(mp_can('etsy', 'pull_orders'), 'orders are read on the schedule');
}

/* --------------------------------------------------------- the shop's choices */

function test_etsy_test_reads_the_shop_and_its_profiles()
{
	$calls = array();
	$result = mp_etsy_test_credentials(array('credentials' => array()), etsy_test_call(etsy_test_routes(), $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same(array_keys(etsy_test_routes()), $calls, 'getMe, getShop, then the two kinds of profile');

	$choices = $result['data']['choices'];

	pg_assert_same(array('87654321' => 'LinenAndClay · USD'), $choices['shop']);
	pg_assert_same(array('201112223334' => 'Worldwide from Izmir', '201112223335' => '201112223335'), $choices['shipping_profile']);
	pg_assert_same(array('1402336022581' => 'Ready to ship · 1–3 days', '1402336022582' => 'Made to order · 2 weeks'), $choices['readiness_state']);
	pg_assert_same(array('i_did', 'collective', 'someone_else'), array_keys($choices['who_made']));
	pg_assert_same('made_to_order', key($choices['when_made']));
	pg_assert_true(isset($choices['when_made']['before_1700'], $choices['when_made']['1990s']));

	// The shop is the one choice, so it is chosen by itself; a value from
	// anywhere else is not kept.
	$clean = mp_choices_clean('etsy', $choices);
	$credentials = mp_provider_credentials('etsy', array('shop' => '87654321', 'who_made' => 'i_did', 'shipping_profile' => 'forged'), $clean);

	pg_assert_same('87654321', $credentials['shop']);
	pg_assert_same('i_did', $credentials['who_made']);
	pg_assert_same('', $credentials['shipping_profile']);
}

function test_etsy_test_says_what_the_shop_lacks()
{
	$calls = array();
	$routes = etsy_test_routes();
	$routes['GET /users/me'] = array(200, 'me-no-shop.json');

	$result = mp_etsy_test_credentials(array(), etsy_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('no shop', $result['error']);
	pg_assert_same(array('GET /users/me'), $calls, 'nothing is read without a shop');

	$routes = etsy_test_routes();
	$routes['GET /shops/87654321/shipping-profiles'] = array(200, 'shipping-profiles-empty.json');

	$result = mp_etsy_test_credentials(array(), etsy_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('shipping profile', $result['error']);

	$routes = etsy_test_routes();
	$routes['GET /shops/87654321/readiness-state-definitions?limit=100&offset=0'] = array(200, 'shipping-profiles-empty.json');

	$result = mp_etsy_test_credentials(array(), etsy_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_contains('processing profile', $result['error']);
}

function test_etsy_errors_carry_etsys_words_and_when_to_retry()
{
	$calls = array();
	$routes = etsy_test_routes();
	$routes['GET /users/me'] = array(403, 'error-forbidden.json');

	$result = mp_etsy_test_credentials(array(), etsy_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_same('Etsy: Invalid API key: should be in the format \'keystring:shared_secret\'.', $result['error']);
	pg_assert_false($result['retry'], 'a refused key is not tried again');

	pg_assert_true(pg_etsy_retry(array('status' => 429)));
	pg_assert_true(pg_etsy_retry(array('status' => 503)));
	pg_assert_true(pg_etsy_retry(array('status' => 0)));
	pg_assert_same('Etsy: HTTP 502', pg_etsy_error_text(array('status' => 502, 'data' => array(), 'error' => '')));
}

function test_etsy_shop_id_is_a_positive_number_only()
{
	pg_assert_same('87654321', pg_etsy_shop_id(array('shop_id' => 87654321)));
	pg_assert_same('', pg_etsy_shop_id(array('shop_id' => 0)));
	pg_assert_same('', pg_etsy_shop_id(array('shop_id' => '12/../34')));
	pg_assert_same('', pg_etsy_shop_id(null));
}
