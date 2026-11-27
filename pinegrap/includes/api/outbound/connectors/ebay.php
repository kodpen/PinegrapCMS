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
 * A listing is three calls an item on the Inventory API with the seller's
 * token: the inventory item (createOrReplaceInventoryItem), its offer on the
 * account's site (createOffer, or updateOffer for one already there) and
 * the publish; an article of several products is an inventory item group
 * published as one listing. eBay answers each at once, so create_listings()
 * answers the verdicts itself (data['results']) and there is no task to
 * read; listing_status() reads each SKU's offer for its listing id.
 *
 * Stock and price go in bulk (bulkUpdatePriceQuantity, 25 SKUs a call)
 * from the queue: push_stock_price() looks up each SKU's offer on the site,
 * since a price is set on an offer and the map keeps the listing id, and
 * answers what went through, what eBay refused and what waits.
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

// Seconds one push_stock_price() call may spend on eBay before the rest of
// the batch waits for the next pass.
if (!defined('PG_EBAY_PUSH_BUDGET')) {
	define('PG_EBAY_PUSH_BUDGET', 20);
}

function mp_ebay_connector() {

	return array(
		'test_credentials'    => 'mp_ebay_test_credentials',
		'category_key'        => 'mp_ebay_category_key',
		'category_tree'       => 'mp_ebay_category_tree',
		'category_attributes' => 'mp_ebay_category_attributes',
		'create_listings'     => 'mp_ebay_create_listings',
		'listing_status'      => 'mp_ebay_listing_status',
		'push_stock_price'    => 'mp_ebay_push_stock_price'
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

/* -------------------------------------------------------------- listings */

// A call on the Inventory API of the account's environment with the seller's
// token, answering array(ok, status, data, error): function ($method, $path,
// $body = null). Every call names the site's language, which eBay asks of
// an inventory item and an offer. null when no eBay account is connected.
function mp_ebay_api_call($account) {

	$environment = mp_ebay_environment($account);

	$connection_id = mp_ebay_connection_id($environment);

	if ($connection_id <= 0) {

		return null;

	}

	$hosts = pg_ebay_hosts($environment);

	$sites = pg_ebay_sites();

	$locale = $sites[mp_ebay_site($account)]['locale'];

	return function ($method, $path, $body = null) use ($connection_id, $hosts, $locale) {

		$options = array(
			'headers'  => array('Accept: application/json', 'Content-Language: ' . $locale),
			'timeout'  => 60,
			'max_body' => 1048576,
			'agent'    => 'marketplace'
		);

		if ($body !== null) {

			$options['headers'][] = 'Content-Type: application/json';

			$options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		}

		$result = pg_connect_api($connection_id, $method, $hosts['api'] . $path, $options);

		$result['data'] = is_array($result['data']) ? $result['data'] : array();

		return $result;

	};

}

// The account's inventory location and three business policies, or null
// while one of them has not been chosen.
function mp_ebay_listing_choices($account) {

	$credentials = (isset($account['credentials']) && is_array($account['credentials'])) ? $account['credentials'] : array();

	$choices = array();

	foreach (array('location', 'fulfillment_policy', 'payment_policy', 'return_policy') as $field) {

		$value = isset($credentials[$field]) ? trim((string)$credentials[$field]) : '';

		if ($value === '') {

			return null;

		}

		$choices[$field] = $value;

	}

	return $choices;

}

// The rate from the shop's currency to $currency today (1 for the same
// currency), or null when none is recorded (Settings › Currencies).
function mp_ebay_rate($currency) {

	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : '';

	if ($base === strtoupper($currency)) {

		return 1.0;

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/currency_rates.php');

	$rate = pg_currency_rate($currency, date('Y-m-d'));

	return is_array($rate) ? (float)$rate['rate'] : null;

}

// The text of a failed call: every message eBay gave, else the status.
function mp_ebay_call_error($result) {

	$messages = pg_ebay_error_messages(isset($result['data']) ? $result['data'] : null);

	return ($messages !== '') ? mb_substr('eBay: ' . $messages, 0, 500) : mp_ebay_error_text($result);

}

// The offer of one item on the account's site, created or brought up to
// date: array(ok, offer_id, error, retry). An SKU has one offer a site, so
// the one already there is replaced (updateOffer) rather than a second
// asked for, which eBay refuses.
function mp_ebay_offer_put($call, $item, $site, $choices, $price) {

	$sku = (string)$item['code'];

	$body = pg_ebay_offer_body($item, $site, $choices, $price);

	$found = call_user_func($call, 'GET', '/sell/inventory/v1/offer?sku=' . rawurlencode($sku) . '&marketplace_id=' . rawurlencode($site));

	if (!$found['ok'] && ((int)$found['status'] !== 404)) {

		return array('ok' => false, 'offer_id' => '', 'error' => mp_ebay_call_error($found), 'retry' => mp_ebay_retry($found));

	}

	$offer = $found['ok'] ? pg_ebay_offer_of($found['data'], $site) : null;

	if ($offer !== null) {

		$answer = call_user_func($call, 'PUT', '/sell/inventory/v1/offer/' . rawurlencode($offer['offer_id']), $body);

		$offer_id = $offer['offer_id'];

	} else {

		$answer = call_user_func($call, 'POST', '/sell/inventory/v1/offer', $body);

		$offer_id = (isset($answer['data']['offerId']) && is_scalar($answer['data']['offerId'])) ? (string)$answer['data']['offerId'] : '';

	}

	if (!$answer['ok']) {

		return array('ok' => false, 'offer_id' => '', 'error' => mp_ebay_call_error($answer), 'retry' => mp_ebay_retry($answer));

	}

	if ($offer_id === '') {

		return array('ok' => false, 'offer_id' => '', 'error' => lang('eBay created the offer but named no offer id.'), 'retry' => false);

	}

	return array('ok' => true, 'offer_id' => $offer_id, 'error' => '', 'retry' => false);

}

// List one article's items on the account's eBay site.
//
// Refused before any call, as an article (mp_sync_send_listings() writes it
// on the article's row): no location or policies chosen, no rate to the
// site's currency, an item eBay would refuse (pg_ebay_item_problem()), no
// price. Then each item's inventory item, for several items their group,
// each item's offer, and the publish: one call for a single item, one for
// the group, whose variants go live together or not at all.
//
// Every call puts or replaces, so a failure eBay may get over (no answer,
// 429, 5xx) gives the whole article back to the queue to send again. Any
// other refusal is that item's verdict in data['results'] (SKU => ok,
// error); in a group, the other variants are not published either. $call
// and $rate replace eBay and the rate table in tests.
function mp_ebay_create_listings($account, $items, $call = null, $rate = null) {

	$items = array_values((array)$items);

	$site = mp_ebay_site($account);

	$sites = pg_ebay_sites();

	$currency = $sites[$site]['currency'];

	$choices = mp_ebay_listing_choices($account);

	if ($choices === null) {

		return mp_error(lang('Choose the eBay account\'s inventory location and business policies first: test the connection, then pick them.'));

	}

	if (!$items) {

		return mp_ok(array('task_id' => '', 'results' => array()));

	}

	$rate = ($rate === null) ? mp_ebay_rate($currency) : $rate;

	if (!is_numeric($rate) || ((float)$rate <= 0)) {

		return mp_error(lang(array(
			'string' => 'There is no exchange rate from {var:1} to {var:2} yet, so the price on eBay cannot be worked out.',
			'vars'   => array(defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $currency)
		)));

	}

	$prices = array();

	foreach ($items as $item) {

		$problem = pg_ebay_item_problem($item);

		if ($problem !== '') {

			return mp_error($problem);

		}

		$prices[(string)$item['code']] = pg_ebay_offer_price(isset($item['price_major']) ? $item['price_major'] : 0, $rate);

		if ($prices[(string)$item['code']] === '') {

			return mp_error(lang(array('string' => '{var:1} has no price.', 'vars' => array((string)$item['code']))));

		}

	}

	$group = null;

	if (count($items) > 1) {

		$group = pg_ebay_inventory_group($items);

		if (!$group['ok']) {

			return mp_error($group['error']);

		}

	}

	if ($call === null) {

		$call = mp_ebay_api_call($account);

		if ($call === null) {

			return mp_error((mp_ebay_environment($account) === 'sandbox')
				? lang('Connect an eBay (sandbox) account on Connections first.')
				: lang('Connect an eBay account on Connections first.'));

		}

	}

	$results = array();

	foreach ($items as $item) {

		$sku = (string)$item['code'];

		$answer = call_user_func($call, 'PUT', '/sell/inventory/v1/inventory_item/' . rawurlencode($sku), pg_ebay_inventory_item($item, $site));

		if ($answer['ok']) {

			continue;

		}

		if (mp_ebay_retry($answer)) {

			return mp_error(mp_ebay_call_error($answer), true);

		}

		$results[$sku] = array('ok' => false, 'error' => mp_ebay_call_error($answer));

	}

	$group_key = ($group !== null) ? pg_ebay_group_key($items[0]['group_code']) : '';

	if (($group !== null) && !$results) {

		$answer = call_user_func($call, 'PUT', '/sell/inventory/v1/inventory_item_group/' . rawurlencode($group_key), $group['body']);

		if (!$answer['ok']) {

			if (mp_ebay_retry($answer)) {

				return mp_error(mp_ebay_call_error($answer), true);

			}

			return mp_ok(array('task_id' => '', 'results' => mp_ebay_all_failed($items, mp_ebay_call_error($answer))));

		}

	}

	$offers = array();

	foreach ($items as $item) {

		$sku = (string)$item['code'];

		if (isset($results[$sku])) {

			continue;

		}

		$offer = mp_ebay_offer_put($call, $item, $site, $choices, $prices[$sku]);

		if (!$offer['ok'] && $offer['retry']) {

			return mp_error($offer['error'], true);

		}

		if (!$offer['ok']) {

			$results[$sku] = array('ok' => false, 'error' => $offer['error']);

			continue;

		}

		$offers[$sku] = $offer['offer_id'];

	}

	if ($group !== null) {

		if ($results) {

			return mp_ok(array('task_id' => '', 'results' => $results + mp_ebay_all_failed($items,
				lang('Not published: another variant of this article was refused by eBay.'))));

		}

		$answer = call_user_func($call, 'POST', '/sell/inventory/v1/offer/publish_by_inventory_item_group',
			array('inventoryItemGroupKey' => $group_key, 'marketplaceId' => $site));

		if (!$answer['ok']) {

			if (mp_ebay_retry($answer)) {

				return mp_error(mp_ebay_call_error($answer), true);

			}

			return mp_ok(array('task_id' => '', 'results' => mp_ebay_all_failed($items, mp_ebay_call_error($answer))));

		}

		return mp_ok(array('task_id' => '', 'results' => mp_ebay_all_failed($items, '', true)));

	}

	foreach ($offers as $sku => $offer_id) {

		$answer = call_user_func($call, 'POST', '/sell/inventory/v1/offer/' . rawurlencode($offer_id) . '/publish', array());

		if (!$answer['ok'] && mp_ebay_retry($answer)) {

			return mp_error(mp_ebay_call_error($answer), true);

		}

		$results[$sku] = $answer['ok'] ? array('ok' => true, 'error' => '') : array('ok' => false, 'error' => mp_ebay_call_error($answer));

	}

	return mp_ok(array('task_id' => '', 'results' => $results));

}

// The same verdict for every item: SKU => ok, error.
function mp_ebay_all_failed($items, $error, $ok = false) {

	$results = array();

	foreach ($items as $item) {

		$results[(string)$item['code']] = array('ok' => $ok, 'error' => $ok ? '' : $error);

	}

	return $results;

}

// Where each SKU's listing stands on the account's site
// (pg_ebay_listing_state()): data['listings'], SKU => state. One getOffers
// call a SKU; an SKU with no offer there (404) is unknown. $call replaces
// eBay in tests.
function mp_ebay_listing_status($account, $codes, $call = null) {

	$site = mp_ebay_site($account);

	if ($call === null) {

		$call = mp_ebay_api_call($account);

		if ($call === null) {

			return mp_error(lang('Connect an eBay account on Connections first.'));

		}

	}

	$listings = array();

	foreach (array_unique(array_map('strval', (array)$codes)) as $sku) {

		if ($sku === '') {

			continue;

		}

		$answer = call_user_func($call, 'GET', '/sell/inventory/v1/offer?sku=' . rawurlencode($sku) . '&marketplace_id=' . rawurlencode($site));

		if (!$answer['ok'] && ((int)$answer['status'] !== 404)) {

			return mp_error(mp_ebay_call_error($answer), mp_ebay_retry($answer));

		}

		$listings[$sku] = pg_ebay_listing_state($answer['ok'] ? pg_ebay_offer_of($answer['data'], $site) : null);

	}

	return mp_ok(array('listings' => $listings));

}

/* -------------------------------------------------------- stock and price */

// Stock and price of a batch of mapped products (mp_product_row()) on the
// account's site, as mp_sync_send_batch() asks. eBay answers at once, so
// there is no task: data['pushed'] names the products that went through,
// data['refused'] the ones eBay or this side refused (product id => reason,
// written on the mapping) and data['deferred'] the ones left for the next
// pass after a fault on eBay's side or past the time budget. A product with
// nothing to say (untracked stock, no price sent) is in none of them and
// closes as done.
//
// Each SKU's offer is looked up first (one GET a SKU); a SKU with no offer
// on the site is not listed there and is refused. The price is the row's
// channel price converted to the site's currency and rounded to the cent,
// as the listing's. $call, $rate and $budget (seconds) replace eBay, the
// rate table and PG_EBAY_PUSH_BUDGET in tests.
function mp_ebay_push_stock_price($account, $rows, $call = null, $rate = null, $budget = null) {

	$rows = array_values((array)$rows);

	$with_price = !empty($account['push_price']);

	$with_stock = !empty($account['push_stock']);

	if (!$rows || (!$with_price && !$with_stock)) {

		return mp_ok(array('task_id' => ''));

	}

	$site = mp_ebay_site($account);

	$sites = pg_ebay_sites();

	$currency = $sites[$site]['currency'];

	$refused = array();

	if ($with_price) {

		$rate = ($rate === null) ? mp_ebay_rate($currency) : $rate;

		if (!is_numeric($rate) || ((float)$rate <= 0)) {

			$no_rate = lang(array(
				'string' => 'There is no exchange rate from {var:1} to {var:2} yet, so the price on eBay cannot be worked out.',
				'vars'   => array(defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $currency)
			));

			foreach ($rows as $row) {

				$refused[(int)$row['product_id']] = $no_rate;

			}

			return mp_ok(array('task_id' => '', 'sent' => 0, 'pushed' => array(), 'refused' => $refused, 'deferred' => array()));

		}

	}

	if ($call === null) {

		$call = mp_ebay_api_call($account);

		if ($call === null) {

			return mp_error((mp_ebay_environment($account) === 'sandbox')
				? lang('Connect an eBay (sandbox) account on Connections first.')
				: lang('Connect an eBay account on Connections first.'));

		}

	}

	$deadline = microtime(true) + (($budget === null) ? PG_EBAY_PUSH_BUDGET : (float)$budget);

	$limits = pg_ebay_listing_limits();

	$pushed = array();

	$deferred = array();

	$failure = null;

	foreach (array_chunk($rows, $limits['bulk']) as $chunk) {

		$requests = array();

		$products = array();

		foreach ($chunk as $row) {

			$product_id = (int)$row['product_id'];

			$sku = trim((string)$row['remote_code']);

			if ($failure !== null || microtime(true) >= $deadline) {

				$deferred[] = $product_id;

				continue;

			}

			if ($sku === '') {

				continue;

			}

			$price = '';

			if ($with_price) {

				$price = pg_ebay_offer_price(isset($row['price_major']) ? $row['price_major'] : 0, $rate);

				if ($price === '') {

					$refused[$product_id] = lang(array('string' => '{var:1} has no price.', 'vars' => array($sku)));

					continue;

				}

			}

			$found = call_user_func($call, 'GET', '/sell/inventory/v1/offer?sku=' . rawurlencode($sku) . '&marketplace_id=' . rawurlencode($site));

			if (!$found['ok'] && ((int)$found['status'] !== 404)) {

				if (mp_ebay_retry($found)) {

					$failure = $found;

					$deferred[] = $product_id;

				} else {

					$refused[$product_id] = mp_ebay_call_error($found);

				}

				continue;

			}

			$offer = $found['ok'] ? pg_ebay_offer_of($found['data'], $site) : null;

			if ($offer === null) {

				$refused[$product_id] = lang(array('string' => '{var:1} is not listed on this eBay site yet.', 'vars' => array($sku)));

				continue;

			}

			$request = pg_ebay_bulk_request($row, $offer['offer_id'], $with_stock, $price, $currency);

			if ($request === null) {

				continue;

			}

			$requests[] = $request;

			$products[$sku][] = $product_id;

		}

		if (!$requests) {

			continue;

		}

		$answer = call_user_func($call, 'POST', '/sell/inventory/v1/bulk_update_price_quantity', array('requests' => $requests));

		if (!$answer['ok']) {

			foreach ($products as $ids) {

				foreach ($ids as $product_id) {

					if (mp_ebay_retry($answer)) {

						$deferred[] = $product_id;

					} else {

						$refused[$product_id] = mp_ebay_call_error($answer);

					}

				}

			}

			if (mp_ebay_retry($answer)) {

				$failure = $answer;

			}

			continue;

		}

		$verdicts = pg_ebay_bulk_verdicts($answer['data']);

		foreach ($products as $sku => $ids) {

			$sku = (string)$sku;

			foreach ($ids as $product_id) {

				if (isset($verdicts[$sku]) && ($verdicts[$sku] !== '')) {

					$refused[$product_id] = mb_substr('eBay: ' . $verdicts[$sku], 0, 500);

				} else {

					$pushed[] = $product_id;

				}

			}

		}

	}

	// A rate limit waits a minute, any other fault half of one.
	$retry_after = ($failure === null) ? 0 : (((int)$failure['status'] === 429) ? 60 : 30);

	// Nothing went through and nothing was refused: the batch failed as a
	// whole, so its rows count an attempt and back off, a rate limit for a
	// minute at least.
	if ($failure !== null && !$pushed && !$refused) {

		return mp_error(mp_ebay_call_error($failure), true, ((int)$failure['status'] === 429) ? array('retry_after' => $retry_after) : array());

	}

	return mp_ok(array(
		'task_id'     => '',
		'sent'        => count($pushed),
		'pushed'      => $pushed,
		'refused'     => $refused,
		'deferred'    => array_values(array_unique($deferred)),
		'retry_after' => $retry_after
	));

}
