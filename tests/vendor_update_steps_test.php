<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checks a signed software update passes before its package is
 * extracted (includes/fn/update.php): pg_vendor_update_steps() against every
 * case of tests/fixtures/vendor_api/update_steps.json that passes the check
 * step, a P-256 pair made at test time for the signature, a ZIP built here
 * for the package facts, and a package swapped between the download and the
 * replace step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function vendor_update_steps_test_vectors()
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/vendor_api/update_steps.json'), true);
}

function vendor_update_steps_test_set(&$array, $path, $value)
{
	$node = &$array;

	foreach (explode('.', $path) as $key) {
		$node = &$node[$key];
	}

	$node = $value;
	unset($node);
}

// The pin the check step made of the vector state's answer.
function vendor_update_steps_test_pin($release, $mode = 'update')
{
	return array(
		'mode'            => $mode,
		'version'         => $release['version'],
		'channel'         => $release['channel'],
		'build'           => $release['build'],
		'package_url'     => $release['update']['url'],
		'size'            => $release['update']['size'],
		'sha256'          => $release['update']['sha256'],
		'manifest_sha256' => $release['manifest_sha256'],
		'sig_key_id'      => $release['sig_key_id'],
		'manifest_url'    => $release['manifest_url'],
		'signature_url'   => $release['signature_url'],
		'package_file'    => 'vendor_update_0123456789abcdef.zip',
	);
}

function vendor_update_steps_test_site($state)
{
	return array(
		'installed_version'     => $state['installed_version'],
		'installed_build'       => $state['installed_build'],
		'update_channel'        => $state['update_channel'],
		'api_base'              => $state['api_base'],
		'openssl_verify_exists' => $state['openssl_verify_exists'],
	);
}

function vendor_update_steps_test_keys($state)
{
	return array_fill_keys($state['embedded_key_ids'], "-----BEGIN PUBLIC KEY-----\n-----END PUBLIC KEY-----\n");
}

// A ZIP shaped like the update package: the codebase under pinegrap/ with
// its versions.php listing $versions.
function vendor_update_steps_test_zip($path, $versions, $extra = '')
{
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	$zip->addFromString('pinegrap/includes/migrations/versions.php', "<?php\nreturn array(\n    '" . implode("',\n    '", $versions) . "',\n);\n");
	$zip->addFromString('pinegrap/index.php', "<?php\n// " . $extra . "\n");
	$zip->close();
}

function vendor_update_steps_test_dir()
{
	$dir = sys_get_temp_dir() . '/pg_vendor_update_' . bin2hex(random_bytes(4));
	mkdir($dir);

	return $dir;
}

function vendor_update_steps_test_rmdir($dir)
{
	foreach ((array) glob($dir . '/*') as $file) {
		@unlink($file);
	}

	@rmdir($dir);
}

// Every case that passes the check step stops where the vectors say, with
// their outcome and reason.
function test_vendor_update_steps_follow_the_vectors()
{
	$vectors = vendor_update_steps_test_vectors();
	$ran = 0;

	foreach ($vectors['cases'] as $case) {
		if ($case['expect']['stopped_at'] === 'check') {
			continue;
		}

		$state = $vectors['baseline'];

		foreach ($case['set'] as $path => $value) {
			vendor_update_steps_test_set($state, $path, $value);
		}

		$result = pg_vendor_update_steps(vendor_update_steps_test_pin($state['answer']), vendor_update_steps_test_site($state), vendor_update_steps_test_keys($state), $state['observed']);

		pg_assert_same($case['expect']['outcome'], $result['outcome'], $case['id']);
		pg_assert_same($case['expect']['stopped_at'], $result['stopped_at'], $case['id'] . ' step');
		pg_assert_same($case['expect']['reason'], $result['reason'], $case['id'] . ' reason');
		$ran++;
	}

	pg_assert_true($ran >= 20, 'the vectors are read');
}

// The steps ask for each observation in turn, so a run fetches nothing a
// check before it has refused.
function test_vendor_update_steps_ask_for_each_observation_in_turn()
{
	$state = vendor_update_steps_test_vectors()['baseline'];
	$pin = vendor_update_steps_test_pin($state['answer']);
	$site = vendor_update_steps_test_site($state);
	$keys = vendor_update_steps_test_keys($state);
	$observed = $state['observed'];

	$expect = array(
		array(array(), 'signature'),
		array(array('openssl_verify_result' => 1), 'manifest'),
		array(array('openssl_verify_result' => 1, 'manifest_sha256' => $observed['manifest_sha256'], 'manifest' => $observed['manifest']), 'download'),
		array(array('openssl_verify_result' => 1, 'manifest_sha256' => $observed['manifest_sha256'], 'manifest' => $observed['manifest'], 'download' => $observed['download']), 'package'),
	);

	foreach ($expect as $row) {
		$result = pg_vendor_update_steps($pin, $site, $keys, $row[0]);

		pg_assert_same(array('outcome' => 'pending', 'stopped_at' => $row[1], 'reason' => null), $result, 'waits for ' . $row[1]);
	}

	// The checks before an observation still run without it: no signature
	// is fetched for an unknown key, no package from a foreign host.
	$unknown = $pin;
	$unknown['sig_key_id'] = 'kp-2099z';

	pg_assert_same('unknown_key_id', pg_vendor_update_steps($unknown, $site, $keys, array())['reason']);
	pg_assert_same('unknown_key_id', pg_vendor_update_steps($pin, $site, array(), array())['reason'], 'no keys: every id is unknown');

	$foreign = $pin;
	$foreign['package_url'] = 'https://downloads.example.net/p.zip';
	$partial = $expect[2][0];

	pg_assert_same(array('outcome' => 'refuse', 'stopped_at' => 'download', 'reason' => 'download_refused'), pg_vendor_update_steps($foreign, $site, $keys, $partial));

	// The replace step checks the package again, from the package step on.
	$facts = array('size' => $observed['size'], 'sha256' => $observed['sha256'], 'first_bytes_hex' => $observed['first_bytes_hex'], 'zip_versions_last' => $observed['zip_versions_last']);

	pg_assert_same(array('outcome' => 'extract', 'stopped_at' => null, 'reason' => null), pg_vendor_update_steps($pin, $site, $keys, $facts, 'package'));
}

// Manifest and signature addresses are checked before they are fetched:
// https, the API's host and port, nothing that parses two ways.
function test_vendor_update_steps_address_rules()
{
	$base = 'https://api.kodpen.com/v1';
	$good = array(
		'https://api.kodpen.com/dl/2026.11.1/manifest.json',
		'https://API.kodpen.com/dl/x.zip',
		'https://api.kodpen.com:443/dl/x.zip',
	);
	$bad = array(
		'http://api.kodpen.com/dl/x.zip',
		'https://api.kodpen.com:8443/dl/x.zip',
		'https://user@api.kodpen.com/dl/x.zip',
		'https://user:pass@api.kodpen.com/dl/x.zip',
		'https://api.kodpen.com\\@evil.example/x.zip',
		'https://api.kodpen.com/dl/a b.zip',
		"https://api.kodpen.com/dl/x.zip\n",
		"https://api.kodpen.com/dl/\x01x.zip",
		'https://api.kodpen.com.evil.example/x.zip',
		'//api.kodpen.com/x.zip',
		'',
	);

	foreach ($good as $url) {
		pg_assert_true(pg_vendor_update_url_ok($url, $base), $url);
	}

	foreach ($bad as $url) {
		pg_assert_false(pg_vendor_update_url_ok($url, $base), json_encode($url));
	}

	pg_assert_true(pg_vendor_update_url_ok('https://localhost:8443/dl/x.zip', 'https://localhost:8443/v1'), 'a test server\'s port');
	pg_assert_false(pg_vendor_update_url_ok('https://localhost/dl/x.zip', 'https://localhost:8443/v1'), 'another port');
	pg_assert_false(pg_vendor_update_url_ok('https://api.kodpen.com/dl/x.zip', ''), 'no base');

	$state = vendor_update_steps_test_vectors()['baseline'];
	$pin = vendor_update_steps_test_pin($state['answer']);
	$pin['signature_url'] = 'https://cdn.example.net/manifest.json.sig';

	pg_assert_same(array('outcome' => 'refuse', 'stopped_at' => 'signature', 'reason' => 'download_refused'), pg_vendor_update_steps($pin, vendor_update_steps_test_site($state), vendor_update_steps_test_keys($state), array()));
}

// The manifest's one update artifact maps to the vectors' flat manifest;
// none or two of them, or another schema, is no manifest.
function test_vendor_update_manifest_read()
{
	$artifact = array('kind' => 'update', 'name' => 'pinegrap_software_update.zip', 'size' => 31000000, 'sha256' => str_repeat('AB', 32));
	$full = array('kind' => 'full', 'name' => 'pinegrap.zip', 'size' => 52000000, 'sha256' => str_repeat('cd', 32));
	$manifest = array('schema' => 1, 'version' => '2026.11.1', 'channel' => 'stable', 'artifacts' => array($full, $artifact));

	pg_assert_same(
		array('version' => '2026.11.1', 'channel' => 'stable', 'build' => '', 'update' => array('size' => 31000000, 'sha256' => str_repeat('ab', 32))),
		pg_vendor_manifest_read(json_encode($manifest))
	);

	$beta = $manifest;
	$beta['channel'] = 'beta';
	$beta['build'] = 'bbbbbbb.202611051500';

	pg_assert_same('bbbbbbb.202611051500', pg_vendor_manifest_read(json_encode($beta))['build'], 'a beta build');

	$broken = array(
		'two update artifacts' => array('artifacts', array($artifact, $artifact)),
		'no update artifact'   => array('artifacts', array($full)),
		'another schema'       => array('schema', 2),
		'size as text'         => array('artifacts', array(array('kind' => 'update', 'size' => '31000000', 'sha256' => str_repeat('ab', 32)))),
		'short sha256'         => array('artifacts', array(array('kind' => 'update', 'size' => 1, 'sha256' => 'ab'))),
		'version missing'      => array('version', null),
		'build not a string'   => array('build', 7),
	);

	foreach ($broken as $label => $change) {
		$copy = $manifest;
		$copy[$change[0]] = $change[1];

		pg_assert_same(null, pg_vendor_manifest_read(json_encode($copy)), $label);
	}

	pg_assert_same(null, pg_vendor_manifest_read(''), 'empty');
	pg_assert_same(null, pg_vendor_manifest_read('[1]'), 'a list');
}

// A P-256 pair made here signs a manifest; only the right signature of the
// same bytes gives 1. The signature file may carry blanks and line breaks.
function test_vendor_update_signature_with_a_test_pair()
{
	if (!function_exists('openssl_sign') || !defined('OPENSSL_KEYTYPE_EC')) {
		pg_assert_true(true, 'OpenSSL is not available here');
		return;
	}

	$made = pg_openssl_key_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'));

	pg_assert_true(is_array($made), 'a P-256 pair is made');

	$details = openssl_pkey_get_details($made['key']);
	$public = $details['key'];
	$manifest = json_encode(array('schema' => 1, 'version' => '2026.11.1', 'channel' => 'stable'));

	openssl_sign($manifest, $signature, $made['key'], OPENSSL_ALGO_SHA256);

	$text = chunk_split(base64_encode($signature), 20, "\r\n") . "  \n";

	pg_assert_same(1, pg_vendor_update_verify($manifest, $text, $public), 'verifies');
	pg_assert_same(0, pg_vendor_update_verify($manifest . ' ', $text, $public), 'other bytes');

	$other = pg_openssl_key_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'));
	$other_public = openssl_pkey_get_details($other['key'])['key'];

	pg_assert_same(0, pg_vendor_update_verify($manifest, $text, $other_public), 'another key');
	pg_assert_same(null, pg_vendor_update_verify($manifest, 'not*base64', $public), 'not base64');
	pg_assert_same(null, pg_vendor_update_verify($manifest, '', $public), 'empty');
	pg_assert_true(pg_vendor_update_verify($manifest, $text, 'not a key') !== 1, 'not a key');
}

// The facts of a ZIP built here; a package swapped after the download
// step fails the replace step's check.
function test_vendor_update_package_facts_and_a_swapped_file()
{
	if (!class_exists('ZipArchive')) {
		pg_assert_true(true, 'ZipArchive is not available here');
		return;
	}

	$dir = vendor_update_steps_test_dir();
	$path = $dir . '/vendor_update_0123456789abcdef.zip';

	vendor_update_steps_test_zip($path, array('2026.11.0', '2026.11.1'));

	$facts = pg_vendor_update_package_facts($path);

	pg_assert_same((int) filesize($path), $facts['size'], 'size');
	pg_assert_same(hash_file('sha256', $path), $facts['sha256'], 'sha256');
	pg_assert_same('504b0304', $facts['first_bytes_hex'], 'first bytes');
	pg_assert_same('2026.11.1', $facts['zip_versions_last'], 'the last version');

	$state = vendor_update_steps_test_vectors()['baseline'];
	$pin = vendor_update_steps_test_pin($state['answer']);
	$pin['size'] = $facts['size'];
	$pin['sha256'] = $facts['sha256'];
	$site = vendor_update_steps_test_site($state);
	$keys = vendor_update_steps_test_keys($state);

	pg_assert_same('extract', pg_vendor_update_steps($pin, $site, $keys, $facts, 'package')['outcome'], 'the downloaded package');

	// Another package under the same name before replace.
	vendor_update_steps_test_zip($path, array('2026.11.0', '2026.11.1'), 'changed');

	pg_assert_same(array('outcome' => 'refuse', 'stopped_at' => 'package', 'reason' => 'package_mismatch'), pg_vendor_update_steps($pin, $site, $keys, pg_vendor_update_package_facts($path), 'package'), 'swapped');

	// A package without versions.php.
	$zip = new ZipArchive();
	$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	$zip->addFromString('pinegrap/index.php', '<?php');
	$zip->close();

	pg_assert_same(null, pg_vendor_update_package_facts($path)['zip_versions_last'], 'no versions.php');

	file_put_contents($path, '<html>blocked</html>');
	$facts = pg_vendor_update_package_facts($path);

	pg_assert_same('3c68746d', $facts['first_bytes_hex'], 'an HTML page');
	pg_assert_same(null, $facts['zip_versions_last'], 'not a zip');

	@unlink($path);

	pg_assert_same(null, pg_vendor_update_package_facts($path), 'a missing file');

	vendor_update_steps_test_rmdir($dir);
}

// The package lives only under its own name in data/temp/.
function test_vendor_update_package_path()
{
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/temp/vendor_update_0123456789abcdef.zip', pg_vendor_update_package_path('vendor_update_0123456789abcdef.zip'));

	foreach (array('', '../config.php', 'vendor_update_0123456789ABCDEF.zip', 'vendor_update_0123.zip', 'pinegrap_software_update.zip', null) as $name) {
		pg_assert_same('', pg_vendor_update_package_path($name), json_encode($name));
	}
}

// No key ships before the owner's keys: every key id is unknown.
function test_vendor_update_release_keys_are_empty()
{
	pg_assert_same(array(), pg_vendor_release_keys());
}

// Every refusal has its own text; the security events are the ones a
// complete answer can show.
function test_vendor_update_refusal_texts()
{
	$reasons = array('signature_unverifiable', 'signature_invalid', 'unknown_key_id', 'manifest_mismatch', 'download_refused', 'package_mismatch', 'not_a_zip', 'zip_version_mismatch', 'not_newer');
	$texts = array();

	foreach ($reasons as $reason) {
		$texts[] = pg_vendor_update_refusal_message($reason);
	}

	pg_assert_same(count($reasons), count(array_unique($texts)), 'one text each');
	pg_assert_true(pg_vendor_update_security_event('signature_invalid'), 'a bad signature');
	pg_assert_true(pg_vendor_update_security_event('package_mismatch'), 'another package');
	pg_assert_true(pg_vendor_update_security_event('manifest_mismatch'), 'another manifest');
	pg_assert_false(pg_vendor_update_security_event('signature_unverifiable'), 'a server without OpenSSL');
	pg_assert_false(pg_vendor_update_security_event('not_newer'), 'already installed');
}
