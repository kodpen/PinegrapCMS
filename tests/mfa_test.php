<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the two-step sign-in primitives in includes/fn/mfa.php: base32
 * (RFC 4648), TOTP (RFC 6238 / RFC 4226), the key URI and recovery codes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The SHA1 seed of the RFC 6238 Appendix B test vectors.
function mfa_test_rfc_secret()
{
	return '12345678901234567890';
}

// RFC 4648 section 10 test vectors, encoded with padding.
function test_mfa_base32_encode_rfc4648_vectors()
{
	$vectors = array(
		'' => '',
		'f' => 'MY======',
		'fo' => 'MZXQ====',
		'foo' => 'MZXW6===',
		'foob' => 'MZXW6YQ=',
		'fooba' => 'MZXW6YTB',
		'foobar' => 'MZXW6YTBOI======',
	);

	foreach ($vectors as $plain => $encoded) {
		pg_assert_same($encoded, pg_base32_encode((string) $plain), var_export($plain, true));
	}
}

// Every RFC 4648 vector decodes back to its input, and random bytes survive
// the round trip.
function test_mfa_base32_decode_round_trip()
{
	foreach (array('', 'f', 'fo', 'foo', 'foob', 'fooba', 'foobar') as $plain) {
		pg_assert_same($plain, pg_base32_decode(pg_base32_encode($plain)), var_export($plain, true));
	}

	$bytes = random_bytes(37);
	pg_assert_same($bytes, pg_base32_decode(pg_base32_encode($bytes)), 'random bytes');
}

// A hand-typed key: lower case, spaces, no padding - decodes the same.
function test_mfa_base32_decode_is_tolerant()
{
	pg_assert_same('foobar', pg_base32_decode('mzxw 6ytb oi'));
	pg_assert_same('foobar', pg_base32_decode('MZXW6YTBOI'));
	pg_assert_same('foob', pg_base32_decode(" mzxw6yq\t"));
}

// A character outside the alphabet rejects the whole input.
function test_mfa_base32_decode_rejects_invalid_character()
{
	pg_assert_same('', pg_base32_decode('MZXW1'));
	pg_assert_same('', pg_base32_decode('MZ=XW6'), 'padding inside');
	pg_assert_same('', pg_base32_decode('MZXW-6YTB'), 'separator');
}

// RFC 6238 Appendix B, SHA1, eight digits.
function test_mfa_totp_code_rfc6238_vectors()
{
	$vectors = array(
		59 => '94287082',
		1111111109 => '07081804',
		1111111111 => '14050471',
		1234567890 => '89005924',
		2000000000 => '69279037',
		20000000000 => '65353130',
	);

	foreach ($vectors as $time => $code) {
		pg_assert_same($code, pg_totp_code(mfa_test_rfc_secret(), pg_totp_step($time), 8), 'T=' . $time);
	}
}

// The six-digit code is the last six digits of the same value.
function test_mfa_totp_code_six_digits()
{
	pg_assert_same(1, pg_totp_step(59));
	pg_assert_same('287082', pg_totp_code(mfa_test_rfc_secret(), 1));
	pg_assert_same('081804', pg_totp_code(mfa_test_rfc_secret(), pg_totp_step(1111111109)), 'leading zero kept');
}

// The step is the whole number of 30-second periods.
function test_mfa_totp_step_floors()
{
	pg_assert_same(0, pg_totp_step(29));
	pg_assert_same(1, pg_totp_step(30));
	pg_assert_same(37037036, pg_totp_step(1111111109));
	pg_assert_same(2, pg_totp_step(59, 20), 'custom period');
}

// The current code is accepted and the accepted step is returned.
function test_mfa_totp_verify_accepts_current_code()
{
	$secret = mfa_test_rfc_secret();
	$now = 1111111109;
	$step = pg_totp_step($now);

	pg_assert_same($step, pg_totp_verify($secret, pg_totp_code($secret, $step), $now, 0));
}

// One step of drift either way is accepted; two is not.
function test_mfa_totp_verify_window()
{
	$secret = mfa_test_rfc_secret();
	$now = 1111111109;
	$step = pg_totp_step($now);

	pg_assert_same($step - 1, pg_totp_verify($secret, pg_totp_code($secret, $step - 1), $now, 0), '-1');
	pg_assert_same($step + 1, pg_totp_verify($secret, pg_totp_code($secret, $step + 1), $now, 0), '+1');
	pg_assert_false(pg_totp_verify($secret, pg_totp_code($secret, $step - 2), $now, 0), '-2');
	pg_assert_false(pg_totp_verify($secret, pg_totp_code($secret, $step + 2), $now, 0), '+2');
}

// A code whose step was already accepted is refused, as is an earlier step
// once a later one was accepted.
function test_mfa_totp_verify_refuses_replay()
{
	$secret = mfa_test_rfc_secret();
	$now = 1111111109;
	$step = pg_totp_step($now);
	$code = pg_totp_code($secret, $step);

	$accepted = pg_totp_verify($secret, $code, $now, 0);
	pg_assert_same($step, $accepted);
	pg_assert_false(pg_totp_verify($secret, $code, $now, $accepted), 'same code again');
	pg_assert_false(pg_totp_verify($secret, pg_totp_code($secret, $step - 1), $now, $step), 'earlier window');
}

// Only exactly six ASCII digits are checked at all.
function test_mfa_totp_verify_refuses_malformed_code()
{
	$secret = mfa_test_rfc_secret();
	$now = 1111111109;
	$code = pg_totp_code($secret, pg_totp_step($now));

	pg_assert_false(pg_totp_verify($secret, substr($code, 1), $now, 0), 'five digits');
	pg_assert_false(pg_totp_verify($secret, '12a456', $now, 0), 'letter');
	pg_assert_false(pg_totp_verify($secret, $code . "\n", $now, 0), 'trailing newline');
	pg_assert_false(pg_totp_verify($secret, pg_totp_code($secret, pg_totp_step($now), 8), $now, 0), 'eight digits');
}

// A wrong code is refused, and so is any code against an empty key.
function test_mfa_totp_verify_refuses_wrong_code()
{
	$secret = mfa_test_rfc_secret();
	$now = 1111111109;
	$step = pg_totp_step($now);
	$good = pg_totp_code($secret, $step);
	$wrong = str_pad((string) (((int) $good + 1) % 1000000), 6, '0', STR_PAD_LEFT);

	pg_assert_false(pg_totp_verify($secret, $wrong, $now, 0));
	pg_assert_false(pg_totp_verify('', pg_totp_code('', $step), $now, 0), 'empty key');
}

// A new secret is 20 random bytes.
function test_mfa_totp_secret_is_twenty_random_bytes()
{
	$first = pg_totp_secret();

	pg_assert_same(20, strlen($first));
	pg_assert_false($first === pg_totp_secret());
}

// Ten distinct codes in the XXXX-XXXX shape, without I, O, 0 or 1.
function test_mfa_recovery_codes_shape()
{
	$codes = pg_mfa_recovery_codes(10);

	pg_assert_same(10, count($codes));
	pg_assert_same(10, count(array_unique($codes)), 'distinct');

	foreach ($codes as $code) {
		pg_assert_same(1, preg_match('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/D', $code), $code);
	}
}

// Case, separator and spaces do not matter; the hash is SHA-256 hex.
function test_mfa_recovery_normalize_and_hash()
{
	pg_assert_same('AB12CD34', pg_mfa_recovery_normalize('ab12-cd34 '));
	pg_assert_same(1, preg_match('/^[0-9a-f]{64}$/D', pg_mfa_recovery_hash('ABCD-EFGH')));
	pg_assert_same(pg_mfa_recovery_hash('ABCDEFGH'), pg_mfa_recovery_hash('abcd-efgh'));
	pg_assert_same(hash('sha256', 'ABCDEFGH'), pg_mfa_recovery_hash('ABCD EFGH'));
}

// The key URI: issuer and account percent-encoded, unpadded secret, the
// defaults spelled out.
function test_mfa_totp_uri()
{
	$uri = pg_totp_uri('My Site', 'ayşe@example.com', mfa_test_rfc_secret());

	pg_assert_same(
		'otpauth://totp/My%20Site:ay%C5%9Fe%40example.com?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'
			. '&issuer=My%20Site&algorithm=SHA1&digits=6&period=30',
		$uri);

	// A key whose base32 form is padded loses the padding in the URI.
	parse_str((string) parse_url(pg_totp_uri('X', 'y', 'foo'), PHP_URL_QUERY), $query);
	pg_assert_same('MZXW6', $query['secret'], 'no padding');
}

// The key for typing: unpadded base32 in groups of four, read back as is.
function test_mfa_format_secret_groups_of_four()
{
	pg_assert_same('GEZD GNBV GY3T QOJQ GEZD GNBV GY3T QOJQ', pg_mfa_format_secret(mfa_test_rfc_secret()));
	pg_assert_same('MZXW 6', pg_mfa_format_secret('foo'));
	pg_assert_same('', pg_mfa_format_secret(''));
	pg_assert_same(mfa_test_rfc_secret(), pg_base32_decode(pg_mfa_format_secret(mfa_test_rfc_secret())));
}
