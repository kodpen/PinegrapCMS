<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the MCP client (includes/mcp/client.php): reading answers sent
 * as JSON or as an event stream, the handshake with a server of either
 * generation, a forgotten session, paged tool lists, what a tool's result
 * becomes as text, a task followed to its result (the tasks extension), and
 * what a refusal says: a sign-in asked for, a moved address, an old /sse
 * address, a JSON-RPC error, a timeout. The servers are stand-ins passed as
 * the connection's transport; nothing goes over the network.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/client.php');

// A header's value in a list of "Name: value" lines, '' when it is missing.
function mcp_client_test_header($headers, $name)
{
	foreach ($headers as $header) {
		list($key, $value) = array_pad(explode(':', $header, 2), 2, '');

		if (strcasecmp(trim($key), $name) === 0) {
			return trim($value);
		}
	}

	return '';
}

// A stand-in server of the current generation. Every request is written to
// $log; tools come in two pages.
function mcp_client_test_modern_server(&$log)
{
	return function ($url, $body, $headers, $timeout, $max_body) use (&$log) {
		$message = json_decode($body, true);
		$log[] = array('message' => $message, 'headers' => $headers);
		$id = $message['id'] ?? null;
		$answer = function ($result) use ($id) {
			$result['resultType'] = 'complete';

			return array('status' => 200, 'headers' => array('content-type' => 'application/json'), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result)), 'error' => '');
		};

		if (mcp_client_test_header($headers, 'MCP-Protocol-Version') !== '2026-07-28') {
			return array('status' => 400, 'headers' => array(), 'body' => '', 'error' => '');
		}

		switch ($message['method']) {
			case 'server/discover':
				return $answer(array(
					'supportedVersions' => array('2026-07-28'),
					'capabilities'      => array('tools' => new stdClass()),
					'instructions'      => 'Use search first.',
					'_meta'             => array('io.modelcontextprotocol/serverInfo' => array('name' => 'docs', 'version' => '1.2')),
				));

			case 'tools/list':
				if (empty($message['params']['cursor'])) {
					return array('status' => 200, 'headers' => array('content-type' => 'application/json'),
						'body' => '{"jsonrpc":"2.0","id":' . json_encode($id) . ',"result":{"resultType":"complete","nextCursor":"p2","tools":[{"name":"search","description":"Search the docs.","inputSchema":{"type":"object","properties":{"query":{"type":"string"}},"required":["query"]},"annotations":{"readOnlyHint":true}},{"name":"ping","inputSchema":{"type":"object","properties":{}}}]}}', 'error' => '');
				}

				return $answer(array('tools' => array(array('name' => 'delete.page', 'inputSchema' => array('type' => 'object'), 'annotations' => array('destructiveHint' => true))), 'ttlMs' => 300000));

			case 'tools/call':
				return $answer(array('content' => array(array('type' => 'text', 'text' => 'Found: ' . $message['params']['arguments']['query']))));
		}

		return array('status' => 404, 'headers' => array(), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'error' => array('code' => -32601, 'message' => 'Method not found'))), 'error' => '');
	};
}

// A stand-in server of the earlier generation: no server/discover, a
// session from initialize, answers as an event stream. $state['forget'] makes
// it forget the session once.
function mcp_client_test_legacy_server(&$log, &$state)
{
	return function ($url, $body, $headers, $timeout, $max_body) use (&$log, &$state) {
		$message = json_decode($body, true);
		$log[] = array('message' => $message, 'headers' => $headers);
		$id = $message['id'] ?? null;
		$stream = function ($result) use ($id) {
			return array('status' => 200, 'headers' => array('content-type' => 'text/event-stream'),
				'body' => ": keep-alive\n\nevent: message\ndata: {\"jsonrpc\":\"2.0\",\"method\":\"notifications/progress\",\"params\":{}}\n\nevent: message\ndata: " . json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result)) . "\n\n", 'error' => '');
		};

		if ($message['method'] === 'server/discover') {
			return array('status' => 200, 'headers' => array('content-type' => 'application/json'), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'error' => array('code' => -32601, 'message' => 'Method not found'))), 'error' => '');
		}

		if ($message['method'] === 'initialize') {
			$state['sessions'] = ($state['sessions'] ?? 0) + 1;

			return array('status' => 200, 'headers' => array('content-type' => 'application/json', 'mcp-session-id' => 'sess-' . $state['sessions']),
				'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'result' => array('protocolVersion' => '2025-06-18', 'capabilities' => array(), 'serverInfo' => array('name' => 'legacy', 'version' => '0.9'), 'instructions' => 'Old server.'))), 'error' => '');
		}

		if (mcp_client_test_header($headers, 'Mcp-Session-Id') !== 'sess-' . ($state['sessions'] ?? 0)) {
			return array('status' => 400, 'headers' => array(), 'body' => '', 'error' => '');
		}

		if ($message['method'] === 'notifications/initialized') {
			return array('status' => 202, 'headers' => array(), 'body' => '', 'error' => '');
		}

		if (!empty($state['forget'])) {
			$state['forget'] = false;

			return array('status' => 404, 'headers' => array(), 'body' => '', 'error' => '');
		}

		if ($message['method'] === 'tools/list') {
			return $stream(array('tools' => array(array('name' => 'list_issues', 'inputSchema' => array('type' => 'object', 'properties' => new stdClass())))));
		}

		return $stream(array('content' => array(array('type' => 'text', 'text' => 'ok')), 'isError' => false));
	};
}

function test_mcp_client_url_rules()
{
	pg_assert_true(pg_mcp_client_url_is_valid('https://mcp.example.com/mcp'));
	pg_assert_true(pg_mcp_client_url_is_valid('https://example.com:8443/api/mcp?team=1'));
	pg_assert_false(pg_mcp_client_url_is_valid('http://mcp.example.com/mcp'), 'plain http');
	pg_assert_false(pg_mcp_client_url_is_valid('https://user:pass@mcp.example.com/'), 'credentials in the address');
	pg_assert_false(pg_mcp_client_url_is_valid('https://mcp.example.com/#x'), 'fragment');
	pg_assert_false(pg_mcp_client_url_is_valid('https://mcp.example.com/a b'), 'space');
	pg_assert_false(pg_mcp_client_url_is_valid('javascript:alert(1)'));
	pg_assert_false(pg_mcp_client_url_is_valid(''));
}

function test_mcp_client_reads_an_event_stream()
{
	$body = ": comment\n\nevent: message\ndata: {\"jsonrpc\":\"2.0\",\"method\":\"notifications/message\"}\n\nid: 7\ndata: {\"jsonrpc\":\"2.0\",\ndata: \"id\":3,\"result\":{\"a\":1}}\n\n";
	$read = pg_mcp_client_read(array('headers' => array('content-type' => 'text/event-stream; charset=utf-8'), 'body' => $body), 3);

	pg_assert_same(array('a' => 1), $read['message']['result'] ?? null, 'data lines of one event are joined');
	pg_assert_same(2, count(pg_mcp_client_sse_messages($body)), 'the comment is no message');

	$crlf = pg_mcp_client_read(array('headers' => array(), 'body' => "data: {\"jsonrpc\":\"2.0\",\"id\":1,\"result\":{}}\r\n\r\n"), 1);
	pg_assert_true(is_array($crlf['message']), 'a stream without its content type, with CRLF line ends');
}

function test_mcp_client_reads_the_answer_with_the_right_id()
{
	$json = array('headers' => array('content-type' => 'application/json'), 'body' => '{"jsonrpc":"2.0","id":5,"result":{"ok":true}}');

	pg_assert_true(is_array(pg_mcp_client_read($json, 5)['message']));
	pg_assert_same(null, pg_mcp_client_read($json, 6)['message'], 'another request\'s answer is not this one\'s');

	$parse = pg_mcp_client_read(array('headers' => array(), 'body' => '{"jsonrpc":"2.0","id":null,"error":{"code":-32700,"message":"Parse error"}}'), 9);
	pg_assert_same(-32700, $parse['message']['error']['code'] ?? 0, 'an error without the id still says what went wrong');
}

function test_mcp_client_modern_handshake_and_tools()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://docs.example.com/mcp', array('Authorization: Bearer abc'));
	$conn['transport'] = mcp_client_test_modern_server($log);

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_true($open['ok'], 'discovered');
	pg_assert_same('2026-07-28', $conn['protocol']);
	pg_assert_same('Use search first.', $open['instructions']);
	pg_assert_same('docs', $open['server']['name'] ?? '');
	pg_assert_same('server/discover', mcp_client_test_header($log[0]['headers'], 'Mcp-Method'));
	pg_assert_same('Bearer abc', mcp_client_test_header($log[0]['headers'], 'Authorization'), 'the credential goes along');
	pg_assert_same('2026-07-28', $log[0]['message']['params']['_meta']['io.modelcontextprotocol/protocolVersion'] ?? '', 'the version is in _meta as well');

	$list = pg_mcp_client_list_tools($conn);

	pg_assert_true($list['ok']);
	pg_assert_same(array('search', 'ping', 'delete.page'), array_column($list['tools'], 'name'), 'both pages, in order');
	pg_assert_same(300, $list['ttl']);
	pg_assert_same('{"type":"object","properties":{}}', $list['tools'][1]['schema'], 'an empty object stays an object');
	pg_assert_true($list['tools'][0]['read_only']);
	pg_assert_false($list['tools'][0]['destructive']);
	pg_assert_false($list['tools'][1]['read_only'], 'no hint: may write');
	pg_assert_true($list['tools'][1]['destructive'], 'no hint: may destroy');
	pg_assert_true($list['tools'][2]['destructive']);

	$call = pg_mcp_client_call_tool($conn, 'search', array('query' => 'mcp'));
	$last = end($log);

	pg_assert_true($call['ok']);
	pg_assert_false($call['is_error']);
	pg_assert_same('Found: mcp', pg_mcp_client_result_text($call['result']));
	pg_assert_same('search', mcp_client_test_header($last['headers'], 'Mcp-Name'), 'the tool name is in a header');
	pg_assert_same('', mcp_client_test_header($last['headers'], 'Mcp-Session-Id'), 'no session in this generation');
}

function test_mcp_client_falls_back_to_initialize()
{
	$log = array();
	$state = array();
	$conn = pg_mcp_client_connection('https://code.example.com/mcp');
	$conn['transport'] = mcp_client_test_legacy_server($log, $state);

	$list = pg_mcp_client_list_tools($conn);

	pg_assert_true($list['ok'], 'listed after the fallback');
	pg_assert_same('2025-06-18', $conn['protocol'], 'the version the server chose');
	pg_assert_same('sess-1', $conn['session']);
	pg_assert_same(array('server/discover', 'initialize', 'notifications/initialized', 'tools/list'), array_map(function ($entry) {
		return $entry['message']['method'];
	}, $log));
	pg_assert_false(isset($log[2]['message']['id']), 'a notification has no id');
	pg_assert_same('2025-06-18', mcp_client_test_header($log[3]['headers'], 'MCP-Protocol-Version'));
	pg_assert_same('', mcp_client_test_header($log[3]['headers'], 'Mcp-Method'), 'the earlier generation has no method header');
	pg_assert_same('{"type":"object","properties":{}}', $list['tools'][0]['schema']);

	// The server forgets the session: opened again, once, and the call goes
	// through.
	$state['forget'] = true;
	$call = pg_mcp_client_call_tool($conn, 'list_issues', array());

	pg_assert_true($call['ok'], 'answered after a new session');
	pg_assert_same('sess-2', $conn['session']);
	pg_assert_same('ok', pg_mcp_client_result_text($call['result']));
}

function test_mcp_client_does_not_fall_back_on_refused_credentials()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://private.example.com/mcp', array('Authorization: Bearer wrong'));
	$conn['transport'] = function ($url, $body, $headers) use (&$log) {
		$log[] = json_decode($body, true);

		return array('status' => 401, 'headers' => array('www-authenticate' => 'Bearer'), 'body' => '', 'error' => '');
	};

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_false($open['ok']);
	pg_assert_same(401, $open['status']);
	pg_assert_same(1, count($log), 'initialize is not tried with the same refused credential');
	pg_assert_same('', $conn['protocol']);
	pg_assert_same('The MCP server refused the credentials. Check the token or the header.', $open['error'], 'a challenge that names no sign-in');
	pg_assert_false($open['oauth_challenge'] ?? null, 'no OAuth sign-in offered');
}

// A stand-in server that answers every request the same way and writes each
// address it was sent to $log.
function mcp_client_test_fixed_server($status, $headers, $body, &$log, $error = '', $errno = 0)
{
	return function ($url) use ($status, $headers, $body, &$log, $error, $errno) {
		$log[] = $url;

		return array('status' => $status, 'headers' => $headers, 'body' => $body, 'error' => $error, 'errno' => $errno);
	};
}

function test_mcp_client_names_oauth_when_no_credential_was_sent()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://mcp.example.com/mcp');
	$conn['transport'] = mcp_client_test_fixed_server(401, array('www-authenticate' => 'Bearer realm="OAuth", resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource/mcp", scope="read write"'), '{"error":"invalid_token"}', $log);

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_false($open['ok']);
	pg_assert_same(401, $open['status']);
	pg_assert_true($open['oauth_challenge'] ?? null, 'the server offers an OAuth sign-in');
	pg_assert_same('This MCP server asks for a sign-in: under How the site signs in, choose Sign in to the service (OAuth), or Access token (Bearer) if the service gives one.', $open['error']);
	pg_assert_same(1, count($log), 'initialize is not tried');
}

function test_mcp_client_a_refused_token_also_names_oauth()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://mcp.example.com/mcp', array('Authorization: Bearer wrong'));
	$conn['transport'] = mcp_client_test_fixed_server(401, array('www-authenticate' => 'Bearer error="invalid_token", resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource/mcp"'), '', $log);

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_false($open['ok']);
	pg_assert_true($open['oauth_challenge'] ?? null);
	pg_assert_same('The MCP server refused the credentials. Check the token or the header, or choose Sign in to the service (OAuth) under How the site signs in.', $open['error']);
	pg_assert_same(1, count($log));

	// Discovery reads the same refusal for where to sign in.
	$send = pg_mcp_client_send($conn, 'tools/list', array(), '2025-06-18');

	pg_assert_same(401, $send['status'], 'the status stays for the caller');
	pg_assert_true($send['oauth']);
	pg_assert_contains('resource_metadata', $send['headers']['www-authenticate'] ?? '', 'the challenge stays for the caller');
}

function test_mcp_client_a_moved_server_is_named_not_followed()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://mcp.example.com/mcp/');
	$conn['transport'] = mcp_client_test_fixed_server(307, array('location' => '/mcp'), '', $log);

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_false($open['ok']);
	pg_assert_same(307, $open['status']);
	pg_assert_same('The MCP server moved to https://mcp.example.com/mcp. Enter that address instead.', $open['error'], 'a relative Location against the address');
	pg_assert_same(array('https://mcp.example.com/mcp/', 'https://mcp.example.com/mcp/'), $log, 'the new address is never called');

	$log = array();
	$conn['transport'] = mcp_client_test_fixed_server(307, array('location' => 'http://MCP.example.com/mcp'), '', $log);

	pg_assert_same('The MCP server moved to https://mcp.example.com/mcp. Enter that address instead.', pg_mcp_client_negotiate($conn)['error'], 'http on the same host is named as https');

	$conn['transport'] = mcp_client_test_fixed_server(308, array('location' => 'http://other.example.com/mcp'), '', $log);

	pg_assert_same('The MCP server moved to http://other.example.com/mcp. Enter that address instead.', pg_mcp_client_negotiate($conn)['error'], 'another host is named as it is');

	$conn['transport'] = mcp_client_test_fixed_server(301, array('location' => 'https://mcp.example.com/v2/mcp?api_key=secret'), '', $log);

	pg_assert_same('The MCP server moved to https://mcp.example.com/v2/mcp?…. Enter that address instead.', pg_mcp_client_negotiate($conn)['error'], 'a query is not repeated');

	$conn['transport'] = mcp_client_test_fixed_server(302, array('location' => 'javascript:alert(1)'), '', $log);

	pg_assert_same('The MCP server gave an answer that could not be read (HTTP 302).', pg_mcp_client_negotiate($conn)['error'], 'a Location that is no web address');

	$conn['transport'] = mcp_client_test_fixed_server(307, array(), '', $log);

	pg_assert_same('The MCP server gave an answer that could not be read (HTTP 307).', pg_mcp_client_negotiate($conn)['error'], 'no Location');

	$conn['transport'] = mcp_client_test_fixed_server(307, array('location' => 'https://mcp.example.com/mcp/'), '', $log);

	pg_assert_same('The MCP server gave an answer that could not be read (HTTP 307).', pg_mcp_client_negotiate($conn)['error'], 'a Location that is the same address');
}

function test_mcp_client_an_old_sse_address_names_the_mcp_one()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://mcp.example.com/sse');
	$conn['transport'] = mcp_client_test_fixed_server(405, array('content-type' => 'text/plain'), 'Method Not Allowed', $log);

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_false($open['ok']);
	pg_assert_same(405, $open['status']);
	pg_assert_same('This is an old SSE address. The address that works here usually ends in /mcp: https://mcp.example.com/mcp', $open['error']);

	$conn = pg_mcp_client_connection('https://docs.example.com/v1/sse/?team=7');
	$conn['transport'] = mcp_client_test_fixed_server(410, array('content-type' => 'application/problem+json'), '{"type":"about:blank","title":"This MCP URL has moved to /mcp","status":410}', $log);

	pg_assert_same('This is an old SSE address. The address that works here usually ends in /mcp: https://docs.example.com/v1/mcp?…', pg_mcp_client_negotiate($conn)['error'], 'a trailing slash, a query not repeated');

	$conn['transport'] = mcp_client_test_fixed_server(404, array(), '', $log);

	pg_assert_contains('/mcp', pg_mcp_client_negotiate($conn)['error'], '404 too');

	$conn = pg_mcp_client_connection('https://mcp.example.com/messages');
	$conn['transport'] = mcp_client_test_fixed_server(405, array(), '', $log);

	pg_assert_same('The MCP server gave an answer that could not be read (HTTP 405).', pg_mcp_client_negotiate($conn)['error'], 'another path');

	$conn = pg_mcp_client_connection('https://mcp.example.com/sse');
	$conn['transport'] = mcp_client_test_fixed_server(500, array(), '', $log);

	pg_assert_same('The MCP server had an error (500).', pg_mcp_client_negotiate($conn)['error'], 'another status');
}

function test_mcp_client_reads_a_json_rpc_error_with_any_id_on_a_failed_status()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://mcp.example.com/mcp', array(), '2025-06-18');
	$conn['transport'] = mcp_client_test_fixed_server(400, array('content-type' => 'application/json'), '{"jsonrpc":"2.0","id":"server-error","error":{"code":-32600,"message":"Bad Request: Unsupported protocol version"}}', $log);

	$list = pg_mcp_client_request($conn, 'tools/list', array());

	pg_assert_false($list['ok']);
	pg_assert_same(400, $list['status']);
	pg_assert_same(-32600, $list['code']);
	pg_assert_same('Bad Request: Unsupported protocol version', $list['error'], 'the server\'s reason');

	$ok = pg_mcp_client_read(array('status' => 200, 'headers' => array(), 'body' => '{"jsonrpc":"2.0","id":"other","error":{"code":-32600,"message":"x"}}'), 3);

	pg_assert_same(null, $ok['message'], 'with a 200 another request\'s error is not this one\'s');
}

function test_mcp_client_a_timeout_is_said_plainly()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://slow.example.com/mcp');
	$conn['transport'] = mcp_client_test_fixed_server(0, array(), '', $log, 'Operation timed out after 5001 milliseconds with 184 bytes received', 28);

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_false($open['ok']);
	pg_assert_same(0, $open['status']);
	pg_assert_same('The MCP server took too long to answer.', $open['error']);

	pg_assert_same('The MCP server could not be reached.', pg_mcp_client_transport_text('Failed to connect to slow.example.com port 443', 7));
	pg_assert_same('The address of the MCP server does not resolve.', pg_mcp_client_transport_text('Could not resolve host: slow.example.com', 6));
	pg_assert_same('SSL connect error', pg_mcp_client_transport_text('SSL connect error', 35), 'any other error in curl\'s words');
	pg_assert_same('The address of the MCP server is inside this network and is not called.', pg_mcp_client_transport_text('That address is inside this network and is not called.'), 'a refusal before curl');
}

function test_mcp_client_refuses_a_version_it_does_not_speak()
{
	$conn = pg_mcp_client_connection('https://old.example.com/mcp');
	$conn['transport'] = function ($url, $body) {
		$message = json_decode($body, true);

		if ($message['method'] === 'server/discover') {
			return array('status' => 405, 'headers' => array(), 'body' => '', 'error' => '');
		}

		return array('status' => 200, 'headers' => array(), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $message['id'], 'result' => array('protocolVersion' => '2024-11-05'))), 'error' => '');
	};

	$open = pg_mcp_client_negotiate($conn);

	pg_assert_false($open['ok']);
	pg_assert_contains('2024-11-05', $open['error']);
}

function test_mcp_client_a_background_task_is_not_taken_for_a_result()
{
	$conn = pg_mcp_client_connection('https://slow.example.com/mcp', array(), '2026-07-28');
	$conn['transport'] = function ($url, $body) {
		$message = json_decode($body, true);

		return array('status' => 200, 'headers' => array(), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $message['id'], 'result' => array('resultType' => 'task', 'task' => array('id' => 't1')))), 'error' => '');
	};

	$call = pg_mcp_client_call_tool($conn, 'export', array());

	pg_assert_false($call['ok'], 'the caller did not say it follows tasks');
	pg_assert_same(null, $call['task']);
}

function test_mcp_client_input_asked_for_is_not_taken_for_a_result()
{
	$conn = pg_mcp_client_connection('https://slow.example.com/mcp', array(), '2026-07-28');
	$conn['extensions'] = array(PG_MCP_CLIENT_TASKS);
	$conn['transport'] = function ($url, $body) {
		$message = json_decode($body, true);

		return array('status' => 200, 'headers' => array(), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $message['id'], 'result' => array('resultType' => 'input_required', 'inputRequests' => array()))), 'error' => '');
	};

	$call = pg_mcp_client_call_tool($conn, 'export', array());

	pg_assert_false($call['ok']);
	pg_assert_same(null, $call['task']);
	pg_assert_contains('input', $call['error']);
}

function test_mcp_client_declares_what_it_follows_with_every_request()
{
	$bodies = array();
	$conn = pg_mcp_client_connection('https://agent.example.com/mcp', array(), '2026-07-28');
	$conn['transport'] = function ($url, $body) use (&$bodies) {
		$bodies[] = $body;
		$message = json_decode($body, true);

		return array('status' => 200, 'headers' => array(), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $message['id'], 'result' => array('resultType' => 'complete', 'content' => array()))), 'error' => '');
	};

	pg_mcp_client_call_tool($conn, 'ask', array('message' => 'hi'));

	pg_assert_contains('"io.modelcontextprotocol/clientCapabilities":{}', $bodies[0], 'no extension: an empty object');

	$conn['extensions'] = array(PG_MCP_CLIENT_TASKS);
	pg_mcp_client_call_tool($conn, 'ask', array('message' => 'hi'));

	pg_assert_contains('"io.modelcontextprotocol/clientCapabilities":{"extensions":{"io.modelcontextprotocol/tasks":{}}}', $bodies[1], 'the tasks extension, by its name');

	$legacy = pg_mcp_client_message(1, 'tools/call', array('name' => 'ask'), '2025-11-25', array(PG_MCP_CLIENT_TASKS));

	pg_assert_false(isset($legacy['params']['_meta']), 'the earlier generation declares at the handshake, not per request');
}

// A stand-in agent that answers a call with a task, then tells how the task
// goes: working once, then done. Every request is written to $log.
function mcp_client_test_task_server(&$log)
{
	return function ($url, $body, $headers) use (&$log) {
		$message = json_decode($body, true);
		$log[] = array('message' => $message, 'headers' => $headers);
		$id = $message['id'];
		$asked = count(array_filter($log, function ($entry) {
			return $entry['message']['method'] === 'tasks/get';
		}));
		$task = array('taskId' => 'job/42', 'createdAt' => '2026-10-14T10:00:00Z', 'lastUpdatedAt' => '2026-10-14T10:00:00Z', 'ttlMs' => 600000);

		switch ($message['method']) {
			case 'tools/call':
				$result = array_merge($task, array('resultType' => 'task', 'status' => 'working', 'statusMessage' => "Reading\n  three files", 'pollIntervalMs' => 3000));
				break;

			case 'tasks/get':
				$result = ($asked < 2)
					? array_merge($task, array('resultType' => 'complete', 'status' => 'working', 'statusMessage' => 'Two of three read'))
					: array_merge($task, array('resultType' => 'complete', 'status' => 'completed', 'result' => array('resultType' => 'complete', 'content' => array(array('type' => 'text', 'text' => 'All read.')))));
				break;

			default:
				$result = array('resultType' => 'complete');
		}

		return array('status' => 200, 'headers' => array('content-type' => 'application/json'), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result)), 'error' => '');
	};
}

function test_mcp_client_follows_a_task_to_its_result()
{
	$log = array();
	$conn = pg_mcp_client_connection('https://agent.example.com/mcp', array(), '2026-07-28');
	$conn['extensions'] = array(PG_MCP_CLIENT_TASKS);
	$conn['transport'] = mcp_client_test_task_server($log);

	$call = pg_mcp_client_call_tool($conn, 'ask', array('message' => 'Read the files'));

	pg_assert_true($call['ok'], 'a task is an answer');
	pg_assert_false($call['is_error']);
	pg_assert_same(null, $call['result'], 'the result comes later');
	pg_assert_same('job/42', $call['task']['id']);
	pg_assert_same('working', $call['task']['status']);
	pg_assert_same(3000, $call['task']['poll_ms']);
	pg_assert_same('Reading three files', $call['task']['message'], 'the server\'s words on one line');

	$first = pg_mcp_client_get_task($conn, 'job/42');
	$asked = end($log);

	pg_assert_true($first['ok']);
	pg_assert_same('working', $first['task']['status']);
	pg_assert_same('Two of three read', $first['task']['message']);
	pg_assert_same('tasks/get', mcp_client_test_header($asked['headers'], 'Mcp-Method'));
	pg_assert_same('', mcp_client_test_header($asked['headers'], 'Mcp-Name'), 'only tools/call names a tool');
	pg_assert_same('job/42', $asked['message']['params']['taskId'] ?? '');

	$second = pg_mcp_client_get_task($conn, 'job/42');

	pg_assert_true($second['ok']);
	pg_assert_same('completed', $second['task']['status']);
	pg_assert_same('All read.', pg_mcp_client_result_text($second['task']['result']), 'the result is in the task');

	$stop = pg_mcp_client_cancel_task($conn, 'job/42');
	$asked = end($log);

	pg_assert_true($stop['ok']);
	pg_assert_same('tasks/cancel', $asked['message']['method']);
	pg_assert_same('job/42', $asked['message']['params']['taskId'] ?? '');
}

function test_mcp_client_a_task_answer_about_another_task_is_not_taken()
{
	$conn = pg_mcp_client_connection('https://agent.example.com/mcp', array(), '2026-07-28');
	$conn['transport'] = function ($url, $body) {
		$message = json_decode($body, true);

		return array('status' => 200, 'headers' => array(), 'body' => json_encode(array('jsonrpc' => '2.0', 'id' => $message['id'], 'result' => array('resultType' => 'complete', 'taskId' => 'other', 'status' => 'completed'))), 'error' => '');
	};

	$out = pg_mcp_client_get_task($conn, 'mine');

	pg_assert_false($out['ok']);
	pg_assert_same(null, $out['task']);
}

function test_mcp_client_task_shapes()
{
	pg_assert_same(null, pg_mcp_client_task(array('status' => 'working')), 'no id');
	pg_assert_same(null, pg_mcp_client_task(array('taskId' => 42)), 'an id is a string');
	pg_assert_same(null, pg_mcp_client_task(array('taskId' => "a\nb")), 'no line break in an id');
	pg_assert_same(null, pg_mcp_client_task(array('taskId' => str_repeat('x', 256))), 'an id fits where it is kept');
	pg_assert_same('working', pg_mcp_client_task(array('taskId' => 't', 'status' => 'paused'))['status'], 'a status not known here: still working');
	pg_assert_same(0, pg_mcp_client_task(array('taskId' => 't', 'status' => 'working'))['poll_ms'], 'no interval given');

	$failed = pg_mcp_client_task(array('taskId' => 't', 'status' => 'failed', 'error' => array('code' => -32603, 'message' => "Disk\nfull")));

	pg_assert_same('Disk full', $failed['error']);

	$done = pg_mcp_client_task(array('taskId' => 't', 'status' => 'completed'));

	pg_assert_same(null, $done['result'], 'completed without a result');
	pg_assert_same('', $done['error']);
}

function test_mcp_client_tool_names_and_schemas()
{
	pg_assert_same(null, pg_mcp_client_tool_normalise(array('name' => 'has space'), '{}'));
	pg_assert_same(null, pg_mcp_client_tool_normalise(array('name' => ''), '{}'));
	pg_assert_same('github/search_code', pg_mcp_client_tool_normalise(array('name' => 'github/search_code'), '{"type":"object"}')['name']);
	pg_assert_same('{"type":"object","properties":{}}', pg_mcp_client_tool_normalise(array('name' => 'a'), '{"type":"string"}')['schema'], 'a schema that is not an object');
	pg_assert_same('{"type":"object","properties":{}}', pg_mcp_client_tool_normalise(array('name' => 'a'), 'not json')['schema']);
	pg_assert_same('Read only', pg_mcp_client_tool_normalise(array('name' => 'a', 'annotations' => array('title' => "Read\n only")), '{}')['title'], 'title from the hints, on one line');

	$quoted = pg_mcp_client_tool_normalise(array('name' => 'a', 'annotations' => array('readOnlyHint' => 'true')), '{}');
	pg_assert_false($quoted['read_only'], 'a hint is only a boolean');

	$safe = pg_mcp_client_tool_normalise(array('name' => 'a', 'annotations' => array('destructiveHint' => false)), '{}');
	pg_assert_false($safe['destructive'], 'writes but destroys nothing');
	pg_assert_false($safe['read_only']);
}

function test_mcp_client_result_text()
{
	$result = array(
		'content' => array(
			array('type' => 'text', 'text' => 'First'),
			array('type' => 'image', 'data' => 'AAAA', 'mimeType' => 'image/png'),
			array('type' => 'resource', 'resource' => array('uri' => 'file:///a.txt', 'text' => 'Inside')),
			array('type' => 'resource_link', 'uri' => 'https://example.com/x', 'name' => 'X'),
		),
	);

	pg_assert_same("First\n\n[image image/png]\n\nInside\n\nX <https://example.com/x>", pg_mcp_client_result_text($result));
	pg_assert_same("{\n    \"total\": 3\n}", pg_mcp_client_result_text(array('content' => array(), 'structuredContent' => array('total' => 3))), 'structured content when nothing else');
	pg_assert_same('abcd…', pg_mcp_client_result_text(array('content' => array(array('type' => 'text', 'text' => 'abcdefgh'))), 5), 'cut to the limit');
}

function test_mcp_client_session_ids()
{
	pg_assert_true(pg_mcp_client_session_is_valid('1868a90c-3d3f-4d7b'));
	pg_assert_false(pg_mcp_client_session_is_valid("a\r\nX-Injected: 1"), 'no line breaks into a header');
	pg_assert_false(pg_mcp_client_session_is_valid('a b'));
	pg_assert_false(pg_mcp_client_session_is_valid(''));
}

function test_mcp_client_headers_strip_line_breaks()
{
	$conn = pg_mcp_client_connection('https://a.example.com/mcp', array("X-Key: a\r\nX-Other: b", 'not a header'));
	$headers = pg_mcp_client_headers($conn, 'tools/list', array(), '2025-06-18');

	pg_assert_true(in_array('X-Key: aX-Other: b', $headers, true), 'a credential cannot add a header');
	pg_assert_false(in_array('not a header', $headers, true));
}

// -- signing in (the MCP authorization specification) --------------------------

function test_mcp_client_bearer_challenge_params()
{
	$params = pg_mcp_client_bearer_challenge('Bearer resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource/mcp", scope="repo read:org"');

	pg_assert_same('https://mcp.example.com/.well-known/oauth-protected-resource/mcp', $params['resource_metadata'], 'resource_metadata');
	pg_assert_same('repo read:org', $params['scope'], 'scope');

	$second = pg_mcp_client_bearer_challenge('Basic realm="files", Bearer error="invalid_token", scope=files.read, error_description="say \"again\""');

	pg_assert_same('invalid_token', $second['error'], 'the Bearer challenge after another one');
	pg_assert_same('files.read', $second['scope'], 'a token value');
	pg_assert_same('say "again"', $second['error_description'], 'an escaped quote');
	pg_assert_false(isset($second['realm']), 'the Basic challenge\'s realm is not Bearer\'s');

	$before = pg_mcp_client_bearer_challenge('Bearer realm="api", Basic realm="other"');

	pg_assert_same(array('realm' => 'api'), $before, 'stops at the next challenge');
	pg_assert_same(array(), pg_mcp_client_bearer_challenge('Basic realm="x"'), 'no Bearer challenge');
	pg_assert_same(array(), pg_mcp_client_bearer_challenge(''), 'no header');
	pg_assert_same(array(), pg_mcp_client_bearer_challenge('Bearer'), 'Bearer with nothing after it');
}

function test_mcp_client_auth_addresses()
{
	pg_assert_same('https://mcp.example.com', pg_mcp_client_origin('HTTPS://MCP.Example.com:443/mcp'), 'origin without the default port');
	pg_assert_same('https://mcp.example.com:8443', pg_mcp_client_origin('https://mcp.example.com:8443/mcp'), 'origin with another port');
	pg_assert_same('', pg_mcp_client_origin('not an address'), 'no origin');

	pg_assert_same('https://mcp.example.com/mcp', pg_mcp_client_canonical_resource('https://MCP.example.com/mcp/'), 'canonical: lower-case host, no trailing slash');
	pg_assert_same('https://mcp.example.com', pg_mcp_client_canonical_resource('https://mcp.example.com/'), 'canonical: the root');
	pg_assert_same('https://mcp.example.com/mcp?team=7', pg_mcp_client_canonical_resource('https://mcp.example.com/mcp?team=7#x'), 'canonical: the query kept, the fragment dropped');

	pg_assert_same(array(
		'https://mcp.example.com/.well-known/oauth-protected-resource/v1/mcp',
		'https://mcp.example.com/.well-known/oauth-protected-resource'
	), pg_mcp_client_resource_metadata_urls('https://mcp.example.com/v1/mcp/'), 'the path form first, then the root');

	pg_assert_same(array(
		'https://auth.example.com/prm',
		'https://mcp.example.com/.well-known/oauth-protected-resource'
	), pg_mcp_client_resource_metadata_urls('https://mcp.example.com/', 'https://auth.example.com/prm'), 'the challenge\'s address first');

	pg_assert_same(array('https://mcp.example.com/.well-known/oauth-protected-resource'), pg_mcp_client_resource_metadata_urls('https://mcp.example.com', 'http://inside/prm'), 'an http address in a challenge is not followed');

	pg_assert_same(array(
		'https://github.com/.well-known/oauth-authorization-server/login/oauth',
		'https://github.com/.well-known/openid-configuration/login/oauth',
		'https://github.com/login/oauth/.well-known/openid-configuration'
	), pg_mcp_client_auth_server_metadata_urls('https://github.com/login/oauth'), 'an issuer with a path');

	pg_assert_same(array(
		'https://auth.example.com/.well-known/oauth-authorization-server',
		'https://auth.example.com/.well-known/openid-configuration'
	), pg_mcp_client_auth_server_metadata_urls('https://auth.example.com/'), 'an issuer at the root');

	pg_assert_true(pg_mcp_client_resource_covers('https://mcp.example.com/mcp', 'https://mcp.example.com/mcp/'), 'the same resource');
	pg_assert_true(pg_mcp_client_resource_covers('https://mcp.example.com/', 'https://mcp.example.com/mcp'), 'the server is under the resource');
	pg_assert_false(pg_mcp_client_resource_covers('https://mcp.example.com/mcp', 'https://mcp.example.com/mcpx'), 'a sibling path is not under it');
	pg_assert_false(pg_mcp_client_resource_covers('https://other.example.com/mcp', 'https://mcp.example.com/mcp'), 'another host');
	pg_assert_false(pg_mcp_client_resource_covers('https://mcp.example.com/mcp/v2', 'https://mcp.example.com/mcp'), 'a resource deeper than the server');
}

// A stand-in authorization server's metadata.
function mcp_client_test_as_metadata($issuer, $changes = array())
{
	return array_merge(array(
		'issuer'                                         => $issuer,
		'authorization_endpoint'                         => $issuer . '/authorize',
		'token_endpoint'                                 => $issuer . '/token',
		'revocation_endpoint'                            => $issuer . '/revoke',
		'registration_endpoint'                          => $issuer . '/register',
		'code_challenge_methods_supported'               => array('S256'),
		'token_endpoint_auth_methods_supported'          => array('none', 'client_secret_post'),
		'client_id_metadata_document_supported'          => true,
		'authorization_response_iss_parameter_supported' => true,
		'scopes_supported'                               => array('read', 'write')
	), $changes);
}

function test_mcp_client_auth_server_metadata_is_checked()
{
	$read = pg_mcp_client_auth_server_read(mcp_client_test_as_metadata('https://auth.example.com'), 'https://auth.example.com/');

	pg_assert_true($read['ok'], 'a trailing slash aside, the same issuer');
	pg_assert_same('https://auth.example.com/authorize', $read['authorize_url']);
	pg_assert_same('https://auth.example.com/token', $read['token_url']);
	pg_assert_same('https://auth.example.com/revoke', $read['revoke_url']);
	pg_assert_same('https://auth.example.com/register', $read['registration_url']);
	pg_assert_true($read['cimd'], 'reads client ID metadata documents');
	pg_assert_true($read['iss_param'], 'names itself on the redirect');
	pg_assert_same(array('none', 'client_secret_post'), $read['auth_methods']);

	pg_assert_false(pg_mcp_client_auth_server_read(mcp_client_test_as_metadata('https://evil.example.com'), 'https://auth.example.com')['ok'], 'another issuer');
	pg_assert_false(pg_mcp_client_auth_server_read(mcp_client_test_as_metadata('https://auth.example.com', array('code_challenge_methods_supported' => array('plain'))), 'https://auth.example.com')['ok'], 'no S256');
	pg_assert_false(pg_mcp_client_auth_server_read(mcp_client_test_as_metadata('https://auth.example.com', array('code_challenge_methods_supported' => null)), 'https://auth.example.com')['ok'], 'PKCE not said');
	pg_assert_false(pg_mcp_client_auth_server_read(mcp_client_test_as_metadata('https://auth.example.com', array('token_endpoint' => 'http://auth.example.com/token')), 'https://auth.example.com')['ok'], 'an http token endpoint');
	pg_assert_false(pg_mcp_client_auth_server_read(mcp_client_test_as_metadata('https://auth.example.com', array('authorization_endpoint' => 'javascript:alert(1)')), 'https://auth.example.com')['ok'], 'not an address');

	$bare = pg_mcp_client_auth_server_read(mcp_client_test_as_metadata('https://auth.example.com', array(
		'revocation_endpoint' => 'http://auth.example.com/revoke', 'registration_endpoint' => null, 'client_id_metadata_document_supported' => 'yes')), 'https://auth.example.com');

	pg_assert_true($bare['ok'], 'the optional parts may be missing');
	pg_assert_same('', $bare['revoke_url'], 'an unusable revocation address is left out');
	pg_assert_same('', $bare['registration_url'], 'no registration');
	pg_assert_false($bare['cimd'], 'only true says yes');
}

// A stand-in network for discovery: answers by method and address, and
// writes every request to $log.
function mcp_client_test_fetch($answers, &$log)
{
	return function ($method, $url, $body, $headers) use ($answers, &$log) {
		$log[] = $method . ' ' . $url;

		if (isset($answers[$method . ' ' . $url])) {
			$answer = $answers[$method . ' ' . $url];

			return array(
				'status'  => $answer[0],
				'headers' => isset($answer[2]) ? $answer[2] : array('content-type' => 'application/json'),
				'body'    => is_array($answer[1]) ? json_encode($answer[1]) : (string)$answer[1],
				'error'   => ''
			);
		}

		return array('status' => 404, 'headers' => array(), 'body' => '', 'error' => '');
	};
}

function test_mcp_client_discovers_how_to_sign_in()
{
	$log = array();

	$fetch = mcp_client_test_fetch(array(
		'POST https://mcp.example.com/mcp' => array(401, '', array('www-authenticate' => 'Bearer resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource/mcp", scope="repo"')),
		'GET https://mcp.example.com/.well-known/oauth-protected-resource/mcp' => array(200, array(
			'resource'              => 'https://mcp.example.com/mcp',
			'authorization_servers' => array('https://auth.example.com/tenant'),
			'scopes_supported'      => array('repo', 'gist')
		)),
		'GET https://auth.example.com/.well-known/openid-configuration/tenant' => array(200, mcp_client_test_as_metadata('https://auth.example.com/tenant'))
	), $log);

	$found = pg_mcp_client_discover_auth('https://mcp.example.com/mcp', $fetch);

	pg_assert_true($found['ok'], 'found');
	pg_assert_true($found['challenged'], 'the server asked for a sign-in');
	pg_assert_same('https://mcp.example.com/mcp', $found['resource'], 'the resource its metadata names');
	pg_assert_same('https://auth.example.com/tenant', $found['issuer'], 'the issuer');
	pg_assert_same('https://auth.example.com/tenant/token', $found['token_url'], 'the token endpoint');
	pg_assert_same(array('repo'), $found['scopes'], 'the challenge\'s scope wins over scopes_supported');
	pg_assert_same(array(
		'POST https://mcp.example.com/mcp',
		'GET https://mcp.example.com/.well-known/oauth-protected-resource/mcp',
		'GET https://auth.example.com/.well-known/oauth-authorization-server/tenant',
		'GET https://auth.example.com/.well-known/openid-configuration/tenant'
	), $log, 'asked in the order of the specification');
}

function test_mcp_client_discovery_without_resource_metadata()
{
	// A server of the first authorization specification: no resource
	// metadata; its own origin is the authorization server.
	$log = array();

	$fetch = mcp_client_test_fetch(array(
		'POST https://mcp.example.com/v1/mcp' => array(401, '', array()),
		'GET https://mcp.example.com/.well-known/oauth-authorization-server' => array(200, mcp_client_test_as_metadata('https://mcp.example.com'))
	), $log);

	$found = pg_mcp_client_discover_auth('https://mcp.example.com/v1/mcp/', $fetch);

	pg_assert_true($found['ok'], 'found at the origin');
	pg_assert_same('https://mcp.example.com/v1/mcp', $found['resource'], 'the canonical address is the resource');
	pg_assert_same(array(), $found['scopes'], 'no scope asked for');

	// Metadata about another resource is not followed.
	$fetch = mcp_client_test_fetch(array(
		'POST https://mcp.example.com/mcp' => array(401, '', array()),
		'GET https://mcp.example.com/.well-known/oauth-protected-resource/mcp' => array(200, array(
			'resource' => 'https://other.example.com/mcp', 'authorization_servers' => array('https://auth.example.com')))
	), $log);

	$other = pg_mcp_client_discover_auth('https://mcp.example.com/mcp', $fetch);

	pg_assert_false($other['ok'], 'another resource');
	pg_assert_true($other['error'] !== '', 'says why');

	// A server that takes requests without a sign-in and names no
	// authorization server.
	$fetch = mcp_client_test_fetch(array(
		'POST https://open.example.com/mcp' => array(200, array('jsonrpc' => '2.0', 'id' => 1, 'result' => array()))
	), $log);

	$open = pg_mcp_client_discover_auth('https://open.example.com/mcp', $fetch);

	pg_assert_false($open['ok'], 'nothing to sign in to');
	pg_assert_same('The server does not ask for a sign-in. Choose another way to sign in, or none.', $open['error']);

	pg_assert_false(pg_mcp_client_discover_auth('http://mcp.example.com/mcp', $fetch)['ok'], 'not https');
}

function test_mcp_client_registers_as_a_public_client()
{
	$metadata = pg_mcp_client_client_metadata('https://site.example/pinegrap/connect.php', 'https://site.example/');

	pg_assert_false(isset($metadata['client_id']), 'a registration names no client_id');
	pg_assert_same(array('https://site.example/pinegrap/connect.php'), $metadata['redirect_uris']);
	pg_assert_same('none', $metadata['token_endpoint_auth_method'], 'no secret');
	pg_assert_same(array('authorization_code', 'refresh_token'), $metadata['grant_types']);

	$document = pg_mcp_client_client_metadata('https://site.example/pinegrap/connect.php', 'https://site.example/', 'https://site.example/pinegrap/connect_client.php');

	pg_assert_same('https://site.example/pinegrap/connect_client.php', $document['client_id'], 'the document names its own address');

	$sent = null;

	$fetch = function ($method, $url, $body, $headers) use (&$sent) {
		$sent = array('method' => $method, 'url' => $url, 'body' => json_decode($body, true));

		return array('status' => 201, 'headers' => array(), 'body' => json_encode(array('client_id' => 'abc123', 'token_endpoint_auth_method' => 'none')), 'error' => '');
	};

	$registered = pg_mcp_client_register('https://auth.example.com/register', $metadata, array('repo'), $fetch);

	pg_assert_true($registered['ok'], 'registered');
	pg_assert_same('abc123', $registered['client_id']);
	pg_assert_same('', $registered['client_secret'], 'no secret');
	pg_assert_same('none', $registered['auth_method']);
	pg_assert_same('POST', $sent['method']);
	pg_assert_same('repo', $sent['body']['scope'], 'the scope asked for');
	pg_assert_same(array('https://site.example/pinegrap/connect.php'), $sent['body']['redirect_uris'], 'the redirect address');

	$secret = pg_mcp_client_register('https://auth.example.com/register', $metadata, array(), function () {
		return array('status' => 200, 'headers' => array(), 'body' => json_encode(array('client_id' => 'c', 'client_secret' => 's')), 'error' => '');
	});

	pg_assert_same('s', $secret['client_secret'], 'a secret handed out anyway');
	pg_assert_same('client_secret_basic', $secret['auth_method'], 'sent as RFC 7591 says by default');

	$refused = pg_mcp_client_register('https://auth.example.com/register', $metadata, array(), function () {
		return array('status' => 400, 'headers' => array(), 'body' => json_encode(array('error' => 'invalid_redirect_uri', 'error_description' => 'not allowed')), 'error' => '');
	});

	pg_assert_false($refused['ok'], 'refused');
	pg_assert_contains('invalid_redirect_uri: not allowed', $refused['error'], 'with the server\'s reason');
}
