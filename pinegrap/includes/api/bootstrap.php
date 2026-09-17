<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The order of one request, start to finish.
//
// Every gate is here rather than spread through the handlers, so reading this
// one function tells you exactly what has been established by the time a handler
// runs: the surface is switched on, the connection is encrypted, the caller is
// not banned, the credentials are an application's own, the application is
// inside its rate ceiling, its scopes cover this endpoint, and every parameter
// has been checked against its declared type.

if (!defined('PG_API_ENTRY')) {
	exit;
}

$pg_api_directory = dirname(__FILE__);

require_once($pg_api_directory . '/db.php');
require_once($pg_api_directory . '/response.php');
require_once($pg_api_directory . '/log.php');
require_once($pg_api_directory . '/keys.php');
require_once($pg_api_directory . '/scopes.php');
require_once($pg_api_directory . '/auth.php');
require_once($pg_api_directory . '/ratelimit.php');
require_once($pg_api_directory . '/idempotency.php');
require_once($pg_api_directory . '/schema.php');
require_once($pg_api_directory . '/router.php');
require_once($pg_api_directory . '/openapi.php');
require_once($pg_api_directory . '/seo.php');

require_once($pg_api_directory . '/resources/meta.php');
require_once($pg_api_directory . '/resources/products.php');
require_once($pg_api_directory . '/resources/inventory.php');
require_once($pg_api_directory . '/resources/product_groups.php');
require_once($pg_api_directory . '/resources/orders.php');
require_once($pg_api_directory . '/resources/customers.php');
require_once($pg_api_directory . '/resources/pages.php');
require_once($pg_api_directory . '/resources/files.php');
require_once($pg_api_directory . '/resources/webhooks.php');

// API settings, read straight from the config row.
//
// init.php promotes config columns to constants one by one, and an installation
// running new code against a database that has not been upgraded yet would not
// have these columns at all. Reading them here, tolerantly, means the endpoint
// can answer "not installed yet" properly instead of dying inside a SELECT.
function api_settings() {

	static $settings = null;

	if ($settings !== null) {

		return $settings;

	}

	$settings = array(
		'enabled'        => true,
		'require_https'  => true,
		'openapi_public' => false,
		'retention_days' => 30
	);

	if (!defined('DB_CONNECTED')) {

		return $settings;

	}

	$result = @mysqli_query(db::$con, "SELECT api_enabled, api_require_https, api_openapi_public, api_log_retention_days FROM config LIMIT 1");

	if ($result === false) {

		return $settings;

	}

	$row = mysqli_fetch_assoc($result);

	mysqli_free_result($result);

	if (is_array($row)) {

		$settings['enabled']        = ((int)$row['api_enabled'] === 1);
		$settings['require_https']  = ((int)$row['api_require_https'] === 1);
		$settings['openapi_public'] = ((int)$row['api_openapi_public'] === 1);
		$settings['retention_days'] = (int)$row['api_log_retention_days'];

	}

	return $settings;

}

function api_run() {

	$settings = api_settings();

	// The schema has to be current before anything else is attempted: on a site
	// whose files were updated but whose database has not caught up, api_apps
	// does not exist yet and every route would fail inside a query.
	if (!defined('DB_CONNECTED')) {

		api_fail(503, 'service_unavailable', lang('No database connection.'));

	}

	$installed = @mysqli_query(db::$con, "SELECT 1 FROM api_apps LIMIT 1");

	if ($installed === false) {

		api_fail(503, 'not_installed', lang('The API is not installed on this site yet. Run the software upgrade to finish it.'));

	}

	if (!$settings['enabled']) {

		api_fail(503, 'api_disabled', lang('The API is switched off for this site.'));

	}

	// Credentials travel on every single call, so plain HTTP is not a warning
	// here, it is a refusal. The setting exists because the software is also
	// installed on internal networks with no certificate at all.
	if ($settings['require_https'] && function_exists('pg_request_is_https') && !pg_request_is_https()) {

		api_fail(403, 'https_required', lang('This API requires a secure (https) connection.'));

	}

	// The firewall's address ban, applied before anything reads a credential -
	// a banned caller should not get as far as being told whether its key
	// exists.
	if (function_exists('waf_ip_is_blocked')
		&& function_exists('waf_ip_is_allowed')
		&& !waf_ip_is_allowed(api_client_ip())
		&& waf_ip_is_blocked(api_client_ip())) {

		api_fail(403, 'address_blocked', lang('We are currently unable to fulfill your request.'));

	}

	$method = api_request_method();

	$path   = api_request_path();

	if ($method === 'OPTIONS') {

		// Answered so that a browser-based client's preflight does not look like
		// a broken endpoint. Nothing is granted: no cross-origin header is sent,
		// because an API key does not belong in a web page in the first place.
		api_send(204, array());

	}

	if ($path === '/' || $path === '') {

		api_fail(404, 'no_path', lang(array(
			'string' => 'Address a resource, for example {var:1}.',
			'vars'   => '/products'
		)));

	}

	// There is no version segment in the path. Nothing was ever built against
	// the endpoint this replaces, so there is no older shape to tell apart from
	// this one, and a /v1/ that only ever has one member is a segment every
	// caller types for nothing. The version is reported in the meta endpoint and
	// in the generated description instead; should a breaking change ever be
	// needed, a prefixed path can be added beside these without touching them.
	$route = api_route_match($method, $path);

	if ($route === null) {

		api_fail(404, 'unknown_endpoint', lang('No such endpoint.'));

	}

	$input = api_request_input();

	// The description of the API is the one thing that can be public, and only
	// when the operator has said so. Otherwise it authenticates like everything
	// else.
	if ($route['scope'] === '' && $route['id'] === 'openapi' && $settings['openapi_public']) {

		call_user_func($route['handler'], array());

		api_fail_server();

	}

	$app = api_authenticate($input);

	api_rate_limit_check($app);

	if ($route['scope'] !== '') {

		api_require_scope($route['scope']);

	}

	$params = api_validate_input($route, $input);

	// Writes may be retried by the caller, so they run under an idempotency key
	// when one was sent: the answer to the first attempt is recorded and the
	// retry is answered from that record instead of being applied again.
	if (in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {

		$key = api_idempotency_key();

		if ($key !== '') {

			$hash = api_idempotency_hash($params);

			api_idempotency_context((int)$app['id'], $key, $hash);

			api_idempotency_replay((int)$app['id'], $key, $hash);

		}

	}

	call_user_func($route['handler'], $params);

	// A handler answers through api_send() and never returns. Reaching this line
	// means one forgot to, which is a fault in the software rather than in the
	// request.
	api_fail_server();

}
