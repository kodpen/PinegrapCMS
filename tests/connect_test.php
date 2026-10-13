<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the connected-accounts rules that need no database
 * (includes/fn/connect.php): the provider and purpose files, the address the
 * person is sent to, scopes, where access tokens are cached, the identity
 * token checks and the screen a connection returns to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every provider file carries what the screens and the flow read.
function test_connect_providers_load_with_their_endpoints()
{
	$providers = pg_connect_providers();

	pg_assert_true(isset($providers['google']), 'Google');
	pg_assert_true(isset($providers['microsoft']), 'Microsoft');

	foreach ($providers as $key => $provider) {
		pg_assert_same($key, $provider['key'], $key . ': key is the file name');
		pg_assert_true($provider['label'] !== '', $key . ': label');
		pg_assert_same(0, strpos($provider['authorize_url'], 'https://'), $key . ': authorize over https');
		pg_assert_same(0, strpos($provider['token_url'], 'https://'), $key . ': token over https');
		pg_assert_true(in_array('openid', $provider['identity_scopes'], true), $key . ': asks who the account is');
		pg_assert_true(!empty($provider['issuers']) || !empty($provider['issuer_pattern']), $key . ': knows its issuer');
		pg_assert_true(is_array($provider['setup']) && $provider['setup'], $key . ': setup steps');
	}

	pg_assert_same(null, pg_connect_provider('nope'), 'unknown provider');
	pg_assert_same(null, pg_connect_provider('../fn/connect'), 'a path is not a key');
}

function test_connect_account_purpose_serves_both_providers_and_owners()
{
	$purpose = pg_connect_purpose('account');

	pg_assert_true(is_array($purpose), 'account purpose');
	pg_assert_true(pg_connect_purpose_allows($purpose, 'google', 'site'), 'Google, the site');
	pg_assert_true(pg_connect_purpose_allows($purpose, 'microsoft', 'person'), 'Microsoft, a person');
	pg_assert_false(pg_connect_purpose_allows($purpose, 'dropbox', 'site'), 'a provider it does not list');

	$site_only = array('owner' => 'site', 'scopes' => array('google' => array('x')));

	pg_assert_true(pg_connect_purpose_allows($site_only, 'google', 'site'), 'site-owned purpose, the site');
	pg_assert_false(pg_connect_purpose_allows($site_only, 'google', 'person'), 'site-owned purpose, a person');
	pg_assert_false(pg_connect_purpose_allows(null, 'google', 'site'), 'no purpose');
}

// The RFC 7636 appendix B example.
function test_connect_pkce_challenge_is_s256()
{
	$pkce = pg_connect_pkce('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk');

	pg_assert_same('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $pkce['challenge'], 'challenge');

	$fresh = pg_connect_pkce();

	pg_assert_true(strlen($fresh['verifier']) >= 43, 'a fresh verifier is long enough');
	pg_assert_same(1, preg_match('/^[A-Za-z0-9_-]+$/', $fresh['verifier']), 'and uses the unreserved characters');
}

function test_connect_scope_list_dedupes_and_keeps_order()
{
	pg_assert_same(array('openid', 'email', 'b'), pg_connect_scope_list("  openid email\topenid  b "), 'string');
	pg_assert_same(array('a', 'b'), pg_connect_scope_list(array('a', '', 'b', 'a', ' ')), 'list');
	pg_assert_same(array(), pg_connect_scope_list(''), 'empty');
}

function test_connect_request_scopes_add_the_purpose_to_the_identity()
{
	$provider = pg_connect_provider('google');
	$purpose = array('scopes' => array('google' => array('https://www.googleapis.com/auth/gmail.send', 'email')));

	pg_assert_same(
		array('openid', 'email', 'profile', 'https://www.googleapis.com/auth/gmail.send'),
		pg_connect_request_scopes($provider, $purpose),
		'identity first, the purpose after, no repeats');

	pg_assert_same(array('openid', 'email', 'profile'), pg_connect_request_scopes($provider, pg_connect_purpose('account')), 'account alone');

	$microsoft = pg_connect_request_scopes(pg_connect_provider('microsoft'), pg_connect_purpose('account'));

	pg_assert_true(in_array('offline_access', $microsoft, true), 'Microsoft asks for lasting access');
	pg_assert_true(in_array('https://graph.microsoft.com/User.Read', $microsoft, true), 'and for the scope its default token uses');
}

// Google: one token for everything. Microsoft: one per resource, cached
// apart, the same key whatever the order the scopes are named in.
function test_connect_token_scopes_and_access_key()
{
	$google = pg_connect_provider('google');
	$microsoft = pg_connect_provider('microsoft');

	pg_assert_same(array(), pg_connect_token_scopes($google, array('x')), 'Google needs no scopes on refresh');
	pg_assert_same('default', pg_connect_access_key($google, array()), 'Google: one cache entry');

	pg_assert_same(array('https://graph.microsoft.com/User.Read'), pg_connect_token_scopes($microsoft, array()), 'Microsoft default');

	$smtp = pg_connect_token_scopes($microsoft, array('https://outlook.office.com/SMTP.Send'));
	$key = pg_connect_access_key($microsoft, $smtp);

	pg_assert_same(16, strlen($key), 'a key per scope set');
	pg_assert_false($key === pg_connect_access_key($microsoft, pg_connect_token_scopes($microsoft, array())), 'another resource, another key');
	pg_assert_same(
		pg_connect_access_key($microsoft, array('b', 'a')),
		pg_connect_access_key($microsoft, array('a', 'b', 'a')),
		'order and repeats do not matter');
}

function test_connect_endpoint_fills_the_tenant()
{
	$microsoft = pg_connect_provider('microsoft');

	pg_assert_same(
		'https://login.microsoftonline.com/common/oauth2/v2.0/token',
		pg_connect_endpoint($microsoft, array('options' => array()), 'token_url'),
		'no tenant: common');

	pg_assert_same(
		'https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/token',
		pg_connect_endpoint($microsoft, array('options' => array('tenant' => 'contoso.onmicrosoft.com')), 'token_url'),
		'a domain');

	pg_assert_same(
		'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
		pg_connect_endpoint($microsoft, array('options' => array('tenant' => '../evil?x=')), 'authorize_url'),
		'anything else falls back');

	pg_assert_same('', pg_connect_endpoint($microsoft, array(), 'revoke_url'), 'no revoke endpoint');
	pg_assert_same('https://oauth2.googleapis.com/token', pg_connect_endpoint(pg_connect_provider('google'), array(), 'token_url'), 'Google has no tenant');
}

function test_connect_authorize_url_carries_the_request()
{
	$google = pg_connect_provider('google');

	$url = pg_connect_authorize_url($google, array('client_id' => 'abc.apps.googleusercontent.com'), array(
		'redirect_uri' => 'https://example.com/pinegrap/connect.php',
		'scopes'       => array('openid', 'email'),
		'state'        => 'st',
		'challenge'    => 'ch',
		'nonce'        => 'no',
		'login_hint'   => 'a@example.com',
	));

	pg_assert_same(0, strpos($url, 'https://accounts.google.com/o/oauth2/v2/auth?'), 'endpoint');

	parse_str(parse_url($url, PHP_URL_QUERY), $query);

	pg_assert_same('code', $query['response_type'], 'response_type');
	pg_assert_same('abc.apps.googleusercontent.com', $query['client_id'], 'client_id');
	pg_assert_same('https://example.com/pinegrap/connect.php', $query['redirect_uri'], 'redirect_uri');
	pg_assert_same('openid email', $query['scope'], 'scope');
	pg_assert_same('st', $query['state'], 'state');
	pg_assert_same('ch', $query['code_challenge'], 'challenge');
	pg_assert_same('S256', $query['code_challenge_method'], 'S256');
	pg_assert_same('no', $query['nonce'], 'nonce');
	pg_assert_same('a@example.com', $query['login_hint'], 'login_hint');
	pg_assert_same('offline', $query['access_type'], 'Google: a refresh token');
	pg_assert_same('consent', $query['prompt'], 'Google: asked again, so the refresh token comes again');
	pg_assert_contains('scope=openid%20email', $url, 'spaces as %20');

	$plain = pg_connect_authorize_url($google, array('client_id' => 'x'), array(
		'redirect_uri' => 'https://example.com/c', 'scopes' => 'openid', 'state' => 's', 'challenge' => 'c'));

	pg_assert_false(strpos($plain, 'nonce=') !== false, 'no empty nonce');
	pg_assert_false(strpos($plain, 'login_hint=') !== false, 'no empty login_hint');
}

function test_connect_token_response_shape()
{
	$response = pg_connect_token_response(array(
		'access_token'  => 'at',
		'refresh_token' => 'rt',
		'expires_in'    => 3599,
		'scope'         => 'openid https://www.googleapis.com/auth/gmail.send',
		'id_token'      => 'id',
	), 1000);

	pg_assert_same('at', $response['access_token'], 'access');
	pg_assert_same('rt', $response['refresh_token'], 'refresh');
	pg_assert_same(4599, $response['expires_at'], 'expiry');
	pg_assert_same(array('openid', 'https://www.googleapis.com/auth/gmail.send'), $response['scopes'], 'scopes');

	$bare = pg_connect_token_response(array('access_token' => 'at'), 1000);

	pg_assert_same('', $bare['refresh_token'], 'no refresh token');
	pg_assert_same(4600, $bare['expires_at'], 'an hour when the provider does not say');
}

function test_connect_claims_are_checked()
{
	$google = pg_connect_provider('google');
	$now = 1700000000;
	$claims = array(
		'iss'   => 'https://accounts.google.com',
		'aud'   => 'abc',
		'exp'   => $now + 3000,
		'nonce' => 'n1',
		'sub'   => '1234',
		'email' => 'a@example.com',
	);

	pg_assert_same('', pg_connect_claims_problem($google, $claims, 'abc', 'n1', $now), 'good');
	pg_assert_same('', pg_connect_claims_problem($google, array_merge($claims, array('iss' => 'accounts.google.com')), 'abc', 'n1', $now), 'the short issuer Google also uses');
	pg_assert_same('', pg_connect_claims_problem($google, array_merge($claims, array('aud' => array('other', 'abc'))), 'abc', 'n1', $now), 'audience list');

	pg_assert_true(pg_connect_claims_problem($google, array_merge($claims, array('iss' => 'https://evil.example')), 'abc', 'n1', $now) !== '', 'issuer');
	pg_assert_true(pg_connect_claims_problem($google, array_merge($claims, array('aud' => 'other')), 'abc', 'n1', $now) !== '', 'audience');
	pg_assert_true(pg_connect_claims_problem($google, array_merge($claims, array('exp' => $now - 400)), 'abc', 'n1', $now) !== '', 'expired');
	pg_assert_same('', pg_connect_claims_problem($google, array_merge($claims, array('exp' => $now - 100)), 'abc', 'n1', $now), 'within the clock skew');
	pg_assert_true(pg_connect_claims_problem($google, array_merge($claims, array('nonce' => 'n2')), 'abc', 'n1', $now) !== '', 'another request');
	pg_assert_true(pg_connect_claims_problem($google, array_merge($claims, array('sub' => '')), 'abc', 'n1', $now) !== '', 'no account');
	pg_assert_true(pg_connect_claims_problem($google, null, 'abc', 'n1', $now) !== '', 'unreadable');

	$no_nonce = $claims;
	unset($no_nonce['nonce']);

	pg_assert_true(pg_connect_claims_problem($google, $no_nonce, 'abc', 'n1', $now) !== '', 'a nonce was sent but none came back');
}

function test_connect_microsoft_issuer_is_a_directory()
{
	$microsoft = pg_connect_provider('microsoft');

	pg_assert_true(pg_connect_issuer_matches($microsoft, 'https://login.microsoftonline.com/9188040d-6c67-4c5b-b112-36a304b66dad/v2.0'), 'personal accounts directory');
	pg_assert_true(pg_connect_issuer_matches($microsoft, 'https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/v2.0'), 'an organization');
	pg_assert_false(pg_connect_issuer_matches($microsoft, 'https://login.microsoftonline.com/common/v2.0'), 'not a directory');
	pg_assert_false(pg_connect_issuer_matches($microsoft, 'https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/v2.0/x'), 'trailing path');
	pg_assert_false(pg_connect_issuer_matches($microsoft, ''), 'empty');
	pg_assert_false(pg_connect_issuer_matches(pg_connect_provider('google'), 'https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/v2.0'), 'Google does not take it');
}

function test_connect_identity_label()
{
	pg_assert_same(array('id' => 's', 'label' => 'a@example.com'), pg_connect_identity(array('sub' => 's', 'email' => 'a@example.com', 'name' => 'A')), 'e-mail first');
	pg_assert_same(array('id' => 's', 'label' => 'a@contoso.com'), pg_connect_identity(array('sub' => 's', 'email' => ' ', 'preferred_username' => 'a@contoso.com')), 'Microsoft user name');
	pg_assert_same(array('id' => '', 'label' => ''), pg_connect_identity(array()), 'nothing');
}

// The return address cannot leave the panel or carry a header.
function test_connect_return_path_stays_in_the_panel()
{
	pg_assert_same('smtp_settings.php', pg_connect_return_path('smtp_settings.php'), 'a screen');
	pg_assert_same('backups.php?tab=remote#drive', pg_connect_return_path('backups.php?tab=remote#drive'), 'with a query and a fragment');

	foreach (array('', 'https://evil.example/x.php', '//evil.example/x.php', '../x.php', 'a/b.php', 'x.php?a=b c', "x.php\r\nSet-Cookie: a=b", 'x.html', null, array('x.php')) as $bad) {
		pg_assert_same('connections.php', pg_connect_return_path($bad), var_export($bad, true));
	}
}

function test_connect_access_cache()
{
	$now = 1000;
	$secret = array('refresh_token' => 'rt');

	pg_assert_same('', pg_connect_cached_access($secret, 'default', $now), 'nothing cached');

	$secret = pg_connect_store_access($secret, 'default', 'at1', $now + 3600, $now);

	pg_assert_same('at1', pg_connect_cached_access($secret, 'default', $now), 'cached');
	pg_assert_same('', pg_connect_cached_access($secret, 'default', $now + 3500), 'about to end: renewed early');
	pg_assert_same('rt', $secret['refresh_token'], 'the refresh token stays');

	$secret['access']['old'] = array('token' => 'x', 'expires' => $now - 1);
	$secret = pg_connect_store_access($secret, 'k2', 'at2', $now + 100, $now);

	pg_assert_false(isset($secret['access']['old']), 'expired entries are dropped');
	pg_assert_same('at1', pg_connect_cached_access($secret, 'default', $now), 'others stay');
	pg_assert_same('at2', pg_connect_cached_access($secret, 'k2', $now, 60), 'per key, with a margin');
}

function test_connect_clip()
{
	pg_assert_same('a b c', pg_connect_clip("  a\n b <b>c</b> "), 'one line, no markup');
	pg_assert_same(250, strlen(pg_connect_clip(str_repeat('x', 400))), 'fits the column');
}
