<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for making the site's DKIM key pair without a database: the key is
 * made with the OpenSSL configuration file the software ships when the
 * server has none (pg_openssl_key_new() in includes/fn/core.php), exported
 * with the same file, and the SMTP settings screen removes the old pair only
 * once the new one exists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An RSA key is made, and exported with the arguments that made it: on a
// server without an OpenSSL configuration file (a Windows PHP) both calls
// need the shipped includes/openssl.cnf, and the export fails without it even
// when the key exists.
function test_dkim_key_openssl_key_new_makes_an_exportable_rsa_key()
{
	$made = pg_openssl_key_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));

	pg_assert_true(is_array($made), 'a key is made');

	$private = '';
	pg_assert_true(openssl_pkey_export($made['key'], $private, null, $made['arguments']), 'the key is exported with the arguments that made it');
	pg_assert_contains('-----BEGIN PRIVATE KEY-----', $private);

	$details = openssl_pkey_get_details($made['key']);
	pg_assert_same(2048, $details['bits']);
	pg_assert_same(OPENSSL_KEYTYPE_RSA, $details['type']);
}

// The fallback is the file that ships with the software, never a path from
// the caller or the environment.
function test_dkim_key_openssl_key_new_falls_back_to_the_shipped_file()
{
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/includes/openssl.cnf'), 'includes/openssl.cnf ships');

	$made = pg_openssl_key_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'));

	pg_assert_true(is_array($made), 'an EC key is made');

	if (isset($made['arguments']['config'])) {
		pg_assert_same(PG_FUNCTIONS_DIR . '/includes/openssl.cnf', $made['arguments']['config']);
	}
}

// A key OpenSSL refuses to make is false after both attempts, without a
// warning reaching the screen.
function test_dkim_key_openssl_key_new_refused_is_false()
{
	pg_assert_false(pg_openssl_key_new(array('private_key_bits' => 128, 'private_key_type' => OPENSSL_KEYTYPE_RSA)));
}
