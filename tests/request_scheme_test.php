<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one scheme helper (includes/fn/request_scheme.php):
 * pg_forwarded_https() reads the forwarded-protocol headers a proxy in front
 * of this server may set, and pg_https_peer_trusted() says whether the peer
 * that sent them is one whose word on the scheme counts (the config
 * constant, a Trusted Proxies entry or a Cloudflare edge address).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each recognised header on its own says https; one is enough.
function test_request_scheme_forwarded_header_matrix()
{
	$says_https = array(
		'X-Forwarded-Proto' => array('HTTP_X_FORWARDED_PROTO' => 'https'),
		'X-Forwarded-Proto case and spaces' => array('HTTP_X_FORWARDED_PROTO' => ' HTTPS '),
		'Forwarded' => array('HTTP_FORWARDED' => 'for=192.0.2.1;proto=https'),
		'Forwarded quoted' => array('HTTP_FORWARDED' => 'for="[2001:db8::1]";proto="https";by=203.0.113.43'),
		'X-Forwarded-Ssl' => array('HTTP_X_FORWARDED_SSL' => 'on'),
		'X-Forwarded-Ssl case' => array('HTTP_X_FORWARDED_SSL' => 'On'),
		'Front-End-Https' => array('HTTP_FRONT_END_HTTPS' => 'on'),
		'X-ARR-SSL' => array('HTTP_X_ARR_SSL' => '2048|256|C=US, O=Example CA|CN=www.example.com'),
		'CF-Visitor' => array('HTTP_CF_VISITOR' => '{"scheme":"https"}'),
		'X-Forwarded-Port' => array('HTTP_X_FORWARDED_PORT' => '443'),
	);

	foreach ($says_https as $name => $server) {
		pg_assert_true(pg_forwarded_https($server), $name);
	}

	$says_nothing = array(
		'no header' => array(),
		'direct https only' => array('HTTPS' => 'on', 'SERVER_PORT' => '443'),
		'X-Forwarded-Proto http' => array('HTTP_X_FORWARDED_PROTO' => 'http'),
		'X-Forwarded-Proto other' => array('HTTP_X_FORWARDED_PROTO' => 'httpsx'),
		'X-Forwarded-Proto empty' => array('HTTP_X_FORWARDED_PROTO' => ''),
		'Forwarded http' => array('HTTP_FORWARDED' => 'for=192.0.2.1;proto=http'),
		'Forwarded without proto' => array('HTTP_FORWARDED' => 'for=192.0.2.1'),
		'X-Forwarded-Ssl off' => array('HTTP_X_FORWARDED_SSL' => 'off'),
		'Front-End-Https off' => array('HTTP_FRONT_END_HTTPS' => 'off'),
		'X-ARR-SSL empty' => array('HTTP_X_ARR_SSL' => ''),
		'CF-Visitor http' => array('HTTP_CF_VISITOR' => '{"scheme":"http"}'),
		'X-Forwarded-Port other' => array('HTTP_X_FORWARDED_PORT' => '8443'),
		'not a string' => array('HTTP_X_FORWARDED_PROTO' => array('https')),
	);

	foreach ($says_nothing as $name => $server) {
		pg_assert_false(pg_forwarded_https($server), $name);
	}
}

// A chain of proxies: X-Forwarded-Proto lists one value per hop and the
// left-most is the visitor's. An inner proxy may rewrite the header to http
// while another header still carries the visitor's https, and that request
// stays https.
function test_request_scheme_forwarded_chain()
{
	pg_assert_true(pg_forwarded_https(array('HTTP_X_FORWARDED_PROTO' => 'https, http')), 'visitor https, inner hop http');
	pg_assert_false(pg_forwarded_https(array('HTTP_X_FORWARDED_PROTO' => 'http, https')), 'visitor http, inner hop https');
	pg_assert_true(pg_forwarded_https(array('HTTP_X_FORWARDED_PROTO' => 'http', 'HTTP_CF_VISITOR' => '{"scheme":"https"}')), 'Cloudflare Flexible to nginx on port 80');
	pg_assert_true(pg_forwarded_https(array('HTTP_X_FORWARDED_PROTO' => 'http', 'HTTP_FORWARDED' => 'proto=https')), 'Forwarded kept');
}

// Whose word counts: the config constant from any peer, a peer in the
// Trusted Proxies list, a Cloudflare edge address; nobody else, loopback
// included, since shared hosts and container networks share those addresses.
function test_request_scheme_peer_trust_matrix()
{
	$list = "10.0.0.0/8, 192.0.2.5 true-client-ip\n2001:db8::/32 x-forwarded-for";

	pg_assert_true(pg_https_peer_trusted('203.0.113.9', '', true), 'constant');
	pg_assert_true(pg_https_peer_trusted('', '', true), 'constant without a peer');

	pg_assert_true(pg_https_peer_trusted('10.20.30.40', $list, false), 'listed range');
	pg_assert_true(pg_https_peer_trusted('192.0.2.5', $list, false), 'listed address with a header name');
	pg_assert_true(pg_https_peer_trusted('2001:db8:1::7', $list, false), 'listed IPv6 range');
	pg_assert_true(pg_https_peer_trusted('::ffff:10.20.30.40', $list, false), 'listed range, mapped peer');

	pg_assert_true(pg_https_peer_trusted('104.16.1.1', '', false), 'Cloudflare edge');
	pg_assert_true(pg_https_peer_trusted('2606:4700::1', '', false), 'Cloudflare edge IPv6');

	pg_assert_false(pg_https_peer_trusted('203.0.113.9', $list, false), 'unlisted');
	pg_assert_false(pg_https_peer_trusted('127.0.0.1', $list, false), 'loopback not listed');
	pg_assert_false(pg_https_peer_trusted('::1', $list, false), 'loopback IPv6 not listed');
	pg_assert_false(pg_https_peer_trusted('127.0.0.1', '', false), 'loopback, empty list');
	pg_assert_false(pg_https_peer_trusted('', $list, false), 'no peer');
	pg_assert_false(pg_https_peer_trusted('not-an-address', $list, false), 'not an address');

	pg_assert_true(pg_https_peer_trusted('127.0.0.1', '127.0.0.1, ::1', false), 'loopback listed');
	pg_assert_true(pg_https_peer_trusted('::1', '127.0.0.1, ::1', false), 'loopback IPv6 listed');
}

// Without the third argument the constant decides, and it is not defined
// here: the list alone is read.
function test_request_scheme_peer_trust_reads_constant()
{
	pg_assert_same(defined('TRUST_PROXY_SSL_HEADERS') && TRUST_PROXY_SSL_HEADERS === true, pg_https_peer_trusted('203.0.113.9', ''));
	pg_assert_true(pg_https_peer_trusted('10.1.1.1', '10.0.0.0/8'));
}
