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

	if (!is_array($made)) {
		return;
	}

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

// The TXT record is the public key's base64 body on one line, whatever line
// ends the PEM was written with.
function test_dkim_key_txt_record_is_the_public_key_body()
{
	$pem = "-----BEGIN PUBLIC KEY-----\r\nMIIBIjANBgkqhkiG9w0BAQEF\r\nAAOCAQ8AMIIBCgKCAQEA\r\n-----END PUBLIC KEY-----\r\n";

	pg_assert_same(
		'mail._domainkey.example.com IN TXT "v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA"',
		pg_dkim_txt_record('mail', 'example.com', $pem)
	);
}

// The pair the screen writes: a private PEM the mailer can sign with (it
// only reads the file, which needs no configuration file), the public PEM
// that verifies it, and the TXT record that carries that public key.
function test_dkim_key_pair_signs_and_verifies()
{
	$pair = pg_dkim_key_pair('pinegrap', 'example.com');

	pg_assert_true(is_array($pair), 'a pair is made');

	if (!is_array($pair)) {
		return;
	}

	pg_assert_contains('-----BEGIN PRIVATE KEY-----', $pair['private']);
	pg_assert_contains('-----BEGIN PUBLIC KEY-----', $pair['public']);

	$private = openssl_pkey_get_private($pair['private']);
	$details = openssl_pkey_get_details($private);
	pg_assert_same(2048, $details['bits']);
	pg_assert_same($details['key'], $pair['public']);

	$signature = '';
	pg_assert_true(openssl_sign('DKIM test', $signature, $private, OPENSSL_ALGO_SHA256), 'the private key signs');
	pg_assert_same(1, openssl_verify('DKIM test', $signature, $pair['public'], OPENSSL_ALGO_SHA256));

	pg_assert_same(pg_dkim_txt_record('pinegrap', 'example.com', $pair['public']), $pair['txt_record']);
	pg_assert_same(0, strpos($pair['txt_record'], 'pinegrap._domainkey.example.com IN TXT "v=DKIM1; k=rsa; p=MII'));
}

// The screen makes the new pair before it deletes the old files and records,
// and warns instead when no pair could be made. Before, it deleted first and
// called openssl_pkey_new() without the shipped configuration file, so a
// server without one lost its pair and showed nothing.
function test_dkim_key_screen_makes_the_pair_before_removing_the_old_one()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/smtp_settings.php');

	$make = strpos($screen, 'pg_dkim_key_pair($selector, $domain)');
	$warning = strpos($screen, "lang('The DKIM key could not be generated on this server.");
	$delete = strpos($screen, "DELETE FROM files WHERE name IN ('dkim.key','dkim.pub')");
	$unlink = strpos($screen, "unlink(FILE_DIRECTORY_PATH . '/dkim.key')");

	pg_assert_true($make !== false, 'the screen makes the pair with pg_dkim_key_pair()');
	pg_assert_true(($warning !== false) && ($warning > $make) && ($warning < $delete), 'the failure warns before anything is deleted');
	pg_assert_true(($delete !== false) && ($delete > $make), 'the old records are deleted after the pair is made');
	pg_assert_true(($unlink !== false) && ($unlink > $make), 'the old files are deleted after the pair is made');
	pg_assert_false(strpos($screen, 'openssl_pkey_new('), 'the screen does not make keys itself');
}

// Web push makes its VAPID key the same way.
function test_dkim_key_push_vapid_key_uses_the_helper()
{
	$push = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/push.php');

	pg_assert_contains('pg_openssl_key_new(', $push);
	pg_assert_false(strpos($push, 'openssl_pkey_new('), 'push.php does not make keys itself');
	pg_assert_contains("openssl_pkey_export(\$key, \$private, null, \$made['arguments'])", $push);
}
