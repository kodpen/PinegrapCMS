<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google Merchant Center as a marketplace connector: price and stock pushed
 * one product at a time into the supplemental data source the Product Feeds
 * card registered (productInputs.insert), and each product's status read
 * back (products.get). The account row is managed by the card
 * (includes/fn/merchant_center_push.php); the credentials point at the
 * connected Google account, whose token pg_connect_api() renews.
 *
 * The Merchant API answers each product at once, so a batch has no task to
 * ask about later: what went through is answered as data['pushed'], what
 * Google refused as data['refused'], and what was not sent before a rate
 * limit or the time budget as data['deferred'] (sync.php puts those back).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_INIT_LOADED')) {
	exit;
}

// Seconds one batch or one status read may spend on calls, so the job's own
// 25 seconds still leave room for the other accounts.
if (!defined('PG_MERCHANT_PUSH_BUDGET')) {
	define('PG_MERCHANT_PUSH_BUDGET', 15);
}

function mp_merchant_center_connector() {

	return array(
		'test_credentials' => 'mp_merchant_center_test',
		'push_stock_price' => 'mp_merchant_center_push',
		'listing_status'   => 'mp_merchant_center_listing_status'
	);

}

// The connected account and Merchant Center account the row points at, or
// an error answer.
function mp_merchant_center_target($account) {

	$credentials = isset($account['credentials']) && is_array($account['credentials']) ? $account['credentials'] : array();

	$connection_id = isset($credentials['connection_id']) ? (int)$credentials['connection_id'] : 0;

	$merchant = isset($credentials['account']) ? (string)$credentials['account'] : '';

	if ($connection_id <= 0 || !preg_match('/^\d{1,20}$/', $merchant)) {

		return mp_error(lang('Choose the Merchant Center account again on the Product Feeds card.'));

	}

	return mp_ok(array(
		'connection_id' => $connection_id,
		'account'       => $merchant,
		'country'       => isset($credentials['country']) ? (string)$credentials['country'] : ''
	));

}

function mp_merchant_center_test($account) {

	$target = mp_merchant_center_target($account);

	if (!$target['ok']) {

		return $target;

	}

	$call = pg_merchant_call($target['data']['connection_id'], 'GET', '/accounts/v1/accounts/' . $target['data']['account']);

	return $call['ok'] ? mp_ok() : mp_error($call['error'], pg_merchant_push_verdict($call['status']) === 'later');

}

function mp_merchant_center_push($account, $rows) {

	$target = mp_merchant_center_target($account);

	if (!$target['ok']) {

		return $target;

	}

	$target = $target['data'];

	$state = pg_merchant_state();

	if ($state['supplement'] === '' || $state['account'] !== $target['account']) {

		return mp_error(lang('The price and stock update source is not registered in Merchant Center; register the feed again on the Product Feeds card.'));

	}

	$with_price = !empty($account['push_price']);

	$with_stock = !empty($account['push_stock']);

	if (!$with_price && !$with_stock) {

		return mp_ok(array('task_id' => ''));

	}

	$ids = array_values(array_unique(array_map('intval', array_column($rows, 'product_id'))));

	$products = array();

	if ($ids) {

		foreach ((array) db_items("SELECT id, price, enabled, out_of_stock, inventory, inventory_quantity, backorder
			FROM products WHERE id IN (" . implode(',', $ids) . ")") as $product) {

			$products[(int)$product['id']] = $product;

		}

	}

	$sale_prices = function_exists('get_discounted_product_prices') ? get_discounted_product_prices(false) : array();

	$sale_prices = is_array($sale_prices) ? $sale_prices : array();

	$currency = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';

	$language = defined('TRANSLATION_SOURCE_LANGUAGE') ? TRANSLATION_SOURCE_LANGUAGE : 'en';

	$path = '/products/v1/accounts/' . $target['account'] . '/productInputs:insert?dataSource=' . rawurlencode($state['supplement']);

	$deadline = microtime(true) + PG_MERCHANT_PUSH_BUDGET;

	$pushed = array();

	$refused = array();

	$deferred = array();

	$failure = null;

	foreach ($ids as $product_id) {

		if ($failure !== null || microtime(true) >= $deadline) {

			$deferred[] = $product_id;

			continue;

		}

		if (!isset($products[$product_id])) {

			$refused[$product_id] = lang('The product no longer exists.');

			continue;

		}

		$item = pg_merchant_push_item($products[$product_id],
			isset($sale_prices[$product_id]) ? $sale_prices[$product_id] : null, $currency);

		$call = pg_merchant_call($target['connection_id'], 'POST', $path,
			pg_merchant_push_input($item, $language, $target['country'], $with_price, $with_stock));

		$verdict = pg_merchant_push_verdict($call['status']);

		if ($verdict === 'ok') {

			$pushed[] = $product_id;

		} elseif ($verdict === 'refused') {

			$refused[$product_id] = $call['error'];

		} else {

			$failure = $call;

			$deferred[] = $product_id;

		}

	}

	// A rate limit waits a minute, any other failure half of one; products
	// left for the time budget go on the next pass.
	$retry_after = ($failure === null) ? 0 : (((int)$failure['status'] === 429) ? 60 : 30);

	// The first call already failed: a batch error, so the rows count an
	// attempt and back off, and stop after six.
	if ($failure !== null && !$pushed && !$refused) {

		return mp_error($failure['error'], true, ((int)$failure['status'] === 429) ? array('retry_after' => $retry_after) : array());

	}

	return mp_ok(array(
		'task_id'     => '',
		'sent'        => count($pushed),
		'pushed'      => $pushed,
		'refused'     => $refused,
		'deferred'    => $deferred,
		'retry_after' => $retry_after
	));

}

// The status of the products named by their offer ids, as the layer reads
// listings: approved, rejected with the disapproving issues, or unknown.
function mp_merchant_center_listing_status($account, $codes) {

	$target = mp_merchant_center_target($account);

	if (!$target['ok']) {

		return $target;

	}

	$target = $target['data'];

	$language = defined('TRANSLATION_SOURCE_LANGUAGE') ? TRANSLATION_SOURCE_LANGUAGE : 'en';

	$deadline = microtime(true) + PG_MERCHANT_PUSH_BUDGET;

	$listings = array();

	foreach ((array)$codes as $code) {

		if (microtime(true) >= $deadline) {

			break;

		}

		$key = pg_merchant_product_key($language, $target['country'], $code);

		if ($key === '') {

			continue;

		}

		$call = pg_merchant_call($target['connection_id'], 'GET', '/products/v1/accounts/' . $target['account'] . '/products/' . rawurlencode($key));

		if ($call['ok']) {

			$listings[(string)$code] = pg_merchant_listing_state($call['data'], $key);

			continue;

		}

		if ((int)$call['status'] === 404) {

			$listings[(string)$code] = array('state' => 'unknown');

			continue;

		}

		// Nothing read yet: the account's trouble, reported once. Otherwise
		// what was read is kept and the rest waits for the next pass.
		if (!$listings) {

			return mp_error($call['error'], true);

		}

		break;

	}

	return mp_ok(array('listings' => $listings));

}
