<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the MCP client (includes/mcp/client.php): reading answers sent
 * as JSON or as an event stream, the handshake with a server of either
 * generation, a forgotten session, paged tool lists and what a tool's result
 * becomes as text. The servers are stand-ins passed as the connection's
 * transport; nothing goes over the network.
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

	pg_assert_false($call['ok']);
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
