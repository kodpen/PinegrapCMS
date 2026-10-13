<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the OAuth rules that need no database (includes/oauth/rules.php):
 * which redirect addresses a client may register, how a presented one is
 * matched, PKCE, the scope string and resource indicators.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_OAUTH_ENTRY')) {
	define('PG_OAUTH_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/oauth/rules.php');

// https anywhere, http only on the loopback, an app scheme only when it is a
// reversed domain name.
function test_oauth_redirect_registration_accepts_the_three_kinds()
{
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('https://claude.ai/api/mcp/auth_callback'), 'https');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('http://127.0.0.1:33418/callback'), 'IPv4 loopback');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('http://[::1]/cb'), 'IPv6 loopback');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('http://localhost:6274/oauth/callback'), 'localhost');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('com.example.app:/oauth2redirect'), 'private-use scheme');
}

function test_oauth_redirect_registration_refuses_the_rest()
{
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('http://example.com/cb'), 'plain http to another host');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('https://example.com/cb#frag'), 'fragment');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('https://site.com@evil.com/cb'), 'user name in front of the host');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('javascript:alert(1)'), 'javascript');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('data:text/html,x'), 'data');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('myapp:/cb'), 'scheme without a dot');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('/relative/cb'), 'relative');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable("https://example.com/c\nb"), 'control character');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable(array('https://example.com/cb')), 'not a string');
}

// Exact match, except the port of a loopback address.
function test_oauth_redirect_matching_is_exact()
{
	$registered = array('https://claude.ai/api/mcp/auth_callback', 'http://127.0.0.1/callback');

	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai/api/mcp/auth_callback'), 'same string');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai/api/mcp/auth_callback/'), 'trailing slash');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai/api/mcp/auth_callback?x=1'), 'extra query');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai:8443/api/mcp/auth_callback'), 'port on https');
	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1:51234/callback'), 'loopback with a port of its own');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1:51234/other'), 'loopback, other path');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'http://localhost:51234/callback'), 'loopback, other name');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, ''), 'empty');
}

// RFC 7636 appendix B.
function test_oauth_pkce_matches_the_rfc_example()
{
	$verifier  = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
	$challenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

	pg_assert_true(pg_oauth_challenge_is_valid($challenge), 'challenge shape');
	pg_assert_true(pg_oauth_pkce_verify($verifier, $challenge), 'verifier matches');
	pg_assert_false(pg_oauth_pkce_verify($verifier . 'x', $challenge), 'other verifier');
	pg_assert_false(pg_oauth_pkce_verify('short', $challenge), 'verifier too short');
	pg_assert_false(pg_oauth_pkce_verify($verifier, $verifier), 'plain method is not accepted');
	pg_assert_false(pg_oauth_challenge_is_valid('abc'), 'short challenge');
}

function test_oauth_scope_parse_and_string()
{
	pg_assert_same(array('products:read', 'orders:write'), pg_oauth_scope_parse('  Products:Read  orders:write products:read '));
	pg_assert_same(array(), pg_oauth_scope_parse(''));
	pg_assert_same(array(), pg_oauth_scope_parse(array('products:read')));
	pg_assert_same('products:read orders:write', pg_oauth_scope_string(array('products:read', 'orders:write')));
}

// The answer is added to the query string the address was registered with.
function test_oauth_redirect_with_keeps_the_query()
{
	pg_assert_same('https://a.example/cb?code=x&state=a%20b', pg_oauth_redirect_with('https://a.example/cb', array('code' => 'x', 'state' => 'a b')));
	pg_assert_same('https://a.example/cb?tenant=1&code=x', pg_oauth_redirect_with('https://a.example/cb?tenant=1', array('code' => 'x')));
}

function test_oauth_resource_normalise()
{
	pg_assert_same('https://site.com/pinegrap/integration.php', pg_oauth_resource_normalise(' https://site.com/pinegrap/integration.php/ '));
	pg_assert_same('', pg_oauth_resource_normalise('https://site.com/x#frag'), 'fragment');
	pg_assert_same('', pg_oauth_resource_normalise('/pinegrap/integration.php'), 'relative');
	pg_assert_same('', pg_oauth_resource_normalise(null), 'not a string');
}
