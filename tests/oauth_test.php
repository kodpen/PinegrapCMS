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

// draft-ietf-oauth-client-id-metadata-document 3: https, a path, nothing that
// lets two spellings point to different places.
function test_oauth_client_id_url_shape()
{
	pg_assert_true(pg_oauth_client_id_is_url('https://claude.ai/oauth/mcp-oauth-client-metadata'), 'plain https address with a path');
	pg_assert_true(pg_oauth_client_id_is_url('https://app.example.com:8443/client.json'), 'with a port');
	pg_assert_false(pg_oauth_client_id_is_url('pgoc_0123456789abcdef'), 'a registered client_id');
	pg_assert_false(pg_oauth_client_id_is_url('http://app.example.com/client.json'), 'plain http');
	pg_assert_false(pg_oauth_client_id_is_url('https://app.example.com'), 'no path');
	pg_assert_false(pg_oauth_client_id_is_url('https://app.example.com/'), 'root path only');
	pg_assert_false(pg_oauth_client_id_is_url('https://app.example.com/a/../client.json'), 'dot segment');
	pg_assert_false(pg_oauth_client_id_is_url('https://app.example.com/client.json#x'), 'fragment');
	pg_assert_false(pg_oauth_client_id_is_url('https://app.example.com/client.json?v=1'), 'query');
	pg_assert_false(pg_oauth_client_id_is_url('https://user@app.example.com/client.json'), 'user name');
	pg_assert_false(pg_oauth_client_id_is_url('https://app.example.com/a b'), 'space');
	pg_assert_false(pg_oauth_client_id_is_url('https://app.example.com/' . str_repeat('a', 260)), 'too long');
}

function test_oauth_metadata_document_accepts_a_public_client()
{
	$id = 'https://app.example.com/oauth/client.json';

	$read = pg_oauth_metadata_document_client($id, json_encode(array(
		'client_id'                  => $id,
		'client_name'                => "Example\nApp",
		'client_uri'                 => 'https://app.example.com',
		'redirect_uris'              => array('https://app.example.com/callback', 'http://127.0.0.1/callback'),
		'grant_types'                => array('authorization_code', 'refresh_token'),
		'response_types'             => array('code'),
		'token_endpoint_auth_method' => 'none'
	)));

	pg_assert_true($read['ok'], 'accepted');
	pg_assert_same('Example App', $read['client']['name'], 'control characters become spaces');
	pg_assert_same(array('https://app.example.com/callback', 'http://127.0.0.1/callback'), $read['client']['redirect_uris']);
	pg_assert_same('https://app.example.com', $read['client']['client_uri']);
}

function test_oauth_metadata_document_refusals()
{
	$id = 'https://app.example.com/oauth/client.json';

	$base = array('client_id' => $id, 'redirect_uris' => array('https://app.example.com/callback'));

	$refused = function ($document, $label) use ($id) {

		$read = pg_oauth_metadata_document_client($id, is_string($document) ? $document : json_encode($document));

		pg_assert_false($read['ok'], $label);

	};

	pg_assert_true(pg_oauth_metadata_document_client($id, json_encode($base))['ok'], 'minimal document');

	$refused(array_merge($base, array('client_id' => 'https://evil.example.com/client.json')), 'another client_id');
	$refused(array_merge($base, array('client_secret' => 'x')), 'a secret');
	$refused(array_merge($base, array('token_endpoint_auth_method' => 'client_secret_basic')), 'secret based method');
	$refused(array_merge($base, array('token_endpoint_auth_method' => 'private_key_jwt')), 'unsupported method');
	$refused(array_merge($base, array('grant_types' => array('client_credentials'))), 'no authorization_code');
	$refused(array_merge($base, array('redirect_uris' => array('http://evil.example.com/cb'))), 'plain http redirect');
	$refused(array_merge($base, array('redirect_uris' => array())), 'no redirect');
	$refused('[1,2]', 'a list');
	$refused('not json', 'not JSON');
	$refused(json_encode(array_merge($base, array('client_name' => str_repeat('a', 6000)))), 'over 5 KB');
}
