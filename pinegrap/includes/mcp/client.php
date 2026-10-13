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
		'url'       => (string)$url,
		'headers'   => array_values((array)$headers),
		'protocol'  => (string)$protocol,
		'session'   => (string)$session,
		'timeout'   => max(3, (int)$timeout),
		'transport' => null
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
// current generation also carries its version in _meta.
function pg_mcp_client_message($id, $method, $params, $version) {

	$params = is_array($params) ? $params : array();

	if (pg_mcp_client_is_modern($version)) {

		$meta = (isset($params['_meta']) && is_array($params['_meta'])) ? $params['_meta'] : array();

		$meta['io.modelcontextprotocol/protocolVersion'] = $version;

		$meta['io.modelcontextprotocol/clientInfo'] = pg_mcp_client_info();

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
// case name), body, error.
function pg_mcp_client_post($conn, $message, $method, $version) {

	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);

	$body = json_encode($message, $flags);

	if ($body === false) {

		return array('status' => 0, 'headers' => array(), 'body' => '', 'error' => 'The request could not be written as JSON.');

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
		'error'   => isset($response['error']) ? (string)$response['error'] : ''
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
	foreach ($candidates as $candidate) {

		list($message, $raw) = $candidate;

		if (isset($message['error']) && (!array_key_exists('id', $message) || ($message['id'] === null))) {

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
// for none), status (HTTP) and headers.
function pg_mcp_client_send($conn, $method, $params, $version) {

	$id = pg_mcp_client_next_id();

	$response = pg_mcp_client_post($conn, pg_mcp_client_message($id, $method, $params, $version), $method, $version);

	$out = array('ok' => false, 'result' => null, 'raw' => '', 'error' => '', 'code' => 0, 'status' => $response['status'], 'headers' => $response['headers']);

	if ($response['status'] === 0) {

		$out['error'] = pg_mcp_client_transport_text($response['error']);

		return $out;

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

		// Work that goes on after the answer (the tasks extension) is not
		// followed: the call is reported as not done rather than as empty.
		if (isset($result['resultType']) && ($result['resultType'] !== 'complete')) {

			$out['error'] = lang('The MCP server answered that the work goes on in the background, which is not supported here yet.');

			return $out;

		}

		$out['ok'] = true;

		$out['result'] = $result;

		$out['raw'] = $read['raw'];

		return $out;

	}

	$out['error'] = pg_mcp_client_status_text($response['status']);

	return $out;

}

// Why a request did not reach a server, in a sentence. The refusals of
// api_http_check_url() are said in the screen's language; anything else is
// curl's own words.
function pg_mcp_client_transport_text($error) {

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

// A session id as the transport allows it: visible ASCII only.
function pg_mcp_client_session_is_valid($session) {

	return is_string($session) && ($session !== '') && (strlen($session) <= 255) && (bool)preg_match('/^[\x21-\x7E]+$/', $session);

}

// Finds out how a server is spoken to: the current generation first, then
// initialize. Answers ok, error, status, instructions and server (its name
// and version, as it gives them); the connection learns the version and,
// from an earlier-generation server, the session.
function pg_mcp_client_negotiate(&$conn) {

	$out = array('ok' => false, 'error' => '', 'status' => 0, 'instructions' => '', 'server' => array());

	$conn['protocol'] = '';

	$conn['session'] = '';

	$discover = pg_mcp_client_send($conn, 'server/discover', array(), '2026-07-28');

	$out['status'] = $discover['status'];

	// A refused credential is refused whatever the version: asking again with
	// initialize would only hide the reason.
	if (($discover['status'] === 401) || ($discover['status'] === 403)) {

		$out['error'] = $discover['error'];

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
// server does not say).
function pg_mcp_client_list_tools(&$conn) {

	$tools = array();

	$seen = array();

	$cursor = '';

	$ttl = 0;

	for ($page = 0; $page < PG_MCP_CLIENT_MAX_PAGES; $page++) {

		$answer = pg_mcp_client_request($conn, 'tools/list', ($cursor !== '') ? array('cursor' => $cursor) : array());

		if (!$answer['ok']) {

			return array('ok' => false, 'error' => $answer['error'], 'tools' => array(), 'ttl' => 0);

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

	return array('ok' => true, 'error' => '', 'tools' => $tools, 'ttl' => $ttl);

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
// was answered), is_error (the tool says it failed), result, error.
function pg_mcp_client_call_tool(&$conn, $name, $arguments) {

	$answer = pg_mcp_client_request($conn, 'tools/call', array(
		'name'      => (string)$name,
		'arguments' => is_object($arguments) ? $arguments : (object)(is_array($arguments) ? $arguments : array())
	));

	if (!$answer['ok']) {

		return array('ok' => false, 'is_error' => true, 'result' => null, 'error' => $answer['error']);

	}

	$result = $answer['result'];

	return array(
		'ok'       => true,
		'is_error' => !empty($result['isError']),
		'result'   => $result,
		'error'    => ''
	);

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
