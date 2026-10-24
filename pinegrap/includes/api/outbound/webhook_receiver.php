<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Order packages a marketplace posts to this site (marketplace_webhook.php).
//
// Who calls it: the marketplace's own servers, once a webhook has been
// registered from the account screen, every time one of the seller's order
// packages changes. Trendyol posts the same page its order feed answers with
// and puts the key it was given in an x-api-key header (or HTTP Basic, for a
// webhook registered that way). Nothing is signed, so the key is the whole
// credential: it is 32 random bytes kept in the account's encrypted
// credentials, compared in constant time, and never shown on any screen.
//
// What it checks, in order: a POST; an account that exists; an account that is
// switched on, brings orders in, and whose connector reads webhooks; the key;
// a body of at most 4 MiB that is JSON. Each refusal is a status code and a
// JSON body that says no more than that.
//
// It never calls the marketplace back - no acceptance, no lookup. A network
// call is not made inside a request (the outbound layer's rule): the request
// would wait on somebody else's server, and their delivery would time out
// while it did. A package imported here is accepted, when the account asks
// for that, by the next pass of the scheduled job (mp_sync_approve_pending()).
//
// A package that could not be imported - a line no product here matches - is
// still answered 200. The map row holds the reason, the Imported orders card
// shows it, and "Try again" reads it from the feed once the product is
// matched. Anything else would make the marketplace send it again every five
// minutes and then switch the webhook off.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');
require_once(dirname(__FILE__) . '/orders.php');

// The largest body accepted. A delivery carries one page of packages.
function mp_webhook_max_body() {

	return 4194304;

}

// The address a marketplace posts this account's orders to.
function mp_webhook_url($account_id) {

	return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/marketplace_webhook.php?account=' . (int)$account_id;

}

// Answer one delivery and end the request.
function mp_webhook_receive() {

	$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : '';

	$account_id = (isset($_GET['account']) && is_scalar($_GET['account'])) ? (int)$_GET['account'] : 0;

	$account = ($method === 'POST' && $account_id > 0) ? mp_account($account_id) : null;

	$status = mp_webhook_gate($method, $account, mp_webhook_presented_key($_SERVER));

	if ($status !== 0) {

		mp_webhook_answer($status, array('ok' => false));

	}

	$length = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;

	if ($length > mp_webhook_max_body()) {

		mp_webhook_answer(413, array('ok' => false));

	}

	// One byte more than the limit is read, so that a body sent without a
	// length is still caught at the limit rather than cut there.
	$body = (string)@file_get_contents('php://input', false, null, 0, mp_webhook_max_body() + 1);

	if (strlen($body) > mp_webhook_max_body()) {

		mp_webhook_answer(413, array('ok' => false));

	}

	$decoded = json_decode($body, true);

	if (!is_array($decoded)) {

		mp_webhook_answer(400, array('ok' => false));

	}

	$result = mp_call($account['provider'], 'webhook_packages', array($account, $decoded));

	$packages = ($result['ok'] && isset($result['data']['packages'])) ? (array)$result['data']['packages'] : array();

	$counts = array('imported' => 0, 'skipped' => 0, 'failed' => 0);

	foreach ($packages as $package) {

		$outcome = mp_import_order($account, $package);

		if (!empty($outcome['skipped'])) {

			$counts['skipped']++;

		} elseif ($outcome['ok']) {

			$counts['imported']++;

		} else {

			$counts['failed']++;

		}

	}

	// The account's last success and last error are left alone: they answer
	// whether this site can reach the marketplace, and a delivery the other
	// way says nothing about that. Clearing the error here would hide a
	// refused key from the screen while every stock push failed on it.
	mp_webhook_answer(200, array_merge(array('ok' => true), $counts));

}

// The status a delivery is refused with, or 0 when it may be read.
//
// $account is the account row with its credentials decoded, or null. The
// account is looked up before the key is checked because the key is the
// account's; an address naming no account has no key to compare with.
function mp_webhook_gate($method, $account, $presented_key) {

	if ($method !== 'POST') {

		return 405;

	}

	if (!is_array($account)) {

		return 404;

	}

	if ((isset($account['status']) ? $account['status'] : '') !== 'active'
		|| empty($account['pull_orders'])
		|| !mp_can(isset($account['provider']) ? (string)$account['provider'] : '', 'webhook_packages')) {

		return 403;

	}

	$stored = (isset($account['credentials']['webhook_key']) && is_string($account['credentials']['webhook_key']))
		? $account['credentials']['webhook_key'] : '';

	if ($stored === '' || !hash_equals($stored, (string)$presented_key)) {

		return 401;

	}

	return 0;

}

// The key a delivery presents, read from a copy of $_SERVER.
//
// x-api-key first, the header Trendyol sends for a webhook registered with an
// API key. Then the password of HTTP Basic, for one registered with a user name
// and password: PHP_AUTH_PW where the server hands the credentials to PHP,
// otherwise the Authorization header itself, which a common rewrite rule
// passes on as REDIRECT_HTTP_AUTHORIZATION on hosts that strip it.
function mp_webhook_presented_key($server) {

	if (isset($server['HTTP_X_API_KEY']) && is_string($server['HTTP_X_API_KEY']) && trim($server['HTTP_X_API_KEY']) !== '') {

		return trim($server['HTTP_X_API_KEY']);

	}

	if (isset($server['PHP_AUTH_PW']) && is_string($server['PHP_AUTH_PW']) && $server['PHP_AUTH_PW'] !== '') {

		return $server['PHP_AUTH_PW'];

	}

	foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $name) {

		if (!isset($server[$name]) || !is_string($server[$name])
			|| preg_match('/^\s*Basic\s+([A-Za-z0-9+\/=]+)\s*$/i', $server[$name], $match) !== 1) {

			continue;

		}

		$decoded = base64_decode($match[1], true);

		if ($decoded !== false && strpos($decoded, ':') !== false) {

			$parts = explode(':', $decoded, 2);

			return $parts[1];

		}

	}

	return '';

}

// Why a webhook cannot be registered for this account now, or ''.
//
// One at a time: a second registration would leave the first one delivering
// with nothing here to remove it, and Trendyol allows fifteen per seller. And
// only while the account takes deliveries, because the receiver refuses them
// otherwise and the marketplace switches a webhook off after repeated refusals.
function mp_webhook_register_problem($account) {

	if (isset($account['credentials']['webhook_id']) && (string)$account['credentials']['webhook_id'] !== '') {

		return lang('A webhook is already registered for this account. Remove it first.');

	}

	if ((isset($account['status']) ? $account['status'] : '') !== 'active' || empty($account['pull_orders'])) {

		return lang('Switch the account on and let it bring orders in before registering a webhook. Deliveries are refused until then.');

	}

	return '';

}

// Would saving these credentials change who a registered webhook belongs to?
//
// A webhook is registered for one seller in one environment - the fields the
// provider names in 'webhook_owner_fields' - and keeps delivering that
// seller's packages, with a key the receiver accepts, whatever the account is
// changed to. Switching a test account to live under it would import the test
// environment's packages as real orders and take their stock. $stored and
// $submitted are credential arrays; both are read the way a form is
// (mp_provider_credentials()), so a select stored before it existed counts as
// its default and spaces around a text value are no change.
function mp_webhook_owner_changed($provider, $stored, $submitted) {

	$providers = mp_providers();

	$fields = isset($providers[$provider]['webhook_owner_fields']) ? (array)$providers[$provider]['webhook_owner_fields'] : array();

	if (!$fields) {

		return false;

	}

	$before = mp_provider_credentials($provider, (array)$stored);

	$after = mp_provider_credentials($provider, (array)$submitted);

	foreach ($fields as $field) {

		if ((isset($before[$field]) ? $before[$field] : '') !== (isset($after[$field]) ? $after[$field] : '')) {

			return true;

		}

	}

	return false;

}

// A status code and a JSON body, and nothing the shared code printed on the
// way here.
function mp_webhook_answer($status, $body) {

	while (ob_get_level() > 0) {

		ob_end_clean();

	}

	http_response_code((int)$status);

	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	header('X-Robots-Tag: noindex, nofollow');

	if ((int)$status === 405) {

		header('Allow: POST');

	}

	echo json_encode($body);

	exit;

}
