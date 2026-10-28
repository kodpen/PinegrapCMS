<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The discovery document: what a client that knows only the typed address
// reads before it holds any credential. It names the software, the API base,
// the site's title, logo and language, and whether a team device can sign in.
//
// Answered twice, from one builder:
//
//   GET {PATH}.well-known/pinegrap.json   plain, from router.php, also with
//                                         the API off and over http
//   GET integration.php/discovery         the same through the API, under its
//                                         usual gates
//
// Nothing about people, keys, scopes or the firewall is in it. The version is
// given as its major line only (2026, not 2026.11.0), so one unauthenticated
// request does not name the exact build; GET /meta gives the exact version
// after sign-in.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The lowest app version the site works with. Null until a release needs one.
if (!defined('API_DISCOVERY_MIN_APP_VERSION')) {
	define('API_DISCOVERY_MIN_APP_VERSION', null);
}

// The document from the facts api_discovery_facts() gathers. Pure, so the
// rules are testable without a site.
//
// device_signin follows api_device_app_check(): the API on, the devices table
// there, a device application that is active and not past expires_at, and the
// caller's address on its allow list. A client that reads false shows its
// blocked screen before asking for a password that would be refused anyway.
function api_discovery_document($facts) {

	$app = isset($facts['device_app']) ? $facts['device_app'] : null;

	$device_signin = !empty($facts['api_enabled'])
		&& !empty($facts['devices_ready'])
		&& is_array($app)
		&& (isset($app['status']) && $app['status'] === 'active')
		&& !(((int)$app['expires_at'] > 0) && ((int)$app['expires_at'] < (int)$facts['now']))
		&& !empty($facts['ip_allowed']);

	$logo = isset($facts['logo_url']) ? (string)$facts['logo_url'] : '';

	return array(
		'software'        => 'pinegrap',
		'version'         => api_discovery_version_line($facts['version']),
		'api_version'     => 1,
		'api_base'        => (string)$facts['api_base'],
		'title'           => (string)$facts['title'],
		'logo_url'        => ($logo !== '') ? $logo : null,
		'language'        => (string)$facts['language'],
		'api_enabled'     => !empty($facts['api_enabled']),
		'device_signin'   => $device_signin,
		'pairing'         => $device_signin && !empty($facts['pairing_ready']),
		'https_required'  => !empty($facts['https_required']),
		'min_app_version' => API_DISCOVERY_MIN_APP_VERSION
	);

}

// The major line of a version (2026.11.0 -> 2026): the part before the first
// dot, '' for an empty version.
function api_discovery_version_line($version) {

	$version = trim((string)$version);
	$dot = strpos($version, '.');

	return ($dot === false) ? $version : substr($version, 0, $dot);

}

// Whether an application's IP allow list admits an address. An empty list
// admits everyone; entries are plain addresses, wildcards or CIDR ranges,
// matched the firewall's way when the firewall is loaded.
function api_ip_allowlist_admits($allowlist, $ip) {

	$allowlist = trim((string)$allowlist);

	if ($allowlist === '') {

		return true;

	}

	foreach (preg_split('/[\s,;]+/', $allowlist, -1, PREG_SPLIT_NO_EMPTY) as $pattern) {

		if (function_exists('waf_ip_matches') && waf_ip_matches($ip, $pattern)) {

			return true;

		}

		if ($ip === $pattern) {

			return true;

		}

	}

	return false;

}

// The facts of this site and this caller. Every read tolerates a site whose
// database is missing or not upgraded yet: the API is then reported off.
function api_discovery_facts() {

	$settings = api_settings();

	$installed = defined('DB_CONNECTED')
		&& (@mysqli_query(db::$con, "SELECT 1 FROM api_apps LIMIT 1") !== false);

	$enabled = $installed && $settings['enabled'];

	$devices_ready = $enabled && api_devices_ready();

	$app = $devices_ready ? api_device_app('') : null;

	$logo = null;

	if (defined('ORGANIZATION_LOGO') && function_exists('pg_resolve_config_image')) {

		$image = pg_resolve_config_image(ORGANIZATION_LOGO);

		if (is_array($image) && !empty($image['url'])) {

			$logo = (string)$image['url'];

		}

	}

	return array(
		'version'        => defined('VERSION') ? VERSION : '',
		'api_base'       => api_openapi_base_url(),
		'title'          => defined('TITLE') ? TITLE : '',
		'logo_url'       => $logo,
		'language'       => api_text_language(),
		'api_enabled'    => $enabled,
		'https_required' => !empty($settings['require_https']),
		'devices_ready'  => $devices_ready,
		'device_app'     => is_array($app) ? $app : null,
		'ip_allowed'     => is_array($app) && api_ip_allowlist_admits($app['ip_allowlist'], api_client_ip()),
		// POST /auth/pair answers once the pairing table is there.
		'pairing_ready'  => $devices_ready && api_pairing_ready(),
		'now'            => time()
	);

}

// The headers both answers carry. The answer depends on the caller's address
// (the allow list), so a shared cache must not hold it; any web page may read
// it, since it holds nothing secret.
function api_discovery_headers() {

	return array(
		'Cache-Control'               => 'private, max-age=300',
		'Access-Control-Allow-Origin' => '*'
	);

}

// The open route beside api_open_routes(): answered before credentials, and,
// like the other open routes, outside api_schema(), so neither the OpenAPI
// document nor the MCP tools list it.
function api_discovery_routes() {

	return array(

		array(
			'id'      => 'discovery',
			'method'  => 'GET',
			'path'    => '/discovery',
			'scope'   => '',
			'handler' => 'api_discovery_read',
			'summary' => 'What a client reads before signing in',
			'params'  => array()
		)

	);

}

// GET integration.php/discovery, an open route answered before credentials.
function api_discovery_read($params) {

	foreach (api_discovery_headers() as $name => $value) {

		api_extra_headers($name, $value);

	}

	api_ok(api_discovery_document(api_discovery_facts()));

}

// GET .well-known/pinegrap.json, handed over by router.php. Never through
// api_run(): it answers with the API off and over http, since a client needs
// it to tell the person exactly that.
function api_discovery_well_known() {

	$json = pg_json_encode_answer(api_discovery_document(api_discovery_facts()));

	while (ob_get_level() > 0) {

		ob_end_clean();

	}

	if (!headers_sent()) {

		http_response_code(200);

		header('Content-Type: application/json; charset=utf-8');

		pg_send_nosniff();

		foreach (api_discovery_headers() as $name => $value) {

			header($name . ': ' . $value);

		}

	}

	echo $json;

	exit();

}
