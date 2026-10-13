<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site as an MCP server (Model Context Protocol), over Streamable HTTP:
 * one POST carries one JSON-RPC message, the answer is one JSON object.
 *
 * Two generations of the protocol are answered. The current one (2026-07-28)
 * has no session and no handshake: every request carries its version, in the
 * MCP-Protocol-Version header and in _meta, and names its method and tool in
 * the Mcp-Method and Mcp-Name headers, which must agree with the body. The
 * earlier ones (2024-11-05 to 2025-11-25) open with initialize; no session id
 * is handed out, which they allow, so each of their requests stands alone as
 * well. Nothing is streamed: every answer is complete when it is sent.
 *
 * Who is calling is settled the way integration.php settles it - an OAuth
 * token issued for this endpoint, a device token, or an application's key -
 * and every tool is held to that caller's scopes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_MCP_ENTRY')) {
	exit;
}

// The protocol versions answered, newest first. The first is the current
// generation; the rest open with initialize. 2024-11-05 is still what some
// clients built on an older SDK ask for, and the messages this server
// exchanges - tools/list and tools/call - read the same in it: a client
// answered with a version it does not know gives up instead.
function pg_mcp_versions() {

	return array('2026-07-28', '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05');

}

function pg_mcp_is_modern($version) {

	return ($version === '2026-07-28');

}

// The version an initialize request is answered with: the one the client
// asked for when this server speaks it, the newest that opens with
// initialize otherwise - the client then decides whether it can go on.
function pg_mcp_legacy_version($requested) {

	$legacy = array_slice(pg_mcp_versions(), 1);

	return in_array($requested, $legacy, true) ? $requested : $legacy[0];

}

// Where a request's version comes from. The header wins; without it the
// current generation's _meta key is read; without either, the transport says
// a server may take the request for 2025-03-26.
function pg_mcp_request_version($header, $params) {

	if ($header !== '') {

		return $header;

	}

	$meta = (isset($params['_meta']) && is_array($params['_meta'])) ? $params['_meta'] : array();

	if (isset($meta['io.modelcontextprotocol/protocolVersion']) && is_string($meta['io.modelcontextprotocol/protocolVersion'])) {

		return $meta['io.modelcontextprotocol/protocolVersion'];

	}

	return '2025-03-26';

}

// The current generation repeats the method, the tool's name and the version
// in headers so that a gateway can route on them without reading the body;
// the server refuses a request whose headers and body disagree, or the
// gateway's decision would rest on something the server never sees. Answers
// '' when they agree, the reason otherwise.
function pg_mcp_header_mismatch($method, $params, $headers) {

	if ($headers['method'] !== $method) {

		return 'The Mcp-Method header does not match the method in the body.';

	}

	if (in_array($method, array('tools/call', 'prompts/get'), true)) {

		$name = (isset($params['name']) && is_string($params['name'])) ? $params['name'] : '';

		if ($headers['name'] !== $name) {

			return 'The Mcp-Name header does not match the name in the body.';

		}

	}

	$meta = (isset($params['_meta']) && is_array($params['_meta'])) ? $params['_meta'] : array();

	if (isset($meta['io.modelcontextprotocol/protocolVersion'])
		&& ($meta['io.modelcontextprotocol/protocolVersion'] !== $headers['version'])) {

		return 'The MCP-Protocol-Version header does not match _meta.';

	}

	return '';

}

/* ---------------------------------------------------------------------------
   Answers
   --------------------------------------------------------------------------- */

// The headers every answer carries, the refusals the API sends on its own
// included: a browser-based client has to be able to read a 401 and its
// WWW-Authenticate challenge to find out where to sign in. Allowing every
// origin gives nothing away here - the endpoint reads no cookie and holds no
// session, so a page can only ever act with a token it already has.
function pg_mcp_cors() {

	api_extra_headers('Access-Control-Allow-Origin', '*');

	api_extra_headers('Access-Control-Allow-Methods', 'POST, OPTIONS');

	api_extra_headers('Access-Control-Allow-Headers',
		'Authorization, Content-Type, Accept, MCP-Protocol-Version, Mcp-Method, Mcp-Name, Mcp-Session-Id, Last-Event-ID');

	api_extra_headers('Access-Control-Expose-Headers', 'WWW-Authenticate, Mcp-Session-Id');

	api_extra_headers('Access-Control-Max-Age', '86400');

}

// An Origin header as a browser writes one: scheme and host, perhaps a port,
// nothing else - or "null" for a page with no origin of its own.
function pg_mcp_origin_is_valid($origin) {

	return ($origin === 'null') || (preg_match('#^https?://[A-Za-z0-9.\-\[\]:]+$#', $origin) === 1);

}

function pg_mcp_send($http_status, $payload) {

	if (ob_get_level() > 0) {

		ob_end_clean();

	}

	if (!headers_sent()) {

		http_response_code($http_status);

		header('Cache-Control: no-store');

		header('X-Content-Type-Options: nosniff');

		header('X-Request-Id: ' . api_request_id());

		foreach (api_extra_headers() as $name => $value) {

			header($name . ': ' . $value);

		}

		if ($payload !== null) {

			header('Content-Type: application/json; charset=utf-8');

		}

	}

	api_log_request($http_status);

	if ($payload !== null) {

		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	}

	exit();

}

// A result. The current generation marks every result complete and names the
// server in _meta, where the handshake used to.
function pg_mcp_result($id, $result, $modern) {

	if ($modern) {

		$result['resultType'] = 'complete';

		$meta = (isset($result['_meta']) && is_array($result['_meta'])) ? $result['_meta'] : array();

		$meta['io.modelcontextprotocol/serverInfo'] = pg_mcp_server_info();

		$result['_meta'] = $meta;

	}

	pg_mcp_send(200, array('jsonrpc' => '2.0', 'id' => $id, 'result' => (object)$result));

}

function pg_mcp_error($id, $code, $message, $data = null, $http_status = 400) {

	$error = array('code' => (int)$code, 'message' => (string)$message);

	if ($data !== null) {

		$error['data'] = $data;

	}

	pg_mcp_send($http_status, array('jsonrpc' => '2.0', 'id' => $id, 'error' => $error));

}

/* ---------------------------------------------------------------------------
   What the server says about itself
   --------------------------------------------------------------------------- */

function pg_mcp_site_name() {

	return (defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== '')) ? ORGANIZATION_NAME : pg_oauth_host();

}

function pg_mcp_server_info() {

	return array(
		'name'    => 'pinegrap',
		'title'   => pg_mcp_site_name(),
		'version' => defined('VERSION') ? (string)VERSION : ''
	);

}

function pg_mcp_capabilities() {

	return array('tools' => array('listChanged' => false));

}

// Read by the application's model before it uses the tools: the conventions
// every endpoint shares, which no single tool description repeats.
function pg_mcp_instructions() {

	return 'Tools for the website ' . pg_mcp_site_name() . ' (' . pg_oauth_host() . '), run with the permissions of the person who connected. '
		. 'Each tool is an endpoint of the site\'s API. Money is in minor units (1999 is 19.99). '
		. 'Lists are paged: pass page.next_cursor back as cursor while page.has_more is true. '
		. 'Writes that accept dry_run can be checked first with dry_run: true, which writes nothing. '
		. 'A failed call returns the API\'s error object with a stable code and a message.';

}

/* ---------------------------------------------------------------------------
   The request
   --------------------------------------------------------------------------- */

function pg_mcp_run() {

	pg_mcp_cors();

	api_request_path('/mcp');

	$http_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';

	api_request_method($http_method);

	if ($http_method === 'OPTIONS') {

		pg_mcp_send(204, null);

	}

	// Nothing is streamed and no session is kept, so there is no stream to
	// open with GET and no session to end with DELETE (Streamable HTTP 2.2).
	// A GET or HEAD is still asked for credentials first: some clients probe
	// the address that way, before they send anything, to find out whether
	// and where to sign in, and the 401's challenge is what tells them.
	if ($http_method !== 'POST') {

		if (($http_method === 'GET') || ($http_method === 'HEAD')) {

			pg_mcp_gate();

			api_authenticate(array());

		}

		api_extra_headers('Allow', 'POST, OPTIONS');

		pg_mcp_send(405, null);

	}

	// Streamable HTTP asks a server to refuse an invalid Origin, against DNS
	// rebinding: a page on another site reaching a server on the visitor's own
	// machine. This server is reached over the internet and grants nothing
	// without a token the page would have to hold already (pg_mcp_cors()), so
	// any real origin is let through and only a malformed one is refused.
	if (isset($_SERVER['HTTP_ORIGIN']) && !pg_mcp_origin_is_valid((string)$_SERVER['HTTP_ORIGIN'])) {

		pg_mcp_send(403, null);

	}

	pg_mcp_gate();

	$message = json_decode((string)file_get_contents('php://input'), true);

	if (!is_array($message)) {

		pg_mcp_error(null, -32700, 'Parse error: the body is not a JSON object.');

	}

	// A batch was allowed by 2025-03-26 only and is answered by no one now.
	if (isset($message[0])) {

		pg_mcp_error(null, -32600, 'Batches are not supported. Send one message per request.');

	}

	$has_id = array_key_exists('id', $message);

	$id = $has_id ? $message['id'] : null;

	if (($id !== null) && !is_string($id) && !is_int($id)) {

		pg_mcp_error(null, -32600, 'Invalid request: the id must be a string or a number.');

	}

	$method = (isset($message['method']) && is_string($message['method'])) ? $message['method'] : '';

	if ((!isset($message['jsonrpc'])) || ($message['jsonrpc'] !== '2.0')) {

		pg_mcp_error($id, -32600, 'Invalid request: jsonrpc must be "2.0".');

	}

	// A response sent to the server (the answer to something it asked) is
	// accepted and dropped: this server never asks the client anything.
	if ($method === '') {

		if (isset($message['result']) || isset($message['error'])) {

			pg_mcp_send(202, null);

		}

		pg_mcp_error($id, -32600, 'Invalid request: no method.');

	}

	$params = (isset($message['params']) && is_array($message['params'])) ? $message['params'] : array();

	$version = ($method === 'initialize') ? '' : pg_mcp_request_version(api_header('MCP-Protocol-Version'), $params);

	if (($version !== '') && !in_array($version, pg_mcp_versions(), true)) {

		pg_mcp_error($id, -32022, 'Unsupported protocol version.', array(
			'supported' => pg_mcp_versions(),
			'requested' => mb_substr($version, 0, 40)
		));

	}

	$modern = pg_mcp_is_modern($version);

	if ($modern) {

		$mismatch = pg_mcp_header_mismatch($method, $params, array(
			'method'  => api_header('Mcp-Method'),
			'name'    => api_header('Mcp-Name'),
			'version' => api_header('MCP-Protocol-Version')
		));

		if ($mismatch !== '') {

			pg_mcp_error($id, -32020, $mismatch);

		}

	}

	// A notification asks for no answer; the server has nothing to do with
	// any of them - there is no session to start and no request it could
	// cancel, each one having already been answered.
	if (!$has_id) {

		pg_mcp_send(202, null);

	}

	// Every request, the handshake included, is made by somebody. A client
	// refused here receives the API's 401 with the challenge that tells it
	// where to sign in (RFC 9728 5.1).
	$app = api_authenticate(array());

	api_rate_limit_check($app);

	switch ($method) {

		case 'initialize':

			$requested = (isset($params['protocolVersion']) && is_string($params['protocolVersion'])) ? $params['protocolVersion'] : '';

			pg_mcp_result($id, array(
				'protocolVersion' => pg_mcp_legacy_version($requested),
				'capabilities'    => pg_mcp_capabilities(),
				'serverInfo'      => pg_mcp_server_info(),
				'instructions'    => pg_mcp_instructions()
			), false);

		case 'ping':

			pg_mcp_result($id, array(), $modern);

		case 'server/discover':

			pg_mcp_result($id, array(
				'supportedVersions' => pg_mcp_versions(),
				'capabilities'      => pg_mcp_capabilities(),
				'instructions'      => pg_mcp_instructions(),
				'ttlMs'             => 300000,
				'cacheScope'        => 'private'
			), $modern);

		case 'tools/list':

			$result = array('tools' => pg_mcp_tools_list());

			// The list depends on who asks, so it is cached by that client
			// alone, and for a few minutes: a permission granted or taken
			// away shows within them.
			if ($modern) {

				$result['ttlMs'] = 300000;

				$result['cacheScope'] = 'private';

			}

			pg_mcp_result($id, $result, $modern);

		case 'tools/call':

			pg_mcp_tool_call($id, $params, $modern);

		// Asked by some clients whatever the capabilities say. The server
		// offers no resources and no prompts, and an empty list says so
		// without the client reporting a failed server.
		case 'resources/list':

			pg_mcp_result($id, array('resources' => array()), $modern);

		case 'resources/templates/list':

			pg_mcp_result($id, array('resourceTemplates' => array()), $modern);

		case 'prompts/list':

			pg_mcp_result($id, array('prompts' => array()), $modern);

	}

	pg_mcp_error($id, -32601, 'Method not found: ' . mb_substr($method, 0, 100), null, $modern ? 404 : 200);

}

// The gates integration.php passes before it reads a credential, in the same
// order and with the same answers.
function pg_mcp_gate() {

	if (!defined('DB_CONNECTED')) {

		api_fail(503, 'service_unavailable', lang('No database connection.'));

	}

	if (@mysqli_query(db::$con, "SELECT 1 FROM api_apps LIMIT 1") === false) {

		api_fail(503, 'not_installed', lang('The API is not installed on this site yet. Run the software upgrade to finish it.'));

	}

	$settings = api_settings();

	if (!$settings['enabled']) {

		api_fail(503, 'api_disabled', lang('The API is switched off for this site.'));

	}

	if ($settings['require_https'] && function_exists('pg_request_is_https') && !pg_request_is_https()) {

		api_fail(403, 'https_required', lang('This API requires a secure (https) connection.'));

	}

	if (function_exists('waf_ip_is_blocked')
		&& function_exists('waf_ip_is_allowed')
		&& !waf_ip_is_allowed(api_client_ip())
		&& waf_ip_is_blocked(api_client_ip())) {

		api_fail(403, 'address_blocked', lang('We are currently unable to fulfill your request.'));

	}

}
