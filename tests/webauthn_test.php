<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WebAuthn verifier (includes/webauthn/): base64url, the CBOR
 * reader (the RFC 8949 Appendix A examples and what it refuses), COSE keys,
 * the test vectors of the W3C Web Authentication Level 3 specification
 * (section 16) for both ceremonies, and every refusal of the registration and
 * sign-in checks, each made by changing one thing in a response signed with a
 * W3C test key, so the check under test meets an otherwise valid response.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/webauthn/webauthn.php');

// -- helpers -----------------------------------------------------------------

// The relying party every W3C vector was made for.
function webauthn_test_expected($challenge, $extra = array())
{
	return array_merge(array(
		'challenge' => $challenge,
		'origin' => 'https://example.org',
		'rp_id' => 'example.org',
	), $extra);
}

// One W3C vector (webauthn_test_vectors()), its hex decoded.
function webauthn_test_vector($name)
{
	$vectors = webauthn_test_vectors();
	$vector = $vectors[$name];

	foreach ($vector as $ceremony => $values) {
		foreach ($values as $key => $hex) {
			$vector[$ceremony][$key] = (string) hex2bin($hex);
		}
	}

	return $vector;
}

function webauthn_test_registration_response($vector)
{
	return array(
		'client_data_json' => $vector['registration']['clientDataJSON'],
		'attestation_object' => $vector['registration']['attestationObject'],
		'credential_id' => $vector['registration']['credential_id'],
	);
}

function webauthn_test_assertion_response($vector)
{
	return array(
		'client_data_json' => $vector['authentication']['clientDataJSON'],
		'authenticator_data' => $vector['authentication']['authenticatorData'],
		'signature' => $vector['authentication']['signature'],
		'credential_id' => $vector['registration']['credential_id'],
	);
}

// The registration of a W3C vector, checked as a site would.
function webauthn_test_register($name, $extra = array())
{
	$vector = webauthn_test_vector($name);

	return pg_webauthn_verify_registration(
		webauthn_test_registration_response($vector),
		webauthn_test_expected($vector['registration']['challenge'], $extra));
}

// The authenticator data inside a vector's attestation object.
function webauthn_test_vector_auth_data($vector)
{
	$object = pg_webauthn_cbor_decode($vector['registration']['attestationObject']);

	return $object['authData']->value;
}

// The credential record a vector's own authenticator data describes, for
// vectors whose registration is refused (another format, a frame).
function webauthn_test_record_from_auth_data($vector)
{
	$auth = pg_webauthn_auth_data(webauthn_test_vector_auth_data($vector));
	$key = pg_webauthn_cose_key($auth['public_key']);

	return array(
		'id' => $auth['credential_id'],
		'public_key' => $key['pem'],
		'alg' => $key['alg'],
		'sign_count' => $auth['sign_count'],
		'backup_eligible' => $auth['backup_eligible'],
	);
}

// The error code CBOR decoding throws, '' when the bytes decode.
function webauthn_test_cbor_error($hex, $code = 'cbor')
{
	try {
		pg_webauthn_cbor_decode((string) hex2bin($hex), $code);
	} catch (PgWebAuthnException $exception) {
		return $exception->getMessage();
	}

	return '';
}

// The error code a COSE key is refused with, '' when it is accepted.
function webauthn_test_cose_error($entries)
{
	try {
		pg_webauthn_cose_key(pg_webauthn_cbor_decode(webauthn_test_cbor(webauthn_test_map($entries))));
	} catch (PgWebAuthnException $exception) {
		return $exception->getMessage();
	}

	return '';
}

// A CBOR map with its entries in the order given; a PHP array could not tell
// an empty map from an empty list, or keep the order of integer keys.
function webauthn_test_map($entries)
{
	$map = new stdClass();
	$map->entries = $entries;

	return $map;
}

// CBOR for test input: integers, text (PHP strings), PgWebAuthnBytes, lists
// (PHP arrays), maps (webauthn_test_map()), booleans and null.
function webauthn_test_cbor($value)
{
	if ($value instanceof PgWebAuthnBytes) {
		return webauthn_test_cbor_head(2, strlen($value->value)) . $value->value;
	}

	if ($value instanceof stdClass) {
		$encoded = webauthn_test_cbor_head(5, count($value->entries));

		foreach ($value->entries as $entry) {
			$encoded .= webauthn_test_cbor($entry[0]) . webauthn_test_cbor($entry[1]);
		}

		return $encoded;
	}

	if (is_array($value)) {
		$encoded = webauthn_test_cbor_head(4, count($value));

		foreach ($value as $item) {
			$encoded .= webauthn_test_cbor($item);
		}

		return $encoded;
	}

	if (is_int($value)) {
		return ($value >= 0) ? webauthn_test_cbor_head(0, $value) : webauthn_test_cbor_head(1, -1 - $value);
	}

	if (is_bool($value)) {
		return $value ? "\xf5" : "\xf4";
	}

	if ($value === null) {
		return "\xf6";
	}

	return webauthn_test_cbor_head(3, strlen($value)) . $value;
}

function webauthn_test_cbor_head($major, $argument)
{
	$major <<= 5;

	if ($argument < 24) {
		return chr($major | $argument);
	}

	if ($argument < 0x100) {
		return chr($major | 24) . chr($argument);
	}

	if ($argument < 0x10000) {
		return chr($major | 25) . pack('n', $argument);
	}

	if ($argument < 0x100000000) {
		return chr($major | 26) . pack('N', $argument);
	}

	return chr($major | 27) . pack('J', $argument);
}

// An attestation object made of its three parts.
function webauthn_test_attestation_object($format, $statement_entries, $auth_data)
{
	return webauthn_test_cbor(webauthn_test_map(array(
		array('fmt', $format),
		array('attStmt', webauthn_test_map($statement_entries)),
		array('authData', new PgWebAuthnBytes($auth_data)),
	)));
}

// An EC P-256 private key from its scalar and public point, written as DER
// here so the repository holds no PEM private key.
function webauthn_test_ec_key($d, $x, $y)
{
	$der = pg_webauthn_der(0x30,
		pg_webauthn_der(0x02, "\x01")
		. pg_webauthn_der(0x04, hex2bin($d))
		. pg_webauthn_der(0xa0, pg_webauthn_der(0x06, "\x2a\x86\x48\xce\x3d\x03\x01\x07"))
		. pg_webauthn_der(0xa1, pg_webauthn_der(0x03, "\x00\x04" . hex2bin($x) . hex2bin($y))));

	return openssl_pkey_get_private(pg_webauthn_pem('EC PRIVATE KEY', $der));
}

// The credential key of the W3C "none.ES256" vector (its
// credential_private_key and the point in its attestation object).
function webauthn_test_credential_key()
{
	return webauthn_test_ec_key(
		'6e68e7a58484a3264f66b77f5d6dc5bc36a47085b615c9727ab334e8c369c2ee',
		'afefa16f97ca9b2d23eb86ccb64098d20db90856062eb249c33a9b672f26df61',
		'930a56b87a2fca66334b03458abf879717c12cc68ed73290af2e2664796b9220');
}

// The key the certificates of webauthn_test_certificates() were made for;
// a test key that signs nothing else.
function webauthn_test_attestation_key()
{
	return webauthn_test_ec_key(
		'ab86a40cdbb73e78e34b29ad2f84b1e1e5c14ce364b0302394e01e16c088a1d0',
		'ed24f6d7bed0556defc411863dc2814d958248b9aaf600368ba5cc4c0de06a1e',
		'64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30');
}

// The COSE key of the "none.ES256" credential.
function webauthn_test_credential_cose()
{
	return webauthn_test_cbor(webauthn_test_map(array(
		array(1, 2),
		array(3, -7),
		array(-1, 1),
		array(-2, new PgWebAuthnBytes(hex2bin('afefa16f97ca9b2d23eb86ccb64098d20db90856062eb249c33a9b672f26df61'))),
		array(-3, new PgWebAuthnBytes(hex2bin('930a56b87a2fca66334b03458abf879717c12cc68ed73290af2e2664796b9220'))),
	)));
}

// An ES256 signature (DER) of $data.
function webauthn_test_sign($key, $data)
{
	$signature = '';
	openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);

	return $signature;
}

// The same bytes with the lowest bit of the last one turned over.
function webauthn_test_flip($bytes)
{
	return substr($bytes, 0, -1) . chr(ord(substr($bytes, -1)) ^ 0x01);
}

// Authenticator data with the given flags, counter and attested credential.
function webauthn_test_auth_data($flags, $count = 0, $attested = '', $rp_id = 'example.org')
{
	return hash('sha256', $rp_id, true) . chr($flags) . pack('N', $count) . $attested;
}

// Attested credential data: AAGUID, credential ID length and ID, COSE key.
function webauthn_test_attested($aaguid, $credential_id, $cose)
{
	return $aaguid . pack('n', strlen($credential_id)) . $credential_id . $cose;
}

// The challenge of the ceremonies made here.
function webauthn_test_challenge()
{
	return str_repeat("\x5a", 32);
}

function webauthn_test_client_data($type, $challenge, $extra = array())
{
	return json_encode(array_merge(array(
		'type' => $type,
		'challenge' => pg_webauthn_base64url_encode($challenge),
		'origin' => 'https://example.org',
		'crossOrigin' => false,
	), $extra), JSON_UNESCAPED_SLASHES);
}

// The "none.ES256" credential as a site keeps it after registering it, with
// a user handle.
function webauthn_test_credential()
{
	$result = webauthn_test_register('none.ES256');
	$credential = $result['credential'];
	$credential['user_handle'] = str_repeat("\x07", 32);

	return $credential;
}

// A sign-in signed with the "none.ES256" credential key after its parts were
// set: flags (0x19: present, backup eligible, backed up, as registered),
// counter, client data members, relying party ID, and bytes after the counter
// (extensions, or attested credential data that does not belong there).
function webauthn_test_signed_assertion($flags = 0x19, $count = 0, $client_extra = array(), $rp_id = 'example.org', $tail = '')
{
	$client_data = webauthn_test_client_data('webauthn.get', webauthn_test_challenge(), $client_extra);
	$auth_data = webauthn_test_auth_data($flags, $count, $tail, $rp_id);

	return array(
		'client_data_json' => $client_data,
		'authenticator_data' => $auth_data,
		'signature' => webauthn_test_sign(webauthn_test_credential_key(), $auth_data . hash('sha256', $client_data, true)),
		'credential_id' => hex2bin('f91f391db4c9b2fde0ea70189cba3fb63f579ba6122b33ad94ff3ec330084be4'),
	);
}

// The outcome of a sign-in check: 'ok' or the error code.
function webauthn_test_assertion_outcome($response, $credential, $extra = array())
{
	$result = pg_webauthn_verify_assertion($response, webauthn_test_expected(webauthn_test_challenge(), $extra), $credential);

	return $result['ok'] ? 'ok' : $result['error'];
}

// The outcome of the "none.ES256" registration with its authenticator data,
// statement or format replaced.
function webauthn_test_none_outcome($auth_data, $extra = array(), $statement_entries = array(), $format = 'none')
{
	$vector = webauthn_test_vector('none.ES256');

	$result = pg_webauthn_verify_registration(
		array(
			'client_data_json' => $vector['registration']['clientDataJSON'],
			'attestation_object' => webauthn_test_attestation_object($format, $statement_entries, $auth_data),
			'credential_id' => $vector['registration']['credential_id'],
		),
		webauthn_test_expected($vector['registration']['challenge'], $extra));

	return $result['ok'] ? 'ok' : $result['error'];
}

// The RSA key of the "rsa_valid" certificate (PKCS #8, DER in
// webauthn_test_rsa_attestation_key_der()); a test key that signs nothing
// else.
function webauthn_test_rsa_attestation_key()
{
	return openssl_pkey_get_private(pg_webauthn_pem('PRIVATE KEY', hex2bin(webauthn_test_rsa_attestation_key_der())));
}

// A "packed" registration with a certificate of webauthn_test_certificates()
// and the attestation key: the attestation type it is accepted as, or the
// error code. Options change one part: certificate, aaguid, alg, key, x5c.
function webauthn_test_packed_outcome($options = array())
{
	$options = array_merge(array(
		'certificate' => 'valid_aaguid',
		'aaguid' => hex2bin('0102030405060708090a0b0c0d0e0f10'),
		'alg' => -7,
		'key' => null,
		'x5c' => null,
	), $options);

	$credential_id = str_repeat("\x11", 32);
	$certificates = webauthn_test_certificates();
	$auth_data = webauthn_test_auth_data(0x41, 0,
		webauthn_test_attested($options['aaguid'], $credential_id, webauthn_test_credential_cose()));
	$client_data = webauthn_test_client_data('webauthn.create', webauthn_test_challenge());
	$key = ($options['key'] === null) ? webauthn_test_attestation_key() : $options['key'];
	$x5c = ($options['x5c'] === null)
		? array(new PgWebAuthnBytes(hex2bin($certificates[$options['certificate']])))
		: $options['x5c'];

	$entries = array(
		array('alg', $options['alg']),
		array('sig', new PgWebAuthnBytes(webauthn_test_sign($key, $auth_data . hash('sha256', $client_data, true)))),
		array('x5c', $x5c),
	);

	$result = pg_webauthn_verify_registration(
		array(
			'client_data_json' => $client_data,
			'attestation_object' => webauthn_test_attestation_object('packed', $entries, $auth_data),
			'credential_id' => $credential_id,
		),
		webauthn_test_expected(webauthn_test_challenge()));

	return $result['ok'] ? $result['credential']['attestation'] : $result['error'];
}

// A decoded CBOR value with its lists as PHP arrays, to compare with
// expected values written as PHP literals.
function webauthn_test_plain($value)
{
	if ($value instanceof PgWebAuthnList) {
		return array_map('webauthn_test_plain', $value->items);
	}

	if (is_array($value)) {
		return array_map('webauthn_test_plain', $value);
	}

	return $value;
}

// The next number of a fixed sequence (a linear congruential generator), so
// the mutation test changes the same bytes on every run and every PHP.
function webauthn_test_next(&$state)
{
	$state = (($state * 1103515245) + 12345) & 0x7fffffff;

	return $state;
}

// $bytes with one change: a byte turned over, the rest cut off, a byte put
// in, or a slice repeated.
function webauthn_test_mutate($bytes, &$state)
{
	$position = webauthn_test_next($state) % max(1, strlen($bytes));

	switch (webauthn_test_next($state) % 4) {

		case 0:
			return substr_replace($bytes, chr(ord($bytes[$position]) ^ (1 + (webauthn_test_next($state) % 255))), $position, 1);

		case 1:
			return substr($bytes, 0, $position);

		case 2:
			return substr_replace($bytes, chr(webauthn_test_next($state) % 256), $position, 0);

		default:
			return substr($bytes, 0, $position) . substr($bytes, $position, 8) . substr($bytes, $position);

	}
}

// Every error code the verifier can answer with.
function webauthn_test_error_codes()
{
	return array(
		'response', 'client_data', 'type', 'challenge', 'origin', 'cross_origin',
		'attestation_object', 'auth_data', 'rp_id', 'user_present', 'user_verified',
		'backup_flags', 'credential', 'algorithm', 'public_key', 'attestation_format',
		'attestation_statement', 'signature', 'sign_count', 'user_handle',
	);
}

// -- base64url ---------------------------------------------------------------

// RFC 4648 section 10, without padding, both ways; and the two characters
// base64url changes.
function test_webauthn_base64url_rfc4648_vectors()
{
	$vectors = array(
		array('', ''),
		array('f', 'Zg'),
		array('fo', 'Zm8'),
		array('foo', 'Zm9v'),
		array('foob', 'Zm9vYg'),
		array('fooba', 'Zm9vYmE'),
		array('foobar', 'Zm9vYmFy'),
		array("\xfb\xff", '-_8'),
	);

	foreach ($vectors as $vector) {
		pg_assert_same($vector[1], pg_webauthn_base64url_encode($vector[0]), bin2hex($vector[0]));
		pg_assert_same($vector[0], pg_webauthn_base64url_decode($vector[1]), $vector[1]);
	}

	$bytes = random_bytes(33);
	pg_assert_same($bytes, pg_webauthn_base64url_decode(pg_webauthn_base64url_encode($bytes)), 'random bytes');
}

// One value, one spelling: trailing padding is tolerated, anything else
// that is not canonical base64url is refused.
function test_webauthn_base64url_decode_refuses_malformed_text()
{
	pg_assert_same('f', pg_webauthn_base64url_decode('Zg=='), 'trailing padding');

	$refused = array(
		array('Zm9v+', 'standard alphabet +'),
		array('Zm9v/w', 'standard alphabet /'),
		array('Zm 9v', 'space'),
		array('Z', 'impossible length'),
		array('Zh', 'stray low bits in the last character'),
		array('Zg=a', 'padding inside'),
	);

	foreach ($refused as $case) {
		pg_assert_false(pg_webauthn_base64url_decode($case[0]), $case[1]);
	}

	pg_assert_false(pg_webauthn_base64url_decode(null), 'null');
	pg_assert_false(pg_webauthn_base64url_decode(array('Zg')), 'array');
	pg_assert_false(pg_webauthn_base64url_decode(12), 'integer');
}

// -- CBOR --------------------------------------------------------------------

// RFC 8949 Appendix A, every example of a type WebAuthn uses.
function test_webauthn_cbor_rfc8949_examples()
{
	$examples = array(
		array('00', 0),
		array('01', 1),
		array('0a', 10),
		array('17', 23),
		array('1818', 24),
		array('1819', 25),
		array('1864', 100),
		array('1903e8', 1000),
		array('1a000f4240', 1000000),
		array('1b000000e8d4a51000', 1000000000000),
		array('1b7fffffffffffffff', PHP_INT_MAX),
		array('20', -1),
		array('29', -10),
		array('3863', -100),
		array('3903e7', -1000),
		array('3b7fffffffffffffff', PHP_INT_MIN),
		array('f4', false),
		array('f5', true),
		array('f6', null),
		array('f7', null),
		array('f90000', 0.0),
		array('f93c00', 1.0),
		array('f93e00', 1.5),
		array('f97bff', 65504.0),
		array('f90001', pow(2, -24)),
		array('f90400', pow(2, -14)),
		array('f9c400', -4.0),
		array('fa47c35000', 100000.0),
		array('fa7f7fffff', 3.4028234663852886e+38),
		array('fb3ff199999999999a', 1.1),
		array('fbc010666666666666', -4.1),
		array('60', ''),
		array('6161', 'a'),
		array('6449455446', 'IETF'),
		array('62225c', "\"\\"),
		array('62c3bc', "\xc3\xbc"),
		array('63e6b0b4', "\xe6\xb0\xb4"),
		array('80', array()),
		array('83010203', array(1, 2, 3)),
		array('8301820203820405', array(1, array(2, 3), array(4, 5))),
		array('a0', array()),
		array('a201020304', array(1 => 2, 3 => 4)),
		array('a26161016162820203', array('a' => 1, 'b' => array(2, 3))),
		array('826161a161626163', array('a', array('b' => 'c'))),
	);

	foreach ($examples as $example) {
		pg_assert_same($example[1], webauthn_test_plain(pg_webauthn_cbor_decode(hex2bin($example[0]))), $example[0]);
	}

	$infinity = pg_webauthn_cbor_decode(hex2bin('f97c00'));
	pg_assert_true(is_infinite($infinity) && ($infinity > 0), 'Infinity');
	pg_assert_same(-INF, pg_webauthn_cbor_decode(hex2bin('f9fc00')), '-Infinity');
	pg_assert_true(is_nan(pg_webauthn_cbor_decode(hex2bin('f97e00'))), 'NaN');
}

// A byte string comes back as PgWebAuthnBytes, text as a string: "authData"
// must be bytes and "fmt" text, and the two are never confused.
function test_webauthn_cbor_keeps_byte_strings_apart_from_text()
{
	$empty = pg_webauthn_cbor_decode(hex2bin('40'));
	pg_assert_true($empty instanceof PgWebAuthnBytes, 'empty byte string');
	pg_assert_same('', $empty->value);

	$bytes = pg_webauthn_cbor_decode(hex2bin('4401020304'));
	pg_assert_true($bytes instanceof PgWebAuthnBytes, 'byte string');
	pg_assert_same("\x01\x02\x03\x04", $bytes->value);

	// {"authData": h'abcd'}
	$map = pg_webauthn_cbor_decode(hex2bin('a168617574684461746142abcd'));
	pg_assert_true($map['authData'] instanceof PgWebAuthnBytes, 'byte string in a map');
	pg_assert_same("\xab\xcd", $map['authData']->value);
}

// An array comes back as PgWebAuthnList and a map as a PHP array, so an
// empty list is not an empty map and [x] is not {0: x}: "attStmt" must be a
// map and "x5c" an array.
function test_webauthn_cbor_keeps_lists_apart_from_maps()
{
	$list = pg_webauthn_cbor_decode(hex2bin('80'));
	pg_assert_true($list instanceof PgWebAuthnList, 'empty array');
	pg_assert_same(array(), $list->items);
	pg_assert_same(array(), pg_webauthn_cbor_decode(hex2bin('a0')), 'empty map');

	$list = pg_webauthn_cbor_decode(hex2bin('8101'));
	pg_assert_true($list instanceof PgWebAuthnList, '[1]');
	pg_assert_same(array(1), $list->items);
	pg_assert_same(array(0 => 1), pg_webauthn_cbor_decode(hex2bin('a10001')), '{0: 1}');
}

// At most PG_WEBAUTHN_CBOR_MAX_ITEMS entries in one array or map.
function test_webauthn_cbor_caps_entries()
{
	$items = str_repeat('00', PG_WEBAUTHN_CBOR_MAX_ITEMS);
	$list = pg_webauthn_cbor_decode(webauthn_test_cbor_head(4, PG_WEBAUTHN_CBOR_MAX_ITEMS) . hex2bin($items));
	pg_assert_same(PG_WEBAUTHN_CBOR_MAX_ITEMS, count($list->items), 'the largest array read');
	pg_assert_same('cbor', webauthn_test_cbor_error(bin2hex(webauthn_test_cbor_head(4, PG_WEBAUTHN_CBOR_MAX_ITEMS + 1)) . $items . '00'), 'one entry more');

	$entries = '';

	for ($i = 0; $i <= PG_WEBAUTHN_CBOR_MAX_ITEMS; $i++) {
		$entries .= bin2hex(webauthn_test_cbor($i << 16)) . '00';
	}

	pg_assert_same('cbor', webauthn_test_cbor_error(bin2hex(webauthn_test_cbor_head(5, PG_WEBAUTHN_CBOR_MAX_ITEMS + 1)) . $entries), 'map of colliding integer keys, one entry over');
}

// Indefinite lengths, tags, the other simple values and the reserved
// encodings: CTAP2 canonical CBOR never has them.
function test_webauthn_cbor_refuses_what_webauthn_never_sends()
{
	$refused = array(
		array('5f42010243030405ff', 'indefinite byte string'),
		array('7f657374726561646d696e67ff', 'indefinite text'),
		array('9f018202039f0405ffff', 'indefinite array'),
		array('bf61610161629f0203ffff', 'indefinite map'),
		array('c074323031332d30332d32315432303a30343a30305a', 'tag 0 (date)'),
		array('d82076687474703a2f2f7777772e6578616d706c652e636f6d', 'tag 32 (URI)'),
		array('c249010000000000000000', 'tag 2 (bignum)'),
		array('f0', 'simple value 16'),
		array('f8ff', 'simple value 255'),
		array('ff', 'break outside an indefinite item'),
		array('1c', 'reserved additional information'),
		array('5d', 'reserved byte string length'),
		array('1bffffffffffffffff', 'integer above PHP_INT_MAX'),
		array('3bffffffffffffffff', 'negative integer below PHP_INT_MIN'),
	);

	foreach ($refused as $case) {
		pg_assert_same('cbor', webauthn_test_cbor_error($case[0]), $case[1]);
	}
}

// Lengths are checked against what is left before anything is read or
// looped over.
function test_webauthn_cbor_refuses_truncated_and_oversized_lengths()
{
	$refused = array(
		array('', 'nothing'),
		array('19', 'two-byte integer without its bytes'),
		array('1901', 'two-byte integer cut short'),
		array('440102', 'byte string cut short'),
		array('62c3', 'text cut short'),
		array('5affffffff', 'byte string longer than the input'),
		array('9affffffff', 'array longer than the input'),
		array('baffffffff', 'map longer than the input'),
		array('8301', 'array of three with one item'),
		array('a201', 'map entry without its value'),
		array('0000', 'a second item after the first'),
	);

	foreach ($refused as $case) {
		pg_assert_same('cbor', webauthn_test_cbor_error($case[0]), $case[1]);
	}
}

// Map keys are integers or text, each once; text PHP would store as an
// integer key ("1") is refused so it cannot pass for the COSE label 1.
function test_webauthn_cbor_refuses_ambiguous_maps()
{
	$refused = array(
		array('a1410001', 'byte string key'),
		array('a1f93c0001', 'float key'),
		array('a1f501', 'boolean key'),
		array('a1f601', 'null key'),
		array('a18001', 'array key'),
		array('a201020103', 'integer key twice'),
		array('a2616101616102', 'text key twice'),
		array('a1613102', 'text "1"'),
		array('a1622d3102', 'text "-1"'),
	);

	foreach ($refused as $case) {
		pg_assert_same('cbor', webauthn_test_cbor_error($case[0]), $case[1]);
	}

	pg_assert_same(array('01' => 2), pg_webauthn_cbor_decode(hex2bin('a162303102')), 'text "01" stays text');
	pg_assert_same(array('-0' => 2), pg_webauthn_cbor_decode(hex2bin('a1622d3002')), 'text "-0" stays text');
}

// Text must be UTF-8, and nesting stops at PG_WEBAUTHN_CBOR_MAX_DEPTH.
function test_webauthn_cbor_refuses_invalid_utf8_and_deep_nesting()
{
	pg_assert_same('cbor', webauthn_test_cbor_error('62c328'), 'invalid UTF-8');
	pg_assert_same('cbor', webauthn_test_cbor_error('63eda080'), 'UTF-8 encoded surrogate');

	$expected = 0;

	for ($i = 0; $i < PG_WEBAUTHN_CBOR_MAX_DEPTH; $i++) {
		$expected = array($expected);
	}

	pg_assert_same($expected, webauthn_test_plain(pg_webauthn_cbor_decode(hex2bin(str_repeat('81', PG_WEBAUTHN_CBOR_MAX_DEPTH) . '00'))), 'deepest nesting read');
	pg_assert_same('cbor', webauthn_test_cbor_error(str_repeat('81', PG_WEBAUTHN_CBOR_MAX_DEPTH + 1) . '00'), 'one level deeper');
}

// pg_webauthn_cbor_read() reads one item and says where it ended, as the
// authenticator data needs; pg_webauthn_cbor_decode() wants exactly one.
// Errors carry the caller's code.
function test_webauthn_cbor_read_stops_after_one_item()
{
	$data = hex2bin('a201020304ff00');
	$offset = 0;

	pg_assert_same(array(1 => 2, 3 => 4), pg_webauthn_cbor_read($data, $offset), 'first item');
	pg_assert_same(5, $offset, 'offset after it');
	pg_assert_same('cbor', webauthn_test_cbor_error('a201020304ff00'), 'bytes after the item');
	pg_assert_same('auth_data', webauthn_test_cbor_error('ff', 'auth_data'), 'caller code');
}

// -- keys --------------------------------------------------------------------

// An ES256 COSE key becomes a P-256 SubjectPublicKeyInfo with the same point,
// written exactly as OpenSSL writes it.
function test_webauthn_cose_es256_key_is_the_same_point()
{
	$key = pg_webauthn_cose_key(pg_webauthn_cbor_decode(webauthn_test_credential_cose()));

	pg_assert_same(-7, $key['alg']);

	$details = openssl_pkey_get_details(openssl_pkey_get_public($key['pem']));

	pg_assert_same('prime256v1', $details['ec']['curve_name']);
	pg_assert_same('afefa16f97ca9b2d23eb86ccb64098d20db90856062eb249c33a9b672f26df61', bin2hex($details['ec']['x']));
	pg_assert_same('930a56b87a2fca66334b03458abf879717c12cc68ed73290af2e2664796b9220', bin2hex($details['ec']['y']));
	pg_assert_same($details['key'], $key['pem'], 'PEM as OpenSSL writes it');
}

// An RS256 COSE key keeps its modulus and exponent: the W3C key is the
// product of the Mersenne primes 2^1279 - 1 and 2^2203 - 1.
function test_webauthn_cose_rs256_key_keeps_modulus_and_exponent()
{
	$auth = pg_webauthn_auth_data(webauthn_test_vector_auth_data(webauthn_test_vector('packed.RS256')));
	$key = pg_webauthn_cose_key($auth['public_key']);

	pg_assert_same(-257, $key['alg']);

	$details = openssl_pkey_get_details(openssl_pkey_get_public($key['pem']));

	pg_assert_same(OPENSSL_KEYTYPE_RSA, $details['type']);
	pg_assert_same(3482, $details['bits']);
	pg_assert_same($auth['public_key'][-1]->value, $details['rsa']['n'], 'modulus');
	pg_assert_same("\x01\x00\x01", $details['rsa']['e'], 'exponent');
}

// Only ES256 on P-256 and RS256 are accepted.
function test_webauthn_cose_refuses_other_algorithms_and_curves()
{
	$x = new PgWebAuthnBytes(str_repeat("\x01", 32));
	$p384 = new PgWebAuthnBytes(str_repeat("\x01", 48));
	$modulus = new PgWebAuthnBytes("\xc0" . str_repeat("\x01", 255));
	$exponent = new PgWebAuthnBytes("\x01\x00\x01");

	$refused = array(
		array(array(array(1, 1), array(3, -8), array(-1, 6), array(-2, $x)), 'Ed25519'),
		array(array(array(1, 2), array(3, -35), array(-1, 2), array(-2, $p384), array(-3, $p384)), 'ES384'),
		array(array(array(1, 2), array(3, -7), array(-1, 2), array(-2, $p384), array(-3, $p384)), 'ES256 named on P-384'),
		array(array(array(1, 2), array(3, -257), array(-1, 1), array(-2, $x), array(-3, $x)), 'EC key under RS256'),
		array(array(array(1, 3), array(3, -7), array(-1, $modulus), array(-2, $exponent)), 'RSA key under ES256'),
		array(array(array(1, 3), array(3, -37), array(-1, $modulus), array(-2, $exponent)), 'PS256'),
	);

	foreach ($refused as $case) {
		pg_assert_same('algorithm', webauthn_test_cose_error($case[0]), $case[1]);
	}
}

// A key of an accepted kind that is malformed, off the curve or weak.
function test_webauthn_cose_refuses_malformed_keys()
{
	$x = new PgWebAuthnBytes(hex2bin('afefa16f97ca9b2d23eb86ccb64098d20db90856062eb249c33a9b672f26df61'));
	$y = new PgWebAuthnBytes(hex2bin('930a56b87a2fca66334b03458abf879717c12cc68ed73290af2e2664796b9220'));
	$exponent = new PgWebAuthnBytes("\x01\x00\x01");

	// (2^521 - 1)(2^607 - 1): an RSA modulus of 1128 bits.
	$weak = new PgWebAuthnBytes(hex2bin(
		'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff'
		. 'ffffffffffffffffffffffffffffffffffff7ffffffffffffffffffffe000000000000000000000000000000000000'
		. '0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001'));

	$refused = array(
		array(array(array(1, 2), array(-1, 1), array(-2, $x), array(-3, $y)), 'no alg'),
		array(array(array(1, 2), array(3, '-7'), array(-1, 1), array(-2, $x), array(-3, $y)), 'alg as text'),
		array(array(array(1, 2), array(3, -7), array(-2, $x), array(-3, $y)), 'no crv'),
		array(array(array(1, 2), array(3, -7), array(-1, 1), array(-2, new PgWebAuthnBytes(substr($x->value, 1))), array(-3, $y)), 'x of 31 bytes'),
		array(array(array(1, 2), array(3, -7), array(-1, 1), array(-2, $x)), 'no y'),
		array(array(array(1, 2), array(3, -7), array(-1, 1), array(-2, $x), array(-3, true)), 'compressed point'),
		array(array(array(1, 2), array(3, -7), array(-1, 1), array(-2, 'x'), array(-3, $y)), 'x as text'),
		array(array(array(1, 2), array(3, -7), array(-1, 1), array(-2, $x), array(-3, $x)), 'point not on the curve'),
		array(array(array(1, 3), array(3, -257), array(-1, $weak), array(-2, $exponent)), 'RSA modulus under 2048 bits'),
		array(array(array(1, 3), array(3, -257), array(-1, $weak), array(-2, new PgWebAuthnBytes("\x01\x00\x00"))), 'even exponent'),
		array(array(array(1, 3), array(3, -257), array(-1, $weak), array(-2, new PgWebAuthnBytes("\x00\x01"))), 'exponent 1'),
		array(array(array(1, 3), array(3, -257), array(-1, new PgWebAuthnBytes("\x00\x00")), array(-2, $exponent)), 'empty modulus'),
		array(array(array(1, 3), array(3, -257), array(-1, 'n'), array(-2, $exponent)), 'modulus as text'),
		array(array(array(1, 3), array(3, -257), array(-1, new PgWebAuthnBytes("\xc0" . str_repeat("\x01", 1100))), array(-2, $exponent)), 'RSA modulus over 8192 bits'),
		array(array(array(1, 3), array(3, -257), array(-1, $weak), array(-2, new PgWebAuthnBytes(str_repeat("\x01", 9)))), 'exponent over 8 bytes'),
	);

	foreach ($refused as $case) {
		pg_assert_same('public_key', webauthn_test_cose_error($case[0]), $case[1]);
	}

	foreach (array(null, array(1, 2), 'key', new PgWebAuthnList(array(2, -7))) as $value) {
		$error = '';

		try {
			pg_webauthn_cose_key($value);
		} catch (PgWebAuthnException $exception) {
			$error = $exception->getMessage();
		}

		pg_assert_same('public_key', $error, 'not a map: ' . var_export($value, true));
	}
}

// The DER shape of an ES256 signature.
function test_webauthn_ecdsa_signature_shape()
{
	$signature = webauthn_test_vector('none.ES256');
	$signature = $signature['authentication']['signature'];

	pg_assert_true(pg_webauthn_ecdsa_der_ok($signature), 'a W3C signature');
	pg_assert_false(pg_webauthn_ecdsa_der_ok($signature . "\x00"), 'trailing byte');
	pg_assert_false(pg_webauthn_ecdsa_der_ok(substr($signature, 0, -1)), 'cut short');
	pg_assert_false(pg_webauthn_ecdsa_der_ok("\x31" . substr($signature, 1)), 'SET instead of SEQUENCE');
	pg_assert_false(pg_webauthn_ecdsa_der_ok(''), 'empty');
	pg_assert_false(pg_webauthn_ecdsa_der_ok(str_repeat("\x01", 64)), 'raw r and s');
	pg_assert_false(pg_webauthn_ecdsa_der_ok("\x30\x46\x02\x22" . str_repeat("\x01", 34) . "\x02\x20" . str_repeat("\x01", 32)), 'r of 34 bytes');
	pg_assert_false(pg_webauthn_ecdsa_der_ok("\x30\x06\x02\x01\x01\x04\x01\x01"), 'second element not an INTEGER');
}

// A stored key is used only with the algorithm of its type; what OpenSSL
// cannot load leaves its error queue empty.
function test_webauthn_public_key_must_match_its_algorithm()
{
	$es256 = webauthn_test_register('none.ES256');
	$es256 = $es256['credential']['public_key'];
	$rs256 = webauthn_test_register('packed.RS256');
	$rs256 = $rs256['credential']['public_key'];

	pg_assert_true(pg_webauthn_public_key($es256, -7) !== false, 'EC key, ES256');
	pg_assert_false(pg_webauthn_public_key($es256, -257), 'EC key, RS256');
	pg_assert_true(pg_webauthn_public_key($rs256, -257) !== false, 'RSA key, RS256');
	pg_assert_false(pg_webauthn_public_key($rs256, -7), 'RSA key, ES256');
	pg_assert_false(pg_webauthn_public_key($es256, -8), 'an algorithm the verifier does not have');

	pg_webauthn_openssl_clear();
	pg_assert_false(pg_webauthn_public_key('not a key', -7), 'not a key');
	pg_assert_false(openssl_error_string(), 'OpenSSL error queue left empty');
}

// -- W3C test vectors --------------------------------------------------------

// The registrations the verifier supports: format, attestation type,
// algorithm, flags and the credential ID and AAGUID they carry.
function test_webauthn_w3c_registrations_verify()
{
	// name => format, attestation, alg, user verified, backup eligible, backed up
	$expected = array(
		'none.ES256' => array('none', 'none', -7, false, true, true),
		'packed-self.ES256' => array('packed', 'self', -7, true, true, true),
		'none.ES256.long-credential-id' => array('none', 'none', -7, false, true, false),
		'packed.ES256' => array('packed', 'basic', -7, true, true, false),
		'packed.RS256' => array('packed', 'basic', -257, true, true, true),
	);

	foreach ($expected as $name => $values) {

		$vector = webauthn_test_vector($name);
		$result = webauthn_test_register($name);

		if (!pg_assert_true($result['ok'], $name . (isset($result['error']) ? ': ' . $result['error'] : ''))) {
			continue;
		}

		$credential = $result['credential'];

		pg_assert_same($vector['registration']['credential_id'], $credential['id'], $name . ' credential ID');
		pg_assert_same($vector['registration']['aaguid'], $credential['aaguid'], $name . ' AAGUID');
		pg_assert_same($values[0], $credential['format'], $name . ' format');
		pg_assert_same($values[1], $credential['attestation'], $name . ' attestation');
		pg_assert_same($values[2], $credential['alg'], $name . ' alg');
		pg_assert_same($values[3], $credential['user_verified'], $name . ' user verified');
		pg_assert_same($values[4], $credential['backup_eligible'], $name . ' backup eligible');
		pg_assert_same($values[5], $credential['backup_state'], $name . ' backed up');
		pg_assert_same(0, $credential['sign_count'], $name . ' counter');

	}

	$long = webauthn_test_register('none.ES256.long-credential-id');
	pg_assert_same(1023, strlen($long['credential']['id']), 'the longest credential ID');
}

// Each registered credential verifies the vector's sign-in.
function test_webauthn_w3c_assertions_verify()
{
	// name => user verified, backup eligible, backed up
	$expected = array(
		'none.ES256' => array(false, true, true),
		'packed-self.ES256' => array(false, true, false),
		'none.ES256.long-credential-id' => array(true, true, false),
		'packed.ES256' => array(true, true, false),
		'packed.RS256' => array(false, true, true),
	);

	foreach ($expected as $name => $values) {

		$vector = webauthn_test_vector($name);
		$registration = webauthn_test_register($name);
		$result = pg_webauthn_verify_assertion(
			webauthn_test_assertion_response($vector),
			webauthn_test_expected($vector['authentication']['challenge']),
			$registration['credential']);

		if (!pg_assert_true($result['ok'], $name . (isset($result['error']) ? ': ' . $result['error'] : ''))) {
			continue;
		}

		pg_assert_same(0, $result['sign_count'], $name . ' counter');
		pg_assert_same($values[0], $result['user_verified'], $name . ' user verified');
		pg_assert_same($values[1], $result['backup_eligible'], $name . ' backup eligible');
		pg_assert_same($values[2], $result['backup_state'], $name . ' backed up');

	}
}

// ES384, ES512, Ed25519 and Ed448 credentials are refused.
function test_webauthn_w3c_other_algorithms_are_refused()
{
	foreach (array('packed.ES384', 'packed.ES512', 'packed.EdDSA', 'packed.Ed448') as $name) {
		pg_assert_same(array('ok' => false, 'error' => 'algorithm'), webauthn_test_register($name), $name);
	}
}

// TPM, Android Key, Apple and FIDO U2F statements are refused; their
// authenticator data is read all the same, and the ES256 key in it verifies
// the vector's sign-in.
function test_webauthn_w3c_other_formats_are_refused_but_read()
{
	foreach (array('tpm.ES256', 'android-key.ES256', 'apple.ES256', 'fido-u2f.ES256') as $name) {

		pg_assert_same(array('ok' => false, 'error' => 'attestation_format'), webauthn_test_register($name), $name);

		$vector = webauthn_test_vector($name);
		$result = pg_webauthn_verify_assertion(
			webauthn_test_assertion_response($vector),
			webauthn_test_expected($vector['authentication']['challenge']),
			webauthn_test_record_from_auth_data($vector));

		pg_assert_true($result['ok'], $name . ' sign-in');

	}
}

// A ceremony made inside a cross-origin frame is refused in both directions.
function test_webauthn_w3c_cross_origin_frames_are_refused()
{
	foreach (array('none.ES256.crossOrigin', 'none.ES256.topOrigin') as $name) {

		$vector = webauthn_test_vector($name);

		pg_assert_same(array('ok' => false, 'error' => 'cross_origin'), webauthn_test_register($name), $name . ' registration');

		$result = pg_webauthn_verify_assertion(
			webauthn_test_assertion_response($vector),
			webauthn_test_expected($vector['authentication']['challenge']),
			webauthn_test_record_from_auth_data($vector));

		pg_assert_same(array('ok' => false, 'error' => 'cross_origin'), $result, $name . ' sign-in');

	}
}

// -- registration refusals ---------------------------------------------------

// Client data: type, challenge, origin, shape and size.
function test_webauthn_registration_client_data_refusals()
{
	$vector = webauthn_test_vector('none.ES256');
	$response = webauthn_test_registration_response($vector);
	$challenge = $vector['registration']['challenge'];

	$outcome = function ($response, $expected) {
		$result = pg_webauthn_verify_registration($response, $expected);

		return $result['ok'] ? 'ok' : $result['error'];
	};

	pg_assert_same('ok', $outcome($response, webauthn_test_expected($challenge)), 'the vector');
	pg_assert_same('challenge', $outcome($response, webauthn_test_expected(strrev($challenge))), 'another challenge');
	pg_assert_same('challenge', $outcome($response, webauthn_test_expected('')), 'no challenge kept');
	pg_assert_same('challenge', $outcome($response, webauthn_test_expected(substr($challenge, 0, 15))), 'challenge under 16 bytes');

	foreach (array('https://example.org.evil.example', 'http://example.org', 'https://example.org:443', 'https://www.example.org', 'https://EXAMPLE.org', 'https://example.org/') as $origin) {
		pg_assert_same('origin', $outcome($response, webauthn_test_expected($challenge, array('origin' => $origin))), $origin);
	}

	pg_assert_same('ok', $outcome($response, webauthn_test_expected($challenge, array('origins' => array('https://shop.example', 'https://example.org')))), 'one of several origins');

	$swapped = $response;
	$swapped['client_data_json'] = $vector['authentication']['clientDataJSON'];
	pg_assert_same('type', $outcome($swapped, webauthn_test_expected($vector['authentication']['challenge'])), 'sign-in client data');

	$malformed = array(
		array('', 'empty'),
		array('null', 'JSON null'),
		array('[]', 'JSON list'),
		array('not JSON', 'not JSON'),
		array('{"type":"webauthn.create"}', 'no challenge or origin'),
		array('{"type":1,"challenge":"","origin":""}', 'type not text'),
		array(json_encode(array('type' => 'webauthn.create', 'challenge' => pg_webauthn_base64url_encode($challenge),
			'origin' => 'https://example.org', 'padding' => str_repeat('x', PG_WEBAUTHN_MAX_CLIENT_DATA_BYTES)), JSON_UNESCAPED_SLASHES), 'over the size limit'),
	);

	foreach ($malformed as $case) {
		$changed = $response;
		$changed['client_data_json'] = $case[0];
		pg_assert_same('client_data', $outcome($changed, webauthn_test_expected($challenge)), $case[1]);
	}

	// "none" signs nothing, so changed client data still reaches each check.
	$changed = $response;
	$changed['client_data_json'] = "\xEF\xBB\xBF" . $response['client_data_json'];
	pg_assert_same('ok', $outcome($changed, webauthn_test_expected($challenge)), 'leading byte order mark dropped');

	$client = json_decode($response['client_data_json'], true);
	$client['crossOrigin'] = 'true';
	$changed['client_data_json'] = json_encode($client, JSON_UNESCAPED_SLASHES);
	pg_assert_same('cross_origin', $outcome($changed, webauthn_test_expected($challenge)), 'crossOrigin as text');

	unset($client['crossOrigin']);
	$changed['client_data_json'] = json_encode($client, JSON_UNESCAPED_SLASHES);
	pg_assert_same('ok', $outcome($changed, webauthn_test_expected($challenge)), 'crossOrigin left out');
}

// Relying party, flags and the attested credential, on the "none.ES256"
// authenticator data (flags 0x59: present, backup eligible, backed up,
// attested).
function test_webauthn_registration_relying_party_and_flags()
{
	$auth_data = webauthn_test_vector_auth_data(webauthn_test_vector('none.ES256'));

	$flags = function ($flags) use ($auth_data) {
		return substr_replace($auth_data, chr($flags), 32, 1);
	};

	pg_assert_same('ok', webauthn_test_none_outcome($auth_data), 'rebuilt attestation object');
	pg_assert_same('rp_id', webauthn_test_none_outcome($auth_data, array('rp_id' => 'example.com')), 'another relying party ID');
	pg_assert_same('rp_id', webauthn_test_none_outcome($auth_data, array('rp_id' => 'www.example.org')), 'a subdomain');
	pg_assert_same('rp_id', webauthn_test_none_outcome(hash('sha256', 'example.com', true) . substr($auth_data, 32)), 'made for another site');

	pg_assert_same('user_present', webauthn_test_none_outcome($flags(0x58)), 'presence flag clear');
	pg_assert_same('ok', webauthn_test_none_outcome($flags(0x58), array('mediation' => 'conditional')), 'conditional registration without presence');
	pg_assert_same('user_present', webauthn_test_none_outcome($flags(0x58), array('mediation' => 'Conditional')), 'only "conditional" relaxes presence');
	pg_assert_same('user_verified', webauthn_test_none_outcome($auth_data, array('user_verification' => 'required')), 'verification required, not done');
	pg_assert_same('ok', webauthn_test_none_outcome($flags(0x5d), array('user_verification' => 'required')), 'verification required and done');
	pg_assert_same('ok', webauthn_test_none_outcome($flags(0x5c), array('mediation' => 'conditional', 'user_verification' => 'required')), 'conditional, verified, not present');
	pg_assert_same('user_verified', webauthn_test_none_outcome($flags(0x58), array('mediation' => 'conditional', 'user_verification' => 'required')), 'conditional still needs required verification');
	pg_assert_same('ok', webauthn_test_none_outcome($auth_data, array('user_verification' => 'discouraged')), 'verification discouraged');

	foreach (array('Required', true, 1, 'yes') as $setting) {
		pg_assert_same('user_verified', webauthn_test_none_outcome($auth_data, array('user_verification' => $setting)), 'unrecognised setting counts as required: ' . var_export($setting, true));
	}

	pg_assert_same('backup_flags', webauthn_test_none_outcome($flags(0x51)), 'backed up without being eligible');

	pg_assert_same('credential', webauthn_test_none_outcome(substr($auth_data, 0, 32) . "\x01" . substr($auth_data, 33, 4)), 'no attested credential');
	pg_assert_same('auth_data', webauthn_test_none_outcome($flags(0x19)), 'credential bytes without the AT flag');
	pg_assert_same('auth_data', webauthn_test_none_outcome($auth_data . "\x00"), 'a byte after the key');
	pg_assert_same('auth_data', webauthn_test_none_outcome(substr($auth_data, 0, -1)), 'key cut short');
	pg_assert_same('auth_data', webauthn_test_none_outcome(substr($auth_data, 0, 36)), 'under 37 bytes');
	pg_assert_same('auth_data', webauthn_test_none_outcome($flags(0xd9)), 'extensions flag without extensions');
	pg_assert_same('ok', webauthn_test_none_outcome($flags(0xd9) . webauthn_test_cbor(webauthn_test_map(array(array('credProtect', 2))))), 'extensions after the key');
	pg_assert_same('auth_data', webauthn_test_none_outcome($flags(0xd9) . webauthn_test_cbor(array('credProtect', 2))), 'extensions as an array');

	$key_offset = 55 + 32;
	$key_as_list = substr($auth_data, 0, $key_offset) . webauthn_test_cbor(array(1, 2, 3, -7));
	pg_assert_same('auth_data', webauthn_test_none_outcome($key_as_list), 'public key as an array');

	$cose = substr($auth_data, 55 + 32);
	pg_assert_same('credential', webauthn_test_none_outcome(substr($auth_data, 0, 53) . "\x00\x00" . $cose), 'empty credential ID');
	pg_assert_same('credential', webauthn_test_none_outcome(substr($auth_data, 0, 53) . pack('n', 1024) . str_repeat("\x01", 1024) . $cose), 'credential ID of 1024 bytes');

	$vector = webauthn_test_vector('none.ES256');
	$expected = webauthn_test_expected($vector['registration']['challenge']);
	$response = webauthn_test_registration_response($vector);

	$changed = $response;
	$changed['credential_id'] = str_repeat("\x01", 32);
	pg_assert_same(array('ok' => false, 'error' => 'credential'), pg_webauthn_verify_registration($changed, $expected), 'response names another credential');

	unset($changed['credential_id']);
	pg_assert_same(array('ok' => false, 'error' => 'credential'), pg_webauthn_verify_registration($changed, $expected), 'response names no credential');

	$changed['credential_id'] = new PgWebAuthnBytes($vector['registration']['credential_id']);
	pg_assert_same(array('ok' => false, 'error' => 'credential'), pg_webauthn_verify_registration($changed, $expected), 'credential ID not given as bytes');

	$changed = $response;
	$changed['client_data_json'] = array($response['client_data_json']);
	pg_assert_same(array('ok' => false, 'error' => 'client_data'), pg_webauthn_verify_registration($changed, $expected), 'client data not given as bytes');
}

// The attestation object around the authenticator data.
function test_webauthn_registration_attestation_object_rules()
{
	$vector = webauthn_test_vector('none.ES256');
	$auth_data = webauthn_test_vector_auth_data($vector);
	$bytes = new PgWebAuthnBytes($auth_data);

	$outcome = function ($attestation_object, $extra = array()) use ($vector) {
		$result = pg_webauthn_verify_registration(
			array(
				'client_data_json' => $vector['registration']['clientDataJSON'],
				'attestation_object' => $attestation_object,
				'credential_id' => $vector['registration']['credential_id'],
			),
			webauthn_test_expected($vector['registration']['challenge'], $extra));

		return $result['ok'] ? 'ok' : $result['error'];
	};

	$empty_statement = array('attStmt', webauthn_test_map(array()));

	$malformed = array(
		array('', 'empty'),
		array('garbage', 'not CBOR'),
		array(webauthn_test_cbor(array(1)), 'a list'),
		array(webauthn_test_cbor(webauthn_test_map(array(array('fmt', 'none'), $empty_statement))), 'no authData'),
		array(webauthn_test_cbor(webauthn_test_map(array(array('fmt', 'none'), $empty_statement, array('authData', 'text')))), 'authData as text'),
		array(webauthn_test_cbor(webauthn_test_map(array(array('fmt', new PgWebAuthnBytes('none')), $empty_statement, array('authData', $bytes)))), 'fmt as bytes'),
		array(webauthn_test_cbor(webauthn_test_map(array(array('fmt', 'none'), array('attStmt', 0), array('authData', $bytes)))), 'attStmt not a map'),
		array(webauthn_test_cbor(webauthn_test_map(array(array('fmt', 'none'), array('attStmt', array()), array('authData', $bytes)))), 'attStmt as an empty array'),
		array(webauthn_test_cbor(new PgWebAuthnBytes(str_repeat("\x00", PG_WEBAUTHN_MAX_OBJECT_BYTES))), 'over the size limit'),
	);

	foreach ($malformed as $case) {
		pg_assert_same('attestation_object', $outcome($case[0]), $case[1]);
	}

	pg_assert_same('attestation_statement', $outcome(webauthn_test_attestation_object('none', array(array('alg', -7)), $auth_data)), '"none" with a statement');
	pg_assert_same('attestation_format', $outcome(webauthn_test_attestation_object('unknown', array(), $auth_data)), 'unknown format');
	pg_assert_same('attestation_format', $outcome(webauthn_test_attestation_object('Packed', array(), $auth_data)), 'format names are case-sensitive');
	pg_assert_same('algorithm', $outcome($vector['registration']['attestationObject'], array('algorithms' => array(-257))), 'ES256 not offered');
	pg_assert_same('ok', $outcome($vector['registration']['attestationObject'], array('algorithms' => array(-7))), 'ES256 offered');
}

// Self attestation: signed with the credential's own key and algorithm.
function test_webauthn_packed_self_attestation_rules()
{
	$vector = webauthn_test_vector('packed-self.ES256');
	$auth_data = webauthn_test_vector_auth_data($vector);
	$object = pg_webauthn_cbor_decode($vector['registration']['attestationObject']);
	$signature = new PgWebAuthnBytes($object['attStmt']['sig']->value);

	$outcome = function ($entries, $auth_data) use ($vector) {
		$result = pg_webauthn_verify_registration(
			array(
				'client_data_json' => $vector['registration']['clientDataJSON'],
				'attestation_object' => webauthn_test_attestation_object('packed', $entries, $auth_data),
				'credential_id' => $vector['registration']['credential_id'],
			),
			webauthn_test_expected($vector['registration']['challenge']));

		return $result['ok'] ? $result['credential']['attestation'] : $result['error'];
	};

	pg_assert_same('self', $outcome(array(array('alg', -7), array('sig', $signature)), $auth_data), 'rebuilt');
	pg_assert_same('signature', $outcome(array(array('alg', -7), array('sig', new PgWebAuthnBytes(webauthn_test_flip($signature->value)))), $auth_data), 'signature changed');
	pg_assert_same('signature', $outcome(array(array('alg', -7), array('sig', $signature)), substr_replace($auth_data, "\x00\x00\x00\x01", 33, 4)), 'counter changed after signing');
	pg_assert_same('signature', $outcome(array(array('alg', -7), array('sig', new PgWebAuthnBytes(''))), $auth_data), 'empty signature');
	pg_assert_same('attestation_statement', $outcome(array(array('alg', -257), array('sig', $signature)), $auth_data), 'alg not the credential\'s');
	pg_assert_same('attestation_statement', $outcome(array(array('alg', '-7'), array('sig', $signature)), $auth_data), 'alg as text');
	pg_assert_same('attestation_statement', $outcome(array(array('alg', -7), array('sig', 'signature')), $auth_data), 'sig as text');
	pg_assert_same('attestation_statement', $outcome(array(array('alg', -7)), $auth_data), 'no sig');
	pg_assert_same('attestation_statement', $outcome(array(array('alg', -7), array('sig', $signature), array('ecdaaKeyId', new PgWebAuthnBytes('id'))), $auth_data), 'unknown member');
}

// Basic attestation: signed with the key of the first certificate, which
// meets the format's certificate rules.
function test_webauthn_packed_certificate_rules()
{
	pg_assert_same('basic', webauthn_test_packed_outcome(), 'certificate with the same AAGUID');
	pg_assert_same('basic', webauthn_test_packed_outcome(array('certificate' => 'no_aaguid')), 'certificate without the AAGUID extension');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('certificate' => 'other_aaguid')), 'another AAGUID');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('aaguid' => str_repeat("\x00", 16))), 'authenticator data without the AAGUID');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('certificate' => 'wrong_ou')), 'OU is not "Authenticator Attestation"');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('certificate' => 'ca_true')), 'a CA certificate');
	pg_assert_same('signature', webauthn_test_packed_outcome(array('key' => webauthn_test_credential_key())), 'signed by another key');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('alg' => -257)), 'RS256 named for an EC certificate');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('alg' => -35)), 'an algorithm this verifier cannot check');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => array())), 'no certificate');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => array(new PgWebAuthnBytes('not a certificate')))), 'not a certificate');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => array('text'))), 'certificate as text');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => new PgWebAuthnBytes('x'))), 'x5c not a list');

	$certificates = webauthn_test_certificates();
	$certificate = new PgWebAuthnBytes(hex2bin($certificates['valid_aaguid']));
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => array_fill(0, 9, $certificate))), 'nine certificates');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => webauthn_test_map(array(array(1, $certificate))))), 'x5c as a map {1: certificate}');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => webauthn_test_map(array(array(0, $certificate))))), 'x5c as a map {0: certificate}');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('x5c' => array(new PgWebAuthnBytes($certificate->value . "\x00")))), 'certificate followed by a byte');
	pg_assert_same('basic', webauthn_test_packed_outcome(array('x5c' => array($certificate, $certificate))), 'certificate and chain');

	// The certificate rules of WebAuthn 8.2.1, each certificate signed by the
	// attestation key so only the rule under test fails.
	$rules = array(
		array('v1', 'version 1, no extensions'),
		array('nobc', 'no basic constraints extension'),
		array('nocountry', 'no C in the subject'),
		array('critaaguid', 'AAGUID extension marked critical'),
	);

	foreach ($rules as $rule) {
		pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('certificate' => $rule[0])), $rule[1]);
	}

	pg_assert_same('basic', webauthn_test_packed_outcome(array('certificate' => 'rsa_valid', 'key' => webauthn_test_rsa_attestation_key(), 'alg' => -257)), 'RS256 attestation with an RSA certificate');
	pg_assert_same('attestation_statement', webauthn_test_packed_outcome(array('certificate' => 'rsa_valid', 'key' => webauthn_test_rsa_attestation_key(), 'alg' => -7)), 'ES256 named for an RSA certificate');
}

// The extensions of a certificate are read from its DER, whatever OpenSSL
// calls them.
function test_webauthn_certificate_extensions_from_der()
{
	$certificates = webauthn_test_certificates();
	$extensions = pg_webauthn_certificate_extensions(hex2bin($certificates['valid_aaguid']));

	pg_assert_true(is_array($extensions), 'read');
	pg_assert_same(array('critical' => true, 'value' => "\x30\x00"), $extensions['2.5.29.19'], 'basic constraints, critical, cA left out');
	pg_assert_same(array('critical' => false, 'value' => hex2bin('04100102030405060708090a0b0c0d0e0f10')), $extensions['1.3.6.1.4.1.45724.1.1.4'], 'AAGUID extension');

	$critical = pg_webauthn_certificate_extensions(hex2bin($certificates['critaaguid']));
	pg_assert_true($critical['1.3.6.1.4.1.45724.1.1.4']['critical'], 'critical flag read');

	pg_assert_same(array(), pg_webauthn_certificate_extensions(hex2bin($certificates['v1'])), 'version 1: none');
	pg_assert_false(pg_webauthn_certificate_extensions('not DER'), 'not DER');
	pg_assert_false(pg_webauthn_certificate_extensions(hex2bin($certificates['valid_aaguid']) . "\x00"), 'a byte after the certificate');
	pg_assert_false(pg_webauthn_certificate_extensions(substr(hex2bin($certificates['valid_aaguid']), 0, -1)), 'cut short');

	pg_assert_true(pg_webauthn_basic_constraints_ca("\x30\x03\x01\x01\xff"), 'cA TRUE');
	pg_assert_false(pg_webauthn_basic_constraints_ca("\x30\x00"), 'cA left out');
	pg_assert_false(pg_webauthn_basic_constraints_ca("\x30\x03\x02\x01\x00"), 'path length only');
	pg_assert_same(null, pg_webauthn_basic_constraints_ca("\x30\x03\x01\x01\x01"), 'BOOLEAN that is neither TRUE nor FALSE');
	pg_assert_same(null, pg_webauthn_basic_constraints_ca("\x04\x00"), 'not a SEQUENCE');

	pg_assert_same('1.3.6.1.4.1.45724.1.1.4', pg_webauthn_der_oid(hex2bin('2b0601040182e51c010104')), 'FIDO AAGUID OID');
	pg_assert_same('2.5.29.19', pg_webauthn_der_oid(hex2bin('551d13')), 'basicConstraints OID');
	pg_assert_same('', pg_webauthn_der_oid(hex2bin('2b060182')), 'arc cut short');
	pg_assert_same('', pg_webauthn_der_oid(hex2bin('2b8001')), 'arc padded with 0x80');
	pg_assert_same('', pg_webauthn_der_oid(''), 'empty');
}

// -- sign-in refusals --------------------------------------------------------

// The signature covers the authenticator data and the client data hash and
// verifies only with the stored key.
function test_webauthn_assertion_signature()
{
	$credential = webauthn_test_credential();
	$response = webauthn_test_signed_assertion();

	pg_assert_same('ok', webauthn_test_assertion_outcome($response, $credential), 'a fresh signature');

	$changed = $response;
	$changed['signature'] = webauthn_test_flip($response['signature']);
	pg_assert_same('signature', webauthn_test_assertion_outcome($changed, $credential), 'signature changed');

	$changed = $response;
	$changed['authenticator_data'] = substr_replace($response['authenticator_data'], pack('N', 7), 33, 4);
	pg_assert_same('signature', webauthn_test_assertion_outcome($changed, $credential), 'counter changed after signing');

	$changed = $response;
	$changed['client_data_json'] = str_replace('"crossOrigin":false', '"crossOrigin":false,"extra":1', $response['client_data_json']);
	pg_assert_same('signature', webauthn_test_assertion_outcome($changed, $credential), 'client data changed after signing');

	$changed = $response;
	$changed['signature'] = str_repeat("\x01", 64);
	pg_assert_same('signature', webauthn_test_assertion_outcome($changed, $credential), 'raw r and s instead of DER');

	$changed['signature'] = '';
	pg_assert_same('signature', webauthn_test_assertion_outcome($changed, $credential), 'no signature');

	$other = webauthn_test_register('packed.ES256');
	$other_credential = $credential;
	$other_credential['public_key'] = $other['credential']['public_key'];
	pg_assert_same('signature', webauthn_test_assertion_outcome($response, $other_credential), 'another credential\'s key');

	$vector = webauthn_test_vector('packed.RS256');
	$rs256 = webauthn_test_register('packed.RS256');
	$changed = webauthn_test_assertion_response($vector);
	$changed['signature'] = webauthn_test_flip($changed['signature']);
	$result = pg_webauthn_verify_assertion($changed, webauthn_test_expected($vector['authentication']['challenge']), $rs256['credential']);
	pg_assert_same(array('ok' => false, 'error' => 'signature'), $result, 'RS256 signature changed');
}

// Presence, verification and backup flags, each in a correctly signed
// sign-in. The credential was registered backup eligible.
function test_webauthn_assertion_flags()
{
	$credential = webauthn_test_credential();

	pg_assert_same('user_present', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x18), $credential), 'presence flag clear');
	pg_assert_same('user_verified', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19), $credential, array('user_verification' => 'required')), 'verification required, not done');
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x1d), $credential, array('user_verification' => 'required')), 'verification required and done');
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x09), $credential), 'no longer backed up');
	pg_assert_same('backup_flags', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x01), $credential), 'eligibility lost');
	pg_assert_same('rp_id', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19, 0, array(), 'example.com'), $credential), 'signed for another relying party');

	$not_eligible = $credential;
	$not_eligible['backup_eligible'] = false;
	pg_assert_same('backup_flags', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x09), $not_eligible), 'eligibility gained');
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x01), $not_eligible), 'not eligible, as registered');

	$unknown = $credential;
	$unknown['backup_eligible'] = null;
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x01), $unknown), 'eligibility not recorded');
	pg_assert_same('backup_flags', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x11), $unknown), 'backed up without eligibility');

	unset($unknown['backup_eligible']);
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x01), $unknown), 'eligibility left out of the record');

	// Values as a database row gives them.
	$stored = array(
		array('1', 0x19, 'ok'),
		array(1, 0x19, 'ok'),
		array('1', 0x01, 'backup_flags'),
		array('0', 0x01, 'ok'),
		array(0, 0x09, 'backup_flags'),
		array('yes', 0x19, 'backup_flags'),
		array('', 0x19, 'backup_flags'),
		array(array(), 0x19, 'backup_flags'),
	);

	foreach ($stored as $case) {
		$record = $credential;
		$record['backup_eligible'] = $case[0];
		pg_assert_same($case[2], webauthn_test_assertion_outcome(webauthn_test_signed_assertion($case[1]), $record), 'stored ' . var_export($case[0], true) . ', flags ' . dechex($case[1]));
	}

	foreach (array('Required', true, 1) as $setting) {
		pg_assert_same('user_verified', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19), $credential, array('user_verification' => $setting)), 'unrecognised setting counts as required: ' . var_export($setting, true));
	}

	// Extensions after the counter are read and left alone; attested
	// credential data has no place in a sign-in.
	$extensions = webauthn_test_cbor(webauthn_test_map(array(array('credProtect', 2))));
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x99, 0, array(), 'example.org', $extensions), $credential), 'extensions');
	pg_assert_same('auth_data', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x99, 0, array(), 'example.org', webauthn_test_cbor(array(2))), $credential), 'extensions as an array');

	$attested = webauthn_test_attested(str_repeat("\x00", 16), $credential['id'], webauthn_test_credential_cose());
	pg_assert_same('auth_data', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x59, 0, array(), 'example.org', $attested), $credential), 'attested credential data in a sign-in');
}

// The counter moves forward, unless both sides keep none.
function test_webauthn_assertion_signature_counter()
{
	$credential = webauthn_test_credential();

	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19, 0), $credential), 'both zero');

	$result = pg_webauthn_verify_assertion(webauthn_test_signed_assertion(0x19, 5), webauthn_test_expected(webauthn_test_challenge()), $credential);
	pg_assert_same(5, $result['sign_count'], 'the count to store');

	$credential['sign_count'] = 4;
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19, 5), $credential), 'moved forward');

	$credential['sign_count'] = 5;
	pg_assert_same('sign_count', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19, 5), $credential), 'repeated');
	pg_assert_same('sign_count', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19, 0), $credential), 'back to zero');

	$credential['sign_count'] = 4294967294;
	pg_assert_same('ok', webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19, 4294967295), $credential), 'the largest counter');
}

// The response names the stored credential, and its user handle is the
// account's; an account found by user handle alone must have one.
function test_webauthn_assertion_credential_and_user_handle()
{
	$credential = webauthn_test_credential();
	$response = webauthn_test_signed_assertion();

	$changed = $response;
	$changed['credential_id'] = str_repeat("\x01", 32);
	pg_assert_same('credential', webauthn_test_assertion_outcome($changed, $credential), 'another credential named');

	$with_handle = $response;
	$with_handle['user_handle'] = $credential['user_handle'];
	pg_assert_same('ok', webauthn_test_assertion_outcome($with_handle, $credential), 'the account\'s user handle');
	pg_assert_same('ok', webauthn_test_assertion_outcome($with_handle, $credential, array('discoverable' => true)), 'account found by its user handle');

	$changed = $with_handle;
	$changed['user_handle'] = str_repeat("\x08", 32);
	pg_assert_same('user_handle', webauthn_test_assertion_outcome($changed, $credential), 'another account\'s user handle');

	pg_assert_same('user_handle', webauthn_test_assertion_outcome($response, $credential, array('discoverable' => true)), 'account not known, no user handle');

	$without = $credential;
	unset($without['user_handle']);
	pg_assert_same('user_handle', webauthn_test_assertion_outcome($with_handle, $without, array('discoverable' => true)), 'nothing to compare the user handle with');
	pg_assert_same('user_handle', webauthn_test_assertion_outcome($with_handle, $without), 'a user handle the record cannot confirm');
	pg_assert_same('ok', webauthn_test_assertion_outcome($response, $without), 'no user handle on either side, account known before');

	$changed = $response;
	unset($changed['credential_id']);
	pg_assert_same('credential', webauthn_test_assertion_outcome($changed, $credential), 'response names no credential');

	$record = $credential;
	unset($record['id']);
	pg_assert_same('credential', webauthn_test_assertion_outcome($response, $record), 'record without its ID');
}

// What the site stored decides how the signature is checked.
function test_webauthn_assertion_stored_credential_rules()
{
	$credential = webauthn_test_credential();
	$response = webauthn_test_signed_assertion();

	$changed = $credential;
	$changed['alg'] = -8;
	pg_assert_same('algorithm', webauthn_test_assertion_outcome($response, $changed), 'an algorithm the verifier does not have');

	$changed['alg'] = -257;
	pg_assert_same('public_key', webauthn_test_assertion_outcome($response, $changed), 'EC key stored as RS256');

	$changed = $credential;
	$changed['public_key'] = 'not a key';
	pg_assert_same('public_key', webauthn_test_assertion_outcome($response, $changed), 'not a key');

	$changed = $credential;
	$changed['alg'] = '-7';
	$changed['sign_count'] = '0';
	pg_assert_same('ok', webauthn_test_assertion_outcome($response, $changed), 'values read from text columns');

	$changed = $credential;
	unset($changed['alg']);
	pg_assert_same('algorithm', webauthn_test_assertion_outcome($response, $changed), 'no algorithm stored');

	$changed['alg'] = 'ES256';
	pg_assert_same('algorithm', webauthn_test_assertion_outcome($response, $changed), 'algorithm stored by name');

	foreach (array(null, '-1', -1, 'abc', '1.5', array(), '007', '', '99999999999999999999') as $count) {
		$changed = $credential;
		$changed['sign_count'] = $count;
		pg_assert_same('credential', webauthn_test_assertion_outcome($response, $changed), 'counter ' . var_export($count, true));
	}

	$changed = $credential;
	unset($changed['sign_count']);
	pg_assert_same('credential', webauthn_test_assertion_outcome($response, $changed), 'no counter stored');

	$changed = $credential;
	$changed['public_key'] = new PgWebAuthnBytes($credential['public_key']);
	pg_assert_same('public_key', webauthn_test_assertion_outcome($response, $changed), 'key not given as text');

	$changed = $response;
	$changed['signature'] = array($response['signature']);
	pg_assert_same('signature', webauthn_test_assertion_outcome($changed, $credential), 'signature not given as bytes');
}

// Client data and authenticator data of a sign-in.
function test_webauthn_assertion_client_data_and_auth_data()
{
	$credential = webauthn_test_credential();

	$refused = array(
		array(array('type' => 'webauthn.create'), 'type', 'creation client data'),
		array(array('origin' => 'https://evil.example'), 'origin', 'another origin'),
		array(array('challenge' => pg_webauthn_base64url_encode(str_repeat("\x01", 32))), 'challenge', 'another challenge'),
		array(array('crossOrigin' => true), 'cross_origin', 'cross-origin frame'),
		array(array('topOrigin' => 'https://example.com'), 'cross_origin', 'top origin'),
	);

	foreach ($refused as $case) {
		pg_assert_same($case[1], webauthn_test_assertion_outcome(webauthn_test_signed_assertion(0x19, 0, $case[0]), $credential), $case[2]);
	}

	$changed = webauthn_test_signed_assertion();
	$changed['authenticator_data'] = substr($changed['authenticator_data'], 0, 36);
	pg_assert_same('auth_data', webauthn_test_assertion_outcome($changed, $credential), 'authenticator data cut short');

	$changed = webauthn_test_signed_assertion();
	$changed['authenticator_data'] .= "\x00";
	pg_assert_same('auth_data', webauthn_test_assertion_outcome($changed, $credential), 'a byte after the counter');
}

// Without something to compare against there is nothing to accept.
function test_webauthn_expectations_are_required()
{
	$vector = webauthn_test_vector('none.ES256');
	$response = webauthn_test_registration_response($vector);
	$challenge = $vector['registration']['challenge'];

	$cases = array(
		array('origin', array('challenge' => $challenge, 'rp_id' => 'example.org')),
		array('origin', array('challenge' => $challenge, 'rp_id' => 'example.org', 'origins' => array('', null))),
		array('rp_id', array('challenge' => $challenge, 'origin' => 'https://example.org')),
		array('challenge', array('origin' => 'https://example.org', 'rp_id' => 'example.org')),
		array('algorithm', webauthn_test_expected($challenge, array('algorithms' => array(-8)))),
		array('challenge', 'not an array'),
	);

	foreach ($cases as $case) {
		pg_assert_same(array('ok' => false, 'error' => $case[0]), pg_webauthn_verify_registration($response, $case[1]), $case[0]);
	}

	pg_assert_same(array('ok' => false, 'error' => 'client_data'), pg_webauthn_verify_registration('nothing', webauthn_test_expected($challenge)), 'no response');
	pg_assert_same(array('ok' => false, 'error' => 'credential'), pg_webauthn_verify_assertion(null, webauthn_test_expected($challenge), webauthn_test_credential()), 'no sign-in response');
	pg_assert_same(array('ok' => false, 'error' => 'credential'), pg_webauthn_verify_assertion(webauthn_test_signed_assertion(), webauthn_test_expected(webauthn_test_challenge()), null), 'no stored record');
}

// Fifteen hundred single changes to real responses: every answer is a known
// error code (or a success where nothing signed was changed), with no PHP
// warning on the way, and no change to a signed sign-in passes.
function test_webauthn_mutated_responses_answer_cleanly()
{
	$codes = webauthn_test_error_codes();
	$state = 20261019;

	$registrations = array();

	foreach (array('none.ES256', 'packed.ES256', 'packed.RS256') as $name) {
		$vector = webauthn_test_vector($name);
		$registrations[] = array(webauthn_test_registration_response($vector), webauthn_test_expected($vector['registration']['challenge']));
	}

	for ($i = 0; $i < 600; $i++) {

		list($response, $expected) = $registrations[$i % 3];
		$field = (($i % 6) < 3) ? 'attestation_object' : 'client_data_json';
		$response[$field] = webauthn_test_mutate($response[$field], $state);
		$result = pg_webauthn_verify_registration($response, $expected);

		if (!$result['ok']) {
			pg_assert_contains($result['error'], $codes, 'registration ' . $i);
		}

	}

	$credential = webauthn_test_credential();
	$assertion = webauthn_test_signed_assertion();
	$expected = webauthn_test_expected(webauthn_test_challenge());
	$fields = array('authenticator_data', 'client_data_json', 'signature');
	$accepted = 0;

	for ($i = 0; $i < 600; $i++) {

		$response = $assertion;
		$response[$fields[$i % 3]] = webauthn_test_mutate($response[$fields[$i % 3]], $state);
		$result = pg_webauthn_verify_assertion($response, $expected, $credential);

		if ($result['ok']) {
			$accepted++;
		} else {
			pg_assert_contains($result['error'], $codes, 'sign-in ' . $i);
		}

	}

	pg_assert_same(0, $accepted, 'changed sign-ins accepted');

	$vector = webauthn_test_vector('none.ES256');
	$id = pg_webauthn_base64url_encode($vector['registration']['credential_id']);
	$json = json_encode(array(
		'id' => $id,
		'rawId' => $id,
		'type' => 'public-key',
		'response' => array(
			'clientDataJSON' => pg_webauthn_base64url_encode($vector['registration']['clientDataJSON']),
			'attestationObject' => pg_webauthn_base64url_encode($vector['registration']['attestationObject']),
		),
	));

	for ($i = 0; $i < 300; $i++) {
		$decoded = pg_webauthn_registration_response(webauthn_test_mutate($json, $state));
		pg_assert_true(is_bool($decoded['ok']), 'browser JSON ' . $i);
	}
}

// -- options and browser responses -------------------------------------------

function test_webauthn_creation_options()
{
	$challenge = str_repeat("\x01", 32);
	$handle = str_repeat("\x02", 32);

	$options = pg_webauthn_creation_options(
		array('id' => 'example.org', 'name' => 'Example'),
		array('handle' => $handle, 'name' => 'ada@example.org', 'display_name' => 'Ada'),
		$challenge,
		array(
			'exclude' => array(
				array('id' => "\x03\x04", 'transports' => array('internal', 'carrier-pigeon', 'hybrid', 'internal')),
				array('id' => ''),
				'not a credential',
			),
			'resident_key' => 'required',
			'attachment' => 'platform',
			'timeout' => 5,
		));

	pg_assert_same(array('id' => 'example.org', 'name' => 'Example'), $options['rp']);
	pg_assert_same(array('id' => pg_webauthn_base64url_encode($handle), 'name' => 'ada@example.org', 'displayName' => 'Ada'), $options['user']);
	pg_assert_same(pg_webauthn_base64url_encode($challenge), $options['challenge']);
	pg_assert_same(array(array('type' => 'public-key', 'alg' => -7), array('type' => 'public-key', 'alg' => -257)), $options['pubKeyCredParams']);
	pg_assert_same(60000, $options['timeout'], 'timeout raised to a minute');
	pg_assert_same(array(array('type' => 'public-key', 'id' => 'AwQ', 'transports' => array('internal', 'hybrid'))), $options['excludeCredentials']);
	pg_assert_same(array('residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'preferred', 'authenticatorAttachment' => 'platform'), $options['authenticatorSelection']);
	pg_assert_same('none', $options['attestation']);
	pg_assert_same(array('credProps' => true), $options['extensions']);
	pg_assert_true(is_string(json_encode($options)), 'JSON for the browser');

	$defaults = pg_webauthn_creation_options(array('id' => 'example.org'), array('handle' => $handle), $challenge, array('user_verification' => 'nonsense'));
	pg_assert_same('example.org', $defaults['rp']['name'], 'name defaults to the ID');
	pg_assert_same(300000, $defaults['timeout']);
	pg_assert_same(array(), $defaults['excludeCredentials']);
	pg_assert_same(array('residentKey' => 'preferred', 'requireResidentKey' => false, 'userVerification' => 'preferred'), $defaults['authenticatorSelection']);

	pg_assert_false(pg_webauthn_creation_options(array('id' => ''), array('handle' => $handle), $challenge), 'no relying party ID');
	pg_assert_false(pg_webauthn_creation_options(array('id' => 'example.org'), array('handle' => ''), $challenge), 'no user handle');
	pg_assert_false(pg_webauthn_creation_options(array('id' => 'example.org'), array('handle' => str_repeat('x', 65)), $challenge), 'user handle over 64 bytes');
	pg_assert_false(pg_webauthn_creation_options(array('id' => 'example.org'), array('handle' => $handle), 'short'), 'challenge under 16 bytes');
}

function test_webauthn_request_options()
{
	$challenge = str_repeat("\x01", 32);

	pg_assert_same(array(
		'challenge' => pg_webauthn_base64url_encode($challenge),
		'rpId' => 'example.org',
		'timeout' => 600000,
		'userVerification' => 'required',
		'allowCredentials' => array(array('type' => 'public-key', 'id' => 'AwQ', 'transports' => array('usb'))),
	), pg_webauthn_request_options('example.org', $challenge, array(
		'allow' => array(array('id' => "\x03\x04", 'transports' => array('usb'))),
		'user_verification' => 'required',
		'timeout' => 900000,
	)));

	$open = pg_webauthn_request_options('example.org', $challenge);
	pg_assert_same(array(), $open['allowCredentials'], 'empty: the browser offers every passkey it has for the site');
	pg_assert_same('preferred', $open['userVerification']);

	pg_assert_false(pg_webauthn_request_options('', $challenge), 'no relying party ID');
	pg_assert_false(pg_webauthn_request_options('example.org', 'short'), 'challenge under 16 bytes');
}

// RegistrationResponseJSON as PublicKeyCredential.toJSON() writes it.
function test_webauthn_registration_response_from_browser_json()
{
	$vector = webauthn_test_vector('none.ES256');
	$id = pg_webauthn_base64url_encode($vector['registration']['credential_id']);

	$json = array(
		'id' => $id,
		'rawId' => $id,
		'type' => 'public-key',
		'response' => array(
			'clientDataJSON' => pg_webauthn_base64url_encode($vector['registration']['clientDataJSON']),
			'attestationObject' => pg_webauthn_base64url_encode($vector['registration']['attestationObject']),
			'transports' => array('internal', 'hybrid', 'warp-drive'),
		),
		'authenticatorAttachment' => 'platform',
		'clientExtensionResults' => array('credProps' => array('rk' => true)),
	);

	$response = pg_webauthn_registration_response(json_encode($json));

	if (!pg_assert_true($response['ok'], 'decoded')) {
		return;
	}

	pg_assert_same($vector['registration']['credential_id'], $response['credential_id']);
	pg_assert_same($vector['registration']['clientDataJSON'], $response['client_data_json']);
	pg_assert_same(array('internal', 'hybrid'), $response['transports']);
	pg_assert_same('platform', $response['attachment']);
	pg_assert_same(array('credProps' => array('rk' => true)), $response['client_extensions']);

	$result = pg_webauthn_verify_registration($response, webauthn_test_expected($vector['registration']['challenge']));
	pg_assert_true($result['ok'], 'verifies as decoded');

	$other = $json;
	$other['authenticatorAttachment'] = 'elsewhere';
	$decoded = pg_webauthn_registration_response($other);
	pg_assert_same('', $decoded['attachment'], 'unknown attachment left out');

	$changes = array(
		array('type', 'password', 'type'),
		array('rawId', pg_webauthn_base64url_encode(str_repeat("\x01", 32)), 'rawId is not id'),
		array('id', 'ab+c', 'id not base64url'),
		array('rawId', pg_webauthn_base64url_encode(str_repeat("\x01", 1024)), 'credential ID over 1023 bytes'),
		array('response', 'text', 'response not an object'),
	);

	foreach ($changes as $change) {
		$changed = $json;
		$changed[$change[0]] = $change[1];
		pg_assert_same(array('ok' => false, 'error' => 'response'), pg_webauthn_registration_response($changed), $change[2]);
	}

	$changed = $json;
	unset($changed['response']['clientDataJSON']);
	pg_assert_same(array('ok' => false, 'error' => 'response'), pg_webauthn_registration_response($changed), 'no clientDataJSON');

	$changed = $json;
	$changed['response']['attestationObject'] = 'a+b';
	pg_assert_same(array('ok' => false, 'error' => 'response'), pg_webauthn_registration_response($changed), 'attestationObject not base64url');

	pg_assert_same(array('ok' => false, 'error' => 'response'), pg_webauthn_registration_response('not JSON'), 'not JSON');
}

// AuthenticationResponseJSON, with and without a user handle.
function test_webauthn_assertion_response_from_browser_json()
{
	$vector = webauthn_test_vector('none.ES256');
	$id = pg_webauthn_base64url_encode($vector['registration']['credential_id']);

	$json = array(
		'id' => $id,
		'rawId' => $id,
		'type' => 'public-key',
		'response' => array(
			'clientDataJSON' => pg_webauthn_base64url_encode($vector['authentication']['clientDataJSON']),
			'authenticatorData' => pg_webauthn_base64url_encode($vector['authentication']['authenticatorData']),
			'signature' => pg_webauthn_base64url_encode($vector['authentication']['signature']),
			'userHandle' => null,
		),
		'authenticatorAttachment' => 'cross-platform',
		'clientExtensionResults' => array(),
	);

	$response = pg_webauthn_assertion_response($json);

	if (!pg_assert_true($response['ok'], 'decoded')) {
		return;
	}

	pg_assert_same('', $response['user_handle'], 'null user handle');
	pg_assert_same('cross-platform', $response['attachment']);

	$registration = webauthn_test_register('none.ES256');
	$result = pg_webauthn_verify_assertion($response, webauthn_test_expected($vector['authentication']['challenge']), $registration['credential']);
	pg_assert_true($result['ok'], 'verifies as decoded');

	$handles = array(
		array('', ''),
		array('AQID', "\x01\x02\x03"),
	);

	foreach ($handles as $handle) {
		$changed = $json;
		$changed['response']['userHandle'] = $handle[0];
		$decoded = pg_webauthn_assertion_response($changed);
		pg_assert_same($handle[1], $decoded['user_handle'], var_export($handle[0], true));
	}

	$changed = $json;
	unset($changed['response']['userHandle']);
	$decoded = pg_webauthn_assertion_response($changed);
	pg_assert_same('', $decoded['user_handle'], 'no user handle');

	$refused = array(
		array('userHandle', pg_webauthn_base64url_encode(str_repeat("\x01", 65)), 'user handle over 64 bytes'),
		array('userHandle', 'a+b', 'user handle not base64url'),
		array('signature', '', 'empty signature'),
		array('authenticatorData', 12, 'authenticator data not text'),
	);

	foreach ($refused as $case) {
		$changed = $json;
		$changed['response'][$case[0]] = $case[1];
		pg_assert_same(array('ok' => false, 'error' => 'response'), pg_webauthn_assertion_response($changed), $case[2]);
	}

	$changed = $json;
	unset($changed['response']['signature']);
	pg_assert_same(array('ok' => false, 'error' => 'response'), pg_webauthn_assertion_response($changed), 'no signature');
}

// -- the site as a relying party ---------------------------------------------

function test_webauthn_rp_id_is_the_host_alone()
{
	$cases = array(
		array('example.org', 'example.org'),
		array('Example.ORG', 'example.org'),
		array('example.org:8443', 'example.org'),
		array('example.org.', 'example.org'),
		array('shop.example.org', 'shop.example.org'),
		array('localhost', 'localhost'),
		array('localhost:8000', 'localhost'),
		array('xn--rnek-4qa.com', 'xn--rnek-4qa.com'),
		array('127.0.0.1', ''),
		array('127.0.0.1:8000', ''),
		array('[::1]:8000', ''),
		array('::1', ''),
		array('', ''),
		array('exa mple.org', ''),
		array('example..org', ''),
		array('-example.org', ''),
		array('example_shop.org', ''),
		array('example.org/path', ''),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[1], pg_webauthn_rp_id($case[0]), $case[0]);
	}

	if (function_exists('idn_to_ascii')) {
		pg_assert_same('xn--rnek-4qa.com', pg_webauthn_rp_id("\xc3\xb6rnek.com"), 'international name in its ASCII form');
	}
}

// An international host name is converted as the browser converts it (WHATWG
// URL: UTS #46 non-transitional): ß and final sigma keep their own letters,
// transitional processing would turn straße.de into strasse.de, another
// domain, which the browser refuses as the relying party and whose origin
// the signed answer never names.
function test_webauthn_international_host_as_the_browser_writes_it()
{
	if (!function_exists('idn_to_ascii')) {
		return;
	}

	pg_assert_same('xn--strae-oqa.de', pg_webauthn_rp_id('straße.de'), 'ß is kept, not strasse.de');
	pg_assert_same('xn--foo-lzc.gr', pg_webauthn_rp_id('ςfoo.gr'), 'final sigma is kept');
	pg_assert_same('https://xn--strae-oqa.de:8443', pg_webauthn_origin('https://', 'straße.de:8443'), 'the origin too');
	pg_assert_same('xn--rnek-4qa.com', pg_webauthn_rp_id('örnek.com'), 'Turkish letters as before');
	pg_assert_same('xn--k-ekaa7p.com.tr', pg_webauthn_rp_id('ışık.com.tr'), 'dotless i as the browser writes it');
}

function test_webauthn_origin_as_the_browser_writes_it()
{
	$cases = array(
		array('https://', 'Example.org', 'https://example.org'),
		array('https', 'example.org:443', 'https://example.org'),
		array('https://', 'example.org:8443', 'https://example.org:8443'),
		array('https://', 'example.org:80', 'https://example.org:80'),
		array('http://', 'localhost:8000', 'http://localhost:8000'),
		array('http://', 'localhost:80', 'http://localhost'),
		array('https://', 'example.org.', 'https://example.org'),
		array('https://', 'xn--rnek-4qa.com', 'https://xn--rnek-4qa.com'),
		array('ftp://', 'example.org', ''),
		array('https://', '', ''),
		array('https://', ':443', ''),
		array('https://', 'example.org:', ''),
		array('https://', 'example.org:abc', ''),
		array('https://', 'example.org:0443', ''),
		array('https://', 'example.org:65536', ''),
		array('https://', 'example.org:8443:1', ''),
		array('https://', 'example.org/path', ''),
		array('https://', 'user@example.org', ''),
		array('https://', '127.0.0.1', ''),
		array('https://', '[::1]:8443', ''),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[2], pg_webauthn_origin($case[0], $case[1]), $case[0] . $case[1]);
	}

	if (function_exists('idn_to_ascii')) {
		pg_assert_same('https://xn--rnek-4qa.com', pg_webauthn_origin('https://', "\xc3\xb6rnek.com"), 'international name in its ASCII form');
	}

	pg_assert_same('', pg_webauthn_origin(array('https'), 'example.org'), 'scheme not text');
	pg_assert_same('', pg_webauthn_origin('https', null), 'host not text');

	pg_assert_true(pg_webauthn_secure_context('https://', 'example.org'), 'https');
	pg_assert_false(pg_webauthn_secure_context('http://', 'example.org'), 'http');
	pg_assert_true(pg_webauthn_secure_context('http://', 'localhost:8000'), 'http on localhost');
	pg_assert_true(pg_webauthn_secure_context('http', 'shop.localhost'), 'http on a localhost subdomain');
	pg_assert_false(pg_webauthn_secure_context('https://', '10.0.0.1'), 'an IP address');
	pg_assert_false(pg_webauthn_secure_context('ftp://', 'example.org'), 'ftp');
}

function test_webauthn_aaguid_text()
{
	pg_assert_same('8446ccb9-ab1d-b374-750b-2367ff6f3a1f', pg_webauthn_aaguid_text(hex2bin('8446ccb9ab1db374750b2367ff6f3a1f')));
	pg_assert_same('00000000-0000-0000-0000-000000000000', pg_webauthn_aaguid_text(str_repeat("\x00", 16)));
	pg_assert_same('', pg_webauthn_aaguid_text('short'));
}

function test_webauthn_challenge_and_support()
{
	$challenge = pg_webauthn_challenge();

	pg_assert_same(32, strlen($challenge));
	pg_assert_true($challenge !== pg_webauthn_challenge(), 'a new challenge each time');
	pg_assert_true(pg_webauthn_supported(), 'this PHP can verify passkeys');
}

// -- data --------------------------------------------------------------------

// The test vectors of the W3C Web Authentication Level 3 specification,
// section 16 (https://www.w3.org/TR/webauthn-3/#sctn-test-vectors), as hex.
// Each registration and sign-in uses the same credential, the relying party
// ID example.org and the origin https://example.org; the names are the
// labels the specification derives each vector from. The vectors of
// algorithms the verifier refuses keep their registration only.
function webauthn_test_vectors()
{
	return array(
		'none.ES256' => array(
			'registration' => array(
				'challenge' => '00c30fb78531c464d2b6771dab8d7b603c01162f2fa486bea70f283ae556e130',
				'credential_id' => 'f91f391db4c9b2fde0ea70189cba3fb63f579ba6122b33ad94ff3ec330084be4',
				'aaguid' => '8446ccb9ab1db374750b2367ff6f3a1f',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a22414d4d5074345578784754' .
					'53746e63647134313759447742466938767049612d7077386f4f755657345441222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c' .
					'69656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64' .
					'7320696e20746865206675747572652c207375636820617320746869733a20426b5165446a646354427258426941774a544c' .
					'453551227d',
				'attestationObject' => 'a363666d74646e6f6e656761747453746d74a068617574684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8' .
					'edd46592a5e0f01452b2e4b559000000008446ccb9ab1db374750b2367ff6f3a1f0020f91f391db4c9b2fde0ea70189cba3f' .
					'b63f579ba6122b33ad94ff3ec330084be4a5010203262001215820afefa16f97ca9b2d23eb86ccb64098d20db90856062eb2' .
					'49c33a9b672f26df61225820930a56b87a2fca66334b03458abf879717c12cc68ed73290af2e2664796b9220',
			),
			'authentication' => array(
				'challenge' => '39c0e7521417ba54d43e8dc95174f423dee9bf3cd804ff6d65c857c9abf4d408',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a224f63446e55685158756c5455506f' .
					'334a5558543049393770767a7a59425039745a63685879617630314167222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b51900000000',
				'signature' => '3046022100f50a4e2e4409249c4a853ba361282f09841df4dd4547a13a87780218deffcd380221008480ac0f0b93538174f5' .
					'75bf11a1dd5d78c6e486013f937295ea13653e331e87',
			),
		),
		'packed-self.ES256' => array(
			'registration' => array(
				'challenge' => '7869c2b772d4b58eba9378cf8f29e26cf935aa77df0da89fa99c0bdc0a76f7e5',
				'credential_id' => '455ef34e2043a87db3d4afeb39bbcb6cc32df9347c789a865ecdca129cbef58c',
				'aaguid' => 'df850e09db6afbdfab51697791506cfc',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a2265476e4374334c55745936' .
					'366b336a506a796e6962506b31716e666644616966715a774c33417032392d55222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c' .
					'69656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64' .
					'7320696e20746865206675747572652c207375636820617320746869733a205539685458764b453255526b4d6e625f307859' .
					'485667227d',
				'attestationObject' => 'a363666d74667061636b65646761747453746d74a263616c672663736967584630440220067a20754ab925005dbf378097c9' .
					'2120031581c73228d1fb4f5b881bcd7da98302207fc7b147558c7c0eba3af18bd9d121fa3d3a26d17fe3f220272178f473b6' .
					'006d68617574684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b55d00000000' .
					'df850e09db6afbdfab51697791506cfc0020455ef34e2043a87db3d4afeb39bbcb6cc32df9347c789a865ecdca129cbef58c' .
					'a5010203262001215820eb151c8176b225cc651559fecf07af450fd85802046656b34c18f6cf193843c5225820927b8aa427' .
					'a2be1b8834d233a2d34f61f13bfd44119c325d5896e183fee484f2',
			),
			'authentication' => array(
				'challenge' => '4478a10b1352348dd160c1353b0d469b5db19eb91c27f7dfa6fed39fe26af20b',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a225248696843784e534e493352594d' .
					'45314f7731476d3132786e726b634a5f6666707637546e2d4a71386773222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c69656e' .
					'74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64732069' .
					'6e20746865206675747572652c207375636820617320746869733a206754623533727a36456853576f6d58477a696d433151' .
					'227d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50900000000',
				'signature' => '304402203310b9431903c401f1be2bdc8d23a4007682dbbddcf846994947b7f465daf84002204e94dd00047b316061b3b997' .
					'72b7efd95994a83ef584b3b6b825ea3550251b66',
			),
		),
		'none.ES256.crossOrigin' => array(
			'registration' => array(
				'challenge' => '3be5aacd03537142472340ab5969f240f1d87716e20b6807ac230655fa4b3b49',
				'credential_id' => '6e1050c0d2ca2f07c755cb2c66a74c64fa43065c18f938354d9915db2bd5ce57',
				'aaguid' => '883f4f6014f19c09d87aa38123be48d0',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a224f2d57717a514e5463554a' .
					'484930437257576e7951504859647862694332674872434d475666704c4f306b222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a747275652c22657874726144617461223a22636c69' .
					'656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c6473' .
					'20696e20746865206675747572652c207375636820617320746869733a207a5a717545744452394457717057357442575446' .
					'7567227d',
				'attestationObject' => 'a363666d74646e6f6e656761747453746d74a068617574684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8' .
					'edd46592a5e0f01452b2e4b54500000000883f4f6014f19c09d87aa38123be48d000206e1050c0d2ca2f07c755cb2c66a74c' .
					'64fa43065c18f938354d9915db2bd5ce57a501020326200121582022200a473f90b11078851550d03b4e44a2279f8c4eca27' .
					'b3153dedfe03e4e97d225820cbd0be95e746ad6f5a8191be11756e4c0420e72f65b466d39bc56b8b123a9c6e',
			),
			'authentication' => array(
				'challenge' => '876aa517ba83fdee65fcffdbca4c84eeae5d54f8041a1fc85c991e5bbb273137',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a226832716c463771445f65356c5f50' .
					'5f62796b7945377135645650674547685f49584a6b655737736e4d5463222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a747275652c22657874726144617461223a22636c69656e74' .
					'446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c647320696e' .
					'20746865206675747572652c207375636820617320746869733a2039327063545644304162792d713464746d6a3665666722' .
					'7d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50500000000',
				'signature' => '3046022100eb12fcf23b12764c0f122e22371fab92e283879fd798f38ee1841c951b6e40e7022100c76237ff9db77b3c56f3' .
					'0837cda6a09acfa2e915544e609c0733b1184036d1cf',
			),
		),
		'none.ES256.topOrigin' => array(
			'registration' => array(
				'challenge' => '4e1f4c6198699e33c14f192153f49d7e0e8e3577d5ac416c5f3adc92a41f27e5',
				'credential_id' => 'b8ad59b996047ab18e2ceb57206c362da57458793481f4a8ebf101c7ca7cc0f1',
				'aaguid' => '97586fd09799a76401c200455099ef2a',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a225468394d595a68706e6a50' .
					'4254786b68555f53646667364f4e58665672454673587a72636b7151664a2d55222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a747275652c22746f704f726967696e223a22687474' .
					'70733a2f2f6578616d706c652e636f6d227d',
				'attestationObject' => 'a363666d74646e6f6e656761747453746d74a068617574684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8' .
					'edd46592a5e0f01452b2e4b5410000000097586fd09799a76401c200455099ef2a0020b8ad59b996047ab18e2ceb57206c36' .
					'2da57458793481f4a8ebf101c7ca7cc0f1a5010203262001215820a1c47c1d82da4ebe82cd72207102b380670701993bc353' .
					'98ae2e5726427fe01d22582086c1080d82987028c7f54ecb1b01185de243b359294a0ed210cd47480f0adc88',
			),
			'authentication' => array(
				'challenge' => 'd54a5c8ca4b62a8e3bb321e3b2bc73856f85a10150db2939ac195739eb1ea066',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a22315570636a4b53324b6f34377379' .
					'486a7372787a68572d466f51465132796b3572426c584f6573656f4759222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a747275652c22746f704f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e636f6d222c22657874726144617461223a22636c69656e74446174614a534f4e206d617920626520' .
					'657874656e6465642077697468206164646974696f6e616c206669656c647320696e20746865206675747572652c20737563' .
					'6820617320746869733a205569466f4a4d56525148444146574669347678557051227d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50500000000',
				'signature' => '3045022100b5a70c81780d5fcc9a4f2ae9caae99058f8accaf58b91fb59329646c28ac6ffc022012e101c165db3c8e9957f0' .
					'c54dd6ca9b56bc3bd2f280bd2faa6c1d02c6e5c171',
			),
		),
		'none.ES256.long-credential-id' => array(
			'registration' => array(
				'challenge' => '1113c7265ccf5e65124282fa1d7819a7a14cb8539aa4cdbec7487e5f35d8ec6c',
				'credential_id' => '3a761a4e1674ad6c4305869435c0eee9c286172c229bb91b48b4ada140c0863417031305cce5b4a27a88d7fe728a5f5a627d' .
					'e771b4b40e77f187980c124f9fe832d7136010436a056cce716680587d23187cf1fc2c62ae86fc3e508ee9617ffc74fbc104' .
					'88ec16ec5e9096328669a898709b655e549738c666c1ae6281dc3b5f733c251d3eefb76ee70a3805ca91bcc18e49c8dc7f63' .
					'ebcb486ba8c3d6ab52b88ff72c6a5bb47c32f3ee8683a3ddc8abf60870448ec8a21b5bdcb183c7dead870255575a6df96eb1' .
					'b6a2a1019780cba9e4887b17ff1164bbbcc10eb0d86ed75984cd3fa3419103024507dfd9ce8f92c56af7914cb0bb50b87ba8' .
					'2a312bb7dcd93028dbdcd6adb266979667158335171e3682d37755701edbf9d872846a291d49e57ef09da1ec637f5052ed2a' .
					'a7407f7e61827468e94b461844f4c67be5fa9c6055a566f8fdfc29d4bf78a9ff275f552cc68ba543fa3962eea36fd1ea8453' .
					'764577d021d0a181efc1f6100ab2e4110039e21ee16970bda7432b6134492155afc126295b3a2eccd12c66a68e340969e995' .
					'e3e8c9c476e395cfc21203414110779474f1c9797406637dbe414f132519d3bf0ce4f01734ef0e1a12c3ad604ff15d766b16' .
					'24db6a5a7ccbff7bc35c9908df94aba277e0af48f04ff3d16381c47e5a37ed3988a67a3b1ecaa926336b33391fff04128f86' .
					'9991c9fabd905b6fe3ceef5f8b630ec1c5d2636d5b1961ad5ca5004170f6f5e482792aad989b0287fe91e5c479403397152f' .
					'1fa56aa79b156eb47e6c8ea3eb175c34cfb38ad8e772874639b1023d4d01395c94e55831671cc022aa6fa1e02a02c2e4abc7' .
					'76f6960e51f83b71a8c0f207b6a347573977812c9aa5480b0011aa739bd4b76c18c000cc4757cceccb920f007c40c00e37e5' .
					'ab21476cd9f6054a8fffb55a108f5c706e2cea2049d81fd321ff47d2a5761b0800955ab1d4f4889f55a84e2601c684f17a4a' .
					'de7453ea49591d0b59c8d9a765052f62219cf6ef4a5dd9539f0617d6ebbebce7c000455475d18449e25c49ef9a1e3efe18c0' .
					'9082ebe2058d7c347defaa92f0664553b805c7d76bbfce5f330aca220ac90a789380fc479ea0d8793205813cca590a912f69' .
					'9ad52f991a1bc0a503c3ec4b2a696719e3c26591a87127f7305cc7e72f4c8e39355ebb06a5b1042990f38710ee7aa612ee43' .
					'74bb82e878585a70a96c2a6b47f101a4ff154be4fd76a3167577a5cc54d9167c154c69ac35485e44cc898b719e1be3cc9c0f' .
					'b5624b8f8a0dae10947a41bf848b6c1bb33d1006ec077d7e286e3f2a7b4843716390119449fe2721e81a5ed2333d331c7120' .
					'765da58fadae73c19d9a8c4509cf8ac1e9d98b799a5274509069739b5823f3fb496663820033426988eefca53e580e0f9e0d' .
					'fe0992fc2e53a97e053639f98577058f995bdbd41cefdb',
				'aaguid' => '8f3360c2cd1b0ac14ffe0795c5d2638e',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a22455250484a6c7a50586d55' .
					'53516f4c364858675a7036464d75464f61704d322d7830682d587a5859374777222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'attestationObject' => 'a363666d74646e6f6e656761747453746d74a0686175746844617461590483bfabc37432958b063360d3ad6461c9c4735ae7' .
					'f8edd46592a5e0f01452b2e4b549000000008f3360c2cd1b0ac14ffe0795c5d2638e03ff3a761a4e1674ad6c4305869435c0' .
					'eee9c286172c229bb91b48b4ada140c0863417031305cce5b4a27a88d7fe728a5f5a627de771b4b40e77f187980c124f9fe8' .
					'32d7136010436a056cce716680587d23187cf1fc2c62ae86fc3e508ee9617ffc74fbc10488ec16ec5e9096328669a898709b' .
					'655e549738c666c1ae6281dc3b5f733c251d3eefb76ee70a3805ca91bcc18e49c8dc7f63ebcb486ba8c3d6ab52b88ff72c6a' .
					'5bb47c32f3ee8683a3ddc8abf60870448ec8a21b5bdcb183c7dead870255575a6df96eb1b6a2a1019780cba9e4887b17ff11' .
					'64bbbcc10eb0d86ed75984cd3fa3419103024507dfd9ce8f92c56af7914cb0bb50b87ba82a312bb7dcd93028dbdcd6adb266' .
					'979667158335171e3682d37755701edbf9d872846a291d49e57ef09da1ec637f5052ed2aa7407f7e61827468e94b461844f4' .
					'c67be5fa9c6055a566f8fdfc29d4bf78a9ff275f552cc68ba543fa3962eea36fd1ea8453764577d021d0a181efc1f6100ab2' .
					'e4110039e21ee16970bda7432b6134492155afc126295b3a2eccd12c66a68e340969e995e3e8c9c476e395cfc21203414110' .
					'779474f1c9797406637dbe414f132519d3bf0ce4f01734ef0e1a12c3ad604ff15d766b1624db6a5a7ccbff7bc35c9908df94' .
					'aba277e0af48f04ff3d16381c47e5a37ed3988a67a3b1ecaa926336b33391fff04128f869991c9fabd905b6fe3ceef5f8b63' .
					'0ec1c5d2636d5b1961ad5ca5004170f6f5e482792aad989b0287fe91e5c479403397152f1fa56aa79b156eb47e6c8ea3eb17' .
					'5c34cfb38ad8e772874639b1023d4d01395c94e55831671cc022aa6fa1e02a02c2e4abc776f6960e51f83b71a8c0f207b6a3' .
					'47573977812c9aa5480b0011aa739bd4b76c18c000cc4757cceccb920f007c40c00e37e5ab21476cd9f6054a8fffb55a108f' .
					'5c706e2cea2049d81fd321ff47d2a5761b0800955ab1d4f4889f55a84e2601c684f17a4ade7453ea49591d0b59c8d9a76505' .
					'2f62219cf6ef4a5dd9539f0617d6ebbebce7c000455475d18449e25c49ef9a1e3efe18c09082ebe2058d7c347defaa92f066' .
					'4553b805c7d76bbfce5f330aca220ac90a789380fc479ea0d8793205813cca590a912f699ad52f991a1bc0a503c3ec4b2a69' .
					'6719e3c26591a87127f7305cc7e72f4c8e39355ebb06a5b1042990f38710ee7aa612ee4374bb82e878585a70a96c2a6b47f1' .
					'01a4ff154be4fd76a3167577a5cc54d9167c154c69ac35485e44cc898b719e1be3cc9c0fb5624b8f8a0dae10947a41bf848b' .
					'6c1bb33d1006ec077d7e286e3f2a7b4843716390119449fe2721e81a5ed2333d331c7120765da58fadae73c19d9a8c4509cf' .
					'8ac1e9d98b799a5274509069739b5823f3fb496663820033426988eefca53e580e0f9e0dfe0992fc2e53a97e053639f98577' .
					'058f995bdbd41cefdba50102032620012158203b8176b7504489cc593046d7988abb7905a742de6ac2cdc748a873c663e90c' .
					'b12258201436d5edc9a75f23999eef9d5950a5c2455514ee1014084720f841a06b828a11',
			),
			'authentication' => array(
				'challenge' => 'ef1deba56dce48f674a447ccf63b9599258ce87648e5c396f2ef0ca1da460e3b',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a22377833727057334f53505a307045' .
					'664d396a75566d53574d36485a4935634f573875384d6f647047446a73222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50d00000000',
				'signature' => '304502203ecef83fb12a0cae7841055f9f87103a99fd14b424194bbf06c4623d3ee6e3fd022100d2ace346db262b1374a6b7' .
					'0faa51f518a42ddca13a4125ce6f5052a75bac9fb6',
			),
		),
		'packed.ES256' => array(
			'registration' => array(
				'challenge' => 'c1184a5fddf8045e13dc47f54b61f5a656b666b59018f16d870e9256e9952012',
				'credential_id' => 'c9a6f5b3462d02873fea0c56862234f99f081728084e511bb7760201a89054a5',
				'aaguid' => '876ca4f52071c3e9b25509ef2cdf7ed6',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a227752684b58393334424634' .
					'543345663153324831706c61325a725751475046746877365356756d56494249222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c' .
					'69656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64' .
					'7320696e20746865206675747572652c207375636820617320746869733a20396138624e596a4b436757724258552d66436c' .
					'316167227d',
				'attestationObject' => 'a363666d74667061636b65646761747453746d74a363616c6726637369675847304502203f19ec4b229f46ab8c45eff29b90' .
					'4ff10c0390dc40bf1216f04a78f4ceba3425022100fe7041a32759aff05a0f9f26c70a999c7a284451ba89234a1d3483c25e' .
					'21925b637835638159022530820221308201c8a00302010202110088c220f83c8ef1feafe94deae45faad0300a06082a8648' .
					'ce3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c03' .
					'57334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b300906035504' .
					'06130241413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c0603550403' .
					'0c15576562417574686e207465737420766563746f7273310c300a060355040a0c0357334331223020060355040b0c194175' .
					'7468656e74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648ce3d020106' .
					'082a8648ce3d03010703420004a91ba4389409dd38a428141940ca8feb1ac0d7b4350558104a3777a49322f3798440f378b3' .
					'398ab2d3bb7bf91322c92eb23556f59ad0a836fec4c7663b0e4dc3a360305e300c0603551d130101ff04023000300e060355' .
					'1d0f0101ff040403020780301d0603551d0e04160414a589ba72d060842ab11f74fb246bdedab16f9b9b301f0603551d2304' .
					'183016801445aff715b0dd786741fee996ebc16547a3931b1e300a06082a8648ce3d040302034700304402201726b9d85ecd' .
					'8a5ed51163722ca3a20886fd9b242a0aa0453d442116075defd502207ef471e530ac87961a88a7f0d0c17b091ffc6b9238d3' .
					'0f79f635b417be5910e768617574684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452' .
					'b2e4b54d00000000876ca4f52071c3e9b25509ef2cdf7ed60020c9a6f5b3462d02873fea0c56862234f99f081728084e511b' .
					'b7760201a89054a5a50102032620012158201cf27f25da591208a4239c2e324f104f585525479a29edeedd830f48e77aeae5' .
					'22582059e4b7da6c0106e206ce390c93ab98a15a5ec3887e57f0cc2bece803b920c423',
			),
			'authentication' => array(
				'challenge' => 'b1106fa46a57bef1781511c0557dc898a03413d5f0f17d244630c194c7e1adb5',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a227352427670477058767646344652' .
					'4841565833496d4b4130453958773858306b526a44426c4d6668726255222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c69656e' .
					'74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64732069' .
					'6e20746865206675747572652c207375636820617320746869733a20415a4d77794d78496244382d756775464e7036723851' .
					'227d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50d00000000',
				'signature' => '30450220694969d3ee928de6f02ef23a9c644d7d779916451734a94b432542f498a1ebe90221008b0819c824218a97152cd0' .
					'99c55bfb1477b29d900a49a64018314f9bfccda163',
			),
		),
		'packed.RS256' => array(
			'registration' => array(
				'challenge' => 'bea8f0770009bd57f2c0df6fea9f743a27e4b61bbe923c862c7aad7a9fc8e4a6',
				'credential_id' => '992a18acc83f67533600c1138a4b4c4bd236de13629cf025ed17cb00b00b74df',
				'aaguid' => '428f8878298b9862a36ad8c7527bfef2',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a2276716a776477414a765666' .
					'79774e3976367039304f69666b7468752d6b6a79474c48717465705f49354b59222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'attestationObject' => 'a363666d74667061636b65646761747453746d74a363616c672663736967584730450221008b8c5c6ea8c142c032e0be69e1' .
					'353d44461c5c9109941cdda951b976eb95b6b302204d52f406c19e254b3ff9589bd18070fb055ac8db12fdd0a6734bea9d71' .
					'68e900637835638159022630820222308201c7a00302010202101f6fb7a5ece81b45896b983a995da5f3300a06082a8648ce' .
					'3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c0357' .
					'334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b30090603550406' .
					'130241413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c06035504030c' .
					'15576562417574686e207465737420766563746f7273310c300a060355040a0c0357334331223020060355040b0c19417574' .
					'68656e74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648ce3d02010608' .
					'2a8648ce3d03010703420004b7b36b7542a11120b443c794d0c99fdc25a06b76586413d81e086163ef6fe147a557afc34e28' .
					'61d9057d6d465d4705a0310550bdeeb5f35ee35b9425ab859981a360305e300c0603551d130101ff04023000300e0603551d' .
					'0f0101ff040403020780301d0603551d0e04160414fb37b647bccfb9e54d989eaaacc1633868703fb3301f0603551d230418' .
					'3016801445aff715b0dd786741fee996ebc16547a3931b1e300a06082a8648ce3d0403020349003046022100b86bc129d92a' .
					'fca7d9869a39f70f139a305b4073a39eb654d81424bed5757d91022100cf9f7c60cab7c4a7d3e7f0020f281a93d4fd0a9f95' .
					'121b989f56932a68885fba68617574684461746159021bbfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f0' .
					'1452b2e4b55d00000000428f8878298b9862a36ad8c7527bfef20020992a18acc83f67533600c1138a4b4c4bd236de13629c' .
					'f025ed17cb00b00b74dfa4010303390100205901b403ffffffffffffffffffffffffffffffffffffffffffffffffffffffff' .
					'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff' .
					'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff' .
					'fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff7ffffffffffffffffffffffffffffffffffff' .
					'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff' .
					'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff800000' .
					'0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000' .
					'0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000' .
					'0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000' .
					'000000000000012143010001',
			),
			'authentication' => array(
				'challenge' => '295f59f5fa8fe62c5aca9e27626c78c8da376ae6d8cd2dd29aebad601e1bc4c5',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a224b56395a39667150356978617970' .
					'346e596d7834794e6f33617562597a5333536d75757459423462784d55222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b51900000000',
				'signature' => '01063d52d7c39b4d432fc7063c5d93e582bdcb16889cd71f888d67d880ea730a428498d3bc8e1ee11f2b1ecbe6c292b118c5' .
					'5ffaaddefa8cad0a54dd137c51f1eec673f1bb6c4d1789d6826a222b22d0f585fc901fdc933212e579d199b89d672aa44891' .
					'333e6a1355536025e82b25590256c3538229b55737083b2f6b9377e49e2472f11952f79fdd0da180b5ffd901b4049a8f081b' .
					'b40711bef76c62aed943571f2d0575304cb549d68d8892f95086a30f93716aee818f8dc06e96c0d5e0ed4cfa9fd8773d9046' .
					'4b68cf140f7986666ff9c9e3302acd0535d60d769f465e2ab57ef8aabc89fccfef7ba32a64154a8b3d26be2298f470b8cc53' .
					'77dbe3dfd4b0b45f8f01e63bde6cfc76b62771f9b70aa27cf40152cad93aa5acd784fd4b90f676e2ea828d0bf2400aebbaae' .
					'4153e5838f537f88b6228346782a93a899be66ec77de45b3efcf311da6321c92e6b0cd11bfe653bf3e98cee8e341f02d67db' .
					'b6f9c98d9e8178090cfb5b70fbc6d541599ac794ae2f1d4de1286ec8de8c2daf7b1d15c8438e90d924df5c19045220a4c843' .
					'8c1b979bbe016cf3d0eeec23c3999d4882cc645b776de930756612cdc6dd398160ff02a6',
			),
		),
		'packed.ES384' => array(
			'registration' => array(
				'challenge' => '567b030b3e186bc1d169dd45b79f9e0d86f1fd63474da3eade5bdb8db379a0c3',
				'credential_id' => '953ae2dd9f28b1a1d5802c83e1f65833bb9769a08de82d812bc27c13fc6f06a9',
				'aaguid' => 'e950dcda3bdae1d087cda380a897848b',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a22566e7344437a3459613848' .
					'526164314674352d65445962785f574e4854615071336c76626a624e356f4d4d222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'attestationObject' => 'a363666d74667061636b65646761747453746d74a363616c67266373696758473045022100c56ecc970b7843833e0f461fde' .
					'26233f61eb395161d481558c08b9c6ed61675b022029f5e05033705cd0f9b0a07e149468ec308a4f84906409efdceb1da20a' .
					'7518d6637835638159022530820221308201c7a00302010202103d0a5588bb87ebb1d4cee4a1807c1b7c300a06082a8648ce' .
					'3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c0357' .
					'334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b30090603550406' .
					'130241413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c06035504030c' .
					'15576562417574686e207465737420766563746f7273310c300a060355040a0c0357334331223020060355040b0c19417574' .
					'68656e74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648ce3d02010608' .
					'2a8648ce3d0301070342000417e5cc91d676d370e36aa7de40c25aacb45a3845f13d2932088ece2270b9b431241c219c22d0' .
					'c256c9438ade00f2c05e62f8ef906b9b997ae9f3c460c2db66f5a360305e300c0603551d130101ff04023000300e0603551d' .
					'0f0101ff040403020780301d0603551d0e04160414c7c8dd95382a2230e4c0dd3664338fa908169a9c301f0603551d230418' .
					'3016801445aff715b0dd786741fee996ebc16547a3931b1e300a06082a8648ce3d0403020348003045022054068cc9ae0389' .
					'37b7c468c307edb9c6927ffdeb6a20070c483eb40330f99f10022100cf41953919c3c04693d6b1f42a613753f204e70e85fc' .
					'6e9b17036170b83596e068617574684461746158c5bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452' .
					'b2e4b55900000000e950dcda3bdae1d087cda380a897848b0020953ae2dd9f28b1a1d5802c83e1f65833bb9769a08de82d81' .
					'2bc27c13fc6f06a9a5010203382220022158304866bd8b01da789e9eb806e5eab05ae5a638542296ab057a2f1bbce9b58f8a' .
					'08b9171390b58a37ac7fffc2c5f45857da2258302a0b024c7f4b72072a1f96bd30a7261aae9571dd39870eb29e55c0941c6b' .
					'08e89629a1ea1216aa64ce57c2807bf3901a',
			),
		),
		'packed.ES512' => array(
			'registration' => array(
				'challenge' => '4ee220cd92b07e11451cb4c201c5755bd879848e492a9b12d79135c62764dc2fd28ead4808cafe5ad1de8fa9e08d4a8eeafe' .
					'a4dfb333877b02bc503f475d3b0c1394a7683baaf4f2477829f7b8cf750948985558748c073068396fcfdcd3f245bf2038e6' .
					'bb38d7532768aad13be8c118f727722e7426139041e9caca503884c5',
				'credential_id' => 'd17d5af7e3f37c56622a67c8462c9e1c6336dfccb8b61d359dc47378dba58ce4',
				'aaguid' => '39d8ce6a3cf61025775083a738e5c254',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a22547549677a5a4b77666846' .
					'46484c544341635631573968356849354a4b707353313545317869646b33435f536a713149434d722d577448656a366e676a' .
					'55714f3676366b33374d7a683373437646415f5231303744424f5570326737717654795233677039376a5064516c496d4656' .
					'59644977484d47673562385f63305f4a46767941343572733431314d6e614b72524f2d6a424750636e636935304a684f5151' .
					'656e4b796c4134684d55222c226f726967696e223a2268747470733a2f2f6578616d706c652e6f7267222c2263726f73734f' .
					'726967696e223a66616c73652c22657874726144617461223a22636c69656e74446174614a534f4e206d6179206265206578' .
					'74656e6465642077697468206164646974696f6e616c206669656c647320696e20746865206675747572652c207375636820' .
					'617320746869733a206f3371566a4f4c327454576d3447786b7a495f516767227d',
				'attestationObject' => 'a363666d74667061636b65646761747453746d74a363616c67266373696758473045022100ce158f6c04aa5c14c0dd3e1103' .
					'cf93664896fb5c337a66dbd7dba31546ff0d41022071cbcd0d3b8e218a6d05374e0ef8031329d25059002ac1603d02d5fd8a' .
					'0a29cb637835638159022730820223308201c8a0030201020211008a128b7ebe52b993835779e6d9b81355300a06082a8648' .
					'ce3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c03' .
					'57334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b300906035504' .
					'06130241413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c0603550403' .
					'0c15576562417574686e207465737420766563746f7273310c300a060355040a0c0357334331223020060355040b0c194175' .
					'7468656e74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648ce3d020106' .
					'082a8648ce3d03010703420004940b68885291536e2f7c60c05acfb252e7eebcf4304425dd93ab7b1962f20492bf18dc0f12' .
					'862599e81fb764ac92151f9a78fcbb35d7a26c8c52949b18133c06a360305e300c0603551d130101ff04023000300e060355' .
					'1d0f0101ff040403020780301d0603551d0e041604143ffad863abcd3dc5717b8a252189f41af97e7f31301f0603551d2304' .
					'183016801445aff715b0dd786741fee996ebc16547a3931b1e300a06082a8648ce3d0403020349003046022100832c8b64c4' .
					'f0188bd32e1bec63e13301cdc03165d3ef840d1f3dabb9a5719f83022100add57a9d5bedec98f29222dfc97ea795d055ee13' .
					'a02a153d02be9ce00aedeb9168617574684461746158e9bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f0' .
					'1452b2e4b54d0000000039d8ce6a3cf61025775083a738e5c2540020d17d5af7e3f37c56622a67c8462c9e1c6336dfccb8b6' .
					'1d359dc47378dba58ce4a5010203382320032158420083240a2c3ad21a3dc0a6daa3d8bc05a46d7cd9825ba010ae2a22686c' .
					'2d6d663d7d5f678987fb1e767542e63dc197ae915e25f8ee284651af29066910a2cc083f50225842017337df47ab5cce5d71' .
					'6ef8caffa97a3012689b1f326ea6c43a1ba9596c72f71f0122390143552b42be772b4c35ffb961220c743b486a601ea4cb6d' .
					'5412f5b078d3',
			),
		),
		'packed.EdDSA' => array(
			'registration' => array(
				'challenge' => 'a8abf9dabdc6b0df63466b39bda9e8a34a34e185337a59f1c579990676d3b3bd',
				'credential_id' => 'ce9f840ed96599580cd140fbc7bb3230633f50f61041aff73308ae71caa8a2bd',
				'aaguid' => 'd5aa33581e8ca478e20fe713f5d32ff2',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a22714b763532723347734e39' .
					'6a526d733576616e6f6f306f303459557a656c6e7878586d5a426e6254733730222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c' .
					'69656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64' .
					'7320696e20746865206675747572652c207375636820617320746869733a20425f44543567375a445f2d394f544c59583549' .
					'764551227d',
				'attestationObject' => 'a363666d74667061636b65646761747453746d74a363616c67266373696758483046022100d83f60bd80269537583218858a' .
					'efb03ac57d45fa06e42feaae332d187f62da9f022100a02bd3cb6f7e1d283c93bad1f3f4b5a4c0494463da7fdbf256949116' .
					'754d1f17637835638159022730820223308201c8a003020102021100b2cfc9ea33c8643b0e1a760463eaf164300a06082a86' .
					'48ce3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c' .
					'0357334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b3009060355' .
					'0406130241413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c06035504' .
					'030c15576562417574686e207465737420766563746f7273310c300a060355040a0c0357334331223020060355040b0c1941' .
					'757468656e74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648ce3d0201' .
					'06082a8648ce3d03010703420004dd2b7a564b73b8c0b81c4c62e521925c4d1198ec9f583dbf1eebe364b65cd9c29a9bdf34' .
					'6aaa81fb6b9507e5249a52fdaf8e39e26b0b7dc45992a7e233b70f70a360305e300c0603551d130101ff04023000300e0603' .
					'551d0f0101ff040403020780301d0603551d0e041604140ae27546bc7eccb1b4b597bd354f0c0b1f1f8f8e301f0603551d23' .
					'04183016801445aff715b0dd786741fee996ebc16547a3931b1e300a06082a8648ce3d0403020349003046022100a0d434ec' .
					'b5fc3bfd7da5f41904517ad2836249f561bd834ba7a438a8ab7a4ce8022100fac845bb7a02513b58e9f319654dbe49b0f02b' .
					'95835bac568c71f8a18cdde9ab6861757468446174615881bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0' .
					'f01452b2e4b54100000000d5aa33581e8ca478e20fe713f5d32ff20020ce9f840ed96599580cd140fbc7bb3230633f50f610' .
					'41aff73308ae71caa8a2bda401010327200621582044e06ddd331c36a8dc667bab52bcae63486c916aa5e339e6acebaa8493' .
					'4bf832',
			),
		),
		'packed.Ed448' => array(
			'registration' => array(
				'challenge' => '2578d0801b5a005b5451e540121788cb01949e187b91db13f58755403efbf337',
				'credential_id' => '224fcde324e6b075ede55098a24b9ddce5f5a7c71d23703efd528a38f8a5f33c',
				'aaguid' => '41c913aeda925fe02273322e34c2ae67',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a224a586a5167427461414674' .
					'555565564145686549797747556e6868376b6473543959645651443737387a63222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c' .
					'69656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64' .
					'7320696e20746865206675747572652c207375636820617320746869733a2042517141336965485653484d54444d57774732' .
					'6b4b77227d',
				'attestationObject' => 'a363666d74667061636b65646761747453746d74a363616c67266373696758473045022100c5430b6b53f6b8fd1e5fa46ae7' .
					'ebb8f9e9ae0d10ebc748e876e2d979b39b1382022008c5cda8af174f2d3c050d5d14eec0721031364cb12924a06633d4924c' .
					'6bcad3637835638159022730820223308201c8a003020102021100cff4228697d6e5ac47480b2390677f05300a06082a8648' .
					'ce3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c03' .
					'57334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b300906035504' .
					'06130241413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c0603550403' .
					'0c15576562417574686e207465737420766563746f7273310c300a060355040a0c0357334331223020060355040b0c194175' .
					'7468656e74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648ce3d020106' .
					'082a8648ce3d03010703420004b85aaf790c824037cfe9fc56ab8d7ce6fbfaff2e3fe7c8d745734c3c6e3c6ce880d505ccdb' .
					'1e2c3738680e6f49f475e4d8d0b6c29060e6e0d7a6392fb69094cea360305e300c0603551d130101ff04023000300e060355' .
					'1d0f0101ff040403020780301d0603551d0e04160414fa8f81c2dcc0e194ae5034c7e79dcf6d9d8593e2301f0603551d2304' .
					'183016801445aff715b0dd786741fee996ebc16547a3931b1e300a06082a8648ce3d0403020349003046022100e761f54215' .
					'ad92f27c2c14b9eea3e39e8c22429e833ecba5be918987aa72e0e6022100d5a714df479c238586b7d9e6684ea84991087038' .
					'b0fef6a29b57b66b74df05fd686175746844617461589bbfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f0' .
					'1452b2e4b5590000000041c913aeda925fe02273322e34c2ae670020224fcde324e6b075ede55098a24b9ddce5f5a7c71d23' .
					'703efd528a38f8a5f33ca4010103383420072158398051ef4f94670b5abf17da2e9558ba6eba94eb8704363915b4d666de28' .
					'7ad329de9f1f075211aba602dc6e7a5e52b15a8ee1c984a9f8887380',
			),
		),
		'tpm.ES256' => array(
			'registration' => array(
				'challenge' => 'cfc82cdf1ceee876120aa88f0364f0910193460cfb97a317b2fe090694f9a299',
				'credential_id' => 'ec27bec7521c894bbb821105ea3724c90e770cf1fa354157ef18d0f18f78bea9',
				'aaguid' => '4b92a377fc5f6107c4c85c190adbfd99',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a227a38677333787a75364859' .
					'5343716950413254776b51475452677a376c364d587376344a427054356f706b222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'attestationObject' => 'a363666d746374706d6761747453746d74a663616c67266373696758463044022066e5826a652091030fd444e33c3eca2bc6' .
					'dc548cf3045013addb38aa6457a21002203f3a5c95c9e707d0e555041bcc8698ee4ebc04e26cc8bae4597054717898517663' .
					'76657263322e30637835638159023a30820236308201dca0030201020210311fc42da0ab10c43a9b1bf3a75e34e2300a0608' .
					'2a8648ce3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a06035504' .
					'0a0c0357334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b300906' .
					'03550406130241413020170d3234303130313030303030305a180f33303234303130313030303030305a3000305930130607' .
					'2a8648ce3d020106082a8648ce3d03010703420004c54e3f109094f60d7699b7db5d838569ffd1f3e1c9e897cd9eb40063f9' .
					'402e3e9937e936cf1fcd5eb743ff443c97ab2edcd7c8e0e6cf6cfd413b8ab19fffa769a381d33081d0300c0603551d130101' .
					'ff04023000300e0603551d0f0101ff040403020780301d0603551d0e041604145f546cb6973d4981e80fcdc7463859f58796' .
					'80e4301f0603551d2304183016801445aff715b0dd786741fee996ebc16547a3931b1e30100603551d250409300706056781' .
					'050803305e0603551d110101ff04543052a450304e314c3014060567810502010c0b69643a30303030303030303014060567' .
					'810502030c0b69643a3030303030303030301e060567810502020c15576562417574686e207465737420766563746f727330' .
					'0a06082a8648ce3d0403020348003045022063c9a2797b8066f1db34dd609f1ab6695607e7a98e9ff8090a68853c9a9fc949' .
					'022100a55831a39f5b8a2aa9a68837829cabf43fea2a5cea4859ae851cac78e6ac3e97677075624172656158560023000b00' .
					'04000000000010001000030010002041202698c9d9753fb4bb3f27cd09fe6b8afdb76438ee2ae54d7c9dade10d864b0020d8' .
					'735115cdb330a63ea1d6e43d5000f4bd56f99bce83ee1d73301fc270116d076863657274496e666f5869ff54434780170000' .
					'0020277d0e05579dd013215a62273f7f3a3e7e191ead2654a3036d75a5a3ee37a6b000000000000000001111111122222222' .
					'3300000000000000000022000b9c42d8aad5939331b9af3711af179f17123178098c9a7d0ca89fcd1fc800f3c70000686175' .
					'74684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b54d000000004b92a377fc' .
					'5f6107c4c85c190adbfd990020ec27bec7521c894bbb821105ea3724c90e770cf1fa354157ef18d0f18f78bea9a501020326' .
					'200121582041202698c9d9753fb4bb3f27cd09fe6b8afdb76438ee2ae54d7c9dade10d864b225820d8735115cdb330a63ea1' .
					'd6e43d5000f4bd56f99bce83ee1d73301fc270116d07',
			),
			'authentication' => array(
				'challenge' => '00093b66c21d5b5e89f7a07082118907ea3e502d343b314b8c5a54d62db202fb',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a2241416b375a7349645731364a3936' .
					'42776768474a422d6f2d554330304f7a464c6a46705531693279417673222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50d00000000',
				'signature' => '3045022060dc76b1607ec716c6e5eba8d056695ed6bc47b2e3d7a729c34e759e3ab66aa0022100d010a9e8fddcb64c439dfd' .
					'ca628ddb33cf245d567d157d9f66f942601bed9b38',
			),
		),
		'android-key.ES256' => array(
			'registration' => array(
				'challenge' => '3de1f0b7365dccde3ff0cbf25e26ffa7baff87ef106c80fc865dc402d9960050',
				'credential_id' => '0a4729519788b6ed8a2d772b494e186244d8c798c052960dbc8c10c915176795',
				'aaguid' => 'ade9705e1ce7085b899a540d02199bf8',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a2250654877747a5a647a4e34' .
					'5f384d76795869625f7037725f682d385162494438686c334541746d57414641222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c' .
					'69656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64' .
					'7320696e20746865206675747572652c207375636820617320746869733a205656316351755232714c4d5f616d50666f487a' .
					'4c3067227d',
				'attestationObject' => 'a363666d746b616e64726f69642d6b65796761747453746d74a363616c67266373696758483046022100e95512982aa3f216' .
					'cff2e87c8ec57057b8529f674eaabeccaa27fd03d8779f19022100afb6bf459da4a826f00d01fc6b60712ff31dc4eb331619' .
					'c8f874bb17e4314e94637835638159027c308202783082021ea00302010202101ff91f76b63f44812f998b250b0286bf300a' .
					'06082a8648ce3d0403023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a0603' .
					'55040a0c0357334331253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b30' .
					'090603550406130241413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c' .
					'06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c035733433122302006035504' .
					'0b0c1941757468656e74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648' .
					'ce3d020106082a8648ce3d0301070342000499169657036d089a2a9821a7d0063d341f1a4613389359636efab5f3cbf1accf' .
					'dd91c55543176ea99b644406dd1dd63774b6af65ac759e06ff40b1c8ab02df6ba381b63081b3300c0603551d130101ff0402' .
					'3000300e0603551d0f0101ff040403020780301d0603551d0e041604141ac81e50641e8d1339ab9f7eb25f0cd5aac054b030' .
					'1f0603551d2304183016801445aff715b0dd786741fee996ebc16547a3931b1e3053060a2b06010401d67902011104453043' .
					'0202012c0a01000201000a01000420b435028d7b6a8f83bb461d41c19b053a9d3cdb30351a4f374cd4cde8dbefb606040030' .
					'00300ea1053103020102bf853e03020100300a06082a8648ce3d040302034800304502201354f9149996f6e1830852da629b' .
					'86d37c10da0ffe17471c67d48765ed83f63b02210086db5746944cecdfce071d9e041072ceac3d1c504382f205676e9a1f02' .
					'd7632568617574684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b55d000000' .
					'00ade9705e1ce7085b899a540d02199bf800200a4729519788b6ed8a2d772b494e186244d8c798c052960dbc8c10c9151767' .
					'95a501020326200121582099169657036d089a2a9821a7d0063d341f1a4613389359636efab5f3cbf1accf225820dd91c555' .
					'43176ea99b644406dd1dd63774b6af65ac759e06ff40b1c8ab02df6b',
			),
			'authentication' => array(
				'challenge' => 'e4ee05ca9dbced74116540f24ed9adc62aae8507560522844ffa7eea14f7af86',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a22354f344679703238375851525a55' .
					'447954746d74786971756851645742534b45545f702d36685433723459222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c69656e' .
					'74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64732069' .
					'6e20746865206675747572652c207375636820617320746869733a2071784a78422d5f78677277794d4c3631386472536e41' .
					'227d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50900000000',
				'signature' => '3045022100a2b5e37da43ceb63566f6e2817c6cbef261073d0cadfd213ff6229bd33ddea6c02203d77eb3202fc92b9b5bb84' .
					'3ef082c7766f8c7f23194c92708ff2f3d1de765a8f',
			),
		),
		'apple.ES256' => array(
			'registration' => array(
				'challenge' => 'f7f688213852007775009cf8c096fda89d60b9a9fb5a50dd81dd9898af5a0609',
				'credential_id' => '9c4a5886af9283d9be3e9ec55978dedfdce2e3b365cab193ae850c16238fafb8',
				'aaguid' => '748210a20076616a733b2114336fc384',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a22395f614949546853414864' .
					'31414a7a34774a6239714a316775616e37576c4464676432596d4b396142676b222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73652c22657874726144617461223a22636c' .
					'69656e74446174614a534f4e206d617920626520657874656e6465642077697468206164646974696f6e616c206669656c64' .
					'7320696e20746865206675747572652c207375636820617320746869733a20546a4c506e704f6158515572464e6362483274' .
					'545a41227d',
				'attestationObject' => 'a363666d74656170706c656761747453746d74a1637835638159025c30820258308201fea0030201020210394275613d5310' .
					'b81a29ce90f48b61c1300a06082a8648ce3d0403023062311e301c06035504030c15576562417574686e2074657374207665' .
					'63746f7273310c300a060355040a0c0357334331253023060355040b0c1c41757468656e74696361746f7220417474657374' .
					'6174696f6e204341310b30090603550406130241413020170d3234303130313030303030305a180f33303234303130313030' .
					'303030305a305f311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c03' .
					'57334331223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e310b300906035504061302' .
					'41413059301306072a8648ce3d020106082a8648ce3d030107034200048a3d5b1b4c543a706bf6e4b00afedb3c930b690dd2' .
					'86934fe2911f779cc7761af728e1aa3b0ff66692192daa776b83ddf8e3340d2d9a0eabdfc324eb3e2f136ca3819630819330' .
					'0c0603551d130101ff04023000300e0603551d0f0101ff040403020780301d0603551d0e0416041412f1ce6c0ae39b403bfc' .
					'9200317bc183a4e4d766301f0603551d2304183016801445aff715b0dd786741fee996ebc16547a3931b1e303306092a8648' .
					'86f76364080204263024a1220420d7a86e7233fb843eb0eeb407d8b76ff7e4f82d218cf5dbb461d752073f5cb29a300a0608' .
					'2a8648ce3d0403020348003045022070f5c2ede3000e9dae358d412b26a4acbf18f4cdeb80f5b13fcd564d090c39ec022100' .
					'f672e2c3dbe117c9b1490b3c660abf5dcd74398187082dacb58b6744de4aca6068617574684461746158a4bfabc37432958b' .
					'063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b54900000000748210a20076616a733b2114336fc38400209c4a' .
					'5886af9283d9be3e9ec55978dedfdce2e3b365cab193ae850c16238fafb8a50102032620012158208a3d5b1b4c543a706bf6' .
					'e4b00afedb3c930b690dd286934fe2911f779cc7761a225820f728e1aa3b0ff66692192daa776b83ddf8e3340d2d9a0eabdf' .
					'c324eb3e2f136c',
			),
			'authentication' => array(
				'challenge' => 'd3eb2964641e26fed023403a72dde093b19c4ba9008c3f9dd83fcfd347a66d05',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a22302d73705a4751654a7637514930' .
					'4136637433676b37476353366b416a442d6432445f503030656d625155222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50900000000',
				'signature' => '3046022100ee35db795ce28044e1f8231d68b3d79a9882f7415aa35c1b5ac74d24251073c8022100dcc65691650a412d0cee' .
					'f843710c09827acf26c7845bddac07eec95863e7fc4c',
			),
		),
		'fido-u2f.ES256' => array(
			'registration' => array(
				'challenge' => 'e074372990b9caa507a227dfc67b003780c45325380d1a90c20f81ed7d080c06',
				'credential_id' => 'a4ba6e2d2cfec43648d7d25c5ed5659bc18f2b781538527ebd492de03256bdf4',
				'aaguid' => 'afb3c2efc054df425013d5c88e79c3c1',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e637265617465222c226368616c6c656e6765223a22344851334b5a4335797155' .
					'486f696666786e73414e344445557955344452715177672d4237583049444159222c226f726967696e223a2268747470733a' .
					'2f2f6578616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'attestationObject' => 'a363666d74686669646f2d7532666761747453746d74a26373696758473045022100f41887a20063bb26867cb9751978acce' .
					'a5b81791a68f4f4dd6ea1fb6a5c086c302204e5e00aa3895777e6608f1f375f95450045da3da57a0e4fd451df35a31d2d98a' .
					'637835638159022530820221308201c7a003020102021004f66dc6542ea7719dea416d325a2401300a06082a8648ce3d0403' .
					'023062311e301c06035504030c15576562417574686e207465737420766563746f7273310c300a060355040a0c0357334331' .
					'253023060355040b0c1c41757468656e74696361746f72204174746573746174696f6e204341310b30090603550406130241' .
					'413020170d3234303130313030303030305a180f33303234303130313030303030305a305f311e301c06035504030c155765' .
					'62417574686e207465737420766563746f7273310c300a060355040a0c0357334331223020060355040b0c1941757468656e' .
					'74696361746f72204174746573746174696f6e310b30090603550406130241413059301306072a8648ce3d020106082a8648' .
					'ce3d0301070342000456fffa7093dede46aefeefb6e520c7ccc78967636e2f92582ba71455f64e93932dff3be4e0d4ef68e3' .
					'e3b73aa087e26a0a0a30b02dc2aa2309db4c3a2fc936dea360305e300c0603551d130101ff04023000300e0603551d0f0101' .
					'ff040403020780301d0603551d0e04160414420822eb1908b5cd3911017fbcad4641c05e05a3301f0603551d230418301680' .
					'1445aff715b0dd786741fee996ebc16547a3931b1e300a06082a8648ce3d040302034800304502200d0b777f0a0b181ad283' .
					'0275acc3150fd6092430bcd034fd77beb7bdf8c2d546022100d4864edd95daa3927080855df199f1717299b24a5eecefbd01' .
					'7455a9b934d8f668617574684461746158a4bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b5' .
					'4100000000afb3c2efc054df425013d5c88e79c3c10020a4ba6e2d2cfec43648d7d25c5ed5659bc18f2b781538527ebd492d' .
					'e03256bdf4a5010203262001215820b0d62de6b30f86f0bac7a9016951391c2e31849e2e64661cbd2b13cd7d5508ad225820' .
					'503b0bda2a357a9a4b34475a28e65b660b4898a9e3e9bbf0820d43494297edd0',
			),
			'authentication' => array(
				'challenge' => 'f90c612981d84f599438de1a500f76926e92cc84bef8e02c6e23553f00485435',
				'clientDataJSON' => '7b2274797065223a22776562617574686e2e676574222c226368616c6c656e6765223a222d5178684b59485954316d554f4e' .
					'3461554139326b6d36537a49532d2d4f417362694e5650774249564455222c226f726967696e223a2268747470733a2f2f65' .
					'78616d706c652e6f7267222c2263726f73734f726967696e223a66616c73657d',
				'authenticatorData' => 'bfabc37432958b063360d3ad6461c9c4735ae7f8edd46592a5e0f01452b2e4b50100000000',
				'signature' => '304402206172459958fea907b7292b92f555034bfd884895f287a76200c1ba287239137002204727b166147e26a21bbc2921' .
					'd192ebfed569b79438538e5c128b5e28e6926dd7',
			),
		),
	);
}

// Self-signed "packed" attestation certificates (DER, hex), made for the key
// of webauthn_test_attestation_key() except rsa_valid, made for
// webauthn_test_rsa_attestation_key(). Subject C=AA, O=Pinegrap Test,
// OU=Authenticator Attestation, CN=Packed Test and a critical basic
// constraints extension with CA:FALSE, except where the name says otherwise:
// valid_aaguid carries the AAGUID extension 01..10, other_aaguid ff..00,
// critaaguid 01..10 marked critical, no_aaguid none; wrong_ou has the OU
// "Test Department", nocountry no C, ca_true is a CA, nobc has only a key
// usage extension, v1 is a version 1 certificate without extensions.
function webauthn_test_certificates()
{
	return array(
		'valid_aaguid' => '30820223308201c9a00302010202023fd7300a06082a8648ce3d040302305f310b3009060355040613024141311630140603' .
			'55040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f722041747465737461' .
			'74696f6e3114301206035504030c0b5061636b656420546573743020170d3236313031393132323431395a180f3231323630' .
			'3932353132323431395a305f310b300906035504061302414131163014060355040a0c0d50696e6567726170205465737431' .
			'223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b5061636b' .
			'656420546573743059301306072a8648ce3d020106082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d' .
			'958248b9aaf600368ba5cc4c0de06a1e64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30a373' .
			'3071301d0603551d0e0416041432f42e05a2a3416948410c6228db8789c28ee90f301f0603551d2304183016801432f42e05' .
			'a2a3416948410c6228db8789c28ee90f300c0603551d130101ff040230003021060b2b0601040182e51c0101040412041001' .
			'02030405060708090a0b0c0d0e0f10300a06082a8648ce3d04030203480030450221008e377ba012564371b97efe4f4e5019' .
			'95e0bc689fbddc7052cdc4091c112fb002022064b5fa1673deeb4f8cb097c3acdabd6f74d581dbc02ce7b1c078ec8162cc53' .
			'17',
		'other_aaguid' => '30820223308201c9a00302010202020335300a06082a8648ce3d040302305f310b3009060355040613024141311630140603' .
			'55040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f722041747465737461' .
			'74696f6e3114301206035504030c0b5061636b656420546573743020170d3236313031393132323431395a180f3231323630' .
			'3932353132323431395a305f310b300906035504061302414131163014060355040a0c0d50696e6567726170205465737431' .
			'223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b5061636b' .
			'656420546573743059301306072a8648ce3d020106082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d' .
			'958248b9aaf600368ba5cc4c0de06a1e64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30a373' .
			'3071301d0603551d0e0416041432f42e05a2a3416948410c6228db8789c28ee90f301f0603551d2304183016801432f42e05' .
			'a2a3416948410c6228db8789c28ee90f300c0603551d130101ff040230003021060b2b0601040182e51c01010404120410ff' .
			'eeddccbbaa99887766554433221100300a06082a8648ce3d0403020348003045022100d3561573688a52ffde3d5f38a03e97' .
			'8402c06c585ab7d6a8338127d61e6b0a36022062a276bc9f0a9e23293f7065bf5d057218310e97b27b1b0c3994b85bdfc1f0' .
			'85',
		'critaaguid' => '30820226308201cca00302010202027373300a06082a8648ce3d040302305f310b3009060355040613024141311630140603' .
			'55040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f722041747465737461' .
			'74696f6e3114301206035504030c0b5061636b656420546573743020170d3236313031393132353932395a180f3231323630' .
			'3932353132353932395a305f310b300906035504061302414131163014060355040a0c0d50696e6567726170205465737431' .
			'223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b5061636b' .
			'656420546573743059301306072a8648ce3d020106082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d' .
			'958248b9aaf600368ba5cc4c0de06a1e64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30a376' .
			'3074301d0603551d0e0416041432f42e05a2a3416948410c6228db8789c28ee90f301f0603551d2304183016801432f42e05' .
			'a2a3416948410c6228db8789c28ee90f300c0603551d130101ff040230003024060b2b0601040182e51c0101040101ff0412' .
			'04100102030405060708090a0b0c0d0e0f10300a06082a8648ce3d0403020348003045022100c3963981d94f4b7578f2d9ed' .
			'8f6f72c8e85a75ef828cb833bc19020a0b895a77022060f16d4ae91e05f99188d2ad00512b62f3ef82f30635a3551dac3512' .
			'c567ec2b',
		'no_aaguid' => '30820201308201a6a00302010202023fe2300a06082a8648ce3d040302305f310b3009060355040613024141311630140603' .
			'55040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f722041747465737461' .
			'74696f6e3114301206035504030c0b5061636b656420546573743020170d3236313031393132323431395a180f3231323630' .
			'3932353132323431395a305f310b300906035504061302414131163014060355040a0c0d50696e6567726170205465737431' .
			'223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b5061636b' .
			'656420546573743059301306072a8648ce3d020106082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d' .
			'958248b9aaf600368ba5cc4c0de06a1e64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30a350' .
			'304e301d0603551d0e0416041432f42e05a2a3416948410c6228db8789c28ee90f301f0603551d2304183016801432f42e05' .
			'a2a3416948410c6228db8789c28ee90f300c0603551d130101ff04023000300a06082a8648ce3d0403020349003046022100' .
			'9ca72582011e5f943725641b70c1f294c0d43242ce6893e6ba5d6a6041b16c1b02210089d045be654b47db4eb12b6bdbb7e7' .
			'dd26cfd0df947815beea27f2f5a596c6ff',
		'wrong_ou' => '308201ec30820192a00302010202024381300a06082a8648ce3d0403023055310b3009060355040613024141311630140603' .
			'55040a0c0d50696e6567726170205465737431183016060355040b0c0f54657374204465706172746d656e74311430120603' .
			'5504030c0b5061636b656420546573743020170d3236313031393132323431395a180f32313236303932353132323431395a' .
			'3055310b300906035504061302414131163014060355040a0c0d50696e6567726170205465737431183016060355040b0c0f' .
			'54657374204465706172746d656e743114301206035504030c0b5061636b656420546573743059301306072a8648ce3d0201' .
			'06082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d958248b9aaf600368ba5cc4c0de06a1e64a70abe' .
			'292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30a350304e301d0603551d0e0416041432f42e05a2a341' .
			'6948410c6228db8789c28ee90f301f0603551d2304183016801432f42e05a2a3416948410c6228db8789c28ee90f300c0603' .
			'551d130101ff04023000300a06082a8648ce3d0403020348003045022058df1dc672e6dcfc859ffff6d170b46695a634a476' .
			'4d4e812a254ae85d5ba514022100dbf737141fb29f45d059c6e8ef82195fa74ad21fe584cb519bc7b15e019d7564',
		'nocountry' => '308201e63082018ca00302010202027272300a06082a8648ce3d040302305231163014060355040a0c0d50696e6567726170' .
			'205465737431223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e311430120603550403' .
			'0c0b5061636b656420546573743020170d3236313031393132353932395a180f32313236303932353132353932395a305231' .
			'163014060355040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f72204174' .
			'746573746174696f6e3114301206035504030c0b5061636b656420546573743059301306072a8648ce3d020106082a8648ce' .
			'3d03010703420004ed24f6d7bed0556defc411863dc2814d958248b9aaf600368ba5cc4c0de06a1e64a70abe292d0a1acaf7' .
			'e5966e0f11e83899600d3491d0e35856ab84ff947f30a350304e301d0603551d0e0416041432f42e05a2a3416948410c6228' .
			'db8789c28ee90f301f0603551d2304183016801432f42e05a2a3416948410c6228db8789c28ee90f300c0603551d130101ff' .
			'04023000300a06082a8648ce3d0403020348003045022100bb8303e5c69cd38364d884ccac1c7d7ec80c30866e867728f9c0' .
			'13ced1d1251e02200b33e8e0234b1950427197c120ec2b00a1ae69fe0ff2d329aa3da6425e340fc0',
		'ca_true' => '30820204308201a9a00302010202025cad300a06082a8648ce3d040302305f310b3009060355040613024141311630140603' .
			'55040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f722041747465737461' .
			'74696f6e3114301206035504030c0b5061636b656420546573743020170d3236313031393132323431395a180f3231323630' .
			'3932353132323431395a305f310b300906035504061302414131163014060355040a0c0d50696e6567726170205465737431' .
			'223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b5061636b' .
			'656420546573743059301306072a8648ce3d020106082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d' .
			'958248b9aaf600368ba5cc4c0de06a1e64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30a353' .
			'3051301d0603551d0e0416041432f42e05a2a3416948410c6228db8789c28ee90f301f0603551d2304183016801432f42e05' .
			'a2a3416948410c6228db8789c28ee90f300f0603551d130101ff040530030101ff300a06082a8648ce3d0403020349003046' .
			'022100cbd987a926e6419116aae835b1fb5afb624a11f2650aa86a057bde8466fd1866022100f6486a6ea3dcadecd15ff0fb' .
			'8653455ef573d0725fe5bf3aec43151cbcf3316c',
		'nobc' => '308201e130820187a00302010202027171300a06082a8648ce3d040302305f310b3009060355040613024141311630140603' .
			'55040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f722041747465737461' .
			'74696f6e3114301206035504030c0b5061636b656420546573743020170d3236313031393132353933385a180f3231323630' .
			'3932353132353933385a305f310b300906035504061302414131163014060355040a0c0d50696e6567726170205465737431' .
			'223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b5061636b' .
			'656420546573743059301306072a8648ce3d020106082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d' .
			'958248b9aaf600368ba5cc4c0de06a1e64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30a331' .
			'302f300e0603551d0f0101ff040403020780301d0603551d0e0416041432f42e05a2a3416948410c6228db8789c28ee90f30' .
			'0a06082a8648ce3d0403020348003045022100804141fecea483de21f84b036a0b355329823f426ee31c190d9449a493345f' .
			'41022077b514138911847f6f67ab4aba32f9f45373912749adc9b5ecd2114faaa6e60a',
		'v1' => '308201a93082014f02026161300a06082a8648ce3d040302305f310b300906035504061302414131163014060355040a0c0d' .
			'50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e31' .
			'14301206035504030c0b5061636b656420546573743020170d3236313031393132353932395a180f32313236303932353132' .
			'353932395a305f310b300906035504061302414131163014060355040a0c0d50696e65677261702054657374312230200603' .
			'55040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b5061636b6564205465' .
			'73743059301306072a8648ce3d020106082a8648ce3d03010703420004ed24f6d7bed0556defc411863dc2814d958248b9aa' .
			'f600368ba5cc4c0de06a1e64a70abe292d0a1acaf7e5966e0f11e83899600d3491d0e35856ab84ff947f30300a06082a8648' .
			'ce3d040302034800304502207c2e8557c3d158d4bc0dedc971f26cb2ef0f28e41d7bd62d5f587161cab03d42022100822a4f' .
			'e9d01cb8ae95234f1a25d02382cce379c66252331aaa66c9504b7d3c56',
		'rsa_valid' => '3082038c30820274a00302010202025151300d06092a864886f70d01010b0500305f310b3009060355040613024141311630' .
			'14060355040a0c0d50696e6567726170205465737431223020060355040b0c1941757468656e74696361746f722041747465' .
			'73746174696f6e3114301206035504030c0b5061636b656420546573743020170d3236313031393132353932395a180f3231' .
			'3236303932353132353932395a305f310b300906035504061302414131163014060355040a0c0d50696e6567726170205465' .
			'737431223020060355040b0c1941757468656e74696361746f72204174746573746174696f6e3114301206035504030c0b50' .
			'61636b6564205465737430820122300d06092a864886f70d01010105000382010f003082010a0282010100ce31b24666f853' .
			'775bc7a8ae2e596d2e4aa18a1a826363ec48417ba25e2cf5c84b619bf8496f94f9630750e69b06d60f3e5294bd4e30bcd2b2' .
			'96895085ac3806ace8f992680f8d4ea9234f4fd8376e710bd3590298f5ef85974c3df4471574ae0630ea81c29baae97680a0' .
			'3b9c37fbc26f02d6c02dc92a096f68b04a1381dcbdd5f69e2ab402448e5a06bb758d29fc335afc061c245a38f8a14dbbb514' .
			'afe998b240bebdda5b5fe92e73af38289f8b304c5c7de6af1c83641e1a82e5b7231b130cf7f415b9e977083bcfeb8e543ec0' .
			'79fe4abb4667a2f1d8d1f9b106259bbab3896899dbf5dbd379eeb33079c94750db78b0e03bd63f87e9b5ad51362d9a1ec702' .
			'03010001a350304e301d0603551d0e041604143d5ae6c494fc54172ad11960ba982d9cf9b9ed0d301f0603551d2304183016' .
			'80143d5ae6c494fc54172ad11960ba982d9cf9b9ed0d300c0603551d130101ff04023000300d06092a864886f70d01010b05' .
			'0003820101006e37aeec296fa58d33e91622cfee3baac3b30b5dce521aec6a1781a3da931d52bdeb1607af5231a8d05afa4c' .
			'928b09356461a1dd7822e3b61e923dd1b0dfc99fd3373fdffded35ca1c6844b6251295b4a52fbec3def31abd26bde87529c9' .
			'1d7b9ceb5077f371dadb433e3131866594a16078960ae44485f6d0f377ef61420b9c19bf0f039bbe3e3fe4196518fe1a5d81' .
			'43bcf01a83341482276fa45e8d6d02655c46d2c3b37d51cf138646492e6e967281ed216d1d8e00bf11201fc22d7b72791cf2' .
			'a3f803d65b8884e3a50fcca3d9c60ab74bc02cc282c7e3ba2577eba80e38e5759ebdb2082c389349db5624fbbbacb0a03993' .
			'f0454a5241e962b86704d11e',
	);
}

// The RSA 2048 private key of the rsa_valid certificate, PKCS #8 DER as hex;
// a test key that signs nothing else.
function webauthn_test_rsa_attestation_key_der()
{
	return '308204bc020100300d06092a864886f70d0101010500048204a6308204a20201000282010100ce31b24666f853775bc7a8ae' .
		'2e596d2e4aa18a1a826363ec48417ba25e2cf5c84b619bf8496f94f9630750e69b06d60f3e5294bd4e30bcd2b296895085ac' .
		'3806ace8f992680f8d4ea9234f4fd8376e710bd3590298f5ef85974c3df4471574ae0630ea81c29baae97680a03b9c37fbc2' .
		'6f02d6c02dc92a096f68b04a1381dcbdd5f69e2ab402448e5a06bb758d29fc335afc061c245a38f8a14dbbb514afe998b240' .
		'bebdda5b5fe92e73af38289f8b304c5c7de6af1c83641e1a82e5b7231b130cf7f415b9e977083bcfeb8e543ec079fe4abb46' .
		'67a2f1d8d1f9b106259bbab3896899dbf5dbd379eeb33079c94750db78b0e03bd63f87e9b5ad51362d9a1ec7020301000102' .
		'8201001cdaa17aee9fe3763976b8291fbd4de50376958fde1d3077792908b710fcb3ac42b8370e48a07fb562613cb5f88dbd' .
		'58c6bce1a9980e83edec25dab354f163a9b37aaaaa0d3bd7e7610ddae46e7a5b4f207b83e2342b7e483082efcc8641ff60f6' .
		'e6d37a33147b974e08f3e3439a25f5f078f23a546de91f06e31c80513883fe20379830404f914120faff9b4b7682ed2f7e01' .
		'b5c97ad78b92760a66e5c11d406e4ef9d593f2062765f1dec53ad8f3acbcda082c8d02d55d449ef60c131d17b126b75dbd15' .
		'd2f911c0e230a3a5428dc9302d09687fec1044cd7bfaf9623761ffd18e4af4cf013c6b6e61543dc00c650d6f0c63e34a250a' .
		'68163d2125444f040902818100f8f255df07e9b30dc74dc4f0574a9b06c195ea9f3ff6779edcfcc1ea3fd7e60cb7aa6fcbbc' .
		'3cb833e842375619cd1a516ca0513f6711332e866c32ad33c08c7085b86f2e942ee074ab954c407704a2f06a1d1d8b84f3e1' .
		'076d908146e4b4dd02d38466788f7c024934f53aaf07909825795503d7fe077857f6a5154651be266d02818100d409448810' .
		'7ae4d590a01a61bace08b9143e4bd73ea56fb7e49885fa8119eb37e9f78774d1488d26683dacb8ba523065d2e6a8aff520ef' .
		'adfc800a84afae11f6bbd2e48d46b8bfb395082397f370e8d09f07cddde88ed8f731dd52fe0e0c841bbed945a429617aeace' .
		'6edcb9143945d96aeaabccf0b0bef32d1e86d8e699298302818023834676b3854612b01ccafc52278567f853af8d9a2d49b1' .
		'32eb27aa8802ff814eee1456a6faae785128a90632560cec12a7e80f385fbec26350aa06053cf18bd7b790bcbf4cd49e301f' .
		'f329bb10c141b49184b67377eae1096334dded2053eae44b9de692e968d612a4ed032cfc168839ffc3b59b3a6b9378cee1f3' .
		'0f14261d0281803a9a291fb5f65142994b369f6622c594697ce23726305115310fda8fa698c442dade37c8d882c5c824ad4f' .
		'0c6b2a8607ce67439e0610c94d7be8e37f5292b89249ccfaaff528e08ac098d4458dd9d9eeb6d55057918bd1795885c40745' .
		'58be3670e3d9f48a1f4ffa8f7d6271a09e47a8d47cf1689de92b8d481e14c4e3ced7030281801ac3025dfcc297aee410158f' .
		'a77d0ab02771bae65e7268f88652059d6b1629e8ef365ded299e8ea2f3d9067209505bc213412cde905e81d14d4c495e3d4d' .
		'e3e550cdda742fa86827c4c52cabb60d449c8e0407f97e77282527d04a15e7a252a50b8fb0b09e9ac8efaaeafc73a28ec9eb' .
		'a4a6f07c8a497b9e2ca568a7adba40f6';
}
