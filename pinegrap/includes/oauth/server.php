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

// How long a client metadata document is trusted before it is read again, and
// how long an old copy may stand in when reading it again fails: the client's
// own server being down for an afternoon should not stop people connecting.
function pg_oauth_metadata_lifetime() {

	return 86400;

}

function pg_oauth_metadata_stale_limit() {

	return 7 * 86400;

}

// Client metadata documents one address may make the site read in an hour.
// Each read is a request the site makes to an address somebody else chose.
function pg_oauth_metadata_fetch_limit() {

	return 30;

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

// Whether clients may name themselves with a metadata document: the column
// that holds the copy's age arrived a step after the tables.
function pg_oauth_metadata_ready() {

	static $ready = null;

	if ($ready === null) {

		$ready = pg_oauth_ready() && ((int)db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'oauth_clients'
			AND COLUMN_NAME = 'metadata_expires'") > 0);

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

// The addresses each resource answers on, by a short key; the first is the
// one the site gives out. The MCP server is given out at the site's own
// address - site.com/mcp.php is what a person types into an AI application -
// which the router hands to the file in the software directory, where it
// answers too. A client checks that the resource its metadata names is the
// address it connected to, so either address is a resource of its own name
// with the same key.
function pg_oauth_resource_addresses() {

	$site = pg_oauth_scheme() . pg_oauth_host() . PATH;

	$base = $site . SOFTWARE_DIRECTORY;

	return array(
		'integration' => array($base . '/integration.php'),
		'mcp'         => array($site . 'mcp.php', $base . '/mcp.php')
	);

}

// The resources a token can be issued for (RFC 8707), by a short key, each at
// the address the site gives out. A token issued for one is refused by the
// others.
function pg_oauth_resources() {

	$resources = array();

	foreach (pg_oauth_resource_addresses() as $key => $addresses) {

		$resources[$key] = $addresses[0];

	}

	return $resources;

}

// The key of a resource address, or '' when the site has no such resource.
function pg_oauth_resource_key($resource) {

	$resource = pg_oauth_resource_normalise($resource);

	if ($resource === '') {

		return '';

	}

	foreach (pg_oauth_resource_addresses() as $key => $addresses) {

		foreach ($addresses as $url) {

			if (strcasecmp(rtrim($url, '/'), $resource) === 0) {

				return $key;

			}

		}

	}

	return '';

}

// The address of a resource this request was made to, or the one the site
// gives out when the request came in some other way. The router is reached
// through a rewrite, so IIS's original address is read first, as the router
// reads it.
function pg_oauth_request_resource($key) {

	$resources = pg_oauth_resources();

	if (!isset($resources[$key])) {

		return '';

	}

	$uri = '';

	foreach (array('HTTP_X_ORIGINAL_URL', 'HTTP_X_REWRITE_URL', 'REQUEST_URI') as $name) {

		if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && ($_SERVER[$name] !== '')) {

			$uri = $_SERVER[$name];

			break;

		}

	}

	$path = (string)parse_url($uri, PHP_URL_PATH);

	if ($path !== '') {

		$address = pg_oauth_scheme() . pg_oauth_host() . $path;

		if (pg_oauth_resource_key($address) === $key) {

			return rtrim($address, '/');

		}

	}

	return $resources[$key];

}

// Where a resource's metadata is read (RFC 9728 3.1): the well-known name put
// in front of the resource's path. The site's router answers it, which is why
// it is built on the site's own address - at the root of the host on a site
// installed there, under the installation's folder otherwise, where the
// router can still reach it. The resource names this address in its 401
// answers, so a client never has to work it out.
function pg_oauth_resource_metadata_url($resource) {

	if (pg_oauth_resource_key($resource) === '') {

		return '';

	}

	$path = parse_url($resource, PHP_URL_PATH);

	return pg_oauth_scheme() . pg_oauth_host() . PATH . '.well-known/oauth-protected-resource' . (is_string($path) ? $path : '');

}

// Which resource address a request for
// /.well-known/oauth-protected-resource<suffix> is about, or ''. No suffix is
// the API, the one resource every site has.
function pg_oauth_resource_for_suffix($suffix) {

	$suffix = '/' . trim((string)$suffix, '/');

	if ($suffix === '/') {

		$resources = pg_oauth_resources();

		return $resources['integration'];

	}

	foreach (pg_oauth_resource_addresses() as $addresses) {

		foreach ($addresses as $url) {

			$path = parse_url($url, PHP_URL_PATH);

			if (is_string($path) && (strcasecmp(rtrim($path, '/'), $suffix) === 0)) {

				return $url;

			}

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

	$url = pg_oauth_resource_metadata_url(pg_oauth_request_resource(defined('PG_OAUTH_RESOURCE') ? PG_OAUTH_RESOURCE : 'integration'));

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
		'authorization_response_iss_parameter_supported' => true,
		'client_id_metadata_document_supported'          => pg_oauth_metadata_ready()
	);

}

// RFC 9728, for one of a resource's addresses.
function pg_oauth_resource_metadata($resource) {

	$key = pg_oauth_resource_key($resource);

	$app = pg_oauth_app();

	$name = defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== '') ? ORGANIZATION_NAME : pg_oauth_host();

	return array(
		'resource'                 => rtrim($resource, '/'),
		'authorization_servers'    => array(pg_oauth_issuer()),
		'scopes_supported'         => ($app !== null) ? pg_oauth_ceiling($app) : array(),
		'bearer_methods_supported' => array('header'),
		'resource_name'            => $name . (($key === 'mcp') ? ' MCP' : ' API')
	);

}

/* ---------------------------------------------------------------------------
   Clients
   --------------------------------------------------------------------------- */

function pg_oauth_client($client_id) {

	$client_id = trim((string)$client_id);

	if (($client_id === '') || (strlen($client_id) > 255) || !pg_oauth_ready()) {

		return null;

	}

	$row = db_item("SELECT * FROM oauth_clients WHERE client_id = '" . escape($client_id) . "' LIMIT 1");

	return (is_array($row) && !empty($row)) ? $row : null;

}

// The client a person is asked to connect, on the authorization screen.
//
// A registered client is looked up. A client_id that is an https address is
// a client metadata document (draft-ietf-oauth-client-id-metadata-document):
// the document is read from that address and kept as an oauth_clients row, so
// the token endpoint, the API and the panel find the client like any other.
// The copy is read again once a day; when that fails an older copy stands in
// for a week. Answers array('client' => row|null, 'error' => string), error
// being English for the client's developer.
//
// Only this screen reads a document, and only for a person signed in to the
// panel (includes/oauth/authorize.php): the read is a request the site makes
// to an address in the request, and nobody without an account should be able
// to make the site send one.
function pg_oauth_client_resolve($client_id) {

	$client_id = trim((string)$client_id);

	if (!pg_oauth_client_id_is_url($client_id)) {

		return array('client' => pg_oauth_client($client_id), 'error' => '');

	}

	if (!pg_oauth_metadata_ready()) {

		return array('client' => null, 'error' => 'This site does not read client metadata documents yet.');

	}

	$row = pg_oauth_client($client_id);

	if (($row !== null) && ((string)$row['registered_by'] !== 'metadata')) {

		$row = null;

	}

	// A copy the operator turned off is not read again: it is refused
	// whatever the document says now.
	if (($row !== null) && (((int)$row['metadata_expires'] > time()) || ((string)$row['status'] !== 'active'))) {

		return array('client' => $row, 'error' => '');

	}

	// A copy still within its week stands in when the read fails or is not
	// allowed right now.
	$stale = (($row !== null) && ((int)$row['metadata_expires'] + pg_oauth_metadata_stale_limit() > time())) ? $row : null;

	if (function_exists('waf_rate_exceeded') && function_exists('waf_client_ip')
		&& waf_rate_exceeded(waf_client_ip(), 'oauth_cimd', pg_oauth_metadata_fetch_limit(), 3600)) {

		return array('client' => $stale, 'error' => ($stale === null) ? 'Too many client metadata documents read from this address. Try again later.' : '');

	}

	require_once(dirname(dirname(__FILE__)) . '/api/outbound/http.php');

	$answer = api_http_request('GET', $client_id, array(
		'headers'  => array('Accept: application/json'),
		'timeout'  => 5,
		'max_body' => pg_oauth_metadata_document_limit() + 1,
		'agent'    => 'oauth'
	));

	if (!$answer['ok'] || ($answer['status'] !== 200)) {

		return array(
			'client' => $stale,
			'error'  => ($stale === null) ? 'The client metadata document could not be read'
				. (($answer['status'] > 0) ? ' (HTTP ' . $answer['status'] . ').' : ': ' . $answer['error']) : ''
		);

	}

	$read = pg_oauth_metadata_document_client($client_id, $answer['body']);

	if (!$read['ok']) {

		return array('client' => null, 'error' => $read['error']);

	}

	$client = $read['client'];

	$fields = "name = '" . escape($client['name']) . "',
		client_uri = '" . escape($client['client_uri']) . "',
		redirect_uris = '" . escape(json_encode($client['redirect_uris'], JSON_UNESCAPED_SLASHES)) . "',
		software_id = '" . escape($client['software_id']) . "',
		software_version = '" . escape($client['software_version']) . "',
		metadata_expires = '" . (time() + pg_oauth_metadata_lifetime()) . "'";

	if ($row !== null) {

		db("UPDATE oauth_clients SET " . $fields . " WHERE id = '" . (int)$row['id'] . "'");

	} else {

		db("INSERT INTO oauth_clients SET
			client_id = '" . escape($client_id) . "',
			auth_method = 'none',
			registered_by = 'metadata',
			status = 'active',
			created_ip = '" . escape(function_exists('waf_client_ip') ? substr((string)waf_client_ip(), 0, 45) : '') . "',
			created_timestamp = UNIX_TIMESTAMP(),
			" . $fields);

	}

	return array('client' => pg_oauth_client($client_id), 'error' => '');

}

// The host a metadata client is identified by: the part of its client_id a
// person can check, unlike the name the document gives itself.
function pg_oauth_client_identity_host($client) {

	if ((string)$client['registered_by'] !== 'metadata') {

		return '';

	}

	$host = parse_url((string)$client['client_id'], PHP_URL_HOST);

	return is_string($host) ? strtolower($host) : '';

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

// Removes dynamically registered clients and copies of metadata documents
// that never connected, or have not connected for a month, together with
// nothing else: a client that holds a connection is in use by definition and
// is kept. So is one the operator turned off: the row is the record of that
// decision, and a metadata client removed here would be read back in, switched
// on, the next time it asked.
function pg_oauth_clients_sweep() {

	if (!pg_oauth_ready()) {

		return;

	}

	$before = time() - pg_oauth_idle_client_lifetime();

	db("DELETE oauth_clients FROM oauth_clients
		LEFT JOIN api_devices ON api_devices.oauth_client_id = oauth_clients.id
		WHERE oauth_clients.registered_by IN ('dynamic', 'metadata')
		AND oauth_clients.status = 'active'
		AND GREATEST(oauth_clients.last_used_timestamp, oauth_clients.created_timestamp) < '" . $before . "'
		AND api_devices.id IS NULL");

}

/* ---------------------------------------------------------------------------
   The panel: the clients the operator looks after, and the connections a
   person looks after for themselves
   --------------------------------------------------------------------------- */

// Every registered client with the number of connections it holds, the
// clients still in use first. Empty before the schema is there.
function pg_oauth_clients_overview($limit = 200) {

	if (!pg_oauth_ready()) {

		return array();

	}

	$rows = db_items("SELECT oauth_clients.id, oauth_clients.client_id, oauth_clients.name,
			oauth_clients.client_uri, oauth_clients.redirect_uris, oauth_clients.registered_by,
			oauth_clients.status, oauth_clients.software_id, oauth_clients.software_version,
			oauth_clients.created_timestamp, oauth_clients.last_used_timestamp,
			COUNT(api_devices.id) AS connections
		FROM oauth_clients
		LEFT JOIN api_devices ON api_devices.oauth_client_id = oauth_clients.id
		GROUP BY oauth_clients.id
		ORDER BY (COUNT(api_devices.id) > 0) DESC,
			GREATEST(oauth_clients.last_used_timestamp, oauth_clients.created_timestamp) DESC
		LIMIT " . max(1, (int)$limit));

	return is_array($rows) ? $rows : array();

}

// One person's connections, the most recently used first, each with the
// client it belongs to. A connection whose client row is gone is listed too,
// under no name, so that it can still be ended.
function pg_oauth_connections_of_user($user_id) {

	$user_id = (int)$user_id;

	if (($user_id < 1) || !pg_oauth_ready()) {

		return array();

	}

	$rows = db_items("SELECT api_devices.id, api_devices.scopes, api_devices.resource,
			api_devices.created_timestamp, api_devices.last_used_timestamp, api_devices.last_used_ip,
			oauth_clients.id AS client_row_id, oauth_clients.client_id, oauth_clients.name,
			oauth_clients.registered_by, oauth_clients.status
		FROM api_devices
		LEFT JOIN oauth_clients ON oauth_clients.id = api_devices.oauth_client_id
		WHERE api_devices.user_id = '" . $user_id . "'
		AND api_devices.oauth_client_id > 0
		ORDER BY GREATEST(api_devices.last_used_timestamp, api_devices.created_timestamp) DESC");

	return is_array($rows) ? $rows : array();

}

// Ends one of a person's connections. Only a connection that belongs to them
// and is an OAuth connection: the id comes from a form. Answers the client's
// name, or null when there was nothing of theirs to end.
function pg_oauth_connection_end($user_id, $device_id) {

	$row = db_item("SELECT api_devices.id, oauth_clients.name
		FROM api_devices
		LEFT JOIN oauth_clients ON oauth_clients.id = api_devices.oauth_client_id
		WHERE api_devices.id = '" . (int)$device_id . "'
		AND api_devices.user_id = '" . (int)$user_id . "'
		AND api_devices.oauth_client_id > 0
		LIMIT 1");

	if (!is_array($row) || empty($row)) {

		return null;

	}

	api_device_revoke((int)$row['id']);

	return ((string)$row['name'] !== '') ? (string)$row['name'] : lang('Unnamed application');

}

// Turns a client off or back on. While it is off it cannot start a
// connection, its refresh tokens are refused at the token endpoint and its
// access tokens on every request (api_device_oauth_check()); its connections
// are kept, so turning it back on gives them back without anybody agreeing
// again.
function pg_oauth_client_set_status($client_row_id, $active) {

	db("UPDATE oauth_clients SET status = '" . ($active ? 'active' : 'disabled') . "'
		WHERE id = '" . (int)$client_row_id . "'");

}

// Removes a client with every connection it holds and the codes waiting for
// it. An application that registered itself may register again; one that is
// to stay out is turned off instead.
function pg_oauth_client_delete($client_row_id) {

	$client_row_id = (int)$client_row_id;

	if ($client_row_id < 1) {

		return;

	}

	foreach (db_values("SELECT id FROM api_devices WHERE oauth_client_id = '" . $client_row_id . "'") as $device_id) {

		api_device_revoke((int)$device_id);

	}

	db("DELETE FROM oauth_codes WHERE client_id = '" . $client_row_id . "'");

	db("DELETE FROM oauth_clients WHERE id = '" . $client_row_id . "'");

}

// What a set of granted scopes lets an application do, one line per resource
// in the order the permission screens use: array(label, 'Read only' or 'Read
// and write' or 'Manage').
function pg_oauth_scope_lines($scopes) {

	$scopes = api_scopes_expand(is_array($scopes) ? $scopes : array());

	$lines = array();

	foreach (api_scope_groups() as $group) {

		if (($group['write'] !== '') && in_array($group['write'], $scopes, true)) {

			$lines[] = array(lang($group['label']), ($group['read'] === '') ? lang('Manage') : lang('Read and write'));

		} elseif (($group['read'] !== '') && in_array($group['read'], $scopes, true)) {

			$lines[] = array(lang($group['label']), lang('Read only'));

		}

	}

	return $lines;

}
