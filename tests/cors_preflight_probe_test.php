<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * System Status sends one OPTIONS request to the site's own mcp.php and reads
 * whether the answer carries Access-Control-Allow-Origin. IIS answers a
 * preflight itself when its PHP handler mapping lacks the OPTIONS verb: a 200
 * without CORS headers, which browser-based MCP and OAuth clients cannot get
 * past. These tests pin how an answer is read and the address that is asked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cors_preflight_probe_answer($status, $headers, $errno = 0)
{
	return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => '', 'errno' => $errno, 'error' => '', 'headers' => $headers);
}

function test_cors_preflight_probe_iis_answer_is_missing()
{
	// The answer reported from IIS 10: its own OPTIONS handler, no CORS header.
	$answer = cors_preflight_probe_answer(200, "HTTP/1.1 200 OK\r\nAllow: OPTIONS, TRACE, GET, HEAD, POST\r\nServer: Microsoft-IIS/10.0\r\n\r\n");

	pg_assert_same('missing', pg_cors_preflight_verdict($answer));
}

function test_cors_preflight_probe_php_answer_is_ok()
{
	$answer = cors_preflight_probe_answer(204, "HTTP/1.1 204 No Content\r\naccess-control-allow-origin: *\r\nAccess-Control-Allow-Methods: POST, OPTIONS\r\n\r\n");

	pg_assert_same('ok', pg_cors_preflight_verdict($answer));
}

function test_cors_preflight_probe_header_after_proxy_block_is_ok()
{
	// Behind a proxy the header block starts with the tunnel's own answer.
	$answer = cors_preflight_probe_answer(204, "HTTP/1.1 200 Connection established\r\n\r\nHTTP/2 204\r\nAccess-Control-Allow-Origin: *\r\n\r\n");

	pg_assert_same('ok', pg_cors_preflight_verdict($answer));
}

function test_cors_preflight_probe_no_answer_is_unknown()
{
	// Loopback blocked, a timeout, a refused connection: nothing is shown.
	pg_assert_same('unknown', pg_cors_preflight_verdict(cors_preflight_probe_answer(0, '', 28)));
	pg_assert_same('unknown', pg_cors_preflight_verdict(cors_preflight_probe_answer(0, '')));
	pg_assert_same('unknown', pg_cors_preflight_verdict(array()));
	pg_assert_same('unknown', pg_cors_preflight_verdict(null));
}

function test_cors_preflight_probe_error_status_is_unknown()
{
	// A firewall's 403 or a maintenance page is not the IIS handler fault, so
	// naming the handler change there would send the operator the wrong way.
	pg_assert_same('unknown', pg_cors_preflight_verdict(cors_preflight_probe_answer(403, "HTTP/1.1 403 Forbidden\r\n\r\n")));
	pg_assert_same('unknown', pg_cors_preflight_verdict(cors_preflight_probe_answer(503, "HTTP/1.1 503 Service Unavailable\r\n\r\n")));
	pg_assert_same('unknown', pg_cors_preflight_verdict(cors_preflight_probe_answer(301, "HTTP/1.1 301 Moved Permanently\r\nLocation: https://x/\r\n\r\n")));
}

function test_cors_preflight_probe_header_name_must_be_whole()
{
	// Another Access-Control header alone does not answer the browser.
	$answer = cors_preflight_probe_answer(200, "HTTP/1.1 200 OK\r\nAccess-Control-Allow-Origin-Extra: *\r\nX-Access-Control-Allow-Origin: *\r\n\r\n");

	pg_assert_same('missing', pg_cors_preflight_verdict($answer));
}

function test_cors_preflight_probe_url()
{
	pg_assert_same('https://example.com/pinegrap/mcp.php', pg_cors_preflight_url('https://', 'example.com', '/', 'pinegrap'));
	pg_assert_same('http://example.com/site/pinegrap/mcp.php', pg_cors_preflight_url('http://', 'example.com', '/site/', 'pinegrap'));
	pg_assert_same('', pg_cors_preflight_url('https://', '', '/', 'pinegrap'));
}
