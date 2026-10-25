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
 * and every tool, resource and prompt is held to that caller's scopes.
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
// exchanges - the tools, resources and prompts lists, tools/call,
// resources/read and prompts/get - read the same in it, apart from fields it
// ignores: a client answered with a version it does not know gives up
// instead.
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

// The current generation repeats the method, the name of the tool or prompt
// (the uri of a resource read) and the version in headers so that a gateway
// can route on them without reading the body; the server refuses a request
// whose headers and body disagree, or the gateway's decision would rest on
// something the server never sees. Answers '' when they agree, the reason
// otherwise.
function pg_mcp_header_mismatch($method, $params, $headers) {

	if ($headers['method'] !== $method) {

		return 'The Mcp-Method header does not match the method in the body.';

	}

	$named = array('tools/call' => 'name', 'prompts/get' => 'name', 'resources/read' => 'uri');

	if (isset($named[$method])) {

		$key = $named[$method];

		$name = (isset($params[$key]) && is_string($params[$key])) ? $params[$key] : '';

		if (pg_mcp_header_name_decode($headers['name']) !== $name) {

			return 'The Mcp-Name header does not match the ' . $key . ' in the body.';

		}

	}

	$meta = (isset($params['_meta']) && is_array($params['_meta'])) ? $params['_meta'] : array();

	if (isset($meta['io.modelcontextprotocol/protocolVersion'])
		&& ($meta['io.modelcontextprotocol/protocolVersion'] !== $headers['version'])) {

		return 'The MCP-Protocol-Version header does not match _meta.';

	}

	return '';

}

// An Mcp-Name value as the body would hold it. A name or uri that is not
// plain ASCII travels as =?base64?...?= (the markers lowercase), and is
// compared decoded; a value in that shape that is not Base64 is null and
// matches nothing.
function pg_mcp_header_name_decode($value) {

	$value = (string)$value;

	if (preg_match('#^=\?base64\?(.*)\?=\z#s', $value, $found) !== 1) {

		return $value;

	}

	$decoded = (preg_match('#^[A-Za-z0-9+/]*={0,2}\z#', $found[1]) === 1) ? base64_decode($found[1], true) : false;

	return ($decoded === false) ? null : $decoded;

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

		pg_send_nosniff();

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

		echo pg_mcp_payload_json($payload);

	}

	exit();

}

// A value as the JSON an MCP answer carries, by the rules of api_json_encode()
// (includes/api/response.php, which loads only behind PG_API_ENTRY): a byte
// that is not UTF-8 becomes U+FFFD and the rest of the value stays; PHP 7.1,
// which cannot substitute, writes the broken string alone as null. null when
// the value cannot be JSON at all (INF, NAN), for the caller to answer an
// error: a bare json_encode() returns false there, which casts to an empty
// text a client cannot tell from a real, empty answer.
function pg_mcp_json_encode($value) {

	$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {

		$flags |= JSON_INVALID_UTF8_SUBSTITUTE; // phpcs:ignore PHPCompatibility.Constants.NewConstants.json_invalid_utf8_substituteFound -- guarded by defined() above

	}

	$json = json_encode($value, $flags);

	if (($json === false) && (json_last_error() === JSON_ERROR_UTF8)) {

		$json = json_encode($value, $flags | JSON_PARTIAL_OUTPUT_ON_ERROR);

	}

	return is_string($json) ? $json : null;

}

// The body pg_mcp_send() writes, never empty: a payload JSON cannot hold
// becomes an internal error under the request's id, or under null when the
// id is what cannot be written (a client's 1e999 decodes as INF).
function pg_mcp_payload_json($payload) {

	$json = pg_mcp_json_encode($payload);

	if ($json !== null) {

		return $json;

	}

	$id = (is_array($payload) && isset($payload['id']) && (pg_mcp_json_encode($payload['id']) !== null)) ? $payload['id'] : null;

	return (string)pg_mcp_json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'error' => array('code' => -32603, 'message' => 'Internal error')));

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

// The caching hints the current generation asks for on lists and reads: how
// long the answer may be used again, and that it is this caller's own - what
// is listed and read follows the caller's scopes and rights. Earlier
// generations have none.
function pg_mcp_cache_hints($result, $ttl_ms, $modern) {

	if ($modern) {

		$result['ttlMs'] = (int)$ttl_ms;

		$result['cacheScope'] = 'private';

	}

	return $result;

}

function pg_mcp_error($id, $code, $message, $data = null, $http_status = 400) {

	$error = array('code' => (int)$code, 'message' => (string)$message);

	if ($data !== null) {

		$error['data'] = $data;

	}

	pg_mcp_send($http_status, array('jsonrpc' => '2.0', 'id' => $id, 'error' => $error));

}

// Writes an answer built without side effects (pg_mcp_list_answer(),
// pg_mcp_resource_target(), pg_mcp_resource_answer(), pg_mcp_tool_answer()):
// array('result' => ...) or array('error' => array('code', 'message', 'data'
// when there is one)). Such an error is about the request it answers, so it
// travels with HTTP 200 in both generations, as the tools' do.
function pg_mcp_answer($id, $answer, $modern) {

	if (isset($answer['error'])) {

		pg_mcp_error($id, $answer['error']['code'], $answer['error']['message'], isset($answer['error']['data']) ? $answer['error']['data'] : null, 200);

	}

	pg_mcp_result($id, $answer['result'], $modern);

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

// Tools, resources and prompts; none of the lists is announced as changed
// (no notifications are sent) and no resource can be subscribed to.
function pg_mcp_capabilities() {

	return array(
		'tools'     => array('listChanged' => false),
		'resources' => array('listChanged' => false),
		'prompts'   => array('listChanged' => false)
	);

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

// The answer to tools/list, resources/list, resources/templates/list or
// prompts/list for a caller whose tools are $routes (pg_mcp_routes()), or
// null for any other method. The lists depend on who asks, so each is cached
// by that client alone, and for a few minutes: a permission granted or taken
// away shows within them. None is paged: each is complete in one answer and
// no nextCursor is ever handed out, so a cursor the client sends is one this
// server never gave, and invalid params (the pagination utility of every
// generation). A null or empty cursor is no cursor.
function pg_mcp_list_answer($method, $params, $routes, $modern) {

	$lists = array(
		'tools/list'               => array('tools', 'pg_mcp_tools_list'),
		'resources/list'           => array('resources', 'pg_mcp_resources_list'),
		'resources/templates/list' => array('resourceTemplates', 'pg_mcp_resource_templates_list'),
		'prompts/list'             => array('prompts', 'pg_mcp_prompts_list')
	);

	if (!isset($lists[$method])) {

		return null;

	}

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		return array('error' => array('code' => -32602, 'message' => 'Invalid params: unknown cursor.'));

	}

	list($key, $builder) = $lists[$method];

	return array('result' => pg_mcp_cache_hints(array($key => call_user_func($builder, $routes)), 300000, $modern));

}

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
		case 'resources/list':
		case 'resources/templates/list':
		case 'prompts/list':

			pg_mcp_answer($id, pg_mcp_list_answer($method, $params, pg_mcp_routes(), $modern), $modern);

		case 'tools/call':

			pg_mcp_tool_call($id, $params, $modern);

		case 'resources/read':

			pg_mcp_resource_read($id, $params, $modern);

		case 'prompts/get':

			pg_mcp_prompt_get($id, $params, $modern);

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
