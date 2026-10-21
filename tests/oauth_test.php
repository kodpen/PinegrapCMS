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
require_once(PG_FUNCTIONS_DIR . '/includes/oauth/endpoints.php');

// https anywhere, http only on the loopback, and an application's own
// scheme, reversed domain name or single word.
function test_oauth_redirect_registration_accepts_the_three_kinds()
{
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('https://claude.ai/api/mcp/auth_callback'), 'https');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('https://chatgpt.com/connector_platform_oauth_redirect'), 'ChatGPT');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('https://vscode.dev/redirect'), 'VS Code on the web');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('http://127.0.0.1:33418/callback'), 'IPv4 loopback');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('http://127.0.0.1/'), 'IPv4 loopback with no port, as VS Code registers it');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('http://[::1]/cb'), 'IPv6 loopback');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('http://localhost:6274/oauth/callback'), 'localhost');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('com.example.app:/oauth2redirect'), 'private-use scheme');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('cursor://anysphere.cursor-mcp/oauth/callback'), 'Cursor: a single-word scheme');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('kiro://kiro.kiroAgent/authenticate-success'), 'Kiro');
	pg_assert_true(pg_oauth_redirect_uri_is_acceptable('myapp:/cb'), 'scheme without a dot');
}

function test_oauth_redirect_registration_refuses_the_rest()
{
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('http://example.com/cb'), 'plain http to another host');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('https://example.com/cb#frag'), 'fragment');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('https://site.com@evil.com/cb'), 'user name in front of the host');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('javascript:alert(1)'), 'javascript');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('JavaScript://x%0Aalert(1)'), 'javascript with slashes, any case');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('data:text/html,x'), 'data');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('vbscript:msgbox(1)'), 'vbscript');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('file:///etc/passwd'), 'file');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('blob:https://a.example/1'), 'blob');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('mailto:a@example.com'), 'another kind of program');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('cursor:'), 'a scheme on its own');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('cursor://'), 'a scheme and slashes');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('cursor://me@anysphere.cursor-mcp/cb'), 'user name in an app address');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('1app:/cb'), 'a scheme has to start with a letter');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable('/relative/cb'), 'relative');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable("https://example.com/c\nb"), 'control character');
	pg_assert_false(pg_oauth_redirect_uri_is_acceptable(array('https://example.com/cb')), 'not a string');
}

// Exact match, except a loopback address, compared by path and query alone.
function test_oauth_redirect_matching_is_exact()
{
	$registered = array('https://claude.ai/api/mcp/auth_callback', 'http://127.0.0.1/callback');

	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai/api/mcp/auth_callback'), 'same string');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai/api/mcp/auth_callback/'), 'trailing slash');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai/api/mcp/auth_callback?x=1'), 'extra query');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'https://claude.ai:8443/api/mcp/auth_callback'), 'port on https');
	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1:51234/callback'), 'loopback with a port of its own');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1:51234/other'), 'loopback, other path');
	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'http://localhost:51234/callback'), 'loopback, other name for the same machine');
	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'http://[::1]:51234/callback'), 'loopback, IPv6');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'https://127.0.0.1:51234/callback'), 'https is not the loopback rule');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1.evil.com:51234/callback'), 'a host that only starts like the loopback');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'http://user@127.0.0.1:51234/callback'), 'user name on the loopback');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, ''), 'empty');
}

// What VS Code registers and what it then sends.
function test_oauth_redirect_matching_vscode()
{
	$registered = array('https://insiders.vscode.dev/redirect', 'https://vscode.dev/redirect', 'http://127.0.0.1/', 'http://127.0.0.1:33418/');

	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1:33418/'), 'its usual port');
	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1:61022/'), 'another port when that one is taken');
	pg_assert_true(pg_oauth_redirect_uri_matches(array('http://127.0.0.1'), 'http://127.0.0.1:61022/'), 'no path is the root');
	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'https://vscode.dev/redirect'));
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'http://127.0.0.1:61022/callback'), 'another path');
}

function test_oauth_redirect_matching_app_scheme()
{
	$registered = array('cursor://anysphere.cursor-mcp/oauth/callback');

	pg_assert_true(pg_oauth_redirect_uri_matches($registered, 'cursor://anysphere.cursor-mcp/oauth/callback'));
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'cursor://anysphere.cursor-mcp/oauth/callback/'), 'an app address is exact');
	pg_assert_false(pg_oauth_redirect_uri_matches($registered, 'Cursor://anysphere.cursor-mcp/oauth/callback'), 'and so is its spelling');
}

// How the consent screen names where the answer goes.
function test_oauth_redirect_destination()
{
	pg_assert_same(array('kind' => 'web', 'text' => 'claude.ai'), pg_oauth_redirect_destination('https://claude.ai/api/mcp/auth_callback'));
	pg_assert_same(array('kind' => 'device', 'text' => '127.0.0.1:33418'), pg_oauth_redirect_destination('http://127.0.0.1:33418/'));
	pg_assert_same(array('kind' => 'device', 'text' => 'localhost'), pg_oauth_redirect_destination('http://localhost/callback'));
	pg_assert_same(array('kind' => 'app', 'text' => 'cursor://anysphere.cursor-mcp'), pg_oauth_redirect_destination('cursor://anysphere.cursor-mcp/oauth/callback'));
	pg_assert_same(array('kind' => 'app', 'text' => 'com.example.app:/oauth2redirect'), pg_oauth_redirect_destination('com.example.app:/oauth2redirect'));
}

// The registrations real clients send (RFC 7591), as their own code builds
// them.
function test_oauth_registration_of_known_clients()
{
	$vscode = pg_oauth_registration_validate(array(
		'client_name'                => 'Visual Studio Code',
		'client_uri'                 => 'https://code.visualstudio.com',
		'grant_types'                => array('authorization_code', 'refresh_token', 'urn:ietf:params:oauth:grant-type:device_code'),
		'response_types'             => array('code'),
		'redirect_uris'              => array('https://insiders.vscode.dev/redirect', 'https://vscode.dev/redirect', 'http://127.0.0.1/', 'http://127.0.0.1:33418/'),
		'scope'                      => 'products:read',
		'token_endpoint_auth_method' => 'none',
		'application_type'           => 'native'
	));

	pg_assert_false(isset($vscode['error']), 'VS Code, the device code grant included');
	pg_assert_same(array('authorization_code', 'refresh_token'), $vscode['grant_types'], 'registered for what the server has');
	pg_assert_same('none', $vscode['token_endpoint_auth_method']);

	$cursor = pg_oauth_registration_validate(array(
		'client_name'                => 'Cursor',
		'redirect_uris'              => array('cursor://anysphere.cursor-mcp/oauth/callback', 'http://127.0.0.1:54321/callback'),
		'grant_types'                => array('authorization_code', 'refresh_token'),
		'response_types'             => array('code'),
		'token_endpoint_auth_method' => 'none'
	));

	pg_assert_false(isset($cursor['error']), 'Cursor and its own scheme');

	$gemini = pg_oauth_registration_validate(array(
		'client_name'                => 'Gemini CLI MCP Client',
		'redirect_uris'              => array('http://localhost:7777/oauth/callback'),
		'grant_types'                => array('authorization_code', 'refresh_token'),
		'response_types'             => array('code'),
		'token_endpoint_auth_method' => 'none',
		'scope'                      => ''
	));

	pg_assert_false(isset($gemini['error']), 'Gemini CLI');

	$bare = pg_oauth_registration_validate(array('redirect_uris' => array('https://chatgpt.com/connector_platform_oauth_redirect')));

	pg_assert_false(isset($bare['error']), 'only the redirect address');
	pg_assert_same('client_secret_basic', $bare['token_endpoint_auth_method'], 'RFC 7591 default method');
	pg_assert_same(array('authorization_code', 'refresh_token'), $bare['grant_types'], 'refresh tokens are part of every connection');

	$mixed = pg_oauth_registration_validate(array('redirect_uris' => array('https://a.example/cb'), 'response_types' => array('code', 'id_token')));

	pg_assert_false(isset($mixed['error']), 'another response type alongside code');
}

function test_oauth_registration_refusals()
{
	$no_code = pg_oauth_registration_validate(array('redirect_uris' => array('https://a.example/cb'), 'grant_types' => array('client_credentials')));

	pg_assert_same('invalid_client_metadata', $no_code['error'], 'no code grant');

	$implicit = pg_oauth_registration_validate(array('redirect_uris' => array('https://a.example/cb'), 'response_types' => array('token')));

	pg_assert_same('invalid_client_metadata', $implicit['error'], 'implicit flow only');

	$jwt = pg_oauth_registration_validate(array('redirect_uris' => array('https://a.example/cb'), 'token_endpoint_auth_method' => 'private_key_jwt'));

	pg_assert_same('invalid_client_metadata', $jwt['error'], 'a client authentication this server does not have');

	$script = pg_oauth_registration_validate(array('redirect_uris' => array('javascript:alert(1)')));

	pg_assert_same('invalid_redirect_uri', $script['error'], 'script address');
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

// Scopes the site does not have are passed over; asking for none of its own
// is asking for nothing.
function test_oauth_scope_requested()
{
	$known = array('orders:read', 'orders:write', 'products:read', 'products:write', 'webhooks:manage');
	$available = array('orders:read', 'products:read', 'products:write');

	pg_assert_same($available, pg_oauth_scope_requested('', $known, $available), 'nothing asked: everything available');
	pg_assert_same($available, pg_oauth_scope_requested('openid offline_access', $known, $available), 'only scopes the site does not have');
	pg_assert_same(array('products:read'), pg_oauth_scope_requested('openid products:read offline_access', $known, $available), 'the real ones kept');
	pg_assert_same(array('orders:read', 'products:write'), pg_oauth_scope_requested('products:write orders:read', $known, $available), 'in the order offered');
	pg_assert_same(array(), pg_oauth_scope_requested('orders:write webhooks:manage', $known, $available), 'real scopes this person cannot hand over');
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

// state goes back exactly as it came: a signed state of a kilobyte and more
// is carried whole (it used to be cut to 1000 characters); one past the
// ceiling is refused rather than shortened.
function test_oauth_state_is_carried_whole_or_refused()
{
	pg_assert_true(pg_oauth_state_is_acceptable(''), 'no state');
	pg_assert_true(pg_oauth_state_is_acceptable(str_repeat('a', 1403)), 'a signed state of 1403 characters');
	pg_assert_true(pg_oauth_state_is_acceptable(str_repeat('a', pg_oauth_state_max())), 'at the ceiling');
	pg_assert_false(pg_oauth_state_is_acceptable(str_repeat('a', pg_oauth_state_max() + 1)), 'past the ceiling');
	pg_assert_false(pg_oauth_state_is_acceptable(array('a')), 'not a string');
	pg_assert_true(pg_oauth_state_max() >= 2048, 'room for the states clients sign');
}
