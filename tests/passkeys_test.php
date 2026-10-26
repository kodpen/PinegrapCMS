<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the passkey module (includes/fn/passkeys.php): the ceremonies
 * kept in the session, names, the relying party, the row a registration is
 * kept as and the record a sign-in is checked against, and both ceremonies
 * end to end with a software authenticator, each refusal made by changing
 * one thing in an otherwise valid answer. The parts that read and write
 * user_passkeys are checked on a sandbox site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

pg_passkey_verifier();

// -- a software authenticator ------------------------------------------------

// The relying party of these ceremonies.
function passkeys_test_rp()
{
	return array('id' => 'example.org', 'name' => 'Example', 'origin' => 'https://example.org');
}

// CBOR for the test's own answers: integers, text, byte strings
// (PgWebAuthnBytes) and maps (lists of key/value pairs in a stdClass).
function passkeys_test_cbor($value)
{
	if ($value instanceof PgWebAuthnBytes) {
		return passkeys_test_cbor_head(2, strlen($value->value)) . $value->value;
	}

	if ($value instanceof stdClass) {
		$encoded = passkeys_test_cbor_head(5, count($value->entries));

		foreach ($value->entries as $entry) {
			$encoded .= passkeys_test_cbor($entry[0]) . passkeys_test_cbor($entry[1]);
		}

		return $encoded;
	}

	if (is_int($value)) {
		return ($value >= 0) ? passkeys_test_cbor_head(0, $value) : passkeys_test_cbor_head(1, -1 - $value);
	}

	return passkeys_test_cbor_head(3, strlen($value)) . $value;
}

function passkeys_test_cbor_head($major, $argument)
{
	$major <<= 5;

	if ($argument < 24) {
		return chr($major | $argument);
	}

	if ($argument < 0x100) {
		return chr($major | 24) . chr($argument);
	}

	return chr($major | 25) . pack('n', $argument);
}

function passkeys_test_map($entries)
{
	$map = new stdClass();
	$map->entries = $entries;

	return $map;
}

// The ES256 credential key of the W3C Web Authentication Level 3 test vector
// "none.ES256" (section 16): its private scalar and public point, a public
// test key that signs nothing else. Written as DER here so the repository
// holds no PEM private key.
function passkeys_test_key()
{
	$der = pg_webauthn_der(0x30,
		pg_webauthn_der(0x02, "\x01")
		. pg_webauthn_der(0x04, hex2bin('6e68e7a58484a3264f66b77f5d6dc5bc36a47085b615c9727ab334e8c369c2ee'))
		. pg_webauthn_der(0xa0, pg_webauthn_der(0x06, "\x2a\x86\x48\xce\x3d\x03\x01\x07"))
		. pg_webauthn_der(0xa1, pg_webauthn_der(0x03, "\x00\x04"
			. hex2bin('afefa16f97ca9b2d23eb86ccb64098d20db90856062eb249c33a9b672f26df61')
			. hex2bin('930a56b87a2fca66334b03458abf879717c12cc68ed73290af2e2664796b9220'))));

	return openssl_pkey_get_private(pg_webauthn_pem('EC PRIVATE KEY', $der));
}

function passkeys_test_cose()
{
	return passkeys_test_cbor(passkeys_test_map(array(
		array(1, 2),
		array(3, -7),
		array(-1, 1),
		array(-2, new PgWebAuthnBytes(hex2bin('afefa16f97ca9b2d23eb86ccb64098d20db90856062eb249c33a9b672f26df61'))),
		array(-3, new PgWebAuthnBytes(hex2bin('930a56b87a2fca66334b03458abf879717c12cc68ed73290af2e2664796b9220'))),
	)));
}

function passkeys_test_credential_id()
{
	return str_repeat("\xc3", 32);
}

function passkeys_test_handle()
{
	return str_repeat("\x07", 32);
}

function passkeys_test_aaguid()
{
	return hex2bin('ea9b8d664d011d213ce4b6b48cb575d4');
}

function passkeys_test_client_data($type, $challenge, $extra = array())
{
	return json_encode(array_merge(array(
		'type' => $type,
		'challenge' => pg_webauthn_base64url_encode($challenge),
		'origin' => 'https://example.org',
		'crossOrigin' => false,
	), $extra), JSON_UNESCAPED_SLASHES);
}

// What the browser posts after navigator.credentials.create(), as
// PublicKeyCredential.toJSON() writes it: attestation "none", flags user
// present, user verified, backup eligible and backed up, attested data.
function passkeys_test_registration_json($challenge, $client_extra = array(), $flags = 0x5d)
{
	$id = passkeys_test_credential_id();
	$auth_data = hash('sha256', 'example.org', true) . chr($flags) . pack('N', 0)
		. passkeys_test_aaguid() . pack('n', strlen($id)) . $id . passkeys_test_cose();
	$object = passkeys_test_cbor(passkeys_test_map(array(
		array('fmt', 'none'),
		array('attStmt', passkeys_test_map(array())),
		array('authData', new PgWebAuthnBytes($auth_data)),
	)));

	return json_encode(array(
		'id' => pg_webauthn_base64url_encode($id),
		'rawId' => pg_webauthn_base64url_encode($id),
		'type' => 'public-key',
		'authenticatorAttachment' => 'platform',
		'clientExtensionResults' => array('credProps' => array('rk' => true)),
		'response' => array(
			'clientDataJSON' => pg_webauthn_base64url_encode(passkeys_test_client_data('webauthn.create', $challenge, $client_extra)),
			'attestationObject' => pg_webauthn_base64url_encode($object),
			'transports' => array('internal', 'hybrid', 'internal', 'telepathy'),
		),
	));
}

// What the browser posts after navigator.credentials.get(): flags (0x19:
// present, backup eligible, backed up), counter, user handle ('' for none).
function passkeys_test_assertion_input($challenge, $flags = 0x19, $count = 0, $handle = null, $client_extra = array())
{
	$client_data = passkeys_test_client_data('webauthn.get', $challenge, $client_extra);
	$auth_data = hash('sha256', 'example.org', true) . chr($flags) . pack('N', $count);
	$signature = '';
	openssl_sign($auth_data . hash('sha256', $client_data, true), $signature, passkeys_test_key(), OPENSSL_ALGO_SHA256);
	$handle = ($handle === null) ? passkeys_test_handle() : $handle;

	return array(
		'id' => pg_webauthn_base64url_encode(passkeys_test_credential_id()),
		'rawId' => pg_webauthn_base64url_encode(passkeys_test_credential_id()),
		'type' => 'public-key',
		'response' => array(
			'clientDataJSON' => pg_webauthn_base64url_encode($client_data),
			'authenticatorData' => pg_webauthn_base64url_encode($auth_data),
			'signature' => pg_webauthn_base64url_encode($signature),
			'userHandle' => ($handle === '') ? null : pg_webauthn_base64url_encode($handle),
		),
	);
}

// A ceremony as pg_passkey_ceremony_take() hands it back.
function passkeys_test_ceremony($challenge)
{
	$store = array();
	pg_passkey_ceremony_begin($store, 'signin', 5, $challenge, 1000);

	return pg_passkey_ceremony_take($store, 'signin', 5, 1001);
}

// The row the "registered" passkey is kept as: registered through the module
// with the given counter written over it.
function passkeys_test_row($sign_count = 0)
{
	$challenge = str_repeat("\x11", 32);
	$checked = pg_passkey_registration_check(passkeys_test_registration_json($challenge), passkeys_test_ceremony($challenge), passkeys_test_rp());
	$row = pg_passkey_row_values($checked['credential'], $checked['transports'], 5, passkeys_test_handle(), 'Laptop', 1700000000);

	// As the database hands it back: every value a string.
	$row = array_map('strval', $row);
	$row['sign_count'] = (string) $sign_count;

	return $row;
}

// The outcome of a sign-in answer checked against the stored row.
function passkeys_test_signin_outcome($input, $row, $discoverable = false)
{
	$response = pg_webauthn_assertion_response(json_encode($input));

	if (!$response['ok']) {
		return $response['error'];
	}

	$result = pg_passkey_assertion_check($response, passkeys_test_ceremony(str_repeat("\x22", 32)), passkeys_test_rp(), $row, $discoverable);

	return $result['ok'] ? 'ok' : $result['error'];
}

// -- ceremonies in the session -------------------------------------------------

// Begun, taken back once with its challenge as bytes, then gone.
function test_passkeys_ceremony_is_answered_once()
{
	$store = null;
	$challenge = random_bytes(32);

	pg_passkey_ceremony_begin($store, 'register', 12, $challenge, 1000, array('handle' => 'abc'));
	pg_assert_true(is_string($store['register']['challenge']) && (strpos($store['register']['challenge'], '=') === false), 'kept as base64url');

	$taken = pg_passkey_ceremony_take($store, 'register', 12, 1100);
	pg_assert_same($challenge, $taken['challenge']);
	pg_assert_same('abc', $taken['handle']);
	pg_assert_same(12, $taken['user_id']);
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'register', 12, 1100), 'a second answer');
}

// The purposes are kept apart, and a newer ceremony of the same purpose
// replaces the older one.
function test_passkeys_ceremony_purposes_and_replacement()
{
	$store = array();
	$first = str_repeat("\x01", 32);
	$second = str_repeat("\x02", 32);

	pg_passkey_ceremony_begin($store, 'register', 3, $first, 1000);
	pg_passkey_ceremony_begin($store, 'signin', 3, $second, 1000);
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'other', 3, 1000));
	pg_assert_same($second, pg_passkey_ceremony_take($store, 'signin', 3, 1000)['challenge']);
	pg_assert_same($first, pg_passkey_ceremony_take($store, 'register', 3, 1000)['challenge']);

	pg_passkey_ceremony_begin($store, 'signin', 3, $first, 1000);
	pg_passkey_ceremony_begin($store, 'signin', 3, $second, 1010);
	pg_assert_same($second, pg_passkey_ceremony_take($store, 'signin', 3, 1020)['challenge'], 'the newer one');
}

// Refused - and used up - when it was begun for another account, is older
// than its lifetime, dated ahead, or damaged.
function test_passkeys_ceremony_refusals_use_it_up()
{
	$challenge = random_bytes(32);
	$lifetime = pg_passkey_ceremony_lifetime();

	$store = array();
	pg_passkey_ceremony_begin($store, 'signin', 7, $challenge, 1000);
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 8, 1000), 'another account');
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 7, 1000), 'gone after a refusal');

	pg_passkey_ceremony_begin($store, 'signin', 0, $challenge, 1000);
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 7, 1000), 'begun without an account');

	pg_passkey_ceremony_begin($store, 'signin', 7, $challenge, 1000);
	pg_assert_true(is_array(pg_passkey_ceremony_take($store, 'signin', 7, 1000 + $lifetime)), 'at its lifetime');

	pg_passkey_ceremony_begin($store, 'signin', 7, $challenge, 1000);
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 7, 1001 + $lifetime), 'past its lifetime');

	pg_passkey_ceremony_begin($store, 'signin', 7, $challenge, 1000);
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 7, 999), 'dated ahead');

	foreach (array('', 'short', array('x'), '!!not base64url!!') as $damaged) {
		$store = array('signin' => array('user_id' => 7, 'challenge' => $damaged, 'time' => 1000));
		pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 7, 1000), var_export($damaged, true));
		pg_assert_false(isset($store['signin']), 'removed: ' . var_export($damaged, true));
	}

	$store = array('signin' => 'not a ceremony');
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 7, 1000));

	$store = 'not a store';
	pg_assert_same(null, pg_passkey_ceremony_take($store, 'signin', 7, 1000));
}

// -- names, transports, relying party ------------------------------------------

function test_passkeys_name_is_one_clean_line()
{
	pg_assert_same('Work laptop', pg_passkey_name("  Work \t\n laptop  "));
	pg_assert_same('Telefon (iş)', pg_passkey_name('Telefon (iş)'));
	pg_assert_same('a b', pg_passkey_name("a\x00\x07b"), 'control characters');
	pg_assert_same('a b', pg_passkey_name("a\u{0085}b"), 'a C1 control character');
	pg_assert_same('evil gpj.exe', pg_passkey_name("evil\u{202e}gpj.exe"), 'bidirectional override');
	pg_assert_same('a b', pg_passkey_name("a\u{200b}b"), 'zero width space');
	pg_assert_same('', pg_passkey_name(" \u{feff} "));
	pg_assert_same('', pg_passkey_name("\xc3\x28"), 'invalid UTF-8');
	pg_assert_same('', pg_passkey_name(array('x')));
	pg_assert_same('', pg_passkey_name(null));
	pg_assert_same(PG_PASSKEY_NAME_MAX, mb_strlen(pg_passkey_name(str_repeat('ş', 300))));
}

function test_passkeys_transports_read_back()
{
	pg_assert_same(array('internal', 'hybrid'), pg_passkey_transports_list('internal,hybrid,internal,bogus'));
	pg_assert_same(array(), pg_passkey_transports_list(''));
	pg_assert_same(array(), pg_passkey_transports_list(null));
}

function test_passkeys_relying_party_from_scheme_and_host()
{
	pg_assert_same(
		array('id' => 'example.com', 'name' => 'My Shop', 'origin' => 'https://example.com'),
		pg_passkey_relying_party_for('https://', 'Example.COM', "  My \n Shop "));

	pg_assert_same(
		array('id' => 'shop.example.com', 'name' => 'shop.example.com', 'origin' => 'https://shop.example.com:8443'),
		pg_passkey_relying_party_for('https://', 'shop.example.com:8443', ''), 'port in the origin only, host as the name');

	pg_assert_same('http://localhost:8080', pg_passkey_relying_party_for('http://', 'localhost:8080', 'Dev')['origin'], 'localhost over http');
	pg_assert_same(64, mb_strlen(pg_passkey_relying_party_for('https://', 'example.com', str_repeat('ğ', 100))['name']));

	pg_assert_same(null, pg_passkey_relying_party_for('http://', 'example.com', 'x'), 'plain http');
	pg_assert_same(null, pg_passkey_relying_party_for('https://', '203.0.113.7', 'x'), 'an IP address');
	pg_assert_same(null, pg_passkey_relying_party_for('https://', '', 'x'), 'no host');
	pg_assert_same(null, pg_passkey_relying_party_for('ftp://', 'example.com', 'x'));
}

function test_passkeys_user_entity()
{
	pg_assert_same(array('name' => 'ayse@example.com', 'display_name' => 'ayse'), pg_passkey_user_entity(array('username' => 'ayse', 'email' => 'ayse@example.com')));
	pg_assert_same(array('name' => 'admin', 'display_name' => 'admin'), pg_passkey_user_entity(array('username' => 'admin', 'email' => '')));
	pg_assert_same(array('name' => 'x@example.com', 'display_name' => 'x@example.com'), pg_passkey_user_entity(array('username' => '', 'email' => 'x@example.com')));
}

// -- registration ----------------------------------------------------------------

// A registration answer is verified and kept as a row of text values: the
// unique key is the SHA-256 of the raw ID, binary values base64url, the
// reported transports filtered, the backup flags as registered.
function test_passkeys_registration_is_kept_as_a_row()
{
	$challenge = random_bytes(32);
	$checked = pg_passkey_registration_check(passkeys_test_registration_json($challenge), passkeys_test_ceremony($challenge), passkeys_test_rp());

	pg_assert_true($checked['ok'], 'verified: ' . ($checked['error'] ?? ''));
	pg_assert_same(array('internal', 'hybrid'), $checked['transports']);

	$row = pg_passkey_row_values($checked['credential'], $checked['transports'], 5, passkeys_test_handle(), 'Laptop', 1700000000);

	pg_assert_same(array('user_id', 'credential_hash', 'credential_id', 'public_key', 'alg', 'sign_count', 'transports', 'aaguid',
		'backup_eligible', 'backup_state', 'user_handle', 'name', 'created_at', 'last_used_at'), array_keys($row), 'the columns of user_passkeys');
	pg_assert_same(hash('sha256', passkeys_test_credential_id()), $row['credential_hash']);
	pg_assert_same(passkeys_test_credential_id(), pg_webauthn_base64url_decode($row['credential_id']));
	pg_assert_same(64, strlen($row['credential_hash']));
	pg_assert_contains('-----BEGIN PUBLIC KEY-----', $row['public_key']);
	pg_assert_same(-7, $row['alg']);
	pg_assert_same(0, $row['sign_count']);
	pg_assert_same('internal,hybrid', $row['transports']);
	pg_assert_same('ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4', $row['aaguid']);
	pg_assert_same(1, $row['backup_eligible']);
	pg_assert_same(1, $row['backup_state']);
	pg_assert_same(passkeys_test_handle(), pg_webauthn_base64url_decode($row['user_handle']));
	pg_assert_same(array(5, 'Laptop', 1700000000, 0), array($row['user_id'], $row['name'], $row['created_at'], $row['last_used_at']));
}

// Refused: another challenge, another origin, a sign-in answer, no user
// presence, a body that is not a credential.
function test_passkeys_registration_refusals()
{
	$challenge = random_bytes(32);
	$ceremony = passkeys_test_ceremony($challenge);
	$rp = passkeys_test_rp();

	$outcome = function ($input, $ceremony_used = null) use ($ceremony, $rp) {
		$result = pg_passkey_registration_check($input, ($ceremony_used !== null) ? $ceremony_used : $ceremony, $rp);
		return $result['ok'] ? 'ok' : $result['error'];
	};

	pg_assert_same('ok', $outcome(passkeys_test_registration_json($challenge)));
	pg_assert_same('challenge', $outcome(passkeys_test_registration_json(random_bytes(32))));
	pg_assert_same('origin', $outcome(passkeys_test_registration_json($challenge, array('origin' => 'https://evil.example'))));
	pg_assert_same('type', $outcome(passkeys_test_registration_json($challenge, array('type' => 'webauthn.get'))));
	pg_assert_same('user_present', $outcome(passkeys_test_registration_json($challenge, array(), 0x5c)));
	pg_assert_same('response', $outcome('{"type":"public-key"}'));
	pg_assert_same('response', $outcome(''));
	pg_assert_same('response', $outcome(json_encode(passkeys_test_assertion_input($challenge))), 'a sign-in answer');

	// The relying party of another site.
	$other = array('id' => 'example.net', 'name' => 'x', 'origin' => 'https://example.org');
	$result = pg_passkey_registration_check(passkeys_test_registration_json($challenge), $ceremony, $other);
	pg_assert_same('rp_id', $result['error']);
}

// -- sign-in -----------------------------------------------------------------------

// A stored row is read back as the verifier's record: binary values decoded,
// the database's strings passed as they are.
function test_passkeys_record_from_a_row()
{
	$row = passkeys_test_row(4);
	$record = pg_passkey_record($row);

	pg_assert_same(passkeys_test_credential_id(), $record['id']);
	pg_assert_same(passkeys_test_handle(), $record['user_handle']);
	pg_assert_same('-7', $record['alg']);
	pg_assert_same('4', $record['sign_count']);
	pg_assert_same('1', $record['backup_eligible']);
	pg_assert_same($row['public_key'], $record['public_key']);

	$empty = pg_passkey_record(array());
	pg_assert_same(array('id' => '', 'public_key' => '', 'alg' => null, 'sign_count' => null, 'backup_eligible' => null, 'user_handle' => ''), $empty);
	pg_assert_same('', pg_passkey_record(array('credential_id' => '!!', 'user_handle' => '%%'))['id'], 'damaged base64url');
}

// The second step of a sign-in: the stored passkey verifies the answer, with
// or without the person verified on the device; the counter moves forward.
function test_passkeys_signin_as_second_step()
{
	$challenge = str_repeat("\x22", 32);
	$row = passkeys_test_row(0);

	$response = pg_webauthn_assertion_response(json_encode(passkeys_test_assertion_input($challenge, 0x19, 0)));
	$result = pg_passkey_assertion_check($response, passkeys_test_ceremony($challenge), passkeys_test_rp(), $row, false);

	pg_assert_true($result['ok'], 'verified: ' . ($result['error'] ?? ''));
	pg_assert_same(0, $result['sign_count']);
	pg_assert_false($result['user_verified']);
	pg_assert_true($result['backup_state']);

	pg_assert_same('ok', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x1d, 0), $row), 'user verified too');
	pg_assert_same('ok', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x09, 0), $row), 'no longer backed up');
	pg_assert_same('ok', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19, 0, ''), $row), 'no user handle given');
	pg_assert_same('ok', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19, 8), passkeys_test_row(7)), 'counter moved on');
}

// Each refusal of a sign-in answer.
function test_passkeys_signin_refusals()
{
	$challenge = str_repeat("\x22", 32);
	$row = passkeys_test_row(0);

	pg_assert_same('challenge', passkeys_test_signin_outcome(passkeys_test_assertion_input(str_repeat("\x33", 32)), $row));
	pg_assert_same('origin', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19, 0, null, array('origin' => 'https://example.org.evil.test')), $row));
	pg_assert_same('cross_origin', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19, 0, null, array('crossOrigin' => true)), $row));
	pg_assert_same('user_present', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x18), $row));
	pg_assert_same('backup_flags', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x01), $row), 'eligibility changed');
	pg_assert_same('user_handle', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19, 0, str_repeat("\x08", 32)), $row), 'another account\'s handle');
	pg_assert_same('sign_count', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19, 3), passkeys_test_row(5)), 'counter went back');
	pg_assert_same('sign_count', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19, 0), passkeys_test_row(5)), 'counter stopped');

	$input = passkeys_test_assertion_input($challenge);
	$input['response']['signature'] = pg_webauthn_base64url_encode(str_repeat("\x30", 70));
	pg_assert_same('signature', passkeys_test_signin_outcome($input, $row));

	// A row whose key is another credential's.
	$other = $row;
	$other['credential_id'] = pg_webauthn_base64url_encode(str_repeat("\xd4", 32));
	pg_assert_same('credential', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge), $other));

	// A row the database damaged: no counter, no algorithm.
	$damaged = $row;
	unset($damaged['sign_count']);
	pg_assert_same('credential', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge), $damaged));
	$damaged = $row;
	$damaged['alg'] = '';
	pg_assert_same('algorithm', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge), $damaged));
}

// With no account known beforehand the passkey is the only factor: the
// answer must name its account by the user handle and verify the person.
function test_passkeys_signin_without_an_account()
{
	$challenge = str_repeat("\x22", 32);
	$row = passkeys_test_row(0);

	pg_assert_same('ok', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x1d), $row, true));
	pg_assert_same('user_verified', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x19), $row, true));
	pg_assert_same('user_handle', passkeys_test_signin_outcome(passkeys_test_assertion_input($challenge, 0x1d, 0, ''), $row, true));
}

// -- words for the visitor -----------------------------------------------------------

// The codes about the site, the session and the account's own list have
// words of their own; every refusal of the credential itself reads the same,
// so it never tells whose passkey it was.
function test_passkeys_refusal_words()
{
	$own = array();

	foreach (array('unavailable', 'host', 'challenge', 'none', 'limit', 'duplicate') as $code) {
		$own[$code] = pg_passkey_refusal($code, 'signin');
		pg_assert_true($own[$code] !== '', $code);
	}

	pg_assert_same(count($own), count(array_unique($own)), 'each its own sentence');

	$plain = pg_passkey_refusal('signature', 'signin');

	foreach (array('credential', 'user_handle', 'sign_count', 'backup_flags', 'origin', 'response', 'storage', 'anything') as $code) {
		pg_assert_same($plain, pg_passkey_refusal($code, 'signin'), $code);
	}

	pg_assert_false(in_array($plain, $own, true));
	pg_assert_true(pg_passkey_refusal('signature', 'register') !== $plain, 'registration has its own sentence');
}

// -- the account screen --------------------------------------------------------------

// A change to an account's passkeys asks for the proofs the account has: its
// password unless it is Google-only (algo 3), a code while two-step
// verification is on.
function test_passkeys_reauth_fields()
{
	pg_assert_same(array('password' => true, 'code' => true), pg_passkey_reauth_fields(1, true), 'password and two-step');
	pg_assert_same(array('password' => true, 'code' => false), pg_passkey_reauth_fields(1, false), 'password only');
	pg_assert_same(array('password' => false, 'code' => true), pg_passkey_reauth_fields(3, true), 'Google-only with two-step');
	pg_assert_same(array('password' => false, 'code' => false), pg_passkey_reauth_fields('3', 0), 'Google-only without two-step');
}

// -- sign-in with a passkey alone ----------------------------------------------------

// The account a passkey names must still be there, and a member whose
// address is blocked is refused as the password sign-in refuses them; staff
// are never stopped by the block list.
function test_passkeys_passwordless_account_refusal()
{
	pg_assert_same('account', pg_passkey_passwordless_refusal(null, false));
	pg_assert_same('account', pg_passkey_passwordless_refusal(array(), false));
	pg_assert_same('', pg_passkey_passwordless_refusal(array('user_id' => 7, 'user_role' => 3), false));
	pg_assert_same('blocked', pg_passkey_passwordless_refusal(array('user_id' => 7, 'user_role' => 3), true));
	pg_assert_same('', pg_passkey_passwordless_refusal(array('user_id' => 7, 'user_role' => 2), true));
	pg_assert_same('', pg_passkey_passwordless_refusal(array('user_id' => 7, 'user_role' => 0), true));
}

// A gone or blocked account reads like any refused passkey: the answer never
// tells whose passkey it was.
function test_passkeys_passwordless_refusal_words()
{
	$plain = pg_passkey_refusal('signature', 'signin');

	pg_assert_same($plain, pg_passkey_refusal('account', 'signin'));
	pg_assert_same($plain, pg_passkey_refusal('blocked', 'signin'));
	pg_assert_true(pg_passkey_refusal('passwordless_off', 'signin') !== $plain, 'switched off has its own sentence');
}

// Passkeys are offered in the username field's autofill only on screens that
// only sign in (the login screen, the login widget); a screen that also
// registers keeps its address field for the new account.
function test_passkeys_autofill_only_on_sign_in_screens()
{
	pg_assert_true(pg_passkey_autofill_for(false));
	pg_assert_true(pg_passkey_autofill_for('none'));
	pg_assert_false(pg_passkey_autofill_for('create'));
	pg_assert_false(pg_passkey_autofill_for('membership'));
}
