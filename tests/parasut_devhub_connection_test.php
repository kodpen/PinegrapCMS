<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Paraşüt through a client from its Developer Portal: the provider
 * and purpose of the connected accounts (the authorize address with the
 * site's return address, the scopes, PKCE and the state), which token the
 * requests go with, the status line, the fold of the older way and the
 * boxes of Paraşüt's page of Connections.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a function of a source file, read from the source.
function parasut_devhub_test_body($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// The address the browser is sent to: Paraşüt's OpenID Connect server, the
// site's one return address, openid and offline_access, the S256 challenge
// and the state; the identity provider's choice is skipped and no nonce is
// sent (no identity token is read).
function test_parasut_devhub_authorize_address()
{
	$provider = pg_connect_provider('parasut');
	$purpose = pg_connect_purpose('parasut');

	pg_assert_true(is_array($provider), 'the provider file');
	pg_assert_true(is_array($purpose), 'the purpose file');

	$pkce = pg_connect_pkce('verifier-of-a-test-0123456789-0123456789-0123456789');

	$url = pg_connect_authorize_url($provider, array('client_id' => 'devhub-client'), array(
		'redirect_uri' => 'https://dev.pinegrap.com/pinegrap/connect.php',
		'scopes'       => pg_connect_request_scopes($provider, $purpose),
		'state'        => 'st4te',
		'challenge'    => $pkce['challenge'],
		'nonce'        => '',
	));

	pg_assert_same(0, strpos($url, 'https://id.parasut.com/realms/parasut/protocol/openid-connect/auth?'), 'the endpoint');

	parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

	pg_assert_same('code', $query['response_type']);
	pg_assert_same('devhub-client', $query['client_id']);
	pg_assert_same('https://dev.pinegrap.com/pinegrap/connect.php', $query['redirect_uri'], 'the return address, not the out-of-band one');
	pg_assert_same('openid offline_access', $query['scope'], 'a refresh token that lives while it is used');
	pg_assert_same('st4te', $query['state']);
	pg_assert_same($pkce['challenge'], $query['code_challenge']);
	pg_assert_same('S256', $query['code_challenge_method']);
	pg_assert_same('parasut', $query['kc_idp_hint']);
	pg_assert_false(isset($query['nonce']), 'no nonce');
	pg_assert_same('https://id.parasut.com/realms/parasut/protocol/openid-connect/token', pg_connect_endpoint($provider, array(), 'token_url'));
}

// One connection for the site, named Paraşüt, kept as long as it renews;
// no revocation address is guessed; no row of Account providers.
function test_parasut_devhub_provider_and_purpose()
{
	$provider = pg_connect_provider('parasut');
	$purpose = pg_connect_purpose('parasut');

	pg_assert_false($provider['identity'], 'no identity token is read');
	pg_assert_same('Paraşüt', $provider['account_label'], 'the flash says "Paraşüt is connected."');
	pg_assert_false($provider['nonce']);
	pg_assert_true(empty($provider['refresh_optional']), 'a connection without a refresh token is refused');
	pg_assert_false(isset($provider['revoke_url']), 'no revocation address the guide does not name');
	pg_assert_false($provider['listed'], 'set up on its service\'s page');
	pg_assert_same('parasut', $provider['connect_purpose']);
	pg_assert_true($provider['redirect_help'] !== '', 'the help under the redirect address');

	pg_assert_true(pg_connect_purpose_allows($purpose, 'parasut', 'site'), 'the site\'s');
	pg_assert_false(pg_connect_purpose_allows($purpose, 'parasut', 'person'), 'never a person\'s');
	pg_assert_false(pg_connect_purpose_allows($purpose, 'google', 'site'), 'Paraşüt only');

	pg_assert_same('connections.php?service=parasut', pg_connect_return_path('connections.php?service=parasut'), 'back to Paraşüt\'s page');
}

// The site's connection when there is one, in any state; else the older way.
function test_parasut_devhub_token_choice()
{
	pg_assert_same('legacy', parasut_token_choice(null));
	pg_assert_same('legacy', parasut_token_choice(array()));
	pg_assert_same('legacy', parasut_token_choice(array('id' => 0)));
	pg_assert_same('connection', parasut_token_choice(array('id' => 7, 'status' => 'active')));
	pg_assert_same('connection', parasut_token_choice(array('id' => 7, 'status' => 'reauth')), 'says it has to be connected again');

	$get = parasut_devhub_test_body('includes/fn/parasut.php', 'parasut_get_token');
	pg_assert_true(strpos($get, 'parasut_token_choice($connection)') < strpos($get, '_parasut_token_cache_read()'), 'the connection is asked first');

	foreach (array('parasut_token_forget', '_parasut_token_after_401') as $name) {
		pg_assert_contains('parasut_devhub_token($connection, true)', parasut_devhub_test_body('includes/fn/parasut.php', $name), $name . ' renews the connection\'s token');
	}

	pg_assert_contains("pg_connect_access_token((int) \$connection['id']", parasut_devhub_test_body('includes/fn/parasut.php', 'parasut_devhub_token'), 'renewed under the connection\'s lock');
}

// The status line: the connection, its e-mail and company; one that has to
// be made again says so; details read for another connection are not used.
function test_parasut_devhub_status_line()
{
	$details = array('connection_id' => 7, 'email' => 'a@example.com', 'company_name' => 'Örnek A.Ş.', 'connected_at' => 0);

	$status = parasut_devhub_status(array('id' => 7, 'status' => 'active', 'created_timestamp' => 0), $details);
	pg_assert_true($status['connected']);
	pg_assert_same('devhub', $status['grant']);
	pg_assert_same('Paraşüt is connected. · a@example.com · Örnek A.Ş.', parasut_connection_line($status)['text']);
	pg_assert_same('success', parasut_connection_line($status)['tone']);

	$other = parasut_devhub_status(array('id' => 8, 'status' => 'active', 'created_timestamp' => 0), $details);
	pg_assert_same('', $other['company_name'], 'another connection\'s company');

	$lost = parasut_devhub_status(array('id' => 7, 'status' => 'reauth', 'last_error' => 'invalid_grant'), $details);
	pg_assert_false($lost['connected']);
	pg_assert_contains('invalid_grant', parasut_connection_line($lost)['text']);
	pg_assert_same('secondary', parasut_connection_line($lost)['tone']);
}

// The fold of the older way opens only while the site's token is of that kind.
function test_parasut_devhub_legacy_fold()
{
	pg_assert_true(parasut_page_legacy_open(null, array('grant' => 'code', 'connected' => true)));
	pg_assert_true(parasut_page_legacy_open(null, array('grant' => 'password', 'connected' => true)));
	pg_assert_false(parasut_page_legacy_open(null, array('grant' => '', 'connected' => false)), 'nothing connected');
	pg_assert_false(parasut_page_legacy_open(array('id' => 3), array('grant' => 'code', 'connected' => true)), 'a portal connection');
}

// The page's boxes: the application as every provider's page draws it
// (Client ID, Client secret, the read-only redirect address with Copy and
// its help), then Connect through connect.php back to the page; the older
// way in its fold; the company number under Advanced; Disconnect ends both.
function test_parasut_devhub_page_boxes()
{
	$part = parasut_devhub_test_body('connections.php', 'connections_parasut_devhub_part');

	pg_assert_contains("connections_app_form('parasut', \$provider, \$app", $part, 'the application boxes');
	pg_assert_contains("pg_connect_start_fields('parasut', 'parasut', 'connections.php?service=parasut')", $part, 'Connect');
	pg_assert_contains('action="connect.php"', $part);

	$form = parasut_devhub_test_body('connections.php', 'connections_app_form');
	pg_assert_contains("\$provider['redirect_help']", $form, 'the redirect address\'s help');
	pg_assert_contains('readonly', $form);
	pg_assert_contains("lang('Copy')", $form);

	$page = parasut_devhub_test_body('connections.php', 'connections_edoc_page');
	pg_assert_true(strpos($page, 'connections_parasut_devhub_part(') < strpos($page, 'parasut_page_fields_html()'), 'the portal client first');

	$fields = parasut_devhub_test_body('includes/fn/parasut.php', 'parasut_page_fields_html');
	pg_assert_contains("lang('A client from before the Developer Portal')", $fields);
	pg_assert_contains("lang('Advanced')", $fields);
	pg_assert_contains('parasut_page_legacy_open(', $fields);
	pg_assert_true(strpos($fields, '$box(\'parasut_company_id\'') > strpos($fields, "lang('Advanced')"), 'the company number under Advanced');

	$disconnect = parasut_devhub_test_body('includes/fn/parasut.php', 'parasut_disconnect');
	pg_assert_contains('pg_connect_disconnect(', $disconnect);
	pg_assert_contains('_parasut_token_cache_write(array())', $disconnect);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
	pg_assert_contains("\$provider['listed'] === false", $screen, 'no row of Account providers');
	pg_assert_contains('parasut_devhub_after_connect(', $screen, 'the company is read on return');
}

// A code of the older way pasted while the site is connected through a
// portal client is refused before it is exchanged: the requests keep going
// with the portal connection, so the older Connect would report a
// connection that is not used and move the company number under it.
function test_parasut_devhub_code_connect_refused_beside_a_portal_connection()
{
	pg_assert_same('', parasut_code_connect_refusal(null), 'no portal connection');
	pg_assert_same('', parasut_code_connect_refusal(array('id' => 0)));
	pg_assert_contains('Developer Portal', parasut_code_connect_refusal(array('id' => 7, 'status' => 'active')));
	pg_assert_contains('Developer Portal', parasut_code_connect_refusal(array('id' => 7, 'status' => 'reauth')), 'one to be made again too');

	$connect = parasut_devhub_test_body('includes/fn/parasut.php', 'parasut_connect_with_code');
	$refused = strpos($connect, 'parasut_code_connect_refusal(parasut_devhub_connection())');
	pg_assert_true($refused !== false, 'Connect asks');
	pg_assert_true($refused < strpos($connect, '_parasut_token_exchange('), 'before the code is spent');
	pg_assert_true($refused < strpos($connect, 'UPDATE config'), 'before anything is saved');
}

// Connect again keeps the connection's id (one row per site), so what was
// read for the account before is dropped before the new account is read:
// when that read fails, the status line does not name the earlier account's
// e-mail and company.
function test_parasut_devhub_after_connect_drops_the_earlier_account()
{
	$after = parasut_devhub_test_body('includes/fn/parasut.php', 'parasut_devhub_after_connect');
	$dropped = strpos($after, "pg_connect_service_delete('parasut_devhub')");

	pg_assert_true($dropped !== false, 'the earlier details are dropped');
	pg_assert_true($dropped < strpos($after, 'pg_connect_access_token('), 'before the token is read');
	pg_assert_true($dropped < strpos($after, 'parasut_me('), 'before the account is asked');
}
