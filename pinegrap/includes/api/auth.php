<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Who is calling.
//
// Both halves of the credential belong to the application. The surface this
// replaces split them: the key identified an application but the secret
// identified a person, so any person's secret opened any application's key, the
// rights that applied were a collision of two unrelated things, and rotating a
// secret broke every integration its owner had ever created.
//
// Here the key names the application and the secret proves it, the application
// carries its own scopes, and the owner is consulted only to cap what those
// scopes can reach. Nothing about a browser session takes part: the public
// entry point runs with no session at all, so a signed-in operator's cookie can
// never decide what an unauthenticated call is allowed to do.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// The authenticated application for this request, or null before authentication
// has run. The log writer reads it, which is why a refused request still logs
// with application zero rather than not at all.
function api_current_app($app = null) {

	static $current = null;

	if ($app !== null) {

		$current = $app;

	}

	return $current;

}

function api_current_scopes($scopes = null) {

	static $current = array();

	if ($scopes !== null) {

		$current = $scopes;

	}

	return $current;

}

function api_require_scope($needed) {

	if (!api_has_scope(api_current_scopes(), $needed)) {

		api_fail_scope($needed);

	}

}

// Credentials off the wire, in order of preference.
//
// HTTP Basic first, because every client speaks it - curl, Postman, Swagger UI,
// the automation tools people wire sites into - and because the header is not
// written to access logs the way a query string is. The two custom headers are
// for clients that cannot set an Authorization header.
//
// Credentials in the body or the query string are accepted only from a test key.
// A live secret in a URL ends up in the web server's access log, in any proxy in
// front of it, and in the browser history of whoever pasted it, and no amount of
// convenience is worth that.
function api_read_credentials($input) {

	$key    = '';
	$secret = '';

	$authorization = api_header('Authorization');

	if ($authorization !== '' && stripos($authorization, 'basic ') === 0) {

		$decoded = base64_decode(substr($authorization, 6), true);

		if ($decoded !== false && strpos($decoded, ':') !== false) {

			list($key, $secret) = explode(':', $decoded, 2);

		}

	}

	// Some server configurations hand Basic credentials to PHP directly instead
	// of leaving the header in place.
	if ($key === '' && isset($_SERVER['PHP_AUTH_USER'])) {

		$key    = (string)$_SERVER['PHP_AUTH_USER'];
		$secret = isset($_SERVER['PHP_AUTH_PW']) ? (string)$_SERVER['PHP_AUTH_PW'] : '';

	}

	if ($key === '') {

		$key    = api_header('X-Pinegrap-Key');
		$secret = api_header('X-Pinegrap-Secret');

	}

	if ($key === '' && isset($input['api_key'])) {

		$key    = (string)$input['api_key'];
		$secret = isset($input['api_secret']) ? (string)$input['api_secret'] : '';

	}

	return array('key' => trim($key), 'secret' => trim($secret));

}

// Tell the firewall that this address guessed wrong.
//
// The endpoint is outside the firewall's per address limit for sign-in and
// checkout scripts (waf.php, waf_handle_rate_sensitive), because an application
// calling it with the key it was given is not the thing that limit is for. This
// is: a caller working through keys or secrets it does not have. Same weight
// the firewall gives its own rate offences, so the same automatic ban follows a
// run of them.
//
// The firewall is loaded by init.php on every request and switched off by its
// own setting rather than by being absent, but it is a separate component and
// the API does not fail because it is missing. Its monitoring mode is honoured:
// an installation that is only watching stays watching.
function api_offence() {

	if (!function_exists('waf_register_offence')
		|| !function_exists('waf_client_ip')
		|| !function_exists('waf_mode')) {

		return;

	}

	waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));

}

// Authenticate, or answer and exit. Returns the application row with its
// effective scopes already worked out.
function api_authenticate($input) {

	$credentials = api_read_credentials($input);

	if ($credentials['key'] === '' || $credentials['secret'] === '') {

		api_fail(401, 'credentials_missing', lang('An API key and secret are required.'));

	}

	// One indexed lookup on the public identifier. The old surface hashed the
	// supplied key to find the row, which worked but meant the key could not be
	// shown in a list or searched for; here the key is the identifier and only
	// the secret is hashed.
	$app = api_row("SELECT id, name, api_key, api_secret_hash, api_secret_prev_hash,
			api_secret_prev_expires, owner_user_id, scopes, ip_allowlist,
			status, expires_at, rate_limit_per_min
		FROM api_apps
		WHERE api_key = '" . escape($credentials['key']) . "'
		LIMIT 1");

	if ($app === null) {

		// Deliberately the same answer as a wrong secret. Telling the two apart
		// turns the endpoint into a way to find out which keys exist.
		api_offence();

		api_fail_unauthorized();

	}

	// The application is known from here on, so its identity goes on the log row
	// even when the call is about to be refused. A key being hammered with the
	// wrong secret is exactly the thing the log has to show.
	api_current_app($app);

	if (!api_secret_matches($app, $credentials['secret'])) {

		api_offence();

		api_fail_unauthorized();

	}

	if ($app['status'] !== 'active') {

		api_fail(403, 'application_disabled', lang('This application is not active.'));

	}

	if ((int)$app['expires_at'] > 0 && (int)$app['expires_at'] < time()) {

		api_fail(403, 'application_expired', lang('This application key has expired.'));

	}

	api_check_ip_allowlist($app);

	$owner = api_load_owner((int)$app['owner_user_id']);

	if ($owner === null) {

		// The account that delegated these rights is gone. The application
		// cannot outlive it, because there is no longer anything capping what
		// it may reach.
		api_fail(403, 'owner_unavailable', lang('The account that owns this application is no longer available.'));

	}

	$granted = json_decode((string)$app['scopes'], true);

	$app['owner']  = $owner;
	$app['scopes'] = api_effective_scopes(is_array($granted) ? $granted : array(), $owner);

	api_current_app($app);

	api_current_scopes($app['scopes']);

	return $app;

}

// The current secret, or the previous one while its grace period runs.
//
// Rotation is the reason for the second hash. Issuing a new secret and expecting
// every integration to be updated in the same instant is not how a live site
// works, so the old secret keeps answering for a while and the operator can move
// the sync over without a window of failing calls.
function api_secret_matches($app, $supplied) {

	$supplied_hash = api_secret_hash($supplied);

	if ($app['api_secret_hash'] !== '' && hash_equals((string)$app['api_secret_hash'], $supplied_hash)) {

		return true;

	}

	if ($app['api_secret_prev_hash'] !== ''
		&& (int)$app['api_secret_prev_expires'] > time()
		&& hash_equals((string)$app['api_secret_prev_hash'], $supplied_hash)) {

		return true;

	}

	return false;

}

// An empty list means no restriction. Entries may be plain addresses, wildcards
// or CIDR ranges; matching is the firewall's own, so an operator writes the same
// notation here that they already write there.
function api_check_ip_allowlist($app) {

	$allowlist = trim((string)$app['ip_allowlist']);

	if ($allowlist === '') {

		return;

	}

	$ip = api_client_ip();

	$patterns = preg_split('/[\s,;]+/', $allowlist, -1, PREG_SPLIT_NO_EMPTY);

	foreach ($patterns as $pattern) {

		if (function_exists('waf_ip_matches') && waf_ip_matches($ip, $pattern)) {

			return;

		}

		if ($ip === $pattern) {

			return;

		}

	}

	api_fail(403, 'ip_not_allowed', lang('This address is not on the allow list for this application.'));

}

// The owner, in the shape the scope rules expect. Signed-out, suspended or
// deleted accounts answer null.
function api_load_owner($user_id) {

	if ($user_id <= 0) {

		return null;

	}

	$row = api_row("SELECT user_id, user_username, user_role,
			user_manage_ecommerce, user_manage_contacts, user_manage_forms
		FROM user
		WHERE user_id = '" . (int)$user_id . "'
		LIMIT 1");

	if ($row === null) {

		return null;

	}

	return array(
		'id'               => (int)$row['user_id'],
		'username'         => $row['user_username'],
		'role'             => (int)$row['user_role'],
		'manage_ecommerce' => $row['user_manage_ecommerce'],
		'manage_contacts'  => $row['user_manage_contacts'],
		'manage_forms'     => $row['user_manage_forms']
	);

}
