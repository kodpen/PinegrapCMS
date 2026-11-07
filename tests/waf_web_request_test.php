<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which runs the firewall judges (waf_is_web_request() in
 * waf.php): every request reached over the web, with or without a Host
 * header, and never a command-line run or one without a remote address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// A request without a Host header (HTTP/1.0) is still judged: the server
// answers it from its default site.
function test_waf_is_web_request_without_host()
{
	pg_assert_true(waf_is_web_request(array('REMOTE_ADDR' => '203.0.113.9'), 'fpm-fcgi'), 'no Host header');
	pg_assert_true(waf_is_web_request(array('REMOTE_ADDR' => '203.0.113.9', 'HTTP_HOST' => ''), 'apache2handler'), 'an empty Host header');
	pg_assert_true(waf_is_web_request(array('REMOTE_ADDR' => '2001:db8::1', 'HTTP_HOST' => 'example.com'), 'cgi-fcgi'), 'with a Host header');
}

// A scheduled task is not judged.
function test_waf_is_web_request_not_cli()
{
	pg_assert_false(waf_is_web_request(array('REMOTE_ADDR' => '203.0.113.9', 'HTTP_HOST' => 'example.com'), 'cli'), 'the command line');
	pg_assert_false(waf_is_web_request(array(), 'fpm-fcgi'), 'no remote address');
	pg_assert_false(waf_is_web_request(array('REMOTE_ADDR' => '', 'HTTP_HOST' => 'example.com'), 'cgi-fcgi'), 'an empty remote address');
}
