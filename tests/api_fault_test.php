<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the answer to a fault that escapes an external API handler
 * (includes/api/fault.php): when the shutdown function answers, and that the
 * answer is the envelope with the request id and never PHP's message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/fault.php');

// Only an error that ended the request is a fault; the last warning is not.
function test_api_fault_is_fatal_tells_fatal_errors()
{
	foreach (array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR) as $type) {
		pg_assert_true(api_fault_is_fatal(array('type' => $type, 'message' => 'x')), 'type ' . $type);
	}

	foreach (array(E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING, E_USER_NOTICE) as $type) {
		pg_assert_false(api_fault_is_fatal(array('type' => $type, 'message' => 'x')), 'type ' . $type);
	}

	pg_assert_false(api_fault_is_fatal(null), 'no error at all');
	pg_assert_false(api_fault_is_fatal(array()), 'no type');
}

// The shutdown function answers a fatal error only while nothing has gone out.
function test_api_fault_can_answer_only_before_headers()
{
	$fatal = array('type' => E_ERROR, 'message' => 'Allowed memory size exhausted');

	pg_assert_true(api_fault_can_answer($fatal, false), 'fatal, nothing sent');
	pg_assert_false(api_fault_can_answer($fatal, true), 'fatal, headers already sent');
	pg_assert_false(api_fault_can_answer(array('type' => E_WARNING, 'message' => 'x'), false), 'a warning is not answered');
	pg_assert_false(api_fault_can_answer(null, false), 'a request that ended well');
}

// The REST answer is the envelope of every other failure: code, sentence,
// request id; nothing else, so no file, line or PHP text can ride along.
function test_api_fault_body_is_the_envelope()
{
	$body = api_fault_body('abc123', 'A system error occurred.');

	pg_assert_same(array('error' => array(
		'code'       => 'server_error',
		'message'    => 'A system error occurred.',
		'request_id' => 'abc123'
	)), $body, 'envelope');
}

// The message is the generic sentence: whatever PHP said stays out.
function test_api_fault_message_is_generic()
{
	$message = api_fault_message();

	pg_assert_true($message !== '', 'a sentence');
	pg_assert_false(strpos($message, 'Call to undefined function'), 'no PHP text');

	$json = json_encode(api_fault_body('r1', $message));

	pg_assert_false(strpos($json, '.php'), 'no file name');
}

// The MCP answer outside a route is a JSON-RPC internal error with the
// request id in its data.
function test_api_fault_mcp_body_is_an_internal_error()
{
	pg_assert_same(array(
		'jsonrpc' => '2.0',
		'id'      => null,
		'error'   => array(
			'code'    => -32603,
			'message' => 'Internal error',
			'data'    => array('request_id' => 'r9')
		)
	), api_fault_mcp_body('r9'), 'JSON-RPC error');
}
