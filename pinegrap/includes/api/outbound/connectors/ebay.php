<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * eBay as a marketplace connector. The seller signs in once on Connections
 * (the ebay or ebay_sandbox provider of connected accounts, purpose ebay),
 * whose token pg_connect_api() renews; a marketplace account holds no key of
 * its own, only the environment, the eBay site it sells on and what it lists
 * with there: an inventory location and a shipping, payment and return
 * business policy. test_credentials() reads those from the seller's account
 * and answers them as data['choices'] for the account's selects
 * (mp_account_choices_merge()).
 *
 * Categories and item specifics come from the Taxonomy API with an
 * application token (the client credentials grant of the same keyset), and
 * are cached under the account's site (mp_ebay_category_key()): eBay keeps
 * one category tree a site.
 *
 * The eBay functions of their own (sites, scopes, the parsers of these
 * answers) are in includes/fn/ebay.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_INIT_LOADED')) {
	exit;
}

function mp_ebay_connector() {

	return array(
		'test_credentials'    => 'mp_ebay_test_credentials',
		'category_key'        => 'mp_ebay_category_key',
		'category_tree'       => 'mp_ebay_category_tree',
		'category_attributes' => 'mp_ebay_category_attributes'
	);

}

// 'production' or 'sandbox', as the account says.
function mp_ebay_environment($account) {

	$credentials = (isset($account['credentials']) && is_array($account['credentials'])) ? $account['credentials'] : array();

	return (isset($credentials['environment']) && ($credentials['environment'] === 'sandbox')) ? 'sandbox' : 'production';

}

// The eBay site (marketplace id) the account sells on; EBAY_US when the
// stored one is unknown.
function mp_ebay_site($account) {

	$credentials = (isset($account['credentials']) && is_array($account['credentials'])) ? $account['credentials'] : array();

	$site = isset($credentials['site']) ? (string)$credentials['site'] : '';

	$sites = pg_ebay_sites();

	return isset($sites[$site]) ? $site : 'EBAY_US';

}

// The id of the connected account the environment signs in through, or 0.
function mp_ebay_connection_id($environment) {

	foreach (pg_connect_accounts(array('provider' => pg_ebay_provider_key($environment), 'user_id' => 0, 'purpose' => 'ebay')) as $row) {

		return (int)$row['id'];

	}

	return 0;

}

// The message of an eBay error answer ({"errors": [{"message": …}]}), or
// the HTTP status. Pure.
function mp_ebay_error_text($result) {

	$data = (isset($result['data']) && is_array($result['data'])) ? $result['data'] : array();

	if (isset($data['errors'][0]['message']) && is_string($data['errors'][0]['message']) && ($data['errors'][0]['message'] !== '')) {

		return mb_substr('eBay: ' . $data['errors'][0]['message'], 0, 255);

	}

	if (isset($result['error']) && ((string)$result['error'] !== '')) {

		return mb_substr((string)$result['error'], 0, 255);

	}

	return 'eBay: HTTP ' . (int)$result['status'];

}

// Whether a failed call is worth trying again later: no answer, a rate
// limit or a fault on eBay's side. Pure.
function mp_ebay_retry($result) {

	$status = (int)$result['status'];

	return ($status === 0) || ($status === 429) || ($status >= 500);

}

// What the seller's account holds for one site: whether business policies
// are on, the inventory locations and the three kinds of policy. $call is
// function ($path) answering array(ok, status, data, error) for a GET on the
// environment's API host; tests pass recorded answers. Answers mp_ok() with
// data['choices'], or mp_error() saying what to set up on eBay.
function mp_ebay_read_choices($site, $call) {

	$programs = call_user_func($call, '/sell/account/v1/program/get_opted_in_programs');

	if (!$programs['ok']) {

		return mp_error(mp_ebay_error_text($programs), mp_ebay_retry($programs));

	}

	$opted_in = false;

	foreach ((isset($programs['data']['programs']) && is_array($programs['data']['programs'])) ? $programs['data']['programs'] : array() as $program) {

		if (isset($program['programType']) && ($program['programType'] === 'SELLING_POLICY_MANAGEMENT')) {

			$opted_in = true;

		}

	}

	if (!$opted_in) {

		return mp_error(lang('The eBay account does not use business policies yet. Turn them on in eBay (Account › Business policies), create a shipping, payment and return policy, then test again.'));

	}

	$locations = call_user_func($call, '/sell/inventory/v1/location?limit=100&offset=0');

	if (!$locations['ok']) {

		return mp_error(mp_ebay_error_text($locations), mp_ebay_retry($locations));

	}

	$choices = array('location' => pg_ebay_location_choices($locations['data']));

	$missing = array();

	foreach (array('fulfillment' => 'Shipping policy', 'payment' => 'Payment policy', 'return' => 'Return policy') as $kind => $label) {

		$policies = call_user_func($call, '/sell/account/v1/' . $kind . '_policy?marketplace_id=' . rawurlencode($site));

		if (!$policies['ok']) {

			return mp_error(mp_ebay_error_text($policies), mp_ebay_retry($policies));

		}

		$choices[$kind . '_policy'] = pg_ebay_policy_choices($policies['data'], $kind);

		if (!$choices[$kind . '_policy']) {

			$missing[] = lang($label);

		}

	}

	if (!$choices['location']) {

		return mp_error(lang('The eBay account has no inventory location yet. Add the address the goods ship from as a location on eBay, then test again.'));

	}

	if ($missing) {

		$sites = pg_ebay_sites();

		return mp_error(lang(array(
			'string' => 'The eBay account has no {var:1} for {var:2} yet. Create it in eBay\'s business policies, then test again.',
			'vars'   => array(implode(', ', $missing), $sites[$site]['domain'])
		)));

	}

	return mp_ok(array('choices' => $choices));

}

// The connection to eBay works and the account's choices are read. $call
// replaces the calls to eBay in tests (mp_ebay_read_choices()).
function mp_ebay_test_credentials($account, $call = null) {

	$environment = mp_ebay_environment($account);

	if ($call === null) {

		$connection_id = mp_ebay_connection_id($environment);

		if ($connection_id <= 0) {

			return mp_error(($environment === 'sandbox')
				? lang('Connect an eBay (sandbox) account on Connections first.')
				: lang('Connect an eBay account on Connections first.'));

		}

		$hosts = pg_ebay_hosts($environment);

		$call = function ($path) use ($connection_id, $hosts) {

			$result = pg_connect_api($connection_id, 'GET', $hosts['api'] . $path, array(
				'headers'  => array('Accept: application/json'),
				'timeout'  => 30,
				'max_body' => 1048576,
				'agent'    => 'marketplace'
			));

			$result['data'] = is_array($result['data']) ? $result['data'] : array();

			return $result;

		};

	}

	return mp_ebay_read_choices(mp_ebay_site($account), $call);

}

// The key the account's categories are cached under: one tree a site and
// environment (pg_ebay_category_key()).
function mp_ebay_category_key($account) {

	return pg_ebay_category_key(mp_ebay_environment($account), mp_ebay_site($account));

}

// A GET on the Taxonomy API of the account's environment with an
// application token, answering array(ok, status, data, error) as the
// test's $call does; $max_body caps the answer kept.
function mp_ebay_taxonomy_call($account) {

	$environment = mp_ebay_environment($account);

	$token = null;

	return function ($path, $max_body = 4194304) use ($environment, &$token) {

		require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

		if ($token === null) {

			$token = pg_ebay_app_token($environment);

		}

		if ($token === '') {

			return array('ok' => false, 'status' => 0, 'data' => array(), 'error' => lang('eBay refused the application\'s keys. Check the eBay application on Connections.'));

		}

		$hosts = pg_ebay_hosts($environment);

		$result = api_http_request('GET', $hosts['api'] . $path, array(
			'headers'  => array('Authorization: Bearer ' . $token, 'Accept: application/json'),
			'decode'   => true,
			'timeout'  => 120,
			'max_body' => $max_body,
			'agent'    => 'marketplace'
		));

		$data = json_decode((string)$result['body'], true);

		$result['data'] = is_array($data) ? $data : array();

		return $result;

	};

}

// The id of the site's category tree (getDefaultCategoryTreeId).
function mp_ebay_category_tree_id($account, $call) {

	$answer = call_user_func($call, '/commerce/taxonomy/v1/get_default_category_tree_id?marketplace_id=' . rawurlencode(mp_ebay_site($account)));

	if (!$answer['ok']) {

		return mp_error(mp_ebay_error_text($answer), mp_ebay_retry($answer));

	}

	$id = isset($answer['data']['categoryTreeId']) && is_scalar($answer['data']['categoryTreeId']) ? trim((string)$answer['data']['categoryTreeId']) : '';

	if (preg_match('/^[0-9]{1,10}$/', $id) !== 1) {

		return mp_error(lang('eBay named no category tree for this site.'));

	}

	return mp_ok(array('tree_id' => $id));

}

// The site's whole category tree, flattened (pg_ebay_category_rows()). The
// tree of a large site is tens of megabytes; the cap is generous because a
// cut answer is not a smaller tree but broken JSON. $call replaces the calls
// to eBay in tests.
function mp_ebay_category_tree($account, $call = null) {

	$call = ($call === null) ? mp_ebay_taxonomy_call($account) : $call;

	$tree = mp_ebay_category_tree_id($account, $call);

	if (!$tree['ok']) {

		return $tree;

	}

	$answer = call_user_func($call, '/commerce/taxonomy/v1/category_tree/' . $tree['data']['tree_id'], 134217728);

	if (!$answer['ok']) {

		return mp_error(mp_ebay_error_text($answer), mp_ebay_retry($answer));

	}

	$rows = pg_ebay_category_rows($answer['data']);

	if (!$rows) {

		return mp_error(lang('eBay returned no categories.'));

	}

	return mp_ok(array('categories' => $rows));

}

// The item specifics one category asks for (getItemAspectsForCategory), as
// attribute rows (pg_ebay_aspect_rows()).
function mp_ebay_category_attributes($account, $category_id, $call = null) {

	$category_id = trim((string)$category_id);

	if (preg_match('/^[0-9]{1,20}$/', $category_id) !== 1) {

		return mp_error(lang('No category to ask about.'));

	}

	$call = ($call === null) ? mp_ebay_taxonomy_call($account) : $call;

	$tree = mp_ebay_category_tree_id($account, $call);

	if (!$tree['ok']) {

		return $tree;

	}

	$answer = call_user_func($call, '/commerce/taxonomy/v1/category_tree/' . $tree['data']['tree_id']
		. '/get_item_aspects_for_category?category_id=' . $category_id, 16777216);

	if (!$answer['ok']) {

		return mp_error(mp_ebay_error_text($answer), mp_ebay_retry($answer));

	}

	return mp_ok(array('attributes' => pg_ebay_aspect_rows($answer['data'])));

}
