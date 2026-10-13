<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sign-in rules that need no database (includes/fn/signin.php):
 * the provider definitions, Google's return address and request, what a
 * screen may do with an unknown identity, when an e-mail address counts as
 * verified, who the person is, the names for the address book, the pasted
 * Apple key and the client secret JWT signed with it, and the badges of the
 * user and session lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A fresh P-256 key, as the .p8 file Apple hands out holds one.
function signin_test_ec_key()
{
	$key = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'));
	$pem = '';
	openssl_pkey_export($key, $pem);

	return array('pem' => $pem, 'public' => openssl_pkey_get_details($key)['key']);
}

// The JWS signature back in the DER form openssl_verify() reads.
function signin_test_jose_to_der($raw)
{
	$der = '';

	foreach (str_split($raw, 32) as $int) {
		$int = ltrim($int, "\x00");

		if (($int === '') || (ord($int[0]) & 0x80)) {
			$int = "\x00" . $int;
		}

		$der .= "\x02" . chr(strlen($int)) . $int;
	}

	return "\x30" . chr(strlen($der)) . $der;
}

function signin_test_b64url_decode($text)
{
	$text = strtr($text, '-_', '+/');

	return base64_decode($text . str_repeat('=', (4 - strlen($text) % 4) % 4));
}

// Google, Microsoft and Apple sign people in, in that order; Apple has no
// account to connect.
function test_signin_providers_in_button_order()
{
	$providers = pg_signin_providers();

	pg_assert_same(array('google', 'microsoft', 'apple'), array_keys($providers), 'order');
	pg_assert_false(isset($providers['google']['accounts']), 'Google also connects');
	pg_assert_same(false, $providers['apple']['accounts'], 'Apple only signs in');
	pg_assert_false(isset($providers['microsoft']['accounts']), 'Microsoft also connects');
	pg_assert_same('apple_jwt', $providers['apple']['signin']['client_secret'], 'Apple signs its secret');
	pg_assert_same(false, $providers['apple']['signin']['pkce'], 'no PKCE for Apple');
	pg_assert_same('form_post', $providers['apple']['authorize_params']['response_mode'], 'Apple posts back');
	pg_assert_same(array('tid', 'oid'), $providers['microsoft']['signin']['subject_claims'], 'Microsoft person');
}

function test_signin_signup_mode_matches_the_google_flags()
{
	pg_assert_same('create', pg_signin_signup_mode(true), 'true');
	pg_assert_same('none', pg_signin_signup_mode(false), 'false');
	pg_assert_same('none', pg_signin_signup_mode('0'), 'URL flag 0');
	pg_assert_same('none', pg_signin_signup_mode('none'), 'none');
	pg_assert_same('membership', pg_signin_signup_mode('membership'), 'membership');
	pg_assert_same('create', pg_signin_signup_mode('create'), 'create');
	pg_assert_same('create', pg_signin_signup_mode('anything'), 'unknown');
}

// Apple and Google say so; Microsoft is trusted for personal accounts and
// for a directory that owns the domain, never for an address typed in a
// work or school directory.
function test_signin_email_verified_per_provider()
{
	$providers = pg_signin_providers();
	$apple = $providers['apple']['signin'];
	$microsoft = $providers['microsoft']['signin'];

	pg_assert_true(pg_signin_email_verified($apple, array('email' => 'a@privaterelay.appleid.com', 'email_verified' => 'true')), 'Apple string');
	pg_assert_true(pg_signin_email_verified($apple, array('email' => 'a@example.com', 'email_verified' => true)), 'Apple bool');
	pg_assert_false(pg_signin_email_verified($apple, array('email' => 'a@example.com', 'email_verified' => 'false')), 'Apple says no');
	pg_assert_false(pg_signin_email_verified($apple, array('email' => '', 'email_verified' => true)), 'no address');

	pg_assert_true(pg_signin_email_verified($microsoft, array('email' => 'a@outlook.com', 'tid' => '9188040D-6C67-4C5B-B112-36A304B66DAD')), 'personal account');
	pg_assert_false(pg_signin_email_verified($microsoft, array('email' => 'ceo@bank.example', 'tid' => '11111111-2222-3333-4444-555555555555')), 'work account, typed address');
	pg_assert_true(pg_signin_email_verified($microsoft, array('email' => 'a@contoso.example', 'tid' => '11111111-2222-3333-4444-555555555555', 'xms_edov' => true)), 'domain owner verified');
	pg_assert_true(pg_signin_email_verified($microsoft, array('email' => 'a@contoso.example', 'tid' => '11111111-2222-3333-4444-555555555555', 'xms_edov' => '1')), 'xms_edov as a string');
	pg_assert_false(pg_signin_email_verified($microsoft, array('email' => 'a@contoso.example', 'tid' => '11111111-2222-3333-4444-555555555555', 'xms_edov' => false)), 'domain not verified');
}

function test_signin_subject_prefers_the_named_claims()
{
	$providers = pg_signin_providers();
	$microsoft = $providers['microsoft']['signin'];

	pg_assert_same(
		'9188040d-6c67-4c5b-b112-36a304b66dad:00000000-0000-0000-66f3-3332eca7ea81',
		pg_signin_subject($microsoft, array('sub' => 'AAAA', 'tid' => '9188040D-6C67-4C5B-B112-36A304B66DAD', 'oid' => '00000000-0000-0000-66f3-3332eca7ea81')),
		'directory and object');
	pg_assert_same('AAAA-bbb_1', pg_signin_subject($microsoft, array('sub' => 'AAAA-bbb_1', 'tid' => 'x')), 'falls back to sub');
	pg_assert_same('001234.abcdef.0123', pg_signin_subject($providers['apple']['signin'], array('sub' => '001234.abcdef.0123')), 'Apple');
	pg_assert_same('', pg_signin_subject($microsoft, array('sub' => "bad id")), 'not ASCII printable');
	pg_assert_same('', pg_signin_subject($microsoft, array()), 'nothing');
}

function test_signin_names_from_claims_apple_field_or_address()
{
	pg_assert_same(array('first_name' => 'Ada', 'last_name' => 'Lovelace'),
		pg_signin_names(array('given_name' => 'Ada', 'family_name' => 'Lovelace', 'name' => 'X Y'), '', 'a@example.com'), 'claims');
	pg_assert_same(array('first_name' => 'Ayşe', 'last_name' => 'Yılmaz'),
		pg_signin_names(array(), '{"name":{"firstName":"Ayşe","lastName":"Yılmaz"},"email":"x@privaterelay.appleid.com"}', 'x@privaterelay.appleid.com'), 'Apple first sign-in');
	pg_assert_same(array('first_name' => 'Mary Ann', 'last_name' => 'Smith'),
		pg_signin_names(array('name' => 'Mary Ann Smith'), '', 'm@example.com'), 'display name');
	pg_assert_same(array('first_name' => 'jo', 'last_name' => ''),
		pg_signin_names(array(), 'not json', 'jo@example.com'), 'address');
}

function test_signin_pem_private_key_accepts_what_is_pasted()
{
	$key = signin_test_ec_key();
	$body = trim(preg_replace('/-----[A-Z ]+-----/', '', $key['pem']));

	pg_assert_true(openssl_pkey_get_private(pg_signin_pem_private_key($key['pem'])) !== false, 'as exported');
	pg_assert_true(openssl_pkey_get_private(pg_signin_pem_private_key(str_replace("\n", "\r\n", $key['pem']))) !== false, 'Windows line ends');
	pg_assert_true(openssl_pkey_get_private(pg_signin_pem_private_key(preg_replace('/\s+/', '', $body))) !== false, 'body alone, one line');
	pg_assert_same('', pg_signin_pem_private_key('  '), 'empty');
	pg_assert_same('', pg_signin_pem_private_key('<script>'), 'not base64');
}

function test_signin_apple_key_problem_wants_a_p256_key()
{
	$key = signin_test_ec_key();

	pg_assert_same('', pg_signin_apple_key_problem($key['pem']), 'P-256');
	pg_assert_true(pg_signin_apple_key_problem('MIGTAgEAMBMG') !== '', 'not a key');

	$rsa = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048));
	$rsa_pem = '';
	openssl_pkey_export($rsa, $rsa_pem);

	pg_assert_true(pg_signin_apple_key_problem($rsa_pem) !== '', 'RSA');
}

function test_signin_ecdsa_der_to_jose()
{
	// R with a leading zero byte (high bit set), S shorter than 32 bytes.
	$r = "\x00" . str_repeat("\x81", 32);
	$s = str_repeat("\x05", 31);
	$der = "\x30" . chr(4 + strlen($r) + strlen($s)) . "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;

	$raw = pg_signin_ecdsa_der_to_jose($der, 32);

	pg_assert_same(64, strlen($raw), 'two 32-byte halves');
	pg_assert_same(str_repeat("\x81", 32), substr($raw, 0, 32), 'R without the sign byte');
	pg_assert_same("\x00" . str_repeat("\x05", 31), substr($raw, 32), 'S padded');
	pg_assert_same('', pg_signin_ecdsa_der_to_jose('nope'), 'not DER');
}

// The JWT Apple takes as a client secret: its header and claims, and a
// signature the key's public half verifies.
function test_signin_apple_client_secret_is_a_signed_jwt()
{
	$key = signin_test_ec_key();
	$jwt = pg_signin_apple_client_secret('com.example.signin', 'ABCDE12345', 'KEY1234567', $key['pem'], 1760000000, 600);

	$parts = explode('.', $jwt);
	pg_assert_same(3, count($parts), 'three parts');

	$header = json_decode(signin_test_b64url_decode($parts[0]), true);
	$claims = json_decode(signin_test_b64url_decode($parts[1]), true);

	pg_assert_same(array('alg' => 'ES256', 'kid' => 'KEY1234567'), $header, 'header');
	pg_assert_same(array(
		'iss' => 'ABCDE12345',
		'iat' => 1760000000,
		'exp' => 1760000600,
		'aud' => 'https://appleid.apple.com',
		'sub' => 'com.example.signin',
	), $claims, 'claims');

	$signature = signin_test_b64url_decode($parts[2]);
	pg_assert_same(64, strlen($signature), 'raw R and S');
	pg_assert_same(1, openssl_verify($parts[0] . '.' . $parts[1], signin_test_jose_to_der($signature), $key['public'], OPENSSL_ALGO_SHA256), 'verifies');

	pg_assert_same('', pg_signin_apple_client_secret('com.example.signin', 'ABCDE12345', 'KEY1234567', 'garbage', 1760000000), 'bad key');
	pg_assert_same('', pg_signin_apple_client_secret('com.example.signin', '', 'KEY1234567', $key['pem'], 1760000000), 'no team');
}

// Apple's and Microsoft's id_token claims pass the connected accounts'
// checks with their own issuers.
function test_signin_claims_checks_accept_each_issuer()
{
	$providers = pg_signin_providers();
	$now = 1760000000;

	pg_assert_same('', pg_connect_claims_problem($providers['apple'], array(
		'iss' => 'https://appleid.apple.com', 'aud' => 'com.example.signin', 'sub' => '001.a.1', 'exp' => $now + 600, 'nonce' => 'n1',
	), 'com.example.signin', 'n1', $now), 'Apple');

	pg_assert_same('', pg_connect_claims_problem($providers['microsoft'], array(
		'iss' => 'https://login.microsoftonline.com/9188040d-6c67-4c5b-b112-36a304b66dad/v2.0', 'aud' => 'app-1', 'sub' => 's', 'exp' => $now + 600, 'nonce' => 'n2',
	), 'app-1', 'n2', $now), 'Microsoft personal');

	pg_assert_true(pg_connect_claims_problem($providers['apple'], array(
		'iss' => 'https://accounts.google.com', 'aud' => 'com.example.signin', 'sub' => '1', 'exp' => $now + 600, 'nonce' => 'n1',
	), 'com.example.signin', 'n1', $now) !== '', 'another issuer');
}

function test_signin_membership_notice_names_the_provider()
{
	pg_assert_contains('Google', pg_signin_membership_notice(array('sub' => '1')), 'Google record');
	pg_assert_contains('Microsoft', pg_signin_membership_notice(array('provider' => 'microsoft')), 'Microsoft record');
	pg_assert_contains('Apple', pg_signin_membership_notice(array('provider' => 'apple')), 'Apple record');
}

// Google keeps the return address sites registered in Google Cloud Console;
// everybody else comes back to signin.php.
function test_signin_return_file_per_provider()
{
	pg_assert_same('google_auth.php', pg_signin_return_file(pg_connect_provider('google')), 'Google');
	pg_assert_same('signin.php', pg_signin_return_file(pg_connect_provider('microsoft')), 'Microsoft');
	pg_assert_same('signin.php', pg_signin_return_file(pg_connect_provider('apple')), 'Apple');
	pg_assert_same('signin.php', pg_signin_return_file(null), 'no provider');
	pg_assert_same('signin.php', pg_signin_return_file(array('signin' => array('return' => '../evil.php'))), 'not a root script');
	pg_assert_same('signin.php', pg_signin_return_file(array('signin' => array('return' => 'https://example.com/x.php'))), 'not a file name');
}

// Signing in with Google asks for no refresh token and no consent screen
// every time; connecting a Google account still does.
function test_signin_google_request_parameters()
{
	$google = pg_connect_provider('google');
	$params = pg_signin_authorize_params($google);

	pg_assert_same(array('access_type' => 'online', 'prompt' => 'select_account'), $params, 'sign-in');
	pg_assert_same('offline', $google['authorize_params']['access_type'], 'connecting keeps offline');
	pg_assert_same('consent', $google['authorize_params']['prompt'], 'connecting keeps consent');
	pg_assert_same(array('openid', 'email', 'profile'), $google['signin']['scopes'], 'scopes');
	pg_assert_true($google['signin']['pkce'], 'PKCE');

	pg_assert_same('form_post', pg_signin_authorize_params(pg_connect_provider('apple'))['response_mode'], 'Apple falls back to the provider');
	pg_assert_same(array(), pg_signin_authorize_params(null), 'nothing');
}

// Google's id_token: either issuer spelling, its email_verified claim, sub.
function test_signin_google_claims()
{
	$google = pg_connect_provider('google');
	$now = 1760000000;

	foreach (array('https://accounts.google.com', 'accounts.google.com') as $issuer) {
		pg_assert_same('', pg_connect_claims_problem($google, array(
			'iss' => $issuer, 'aud' => 'client.apps.googleusercontent.com', 'sub' => '110248495921238986420', 'exp' => $now + 600, 'nonce' => 'n',
		), 'client.apps.googleusercontent.com', 'n', $now), $issuer);
	}

	pg_assert_true(pg_connect_claims_problem($google, array(
		'iss' => 'https://accounts.google.com', 'aud' => 'another.apps.googleusercontent.com', 'sub' => '1', 'exp' => $now + 600, 'nonce' => 'n',
	), 'client.apps.googleusercontent.com', 'n', $now) !== '', 'another client');

	pg_assert_true(pg_signin_email_verified($google['signin'], array('email' => 'ada@gmail.com', 'email_verified' => true)), 'verified');
	pg_assert_false(pg_signin_email_verified($google['signin'], array('email' => 'ada@example.com', 'email_verified' => false)), 'not verified');
	pg_assert_false(pg_signin_email_verified($google['signin'], array('email' => 'ada@example.com')), 'no claim');
	pg_assert_same('110248495921238986420', pg_signin_subject($google['signin'], array('sub' => '110248495921238986420')), 'sub');
}

function test_signin_group_identities_in_button_order()
{
	$grouped = pg_signin_group_identities(array(
		array('user_id' => '7', 'provider' => 'apple'),
		array('user_id' => '7', 'provider' => 'google'),
		array('user_id' => '9', 'provider' => 'microsoft'),
		array('user_id' => '7', 'provider' => 'microsoft'),
		array('user_id' => '9', 'provider' => 'retired'),
		array('user_id' => '7', 'provider' => 'google'),
		'not a row',
	), array('google', 'microsoft', 'apple'));

	pg_assert_same(array(7 => array('google', 'microsoft', 'apple'), 9 => array('microsoft', 'retired')), $grouped, 'grouped and ordered');
	pg_assert_same(array(), pg_signin_group_identities(array(), array('google')), 'none');
}

function test_signin_identity_badges()
{
	pg_assert_contains('&mdash;', pg_signin_identity_badges(array(), 2), 'nothing connected');

	$only = pg_signin_identity_badges(array('apple'), 3);
	pg_assert_contains('Apple only', $only, 'the only way in');
	pg_assert_contains('bg-warning', $only, 'as a warning');

	$with_password = pg_signin_identity_badges(array('google'), 2);
	pg_assert_contains('>Google<', $with_password, 'Google with a password');
	pg_assert_false(strpos($with_password, 'bg-warning') !== false, 'no warning with a password');

	$two = pg_signin_identity_badges(array('google', 'microsoft'), 3);
	pg_assert_contains('>Google<', $two, 'two providers, Google');
	pg_assert_contains('>Microsoft<', $two, 'two providers, Microsoft');
	pg_assert_false(strpos($two, 'bg-warning') !== false, 'either one can go');

	pg_assert_same(pg_signin_identity_badges(array('google'), 2), pg_connected_account_label('google', 2), 'the sessions screen reads the same');
	pg_assert_contains('&mdash;', pg_connected_account_label('', 2), 'sessions screen, nothing');
}
