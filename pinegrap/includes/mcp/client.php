<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site as an MCP client: using the tools of another MCP server over
 * Streamable HTTP. The other half of includes/mcp/protocol.php, which is the
 * site as a server.
 *
 * A server is reached at one https address and every message is one POST.
 * Two generations of the protocol are spoken, as on the server side. The
 * current one (2026-07-28) has no session and no handshake: the client asks
 * server/discover once and then sends each request on its own, with its
 * version, method and tool name repeated in headers. An earlier server
 * (2025-03-26 to 2025-11-25) is opened with initialize, may hand out a session
 * id that every later request has to carry, and may answer a POST with an
 * event stream (text/event-stream) instead of a single JSON object; the answer
 * is then read out of the stream. A session the server has forgotten (404) is
 * opened again, once.
 *
 * Nothing here keeps state: the caller passes a connection in and gets it
 * back with what was learnt on the way (the version, a session id) and keeps
 * that where it likes - ws_mcp_servers, for the Workspace.
 *
 * Every request goes through api_http_request(): https only, the host
 * resolved and checked against private networks, curl pinned to the address
 * that was checked, no redirect followed. The server is somebody else's: what
 * it answers - tool descriptions, results - is data to show or to hand a
 * model as data, never an instruction.
 *
 * A connection is an array:
 *
 *   url       the server's address
 *   headers   the credential headers ("Authorization: Bearer ..."); never
 *             written anywhere by this file
 *   protocol  the version agreed on, '' until it is known
 *   session   the session id of an earlier-generation server, '' for none
 *   timeout   seconds one request may take
 *   extensions the extensions of the current generation the caller follows
 *             (PG_MCP_CLIENT_TASKS); declared with every request, so a
 *             server may answer in their shapes. Empty by default: a caller
 *             that cannot follow a task never gets one
 *   transport a callable taking (url, body, headers, timeout, max_body) and
 *             answering like api_http_request() with response_headers; only
 *             the tests set it
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

// The most bytes of one answer that are read. A tool list with long schemas
// (a code host's hundred tools) runs to a few hundred kilobytes.
define('PG_MCP_CLIENT_MAX_BODY', 4194304);

// The most tools taken from one server, and the most pages asked for them.
define('PG_MCP_CLIENT_MAX_TOOLS', 200);

define('PG_MCP_CLIENT_MAX_PAGES', 10);

// The tasks extension of the current generation: a server may answer a
// tools/call with a task handle (resultType "task") instead of the result,
// and the client asks tasks/get until the task ends. tasks/get carries the
// result itself; tasks/cancel asks the server to stop.
define('PG_MCP_CLIENT_TASKS', 'io.modelcontextprotocol/tasks');

// The versions spoken, newest first. The first has no session; the rest open
// with initialize, the second being the one asked for.
function pg_mcp_client_versions() {

	return array('2026-07-28', '2025-11-25', '2025-06-18', '2025-03-26');

}

function pg_mcp_client_is_modern($version) {

	return ($version === '2026-07-28');

}

// How the site names itself to a server.
function pg_mcp_client_info() {

	$title = (defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== '')) ? (string)ORGANIZATION_NAME : (defined('HOSTNAME_SETTING') ? (string)HOSTNAME_SETTING : 'Pinegrap');

	return array(
		'name'    => 'pinegrap',
		'title'   => $title,
		'version' => defined('VERSION') ? (string)VERSION : ''
	);

}

// A fresh connection to an address.
function pg_mcp_client_connection($url, $headers = array(), $protocol = '', $session = '', $timeout = 20) {

	return array(
		'url'        => (string)$url,
		'headers'    => array_values((array)$headers),
		'protocol'   => (string)$protocol,
		'session'    => (string)$session,
		'timeout'    => max(3, (int)$timeout),
		'extensions' => array(),
		'transport'  => null
	);

}

// Whether an address can be a server's: https, a host, no user name or
// password in it and no fragment. Whether the host is outside this network is
// asked when it is called (api_http_check_url()), since a name can change
// what it points at.
function pg_mcp_client_url_is_valid($url) {

	$url = trim((string)$url);

	if (($url === '') || (strlen($url) > 500) || preg_match('/[\s<>"\\\\]/', $url)) {

		return false;

	}

	$parts = @parse_url($url);

	if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || (strtolower($parts['scheme']) !== 'https')) {

		return false;

	}

	return !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']) && ($parts['host'] !== '');

}

// One JSON-RPC message. A notification (no id) asks for no answer. The
// current generation also carries in _meta its version, who the client is
// and what it can do: the capabilities are declared with every request, an
// empty object when it follows no extension.
function pg_mcp_client_message($id, $method, $params, $version, $extensions = array()) {

	$params = is_array($params) ? $params : array();

	if (pg_mcp_client_is_modern($version)) {

		$meta = (isset($params['_meta']) && is_array($params['_meta'])) ? $params['_meta'] : array();

		$meta['io.modelcontextprotocol/protocolVersion'] = $version;

		$meta['io.modelcontextprotocol/clientInfo'] = pg_mcp_client_info();

		$followed = array();

		foreach ((array)$extensions as $extension) {

			$followed[(string)$extension] = new stdClass();

		}

		$meta['io.modelcontextprotocol/clientCapabilities'] = empty($followed) ? new stdClass() : array('extensions' => $followed);

		$params['_meta'] = $meta;

	}

	$message = array('jsonrpc' => '2.0');

	if ($id !== null) {

		$message['id'] = $id;

	}

	$message['method'] = (string)$method;

	if (!empty($params)) {

		$message['params'] = $params;

	}

	return $message;

}

// The headers of one request: the body's type, the two kinds of answer this
// client reads, the version and session once they are known, the method and
// tool name of the current generation, and the credentials.
function pg_mcp_client_headers($conn, $method, $params, $version) {

	$headers = array(
		'Content-Type: application/json',
		'Accept: application/json, text/event-stream'
	);

	if ($version !== '') {

		$headers[] = 'MCP-Protocol-Version: ' . $version;

	}

	if (pg_mcp_client_is_modern($version)) {

		$headers[] = 'Mcp-Method: ' . $method;

		if (($method === 'tools/call') && isset($params['name']) && is_string($params['name'])) {

			$headers[] = 'Mcp-Name: ' . $params['name'];

		}

	} elseif ((string)$conn['session'] !== '') {

		$headers[] = 'Mcp-Session-Id: ' . $conn['session'];

	}

	foreach ((array)$conn['headers'] as $header) {

		$header = str_replace(array("\r", "\n"), '', (string)$header);

		if (strpos($header, ':') !== false) {

			$headers[] = $header;

		}

	}

	return $headers;

}

// Sends one message and answers what came back: status, headers (by lower-
// case name), body, error, errno (curl's error number, 0 for none).
function pg_mcp_client_post($conn, $message, $method, $version) {

	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);

	$body = json_encode($message, $flags);

	if ($body === false) {

		return array('status' => 0, 'headers' => array(), 'body' => '', 'error' => 'The request could not be written as JSON.', 'errno' => 0);

	}

	$params = isset($message['params']) ? $message['params'] : array();

	$headers = pg_mcp_client_headers($conn, $method, $params, $version);

	if (isset($conn['transport']) && is_callable($conn['transport'])) {

		$response = call_user_func($conn['transport'], $conn['url'], $body, $headers, $conn['timeout'], PG_MCP_CLIENT_MAX_BODY);

	} else {

		if (!function_exists('api_http_request')) {

			require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

		}

		$response = api_http_request('POST', $conn['url'], array(
			'body'             => $body,
			'headers'          => $headers,
			'timeout'          => $conn['timeout'],
			'max_body'         => PG_MCP_CLIENT_MAX_BODY,
			'agent'            => 'mcp',
			'response_headers' => true
		));

	}

	return array(
		'status'  => isset($response['status']) ? (int)$response['status'] : 0,
		'headers' => (isset($response['headers']) && is_array($response['headers'])) ? $response['headers'] : array(),
		'body'    => isset($response['body']) ? (string)$response['body'] : '',
		'error'   => isset($response['error']) ? (string)$response['error'] : '',
		'errno'   => isset($response['errno']) ? (int)$response['errno'] : 0
	);

}

// The messages in an event stream: the data lines of each event, joined,
// decoded. Events that are not JSON (a comment, a keep-alive) are left out.
// Each message comes as array(decoded, raw).
function pg_mcp_client_sse_messages($body) {

	$out = array();

	$events = preg_split('/\r?\n\r?\n|\r\r/', str_replace("\r\n", "\n", (string)$body));

	foreach ($events as $event) {

		$data = array();

		foreach (preg_split('/\r?\n|\r/', $event) as $line) {

			if (strpos($line, 'data:') === 0) {

				$value = substr($line, 5);

				$data[] = (substr($value, 0, 1) === ' ') ? substr($value, 1) : $value;

			}

		}

		if (empty($data)) {

			continue;

		}

		$raw = implode("\n", $data);

		$decoded = json_decode($raw, true);

		if (is_array($decoded)) {

			$out[] = array($decoded, $raw);

		}

	}

	return $out;

}

// The answer to request $id in what a server sent: a JSON body or an event
// stream. Answers array('message' => the JSON-RPC response or null, 'raw' =>
// its JSON text).
function pg_mcp_client_read($response, $id) {

	$type = isset($response['headers']['content-type']) ? strtolower((string)$response['headers']['content-type']) : '';

	$body = ltrim((string)$response['body']);

	$candidates = array();

	if ((strpos($type, 'text/event-stream') === 0) || preg_match('/^(event|data|id|retry):/', $body) || (substr($body, 0, 1) === ':')) {

		$candidates = pg_mcp_client_sse_messages($body);

	} elseif ($body !== '') {

		$decoded = json_decode($body, true);

		if (is_array($decoded)) {

			$candidates[] = array($decoded, $body);

		}

	}

	foreach ($candidates as $candidate) {

		list($message, $raw) = $candidate;

		if (array_key_exists('id', $message) && ($message['id'] === $id) && (isset($message['result']) || isset($message['error']))) {

			return array('message' => $message, 'raw' => $raw);

		}

	}

	// A server that answers an error without the request's id (a parse
	// error, a refusal before the body was read) still says what went wrong.
	// So does one that refuses with a failed status and an id of its own
	// (FastMCP answers 400 with the id "server-error"): with that status the
	// error cannot be another request's answer.
	$failed = isset($response['status']) && ((int)$response['status'] >= 400);

	foreach ($candidates as $candidate) {

		list($message, $raw) = $candidate;

		if (isset($message['error']) && ($failed || !array_key_exists('id', $message) || ($message['id'] === null))) {

			return array('message' => $message, 'raw' => $raw);

		}

	}

	return array('message' => null, 'raw' => '');

}

// A request id of its own for every message of this run.
function pg_mcp_client_next_id() {

	static $id = 0;

	return ++$id;

}

// One request as it is, with no handshake before it. Answers ok, result,
// raw (the response's JSON text), error, code (the JSON-RPC error code, 0
// for none), status (HTTP), headers and oauth (the server refused it with a
// challenge that offers an OAuth sign-in).
function pg_mcp_client_send($conn, $method, $params, $version) {

	$id = pg_mcp_client_next_id();

	$extensions = isset($conn['extensions']) ? (array)$conn['extensions'] : array();

	$response = pg_mcp_client_post($conn, pg_mcp_client_message($id, $method, $params, $version, $extensions), $method, $version);

	$out = array('ok' => false, 'result' => null, 'raw' => '', 'error' => '', 'code' => 0, 'status' => $response['status'], 'headers' => $response['headers'], 'task' => null, 'oauth' => false);

	if ($response['status'] === 0) {

		$out['error'] = pg_mcp_client_transport_text($response['error'], $response['errno']);

		return $out;

	}

	// A server that wants an OAuth sign-in names its resource metadata in the
	// challenge (RFC 9728). The sentence depends on whether a credential went
	// along: none, or one the server refused (GitHub and Linear answer a wrong
	// token with the same challenge). The status and the challenge stay in the
	// answer for pg_mcp_client_discover_auth().
	if ($response['status'] === 401) {

		$challenge = pg_mcp_client_bearer_challenge(isset($response['headers']['www-authenticate']) ? $response['headers']['www-authenticate'] : '');

		if (isset($challenge['resource_metadata']) && ($challenge['resource_metadata'] !== '')) {

			$out['oauth'] = true;

			$out['error'] = empty($conn['headers'])
				? lang('This MCP server asks for a sign-in: under How the site signs in, choose Sign in to the service (OAuth), or Access token (Bearer) if the service gives one.')
				: lang('The MCP server refused the credentials. Check the token or the header, or choose Sign in to the service (OAuth) under How the site signs in.');

			return $out;

		}

	}

	$read = pg_mcp_client_read($response, $id);

	$message = $read['message'];

	if (is_array($message) && isset($message['error']) && is_array($message['error'])) {

		$out['code'] = isset($message['error']['code']) ? (int)$message['error']['code'] : 0;

		$out['error'] = mb_substr(isset($message['error']['message']) ? (string)$message['error']['message'] : lang('The MCP server answered with an error.'), 0, 300);

		return $out;

	}

	if (is_array($message) && isset($message['result']) && is_array($message['result']) && ($response['status'] >= 200) && ($response['status'] < 300)) {

		$result = $message['result'];

		$type = isset($result['resultType']) ? (string)$result['resultType'] : 'complete';

		// Work that goes on after the answer (the tasks extension): a handle
		// to ask about, for a caller that follows tasks. Anything else that
		// is not the result - a task for a caller that did not ask for one,
		// a request for more input - is reported as not done rather than as
		// empty.
		if ($type === 'task') {

			$task = (in_array(PG_MCP_CLIENT_TASKS, $extensions, true) && pg_mcp_client_is_modern($version)) ? pg_mcp_client_task($result) : null;

			if (!$task) {

				$out['error'] = lang('The MCP server answered that the work goes on in the background, which is not supported here yet.');

				return $out;

			}

			$out['ok'] = true;

			$out['result'] = $result;

			$out['raw'] = $read['raw'];

			$out['task'] = $task;

			return $out;

		}

		if ($type !== 'complete') {

			$out['error'] = ($type === 'input_required')
				? lang('The MCP server asked for more input before it answers, which cannot be given from here yet.')
				: lang('The MCP server answered that the work goes on in the background, which is not supported here yet.');

			return $out;

		}

		$out['ok'] = true;

		$out['result'] = $result;

		$out['raw'] = $read['raw'];

		return $out;

	}

	// An address of the old HTTP+SSE transport, where a GET opens the stream
	// and a POST is refused, is not spoken: the Streamable HTTP address the
	// server most likely has is named instead.
	if (in_array($response['status'], array(404, 405, 410), true)) {

		$sse = pg_mcp_client_sse_address($conn['url']);

		if ($sse !== '') {

			$out['error'] = lang(array('string' => 'This is an old SSE address. The address that works here usually ends in /mcp: {var:1}', 'vars' => $sse));

			return $out;

		}

	}

	// A redirect is not followed (api_http_request()): the new address is
	// named, for the administrator to enter, where it is checked like any
	// other.
	if (in_array($response['status'], array(301, 302, 303, 307, 308), true)) {

		$moved = pg_mcp_client_moved_to(isset($response['headers']['location']) ? $response['headers']['location'] : '', $conn['url']);

		if ($moved !== '') {

			$out['error'] = lang(array('string' => 'The MCP server moved to {var:1}. Enter that address instead.', 'vars' => $moved));

			return $out;

		}

	}

	$out['error'] = pg_mcp_client_status_text($response['status']);

	return $out;

}

// Why a request did not reach a server, in a sentence. The refusals of
// api_http_check_url() and the commonest curl errors, by curl's error number
// ($errno), are said in the screen's language; anything else is curl's own
// words.
function pg_mcp_client_transport_text($error, $errno = 0) {

	switch ((int)$errno) {

		case 28:

			return lang('The MCP server took too long to answer.');

		case 7:

			return lang('The MCP server could not be reached.');

		case 6:

			return lang('The address of the MCP server does not resolve.');

	}

	switch ((string)$error) {

		case '':

			return lang('The MCP server could not be reached.');

		case 'The address does not resolve.':

			return lang('The address of the MCP server does not resolve.');

		case 'That address is inside this network and is not called.':

			return lang('The address of the MCP server is inside this network and is not called.');

		case 'Outgoing addresses must be https.':

		case 'Only http and https addresses are called.':

		case 'The address is not a valid URL.':

			return lang('The address must be an https address, without a user name, a password or a # part.');

	}

	return mb_substr((string)$error, 0, 250);

}

// What an HTTP status means for a request to a server, in a sentence.
function pg_mcp_client_status_text($status) {

	$status = (int)$status;

	if (($status === 401) || ($status === 403)) {

		return lang('The MCP server refused the credentials. Check the token or the header.');

	}

	if ($status === 404) {

		return lang('There is no MCP server at that address.');

	}

	if ($status === 429) {

		return lang('The MCP server asks to slow down. Try again a little later.');

	}

	if ($status >= 500) {

		return lang(array('string' => 'The MCP server had an error ({var:1}).', 'vars' => $status));

	}

	return lang(array('string' => 'The MCP server gave an answer that could not be read (HTTP {var:1}).', 'vars' => $status));

}

// An address as a refusal names it: the origin and the path. A query may
// carry a key (some services take one there) and a refusal can be posted in
// a channel, so a query is shown as "?…". '' for no web address.
function pg_mcp_client_shown_address($url) {

	$origin = pg_mcp_client_origin($url);

	$parts = @parse_url(trim((string)$url));

	if (($origin === '') || !is_array($parts)) {

		return '';

	}

	$path = isset($parts['path']) ? (string)$parts['path'] : '';

	return mb_substr($origin . $path . ((isset($parts['query']) && ($parts['query'] !== '')) ? '?…' : ''), 0, 200);

}

// Where a redirect points, as a refusal names it: a relative Location read
// against the address asked, plain http on the same host as https (only
// https is called). '' when it names no web address, or the address asked.
function pg_mcp_client_moved_to($location, $url) {

	$location = trim((string)$location);

	$origin = pg_mcp_client_origin($url);

	if (($origin === '') || ($location === '') || (strlen($location) > 2000) || preg_match('/[\x00-\x20\x7F<>"\\\\]/', $location)) {

		return '';

	}

	$path = (string)parse_url(trim((string)$url), PHP_URL_PATH);

	if (substr($location, 0, 2) === '//') {

		$target = strtolower((string)parse_url(trim((string)$url), PHP_URL_SCHEME)) . ':' . $location;

	} elseif (preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*:/', $location)) {

		$target = $location;

	} elseif (substr($location, 0, 1) === '/') {

		$target = $origin . $location;

	} elseif (substr($location, 0, 1) === '?') {

		$target = $origin . $path . $location;

	} else {

		$slash = strrpos($path, '/');

		$target = $origin . (($slash === false) ? '/' : substr($path, 0, $slash + 1)) . $location;

	}

	$scheme = strtolower((string)parse_url($target, PHP_URL_SCHEME));

	$host = strtolower((string)parse_url($target, PHP_URL_HOST));

	if ((($scheme !== 'https') && ($scheme !== 'http')) || ($host === '')) {

		return '';

	}

	if (($scheme === 'http') && ($host === strtolower((string)parse_url(trim((string)$url), PHP_URL_HOST)))) {

		$target = 'https' . substr($target, 4);

	}

	// The origin, path and query, to tell the new address from the old one.
	$same = function ($address) {

		$parts = @parse_url(trim((string)$address));

		return pg_mcp_client_origin($address) . (isset($parts['path']) ? $parts['path'] : '') . (isset($parts['query']) ? '?' . $parts['query'] : '');

	};

	return ($same($target) === $same($url)) ? '' : pg_mcp_client_shown_address($target);

}

// The address an old HTTP+SSE address (its path ending in /sse) most likely
// has on Streamable HTTP, as a refusal names it: that /sse made /mcp. '' for
// any other path.
function pg_mcp_client_sse_address($url) {

	$parts = @parse_url(trim((string)$url));

	$path = (is_array($parts) && isset($parts['path'])) ? (string)$parts['path'] : '';

	if (!preg_match('#/sse/?$#', $path)) {

		return '';

	}

	$query = (isset($parts['query']) && ($parts['query'] !== '')) ? '?' . $parts['query'] : '';

	return pg_mcp_client_shown_address(pg_mcp_client_origin($url) . preg_replace('#/sse/?$#', '/mcp', $path) . $query);

}

// A session id as the transport allows it: visible ASCII only.
function pg_mcp_client_session_is_valid($session) {

	return is_string($session) && ($session !== '') && (strlen($session) <= 255) && (bool)preg_match('/^[\x21-\x7E]+$/', $session);

}

// Finds out how a server is spoken to: the current generation first, then
// initialize. Answers ok, error, status, oauth_challenge (the server asked
// for an OAuth sign-in), instructions and server (its name and version, as it
// gives them); the connection learns the version and, from an
// earlier-generation server, the session.
function pg_mcp_client_negotiate(&$conn) {

	$out = array('ok' => false, 'error' => '', 'status' => 0, 'oauth_challenge' => false, 'instructions' => '', 'server' => array());

	$conn['protocol'] = '';

	$conn['session'] = '';

	$discover = pg_mcp_client_send($conn, 'server/discover', array(), '2026-07-28');

	$out['status'] = $discover['status'];

	// A refused credential is refused whatever the version: asking again with
	// initialize would only hide the reason.
	if (($discover['status'] === 401) || ($discover['status'] === 403)) {

		$out['error'] = $discover['error'];

		$out['oauth_challenge'] = !empty($discover['oauth']);

		return $out;

	}

	if ($discover['status'] === 0) {

		$out['error'] = $discover['error'];

		return $out;

	}

	if ($discover['ok']) {

		$supported = (isset($discover['result']['supportedVersions']) && is_array($discover['result']['supportedVersions'])) ? $discover['result']['supportedVersions'] : array('2026-07-28');

		if (in_array('2026-07-28', $supported, true)) {

			$conn['protocol'] = '2026-07-28';

			$meta = (isset($discover['result']['_meta']) && is_array($discover['result']['_meta'])) ? $discover['result']['_meta'] : array();

			$out['ok'] = true;

			$out['instructions'] = isset($discover['result']['instructions']) ? (string)$discover['result']['instructions'] : '';

			$out['server'] = (isset($meta['io.modelcontextprotocol/serverInfo']) && is_array($meta['io.modelcontextprotocol/serverInfo'])) ? $meta['io.modelcontextprotocol/serverInfo'] : array();

			return $out;

		}

	}

	$init = pg_mcp_client_send($conn, 'initialize', array(
		'protocolVersion' => '2025-11-25',
		'capabilities'    => (object)array(),
		'clientInfo'      => pg_mcp_client_info()
	), '');

	$out['status'] = $init['status'];

	if (!$init['ok']) {

		$out['error'] = $init['error'];

		$out['oauth_challenge'] = !empty($init['oauth']);

		return $out;

	}

	$version = isset($init['result']['protocolVersion']) ? (string)$init['result']['protocolVersion'] : '';

	if (!in_array($version, array_slice(pg_mcp_client_versions(), 1), true)) {

		$out['error'] = lang(array('string' => 'The MCP server speaks a version of the protocol this site does not: {var:1}.', 'vars' => mb_substr(($version !== '') ? $version : '?', 0, 40)));

		return $out;

	}

	$conn['protocol'] = $version;

	$session = isset($init['headers']['mcp-session-id']) ? (string)$init['headers']['mcp-session-id'] : '';

	$conn['session'] = pg_mcp_client_session_is_valid($session) ? $session : '';

	// The handshake ends with a notification; its answer (202) says nothing.
	$id = null;

	pg_mcp_client_post($conn, pg_mcp_client_message($id, 'notifications/initialized', array(), $version), 'notifications/initialized', $version);

	$out['ok'] = true;

	$out['instructions'] = isset($init['result']['instructions']) ? (string)$init['result']['instructions'] : '';

	$out['server'] = (isset($init['result']['serverInfo']) && is_array($init['result']['serverInfo'])) ? $init['result']['serverInfo'] : array();

	return $out;

}

// A request, with the handshake before it when the version is not known yet
// and once more when the server forgot the session or no longer speaks the
// version agreed on. Answers like pg_mcp_client_send().
function pg_mcp_client_request(&$conn, $method, $params) {

	if ($conn['protocol'] === '') {

		$open = pg_mcp_client_negotiate($conn);

		if (!$open['ok']) {

			return array('ok' => false, 'result' => null, 'raw' => '', 'error' => $open['error'], 'code' => 0, 'status' => $open['status'], 'headers' => array());

		}

	}

	$answer = pg_mcp_client_send($conn, $method, $params, $conn['protocol']);

	$forgotten = !$answer['ok'] && !pg_mcp_client_is_modern($conn['protocol']) && ($conn['session'] !== '') && ($answer['status'] === 404);

	$outdated = !$answer['ok'] && ($answer['code'] === -32022);

	if ($forgotten || $outdated) {

		$open = pg_mcp_client_negotiate($conn);

		if (!$open['ok']) {

			return array('ok' => false, 'result' => null, 'raw' => '', 'error' => $open['error'], 'code' => 0, 'status' => $open['status'], 'headers' => array());

		}

		$answer = pg_mcp_client_send($conn, $method, $params, $conn['protocol']);

	}

	return $answer;

}

// The tools of a server, as pg_mcp_client_tool_normalise() keeps them.
// Answers ok, error, tools, ttl (seconds the list may be kept, 0 when the
// server does not say) and status (HTTP: a 401 is a credential the server no
// longer takes).
function pg_mcp_client_list_tools(&$conn) {

	$tools = array();

	$seen = array();

	$cursor = '';

	$ttl = 0;

	for ($page = 0; $page < PG_MCP_CLIENT_MAX_PAGES; $page++) {

		$answer = pg_mcp_client_request($conn, 'tools/list', ($cursor !== '') ? array('cursor' => $cursor) : array());

		if (!$answer['ok']) {

			return array('ok' => false, 'error' => $answer['error'], 'tools' => array(), 'ttl' => 0, 'status' => $answer['status']);

		}

		// The schemas are read again from the text as objects: decoded as
		// arrays, an empty object ({}) would come back as a list ([]).
		$objects = json_decode($answer['raw']);

		$listed = (isset($objects->result->tools) && is_array($objects->result->tools)) ? $objects->result->tools : array();

		foreach ($listed as $index => $object) {

			$tool = isset($answer['result']['tools'][$index]) ? $answer['result']['tools'][$index] : null;

			$schema = isset($object->inputSchema) ? json_encode($object->inputSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

			$kept = is_array($tool) ? pg_mcp_client_tool_normalise($tool, ($schema !== false) ? $schema : '') : null;

			if ($kept && !isset($seen[$kept['name']])) {

				$seen[$kept['name']] = true;

				$tools[] = $kept;

			}

			if (count($tools) >= PG_MCP_CLIENT_MAX_TOOLS) {

				break 2;

			}

		}

		if (isset($answer['result']['ttlMs']) && is_numeric($answer['result']['ttlMs'])) {

			$ttl = max(0, (int)floor($answer['result']['ttlMs'] / 1000));

		}

		$cursor = (isset($answer['result']['nextCursor']) && is_string($answer['result']['nextCursor'])) ? $answer['result']['nextCursor'] : '';

		if ($cursor === '') {

			break;

		}

	}

	return array('ok' => true, 'error' => '', 'tools' => $tools, 'ttl' => $ttl, 'status' => 200);

}

// A tool as it is kept: its name, a title and a description cut to size, its
// input schema as JSON text (an object schema; one that is not becomes the
// empty object schema) and the hints the server gives about it. A tool
// without a usable name is left out (null).
function pg_mcp_client_tool_normalise($tool, $schema_json) {

	$name = isset($tool['name']) ? (string)$tool['name'] : '';

	if (($name === '') || (strlen($name) > 128) || !preg_match('/^[A-Za-z0-9_.\/-]+$/', $name)) {

		return null;

	}

	$annotations = (isset($tool['annotations']) && is_array($tool['annotations'])) ? $tool['annotations'] : array();

	$title = isset($tool['title']) ? (string)$tool['title'] : (isset($annotations['title']) ? (string)$annotations['title'] : '');

	$schema = json_decode((string)$schema_json, true);

	if (!is_array($schema) || (isset($schema['type']) && ($schema['type'] !== 'object'))) {

		$schema_json = '{"type":"object","properties":{}}';

	}

	// The hints exactly as given, booleans only: whether a hint was given at
	// all matters as much as its value.
	$hints = array();

	foreach (array('readOnlyHint', 'destructiveHint', 'idempotentHint', 'openWorldHint') as $hint) {

		if (isset($annotations[$hint]) && is_bool($annotations[$hint])) {

			$hints[$hint] = $annotations[$hint];

		}

	}

	return array(
		'name'        => $name,
		'title'       => mb_substr(trim(preg_replace('/\s+/u', ' ', $title)), 0, 120),
		'description' => mb_substr(trim((string)(isset($tool['description']) ? $tool['description'] : '')), 0, 2000),
		'schema'      => (string)$schema_json,
		// A hint is taken only as the boolean it has to be; anything else
		// counts as not given. Not given, the protocol's defaults hold: a
		// tool may write, and what it writes may be destructive.
		'read_only'   => (isset($annotations['readOnlyHint']) && ($annotations['readOnlyHint'] === true)),
		'destructive' => !(isset($annotations['readOnlyHint']) && ($annotations['readOnlyHint'] === true))
			&& !(isset($annotations['destructiveHint']) && ($annotations['destructiveHint'] === false)),
		'open_world'  => !(isset($annotations['openWorldHint']) && ($annotations['openWorldHint'] === false)),
		'hints'       => $hints
	);

}

// Calls a tool. The arguments are an array or, to keep an empty object one,
// what json_decode() gives without its second argument. Answers ok (the call
// was answered), is_error (the tool says it failed), result, error and status
// (HTTP).
function pg_mcp_client_call_tool(&$conn, $name, $arguments) {

	$answer = pg_mcp_client_request($conn, 'tools/call', array(
		'name'      => (string)$name,
		'arguments' => is_object($arguments) ? $arguments : (object)(is_array($arguments) ? $arguments : array())
	));

	if (!$answer['ok']) {

		return array('ok' => false, 'is_error' => true, 'result' => null, 'error' => $answer['error'], 'status' => $answer['status'], 'task' => null);

	}

	$result = $answer['result'];

	// A task: the result comes later, from pg_mcp_client_get_task().
	if (!empty($answer['task'])) {

		return array('ok' => true, 'is_error' => false, 'result' => null, 'error' => '', 'status' => $answer['status'], 'task' => $answer['task']);

	}

	return array(
		'ok'       => true,
		'is_error' => !empty($result['isError']),
		'result'   => $result,
		'error'    => '',
		'status'   => $answer['status'],
		'task'     => null
	);

}

// A task as a server describes it - a tools/call answered with a handle, or
// tasks/get - as it is kept: id, status (working, input_required, completed,
// failed, cancelled; a status this client does not know counts as working),
// message (the server's own words on where it stands, on one line), poll_ms
// (how long the server asks to wait between two questions, 0 when it does
// not say), result (completed: what the request would have answered at
// once) and error (failed: the error's message). null when it carries no
// usable id. The id is sent back as it is, so it must fit where it is kept.
function pg_mcp_client_task($result) {

	if (!is_array($result) || !isset($result['taskId']) || !is_string($result['taskId'])) {

		return null;

	}

	$id = $result['taskId'];

	if (($id === '') || (strlen($id) > 255) || !preg_match('/^[^\x00-\x1F\x7F]+$/u', $id)) {

		return null;

	}

	$status = isset($result['status']) ? (string)$result['status'] : '';

	if (!in_array($status, array('working', 'input_required', 'completed', 'failed', 'cancelled'), true)) {

		$status = 'working';

	}

	$message = isset($result['statusMessage']) && is_scalar($result['statusMessage']) ? trim(preg_replace('/\s+/u', ' ', (string)$result['statusMessage'])) : '';

	$error = '';

	if ($status === 'failed') {

		$error = (isset($result['error']['message']) && is_scalar($result['error']['message'])) ? (string)$result['error']['message'] : $message;

		$error = mb_substr(trim(preg_replace('/\s+/u', ' ', $error)), 0, 300);

	}

	return array(
		'id'      => $id,
		'status'  => $status,
		'message' => mb_substr($message, 0, 200),
		'poll_ms' => (isset($result['pollIntervalMs']) && is_numeric($result['pollIntervalMs'])) ? max(0, (int)$result['pollIntervalMs']) : 0,
		'result'  => (($status === 'completed') && isset($result['result']) && is_array($result['result'])) ? $result['result'] : null,
		'error'   => $error
	);

}

// Asks a server where one of its tasks stands. Answers ok (the server
// answered), task (as pg_mcp_client_task() keeps it; null with ok false
// when the answer is not a task), error, code (the JSON-RPC error code: a
// task the server does not know) and status (HTTP).
function pg_mcp_client_get_task(&$conn, $task_id) {

	$answer = pg_mcp_client_request($conn, 'tasks/get', array('taskId' => (string)$task_id));

	if (!$answer['ok']) {

		return array('ok' => false, 'task' => null, 'error' => $answer['error'], 'code' => $answer['code'], 'status' => $answer['status']);

	}

	$task = pg_mcp_client_task($answer['result']);

	if (!$task || ($task['id'] !== (string)$task_id)) {

		return array('ok' => false, 'task' => null, 'error' => lang(array('string' => 'The MCP server gave an answer that could not be read (HTTP {var:1}).', 'vars' => $answer['status'])), 'code' => 0, 'status' => $answer['status']);

	}

	return array('ok' => true, 'task' => $task, 'error' => '', 'code' => 0, 'status' => $answer['status']);

}

// Asks a server to stop one of its tasks. Stopping is the server's to do:
// it may still finish the work. Answers ok, error and status (HTTP).
function pg_mcp_client_cancel_task(&$conn, $task_id) {

	$answer = pg_mcp_client_request($conn, 'tasks/cancel', array('taskId' => (string)$task_id));

	return array('ok' => $answer['ok'], 'error' => $answer['error'], 'status' => $answer['status']);

}

// A tool's result as text, for a model or a message: its text parts in
// order, a resource's text or address, a note for what cannot be shown as
// text (an image, audio), and the structured result when nothing else says
// anything. Cut to $limit characters.
function pg_mcp_client_result_text($result, $limit = 6000) {

	$parts = array();

	$content = (isset($result['content']) && is_array($result['content'])) ? $result['content'] : array();

	foreach ($content as $item) {

		$type = (is_array($item) && isset($item['type'])) ? (string)$item['type'] : '';

		if (($type === 'text') && isset($item['text'])) {

			$parts[] = (string)$item['text'];

		} elseif (($type === 'resource') && isset($item['resource']) && is_array($item['resource'])) {

			$resource = $item['resource'];

			$parts[] = isset($resource['text']) ? (string)$resource['text'] : '[' . (isset($resource['uri']) ? (string)$resource['uri'] : 'resource') . ']';

		} elseif ($type === 'resource_link') {

			$parts[] = trim((isset($item['name']) ? (string)$item['name'] . ' ' : '') . '<' . (isset($item['uri']) ? (string)$item['uri'] : '') . '>');

		} elseif (($type === 'image') || ($type === 'audio')) {

			$parts[] = '[' . $type . (isset($item['mimeType']) ? ' ' . (string)$item['mimeType'] : '') . ']';

		}

	}

	$text = trim(implode("\n\n", $parts));

	if (($text === '') && isset($result['structuredContent'])) {

		$json = json_encode($result['structuredContent'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

		$text = ($json !== false) ? $json : '';

	}

	if (mb_strlen($text) > $limit) {

		$text = rtrim(mb_substr($text, 0, max(1, $limit - 1))) . '…';

	}

	return $text;

}

/* ---------------------------------------------------------------------------
   Signing in to a server (the MCP authorization specification)
   ---------------------------------------------------------------------------

   A server that wants a sign-in answers a request without one with 401 and a
   WWW-Authenticate header naming its protected resource metadata (RFC 9728);
   that names the authorization server, whose own metadata (RFC 8414, or
   OpenID Connect discovery) gives the endpoints. The site is then a client of
   that server: by its client ID metadata document when the server reads
   those, by dynamic registration (RFC 7591) when it offers that, otherwise by
   an application the administrator registered there. The code, the tokens and
   their renewal are includes/fn/connect.php's.

   Every request goes through api_http_request(). $fetch, where a function
   takes it, is a callable taking (method, url, body, headers) and answering
   like api_http_request() with response_headers; only the tests pass one. */

// The parameters of the Bearer challenge in a WWW-Authenticate header, by
// lower-case name (resource_metadata, scope, error, ...). Empty when the
// header has no Bearer challenge.
function pg_mcp_client_bearer_challenge($header) {

	$header = (string)$header;

	if (!preg_match('/(?:^|,)\s*Bearer(?=\s|,|$)/i', $header, $match, PREG_OFFSET_CAPTURE)) {

		return array();

	}

	$rest = (string)substr($header, $match[0][1] + strlen($match[0][0]));

	$params = array();

	// Each auth-param is a name, = and a token or a quoted string. The next
	// challenge ("Basic realm=...") is a word not followed by =, so the
	// parameters of this one end there.
	while (preg_match('/^\s*,?\s*([A-Za-z0-9_.\-]+)\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^\s,"]*))/', $rest, $param)) {

		$name = strtolower($param[1]);

		if (!isset($params[$name])) {

			$params[$name] = array_key_exists(3, $param) ? $param[3] : preg_replace('/\\\\(.)/s', '$1', $param[2]);

		}

		$rest = (string)substr($rest, strlen($param[0]));

	}

	return $params;

}

// scheme://host[:port] of an address, in lower case, without a default port.
// '' when the address has no scheme or host.
function pg_mcp_client_origin($url) {

	$parts = @parse_url(trim((string)$url));

	if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || ($parts['host'] === '')) {

		return '';

	}

	$scheme = strtolower($parts['scheme']);

	$port = isset($parts['port']) ? (int)$parts['port'] : 0;

	$default = ($scheme === 'https') ? 443 : 80;

	return $scheme . '://' . strtolower($parts['host']) . ((($port > 0) && ($port !== $default)) ? ':' . $port : '');

}

// Whether an address of the sign-in (a metadata document, an endpoint) may be
// used: https, like a server's own address, or http where the site allows
// outgoing http at all (API_WEBHOOK_ALLOW_HTTP, which only a test setup sets).
function pg_mcp_client_auth_url_is_valid($url) {

	$url = trim((string)$url);

	if (pg_mcp_client_url_is_valid($url)) {

		return true;

	}

	if (!defined('API_WEBHOOK_ALLOW_HTTP') || (API_WEBHOOK_ALLOW_HTTP !== true) || (stripos($url, 'http://') !== 0)) {

		return false;

	}

	return pg_mcp_client_url_is_valid('https://' . substr($url, 7));

}

// A server's address as the resource a token is asked for (RFC 8707, in the
// MCP specification's canonical form): the scheme and host in lower case, the
// path without a trailing slash, the query kept, no fragment.
function pg_mcp_client_canonical_resource($url) {

	$origin = pg_mcp_client_origin($url);

	if ($origin === '') {

		return '';

	}

	$path = rtrim((string)parse_url(trim((string)$url), PHP_URL_PATH), '/');

	$query = (string)parse_url(trim((string)$url), PHP_URL_QUERY);

	return $origin . $path . (($query !== '') ? '?' . $query : '');

}

// Whether the resource a metadata document names is this server: the same
// origin, and the server's path that path or under it. A document about
// another resource is not this server's (RFC 9728 3.3) and is not followed.
function pg_mcp_client_resource_covers($resource, $server_url) {

	$resource = trim((string)$resource);

	$origin = pg_mcp_client_origin($resource);

	if (($origin === '') || ($origin !== pg_mcp_client_origin($server_url)) || (strpos($resource, '#') !== false)) {

		return false;

	}

	$base = rtrim((string)parse_url($resource, PHP_URL_PATH), '/');

	$path = rtrim((string)parse_url(trim((string)$server_url), PHP_URL_PATH), '/');

	return ($base === '') || (strpos($path . '/', $base . '/') === 0);

}

// Where a server's protected resource metadata may be, in the order the MCP
// specification asks: the address its challenge named, then the well-known
// name with the server's path after it, then the well-known name at the root
// (RFC 9728 3.1).
function pg_mcp_client_resource_metadata_urls($server_url, $named = '') {

	$urls = array();

	if (((string)$named !== '') && pg_mcp_client_auth_url_is_valid($named)) {

		$urls[] = (string)$named;

	}

	$origin = pg_mcp_client_origin($server_url);

	if ($origin !== '') {

		$path = rtrim((string)parse_url(trim((string)$server_url), PHP_URL_PATH), '/');

		if ($path !== '') {

			$urls[] = $origin . '/.well-known/oauth-protected-resource' . $path;

		}

		$urls[] = $origin . '/.well-known/oauth-protected-resource';

	}

	return array_values(array_unique($urls));

}

// Where an authorization server's metadata may be, in the order the MCP
// specification asks: RFC 8414 with the issuer's path after the well-known
// name, OpenID Connect discovery the same way, then OpenID Connect's own form
// with the well-known name after the path. An issuer without a path has the
// first two, at the root.
function pg_mcp_client_auth_server_metadata_urls($issuer) {

	$origin = pg_mcp_client_origin($issuer);

	if ($origin === '') {

		return array();

	}

	$path = rtrim((string)parse_url(trim((string)$issuer), PHP_URL_PATH), '/');

	if ($path === '') {

		return array($origin . '/.well-known/oauth-authorization-server', $origin . '/.well-known/openid-configuration');

	}

	return array(
		$origin . '/.well-known/oauth-authorization-server' . $path,
		$origin . '/.well-known/openid-configuration' . $path,
		$origin . $path . '/.well-known/openid-configuration'
	);

}

// An authorization server's metadata, checked: it names the issuer it was
// asked for (RFC 8414 3.3; a trailing slash aside), its endpoints are
// addresses that may be called, and it takes PKCE with S256 - the MCP
// specification has a client refuse a server that does not say so. Answers
// ok, error, issuer, authorize_url, token_url, revoke_url, registration_url,
// cimd (it reads client ID metadata documents), iss_param (it names itself on
// the redirect, RFC 9207), auth_methods (token_endpoint_auth_methods_supported)
// and scopes (scopes_supported).
function pg_mcp_client_auth_server_read($metadata, $issuer) {

	$fail = function ($error) {

		return array('ok' => false, 'error' => $error);

	};

	if (!is_array($metadata) || !isset($metadata['issuer']) || !is_string($metadata['issuer'])
		|| (rtrim($metadata['issuer'], '/') !== rtrim((string)$issuer, '/'))) {

		return $fail(lang('The sign-in server\'s metadata names another issuer, so it is not followed.'));

	}

	foreach (array('authorization_endpoint', 'token_endpoint') as $name) {

		if (!isset($metadata[$name]) || !is_string($metadata[$name]) || !pg_mcp_client_auth_url_is_valid($metadata[$name])) {

			return $fail(lang('The sign-in server gives no usable authorization or token address.'));

		}

	}

	$list = function ($name) use ($metadata) {

		return (isset($metadata[$name]) && is_array($metadata[$name])) ? array_values(array_filter($metadata[$name], 'is_string')) : array();

	};

	if (!in_array('S256', $list('code_challenge_methods_supported'), true)) {

		return $fail(lang('The sign-in server does not say that it takes PKCE (S256), so the site does not sign in there.'));

	}

	$optional = function ($name) use ($metadata) {

		return (isset($metadata[$name]) && is_string($metadata[$name]) && pg_mcp_client_auth_url_is_valid($metadata[$name])) ? $metadata[$name] : '';

	};

	return array(
		'ok'               => true,
		'error'            => '',
		'issuer'           => $metadata['issuer'],
		'authorize_url'    => $metadata['authorization_endpoint'],
		'token_url'        => $metadata['token_endpoint'],
		'revoke_url'       => $optional('revocation_endpoint'),
		'registration_url' => $optional('registration_endpoint'),
		'cimd'             => (isset($metadata['client_id_metadata_document_supported']) && ($metadata['client_id_metadata_document_supported'] === true)),
		'iss_param'        => (isset($metadata['authorization_response_iss_parameter_supported']) && ($metadata['authorization_response_iss_parameter_supported'] === true)),
		'auth_methods'     => $list('token_endpoint_auth_methods_supported'),
		'scopes'           => $list('scopes_supported')
	);

}

// What the sign-in's requests go through when the caller passes nothing.
function pg_mcp_client_fetcher($timeout = 20) {

	return function ($method, $url, $body, $headers) use ($timeout) {

		if (!function_exists('api_http_request')) {

			require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

		}

		$options = array(
			'headers'          => $headers,
			'timeout'          => $timeout,
			'max_body'         => 65536,
			'agent'            => 'mcp',
			'response_headers' => true
		);

		if ($body !== null) {

			$options['body'] = $body;

		}

		return api_http_request($method, $url, $options);

	};

}

// A metadata document: a JSON object answered with 200, or null.
function pg_mcp_client_fetch_json($fetch, $url) {

	$got = call_user_func($fetch, 'GET', $url, null, array('Accept: application/json'));

	if (!is_array($got) || !isset($got['status']) || ((int)$got['status'] !== 200)) {

		return null;

	}

	$data = json_decode(isset($got['body']) ? (string)$got['body'] : '', true);

	return is_array($data) ? $data : null;

}

// Finds out how to sign in to a server. Answers ok, error, resource (what a
// token is asked for), what pg_mcp_client_auth_server_read() gives, scopes
// (those the server's challenge asked for, else those its resource metadata
// lists, else none, the MCP specification's order) and challenged (the server
// refused a request without a sign-in).
function pg_mcp_client_discover_auth($url, $fetch = null, $timeout = 20) {

	$fail = function ($error) {

		return array('ok' => false, 'error' => $error);

	};

	$url = trim((string)$url);

	if (!pg_mcp_client_auth_url_is_valid($url)) {

		return $fail(lang('The address must be an https address, without a user name, a password or a # part.'));

	}

	$fetch = is_callable($fetch) ? $fetch : pg_mcp_client_fetcher($timeout);

	// Asked without a credential, a server that wants a sign-in says where.
	$conn = pg_mcp_client_connection($url, array(), '', '', $timeout);

	$conn['transport'] = function ($to, $body, $headers, $seconds, $max_body) use ($fetch) {

		return call_user_func($fetch, 'POST', $to, $body, $headers);

	};

	$probe = pg_mcp_client_send($conn, 'initialize', array(
		'protocolVersion' => '2025-11-25',
		'capabilities'    => (object)array(),
		'clientInfo'      => pg_mcp_client_info()
	), '');

	if ($probe['status'] === 0) {

		return $fail($probe['error']);

	}

	$challenged = ($probe['status'] === 401);

	$challenge = $challenged ? pg_mcp_client_bearer_challenge(isset($probe['headers']['www-authenticate']) ? $probe['headers']['www-authenticate'] : '') : array();

	$resource_metadata = null;

	foreach (pg_mcp_client_resource_metadata_urls($url, isset($challenge['resource_metadata']) ? $challenge['resource_metadata'] : '') as $metadata_url) {

		$document = pg_mcp_client_fetch_json($fetch, $metadata_url);

		if (is_array($document) && isset($document['authorization_servers']) && is_array($document['authorization_servers']) && $document['authorization_servers']) {

			$resource_metadata = $document;

			break;

		}

	}

	if (is_array($resource_metadata)) {

		$resource = (isset($resource_metadata['resource']) && is_string($resource_metadata['resource'])) ? trim($resource_metadata['resource']) : '';

		if (($resource !== '') && !pg_mcp_client_resource_covers($resource, $url)) {

			return $fail(lang('The server\'s sign-in metadata is about another address, so it is not followed.'));

		}

		if ($resource === '') {

			$resource = pg_mcp_client_canonical_resource($url);

		}

		$issuers = array_values(array_filter($resource_metadata['authorization_servers'], 'is_string'));

	} else {

		// A server of the first authorization specification (2025-03-26)
		// has no resource metadata: its own origin is the authorization
		// server.
		$resource = pg_mcp_client_canonical_resource($url);

		$issuers = array(pg_mcp_client_origin($url));

	}

	$server = null;

	$error = lang('The sign-in server\'s metadata could not be found.');

	foreach (array_slice($issuers, 0, 3) as $issuer) {

		if (!pg_mcp_client_auth_url_is_valid($issuer)) {

			continue;

		}

		foreach (pg_mcp_client_auth_server_metadata_urls($issuer) as $metadata_url) {

			$document = pg_mcp_client_fetch_json($fetch, $metadata_url);

			if (!is_array($document)) {

				continue;

			}

			$read = pg_mcp_client_auth_server_read($document, $issuer);

			if ($read['ok']) {

				$server = $read;

				break 2;

			}

			$error = $read['error'];

		}

	}

	if (!is_array($server)) {

		return $fail((!$challenged && !is_array($resource_metadata)) ? lang('The server does not ask for a sign-in. Choose another way to sign in, or none.') : $error);

	}

	if (isset($challenge['scope']) && (trim($challenge['scope']) !== '')) {

		$scopes = preg_split('/\s+/', trim($challenge['scope']));

	} elseif (is_array($resource_metadata) && isset($resource_metadata['scopes_supported']) && is_array($resource_metadata['scopes_supported'])) {

		$scopes = array_values(array_filter($resource_metadata['scopes_supported'], 'is_string'));

	} else {

		$scopes = array();

	}

	return array_merge($server, array(
		'resource'   => $resource,
		'scopes'     => array_slice(array_values(array_unique($scopes)), 0, 50),
		'challenged' => $challenged
	));

}

// What the site says about itself as an OAuth client, in its client ID
// metadata document (with client_id, the document's own address) and in a
// dynamic registration (without). It has no secret: PKCE proves it.
function pg_mcp_client_client_metadata($redirect_uri, $home, $client_id = '') {

	$info = pg_mcp_client_info();

	$metadata = ($client_id !== '') ? array('client_id' => (string)$client_id) : array();

	return $metadata + array(
		'client_name'                => $info['title'],
		'client_uri'                 => (string)$home,
		'redirect_uris'              => array((string)$redirect_uri),
		'grant_types'                => array('authorization_code', 'refresh_token'),
		'response_types'             => array('code'),
		'token_endpoint_auth_method' => 'none',
		'software_id'                => 'pinegrap',
		'software_version'           => $info['version']
	);

}

// Registers the site with an authorization server (RFC 7591). A server that
// hands out a secret anyway says how it wants it sent. Answers ok, error,
// client_id, client_secret ('' for none) and auth_method.
function pg_mcp_client_register($registration_url, $metadata, $scopes = array(), $fetch = null, $timeout = 20) {

	$fetch = is_callable($fetch) ? $fetch : pg_mcp_client_fetcher($timeout);

	if ($scopes) {

		$metadata['scope'] = implode(' ', $scopes);

	}

	$got = call_user_func($fetch, 'POST', $registration_url, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), array('Content-Type: application/json', 'Accept: application/json'));

	$status = (is_array($got) && isset($got['status'])) ? (int)$got['status'] : 0;

	$data = json_decode((is_array($got) && isset($got['body'])) ? (string)$got['body'] : '', true);

	if ((($status !== 200) && ($status !== 201)) || !is_array($data) || !isset($data['client_id']) || !is_string($data['client_id']) || ($data['client_id'] === '')) {

		if (is_array($data) && isset($data['error']) && is_string($data['error'])) {

			$reason = $data['error'] . ((isset($data['error_description']) && is_string($data['error_description'])) ? ': ' . $data['error_description'] : '');

		} elseif ($status === 0) {

			$reason = pg_mcp_client_transport_text((is_array($got) && isset($got['error'])) ? $got['error'] : '', (is_array($got) && isset($got['errno'])) ? (int)$got['errno'] : 0);

		} else {

			$reason = 'HTTP ' . $status;

		}

		return array('ok' => false, 'error' => lang(array('string' => 'The sign-in server did not register the site: {var:1}', 'vars' => mb_substr($reason, 0, 200))));

	}

	$secret = (isset($data['client_secret']) && is_string($data['client_secret'])) ? $data['client_secret'] : '';

	$method = (isset($data['token_endpoint_auth_method']) && is_string($data['token_endpoint_auth_method']))
		? $data['token_endpoint_auth_method']
		: (($secret !== '') ? 'client_secret_basic' : 'none');

	return array('ok' => true, 'error' => '', 'client_id' => $data['client_id'], 'client_secret' => $secret, 'auth_method' => $method);

}
