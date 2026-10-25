<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sign-in rules that need no database (includes/fn/signin.php):
 * the provider definitions, Google's return address and request, what a
 * screen may do with an unknown identity, when an e-mail address counts as
 * verified, who the person is, the names for the address book, the pasted
 * Apple key and the client secret JWT signed with it, the badges of the
 * user and session lists, when a "Connect" button may start and what its
 * answer does, and which account a verified address may be tied to (the
 * stored address, letter case aside; never an accent-folded look-alike).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A new key pair for $arguments. OpenSSL reads a configuration file before it
// makes a key, and a Windows PHP ships none, so the call is tried once more
// with the file the software ships for that case (includes/openssl.cnf), as
// pg_push_create_vapid_keys() does. The Apple sign-in code itself only reads
// a pasted key and signs with it, which needs no configuration file.
function signin_test_pkey_new($arguments)
{
	$key = @openssl_pkey_new($arguments);

	if (!$key) {
		$arguments['config'] = PG_FUNCTIONS_DIR . '/includes/openssl.cnf';
		$key = openssl_pkey_new($arguments);
	}

	return array('key' => $key, 'arguments' => $arguments);
}

// A fresh P-256 key, as the .p8 file Apple hands out holds one: PKCS#8
// (BEGIN PRIVATE KEY) around the SEC1 key with its curve and public point.
// The DER is put together here because openssl_pkey_export() writes an EC
// key in the traditional SEC1 form (BEGIN EC PRIVATE KEY) before PHP 8.1 and
// in PKCS#8 only from 8.1 on, and pg_signin_pem_private_key() puts any pasted
// body under the PKCS#8 label, where a SEC1 body cannot be read.
function signin_test_ec_key()
{
	$new = signin_test_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'));
	$key = $new['key'];
	$details = openssl_pkey_get_details($key);
	$curve = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
	$point = "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

	// ECPrivateKey (RFC 5915): version 1, the 32-byte scalar, [0] the curve,
	// [1] the public point.
	$sec1 = "\x02\x01\x01\x04\x20" . str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT)
		. "\xa0\x0a" . $curve . "\xa1\x44\x03\x42\x00" . $point;
	$sec1 = "\x30" . chr(strlen($sec1)) . $sec1;

	// PrivateKeyInfo (RFC 5208): version 0, id-ecPublicKey on that curve, the
	// key above.
	$info = "\x02\x01\x00\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" . $curve . "\x04" . chr(strlen($sec1)) . $sec1;
	$pem = "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode("\x30\x81" . chr(strlen($info)) . $info), 64, "\n") . "-----END PRIVATE KEY-----\n";

	return array('pem' => $pem, 'public' => $details['key']);
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

	// The export reads the configuration file too, so it is handed the one the
	// key was made with.
	$rsa = signin_test_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048));
	$rsa_pem = '';
	openssl_pkey_export($rsa['key'], $rsa_pem, null, $rsa['arguments']);

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

// The way back from the failure screen: a page of this site only, never
// another site, a script-relative trick or the sign-in's own scripts.
function test_signin_referer_path_keeps_this_site_only()
{
	pg_assert_same('/giris?send_to=%2Fhesabim', pg_signin_referer_path('https://www.example.com/giris?send_to=%2Fhesabim', 'www.example.com'), 'the login page');
	pg_assert_same('/pinegrap/index.php', pg_signin_referer_path('https://Example.com/pinegrap/index.php', 'example.com:443'), 'host case and port');
	pg_assert_same('/', pg_signin_referer_path('https://example.com', 'example.com'), 'no path');
	pg_assert_same('', pg_signin_referer_path('https://evil.test/giris', 'example.com'), 'another site');
	pg_assert_same('', pg_signin_referer_path('https://example.com.evil.test/giris', 'example.com'), 'a look-alike host');
	pg_assert_same('', pg_signin_referer_path('https://accounts.google.com/o/oauth2/v2/auth', 'example.com'), 'the provider');
	pg_assert_same('', pg_signin_referer_path('javascript://example.com/%0Aalert(1)', 'example.com'), 'not a web address');
	pg_assert_same('', pg_signin_referer_path("https://example.com/a\r\nLocation: x", 'example.com'), 'header splitting');
	pg_assert_same('', pg_signin_referer_path('https://example.com/pinegrap/signin.php?provider=google', 'example.com'), 'the start script');
	pg_assert_same('', pg_signin_referer_path('https://example.com/pinegrap/google_auth.php', 'example.com'), 'the return script');
	pg_assert_same('', pg_signin_referer_path('', 'example.com'), 'no referrer');
	pg_assert_same('', pg_signin_referer_path(array('x'), 'example.com'), 'not a string');
}

// The failure screen says what happened in plain, escaped text and offers
// the way back first.
function test_signin_failure_card()
{
	$card = pg_signin_failure_card('Sign-in did not complete', 'You signed in to Google as <b>x@example.com</b>', array(
		array('url' => '/giris?a=1&b=2', 'label' => 'Go Back', 'primary' => true),
		array('url' => '/pinegrap/signin.php?provider=google', 'label' => 'Try again with Google'),
		array('url' => '', 'label' => 'dropped'),
	));

	pg_assert_contains('&lt;b&gt;x@example.com&lt;/b&gt;', $card, 'the message is escaped');
	pg_assert_contains('role="alert"', $card, 'an error is announced');
	pg_assert_contains('bi-exclamation-triangle', $card, 'an error mark');
	pg_assert_contains('class="btn btn-primary" href="/giris?a=1&amp;b=2"', $card, 'the way back comes first');
	pg_assert_contains('class="btn btn-outline-secondary" href="/pinegrap/signin.php?provider=google"', $card, 'another try');
	pg_assert_false(strpos($card, 'dropped') !== false, 'a link without an address drops');
	pg_assert_true(strpos($card, 'Go Back') < strpos($card, 'Try again with Google'), 'back before again');

	$cancelled = pg_signin_failure_card('Sign-in cancelled', 'The sign-in was cancelled at Google, so you are not signed in.', array(), true);
	pg_assert_contains('role="status"', $cancelled, 'a cancel is a notice');
	pg_assert_contains('bi-info-circle', $cancelled, 'a notice mark');
	pg_assert_false(strpos($cancelled, 'd-flex') !== false, 'no button row without links');
}

function test_signin_button_html_is_one_markup_for_every_provider()
{
	$google = pg_signin_button_html('google', '/pinegrap/google_auth.php?send_to=/a&b=1', 'Sign in with Google');
	$apple = pg_signin_button_html('apple', '/pinegrap/signin.php?provider=apple', 'Sign in with <Apple>');

	pg_assert_contains('class="pg-signin-button pg-signin-google"', $google, 'a shared class and the provider class');
	pg_assert_contains('href="/pinegrap/google_auth.php?send_to=/a&amp;b=1"', $google, 'the address is escaped');
	pg_assert_contains('min-width:240px', $google, 'a common width so a column lines up');
	pg_assert_contains('#4285F4', strtoupper($google), 'Google keeps its own mark');
	pg_assert_false(strpos($google, 'gap:') !== false, 'no flex gap, which older browsers ignore');
	pg_assert_contains('background:#000 !important', $apple, 'Apple is black');
	pg_assert_contains('Sign in with &lt;Apple&gt;', $apple, 'the label is escaped');

	foreach (array('google', 'microsoft', 'apple', 'unknown') as $key) {
		$style = pg_signin_button_style($key);
		pg_assert_true(isset($style['border'], $style['background'], $style['color'], $style['font']), $key . ' has a complete style');
	}
}

// The same button, aligned to the left of an account's list of sign-ins.
function test_signin_button_html_left_for_an_account_list()
{
	pg_assert_contains('text-align:center', pg_signin_button_html('google', '/x', 'Sign in with Google'), 'centred under a sign-in form');
	pg_assert_contains('text-align:left', pg_signin_button_html('google', '/x', 'Connect with Google', 'left'), 'left in an account list');
	pg_assert_contains('text-align:center', pg_signin_button_html('google', '/x', 'Sign in with Google', 'right'), 'anything else is centred');
}

// A "Connect" button starts only for the account signed in now, with this
// session's token, and never for an operator signed in as the person.
function test_signin_connect_problem()
{
	pg_assert_same('', pg_signin_connect_problem(7, false, 'abc', 'abc', false), 'signed in, own token, not connected');
	pg_assert_same('signed_out', pg_signin_connect_problem(0, false, 'abc', 'abc', false), 'nobody signed in');
	pg_assert_same('impersonated', pg_signin_connect_problem(7, true, 'abc', 'abc', false), 'an operator signed in as the person');
	pg_assert_same('token', pg_signin_connect_problem(7, false, 'abd', 'abc', false), 'another token');
	pg_assert_same('token', pg_signin_connect_problem(7, false, '', '', false), 'no token in the session');
	pg_assert_same('token', pg_signin_connect_problem(7, false, array('abc'), 'abc', false), 'a token sent as an array');
	pg_assert_same('connected', pg_signin_connect_problem(7, false, 'abc', 'abc', true), 'already connected at the provider');
}

// What the answer of a "Connect" does: ties the identity to the account that
// pressed the button, whatever its address, unless another account holds it.
function test_signin_connect_outcome()
{
	pg_assert_same('connect', pg_signin_connect_outcome(7, 7, false, 0, false), 'a free identity is tied');
	pg_assert_same('already', pg_signin_connect_outcome(7, 7, false, 7, true), 'already this account\'s');
	pg_assert_same('other_account', pg_signin_connect_outcome(7, 7, false, 9, false), 'held by another account: refused');
	pg_assert_same('other_account', pg_signin_connect_outcome(7, 7, false, 9, true), 'held by another account, whatever this one has');
	pg_assert_same('has_other', pg_signin_connect_outcome(7, 7, false, 0, true), 'this account holds another person at the provider');
	pg_assert_same('signed_out', pg_signin_connect_outcome(7, 0, false, 0, false), 'signed out meanwhile');
	pg_assert_same('signed_out', pg_signin_connect_outcome(7, 8, false, 0, false), 'another account signed in meanwhile');
	pg_assert_same('signed_out', pg_signin_connect_outcome(0, 0, false, 0, false), 'no account named');
	pg_assert_same('impersonated', pg_signin_connect_outcome(7, 7, true, 0, false), 'an operator signed in as the person');
}

// An address the provider vouches for is an account's address only when the
// stored one is the same apart from letter case: user.user_email's collation
// also folds accents, and "jane@sirket.com" is not "jane@şirket.com".
function test_signin_same_address_user_needs_the_stored_address()
{
	$rows = array(
		array('user_id' => '4', 'user_email' => 'jane@şirket.com'),
		array('user_id' => '9', 'user_email' => 'Jane@Sirket.com'),
	);
	pg_assert_same(9, pg_signin_same_address_user($rows, 'jane@sirket.com'), 'the exact address, letter case aside, even after a look-alike');
	pg_assert_same(4, pg_signin_same_address_user($rows, 'JANE@ŞIRKET.COM'), 'letter case aside in any script');
	pg_assert_same(0, pg_signin_same_address_user(array($rows[0]), 'jane@sirket.com'), 'an accent-folded look-alike is not found');
	pg_assert_same(0, pg_signin_same_address_user(array(array('user_id' => '5', 'user_email' => 'jane@example.com')), 'jäne@example.com'), 'accent in the name');
	pg_assert_same(0, pg_signin_same_address_user(array(), 'jane@sirket.com'), 'no rows');
	pg_assert_same(0, pg_signin_same_address_user($rows, ''), 'no address');
	pg_assert_same(0, pg_signin_same_address_user(array('x', array('user_id' => '3')), 'jane@sirket.com'), 'malformed rows');
}

// Why a verified address nobody matched is refused: the same account tied to
// another person at the provider, or only a look-alike in use.
function test_signin_address_refusal()
{
	$exact = array('user_id' => '9', 'user_email' => 'jane@sirket.com');
	$alike = array('user_id' => '4', 'user_email' => 'jane@şirket.com');
	pg_assert_same('', pg_signin_address_refusal('jane@sirket.com', array()), 'nobody uses it');
	pg_assert_same('connected', pg_signin_address_refusal('jane@sirket.com', array($exact)), 'the address is an account\'s');
	pg_assert_same('connected', pg_signin_address_refusal('Jane@Sirket.com', array($alike, $exact)), 'the exact one decides over a look-alike');
	pg_assert_same('in_use', pg_signin_address_refusal('jane@sirket.com', array($alike)), 'only a look-alike');
}

// The shared rule: letter case aside, never an accent-folded look-alike; the
// forgot password name answers the same.
function test_signin_email_address_matches()
{
	pg_assert_true(pg_email_address_matches('Jane@Example.COM', 'jane@example.com'), 'case only');
	pg_assert_true(pg_email_address_matches(' jane@example.com ', 'jane@example.com'), 'spaces');
	pg_assert_false(pg_email_address_matches('jane@sirket.com', 'jane@şirket.com'), 'accent in the stored domain');
	pg_assert_false(pg_email_address_matches('jane@exámple.com', 'jane@example.com'), 'accent in the given domain');
	pg_assert_false(pg_email_address_matches('', ''), 'empty');
	pg_assert_false(pg_email_address_matches(array('a@b.c'), 'a@b.c'), 'not a string');
	pg_assert_same(pg_email_address_matches('jane@sirket.com', 'jane@şirket.com'), pg_password_reset_address_matches('jane@sirket.com', 'jane@şirket.com'), 'reset name is the same rule');
}

// pg_signin_match_user() ties an identity only to a row whose stored address
// passes the exact-match rule, and the callback refuses a look-alike before
// anything is opened.
function test_signin_match_user_source_uses_the_exact_match()
{
	$fn = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/signin.php');
	$start = strpos($fn, 'function pg_signin_match_user(');
	$end = ($start === false) ? false : strpos($fn, "\n}\n", $start);
	$body = ($end === false) ? '' : substr($fn, $start, $end - $start);
	pg_assert_true(strpos($body, 'pg_signin_same_address_user(db_items(') !== false, 'the lookup goes through the exact-match rule');
	pg_assert_true(strpos($body, 'SELECT user.user_id, user.user_email') !== false, 'the lookup reads the stored address');
	pg_assert_same(0, preg_match('/\$match_id\s*=\s*\(int\)\s*db_value\(/', $body), 'no first row taken as is');

	$same = strpos($fn, 'function pg_signin_same_address_user(');
	pg_assert_true($same !== false && strpos(substr($fn, $same, 600), 'pg_email_address_matches(') !== false, 'the rule is pg_email_address_matches()');

	$cb = (string) file_get_contents(PG_FUNCTIONS_DIR . '/signin.php');
	$refusal = strpos($cb, 'pg_signin_address_refusal($email, pg_signin_address_accounts($email))');
	$in_use = strpos($cb, "if (\$address_refusal === 'in_use')");
	$membership = strpos($cb, '$_SESSION[\'software\'][\'google_membership_pending\'] = array(');
	$create = strpos($cb, 'pg_signin_create_member($identity)');
	pg_assert_true($refusal !== false && $in_use !== false && $membership !== false && $create !== false
		&& $refusal < $in_use && $in_use < $membership && $in_use < $create, 'a look-alike is refused before an account is handed over or opened');
}
