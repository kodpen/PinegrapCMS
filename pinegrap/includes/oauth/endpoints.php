<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The machine-facing OAuth endpoints: the discovery documents, dynamic client
 * registration (RFC 7591), the token endpoint and revocation (RFC 7009).
 * Everything here answers in OAuth's own wire format - {"error": "..."} with
 * the status the RFCs give - rather than the external API's error shape, so
 * that any OAuth library can read it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_OAUTH_ENTRY')) {
	exit;
}

/* ---------------------------------------------------------------------------
   Answering
   --------------------------------------------------------------------------- */

// The single exit for every JSON answer. Whatever the shared code printed on
// the way is dropped, as integration.php does, so the body stays parseable.
//
// Any origin may read these answers. They carry no cookie and act on no
// session - a browser-based client proves itself with PKCE and its own
// tokens - so allowing the read grants nothing a server-side client does not
// already have.
function pg_oauth_send($status, $body, $headers = array()) {

	while (ob_get_level() > 0) {

		ob_end_clean();

	}

	if (!headers_sent()) {

		http_response_code($status);

		header('Content-Type: application/json; charset=utf-8');

		header('Cache-Control: no-store');

		header('Pragma: no-cache');

		pg_send_nosniff();

		header('Access-Control-Allow-Origin: *');

		foreach ($headers as $name => $value) {

			header($name . ': ' . $value);

		}

	}

	if ($status !== 204) {

		echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	}

	exit();

}

// error_description is protocol text for the developer of the client, and
// RFC 6749 5.2 limits it to printable ASCII, so it is written in English and
// not translated.
function pg_oauth_fail($status, $error, $description = '', $headers = array()) {

	$body = array('error' => $error);

	if ($description !== '') {

		$body['error_description'] = $description;

	}

	pg_oauth_send($status, $body, $headers);

}

// The checks every endpoint shares, the discovery documents included: the
// schema is there, the API is on, the connection is encrypted where the site
// asks for that, the caller is not banned, and the operator has switched
// OAuth on. Until then the site answers as if it had no authorization server,
// which is the truth.
function pg_oauth_gate() {

	if (!defined('DB_CONNECTED') || !pg_oauth_ready()) {

		pg_oauth_fail(404, 'not_found');

	}

	$settings = api_settings();

	if (!$settings['enabled'] || !pg_oauth_active()) {

		pg_oauth_fail(404, 'not_found');

	}

	if ($settings['require_https'] && !pg_request_is_https()) {

		pg_oauth_fail(403, 'invalid_request', 'This API requires a secure (https) connection.');

	}

	if (function_exists('waf_ip_is_blocked')
		&& function_exists('waf_ip_is_allowed')
		&& !waf_ip_is_allowed(api_client_ip())
		&& waf_ip_is_blocked(api_client_ip())) {

		pg_oauth_fail(403, 'access_denied', 'We are currently unable to fulfill your request.');

	}

}

// A browser's preflight, answered for the endpoints a browser-based client
// calls with a body, and for the discovery documents, which MCP clients read
// with their protocol version in a header of its own.
function pg_oauth_preflight() {

	while (ob_get_level() > 0) {

		ob_end_clean();

	}

	http_response_code(204);

	header('Access-Control-Allow-Origin: *');

	header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

	header('Access-Control-Allow-Headers: Authorization, Content-Type, MCP-Protocol-Version');

	header('Access-Control-Max-Age: 86400');

	exit();

}

/* ---------------------------------------------------------------------------
   Discovery
   --------------------------------------------------------------------------- */

// /.well-known/... as the site's router hands it over: the name after the
// well-known segment, and for a protected resource the path after that.
function pg_oauth_well_known($name) {

	pg_oauth_gate();

	if (($name === 'oauth-authorization-server') || ($name === 'openid-configuration')) {

		pg_oauth_send(200, pg_oauth_metadata());

	}

	if (strpos($name, 'oauth-protected-resource') === 0) {

		$resource = pg_oauth_resource_for_suffix((string)substr($name, strlen('oauth-protected-resource')));

		if ($resource !== '') {

			pg_oauth_send(200, pg_oauth_resource_metadata($resource));

		}

	}

	pg_oauth_fail(404, 'not_found');

}

/* ---------------------------------------------------------------------------
   Client authentication
   --------------------------------------------------------------------------- */

// The client a token or revocation request comes from, authenticated, or an
// invalid_client answer (RFC 6749 5.2).
//
// HTTP Basic carries the pair form-encoded (RFC 6749 2.3.1), so both halves
// are decoded. A client registered for one method must use it: a secret sent
// in the body by a client registered for Basic is still accepted, because it
// is the same secret over the same encrypted connection, but a confidential
// client sending no secret at all is refused.
function pg_oauth_authenticate_client() {

	$client_id = '';
	$secret    = '';
	$basic     = false;

	$authorization = api_header('Authorization');

	if (stripos($authorization, 'basic ') === 0) {

		$decoded = base64_decode(substr($authorization, 6), true);

		if (($decoded !== false) && (strpos($decoded, ':') !== false)) {

			list($client_id, $secret) = explode(':', $decoded, 2);

			$client_id = urldecode($client_id);
			$secret    = urldecode($secret);
			$basic     = true;

		}

	}

	if (!$basic) {

		$client_id = isset($_POST['client_id']) ? (string)$_POST['client_id'] : '';
		$secret    = isset($_POST['client_secret']) ? (string)$_POST['client_secret'] : '';

	}

	$client = pg_oauth_client($client_id);

	if (($client === null) || ($client['status'] !== 'active') || !pg_oauth_client_secret_matches($client, $secret)) {

		// A client that is not there and a secret that is wrong get the same
		// answer, so neither can be found out from it. A wrong secret is a
		// guess at a credential and goes to the firewall like one; an unknown
		// public client_id is far more often a client whose registration was
		// swept, and is not.
		if (($client !== null) && ((string)$client['auth_method'] !== 'none')) {

			api_offence();

		}

		pg_oauth_fail(401, 'invalid_client', 'Client authentication failed.',
			$basic ? array('WWW-Authenticate' => 'Basic realm="' . pg_oauth_issuer() . '"') : array());

	}

	return $client;

}

/* ---------------------------------------------------------------------------
   Token endpoint
   --------------------------------------------------------------------------- */

function pg_oauth_token() {

	pg_oauth_gate();

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

		pg_oauth_fail(405, 'invalid_request', 'Use POST.', array('Allow' => 'POST'));

	}

	$client = pg_oauth_authenticate_client();

	$grant = isset($_POST['grant_type']) ? (string)$_POST['grant_type'] : '';

	if ($grant === 'authorization_code') {

		pg_oauth_token_from_code($client);

	}

	if ($grant === 'refresh_token') {

		pg_oauth_token_from_refresh($client);

	}

	pg_oauth_fail(400, 'unsupported_grant_type');

}

// The person behind a grant may still connect: the account is there and has
// some right an application could use. Answers invalid_grant otherwise, and
// ends the connection when there is one.
function pg_oauth_owner_or_fail($user_id, $device_id = 0) {

	$owner = api_load_owner((int)$user_id);

	if (($owner === null) || !api_device_account_has_rights($owner)) {

		if ($device_id > 0) {

			api_device_revoke($device_id);

		}

		pg_oauth_fail(400, 'invalid_grant', 'This account can no longer connect applications.');

	}

	return $owner;

}

// A resource named on the token request must be the one the code or the
// connection was issued for (RFC 8707 2.2).
function pg_oauth_check_resource($stored) {

	if (!isset($_POST['resource']) || ((string)$_POST['resource'] === '')) {

		return;

	}

	$asked = pg_oauth_resource_normalise((string)$_POST['resource']);

	// Compared by resource rather than by spelling: the MCP server answers on
	// two addresses, and a token for one is a token for the other.
	if (($asked === '') || (pg_oauth_resource_key($asked) === '')
		|| (((string)$stored !== '') && (pg_oauth_resource_key($asked) !== pg_oauth_resource_key((string)$stored)))) {

		pg_oauth_fail(400, 'invalid_target', 'The resource is not one this token can be issued for.');

	}

}

function pg_oauth_token_answer($issued, $scopes) {

	pg_oauth_send(200, array(
		'access_token'  => $issued['access_token'],
		'token_type'    => 'Bearer',
		'expires_in'    => max(0, (int)$issued['access_expires'] - time()),
		'refresh_token' => $issued['refresh_token'],
		'scope'         => pg_oauth_scope_string($scopes)
	));

}

function pg_oauth_token_from_code($client) {

	$code     = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
	$verifier = isset($_POST['code_verifier']) ? (string)$_POST['code_verifier'] : '';
	$redirect = isset($_POST['redirect_uri']) ? (string)$_POST['redirect_uri'] : '';

	if (($code === '') || ($verifier === '')) {

		pg_oauth_fail(400, 'invalid_request', 'code and code_verifier are required.');

	}

	$hash = api_secret_hash($code);

	$row = db_item("SELECT * FROM oauth_codes WHERE code_hash = '" . escape($hash) . "' LIMIT 1");

	if (!is_array($row) || ((int)$row['client_id'] !== (int)$client['id'])) {

		pg_oauth_fail(400, 'invalid_grant', 'The authorization code is not valid.');

	}

	// Seen before: whoever presents it now is not the client that redeemed
	// it, or the same client was replayed. Either way the connection made from
	// it is ended (RFC 6749 4.1.2), so neither party keeps tokens from a code
	// that leaked.
	if ((int)$row['used_timestamp'] > 0) {

		if ((int)$row['device_id'] > 0) {

			api_device_revoke((int)$row['device_id']);

		}

		pg_oauth_fail(400, 'invalid_grant', 'The authorization code has already been used.');

	}

	if ((int)$row['expires'] < time()) {

		pg_oauth_fail(400, 'invalid_grant', 'The authorization code has expired.');

	}

	// OAuth 2.1 asks for the redirect address again when one was sent with
	// the authorization request, and compared the way it was matched there: a
	// loopback address by its path and query. A request that sent none was
	// answered at the client's only address and may send none here either.
	$stored = (string)$row['redirect_uri'];

	$same = ($stored === '')
		? (($redirect === '') || pg_oauth_redirect_uri_matches(pg_oauth_client_redirect_uris($client), $redirect))
		: pg_oauth_redirect_uri_matches(array($stored), $redirect);

	if (!$same) {

		pg_oauth_fail(400, 'invalid_grant', 'The redirect address does not match the authorization request.');

	}

	if (!pg_oauth_pkce_verify($verifier, (string)$row['code_challenge'])) {

		pg_oauth_fail(400, 'invalid_grant', 'The code verifier does not match.');

	}

	pg_oauth_check_resource($row['resource']);

	// Claimed before anything is issued, and only by the request whose update
	// changed the row: two requests racing with the same code cannot both
	// get tokens.
	db("UPDATE oauth_codes SET used_timestamp = UNIX_TIMESTAMP()
		WHERE code_hash = '" . escape($hash) . "' AND used_timestamp = 0");

	if (mysqli_affected_rows(db::$con) !== 1) {

		pg_oauth_fail(400, 'invalid_grant', 'The authorization code has already been used.');

	}

	$app = pg_oauth_app();

	$owner = pg_oauth_owner_or_fail((int)$row['user_id']);

	$consented = json_decode((string)$row['scopes'], true);

	$consented = is_array($consented) ? $consented : array();

	$issued = api_device_issue($app, (int)$row['user_id'], pg_oauth_client_name($client), 'oauth',
		(string)$client['software_version'], array(
			'client_id' => (int)$client['id'],
			'scopes'    => $consented,
			'resource'  => (string)$row['resource']
		));

	db("UPDATE oauth_codes SET device_id = '" . (int)$issued['device_id'] . "'
		WHERE code_hash = '" . escape($hash) . "'");

	pg_oauth_client_touch((int)$client['id']);

	log_activity(lang(array(
		'string' => 'connected an application with OAuth ({var:1})',
		'vars'   => pg_oauth_client_name($client)
	)), $owner['username']);

	pg_oauth_token_answer($issued, pg_oauth_effective_scopes($app, $owner, $consented));

}

function pg_oauth_token_from_refresh($client) {

	$token = isset($_POST['refresh_token']) ? trim((string)$_POST['refresh_token']) : '';

	if ($token === '') {

		pg_oauth_fail(400, 'invalid_request', 'refresh_token is required.');

	}

	// Whose token it is, before anything turns over: a token presented by a
	// client it was not issued to is refused and left as it was.
	$found = api_device_find_refresh($token);

	if (($found === null) || ((int)$found['oauth_client_id'] !== (int)$client['id'])) {

		pg_oauth_fail(400, 'invalid_grant', 'The refresh token is not valid.');

	}

	pg_oauth_check_resource($found['resource']);

	$consented = json_decode((string)$found['scopes'], true);

	$consented = is_array($consented) ? $consented : array();

	// A refresh may ask for less than was granted, never more (RFC 6749 6).
	// The connection keeps what the person agreed to; the narrower set is not
	// stored, because the next refresh may ask for all of it again. Values the
	// site has no scope of are passed over, as on the consent screen: a
	// client sending its first request's scope string again, offline_access
	// and all, asks for nothing new.
	if (isset($_POST['scope']) && ((string)$_POST['scope'] !== '')) {

		$asked = array_intersect(pg_oauth_scope_parse((string)$_POST['scope']), api_scopes_all());

		if (count(array_diff($asked, api_scopes_expand($consented))) > 0) {

			pg_oauth_fail(400, 'invalid_scope', 'A refresh cannot ask for more than was granted.');

		}

	}

	$rotated = api_device_rotate_pair($token);

	if ($rotated['error'] !== '') {

		pg_oauth_fail(400, 'invalid_grant', 'The refresh token is not valid.');

	}

	$row = $rotated['row'];

	$app = pg_oauth_app();

	// The operator's application may have been replaced since the connection
	// was made; a connection belongs to the one it was made under.
	if ((int)$row['app_id'] !== (int)$app['id']) {

		api_device_revoke((int)$row['id']);

		pg_oauth_fail(400, 'invalid_grant', 'The refresh token is not valid.');

	}

	$owner = pg_oauth_owner_or_fail((int)$row['user_id'], (int)$row['id']);

	pg_oauth_client_touch((int)$client['id']);

	pg_oauth_token_answer($rotated, pg_oauth_effective_scopes($app, $owner, $consented));

}

/* ---------------------------------------------------------------------------
   Revocation
   --------------------------------------------------------------------------- */

// Ends the connection a token belongs to, access or refresh. The answer is 200
// whether or not the token was found (RFC 7009 2.2): ending a token that is
// already gone is what the caller wanted, and telling the two apart would say
// which tokens exist.
function pg_oauth_revoke() {

	pg_oauth_gate();

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

		pg_oauth_fail(405, 'invalid_request', 'Use POST.', array('Allow' => 'POST'));

	}

	$client = pg_oauth_authenticate_client();

	$token = isset($_POST['token']) ? trim((string)$_POST['token']) : '';

	if ($token === '') {

		pg_oauth_fail(400, 'invalid_request', 'token is required.');

	}

	$hash = escape(api_secret_hash($token));

	$device_id = (int)db_value("SELECT id FROM api_devices
		WHERE oauth_client_id = '" . (int)$client['id'] . "'
		AND (access_hash = '" . $hash . "' OR refresh_hash = '" . $hash . "' OR refresh_prev_hash = '" . $hash . "')
		LIMIT 1");

	if ($device_id > 0) {

		api_device_revoke($device_id);

	}

	pg_oauth_send(200, new stdClass());

}

/* ---------------------------------------------------------------------------
   Dynamic client registration
   --------------------------------------------------------------------------- */

// RFC 7591. Open: an MCP client registers itself the first time it meets the
// site, with nobody at the panel to do it for it. Registering grants nothing -
// every connection still passes a person's consent screen - so what is
// guarded here is the table, by a limit per address and by the nightly sweep
// of clients that never connected.
function pg_oauth_register() {

	pg_oauth_gate();

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

		pg_oauth_fail(405, 'invalid_request', 'Use POST.', array('Allow' => 'POST'));

	}

	if (function_exists('waf_rate_exceeded')
		&& waf_rate_exceeded(api_client_ip(), 'oauth_reg', pg_oauth_registration_limit(), 3600)) {

		pg_oauth_fail(429, 'temporarily_unavailable', 'Too many registrations from this address. Try again later.',
			array('Retry-After' => '3600'));

	}

	$body = json_decode((string)file_get_contents('php://input'), true);

	if (!is_array($body)) {

		pg_oauth_fail(400, 'invalid_client_metadata', 'The request body must be a JSON object.');

	}

	$metadata = pg_oauth_registration_validate($body);

	if (isset($metadata['error'])) {

		pg_oauth_fail(400, $metadata['error'], $metadata['description']);

	}

	$client_id = 'pgoc_' . bin2hex(random_bytes(16));

	$secret = ($metadata['token_endpoint_auth_method'] !== 'none')
		? 'pgcs_' . pg_oauth_base64url(random_bytes(32))
		: '';

	$now = time();

	db("INSERT INTO oauth_clients
		(client_id, secret_hash, name, client_uri, redirect_uris, auth_method, software_id,
		 software_version, registered_by, status, created_ip, created_timestamp)
		VALUES (
			'" . escape($client_id) . "',
			'" . escape(($secret !== '') ? api_secret_hash($secret) : '') . "',
			'" . escape($metadata['client_name']) . "',
			'" . escape($metadata['client_uri']) . "',
			'" . escape(json_encode($metadata['redirect_uris'], JSON_UNESCAPED_SLASHES)) . "',
			'" . escape($metadata['token_endpoint_auth_method']) . "',
			'" . escape($metadata['software_id']) . "',
			'" . escape($metadata['software_version']) . "',
			'dynamic',
			'active',
			'" . escape(mb_substr(api_client_ip(), 0, 45)) . "',
			'" . $now . "'
		)");

	$answer = array(
		'client_id'           => $client_id,
		'client_id_issued_at' => $now
	);

	if ($secret !== '') {

		$answer['client_secret'] = $secret;

		$answer['client_secret_expires_at'] = 0;

	}

	foreach (array('client_name', 'client_uri', 'software_id', 'software_version') as $field) {

		if ($metadata[$field] !== '') {

			$answer[$field] = $metadata[$field];

		}

	}

	$answer['redirect_uris']              = $metadata['redirect_uris'];
	$answer['grant_types']                = $metadata['grant_types'];
	$answer['response_types']             = array('code');
	$answer['token_endpoint_auth_method'] = $metadata['token_endpoint_auth_method'];

	pg_oauth_send(201, $answer);

}

// The metadata a registration may set, checked and cut to size, or an array
// with 'error' and 'description' (RFC 7591 3.2.2). Fields this server does
// not use - a logo, contacts, a policy address - are accepted and dropped,
// as section 2 allows.
function pg_oauth_registration_validate($body) {

	$text = function ($value, $length) {

		if (!is_string($value)) {

			return '';

		}

		return mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)), 0, $length);

	};

	$redirect_uris = isset($body['redirect_uris']) ? $body['redirect_uris'] : null;

	if (!is_array($redirect_uris) || (count($redirect_uris) === 0) || (count($redirect_uris) > 10)) {

		return array('error' => 'invalid_redirect_uri', 'description' => 'Between one and ten redirect_uris are required.');

	}

	$clean = array();

	foreach ($redirect_uris as $uri) {

		if (!pg_oauth_redirect_uri_is_acceptable($uri)) {

			return array('error' => 'invalid_redirect_uri', 'description' => 'A redirect address must use https, a loopback http address or an app scheme, and carry no fragment.');

		}

		if (!in_array($uri, $clean, true)) {

			$clean[] = $uri;

		}

	}

	$method = isset($body['token_endpoint_auth_method']) ? (string)$body['token_endpoint_auth_method'] : 'client_secret_basic';

	if (!in_array($method, array('none', 'client_secret_basic', 'client_secret_post'), true)) {

		return array('error' => 'invalid_client_metadata', 'description' => 'token_endpoint_auth_method must be none, client_secret_basic or client_secret_post.');

	}

	// A client is registered for what this server has - the code grant, with
	// the refresh tokens every connection gets, and response type code -
	// whatever else it lists: one that also names the device code grant or
	// client credentials is not refused for it (RFC 7591 2 lets the server
	// replace what it does not support, and the answer says what was
	// registered). One that does not list the code grant at all cannot
	// connect here.
	$grant_types = isset($body['grant_types']) ? $body['grant_types'] : array('authorization_code');

	if (!is_array($grant_types) || !in_array('authorization_code', $grant_types, true)) {

		return array('error' => 'invalid_client_metadata', 'description' => 'grant_types must include authorization_code.');

	}

	$response_types = isset($body['response_types']) ? $body['response_types'] : array('code');

	if (!is_array($response_types) || !in_array('code', $response_types, true)) {

		return array('error' => 'invalid_client_metadata', 'description' => 'response_types must include code.');

	}

	$client_uri = $text(isset($body['client_uri']) ? $body['client_uri'] : '', 255);

	if (($client_uri !== '') && (stripos($client_uri, 'https://') !== 0)) {

		$client_uri = '';

	}

	return array(
		'redirect_uris'              => $clean,
		'token_endpoint_auth_method' => $method,
		'grant_types'                => array('authorization_code', 'refresh_token'),
		'client_name'                => $text(isset($body['client_name']) ? $body['client_name'] : '', 100),
		'client_uri'                 => $client_uri,
		'software_id'                => $text(isset($body['software_id']) ? $body['software_id'] : '', 100),
		'software_version'           => $text(isset($body['software_version']) ? $body['software_version'] : '', 40)
	);

}
