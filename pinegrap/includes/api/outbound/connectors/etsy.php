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
 * Etsy's seller taxonomy is one tree for every shop, so it is cached under
 * the provider's key (no category_key): category_tree() reads its nodes,
 * category_attributes() a leaf's properties, for the pairing of a product
 * group on Marketplace Products › Listings.
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
		'test_credentials'    => 'mp_etsy_test_credentials',
		'category_tree'       => 'mp_etsy_category_tree',
		'category_attributes' => 'mp_etsy_category_attributes'
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

/* -------------------------------------------------------------- taxonomy */

// The seller taxonomy, flattened (pg_etsy_taxonomy_rows()). Several thousand
// nested nodes make an answer of a few megabytes; the cap is generous
// because a cut answer is broken JSON, not a smaller tree. $call replaces
// the calls to Etsy in tests.
function mp_etsy_category_tree($account, $call = null) {

	if ($call === null) {

		$call = mp_etsy_api_call();

		if ($call === null) {

			return mp_error(lang('Connect an Etsy account on Connections first.'));

		}

	}

	$answer = call_user_func($call, 'GET', '/seller-taxonomy/nodes', array('timeout' => 120, 'max_body' => 33554432, 'decode' => true));

	if (!$answer['ok']) {

		return mp_error(pg_etsy_error_text($answer), pg_etsy_retry($answer));

	}

	$rows = pg_etsy_taxonomy_rows($answer['data']);

	if (!$rows) {

		return mp_error(lang('Etsy returned no categories.'));

	}

	return mp_ok(array('categories' => $rows));

}

// The properties one taxonomy node takes (getPropertiesByTaxonomyId), as
// attribute rows (pg_etsy_property_rows()). An id that is not a number is
// never sent.
function mp_etsy_category_attributes($account, $category_id, $call = null) {

	$category_id = trim((string)$category_id);

	if (preg_match('/^[0-9]{1,20}$/', $category_id) !== 1) {

		return mp_error(lang('No category to ask about.'));

	}

	if ($call === null) {

		$call = mp_etsy_api_call();

		if ($call === null) {

			return mp_error(lang('Connect an Etsy account on Connections first.'));

		}

	}

	$answer = call_user_func($call, 'GET', '/seller-taxonomy/nodes/' . $category_id . '/properties', array('timeout' => 60, 'max_body' => 8388608, 'decode' => true));

	if (!$answer['ok']) {

		return mp_error(pg_etsy_error_text($answer), pg_etsy_retry($answer));

	}

	return mp_ok(array('attributes' => pg_etsy_property_rows($answer['data'])));

}
