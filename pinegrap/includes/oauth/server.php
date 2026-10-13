<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site as an OAuth 2.1 authorization server: its addresses, the documents
 * that let a client find them, the registered clients, the authorization codes
 * and the scopes a connection holds.
 *
 * A connection is a person letting an outside application act for them. Its
 * tokens are api_devices rows (includes/api/devices.php) marked with the
 * client they belong to, so they authenticate on the API exactly like a
 * device does and end on every path that ends a person's devices. The
 * operator's switch and ceiling is an application of the 'oauth' kind, the
 * way a 'device' application is for phones.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Gated on init rather than on an entry point: the API, the OAuth endpoint
// and the nightly housekeeping all load it.
if (!defined('PG_INIT_LOADED')) {
	exit;
}

require_once(dirname(__FILE__) . '/rules.php');

// How long an authorization code may wait for the token request. RFC 6749
// recommends at most ten minutes; a client exchanges it within a second of
// receiving it, so one minute leaves room for a slow network and nothing else.
function pg_oauth_code_lifetime() {

	return 60;

}

// A dynamically registered client that has not been used for this long is
// removed. Registration is open, so rows from clients that registered and were
// never connected would otherwise pile up for ever.
function pg_oauth_idle_client_lifetime() {

	return 30 * 86400;

}

// Registrations one address may make in an hour. A client registers once and
// keeps its client_id; ten is generous for a person trying several tools.
function pg_oauth_registration_limit() {

	return 10;

}

// Whether the schema is there. Files land before the upgrade does.
function pg_oauth_ready() {

	static $ready = null;

	if ($ready === null) {

		$ready = ((int)db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'api_devices'
			AND COLUMN_NAME = 'oauth_client_id'") > 0)
			&& ((int)db_value("SELECT COUNT(*) FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'oauth_clients'") > 0);

	}

	return $ready;

}

// The operator's OAuth application: the oldest active one, so the answer does
// not change when a second is added for testing. Null while OAuth is off.
function pg_oauth_app() {

	static $app = false;

	if ($app !== false) {

		return $app;

	}

	$app = null;

	if (!pg_oauth_ready()) {

		return $app;

	}

	$row = db_item("SELECT id, name, api_key, owner_user_id, scopes, ip_allowlist,
			status, expires_at, rate_limit_per_min, kind
		FROM api_apps
		WHERE kind = 'oauth' AND status = 'active'
		ORDER BY id ASC
		LIMIT 1");

	if (is_array($row) && !empty($row)
		&& (((int)$row['expires_at'] === 0) || ((int)$row['expires_at'] > time()))) {

		$app = $row;

	}

	return $app;

}

function pg_oauth_active() {

	return (pg_oauth_app() !== null);

}

/* ---------------------------------------------------------------------------
   Addresses
   --------------------------------------------------------------------------- */

// The configured host rather than the Host header: these addresses are handed
// to outside applications, and a spoofed header must not decide where they
// send a person to sign in.
function pg_oauth_host() {

	if (defined('HOSTNAME_SETTING') && (HOSTNAME_SETTING !== '')) {

		return HOSTNAME_SETTING;

	}

	return defined('HOSTNAME') ? HOSTNAME : '';

}

function pg_oauth_scheme() {

	return defined('URL_SCHEME') ? URL_SCHEME : 'https://';

}

// The issuer is the site itself (RFC 8414 2): its address with no trailing
// slash, the software directory not included.
function pg_oauth_issuer() {

	return pg_oauth_scheme() . pg_oauth_host() . rtrim(PATH, '/');

}

function pg_oauth_endpoint($name) {

	return pg_oauth_scheme() . pg_oauth_host() . PATH . SOFTWARE_DIRECTORY . '/oauth.php/' . $name;

}

// The resources a token can be issued for (RFC 8707), by a short key. Each is
// the address its requests are made to; a token issued for one is refused by
// the others. The MCP endpoint joins this list when it is added.
function pg_oauth_resources() {

	return array(
		'integration' => pg_oauth_scheme() . pg_oauth_host() . PATH . SOFTWARE_DIRECTORY . '/integration.php'
	);

}

// The key of a resource address, or '' when the site has no such resource.
function pg_oauth_resource_key($resource) {

	$resource = pg_oauth_resource_normalise($resource);

	if ($resource === '') {

		return '';

	}

	foreach (pg_oauth_resources() as $key => $url) {

		if (strcasecmp(rtrim($url, '/'), $resource) === 0) {

			return $key;

		}

	}

	return '';

}

// Where a resource's metadata is read (RFC 9728 3.1): the well-known name put
// in front of the resource's path. The site's router answers it, which is why
// it is built on the site's own address - at the root of the host on a site
// installed there, under the installation's folder otherwise, where the
// router can still reach it. The resource names this address in its 401
// answers, so a client never has to work it out.
function pg_oauth_resource_metadata_url($key) {

	$resources = pg_oauth_resources();

	if (!isset($resources[$key])) {

		return '';

	}

	$path = parse_url($resources[$key], PHP_URL_PATH);

	return pg_oauth_scheme() . pg_oauth_host() . PATH . '.well-known/oauth-protected-resource' . (is_string($path) ? $path : '');

}

// Which resource a request for /.well-known/oauth-protected-resource<suffix>
// is about. No suffix is the API, the one resource every site has.
function pg_oauth_resource_for_suffix($suffix) {

	$suffix = '/' . trim((string)$suffix, '/');

	if ($suffix === '/') {

		return 'integration';

	}

	foreach (pg_oauth_resources() as $key => $url) {

		$path = parse_url($url, PHP_URL_PATH);

		if (is_string($path) && (strcasecmp(rtrim($path, '/'), $suffix) === 0)) {

			return $key;

		}

	}

	return '';

}

// The WWW-Authenticate challenge a resource adds to its 401 answers while
// OAuth is on (RFC 9728 5.1), which is how an MCP client that was refused
// finds out where to sign in. Empty when OAuth is off, and for a request that
// presented an application's key and secret: that caller is answered in its
// own scheme. Runs inside the API, whose header reader it uses.
function pg_oauth_bearer_challenge() {

	if (!function_exists('api_header') || !pg_oauth_active()) {

		return '';

	}

	$authorization = (string)api_header('Authorization');

	if ((stripos($authorization, 'basic ') === 0)
		|| (api_header('X-Pinegrap-Key') !== '')
		|| isset($_SERVER['PHP_AUTH_USER'])) {

		return '';

	}

	$url = pg_oauth_resource_metadata_url(defined('PG_OAUTH_RESOURCE') ? PG_OAUTH_RESOURCE : 'integration');

	if ($url === '') {

		return '';

	}

	$challenge = 'Bearer resource_metadata="' . $url . '"';

	if (stripos($authorization, 'bearer ') === 0) {

		$challenge .= ', error="invalid_token"';

	}

	return $challenge;

}

/* ---------------------------------------------------------------------------
   Scopes
   --------------------------------------------------------------------------- */

// The scopes a connection can ever hold: the operator's ceiling, with each
// write's read added. webhooks:manage is never handed out this way - a
// subscription sends the site's events to an address, which is set up once by
// an integration holding a key, not by an application a person clicked
// through.
function pg_oauth_ceiling($app) {

	$granted = json_decode((string)$app['scopes'], true);

	$scopes = api_scopes_expand(is_array($granted) ? $granted : array());

	return array_values(array_diff($scopes, array('webhooks:manage')));

}

// What a person may hand to an application today: the ceiling narrowed to
// their own rights.
function pg_oauth_available_scopes($app, $owner) {

	$scopes = array_intersect(pg_oauth_ceiling($app), api_owner_scopes($owner));

	sort($scopes);

	return array_values($scopes);

}

// What a connection may do on this request: what the person agreed to, within
// what they may still hand over. Worked out on every call, never stored, so a
// demotion or a narrower ceiling takes effect at once.
function pg_oauth_effective_scopes($app, $owner, $consented) {

	$consented = api_scopes_expand(is_array($consented) ? $consented : array());

	$scopes = array_intersect($consented, pg_oauth_available_scopes($app, $owner));

	sort($scopes);

	return array_values($scopes);

}

/* ---------------------------------------------------------------------------
   Discovery documents
   --------------------------------------------------------------------------- */

// RFC 8414. Also served as openid-configuration, which is the only name a
// client can reach on a site installed in a folder; it describes the same
// server and claims nothing OpenID Connect adds (no id_token, no userinfo).
function pg_oauth_metadata() {

	$app = pg_oauth_app();

	$methods = array('none', 'client_secret_basic', 'client_secret_post');

	return array(
		'issuer'                                         => pg_oauth_issuer(),
		'authorization_endpoint'                         => pg_oauth_endpoint('authorize'),
		'token_endpoint'                                 => pg_oauth_endpoint('token'),
		'registration_endpoint'                          => pg_oauth_endpoint('register'),
		'revocation_endpoint'                            => pg_oauth_endpoint('revoke'),
		'response_types_supported'                       => array('code'),
		'response_modes_supported'                       => array('query'),
		'grant_types_supported'                          => array('authorization_code', 'refresh_token'),
		'code_challenge_methods_supported'               => array('S256'),
		'token_endpoint_auth_methods_supported'          => $methods,
		'revocation_endpoint_auth_methods_supported'     => $methods,
		'scopes_supported'                               => ($app !== null) ? pg_oauth_ceiling($app) : array(),
		'authorization_response_iss_parameter_supported' => true
	);

}

// RFC 9728.
function pg_oauth_resource_metadata($key) {

	$resources = pg_oauth_resources();

	$app = pg_oauth_app();

	$name = defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== '') ? ORGANIZATION_NAME : pg_oauth_host();

	return array(
		'resource'                 => $resources[$key],
		'authorization_servers'    => array(pg_oauth_issuer()),
		'scopes_supported'         => ($app !== null) ? pg_oauth_ceiling($app) : array(),
		'bearer_methods_supported' => array('header'),
		'resource_name'            => $name . ' API'
	);

}

/* ---------------------------------------------------------------------------
   Clients
   --------------------------------------------------------------------------- */

function pg_oauth_client($client_id) {

	$client_id = trim((string)$client_id);

	if (($client_id === '') || (strlen($client_id) > 64) || !pg_oauth_ready()) {

		return null;

	}

	$row = db_item("SELECT * FROM oauth_clients WHERE client_id = '" . escape($client_id) . "' LIMIT 1");

	return (is_array($row) && !empty($row)) ? $row : null;

}

function pg_oauth_client_redirect_uris($client) {

	$list = json_decode((string)$client['redirect_uris'], true);

	return is_array($list) ? array_values(array_filter($list, 'is_string')) : array();

}

function pg_oauth_client_name($client) {

	return ((string)$client['name'] !== '') ? (string)$client['name'] : lang('Unnamed application');

}

// A confidential client's secret, compared as a hash. A public client has none
// to compare and passes on its client_id alone; what protects its codes is
// PKCE, and what protects its refresh tokens is that they turn over on every
// use.
function pg_oauth_client_secret_matches($client, $secret) {

	if ((string)$client['auth_method'] === 'none') {

		return true;

	}

	if (((string)$client['secret_hash'] === '') || !is_string($secret) || ($secret === '')) {

		return false;

	}

	return hash_equals((string)$client['secret_hash'], api_secret_hash($secret));

}

function pg_oauth_client_touch($client_row_id) {

	db("UPDATE oauth_clients SET last_used_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . (int)$client_row_id . "'");

}

/* ---------------------------------------------------------------------------
   Authorization codes
   --------------------------------------------------------------------------- */

// Writes a code and hands it back - the only moment it exists in the clear.
function pg_oauth_code_issue($client, $user_id, $redirect_uri, $scopes, $resource, $challenge) {

	pg_oauth_codes_sweep();

	$code = 'pgac_' . bin2hex(random_bytes(24));

	db("INSERT INTO oauth_codes
		(code_hash, client_id, user_id, redirect_uri, scopes, resource, code_challenge,
		 expires, created_timestamp)
		VALUES (
			'" . escape(api_secret_hash($code)) . "',
			'" . (int)$client['id'] . "',
			'" . (int)$user_id . "',
			'" . escape($redirect_uri) . "',
			'" . escape(json_encode(array_values($scopes))) . "',
			'" . escape($resource) . "',
			'" . escape($challenge) . "',
			'" . (time() + pg_oauth_code_lifetime()) . "',
			UNIX_TIMESTAMP()
		)");

	return $code;

}

// Codes stay for ten minutes past their end, long enough to recognise one
// that is presented again, then go.
function pg_oauth_codes_sweep() {

	db("DELETE FROM oauth_codes WHERE expires < '" . (time() - 600) . "'");

}

// Removes dynamically registered clients that never connected, or have not
// connected for a month, together with nothing else: a client that holds a
// connection is in use by definition and is kept.
function pg_oauth_clients_sweep() {

	if (!pg_oauth_ready()) {

		return;

	}

	$before = time() - pg_oauth_idle_client_lifetime();

	db("DELETE oauth_clients FROM oauth_clients
		LEFT JOIN api_devices ON api_devices.oauth_client_id = oauth_clients.id
		WHERE oauth_clients.registered_by = 'dynamic'
		AND GREATEST(oauth_clients.last_used_timestamp, oauth_clients.created_timestamp) < '" . $before . "'
		AND api_devices.id IS NULL");

}
