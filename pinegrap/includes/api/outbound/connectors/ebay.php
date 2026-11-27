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
		'test_credentials' => 'mp_ebay_test_credentials'
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
