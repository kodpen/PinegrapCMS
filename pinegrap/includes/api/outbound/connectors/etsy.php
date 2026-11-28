<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Etsy as a marketplace connector. The seller signs in once on Connections
 * (the etsy provider of connected accounts, purpose etsy), whose token
 * pg_connect_api() renews; a marketplace account holds no key of its own,
 * only the shop and what a listing names there: a shipping profile, a
 * processing profile (readiness state), who made the goods and when.
 * test_credentials() reads the shop from the sign-in (getMe, then getShop)
 * and its profiles, and answers them as data['choices'] for the account's
 * selects (mp_account_choices_merge()). One account is one shop, in the
 * shop's currency.
 *
 * Every call carries the application's x-api-key header
 * (pg_etsy_app_headers()). The Etsy functions of their own (scopes, the
 * provider, the parsers of these answers) are in includes/fn/etsy.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_INIT_LOADED')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/etsy.php');

function mp_etsy_connector() {

	return array(
		'test_credentials' => 'mp_etsy_test_credentials'
	);

}

// The id of the connected account the shop signs in through, or 0.
function mp_etsy_connection_id() {

	foreach (pg_connect_accounts(array('provider' => 'etsy', 'user_id' => 0, 'purpose' => 'etsy')) as $row) {

		return (int)$row['id'];

	}

	return 0;

}

// A call to the Open API with the seller's token and the application's
// x-api-key: function ($method, $path, $options = array()) answering
// array(ok, status, data, error), data always an array. Null when there is no
// connection.
function mp_etsy_api_call() {

	$connection_id = mp_etsy_connection_id();

	if ($connection_id <= 0) {

		return null;

	}

	$key_headers = pg_etsy_app_headers(pg_connect_app('etsy'));

	return function ($method, $path, $options = array()) use ($connection_id, $key_headers) {

		$headers = isset($options['headers']) ? (array)$options['headers'] : array();

		$options['headers'] = array_merge(array('Accept: application/json'), $key_headers, $headers);
		$options['timeout'] = isset($options['timeout']) ? $options['timeout'] : 30;
		$options['agent'] = 'marketplace';

		$result = pg_connect_api($connection_id, $method, pg_etsy_api_base() . $path, $options);

		$result['data'] = is_array($result['data']) ? $result['data'] : array();

		return $result;

	};

}

// What the seller's shop holds: the shop itself, its shipping profiles and
// its processing profiles, and the fixed who_made and when_made values.
// $call is mp_etsy_api_call()'s shape; tests pass recorded answers. Answers
// mp_ok() with data['choices'], or mp_error() saying what to set up on Etsy.
function mp_etsy_read_choices($call) {

	$me = call_user_func($call, 'GET', '/users/me');

	if (!$me['ok']) {

		return mp_error(pg_etsy_error_text($me), pg_etsy_retry($me));

	}

	$shop_id = pg_etsy_shop_id($me['data']);

	if ($shop_id === '') {

		return mp_error(lang('The Etsy account has no shop yet. Open a shop on Etsy, then test again.'));

	}

	$shop = call_user_func($call, 'GET', '/shops/' . $shop_id);

	if (!$shop['ok']) {

		return mp_error(pg_etsy_error_text($shop), pg_etsy_retry($shop));

	}

	$profiles = call_user_func($call, 'GET', '/shops/' . $shop_id . '/shipping-profiles');

	if (!$profiles['ok']) {

		return mp_error(pg_etsy_error_text($profiles), pg_etsy_retry($profiles));

	}

	$readiness = call_user_func($call, 'GET', '/shops/' . $shop_id . '/readiness-state-definitions?limit=100&offset=0');

	if (!$readiness['ok']) {

		return mp_error(pg_etsy_error_text($readiness), pg_etsy_retry($readiness));

	}

	$choices = array(
		'shop'             => pg_etsy_shop_choice($shop_id, $shop['data']),
		'shipping_profile' => pg_etsy_shipping_profile_choices($profiles['data']),
		'readiness_state'  => pg_etsy_readiness_choices($readiness['data']),
		'who_made'         => pg_etsy_who_made_choices(),
		'when_made'        => pg_etsy_when_made_choices()
	);

	if (!$choices['shipping_profile']) {

		return mp_error(lang('The Etsy shop has no shipping profile yet. Create one in Shop Manager under Settings › Delivery settings, then test again.'));

	}

	if (!$choices['readiness_state']) {

		return mp_error(lang('The Etsy shop has no processing profile yet. Create one in Shop Manager under Settings › Delivery settings, then test again.'));

	}

	return mp_ok(array('choices' => $choices));

}

// The connection to Etsy works and the shop's choices are read. $call
// replaces the calls to Etsy in tests (mp_etsy_read_choices()).
function mp_etsy_test_credentials($account, $call = null) {

	if ($call === null) {

		$call = mp_etsy_api_call();

		if ($call === null) {

			return mp_error(lang('Connect an Etsy account on Connections first.'));

		}

	}

	return mp_etsy_read_choices($call);

}
