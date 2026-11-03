<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the CA store chooser in includes/fn/update.php: which
 * certificate store an outbound TLS connection is verified against, from the
 * CURL_CA_BUNDLE override, whether PHP has a store of its own, and what the
 * last probe of that store found.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/update.php');

function ca_store_test_choose($override, $shipped, $php_store, $probe)
{
	return pg_ca_bundle_choose($override, $shipped ? '/site/data/cacert.pem' : '', $php_store, $probe);
}

function test_ca_store_override_wins_whatever_the_probe_says()
{
	foreach (array('ok', 'needed', 'unknown', '') as $probe) {
		foreach (array(true, false) as $php_store) {
			$chosen = ca_store_test_choose('/etc/own.pem', true, $php_store, $probe);
			pg_assert_same(array('file' => '/etc/own.pem', 'reason' => 'override'), $chosen, "probe $probe, store " . ($php_store ? 'yes' : 'no'));
		}
	}
}

function test_ca_store_shipped_when_php_has_no_store()
{
	pg_assert_same(array('file' => '/site/data/cacert.pem', 'reason' => 'no_store'), ca_store_test_choose('', true, false, 'ok'));
	pg_assert_same(array('file' => '/site/data/cacert.pem', 'reason' => 'no_store'), ca_store_test_choose('', true, false, ''));
	pg_assert_same(array('file' => '/site/data/cacert.pem', 'reason' => 'no_store'), ca_store_test_choose('', true, false, 'unknown'));
}

function test_ca_store_shipped_when_the_probe_found_the_server_store_failing()
{
	pg_assert_same(array('file' => '/site/data/cacert.pem', 'reason' => 'probe'), ca_store_test_choose('', true, true, 'needed'));
	// Both reasons at once: the missing store is the one that explains it.
	pg_assert_same(array('file' => '/site/data/cacert.pem', 'reason' => 'no_store'), ca_store_test_choose('', true, false, 'needed'));
}

function test_ca_store_server_store_otherwise()
{
	pg_assert_same(array('file' => '', 'reason' => 'server'), ca_store_test_choose('', true, true, 'ok'));
	pg_assert_same(array('file' => '', 'reason' => 'server'), ca_store_test_choose('', true, true, 'unknown'));
	pg_assert_same(array('file' => '', 'reason' => 'server'), ca_store_test_choose('', true, true, ''));
}

function test_ca_store_server_store_when_the_shipped_file_is_missing()
{
	// Nothing else to verify against: cURL's own default, whatever the probe
	// said. The System Status row reports the missing file.
	pg_assert_same(array('file' => '', 'reason' => 'server'), ca_store_test_choose('', false, false, 'needed'));
	pg_assert_same(array('file' => '', 'reason' => 'server'), ca_store_test_choose('', false, true, 'needed'));
	pg_assert_same(array('file' => '/etc/own.pem', 'reason' => 'override'), ca_store_test_choose('/etc/own.pem', false, false, 'needed'));
}

function test_ca_store_php_store_present()
{
	$none = array('default_cert_file' => '/nowhere/cert.pem', 'default_cert_dir' => '/nowhere/certs', 'ini_cafile' => '', 'ini_capath' => '');
	$here = dirname(__DIR__) . '/tests/ca_store_test.php';

	pg_assert_false(pg_ca_bundle_php_store_present('', $none));
	pg_assert_false(pg_ca_bundle_php_store_present('/nowhere/cainfo.pem', $none));
	// curl.cainfo pointing at a file that exists.
	pg_assert_true(pg_ca_bundle_php_store_present($here, $none));
	// OpenSSL's own default file or directory, or its ini settings.
	pg_assert_true(pg_ca_bundle_php_store_present('', array('default_cert_file' => $here) + $none));
	pg_assert_true(pg_ca_bundle_php_store_present('', array('default_cert_dir' => dirname($here)) + $none));
	pg_assert_true(pg_ca_bundle_php_store_present('', array('ini_cafile' => $here) + $none));
	pg_assert_true(pg_ca_bundle_php_store_present('', array('ini_capath' => dirname($here)) + $none));
	// No OpenSSL extension to ask: a store is assumed, the probe decides.
	pg_assert_true(pg_ca_bundle_php_store_present('', false));
}

function test_ca_store_probe_state_from_curl_error()
{
	pg_assert_same('ok', pg_ca_bundle_probe_state_of(0));
	foreach (array(35, 51, 60, 77) as $errno) {
		pg_assert_same('needed', pg_ca_bundle_probe_state_of($errno), "errno $errno");
	}
	// No route, no DNS, a timeout: nothing is known about the store.
	foreach (array(6, 7, 28) as $errno) {
		pg_assert_same('unknown', pg_ca_bundle_probe_state_of($errno), "errno $errno");
	}
}

function test_ca_store_probe_record_decoding()
{
	$empty = array('state' => '', 'checked_at' => 0, 'error' => '');

	pg_assert_same($empty, pg_ca_bundle_probe_decode(false));
	pg_assert_same($empty, pg_ca_bundle_probe_decode(''));
	pg_assert_same($empty, pg_ca_bundle_probe_decode('not json'));
	pg_assert_same($empty, pg_ca_bundle_probe_decode('{"state":"broken","checked_at":5}'));

	pg_assert_same(
		array('state' => 'needed', 'checked_at' => 1700000000, 'error' => '60: peer'),
		pg_ca_bundle_probe_decode('{"state":"needed","checked_at":1700000000,"error":"60: peer"}')
	);
	pg_assert_same(
		array('state' => 'ok', 'checked_at' => 1700000000, 'error' => ''),
		pg_ca_bundle_probe_decode('{"state":"ok","checked_at":"1700000000"}')
	);
}

function test_ca_store_probe_due_once_a_day()
{
	$now = 1700000000;

	pg_assert_true(pg_ca_bundle_probe_due(array('state' => '', 'checked_at' => 0, 'error' => ''), $now));
	pg_assert_true(pg_ca_bundle_probe_due(array('state' => 'ok', 'checked_at' => $now - 86401, 'error' => ''), $now));
	pg_assert_false(pg_ca_bundle_probe_due(array('state' => 'ok', 'checked_at' => $now - 86399, 'error' => ''), $now));
	pg_assert_false(pg_ca_bundle_probe_due(array('state' => 'needed', 'checked_at' => $now - 100, 'error' => ''), $now));
	// A probe that reached nothing is tried again sooner, after an hour.
	pg_assert_true(pg_ca_bundle_probe_due(array('state' => 'unknown', 'checked_at' => $now - 3601, 'error' => ''), $now));
	pg_assert_false(pg_ca_bundle_probe_due(array('state' => 'unknown', 'checked_at' => $now - 3599, 'error' => ''), $now));
}
